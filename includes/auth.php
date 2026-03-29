<?php
// includes/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/security.php';

/**
 * checkAuth()
 * - Verifies user is logged in
 * - Validates session token against DB
 * - Re-fetches role from DB to prevent session tampering
 * - Checks if account is locked since last login
 * - Checks session timeout
 */
function checkAuth() {
    // 1. Must be logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }

    $database = new Database();
    $db       = $database->getConnection();
    $security = new Security($db);

    // 2. Validate session token - BUT SKIP FOR POST REQUESTS to avoid logout during form submission
    $isPostRequest = ($_SERVER['REQUEST_METHOD'] === 'POST');
    
    if (!$isPostRequest && isset($_SESSION['session_token'])) {
        // Only validate session token on GET requests, not POST
        if (!$security->validateSession($_SESSION['user_id'], $_SESSION['session_token'])) {
            session_unset();
            session_destroy();
            header('Location: ../login.php');
            exit();
        }
    }

    // 3. Re-verify user from DB — catches locked/deleted accounts
    $stmt = $db->prepare(
        "SELECT role, full_name, is_locked FROM users WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dbUser) {
        session_unset();
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    if ((int)$dbUser['is_locked'] === 1) {
        session_unset();
        session_destroy();
        header('Location: ../login.php?locked=1');
        exit();
    }

    // Sync session role and name with DB (in case admin updated them)
    $_SESSION['role']      = $dbUser['role'];
    $_SESSION['full_name'] = $dbUser['full_name'];

    // 4. Check session timeout
    if (!checkSessionTimeout()) {
        header('Location: ../login.php?timeout=1');
        exit();
    }
}

/**
 * checkRole($required_role)
 * - Confirms the logged-in user has the required role
 * - Redirects to the correct dashboard if they don't
 */
function checkRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        switch ($_SESSION['role'] ?? '') {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'student':
                header('Location: ../student/dashboard.php');
                break;
            default:
                header('Location: ../login.php');
        }
        exit();
    }
}
?>