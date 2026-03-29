<?php
// student/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuth();
checkRole('student');

$database = new Database();
$db       = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$student    = $stmt->fetch(PDO::FETCH_ASSOC);
$has_record = !empty($student);

function academicStanding($gpa) {
    if ($gpa >= 3.5) return ['label' => 'Excellent',        'cls' => 'excellent'];
    if ($gpa >= 3.0) return ['label' => 'Good Standing',   'cls' => 'good'];
    if ($gpa >= 2.0) return ['label' => 'Satisfactory',    'cls' => 'satisfactory'];
    if ($gpa >= 1.0) return ['label' => 'Probation',       'cls' => 'probation'];
    return              ['label' => 'Academic Warning', 'cls' => 'warning'];
}

$standing   = $has_record ? academicStanding((float)$student['gpa']) : null;
$year_label = ['', '1st', '2nd', '3rd', '4th', '5th'];

// Initials for avatar
$name_parts = explode(' ', trim($_SESSION['full_name']));
$initials   = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// GPA percentage for bar
$gpa_pct = $has_record ? min(100, ((float)$student['gpa'] / 4.0) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — Student Portal</title>
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

        /* ════════════ NAVBAR ════════════ */
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

        .nav-spacer { flex: 1; }

        /* Session timer pill in nav */
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

        .nav-user {
            display: flex; align-items: center; gap: 8px;
        }

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

        /* ════════════ PAGE ════════════ */
        .page {
            max-width: 1040px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        /* ════════════ HERO BANNER ════════════ */
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

        .hero-orb-a {
            position: absolute;
            width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(42,82,232,0.22) 0%, transparent 70%);
            top: -100px; left: -80px; pointer-events: none;
        }

        .hero-orb-b {
            position: absolute;
            width: 200px; height: 200px; border-radius: 50%;
            background: radial-gradient(circle, rgba(42,82,232,0.1) 0%, transparent 70%);
            bottom: -60px; right: 100px; pointer-events: none;
        }

        .hero-grid-bg {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
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
        .hero-eyebrow-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: #93abff;
            animation: dotPulse 2.5s ease-in-out infinite;
        }

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

        .hero-right {
            position: relative; z-index: 1;
            display: flex; flex-direction: column; align-items: flex-end; gap: 10px;
            flex-shrink: 0;
        }

        .hero-id-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--r-md);
            padding: 14px 20px; text-align: right; min-width: 160px;
        }
        .hero-id-label {
            font-size: 10px; font-weight: 500;
            letter-spacing: 0.8px; text-transform: uppercase;
            color: rgba(255,255,255,0.35); margin-bottom: 4px;
        }
        .hero-id-value {
            font-size: 18px; font-weight: 500;
            color: #ffffff; letter-spacing: 0.5px;
            font-variant-numeric: tabular-nums;
        }

        .hero-status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 100px;
            font-size: 11px; font-weight: 500;
        }
        .hero-status-pill.active {
            background: rgba(22,163,74,0.2);
            border: 1px solid rgba(22,163,74,0.3);
            color: #4ade80;
        }
        .hero-status-pill.inactive {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.5);
        }
        .hero-status-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: currentColor;
        }

        /* ════════════ SECTION LABEL ════════════ */
        .section-label {
            font-size: 11px; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--ink-ghost); margin-bottom: 14px; margin-top: 28px;
        }

        /* ════════════ STAT CARDS ROW ════════════ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px; margin-bottom: 4px;
            animation: fadeUp 0.5s 0.08s ease both;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: var(--r-lg);
            padding: 22px 22px 20px;
            box-shadow: var(--shadow-sm);
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card-accent {
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px; border-radius: var(--r-lg) 0 0 var(--r-lg);
        }
        .stat-card.c-green .stat-card-accent { background: var(--green); }
        .stat-card.c-blue  .stat-card-accent { background: var(--accent); }
        .stat-card.c-amber .stat-card-accent { background: var(--amber); }

        .stat-icon {
            width: 36px; height: 36px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .stat-card.c-green .stat-icon { background: var(--green-light); }
        .stat-card.c-blue  .stat-icon { background: var(--accent-light); }
        .stat-card.c-amber .stat-icon { background: var(--amber-light); }

        .stat-label {
            font-size: 11px; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--ink-soft); margin-bottom: 6px;
        }
        .stat-value {
            font-family: 'DM Serif Display', serif;
            font-size: 32px; color: var(--ink);
            letter-spacing: -1px; line-height: 1;
        }
        .stat-sub { font-size: 12px; color: var(--ink-ghost); margin-top: 4px; font-weight: 300; }

        /* GPA bar */
        .gpa-bar-wrap { margin-top: 12px; }
        .gpa-bar-track {
            height: 5px; background: var(--surface-3);
            border-radius: 99px; overflow: hidden;
        }
        .gpa-bar-fill {
            height: 100%; border-radius: 99px;
            background: var(--green);
            transition: width 1.2s cubic-bezier(0.16,1,0.3,1);
        }
        .gpa-bar-labels {
            display: flex; justify-content: space-between;
            margin-top: 4px; font-size: 10px; color: var(--ink-ghost);
        }

        /* ════════════ INFO GRID ════════════ */
        .info-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 14px;
            animation: fadeUp 0.5s 0.15s ease both;
        }

        .info-card {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .info-card-head {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
            display: flex; align-items: center; gap: 8px;
        }
        .info-card-head-icon {
            width: 28px; height: 28px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
        }
        .info-card-head-icon.green { background: var(--green-light); }
        .info-card-head-icon.blue  { background: var(--accent-light); }

        .info-card-head-title {
            font-size: 12px; font-weight: 500;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--ink-mid);
        }

        .info-card-body { padding: 4px 20px 16px; }

        .info-row {
            display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
        }
        .info-row:last-child { border-bottom: none; }

        .info-key {
            font-size: 12px; font-weight: 400;
            color: var(--ink-soft); white-space: nowrap;
        }
        .info-val {
            font-size: 13px; font-weight: 500;
            color: var(--ink); text-align: right;
        }

        /* ════════════ BADGES ════════════ */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 100px;
            font-size: 11px; font-weight: 500;
        }
        .badge::before {
            content: ''; width: 5px; height: 5px;
            border-radius: 50%; display: inline-block;
        }
        /* status */
        .s-active    { background: var(--green-light); color: #166534; }
        .s-active::before { background: var(--green); }
        .s-inactive  { background: var(--surface-3); color: var(--ink-mid); }
        .s-inactive::before { background: var(--ink-ghost); }
        .s-graduated { background: var(--blue-light); color: var(--blue); }
        .s-graduated::before { background: var(--blue); }
        .s-pending   { background: var(--amber-light); color: #92400e; }
        .s-pending::before { background: var(--amber); }
        .s-suspended { background: var(--red-light); color: #991b1b; }
        .s-suspended::before { background: var(--red); }
        /* standing */
        .excellent    { background: var(--green-light); color: #166534; }
        .excellent::before { background: var(--green); }
        .good         { background: var(--blue-light); color: var(--blue); }
        .good::before { background: var(--blue); }
        .satisfactory { background: var(--amber-light); color: #92400e; }
        .satisfactory::before { background: var(--amber); }
        .probation    { background: #fff7ed; color: #9a3412; }
        .probation::before { background: #ea580c; }
        .warning      { background: var(--red-light); color: #991b1b; }
        .warning::before { background: var(--red); }

        /* ════════════ INFO STRIP ════════════ */
        .info-strip {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--accent-light);
            border: 1px solid rgba(42,82,232,0.15);
            border-radius: var(--r-md);
            padding: 13px 16px;
            margin-top: 14px;
            font-size: 13px; font-weight: 300;
            color: var(--accent-deep);
            animation: fadeUp 0.5s 0.22s ease both;
        }
        .info-strip svg { flex-shrink: 0; margin-top: 1px; }

        /* ════════════ NAV LINKS (in navbar) ════════════ */

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


        /* ════════════ NO RECORD ════════════ */
        .no-record {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: var(--r-xl);
            padding: 64px 32px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            animation: fadeUp 0.5s ease both;
        }
        .no-record-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: var(--surface-3);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .no-record h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--ink-mid);
            margin-bottom: 8px;
        }
        .no-record p { font-size: 13px; font-weight: 300; color: var(--ink-ghost); }

        /* ════════════ SESSION TIMEOUT MODAL ════════════ */
        #st-overlay {
            position: fixed; inset: 0; z-index: 99999;
            background: rgba(15,17,23,0.6);
            backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
            animation: stFadeIn .25s ease;
        }
        @keyframes stFadeIn { from{opacity:0} to{opacity:1} }

        #st-box {
            background: var(--surface);
            border: 1px solid var(--border-str);
            border-radius: 22px;
            padding: 36px 32px 30px;
            width: 100%; max-width: 400px;
            text-align: center;
            box-shadow: 0 32px 80px rgba(15,17,23,0.18);
            animation: stSlideIn .32s cubic-bezier(.16,1,.3,1);
            font-family: 'DM Sans', sans-serif;
            position: relative;
        }
        @keyframes stSlideIn {
            from { opacity:0; transform:translateY(18px) scale(.96); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }

        /* Top accent bar on modal */
        #st-box::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #ea580c, #f97316, #ea580c);
            background-size: 200% 100%;
            border-radius: 22px 22px 0 0;
            animation: shimmer 2s linear infinite;
        }
        @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }

        .st-icon-wrap {
            width: 60px; height: 60px; border-radius: 50%;
            background: #fff7ed;
            border: 1px solid rgba(234,88,12,0.2);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px; color: #ea580c;
        }

        .st-title {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; letter-spacing: -0.4px;
            color: var(--ink); margin-bottom: 8px; line-height: 1.2;
        }

        .st-desc {
            font-size: 13px; font-weight: 300;
            color: var(--ink-soft); line-height: 1.7;
            margin-bottom: 24px;
        }
        .st-desc strong { color: #ea580c; font-weight: 500; }

        /* Countdown ring */
        .st-ring-wrap {
            position: relative; width: 80px; height: 80px;
            margin: 0 auto 8px;
        }
        .st-ring-wrap svg { transform: rotate(-90deg); display: block; }
        .st-ring-bg   { fill: none; stroke: var(--surface-3); stroke-width: 5; }
        .st-ring-fill {
            fill: none; stroke: #ea580c; stroke-width: 5;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.85s linear, stroke 0.3s;
        }
        .st-num {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: #ea580c;
            transition: color 0.3s;
        }
        .st-lbl {
            font-size: 11px; color: var(--ink-ghost);
            margin-bottom: 24px; letter-spacing: 0.03em;
        }

        .st-btns { display: flex; gap: 10px; }

        .st-btn {
            flex: 1; padding: 12px 16px; border-radius: 8px;
            font-size: 14px; font-weight: 500;
            font-family: inherit; cursor: pointer; border: none;
            transition: background .15s, transform .1s, box-shadow .15s;
        }
        .st-btn:active { transform: scale(0.98); }

        .st-stay {
            background: var(--accent); color: white;
        }
        .st-stay:hover {
            background: var(--accent-deep);
            box-shadow: 0 4px 16px rgba(42,82,232,0.35);
        }

        .st-out {
            background: transparent; color: var(--ink-soft);
            border: 1px solid var(--border-str) !important;
        }
        .st-out:hover { background: var(--surface-2); color: var(--ink); }

        /* ════════════ ANIMATIONS ════════════ */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ════════════ RESPONSIVE ════════════ */
        @media (max-width: 700px) {
            .nav { padding: 0 16px; }
            .page { padding: 20px 14px 48px; }
            .hero { padding: 24px 20px; flex-direction: column; align-items: flex-start; }
            .hero-right { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
            .stats-row { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
            .nav-name { display: none; }
            .nav-session span { display: none; }
        }
    </style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
<nav class="nav">
    <a href="#" class="nav-logo">
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
        <a href="dashboard.php" class="nav-link active">Dashboard</a>
        <a href="profile.php"  class="nav-link">Profile</a>
    </div>

    <div class="nav-spacer"></div>

    <!-- Live session pill -->
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

<!-- ════════════ PAGE ════════════ -->
<div class="page">

<?php if (!$has_record): ?>

    <div class="no-record">
        <div class="no-record-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ink-ghost)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <h2>No student record linked</h2>
        <p>Your account is not yet linked to a student record.<br>Please contact your administrator.</p>
    </div>

<?php else: ?>

    <!-- ── HERO BANNER ── -->
    <div class="hero">
        <div class="hero-grid-bg"></div>
        <div class="hero-orb-a"></div>
        <div class="hero-orb-b"></div>

        <div class="hero-left">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot"></span>
                Student Dashboard
            </div>
            <h1 class="hero-name">
                Welcome back,<br>
                <em><?php echo htmlspecialchars($student['first_name']); ?>!</em>
            </h1>
            <p class="hero-sub">
                <?php echo htmlspecialchars($student['course']); ?>
                &nbsp;&mdash;&nbsp;
                <?php echo $year_label[(int)$student['year_level']] ?? ''; ?> Year
            </p>
        </div>

        <div class="hero-right">
            <div class="hero-id-card">
                <p class="hero-id-label">Student ID</p>
                <p class="hero-id-value"><?php echo htmlspecialchars($student['student_id']); ?></p>
            </div>
            <div class="hero-status-pill <?php echo htmlspecialchars($student['status']); ?>">
                <span class="hero-status-dot"></span>
                <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
            </div>
        </div>
    </div>

    <!-- ── STATS ── -->
    <p class="section-label">Academic summary</p>
    <div class="stats-row">

        <div class="stat-card c-green">
            <div class="stat-card-accent"></div>
            <div class="stat-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <p class="stat-label">GPA</p>
            <p class="stat-value"><?php echo number_format((float)$student['gpa'], 2); ?></p>
            <p class="stat-sub">out of 4.00</p>
            <div class="gpa-bar-wrap">
                <div class="gpa-bar-track">
                    <div class="gpa-bar-fill" id="gpaBar" style="width:0%"></div>
                </div>
                <div class="gpa-bar-labels"><span>0.00</span><span>4.00</span></div>
            </div>
        </div>

        <div class="stat-card c-blue">
            <div class="stat-card-accent"></div>
            <div class="stat-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#2a52e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
            </div>
            <p class="stat-label">Year Level</p>
            <p class="stat-value"><?php echo (int)$student['year_level']; ?></p>
            <p class="stat-sub"><?php echo ($year_label[(int)$student['year_level']] ?? ''); ?> Year Student</p>
        </div>

        <div class="stat-card c-amber">
            <div class="stat-card-accent"></div>
            <div class="stat-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="7"/>
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                </svg>
            </div>
            <p class="stat-label">Academic Standing</p>
            <p class="stat-value" style="font-size:14px;margin-top:8px">
                <span class="badge <?php echo $standing['cls']; ?>" style="font-size:13px;padding:5px 12px">
                    <?php echo $standing['label']; ?>
                </span>
            </p>
            <p class="stat-sub">Based on current GPA</p>
        </div>

    </div>

    <!-- ── INFO CARDS ── -->
    <p class="section-label">Student information</p>
    <div class="info-grid">

        <!-- Personal -->
        <div class="info-card">
            <div class="info-card-head">
                <div class="info-card-head-icon blue">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2a52e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <span class="info-card-head-title">Personal Details</span>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-key">Full Name</span>
                    <span class="info-val"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Email</span>
                    <span class="info-val"><?php echo htmlspecialchars($student['email'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Phone</span>
                    <span class="info-val"><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val">
                        <span class="badge s-<?php echo htmlspecialchars($student['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Academic -->
        <div class="info-card">
            <div class="info-card-head">
                <div class="info-card-head-icon green">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                </div>
                <span class="info-card-head-title">Academic Details</span>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-key">Student ID</span>
                    <span class="info-val"><?php echo htmlspecialchars($student['student_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Course</span>
                    <span class="info-val"><?php echo htmlspecialchars($student['course'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Year Level</span>
                    <span class="info-val"><?php echo ($year_label[(int)$student['year_level']] ?? '—') . ' Year'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">GPA</span>
                    <span class="info-val"><?php echo number_format((float)$student['gpa'], 2); ?> / 4.00</span>
                </div>
                <div class="info-row">
                    <span class="info-key">Enrolled Since</span>
                    <span class="info-val"><?php echo date('F j, Y', strtotime($student['created_at'])); ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Info strip -->
    <div class="info-strip">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>This is a read-only view of your student record. Contact your administrator to request any changes.</span>
    </div>

<?php endif; ?>
</div>

<!-- SESSION TIMEOUT SCRIPT -->
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

    // DEBUG logs
    console.log('%c[SMS Session] Script loaded OK', 'color:#2a52e8;font-weight:bold');
    console.log('[SMS Session] Timeout=' + TIMEOUT + 's | Warn at ' + WARN_BEFORE + 's remaining');
    console.log('[SMS Session] Login: ' + LOGIN_URL + ' | Ping: ' + PING_URL);

    ['mousemove','keydown','click','scroll','touchstart'].forEach(function (e) {
        document.addEventListener(e, function () {
            var now = Date.now();
            if (now - lastActivity > 30000) pingServer();
            lastActivity = now;
            if (warningShown) hideModal();
        }, { passive: true });
    });

    function pingServer() {
        console.log('[SMS Session] Pinging server...');
        fetch(PING_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r){ return r.json(); })
        .then(function(d){ console.log('[SMS Session] Ping OK:', d); })
        .catch(function(err){ console.warn('[SMS Session] Ping FAILED:', err); });
    }

    // ── Update nav session pill ───────────────────────────────
    function updateNavPill(remaining) {
        var pill = document.getElementById('navSession');
        var txt  = document.getElementById('navSessionTxt');
        if (!pill || !txt) return;

        if (remaining <= 60) {
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            var display = m > 0
                ? m + 'm ' + String(s).padStart(2,'0') + 's left'
                : s + 's left';
            txt.textContent = display;
            pill.className = 'nav-session ' + (remaining <= 30 ? 'urgent' : 'warn');
        } else {
            var mins = Math.floor(remaining / 60);
            txt.textContent = mins + ' min remaining';
            pill.className = 'nav-session' + (remaining < 300 ? ' warn' : '');
        }
    }

    // ── Main interval: runs every second ─────────────────────
    setInterval(function () {
        if (redirected) return;
        var idle      = Math.floor((Date.now() - lastActivity) / 1000);
        var remaining = Math.max(0, TIMEOUT - idle);

        // Log every 5 seconds to avoid flooding console
        if (idle % 5 === 0) {
            var zone = remaining <= WARN_BEFORE ? ' ⚠ WARNING ZONE' : '';
            console.log('[SMS Session] Idle: ' + idle + 's | Remaining: ' + remaining + 's' + zone);
        }

        updateNavPill(remaining);

        if (remaining <= 0) {
            console.warn('[SMS Session] TIMEOUT — redirecting to login now!');
            doRedirect();
        } else if (remaining <= WARN_BEFORE && !warningShown) {
            console.warn('[SMS Session] Showing warning modal — ' + remaining + 's left');
            showModal(remaining);
        } else if (warningShown) {
            updateModal(remaining);
        }
    }, 1000);

    // ── Redirect ─────────────────────────────────────────────
    function doRedirect() {
        if (redirected) return;
        redirected = true;
        window.location.href = LOGIN_URL + '?timeout=1';
    }

    // ── Build modal DOM ───────────────────────────────────────
    function buildModal() {
        if (document.getElementById('st-overlay')) return;

        var div = document.createElement('div');
        div.id  = 'st-overlay';
        div.setAttribute('role', 'dialog');
        div.setAttribute('aria-modal', 'true');
        div.innerHTML =
            '<div id="st-box">' +
            '  <div class="st-icon-wrap">' +
            '    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">' +
            '      <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>' +
            '      <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '    </svg>' +
            '  </div>' +
            '  <p class="st-title">Session expiring soon</p>' +
            '  <p class="st-desc">' +
            '    Your session is about to expire due to <strong>inactivity</strong>.<br>' +
            '    Move your mouse or click <em>Stay logged in</em> to continue.' +
            '  </p>' +
            '  <div class="st-ring-wrap">' +
            '    <svg width="80" height="80" viewBox="0 0 80 80">' +
            '      <circle class="st-ring-bg" cx="40" cy="40" r="34"/>' +
            '      <circle class="st-ring-fill" id="st-ring" cx="40" cy="40" r="34"' +
            '        stroke-dasharray="' + CIRCUM.toFixed(2) + '"' +
            '        stroke-dashoffset="0"/>' +
            '    </svg>' +
            '    <div class="st-num" id="st-num">--</div>' +
            '  </div>' +
            '  <p class="st-lbl" id="st-lbl">seconds before automatic logout</p>' +
            '  <div class="st-btns">' +
            '    <button class="st-btn st-stay" id="st-stay">Stay logged in</button>' +
            '    <button class="st-btn st-out"  id="st-out">Log out now</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(div);

        document.getElementById('st-stay').onclick = function () {
            pingServer();
            lastActivity = Date.now();
            hideModal();
        };
        document.getElementById('st-out').onclick = function () {
            window.location.href = LOGIN_URL + '?logout=1';
        };
    }

    // ── Show / Update / Hide modal ────────────────────────────
    function showModal(remaining) {
        warningShown = true;
        buildModal();
        updateModal(remaining);
    }

    function updateModal(remaining) {
        var numEl  = document.getElementById('st-num');
        var ringEl = document.getElementById('st-ring');
        var lblEl  = document.getElementById('st-lbl');
        if (!numEl) return;

        var secs = Math.max(0, remaining);
        numEl.textContent = secs;

        if (ringEl) {
            var offset = CIRCUM * (1 - secs / WARN_BEFORE);
            ringEl.style.strokeDashoffset = offset.toFixed(2);
        }

        if (secs <= 10) {
            numEl.style.color = '#dc2626';
            if (ringEl) ringEl.style.stroke = '#dc2626';
            if (lblEl)  lblEl.innerHTML = '<strong style="color:#dc2626;font-weight:500">Logging out in ' + secs + 's!</strong>';
        } else {
            numEl.style.color = '#ea580c';
            if (ringEl) ringEl.style.stroke = '#ea580c';
            if (lblEl)  lblEl.textContent = 'seconds before automatic logout';
        }
    }

    function hideModal() {
        warningShown = false;
        var el = document.getElementById('st-overlay');
        if (!el) return;
        el.style.transition = 'opacity .2s';
        el.style.opacity = '0';
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 210);
    }

    // ── Animate GPA bar on load ───────────────────────────────
    var gpaBar = document.getElementById('gpaBar');
    if (gpaBar) {
        setTimeout(function () {
            gpaBar.style.width = '<?php echo $gpa_pct; ?>%';
        }, 400);
    }

})();
</script>

</body>
</html>