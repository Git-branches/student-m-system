<?php
/**
 * Student Management System - Landing Page
 */

session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header('Location: student/dashboard.php');
        exit();
    }
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$total_students = 0;
$active_students = 0;
$total_courses = 0;

try {
    $total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $active_students = $db->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $courses_query = $db->query("SELECT COUNT(DISTINCT course) FROM students WHERE course IS NOT NULL");
    $total_courses = $courses_query->fetchColumn();
} catch (PDOException $e) {
    error_log("Database query failed: " . $e->getMessage());
}

$message = '';
$message_type = '';

if (isset($_GET['registered'])) {
    $message = 'Registration successful! Please login.';
    $message_type = 'success';
} elseif (isset($_GET['timeout'])) {
    $message = 'Your session has expired. Please login again.';
    $message_type = 'warning';
} elseif (isset($_GET['logout'])) {
    $message = 'You have been successfully logged out.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Login & Record Management System">
    <title>Student Management System | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink: #0f1117;
            --ink-mid: #3d4151;
            --ink-soft: #7b8094;
            --ink-ghost: #b0b5c4;
            --surface: #ffffff;
            --surface-2: #f5f6f9;
            --surface-3: #eceef4;
            --accent: #2a52e8;
            --accent-light: #eaedfc;
            --accent-deep: #1a3bb5;
            --border: rgba(15,17,23,0.09);
            --border-strong: rgba(15,17,23,0.18);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 32px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            font-size: 16px;
            color: var(--ink);
            background: var(--surface);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* NAV */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            height: 64px;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-logo-mark {
            width: 32px; height: 32px;
            background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .nav-logo-text {
            font-size: 15px;
            font-weight: 500;
            color: var(--ink);
            letter-spacing: -0.2px;
        }

        .nav-actions { display: flex; align-items: center; gap: 8px; }

        .btn-ghost {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            color: var(--ink-mid);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            border: none; background: none; cursor: pointer;
        }

        .btn-ghost:hover { background: var(--surface-3); color: var(--ink); }

        .btn-primary {
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            color: white;
            background: var(--accent);
            text-decoration: none;
            border: none; cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            font-family: inherit;
        }

        .btn-primary:hover { background: var(--accent-deep); }
        .btn-primary:active { transform: scale(0.98); }

        /* TOAST */
        .toast {
            position: fixed;
            top: 80px; right: 24px;
            z-index: 200;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-md);
            padding: 14px 20px;
            font-size: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 340px;
            animation: slideIn 0.35s ease;
        }

        .toast-success { border-left: 3px solid #22c55e; }
        .toast-warning { border-left: 3px solid #f59e0b; }

        .toast-close {
            margin-left: auto;
            background: none; border: none; cursor: pointer;
            color: var(--ink-soft); font-size: 18px; line-height: 1;
            padding: 0 2px;
        }

        @keyframes slideIn {
            from { transform: translateX(24px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* HERO */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 100px 40px 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 100%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 100%);
        }

        .hero-glow {
            position: absolute;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            width: 900px; height: 600px;
            background: radial-gradient(ellipse, rgba(42,82,232,0.1) 0%, transparent 65%);
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1160px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            border-radius: 100px;
            background: var(--accent-light);
            border: 1px solid rgba(42,82,232,0.2);
            font-size: 12px;
            font-weight: 500;
            color: var(--accent);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .hero-eyebrow-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        .hero-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(40px, 5vw, 64px);
            line-height: 1.08;
            letter-spacing: -1.5px;
            color: var(--ink);
            margin-bottom: 20px;
        }

        .hero-heading em { font-style: italic; color: var(--accent); }

        .hero-sub {
            font-size: 17px;
            font-weight: 300;
            color: var(--ink-mid);
            line-height: 1.65;
            margin-bottom: 36px;
            max-width: 480px;
        }

        .hero-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        .btn-large {
            padding: 13px 28px;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            border: none; cursor: pointer;
        }

        .btn-large-primary { background: var(--accent); color: white; }
        .btn-large-primary:hover {
            background: var(--accent-deep);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(42,82,232,0.3);
        }

        .btn-large-secondary {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--border-strong) !important;
        }
        .btn-large-secondary:hover { background: var(--surface-2); }

        /* Dashboard preview */
        .hero-visual { position: relative; }

        .dashboard-preview {
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.12);
            position: relative;
        }

        .preview-header {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .preview-title { font-size: 14px; font-weight: 500; color: var(--ink); }

        .preview-badge {
            padding: 3px 10px;
            border-radius: 100px;
            font-size: 11px; font-weight: 500;
            background: #dcfce7; color: #166534;
        }

        .preview-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .preview-stat {
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            padding: 14px 12px;
            border: 1px solid var(--border);
        }

        .preview-stat-val {
            font-size: 22px; font-weight: 500;
            color: var(--ink); letter-spacing: -0.5px;
        }

        .preview-stat-label { font-size: 11px; color: var(--ink-soft); margin-top: 3px; }

        .preview-list { display: flex; flex-direction: column; gap: 8px; }

        .preview-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
        }

        .preview-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 500;
            flex-shrink: 0;
        }

        .preview-row-name { font-size: 13px; font-weight: 500; color: var(--ink); flex: 1; }
        .preview-row-course { font-size: 11px; color: var(--ink-soft); }

        .preview-chip {
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 11px; font-weight: 500;
        }

        .chip-active { background: #dcfce7; color: #166534; }
        .chip-pending { background: #fef3c7; color: #92400e; }

        .float-card {
            position: absolute;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            font-size: 13px;
        }

        .float-card-1 { top: -20px; left: -40px; display: flex; align-items: center; gap: 10px; }
        .float-card-2 { bottom: 30px; right: -30px; }

        .shield-icon {
            width: 32px; height: 32px;
            background: var(--accent-light);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        /* TRUST STRIP */
        .trust-strip {
            padding: 32px 40px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .trust-inner {
            max-width: 1160px; margin: 0 auto;
            display: flex; align-items: center; gap: 40px;
        }

        .trust-label {
            font-size: 12px; font-weight: 500;
            color: var(--ink-ghost);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        .trust-items { display: flex; gap: 32px; flex-wrap: wrap; align-items: center; }

        .trust-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--ink-soft);
        }

        .trust-dot { width: 5px; height: 5px; border-radius: 50%; background: #22c55e; }

        /* FEATURES */
        .features { padding: 100px 40px; background: var(--surface-2); }

        .features-inner { max-width: 1160px; margin: 0 auto; }

        .section-eyebrow {
            font-size: 12px; font-weight: 500;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }

        .section-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(32px, 4vw, 48px);
            letter-spacing: -1px; line-height: 1.1;
            color: var(--ink); margin-bottom: 16px;
        }

        .section-sub {
            font-size: 16px; font-weight: 300;
            color: var(--ink-mid);
            max-width: 520px; line-height: 1.65;
            margin-bottom: 56px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .feature-item {
            background: var(--surface);
            padding: 36px 32px;
            transition: background 0.2s;
        }

        .feature-item:hover { background: #fafbff; }

        .feature-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 18px;
            background: var(--surface-2);
            border: 1px solid var(--border);
        }

        .feature-name { font-size: 16px; font-weight: 500; color: var(--ink); margin-bottom: 8px; }
        .feature-desc { font-size: 14px; font-weight: 300; color: var(--ink-soft); line-height: 1.6; }

        /* STATS */
        .stats { padding: 100px 40px; background: var(--ink); position: relative; overflow: hidden; }

        .stats-pattern {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .stats-inner {
            position: relative; z-index: 1;
            max-width: 1160px; margin: 0 auto;
            display: grid; grid-template-columns: 1fr 2fr;
            gap: 80px; align-items: start;
        }

        .stats-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(32px, 3.5vw, 44px);
            color: white; letter-spacing: -1px; line-height: 1.15;
        }

        .stats-heading em { font-style: italic; color: rgba(255,255,255,0.5); }

        .stats-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .stat-cell { background: rgba(255,255,255,0.04); padding: 36px 32px; transition: background 0.2s; }
        .stat-cell:hover { background: rgba(255,255,255,0.07); }

        .stat-num {
            font-family: 'DM Serif Display', serif;
            font-size: 52px; color: white;
            letter-spacing: -2px; line-height: 1; margin-bottom: 8px;
        }

        .stat-unit { font-size: 24px; color: rgba(255,255,255,0.4); }
        .stat-lbl { font-size: 14px; color: rgba(255,255,255,0.45); font-weight: 300; }

        /* SECURITY */
        .security { padding: 100px 40px; background: var(--surface); }

        .security-inner {
            max-width: 1160px; margin: 0 auto;
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 80px; align-items: center;
        }

        .check-item {
            display: flex; align-items: flex-start; gap: 16px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }

        .check-item:last-child { border-bottom: none; }

        .check-icon {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: var(--accent-light);
            border: 1px solid rgba(42,82,232,0.2);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-top: 1px;
        }

        .check-title { font-size: 15px; font-weight: 500; color: var(--ink); margin-bottom: 3px; }
        .check-desc { font-size: 13px; font-weight: 300; color: var(--ink-soft); line-height: 1.5; }

        /* CTA */
        .cta { padding: 100px 40px; background: var(--accent); position: relative; overflow: hidden; }

        .cta-circles {
            position: absolute; top: -200px; right: -200px;
            width: 600px; height: 600px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .cta-circles::before {
            content: '';
            position: absolute; top: 60px; left: 60px; right: 60px; bottom: 60px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .cta-inner { position: relative; z-index: 1; max-width: 1160px; margin: 0 auto; text-align: center; }

        .cta-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(36px, 5vw, 60px);
            color: white; letter-spacing: -1.5px; line-height: 1.08; margin-bottom: 20px;
        }

        .cta-sub { font-size: 17px; color: rgba(255,255,255,0.75); font-weight: 300; margin-bottom: 40px; }

        .btn-white {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 32px;
            border-radius: var(--radius-md);
            font-size: 15px; font-weight: 500;
            background: white; color: var(--accent);
            text-decoration: none;
            transition: all 0.15s;
            border: none; cursor: pointer; font-family: inherit;
        }

        .btn-white:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,0.15); }

        /* FOOTER */
        footer { background: var(--ink); padding: 40px; }

        .footer-inner {
            max-width: 1160px; margin: 0 auto;
            display: flex; align-items: center;
            justify-content: space-between;
            gap: 20px; flex-wrap: wrap;
        }

        .footer-left { display: flex; align-items: center; gap: 12px; }

        .footer-mark {
            width: 28px; height: 28px;
            background: rgba(255,255,255,0.1);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
        }

        .footer-name { font-size: 14px; color: rgba(255,255,255,0.6); }
        .footer-right { font-size: 13px; color: rgba(255,255,255,0.35); }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            nav { padding: 0 20px; }
            .hero { padding: 90px 20px 60px; }
            .hero-inner { grid-template-columns: 1fr; gap: 48px; }
            .float-card { display: none; }
            .features { padding: 64px 20px; }
            .features-grid { grid-template-columns: 1fr; }
            .stats { padding: 64px 20px; }
            .stats-inner { grid-template-columns: 1fr; gap: 48px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .security { padding: 64px 20px; }
            .security-inner { grid-template-columns: 1fr; gap: 40px; }
            .cta { padding: 64px 20px; }
            footer { padding: 32px 20px; }
            .trust-strip { padding: 24px 20px; }
            .trust-inner { flex-direction: column; align-items: flex-start; gap: 16px; }
        }

        .reveal {
            opacity: 0; transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .reveal.in { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>

<?php if ($message): ?>
<div class="toast toast-<?php echo htmlspecialchars($message_type); ?>" id="toast">
    <span><?php echo htmlspecialchars($message); ?></span>
    <button class="toast-close" onclick="document.getElementById('toast').remove()">&#215;</button>
</div>
<?php endif; ?>

<!-- NAV -->
<nav>
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-mark">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M3 4.5C3 3.67 3.67 3 4.5 3H13.5C14.33 3 15 3.67 15 4.5V5.5C15 6.33 14.33 7 13.5 7H4.5C3.67 7 3 6.33 3 5.5V4.5Z" fill="white" fill-opacity="0.9"/>
                <path d="M3 9.5C3 8.67 3.67 8 4.5 8H9.5C10.33 8 11 8.67 11 9.5C11 10.33 10.33 11 9.5 11H4.5C3.67 11 3 10.33 3 9.5Z" fill="white" fill-opacity="0.6"/>
                <path d="M3 13.5C3 12.67 3.67 12 4.5 12H7.5C8.33 12 9 12.67 9 13.5C9 14.33 8.33 15 7.5 15H4.5C3.67 15 3 14.33 3 13.5Z" fill="white" fill-opacity="0.4"/>
            </svg>
        </div>
        <span class="nav-logo-text">SMS Portal</span>
    </a>
    <div class="nav-actions">
        <a href="#features" class="btn-ghost">Features</a>
        <a href="#security" class="btn-ghost">Security</a>
        <a href="login.php" class="btn-primary">Sign In &rarr;</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot"></span>
                Academic Record System &middot; v1.0
            </div>
            <h1 class="hero-heading">
                Manage every<br><em>student record</em><br>with clarity.
            </h1>
            <p class="hero-sub">
                A secure, role-based platform for administrators and students. Real-time data, CAPTCHA-protected login, and full audit trail &mdash; all in one place.
            </p>
            <div class="hero-cta">
                <a href="login.php" class="btn-large btn-large-primary">
                    Sign in to portal
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M3 8h10M9 4l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="#features" class="btn-large btn-large-secondary">Explore features</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="float-card float-card-1">
                <div class="shield-icon">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1.5L2.5 4v4c0 3 2.5 5.5 5.5 6 3-0.5 5.5-3 5.5-6V4L8 1.5Z" stroke="#2a52e8" stroke-width="1.2" fill="none"/>
                        <path d="M5.5 8l2 2 3-3" stroke="#2a52e8" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:500;color:var(--ink)">SSL Secured</div>
                    <div style="font-size:11px;color:var(--ink-soft)">All data encrypted</div>
                </div>
            </div>

            <div class="dashboard-preview">
                <div class="preview-header">
                    <span class="preview-title">Admin Dashboard</span>
                    <span class="preview-badge">&#9679; Live</span>
                </div>
                <div class="preview-stats">
                    <div class="preview-stat">
                        <div class="preview-stat-val"><?php echo $total_students > 0 ? number_format($total_students) : '&mdash;'; ?></div>
                        <div class="preview-stat-label">Total Students</div>
                    </div>
                    <div class="preview-stat">
                        <div class="preview-stat-val"><?php echo $active_students > 0 ? number_format($active_students) : '&mdash;'; ?></div>
                        <div class="preview-stat-label">Active</div>
                    </div>
                    <div class="preview-stat">
                        <div class="preview-stat-val"><?php echo $total_courses > 0 ? number_format($total_courses) : '&mdash;'; ?></div>
                        <div class="preview-stat-label">Courses</div>
                    </div>
                </div>
                <div class="preview-list">
                    <div class="preview-row">
                        <div class="preview-avatar" style="background:#eaedfc;color:#2a52e8">AR</div>
                        <div>
                            <div class="preview-row-name">Ana Reyes</div>
                            <div class="preview-row-course">BS Computer Science</div>
                        </div>
                        <span class="preview-chip chip-active">Active</span>
                    </div>
                    <div class="preview-row">
                        <div class="preview-avatar" style="background:#fef3c7;color:#92400e">MC</div>
                        <div>
                            <div class="preview-row-name">Marco Cruz</div>
                            <div class="preview-row-course">BS Information Tech</div>
                        </div>
                        <span class="preview-chip chip-pending">Pending</span>
                    </div>
                    <div class="preview-row">
                        <div class="preview-avatar" style="background:#dcfce7;color:#166534">JS</div>
                        <div>
                            <div class="preview-row-name">Jana Santos</div>
                            <div class="preview-row-course">BS Education</div>
                        </div>
                        <span class="preview-chip chip-active">Active</span>
                    </div>
                </div>
            </div>

            <div class="float-card float-card-2">
                <div style="font-size:11px;color:var(--ink-soft);margin-bottom:6px">Session expires in</div>
                <div style="font-size:22px;font-weight:500;color:var(--ink);letter-spacing:-0.5px" id="countdown">29:59</div>
            </div>
        </div>
    </div>
</section>

<!-- TRUST STRIP -->
<div class="trust-strip">
    <div class="trust-inner">
        <span class="trust-label">Built-in protection</span>
        <div class="trust-items">
            <span class="trust-item"><span class="trust-dot"></span>bcrypt passwords</span>
            <span class="trust-item"><span class="trust-dot"></span>CSRF tokens</span>
            <span class="trust-item"><span class="trust-dot"></span>SQL injection safe</span>
            <span class="trust-item"><span class="trust-dot"></span>XSS sanitized</span>
            <span class="trust-item"><span class="trust-dot"></span>Brute-force limited</span>
            <span class="trust-item"><span class="trust-dot"></span>Auto session timeout</span>
        </div>
    </div>
</div>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="features-inner">
        <p class="section-eyebrow reveal">Platform capabilities</p>
        <h2 class="section-heading reveal">Everything you need<br>to run your institution.</h2>
        <p class="section-sub reveal">From role-based dashboards to GPA tracking &mdash; a complete toolkit for academic administration.</p>
        <div class="features-grid reveal">
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <rect x="3" y="3" width="6" height="6" rx="1.5" stroke="#2a52e8" stroke-width="1.3"/>
                        <rect x="11" y="3" width="6" height="6" rx="1.5" stroke="#2a52e8" stroke-width="1.3"/>
                        <rect x="3" y="11" width="6" height="6" rx="1.5" stroke="#2a52e8" stroke-width="1.3"/>
                        <rect x="11" y="11" width="6" height="6" rx="1.5" stroke="#2a52e8" stroke-width="1.3"/>
                    </svg>
                </div>
                <p class="feature-name">Admin Dashboard</p>
                <p class="feature-desc">Full overview of all students, enrollments, and system activity from a single, unified interface.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="7" r="3.5" stroke="#2a52e8" stroke-width="1.3"/>
                        <path d="M3.5 17c0-3.314 2.91-6 6.5-6s6.5 2.686 6.5 6" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="feature-name">Student Portal</p>
                <p class="feature-desc">Students access their own records, grades, and academic history securely without admin involvement.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 2.5L3 6v5c0 4 2.5 6.5 7 7 4.5-.5 7-3 7-7V6L10 2.5Z" stroke="#2a52e8" stroke-width="1.3"/>
                        <path d="M7 10l2 2 4-4" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="feature-name">Role-Based Access</p>
                <p class="feature-desc">Separate permission layers for admins and students. Each role sees only what they're authorized to view.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <polyline points="3,14 7,9 11,12 17,5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="feature-name">GPA &amp; Analytics</p>
                <p class="feature-desc">Track academic performance trends over time, with per-student and institution-wide metrics.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <rect x="3" y="4" width="14" height="12" rx="2" stroke="#2a52e8" stroke-width="1.3"/>
                        <path d="M7 4V3m6 1V3" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round"/>
                        <path d="M3 8h14" stroke="#2a52e8" stroke-width="1.3"/>
                    </svg>
                </div>
                <p class="feature-name">Enrollment Management</p>
                <p class="feature-desc">Add, update, or archive student records with full course assignment and enrollment status tracking.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <rect x="3" y="3" width="14" height="14" rx="2" stroke="#2a52e8" stroke-width="1.3"/>
                        <path d="M7 10h6M10 7v6" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="feature-name">Responsive Interface</p>
                <p class="feature-desc">Works cleanly across desktop, tablet, and mobile. No app installation required &mdash; browser-native.</p>
            </div>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats">
    <div class="stats-pattern"></div>
    <div class="stats-inner">
        <div>
            <h2 class="stats-heading">Built for <em>real</em> institutions.</h2>
        </div>
        <div class="stats-grid">
            <div class="stat-cell">
                <div class="stat-num"><?php echo $total_students > 0 ? number_format($total_students) : '&mdash;'; ?></div>
                <div class="stat-lbl">Students enrolled</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num"><?php echo $active_students > 0 ? number_format($active_students) : '&mdash;'; ?></div>
                <div class="stat-lbl">Currently active</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num"><?php echo $total_courses > 0 ? number_format($total_courses) : '&mdash;'; ?></div>
                <div class="stat-lbl">Course programs</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num">24<span class="stat-unit">/7</span></div>
                <div class="stat-lbl">Uptime availability</div>
            </div>
        </div>
    </div>
</section>

<!-- SECURITY -->
<section class="security" id="security">
    <div class="security-inner">
        <div>
            <p class="section-eyebrow reveal">Security architecture</p>
            <h2 class="section-heading reveal">Protection built into every layer.</h2>
            <p class="section-sub reveal" style="margin-bottom:0">Industry-standard security measures so you can focus on education, not vulnerabilities.</p>
        </div>
        <div class="reveal">
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">CAPTCHA verification</p>
                    <p class="check-desc">Prevents automated login bots and credential stuffing attacks on the sign-in form.</p>
                </div>
            </div>
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">bcrypt password hashing</p>
                    <p class="check-desc">Passwords are never stored in plain text &mdash; salted bcrypt ensures secure storage at rest.</p>
                </div>
            </div>
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">CSRF token protection</p>
                    <p class="check-desc">Every form submission includes a unique token to prevent cross-site request forgery.</p>
                </div>
            </div>
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">SQL injection prevention</p>
                    <p class="check-desc">All database interactions use PDO prepared statements &mdash; no raw query interpolation.</p>
                </div>
            </div>
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">Auto session timeout</p>
                    <p class="check-desc">Idle sessions are automatically invalidated after inactivity, reducing unauthorized access risk.</p>
                </div>
            </div>
            <div class="check-item">
                <div class="check-icon">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 6l3 3 5-5" stroke="#2a52e8" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="check-title">XSS input sanitization</p>
                    <p class="check-desc">All user inputs are sanitized and escaped before rendering, blocking cross-site scripting.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="cta-circles"></div>
    <div class="cta-inner">
        <h2 class="cta-heading">Ready to get started?</h2>
        <p class="cta-sub">Log in to access your dashboard and manage your institution&rsquo;s records.</p>
        <a href="login.php" class="btn-white">
            Go to portal
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="#2a52e8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-left">
            <div class="footer-mark">
                <svg width="14" height="14" viewBox="0 0 18 18" fill="none">
                    <path d="M3 4.5C3 3.67 3.67 3 4.5 3H13.5C14.33 3 15 3.67 15 4.5V5.5C15 6.33 14.33 7 13.5 7H4.5C3.67 7 3 6.33 3 5.5V4.5Z" fill="white" fill-opacity="0.6"/>
                    <path d="M3 9.5C3 8.67 3.67 8 4.5 8H9.5C10.33 8 11 8.67 11 9.5C11 10.33 10.33 11 9.5 11H4.5C3.67 11 3 10.33 3 9.5Z" fill="white" fill-opacity="0.4"/>
                    <path d="M3 13.5C3 12.67 3.67 12 4.5 12H7.5C8.33 12 9 12.67 9 13.5C9 14.33 8.33 15 7.5 15H4.5C3.67 15 3 14.33 3 13.5Z" fill="white" fill-opacity="0.2"/>
                </svg>
            </div>
            <span class="footer-name">Student Management System &middot; Version 1.0</span>
        </div>
        <span class="footer-right">&copy; <?php echo date('Y'); ?> All rights reserved.</span>
    </div>
</footer>

<script>
    // Toast auto-dismiss
    setTimeout(function() {
        var t = document.getElementById('toast');
        if (t) { t.style.opacity = '0'; t.style.transition = 'opacity 0.5s'; setTimeout(function(){ if(t.parentElement) t.remove(); }, 500); }
    }, 5000);

    // Session countdown
    var secs = 29 * 60 + 59;
    var cd = document.getElementById('countdown');
    if (cd) {
        setInterval(function() {
            secs = Math.max(0, secs - 1);
            var m = Math.floor(secs / 60);
            var s = secs % 60;
            cd.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        }, 1000);
    }

    // Scroll reveal
    var ro = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
            if (e.isIntersecting) { e.target.classList.add('in'); ro.unobserve(e.target); }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal').forEach(function(el) { ro.observe(el); });

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var t = document.querySelector(a.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
</script>
</body>
</html>