<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/security.php';

$database = new Database();
$db = $database->getConnection();
$security = new Security($db);

// Clear session from database
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    $query = "DELETE FROM user_sessions WHERE user_id = :user_id AND session_token = :token";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':token', $_SESSION['session_token']);
    $stmt->execute();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>