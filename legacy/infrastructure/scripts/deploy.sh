#!/bin/bash
# Money Paws Deployment Script
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TERRAFORM_DIR="$PROJECT_ROOT/infrastructure/terraform"
ANSIBLE_DIR="$PROJECT_ROOT/infrastructure/ansible"
VERSION_FILE="$PROJECT_ROOT/version.json"

# Default values
ENVIRONMENT=""
VERSION=""
FORCE_DEPLOY=false
DRY_RUN=false
SKIP_TESTS=false
SKIP_TERRAFORM=false
SKIP_ANSIBLE=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Help function
show_help() {
    cat << EOF
Money Paws Deployment Script

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -e, --environment ENV    Target environment (dev|staging|production)
    -v, --version VERSION    Version to deploy (default: from version.json)
    -f, --force             Force deployment even if version exists
    -d, --dry-run           Show what would be deployed without executing
    -t, --skip-tests        Skip running tests
    --skip-terraform        Skip Terraform infrastructure deployment
    --skip-ansible          Skip Ansible application deployment
    -h, --help              Show this help message

EXAMPLES:
    # Deploy to staging with current version
    $0 -e staging
    
    # Deploy specific version to production
    $0 -e production -v 3.1.4
    
    # Dry run deployment
    $0 -e production -v 3.1.4 -d
    
    # Force deployment (skip version checks)
    $0 -e staging -v 3.1.4 -f

ENVIRONMENT VARIABLES:
    AWS_PROFILE             AWS profile to use (optional)
    AWS_REGION              AWS region (default: us-west-2)
    SLACK_WEBHOOK_URL       Slack webhook for notifications
    
EOF
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -e|--environment)
                ENVIRONMENT="$2"
                shift 2
                ;;
            -v|--version)
                VERSION="$2"
                shift 2
                ;;
            -f|--force)
                FORCE_DEPLOY=true
                shift
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -t|--skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            --skip-terraform)
                SKIP_TERRAFORM=true
                shift
                ;;
            --skip-ansible)
                SKIP_ANSIBLE=true
                shift
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

# Validate requirements
validate_requirements() {
    log_info "Validating requirements..."
    
    # Check required tools
    local required_tools=("terraform" "ansible-playbook" "aws" "jq" "curl")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "Required tool not found: $tool"
            exit 1
        fi
    done
    
    # Validate environment
    if [[ ! "$ENVIRONMENT" =~ ^(dev|staging|production)$ ]]; then
        log_error "Invalid environment: $ENVIRONMENT. Must be dev, staging, or production."
        exit 1
    fi
    
    # Load and validate version
    if [[ -z "$VERSION" ]]; then
        if [[ -f "$VERSION_FILE" ]]; then
            VERSION=$(jq -r '.current_version' "$VERSION_FILE")
        else
            log_error "Version file not found: $VERSION_FILE"
            exit 1
        fi
    fi
    
    # Validate version exists in version.json
    if ! jq -e ".versions[\"$VERSION\"]" "$VERSION_FILE" > /dev/null; then
        log_error "Version $VERSION not found in version.json"
        exit 1
    fi
    
    log_success "Requirements validation passed"
}

# Check current deployment status
check_deployment_status() {
    log_info "Checking current deployment status for $ENVIRONMENT..."
    
    local current_version
    current_version=$(aws ssm get-parameter \
        --name "/money-paws/$ENVIRONMENT/last-successful-deployment" \
        --query 'Parameter.Value' \
        --output text 2>/dev/null || echo "none")
    
    if [[ "$current_version" == "$VERSION" ]] && [[ "$FORCE_DEPLOY" != "true" ]]; then
        log_warn "Version $VERSION is already deployed to $ENVIRONMENT"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Deployment cancelled"
            exit 0
        fi
    fi
    
    log_info "Current deployed version: $current_version"
    log_info "Target version: $VERSION"
}

# Run tests
run_tests() {
    if [[ "$SKIP_TESTS" == "true" ]]; then
        log_warn "Skipping tests (--skip-tests specified)"
        return 0
    fi
    
    log_info "Running tests..."
    
    cd "$PROJECT_ROOT/server"
    
    # PHP syntax check
    log_info "Running PHP syntax check..."
    find . -name "*.php" -exec php -l {} \; || {
        log_error "PHP syntax check failed"
        return 1
    }
    
    # Composer audit
    log_info "Running composer security audit..."
    composer audit || log_warn "Composer audit found issues (non-blocking)"
    
    # Terraform validation
    log_info "Validating Terraform configuration..."
    cd "$TERRAFORM_DIR"
    terraform init -backend=false
    terraform validate || {
        log_error "Terraform validation failed"
        return 1
    }
    
    # Ansible syntax check
    log_info "Validating Ansible playbooks..."
    cd "$ANSIBLE_DIR"
    ansible-playbook --syntax-check deploy.yml || {
        log_error "Ansible syntax check failed"
        return 1
    }
    
    log_success "All tests passed"
}

# Deploy infrastructure with Terraform
deploy_infrastructure() {
    if [[ "$SKIP_TERRAFORM" == "true" ]]; then
        log_warn "Skipping Terraform deployment (--skip-terraform specified)"
        return 0
    fi
    
    log_info "Deploying infrastructure with Terraform..."
    
    cd "$TERRAFORM_DIR"
    
    # Initialize Terraform
    log_info "Initializing Terraform..."
    terraform init -backend-config="environments/$ENVIRONMENT-backend.hcl"
    
    # Create terraform plan
    log_info "Creating Terraform plan..."
    local plan_file="tfplan-$ENVIRONMENT-$(date +%s)"
    terraform plan \
        -var-file="environments/$ENVIRONMENT.tfvars" \
        -var="app_version=$VERSION" \
        -out="$plan_file"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Dry run: Terraform plan created but not applied"
        return 0
    fi
    
    # Apply terraform plan
    log_info "Applying Terraform plan..."
    terraform apply "$plan_file"
    
    # Clean up plan file
    rm -f "$plan_file"
    
    log_success "Infrastructure deployment completed"
}

