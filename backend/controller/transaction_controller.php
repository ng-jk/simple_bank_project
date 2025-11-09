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

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return $this->transaction_model->deposit(
            $data['account_id'],
            $data['amount'],
            $data['description'] ?? '',
            $ip_address,
            $user_agent
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

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return $this->transaction_model->withdraw(
            $data['account_id'],
            $data['amount'],
            $data['description'] ?? '',
            null, // payee_id
            null, // payee_name
            null, // payee_code
            $ip_address,
            $user_agent
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

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return $this->transaction_model->transfer(
            $data['from_account_id'],
            $to_account_result['account']['account_id'],
            $data['amount'],
            $data['description'] ?? '',
            $ip_address,
            $user_agent
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

        // Get payee details to snapshot in transaction
        $payee_result = $this->payee_model->get_payee_by_id($data['payee_id']);

        if (!$payee_result['success']) {
            return ['success' => false, 'error' => 'Payee not found'];
        }

        $payee = $payee_result['payee'];

        // Check if payee is active
        if ($payee['status'] !== 'active') {
            return ['success' => false, 'error' => 'Payee is not active'];
        }

        // Build description with all payee details for historical record
        // This preserves the payee information even if it changes later in the bill_payee table
        $description = sprintf(
            'Bill Payment - %s (%s) - Category: %s',
            $payee['payee_name'],
            $payee['payee_code'],
            $payee['payee_category']
        );

        // Add bill reference number if provided
        if (!empty($data['bill_reference'])) {
            $description .= ' - Bill Ref: ' . $data['bill_reference'];
        }

        // Add any additional notes from user
        if (!empty($data['description'])) {
            $description .= ' - ' . $data['description'];
        }

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Process as paybill transaction with denormalized payee information
        return $this->transaction_model->paybill(
            $data['from_account_id'],
            $data['amount'],
            $payee['payee_id'],
            $payee['payee_name'],
            $payee['payee_code'],
            $description,
            $ip_address,
            $user_agent
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

