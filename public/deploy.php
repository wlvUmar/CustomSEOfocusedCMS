<?php
// path: ./public/deploy.php
// SECURE DEPLOYMENT SCRIPT WITH AUTHENTICATION

session_start();

// CRITICAL: Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// Additional IP whitelist (optional but recommended)
$allowed_ips = [
    '127.0.0.1',
    '::1',
    // Add your server IP or office IP here
    // '192.168.1.100',
];

$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Uncomment to enable IP whitelist
// if (!in_array($user_ip, $allowed_ips)) {
//     http_response_code(403);
//     die('Access Denied. Your IP is not authorized.');
// }

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['deploy_csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

// Generate CSRF token for form
if (!isset($_SESSION['deploy_csrf'])) {
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy - Admin Only</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a1c;
            color: #eee;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        .warning {
            background: #dc3545;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info {
            background: #0d6efd;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        button {
            background: #059669;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            background: #047857;
        }
        button:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }
        pre {
            background: #111;
            color: #eee;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info-text { color: #3b82f6; }
        .warning-text { color: #f59e0b; }
        a {
            color: #3b82f6;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #374151;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>🚀 Git Deploy</h1>
    
    <div class="info">
        <strong>ℹ️ Info:</strong> Logged in as: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Unknown') ?></strong>
    </div>
    
    <div class="warning">
        <strong>⚠️ WARNING:</strong> This will pull the latest changes from the master branch and may overwrite local changes!
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        
        <?php
        // Execute git commands
        $commands = [
            'git stash save "auto-stash before deploy ' . date('Y-m-d H:i:s') . '"',
            'git pull origin master',
            'git stash pop'
        ];
        
        $output = [];
        $exit_code = 0;
        
        foreach ($commands as $cmd) {
            $full_cmd = "cd /home/kuplyuta/appliances && $cmd 2>&1";
            exec($full_cmd, $cmd_output, $cmd_exit);
            
            $output[] = "$ $cmd";
            $output = array_merge($output, $cmd_output);
            
            if ($cmd_exit !== 0) {
                $exit_code = $cmd_exit;
            }
        }
        
        $output_text = implode("\n", $output);
        
        // Color-code output
        $output_html = htmlspecialchars($output_text);
        $output_html = preg_replace('/Already up[ -]to[ -]date/', '<span class="success">Already up-to-date</span>', $output_html);
        $output_html = preg_replace('/No local changes to save/', '<span class="info-text">No local changes to save</span>', $output_html);
        $output_html = preg_replace('/Auto-merging/', '<span class="info-text">Auto-merging</span>', $output_html);
        $output_html = preg_replace('/CONFLICT/', '<span class="error">CONFLICT</span>', $output_html);
        $output_html = preg_replace('/error:/', '<span class="error">error:</span>', $output_html);
        $output_html = preg_replace('/warning:/', '<span class="warning-text">warning:</span>', $output_html);
        ?>
        
        <h2><?= $exit_code === 0 ? '✅ Deploy Completed' : '❌ Deploy Failed' ?></h2>
        
        <pre><?= $output_html ?></pre>
        
        <?php if ($exit_code === 0): ?>
            <div class="info">
                <strong>✓ Success!</strong> Changes have been deployed. 
                <a href="/admin/dashboard" style="color: white; text-decoration: underline;">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="warning">
                <strong>⚠ Deployment exited with code <?= $exit_code ?></strong><br>
                Check the output above for errors. You may need to resolve conflicts manually via SSH.
            </div>
        <?php endif; ?>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
            <button type="submit">🔄 Deploy Again</button>
        </form>
        
    <?php else: ?>
        
        <p><strong>Current Status:</strong></p>
        <?php
        $status_cmd = "cd /home/kuplyuta/appliances && git status 2>&1";
        $status_output = shell_exec($status_cmd);
        echo '<pre>' . htmlspecialchars($status_output) . '</pre>';
        ?>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to deploy? This will pull from master branch.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
            <button type="submit">🚀 Deploy Now</button>
        </form>
        
    <?php endif; ?>
    
    <a href="/admin/dashboard" class="back-link">← Back to Admin Dashboard</a>
</body>
</html>