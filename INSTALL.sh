#!/bin/bash

# Digital Emergency Response System - Installation Script
# Yobe State University

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to detect OS
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        echo "linux"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macos"
    elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "cygwin" ]]; then
        echo "windows"
    else
        echo "unknown"
    fi
}

# Function to install PHP dependencies
install_php_dependencies() {
    print_status "Installing PHP dependencies..."

    if command_exists composer; then
        composer install --no-dev --optimize-autoloader
        print_success "PHP dependencies installed"
    else
        print_error "Composer not found. Please install Composer first."
        echo "Visit: https://getcomposer.org/download/"
        exit 1
    fi
}

# Function to set up database
setup_database() {
    print_status "Setting up database..."

    # Prompt for database credentials
    read -p "Enter database host (localhost): " DB_HOST
    DB_HOST=${DB_HOST:-localhost}

    read -p "Enter database name (emergency_response_system): " DB_NAME
    DB_NAME=${DB_NAME:-emergency_response_system}

    read -p "Enter database username: " DB_USER
    read -s -p "Enter database password: " DB_PASSWORD
    echo

    # Test database connection
    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed. Please check your credentials."
        exit 1
    fi

    # Create database if it doesn't exist
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

    # Import database schema
    if [ -f "database/schema.sql" ]; then
        print_status "Importing database schema..."
        mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/schema.sql
        print_success "Database schema imported"
    fi

    # Import seed data
    if [ -f "database/seed_data.sql" ]; then
        read -p "Import test data? (y/N): " IMPORT_DATA
        if [[ $IMPORT_DATA =~ ^[Yy]$ ]]; then
            print_status "Importing test data..."
            mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/seed_data.sql
            print_success "Test data imported"
        fi
    fi

    # Import stored procedures
    if [ -f "database/procedures.sql" ]; then
        print_status "Importing stored procedures..."
        mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/procedures.sql
        print_success "Stored procedures imported"
    fi

    # Create .env file
    create_env_file "$DB_HOST" "$DB_NAME" "$DB_USER" "$DB_PASSWORD"
}

# Function to create .env file
create_env_file() {
    local DB_HOST=$1
    local DB_NAME=$2
    local DB_USER=$3
    local DB_PASSWORD=$4

    print_status "Creating environment configuration..."

    if [ ! -f ".env" ]; then
        cp .env.example .env

        # Update database configuration
        sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/" .env
        sed -i "s/DB_NAME=.*/DB_NAME=$DB_NAME/" .env
        sed -i "s/DB_USER=.*/DB_USER=$DB_USER/" .env
        sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

        print_success "Environment configuration created"
        print_warning "Please update other values in .env file as needed"
    else
        print_warning ".env file already exists. Skipping creation."
    fi
}

# Function to set file permissions
set_permissions() {
    print_status "Setting file permissions..."

    # Create necessary directories
    mkdir -p uploads/emergency_images uploads/profile_images logs backups

    # Set permissions
    chmod 755 uploads logs backups
    chmod -R 644 uploads/*/*
    chmod -R 644 logs/*/*

    # Try to set ownership (may require sudo)
    if command_exists sudo; then
        sudo chown -R www-data:www-data uploads logs backups 2>/dev/null || print_warning "Could not set ownership. Please set it manually."
    fi

    print_success "File permissions set"
}

# Function to install Node.js dependencies
install_node_dependencies() {
    if [ -d "emergency-response-app" ]; then
        print_status "Installing Node.js dependencies..."
        cd emergency-response-app

        if command_exists npm; then
            npm install
            print_success "Node.js dependencies installed"
        else
            print_error "npm not found. Please install Node.js and npm first."
            echo "Visit: https://nodejs.org/"
        fi

        cd ..
    fi
}

