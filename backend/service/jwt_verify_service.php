<?php
require_once "jwt.php";
require_once "jwt_service.php";
require_once "middleware_interface_service.php";
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class jwt_verify_service extends jwt_service implements middleware_interface_service {
    
    public function __construct(jwt $jwt, string $secret_key) {
        parent::__construct($jwt, $secret_key);
    }

    public function generate_token(array $payload) {
        // Not used in verify service
        return null;
    }

    public function verify_token(string $token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    public function decode_token(string $token) {
        return $this->verify_token($token);
    }

    public function jwt_verify($jwt_token) {
        return $this->verify_token($jwt_token);
    }

    public function is_allow() {
        // JWT verification is always allowed
        return true;
    }

    public function is_pass() {
        // Check if JWT token exists in request
        $headers = getallheaders();
        return isset($headers['Authorization']) || isset($_COOKIE['jwt_token']);
    }

    public function is_end() {
        return false;
    }

    public function middleware_check() {
        if (!$this->is_allow()) {
            return false;
        }

        
        if ($this->is_pass()) {
            // Extract token from Authorization header or cookie
            $headers = getallheaders();
            $token = null;
            
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
                $token = str_replace('Bearer ', '', $auth_header);
            } elseif (isset($_COOKIE['jwt_token'])) {
                $token = $_COOKIE['jwt_token'];
            }
            
            if ($token) {
                $decoded = $this->verify_token($token);
                if ($decoded) {
                    // Update status with JWT data
                    $this->jwt_data->status->is_login = true;
                    $this->jwt_data->status->user_info = (array)$decoded->data;
                    $this->jwt_data->status->permission = $decoded->data->role ?? 'user';
                    return true;
                }
            }
        }

        // No valid token, user is not logged in
        $this->jwt_data->status->is_login = false;
        return true; // Still pass middleware, just not logged in
    }
}
?>

