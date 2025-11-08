#!/usr/bin/env php
<?php
/**
 * Admin User Seeding Script
 * Usage: php seed_admin.php <username> <email> <password>
 */

// Check if script is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Load dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../service/middleware_interface_service.php';
require_once __DIR__ . '/../service/database.php';
require_once __DIR__ . '/../service/database_interface_service.php';
require_once __DIR__ . '/../service/database_service.php';
require_once __DIR__ . '/../service/database_mysql_service.php';
require_once __DIR__ . '/../model/user_model.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Parse command line arguments
if ($argc !== 4) {
    echo "Usage: php seed_admin.php <username> <email> <password>\n";
    echo "Example: php seed_admin.php admin admin@example.com SecurePassword123!\n";
    exit(1);
}

$username = $argv[1];
$email = $argv[2];
$password = $argv[3];

// Validate inputs
if (empty($username) || empty($email) || empty($password)) {
    echo "Error: All fields (username, email, password) are required.\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email format.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Error: Password must be at least 8 characters long.\n";
    exit(1);
}

try {
    // Database configuration from environment
    $db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $db_user = $_ENV['DB_USER'] ?? 'bank_user';
    $db_pass = $_ENV['DB_PASSWORD'] ?? 'bank_password';
    $db_name = $_ENV['DB_NAME'] ?? 'simple_bank_db';

    // Initialize database connection
    $database = new database($db_host, $db_user, $db_pass, $db_name);
    $database_mysql_service = new database_mysql_service($database);
    $database_mysql_service->database_init($database);

    if (!$database_mysql_service->middleware_check()) {
        throw new Exception("Failed to connect to database.");
    }

    $mysqli = $database_mysql_service->get_database_conn();

    if (!$mysqli) {
        throw new Exception("Failed to get database connection.");
    }

    // Initialize user model
    $user_model = new user_model($mysqli);

    // Check if username already exists
    $existing_user = $user_model->get_user_by_username($username);
    if ($existing_user['success']) {
        echo "Error: Username '{$username}' already exists.\n";
        exit(1);
    }

    // Check if email already exists
    $existing_email = $user_model->get_user_by_email($email);
    if ($existing_email['success']) {
        echo "Error: Email '{$email}' already exists.\n";
        exit(1);
    }

    // Create admin user with encrypted password
    $result = $user_model->create_user($username, $email, $password, 'admin');

    if ($result['success']) {
        echo "Success! Admin user created:\n";
        echo "  Username: {$username}\n";
        echo "  Email: {$email}\n";
        echo "  User ID: {$result['user_id']}\n";
        echo "  Role: admin\n";
        echo "\nThe password has been securely encrypted and stored in the database.\n";
        exit(0);
    } else {
        throw new Exception($result['error'] ?? 'Failed to create admin user.');
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
