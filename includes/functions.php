<?php
/**
 * Student Management System - Utility Functions
 * Contains helper functions for various operations throughout the application
 */

/**
 * Format date to readable format
 * @param string $date - Date string from database
 * @param string $format - Desired output format
 * @return string - Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime with time
 * @param string $datetime - Datetime string from database
 * @return string - Formatted datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    return date('F j, Y - h:i A', $timestamp);
}

/**
 * Truncate text to specified length
 * @param string $text - Text to truncate
 * @param int $length - Maximum length
 * @param string $suffix - Suffix to add
 * @return string - Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate random password
 * @param int $length - Password length
 * @return string - Random password
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Generate unique student ID
 * @param PDO $db - Database connection
 * @return string - Unique student ID
 */
function generateStudentID($db) {
    $year = date('Y');
    $prefix = 'STU' . $year;

    $query = "SELECT student_id FROM students 
              WHERE student_id LIKE :prefix 
              ORDER BY student_id DESC LIMIT 1";
    $stmt = $db->prepare($query);

    $search_prefix = $prefix . '%';
    $stmt->bindParam(':prefix', $search_prefix);
    $stmt->execute();

    $last_id = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_id) {
        $last_number = intval(substr($last_id['student_id'], -4));
        $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_number = '0001';
    }

    return $prefix . $new_number;
}

/**
 * Validate email address
 * @param string $email - Email to validate
 * @return bool - True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * @param string $phone - Phone number to validate
 * @return bool - True if valid, false otherwise
 */
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 7 && strlen($phone) <= 15;
}

/**
 * Validate GPA
 * @param float $gpa - GPA to validate
 * @return bool - True if valid, false otherwise
 */
function validateGPA($gpa) {
    return is_numeric($gpa) && $gpa >= 0 && $gpa <= 4.0;
}

/**
 * Get status badge HTML
 * @param string $status - Status value
 * @return string - HTML badge
 */
function getStatusBadge($status) {
    $status = strtolower($status);
    $badge_classes = [
        'active'    => 'status-active',
        'inactive'  => 'status-inactive',
        'graduated' => 'status-graduated',
        'pending'   => 'status-pending',
        'suspended' => 'status-suspended'
    ];

    $class = isset($badge_classes[$status]) ? $badge_classes[$status] : 'status-default';

    return '<span class="status-badge ' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Calculate age from birth date
 * @param string $birth_date - Birth date
 * @return int - Age in years
 */
function calculateAge($birth_date) {
    if (empty($birth_date)) {
        return 0;
    }
    $birth = new DateTime($birth_date);
    $now   = new DateTime();
    return $now->diff($birth)->y;
}

/**
 * Get current year level based on enrollment date
 * @param string $enrollment_date - Enrollment date
 * @return int - Year level (1-5)
 */
function getCurrentYearLevel($enrollment_date) {
    if (empty($enrollment_date)) {
        return 1;
    }

    $enrolled = new DateTime($enrollment_date);
    $now      = new DateTime();
    $years    = $now->diff($enrolled)->y;

    return min($years + 1, 5);
}

/**
 * Calculate academic standing based on GPA
 * @param float $gpa - Student's GPA
 * @return string - Academic standing
 */
function getAcademicStanding($gpa) {
    if ($gpa >= 3.5) {
        return 'Excellent';
    } elseif ($gpa >= 3.0) {
        return 'Good';
    } elseif ($gpa >= 2.0) {
        return 'Satisfactory';
    } elseif ($gpa >= 1.0) {
        return 'Probation';
    } else {
        return 'Academic Warning';
    }
}

/**
 * Get letter grade from percentage
 * @param float $percentage - Score percentage
 * @return string - Letter grade
 */
function getLetterGrade($percentage) {
    if ($percentage >= 90) {
        return 'A';
    } elseif ($percentage >= 80) {
        return 'B';
    } elseif ($percentage >= 70) {
        return 'C';
    } elseif ($percentage >= 60) {
        return 'D';
    } else {
        return 'F';
    }
}

/**
 * Format file size
 * @param int $bytes - Size in bytes
 * @param int $precision - Decimal precision
 * @return string - Formatted file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);

    $exponent = floor(($bytes ? log($bytes) : 0) / log(1024));
    $exponent = min($exponent, count($units) - 1);

    $bytes /= pow(1024, $exponent);

    return round($bytes, $precision) . ' ' . $units[$exponent];
}

/**
 * Sanitize filename for upload
 * @param string $filename - Original filename
 * @return string - Sanitized filename
 */
function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $filename);

    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);

    return $name . '_' . time() . '.' . $ext;
}

/**
 * Check if user has permission
 * @param string $required_role - Required role
 * @param string $user_role - User's role
 * @return bool - True if has permission
 */
function hasPermission($required_role, $user_role) {
    $role_hierarchy = [
        'admin'   => 3,
        'teacher' => 2,
        'student' => 1
    ];

    if (!isset($role_hierarchy[$user_role]) || !isset($role_hierarchy[$required_role])) {
        return false;
    }

    return $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
}

/**
 * Get user's full name
 * @param PDO $db - Database connection
 * @param int $user_id - User ID
 * @return string - Full name
 */
function getUserFullName($db, $user_id) {
    $query = "SELECT full_name FROM users WHERE id = :user_id";
    $stmt  = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['full_name'] : 'Unknown User';
}

/**
 * Get student's full name
 * @param PDO $db - Database connection
 * @param int $student_id - Student ID
 * @return string - Full name
 */
