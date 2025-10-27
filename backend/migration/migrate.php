<?php
// Migration runner script

function run_migrations($mysqli) {
    // Create migrations tracking table
    $mysqli->query("CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Get list of migration files
    $migration_dir = __DIR__;
    $migration_files = glob($migration_dir . '/[0-9]*_*.php');
    sort($migration_files);
    
    foreach ($migration_files as $file) {
        $migration_name = basename($file, '.php');
        
        // Check if migration already executed
        $stmt = $mysqli->prepare("SELECT id FROM migrations WHERE migration_name = ?");
        $stmt->bind_param('s', $migration_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "Migration $migration_name already executed, skipping...\n";
            continue;
        }
        
        // Execute migration
        echo "Running migration: $migration_name\n";
        require_once $file;
        
        $up_function = 'migrate_' . explode('_', $migration_name)[0] . '_up';
        
        if (function_exists($up_function)) {
            try {
                $mysqli->begin_transaction();
                $up_function($mysqli);
                
                // Record migration
                $stmt = $mysqli->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
                $stmt->bind_param('s', $migration_name);
                $stmt->execute();
                
                $mysqli->commit();
                echo "Migration $migration_name completed successfully\n";
            } catch (Exception $e) {
                $mysqli->rollback();
                echo "Migration $migration_name failed: " . $e->getMessage() . "\n";
                return false;
            }
        }
    }
    
    return true;
}
?>

