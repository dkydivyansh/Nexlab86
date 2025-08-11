<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function preventCaching() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

// Then call it at the top of your PHP files
preventCaching();

function getApiDomain() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    return $protocol . $domain;
}

function formatJsonValue($value) {
    if (empty($value)) {
        return '';
    }

    // If it's already an array or object, encode it directly
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // If it's a string, try to decode and re-encode to format
    if (is_string($value)) {
        // Clean up the input
        $value = str_replace(['\\r\\n', '\r\n', '\\n', '\n'], '', $value); // Remove line breaks
        $value = preg_replace('/\s+$/m', '', $value); // Remove trailing whitespace
        $value = str_replace('\\', '', $value); // Remove escaped slashes
        
        // Try to decode
        $decoded = json_decode($value, true);
        
        // If valid JSON, re-encode with proper formatting
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
    
    return $value;
}

function cleanJsonInput($value) {
    if (empty($value)) {
        return '';
    }

    if (is_array($value) || is_object($value)) {
        return $value;
    }

    // Clean up the input
    $value = str_replace(['\\r\\n', '\r\n', '\\n', '\n'], '', $value); // Remove line breaks
    $value = preg_replace('/\s+$/m', '', $value); // Remove trailing whitespace
    $value = str_replace('\\', '', $value); // Remove escaped slashes
    
    // Try to decode
    $decoded = json_decode($value, true);
    
    // If valid JSON, return the decoded array/object
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }
    
    return $value;
}

/**
 * Generate a random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send verification email using PHPMailer
 */
function sendVerificationEmail($email, $username, $token) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $domain = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $verificationLink = "$protocol://$domain/public/verify.php?token=" . urlencode($token);
    
    try {
        $mail = new PHPMailer(true);
        // Set up mail server
        $mail->SMTPDebug = 0;  // Set to 0 to suppress verbose output
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'team@dkydivyansh.com';
        $mail->Password   = 'Divyansh.8840';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('team@dkydivyansh.com', 'NexLab86');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        
        // Email body
        $mail->Body = "
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Welcome to NexLab86!</h2>
            <p>Hello $username,</p>
            <p>Thank you for registering. Please click the button below to verify your email address:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$verificationLink' 
                   style='background-color: #007bff; color: white; padding: 12px 25px; 
                          text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Verify Email Address
                </a>
            </p>
            <p>Or copy and paste this link in your browser:</p>
            <p style='background-color: #f8f9fa; padding: 10px; word-break: break-all;'>
                $verificationLink
            </p>
            <p>This verification link will expire in 24 hours.</p>
            <p>If you did not create an account, no further action is required.</p>
            <hr style='margin: 30px 0;'>
            <p style='color: #6c757d; font-size: 0.9em;'>
                This is an automated message, please do not reply.
            </p>
        </div>";
        
        // Plain text version
        $mail->AltBody = "Welcome to NexLab86!\n\n" .
                        "Hello $username,\n\n" .
                        "Thank you for registering. Please click the link below to verify your email address:\n\n" .
                        "$verificationLink\n\n" .
                        "This verification link will expire in 24 hours.\n\n" .
                        "If you did not create an account, no further action is required.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate email verification token
 */
function validateVerificationToken($token) {
    $db = Database::getInstance();
    
    // Get user with this token
    $sql = "SELECT id, email, verification_expires FROM users 
            WHERE verification_token = ? AND status = 'pending'";
    $stmt = $db->query($sql, [$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid verification token'];
    }
    
    // Check if token has expired
    if (strtotime($user['verification_expires']) < time()) {
        return ['success' => false, 'message' => 'Verification link has expired'];
    }
    
    // Update user status
    $sql = "UPDATE users SET 
            status = 'active',
            verification_token = NULL,
            verification_expires = NULL,
            verified_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    $db->query($sql, [$user['id']]);
    
    return ['success' => true, 'message' => 'Email verified successfully'];
} 