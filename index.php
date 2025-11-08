<?php

// Main entry point - handles all requests
require_once 'vendor/autoload.php';
require_once 'backend/function/helper.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set error reporting based on environment
$appDebug = $_ENV['APP_DEBUG'] ?? 'false';
error_reporting(E_ALL);
ini_set('display_errors', $appDebug === 'true' ? '1' : '0');

// Database configuration from environment
$db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db_user = $_ENV['DB_USER'] ?? 'bank_user';
$db_pass = $_ENV['DB_PASSWORD'] ?? 'bank_password';
$db_name = $_ENV['DB_NAME'] ?? 'simple_bank_db';

// HTTP request information
$request_uri = parse_url("/" . ($_GET['_route'] ?? ""), PHP_URL_PATH);

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

    // Get JWT key from environment variable
    $jwt_key = $_ENV['JWT_SECRET_KEY'] ?? null;

    if (empty($jwt_key)) {
        // Throw error if JWT_SECRET_KEY is not set in production
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            throw new Exception('JWT_SECRET_KEY must be set in .env file for production');
        }
        // Use insecure default for development only
        $jwt_key = base64_encode('development_only_insecure_key');
        error_log('WARNING: Using insecure JWT key. Set JWT_SECRET_KEY in .env file!');
    }

    $config = new config($jwt_key);
} catch (Exception $e) {
    // Fatal error - JWT key is required
    http_response_code(500);
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        die('Configuration Error: ' . $e->getMessage());
    } else {
        die('Service Unavailable');
    }
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

    $status = new status($permission, $is_login, $request_uri, $request_method, $request_host, $user_info);

    // Try to verify JWT token
    $jwt_data = new jwt($status, $config, '');
    $jwt_verify_service = new jwt_verify_service($jwt_data, $config->JWT_DAILY_REFRESH_KEY);

    if ($jwt_verify_service->middleware_check()) {
        $status = $jwt_verify_service->get_jwt_data()->status;
    }
} catch (Exception $e) {
    // Continue with guest status if JWT verification fails
    $status = new status('guest', false, $request_uri, $request_method, $request_host, []);
}

// Route the request
include "backend/router.php";
