<?php
require_once __DIR__ . '/../model/user_model.php';
require_once __DIR__ . '/../service/jwt_generate_service.php';
require_once __DIR__ . '/../service/jwt.php';
require_once __DIR__ . '/../service/status.php';
require_once __DIR__ . '/../service/config.php';

class auth_controller {
    private $mysqli;
    private $user_model;
    private $secret_key;
    
    public function __construct($mysqli, $secret_key) {
        $this->mysqli = $mysqli;
        $this->user_model = new user_model($mysqli);
        $this->secret_key = $secret_key;
    }
    
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_name']) || !isset($data['user_email']) || !isset($data['password'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        $result = $this->user_model->create_user(
            $data['user_name'],
            $data['user_email'],
            $data['password'],
            $data['role'] ?? 'user'
        );
        
        if ($result['success']) {
            // Also create a default bank account for the user
            require_once __DIR__ . '/../model/account_model.php';
            $account_model = new account_model($this->mysqli);
            $account_result = $account_model->create_account($result['user_id']);
            
            if ($account_result['success']) {
                $result['account_number'] = $account_result['account_number'];
            }
        }
        
        return $result;
    }
    
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_name']) || !isset($data['password'])) {
            return ['success' => false, 'error' => 'Missing credentials'];
        }
        
        $result = $this->user_model->verify_password($data['user_name'], $data['password']);
        
        if ($result['success']) {
            // Generate JWT token
            $user = $result['user'];
            
            $status = new status(
                $user['user_role'],
                true,
                $_SERVER['REQUEST_URI'],
                $_SERVER['REQUEST_METHOD'],
                $user,
                $_SERVER['HTTP_HOST']
            );
            
            $config = new config($this->secret_key);
            $jwt_data = new jwt($status, $config, '');
            $jwt_service = new jwt_generate_service($jwt_data, $this->secret_key);
            
            $token = $jwt_service->generate_token([
                'user_id' => $user['user_id'],
                'user_name' => $user['user_name'],
                'role' => $user['user_role']
            ]);
            
            // Set cookie
            setcookie('jwt_token', $token, time() + 3600, '/', '', false, true);
            
            return [
                'success' => true,
                'token' => $token,
                'user' => $user
            ];
        }
        
        return $result;
    }
    
    public function logout() {
        setcookie('jwt_token', '', time() - 3600, '/', '', false, true);
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function get_current_user($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Not logged in'];
        }
        
        return ['success' => true, 'user' => $status->user_info];
    }
}
?>

