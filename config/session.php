<?php
// config/session.php
// Make sure session is started only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout (30 minutes) - Use a reasonable timeout
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        if ($inactive_time > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// DON'T automatically regenerate session ID on every page load!
// Only regenerate on login/logout for security
// Remove the automatic regeneration code below:

// REMOVE THIS BLOCK:
/*
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
} elseif (time() - $_SESSION['session_regenerated'] > 300) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}
*/
?>