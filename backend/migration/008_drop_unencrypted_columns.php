<?php
// Migration: Drop old unencrypted columns after verification
// WARNING: Only run this after thoroughly testing encrypted columns!

function migrate_008_up($mysqli) {
    echo "⚠️  WARNING: This migration will permanently delete unencrypted data!\n";
    echo "Make sure you have:\n";
    echo "  1. ✅ Tested all application functionality\n";
    echo "  2. ✅ Verified encrypted data is readable\n";
    echo "  3. ✅ Created a database backup\n";
    echo "\nDropping old unencrypted columns...\n\n";

    $queries = [];

    // 1. Drop old columns from bank_account
    echo "Dropping unencrypted columns from bank_account...\n";
    $queries[] = "ALTER TABLE bank_account
                  DROP COLUMN balance,
                  DROP COLUMN account_number";

    // Rename encrypted columns to original names
    $queries[] = "ALTER TABLE bank_account
                  CHANGE COLUMN balance_encrypted balance VARBINARY(256),
                  CHANGE COLUMN account_number_encrypted account_number VARBINARY(256)";

    // 2. Drop old columns from bank_transaction
    echo "Dropping unencrypted columns from bank_transaction...\n";
    $queries[] = "ALTER TABLE bank_transaction
                  DROP COLUMN amount,
                  DROP COLUMN balance_after,
                  DROP COLUMN account_number,
                  DROP COLUMN destination_account_number";

    // Rename encrypted columns to original names
    $queries[] = "ALTER TABLE bank_transaction
                  CHANGE COLUMN amount_encrypted amount VARBINARY(256),
                  CHANGE COLUMN balance_after_encrypted balance_after VARBINARY(256),
                  CHANGE COLUMN account_number_encrypted account_number VARBINARY(256),
                  CHANGE COLUMN destination_account_number_encrypted destination_account_number VARBINARY(256)";

    // 3. Drop old columns from otp_verification
    echo "Dropping unencrypted columns from otp_verification...\n";
    $queries[] = "ALTER TABLE otp_verification
                  DROP COLUMN otp_code,
                  DROP COLUMN user_data";

    // Rename encrypted columns to original names
    $queries[] = "ALTER TABLE otp_verification
                  CHANGE COLUMN otp_code_encrypted otp_code VARBINARY(256),
                  CHANGE COLUMN user_data_encrypted user_data VARBINARY(2048)";

    // 4. Drop old column from system_config
    echo "Dropping unencrypted column from system_config...\n";
    $queries[] = "ALTER TABLE system_config
                  DROP COLUMN config_value";

    // Rename encrypted column to original name
    $queries[] = "ALTER TABLE system_config
                  CHANGE COLUMN config_value_encrypted config_value VARBINARY(1024)";

    // Execute all queries
    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Migration failed: " . $mysqli->error);
        }
    }

    echo "\n✅ Old unencrypted columns dropped successfully!\n";
    echo "All sensitive data is now encrypted.\n";

    return true;
}

function migrate_008_down($mysqli) {
    echo "⚠️  Cannot fully reverse this migration!\n";
    echo "This would require re-creating unencrypted columns from encrypted data.\n";
    echo "Please restore from backup if you need to rollback.\n";

    throw new Exception("Migration 008 cannot be automatically rolled back. Restore from backup.");

    return false;
}
?>
