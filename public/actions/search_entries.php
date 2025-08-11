<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    $params = [$user_id];
    $conditions = ['user_id = ?'];
    
    if ($search) {
        $conditions[] = "(id LIKE ? OR value LIKE ? OR note LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($type) {
        $conditions[] = "type = ?";
        $params[] = $type;
    }

    $whereClause = implode(' AND ', $conditions);
    $sql = "SELECT * FROM data_entries WHERE $whereClause ORDER BY created_at DESC";
    
    $stmt = $db->query($sql, $params);
    $entries = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'entries' => array_map(function($entry) {
            return [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'value' => $entry['value'],
                'note' => $entry['note'],
                'require_auth' => (bool)$entry['require_auth'],
                'access_key' => $entry['access_key'],
                'is_disabled' => (bool)$entry['is_disabled'],
                'created_at' => date('Y-m-d H:i', strtotime($entry['created_at'])),
                'updated_at' => date('Y-m-d H:i', strtotime($entry['updated_at']))
            ];
        }, $entries)
    ]);
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 