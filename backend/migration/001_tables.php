<?php
// Migration: Create initial database tables

function migrate_001_up($mysqli) {
    $queries = [];
    
    // Create system_config table
    $queries[] = "CREATE TABLE IF NOT EXISTS system_config (
        config_id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(255) NOT NULL UNIQUE,
        config_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Create system_user table
    $queries[] = "CREATE TABLE IF NOT EXISTS system_user (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(100) NOT NULL UNIQUE,
        user_email VARCHAR(255) NOT NULL UNIQUE,
        user_password VARCHAR(255) NOT NULL,
        user_role ENUM('user', 'admin') DEFAULT 'user',
        user_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Create bank_account table
    $queries[] = "CREATE TABLE IF NOT EXISTS bank_account (
        account_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_number VARCHAR(20) NOT NULL UNIQUE,
        account_type ENUM('checking', 'savings') DEFAULT 'checking',
        balance DECIMAL(15, 2) DEFAULT 0.00,
        currency VARCHAR(3) DEFAULT 'RM',
        status ENUM('active', 'closed', 'frozen') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES system_user(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Create transaction table
    $queries[] = "CREATE TABLE IF NOT EXISTS bank_transaction (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        transaction_type ENUM('deposit', 'withdrawal', 'transfer') NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        balance_after DECIMAL(15, 2) NOT NULL,
        description TEXT,
        reference_number VARCHAR(50) UNIQUE,
        related_account_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES bank_account(account_id) ON DELETE CASCADE,
        FOREIGN KEY (related_account_id) REFERENCES bank_account(account_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }
    
    // Insert default config values
    $config_inserts = [
        ['JWT_DAILY_REFRESH_KEY', base64_encode(random_bytes(32))],
        ['SYSTEM_NAME', 'Simple Bank'],
        ['SYSTEM_VERSION', '1.0.0']
    ];
    
    $stmt = $mysqli->prepare("INSERT IGNORE INTO system_config (config_key, config_value) VALUES (?, ?)");
    foreach ($config_inserts as $config) {
        $stmt->bind_param('ss', $config[0], $config[1]);
        $stmt->execute();
    }
    $stmt->close();
    
    return true;
}

function migrate_001_down($mysqli) {
    $queries = [
        "DROP TABLE IF EXISTS bank_transaction",
        "DROP TABLE IF EXISTS bank_account",
        "DROP TABLE IF EXISTS system_user",
        "DROP TABLE IF EXISTS system_config"
    ];
    
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Rollback failed: " . $mysqli->error);
        }
    }
    
    return true;
}
?>

