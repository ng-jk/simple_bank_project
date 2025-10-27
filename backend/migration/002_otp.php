<?php
// Migration: Add OTP verification table

function migrate_002_up($mysqli) {
    $queries = [];
    
    // Create OTP verification table
    $queries[] = "CREATE TABLE IF NOT EXISTS otp_verification (
        otp_id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        purpose ENUM('registration', 'login', 'password_reset') NOT NULL,
        user_data TEXT,
        is_verified BOOLEAN DEFAULT FALSE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_purpose (email, purpose),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Add email verification status to system_user
    $queries[] = "ALTER TABLE system_user 
                  ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER user_email,
                  ADD COLUMN email_verified_at TIMESTAMP NULL AFTER email_verified";
    
    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }
    
    return true;
}

function migrate_002_down($mysqli) {
    $queries = [
        "ALTER TABLE system_user DROP COLUMN email_verified_at",
        "ALTER TABLE system_user DROP COLUMN email_verified",
        "DROP TABLE IF EXISTS otp_verification"
    ];
    
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Rollback failed: " . $mysqli->error);
        }
    }
    
    return true;
}
?>

