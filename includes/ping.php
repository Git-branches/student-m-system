<?php
// includes/ping.php
require_once __DIR__ . '/../config/session.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    isset($_SESSION['user_id'])
) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(403);
    echo json_encode(['status' => 'error']);
}
exit();
?>