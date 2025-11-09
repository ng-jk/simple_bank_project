<?php
// Migration: Make bank_transaction immutable with denormalized fields and audit trail

function migrate_004_up($mysqli) {
    // Start transaction for schema changes
    $mysqli->begin_transaction();

    try {
        // Step 1: Drop existing foreign key constraints
        $mysqli->query("ALTER TABLE bank_transaction DROP FOREIGN KEY bank_transaction_ibfk_1");
        $mysqli->query("ALTER TABLE bank_transaction DROP FOREIGN KEY bank_transaction_ibfk_2");

        // Step 2: Rename related_account_id to destination_account_id for clarity
        $mysqli->query("ALTER TABLE bank_transaction
                       CHANGE COLUMN related_account_id destination_account_id INT NULL");

        // Step 3: Add new columns for immutability and denormalization
        $alter_queries = [
            // Source account information (encrypted snapshot at transaction time)
            "ALTER TABLE bank_transaction
             ADD COLUMN account_number VARCHAR(255) NULL AFTER account_id",

            // Destination account information (for transfers, encrypted snapshot)
            "ALTER TABLE bank_transaction
             ADD COLUMN destination_account_number VARCHAR(255) NULL AFTER destination_account_id",

            // Bill payment fields (for paybill transactions)
            // payee_id is a reference for lookups, snapshots preserve immutability
            "ALTER TABLE bank_transaction
             ADD COLUMN payee_id INT NULL AFTER destination_account_number",

            "ALTER TABLE bank_transaction
             ADD COLUMN payee_name VARCHAR(255) NULL AFTER payee_id",

            "ALTER TABLE bank_transaction
             ADD COLUMN payee_code VARCHAR(50) NULL AFTER payee_name",

            // Audit trail fields
            "ALTER TABLE bank_transaction
             ADD COLUMN ip_address VARCHAR(45) NULL AFTER payee_code",

            "ALTER TABLE bank_transaction
             ADD COLUMN user_agent TEXT NULL AFTER ip_address",

            // Update transaction_type to include paybill
            "ALTER TABLE bank_transaction
             MODIFY COLUMN transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'paybill') NOT NULL"
        ];

        foreach ($alter_queries as $query) {
            if (!$mysqli->query($query)) {
                throw new Exception("Failed to add columns: " . $mysqli->error);
            }
        }

        // Step 4: Populate account_number for existing transactions (if any)
        // Get all existing transactions and update with account numbers
        $result = $mysqli->query("
            SELECT t.transaction_id, t.account_id, t.destination_account_id,
                   a1.account_number as acc_num,
                   a2.account_number as dest_acc_num
            FROM bank_transaction t
            LEFT JOIN bank_account a1 ON t.account_id = a1.account_id
            LEFT JOIN bank_account a2 ON t.destination_account_id = a2.account_id
        ");

        if ($result && $result->num_rows > 0) {
            $update_stmt = $mysqli->prepare("
                UPDATE bank_transaction
                SET account_number = ?,
                    destination_account_number = ?
                WHERE transaction_id = ?
            ");

            while ($row = $result->fetch_assoc()) {
                $update_stmt->bind_param(
                    'ssi',
                    $row['acc_num'],
                    $row['dest_acc_num'],
                    $row['transaction_id']
                );
                $update_stmt->execute();
            }
            $update_stmt->close();
        }

        // Step 5: Make account_number NOT NULL after population
        $mysqli->query("ALTER TABLE bank_transaction MODIFY COLUMN account_number VARCHAR(255) NOT NULL");

        // Step 6: Add new foreign key constraints with RESTRICT (prevents deletion if transactions exist)
        // FKs ensure referential integrity and prevent deletion of referenced records
        $fk_queries = [
            "ALTER TABLE bank_transaction
             ADD CONSTRAINT bank_transaction_ibfk_1
             FOREIGN KEY (account_id) REFERENCES bank_account(account_id) ON DELETE RESTRICT",

            "ALTER TABLE bank_transaction
             ADD CONSTRAINT bank_transaction_ibfk_2
             FOREIGN KEY (destination_account_id) REFERENCES bank_account(account_id) ON DELETE RESTRICT",

            "ALTER TABLE bank_transaction
             ADD CONSTRAINT bank_transaction_ibfk_3
             FOREIGN KEY (payee_id) REFERENCES bill_payee(payee_id) ON DELETE RESTRICT"
        ];

        foreach ($fk_queries as $query) {
            if (!$mysqli->query($query)) {
                throw new Exception("Failed to add foreign keys: " . $mysqli->error);
            }
        }

        // Step 7: Add indexes for better query performance
        $index_queries = [
            "ALTER TABLE bank_transaction ADD INDEX idx_account_created (account_id, created_at)",
            "ALTER TABLE bank_transaction ADD INDEX idx_destination_account (destination_account_id)",
            "ALTER TABLE bank_transaction ADD INDEX idx_payee (payee_id)",
            "ALTER TABLE bank_transaction ADD INDEX idx_transaction_type (transaction_type)"
        ];

        foreach ($index_queries as $query) {
            if (!$mysqli->query($query)) {
                // Ignore if index already exists
                if ($mysqli->errno !== 1061) {
                    throw new Exception("Failed to add index: " . $mysqli->error);
                }
            }
        }

        $mysqli->commit();
        return true;

    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}

function migrate_004_down($mysqli) {
    $mysqli->begin_transaction();

    try {
        // Drop new foreign keys
        $mysqli->query("ALTER TABLE bank_transaction DROP FOREIGN KEY bank_transaction_ibfk_1");
        $mysqli->query("ALTER TABLE bank_transaction DROP FOREIGN KEY bank_transaction_ibfk_2");
        $mysqli->query("ALTER TABLE bank_transaction DROP FOREIGN KEY bank_transaction_ibfk_3");

        // Drop new indexes
        $mysqli->query("ALTER TABLE bank_transaction DROP INDEX idx_account_created");
        $mysqli->query("ALTER TABLE bank_transaction DROP INDEX idx_destination_account");
        $mysqli->query("ALTER TABLE bank_transaction DROP INDEX idx_payee");
        $mysqli->query("ALTER TABLE bank_transaction DROP INDEX idx_transaction_type");

        // Drop new columns
        $drop_columns = [
            "ALTER TABLE bank_transaction DROP COLUMN account_number",
            "ALTER TABLE bank_transaction DROP COLUMN destination_account_number",
            "ALTER TABLE bank_transaction DROP COLUMN payee_id",
            "ALTER TABLE bank_transaction DROP COLUMN payee_name",
            "ALTER TABLE bank_transaction DROP COLUMN payee_code",
            "ALTER TABLE bank_transaction DROP COLUMN ip_address",
            "ALTER TABLE bank_transaction DROP COLUMN user_agent"
        ];

        foreach ($drop_columns as $query) {
            if (!$mysqli->query($query)) {
                throw new Exception("Failed to drop column: " . $mysqli->error);
            }
        }

        // Restore transaction_type enum
        $mysqli->query("ALTER TABLE bank_transaction
                       MODIFY COLUMN transaction_type ENUM('deposit', 'withdrawal', 'transfer') NOT NULL");

        // Rename destination_account_id back to related_account_id
        $mysqli->query("ALTER TABLE bank_transaction
                       CHANGE COLUMN destination_account_id related_account_id INT NULL");

        // Restore old foreign keys with CASCADE
        $mysqli->query("ALTER TABLE bank_transaction
                       ADD CONSTRAINT bank_transaction_ibfk_1
                       FOREIGN KEY (account_id) REFERENCES bank_account(account_id) ON DELETE CASCADE");

        $mysqli->query("ALTER TABLE bank_transaction
                       ADD CONSTRAINT bank_transaction_ibfk_2
                       FOREIGN KEY (related_account_id) REFERENCES bank_account(account_id) ON DELETE SET NULL");

        $mysqli->commit();
        return true;

    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}
?>
