<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$auth = new Auth();

// Only check auth for non-public pages
$public_pages = ['index.php', 'api-docs.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $public_pages)) {
    // Check if user is logged in and account exists
    if (!$auth->isLoggedIn()) {
        header('Location: /public/index.php?error=' . urlencode('Your session has expired or your account no longer exists. Please log in again.'));
        exit;
    }

    // Check account status
    if (!$auth->checkAccountStatus()) {
        header('Location: /public/index.php?error=' . urlencode('Your account has been deactivated. Please contact the administrator.'));
        exit;
    }
}

// Check if user is deactivated by checking database
$isDeactivated = false;
if ($auth->isLoggedIn()) {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT status FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $isDeactivated = ($user && $user['status'] === 'deactivated');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'NexLab86'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="preload" href="/assets/fonts/CosmicOcto-Medium.woff2" as="font" type="font/woff2" crossorigin>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
</head>
<body<?php echo $isDeactivated ? ' class="has-deactivation-notice"' : ''; ?>>
    <?php if ($auth->isLoggedIn() && $isDeactivated): ?>
        <div class="deactivation-notice">
            <div class="notice-content">
                <span class="notice-icon">⚠️</span>
                Your account has been deactivated. Please contact the administrator to reactivate your account and restore services.
                <a href="/public/logout.php" class="btn btn-small btn-danger">Logout</a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($auth->isLoggedIn()): ?>
        <nav class="navbar">
            <div class="container">
                <a href="/public/dashboard.php" class="navbar-brand">NexLab86</a>
                <ul class="navbar-nav">
                    <li><a href="/public/dashboard.php">Dashboard</a></li>
                    <?php if ($auth->isAdmin()): ?>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Admin</a>
                        <ul class="dropdown-menu">
                            <li><a href="/admin/users.php">User Management</a></li>
                            <li><a href="/admin/logs.php">API Logs</a></li>
                            <li><a href="/admin/activity.php">Activity Logs</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li><a href="/public/profile.php">Profile</a></li>
                    <li><a href="/public/logout.php">Logout</a></li>
                    <li><a href="/public/api-docs">API documentation</a></li>
                </ul>
            </div>
        </nav>
    <?php endif; ?>
    <div class="container"> 