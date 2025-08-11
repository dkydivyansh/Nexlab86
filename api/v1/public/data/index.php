<?php
require_once __DIR__ . '/../../../../includes/db.php';

// Ensure CORS headers are sent before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // Cache for 24 hours
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get request parameters
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    // Log bad request
    $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
            VALUES (NULL, ?, ?, ?, ?)";
    $db->query($sql, [
        getClientIP(),
        $_SERVER['REQUEST_METHOD'],
        'Missing ID parameter',
        400
    ]);
    echo json_encode([
        'success' => false,
        'error' => 'ID is required',
        'code' => 'MISSING_ID'
    ]);
    exit;
}

$db = Database::getInstance();

// Get the data entry and user status
$sql = "SELECT d.*, u.status as user_status 
        FROM data_entries d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?";
$stmt = $db->query($sql, [$id]);
$entry = $stmt->fetch();

if (!$entry) {
    http_response_code(404);
    // Log not found error
    $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
            VALUES (?, ?, ?, ?, ?)";
    $db->query($sql, [
        $id,
        getClientIP(),
        $_SERVER['REQUEST_METHOD'],
        'Entry not found',
        404
    ]);
    echo json_encode([
        'success' => false,
        'error' => 'Entry not found',
        'code' => 'ENTRY_NOT_FOUND'
    ]);
    exit;
}
// Check if user account is deactivated
if ($entry['user_status'] === 'deactivated') {
    http_response_code(403);
    // Log deactivated user entry access attempt
    $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
            VALUES (?, ?, ?, ?, ?)";
    $db->query($sql, [
        $entry['id'],
        getClientIP(),
        $_SERVER['REQUEST_METHOD'],
        'Deactivated user entry access attempt',
        403
    ]);
    echo json_encode([
        'success' => false,
        'error' => 'This data entry is not available as the associated account is deactivated',
        'code' => 'ACCOUNT_DEACTIVATED'
    ]);
    exit;
}

// Check if entry is disabled
if ($entry['is_disabled']) {
    http_response_code(403);
    // Log disabled entry access attempt
    $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
            VALUES (?, ?, ?, ?, ?)";
    $db->query($sql, [
        $entry['id'],
        getClientIP(),
        $_SERVER['REQUEST_METHOD'],
        'Disabled entry access attempt',
        403
    ]);
    echo json_encode([
        'success' => false,
        'error' => 'This data entry is temporarily disabled',
        'code' => 'ENTRY_DISABLED'
    ]);
    exit;
}

// Check access key if required - moved before method handling
if ($entry['require_auth']) {
    // Check access key from either GET parameters or JSON body
    $access_key = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $access_key = $_GET['access_key'] ?? '';
    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $access_key = $jsonData['access_key'] ?? '';
    }
    
    if ($access_key !== $entry['access_key']) {
        http_response_code(401);
        // Log authentication failure
        $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
                VALUES (?, ?, ?, ?, ?)";
        $db->query($sql, [
            $entry['id'],
            getClientIP(),
            $_SERVER['REQUEST_METHOD'],
            json_encode(['access_key_attempt' => $access_key]),
            401
        ]);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing access key',
            'code' => 'INVALID_ACCESS_KEY'
        ]);
        exit;
    }
}

