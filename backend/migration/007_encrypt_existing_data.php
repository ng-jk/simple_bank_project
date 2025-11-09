<?php
// Migration: Encrypt existing data in database

function migrate_007_up($mysqli) {
    echo "Encrypting existing data in database...\n";

    // Get encryption key from environment
    $encryption_key = $_ENV['ENCRYPTION_KEY'] ?? null;

    if (!$encryption_key) {
        throw new Exception("ENCRYPTION_KEY not found in environment variables!");
    }

    echo "Using encryption key from environment...\n\n";

    // 1. Encrypt bank_account data
    echo "Encrypting bank_account table...\n";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM bank_account");
    $count = $result->fetch_assoc()['count'];
    echo "  - Found $count accounts to encrypt\n";

    $query = "UPDATE bank_account
              SET balance_encrypted = AES_ENCRYPT(CAST(balance AS CHAR), ?),
                  account_number_encrypted = AES_ENCRYPT(account_number, ?)
              WHERE balance_encrypted IS NULL OR account_number_encrypted IS NULL";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $encryption_key, $encryption_key);
    if (!$stmt->execute()) {
        throw new Exception("Failed to encrypt bank_account: " . $stmt->error);
    }
    echo "  - Encrypted $count accounts successfully\n\n";

    // 2. Encrypt bank_transaction data
    echo "Encrypting bank_transaction table...\n";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM bank_transaction");
    $count = $result->fetch_assoc()['count'];
    echo "  - Found $count transactions to encrypt\n";

    $query = "UPDATE bank_transaction
              SET amount_encrypted = AES_ENCRYPT(CAST(amount AS CHAR), ?),
                  balance_after_encrypted = AES_ENCRYPT(CAST(balance_after AS CHAR), ?),
                  account_number_encrypted = AES_ENCRYPT(account_number, ?),
                  destination_account_number_encrypted = CASE
                      WHEN destination_account_number IS NOT NULL
                      THEN AES_ENCRYPT(destination_account_number, ?)
                      ELSE NULL
                  END
              WHERE amount_encrypted IS NULL";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ssss', $encryption_key, $encryption_key, $encryption_key, $encryption_key);
    if (!$stmt->execute()) {
        throw new Exception("Failed to encrypt bank_transaction: " . $stmt->error);
    }
    echo "  - Encrypted $count transactions successfully\n\n";

    // 3. Encrypt otp_verification data
    echo "Encrypting otp_verification table...\n";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM otp_verification");
    $count = $result->fetch_assoc()['count'];
    echo "  - Found $count OTP records to encrypt\n";

    $query = "UPDATE otp_verification
              SET otp_code_encrypted = AES_ENCRYPT(otp_code, ?),
                  user_data_encrypted = CASE
                      WHEN user_data IS NOT NULL
                      THEN AES_ENCRYPT(user_data, ?)
                      ELSE NULL
                  END
              WHERE otp_code_encrypted IS NULL";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $encryption_key, $encryption_key);
    if (!$stmt->execute()) {
        throw new Exception("Failed to encrypt otp_verification: " . $stmt->error);
    }
    echo "  - Encrypted $count OTP records successfully\n\n";

    // 4. Encrypt system_config data
    echo "Encrypting system_config table...\n";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM system_config");
    $count = $result->fetch_assoc()['count'];
    echo "  - Found $count config entries to encrypt\n";

    $query = "UPDATE system_config
              SET config_value_encrypted = CASE
                  WHEN config_value IS NOT NULL
                  THEN AES_ENCRYPT(config_value, ?)
                  ELSE NULL
              END
              WHERE config_value_encrypted IS NULL";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('s', $encryption_key);
    if (!$stmt->execute()) {
        throw new Exception("Failed to encrypt system_config: " . $stmt->error);
    }
    echo "  - Encrypted $count config entries successfully\n\n";

    echo "✅ All existing data encrypted successfully!\n";
    echo "\nNEXT STEPS:\n";
    echo "1. Update application code to read from encrypted columns\n";
    echo "2. Test all functionality thoroughly\n";
    echo "3. Once verified, run migration 008 to drop old unencrypted columns\n";

    return true;
}

function migrate_007_down($mysqli) {
    echo "Clearing encrypted data (reverting to unencrypted)...\n";

    $queries = [
        "UPDATE system_config SET config_value_encrypted = NULL",
        "UPDATE otp_verification SET otp_code_encrypted = NULL, user_data_encrypted = NULL",
        "UPDATE bank_transaction SET amount_encrypted = NULL, balance_after_encrypted = NULL,
                                     account_number_encrypted = NULL, destination_account_number_encrypted = NULL",
        "UPDATE bank_account SET balance_encrypted = NULL, account_number_encrypted = NULL"
    ];

    foreach ($queries as $query) {
        if (!$mysqli->query($query)) {
            throw new Exception("Rollback failed: " . $mysqli->error);
        }
    }

    echo "Rollback completed - encrypted data cleared.\n";
    return true;
}
?>
