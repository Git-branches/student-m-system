<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/security.php';

checkAuth();
checkRole('admin');

$database = new Database();
$db       = $database->getConnection();
$security = new Security($db);

$flash = null;

// ── ADD STUDENT ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if ($security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $first_name = $security->sanitizeInput($_POST['first_name']);
        $last_name  = $security->sanitizeInput($_POST['last_name']);
        $email      = $security->sanitizeInput($_POST['email']);
        $phone      = $security->sanitizeInput($_POST['phone'] ?? '');
        $course     = $security->sanitizeInput($_POST['course']);
        $year_level = (int)($_POST['year_level'] ?? 1);
        $gpa        = (float)($_POST['gpa'] ?? 0);
        $status     = $security->sanitizeInput($_POST['status']);
        $plain_pw   = !empty(trim($_POST['password'] ?? '')) ? trim($_POST['password']) : 'student123';

        require_once '../includes/functions.php';
        $student_id = generateStudentID($db);

        // Auto-generate username
        $base_username = strtolower(preg_replace('/[^a-z0-9]/i', '', $first_name) . '.' . preg_replace('/[^a-z0-9]/i', '', $last_name));
        $username      = $base_username;
        $suffix        = 1;
        while (true) {
            $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
            $chk->execute([':u' => $username]);
            if ((int)$chk->fetchColumn() === 0) break;
            $username = $base_username . $suffix++;
        }

        $hashed = password_hash($plain_pw, PASSWORD_BCRYPT);

        $db->beginTransaction();
        try {
            $uStmt = $db->prepare(
                "INSERT INTO users (username, password, email, role, full_name, created_at)
                 VALUES (:u, :p, :email, 'student', :fn, NOW())"
            );
            $uStmt->execute([':u' => $username, ':p' => $hashed,
                             ':email' => $email, ':fn' => $first_name . ' ' . $last_name]);
            $new_user_id = $db->lastInsertId();

            $sStmt = $db->prepare(
                "INSERT INTO students (student_id, user_id, first_name, last_name, email, phone, course, year_level, gpa, status, created_at)
                 VALUES (:sid, :uid, :fn, :ln, :email, :phone, :course, :yl, :gpa, :status, NOW())"
            );
            $sStmt->execute([
                ':sid' => $student_id, ':uid' => $new_user_id,
                ':fn'  => $first_name,  ':ln'  => $last_name,
                ':email' => $email,     ':phone' => $phone,
                ':course' => $course,   ':yl'  => $year_level,
                ':gpa'   => $gpa,       ':status' => $status,
            ]);

            $db->commit();
            $flash = [
                'type' => 'success',
                'msg'  => 'Student <strong>' . htmlspecialchars($first_name . ' ' . $last_name) . '</strong> added successfully.',
                'creds'=> ['username' => $username, 'password' => $plain_pw],
            ];
        } catch (Exception $e) {
            $db->rollBack();
            $flash = ['type' => 'error', 'msg' => 'Failed to add student. The email may already be registered.'];
        }
    }
}

// ── EDIT STUDENT ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if ($security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id         = (int)($_POST['student_db_id'] ?? 0);
        $first_name = $security->sanitizeInput($_POST['first_name']);
        $last_name  = $security->sanitizeInput($_POST['last_name']);
        $email      = $security->sanitizeInput($_POST['email']);
        $phone      = $security->sanitizeInput($_POST['phone'] ?? '');
        $course     = $security->sanitizeInput($_POST['course']);
        $year_level = (int)($_POST['year_level'] ?? 1);
        $gpa        = (float)($_POST['gpa'] ?? 0);
        $status     = $security->sanitizeInput($_POST['status']);

        $stmt = $db->prepare(
            "UPDATE students SET first_name=:fn, last_name=:ln, email=:email, phone=:phone,
             course=:course, year_level=:yl, gpa=:gpa, status=:status
             WHERE id=:id"
        );
        $stmt->execute([
            ':fn' => $first_name, ':ln' => $last_name, ':email' => $email, ':phone' => $phone,
            ':course' => $course, ':yl' => $year_level, ':gpa' => $gpa,
            ':status' => $status, ':id' => $id,
        ]);

        // Also sync full_name in users table
        $db->prepare("UPDATE users u JOIN students s ON s.user_id = u.id
                      SET u.full_name = :fn, u.email = :email WHERE s.id = :id")
           ->execute([':fn' => $first_name . ' ' . $last_name, ':email' => $email, ':id' => $id]);

        $flash = ['type' => 'success', 'msg' => 'Student record updated successfully.'];
    }
}

