<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/security.php';

$database = new Database();
$db       = $database->getConnection();
$security = new Security($db);

// ── Constants ────────────────────────────────────────────────────
define('MAX_ATTEMPTS',   5);     // lock after this many failures
define('LOCKOUT_SECS',   300);   // 5 minutes lockout (in seconds)
define('WARN_THRESHOLD', 2);     // start warning when X attempts remain

// ── Helpers ──────────────────────────────────────────────────────

/**
 * Count recent failed attempts for an IP within the lockout window.
 * Uses the login_attempts table that Security already writes to.
 */
function getRecentFailedAttempts(PDO $db, string $ip): int {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = :ip
           AND success    = 0
           AND attempt_time > DATE_SUB(NOW(), INTERVAL :secs SECOND)"
    );
    $stmt->bindValue(':ip',   $ip);
    $stmt->bindValue(':secs', LOCKOUT_SECS, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/**
 * Return how many seconds remain in the lockout, or 0 if not locked.
 */
function getLockoutSecondsRemaining(PDO $db, string $ip): int {
    $stmt = $db->prepare(
        "SELECT MAX(attempt_time) FROM login_attempts
         WHERE ip_address = :ip
           AND success    = 0
           AND attempt_time > DATE_SUB(NOW(), INTERVAL :secs SECOND)"
    );
    $stmt->bindValue(':ip',   $ip);
    $stmt->bindValue(':secs', LOCKOUT_SECS, PDO::PARAM_INT);
    $stmt->execute();
    $last = $stmt->fetchColumn();

    if (!$last) return 0;

    $elapsed = time() - strtotime($last);

    // Find the attempt that triggered the lockout (the MAX_ATTEMPTS-th one)
    $stmt2 = $db->prepare(
        "SELECT attempt_time FROM login_attempts
         WHERE ip_address = :ip
           AND success    = 0
           AND attempt_time > DATE_SUB(NOW(), INTERVAL :secs SECOND)
         ORDER BY attempt_time ASC
         LIMIT 1 OFFSET " . (MAX_ATTEMPTS - 1)
    );
    $stmt2->bindValue(':ip',   $ip);
    $stmt2->bindValue(':secs', LOCKOUT_SECS, PDO::PARAM_INT);
    $stmt2->execute();
    $lockStart = $stmt2->fetchColumn();

    if (!$lockStart) return 0;

    $remaining = LOCKOUT_SECS - (time() - strtotime($lockStart));
    return max(0, $remaining);
}

// ── State variables ───────────────────────────────────────────────
$error          = '';
$warning        = '';
$attempts_used  = 0;
$attempts_left  = MAX_ATTEMPTS;
$is_locked      = false;
$lockout_secs   = 0;
$show_counter   = false;
$ip             = $_SERVER['REMOTE_ADDR'];

// Check current lockout state on every page load
$attempts_used = getRecentFailedAttempts($db, $ip);
$attempts_left = max(0, MAX_ATTEMPTS - $attempts_used);

if ($attempts_used >= MAX_ATTEMPTS) {
    $lockout_secs = getLockoutSecondsRemaining($db, $ip);
    if ($lockout_secs > 0) {
        $is_locked = true;
    } else {
        // Lockout expired — reset counter display
        $attempts_used = 0;
        $attempts_left = MAX_ATTEMPTS;
    }
}

// ── Handle POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {

    if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh the page.';

    } else {
        $username = $security->sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha  = $_POST['captcha']  ?? '';

        // ── Check CAPTCHA ──────────────────────────────────────
        if (strtoupper(trim($captcha)) !== strtoupper($_SESSION['captcha_code'] ?? '')) {

            // Count this as a failed attempt
            $security->recordLoginAttempt($username, $ip, false);
            $attempts_used = getRecentFailedAttempts($db, $ip);
            $attempts_left = max(0, MAX_ATTEMPTS - $attempts_used);

            if ($attempts_left === 0) {
                $is_locked    = true;
                $lockout_secs = LOCKOUT_SECS;
                $error        = '';
            } else {
                $error = 'Incorrect verification code.';
            }

        // ── Check rate limit ────────────────────────────────────
        } elseif ($attempts_left === 0) {
            $is_locked    = true;
            $lockout_secs = getLockoutSecondsRemaining($db, $ip);

        // ── Main authentication ─────────────────────────────────
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM users WHERE username = :username AND is_locked = FALSE"
            );
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $security->verifyPassword($password, $user['password'])) {
                // ✅ SUCCESS
                $security->recordLoginAttempt($username, $ip, true);

                $s2 = $db->prepare(
                    "UPDATE users SET login_attempts = 0, last_login = NOW() WHERE id = :id"
                );
                $s2->bindParam(':id', $user['id']);
                $s2->execute();

                $session_token = bin2hex(random_bytes(32));
                $security->createSession($user['id'], $session_token);

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['full_name']     = $user['full_name'];
                $_SESSION['session_token'] = $session_token;
                $_SESSION['last_activity'] = time();

                header('Location: ' . ($user['role'] === 'admin'
                    ? 'admin/dashboard.php'
                    : 'student/dashboard.php'));
                exit();

            } else {
                // ❌ Wrong credentials
                $security->recordLoginAttempt($username, $ip, false);
                $attempts_used = getRecentFailedAttempts($db, $ip);
                $attempts_left = max(0, MAX_ATTEMPTS - $attempts_used);

                if ($attempts_left === 0) {
                    $is_locked    = true;
                    $lockout_secs = LOCKOUT_SECS;
                    $error        = '';
                } else {
                    $error = 'Username or password is incorrect.';
                }
            }
        }
    }
}

