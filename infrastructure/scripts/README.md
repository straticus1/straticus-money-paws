# Money Paws Deployment Scripts

## Overview

This directory contains the core deployment automation scripts for the Money Paws platform. These scripts provide version management, deployment automation, and rollback capabilities across multiple environments (dev, staging, production).

## Scripts

### 1. `deploy.sh` - Main Deployment Script

The primary deployment script that handles the entire deployment pipeline.

**Features:**
- Multi-environment deployment (dev/staging/production)
- Terraform infrastructure deployment
- Ansible application deployment
- Version validation and tracking
- Health checks and post-deployment validation
- Slack notifications
- Dry-run mode for testing

**Usage:**
```bash
# Deploy current version to staging
./deploy.sh -e staging

# Deploy specific version to production
./deploy.sh -e production -v 3.1.4

# Dry run deployment
./deploy.sh -e production -v 3.1.4 -d

# Force deployment (skip version checks)
./deploy.sh -e staging -v 3.1.4 -f

# Skip tests during deployment
./deploy.sh -e staging -t

# Skip infrastructure deployment (Ansible only)
./deploy.sh -e staging --skip-terraform

# Skip application deployment (Terraform only)
./deploy.sh -e staging --skip-ansible
```

**Environment Variables:**
- `AWS_PROFILE` - AWS profile to use (optional)
- `AWS_REGION` - AWS region (default: us-west-2)
- `SLACK_WEBHOOK_URL` - Slack webhook for notifications

### 2. `rollback.sh` - Rollback Management Script

Handles automated rollbacks to previous versions with safety checks.

**Features:**
- Automatic rollback to previous version
- Manual rollback to specific version
- Safety checks and business hours validation
- Emergency mode for critical situations
- Deployment history tracking
- Health verification after rollback

**Usage:**
```bash
# Rollback to previous version
./rollback.sh -e production

# Rollback to specific version
./rollback.sh -e staging -v 3.1.2

# Dry run rollback
./rollback.sh -e production -d

# Emergency rollback (skip confirmations and safety checks)
./rollback.sh -e production --emergency -y
```

### 3. `version.sh` - Version Management Script

Manages application versions and deployment tracking.

**Features:**
- Create and manage versions
- Track deployment status across environments
- Version validation and metadata
- Git integration for commit tracking
- Deployment history

**Usage:**
```bash
# Initialize version file
./version.sh init

# Create new version
./version.sh create 3.1.5 -m "Bug fixes and performance improvements"

# List all versions
./version.sh list

# Show current version
./version.sh current

# Set current version
./version.sh set-current 3.1.5

# Show version details
./version.sh info 3.1.4

# Remove version
./version.sh remove 3.1.3 -f
```

## Version Management

### Version File Structure

The `version.json` file tracks all application versions and their deployment status:

```json
{
  "current_version": "3.1.4",
  "created_at": "2024-12-28T00:00:00Z",
  "created_by": "ryan",
  "versions": {
    "3.1.4": {
      "created_at": "2024-12-28T00:00:00Z",
      "created_by": "ryan",
      "message": "Version description",
      "git_commit": "abc123",
      "git_branch": "main",
      "environments": {
        "dev": {
          "deployed": false,
          "deployed_at": null,
          "deployed_by": null
        },
        "staging": {
          "deployed": true,
          "deployed_at": "2024-12-28T10:00:00Z",
          "deployed_by": "ryan"
        },
        "production": {
          "deployed": true,
          "deployed_at": "2024-12-28T16:00:00Z",
          "deployed_by": "ryan"
        }
      }
    }
  }
}
```

### Version Naming Convention

- **Production versions:** `x.y.z` (semantic versioning)
- **Alpha versions:** `x.y.z-alpha`
- **Beta versions:** `x.y.z-beta`
- **Release candidates:** `x.y.z-rc.n`

## Deployment Workflow

### Standard Deployment Process

1. **Create Version** (if new)
   ```bash
   ./version.sh create 3.1.5 -m "New feature release"
   ```

2. **Deploy to Development**
   ```bash
   ./deploy.sh -e dev -v 3.1.5
   ```

3. **Deploy to Staging**
   ```bash
   ./deploy.sh -e staging -v 3.1.5
   ```

4. **Deploy to Production**
   ```bash
   ./deploy.sh -e production -v 3.1.5
   ```

### Emergency Deployment

