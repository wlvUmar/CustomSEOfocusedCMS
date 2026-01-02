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
// Handle GitHub Webhook
// --------------------
$latestCommit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    if ($data) {
        $repoName = $data['repository']['name'] ?? '';
        $branch = $data['ref'] ?? '';
        if ($repoName === GITHUB_REPO_NAME && $branch === 'refs/heads/master') {
            $commits = $data['commits'] ?? [];
            if (!empty($commits)) {
                $last = end($commits);
                $latestCommit = [
                    'sha' => substr($last['id'] ?? '', 0, 7),
                    'message' => $last['message'] ?? '',
                    'author' => $last['author']['name'] ?? '',
                    'date' => $last['timestamp'] ?? '',
                ];
            }
            logDeploy("[WEBHOOK] Push detected: " . ($latestCommit['sha'] ?? 'N/A'));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $_SESSION['deploy_csrf'] && !isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
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

    // highlight common messages
    $outputHtml = htmlspecialchars($deployOutput);
    $outputHtml = preg_replace('/Already up[ -]to[ -]date/', '<span class="success">Already up-to-date</span>', $outputHtml);
    $outputHtml = preg_replace('/No local changes to save/', '<span class="info-text">No local changes</span>', $outputHtml);
    $outputHtml = preg_replace('/CONFLICT/', '<span class="danger">CONFLICT</span>', $outputHtml);
}

// --------------------
// Fetch latest commits (always)
// --------------------
$allCommits = [];
$gitLogOutput = shell_exec("cd ".REPO_PATH." && git log origin/master -5 --pretty=format:'%h|%an|%ad|%s'");
if ($gitLogOutput) {
    $lines = explode("\n", $gitLogOutput);
    foreach ($lines as $line) {
        list($sha, $author, $date, $message) = explode('|', $line, 4);
        $allCommits[] = compact('sha','author','date','message');
    }
}

// --------------------
// Page layout variables
// --------------------
$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<style>
.deploy-commits {
    background-color: #1e1e1e;
    color: #d4d4d4;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
    padding: 16px;
    border-radius: 6px;
    overflow-x: auto;
    max-height: 400px;
    margin-bottom: 20px;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
}

.deploy-commits ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

.deploy-commits li {
    padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.deploy-commits li:last-child {
    border-bottom: none;
}

.deploy-commits li::before {
    content: "$ ";
    color: #6a9955;
    margin-right: 4px;
}

.deploy-commits li .sha {
    color: #569cd6;
    font-weight: bold;
}

.deploy-commits li .author {
    color: #9cdcfe;
}

.deploy-commits li .timestamp {
    color: #c586c0;
    font-size: 12px;
    margin-left: 6px;
}

.deploy-commits li .message {
    color: #d4d4d4;
}

/* Highlight keywords */
.deploy-commits li .message .success { color: #6a9955; font-weight: bold; }
.deploy-commits li .message .info-text { color: #4fc1ff; }
.deploy-commits li .message .danger { color: #f44747; font-weight: bold; }

/* Highlight latest push */
.deploy-commits li.new-push {
    background-color: #2d2d2d;
    border-left: 4px solid #ffcc00;
    padding-left: 12px;
}
</style>

<div class="page-header">
    <h1><i data-feather="zap"></i> Git Deploy</h1>
</div>

<?php if(!empty($allCommits)): ?>
<div class="deploy-commits">
    <ul>
    <?php foreach($allCommits as $commit): 
        $messageHtml = htmlspecialchars($commit['message']);
        $messageHtml = preg_replace('/Already up[ -]to[ -]date/', '<span class="success">Already up-to-date</span>', $messageHtml);
        $messageHtml = preg_replace('/No local changes to save/', '<span class="info-text">No local changes</span>', $messageHtml);
        $messageHtml = preg_replace('/CONFLICT/', '<span class="danger">CONFLICT</span>', $messageHtml);

        $isNew = ($latestCommit && $commit['sha'] === $latestCommit['sha']);
    ?>
        <li class="<?= $isNew ? 'new-push' : '' ?>">
            <span class="sha"><?= htmlspecialchars($commit['sha']) ?></span> - 
            <span class="message"><?= $messageHtml ?></span>
            <span class="author"><?= htmlspecialchars($commit['author']) ?></span>
            <span class="timestamp"><?= htmlspecialchars($commit['date']) ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if($manualDeployed): ?>
<h2>
    <?= $deployExit===0 
        ? '<i data-feather="check-circle" class="text-success"></i> Deploy Completed' 
        : '<i data-feather="x-circle" class="text-danger"></i> Deploy Failed' 
    ?>
</h2>
<pre><?= $outputHtml ?></pre>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
    <button type="submit" class="btn btn-primary">
        <i data-feather="zap"></i> Deploy Now
    </button>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