// ── DELETE STUDENT ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['student_db_id'] ?? 0);
        $row = $db->prepare("SELECT user_id FROM students WHERE id = :id");
        $row->execute([':id' => $id]);
        $linked = $row->fetch(PDO::FETCH_ASSOC);

        $db->prepare("DELETE FROM students WHERE id = :id")->execute([':id' => $id]);
        if (!empty($linked['user_id'])) {
            $db->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $linked['user_id']]);
        }
        $flash = ['type' => 'success', 'msg' => 'Student record and login account deleted successfully.'];
    }
}

// ── RESET PASSWORD ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    if ($security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id     = (int)($_POST['student_db_id'] ?? 0);
        $new_pw = !empty(trim($_POST['new_password'] ?? '')) ? trim($_POST['new_password']) : 'student123';
        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $row    = $db->prepare("SELECT user_id FROM students WHERE id = :id");
        $row->execute([':id' => $id]);
        $linked = $row->fetch(PDO::FETCH_ASSOC);
        if (!empty($linked['user_id'])) {
            $db->prepare("UPDATE users SET password = :p WHERE id = :uid")
               ->execute([':p' => $hashed, ':uid' => $linked['user_id']]);
            $flash = ['type'=>'success','msg'=>'Password reset successfully.','creds'=>['username'=>'','password'=>$new_pw,'reset'=>true]];
        } else {
            $flash = ['type'=>'error','msg'=>'No login account linked to this student.'];
        }
    }
}

