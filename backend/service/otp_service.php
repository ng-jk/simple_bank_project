<?php

class otp_service {
    private $mysqli;
    private $otp_expiry_minutes = 10; // OTP valid for 10 minutes
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    /**
     * Generate a 6-digit OTP code
     */
    private function generate_otp_code() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Create and store OTP for email verification
     */
    public function create_otp($email, $purpose = 'registration', $user_data = null) {
        // Clean up expired OTPs first
        $this->cleanup_expired_otps();
        
        // Invalidate any existing unverified OTPs for this email and purpose
        $stmt = $this->mysqli->prepare("UPDATE otp_verification SET is_verified = TRUE WHERE email = ? AND purpose = ? AND is_verified = FALSE");
        $stmt->bind_param('ss', $email, $purpose);
        $stmt->execute();
        
        // Generate new OTP
        $otp_code = $this->generate_otp_code();
        // Use FROM_UNIXTIME to ensure consistent timezone handling
        $expires_timestamp = time() + ($this->otp_expiry_minutes * 60);
        $expires_at = date('Y-m-d H:i:s', $expires_timestamp);
        $user_data_json = $user_data ? json_encode($user_data) : null;
        
        // Store OTP
        $stmt = $this->mysqli->prepare("INSERT INTO otp_verification (email, otp_code, purpose, user_data, expires_at) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))");
        $stmt->bind_param('ssssi', $email, $otp_code, $purpose, $user_data_json, $expires_timestamp);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'otp_code' => $otp_code,
                'expires_at' => $expires_at,
                'otp_id' => $stmt->insert_id
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to create OTP'];
    }
    
    /**
     * Verify OTP code
     */
    public function verify_otp($email, $otp_code, $purpose = 'registration') {
        $stmt = $this->mysqli->prepare("
            SELECT otp_id, user_data, expires_at 
            FROM otp_verification 
            WHERE email = ? 
            AND otp_code = ? 
            AND purpose = ? 
            AND is_verified = FALSE 
            AND UNIX_TIMESTAMP(expires_at) > UNIX_TIMESTAMP()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param('sss', $email, $otp_code, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Mark as verified
            $update_stmt = $this->mysqli->prepare("UPDATE otp_verification SET is_verified = TRUE WHERE otp_id = ?");
            $update_stmt->bind_param('i', $row['otp_id']);
            $update_stmt->execute();
            
            return [
                'success' => true,
                'user_data' => $row['user_data'] ? json_decode($row['user_data'], true) : null
            ];
        }
        
        return ['success' => false, 'error' => 'Invalid or expired OTP'];
    }
    
    /**
     * Check if OTP exists and is valid
     */
    public function check_otp_status($email, $purpose = 'registration') {
        $stmt = $this->mysqli->prepare("
            SELECT otp_id, expires_at, is_verified 
            FROM otp_verification 
            WHERE email = ? 
            AND purpose = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param('ss', $email, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $is_expired = strtotime($row['expires_at']) < time();
            return [
                'exists' => true,
                'is_verified' => (bool)$row['is_verified'],
                'is_expired' => $is_expired,
                'expires_at' => $row['expires_at']
            ];
        }
        
        return ['exists' => false];
    }
    
    /**
     * Resend OTP (creates new one)
     */
    public function resend_otp($email, $purpose = 'registration', $user_data = null) {
        // Check if we can resend (rate limiting)
        $stmt = $this->mysqli->prepare("
            SELECT created_at 
            FROM otp_verification 
            WHERE email = ? 
            AND purpose = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param('ss', $email, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $last_sent = strtotime($row['created_at']);
            $time_diff = time() - $last_sent;
            
            // Require at least 60 seconds between resends
            if ($time_diff < 60) {
                return [
                    'success' => false,
                    'error' => 'Please wait ' . (60 - $time_diff) . ' seconds before requesting a new OTP'
                ];
            }
        }
        
        return $this->create_otp($email, $purpose, $user_data);
    }
    
    /**
     * Clean up expired OTPs
     */
    public function cleanup_expired_otps() {
        $this->mysqli->query("DELETE FROM otp_verification WHERE expires_at < NOW()");
    }
    
    /**
     * Mark user email as verified
     */
    public function mark_email_verified($user_id) {
        $stmt = $this->mysqli->prepare("UPDATE system_user SET email_verified = TRUE, email_verified_at = NOW() WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
}
?>

