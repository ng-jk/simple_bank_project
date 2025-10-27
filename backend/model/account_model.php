<?php

class account_model {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    private function generate_account_number() {
        // Generate a unique 16-digit account number
        do {
            $account_number = '';
            for ($i = 0; $i < 16; $i++) {
                $account_number .= rand(0, 9);
            }
            
            // Check if account number already exists
            $stmt = $this->mysqli->prepare("SELECT account_id FROM bank_account WHERE account_number = ?");
            $stmt->bind_param('s', $account_number);
            $stmt->execute();
            $result = $stmt->get_result();
        } while ($result->num_rows > 0);
        
        return $account_number;
    }
    
    public function create_account($user_id, $account_type = 'checking', $currency = 'USD') {
        $account_number = $this->generate_account_number();
        
        $stmt = $this->mysqli->prepare("INSERT INTO bank_account (user_id, account_number, account_type, currency) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $user_id, $account_number, $account_type, $currency);
        
        if ($stmt->execute()) {
            $account_id = $stmt->insert_id;
            return ['success' => true, 'account_id' => $account_id, 'account_number' => $account_number];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    public function get_account_by_id($account_id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bank_account WHERE account_id = ?");
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'account' => $row];
        }
        
        return ['success' => false, 'error' => 'Account not found'];
    }
    
    public function get_account_by_number($account_number) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bank_account WHERE account_number = ?");
        $stmt->bind_param('s', $account_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'account' => $row];
        }
        
        return ['success' => false, 'error' => 'Account not found'];
    }
    
    public function get_user_accounts($user_id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bank_account WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        
        return ['success' => true, 'accounts' => $accounts];
    }
    
    public function update_balance($account_id, $new_balance) {
        $stmt = $this->mysqli->prepare("UPDATE bank_account SET balance = ? WHERE account_id = ?");
        $stmt->bind_param('di', $new_balance, $account_id);
        
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

