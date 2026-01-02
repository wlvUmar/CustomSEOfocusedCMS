<?php
// path: ./public/deploy.php
// SECURE DEPLOYMENT SCRIPT WITH UI + GITHUB WEBHOOK SUPPORT

session_start();

// ===================== CONFIG =====================
define('REPO_PATH', '/home/kuplyuta/appliances'); // your repo path
define('GITHUB_REPO_NAME', 'seowebsite'); // allow deploy only if payload matches this repo

// ===================== HELPER =====================
function logDeploy($msg) {
    $logFile = REPO_PATH . '/deploy.log';
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ===================== DETECT WEBHOOK =====================
$isWebhook = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_GITHUB_EVENT']));

// ===================== HANDLE WEBHOOK =====================
if ($isWebhook) {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    if (!$data) {
        http_response_code(400);
        die('Invalid JSON payload');
    }

    // Optional: check repository name
    $repoName = $data['repository']['name'] ?? '';
    if ($repoName !== GITHUB_REPO_NAME) {
        http_response_code(200);
        echo "Webhook received from other repo, ignoring.\n";
        exit;
    }

    // Optional: only deploy on master branch
    if (($data['ref'] ?? '') !== 'refs/heads/master') {
        http_response_code(200);
        echo "Not master branch, skipping deploy.\n";
        exit;
    }

    // Run git commands
    $commands = [
        'git reset --hard',
        'git clean -fd',
        'git pull origin master'
    ];

    $output = [];
    $exit_code = 0;

    foreach ($commands as $cmd) {
        $full_cmd = "cd " . REPO_PATH . " && $cmd 2>&1";
        exec($full_cmd, $cmd_output, $cmd_exit);
        $output[] = "$ $cmd";
        $output = array_merge($output, $cmd_output);
        if ($cmd_exit !== 0) $exit_code = $cmd_exit;
    }

    $output_text = implode("\n", $output);
    logDeploy("[WEBHOOK DEPLOY] " . $output_text);

    http_response_code(200);
    echo "Webhook deploy " . ($exit_code === 0 ? "succeeded" : "failed") . "\n";
    exit;
}

// ===================== UI / MANUAL DEPLOY =====================
// Only allow admin users for manual deploy
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF token
if (!isset($_SESSION['deploy_csrf'])) {
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

// Handle manual POST deploy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['deploy_csrf'] ?? '')) {
    $commands = [
        'git stash save "auto-stash before deploy ' . date('Y-m-d H:i:s') . '"',
        'git pull origin master',
        'git stash pop'
    ];

    $output = [];
    $exit_code = 0;

    foreach ($commands as $cmd) {
        $full_cmd = "cd " . REPO_PATH . " && $cmd 2>&1";
        exec($full_cmd, $cmd_output, $cmd_exit);
        $output[] = "$ $cmd";
        $output = array_merge($output, $cmd_output);
        if ($cmd_exit !== 0) $exit_code = $cmd_exit;
    }

    $output_text = implode("\n", $output);
    logDeploy("[MANUAL DEPLOY] " . $output_text);
}

// ===================== HTML UI =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deploy - Admin Only</title>
<style>
	body { font-family: monospace; background: #1a1a1c; color: #eee; padding: 20px; max-width: 900px; margin: 0 auto; } .warning { background: #dc3545; color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px; } .info { background: #0d6efd; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px; } button { background: #059669; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; font-weight: 600; } button:hover { background: #047857; } button:disabled { background: #6b7280; cursor: not-allowed; } pre { background: #111; color: #eee; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; } .success { color: #10b981; } .error { color: #ef4444; } .info-text { color: #3b82f6; } .warning-text { color: #f59e0b; } a { color: #3b82f6; text-decoration: none; } a:hover { text-decoration: underline; } .back-link { display: inline-block; margin-top: 20px; padding: 8px 16px; background: #374151; border-radius: 4px; }
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

	<form method="POST" style="margin-top:20px;">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
		<button type="submit">🔄 Deploy Again</button>
	</form>

<?php else: ?>
<p><strong>Current Status:</strong></p>
<pre><?= htmlspecialchars(shell_exec("cd " . REPO_PATH . " && git status 2>&1")) ?></pre>

	<form method="POST" onsubmit="return confirm('Are you sure you want to deploy?');">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
		<button type="submit">🚀 Deploy Now</button>
	</form>
<?php endif; ?>

<a href="/admin/dashboard" class="back-link">← Back to Admin Dashboard</a>
</body>
</html>
