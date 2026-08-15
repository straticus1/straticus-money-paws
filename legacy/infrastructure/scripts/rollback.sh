#!/bin/bash
# Money Paws Rollback Script
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
TARGET_VERSION=""
DRY_RUN=false
SKIP_CONFIRMATION=false
EMERGENCY_MODE=false

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
Money Paws Rollback Script

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -e, --environment ENV    Target environment (dev|staging|production)
    -v, --version VERSION    Version to rollback to (default: previous version)
    -d, --dry-run           Show what would be rolled back without executing
    -y, --yes               Skip confirmation prompts
    --emergency             Emergency mode - bypass safety checks
    -h, --help              Show this help message

EXAMPLES:
    # Rollback to previous version
    $0 -e production
    
    # Rollback to specific version
    $0 -e staging -v 3.1.2
    
    # Dry run rollback
    $0 -e production -d
    
    # Emergency rollback (skip confirmations and safety checks)
    $0 -e production --emergency -y

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
                TARGET_VERSION="$2"
                shift 2
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -y|--yes)
                SKIP_CONFIRMATION=true
                shift
                ;;
            --emergency)
                EMERGENCY_MODE=true
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
    local required_tools=("ansible-playbook" "aws" "jq" "curl")
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
    
    log_success "Requirements validation passed"
}

# Get deployment history
get_deployment_history() {
    log_info "Retrieving deployment history..."
    
    # Get current deployed version
    local current_version
    current_version=$(aws ssm get-parameter \
        --name "/money-paws/$ENVIRONMENT/last-successful-deployment" \
        --query 'Parameter.Value' \
        --output text 2>/dev/null || echo "unknown")
    
    # Get deployment history from SSM
    local deployment_history
    deployment_history=$(aws ssm get-parameter \
        --name "/money-paws/$ENVIRONMENT/deployment-history" \
        --query 'Parameter.Value' \
        --output text 2>/dev/null || echo '[]')
    
    # Parse deployment history
    local history_array
    history_array=$(echo "$deployment_history" | jq -r '.[]')
    
    log_info "Current deployed version: $current_version"
    log_info "Recent deployment history:"
    echo "$deployment_history" | jq -r '.[] | "  - Version: \(.version), Deployed: \(.timestamp), User: \(.user)"' | head -5
    
    # Determine target version if not specified
    if [[ -z "$TARGET_VERSION" ]]; then
        TARGET_VERSION=$(echo "$deployment_history" | jq -r '.[1].version' 2>/dev/null || echo "")
        if [[ -z "$TARGET_VERSION" ]] || [[ "$TARGET_VERSION" == "null" ]]; then
            log_error "Cannot determine previous version for rollback"
            log_error "Please specify target version with -v/--version option"
            exit 1
        fi
        log_info "Auto-selected rollback target: $TARGET_VERSION"
    fi
    
    # Validate target version exists
    if ! jq -e ".versions[\"$TARGET_VERSION\"]" "$VERSION_FILE" > /dev/null; then
        log_error "Target version $TARGET_VERSION not found in version.json"
        exit 1
    fi
    
    # Safety check - don't rollback to same version
    if [[ "$current_version" == "$TARGET_VERSION" ]]; then
        log_error "Target version $TARGET_VERSION is already deployed"
        exit 1
    fi
}

# Pre-rollback safety checks
safety_checks() {
    if [[ "$EMERGENCY_MODE" == "true" ]]; then
        log_warn "Emergency mode enabled - skipping safety checks"
        return 0
    fi
    
    log_info "Running safety checks..."
    
    # Check if production deployment during business hours
    if [[ "$ENVIRONMENT" == "production" ]]; then
        local current_hour
        current_hour=$(date +%H)
        local current_day
        current_day=$(date +%u) # 1=Monday, 7=Sunday
        
        # Business hours: Monday-Friday 9 AM to 6 PM PT
        if [[ $current_day -le 5 ]] && [[ $current_hour -ge 9 ]] && [[ $current_hour -lt 18 ]]; then
            log_warn "Rolling back production during business hours"
            if [[ "$SKIP_CONFIRMATION" != "true" ]]; then
                read -p "Are you sure you want to proceed? (y/N): " -n 1 -r
                echo
                if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                    log_info "Rollback cancelled"
                    exit 0
                fi
            fi
        fi
    fi
    
    # Check application health before rollback
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
    
    log_info "Checking current application health..."
    if ! curl -f "$app_url/api/health" > /dev/null 2>&1; then
        log_warn "Application health check failed - system may already be impaired"
    else
        log_info "Application is currently healthy"
    fi
    
    log_success "Safety checks completed"
}

# Create rollback checkpoint
create_checkpoint() {
    log_info "Creating rollback checkpoint..."
    
    # Get current deployment info
    local current_version
    current_version=$(aws ssm get-parameter \
        --name "/money-paws/$ENVIRONMENT/last-successful-deployment" \
        --query 'Parameter.Value' \
        --output text 2>/dev/null || echo "unknown")
    
    # Create checkpoint data
    local checkpoint_data
    checkpoint_data=$(cat << EOF
{
    "checkpoint_id": "rollback-$(date +%s)",
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "environment": "$ENVIRONMENT",
    "from_version": "$current_version",
    "to_version": "$TARGET_VERSION",
    "created_by": "$(whoami)",
    "reason": "manual_rollback"
}
EOF
)
    
    # Store checkpoint
    aws ssm put-parameter \
        --name "/money-paws/$ENVIRONMENT/rollback-checkpoint" \
        --value "$checkpoint_data" \
        --type String \
        --overwrite
    
    log_success "Rollback checkpoint created"
}

