# Money Paws Development Environment Configuration
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

# Environment
environment = "dev"
app_version = "4.0.0-alpha"

# Networking
aws_region = "us-west-2"
vpc_cidr   = "10.2.0.0/16"
domain_name = "dev.paws.money"

# Development database configuration
db_instance_class     = "db.t3.micro"
db_allocated_storage  = 20
db_max_allocated_storage = 50
multi_az             = false
backup_retention_period = 1

# Development Redis configuration
redis_node_type      = "cache.t3.micro"
redis_num_nodes      = 1

# ECS Development configuration
ecs_desired_count    = 1
ecs_min_capacity     = 1
ecs_max_capacity     = 2
ecs_cpu              = 256
ecs_memory           = 512

# Security settings
enable_deletion_protection = false
enable_detailed_monitoring = false
enable_waf              = false
allowed_cidr_blocks     = ["0.0.0.0/0"]

# Performance settings
enable_auto_scaling     = false
enable_blue_green       = false

# Backup configuration
backup_retention_days   = 1

# Monitoring
alert_email = "dev@paws.money"
