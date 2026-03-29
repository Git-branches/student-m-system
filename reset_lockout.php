<?php
/**
 * reset_lockout.php
 * Run this from your browser or CLI to clear login lockouts.
 * DELETE THIS FILE after use, or protect it behind admin auth.
 */

session_start();
require_once 'config/database.php';

$database = new Database();
$db       = $database->getConnection();

$message = '';
$stats   = [];

// ── Fetch current lockout stats before doing anything ──────────
$stmt = $db->query(
    "SELECT ip_address, username, COUNT(*) as attempts,
            MAX(attempt_time) as last_attempt
     FROM login_attempts
     WHERE success = 0
       AND attempt_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     GROUP BY ip_address, username
     ORDER BY last_attempt DESC"
);
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Handle reset actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['reset_ip']) && !empty($_POST['reset_ip'])) {
        $ip = $_POST['reset_ip'];
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip AND success = 0");
        $stmt->bindParam(':ip', $ip);
        $stmt->execute();
        $rows = $stmt->rowCount();
        $message = "✓ Cleared {$rows} failed attempt(s) for IP: {$ip}";

    } elseif (isset($_POST['reset_user']) && !empty($_POST['reset_user'])) {
        $user = $_POST['reset_user'];
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = :u AND success = 0");
        $stmt->bindParam(':u', $user);
        $stmt->execute();
        $rows = $stmt->rowCount();
        $message = "✓ Cleared {$rows} failed attempt(s) for user: {$user}";

    } elseif (isset($_POST['reset_all'])) {
        $stmt = $db->query("DELETE FROM login_attempts WHERE success = 0");
        $rows = $stmt->rowCount();
        $message = "✓ Cleared all {$rows} failed attempt(s). All lockouts lifted.";

    } elseif (isset($_POST['unlock_user']) && !empty($_POST['unlock_user'])) {
        $user = $_POST['unlock_user'];
        $stmt = $db->prepare("UPDATE users SET is_locked = FALSE, login_attempts = 0 WHERE username = :u");
        $stmt->bindParam(':u', $user);
        $stmt->execute();
        $rows = $stmt->rowCount();
        $message = "✓ Unlocked account for user: {$user}";
    }

    // Refresh stats after action
    $stmt = $db->query(
        "SELECT ip_address, username, COUNT(*) as attempts,
                MAX(attempt_time) as last_attempt
         FROM login_attempts
         WHERE success = 0
           AND attempt_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         GROUP BY ip_address, username
         ORDER BY last_attempt DESC"
    );
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Login Lockout</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink: #0f1117; --ink-mid: #3d4151; --ink-soft: #7b8094;
            --surface: #fff; --surface-2: #f5f6f9; --surface-3: #eceef4;
            --accent: #2a52e8; --danger: #dc2626; --danger-bg: #fef2f2;
            --success: #16a34a; --success-bg: #f0fdf4;
            --border: rgba(15,17,23,0.09); --border-str: rgba(15,17,23,0.16);
            --r-sm: 6px; --r-md: 12px;
        }
        body {
            font-family: 'DM Sans', sans-serif; background: var(--surface-2);
            min-height: 100vh; padding: 40px 20px; color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 680px; margin: 0 auto; }

        h1 {
            font-size: 22px; font-weight: 500; color: var(--ink);
            margin-bottom: 4px; letter-spacing: -0.3px;
        }
        .sub { font-size: 13px; color: var(--ink-soft); margin-bottom: 32px; }

        .warning-banner {
            background: #fff7ed; border: 1px solid rgba(234,88,12,0.25);
            border-radius: var(--r-sm); padding: 12px 14px;
            font-size: 13px; color: #9a3412; margin-bottom: 24px;
            display: flex; align-items: center; gap: 8px;
        }

        .card {
            background: var(--surface); border: 1px solid var(--border-str);
            border-radius: var(--r-md); padding: 24px; margin-bottom: 20px;
        }
        .card-title {
            font-size: 14px; font-weight: 500; color: var(--ink);
            margin-bottom: 16px; padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .msg {
            padding: 12px 14px; border-radius: var(--r-sm);
            font-size: 13px; margin-bottom: 20px;
            background: var(--success-bg);
            border: 1px solid rgba(22,163,74,0.25);
            color: var(--success); font-weight: 500;
        }

        /* table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            text-align: left; font-size: 11px; font-weight: 500;
            color: var(--ink-soft); text-transform: uppercase; letter-spacing: 0.5px;
            padding: 0 0 8px; border-bottom: 1px solid var(--border);
        }
        td { padding: 10px 0; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 100px;
            font-size: 11px; font-weight: 500;
        }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-amber { background: #fef3c7; color: #92400e; }

        /* forms */
        .field-row { display: flex; gap: 8px; align-items: flex-end; margin-bottom: 12px; }
        .field-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        label { font-size: 12px; font-weight: 500; color: var(--ink-mid); }
        input[type="text"] {
            padding: 9px 12px; border: 1px solid var(--border-str);
            border-radius: var(--r-sm); font-size: 14px; font-family: inherit;
            color: var(--ink); background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s; width: 100%;
        }
        input[type="text"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(42,82,232,.1);
        }
        .btn {
            padding: 9px 18px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 500; font-family: inherit;
            cursor: pointer; border: none; white-space: nowrap;
            transition: background .15s, transform .1s;
        }
        .btn:active { transform: scale(0.98); }
        .btn-accent { background: var(--accent); color: white; }
        .btn-accent:hover { background: #1a3bb5; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-ghost {
            background: transparent; color: var(--ink-mid);
            border: 1px solid var(--border-str);
        }
        .btn-ghost:hover { background: var(--surface-3); }

        .divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

        .empty { font-size: 13px; color: var(--ink-soft); padding: 12px 0; }

        .back { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: var(--ink-soft); text-decoration: none; margin-top: 24px; transition: color .15s; }
        .back:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="wrap">

    <h1>Login Lockout Reset</h1>
    <p class="sub">View and clear active login lockouts. This tool bypasses the timer immediately.</p>

    <div class="warning-banner">
        ⚠ Delete this file after use — it has no authentication and should not be publicly accessible.
    </div>

    <?php if ($message): ?>
    <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Active lockouts table -->
    <div class="card">
        <p class="card-title">Active failed attempts (last 10 min)</p>
        <?php if (empty($stats)): ?>
        <p class="empty">No active lockouts found.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Username</th>
                    <th>Attempts</th>
                    <th>Last attempt</th>
                    <th>Reset</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                    <td><?php echo htmlspecialchars($row['username'] ?: '—'); ?></td>
                    <td>
                        <span class="badge <?php echo $row['attempts'] >= 5 ? 'badge-red' : 'badge-amber'; ?>">
                            <?php echo (int)$row['attempts']; ?> / 5
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['last_attempt']); ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="reset_ip" value="<?php echo htmlspecialchars($row['ip_address']); ?>">
                            <button type="submit" class="btn btn-ghost">Clear IP</button>
                        </form>
                        <?php if ($row['username']): ?>
                        <form method="POST" style="display:inline;margin-left:4px">
                            <input type="hidden" name="reset_user" value="<?php echo htmlspecialchars($row['username']); ?>">
                            <button type="submit" class="btn btn-ghost">Clear user</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Manual reset by IP -->
    <div class="card">
        <p class="card-title">Reset by IP address</p>
        <form method="POST">
            <div class="field-row">
                <div class="field-group">
                    <label for="ip_input">IP Address</label>
                    <input type="text" id="ip_input" name="reset_ip" placeholder="e.g. 192.168.1.1" required>
                </div>
                <button type="submit" class="btn btn-accent">Clear lockout</button>
            </div>
        </form>
    </div>

    <!-- Manual reset by username -->
    <div class="card">
        <p class="card-title">Reset by username</p>
        <form method="POST">
            <div class="field-row">
                <div class="field-group">
                    <label for="user_input">Username</label>
                    <input type="text" id="user_input" name="reset_user" placeholder="e.g. john_doe" required>
                </div>
                <button type="submit" class="btn btn-accent">Clear attempts</button>
            </div>
        </form>
        <hr class="divider">
        <p class="card-title" style="margin-top:4px;border-bottom:none;padding-bottom:0">Unlock account (if is_locked = TRUE)</p>
        <form method="POST" style="margin-top:12px">
            <div class="field-row">
                <div class="field-group">
                    <label for="unlock_input">Username</label>
                    <input type="text" id="unlock_input" name="unlock_user" placeholder="e.g. john_doe" required>
                </div>
                <button type="submit" class="btn btn-accent">Unlock account</button>
            </div>
        </form>
    </div>

    <!-- Nuclear option -->
    <div class="card">
        <p class="card-title" style="color: var(--danger)">Reset all lockouts</p>
        <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">
            Clears every failed attempt record. This immediately unlocks all IPs.
        </p>
        <form method="POST" onsubmit="return confirm('Clear ALL failed login attempts? This cannot be undone.');">
            <button type="submit" name="reset_all" class="btn btn-danger">Clear all failed attempts</button>
        </form>
    </div>

    <a href="login.php" class="back">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Back to login
    </a>
</div>
</body>
</html>