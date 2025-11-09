<?php
// Migration: Add encryption to critical banking fields

function migrate_006_up($mysqli) {
    echo "Adding encrypted columns for critical banking data...\n";

    $queries = [];

    // 1. Bank Account - Encrypt balance and account number
    echo "  - Adding encrypted columns to bank_account table\n";
    $queries[] = "ALTER TABLE bank_account
                  ADD COLUMN balance_encrypted VARBINARY(256) AFTER balance,
                  ADD COLUMN account_number_encrypted VARBINARY(256) AFTER account_number";

    // 2. Bank Transaction - Encrypt amounts and account numbers
    echo "  - Adding encrypted columns to bank_transaction table\n";
    $queries[] = "ALTER TABLE bank_transaction
                  ADD COLUMN amount_encrypted VARBINARY(256) AFTER amount,
                  ADD COLUMN balance_after_encrypted VARBINARY(256) AFTER balance_after,
                  ADD COLUMN account_number_encrypted VARBINARY(256) AFTER account_number,
                  ADD COLUMN destination_account_number_encrypted VARBINARY(256) AFTER destination_account_number";

    // 3. OTP Verification - Encrypt OTP code and user data
    echo "  - Adding encrypted columns to otp_verification table\n";
    $queries[] = "ALTER TABLE otp_verification
                  ADD COLUMN otp_code_encrypted VARBINARY(256) AFTER otp_code,
                  ADD COLUMN user_data_encrypted VARBINARY(2048) AFTER user_data";

    // 4. System Config - Encrypt sensitive config values
    echo "  - Adding encrypted column to system_config table\n";
    $queries[] = "ALTER TABLE system_config
                  ADD COLUMN config_value_encrypted VARBINARY(1024) AFTER config_value";

    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }

    echo "\nEncrypted columns added successfully!\n";
    echo "\nNEXT STEPS:\n";
    echo "1. Run migration 007 to encrypt existing data\n";
    echo "2. Update application code to use encrypted columns\n";
    echo "3. Verify encryption works correctly\n";
    echo "4. Run migration 008 to drop old unencrypted columns\n";

    return true;
}

function migrate_006_down($mysqli) {
    echo "Removing encrypted columns...\n";

    $queries = [
        "ALTER TABLE system_config DROP COLUMN config_value_encrypted",
        "ALTER TABLE otp_verification DROP COLUMN user_data_encrypted",
        "ALTER TABLE otp_verification DROP COLUMN otp_code_encrypted",
        "ALTER TABLE bank_transaction DROP COLUMN destination_account_number_encrypted",
        "ALTER TABLE bank_transaction DROP COLUMN account_number_encrypted",
        "ALTER TABLE bank_transaction DROP COLUMN balance_after_encrypted",
        "ALTER TABLE bank_transaction DROP COLUMN amount_encrypted",
        "ALTER TABLE bank_account DROP COLUMN account_number_encrypted",
        "ALTER TABLE bank_account DROP COLUMN balance_encrypted"
    ];

    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Rollback failed: " . $mysqli->error);
        }
    }

    echo "Rollback completed.\n";
    return true;
}
?>
