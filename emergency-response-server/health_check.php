<?php
/**
 * Health Check Endpoint
 * Yobe State University Emergency Response System
 */

header('Content-Type: application/json; charset=UTF-8');

try {
    // Check database connection
    $dbHealthy = false;
    $dbError = null;

    try {
        require_once __DIR__ . '/includes/db.php';
        $pdo = getDB();

        // Test database with simple query
        $stmt = $pdo->prepare("SELECT 1 as test");
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result && $result['test'] == 1) {
            $dbHealthy = true;
        }
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }

    // Check required tables
    $tables = ['users', 'locations', 'emergency_types', 'emergencies'];
    $tablesStatus = [];

    if ($dbHealthy) {
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE '$table'");
                $stmt->execute();
                $tablesStatus[$table] = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                $tablesStatus[$table] = false;
            }
        }
    }

    // Check if we have emergency types configured
    $emergencyTypesCount = 0;
    if ($dbHealthy && $tablesStatus['emergency_types']) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM emergency_types WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch();
            $emergencyTypesCount = $result['count'];
        } catch (Exception $e) {
            // Ignore
        }
    }

    // Check if we have locations configured
    $locationsCount = 0;
    if ($dbHealthy && $tablesStatus['locations']) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM locations WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch();
            $locationsCount = $result['count'];
        } catch (Exception $e) {
            // Ignore
        }
    }

    // Determine overall health
    $allTablesExist = !in_array(false, $tablesStatus, true);
    $systemHealthy = $dbHealthy && $allTablesExist && $emergencyTypesCount > 0 && $locationsCount > 0;

    $response = [
        'status' => $systemHealthy ? 'healthy' : 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'version' => API_VERSION,
        'checks' => [
            'database' => [
                'status' => $dbHealthy ? 'healthy' : 'unhealthy',
                'error' => $dbError
            ],
            'tables' => $tablesStatus,
            'data' => [
                'emergency_types' => $emergencyTypesCount,
                'locations' => $locationsCount
            ]
        ],
        'system_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . ' seconds',
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ]
    ];

    // Set HTTP status code based on health
    http_response_code($systemHealthy ? 200 : 503);

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>