<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $note = $_POST['note'] ?? '';
    $requireAuth = isset($_POST['require_auth']) ? 1 : 0;
    $accessKey = $requireAuth ? ($_POST['access_key'] ?? generateAccessKey()) : null;
    $isDisabled = isset($_POST['is_disabled']) ? 1 : 0;
    
    // Verify ownership
    $sql = "SELECT id FROM data_entries WHERE id = ? AND user_id = ?";
    $stmt = $db->query($sql, [$id, $_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Entry not found or unauthorized']);
        exit;
    }
    
    // Validate data type
    $validTypes = ['string', 'number', 'float', 'boolean', 'json', 'array'];
    if (!in_array($type, $validTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data type']);
        exit;
    }
    
    // Validate value based on type
    if (!validateValue($type, $value)) {
        echo json_encode(['success' => false, 'message' => 'Invalid value for type ' . $type]);
        exit;
    }
    
    // Update entry
    $sql = "UPDATE data_entries 
            SET type = ?, value = ?, note = ?, require_auth = ?, access_key = ?, is_disabled = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?";
    
    $result = $db->query($sql, [
        $type,
        $value,
        $note,
        $requireAuth,
        $accessKey,
        $isDisabled,
        $id,
        $_SESSION['user_id']
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Entry updated successfully',
            'data' => [
                'id' => $id,
                'type' => $type,
                'value' => $value,
                'note' => $note,
                'require_auth' => (bool)$requireAuth,
                'access_key' => $accessKey,
                'is_disabled' => (bool)$isDisabled
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update entry']);
    }
} catch (Exception $e) {
    error_log("Error updating entry: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function validateValue($type, $value) {
    switch ($type) {
        case 'string':
            return is_string($value);
        case 'number':
            return is_numeric($value) && strpos($value, '.') === false;
        case 'float':
            return is_numeric($value);
        case 'boolean':
            return in_array(strtolower($value), ['true', 'false', '1', '0']);
        case 'json':
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        case 'array':
            return is_array(explode(',', $value));
        default:
            return false;
    }
}

function generateAccessKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
} 