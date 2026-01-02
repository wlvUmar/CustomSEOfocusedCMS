<?php
require_once __DIR__ . '/config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances'); 
define('GITHUB_REPO_NAME', 'seowebsite');

function logDeploy($msg){
    $logFile = REPO_PATH.'/deploy.log';
    $line = "[".date('Y-m-d H:i:s')."] ".$msg.PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$isWebhook = ($_SERVER['REQUEST_METHOD']==='POST' && isset($_SERVER['HTTP_X_GITHUB_EVENT']));
$latestCommit = null;

if($isWebhook){
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    if($data){
        $repoName = $data['repository']['name'] ?? '';
        $branch = $data['ref'] ?? '';
        if($repoName===GITHUB_REPO_NAME && $branch==='refs/heads/master'){
            $latestCommit = [
                'sha' => substr($data['after']??'',0,7),
                'message' => $data['head_commit']['message']??'',
                'author' => $data['head_commit']['author']['name']??'',
                'date' => $data['head_commit']['timestamp']??'',
            ];
            logDeploy("[WEBHOOK] Latest push detected: ".$latestCommit['sha']." by ".$latestCommit['author']);
        }
    }
}

// ===================== MANUAL DEPLOY =====================
if(!isset($_SESSION['user_id'])){
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF token
if(!isset($_SESSION['deploy_csrf'])){
    $_SESSION['deploy_csrf']=bin2hex(random_bytes(32));
}

$deployOutput = '';
$deployExit = null;
$manualDeployed = false;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['csrf_token']) && $_POST['csrf_token']===$_SESSION['deploy_csrf']){
    // RUN MANUAL DEPLOY
    $commands = [
        'git stash save "auto-stash before deploy '.date('Y-m-d H:i:s').'"',
        'git pull origin master',
        'git stash pop'
    ];
    $output=[];
    $exit=0;
    foreach($commands as $cmd){
        $full = "cd ".REPO_PATH." && $cmd 2>&1";
        exec($full,$cmdOut,$cmdExit);
        $output[]="$ $cmd";
        $output = array_merge($output,$cmdOut);
        if($cmdExit!==0) $exit=$cmdExit;
    }
    $deployOutput = implode("\n",$output);
    $deployExit = $exit;
    $manualDeployed = true;
    logDeploy("[MANUAL DEPLOY] ".$deployOutput);
}

// ===================== GET LAST PUSH ON ORIGIN =====================
$lastPush = null;
$gitOutput = shell_exec("cd ".REPO_PATH." && git log origin/master -1 --pretty=format:'%h|%an|%ad|%s'");
if($gitOutput){
    list($sha,$author,$date,$message)=explode('|',$gitOutput,4);
    $lastPush = compact('sha','author','date','message');
}

// ===================== HTML UI =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deploy - Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin.css">
</head>
<body>
<div class="admin-wrapper">
    <div class="sidebar">
        <div class="logo"><h2>Admin</h2></div>
        <nav class="admin-nav">
            <a href="/admin/dashboard">Dashboard</a>
            <a href="#" class="active">Deploy</a>
        </nav>
    </div>
    <div class="admin-main">
        <div class="admin-content">
            <h1>🚀 Git Deploy</h1>

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

            <?php if($manualDeployed): 
                $outputHtml = htmlspecialchars($deployOutput);
                $outputHtml = preg_replace('/Already up[ -]to[ -]date/', '<span class="success">Already up-to-date</span>',$outputHtml);
                $outputHtml = preg_replace('/No local changes to save/', '<span class="info-text">No local changes to save</span>',$outputHtml);
                $outputHtml = preg_replace('/CONFLICT/', '<span class="danger">CONFLICT</span>',$outputHtml);
            ?>
                <h2><?= $deployExit===0 ? '✅ Deploy Completed' : '❌ Deploy Failed' ?></h2>
                <pre><?= $outputHtml ?></pre>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
                <button type="submit" class="btn btn-primary">🚀 Deploy Now</button>
            </form>
        </div>
    </div>
</div>
<script>
// Prevent "form resubmission" popup on reload
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
</body>
</html>
