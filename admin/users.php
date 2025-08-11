<?php
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();

// Check if user is admin
if (!$auth->isAdmin()) {
    header('Location: /public/dashboard.php');
    exit;
}

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'User Management - NexLab86';
require_once __DIR__ . '/../templates/header.php';

$db = Database::getInstance();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $message = '';
    $messageType = '';

    if (!empty($userId)) {
        switch ($action) {
            case 'activate':
                $sql = "UPDATE users SET status = 'active' WHERE id = ? AND role != 'admin'";
                $result = $db->query($sql, [$userId]);
                if ($result) {
                    $auth->logAdminActivity('ACTIVATE_USER', "Activated user ID: $userId");
                }
                $_SESSION['message'] = $result ? 'User activated successfully' : 'Failed to activate user';
                $_SESSION['messageType'] = $result ? 'admin-success-message' : 'error';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;

            case 'deactivate':
                $sql = "UPDATE users SET status = 'deactivated' WHERE id = ? AND role != 'admin'";
                $result = $db->query($sql, [$userId]);
                if ($result) {
                    $auth->logAdminActivity('DEACTIVATE_USER', "Deactivated user ID: $userId");
                }
                $_SESSION['message'] = $result ? 'User deactivated successfully' : 'Failed to deactivate user';
                $_SESSION['messageType'] = $result ? 'admin-success-message' : 'error';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;

            case 'reset_password':
                // First get the username
                $userSql = "SELECT username FROM users WHERE id = ?";
                $userStmt = $db->query($userSql, [$userId]);
                $userData = $userStmt->fetch();
                
                // Generate a random password
                $newPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $sql = "UPDATE users SET password = ? WHERE id = ? AND role != 'admin'";
                $result = $db->query($sql, [$hashedPassword, $userId]);
                
                if ($result) {
                    $auth->logAdminActivity('RESET_PASSWORD', "Reset password for user ID: $userId");
                    $_SESSION['message'] = '<div class="admin-password-reset">' .
                              '<div class="message-header">' .
                              '<span>Password Reset Successful</span>' .
                              '<button type="button" class="btn-close" onclick="closeMessage(this.parentElement.parentElement.parentElement)">&times;</button>' .
                              '</div>' .
                              '<div class="message-content">' .
                              '<p>New password for user <strong>' . htmlspecialchars($userData['username']) . '</strong>:</p>' .
                              '<div class="password-container">' .
                              '<code class="new-password">' . htmlspecialchars($newPassword) . '</code>' .
                              '<button type="button" class="btn btn-small btn-copy" onclick="copyPassword(this)" data-password="' . htmlspecialchars($newPassword) . '">' .
                              '<span class="copy-text">Copy</span>' .
                              '<span class="copied-text">Copied!</span>' .
                              '</button>' .
                              '</div></div></div>';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to reset password';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;

            case 'delete':
                // First check if user exists and is not an admin
                $checkSql = "SELECT role, username FROM users WHERE id = ?";
                $checkStmt = $db->query($checkSql, [$userId]);
                $userToDelete = $checkStmt->fetch();

                if ($userToDelete && $userToDelete['role'] !== 'admin') {
                    // Delete user's entries first
                    $deleteEntriesSql = "DELETE FROM entries WHERE user_id = ?";
                    $db->query($deleteEntriesSql, [$userId]);

                    // Then delete the user
                    $deleteUserSql = "DELETE FROM users WHERE id = ? AND role != 'admin'";
                    $result = $db->query($deleteUserSql, [$userId]);

                    if ($result) {
                        $auth->logAdminActivity('DELETE_USER', "Deleted user ID: $userId, Username: {$userToDelete['username']}");
                        $_SESSION['message'] = 'User account and all associated data deleted successfully';
                        $_SESSION['messageType'] = 'admin-success-message';
                    } else {
                        $_SESSION['message'] = 'Failed to delete user account';
                        $_SESSION['messageType'] = 'error';
                    }
                } else {
                    $_SESSION['message'] = 'Cannot delete admin account';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;
        }
    }
}

// Get message from session if exists
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['messageType'] ?? '';

// Clear the session messages
unset($_SESSION['message'], $_SESSION['messageType']);

// Get all users except current admin
$sql = "SELECT id, username, email, role, status, created_at FROM users WHERE id != ? ORDER BY created_at DESC";
$stmt = $db->query($sql, [$_SESSION['user_id']]);
$users = $stmt->fetchAll();
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>User Management</h1>
        <div class="search-container">
            <input type="text" id="userSearch" class="form-control" placeholder="Search by username or email...">
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <?php if (strpos($message, 'admin-password-reset') !== false): ?>
            <?php echo $message; // Don't escape HTML for password reset message ?>
        <?php else: ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <div class="message-header">
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button type="button" class="btn-close" onclick="closeMessage(this.parentElement.parentElement)">×</button>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="users-table">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr class="<?php echo $user['status'] === 'deactivated' ? 'user-deactivated' : ''; ?>">
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $user['status']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                    <td class="actions">
                        <?php if ($user['role'] !== 'admin'): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                
                                <?php if ($user['status'] === 'deactivated'): ?>
                                    <button type="submit" name="action" value="activate" class="btn btn-small btn-success">
                                        Activate
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="deactivate" class="btn btn-small btn-warning">
                                        Deactivate
                                    </button>
                                <?php endif; ?>
                                
                                <button type="submit" name="action" value="reset_password" class="btn btn-small btn-secondary"
                                        onclick="return confirm('Are you sure you want to reset this user\'s password? A new random password will be generated.')">
                                    Reset Password
                                </button>
                                <button type="submit" name="action" value="delete" class="btn btn-small btn-danger"
                                        onclick="return confirm('WARNING: This will permanently delete the user account and all associated data. This action cannot be undone. Are you sure you want to proceed?')">
                                    Delete Account
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('userSearch');
    const userRows = document.querySelectorAll('.users-table tbody tr');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        userRows.forEach(row => {
            const username = row.querySelector('td:first-child').textContent.toLowerCase();
            const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const matches = username.includes(searchTerm) || email.includes(searchTerm);
            row.style.display = matches ? '' : 'none';
        });
    });

    // Prevent auto-hide for password reset messages
    const messages = document.querySelectorAll('.message, .admin-message, .admin-password-reset');
    messages.forEach(message => {
        // Clear any existing timeouts
        if (message.dataset.hideTimeout) {
            clearTimeout(parseInt(message.dataset.hideTimeout));
        }
        
        // Don't auto-hide admin password reset messages
        if (message.classList.contains('admin-password-reset')) {
            message.style.opacity = '1';
            message.style.display = 'block';
            return;
        }
        
        // Handle other messages as needed
        if (!message.classList.contains('admin-password-reset')) {
            message.dataset.hideTimeout = setTimeout(() => {
                message.remove();
            }, 5000);
        }
    });
});

function closeMessage(messageElement) {
    if (!messageElement) return;
    
    // Get the actual message container if clicked from a child element
    const container = messageElement.closest('.message, .admin-message, .admin-password-reset');
    if (!container) return;
    
    // Clear any existing timeouts
    if (container.dataset.hideTimeout) {
        clearTimeout(parseInt(container.dataset.hideTimeout));
    }
    
    // Remove the message
    container.remove();
}

function copyPassword(button) {
    const password = button.getAttribute('data-password');
    navigator.clipboard.writeText(password).then(() => {
        button.classList.add('copied');
        setTimeout(() => {
            button.classList.remove('copied');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy password:', err);
    });
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?> 