# Function to check system requirements
check_requirements() {
    print_status "Checking system requirements..."

    local missing_requirements=()

    # Check PHP
    if command_exists php; then
        PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
        if [[ $(echo "$PHP_VERSION >= 8.0" | bc -l) -eq 1 ]]; then
            print_success "PHP $PHP_VERSION found"
        else
            print_error "PHP 8.0 or higher required. Found: $PHP_VERSION"
            missing_requirements+=("php")
        fi
    else
        print_error "PHP not found"
        missing_requirements+=("php")
    fi

    # Check required PHP extensions
    local extensions=("pdo" "pdo_mysql" "json" "curl" "openssl" "mbstring" "gd")
    for ext in "${extensions[@]}"; do
        if php -m | grep -q "$ext"; then
            print_success "PHP extension '$ext' found"
        else
            print_error "PHP extension '$ext' missing"
            missing_requirements+=("php-$ext")
        fi
    done

    # Check MySQL
    if command_exists mysql; then
        print_success "MySQL client found"
    else
        print_error "MySQL client not found"
        missing_requirements+=("mysql-client")
    fi

    # Check Node.js
    if command_exists node; then
        NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
        if [ "$NODE_VERSION" -ge 16 ]; then
            print_success "Node.js $(node -v) found"
        else
            print_error "Node.js 16 or higher required. Found: $(node -v)"
            missing_requirements+=("nodejs")
        fi
    else
        print_error "Node.js not found"
        missing_requirements+=("nodejs")
    fi

    # Check npm
    if command_exists npm; then
        print_success "npm $(npm -v) found"
    else
        print_error "npm not found"
        missing_requirements+=("npm")
    fi

    # Check Git
    if command_exists git; then
        print_success "Git $(git --version | cut -d' ' -f3) found"
    else
        print_error "Git not found"
        missing_requirements+=("git")
    fi

    if [ ${#missing_requirements[@]} -gt 0 ]; then
        print_error "Missing requirements: ${missing_requirements[*]}"
        print_status "Please install missing requirements and run the script again."

        # Provide installation hints based on OS
        OS=$(detect_os)
        case $OS in
            "linux")
                echo ""
                echo "For Ubuntu/Debian, run:"
                echo "sudo apt update"
                echo "sudo apt install php8.1 php8.1-mysql php8.1-json php8.1-curl php8.1-openssl php8.1-mbstring php8.1-gd mysql-client nodejs npm git"
                ;;
            "macos")
                echo ""
                echo "For macOS, run:"
                echo "brew install php@8.1 mysql node npm git"
                ;;
        esac

        exit 1
    fi

    print_success "All requirements met!"
}

# Function to test installation
test_installation() {
    print_status "Testing installation..."

    # Test backend
    if [ -f "health_check.php" ]; then
        if php -f health_check.php >/dev/null 2>&1; then
            print_success "Backend health check passed"
        else
            print_error "Backend health check failed"
            return 1
        fi
    fi

    # Test database connection
    if [ -f ".env" ]; then
        source .env
        if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT COUNT(*) FROM users;" >/dev/null 2>&1; then
            print_success "Database connection test passed"
        else
            print_error "Database connection test failed"
            return 1
        fi
    fi

    print_success "All tests passed!"
}

# Function to show next steps
show_next_steps() {
    echo ""
    echo "🎉 Installation completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Configure your web server to point to the 'emergency-response-server' directory"
    echo "2. Update the .env file with your specific settings"
    echo "3. Set up SSL certificates for production"
    echo "4. Run the mobile app:"
    echo "   cd emergency-response-app"
    echo "   npx react-native start"
    echo "   npx react-native run-android  # or run-ios"
    echo ""
    echo "For detailed instructions, see README.md"
    echo ""
    echo "Default admin login:"
    echo "Email: superadmin@ysu.edu.ng"
    echo "Password: admin123"
    echo ""
    echo "API Documentation: http://your-domain.com/api_info.php"
}

# Main installation function
main() {
    echo "🚀 Digital Emergency Response System Installation"
    echo "=================================================="
    echo ""

    # Check if we're in the right directory
    if [ ! -f "README.md" ] || [ ! -d "emergency-response-server" ]; then
        print_error "Please run this script from the project root directory"
        exit 1
    fi

    # Change to server directory
    cd emergency-response-server

    # Run installation steps
    check_requirements
    install_php_dependencies
    setup_database
    set_permissions
    test_installation

    # Go back to root directory for Node.js dependencies
    cd ..
    install_node_dependencies

    show_next_steps
}

# Handle script arguments
case "${1:-}" in
    "requirements")
        check_requirements
        ;;
    "database")
        setup_database
        ;;
    "permissions")
        set_permissions
        ;;
    "test")
        cd emergency-response-server
        test_installation
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  requirements   Check system requirements"
        echo "  database       Set up database"
        echo "  permissions    Set file permissions"
        echo "  test           Test installation"
        echo "  help           Show this help message"
        echo ""
        echo "Running without arguments performs full installation."
        ;;
    *)
        main
        ;;
esac