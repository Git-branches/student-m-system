<?php
// students/profile.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuth();
checkRole('student');

$database = new Database();
$db = $database->getConnection();

// Fetch student record linked to current user
$stmt = $db->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    // No student record linked - show error message
    $noRecord = true;
} else {
    $noRecord = false;
    // Also fetch user data for email and full_name consistency
    $stmtUser = $db->prepare("SELECT username, email, full_name FROM users WHERE id = :uid");
    $stmtUser->execute([':uid' => $_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
}

// Handle profile update
$updateSuccess = false;
$updateError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noRecord) {
    // Determine which action: profile update or password change
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        // Profile update
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        // Basic validation
        if (empty($first_name) || empty($last_name)) {
            $updateError = "First name and last name are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updateError = "Invalid email address.";
        } else {
            try {
                // Start transaction
                $db->beginTransaction();

                // Update students table
                $stmt1 = $db->prepare("UPDATE students SET first_name = :first, last_name = :last, email = :email, phone = :phone WHERE user_id = :uid");
                $stmt1->execute([
                    ':first' => $first_name,
                    ':last'  => $last_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':uid'   => $_SESSION['user_id']
                ]);

                // Update users table: full_name and email
                $full_name = $first_name . ' ' . $last_name;
                $stmt2 = $db->prepare("UPDATE users SET full_name = :full, email = :email WHERE id = :uid");
                $stmt2->execute([
                    ':full' => $full_name,
                    ':email' => $email,
                    ':uid'   => $_SESSION['user_id']
                ]);

                $db->commit();

                // Update session full_name
                $_SESSION['full_name'] = $full_name;

                $updateSuccess = true;
                // Refresh student and user data
                $stmt = $db->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
                $stmt->execute([':uid' => $_SESSION['user_id']]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmtUser = $db->prepare("SELECT username, email, full_name FROM users WHERE id = :uid");
                $stmtUser->execute([':uid' => $_SESSION['user_id']]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $db->rollBack();
                $updateError = "An error occurred while saving your information: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $updateError = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $updateError = "New password and confirmation do not match.";
        } elseif (strlen($newPassword) < 8) {
            $updateError = "Password must be at least 8 characters long.";
        } else {
            // Fetch current hashed password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :uid");
            $stmt->execute([':uid' => $_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($currentPassword, $userData['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUp = $db->prepare("UPDATE users SET password = :pwd WHERE id = :uid");
                if ($stmtUp->execute([':pwd' => $newHash, ':uid' => $_SESSION['user_id']])) {
                    $updateSuccess = true;
                } else {
                    $updateError = "Failed to update password. Please try again.";
                }
            } else {
                $updateError = "Current password is incorrect.";
            }
        }
    }
}

// Helper for year level text
$year_label = ['', '1st', '2nd', '3rd', '4th', '5th'];

// Initials for avatar
$name_parts = explode(' ', trim($_SESSION['full_name']));
$initials   = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Student Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <style>
        /* Copy all styles from dashboard, plus additional for forms */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:          #0f1117;
            --ink-mid:      #3d4151;
            --ink-soft:     #7b8094;
            --ink-ghost:    #b0b5c4;
            --surface:      #ffffff;
            --surface-2:    #f5f6f9;
            --surface-3:    #eceef4;
            --accent:       #2a52e8;
            --accent-light: #eaedfc;
            --accent-deep:  #1a3bb5;
            --green:        #16a34a;
            --green-light:  #dcfce7;
            --amber:        #d97706;
            --amber-light:  #fef3c7;
            --red:          #dc2626;
            --red-light:    #fee2e2;
            --blue-light:   #dbeafe;
            --blue:         #1d4ed8;
            --border:       rgba(15,17,23,0.08);
            --border-str:   rgba(15,17,23,0.14);
            --shadow-sm:    0 1px 3px rgba(15,17,23,0.06), 0 4px 12px rgba(15,17,23,0.05);
            --shadow-md:    0 2px 8px rgba(15,17,23,0.07), 0 12px 32px rgba(15,17,23,0.07);
            --r-sm:  6px;
            --r-md:  12px;
            --r-lg:  18px;
            --r-xl:  24px;
        }

        html, body {
            min-height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--surface-2);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            font-size: 15px;
            line-height: 1.6;
        }

        /* NAVBAR (identical) */
        .nav {
            position: sticky; top: 0; z-index: 80;
            height: 60px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 32px;
            gap: 20px;
        }
        .nav-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; flex-shrink: 0;
        }
        .nav-logo-mark {
            width: 30px; height: 30px;
            background: var(--accent); border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
        }
        .nav-logo-text {
            font-size: 14px; font-weight: 500;
            color: var(--ink); letter-spacing: -0.2px;
        }
        .nav-links {
            display: flex; align-items: center; gap: 2px; margin-left: 16px;
        }
        .nav-link {
            padding: 6px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 500; color: var(--ink-soft);
            text-decoration: none; transition: background 0.15s, color 0.15s;
        }
        .nav-link:hover { background: var(--surface-3); color: var(--ink); }
        .nav-link.active { background: var(--accent-light); color: var(--accent); }
        .nav-spacer { flex: 1; }
        .nav-session {
            display: flex; align-items: center; gap: 7px;
            padding: 5px 12px; border-radius: 100px;
            background: var(--surface-2);
            border: 1px solid var(--border-str);
            font-size: 12px; color: var(--ink-soft);
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .nav-session.warn  { background: #fff7ed; border-color: rgba(234,88,12,0.25); color: #c2410c; }
        .nav-session.urgent{ background: #fef2f2; border-color: rgba(220,38,38,0.25); color: var(--red); }
        .nav-session-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--green); flex-shrink: 0;
            animation: dotPulse 2.5s ease-in-out infinite;
        }
        .nav-session.warn   .nav-session-dot { background: #ea580c; }
        .nav-session.urgent .nav-session-dot { background: var(--red); animation: dotBlink .6s ease-in-out infinite; }
        @keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        @keyframes dotBlink { 0%,100%{opacity:1} 50%{opacity:0} }

        .nav-right { display: flex; align-items: center; gap: 10px; }
        .nav-user { display: flex; align-items: center; gap: 8px; }
        .nav-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent-light);
            border: 2px solid var(--accent-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 500;
            color: var(--accent); flex-shrink: 0;
        }
        .nav-name {
            font-size: 13px; font-weight: 500;
            color: var(--ink-mid);
        }
        .nav-logout {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 500;
            color: var(--ink-soft); text-decoration: none;
            border: 1px solid var(--border-str);
            transition: background 0.15s, color 0.15s;
        }
        .nav-logout:hover { background: var(--surface-3); color: var(--ink); }

        /* Page container */
        .page {
            max-width: 1040px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        /* Hero banner (simplified) */
        .hero {
            background: var(--ink);
            border-radius: var(--r-xl);
            padding: 32px 36px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }
        .hero-grid-bg {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
        }
        .hero-left { position: relative; z-index: 1; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 100px;
            background: rgba(42,82,232,0.2);
            border: 1px solid rgba(42,82,232,0.3);
            font-size: 11px; font-weight: 500;
            color: #93abff; letter-spacing: 0.5px;
            text-transform: uppercase; margin-bottom: 14px;
        }
        .hero-eyebrow-dot { width: 5px; height: 5px; border-radius: 50%; background: #93abff; animation: dotPulse 2.5s ease-in-out infinite; }
        .hero-name {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(26px, 3vw, 38px);
            color: #ffffff; line-height: 1.1;
            letter-spacing: -0.8px; margin-bottom: 8px;
        }
        .hero-name em { font-style: italic; color: #93abff; }
        .hero-sub {
            font-size: 13px; font-weight: 300;
            color: rgba(255,255,255,0.45); line-height: 1.6;
        }

        /* Form Cards */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 28px;
            overflow: hidden;
        }
        .form-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card-header-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-card-header-icon.blue { background: var(--accent-light); }
        .form-card-header-icon.green { background: var(--green-light); }
        .form-card-header-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }
        .form-card-body {
            padding: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--ink-mid);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            border: 1px solid var(--border-str);
            border-radius: var(--r-md);
            background: var(--surface);
            transition: 0.15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(42,82,232,0.1);
        }
        .form-control.readonly {
            background: var(--surface-2);
            color: var(--ink-soft);
            cursor: default;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--r-md);
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        .btn-primary:hover {
            background: var(--accent-deep);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary {
            background: var(--surface-3);
            color: var(--ink-mid);
        }
        .btn-secondary:hover {
            background: var(--surface-3);
            filter: brightness(0.96);
        }
        .alert {
            padding: 12px 16px;
            border-radius: var(--r-md);
            margin-bottom: 24px;
            font-size: 13px;
        }
        .alert-success {
            background: var(--green-light);
            border-left: 3px solid var(--green);
            color: #166534;
        }
        .alert-danger {
            background: var(--red-light);
            border-left: 3px solid var(--red);
            color: #991b1b;
        }
        .info-strip {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: var(--accent-light);
            border: 1px solid rgba(42,82,232,0.15);
            border-radius: var(--r-md);
            padding: 13px 16px;
            margin-top: 14px;
            font-size: 13px;
            font-weight: 300;
            color: var(--accent-deep);
        }
        .no-record {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: var(--r-xl);
            padding: 64px 32px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        .no-record h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--ink-mid);
            margin-bottom: 8px;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 700px) {
            .row { grid-template-columns: 1fr; }
            .nav { padding: 0 16px; }
            .page { padding: 20px 14px 48px; }
            .hero { padding: 24px 20px; }
            .nav-name { display: none; }
            .nav-session span { display: none; }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<nav class="nav">
    <a href="dashboard.php" class="nav-logo">
        <div class="nav-logo-mark">
            <svg width="17" height="17" viewBox="0 0 18 18" fill="none">
                <path d="M3 4.5C3 3.67 3.67 3 4.5 3H13.5C14.33 3 15 3.67 15 4.5V5.5C15 6.33 14.33 7 13.5 7H4.5C3.67 7 3 6.33 3 5.5V4.5Z" fill="white" fill-opacity="0.95"/>
                <path d="M3 9.5C3 8.67 3.67 8 4.5 8H9.5C10.33 8 11 8.67 11 9.5C11 10.33 10.33 11 9.5 11H4.5C3.67 11 3 10.33 3 9.5Z" fill="white" fill-opacity="0.6"/>
                <path d="M3 13.5C3 12.67 3.67 12 4.5 12H7.5C8.33 12 9 12.67 9 13.5C9 14.33 8.33 15 7.5 15H4.5C3.67 15 3 14.33 3 13.5Z" fill="white" fill-opacity="0.35"/>
            </svg>
        </div>
        <span class="nav-logo-text">SMS Portal</span>
    </a>
     <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="profile.php"  class="nav-link active">Profile</a>
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-session" id="navSession">
        <span class="nav-session-dot" id="navDot"></span>
        <span id="navSessionTxt">Session active</span>
    </div>
    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?php echo $initials; ?></div>
            <span class="nav-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
        <a href="../logout.php" class="nav-logout">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none">
                <path d="M5 2H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                <path d="M9.5 9.5L12 7l-2.5-2.5M12 7H5.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Logout
        </a>
    </div>
</nav>

<div class="page">
    <?php if ($noRecord): ?>
        <div class="no-record">
            <div class="no-record-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ink-ghost)" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h2>No student record linked</h2>
            <p>Your account is not yet linked to a student record.<br>Please contact your administrator.</p>
        </div>
    <?php else: ?>

        <!-- Hero banner -->
        <div class="hero">
            <div class="hero-grid-bg"></div>
            <div class="hero-left">
                <div class="hero-eyebrow">
                    <span class="hero-eyebrow-dot"></span>
                    Profile Settings
                </div>
                <h1 class="hero-name">
                    Edit your<br>
                    <em>personal details</em>
                </h1>
                <p class="hero-sub">
                    <?php echo htmlspecialchars($student['course']); ?> &nbsp;•&nbsp; 
                    <?php echo $year_label[(int)$student['year_level']] ?? ''; ?> Year
                </p>
            </div>
        </div>

        <!-- Alert messages -->
        <?php if ($updateSuccess): ?>
            <div class="alert alert-success">
                ✅ Changes saved successfully.
            </div>
        <?php elseif ($updateError): ?>
            <div class="alert alert-danger">
                ❌ <?php echo htmlspecialchars($updateError); ?>
            </div>
        <?php endif; ?>

        <!-- Profile update form -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2a52e8" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="form-card-header-title">Edit Profile Information</div>
            </div>
            <div class="form-card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? $user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username (read-only)</label>
                        <input type="text" class="form-control readonly" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password change form -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div class="form-card-header-title">Change Password</div>
            </div>
            <div class="form-card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small style="font-size: 11px; color: var(--ink-soft);">At least 8 characters.</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Information strip -->
        <div class="info-strip">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>Your student ID, course, year level, and GPA cannot be changed here. Please contact the registrar for corrections.</span>
        </div>

    <?php endif; ?>
</div>

<!-- Session timeout script (same as dashboard) -->
<script>
(function () {
    var TIMEOUT     = 50;
    var WARN_BEFORE = 20;
    var LOGIN_URL   = '../login.php';
    var PING_URL    = '../includes/ping.php';
    var CIRCUM      = 2 * Math.PI * 34;

    var lastActivity = Date.now();
    var warningShown = false;
    var redirected   = false;

    function pingServer() {
        fetch(PING_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function(err){ console.warn('Ping failed', err); });
    }

    function updateNavPill(remaining) {
        var pill = document.getElementById('navSession');
        var txt  = document.getElementById('navSessionTxt');
        if (!pill || !txt) return;
        if (remaining <= 60) {
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            var display = m > 0 ? m + 'm ' + String(s).padStart(2,'0') + 's left' : s + 's left';
            txt.textContent = display;
            pill.className = 'nav-session ' + (remaining <= 30 ? 'urgent' : 'warn');
        } else {
            var mins = Math.floor(remaining / 60);
            txt.textContent = mins + ' min remaining';
            pill.className = 'nav-session' + (remaining < 300 ? ' warn' : '');
        }
    }

    function doRedirect() {
        if (redirected) return;
        redirected = true;
        window.location.href = LOGIN_URL + '?timeout=1';
    }

    function buildModal() {
        if (document.getElementById('st-overlay')) return;
        var div = document.createElement('div');
        div.id  = 'st-overlay';
        div.setAttribute('role', 'dialog');
        div.style.position = 'fixed';
        div.style.inset = '0';
        div.style.zIndex = '99999';
        div.style.background = 'rgba(15,17,23,0.6)';
        div.style.backdropFilter = 'blur(5px)';
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.justifyContent = 'center';
        div.innerHTML = `
            <div style="background:white; border-radius:22px; max-width:400px; width:100%; padding:36px 32px 30px; text-align:center; box-shadow:0 32px 80px rgba(15,17,23,0.18); position:relative;">
                <div style="position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#ea580c,#f97316,#ea580c); background-size:200% 100%; border-radius:22px 22px 0 0; animation: shimmer 2s linear infinite;"></div>
                <div style="width:60px; height:60px; border-radius:50%; background:#fff7ed; border:1px solid rgba(234,88,12,0.2); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; color:#ea580c;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
                <p style="font-family:'DM Serif Display',serif; font-size:22px; margin-bottom:8px;">Session expiring soon</p>
                <p style="font-size:13px; color:var(--ink-soft); margin-bottom:24px;">Your session is about to expire due to inactivity.<br>Move your mouse or click <strong>Stay logged in</strong>.</p>
                <div style="position:relative; width:80px; height:80px; margin:0 auto 8px;">
                    <svg width="80" height="80" viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="34" fill="none" stroke="#eceef4" stroke-width="5"/>
                        <circle id="st-ring" cx="40" cy="40" r="34" fill="none" stroke="#ea580c" stroke-width="5" stroke-linecap="round" stroke-dasharray="${CIRCUM.toFixed(2)}" stroke-dashoffset="0"/>
                    </svg>
                    <div id="st-num" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:22px; font-family:'DM Serif Display',serif; color:#ea580c;">--</div>
                </div>
                <p id="st-lbl" style="font-size:11px; color:var(--ink-ghost); margin-bottom:24px;">seconds before automatic logout</p>
                <div style="display:flex; gap:10px;">
                    <button id="st-stay" style="flex:1; background:#2a52e8; color:white; border:none; padding:12px; border-radius:8px; font-weight:500;">Stay logged in</button>
                    <button id="st-out" style="flex:1; background:transparent; border:1px solid var(--border-str); padding:12px; border-radius:8px;">Log out now</button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
        document.getElementById('st-stay').onclick = function() { pingServer(); lastActivity = Date.now(); hideModal(); };
        document.getElementById('st-out').onclick = function() { window.location.href = LOGIN_URL + '?logout=1'; };
    }

    function showModal(remaining) {
        warningShown = true;
        buildModal();
        updateModal(remaining);
    }
    function updateModal(remaining) {
        var numEl = document.getElementById('st-num');
        var ringEl = document.getElementById('st-ring');
        var lblEl = document.getElementById('st-lbl');
        if (!numEl) return;
        var secs = Math.max(0, remaining);
        numEl.textContent = secs;
        if (ringEl) ringEl.style.strokeDashoffset = (CIRCUM * (1 - secs / WARN_BEFORE)).toFixed(2);
        if (secs <= 10) {
            numEl.style.color = '#dc2626';
            if (ringEl) ringEl.style.stroke = '#dc2626';
            if (lblEl) lblEl.innerHTML = '<strong style="color:#dc2626;">Logging out in '+secs+'s!</strong>';
        } else {
            numEl.style.color = '#ea580c';
            if (ringEl) ringEl.style.stroke = '#ea580c';
            if (lblEl) lblEl.textContent = 'seconds before automatic logout';
        }
    }
    function hideModal() {
        warningShown = false;
        var el = document.getElementById('st-overlay');
        if (el) el.remove();
    }

    ['mousemove','keydown','click','scroll','touchstart'].forEach(function(e) {
        document.addEventListener(e, function() {
            var now = Date.now();
            if (now - lastActivity > 30000) pingServer();
            lastActivity = now;
            if (warningShown) hideModal();
        }, { passive: true });
    });

    setInterval(function () {
        if (redirected) return;
        var idle = Math.floor((Date.now() - lastActivity) / 1000);
        var remaining = Math.max(0, TIMEOUT - idle);
        updateNavPill(remaining);
        if (remaining <= 0) doRedirect();
        else if (remaining <= WARN_BEFORE && !warningShown) showModal(remaining);
        else if (warningShown) updateModal(remaining);
    }, 1000);
})();
</script>
</body>
</html>