// ── Build warning message ──────────────────────────────────────────
// Show warning when attempts_left <= WARN_THRESHOLD and not yet locked
if (!$is_locked && empty($error) === false && $attempts_left > 0 && $attempts_left <= WARN_THRESHOLD) {
    $show_counter = true;
} elseif (!$is_locked && $attempts_left <= WARN_THRESHOLD && $attempts_used > 0) {
    $show_counter = true;
}

$csrf_token = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; SMS Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:          #0f1117;
            --ink-mid:      #3d4151;
            --ink-soft:     #7b8094;
            --surface:      #ffffff;
            --surface-2:    #f5f6f9;
            --surface-3:    #eceef4;
            --accent:       #2a52e8;
            --accent-light: #eaedfc;
            --accent-deep:  #1a3bb5;
            --danger:       #dc2626;
            --danger-bg:    #fef2f2;
            --border:       rgba(15,17,23,0.09);
            --border-str:   rgba(15,17,23,0.16);
            --r-sm: 6px;
            --r-md: 12px;
            --r-lg: 20px;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'DM Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
            color: var(--ink);
        }

        /* ── SPLIT LAYOUT ── */
        .split {
            display: grid;
            grid-template-columns: 1fr 500px;
            height: 100vh;
            width: 100vw;
        }

        /* ═══════════════════════════════════════════
           LEFT PANEL
        ═══════════════════════════════════════════ */
        .left {
            position: relative;
            background: var(--ink);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 52px 60px;
        }

        /* dot grid */
        .left::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* blue orb top-left */
        .orb-a {
            position: absolute;
            width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(42,82,232,0.25) 0%, transparent 68%);
            top: -160px; left: -140px; pointer-events: none;
        }

        /* subtle orb bottom-right */
        .orb-b {
            position: absolute;
            width: 380px; height: 380px; border-radius: 50%;
            background: radial-gradient(circle, rgba(42,82,232,0.1) 0%, transparent 68%);
            bottom: -60px; right: -80px; pointer-events: none;
        }

        /* pulsing rings */
        .ring {
            position: absolute; border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.055);
            animation: rpulse 9s ease-in-out infinite;
        }
        .r1 { width: 400px; height: 400px; top: -80px; left: -80px; animation-delay: 0s; }
        .r2 { width: 560px; height: 560px; top: -160px; left: -160px; animation-delay: 2.5s; }
        .r3 { width: 280px; height: 280px; bottom: 60px; right: -60px; animation-delay: 5s; }

        @keyframes rpulse {
            0%,100% { opacity: 0.35; transform: scale(1); }
            50%      { opacity: 0.8; transform: scale(1.05); }
        }

        /* logo */
        .l-logo {
            position: relative; z-index: 2;
            display: flex; align-items: center; gap: 12px;
        }
        .l-logomark {
            width: 40px; height: 40px; background: var(--accent);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
        }
        .l-logoname {
            font-size: 16px; font-weight: 500;
            color: rgba(255,255,255,0.88); letter-spacing: -0.2px;
        }

        /* body copy */
        .l-body { position: relative; z-index: 2; }

        .l-tag {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 5px 13px; border-radius: 100px;
            background: rgba(42,82,232,0.18);
            border: 1px solid rgba(42,82,232,0.32);
            font-size: 11px; font-weight: 500;
            color: #93abff; letter-spacing: 0.8px;
            text-transform: uppercase; margin-bottom: 24px;
        }
        .l-tag-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: #93abff; animation: blink 2.5s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.25} }

        .l-h1 {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(36px, 3.5vw, 58px);
            line-height: 1.07; letter-spacing: -1.5px;
            color: white; margin-bottom: 20px;
        }
        .l-h1 em { font-style: italic; color: #93abff; }

        .l-p {
            font-size: 15px; font-weight: 300;
            color: rgba(255,255,255,0.48);
            line-height: 1.72; max-width: 400px; margin-bottom: 36px;
        }

        /* security pills */
        .pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 13px; border-radius: 100px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            font-size: 12px; color: rgba(255,255,255,0.6);
            transition: background 0.18s, border-color 0.18s;
        }
        .pill:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.18); }
        .pill-dot { width: 5px; height: 5px; border-radius: 50%; background: #22c55e; }

        /* bottom stats */
        .l-stats {
            position: relative; z-index: 2;
            display: flex; gap: 36px;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 28px;
        }
        .l-stat-n {
            font-family: 'DM Serif Display', serif;
            font-size: 30px; color: white;
            letter-spacing: -1px; line-height: 1; margin-bottom: 4px;
        }
        .l-stat-l { font-size: 12px; color: rgba(255,255,255,0.38); font-weight: 300; }

        /* floating mini dashboard card */
        .fp {
            position: absolute;
            bottom: 130px; right: -1px;
            width: 270px;
            background: rgba(255,255,255,0.045);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-right: none;
            border-radius: 18px 0 0 18px;
            padding: 18px 22px;
            z-index: 4;
            animation: floatY 6s ease-in-out infinite;
        }
        @keyframes floatY { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }

        .fp-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .fp-title { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 0.5px; }
        .fp-live { font-size: 10px; padding: 2px 8px; border-radius: 100px; background: rgba(34,197,94,0.18); color: #4ade80; border: 1px solid rgba(34,197,94,0.28); }

        .fp-bars { display: flex; align-items: flex-end; gap: 5px; height: 40px; }
        .fp-b {
            flex: 1; border-radius: 3px 3px 0 0;
            background: rgba(42,82,232,0.5);
            animation: barAnim 3s ease-in-out infinite alternate;
        }
        .fp-b:nth-child(1){height:55%;animation-delay:0s}
        .fp-b:nth-child(2){height:82%;animation-delay:.15s}
        .fp-b:nth-child(3){height:44%;animation-delay:.3s}
        .fp-b:nth-child(4){height:100%;animation-delay:.45s;background:rgba(147,171,255,.65)}
        .fp-b:nth-child(5){height:70%;animation-delay:.6s}
        .fp-b:nth-child(6){height:92%;animation-delay:.75s}
        .fp-b:nth-child(7){height:38%;animation-delay:.9s}
        @keyframes barAnim { from{opacity:.45} to{opacity:1} }

        .fp-num {
            margin-top: 12px;
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: white; letter-spacing: -0.5px;
        }
        .fp-sub { font-size: 10px; color: rgba(255,255,255,0.38); margin-top: 2px; }

        /* ═══════════════════════════════════════════
           RIGHT PANEL
        ═══════════════════════════════════════════ */
        .right {
            background: var(--surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 52px 56px;
            overflow-y: auto;
            position: relative;
        }

        /* animated top bar accent */
        .right::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--accent), #93abff, var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }
        @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }

        .form-wrap {
            width: 100%; max-width: 380px;
            animation: fadeUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

        /* back link */
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--ink-soft); text-decoration: none;
            margin-bottom: 40px; transition: color 0.15s;
        }
        .back-link:hover { color: var(--accent); }

        /* heading */
        .f-h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 34px; letter-spacing: -0.8px;
            line-height: 1.1; color: var(--ink); margin-bottom: 6px;
        }
        .f-sub {
            font-size: 14px; font-weight: 300;
            color: var(--ink-soft); margin-bottom: 32px;
        }

        /* error */
        .err-box {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--danger-bg);
            border: 1px solid rgba(220,38,38,0.18);
            border-radius: var(--r-sm);
            padding: 12px 14px; margin-bottom: 24px;
            animation: shake .4s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-5px)}
            40%{transform:translateX(5px)}
            60%{transform:translateX(-3px)}
            80%{transform:translateX(3px)}
        }
        .err-ico {
            width: 16px; height: 16px; background: var(--danger); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-top: 1px;
        }
        .err-txt { font-size: 13px; color: var(--danger); line-height: 1.45; }

        /* field */
        .field { margin-bottom: 18px; }
        .f-label {
            display: block; font-size: 13px; font-weight: 500;
            color: var(--ink-mid); margin-bottom: 7px; letter-spacing: 0.1px;
        }
        .f-input {
            width: 100%; padding: 11px 14px;
            border: 1px solid var(--border-str); border-radius: var(--r-sm);
            font-size: 15px; font-family: inherit; color: var(--ink);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .f-input::placeholder { color: var(--ink-soft); font-weight: 300; }
        .f-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,82,232,.1); }

        /* pw */
        .pw-wrap { position: relative; }
        .pw-eye {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--ink-soft); padding: 4px;
            display: flex; align-items: center; transition: color .15s;
        }
        .pw-eye:hover { color: var(--ink); }

        /* captcha */
        .cap-panel { display: flex; gap: 10px; margin-top: 7px; margin-bottom: 8px; }
        .cap-frame {
            flex: 1; height: 54px;
            border: 1px solid var(--border-str); border-radius: var(--r-sm);
            background: var(--surface-2); overflow: hidden; position: relative;
        }
        .cap-frame img { width: 100%; height: 100%; object-fit: cover; display: block; transition: opacity .2s; }
        .cap-frame.loading img { opacity: 0.28; }
        .cap-spin-wrap { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; }
        .cap-frame.loading .cap-spin-wrap { display: flex; }
        .spin-ring {
            width: 18px; height: 18px;
            border: 2px solid var(--border-str); border-top-color: var(--accent);
            border-radius: 50%; animation: spin .6s linear infinite;
        }
        @keyframes spin { to{transform:rotate(360deg)} }
        .cap-refresh {
            width: 54px; height: 54px;
            border: 1px solid var(--border-str); border-radius: var(--r-sm);
            background: var(--surface); cursor: pointer;
            color: var(--ink-soft);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: background .15s, color .15s, transform .25s;
        }
        .cap-refresh:hover { background: var(--surface-3); color: var(--accent); }
        .cap-refresh:active { transform: rotate(180deg); }
        .cap-hint {
            font-size: 11px; color: var(--ink-soft); margin-bottom: 8px;
            display: flex; align-items: center; gap: 5px;
        }

        /* submit */
        .sub-btn {
            width: 100%; padding: 13px;
            background: var(--accent); color: white;
            border: none; border-radius: var(--r-sm);
            font-size: 15px; font-weight: 500; font-family: inherit;
            cursor: pointer; margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background .15s, transform .1s, box-shadow .15s;
        }
        .sub-btn:hover { background: var(--accent-deep); box-shadow: 0 4px 20px rgba(42,82,232,.38); }
        .sub-btn:active { transform: scale(0.99); }
        .sub-btn:disabled { opacity: .55; cursor: not-allowed; }
        .sub-spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.28); border-top-color: white; border-radius: 50%; animation: spin .6s linear infinite; display: none; }
        .sub-btn.loading .sub-text { display: none; }
        .sub-btn.loading .sub-spin { display: block; }

        /* form footer */
        .f-footer {
            margin-top: 28px; padding-top: 22px;
            border-top: 1px solid var(--border);
            text-align: center; font-size: 12px; color: var(--ink-soft);
        }

        /* ── LOCKOUT BOX ── */
        .lockout-box {
            background: #fff8f0;
            border: 1px solid rgba(234,88,12,0.25);
            border-radius: var(--r-md);
            padding: 28px 24px 22px;
            text-align: center;
            margin-bottom: 24px;
            animation: fadeUp 0.4s ease;
        }
        .lockout-icon {
            width: 52px; height: 52px;
            background: rgba(234,88,12,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            color: #ea580c;
        }
        .lockout-title {
            font-size: 16px; font-weight: 500;
            color: #9a3412; margin-bottom: 4px;
        }
        .lockout-sub {
            font-size: 13px; color: #c2410c; margin-bottom: 10px; font-weight: 300;
        }
        .lockout-timer {
            font-family: 'DM Serif Display', serif;
            font-size: 38px; letter-spacing: -1px;
            color: #ea580c; line-height: 1;
            margin-bottom: 14px;
        }
        .lockout-track {
            height: 4px; background: rgba(234,88,12,0.15);
            border-radius: 2px; overflow: hidden; margin-bottom: 14px;
        }
        .lockout-bar {
            height: 100%; background: #ea580c;
            border-radius: 2px;
            transition: width 1s linear;
        }
        .lockout-hint {
            font-size: 11px; color: #c2410c; font-weight: 300; opacity: 0.8;
        }

        /* ── ATTEMPT WARNING ── */
        .atw {
            border-radius: var(--r-sm);
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 12px;
        }
        .atw--amber {
            background: #fffbeb;
            border: 1px solid rgba(245,158,11,0.3);
        }
        .atw--red {
            background: #fff5f5;
            border: 1px solid rgba(220,38,38,0.3);
            animation: shake .35s ease;
        }
        .atw-dots {
            display: flex; gap: 5px; flex-shrink: 0;
        }
        .atw-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #e5e7eb;
            transition: background 0.2s;
        }
        .atw-dot.atw-dot--used { background: var(--danger); }
        .atw--amber .atw-dot:not(.atw-dot--used) { background: #fcd34d; }
        .atw--red   .atw-dot:not(.atw-dot--used) { background: #fca5a5; }
        .atw-txt { font-size: 13px; line-height: 1.45; }
        .atw--amber .atw-txt { color: #92400e; }
        .atw--red   .atw-txt { color: #991b1b; }
        .atw-txt strong { font-weight: 600; }

        /* mobile */
        @media (max-width: 860px) {
            html, body { overflow: auto; }
            .split { grid-template-columns: 1fr; height: auto; }
            .left { min-height: 280px; padding: 36px 28px; }
            .fp { display: none; }
            .right { padding: 40px 24px; min-height: 100vh; border-left: none; border-top: 1px solid var(--border); }
        }
    </style>
</head>
<body>
<div class="split">

    <!-- ════════════ LEFT ════════════ -->
    <div class="left">
        <div class="orb-a"></div>
        <div class="orb-b"></div>
        <div class="ring r1"></div>
        <div class="ring r2"></div>
        <div class="ring r3"></div>

        <!-- logo -->
        <div class="l-logo">
            <div class="l-logomark">
                <svg width="22" height="22" viewBox="0 0 18 18" fill="none">
                    <path d="M3 4.5C3 3.67 3.67 3 4.5 3H13.5C14.33 3 15 3.67 15 4.5V5.5C15 6.33 14.33 7 13.5 7H4.5C3.67 7 3 6.33 3 5.5V4.5Z" fill="white" fill-opacity="0.95"/>
                    <path d="M3 9.5C3 8.67 3.67 8 4.5 8H9.5C10.33 8 11 8.67 11 9.5C11 10.33 10.33 11 9.5 11H4.5C3.67 11 3 10.33 3 9.5Z" fill="white" fill-opacity="0.65"/>
                    <path d="M3 13.5C3 12.67 3.67 12 4.5 12H7.5C8.33 12 9 12.67 9 13.5C9 14.33 8.33 15 7.5 15H4.5C3.67 15 3 14.33 3 13.5Z" fill="white" fill-opacity="0.38"/>
                </svg>
            </div>
            <span class="l-logoname">SMS Portal</span>
        </div>

        <!-- copy -->
        <div class="l-body">
            <div class="l-tag"><span class="l-tag-dot"></span>Academic Management System</div>
            <h1 class="l-h1">Where every<br><em>record</em> finds<br>its place.</h1>
            <p class="l-p">A secure, role-based platform for administrators and students. Manage enrollments, track performance, and keep records &mdash; all from one unified dashboard.</p>
            <div class="pills">
                <span class="pill"><span class="pill-dot"></span>bcrypt passwords hashing</span>
                <span class="pill"><span class="pill-dot"></span>Role-based access</span>
                <span class="pill"><span class="pill-dot"></span>Session timeout</span>
                <span class="pill"><span class="pill-dot"></span> Limit Login Attempts</span>
                <span class="pill"><span class="pill-dot"></span>Captcha Verification</span>
            </div>
        </div>

        <!-- stats -->
        <div class="l-stats">
            <div><div class="l-stat-n">24/7</div><div class="l-stat-l">System uptime</div></div>
            <div><div class="l-stat-n">v1.0</div><div class="l-stat-l">Current version</div></div>
            <div><div class="l-stat-n">256&#8209;bit</div><div class="l-stat-l">Encryption</div></div>
        </div>

        <!-- floating mini chart -->
        <div class="fp">
            <div class="fp-top">
                <span class="fp-title">Student Activity</span>
                <span class="fp-live">&#9679;&nbsp;Live</span>
            </div>
            <div class="fp-bars">
                <div class="fp-b"></div><div class="fp-b"></div><div class="fp-b"></div>
                <div class="fp-b"></div><div class="fp-b"></div><div class="fp-b"></div>
                <div class="fp-b"></div>
            </div>
            <div class="fp-num" id="liveCount">&mdash;</div>
            <div class="fp-sub">active sessions today</div>
        </div>
    </div>

    <!-- ════════════ RIGHT ════════════ -->
    <div class="right">
        <div class="form-wrap">

            <a href="index.php" class="back-link">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to home
            </a>

            <h1 class="f-h1">Welcome back.</h1>
            <p class="f-sub">Sign in to access your dashboard.</p>

            <?php if ($is_locked): ?>
            <!-- LOCKOUT SCREEN -->
            <div class="lockout-box" id="lockoutBox">
                <div class="lockout-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
                    </svg>
                </div>
                <p class="lockout-title">Login temporarily disabled</p>
                <p class="lockout-sub">Too many failed attempts. Try again in</p>
                <div class="lockout-timer" id="lockoutTimer">--:--</div>
                <div class="lockout-track">
                    <div class="lockout-bar" id="lockoutBar" style="width:100%"></div>
                </div>
                <p class="lockout-hint">The form will unlock automatically when the timer ends.</p>
            </div>
            <?php endif; ?>

            <?php if (!$is_locked && $error): ?>
            <!-- ERROR BOX -->
            <div class="err-box" id="errBox">
                <div class="err-ico">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                        <path d="M2 2l4 4M6 2L2 6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="err-txt"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!$is_locked && $show_counter): ?>
            <!-- ATTEMPT WARNING -->
            <div class="atw <?php echo $attempts_left === 1 ? 'atw--red' : 'atw--amber'; ?>">
                <div class="atw-dots">
                    <?php for ($i = 0; $i < MAX_ATTEMPTS; $i++): ?>
                    <span class="atw-dot <?php echo ($i < $attempts_used) ? 'atw-dot--used' : ''; ?>"></span>
                    <?php endfor; ?>
                </div>
                <p class="atw-txt">
                    <?php if ($attempts_left === 1): ?>
                        <strong>Last attempt!</strong> Login will lock for <?php echo LOCKOUT_SECS / 60; ?> min after this.
                    <?php else: ?>
                        <strong><?php echo $attempts_left; ?> attempts left</strong> before a <?php echo LOCKOUT_SECS / 60; ?>-min lockout.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="off" novalidate
                  <?php echo $is_locked ? 'style="display:none"' : ''; ?>>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- Username -->
                <div class="field">
                    <label class="f-label" for="username">Username</label>
                    <input class="f-input" type="text" id="username" name="username"
                           placeholder="Enter your username" required autocomplete="username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <!-- Password -->
                <div class="field">
                    <label class="f-label" for="password">Password</label>
                    <div class="pw-wrap">
                        <input class="f-input" type="password" id="password" name="password"
                               placeholder="&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;"
                               required autocomplete="current-password" style="padding-right:42px">
                    </div>
                </div>

                <!-- CAPTCHA -->
                <div class="field">
                    <label class="f-label">Verification code</label>
                    <div class="cap-panel">
                        <div class="cap-frame" id="capFrame">
                            <img src="captcha/captcha.php" alt="CAPTCHA" id="capImg">
                            <div class="cap-spin-wrap"><div class="spin-ring"></div></div>
                        </div>
                        <button type="button" class="cap-refresh" id="capRefresh"
                                title="Get a new code" aria-label="Refresh CAPTCHA">
                            <svg width="17" height="17" viewBox="0 0 17 17" fill="none">
                                <path d="M14 8.5A5.5 5.5 0 1 1 8.5 3c1.5 0 2.9.6 3.9 1.6L14 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M14 3v3h-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <p class="cap-hint">
                        <svg width="11" height="11" viewBox="0 0 12 12" fill="none">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1"/>
                            <path d="M6 5v4M6 3.5v.5" stroke="currentColor" stroke-width="1" stroke-linecap="round"/>
                        </svg>
                        Not case-sensitive &middot; Click the refresh icon for a new code
                    </p>
                    <input class="f-input" type="text" id="captcha" name="captcha"
                           placeholder="Type the characters shown above"
                           required autocomplete="off"
                           style="letter-spacing:4px;text-transform:uppercase;font-weight:500">
                </div>

                <button type="submit" class="sub-btn" id="subBtn">
                    <span class="sub-text">
                        Sign in
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-left:4px;vertical-align:middle">
                            <path d="M2.5 7h9M8 3.5l3.5 3.5L8 10.5" stroke="white" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="sub-spin"></div>
                </button>
            </form>

            <div class="f-footer">
                &copy; <?php echo date('Y'); ?> Student Management System &middot; Secure Portal
            </div>
        </div>
    </div>

</div>

<script>
    // ── LOCKOUT COUNTDOWN ─────────────────────────────────────
    <?php if ($is_locked && $lockout_secs > 0): ?>
    (function() {
        var total     = <?php echo (int)$lockout_secs; ?>;
        var remaining = total;
        var timerEl   = document.getElementById('lockoutTimer');
        var barEl     = document.getElementById('lockoutBar');

        function fmt(s) {
            var m = Math.floor(s / 60);
            var sec = s % 60;
            return String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
        }

        function tick() {
            if (remaining <= 0) {
                // Lockout expired — reload page to unlock the form
                window.location.reload();
                return;
            }
            timerEl.textContent = fmt(remaining);
            var pct = (remaining / total) * 100;
            barEl.style.width = pct + '%';
            remaining--;
        }

        tick(); // run immediately so there's no 1-second blank
        setInterval(tick, 1000);
    })();
    <?php endif; ?>

    // ── Password toggle ───────────────────────────────────────
    var pwInput = document.getElementById('password');
    var pwEye   = document.getElementById('pwEye');
    if (pwInput && pwEye) {
        var eyeOpen = '<svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M1 8.5C1 8.5 3.5 3.5 8.5 3.5S16 8.5 16 8.5 13.5 13.5 8.5 13.5 1 8.5 1 8.5Z" stroke="currentColor" stroke-width="1.2"/><circle cx="8.5" cy="8.5" r="2.5" stroke="currentColor" stroke-width="1.2"/></svg>';
        var eyeShut = '<svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M2 2l13 13M7.1 7.2A2.5 2.5 0 0 0 10.8 10.9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M5 4.2C2.7 5.6 1 8.5 1 8.5S3.5 13.5 8.5 13.5c1.5 0 2.8-.5 3.9-1.3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M11.5 6A5.5 5.5 0 0 1 16 8.5s-2.5 5-7.5 5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        // Set the initial icon once — button starts empty in HTML
        pwEye.innerHTML = eyeOpen;

        pwEye.addEventListener('click', function() {
            var isPass = pwInput.type === 'password';
            pwInput.type = isPass ? 'text' : 'password';
            pwEye.innerHTML = isPass ? eyeShut : eyeOpen;
        });
    }

    // ── CAPTCHA refresh ───────────────────────────────────────
    var capFrame   = document.getElementById('capFrame');
    var capImg     = document.getElementById('capImg');
    var capRefresh = document.getElementById('capRefresh');
    var capInput   = document.getElementById('captcha');

    function refreshCaptcha() {
        if (!capFrame) return;
        capFrame.classList.add('loading');
        if (capInput) { capInput.value = ''; capInput.focus(); }
        var tmp = new Image();
        tmp.onload = function() {
            capImg.src = tmp.src;
            capFrame.classList.remove('loading');
        };
        tmp.onerror = function() { capFrame.classList.remove('loading'); };
        tmp.src = 'captcha/captcha.php?v=' + Date.now();
    }

    if (capRefresh) capRefresh.addEventListener('click', refreshCaptcha);

    if (capInput) {
        capInput.addEventListener('input', function() {
            var pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    }

    // Auto-refresh captcha after any login error
    <?php if ($error): ?>
    refreshCaptcha();
    <?php endif; ?>

    // ── Submit loading state ──────────────────────────────────
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            var u = document.getElementById('username');
            var p = document.getElementById('password');
            if (!u || !p || !capInput) return;
            if (!u.value.trim() || !p.value.trim() || !capInput.value.trim()) return;
            var btn = document.getElementById('subBtn');
            if (btn) { btn.classList.add('loading'); btn.disabled = true; }
        });
    }

    // ── Animated live counter (cosmetic) ──────────────────────
    var liveEl = document.getElementById('liveCount');
    if (liveEl) {
        var n = Math.floor(Math.random() * 38) + 10;
        liveEl.textContent = n;
        setInterval(function() {
            n += Math.floor(Math.random() * 3) - 1;
            n = Math.max(4, Math.min(75, n));
            liveEl.textContent = n;
        }, 2600);
    }
</script>
</body>
</html>