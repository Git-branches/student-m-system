<?php
require_once __DIR__ . '/../config/database.php';

class Security {
    private $conn;
    private $max_login_attempts = 5;
    private $lockout_time = 900; // 15 minutes in seconds
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // 1. Password Hashing
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // 2. Limit Login Attempts
    public function checkLoginAttempts($username, $ip) {
        $query = "SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                  FROM login_attempts 
                  WHERE (username = :username OR ip_address = :ip) 
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL :lockout SECOND)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':lockout', $this->lockout_time);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempts'] >= $this->max_login_attempts;
    }
    
    public function recordLoginAttempt($username, $ip, $success) {
        $query = "INSERT INTO login_attempts (username, ip_address, success) 
                  VALUES (:username, :ip, :success)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':success', $success);
        $stmt->execute();
        
        if (!$success) {
            // Lock user account if too many failed attempts
            $this->checkAndLockUser($username);
        }
    }
    
    private function checkAndLockUser($username) {
        $query = "SELECT COUNT(*) as failed_attempts 
                  FROM login_attempts 
                  WHERE username = :username 
                  AND success = 0 
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['failed_attempts'] >= 10) {
            $update = "UPDATE users SET is_locked = TRUE WHERE username = :username";
            $stmt = $this->conn->prepare($update);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
        }
    }
    
    // 3. Session Management
    public function createSession($user_id, $session_token) {
        $query = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
                  VALUES (:user_id, :token, :ip, :agent)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $session_token);
        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':agent', $_SERVER['HTTP_USER_AGENT']);
        return $stmt->execute();
    }
    
    public function validateSession($user_id, $session_token) {
        $query = "SELECT * FROM user_sessions 
                  WHERE user_id = :user_id 
                  AND session_token = :token 
                  AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $session_token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update last activity
            $update = "UPDATE user_sessions SET last_activity = NOW() 
                       WHERE session_token = :token";
            $stmt = $this->conn->prepare($update);
            $stmt->bindParam(':token', $session_token);
            $stmt->execute();
            return true;
        }
        return false;
    }
    
    // 4. Input Sanitization
    public function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    // 5. CSRF Protection
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }
}
?>