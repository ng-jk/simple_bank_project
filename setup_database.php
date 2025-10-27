<?php
// Database setup script - run this once to initialize the database
// disabled all rules in htaccess to use it

require_once 'vendor/autoload.php';
require_once 'backend/migration/migrate.php';

// Database configuration
$db_host = 'sql207.infinityfree.com';
$db_user = 'if0_40197402';
$db_pass = 'Zk4Ivol6RiynT';
$db_name = 'if0_40197402_system_database';

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

