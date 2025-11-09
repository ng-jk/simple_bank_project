<?php
require_once __DIR__ . '/../model/user_model.php';
require_once __DIR__ . '/../service/jwt_generate_service.php';
require_once __DIR__ . '/../service/jwt.php';
require_once __DIR__ . '/../service/status.php';
require_once __DIR__ . '/../service/config.php';
require_once __DIR__ . '/../service/otp_service.php';
require_once __DIR__ . '/../service/email_service.php';

class auth_controller {
    private $mysqli;
    private $user_model;
    private $secret_key;
    private $otp_service;
    private $email_service;
    
    public function __construct($mysqli, $secret_key) {
        $this->mysqli = $mysqli;
        $this->user_model = new user_model($mysqli);
        $this->secret_key = $secret_key;
        $this->otp_service = new otp_service($mysqli);
        $this->email_service = new email_service();
    }
    
    /**
     * Step 1: Request OTP for registration
     */
    public function request_registration_otp() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_name']) || !isset($data['user_email']) || !isset($data['password'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        // Validate email format
        if (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        // Check if email already exists
        $existing_user = $this->user_model->get_user_by_email($data['user_email']);
        if (empty($existing_user)) {
            return ['success' => false, 'error' => 'Email already registered'];
        }
        
        // Check if username already exists
        $existing_username = $this->user_model->get_user_by_username($data['user_name']);
        if (empty($existing_username)) {
            return ['success' => false, 'error' => 'Username already taken'];
        }
        
        // Store user data temporarily with OTP
        $user_data = [
            'user_name' => $data['user_name'],
            'user_email' => $data['user_email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'user'
        ];
        
        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Create OTP (no user_id for registration since user doesn't exist yet)
        $otp_result = $this->otp_service->create_otp(
            $data['user_email'],
            'registration',
            $user_data,
            null, // user_id is null for registration
            $ip_address,
            $user_agent
        );

        if (!$otp_result['success']) {
            return $otp_result;
        }

        // Send OTP via email
        $email_result = $this->email_service->send_otp_email(
            $data['user_email'],
            $otp_result['otp_code'],
            'registration'
        );
        
        if(!$email_result['success']){
            return [
                'success' => false,
                'error' => $email_result['error'],
                'email' => $data['user_email']
            ];
        }else{
            return [
                'success' => true,
                'message' => 'OTP sent to your email',
                'email' => $data['user_email']
            ];
        }
    }
    
    /**
     * Step 2: Verify OTP and complete registration
     */
    public function verify_registration_otp() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['otp_code'])) {
            return ['success' => false, 'error' => 'Missing email or OTP code'];
        }
        
        // Verify OTP
        $verify_result = $this->otp_service->verify_otp($data['email'], $data['otp_code'], 'registration');
        
        if (!$verify_result['success']) {
            return $verify_result;
        }
        
        // Get stored user data
        $user_data = $verify_result['user_data'];
        if (!$user_data) {
            return ['success' => false, 'error' => 'Registration data not found'];
        }
        
        // Create user account
        $result = $this->user_model->create_user(
            $user_data['user_name'],
            $user_data['user_email'],
            $user_data['password'],
            $user_data['role']
        );
        
        if ($result['success']) {
            // Mark email as verified
            $this->otp_service->mark_email_verified($result['user_id']);
            
            // Create default bank account
            require_once __DIR__ . '/../model/account_model.php';
            $account_model = new account_model($this->mysqli);
            $account_result = $account_model->create_account($result['user_id']);
            
            if ($account_result['success']) {
                $result['account_number'] = $account_result['account_number'];
            }
            
            $result['message'] = 'Registration completed successfully! You can now login.';
        }
        
