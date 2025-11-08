<?php
require_once __DIR__ . '/../model/user_model.php';
require_once __DIR__ . '/../service/jwt_generate_service.php';
require_once __DIR__ . '/../service/jwt.php';
require_once __DIR__ . '/../service/status.php';
require_once __DIR__ . '/../service/config.php';

class admin_controller {
    private $mysqli;
    private $user_model;
    private $secret_key;

    public function __construct($mysqli, $secret_key) {
        $this->mysqli = $mysqli;
        $this->user_model = new user_model($mysqli);
        $this->secret_key = $secret_key;
    }

    /**
     * Admin login - only allows users with admin role
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_name']) || !isset($data['password'])) {
            return ['success' => false, 'error' => 'Missing credentials'];
        }

        $result = $this->user_model->verify_password($data['user_name'], $data['password']);

        if ($result['success']) {
            $user = $result['user'];

            // Check if user has admin role
            if ($user['user_role'] !== 'admin') {
                return ['success' => false, 'error' => 'Access denied. Admin privileges required.'];
            }

            // Check if admin account is active
            if ($user['user_status'] !== 'active') {
                return ['success' => false, 'error' => 'Your admin account has been suspended. Please contact system administrator.'];
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

    /**
     * Admin logout
     */
    public function logout() {
        setcookie('jwt_token', '', time() - 3600, '/', '', false, true);
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Get current admin user info
     */
    public function get_current_user($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Not logged in'];
        }

        if ($status->permission !== 'admin') {
            return ['success' => false, 'error' => 'Access denied. Admin privileges required.'];
        }

        return [
            'success' => true,
            'user' => $status->user_info
        ];
    }

    /**
     * Verify admin middleware - checks if user is logged in and has admin role
     */
    public function verify_admin($status) {
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Authentication required'];
        }

        if ($status->permission !== 'admin') {
            return ['success' => false, 'error' => 'Access denied. Admin privileges required.'];
        }

        return ['success' => true];
    }

    /**
     * Get dashboard statistics
     */
    public function get_statistics($status) {
        if (!$status->is_login || $status->permission !== 'admin') {
            return ['success' => false, 'error' => 'Access denied. Admin privileges required.'];
        }

        try {
            // Get total users count
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM system_user");
            $stmt->execute();
            $result = $stmt->get_result();
            $total_users = $result->fetch_assoc()['total'];

            // Get total accounts count
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM bank_account");
            $stmt->execute();
            $result = $stmt->get_result();
            $total_accounts = $result->fetch_assoc()['total'];

            // Get total transactions count
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM bank_transaction");
            $stmt->execute();
            $result = $stmt->get_result();
            $total_transactions = $result->fetch_assoc()['total'];

            return [
                'success' => true,
                'statistics' => [
                    'total_users' => $total_users,
                    'total_accounts' => $total_accounts,
                    'total_transactions' => $total_transactions
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to fetch statistics: ' . $e->getMessage()];
        }
    }
}
?>