function getStudentFullName($db, $student_id) {
    $query = "SELECT first_name, last_name FROM students WHERE id = :student_id";
    $stmt  = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['first_name'] . ' ' . $result['last_name'] : 'Unknown Student';
}

/**
 * Log system activity
 * @param PDO $db - Database connection
 * @param int $user_id - User ID
 * @param string $action - Action performed
 * @param string $details - Additional details
 * @return bool - True on success
 */
function logActivity($db, $user_id, $action, $details = '') {
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
              VALUES (:user_id, :action, :details, :ip, :agent, NOW())";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':details', $details);

    $ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

    $stmt->bindParam(':ip', $ip);
    $stmt->bindParam(':agent', $agent);

    return $stmt->execute();
}

/**
 * Get student count by status
 * @param PDO $db - Database connection
 * @return array - Student counts by status
 */
function getStudentStats($db) {
    $stats = [];

    $query  = "SELECT status, COUNT(*) as count FROM students GROUP BY status";
    $result = $db->query($query);

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
    }

    return $stats;
}

/**
 * Get course list with student counts
 * @param PDO $db - Database connection
 * @return array - Courses with student counts
 */
function getCourseStats($db) {
    $query = "SELECT course, COUNT(*) as student_count 
              FROM students 
              WHERE status = 'active' 
              GROUP BY course 
              ORDER BY student_count DESC";

    $result = $db->query($query);
    return $result->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Export data to CSV
 * @param array $data - Data to export
 * @param string $filename - Output filename
 */
function exportToCSV($data, $filename = 'export.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }

    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

/**
 * Create pagination links — preserves existing query parameters
 * @param int $current_page - Current page number
 * @param int $total_pages - Total number of pages
 * @param string $url - Base URL (may include existing query string)
 * @return string - HTML pagination
 */
function paginate($current_page, $total_pages, $url) {
    if ($total_pages <= 1) {
        return '';
    }

    // Preserve any existing query params (e.g. ?search=, ?filter=)
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
    $base = strtok($url, '?');

    $pageUrl = function ($p) use ($base, $params) {
        $params['page'] = $p;
        return $base . '?' . http_build_query($params);
    };

    $html = '<div class="pagination">';

    if ($current_page > 1) {
        $html .= '<a href="' . $pageUrl($current_page - 1) . '" class="page-link">&laquo; Previous</a>';
    }

    $start = max(1, $current_page - 2);
    $end   = min($total_pages, $current_page + 2);

    if ($start > 1) {
        $html .= '<a href="' . $pageUrl(1) . '" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= '<a href="' . $pageUrl($i) . '" class="page-link ' . $active . '">' . $i . '</a>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="page-dots">...</span>';
        }
        $html .= '<a href="' . $pageUrl($total_pages) . '" class="page-link">' . $total_pages . '</a>';
    }

    if ($current_page < $total_pages) {
        $html .= '<a href="' . $pageUrl($current_page + 1) . '" class="page-link">Next &raquo;</a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Display flash messages
 * @return string - Flash message HTML
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type    = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';

        $html  = '<div class="flash-message flash-' . $type . '">';
        $html .= '<p>' . htmlspecialchars($message) . '</p>';
        $html .= '<button class="flash-close" onclick="this.parentElement.remove()">&times;</button>';
        $html .= '</div>';

        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return $html;
    }

    return '';
}

/**
 * Set flash message
 * @param string $message - Message to display
 * @param string $type - Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
}

/**
 * Get browser name from user agent string
 * @return string - Browser name (e.g. 'Chrome', 'Firefox', 'Unknown')
 */
function getBrowserInfo(): string {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $browsers = [
        'Chrome'  => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari'  => 'Safari',
        'Edge'    => 'Edg',
        'Opera'   => 'OPR',
        'IE'      => 'MSIE|Trident'
    ];

    foreach ($browsers as $name => $pattern) {
        if (preg_match('/' . $pattern . '/i', $user_agent)) {
            return $name;
        }
    }

    return 'Unknown';
}

/**
 * Get real IP address with proxy detection
 * @return string - Client IP address
 */
function getRealIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For can be a comma-separated list; take only the first (client) IP
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Check if request is AJAX
 * @return bool - True if AJAX request
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Send JSON response
 * @param mixed $data - Data to send
 * @param int $status_code - HTTP status code
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Redirect to URL with optional message
 * @param string $url - Redirect URL
 * @param string $message - Optional flash message
 * @param string $type - Message type
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        setFlashMessage($message, $type);
    }
    header('Location: ' . $url);
    exit();
}

/**
 * Get current URL
 * @return string - Current URL
 */
function getCurrentURL() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri      = $_SERVER['REQUEST_URI'] ?? '';

    return $protocol . '://' . $host . $uri;
}

/**
 * Check if user is logged in
 * @return bool - True if logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login — redirects to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../login.php', 'Please login to access this page', 'warning');
    }
}

/**
 * Get user's role badge HTML
 * @param string $role - User role
 * @return string - Badge HTML
 */
function getRoleBadge($role) {
    $badge_classes = [
        'admin'   => 'badge-admin',
        'teacher' => 'badge-teacher',
        'student' => 'badge-student'
    ];

    $class = isset($badge_classes[$role]) ? $badge_classes[$role] : 'badge-default';

    return '<span class="role-badge ' . $class . '">' . ucfirst($role) . '</span>';
}