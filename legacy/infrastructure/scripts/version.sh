#!/bin/bash
# Money Paws Version Management Script
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
VERSION_FILE="$PROJECT_ROOT/version.json"

# Default values
ACTION=""
VERSION=""
MESSAGE=""
FORCE=false

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
Money Paws Version Management Script

USAGE:
    $0 <action> [OPTIONS]

ACTIONS:
    create VERSION          Create a new version
    list                    List all versions
    current                 Show current version
    set-current VERSION     Set current version
    info VERSION            Show version information
    remove VERSION          Remove a version
    init                    Initialize version file

OPTIONS:
    -m, --message MSG       Version message/changelog
    -f, --force             Force action (skip confirmations)
    -h, --help              Show this help message

EXAMPLES:
    # Initialize version file
    $0 init
    
    # Create new version
    $0 create 3.1.4 -m "Bug fixes and performance improvements"
    
    # List all versions
    $0 list
    
    # Show current version
    $0 current
    
    # Set current version
    $0 set-current 3.1.4
    
    # Show version info
    $0 info 3.1.4
    
    # Remove version
    $0 remove 3.1.3 -f

EOF
}

# Parse command line arguments
parse_args() {
    if [[ $# -eq 0 ]]; then
        show_help
        exit 1
    fi
    
    ACTION="$1"
    shift
    
    # Handle version argument for relevant actions
    case "$ACTION" in
        create|info|remove|set-current)
            if [[ $# -eq 0 ]]; then
                log_error "$ACTION requires a version argument"
                exit 1
            fi
            VERSION="$1"
            shift
            ;;
    esac
    
    # Parse remaining options
    while [[ $# -gt 0 ]]; do
        case $1 in
            -m|--message)
                MESSAGE="$2"
                shift 2
                ;;
            -f|--force)
                FORCE=true
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

# Validate version format
validate_version() {
    local version="$1"
    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        log_error "Invalid version format: $version (expected: x.y.z)"
        exit 1
    fi
}

# Initialize version file
init_version_file() {
    log_info "Initializing version file..."
    
    if [[ -f "$VERSION_FILE" ]] && [[ "$FORCE" != "true" ]]; then
        log_error "Version file already exists: $VERSION_FILE"
        log_error "Use --force to overwrite"
        exit 1
    fi
    
    local initial_version="1.0.0"
    local init_data
    init_data=$(cat << EOF
{
    "current_version": "$initial_version",
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "created_by": "$(whoami)",
    "versions": {
        "$initial_version": {
            "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
            "created_by": "$(whoami)",
            "message": "Initial version",
            "git_commit": "$(git rev-parse HEAD 2>/dev/null || echo 'unknown')",
            "git_branch": "$(git branch --show-current 2>/dev/null || echo 'unknown')",
            "environments": {
                "dev": {
                    "deployed": false,
                    "deployed_at": null,
                    "deployed_by": null
                },
                "staging": {
                    "deployed": false,
                    "deployed_at": null,
                    "deployed_by": null
                },
                "production": {
                    "deployed": false,
                    "deployed_at": null,
                    "deployed_by": null
                }
            }
        }
    }
}
EOF
)
    
    echo "$init_data" | jq '.' > "$VERSION_FILE"
    log_success "Version file initialized with version $initial_version"
}

# Create new version
create_version() {
    log_info "Creating version $VERSION..."
    
    validate_version "$VERSION"
    
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        log_error "Run '$0 init' to initialize"
        exit 1
    fi
    
    # Check if version already exists
    if jq -e ".versions[\"$VERSION\"]" "$VERSION_FILE" > /dev/null; then
        if [[ "$FORCE" != "true" ]]; then
            log_error "Version $VERSION already exists"
            log_error "Use --force to overwrite"
            exit 1
        else
            log_warn "Version $VERSION exists, overwriting..."
        fi
    fi
    
    # Get git information
    local git_commit
    local git_branch
    git_commit=$(git rev-parse HEAD 2>/dev/null || echo 'unknown')
    git_branch=$(git branch --show-current 2>/dev/null || echo 'unknown')
    
    # Create version data
    local version_data
    version_data=$(cat << EOF
{
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "created_by": "$(whoami)",
    "message": "${MESSAGE:-Version $VERSION}",
    "git_commit": "$git_commit",
    "git_branch": "$git_branch",
    "environments": {
        "dev": {
            "deployed": false,
            "deployed_at": null,
            "deployed_by": null
        },
        "staging": {
            "deployed": false,
            "deployed_at": null,
            "deployed_by": null
        },
        "production": {
            "deployed": false,
            "deployed_at": null,
            "deployed_by": null
        }
    }
}
EOF
)
    
    # Update version file
    local updated_file
    updated_file=$(jq --arg version "$VERSION" --argjson data "$version_data" \
        '.versions[$version] = $data | .current_version = $version' "$VERSION_FILE")
    
    echo "$updated_file" > "$VERSION_FILE"
    
    log_success "Version $VERSION created successfully"
    log_info "Git commit: $git_commit"
    log_info "Git branch: $git_branch"
}

# List all versions
list_versions() {
    log_info "Available versions:"
    
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        exit 1
    fi
    
    local current_version
    current_version=$(jq -r '.current_version' "$VERSION_FILE")
    
    echo
    printf "%-12s %-20s %-15s %-50s\n" "VERSION" "CREATED" "BY" "MESSAGE"
    printf "%-12s %-20s %-15s %-50s\n" "-------" "-------" "--" "-------"
    
    jq -r '.versions | to_entries | sort_by(.key | split(".") | map(tonumber)) | reverse | .[] | 
        "\(.key)\t\(.value.created_at)\t\(.value.created_by)\t\(.value.message)"' "$VERSION_FILE" | \
    while IFS=$'\t' read -r version created_at created_by message; do
        local marker=""
        if [[ "$version" == "$current_version" ]]; then
            marker=" *"
        fi
        printf "%-12s %-20s %-15s %-50s%s\n" "$version" "$created_at" "$created_by" "$message" "$marker"
    done
    
    echo
    log_info "Current version: $current_version (* indicates current)"
}

# Show current version
show_current() {
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        exit 1
    fi
    
    local current_version
    current_version=$(jq -r '.current_version' "$VERSION_FILE")
    
    echo "$current_version"
}

# Set current version
set_current() {
    log_info "Setting current version to $VERSION..."
    
    validate_version "$VERSION"
    
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        exit 1
    fi
    
    # Check if version exists
    if ! jq -e ".versions[\"$VERSION\"]" "$VERSION_FILE" > /dev/null; then
        log_error "Version $VERSION not found"
        exit 1
    fi
    
    # Update current version
    local updated_file
    updated_file=$(jq --arg version "$VERSION" '.current_version = $version' "$VERSION_FILE")
    
    echo "$updated_file" > "$VERSION_FILE"
    
    log_success "Current version set to $VERSION"
}

# Show version information
show_version_info() {
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        exit 1
    fi
    
    validate_version "$VERSION"
    
    # Check if version exists
    if ! jq -e ".versions[\"$VERSION\"]" "$VERSION_FILE" > /dev/null; then
        log_error "Version $VERSION not found"
        exit 1
    fi
    
    log_info "Version $VERSION information:"
    echo
    
    # Get version data
    local version_data
    version_data=$(jq ".versions[\"$VERSION\"]" "$VERSION_FILE")
    
    echo "Created:    $(echo "$version_data" | jq -r '.created_at')"
    echo "Created by: $(echo "$version_data" | jq -r '.created_by')"
    echo "Message:    $(echo "$version_data" | jq -r '.message')"
    echo "Git commit: $(echo "$version_data" | jq -r '.git_commit')"
    echo "Git branch: $(echo "$version_data" | jq -r '.git_branch')"
    echo
    
    # Show deployment status
    echo "Deployment Status:"
    printf "%-12s %-10s %-20s %-15s\n" "ENVIRONMENT" "DEPLOYED" "DEPLOYED AT" "DEPLOYED BY"
    printf "%-12s %-10s %-20s %-15s\n" "-----------" "--------" "-----------" "-----------"
    
    for env in dev staging production; do
        local deployed
        local deployed_at
        local deployed_by
        
        deployed=$(echo "$version_data" | jq -r ".environments.$env.deployed")
        deployed_at=$(echo "$version_data" | jq -r ".environments.$env.deployed_at // \"N/A\"")
        deployed_by=$(echo "$version_data" | jq -r ".environments.$env.deployed_by // \"N/A\"")
        
        printf "%-12s %-10s %-20s %-15s\n" "$env" "$deployed" "$deployed_at" "$deployed_by"
    done
}

# Remove version
remove_version() {
    log_info "Removing version $VERSION..."
    
    validate_version "$VERSION"
    
    if [[ ! -f "$VERSION_FILE" ]]; then
        log_error "Version file not found: $VERSION_FILE"
        exit 1
    fi
    
    # Check if version exists
    if ! jq -e ".versions[\"$VERSION\"]" "$VERSION_FILE" > /dev/null; then
        log_error "Version $VERSION not found"
        exit 1
    fi
    
    # Check if it's the current version
    local current_version
    current_version=$(jq -r '.current_version' "$VERSION_FILE")
    
    if [[ "$VERSION" == "$current_version" ]] && [[ "$FORCE" != "true" ]]; then
        log_error "Cannot remove current version $VERSION"
        log_error "Set a different current version first, or use --force"
        exit 1
    fi
    
    # Confirmation
    if [[ "$FORCE" != "true" ]]; then
        read -p "Are you sure you want to remove version $VERSION? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Operation cancelled"
            exit 0
        fi
    fi
    
    # Remove version
    local updated_file
    updated_file=$(jq --arg version "$VERSION" 'del(.versions[$version])' "$VERSION_FILE")
    
    # If removed version was current, set to latest remaining
    if [[ "$VERSION" == "$current_version" ]]; then
        local new_current
        new_current=$(echo "$updated_file" | jq -r '.versions | keys | sort_by(split(".") | map(tonumber)) | reverse | .[0]')
        updated_file=$(echo "$updated_file" | jq --arg version "$new_current" '.current_version = $version')
        log_warn "Current version changed to $new_current"
    fi
    
    echo "$updated_file" > "$VERSION_FILE"
    
    log_success "Version $VERSION removed successfully"
}

# Update deployment status
update_deployment_status() {
    local version="$1"
    local environment="$2"
    local deployed="$3"
    local deployed_by="${4:-$(whoami)}"
    
    if [[ ! -f "$VERSION_FILE" ]]; then
        return 0
    fi
    
    local updated_file
    updated_file=$(jq --arg version "$version" --arg env "$environment" --arg deployed "$deployed" \
        --arg deployed_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg deployed_by "$deployed_by" \
        '.versions[$version].environments[$env] = {
            "deployed": ($deployed | test("true")),
            "deployed_at": (if $deployed == "true" then $deployed_at else null end),
            "deployed_by": (if $deployed == "true" then $deployed_by else null end)
        }' "$VERSION_FILE" 2>/dev/null || echo "{}")
    
    if [[ "$updated_file" != "{}" ]]; then
        echo "$updated_file" > "$VERSION_FILE"
    fi
}

# Main function
main() {
    case "$ACTION" in
        init)
            init_version_file
            ;;
        create)
            create_version
            ;;
        list)
            list_versions
            ;;
        current)
            show_current
            ;;
        set-current)
            set_current
            ;;
        info)
            show_version_info
            ;;
        remove)
            remove_version
            ;;
        *)
            log_error "Unknown action: $ACTION"
            show_help
            exit 1
            ;;
    esac
}

# Parse arguments and run main function
parse_args "$@"

# Run main function
main
