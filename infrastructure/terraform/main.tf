terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.1"
    }
  }

  backend "s3" {
    # Backend configuration will be provided via backend config files
  }
}

provider "aws" {
  region = var.aws_region
  
  default_tags {
    tags = {
      Project     = "Money Paws"
      Environment = var.environment
      ManagedBy   = "Terraform"
      Version     = var.app_version
    }
  }
}

# Data sources
data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_caller_identity" "current" {}

# Local values
locals {
  name = "${var.project_name}-${var.environment}"
  
  common_tags = {
    Project     = var.project_name
    Environment = var.environment
    Version     = var.app_version
    ManagedBy   = "Terraform"
  }
  
  azs = slice(data.aws_availability_zones.available.names, 0, 2)
}

# VPC Module
module "vpc" {
  source = "./modules/vpc"
  
  name         = local.name
  cidr         = var.vpc_cidr
  azs          = local.azs
  environment  = var.environment
  project_name = var.project_name
  
  tags = local.common_tags
}

# S3 Module for static assets and backups
module "s3" {
  source = "./modules/s3"
  
  name         = local.name
  environment  = var.environment
  
  tags = local.common_tags
}

# RDS Module for database
module "rds" {
  source = "./modules/rds"
  
  name                = local.name
  environment         = var.environment
  vpc_id              = module.vpc.vpc_id
  private_subnet_ids  = module.vpc.private_subnet_ids
  
  # Database configuration
  instance_class      = var.db_instance_class
  allocated_storage   = var.db_allocated_storage
  max_allocated_storage = var.db_max_allocated_storage
  multi_az           = var.environment == "production"
  backup_retention_period = var.environment == "production" ? 30 : 7
  
  tags = local.common_tags
}

# ElastiCache Redis Module
module "redis" {
  source = "./modules/redis"
  
  name               = local.name
  environment        = var.environment
  vpc_id             = module.vpc.vpc_id
  private_subnet_ids = module.vpc.private_subnet_ids
  
  node_type          = var.redis_node_type
  num_cache_nodes    = var.redis_num_nodes
  
  tags = local.common_tags
}

# Application Load Balancer
module "alb" {
  source = "./modules/alb"
  
  name               = local.name
  environment        = var.environment
  vpc_id             = module.vpc.vpc_id
  public_subnet_ids  = module.vpc.public_subnet_ids
  
  domain_name        = var.domain_name
  certificate_arn    = var.ssl_certificate_arn
  
  tags = local.common_tags
}

# ECS Module for containerized application
module "ecs" {
  source = "./modules/ecs"
  
  name                = local.name
  environment         = var.environment
  vpc_id              = module.vpc.vpc_id
  private_subnet_ids  = module.vpc.private_subnet_ids
  
  # ALB configuration
  target_group_arn    = module.alb.target_group_arn
  alb_security_group_id = module.alb.security_group_id
  
  # Database configuration
  db_host             = module.rds.db_endpoint
  db_name             = module.rds.db_name
  db_username         = module.rds.db_username
  db_password         = module.rds.db_password
  
  # Redis configuration
  redis_host          = module.redis.redis_endpoint
  
  # S3 configuration
  assets_bucket_name  = module.s3.assets_bucket_name
  
  # Application configuration
  app_version         = var.app_version
  desired_count       = var.ecs_desired_count
  min_capacity        = var.ecs_min_capacity
  max_capacity        = var.ecs_max_capacity
  
  tags = local.common_tags
}

# CloudFront CDN
module "cloudfront" {
  source = "./modules/cloudfront"
  
  name              = local.name
  environment       = var.environment
  domain_name       = var.domain_name
  alb_domain_name   = module.alb.dns_name
  assets_bucket_name = module.s3.assets_bucket_name
  
  tags = local.common_tags
}

# Monitoring and Logging
module "monitoring" {
  source = "./modules/monitoring"
  
  name         = local.name
  environment  = var.environment
  
  # ECS monitoring
  ecs_cluster_name = module.ecs.cluster_name
  ecs_service_name = module.ecs.service_name
  
  # RDS monitoring
  db_instance_id = module.rds.db_instance_id
  
  # Redis monitoring
  redis_cluster_id = module.redis.cluster_id
  
  # ALB monitoring
  alb_arn_suffix = module.alb.arn_suffix
  target_group_arn_suffix = module.alb.target_group_arn_suffix
  
  # Notification settings
  alert_email = var.alert_email
  slack_webhook_url = var.slack_webhook_url
  
  tags = local.common_tags
}
