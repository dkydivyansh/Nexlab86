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

$pageTitle = 'Admin Activity Logs';
require_once __DIR__ . '/../templates/header.php';

$db = Database::getInstance();

// Handle form submission for filters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId = $_POST['admin_id'] ?? '';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    
    // Redirect with filter parameters as GET parameters
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query([
        'action' => $action,
        'admin_id' => $adminId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ]));
    exit;
}

// Get filter parameters from GET
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build the base query
$baseQuery = "FROM admin_logs al LEFT JOIN users u ON al.admin_id = u.id WHERE 1=1";
$params = [];

// Add filters to query
if ($action) {
    $baseQuery .= " AND al.action LIKE ?";
    $params[] = "%$action%";
}
if ($adminId) {
    $baseQuery .= " AND al.admin_id = ?";
    $params[] = $adminId;
}
if ($dateFrom) {
    $baseQuery .= " AND DATE(al.timestamp) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $baseQuery .= " AND DATE(al.timestamp) <= ?";
    $params[] = $dateTo;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total " . $baseQuery;
$totalStmt = $db->query($countQuery, $params);
$total = $totalStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Get logs with pagination
$query = "SELECT al.*, u.username " . $baseQuery . " ORDER BY al.timestamp DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->query($query, $params);
$logs = $stmt->fetchAll();

// Get unique admin users for filter dropdown
$adminQuery = "SELECT DISTINCT u.id, u.username FROM users u 
               INNER JOIN admin_logs al ON u.id = al.admin_id 
               WHERE u.role = 'admin' 
               ORDER BY u.username";
$adminStmt = $db->query($adminQuery);
$admins = $adminStmt->fetchAll();
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Admin Activity Logs</h1>
    </div>

    <!-- Filters -->
    <div class="filters-container">
        <form method="POST" class="filters-form">
            <div class="filter-group">
                <label for="action">Action:</label>
                <input type="text" id="action" name="action" value="<?php echo htmlspecialchars($action); ?>" 
                       class="form-control" placeholder="Filter by action...">
            </div>
            
            <div class="filter-group">
                <label for="admin_id">Admin User:</label>
                <select id="admin_id" name="admin_id" class="form-control">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" 
                                <?php echo $adminId == $admin['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">Date From:</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Date To:</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="activity.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="logs-table">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Admin User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No activity logs found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                            <td><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'System'; ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1); ?>&action=<?php echo urlencode($action); ?>&admin_id=<?php echo $adminId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">&laquo; Previous</a>
            <?php endif; ?>
            
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo ($page + 1); ?>&action=<?php echo urlencode($action); ?>&admin_id=<?php echo $adminId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?> 