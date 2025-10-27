<?php
require_once "jwt.php";
require_once "jwt_service.php";
require_once "middleware_interface_service.php";
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT as FirebaseJWT;

class jwt_generate_service extends jwt_service implements middleware_interface_service {
    
    public function __construct(jwt $jwt, string $secret_key) {
        parent::__construct($jwt, $secret_key);
    }

    public function generate_token(array $payload) {
        $issued_at = time();
        $expiration_time = $issued_at + 3600; // jwt valid for 1 hour
        
        $token_payload = [
            'iss' => $this->jwt_data->status->host_name ?? 'simple_bank',
            'sub' => $payload['user_name'] ?? '',
            'iat' => $issued_at,
            'exp' => $expiration_time,
            'data' => $payload
        ];

        return FirebaseJWT::encode($token_payload, $this->secret_key, 'HS256');
    }

    public function verify_token(string $token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Firebase\JWT\Key($this->secret_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    public function decode_token(string $token) {
        return $this->verify_token($token);
    }

    public function is_allow() {
        // JWT generation is allowed when user is logged in
        return isset($this->jwt_data->status) && $this->jwt_data->status->is_login;
    }

    public function is_pass() {
        // Check if we have necessary data to generate JWT
        return !empty($this->secret_key);
    }

    public function is_end() {
        return false;
    }

    public function middleware_check() {
        if (!$this->is_allow()) {
            return false;
        }
        if ($this->is_allow() && $this->is_pass()) {
            return true;
        }
        if ($this->is_allow() && $this->is_end()) {
            http_response_code(401);
            exit;
        }
        return false;
    }
}
?>

