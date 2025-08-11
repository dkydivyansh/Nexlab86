<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get POST data
    $id = $_POST['id'] ?? '';
    
    // Verify ownership and delete
    $sql = "DELETE FROM data_entries WHERE id = ? AND user_id = ?";
    $result = $db->query($sql, [$id, $_SESSION['user_id']]);
    
    if ($result && $result->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Entry deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entry not found or unauthorized']);
    }
} catch (Exception $e) {
    error_log("Error deleting entry: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 