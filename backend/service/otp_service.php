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
     *
     * @param string $email User's email address
     * @param string $purpose Purpose of OTP (registration, login, password_reset, transaction, account_change)
     * @param array|null $user_data Additional user data to store (for registration)
     * @param int|null $user_id User ID to link OTP to (null for registration, required for login/password_reset)
     * @param string|null $ip_address IP address of the request
     * @param string|null $user_agent User agent of the request
     */
    public function create_otp($email, $purpose = 'registration', $user_data = null, $user_id = null, $ip_address = null, $user_agent = null) {
        // Clean up expired OTPs first
        $this->cleanup_expired_otps();

        // For security, invalidate any existing unverified OTPs for this email and purpose
        // If user_id is provided, also match on user_id for added security
        if ($user_id !== null) {
            $stmt = $this->mysqli->prepare("UPDATE otp_verification SET is_verified = TRUE WHERE email = ? AND purpose = ? AND user_id = ? AND is_verified = FALSE");
            $stmt->bind_param('ssi', $email, $purpose, $user_id);
        } else {
            $stmt = $this->mysqli->prepare("UPDATE otp_verification SET is_verified = TRUE WHERE email = ? AND purpose = ? AND is_verified = FALSE");
            $stmt->bind_param('ss', $email, $purpose);
        }
        $stmt->execute();

        // Generate new OTP
        $otp_code = $this->generate_otp_code();

        // Use FROM_UNIXTIME to ensure consistent timezone handling
        $expires_timestamp = time() + ($this->otp_expiry_minutes * 60);
        $expires_at = date('Y-m-d H:i:s', $expires_timestamp);

        // Serialize user_data if provided
        $user_data_json = null;
        if ($user_data) {
            $user_data_json = json_encode($user_data);
        }

        // Store OTP with data and audit trail
        $stmt = $this->mysqli->prepare("INSERT INTO otp_verification (email, user_id, otp_code, purpose, user_data, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))");
        $stmt->bind_param('sisssssi', $email, $user_id, $otp_code, $purpose, $user_data_json, $ip_address, $user_agent, $expires_timestamp);

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
     *
     * @param string $email User's email address
     * @param string $otp_code The OTP code to verify
     * @param string $purpose Purpose of OTP verification
     * @param int|null $user_id User ID to verify against (for added security on login/password_reset)
     */
    public function verify_otp($email, $otp_code, $purpose = 'registration', $user_id = null) {
        // Build query with optional user_id check for enhanced security
        if ($user_id !== null) {
            $stmt = $this->mysqli->prepare("
                SELECT otp_id, user_id, user_data, expires_at
                FROM otp_verification
                WHERE email = ?
                AND otp_code = ?
                AND purpose = ?
                AND user_id = ?
                AND is_verified = FALSE
                AND UNIX_TIMESTAMP(expires_at) > UNIX_TIMESTAMP()
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param('sssi', $email, $otp_code, $purpose, $user_id);
        } else {
            $stmt = $this->mysqli->prepare("
                SELECT otp_id, user_id, user_data, expires_at
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
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Mark as verified and record verification timestamp
            $update_stmt = $this->mysqli->prepare("UPDATE otp_verification SET is_verified = TRUE, verified_at = NOW() WHERE otp_id = ?");
            $update_stmt->bind_param('i', $row['otp_id']);
            $update_stmt->execute();

            // Deserialize user_data
            $user_data = null;
            if ($row['user_data']) {
                $user_data = json_decode($row['user_data'], true);
            }

            return [
                'success' => true,
                'user_data' => $user_data,
                'user_id' => $row['user_id']
            ];
        }

        return ['success' => false, 'error' => 'Invalid or expired OTP'];
    }
    
    /**
     * Check if OTP exists and is valid
     *
     * @param string $email User's email address
     * @param string $purpose Purpose of OTP
     * @param int|null $user_id User ID to check (optional)
     */
    public function check_otp_status($email, $purpose = 'registration', $user_id = null) {
        if ($user_id !== null) {
            $stmt = $this->mysqli->prepare("
                SELECT otp_id, expires_at, is_verified, user_id
                FROM otp_verification
                WHERE email = ?
                AND purpose = ?
                AND user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param('ssi', $email, $purpose, $user_id);
        } else {
            $stmt = $this->mysqli->prepare("
                SELECT otp_id, expires_at, is_verified, user_id
                FROM otp_verification
                WHERE email = ?
                AND purpose = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param('ss', $email, $purpose);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $is_expired = strtotime($row['expires_at']) < time();
            return [
                'exists' => true,
                'is_verified' => (bool)$row['is_verified'],
                'is_expired' => $is_expired,
                'expires_at' => $row['expires_at'],
                'user_id' => $row['user_id']
            ];
        }

        return ['exists' => false];
    }
    
    /**
     * Resend OTP (creates new one)
     *
     * @param string $email User's email address
     * @param string $purpose Purpose of OTP
     * @param array|null $user_data Additional user data to store
     * @param int|null $user_id User ID to link OTP to
     * @param string|null $ip_address IP address of the request
     * @param string|null $user_agent User agent of the request
     */
    public function resend_otp($email, $purpose = 'registration', $user_data = null, $user_id = null, $ip_address = null, $user_agent = null) {
        // Check if we can resend (rate limiting)
        if ($user_id !== null) {
            $stmt = $this->mysqli->prepare("
                SELECT created_at
                FROM otp_verification
                WHERE email = ?
                AND purpose = ?
                AND user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param('ssi', $email, $purpose, $user_id);
        } else {
            $stmt = $this->mysqli->prepare("
                SELECT created_at
                FROM otp_verification
                WHERE email = ?
                AND purpose = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param('ss', $email, $purpose);
        }

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

        return $this->create_otp($email, $purpose, $user_data, $user_id, $ip_address, $user_agent);
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

    /**
     * Get OTP verification history for a user (for audit purposes)
     *
     * @param int $user_id User ID
     * @param int $limit Number of records to retrieve
     */
    public function get_user_otp_history($user_id, $limit = 10) {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT otp_id, purpose, is_verified, verified_at, expires_at, created_at, ip_address
                FROM otp_verification
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param('ii', $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }

            return ['success' => true, 'history' => $history];
        } catch (Exception $e) {
            error_log('Error getting OTP history: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to retrieve OTP history'];
        }
    }

    /**
     * Get failed OTP verification attempts for security monitoring
     *
     * @param string $email Email to check
     * @param int $minutes Time window in minutes
     */
    public function get_failed_attempts($email, $minutes = 30) {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));

        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as attempt_count
            FROM otp_verification
            WHERE email = ?
            AND created_at >= ?
            AND is_verified = FALSE
        ");
        $stmt->bind_param('ss', $email, $since);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'success' => true,
                'attempt_count' => (int)$row['attempt_count'],
                'time_window_minutes' => $minutes
            ];
        }

        return ['success' => true, 'attempt_count' => 0];
    }
}
?>

