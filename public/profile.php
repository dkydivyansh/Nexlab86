<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: /public/index.php');
    exit;
}

// Check if email is verified
$auth->checkVerification();

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Profile Management - NexLab86';
require_once __DIR__ . '/../templates/header.php';

// Get user data
$db = Database::getInstance();
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $db->query($sql, [$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get message from session if exists
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';

// Clear session messages
unset($_SESSION['message'], $_SESSION['message_type']);

// Check if user is deactivated
$isDeactivated = $user['status'] === 'deactivated';

// Redirect deactivated users if they try to submit the form
if ($isDeactivated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['message'] = 'Your account is deactivated. Profile updates are not allowed.';
    $_SESSION['message_type'] = 'error';
    header('Location: /public/profile.php');
    exit;
}

// Handle form submission only if user is not deactivated
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isDeactivated) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_email') {
        $newEmail = $_POST['email'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        
        $errors = [];
        
        // Validate email
        if (empty($newEmail)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Validate current password
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required to change email';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        // Check if new email is same as current
        if ($newEmail === $user['email']) {
            $errors[] = 'New email must be different from your current email';
        }
        
        // Check if email is already taken by another user
        if (empty($errors)) {
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $db->query($sql, [$newEmail, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Email is already registered';
            }
        }
        
        if (empty($errors)) {
            // Generate verification token
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+20 minutes'));
            
            // Start transaction
            $db->beginTransaction();
            try {
                // Update user's email and set status to pending
                $sql = "UPDATE users SET email = ?, status = 'pending', verification_token = ?, verification_expires = ? WHERE id = ?";
                if ($db->query($sql, [$newEmail, $token, $expires, $_SESSION['user_id']])) {
                    // Send verification email
                    if (sendVerificationEmail($newEmail, $user['username'], $token)) {
                        $db->commit();
                        $_SESSION['message'] = 'Email updated. Please verify your new email address to continue using your account.';
                        $_SESSION['message_type'] = 'success';
                        
                        // Log the email change attempt
                        $sql = "INSERT INTO admin_logs (user_id, action, details) VALUES (?, 'email_change', ?)";
                        $db->query($sql, [$_SESSION['user_id'], "Email changed from {$user['email']} to {$newEmail}"]);
                        
                        // Redirect to verification page since status is now pending
                        header('Location: /public/pending_verification.php');
                        exit;
                    } else {
                        // Rollback if email sending fails
                        $db->rollBack();
                        $_SESSION['message'] = 'Failed to send verification email. Please try again later.';
                        $_SESSION['message_type'] = 'error';
                    }
                } else {
                    $db->rollBack();
                    $_SESSION['message'] = 'Failed to update email. Please try again.';
                    $_SESSION['message_type'] = 'error';
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Email change error: " . $e->getMessage());
                $_SESSION['message'] = 'An error occurred. Please try again later.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = implode('<br>', $errors);
            $_SESSION['message_type'] = 'error';
        }
        header('Location: /public/profile.php');
        exit;
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Validate passwords
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }
        
        if (empty($errors)) {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            if ($db->query($sql, [$hashedPassword, $_SESSION['user_id']])) {
                $_SESSION['message'] = 'Password updated successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update password. Please try again.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = implode('<br>', $errors);
            $_SESSION['message_type'] = 'error';
        }
        header('Location: /public/profile.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - NexLab86</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <h1>Profile Management</h1>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($isDeactivated): ?>
                <div class="message error">
                    Your account is currently deactivated. Please contact support for assistance.
                </div>
            <?php endif; ?>

            <div class="profile-sections">
                <!-- Email Change Section -->
                <div class="profile-section">
                    <h2><i class="fas fa-envelope"></i> Change Email</h2>
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_email">
                        <div class="form-group">
                            <label for="current-email">Current Email</label>
                            <input type="email" id="current-email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="email">New Email</label>
                            <input type="email" id="email" name="email" required placeholder="Enter new email">
                        </div>
                        <div class="form-group">
                            <label for="current-password-email">Current Password</label>
                            <div class="password-input">
                                <input type="password" id="current-password-email" name="current_password" required 
                                       placeholder="Enter current password">
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" <?php echo $isDeactivated ? 'disabled' : ''; ?>>
                            Update Email
                        </button>
                    </form>
                </div>

                <!-- Password Change Section -->
                <div class="profile-section">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_password">
                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <div class="password-input">
                                <input type="password" id="current-password" name="current_password" required 
                                       placeholder="Enter current password">
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <div class="password-input">
                                <input type="password" id="new-password" name="new_password" required 
                                       placeholder="Enter new password">
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="help-text">Password must be at least 8 characters long</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <div class="password-input">
                                <input type="password" id="confirm-password" name="confirm_password" required 
                                       placeholder="Confirm new password">
                                <button type="button" class="toggle-password" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" <?php echo $isDeactivated ? 'disabled' : ''; ?>>
                            Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
</body>
</html> 