// ── FETCH ALL ────────────────────────────────────────────────
$students = $db->query(
    "SELECT s.*, u.username
     FROM students s
     LEFT JOIN users u ON u.id = s.user_id
     ORDER BY s.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($students);
$csrf  = $security->generateCSRFToken();

$courses = [
    'BSIT'   => 'BSIT — BS Information Technology',
    'BSBA'   => 'BSBA — BS Business Administration',
    'BSCRIM' => 'BSCRIM — BS Criminology',
    'BSCE'   => 'BSCE — BS Civil Engineering',
    'BSSW'   => 'BSSW — BS Social Work',
    'BSFi'   => 'BSFi — BS Fisheries',
    'BSA'    => 'BSA — BS Accountancy',
];

// Initials for avatar
$name_parts = explode(' ', trim($_SESSION['full_name']));
$initials   = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students — Student Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <style>
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
            --teal:         #0f766e;
            --teal-light:   #ccfbf1;
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

        /* ════════════ NAVBAR ════════════ */
        .nav {
            position: sticky; top: 0; z-index: 80;
            height: 60px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 32px; gap: 8px;
        }

        .nav-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; flex-shrink: 0;
        }
        .nav-logo-mark {
            width: 30px; height: 30px; background: var(--accent);
            border-radius: 7px; display: flex; align-items: center; justify-content: center;
        }
        .nav-logo-text { font-size: 14px; font-weight: 500; color: var(--ink); letter-spacing: -0.2px; }

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

        /* Session timer pill */
        .nav-session {
            display: flex; align-items: center; gap: 7px;
            padding: 5px 12px; border-radius: 100px;
            background: var(--surface-2);
            border: 1px solid var(--border-str);
            font-size: 12px; color: var(--ink-soft);
            transition: background 0.2s, border-color 0.2s, color 0.2s;
            white-space: nowrap;
        }
        .nav-session.warn   { background: #fff7ed; border-color: rgba(234,88,12,0.25); color: #c2410c; }
        .nav-session.urgent { background: #fef2f2; border-color: rgba(220,38,38,0.25); color: var(--red); }

        .nav-session-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--green); flex-shrink: 0;
            animation: dotPulse 2.5s ease-in-out infinite;
        }
        .nav-session.warn   .nav-session-dot { background: #ea580c; }
        .nav-session.urgent .nav-session-dot { background: var(--red); animation: dotBlink .6s ease-in-out infinite; }

        @keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        @keyframes dotBlink { 0%,100%{opacity:1} 50%{opacity:0} }

        .nav-right { display: flex; align-items: center; gap: 10px; margin-left: 12px; }

        .nav-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 500; color: var(--accent); flex-shrink: 0;
        }
        .nav-name { font-size: 13px; font-weight: 500; color: var(--ink-mid); }

        .nav-logout {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 500; color: var(--ink-soft);
            text-decoration: none; border: 1px solid var(--border-str);
            transition: background 0.15s, color 0.15s;
        }
        .nav-logout:hover { background: var(--red-light); color: var(--red); border-color: rgba(220,38,38,0.2); }

        /* ════════════ PAGE ════════════ */
        .page { max-width: 1280px; margin: 0 auto; padding: 32px 24px 64px; }

        /* ════════════ HERO ════════════ */
        .hero {
            background: var(--ink);
            border-radius: var(--r-xl);
            padding: 32px 36px;
            margin-bottom: 28px;
            display: flex; align-items: center;
            justify-content: space-between; gap: 24px;
            position: relative; overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }
        .hero-grid-bg {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 36px 36px; pointer-events: none;
        }
        .hero-orb {
            position: absolute; width: 320px; height: 320px; border-radius: 50%;
            background: radial-gradient(circle, rgba(42,82,232,0.22) 0%, transparent 70%);
            top: -100px; left: -80px; pointer-events: none;
        }
        .hero-left { position: relative; z-index: 1; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 100px;
            background: rgba(42,82,232,0.2); border: 1px solid rgba(42,82,232,0.3);
            font-size: 11px; font-weight: 500; color: #93abff;
            letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 14px;
        }
        .hero-eyebrow-dot {
            width: 5px; height: 5px; border-radius: 50%; background: #93abff;
            animation: dotPulse 2.5s ease-in-out infinite;
        }
        .hero-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(24px, 3vw, 36px); color: #fff;
            line-height: 1.1; letter-spacing: -0.6px; margin-bottom: 8px;
        }
        .hero-title em { font-style: italic; color: #93abff; }
        .hero-sub { font-size: 13px; font-weight: 300; color: rgba(255,255,255,0.45); }
        .hero-right { position: relative; z-index: 1; flex-shrink: 0; }
        .hero-date-card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--r-md); padding: 14px 20px; text-align: right;
        }
        .hero-date-label {
            font-size: 10px; font-weight: 500; letter-spacing: 0.8px;
            text-transform: uppercase; color: rgba(255,255,255,0.35); margin-bottom: 4px;
        }
        .hero-date-value { font-size: 14px; font-weight: 400; color: rgba(255,255,255,0.85); }

        /* ════════════ SECTION LABEL ════════════ */
        .section-label {
            font-size: 11px; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--ink-ghost); margin-bottom: 14px; margin-top: 0;
        }

        /* FLASH MESSAGE */
        .flash {
            display: flex; align-items: flex-start; gap: 12px;
            background: var(--green-light); border-left: 3px solid var(--green);
            border-radius: var(--r-md); padding: 14px 18px;
            margin-bottom: 24px; font-size: 13px;
            animation: fadeUp 0.4s ease both;
            position: relative;
        }
        .flash-error {
            background: var(--red-light); border-left-color: var(--red);
            color: #991b1b;
        }
        .flash-body { flex: 1; }
        .flash-body p { line-height: 1.5; }
        .flash-creds {
            margin-top: 8px; padding: 8px 12px;
            background: rgba(0,0,0,0.05); border-radius: var(--r-sm);
            display: flex; gap: 16px; flex-wrap: wrap;
            font-size: 12px;
        }
        .flash-creds code {
            font-family: monospace; font-size: 12px; font-weight: 600;
            background: rgba(0,0,0,0.08); padding: 2px 6px; border-radius: 4px;
        }
        .flash-progress {
            position: absolute; bottom: 0; left: 0; height: 3px;
            background: currentColor; opacity: 0.3;
            animation: flashTimer 8s linear forwards;
            border-radius: 0 0 0 var(--r-md);
        }
        @keyframes flashTimer { from { width: 100%; } to { width: 0%; } }

        /* TOOLBAR */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; gap: 16px;
            animation: fadeUp 0.5s 0.05s ease both;
        }
        .toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .record-count { font-size: 12px; font-weight: 300; color: var(--ink-ghost); }
        .search-wrap { position: relative; }
        .search-wrap svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--ink-ghost); }
        .search-input {
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--border-str); border-radius: var(--r-md);
            width: 260px; outline: none; transition: all 0.2s;
            background: var(--surface);
        }
        .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,82,232,0.1); }
        .btn-add {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--accent); color: white;
            border: none; padding: 8px 18px; border-radius: var(--r-md);
            font-size: 13px; font-weight: 500; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add:hover { background: var(--accent-deep); transform: translateY(-1px); box-shadow: var(--shadow-sm); }

        /* TABLE */
        .table-card {
            background: var(--surface); border: 1px solid var(--border-str);
            border-radius: var(--r-lg); overflow: hidden;
            box-shadow: var(--shadow-sm);
            animation: fadeUp 0.5s 0.1s ease both;
        }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead { background: var(--surface-2); border-bottom: 1px solid var(--border); }
        .data-table thead th {
            padding: 14px 16px; text-align: left;
            font-size: 11px; font-weight: 500; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--ink-soft);
        }
        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        .data-table tbody tr:hover { background: var(--surface-2); }
        .data-table td { padding: 14px 16px; vertical-align: middle; }

        .cell-id { font-family: monospace; font-size: 12px; color: var(--ink-soft); }
        .cell-name { font-weight: 500; }
        .cell-login { font-family: monospace; font-size: 12px; color: var(--ink-soft); }

        .gpa-pill {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 500;
        }
        .gpa-excellent { background: var(--green-light); color: #166534; }
        .gpa-good { background: var(--accent-light); color: var(--accent); }
        .gpa-average { background: var(--amber-light); color: #92400e; }
        .gpa-low { background: var(--red-light); color: #991b1b; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .status-active::before { background: var(--green); }
        .status-active { background: var(--green-light); color: #166534; }
        .status-inactive::before { background: var(--ink-ghost); }
        .status-inactive { background: var(--surface-3); color: var(--ink-mid); }
        .status-graduated::before { background: var(--accent); }
        .status-graduated { background: var(--accent-light); color: var(--accent); }
        .status-pending::before { background: var(--amber); }
        .status-pending { background: var(--amber-light); color: #92400e; }
        .status-suspended::before { background: var(--red); }
        .status-suspended { background: var(--red-light); color: #991b1b; }

        .actions { display: flex; gap: 8px; }
        .btn-icon {
            padding: 6px 12px; border-radius: var(--r-sm);
            font-size: 11px; font-weight: 500;
            border: none; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-view { background: var(--accent-light); color: var(--accent); }
        .btn-view:hover { background: var(--accent); color: white; transform: translateY(-1px); }
        .btn-edit { background: var(--surface-3); color: var(--ink-mid); }
        .btn-edit:hover { background: var(--ink-mid); color: white; transform: translateY(-1px); }
        .btn-delete { background: var(--red-light); color: var(--red); }
        .btn-delete:hover { background: var(--red); color: white; transform: translateY(-1px); }

        .empty-state {
            text-align: center; padding: 60px 32px;
            color: var(--ink-soft);
        }
        .empty-state svg { opacity: 0.3; margin-bottom: 16px; }
        .empty-state p { font-size: 14px; margin-bottom: 8px; }

        .table-footer {
            padding: 12px 20px; background: var(--surface-2);
            border-top: 1px solid var(--border);
            font-size: 12px; color: var(--ink-ghost);
            display: flex; justify-content: space-between;
        }

        /* MODALS */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,17,23,0.6);
            backdrop-filter: blur(5px); z-index: 900;
            display: flex; align-items: center; justify-content: center;
            padding: 20px; opacity: 0; pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .modal-backdrop.open { opacity: 1; pointer-events: all; }
        .modal {
            background: var(--surface); border-radius: var(--r-xl);
            width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;
            box-shadow: var(--shadow-md); transform: translateY(20px) scale(0.96);
            transition: transform 0.28s cubic-bezier(0.34, 1.35, 0.64, 1);
        }
        .modal-backdrop.open .modal { transform: translateY(0) scale(1); }
        .modal-sm { max-width: 420px; }

        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; background: var(--surface);
        }
        .modal-header-left { display: flex; align-items: center; gap: 12px; }
        .modal-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-icon.green { background: var(--green-light); }
        .modal-icon.blue { background: var(--accent-light); }
        .modal-icon.red { background: var(--red-light); }
        .modal-icon.amber { background: var(--amber-light); }
        .modal-title { font-size: 18px; font-weight: 600; color: var(--ink); }
        .modal-subtitle { font-size: 12px; font-weight: 300; color: var(--ink-soft); margin-top: 2px; }
        .modal-close {
            background: none; border: none; cursor: pointer;
            padding: 6px; border-radius: 6px; color: var(--ink-soft);
            transition: all 0.2s;
        }
        .modal-close:hover { background: var(--surface-2); color: var(--ink); }
        .modal-body { padding: 20px 24px; }
        .modal-footer {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 12px; padding: 16px 24px; border-top: 1px solid var(--border);
        }

        /* FORM */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .span-2 { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label {
            font-size: 11px; font-weight: 500; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--ink-mid);
        }
        .form-label .hint { font-weight: 300; text-transform: none; color: var(--ink-ghost); }
        .form-input, .form-select {
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            padding: 10px 12px; border: 1px solid var(--border-str);
            border-radius: var(--r-md); outline: none;
            transition: all 0.2s;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,82,232,0.1);
        }
        .form-select { cursor: pointer; background: var(--surface); }
        .form-divider {
            border-top: 1px dashed var(--border); padding-top: 16px; margin-top: 8px;
        }

        .btn-cancel {
            padding: 8px 20px; border-radius: var(--r-md);
            font-size: 13px; font-weight: 500; background: var(--surface-2);
            border: 1px solid var(--border-str); cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover { background: var(--surface-3); }
        .btn-submit {
            padding: 8px 24px; border-radius: var(--r-md);
            font-size: 13px; font-weight: 500; background: var(--accent);
            color: white; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s;
        }
        .btn-submit:hover { background: var(--accent-deep); transform: translateY(-1px); }
        .btn-submit.danger { background: var(--red); }
        .btn-submit.danger:hover { background: #991b1b; }

        /* View modal styles */
        .view-section { margin-bottom: 20px; }
        .view-section-title {
            font-size: 10px; font-weight: 500; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--ink-ghost);
            margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border);
        }
        .view-row {
            display: flex; justify-content: space-between;
            padding: 8px 0; border-bottom: 1px solid var(--border);
        }
        .view-row:last-child { border-bottom: none; }
        .view-label { font-size: 12px; color: var(--ink-soft); }
        .view-value { font-size: 13px; font-weight: 500; color: var(--ink); }
        .creds-box {
            background: var(--ink); border-radius: var(--r-md); padding: 12px 16px;
        }
        .creds-box-title {
            font-size: 10px; font-weight: 500; letter-spacing: 0.1em;
            text-transform: uppercase; color: rgba(255,255,255,0.4);
            margin-bottom: 8px;
        }
        .creds-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 6px;
        }
        .creds-label { font-size: 11px; color: rgba(255,255,255,0.5); }
        .creds-value {
            font-family: monospace; font-size: 12px; font-weight: 600;
            color: var(--accent-light); background: rgba(255,255,255,0.1);
            padding: 2px 8px; border-radius: 4px;
        }
        .no-account-note { font-size: 11px; color: var(--ink-soft); font-style: italic; margin-top: 8px; }

        /* Delete modal */
        .delete-body { text-align: center; padding: 24px; }
        .del-icon {
            width: 60px; height: 60px; border-radius: 50%;
            background: var(--red-light); display: flex;
            align-items: center; justify-content: center; margin: 0 auto 16px;
        }
        .delete-body p { font-size: 13px; color: var(--ink-mid); line-height: 1.6; }
        .delete-body strong { color: var(--ink); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .nav { padding: 0 16px; }
            .nav-name { display: none; }
            .nav-session span { display: none; }
            .page { padding: 20px 16px 48px; }
            .hero { padding: 24px 20px; flex-direction: column; align-items: flex-start; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-input { width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 10px 12px; }
            .actions { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
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
        <a href="students.php" class="nav-link active">Students</a>
    </div>

    <div class="nav-spacer"></div>

    <!-- Live session pill -->
    <div class="nav-session" id="navSession">
        <span class="nav-session-dot" id="navDot"></span>
        <span id="navSessionTxt">Session active</span>
    </div>

    <div class="nav-right">
        <div class="nav-avatar"><?php echo $initials; ?></div>
        <span class="nav-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="../logout.php" class="nav-logout">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none">
                <path d="M5 2H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                <path d="M9.5 9.5L12 7l-2.5-2.5M12 7H5.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Logout
        </a>
    </div>
</nav>

<!-- ════════════ PAGE ════════════ -->
<div class="page">

    <!-- Hero -->
    <div class="hero">
        <div class="hero-grid-bg"></div>
        <div class="hero-orb"></div>
        <div class="hero-left">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot"></span>
                Student Management
            </div>
            <h1 class="hero-title">
                Manage<br>
                <em>Student Records</em>
            </h1>
            <p class="hero-sub">View, add, edit, and manage all student information</p>
        </div>
        <div class="hero-right">
            <div class="hero-date-card">
                <p class="hero-date-label">Today</p>
                <p class="hero-date-value"><?php echo $current_date; ?></p>
            </div>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flash): ?>
    <div class="flash <?php echo $flash['type'] === 'error' ? 'flash-error' : ''; ?>" id="flashMsg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <?php if ($flash['type'] === 'error'): ?>
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            <?php else: ?>
                <polyline points="20 6 9 17 4 12"/>
            <?php endif; ?>
        </svg>
        <div class="flash-body">
            <p><?php echo $flash['msg']; ?></p>
            <?php if (!empty($flash['creds'])): ?>
            <div class="flash-creds">
                <?php if (empty($flash['creds']['reset'])): ?>
                <span>Username: <code><?php echo htmlspecialchars($flash['creds']['username']); ?></code></span>
                <?php endif; ?>
                <span>Password: <code><?php echo htmlspecialchars($flash['creds']['password']); ?></code></span>
            </div>
            <?php endif; ?>
        </div>
        <div class="flash-progress"></div>
    </div>
    <?php endif; ?>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Search students..." oninput="filterTable(this.value)">
            </div>
            <span class="record-count" id="recordCount"><?php echo $total; ?> records</span>
        </div>
        <button class="btn-add" onclick="openAddModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add New Student
        </button>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Login</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>GPA</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if (empty($students)): ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <p>No students found</p>
                        <span>Click "Add New Student" to get started</span>
                    </div>
                </td></tr>
            <?php else: ?>
            <?php foreach ($students as $s):
                $gpa = (float)$s['gpa'];
                $gpa_cls = $gpa >= 3.5 ? 'gpa-excellent' : ($gpa >= 3.0 ? 'gpa-good' : ($gpa >= 2.0 ? 'gpa-average' : 'gpa-low'));
                $s_json = htmlspecialchars(json_encode($s), ENT_QUOTES);
            ?>
            <tr class="student-row">
                <td class="cell-id"><?php echo htmlspecialchars($s['student_id']); ?></td>
                <td class="cell-name"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                <td class="cell-login"><?php echo $s['username'] ? htmlspecialchars($s['username']) : '<span style="color:var(--ink-ghost);">—</span>'; ?></td>
                <td><?php echo htmlspecialchars($s['course'] ?? '—'); ?></td>
                <td style="text-align:center;"><?php echo (int)$s['year_level']; ?></td>
                <td><span class="gpa-pill <?php echo $gpa_cls; ?>"><?php echo number_format($gpa, 2); ?></span></td>
                <td>
                    <span class="status-badge status-<?php echo htmlspecialchars($s['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($s['status'])); ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <button class="btn-icon btn-view" onclick='openViewModal(<?php echo $s_json; ?>)'>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </button>
                        <button class="btn-icon btn-edit" onclick='openEditModal(<?php echo $s_json; ?>)'>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit
                        </button>
                        <button class="btn-icon btn-delete" onclick='openDeleteModal(<?php echo $s_json; ?>)'>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="table-footer">
            <span>Student Management System</span>
            <span id="footerCount">Showing <?php echo $total; ?> of <?php echo $total; ?> records</span>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════ VIEW MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="viewBackdrop" onclick="closeOnBackdrop(event,'viewBackdrop')">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon amber">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <div>
                    <div class="modal-title" id="viewName">Student Details</div>
                    <div class="modal-subtitle" id="viewStudentId">—</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('viewBackdrop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="view-section">
                <div class="view-section-title">Personal Information</div>
                <div class="view-row"><span class="view-label">Full Name</span><span class="view-value" id="vFullName">—</span></div>
                <div class="view-row"><span class="view-label">Email</span><span class="view-value" id="vEmail">—</span></div>
                <div class="view-row"><span class="view-label">Phone</span><span class="view-value" id="vPhone">—</span></div>
            </div>
            <div class="view-section">
                <div class="view-section-title">Academic Information</div>
                <div class="view-row"><span class="view-label">Student ID</span><span class="view-value" id="vStudentId">—</span></div>
                <div class="view-row"><span class="view-label">Course</span><span class="view-value" id="vCourse">—</span></div>
                <div class="view-row"><span class="view-label">Year Level</span><span class="view-value" id="vYear">—</span></div>
                <div class="view-row"><span class="view-label">GPA</span><span class="view-value" id="vGpa">—</span></div>
                <div class="view-row"><span class="view-label">Status</span><span class="view-value" id="vStatus">—</span></div>
                <div class="view-row"><span class="view-label">Enrolled</span><span class="view-value" id="vEnrolled">—</span></div>
            </div>
            <div class="view-section">
                <div class="view-section-title">Login Account</div>
                <div id="vCredsBox" class="creds-box">
                    <div class="creds-box-title">Student Portal Credentials</div>
                    <div class="creds-row">
                        <span class="creds-label">Username</span>
                        <span class="creds-value" id="vUsername">—</span>
                    </div>
                    <div class="creds-row">
                        <span class="creds-label">Password</span>
                        <span class="creds-value">••••••••</span>
                    </div>
                </div>
                <p class="no-account-note" id="vNoAccount" style="display:none">No login account linked to this student.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('viewBackdrop')">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════ ADD MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="addBackdrop" onclick="closeOnBackdrop(event,'addBackdrop')">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon green">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#2D6A4F" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div>
                    <div class="modal-title">Add New Student</div>
                    <div class="modal-subtitle">A login account will be created automatically</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('addBackdrop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Course / Program</label>
                        <select name="course" class="form-select" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $code => $label): ?>
                            <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-select" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                            <option value="5">5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">GPA (0.00 – 4.00)</label>
                        <input type="number" name="gpa" class="form-input" step="0.01" min="0" max="4.0" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Enrollment Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="graduated">Graduated</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group span-2 form-divider">
                        <label class="form-label">Login Password <span class="hint">(leave blank for default: student123)</span></label>
                        <input type="text" name="password" class="form-input" placeholder="Optional — custom password">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('addBackdrop')">Cancel</button>
                <button type="submit" class="btn-submit">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════ EDIT MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="editBackdrop" onclick="closeOnBackdrop(event,'editBackdrop')">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon blue">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1D4E89" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <div class="modal-title">Edit Student</div>
                    <div class="modal-subtitle" id="editSubtitle">Loading…</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('editBackdrop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="student_db_id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-input" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-input" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-input">
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Course / Program</label>
                        <select name="course" id="edit_course" class="form-select" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $code => $label): ?>
                            <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" id="edit_year_level" class="form-select" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                            <option value="5">5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">GPA (0.00 – 4.00)</label>
                        <input type="number" name="gpa" id="edit_gpa" class="form-input" step="0.01" min="0" max="4.0" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Enrollment Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="graduated">Graduated</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <button type="button" class="btn-cancel" style="background:var(--amber-light);color:var(--amber);border-color:var(--amber-light);" onclick="openResetModal()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Reset Password
                </button>
                <div style="display:flex;gap:12px;">
                    <button type="button" class="btn-cancel" onclick="closeModal('editBackdrop')">Cancel</button>
                    <button type="submit" class="btn-submit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════ DELETE MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="deleteBackdrop" onclick="closeOnBackdrop(event,'deleteBackdrop')">
    <div class="modal modal-sm" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon red">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </div>
                <div>
                    <div class="modal-title">Delete Student</div>
                    <div class="modal-subtitle">Removes record and login account</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('deleteBackdrop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="student_db_id" id="delete_id">
            <div class="delete-body">
                <div class="del-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <p>You are about to permanently delete<br><strong id="delete_name"></strong>.<br><br>Their student record <em>and</em> login account will both be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('deleteBackdrop')">Cancel</button>
                <button type="submit" class="btn-submit danger">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════ RESET PASSWORD MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="resetBackdrop" onclick="closeOnBackdrop(event,'resetBackdrop')">
    <div class="modal modal-sm" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon amber">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <div class="modal-title">Reset Password</div>
                    <div class="modal-subtitle" id="resetSubtitle">Set a new login password</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('resetBackdrop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="student_db_id" id="reset_student_id">
            <div class="modal-body">
                <p style="font-size:13px;color:var(--ink-mid);margin-bottom:16px;">Passwords are stored encrypted. Set a new password here — the student will need to use it on next login.</p>
                <div class="form-group">
                    <label class="form-label">New Password <span class="hint">(leave blank for default: student123)</span></label>
                    <input type="text" name="new_password" id="reset_new_password" class="form-input" placeholder="e.g., NewPass2026">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('resetBackdrop')">Cancel</button>
                <button type="submit" class="btn-submit" style="background:var(--amber);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const yearLabels = ['', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
const courseMap  = <?php echo json_encode($courses); ?>;

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
function closeOnBackdrop(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') ['viewBackdrop','addBackdrop','editBackdrop','deleteBackdrop','resetBackdrop'].forEach(closeModal); });

// View Modal
function openViewModal(s) {
    document.getElementById('viewName').textContent = s.first_name + ' ' + s.last_name;
    document.getElementById('viewStudentId').textContent = s.student_id;
    document.getElementById('vFullName').textContent = s.first_name + ' ' + s.last_name;
    document.getElementById('vEmail').textContent = s.email || '—';
    document.getElementById('vPhone').textContent = s.phone || '—';
    document.getElementById('vStudentId').textContent = s.student_id;
    document.getElementById('vCourse').textContent = courseMap[s.course] || s.course || '—';
    document.getElementById('vYear').textContent = yearLabels[parseInt(s.year_level)] || s.year_level;
    document.getElementById('vGpa').textContent = parseFloat(s.gpa).toFixed(2) + ' / 4.00';
    document.getElementById('vStatus').textContent = s.status ? s.status.charAt(0).toUpperCase() + s.status.slice(1) : '—';
    document.getElementById('vEnrolled').textContent = s.created_at ? s.created_at.split(' ')[0] : '—';
    
    const credsBox = document.getElementById('vCredsBox');
    const noAccount = document.getElementById('vNoAccount');
    if (s.username) {
        document.getElementById('vUsername').textContent = s.username;
        credsBox.style.display = 'block';
        noAccount.style.display = 'none';
    } else {
        credsBox.style.display = 'none';
        noAccount.style.display = 'block';
    }
    openModal('viewBackdrop');
}

// Add Modal
function openAddModal() { openModal('addBackdrop'); }

// Edit Modal
function openEditModal(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_first_name').value = s.first_name;
    document.getElementById('edit_last_name').value = s.last_name;
    document.getElementById('edit_email').value = s.email;
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_course').value = s.course;
    document.getElementById('edit_gpa').value = s.gpa;
    document.getElementById('edit_year_level').value = s.year_level;
    document.getElementById('edit_status').value = s.status;
    document.getElementById('editSubtitle').textContent = s.first_name + ' ' + s.last_name + ' · ' + s.student_id;
    openModal('editBackdrop');
}

// Delete Modal
function openDeleteModal(s) {
    document.getElementById('delete_id').value = s.id;
    document.getElementById('delete_name').textContent = s.first_name + ' ' + s.last_name;
    openModal('deleteBackdrop');
}

// Reset Password Modal
function openResetModal() {
    const id = document.getElementById('edit_id').value;
    const name = document.getElementById('edit_first_name').value + ' ' + document.getElementById('edit_last_name').value;
    document.getElementById('reset_student_id').value = id;
    document.getElementById('reset_new_password').value = '';
    document.getElementById('resetSubtitle').textContent = name;
    openModal('resetBackdrop');
}

// Search filter
function filterTable(query) {
    const rows = document.querySelectorAll('#tableBody .student-row');
    const q = query.toLowerCase().trim();
    let visible = 0;
    rows.forEach(row => {
        const show = !q || row.textContent.toLowerCase().includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const total = rows.length;
    document.getElementById('recordCount').textContent = visible + ' record' + (visible !== 1 ? 's' : '');
    document.getElementById('footerCount').textContent = 'Showing ' + visible + ' of ' + total + ' records';
}

// Auto-dismiss flash
const flash = document.getElementById('flashMsg');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity 0.6s ease';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 650);
    }, 8000);
}

// Session timeout script (same as dashboard)
(function () {
    var TIMEOUT = 20;
    var WARN_BEFORE = 10;
    var LOGIN_URL = '../login.php';
    var PING_URL = '../includes/ping.php';
    var CIRCUM = 2 * Math.PI * 34;

    var lastActivity = Date.now();
    var warningShown = false;
    var redirected = false;

    function pingServer() {
        fetch(PING_URL, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .catch(err => console.warn('Ping failed', err));
    }

    function updateNavPill(remaining) {
        var pill = document.getElementById('navSession');
        var txt = document.getElementById('navSessionTxt');
        if (!pill || !txt) return;
        if (remaining <= 60) {
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            txt.textContent = m > 0 ? m + 'm ' + String(s).padStart(2,'0') + 's left' : s + 's left';
            pill.className = 'nav-session ' + (remaining <= 30 ? 'urgent' : 'warn');
        } else {
            txt.textContent = Math.floor(remaining / 60) + ' min remaining';
            pill.className = 'nav-session' + (remaining < 300 ? ' warn' : '');
        }
    }

    function doRedirect() { if (!redirected) { redirected = true; window.location.href = LOGIN_URL + '?timeout=1'; } }

    function buildModal() {
        if (document.getElementById('st-overlay')) return;
        var div = document.createElement('div');
        div.id = 'st-overlay';
        div.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,17,23,0.6);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;';
        div.innerHTML = '<div style="background:#fff;border-radius:22px;max-width:400px;width:100%;padding:36px 32px;text-align:center;"><div style="width:60px;height:60px;background:#fff7ed;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ea580c"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div><p style="font-size:22px;font-weight:500;margin-bottom:8px;">Session expiring soon</p><p style="font-size:13px;color:#7b8094;margin-bottom:24px;">Your session is about to expire due to inactivity.<br>Click Stay logged in to continue.</p><div style="display:flex;gap:10px;"><button id="st-stay" style="flex:1;background:#2a52e8;color:#fff;border:none;padding:12px;border-radius:8px;font-weight:500;">Stay logged in</button><button id="st-out" style="flex:1;background:transparent;border:1px solid #ddd;padding:12px;border-radius:8px;">Log out now</button></div></div>';
        document.body.appendChild(div);
        document.getElementById('st-stay').onclick = function() { pingServer(); lastActivity = Date.now(); hideModal(); };
        document.getElementById('st-out').onclick = function() { window.location.href = LOGIN_URL + '?logout=1'; };
    }

    function showModal() { warningShown = true; buildModal(); }
    function hideModal() { warningShown = false; var el = document.getElementById('st-overlay'); if (el) el.remove(); }

    ['mousemove','keydown','click','scroll','touchstart'].forEach(e => {
        document.addEventListener(e, () => { lastActivity = Date.now(); if (warningShown) hideModal(); }, { passive: true });
    });

    setInterval(() => {
        if (redirected) return;
        var idle = Math.floor((Date.now() - lastActivity) / 1000);
        var remaining = Math.max(0, TIMEOUT - idle);
        updateNavPill(remaining);
        if (remaining <= 0) doRedirect();
        else if (remaining <= WARN_BEFORE && !warningShown) showModal();
    }, 1000);
})();
</script>
</body>
</html>