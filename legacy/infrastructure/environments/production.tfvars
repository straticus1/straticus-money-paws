# Money Paws Production Environment Configuration
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

# Environment
environment = "production"
app_version = "3.1.4"

# Networking
aws_region = "us-west-2"
vpc_cidr   = "10.0.0.0/16"
domain_name = "paws.money"

# High-availability database configuration
db_instance_class     = "db.r5.large"
db_allocated_storage  = 100
db_max_allocated_storage = 1000
multi_az             = true
backup_retention_period = 30

# Production Redis configuration
redis_node_type      = "cache.r5.large"
redis_num_nodes      = 2

# ECS Production configuration
ecs_desired_count    = 3
ecs_min_capacity     = 2
ecs_max_capacity     = 20
ecs_cpu              = 1024
ecs_memory           = 2048

# Security settings
enable_deletion_protection = true
enable_detailed_monitoring = true
enable_waf              = true
allowed_cidr_blocks     = ["0.0.0.0/0"]

# Performance settings
enable_auto_scaling     = true
enable_blue_green       = true

# Backup configuration
backup_retention_days   = 30

# Monitoring
alert_email = "admin@paws.money"
