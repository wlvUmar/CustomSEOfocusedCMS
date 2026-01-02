<?php
// path: ./views/admin/deploy.php

require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances'); 
define('GITHUB_REPO_NAME', 'seowebsite');

// --------------------
// Logging function
// --------------------
function logDeploy($msg){
    $logFile = REPO_PATH.'/deploy.log';
    $line = "[".date('Y-m-d H:i:s')."] ".$msg.PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// --------------------
// Check for GitHub webhook
// --------------------
$latestCommit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    if ($data) {
        $repoName = $data['repository']['name'] ?? '';
        $branch = $data['ref'] ?? '';
        if ($repoName === GITHUB_REPO_NAME && $branch === 'refs/heads/master') {
            $latestCommit = [
                'sha' => substr($data['after'] ?? '', 0, 7),
                'message' => $data['head_commit']['message'] ?? '',
                'author' => $data['head_commit']['author']['name'] ?? '',
                'date' => $data['head_commit']['timestamp'] ?? '',
            ];
            logDeploy("[WEBHOOK] Latest push detected: ".$latestCommit['sha']." by ".$latestCommit['author']);
        }
    }
}

// --------------------
// Manual deploy (admin only)
// --------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF token
if (!isset($_SESSION['deploy_csrf'])) {
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

$deployOutput = '';
$deployExit = null;
$manualDeployed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $_SESSION['deploy_csrf']) {
    $commands = [
        'git reset --hard',
        'git clean -fd',
        'git pull origin master'
    ];

    $output = [];
    $exit = 0;
    foreach ($commands as $cmd) {
        $full = "cd ".REPO_PATH." && $cmd 2>&1";
        exec($full, $cmdOut, $cmdExit);
        $output[] = "$ $cmd";
        $output = array_merge($output, $cmdOut);
        if ($cmdExit !== 0) $exit = $cmdExit;
    }

    $deployOutput = implode("\n", $output);
    $deployExit = $exit;
    $manualDeployed = true;
    logDeploy("[MANUAL DEPLOY] ".$deployOutput);
}

// Get last push on origin/master
$lastPush = null;
$gitOutput = shell_exec("cd ".REPO_PATH." && git log origin/master -1 --pretty=format:'%h|%an|%ad|%s'");
if ($gitOutput) {
    list($sha,$author,$date,$message) = explode('|', $gitOutput, 4);
    $lastPush = compact('sha','author','date','message');
}

// --------------------
// Page layout variables
// --------------------
$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="zap"></i> Git Deploy</h1>
</div>


<?php if($latestCommit): ?>
<div class="alert alert-success">
    Latest push from webhook detected: 
    <strong><?= htmlspecialchars($latestCommit['sha']) ?></strong> - 
    <?= htmlspecialchars($latestCommit['message']) ?> by 
    <?= htmlspecialchars($latestCommit['author']) ?> at <?= htmlspecialchars($latestCommit['date']) ?>
</div>
<?php elseif($lastPush): ?>
<div class="alert alert-info">
    Last push on origin/master: 
    <strong><?= htmlspecialchars($lastPush['sha']) ?></strong> - 
    <?= htmlspecialchars($lastPush['message']) ?> by 
    <?= htmlspecialchars($lastPush['author']) ?> at <?= htmlspecialchars($lastPush['date']) ?>
</div>
<?php endif; ?>

<?php if($manualDeployed): ?>
<h2>
    <?= $deployExit===0 ? '<i data-feather="check-circle" class="text-success"></i> Deploy Completed' : '<i data-feather="x-circle" class="text-danger"></i> Deploy Failed' ?>
</h2>
<pre><?= $outputHtml ?></pre>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
    <button type="submit" class="btn btn-primary"><i data-feather="zap"></i> Deploy Now</button>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
