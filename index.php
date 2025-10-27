<?php 

// Main entry point - handles all requests
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production

require_once 'vendor/autoload.php';
require_once 'backend/function/helper.php';

// Database configuration
$db_host = 'sql207.infinityfree.com';
$db_user = 'if0_40197402';
$db_pass = 'Zk4Ivol6RiynT';
$db_name = 'if0_40197402_system_database';

// HTTP request information
$request_uri = parse_url("/".$_GET['_route']??"", PHP_URL_PATH); // this one cannot use
// $request_uri = isset($_GET['_route']) ? "/".$_GET['_route'] : '/';

$request_method = $_SERVER['REQUEST_METHOD'];
$request_host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Initialize global variables
$mysqli = null;
$status = null;
$config = null;

// Middleware 1: Database connection
try {
    include "backend/service/middleware_interface_service.php";
    include "backend/service/database.php";
    include "backend/service/database_interface_service.php";
    include "backend/service/database_service.php";
    include "backend/service/database_mysql_service.php";
    
    $database = new database($db_host, $db_user, $db_pass, $db_name);
    $database_mysql_service = new database_mysql_service($database);
    $database_mysql_service->database_init($database);
    
    $result = $database_mysql_service->middleware_check();
    if ($result) {
        $mysqli = $database_mysql_service->get_database_conn();
    } else {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    http_response_code(500);
    if (strpos($request_uri, '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    } else {
        echo '<h1>Service Unavailable</h1><p>Please try again later.</p>';
    }
    exit;
}

// Middleware 2: Load configuration
try {
    include "backend/service/config.php";
    include "backend/service/config_service.php";
    include "backend/service/config_interface_service.php";
    include "backend/service/config_basic_service.php";
    
    // Get JWT key from database
    $stmt = $mysqli->prepare("SELECT config_value FROM system_config WHERE config_key = 'JWT_DAILY_REFRESH_KEY'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $jwt_key = $row['config_value'];
    } else {
        // Generate new key if not exists
        $jwt_key = base64_encode(random_bytes(32));
        $stmt = $mysqli->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('JWT_DAILY_REFRESH_KEY', ?)");
        $stmt->bind_param('s', $jwt_key);
        $stmt->execute();
    }
    
    $config = new config($jwt_key);
} catch (Exception $e) {
    // Use default key if config table doesn't exist yet (for migration)
    $config = new config(base64_encode('default_secret_key_change_in_production'));
}

// Middleware 3: Initialize status and verify JWT
try {
    include "backend/service/status.php";
    include "backend/service/status_service.php";
    include "backend/service/status_interface_service.php";
    include "backend/service/status_basic_service.php";
    include "backend/service/jwt.php";
    include "backend/service/jwt_service.php";
    include "backend/service/jwt_interface_service.php";
    include "backend/service/jwt_verify_service.php";
    
    // Initialize status with default values
    $permission = 'guest';
    $is_login = false;
    $user_info = [];
    
    $status = new status($permission, $is_login, $request_uri, $request_method, $user_info, $request_host);
    
    // Try to verify JWT token
    $jwt_data = new jwt($status, $config, '');
    $jwt_verify_service = new jwt_verify_service($jwt_data, $config->JWT_DAILY_REFRESH_KEY);

    if ($jwt_verify_service->middleware_check()) {
        $status = $jwt_verify_service->get_jwt_data()->status;
    }
    
} catch (Exception $e) {
    // Continue with guest status if JWT verification fails
    $status = new status('guest', false, $request_uri, $request_method, [], $request_host);
}

// Route the request
include "backend/router.php";
?>

