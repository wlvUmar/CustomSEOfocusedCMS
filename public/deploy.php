<?php
// path: ./views/admin/deploy.php

session_start();
require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');
define('DEPLOY_FILE', REPO_PATH.'/deploy.json');

// Ensure deploy.json exists
if(!file_exists(DEPLOY_FILE)){
    file_put_contents(DEPLOY_FILE, json_encode(['queue'=>[], 'deployOutput'=>'']));
}

// --------------------
// Logging function
// --------------------
function logDeploy($msg){
    $logFile = REPO_PATH.'/deploy.log';
    $line = "[".date('Y-m-d H:i:s')."] ".$msg.PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Load deploy queue
$deployData = json_decode(file_get_contents(DEPLOY_FILE), true);
$queue = $deployData['queue'] ?? [];
$deployOutput = $deployData['deployOutput'] ?? '';

// --------------------
// Handle GitHub Webhook
// --------------------
$isWebhook = !empty($_SERVER['HTTP_X_GITHUB_EVENT']);
if($isWebhook && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    if($data){
        $repoName = $data['repository']['name'] ?? '';
        $branch = $data['ref'] ?? '';
        if($repoName === GITHUB_REPO_NAME && $branch === 'refs/heads/master'){
            $commits = $data['commits'] ?? [];
            foreach($commits as $commit){
                $queue[] = [
                    'sha' => substr($commit['id'] ?? '', 0, 7),
                    'message' => $commit['message'] ?? '',
                    'timestamp' => $commit['timestamp'] ?? date('c'),
                    'deployed' => false
                ];
            }
            file_put_contents(DEPLOY_FILE, json_encode(['queue'=>$queue,'deployOutput'=>$deployOutput]));
            logDeploy("[WEBHOOK] Push received from {$repoName}");
    }
    }
    http_response_code(200);
    exit; // webhook returns OK
}

// --------------------
// Admin authentication
// --------------------
if(!$isWebhook && !isset($_SESSION['user_id'])){
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF token for manual deploy
if(!isset($_SESSION['deploy_csrf'])){
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

// --------------------
// Manual deploy (approve latest push)
// --------------------
$manualDeployed = false;
$deployMessage = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $_SESSION['deploy_csrf']){
    // Find latest non-deployed push
    $latest = null;
    foreach($queue as &$q){
        if(!$q['deployed']){
            $latest = &$q;
            break;
        }
    }

    if($latest){
        $commands = [
            'git reset --hard',
            'git clean -fd',
            'git pull origin master'
        ];
        $output = [];
        $exit = 0;
        foreach($commands as $cmd){
            $full = "cd ".REPO_PATH." && $cmd 2>&1";
            exec($full, $cmdOut, $cmdExit);
            $output[] = "$ $cmd";
            $output = array_merge($output, $cmdOut);
            if($cmdExit !== 0) $exit = $cmdExit;
        }

        $deployOutput = implode("\n", $output);
        $deployMessage = $deployOutput;
        $manualDeployed = true;

        // mark deployed
        $latest['deployed'] = true;
        file_put_contents(DEPLOY_FILE, json_encode(['queue'=>$queue,'deployOutput'=>$deployOutput]));
        logDeploy("[MANUAL DEPLOY] ".$deployOutput);
    } else {
        $deployMessage = "No pending push to deploy.";
    }
}

// --------------------
// Page layout
// --------------------
$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<style>
.btn {margin-top:5;display: inline-flex;align-items: center;gap: 6px;}
.deploy-container { max-width: 900px; margin:20px auto; }
.deploy-commits { background-color:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:14px; line-height:1.5; padding:16px; border-radius:6px; overflow-x:auto; max-height:400px; }
.deploy-commits ul { list-style:none; padding-left:0; margin:0; }
.deploy-commits li { padding:6px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
.deploy-commits li:last-child { border-bottom:none; }
.deploy-commits li .sha { color:#569cd6; font-weight:bold; }
.deploy-commits li .message { color:#d4d4d4; }
.deploy-commits li.deployed { background-color:#2d2d2d; border-left:4px solid #6a9955; padding-left:12px; }
.deploy-commits li.pending { background-color:#2d2d2d; border-left:4px solid #ffcc00; padding-left:12px; }
.deploy-output { background:#222; color:#fff; padding:12px; border-radius:6px; margin-top:10px; white-space:pre-wrap; max-height:400px; overflow:auto; }
button:disabled { opacity:0.5; cursor:not-allowed; }
</style>

<div class="deploy-container">
    <h1><i data-feather="zap"></i> Git Deploy</h1>

    <div class="deploy-commits" id="deployQueue">
        <ul>
            <?php foreach($queue as $q): ?>
            <li class="<?= $q['deployed'] ? 'deployed' : 'pending' ?>">
                <span class="sha"><?= htmlspecialchars($q['sha']) ?></span> - 
                <span class="message"><?= htmlspecialchars($q['message']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if($manualDeployed): ?>
    <div class="deploy-output" id="deployOutput"><?= htmlspecialchars($deployMessage) ?></div>
    <?php endif; ?>

    <form method="POST" id="deployForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
        <button type="submit" class="btn btn-primary" <?= count(array_filter($queue, fn($q)=>!$q['deployed']))===0?'disabled':'' ?>>
            <i data-feather="zap"></i> Deploy Latest Push
        </button>
    </form>
</div>

<script src="https://unpkg.com/feather-icons"></script>
<script>feather.replace();</script>

<script>
// --------------------
// AJAX live update
// --------------------
function fetchQueue(){
    fetch('deploy.php')
        .then(res => res.text())
        .then(html=>{
            const parser = new DOMParser();
            const doc = parser.parseFromString(html,'text/html');
            const newQueue = doc.querySelector('#deployQueue');
            const newOutput = doc.querySelector('#deployOutput');
            if(newQueue) document.querySelector('#deployQueue').innerHTML = newQueue.innerHTML;
            if(newOutput){
                let out = document.querySelector('#deployOutput');
                if(out) out.innerHTML = newOutput.innerHTML;
                else {
                    const div = document.createElement('div');
                    div.id='deployOutput'; div.className='deploy-output';
                    div.innerHTML = newOutput.innerHTML;
                    document.querySelector('.deploy-container').appendChild(div);
                }
            }
        });
}

// auto-refresh every 5 seconds
setInterval(fetchQueue,5000);

// Optional: prevent full page reload for manual deploy
document.getElementById('deployForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch('deploy.php',{
        method:'POST',
        body:formData
    }).then(fetchQueue);
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
