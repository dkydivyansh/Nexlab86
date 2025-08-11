<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = Database::getInstance();
    }

    public function login($username, $password) {
        try {
            if (empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'Username and password are required'];
            }

            // Get user by username
            $sql = "SELECT id, username, password, role, status FROM users WHERE username = ?";
            $stmt = $this->db->query($sql, [$username]);
            
            if (!$stmt || !($user = $stmt->fetch())) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Check if account is deactivated
            if ($user['status'] === 'deactivated') {
                return ['success' => false, 'message' => 'Your account has been deactivated'];
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // If email is pending verification, redirect to verification page
            if ($user['status'] === 'pending') {
                return [
                    'success' => true,
                    'message' => 'Please verify your email address to continue',
                    'redirect' => '/public/pending_verification.php'
                ];
            }

            return ['success' => true, 'message' => 'Login successful'];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }

    public function register($username, $email, $password, $confirmPassword = '') {
        try {
            // Validate input
            if (empty($username) || empty($email) || empty($password)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
            }

            // Validate confirm password
            if (empty($confirmPassword)) {
                return ['success' => false, 'message' => 'Please confirm your password'];
            }

            if ($password !== $confirmPassword) {
                return ['success' => false, 'message' => 'Passwords do not match'];
            }

            $db = Database::getInstance();

            // Check if username exists
            $sql = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->query($sql, [$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Check if email exists
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $db->query($sql, [$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }

            // Generate verification token and expiry
            $token = generateToken();
            // Set expiration to 20 minutes from now
            $expires = date('Y-m-d H:i:s', strtotime('+20 minutes'));

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert user with pending status
            $sql = "INSERT INTO users (username, email, password, status, verification_token, verification_expires) 
                    VALUES (?, ?, ?, 'pending', ?, ?)";
            $db->query($sql, [$username, $email, $hashedPassword, $token, $expires]);

            // Send verification email
            if (sendVerificationEmail($email, $username, $token)) {
                return [
                    'success' => true, 
                    'message' => 'Registration successful! Please verify your email address. You can login with your username to change email or resend verification link.'
                ];
            } else {
                // If email fails, still create account but notify user
                return [
                    'success' => true, 
                    'message' => 'Registration successful but failed to send verification email. Please login to resend verification email.'
                ];
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration'];
        }
    }

    public function logout() {
        session_start();
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function requireVerification() {
        if (!$this->isLoggedIn()) {
            return false;
        }

        try {
            $sql = "SELECT status FROM users WHERE id = ?";
            $stmt = $this->db->query($sql, [$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            return $user && $user['status'] === 'pending';
        } catch (Exception $e) {
            error_log("Status check error: " . $e->getMessage());
            return false;
        }
    }

    public function checkVerification() {
        if ($this->requireVerification()) {
            header('Location: /public/pending_verification.php');
            exit;
        }
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function checkAccountStatus() {
        if (!$this->isLoggedIn()) {
            return false;
        }

        try {
            $sql = "SELECT status FROM users WHERE id = ?";
            $stmt = $this->db->query($sql, [$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || $user['status'] === 'deactivated') {
                $this->logout();
                return false;
            }
        } catch (Exception $e) {
            error_log("Error checking account status: " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Log admin activity
     * @param string $action The action performed
     * @param string $details Additional details about the action
     * @return bool Whether the logging was successful
     */
    public function logAdminActivity($action, $details = '') {
        if (!$this->isAdmin()) {
            return false;
        }

        try {
            $sql = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)";
            return $this->db->query($sql, [$_SESSION['user_id'], $action, $details]);
        } catch (Exception $e) {
            error_log("Error logging admin activity: " . $e->getMessage());
            return false;
        }
    }
} 