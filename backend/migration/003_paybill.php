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
        account_number VARCHAR(20) NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_category (payee_category),
        FOREIGN KEY (account_number) REFERENCES bank_account(account_number) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }

    // Insert sample bill payees
    $sample_payees = [
        ['TNB', 'TNB001', 'utilities', 'Tenaga Nasional Berhad'],
        ['SYABAS', 'SYABAS001', 'utilities', 'Syarikat Bekalan Air Selangor'],
        ['TM', 'TM001', 'telecommunications', 'Telekom Malaysia'],
        ['MAXIS', 'MAXIS001', 'telecommunications', 'Maxis Communications'],
        ['CELCOM', 'CELCOM001', 'telecommunications', 'Celcom Axiata'],
        ['DIGI', 'DIGI001', 'telecommunications', 'Digi Telecommunications'],
        ['ASTRO', 'ASTRO001', 'telecommunications', 'Astro Malaysia'],
        ['TAKAFUL', 'TAKAFUL001', 'insurance', 'Syarikat Takaful Malaysia'],
        ['PRUDENTIAL', 'PRUDENTIAL001', 'insurance', 'Prudential Assurance Malaysia'],
        ['DBKL', 'DBKL001', 'government', 'Dewan Bandaraya Kuala Lumpur']
    ];

    // First, we need to create bank accounts for these payees
    // Generate unique account numbers for each payee
    $insert_account_stmt = $mysqli->prepare(
        "INSERT INTO bank_account (user_id, account_number, account_type, balance, currency, status)
         VALUES (1, ?, 'checking', 0.00, 'RM', 'active')"
    );

    $insert_payee_stmt = $mysqli->prepare(
        "INSERT INTO bill_payee (payee_code, payee_name, payee_category, account_number, status)
         VALUES (?, ?, ?, ?, 'active')"
    );

    foreach ($sample_payees as $payee) {
        // Generate a unique 16-digit account number for the company
        $account_number = '9' . str_pad(rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);

        // Insert bank account for the company (associated with admin user_id = 1)
        $insert_account_stmt->bind_param('s', $account_number);
        if (!$insert_account_stmt->execute()) {
            // If account number collision, try again with a different number
            $account_number = '9' . str_pad(rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);
            $insert_account_stmt->bind_param('s', $account_number);
            $insert_account_stmt->execute();
        }

        // Insert payee record
        $insert_payee_stmt->bind_param('ssss', $payee[1], $payee[3], $payee[2], $account_number);
        $insert_payee_stmt->execute();
    }

    $insert_account_stmt->close();
    $insert_payee_stmt->close();

    return true;
}

function migrate_003_down($mysqli) {
    $queries = [];

    // Get all account numbers from bill_payee before deleting
    $result = $mysqli->query("SELECT account_number FROM bill_payee");
    $account_numbers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $account_numbers[] = $row['account_number'];
        }
    }

    // Drop bill_payee table
    $queries[] = "DROP TABLE IF EXISTS bill_payee";

    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Rollback failed: " . $mysqli->error);
        }
    }

    // Delete the bank accounts that were created for payees
    if (!empty($account_numbers)) {
        $placeholders = implode(',', array_fill(0, count($account_numbers), '?'));
        $stmt = $mysqli->prepare("DELETE FROM bank_account WHERE account_number IN ($placeholders)");

        if ($stmt) {
            $types = str_repeat('s', count($account_numbers));
            $stmt->bind_param($types, ...$account_numbers);
            $stmt->execute();
            $stmt->close();
        }
    }

    return true;
}
?>
