<?php
require_once __DIR__ . '/../util/EncryptionHelper.php';

class account_model {
    private $mysqli;
    private $encryption_key;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->encryption_key = EncryptionHelper::getKey();
    }

    private function generate_account_number() {
        // Generate a unique 16-digit account number
        do {
            $account_number = '';
            for ($i = 0; $i < 16; $i++) {
                $account_number .= rand(0, 9);
            }

            // Check if account number already exists (search encrypted column)
            $stmt = $this->mysqli->prepare("
                SELECT account_id FROM bank_account
                WHERE account_number = AES_ENCRYPT(?, ?)
            ");
            $stmt->bind_param('ss', $account_number, $this->encryption_key);
            $stmt->execute();
            $result = $stmt->get_result();
        } while ($result->num_rows > 0);

        return $account_number;
    }
    
    public function create_account($user_id, $account_type = 'savings', $currency = 'RM') {
        $account_number = $this->generate_account_number();

        if ($account_number === null) {
            return ['success' => false, 'error' => 'Failed to generate account number'];
        }

        $initial_balance = 0.00;
        $balance_str = (string)$initial_balance;

        $stmt = $this->mysqli->prepare("
            INSERT INTO bank_account (
                user_id,
                account_number,
                account_type,
                currency,
                balance
            ) VALUES (
                ?,
                AES_ENCRYPT(?, ?),
                ?,
                ?,
                AES_ENCRYPT(?, ?)
            )
        ");

        $stmt->bind_param(
            'issssss',
            $user_id,
            $account_number, $this->encryption_key,
            $account_type,
            $currency,
            $balance_str, $this->encryption_key
        );

        if ($stmt->execute()) {
            $account_id = $stmt->insert_id;
            return ['success' => true, 'account_id' => $account_id, 'account_number' => $account_number];
        }

        return ['success' => false, 'error' => $stmt->error];
    }
    
    public function get_account_by_id($account_id) {
        $stmt = $this->mysqli->prepare("
            SELECT
                account_id,
                user_id,
                AES_DECRYPT(account_number, ?) as account_number,
                account_type,
                AES_DECRYPT(balance, ?) as balance,
                currency,
                status,
                created_at,
                updated_at
            FROM bank_account
            WHERE account_id = ?
        ");
        $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $account_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Convert decrypted balance back to float
            $row['balance'] = (float)$row['balance'];
            return ['success' => true, 'account' => $row];
        }

        return ['success' => false, 'error' => 'Account not found'];
    }

    public function get_account_by_number($account_number) {
        $stmt = $this->mysqli->prepare("
            SELECT
                account_id,
                user_id,
                AES_DECRYPT(account_number, ?) as account_number,
                account_type,
                AES_DECRYPT(balance, ?) as balance,
                currency,
                status,
                created_at,
                updated_at
            FROM bank_account
            WHERE account_number = AES_ENCRYPT(?, ?)
        ");
        $stmt->bind_param(
            'ssss',
            $this->encryption_key,  // For AES_DECRYPT account_number
            $this->encryption_key,  // For AES_DECRYPT balance
            $account_number,        // Value to search
            $this->encryption_key   // For AES_ENCRYPT
        );
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $row['balance'] = (float)$row['balance'];
            return ['success' => true, 'account' => $row];
        }

        return ['success' => false, 'error' => 'Account not found'];
    }

    public function get_user_accounts($user_id) {
        $stmt = $this->mysqli->prepare("
            SELECT
                account_id,
                user_id,
                AES_DECRYPT(account_number, ?) as account_number,
                account_type,
                AES_DECRYPT(balance, ?) as balance,
                currency,
                status,
                created_at,
                updated_at
            FROM bank_account
            WHERE user_id = ?
        ");
        $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $row['balance'] = (float)$row['balance'];
            $accounts[] = $row;
        }

        return ['success' => true, 'accounts' => $accounts];
    }
    
    public function update_balance($account_id, $new_balance) {
        $balance_str = (string)$new_balance;

        $stmt = $this->mysqli->prepare("
            UPDATE bank_account
            SET balance = AES_ENCRYPT(?, ?)
            WHERE account_id = ?
        ");
        $stmt->bind_param('ssi', $balance_str, $this->encryption_key, $account_id);

        if ($stmt->execute()) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => $stmt->error];
    }
    
    public function close_account($account_id) {
        // Check if balance is zero
        $account_result = $this->get_account_by_id($account_id);
        if (!$account_result['success']) {
            return $account_result;
        }
        
        if ($account_result['account']['balance'] != 0) {
            return ['success' => false, 'error' => 'Cannot close account with non-zero balance'];
        }
        
        $stmt = $this->mysqli->prepare("UPDATE bank_account SET status = 'closed' WHERE account_id = ?");
        $stmt->bind_param('i', $account_id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
}
?>

