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

$pageTitle = 'API Logs';
require_once __DIR__ . '/../templates/header.php';

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle clear logs action
    if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
        try {
            // First log the admin action
            $sql = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'CLEAR_API_LOGS', 'Cleared all API logs')";
            $db->query($sql, [$_SESSION['user_id']]);
            
            // Then clear the logs
            $sql = "DELETE FROM api_logs";
            $db->query($sql);
            
            $_SESSION['success_message'] = 'API logs cleared successfully';
        } catch (Exception $e) {
            error_log("Error clearing logs: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to clear logs: ' . $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // Handle filter form
    else {
        $method = $_POST['method'] ?? '';
        $ipAddress = $_POST['ip_address'] ?? '';
        $responseCode = $_POST['response_code'] ?? '';
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';
        
        // Redirect with filter parameters as GET parameters
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query([
            'method' => $method,
            'ip_address' => $ipAddress,
            'response_code' => $responseCode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]));
        exit;
    }
}

// Get filter parameters from GET
$method = isset($_GET['method']) ? trim($_GET['method']) : '';
$ipAddress = isset($_GET['ip_address']) ? trim($_GET['ip_address']) : '';
$responseCode = isset($_GET['response_code']) ? (int)$_GET['response_code'] : 0;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build the base query - remove user association
$baseQuery = "FROM api_logs al 
              LEFT JOIN data_entries de ON al.data_entry_id = de.id 
              WHERE 1=1";
$params = [];

// Add filters to query
if ($method) {
    $baseQuery .= " AND al.request_method = ?";
    $params[] = $method;
}
if ($ipAddress) {
    $baseQuery .= " AND al.ip_address LIKE ?";
    $params[] = "%$ipAddress%";
}
if ($responseCode) {
    $baseQuery .= " AND al.response_code = ?";
    $params[] = $responseCode;
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

// Get logs with pagination - remove username
$query = "SELECT al.*, de.type as data_type " . $baseQuery . " 
          ORDER BY al.timestamp DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->query($query, $params);
$logs = $stmt->fetchAll();

// Get unique response codes for filter dropdown
$responseCodesQuery = "SELECT DISTINCT response_code FROM api_logs ORDER BY response_code";
$responseCodesStmt = $db->query($responseCodesQuery);
$responseCodes = $responseCodesStmt->fetchAll();
?>

<div class="admin-container">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <div class="message-header">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <button type="button" class="btn-close" onclick="closeMessage(this.parentElement.parentElement)">×</button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error">
            <div class="message-header">
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                <button type="button" class="btn-close" onclick="closeMessage(this.parentElement.parentElement)">×</button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="admin-header">
        <h1>API Logs</h1>
    </div>

    <!-- Filters -->
    <div class="filters-container">
        <form method="POST" class="filters-form" id="filterForm">
            <div class="filter-group">
                <label for="method">Request Method:</label>
                <select id="method" name="method" class="form-control">
                    <option value="">All Methods</option>
                    <option value="GET" <?php echo $method === 'GET' ? 'selected' : ''; ?>>GET</option>
                    <option value="PUT" <?php echo $method === 'PUT' ? 'selected' : ''; ?>>PUT</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="ip_address">IP Address:</label>
                <input type="text" id="ip_address" name="ip_address" 
                       value="<?php echo htmlspecialchars($ipAddress); ?>" 
                       class="form-control" placeholder="Filter by IP...">
            </div>
            
            <div class="filter-group">
                <label for="response_code">Response Code:</label>
                <select id="response_code" name="response_code" class="form-control">
                    <option value="">All Codes</option>
                    <?php foreach ($responseCodes as $code): ?>
                        <option value="<?php echo $code['response_code']; ?>" 
                                <?php echo $responseCode == $code['response_code'] ? 'selected' : ''; ?>>
                            <?php echo $code['response_code']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">Date From:</label>
                <input type="date" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($dateFrom); ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Date To:</label>
                <input type="date" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($dateTo); ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="logs.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Clear Logs Container -->
    <div class="clear-logs-container">
        <form method="POST" id="clearLogsForm" onsubmit="return confirmClearLogs()">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn-danger">Clear All Logs</button>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="logs-table">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Method</th>
                    <th>IP Address</th>
                    <th>Data Entry</th>
                    <th>Response Code</th>
                    <th>Request Data</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No API logs found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                            <td>
                                <span class="method-badge <?php echo strtolower($log['request_method']); ?>">
                                    <?php echo htmlspecialchars($log['request_method']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td>
                                <?php if ($log['data_entry_id']): ?>
                                    ID: <?php echo htmlspecialchars($log['data_entry_id']); ?>
                                    (<?php echo htmlspecialchars($log['data_type']); ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $log['response_code'] >= 400 ? 'error' : 'success'; ?>">
                                    <?php echo htmlspecialchars($log['response_code']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['request_data']): ?>
                                    <button type="button" class="btn btn-small btn-secondary" 
                                            onclick="showRequestData(this)" 
                                            data-request='<?php echo htmlspecialchars($log['request_data']); ?>'>
                                        View Data
                                    </button>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
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
                <a href="?page=<?php echo ($page - 1); ?>&method=<?php echo urlencode($method); ?>&ip_address=<?php echo urlencode($ipAddress); ?>&response_code=<?php echo $responseCode; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">&laquo; Previous</a>
            <?php endif; ?>
            
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo ($page + 1); ?>&method=<?php echo urlencode($method); ?>&ip_address=<?php echo urlencode($ipAddress); ?>&response_code=<?php echo $responseCode; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Request Data Modal -->
    <div id="request-data-modal" class="modal hidden">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Request Data</h2>
            <pre id="request-data-content"></pre>
        </div>
    </div>
</div>

<style>
.clear-logs-container {
    margin: 20px 0;
    text-align: right;
}

.warning-text {
    color: #dc3545;
    font-weight: bold;
    margin: 15px 0;
}

#clear-logs-modal .modal-content {
    max-width: 400px;
}

#clear-logs-modal .form-group {
    margin: 20px 0;
}

#clear-logs-modal .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
}

.btn-danger:hover {
    background-color: #c82333;
}
</style>

<script>
function showRequestData(button) {
    const modal = document.getElementById('request-data-modal');
    const content = document.getElementById('request-data-content');
    const data = button.getAttribute('data-request');
    
    try {
        // Try to parse and format JSON
        const jsonData = JSON.parse(data);
        content.textContent = JSON.stringify(jsonData, null, 2);
    } catch (e) {
        // If not JSON, show as is
        content.textContent = data;
    }
    
    modal.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking the close button or outside the modal
    const modal = document.getElementById('request-data-modal');
    const closeBtn = modal.querySelector('.close');
    
    closeBtn.onclick = function() {
        modal.classList.add('hidden');
    }
    
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    }
});

function confirmClearLogs() {
    return confirm('Are you absolutely sure you want to clear all API logs? This action cannot be undone.');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?> 