For critical hotfixes:

1. **Create hotfix version**
   ```bash
   ./version.sh create 3.1.4.1 -m "Critical security fix"
   ```

2. **Deploy directly to production** (if needed)
   ```bash
   ./deploy.sh -e production -v 3.1.4.1 -f
   ```

### Rollback Process

If issues are detected after deployment:

1. **Standard rollback**
   ```bash
   ./rollback.sh -e production
   ```

2. **Emergency rollback**
   ```bash
   ./rollback.sh -e production --emergency -y
   ```

## Environment Configuration

### Development Environment
- **Purpose:** Feature development and testing
- **Auto-deploy:** Enabled
- **Approval required:** No
- **Domain:** https://dev.paws.money

### Staging Environment
- **Purpose:** Pre-production testing
- **Auto-deploy:** Disabled
- **Approval required:** Yes
- **Domain:** https://staging.paws.money

### Production Environment
- **Purpose:** Live production system
- **Auto-deploy:** Disabled
- **Approval required:** Yes
- **Blue-green deployment:** Enabled
- **Domain:** https://paws.money

## Safety Features

### Pre-deployment Checks
- Version validation and existence verification
- Infrastructure validation (Terraform syntax)
- Application validation (PHP syntax, Composer audit)
- Ansible playbook syntax validation

### Deployment Safety
- Business hours validation for production deployments
- Current version comparison to prevent duplicate deployments
- Health checks with retry logic
- Performance validation (response time monitoring)

### Rollback Safety
- Automatic rollback target selection
- Business hours confirmation for production rollbacks
- Emergency mode for critical situations
- Health verification after rollback

## Monitoring and Notifications

### Health Checks
- **Endpoints:** `/api/health`, `/api/system/status`
- **Retry logic:** 10 attempts with 30-second intervals
- **Performance monitoring:** Response time tracking

### Slack Notifications
Configure `SLACK_WEBHOOK_URL` environment variable to receive:
- Deployment start/success/failure notifications
- Rollback notifications
- Emergency alerts

### AWS Integration
- **SSM Parameter Store:** Deployment tracking and history
- **CloudWatch:** Application and infrastructure monitoring
- **ECR:** Container image storage
- **ECS:** Application deployment

## Troubleshooting

### Common Issues

1. **Version not found**
   ```bash
   # Check available versions
   ./version.sh list
   
   # Create version if needed
   ./version.sh create 3.1.4 -m "Description"
   ```

2. **Deployment failures**
   ```bash
   # Check logs
   tail -f logs/deployment.log
   
   # Validate configuration
   ./deploy.sh -e staging -v 3.1.4 -d
   ```

3. **Health check failures**
   ```bash
   # Manual health check
   curl -f https://staging.paws.money/api/health
   
   # Check application logs
   aws logs tail /money-paws/staging/application --follow
   ```

### Emergency Procedures

1. **Complete system failure**
   ```bash
   # Emergency rollback
   ./rollback.sh -e production --emergency -y
   ```

2. **Database issues**
   ```bash
   # Check database connectivity
   aws rds describe-db-instances
   
   # Review database logs
   aws logs describe-log-groups --log-group-name-prefix /aws/rds
   ```

## Best Practices

### Version Management
- Always create versions before deployment
- Use semantic versioning for production releases
- Include meaningful commit messages
- Tag versions in Git for reference

### Deployment Process
- Always test in development first
- Use staging for pre-production validation
- Schedule production deployments outside business hours
- Monitor deployments closely

### Emergency Response
- Keep rollback procedures readily available
- Test rollback procedures regularly
- Maintain emergency contact information
- Document all emergency procedures

## Integration with CI/CD

These scripts integrate with the GitHub Actions workflow (`.github/workflows/deploy.yml`) to provide automated deployment capabilities triggered by:

- Push to specific branches
- Manual workflow dispatch
- Scheduled deployments
- Tag creation

## Security Considerations

- All secrets are managed through AWS SSM Parameter Store
- No hardcoded credentials in scripts
- Environment-specific configurations
- IAM roles with least privilege access
- Encrypted communication with AWS services

## Support

For issues or questions regarding deployment:

1. Check the troubleshooting section above
2. Review deployment logs in the `logs/` directory
3. Contact the development team
4. Create an issue in the project repository

---

**Note:** Always test deployment procedures in non-production environments before applying to production systems.
