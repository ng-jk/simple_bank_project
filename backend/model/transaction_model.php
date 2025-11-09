<?php
require_once __DIR__ . '/../util/EncryptionHelper.php';

class transaction_model {
    private $mysqli;
    private $encryption_key;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->encryption_key = EncryptionHelper::getKey();
    }

    private function generate_reference_number() {
        // Use microtime for better uniqueness and larger random component
        // Format: TXN{timestamp}{microseconds}{random}
        $microtime = microtime(true);
        $timestamp = floor($microtime);
        $microseconds = str_pad((int)(($microtime - $timestamp) * 1000000), 6, '0', STR_PAD_LEFT);
        $random = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        return 'TXN' . $timestamp . $microseconds . $random;
    }
    
    public function deposit($account_id, $amount, $description = '', $ip_address = null, $user_agent = null) {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be positive'];
        }

        $this->mysqli->begin_transaction();

        try {
            // Get current balance and account number (decrypt from encrypted columns)
            $stmt = $this->mysqli->prepare("
                SELECT
                    AES_DECRYPT(balance, ?) as balance,
                    AES_DECRYPT(account_number, ?) as account_number
                FROM bank_account
                WHERE account_id = ? AND status = 'active'
                FOR UPDATE
            ");
            $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Account not found or inactive');
            }

            $row = $result->fetch_assoc();
            $current_balance = (float)$row['balance'];
            $account_number = $row['account_number'];
            $new_balance = $current_balance + $amount;

            // Update balance (encrypt)
            $new_balance_str = (string)$new_balance;
            $stmt = $this->mysqli->prepare("
                UPDATE bank_account
                SET balance = AES_ENCRYPT(?, ?)
                WHERE account_id = ?
            ");
            $stmt->bind_param('ssi', $new_balance_str, $this->encryption_key, $account_id);
            $stmt->execute();

            // Record transaction with encrypted data
            $reference = $this->generate_reference_number();
            $type = 'deposit';
            $amount_str = (string)$amount;
            $new_balance_str = (string)$new_balance;

            $stmt = $this->mysqli->prepare("
                INSERT INTO bank_transaction (
                    account_id,
                    account_number,
                    transaction_type,
                    amount,
                    balance_after,
                    description,
                    reference_number,
                    ip_address,
                    user_agent
                ) VALUES (
                    ?,
                    AES_ENCRYPT(?, ?),
                    ?,
                    AES_ENCRYPT(?, ?),
                    AES_ENCRYPT(?, ?),
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");
            $stmt->bind_param(
                'isssssssssss',
                $account_id,
                $account_number, $this->encryption_key,
                $type,
                $amount_str, $this->encryption_key,
                $new_balance_str, $this->encryption_key,
                $description,
                $reference,
                $ip_address,
                $user_agent
            );
            $stmt->execute();

            $transaction_id = $stmt->insert_id;

            $this->mysqli->commit();

            return ['success' => true, 'transaction_id' => $transaction_id, 'new_balance' => $new_balance, 'reference' => $reference];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function withdraw($account_id, $amount, $description = '', $payee_id = null, $payee_name = null, $payee_code = null, $ip_address = null, $user_agent = null) {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be positive'];
        }

        $this->mysqli->begin_transaction();

        try {
            // Get current balance and account number (decrypt)
            $stmt = $this->mysqli->prepare("
                SELECT
                    AES_DECRYPT(balance, ?) as balance,
                    AES_DECRYPT(account_number, ?) as account_number
                FROM bank_account
                WHERE account_id = ? AND status = 'active'
                FOR UPDATE
            ");
            $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Account not found or inactive');
            }

            $row = $result->fetch_assoc();
            $current_balance = (float)$row['balance'];
            $account_number = $row['account_number'];

            if ($current_balance < $amount) {
                throw new Exception('Insufficient funds');
            }

            $new_balance = $current_balance - $amount;

            // Update balance (encrypt)
            $new_balance_str = (string)$new_balance;
            $stmt = $this->mysqli->prepare("
                UPDATE bank_account
                SET balance = AES_ENCRYPT(?, ?)
                WHERE account_id = ?
            ");
            $stmt->bind_param('ssi', $new_balance_str, $this->encryption_key, $account_id);
            $stmt->execute();

            // Record transaction (store as negative amount, encrypted)
            $reference = $this->generate_reference_number();
            $type = 'withdrawal';
            $amount_withdrawn = -$amount;
            $amount_str = (string)$amount_withdrawn;
            $new_balance_str = (string)$new_balance;

            $stmt = $this->mysqli->prepare("
                INSERT INTO bank_transaction (
                    account_id,
                    account_number,
                    transaction_type,
                    amount,
                    balance_after,
                    description,
                    reference_number,
                    payee_id,
                    payee_name,
                    payee_code,
                    ip_address,
                    user_agent
                ) VALUES (
                    ?,
                    AES_ENCRYPT(?, ?),
                    ?,
                    AES_ENCRYPT(?, ?),
                    AES_ENCRYPT(?, ?),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");
            $stmt->bind_param(
                'issssssssisssss',
                $account_id,
                $account_number, $this->encryption_key,
                $type,
                $amount_str, $this->encryption_key,
                $new_balance_str, $this->encryption_key,
                $description,
                $reference,
                $payee_id,
                $payee_name,
                $payee_code,
                $ip_address,
                $user_agent
            );
            $stmt->execute();

            $transaction_id = $stmt->insert_id;

            $this->mysqli->commit();

            return ['success' => true, 'transaction_id' => $transaction_id, 'new_balance' => $new_balance, 'reference' => $reference];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function transfer($from_account_id, $to_account_id, $amount, $description = '', $ip_address = null, $user_agent = null) {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be positive'];
        }

        if ($from_account_id === $to_account_id) {
            return ['success' => false, 'error' => 'Cannot transfer to the same account'];
        }

        $this->mysqli->begin_transaction();

        try {
            // Get both account balances and account numbers (decrypt)
            $stmt = $this->mysqli->prepare("
                SELECT
                    AES_DECRYPT(balance, ?) as balance,
                    AES_DECRYPT(account_number, ?) as account_number
                FROM bank_account
                WHERE account_id = ? AND status = 'active'
                FOR UPDATE
            ");
            $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $from_account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Source account not found or inactive');
            }

            $from_row = $result->fetch_assoc();
            $from_balance = (float)$from_row['balance'];
            $from_account_number = $from_row['account_number'];

            if ($from_balance < $amount) {
                throw new Exception('Insufficient funds');
            }

            $stmt = $this->mysqli->prepare("
                SELECT
                    AES_DECRYPT(balance, ?) as balance,
                    AES_DECRYPT(account_number, ?) as account_number
                FROM bank_account
                WHERE account_id = ? AND status = 'active'
                FOR UPDATE
            ");
            $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $to_account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Destination account not found or inactive');
            }

            $to_row = $result->fetch_assoc();
            $to_balance = (float)$to_row['balance'];
            $to_account_number = $to_row['account_number'];

            // Update balances (encrypt)
            $new_from_balance = $from_balance - $amount;
            $new_to_balance = $to_balance + $amount;

            $new_from_balance_str = (string)$new_from_balance;
            $stmt = $this->mysqli->prepare("
                UPDATE bank_account
                SET balance = AES_ENCRYPT(?, ?)
                WHERE account_id = ?
            ");
            $stmt->bind_param('ssi', $new_from_balance_str, $this->encryption_key, $from_account_id);
            $stmt->execute();

            $new_to_balance_str = (string)$new_to_balance;
            $stmt = $this->mysqli->prepare("
                UPDATE bank_account
                SET balance = AES_ENCRYPT(?, ?)
                WHERE account_id = ?
            ");
            $stmt->bind_param('ssi', $new_to_balance_str, $this->encryption_key, $to_account_id);
            $stmt->execute();

            // Record ONE transaction for the transfer with encrypted data
            $type = 'transfer';
            $reference = $this->generate_reference_number();

            // Store as negative amount (money leaving sender)
            $amount_transfer = -$amount;
            $amount_str = (string)$amount_transfer;
            $new_from_balance_str = (string)$new_from_balance;

            $stmt = $this->mysqli->prepare("
                INSERT INTO bank_transaction (
                    account_id,
                    account_number,
                    transaction_type,
                    amount,
                    balance_after,
                    description,
                    reference_number,
                    destination_account_id,
                    destination_account_number,
                    ip_address,
                    user_agent
                ) VALUES (
                    ?,
                    AES_ENCRYPT(?, ?),
                    ?,
                    AES_ENCRYPT(?, ?),
                    AES_ENCRYPT(?, ?),
                    ?,
                    ?,
                    ?,
                    AES_ENCRYPT(?, ?),
                    ?,
                    ?
                )
            ");
            $stmt->bind_param(
                'issssssssisssss',
                $from_account_id,
                $from_account_number, $this->encryption_key,
                $type,
                $amount_str, $this->encryption_key,
                $new_from_balance_str, $this->encryption_key,
                $description,
                $reference,
                $to_account_id,
                $to_account_number, $this->encryption_key,
                $ip_address,
                $user_agent
            );
            $stmt->execute();

            $this->mysqli->commit();

            return ['success' => true, 'reference' => $reference, 'new_balance' => $new_from_balance];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function paybill($account_id, $amount, $payee_id, $payee_name, $payee_code, $description = '', $ip_address = null, $user_agent = null) {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be positive'];
        }

        $this->mysqli->begin_transaction();

        try {
            // Get current balance and account number (decrypt)
            $stmt = $this->mysqli->prepare("
                SELECT
                    AES_DECRYPT(balance, ?) as balance,
                    AES_DECRYPT(account_number, ?) as account_number
                FROM bank_account
                WHERE account_id = ? AND status = 'active'
                FOR UPDATE
            ");
            $stmt->bind_param('ssi', $this->encryption_key, $this->encryption_key, $account_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Account not found or inactive');
            }

            $row = $result->fetch_assoc();
            $current_balance = (float)$row['balance'];
            $account_number = $row['account_number'];

            if ($current_balance < $amount) {
                throw new Exception('Insufficient funds');
            }

            $new_balance = $current_balance - $amount;

            // Update balance (encrypt)
            $new_balance_str = (string)$new_balance;
            $stmt = $this->mysqli->prepare("
                UPDATE bank_account
                SET balance = AES_ENCRYPT(?, ?)
                WHERE account_id = ?
            ");
            $stmt->bind_param('ssi', $new_balance_str, $this->encryption_key, $account_id);
            $stmt->execute();

            // Record transaction (store as negative amount, encrypted)
            // Store payee information for immutability
            $reference = $this->generate_reference_number();
            $type = 'paybill';
            $amount_withdrawn = -$amount;
            $amount_str = (string)$amount_withdrawn;
            $new_balance_str = (string)$new_balance;

            $stmt = $this->mysqli->prepare("
                INSERT INTO bank_transaction (
                    account_id,
                    account_number,
                    transaction_type,
                    amount,
                    balance_after,
                    description,
                    reference_number,
                    payee_id,
                    payee_name,
                    payee_code,
                    ip_address,
                    user_agent
                ) VALUES (
                    ?,
                    AES_ENCRYPT(?, ?),
                    ?,
                    AES_ENCRYPT(?, ?),
                    AES_ENCRYPT(?, ?),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");
            $stmt->bind_param(
                'issssssssisssss',
                $account_id,
                $account_number, $this->encryption_key,
                $type,
                $amount_str, $this->encryption_key,
                $new_balance_str, $this->encryption_key,
                $description,
                $reference,
                $payee_id,
                $payee_name,
                $payee_code,
                $ip_address,
                $user_agent
            );
            $stmt->execute();

            $transaction_id = $stmt->insert_id;

            $this->mysqli->commit();

            return ['success' => true, 'transaction_id' => $transaction_id, 'new_balance' => $new_balance, 'reference' => $reference];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function get_account_transactions($account_id, $limit = 50, $offset = 0) {
        // Get transactions where account is EITHER sender OR receiver (decrypt encrypted fields)
        $stmt = $this->mysqli->prepare("
            SELECT
                transaction_id,
                account_id,
                AES_DECRYPT(account_number, ?) as account_number,
                transaction_type,
                AES_DECRYPT(amount, ?) as amount,
                AES_DECRYPT(balance_after, ?) as balance_after,
                description,
                reference_number,
                destination_account_id,
                AES_DECRYPT(destination_account_number, ?) as destination_account_number,
                payee_id,
                payee_name,
                payee_code,
                ip_address,
                user_agent,
                created_at
            FROM bank_transaction
            WHERE account_id = ? OR destination_account_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ssssiiii',
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $account_id,
            $account_id,
            $limit,
            $offset
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            // Convert decrypted values to proper types
            $row['amount'] = (float)$row['amount'];
            $row['balance_after'] = (float)$row['balance_after'];

            // If this account is the receiver (in destination_account_id), flip the amount sign
            if ($row['destination_account_id'] == $account_id && $row['transaction_type'] == 'transfer') {
                $row['amount'] = abs($row['amount']); // Make it positive (incoming)
            }
            $transactions[] = $row;
        }

        return ['success' => true, 'transactions' => $transactions];
    }
    
    public function get_transaction_by_reference($reference_number) {
        $stmt = $this->mysqli->prepare("
            SELECT
                transaction_id,
                account_id,
                AES_DECRYPT(account_number, ?) as account_number,
                transaction_type,
                AES_DECRYPT(amount, ?) as amount,
                AES_DECRYPT(balance_after, ?) as balance_after,
                description,
                reference_number,
                destination_account_id,
                AES_DECRYPT(destination_account_number, ?) as destination_account_number,
                payee_id,
                payee_name,
                payee_code,
                ip_address,
                user_agent,
                created_at
            FROM bank_transaction
            WHERE reference_number = ?
        ");
        $stmt->bind_param('sssss',
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $reference_number
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $row['amount'] = (float)$row['amount'];
            $row['balance_after'] = (float)$row['balance_after'];
            $transactions[] = $row;
        }

        if (count($transactions) > 0) {
            return ['success' => true, 'transactions' => $transactions];
        }

        return ['success' => false, 'error' => 'Transaction not found'];
    }

    public function get_account_transactions_by_date_range($account_id, $start_date, $end_date) {
        // Get transactions within date range for statement generation (decrypt)
        $stmt = $this->mysqli->prepare("
            SELECT
                transaction_id,
                account_id,
                AES_DECRYPT(account_number, ?) as account_number,
                transaction_type,
                AES_DECRYPT(amount, ?) as amount,
                AES_DECRYPT(balance_after, ?) as balance_after,
                description,
                reference_number,
                destination_account_id,
                AES_DECRYPT(destination_account_number, ?) as destination_account_number,
                payee_id,
                payee_name,
                payee_code,
                ip_address,
                user_agent,
                created_at
            FROM bank_transaction
            WHERE (account_id = ? OR destination_account_id = ?)
            AND created_at >= ?
            AND created_at <= ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param('ssssiiss',
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $this->encryption_key,
            $account_id,
            $account_id,
            $start_date,
            $end_date
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $row['amount'] = (float)$row['amount'];
            $row['balance_after'] = (float)$row['balance_after'];

            // If this account is the receiver (in destination_account_id), flip the amount sign
            if ($row['destination_account_id'] == $account_id && $row['transaction_type'] == 'transfer') {
                $row['amount'] = abs($row['amount']); // Make it positive (incoming)
            }
            $transactions[] = $row;
        }

        return ['success' => true, 'transactions' => $transactions];
    }

    public function get_balance_at_date($account_id, $date) {
        // Get balance at start of specified date (decrypt balance_after)
        // Only check account_id, not related_account_id, because balance_after is for the owning account
        $stmt = $this->mysqli->prepare("
            SELECT AES_DECRYPT(balance_after, ?) as balance_after
            FROM bank_transaction
            WHERE account_id = ?
            AND created_at < ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param('sis', $this->encryption_key, $account_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $balance = (float)$row['balance_after'];
            return ['success' => true, 'balance' => $balance];
        }

        // No previous transactions, return 0
        return ['success' => true, 'balance' => 0];
    }
}
?>

