<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// If user is not logged in, redirect to login
if (!$auth->isLoggedIn()) {
    header('Location: /public/index.php');
    exit;
}

// If user is already verified, redirect to dashboard
if (!$auth->requireVerification()) {
    header('Location: /public/dashboard.php');
    exit;
}

$db = Database::getInstance();

// Get message from session if exists
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';

// Clear session messages
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'resend') {
        // Check if enough time has passed since last email (20 minutes)
        $sql = "SELECT email, username, verification_token, verification_expires FROM users WHERE id = ?";
        $stmt = $db->query($sql, [$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && strtotime($user['verification_expires']) < time()) {
            // Generate new token and update expiration
            $token = generateToken();
            // Set expiration to 20 minutes from now
            $expires = date('Y-m-d H:i:s', strtotime('+20 minutes'));
            
            $sql = "UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?";
            $db->query($sql, [$token, $expires, $_SESSION['user_id']]);
            
            if (sendVerificationEmail($user['email'], $user['username'], $token)) {
                $_SESSION['message'] = 'Verification email has been resent. Please check your inbox.';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to send verification email. Please try again later.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Please wait 20 minutes before requesting another verification email.';
            $_SESSION['message_type'] = 'error';
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'change_email') {
        $newEmail = $_POST['new_email'] ?? '';
        
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = 'Invalid email format';
            $_SESSION['message_type'] = 'error';
        } else {
            // Check if new email is same as current email
            $sql = "SELECT email FROM users WHERE id = ?";
            $stmt = $db->query($sql, [$_SESSION['user_id']]);
            $currentUser = $stmt->fetch();
            
            if ($newEmail === $currentUser['email']) {
                $_SESSION['message'] = 'New email address must be different from your current email';
                $_SESSION['message_type'] = 'error';
            } else {
                // Check if email is already taken
                $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt = $db->query($sql, [$newEmail, $_SESSION['user_id']]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = 'Email is already registered';
                    $_SESSION['message_type'] = 'error';
                } else {
                    // Update email and generate new verification token
                    $token = generateToken();
                    // Set expiration to 20 minutes for new email verification
                    $expires = date('Y-m-d H:i:s', strtotime('+20 minutes'));
                    
                    $sql = "UPDATE users SET email = ?, verification_token = ?, verification_expires = ? WHERE id = ?";
                    $db->query($sql, [$newEmail, $token, $expires, $_SESSION['user_id']]);
                    
                    // Get username for email
                    $sql = "SELECT username FROM users WHERE id = ?";
                    $stmt = $db->query($sql, [$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if (sendVerificationEmail($newEmail, $user['username'], $token)) {
                        $_SESSION['message'] = 'Email updated successfully. Please check your new email for verification.';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to send verification email. Please try again later.';
                        $_SESSION['message_type'] = 'error';
                    }
                }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get current email and check resend timer
$sql = "SELECT email, verification_expires, TIMESTAMPDIFF(MINUTE, NOW(), verification_expires) as minutes_remaining FROM users WHERE id = ?";
$stmt = $db->query($sql, [$_SESSION['user_id']]);
$user = $stmt->fetch();
$currentEmail = $user['email'];

// Calculate time remaining before can resend
$timeRemaining = max(0, $user['minutes_remaining']);
$canResend = $timeRemaining <= 0;
$waitMinutes = $timeRemaining;

// Add to verification status display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Required</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <h1 class="auth-title">Email Verification Required</h1>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="verification-status">
                <p>A verification email has been sent to <strong><?php echo htmlspecialchars($currentEmail); ?></strong></p>
                <p>Please check your email and click the verification link to activate your account.</p>
                <?php if (!$canResend): ?>
                    <p class="timer-info">You can request another verification email in <strong><?php echo $waitMinutes; ?> minutes</strong></p>
                <?php endif; ?>
            </div>

            <div class="verification-actions">
                <form method="POST" class="resend-form">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn btn-primary btn-block" <?php echo $canResend ? '' : 'disabled'; ?>>
                        <i class="fas fa-paper-plane"></i> Resend Verification Email
                    </button>
                    <?php if (!$canResend): ?>
                        <small class="help-text">Please wait <?php echo $waitMinutes; ?> minutes before requesting another email.</small>
                    <?php endif; ?>
                </form>

                <div class="divider">
                    <span>OR</span>
                </div>

                <div class="wrong-email">
                    <p>Wrong email address?</p>
                    <form method="POST" class="change-email-form">
                        <input type="hidden" name="action" value="change_email">
                        <div class="form-group">
                            <label for="new_email">
                                <i class="fas fa-envelope"></i> Enter New Email
                            </label>
                            <input type="email" id="new_email" name="new_email" required 
                                   placeholder="Enter your correct email">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-block">
                            <i class="fas fa-sync-alt"></i> Update Email & Resend Verification
                        </button>
                    </form>
                </div>

                <div class="logout-link">
                    <a href="/public/logout.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
</body>
</html> 