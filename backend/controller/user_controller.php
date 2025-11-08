<?php

require_once __DIR__ . '/../model/user_model.php';

class user_controller {
    private $mysqli;
    private $user_model;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->user_model = new user_model($mysqli);
    }

    /**
     * Get all users with optional filtering by role
     */
    public function get_users($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $role = isset($_GET['role']) ? $_GET['role'] : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

            $result = $this->user_model->list_users($limit, $offset);

            if (!$result['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to fetch users']);
                return;
            }

            $users = $result['users'];

            // Filter by role if specified
            if ($role && ($role === 'admin' || $role === 'user')) {
                $users = array_filter($users, function($user) use ($role) {
                    return $user['user_role'] === $role;
                });
                $users = array_values($users); // Re-index array
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $users,
                'count' => count($users)
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to fetch users: ' . $e->getMessage()]);
        }
    }

    /**
     * Create a new staff user (admin role only)
     */
    public function create_staff($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($data['user_name']) || !isset($data['user_email']) || !isset($data['user_password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }

            // Validate email format
            if (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                return;
            }

            // Validate password strength
            if (strlen($data['user_password']) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
                return;
            }

            // Create user with admin role
            $user_id = $this->user_model->create_user(
                $data['user_name'],
                $data['user_email'],
                $data['user_password'],
                'admin' // Force admin role for staff
            );

            if ($user_id) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff user created successfully',
                    'user_id' => $user_id
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to create user. Username or email may already exist.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create staff user: ' . $e->getMessage()]);
        }
    }

    /**
     * Update a staff user (admin role only)
     */
    public function update_staff($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                return;
            }

            $user_id = $data['user_id'];

            // Get current user to verify it's an admin
            $user_result = $this->user_model->get_user_by_id($user_id);
            if (!$user_result['success'] || $user_result['user']['user_role'] !== 'admin') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Can only update staff users']);
                return;
            }

            // Prepare update data
            $update_data = [];
            if (isset($data['user_name'])) $update_data['user_name'] = $data['user_name'];
            if (isset($data['user_email'])) {
                if (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    return;
                }
                $update_data['user_email'] = $data['user_email'];
            }
            if (isset($data['user_status']) && in_array($data['user_status'], ['active', 'inactive', 'suspended'])) {
                $update_data['user_status'] = $data['user_status'];
            }

            $result = $this->user_model->update_user($user_id, $update_data);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Staff user updated successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update staff user: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a staff user (admin role only)
     */
    public function delete_staff($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                return;
            }

            $user_id = $data['user_id'];

            // Prevent deleting self
            if ($user_id == $status->user_info['user_id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                return;
            }

            // Get current user to verify it's an admin
            $user_result = $this->user_model->get_user_by_id($user_id);
            if (!$user_result['success'] || $user_result['user']['user_role'] !== 'admin') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Can only delete staff users']);
                return;
            }

            $result = $this->user_model->delete_user($user_id);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Staff user deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete staff user: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset password for a staff user (admin role)
     */
    public function reset_staff_password($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id']) || !isset($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID and new password are required']);
                return;
            }

            $user_id = $data['user_id'];
            $new_password = $data['new_password'];

            // Validate password strength
            if (strlen($new_password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
                return;
            }

            // Get current user to verify it's an admin
            $user_result = $this->user_model->get_user_by_id($user_id);
            if (!$user_result['success'] || $user_result['user']['user_role'] !== 'admin') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Can only reset password for staff users']);
                return;
            }

            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            $result = $this->user_model->update_user($user_id, ['user_password' => $hashed_password]);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset password for a customer user
     */
    public function reset_customer_password($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id']) || !isset($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID and new password are required']);
                return;
            }

            $user_id = $data['user_id'];
            $new_password = $data['new_password'];

            // Validate password strength
            if (strlen($new_password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
                return;
            }

            // Get current user to verify it's a regular user (not admin)
            $user_result = $this->user_model->get_user_by_id($user_id);
            if (!$user_result['success'] || $user_result['user']['user_role'] !== 'user') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Can only reset password for customer users']);
                return;
            }

            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            $result = $this->user_model->update_user($user_id, ['user_password' => $hashed_password]);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset own password (for currently logged-in admin)
     */
    public function reset_own_password($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Current password and new password are required']);
                return;
            }

            $current_password = $data['current_password'];
            $new_password = $data['new_password'];

            // Validate new password strength
            if (strlen($new_password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
                return;
            }

            // Verify current password
            $user_id = $status->user_info['user_id'];
            $user_name = $status->user_info['user_name'];

            $verify_result = $this->user_model->verify_password($user_name, $current_password);

            if (!$verify_result['success']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                return;
            }

            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            $result = $this->user_model->update_user($user_id, ['user_password' => $hashed_password]);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to update password']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . $e->getMessage()]);
        }
    }

    /**
     * Block/unblock a customer user (toggle status)
     */
    public function toggle_customer_status($status) {
        try {
            // Only admins can access this endpoint
            if (!$status->is_login || $status->permission !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['user_id']) || !isset($data['user_status'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID and status are required']);
                return;
            }

            $user_id = $data['user_id'];
            $new_status = $data['user_status'];

            // Validate status
            if (!in_array($new_status, ['active', 'inactive', 'suspended'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid status. Must be active, inactive, or suspended']);
                return;
            }

            // Get current user to verify it's a regular user (not admin)
            $user_result = $this->user_model->get_user_by_id($user_id);
            if (!$user_result['success'] || $user_result['user']['user_role'] !== 'user') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Can only change status for customer users']);
                return;
            }

            $result = $this->user_model->update_user($user_id, ['user_status' => $new_status]);

            if ($result) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update user status: ' . $e->getMessage()]);
        }
    }
}
