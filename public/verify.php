<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Email Verification';
$message = '';
$messageType = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validate token
    $result = validateVerificationToken($token);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
} else {
    $message = 'Invalid verification link';
    $messageType = 'error';
}

// Don't include header.php for verification page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexLab86 - Email Verification</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="verification-container">
            <h1>Email Verification</h1>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="verification-actions">
                <?php if ($messageType === 'success'): ?>
                    <p>You can now <a href="/public/index.php">login to your account</a>.</p>
                <?php else: ?>
                    <p>Return to <a href="/public/index.php">login page</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .verification-container {
        max-width: 500px;
        margin: 50px auto;
        padding: 30px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .verification-container h1 {
        margin-bottom: 30px;
        color: #333;
    }

    .verification-actions {
        margin-top: 30px;
    }

    .verification-actions a {
        color: #007bff;
        text-decoration: none;
    }

    .verification-actions a:hover {
        text-decoration: underline;
    }
    </style>
</body>
</html> 