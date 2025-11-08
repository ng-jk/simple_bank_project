<?php
require_once __DIR__ . '/../model/transaction_model.php';
require_once __DIR__ . '/../model/account_model.php';
require_once __DIR__ . '/../model/payee_model.php';

class transaction_controller {
    private $mysqli;
    private $transaction_model;
    private $account_model;
    private $payee_model;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->transaction_model = new transaction_model($mysqli);
        $this->account_model = new account_model($mysqli);
        $this->payee_model = new payee_model($mysqli);
    }
    
    private function verify_account_ownership($account_id, $user_id) {
        $result = $this->account_model->get_account_by_id($account_id);
        
        if (!$result['success']) {
            return false;
        }
        
        return $result['account']['user_id'] == $user_id;
    }
    
    public function deposit($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['account_id']) || !isset($data['amount'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        // Verify account ownership
        if (!$this->verify_account_ownership($data['account_id'], $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        return $this->transaction_model->deposit(
            $data['account_id'],
            $data['amount'],
            $data['description'] ?? ''
        );
    }
    
    public function withdraw($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['account_id']) || !isset($data['amount'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        // Verify account ownership
        if (!$this->verify_account_ownership($data['account_id'], $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        return $this->transaction_model->withdraw(
            $data['account_id'],
            $data['amount'],
            $data['description'] ?? ''
        );
    }
    
    public function transfer($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['from_account_id']) || !isset($data['to_account_number']) || !isset($data['amount'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        // Verify source account ownership
        if (!$this->verify_account_ownership($data['from_account_id'], $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get destination account ID
        $to_account_result = $this->account_model->get_account_by_number($data['to_account_number']);

        if (!$to_account_result['success']) {
            return ['success' => false, 'error' => 'Destination account not found'];
        }

        return $this->transaction_model->transfer(
            $data['from_account_id'],
            $to_account_result['account']['account_id'],
            $data['amount'],
            $data['description'] ?? ''
        );
    }

    public function pay_bill($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['from_account_id']) || !isset($data['payee_id']) || !isset($data['amount'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        // Verify source account ownership
        if (!$this->verify_account_ownership($data['from_account_id'], $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get payee details
        $payee_result = $this->payee_model->get_payee_by_id($data['payee_id']);

        if (!$payee_result['success']) {
            return ['success' => false, 'error' => 'Payee not found'];
        }

        $payee = $payee_result['payee'];

        // Check if payee is active
        if ($payee['status'] !== 'active') {
            return ['success' => false, 'error' => 'Payee is not active'];
        }

        // Get payee's account
        $payee_account_result = $this->account_model->get_account_by_number($payee['account_number']);

        if (!$payee_account_result['success']) {
            return ['success' => false, 'error' => 'Payee account not found'];
        }

        // Create description with payee name
        $description = 'Bill payment to ' . $payee['payee_name'];
        if (!empty($data['description'])) {
            $description .= ' - ' . $data['description'];
        }

        // Use transfer method to complete the payment
        return $this->transaction_model->transfer(
            $data['from_account_id'],
            $payee_account_result['account']['account_id'],
            $data['amount'],
            $description
        );
    }

    public function get_payees($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        return $this->payee_model->get_all_active_payees();
    }

    public function get_account_transactions($status, $account_id) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        // Verify account ownership
        if (!$this->verify_account_ownership($account_id, $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        return $this->transaction_model->get_account_transactions($account_id, $limit, $offset);
    }
    
    public function get_transaction_by_reference($status, $reference) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $result = $this->transaction_model->get_transaction_by_reference($reference);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Verify user has access to at least one of the accounts in the transaction
        $has_access = false;
        foreach ($result['transactions'] as $transaction) {
            if ($this->verify_account_ownership($transaction['account_id'], $status->user_info['user_id']) || $status->permission == 'admin') {
                $has_access = true;
                break;
            }
        }
        
        if (!$has_access) {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        return $result;
    }
}
?>

