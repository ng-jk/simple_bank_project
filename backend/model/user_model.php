<?php

class user_model {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    public function create_user($user_name, $user_email, $password, $role = 'user') {
        // Validate inputs
        if (empty($user_name) || empty($user_email) || empty($password)) {
            return ['success' => false, 'error' => 'All fields are required'];
        }
        
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $stmt = $this->mysqli->prepare("INSERT INTO system_user (user_name, user_email, user_password, user_role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $user_name, $user_email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();
            return ['success' => true, 'user_id' => $user_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function get_user_by_id($user_id) {
        $stmt = $this->mysqli->prepare("SELECT user_id, user_name, user_email, user_role, user_status, created_at FROM system_user WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'user' => $row];
        }
        
        return ['success' => false, 'error' => 'User not found'];
    }
    
    public function get_user_by_username($user_name) {
        $stmt = $this->mysqli->prepare("SELECT * FROM system_user WHERE user_name = ?");
        $stmt->bind_param('s', $user_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'user' => $row];
        }
        
        return ['success' => false, 'error' => 'User not found'];
    }
    
    public function get_user_by_email($user_email) {
        $stmt = $this->mysqli->prepare("SELECT * FROM system_user WHERE user_email = ?");
        $stmt->bind_param('s', $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'user' => $row];
        }
        
        return ['success' => false, 'error' => 'User not found'];
    }
    
    public function verify_password($user_name, $password) {
        $user_result = $this->get_user_by_username($user_name);
        
        if (!$user_result['success']) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        $user = $user_result['user'];
        
        if (password_verify($password, $user['user_password'])) {
            unset($user['user_password']); // Remove password from returned data
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    public function update_user($user_id, $data) {
        $allowed_fields = ['user_name', 'user_email', 'user_role', 'user_status', 'user_password'];
        $updates = [];
        $params = [];
        $types = '';

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'error' => 'No valid fields to update'];
        }
        
        $params[] = $user_id;
        $types .= 'i';
        
        $sql = "UPDATE system_user SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    public function delete_user($user_id) {
        $stmt = $this->mysqli->prepare("DELETE FROM system_user WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    public function list_users($limit = 50, $offset = 0) {
        $stmt = $this->mysqli->prepare("SELECT user_id, user_name, user_email, user_role, user_status, created_at FROM system_user LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return ['success' => true, 'users' => $users];
    }
}
?>