# Execute rollback
execute_rollback() {
    log_info "Executing rollback to version $TARGET_VERSION..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would rollback application to version $TARGET_VERSION"
        return 0
    fi
    
    cd "$ANSIBLE_DIR"
    
    # Create rollback inventory
    local inventory_file="inventory-rollback-$ENVIRONMENT-$(date +%s).yml"
    cat > "$inventory_file" << EOF
all:
  vars:
    target_env: $ENVIRONMENT
    deploy_version: $TARGET_VERSION
    aws_region: ${AWS_REGION:-us-west-2}
    rollback_mode: true
    emergency_mode: $EMERGENCY_MODE
EOF
    
    # Execute rollback playbook
    log_info "Running rollback playbook..."
    if ansible-playbook -i "$inventory_file" rollback.yml \
        --extra-vars "target_env=$ENVIRONMENT" \
        --extra-vars "deploy_version=$TARGET_VERSION" \
        --extra-vars "rollback_mode=true"; then
        log_success "Rollback playbook executed successfully"
    else
        log_error "Rollback playbook failed"
        rm -f "$inventory_file"
        return 1
    fi
    
    # Clean up inventory file
    rm -f "$inventory_file"
}

# Post-rollback validation
post_rollback_validation() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Skipping validation (dry run mode)"
        return 0
    fi
    
    log_info "Running post-rollback validation..."
    
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
    
    # Verify version endpoint
    log_info "Verifying deployed version..."
    local deployed_version
    deployed_version=$(curl -s "$app_url/api/version" | jq -r '.version' 2>/dev/null || echo "unknown")
    
    if [[ "$deployed_version" == "$TARGET_VERSION" ]]; then
        log_success "Version verification passed: $deployed_version"
    else
        log_error "Version verification failed. Expected: $TARGET_VERSION, Got: $deployed_version"
        return 1
    fi
    
    # Update deployment tracking
    log_info "Updating deployment tracking..."
    aws ssm put-parameter \
        --name "/money-paws/$ENVIRONMENT/last-successful-deployment" \
        --value "$TARGET_VERSION" \
        --type String \
        --overwrite
    
    # Update deployment history
    local deployment_history
    deployment_history=$(aws ssm get-parameter \
        --name "/money-paws/$ENVIRONMENT/deployment-history" \
        --query 'Parameter.Value' \
        --output text 2>/dev/null || echo '[]')
    
    local new_entry
    new_entry=$(cat << EOF
{
    "version": "$TARGET_VERSION",
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "user": "$(whoami)",
    "type": "rollback",
    "from_version": "$(aws ssm get-parameter --name "/money-paws/$ENVIRONMENT/rollback-checkpoint" --query 'Parameter.Value' --output text | jq -r '.from_version' 2>/dev/null || echo 'unknown')"
}
EOF
)
    
    local updated_history
    updated_history=$(echo "$deployment_history" | jq --argjson entry "$new_entry" '. = [$entry] + . | .[0:50]')
    
    aws ssm put-parameter \
        --name "/money-paws/$ENVIRONMENT/deployment-history" \
        --value "$updated_history" \
        --type String \
        --overwrite
    
    log_success "Post-rollback validation completed"
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
            emoji="⏪"
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
        "text": "$emoji **Rollback $status**\nEnvironment: \`$ENVIRONMENT\`\nRolled back to version: \`$TARGET_VERSION\`\nUser: \`$(whoami)\`\nMessage: $message"
    }]
}
EOF
)
    
    curl -X POST -H 'Content-type: application/json' \
        --data "$payload" \
        "$SLACK_WEBHOOK_URL" > /dev/null 2>&1 || \
        log_warn "Failed to send Slack notification"
}

# Main rollback function
main() {
    local start_time
    start_time=$(date +%s)
    
    log_info "Starting Money Paws rollback..."
    log_info "Environment: $ENVIRONMENT"
    log_info "Target version: ${TARGET_VERSION:-'auto-detect'}"
    log_info "Dry run: $DRY_RUN"
    log_info "Emergency mode: $EMERGENCY_MODE"
    
    # Final confirmation
    if [[ "$SKIP_CONFIRMATION" != "true" ]] && [[ "$DRY_RUN" != "true" ]]; then
        echo
        log_warn "This will rollback $ENVIRONMENT to version $TARGET_VERSION"
        read -p "Are you sure you want to proceed? (yes/no): " -r
        if [[ ! $REPLY =~ ^(yes|YES)$ ]]; then
            log_info "Rollback cancelled"
            exit 0
        fi
    fi
    
    # Set up error handling
    trap 'log_error "Rollback failed"; send_notification "failure" "Rollback failed with error"; exit 1' ERR
    
    # Run rollback steps
    validate_requirements
    get_deployment_history
    safety_checks
    create_checkpoint
    execute_rollback
    post_rollback_validation
    
    # Calculate rollback time
    local end_time
    local duration
    end_time=$(date +%s)
    duration=$((end_time - start_time))
    
    log_success "Rollback completed successfully in ${duration}s"
    send_notification "success" "Rollback completed in ${duration}s"
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