# Deploy application with Ansible
deploy_application() {
    if [[ "$SKIP_ANSIBLE" == "true" ]]; then
        log_warn "Skipping Ansible deployment (--skip-ansible specified)"
        return 0
    fi
    
    log_info "Deploying application with Ansible..."
    
    cd "$ANSIBLE_DIR"
    
    # Create dynamic inventory
    local inventory_file="inventory-$ENVIRONMENT-$(date +%s).yml"
    cat > "$inventory_file" << EOF
all:
  vars:
    target_env: $ENVIRONMENT
    deploy_version: $VERSION
    aws_region: ${AWS_REGION:-us-west-2}
    dry_run: $DRY_RUN
EOF
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Dry run: Ansible playbook validated but not executed"
        ansible-playbook -i "$inventory_file" deploy.yml --check --diff
    else
        # Run Ansible playbook
        ansible-playbook -i "$inventory_file" deploy.yml \
            --extra-vars "target_env=$ENVIRONMENT" \
            --extra-vars "deploy_version=$VERSION"
    fi
    
    # Clean up inventory file
    rm -f "$inventory_file"
    
    log_success "Application deployment completed"
}

# Post-deployment validation
post_deployment_validation() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Skipping validation (dry run mode)"
        return 0
    fi
    
    log_info "Running post-deployment validation..."
    
    # Determine application URL
    local app_url
    case "$ENVIRONMENT" in
        "production")
            app_url="https://paws.money"
            ;;
        "staging")
            app_url="https://staging.paws.money"
            ;;
        "dev")
            app_url="https://dev.paws.money"
            ;;
    esac
    
    # Health check with retries
    log_info "Performing health check..."
    local max_attempts=10
    local attempt=1
    
    while [[ $attempt -le $max_attempts ]]; do
        if curl -f "$app_url/api/health" > /dev/null 2>&1; then
            log_success "Health check passed"
            break
        else
            log_warn "Health check failed (attempt $attempt/$max_attempts)"
            if [[ $attempt -eq $max_attempts ]]; then
                log_error "Health check failed after $max_attempts attempts"
                return 1
            fi
            sleep 30
            ((attempt++))
        fi
    done
    
    # Performance test
    log_info "Running performance test..."
    local response_time
    response_time=$(curl -o /dev/null -s -w "%{time_total}" "$app_url/api/health")
    
    log_info "Response time: ${response_time}s"
    
    if (( $(echo "$response_time > 2.0" | bc -l) )); then
        log_warn "Response time is slower than expected: ${response_time}s"
    fi
    
    # Update deployment tracking
    log_info "Updating deployment tracking..."
    aws ssm put-parameter \
        --name "/money-paws/$ENVIRONMENT/last-successful-deployment" \
        --value "$VERSION" \
        --type String \
        --overwrite
    
    log_success "Post-deployment validation completed"
}

# Send notification
send_notification() {
    local status=$1
    local message=$2
    
    if [[ -z "${SLACK_WEBHOOK_URL:-}" ]]; then
        log_info "No Slack webhook configured, skipping notification"
        return 0
    fi
    
    local emoji
    local color
    case "$status" in
        "success")
            emoji="🚀"
            color="good"
            ;;
        "failure")
            emoji="🚨"
            color="danger"
            ;;
        *)
            emoji="ℹ️"
            color="warning"
            ;;
    esac
    
    local payload
    payload=$(cat << EOF
{
    "channel": "#deployments",
    "username": "Money Paws Deploy Bot",
    "icon_emoji": ":dog:",
    "attachments": [{
        "color": "$color",
        "text": "$emoji **Deployment $status**\nEnvironment: \`$ENVIRONMENT\`\nVersion: \`$VERSION\`\nUser: \`$(whoami)\`\nMessage: $message"
    }]
}
EOF
)
    
    curl -X POST -H 'Content-type: application/json' \
        --data "$payload" \
        "$SLACK_WEBHOOK_URL" > /dev/null 2>&1 || \
        log_warn "Failed to send Slack notification"
}

# Main deployment function
main() {
    local start_time
    start_time=$(date +%s)
    
    log_info "Starting Money Paws deployment..."
    log_info "Environment: $ENVIRONMENT"
    log_info "Version: $VERSION"
    log_info "Dry run: $DRY_RUN"
    
    # Create logs directory
    mkdir -p "$PROJECT_ROOT/logs"
    
    # Set up error handling
    trap 'log_error "Deployment failed"; send_notification "failure" "Deployment failed with error"; exit 1' ERR
    
    # Run deployment steps
    validate_requirements
    check_deployment_status
    run_tests
    deploy_infrastructure
    deploy_application
    post_deployment_validation
    
    # Calculate deployment time
    local end_time
    local duration
    end_time=$(date +%s)
    duration=$((end_time - start_time))
    
    log_success "Deployment completed successfully in ${duration}s"
    send_notification "success" "Deployment completed in ${duration}s"
}

# Parse arguments and run main function
parse_args "$@"

# Validate required arguments
if [[ -z "$ENVIRONMENT" ]]; then
    log_error "Environment is required. Use -e/--environment option."
    show_help
    exit 1
fi

# Run main function
main