        return $result;
    }
    
    /**
     * Resend OTP
     */
    public function resend_otp() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['purpose'])) {
            return ['success' => false, 'error' => 'Missing email or purpose'];
        }
        
        // Get the original user data if it exists
        $status = $this->otp_service->check_otp_status($data['email'], $data['purpose']);
        
        if (!$status['exists']) {
            return ['success' => false, 'error' => 'No OTP request found for this email'];
        }
        
        // For registration, we need to get the stored user data
        $user_data = null;
        if ($data['purpose'] === 'registration' && isset($data['user_data'])) {
            $user_data = $data['user_data'];
        }

        // Get user_id if this is for an existing user
        $user_id = null;
        if ($data['purpose'] !== 'registration') {
            // For login/password_reset, try to get user_id from email
            $user_result = $this->user_model->get_user_by_email($data['email']);
            if ($user_result['success']) {
                $user_id = $user_result['user']['user_id'];
            }
        }

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Resend OTP
        $otp_result = $this->otp_service->resend_otp(
            $data['email'],
            $data['purpose'],
            $user_data,
            $user_id,
            $ip_address,
            $user_agent
        );

        if (!$otp_result['success']) {
            return $otp_result;
        }
        
        // Send email
        $email_result = $this->email_service->send_otp_email(
            $data['email'],
            $otp_result['otp_code'],
            $data['purpose']
        );

        if(!$email_result['result']){
            return [
                'success' => false,
                'error' => $email_result['error'],
                'email' => $data['email']
            ];
        }else{
            return [
                'success' => true,
                'message' => 'OTP resent to your email',
                'email' => $data['email']
            ];
        }
        
    }
    
    /**
     * Original register method (deprecated - use OTP flow instead)
     */
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
    
    /**
     * Request password reset OTP
     */
    public function request_password_reset() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['email'])) {
            return ['success' => false, 'error' => 'Email is required'];
        }

        // Check if user exists
        $user_result = $this->user_model->get_user_by_email($data['email']);
        if (!$user_result['success']) {
            // For security, don't reveal if email exists or not
            return [
                'success' => true,
                'message' => 'If an account with this email exists, an OTP has been sent'
            ];
        }

        $user = $user_result['user'];

        // Get audit trail information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Create OTP linked to user
        $otp_result = $this->otp_service->create_otp(
            $data['email'],
            'password_reset',
            null, // no user_data needed
            $user['user_id'], // link to user for security
            $ip_address,
            $user_agent
        );

        if (!$otp_result['success']) {
            return $otp_result;
        }

        // Send OTP via email
        $email_result = $this->email_service->send_otp_email(
            $data['email'],
            $otp_result['otp_code'],
            'password_reset'
        );

        if (!$email_result['success']) {
            return ['success' => false, 'error' => 'Failed to send OTP email'];
        }

        return [
            'success' => true,
            'message' => 'Password reset OTP sent to your email'
        ];
    }

    /**
     * Verify password reset OTP and reset password
     */
    public function verify_password_reset() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['email']) || !isset($data['otp_code']) || !isset($data['new_password'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        // Get user to link verification
        $user_result = $this->user_model->get_user_by_email($data['email']);
        if (!$user_result['success']) {
            return ['success' => false, 'error' => 'Invalid request'];
        }

        $user = $user_result['user'];

        // Verify OTP with user_id for enhanced security
        $verify_result = $this->otp_service->verify_otp(
            $data['email'],
            $data['otp_code'],
            'password_reset',
            $user['user_id']
        );

        if (!$verify_result['success']) {
            return $verify_result;
        }

        // Hash new password
        $hashed_password = password_hash($data['new_password'], PASSWORD_BCRYPT);

        // Update password
        $update_result = $this->user_model->update_user($user['user_id'], [
            'user_password' => $hashed_password
        ]);

        if (!$update_result['success']) {
            return ['success' => false, 'error' => 'Failed to update password'];
        }

        return [
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.'
        ];
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_name']) || !isset($data['password'])) {
            return ['success' => false, 'error' => 'Missing credentials'];
        }

        $result = $this->user_model->verify_password($data['user_name'], $data['password']);

        if ($result['success']) {
            $user = $result['user'];

            // Check if user account is active
            if ($user['user_status'] !== 'active') {
                return ['success' => false, 'error' => 'Your account has been suspended. Please contact support.'];
            }

            // Generate JWT token
            $status = new status(
                $user['user_role'],
                true,
                $_SERVER['REQUEST_URI'],
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['HTTP_HOST'],
                $user
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
        
        return [
            'success' => true,
            'user' => $status->user_info
        ];
    }
}
?>