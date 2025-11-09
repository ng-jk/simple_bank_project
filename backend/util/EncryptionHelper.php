<?php
/**
 * Encryption Helper for MySQL AES Encryption
 *
 * This utility provides helper functions for encrypting/decrypting data
 * using MySQL's AES_ENCRYPT and AES_DECRYPT functions.
 *
 * Usage:
 *   - Use SQL helper methods to build encrypted queries
 *   - Encryption key is loaded from ENCRYPTION_KEY environment variable
 */

class EncryptionHelper {

    /**
     * Get encryption key from environment
     * @return string Encryption key
     * @throws Exception if key not found
     */
    public static function getKey() {
        $key = $_ENV['ENCRYPTION_KEY'] ?? null;

        if (!$key) {
            throw new Exception("ENCRYPTION_KEY not found in environment variables");
        }

        return $key;
    }

    /**
     * Generate SQL fragment for AES_ENCRYPT
     *
     * @param string $value_placeholder Placeholder for value (e.g., '?')
     * @param string $key_placeholder Placeholder for key (e.g., '?')
     * @return string SQL fragment
     *
     * Example:
     *   $sql = "INSERT INTO bank_account (balance) VALUES (" . EncryptionHelper::encrypt('?', '?') . ")";
     *   $stmt->bind_param('ds', $balance, $key);
     */
    public static function encrypt($value_placeholder = '?', $key_placeholder = '?') {
        return "AES_ENCRYPT($value_placeholder, $key_placeholder)";
    }

    /**
     * Generate SQL fragment for AES_DECRYPT with alias
     *
     * @param string $column Column name to decrypt
     * @param string $key_placeholder Placeholder for key (e.g., '?')
     * @param string $alias Alias for decrypted column
     * @return string SQL fragment
     *
     * Example:
     *   $sql = "SELECT " . EncryptionHelper::decrypt('balance', '?', 'balance') . " FROM bank_account";
     *   $stmt->bind_param('s', $key);
     */
    public static function decrypt($column, $key_placeholder = '?', $alias = null) {
        $decrypt_expr = "AES_DECRYPT($column, $key_placeholder)";

        if ($alias) {
            return "$decrypt_expr as $alias";
        }

        return $decrypt_expr;
    }

    /**
     * Build WHERE clause for encrypted column
     *
     * @param string $column Column name
     * @param string $operator Comparison operator (=, !=, etc.)
     * @return string SQL WHERE fragment
     *
     * Example:
     *   $sql = "SELECT * FROM users WHERE " . EncryptionHelper::where('email', '=');
     *   // Results in: WHERE email = AES_ENCRYPT(?, ?)
     *   $stmt->bind_param('ss', $email, $key);
     */
    public static function where($column, $operator = '=') {
        return "$column $operator AES_ENCRYPT(?, ?)";
    }

    /**
     * Helper to prepare encrypted INSERT query
     *
     * @param mysqli $mysqli Database connection
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @param array $encrypt_indexes Indexes of parameters to encrypt (0-based)
     * @return mysqli_stmt Prepared statement
     *
     * Example:
     *   $stmt = EncryptionHelper::prepareEncryptedInsert(
     *       $mysqli,
     *       "INSERT INTO bank_account (balance, account_number) VALUES (?, ?)",
     *       ['dd', 100.50, '1234567890'],
     *       [0, 1]  // Both params should be encrypted
     *   );
     */
    public static function prepareEncryptedInsert($mysqli, $query, $params, $encrypt_indexes = []) {
        $key = self::getKey();

        // Add encryption key for each encrypted parameter
        $new_params = [];
        $new_types = '';
        $param_types = $params[0];
        $param_values = array_slice($params, 1);

        $type_index = 0;
        foreach ($param_values as $index => $value) {
            if (in_array($index, $encrypt_indexes)) {
                // Add value and key for encrypted parameters
                $new_params[] = $value;
                $new_params[] = $key;
                $new_types .= $param_types[$type_index] . 's'; // Original type + 's' for key
            } else {
                $new_params[] = $value;
                $new_types .= $param_types[$type_index];
            }
            $type_index++;
        }

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param($new_types, ...$new_params);

        return $stmt;
    }
}
?>
