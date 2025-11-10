# Digital Emergency Response System - Yobe State University

A comprehensive mobile and web-based emergency response system designed to enhance campus safety through automated emergency reporting, real-time notifications, and efficient departmental coordination.

## 🚀 Project Overview

The Digital Emergency Response System (ERS) is a complete emergency management platform that enables students and staff to quickly report emergencies via mobile devices, automatically routing incidents to the appropriate response departments (Health, Fire, Security) with real-time location tracking and status updates.

### Key Features

- **Mobile Emergency Reporting**: Quick emergency reporting with one-touch panic button
- **GPS Location Tracking**: Automatic location detection with manual override options
- **Automated Departmental Routing**: Intelligent routing to Health, Fire, or Security departments
- **Real-time Notifications**: Multi-channel alerts via push notifications, email, and SMS
- **Administrative Dashboard**: Comprehensive analytics and emergency management interface
- **Role-based Access Control**: Secure authentication with user roles and permissions
- **Offline Support**: Basic functionality available without internet connectivity
- **Multi-platform Support**: Compatible with iOS and Android devices

## 📋 Table of Contents

- [System Requirements](#system-requirements)
- [Installation Guide](#installation-guide)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Running the System](#running-the-system)
- [Testing Guide](#testing-guide)
- [API Documentation](#api-documentation)
- [Mobile App Setup](#mobile-app-setup)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 🔧 System Requirements

### Backend Requirements
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 8.0 or higher
- **Apache**: Version 2.4 or higher (with mod_rewrite enabled)
- **PHP Extensions**:
  - PDO
  - PDO_MySQL
  - JSON
  - cURL
  - OpenSSL
  - mbstring
  - GD
  - zip

### Frontend Requirements
- **Node.js**: Version 16.0 or higher
- **npm**: Version 8.0 or higher
- **React Native CLI**: Latest version
- **React Native**: Version 0.72.6

### Development Tools
- **Git**: Version 2.0 or higher
- **Composer**: Latest version
- **Android Studio** (for Android development)
- **Xcode** (for iOS development - macOS only)

## 📦 Installation Guide

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/ERS-YSU.git
cd ERS-YSU
```

### 2. Backend Setup

#### 2.1 Install Dependencies

```bash
cd emergency-response-server
composer install
```

#### 2.2 Configure Environment

```bash
# Copy environment configuration
cp .env.example .env

# Edit the configuration file
nano .env
```

Update the following variables in `.env`:
```env
DB_HOST=localhost
DB_NAME=emergency_response_system
DB_USER=your_database_username
DB_PASSWORD=your_database_password

JWT_SECRET=your-super-secret-jwt-key-change-this-in-production

SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USERNAME=your_smtp_username
SMTP_PASSWORD=your_smtp_password
```

#### 2.3 Set File Permissions

```bash
# Set proper permissions for upload directories
chmod 755 uploads/
chmod 755 logs/
chmod 644 uploads/*/*
chmod 644 logs/*/*

# Set write permissions for logs
chown -R www-data:www-data logs/
chown -R www-data:www-data uploads/
```

### 3. Database Setup

#### 3.1 Create Database

```sql
CREATE DATABASE emergency_response_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3.2 Import Database Schema

```bash
mysql -u your_username -p emergency_response_system < database/schema.sql
```

#### 3.3 Import Test Data (Optional)

```bash
mysql -u your_username -p emergency_response_system < database/seed_data.sql
```

#### 3.4 Import Stored Procedures

```bash
mysql -u your_username -p emergency_response_system < database/procedures.sql
```

### 4. Web Server Configuration

#### 4.1 Apache Configuration

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/ERS-YSU/emergency-response-server

    <Directory /path/to/ERS-YSU/emergency-response-server>
        AllowOverride All
        Require all granted
    </Directory>

    # Enable URL rewriting
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</VirtualHost>
```

#### 4.2 Enable Required Modules

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### 5. Frontend Setup

#### 5.1 Install Dependencies

```bash
cd emergency-response-app
npm install
```

#### 5.2 Configure API Endpoint

Edit `src/services/api.js` and update the base URL:

```javascript
const API_BASE_URL = 'http://your-domain.com/api';
```

## ⚙️ Configuration

### Backend Configuration

Edit `config/config.php` for advanced settings:

```php
// Emergency Response Settings
define('EMERGENCY_AUTO_ASSIGN', true);
define('RESPONSE_TIMEOUT_MINUTES', 30);
define('NOTIFICATION_ENABLED', true);

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Security Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
```

### Mobile App Configuration

#### Android Setup

1. **Generate Release Key:**
```bash
cd emergency-response-app/android/app
keytool -genkey -v -keystore release-key.keystore -alias release-key -keyalg RSA -keysize 2048 -validity 10000
```

2. **Configure gradle.properties:**
```properties
MYAPP_RELEASE_STORE_FILE=release-key.keystore
MYAPP_RELEASE_KEY_ALIAS=release-key
MYAPP_RELEASE_STORE_PASSWORD=your_password
MYAPP_RELEASE_KEY_PASSWORD=your_password
```

3. **Update AndroidManifest.xml** with proper permissions:
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

#### iOS Setup

1. **Install CocoaPods dependencies:**
```bash
cd emergency-response-app/ios
pod install
```

2. **Configure Info.plist** with location permissions:
```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>This app needs access to your location for emergency reporting</string>
<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>This app needs access to your location for emergency reporting</string>
```

## 🚀 Running the System

### Backend

1. **Start Web Server:**
```bash
# Using Apache (recommended for production)
sudo systemctl start apache2

# Using PHP Built-in Server (development only)
cd emergency-response-server
php -S localhost:8000
```

2. **Verify Backend is Working:**
```bash
curl http://your-domain.com/health_check.php
```

### Mobile App

1. **Start Metro Bundler:**
```bash
cd emergency-response-app
npx react-native start
```

2. **Run on Android:**
```bash
npx react-native run-android
```

3. **Run on iOS:**
```bash
npx react-native run-ios
```

### Admin Dashboard

Access the admin dashboard by navigating to:
```
http://your-domain.com/admin/dashboard.html
```

Default admin credentials (for testing):
- Email: `superadmin@ysu.edu.ng`
- Password: `admin123`

## 🧪 Testing Guide

### Backend Testing

1. **Run API Tests:**
```bash
cd emergency-response-server/tests
php api_tests.php
```

2. **Test Database Connection:**
```bash
php -f health_check.php
```

3. **Verify All Endpoints:**
```bash
# Test authentication
curl -X POST http://your-domain.com/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"login":"testuser@ysu.edu.ng","password":"TestPass123!"}'

# Test emergency reporting
curl -X POST http://your-domain.com/api/emergencies/create.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"emergency_type_id":1,"location_id":1,"description":"Test emergency","severity":"medium"}'
```

### Mobile App Testing

1. **Unit Tests:**
```bash
cd emergency-response-app
npm test
```

2. **End-to-End Testing:**
```bash
# Install Detox for E2E testing
npm install --save-dev detox
detox build
detox test
```

3. **Manual Testing Checklist:**

   - [ ] User registration and login
   - [ ] Emergency type selection
   - [ ] GPS location detection
   - [ ] Manual location selection
   - [ ] Emergency description input
   - [ ] Photo attachment
   - [ ] Severity level selection
   - [ ] Emergency submission
   - [ ] Real-time status updates
   - [ ] Push notifications
   - [ ] Admin dashboard access
   - [ ] Emergency status management
   - [ ] Analytics viewing

### Load Testing

1. **Database Load Test:**
```sql
-- Test database performance with 1000 concurrent users
mysqlslap --user=username --password=password \
  --concurrency=1000 --iterations=10 \
  --create-schema=emergency_response_system \
  --query="SELECT * FROM emergencies WHERE status='pending'"
```

2. **API Load Test:**
```bash
# Install Apache Bench
sudo apt-get install apache2-utils

# Test API endpoints
ab -n 1000 -c 100 http://your-domain.com/api/emergencies/types.php
```

## 📚 API Documentation

### Authentication Endpoints

#### POST /api/auth/login.php
Login user with email or school ID.

**Request Body:**
```json
{
  "login": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {...},
    "token": "jwt_token_here",
    "refresh_token": "refresh_token_here"
  }
}
```

#### POST /api/auth/register.php
Register new user account.

**Request Body:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "school_id": "YSU/2023/0001",
  "phone": "+2348012345678",
  "department": "academic",
  "password": "password123",
  "confirm_password": "password123"
}
```

### Emergency Endpoints

#### POST /api/emergencies/create.php
Report new emergency.

**Headers:**
```
Authorization: Bearer jwt_token_here
```

**Request Body:**
```json
{
  "emergency_type_id": 1,
  "location_id": 1,
  "description": "Medical emergency reported",
  "severity": "high",
  "latitude": 12.4567,
  "longitude": 10.1234
}
```

#### GET /api/emergencies/get_user_emergencies.php
Get user's emergency reports.

**Headers:**
```
Authorization: Bearer jwt_token_here
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_progress, resolved, closed)
- `page` (optional): Page number for pagination
- `limit` (optional): Number of records per page

### Admin Endpoints

#### GET /api/admins/get_dashboard.php
Get admin dashboard data.

**Headers:**
```
Authorization: Bearer jwt_token_here
```

#### GET /api/admins/get_analytics.php
Get department analytics.

**Headers:**
```
Authorization: Bearer jwt_token_here
```

**Query Parameters:**
- `period`: Number of days for analytics (default: 30)
- `date_from`: Start date (YYYY-MM-DD)
- `date_to`: End date (YYYY-MM-DD)

For complete API documentation, see `docs/API.md`.

## 📱 Mobile App Setup

### Development Environment Setup

1. **Install React Native CLI:**
```bash
npm install -g react-native-cli
```

2. **Set up Android Development:**
   - Install Android Studio
   - Configure Android SDK
   - Create an Android Virtual Device (AVD)
   - Set up environment variables

3. **Set up iOS Development (macOS only):**
   - Install Xcode
   - Install CocoaPods
   - Configure iOS Simulator

### Building for Production

#### Android Build

1. **Generate APK:**
```bash
cd emergency-response-app/android
./gradlew assembleRelease
```

2. **Generate Bundle:**
```bash
./gradlew bundleRelease
```

#### iOS Build

1. **Open in Xcode:**
```bash
open ios/YourApp.xceworkspace
```

2. **Build Archive:**
   - Select "Any iOS Device"
   - Product → Archive
   - Distribute via App Store or Ad Hoc

### Deployment

#### Google Play Store

1. **Prepare App Bundle:**
```bash
cd emergency-response-app/android
./gradlew bundleRelease
```

2. **Upload to Google Play Console:**
   - Sign in to Google Play Console
   - Create new application
   - Upload the AAB file
   - Complete store listing

#### Apple App Store

1. **Archive and Upload:**
```bash
# Use Xcode to create and upload archive
```

2. **Complete App Store Connect:**
   - Sign in to App Store Connect
   - Complete app information
   - Submit for review

## 🔧 Troubleshooting

### Common Issues

#### Backend Issues

**Problem: Database Connection Failed**
```bash
# Check MySQL service status
sudo systemctl status mysql

# Check database credentials
mysql -u username -p -h localhost

# Verify PHP MySQL extension
php -m | grep mysql
```

**Problem: JWT Token Not Working**
```bash
# Check JWT secret key is set
grep JWT_SECRET config/config.php

# Verify token expiration
grep JWT_EXPIRE_TIME config/config.php
```

**Problem: File Upload Not Working**
```bash
# Check directory permissions
ls -la uploads/

# Fix permissions
chmod 755 uploads/
chown www-data:www-data uploads/
```

#### Mobile App Issues

**Problem: Metro Bundler Not Starting**
```bash
# Clear Metro cache
npx react-native start --reset-cache

# Clear node modules
rm -rf node_modules package-lock.json
npm install
```

**Problem: Build Failed on Android**
```bash
# Clean Gradle cache
cd android
./gradlew clean

# Check Android SDK path
echo $ANDROID_HOME
```

**Problem: iOS Build Failed**
```bash
# Clear iOS build cache
cd ios
xcodebuild clean

# Reinstall Pods
rm -rf Pods Podfile.lock
pod install
```

### Performance Issues

#### Database Optimization

```sql
-- Check slow queries
SHOW FULL PROCESSLIST;

-- Optimize tables
OPTIMIZE TABLE emergencies, notifications, users;

-- Add indexes if missing
CREATE INDEX idx_emergencies_status ON emergencies(status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
```

#### Mobile App Performance

1. **Enable Performance Monitoring:**
```javascript
// Add to App.js
import { YellowBox } from 'react-native';

if (__DEV__) {
  YellowBox.ignoreWarnings(['VirtualizedLists']);
}
```

2. **Optimize Images:**
```javascript
// Use appropriate image sizes
<Image source={require('./images/icon.png')} style={{width: 20, height: 20}} />
```

### Security Issues

#### Common Vulnerabilities

1. **SQL Injection Prevention:**
```php
// Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

2. **XSS Prevention:**
```php
// Escape output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
```

3. **CSRF Protection:**
```php
// Implement CSRF tokens
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
```

## 🤝 Contributing

We welcome contributions to improve the Digital Emergency Response System. Please follow these guidelines:

### Development Workflow

1. **Fork the repository**
2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes**
4. **Add tests for new functionality**
5. **Run the test suite**
   ```bash
   npm test
   php api_tests.php
   ```
6. **Commit your changes**
   ```bash
   git commit -m "Add your feature description"
   ```
7. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```
8. **Create a Pull Request**

### Code Style Guidelines

- **PHP**: Follow PSR-12 coding standards
- **JavaScript**: Use ESLint configuration
- **React Native**: Follow React Native conventions
- **Database**: Use snake_case for column names
- **API**: Use RESTful principles

### Issue Reporting

When reporting issues, please include:
- Detailed description of the problem
- Steps to reproduce the issue
- Expected vs. actual behavior
- Environment details (OS, browser, app version)
- Screenshots or error messages

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

For support and questions:

- **Technical Issues**: Create an issue on GitHub
- **Security Concerns**: Email security@ysu.edu.ng
- **Emergency System Issues**: Contact the IT Department directly

## 🙏 Acknowledgments

- Yobe State University Administration
- Computer Science Department
- Emergency Response Teams (Health, Security, Fire)
- Student Volunteers for Testing
- Open Source Community

---

**Emergency Contacts:**
- **Security**: +234-XXX-XXXX-XXXX
- **Health Center**: +234-XXX-XXXX-XXXX
- **Fire Safety**: +234-XXX-XXXX-XXXX

**In case of emergency, use the panic button in the mobile app or call the emergency contacts directly.**