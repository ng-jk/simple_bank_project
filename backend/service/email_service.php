<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class email_service {
    private $from_email;
    private $from_name;
    private $mail;
    
    public function __construct($from_email = null, $from_name = null) {
        // Use environment variables if not provided
        $this->from_email = $from_email ?? ($_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@simplebank.com');
        $this->from_name = $from_name ?? ($_ENV['SMTP_FROM_NAME'] ?? 'Simple Bank');
        $this->mail = new PHPMailer(true);
    }
    
    /**
     * Send OTP email
     */
    public function send_otp_email($to_email, $otp_code, $purpose = 'registration') {
        $subject = $this->get_subject($purpose);
        $message = $this->get_message($otp_code, $purpose);
        try {
            // SMTP settings from environment variables
            $this->mail->isSMTP();
            $this->mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
            $this->mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

            // Email details
            $this->mail->setFrom($this->from_email, $this->from_name);
            $this->mail->addAddress($to_email, 'Dear User');
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $message;

            // Send email
            $this->mail->send();
            return [
                'success' => true,
                'to' => $to_email,
                'subject' => $subject
            ];
        } catch (Exception $e) {
             return [
                'success' => false,
                'to' => $to_email,
                'subject' => $subject,
                'error' => $this->mail->ErrorInfo,
            ];
        }
    }
    
    /**
     * Get email subject based on purpose
     */
    private function get_subject($purpose) {
        $subjects = [
            'registration' => 'Verify Your Email - Simple Bank',
            'login' => 'Your Login Verification Code - Simple Bank',
            'password_reset' => 'Password Reset Code - Simple Bank'
        ];
        
        return $subjects[$purpose] ?? 'Verification Code - Simple Bank';
    }
    
    /**
     * Get email message HTML
     */
    private function get_message($otp_code, $purpose) {
        $title = $this->get_title($purpose);
        $description = $this->get_description($purpose);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 40px 30px;
        }
        .otp-box {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .otp-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏦 Simple Bank</h1>
        </div>
        <div class="content">
            <h2>{$title}</h2>
            <p class="description">{$description}</p>
            
            <div class="otp-box">
                <div class="otp-label">Your Verification Code</div>
                <div class="otp-code">{$otp_code}</div>
            </div>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong><br>
                • This code will expire in 10 minutes<br>
                • Never share this code with anyone<br>
                • Simple Bank will never ask for this code via phone or email
            </div>
            
            <p class="description">
                If you didn't request this code, please ignore this email or contact our support team.
            </p>
        </div>
        <div class="footer">
            <p>© 2025 Simple Bank. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Get title based on purpose
     */
    private function get_title($purpose) {
        $titles = [
            'registration' => 'Welcome to Simple Bank!',
            'login' => 'Login Verification',
            'password_reset' => 'Password Reset Request'
        ];
        
        return $titles[$purpose] ?? 'Verification Required';
    }
    
    /**
     * Get description based on purpose
     */
    private function get_description($purpose) {
        $descriptions = [
            'registration' => 'Thank you for registering with Simple Bank. Please use the verification code below to complete your registration.',
            'login' => 'We received a login attempt for your account. Please use the code below to verify your identity.',
            'password_reset' => 'We received a request to reset your password. Use the code below to proceed with password reset.'
        ];
        
        return $descriptions[$purpose] ?? 'Please use the verification code below to continue.';
    }
    
    /**
     * Log email to file for development/testing
     */
    private function log_email($to_email, $subject, $message, $otp_code) {
        $log_dir = __DIR__ . '/../../logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/emails.log';
        $log_entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s | OTP: %s\n",
            date('Y-m-d H:i:s'),
            $to_email,
            $subject,
            $otp_code
        );
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        // Also log full HTML for viewing
        $html_file = $log_dir . '/email_' . time() . '.html';
        file_put_contents($html_file, $message);
        
        return [
            'success' => true,
            'to' => $to_email,
            'subject' => $subject,
            'mode' => 'development',
            'otp_code' => $otp_code, // Only in development!
            'log_file' => $log_file
        ];
    }
}
?>

