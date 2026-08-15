# VPC Outputs
output "vpc_id" {
  description = "ID of the VPC"
  value       = module.vpc.vpc_id
}

output "public_subnet_ids" {
  description = "List of public subnet IDs"
  value       = module.vpc.public_subnet_ids
}

output "private_subnet_ids" {
  description = "List of private subnet IDs"
  value       = module.vpc.private_subnet_ids
}

# Database Outputs
output "db_endpoint" {
  description = "RDS instance endpoint"
  value       = module.rds.db_endpoint
  sensitive   = true
}

output "db_port" {
  description = "RDS instance port"
  value       = module.rds.db_port
}

output "db_name" {
  description = "Database name"
  value       = module.rds.db_name
}

# Redis Outputs
output "redis_endpoint" {
  description = "Redis cluster endpoint"
  value       = module.redis.redis_endpoint
  sensitive   = true
}

output "redis_port" {
  description = "Redis cluster port"
  value       = module.redis.redis_port
}

# Load Balancer Outputs
output "alb_dns_name" {
  description = "DNS name of the Application Load Balancer"
  value       = module.alb.dns_name
}

output "alb_zone_id" {
  description = "Zone ID of the Application Load Balancer"
  value       = module.alb.zone_id
}

output "alb_arn" {
  description = "ARN of the Application Load Balancer"
  value       = module.alb.arn
}

# ECS Outputs
output "ecs_cluster_name" {
  description = "Name of the ECS cluster"
  value       = module.ecs.cluster_name
}

output "ecs_service_name" {
  description = "Name of the ECS service"
  value       = module.ecs.service_name
}

output "ecs_cluster_arn" {
  description = "ARN of the ECS cluster"
  value       = module.ecs.cluster_arn
}

# S3 Outputs
output "assets_bucket_name" {
  description = "Name of the S3 bucket for assets"
  value       = module.s3.assets_bucket_name
}

output "backups_bucket_name" {
  description = "Name of the S3 bucket for backups"
  value       = module.s3.backups_bucket_name
}

output "logs_bucket_name" {
  description = "Name of the S3 bucket for logs"
  value       = module.s3.logs_bucket_name
}

# CloudFront Outputs
output "cloudfront_distribution_id" {
  description = "ID of the CloudFront distribution"
  value       = module.cloudfront.distribution_id
}

output "cloudfront_domain_name" {
  description = "Domain name of the CloudFront distribution"
  value       = module.cloudfront.domain_name
}

# Application URLs
output "application_url" {
  description = "Main application URL"
  value       = "https://${var.domain_name}"
}

output "api_url" {
  description = "API base URL"
  value       = "https://${var.domain_name}/api"
}

# Monitoring Outputs
output "cloudwatch_log_group_name" {
  description = "Name of the CloudWatch log group"
  value       = module.monitoring.log_group_name
}

output "sns_topic_arn" {
  description = "ARN of the SNS topic for alerts"
  value       = module.monitoring.sns_topic_arn
}

# ECR Repository
output "ecr_repository_url" {
  description = "URL of the ECR repository"
  value       = module.ecs.ecr_repository_url
}

# Security Groups
output "app_security_group_id" {
  description = "Security group ID for the application"
  value       = module.ecs.security_group_id
}

output "db_security_group_id" {
  description = "Security group ID for the database"
  value       = module.rds.security_group_id
}

# Version Information
output "infrastructure_version" {
  description = "Version of the infrastructure"
  value       = var.app_version
}

output "deployment_timestamp" {
  description = "Timestamp of the deployment"
  value       = timestamp()
}
