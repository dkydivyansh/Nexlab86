<?php
require_once __DIR__ . '/../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header('Location: /public/dashboard.php');
    exit;
}

// Get message from session if exists
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';

// Clear session messages
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $result = $auth->login($_POST['username'], $_POST['password']);
        if ($result['success']) {
            if (isset($result['redirect'])) {
                header('Location: ' . $result['redirect']);
                exit;
            }
            header('Location: /public/dashboard.php');
            exit;
        } else {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'error';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($action === 'register') {
        $result = $auth->register(
            $_POST['username'], 
            $_POST['email'], 
            $_POST['password'],
            $_POST['confirm_password'] ?? ''
        );
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <meta name="application-name" content="NexLab86">
    <meta property="og:type" content="website">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image" content="<?php echo $ogImage ?? 'https://' . $_SERVER['HTTP_HOST'] . '/assets/NexLab86.png'; ?>">
    <meta property="og:site_name" content="NexLab86">
    <meta property="og:title" content="<?php echo $pageTitle ?? 'NexLab86'; ?>">
    <meta property="og:description" content="Access your secure dashboard to manage data entries, control API access, and monitor your records in real-time.">
    <meta property="og:url" content="https://nexlab86.dkydivyansh.com/public/dashboard.php">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexLab86 - Login/Register</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <h1 class="auth-title">NexLab86</h1>
                <div class="auth-tabs">
                    <button class="auth-tab active" data-tab="login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <button class="auth-tab" data-tab="register">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form id="login-form" class="auth-form active" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="login-username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" id="login-username" name="username" required 
                           placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="login-password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="login-password" name="password" required 
                               placeholder="Enter your password">
                        <button type="button" class="toggle-password" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <!-- Register Form -->
            <form id="register-form" class="auth-form" method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="register-username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" id="register-username" name="username" required 
                           placeholder="Choose a username">
                </div>
                <div class="form-group">
                    <label for="register-email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="register-email" name="email" required 
                           placeholder="Enter your email">
                </div>
                <div class="form-group">
                    <label for="register-password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="register-password" name="password" required 
                               placeholder="Choose a password" minlength="8">
                        <button type="button" class="toggle-password" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="help-text">Password must be at least 8 characters long</small>
                </div>
                <div class="form-group">
                    <label for="register-confirm-password">
                        <i class="fas fa-lock"></i> Confirm Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="register-confirm-password" name="confirm_password" required 
                               placeholder="Confirm your password" minlength="8">
                        <button type="button" class="toggle-password" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.auth-tab');
            const forms = document.querySelectorAll('.auth-form');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const formId = tab.dataset.tab + '-form';
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show/hide forms
                    forms.forEach(form => {
                        if (form.id === formId) {
                            form.style.display = 'block';
                            form.classList.add('active');
                        } else {
                            form.classList.remove('active');
                            form.style.display = 'none';
                        }
                    });
                });
            });

            // Password visibility toggle
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 300);
                }, 5000);
            });

            // Set initial form display
            const activeTab = document.querySelector('.auth-tab.active');
            if (activeTab) {
                const formId = activeTab.dataset.tab + '-form';
                forms.forEach(form => {
                    if (form.id === formId) {
                        form.style.display = 'block';
                        form.classList.add('active');
                    } else {
                        form.classList.remove('active');
                        form.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html> 