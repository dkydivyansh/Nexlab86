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
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $note = $_POST['note'] ?? '';
    $requireAuth = isset($_POST['require_auth']) ? 1 : 0;
    $accessKey = $requireAuth ? ($_POST['access_key'] ?? generateAccessKey()) : null;
    $isDisabled = isset($_POST['is_disabled']) ? 1 : 0;
    
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
    
    // Insert new entry
    $sql = "INSERT INTO data_entries (user_id, type, value, note, require_auth, access_key, is_disabled) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $result = $db->query($sql, [
        $_SESSION['user_id'],
        $type,
        $value,
        $note,
        $requireAuth,
        $accessKey,
        $isDisabled
    ]);
    
    if ($result) {
        $entryId = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Entry created successfully',
            'data' => [
                'id' => $entryId,
                'type' => $type,
                'require_auth' => (bool)$requireAuth,
                'access_key' => $accessKey
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create entry']);
    }
} catch (Exception $e) {
    error_log("Error creating entry: " . $e->getMessage());
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