<?php
// path: ./views/admin/deploy.php
require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances'); 
define('GITHUB_REPO_NAME', 'seowebsite');
define('DEPLOY_QUEUE', REPO_PATH.'/deploy_queue.json');
define('DEPLOY_LOG', REPO_PATH.'/deploy.log');

// --------------------
// Helper functions
// --------------------
function logDeploy($msg){
    file_put_contents(DEPLOY_LOG, "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND | LOCK_EX);
}

function loadQueue(){
    if(file_exists(DEPLOY_QUEUE)){
        return json_decode(file_get_contents(DEPLOY_QUEUE), true) ?: [];
    }
    return [];
}

function saveQueue($queue){
    file_put_contents(DEPLOY_QUEUE, json_encode($queue, JSON_PRETTY_PRINT));
}

// --------------------
// Webhook stores push
// --------------------
$isWebhook = isset($_SERVER['HTTP_X_GITHUB_EVENT']);
if($isWebhook && $_SERVER['REQUEST_METHOD']==='POST'){
    $payload = file_get_contents('php://input');
    $data = json_decode($payload,true);
    if($data){
        $repoName = $data['repository']['name'] ?? '';
        $branch = $data['ref'] ?? '';
        if($repoName===GITHUB_REPO_NAME && $branch==='refs/heads/master'){
            $commits = $data['commits'] ?? [];
            if(!empty($commits)){
                $last = end($commits);
                $queue = loadQueue();
                $queue[] = [
                    'sha' => substr($last['id'],0,7),
                    'message' => $last['message'],
                    'timestamp' => $last['timestamp'],
                    'deployed' => false
                ];
                saveQueue($queue);
                logDeploy("[WEBHOOK] Push stored: ".$queue[count($queue)-1]['sha']);
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']);
    exit;
}

// --------------------
// Admin access for manual deploy
// --------------------
if(!isset($_SESSION['user_id'])){
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF
if(!isset($_SESSION['deploy_csrf'])){
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

// --------------------
// Deploy latest push
// --------------------
$queue = loadQueue();
$deployOutput = '';
$deployExit = null;
$manualDeployed = false;

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['csrf_token']??'') === $_SESSION['deploy_csrf']){
    // find first non-deployed push
    $next = null;
    foreach($queue as &$item){
        if(!$item['deployed']){
            $next = &$item;
            break;
        }
    }

    if($next){
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
            $output = array_merge($output,$cmdOut);
            if($cmdExit!==0) $exit = $cmdExit;
        }

        $deployOutput = implode("\n",$output);
        $deployExit = $exit;
        $manualDeployed = true;
        $next['deployed'] = true;
        saveQueue($queue);
        logDeploy("[MANUAL DEPLOY] SHA: {$next['sha']} Output:\n".$deployOutput);
    } else {
        $deployOutput = "No pending push to deploy.";
    }
}

// --------------------
// Page layout
// --------------------
$pageName = 'deploy';
require BASE_PATH.'/views/admin/layout/header.php';
?>

<style>
.deploy-queue {
    font-family: 'Fira Code', monospace;
    background:#1e1e1e; color:#d4d4d4; padding:16px; border-radius:6px;
}
.deploy-queue ul { list-style:none; margin:0; padding:0; }
.deploy-queue li { padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); }
.deploy-queue li:last-child{border-bottom:none;}
.deploy-queue li.deployed{opacity:0.5;}
.deploy-queue li span.sha{color:#569cd6; font-weight:bold;}
.deploy-queue li span.msg{color:#d4d4d4;}
.deploy-output{background:#111; color:#d4d4d4; padding:16px; border-radius:6px; font-family:'Fira Code', monospace; margin-top:20px; white-space:pre-wrap;}
</style>

<div class="page-header"><h1><i data-feather="zap"></i> Deploy Dashboard</h1></div>

<h2>Push Queue</h2>
<div class="deploy-queue">
    <ul>
        <?php foreach($queue as $item): ?>
            <li class="<?= $item['deployed']?'deployed':'' ?>">
                <span class="sha"><?= htmlspecialchars($item['sha']) ?></span> - 
                <span class="msg"><?= htmlspecialchars($item['message']) ?></span>
                <?php if($item['deployed']): ?><em> (deployed)</em><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<form method="POST" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
    <button type="submit" class="btn btn-primary"><i data-feather="zap"></i> Deploy Latest Push</button>
</form>

<?php if($manualDeployed): ?>
    <h3>Deploy Output:</h3>
    <div class="deploy-output"><?= htmlspecialchars($deployOutput) ?></div>
<?php endif; ?>

<?php require BASE_PATH.'/views/admin/layout/footer.php'; ?>
  