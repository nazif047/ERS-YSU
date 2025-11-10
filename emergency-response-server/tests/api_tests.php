<?php
/**
 * API Tests Suite
 * Yobe State University Emergency Response System
 */

// Include test configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * API Test Runner Class
 */
class APITestRunner {
    private $baseURL;
    private $testToken;
    private $testResults = [];
    private $pdo;

    public function __construct($baseURL = 'http://localhost/emergency-response-server') {
        $this->baseURL = $baseURL;
        $this->pdo = getDB();
    }

    /**
     * Run all API tests
     */
    public function runAllTests() {
        echo "Starting ERS API Tests...\n";
        echo "============================\n\n";

        $this->testAuthenticationEndpoints();
        $this->testEmergencyEndpoints();
        $this->testLocationEndpoints();
        $this->testAdminEndpoints();
        $this->testUserEndpoints();
        $this->testNotificationEndpoints();

        $this->printTestSummary();
    }

    /**
     * Test authentication endpoints
     */
    private function testAuthenticationEndpoints() {
        echo "Testing Authentication Endpoints:\n";
        echo "--------------------------------\n";

        // Test user registration
        $this->runTest('POST', '/api/auth/register.php', [
            'full_name' => 'Test User',
            'email' => 'testuser' . time() . '@ysu.edu.ng',
            'school_id' => 'YSU/TEST/' . time(),
            'phone' => '+2348012345678',
            'department' => 'academic',
            'password' => 'TestPass123!',
            'confirm_password' => 'TestPass123!'
        ], 'User Registration');

        // Test user login
        $this->runTest('POST', '/api/auth/login.php', [
            'login' => 'testuser' . (time() - 10) . '@ysu.edu.ng',
            'password' => 'TestPass123!'
        ], 'User Login');

        // Test profile access (requires token)
        if ($this->testToken) {
            $this->runTest('GET', '/api/users/get_profile.php', [], 'Get User Profile', true);
            $this->runTest('PUT', '/api/users/update_profile.php', [
                'full_name' => 'Updated Test User'
            ], 'Update User Profile', true);
        }

        echo "\n";
    }

    /**
     * Test emergency endpoints
     */
    private function testEmergencyEndpoints() {
        echo "Testing Emergency Endpoints:\n";
        echo "---------------------------\n";

        if (!$this->testToken) {
            echo "SKIPPING: Requires authentication\n\n";
            return;
        }

        // Test emergency types
        $this->runTest('GET', '/api/emergencies/types.php', [], 'Get Emergency Types', true);

        // Test emergency creation
        $this->runTest('POST', '/api/emergencies/create.php', [
            'emergency_type_id' => 1,
            'location_id' => 1,
            'description' => 'This is a test emergency for API testing purposes.',
            'severity' => 'medium'
        ], 'Create Emergency', true);

        // Test emergency list
        $this->runTest('GET', '/api/emergencies/list.php', [], 'Get Emergency List', true);

        // Test user emergencies
        $this->runTest('GET', '/api/emergencies/get_user_emergencies.php', [], 'Get User Emergencies', true);

        echo "\n";
    }

    /**
     * Test location endpoints
     */
    private function testLocationEndpoints() {
        echo "Testing Location Endpoints:\n";
        echo "-------------------------\n";

        // Test get locations
        $this->runTest('GET', '/api/locations/get_locations.php', [], 'Get Locations');

        // Test campus locations
        $this->runTest('GET', '/api/locations/get_campus_locations.php', [], 'Get Campus Locations');

        if ($this->testToken) {
            // Test add location (requires admin token)
            $this->runTest('POST', '/api/locations/add_location.php', [
                'name' => 'Test Location ' . time(),
                'description' => 'Test location for API testing',
                'category' => 'other',
                'latitude' => '12.4567',
                'longitude' => '10.1234'
            ], 'Add Location', true);
        }

        echo "\n";
    }

    /**
     * Test admin endpoints
     */
    private function testAdminEndpoints() {
        echo "Testing Admin Endpoints:\n";
        echo "-----------------------\n";

        if (!$this->testToken) {
            echo "SKIPPING: Requires authentication\n\n";
            return;
        }

        // Test admin dashboard
        $this->runTest('GET', '/api/admins/get_dashboard.php', [], 'Get Admin Dashboard', true);

        // Test department emergencies
        $this->runTest('GET', '/api/admins/get_department_emergencies.php', [], 'Get Department Emergencies', true);

        // Test analytics
        $this->runTest('GET', '/api/admins/get_analytics.php', [], 'Get Analytics', true);

        echo "\n";
    }

    /**
     * Test user endpoints
     */
    private function testUserEndpoints() {
        echo "Testing User Endpoints:\n";
        echo "----------------------\n";

        if (!$this->testToken) {
            echo "SKIPPING: Requires authentication\n\n";
            return;
        }

        // Test get users (admin only)
        $this->runTest('GET', '/api/users/get_users.php', [], 'Get Users List', true);

        echo "\n";
    }

    /**
     * Test notification endpoints
     */
    private function testNotificationEndpoints() {
        echo "Testing Notification Endpoints:\n";
        echo "------------------------------\n";

        if (!$this->testToken) {
            echo "SKIPPING: Requires authentication\n\n";
            return;
        }

        // Test get notifications
        $this->runTest('GET', '/api/notifications/get_notifications.php', [], 'Get Notifications', true);

        // Test mark notification as read
        $this->runTest('PUT', '/api/notifications/mark_read.php?notification_id=1', [], 'Mark Notification as Read', true);

        echo "\n";
    }

    /**
     * Run individual test
     */
    private function runTest($method, $endpoint, $data = [], $testName = '', $requiresAuth = false) {
        $url = $this->baseURL . $endpoint;
        $headers = ['Content-Type: application/json'];

        if ($requiresAuth && $this->testToken) {
            $headers[] = 'Authorization: Bearer ' . $this->testToken;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $testResult = [
            'name' => $testName ?: "$method $endpoint",
            'method' => $method,
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'response' => $response ? json_decode($response, true) : null,
            'error' => $error
        ];

        $this->testResults[] = $testResult;

        // Extract token from login response
        if ($testResult['success'] && strpos($endpoint, 'login.php') !== false && isset($testResult['response']['data']['token'])) {
            $this->testToken = $testResult['response']['data']['token'];
        }

        // Print test result
        $status = $testResult['success'] ? '✓ PASS' : '✗ FAIL';
        echo sprintf("  %s %s (HTTP %d)\n", $status, $testResult['name'], $httpCode);

        if (!$testResult['success']) {
            echo "    Error: " . ($error ?: 'HTTP ' . $httpCode) . "\n";
            if ($testResult['response']['error'] ?? false) {
                echo "    Message: " . $testResult['response']['error'] . "\n";
            }
        }
    }

    /**
     * Print test summary
     */
    private function printTestSummary() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, fn($r) => $r['success']));
        $failed = $total - $passed;

        echo "Test Summary:\n";
        echo "=============\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n\n";

        if ($failed > 0) {
            echo "Failed Tests:\n";
            echo "-------------\n";
            foreach ($this->testResults as $result) {
                if (!$result['success']) {
                    echo "  ✗ {$result['name']} - HTTP {$result['http_code']}\n";
                }
            }
            echo "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    // Check if running from command line
    if (php_sapi_name() === 'cli') {
        $baseURL = $argv[1] ?? 'http://localhost/emergency-response-server';
        $testRunner = new APITestRunner($baseURL);
        $testRunner->runAllTests();
    } else {
        echo "This script can only be run from the command line.\n";
        echo "Usage: php api_tests.php [base_url]\n";
    }
}
?>