// Handle different request methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Log successful GET request
        $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, response_code) 
                VALUES (?, ?, ?, ?)";
        $db->query($sql, [
            $entry['id'],
            getClientIP(),
            'GET',
            200
        ]);

        // Format the value based on type
        $value = $entry['value'];
        
        // For JSON or array types, decode the value
        if ($entry['type'] === 'json' || $entry['type'] === 'array') {
            // First, check if it's already a valid JSON string
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                // If not, try to clean up and decode
                $cleanValue = str_replace(['\\r\\n', '\r\n', '\\n', '\n'], '', $value);
                $cleanValue = preg_replace('/\s+$/m', '', $cleanValue);
                $cleanValue = stripslashes($cleanValue); // Remove escaped slashes
                
                $decoded = json_decode($cleanValue, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                } else {
                    // If still not valid JSON, try one more cleanup
                    $cleanValue = str_replace('\"', '"', $cleanValue);
                    $decoded = json_decode($cleanValue, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
            }
        }

        // Return the data with proper JSON formatting
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'value' => $value,
                'created_at' => $entry['created_at'],
                'updated_at' => $entry['updated_at']
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'PUT':
        // Parse input data
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            // Log invalid JSON input
            $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->query($sql, [
                $entry['id'],
                getClientIP(),
                'PUT',
                $rawInput,
                400
            ]);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON input: ' . json_last_error_msg(),
                'code' => 'INVALID_JSON'
            ]);
            exit;
        }
        
        // Only check for type and value, access_key was already validated
        if (!isset($input['type']) || !isset($input['value'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Type and value are required',
                'code' => 'MISSING_FIELDS'
            ]);
            exit;
        }

        // Validate type hasn't changed
        if ($input['type'] !== $entry['type']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Cannot change data type',
                'code' => 'INVALID_TYPE_CHANGE'
            ]);
            exit;
        }

        // Validate and format value based on type
        $value = $input['value'];
        $validationError = validateValue($entry['type'], $value);
        if ($validationError) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $validationError,
                'code' => 'INVALID_VALUE'
            ]);
            exit;
        }

        try {
            // Start transaction
            $db->beginTransaction();

            // Update the entry
            $sql = "UPDATE data_entries SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $db->query($sql, [$value, $entry['id']]);

            // Log successful PUT request
            $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->query($sql, [
                $entry['id'],
                getClientIP(),
                'PUT',
                $rawInput,
                200
            ]);

            $db->commit();

            // Format the response value
            if ($entry['type'] === 'json' || $entry['type'] === 'array') {
                try {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded; // Keep as array/object for JSON encoding
                    }
                } catch (Exception $e) {
                    error_log("JSON formatting error: " . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Entry updated successfully',
                'data' => [
                    'id' => $entry['id'],
                    'type' => $entry['type'],
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("API Error: " . $e->getMessage());
            http_response_code(500);
            
            // Log server error
            $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->query($sql, [
                $entry['id'],
                getClientIP(),
                'PUT',
                json_encode(['error' => $e->getMessage()]),
                500
            ]);
            
            echo json_encode([
                'success' => false,
                'error' => 'An error occurred while updating the entry',
                'code' => 'UPDATE_ERROR'
            ]);
        }
        break;

    default:
        http_response_code(405);
        // Log method not allowed
        $sql = "INSERT INTO api_logs (data_entry_id, ip_address, request_method, request_data, response_code) 
                VALUES (?, ?, ?, ?, ?)";
        $db->query($sql, [
            $entry['id'],
            getClientIP(),
            $_SERVER['REQUEST_METHOD'],
            'Method not allowed',
            405
        ]);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'code' => 'METHOD_NOT_ALLOWED'
        ]);
        break;
}

function validateValue($type, &$value) {
    switch ($type) {
        case 'string':
            if (!is_string($value)) {
                if (is_numeric($value) || is_bool($value)) {
                    $value = (string)$value;
                } else {
                    return 'Value must be a string';
                }
            }
            // Clean up string formatting
            $value = trim($value);
            break;

        case 'number':
            if (is_string($value)) {
                $value = trim($value);
            }
            if (!is_numeric($value) || strpos($value, '.') !== false) {
                return 'Value must be an integer';
            }
            // Convert to proper integer
            $value = (int)$value;
            break;

        case 'float':
            if (is_string($value)) {
                $value = trim($value);
            }
            if (!is_numeric($value)) {
                return 'Value must be a number';
            }
            // Convert to proper float
            $value = (float)$value;
            break;

        case 'boolean':
            if (is_string($value)) {
                $value = trim(strtolower($value));
                if (in_array($value, ['true', '1'])) {
                    $value = true;
                } elseif (in_array($value, ['false', '0'])) {
                    $value = false;
                } else {
                    return 'Value must be a boolean (true/false)';
                }
            } elseif (!is_bool($value)) {
                if (is_numeric($value)) {
                    $value = (bool)$value;
                } else {
                    return 'Value must be a boolean (true/false)';
                }
            }
            break;

        case 'json':
            if (is_string($value)) {
                // First, try to decode as is
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If failed, try cleaning up the string
                    $cleanValue = str_replace(['\\r\\n', '\r\n', '\\n', '\n'], '', $value);
                    $cleanValue = preg_replace('/\s+$/m', '', $cleanValue);
                    $cleanValue = stripslashes($cleanValue);
                    $decoded = json_decode($cleanValue, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return 'Value must be a valid JSON';
                    }
                }
                $value = $decoded;
            } elseif (!is_array($value) && !is_object($value)) {
                return 'Value must be a valid JSON object';
            }
            
            // Store as a clean JSON string
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;

        case 'array':
            if (is_string($value)) {
                if (strpos($value, '[') === 0) {
                    // Try to parse as JSON array
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return 'Value must be a valid array';
                    }
                    $value = $decoded;
                } else {
                    // Handle comma-separated values
                    $value = array_map('trim', explode(',', $value));
                }
            } elseif (!is_array($value)) {
                return 'Value must be an array';
            }
            
            // Store as a clean JSON string
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            break;
    }
    return null;
}

function getClientIP() {
    // Check for CloudFlare IP
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    
    // Check for proxy IPs
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Get first IP from list (client's real IP)
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    
    // Check other proxy headers
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED'];
    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            return trim($_SERVER[$header]);
        }
    }
    
    // Get direct remote address
    if (isset($_SERVER['REMOTE_ADDR'])) {
        // If it's localhost IPv6, return IPv4 equivalent
        if ($_SERVER['REMOTE_ADDR'] === '::1') {
            return '127.0.0.1';
        }
        return $_SERVER['REMOTE_ADDR'];
    }
    
    return 'UNKNOWN';
} 