<?php
// Migration: Add user_id link and audit trail to OTP verification

function migrate_005_up($mysqli) {
    $mysqli->begin_transaction();

    try {
        // Step 1: Add user_id column (nullable for registration flow)
        $mysqli->query("ALTER TABLE otp_verification
                       ADD COLUMN user_id INT NULL AFTER email");

        // Step 2: Add audit trail columns
        $audit_queries = [
            "ALTER TABLE otp_verification
             ADD COLUMN ip_address VARCHAR(45) NULL AFTER user_data",

            "ALTER TABLE otp_verification
             ADD COLUMN user_agent TEXT NULL AFTER ip_address",

            "ALTER TABLE otp_verification
             ADD COLUMN verified_at TIMESTAMP NULL AFTER is_verified"
        ];

        foreach ($audit_queries as $query) {
            if (!$mysqli->query($query)) {
                throw new Exception("Failed to add audit columns: " . $mysqli->error);
            }
        }

        // Step 3: Add foreign key constraint with RESTRICT
        // This prevents deletion of users who have OTP history
        $mysqli->query("ALTER TABLE otp_verification
                       ADD CONSTRAINT otp_verification_ibfk_1
                       FOREIGN KEY (user_id) REFERENCES system_user(user_id) ON DELETE RESTRICT");

        // Step 4: Add indexes for better query performance
        $index_queries = [
            "ALTER TABLE otp_verification ADD INDEX idx_user_purpose (user_id, purpose)",
            "ALTER TABLE otp_verification ADD INDEX idx_verified_at (verified_at)"
        ];

        foreach ($index_queries as $query) {
            if (!$mysqli->query($query)) {
                // Ignore if index already exists
                if ($mysqli->errno !== 1061) {
                    throw new Exception("Failed to add index: " . $mysqli->error);
                }
            }
        }

        // Step 5: Update purpose enum to include more options
        $mysqli->query("ALTER TABLE otp_verification
                       MODIFY COLUMN purpose ENUM('registration', 'login', 'password_reset', 'transaction', 'account_change') NOT NULL");

        $mysqli->commit();
        return true;

    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}

function migrate_005_down($mysqli) {
    $mysqli->begin_transaction();

    try {
        // Drop foreign key
        $mysqli->query("ALTER TABLE otp_verification DROP FOREIGN KEY otp_verification_ibfk_1");

        // Drop indexes
        $mysqli->query("ALTER TABLE otp_verification DROP INDEX idx_user_purpose");
        $mysqli->query("ALTER TABLE otp_verification DROP INDEX idx_verified_at");

        // Drop new columns
        $drop_columns = [
            "ALTER TABLE otp_verification DROP COLUMN verified_at",
            "ALTER TABLE otp_verification DROP COLUMN user_agent",
            "ALTER TABLE otp_verification DROP COLUMN ip_address",
            "ALTER TABLE otp_verification DROP COLUMN user_id"
        ];

        foreach ($drop_columns as $query) {
            if (!$mysqli->query($query)) {
                throw new Exception("Failed to drop column: " . $mysqli->error);
            }
        }

        // Restore original purpose enum
        $mysqli->query("ALTER TABLE otp_verification
                       MODIFY COLUMN purpose ENUM('registration', 'login', 'password_reset') NOT NULL");

        $mysqli->commit();
        return true;

    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}
?>
