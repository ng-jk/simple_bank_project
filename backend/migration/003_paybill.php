<?php
// Migration: Add bill payee table for paybill feature

function migrate_003_up($mysqli) {
    $queries = [];

    // Create bill_payee table
    $queries[] = "CREATE TABLE IF NOT EXISTS bill_payee (
        payee_id INT AUTO_INCREMENT PRIMARY KEY,
        payee_name VARCHAR(100) NOT NULL,
        payee_code VARCHAR(20) UNIQUE NOT NULL,
        payee_category ENUM('utilities', 'telecommunications', 'insurance', 'credit_card', 'government', 'education', 'other') NOT NULL DEFAULT 'other',
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_category (payee_category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }

    // Insert sample bill payees
    $sample_payees = [
        ['TNB001', 'Tenaga Nasional Berhad', 'utilities'],
        ['SYABAS001', 'Syarikat Bekalan Air Selangor', 'utilities'],
        ['TM001', 'Telekom Malaysia', 'telecommunications'],
        ['MAXIS001', 'Maxis Communications', 'telecommunications'],
        ['CELCOM001', 'Celcom Axiata', 'telecommunications'],
        ['DIGI001', 'Digi Telecommunications', 'telecommunications'],
        ['ASTRO001', 'Astro Malaysia', 'telecommunications'],
        ['TAKAFUL001', 'Syarikat Takaful Malaysia', 'insurance'],
        ['PRUDENTIAL001', 'Prudential Assurance Malaysia', 'insurance'],
        ['DBKL001', 'Dewan Bandaraya Kuala Lumpur', 'government']
    ];

    $insert_payee_stmt = $mysqli->prepare(
        "INSERT INTO bill_payee (payee_code, payee_name, payee_category, status)
         VALUES (?, ?, ?, 'active')"
    );

    foreach ($sample_payees as $payee) {
        $insert_payee_stmt->bind_param('sss', $payee[0], $payee[1], $payee[2]);
        $insert_payee_stmt->execute();
    }

    $insert_payee_stmt->close();

    return true;
}

function migrate_003_down($mysqli) {
    // Drop bill_payee table
    $query = "DROP TABLE IF EXISTS bill_payee";

    if (!$mysqli->query($query)) {
        throw new Exception("Rollback failed: " . $mysqli->error);
    }

    return true;
}
?>
