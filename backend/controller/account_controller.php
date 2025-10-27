<?php
require_once __DIR__ . '/../model/account_model.php';

class account_controller {
    private $mysqli;
    private $account_model;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->account_model = new account_model($mysqli);
    }
    
    public function create_account($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $status->user_info['user_id'];
        
        $account_type = $data['account_type'] ?? 'checking';
        $currency = $data['currency'] ?? 'RM';
        
        return $this->account_model->create_account($user_id, $account_type, $currency);
    }
    
    public function get_my_accounts($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $user_id = $status->user_info['user_id'];
        return $this->account_model->get_user_accounts($user_id);
    }
    
    public function get_account_details($status, $account_id) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        $result = $this->account_model->get_account_by_id($account_id);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Check if account belongs to user
        if ($result['account']['user_id'] != $status->user_info['user_id'] && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        return $result;
    }
    
    public function close_account($status, $account_id) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        // Verify ownership
        $result = $this->account_model->get_account_by_id($account_id);
        
        if (!$result['success']) {
            return $result;
        }
        
        if ($result['account']['user_id'] != $status->user_info['user_id'] && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        return $this->account_model->close_account($account_id);
    }
    
    public function get_all_accounts($status) {
        if (!$status->is_login || $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Admin access required'];
        }
        
        $stmt = $this->mysqli->prepare("SELECT ba.*, su.user_name, su.user_email FROM bank_account ba JOIN system_user su ON ba.user_id = su.user_id ORDER BY ba.created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        
        return ['success' => true, 'accounts' => $accounts];
    }
}
?>

