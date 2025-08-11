<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

$db = Database::getInstance();
$sql = "SELECT * FROM data_entries WHERE id = ? AND user_id = ?";
$stmt = $db->query($sql, [$id, $_SESSION['user_id']]);
$entry = $stmt->fetch();

if (!$entry) {
    echo json_encode(['success' => false, 'message' => 'Entry not found']);
    exit;
}

// Include API domain in response
$apiDomain = getApiDomain();

// Update the API endpoint path
$apiEndpoint = $apiDomain . '/api/v1/public/data';  // Use forward slashes for URLs

echo json_encode([
    'success' => true,
    'id' => $entry['id'],
    'type' => $entry['type'],
    'value' => $entry['value'],
    'note' => $entry['note'],
    'require_auth' => (bool)$entry['require_auth'],
    'access_key' => $entry['require_auth'] ? $entry['access_key'] : null,
    'api_domain' => $apiDomain,
    'api_endpoint' => $apiEndpoint,
    'is_disabled' => (bool)$entry['is_disabled'],
]); 