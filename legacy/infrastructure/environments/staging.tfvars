# Money Paws Staging Environment Configuration
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

# Environment
environment = "staging"
app_version = "3.1.4"

# Networking
aws_region = "us-west-2"
vpc_cidr   = "10.1.0.0/16"
domain_name = "staging.paws.money"

# Staging database configuration
db_instance_class     = "db.t3.small"
db_allocated_storage  = 20
db_max_allocated_storage = 100
multi_az             = false
backup_retention_period = 7

# Staging Redis configuration
redis_node_type      = "cache.t3.small"
redis_num_nodes      = 1

# ECS Staging configuration
ecs_desired_count    = 2
ecs_min_capacity     = 1
ecs_max_capacity     = 5
ecs_cpu              = 512
ecs_memory           = 1024

# Security settings
enable_deletion_protection = false
enable_detailed_monitoring = false
enable_waf              = false
allowed_cidr_blocks     = ["0.0.0.0/0"]

# Performance settings
enable_auto_scaling     = true
enable_blue_green       = false

# Backup configuration
backup_retention_days   = 7

# Monitoring
alert_email = "staging@paws.money"
