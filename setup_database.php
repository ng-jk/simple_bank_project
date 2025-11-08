<?php
// Database setup script - run this once to initialize the database

require_once 'vendor/autoload.php';
require_once 'backend/migration/migrate.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database configuration from environment
$db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db_user = $_ENV['DB_USER'] ?? 'bank_user';
$db_pass = $_ENV['DB_PASSWORD'] ?? 'bank_password';
$db_name = $_ENV['DB_NAME'] ?? 'simple_bank_db';

echo "Connecting to database...\n";

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "Connected successfully!\n\n";
echo "Running migrations...\n";

if (run_migrations($mysqli)) {
    echo "\nDatabase setup completed successfully!\n";
    echo "\nYou can now access the application.\n";
    echo "Default admin account will need to be created through registration.\n";
} else {
    echo "\nDatabase setup failed!\n";
}

$mysqli->close();
?>

