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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $_SESSION['deploy_csrf']) {
    // Find latest non-deployed push
    $latest = null;
    foreach ($queue as &$q) {
        if (!$q['deployed']) {
            $latest = &$q;
            break;
        }
    }

    if ($latest) {
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
        $deployMessage = $deployOutput;
        $manualDeployed = true;

        // mark deployed
        $latest['deployed'] = true;
        file_put_contents(DEPLOY_FILE, json_encode(['queue'=>$queue,'deployOutput'=>$deployOutput]));
        logDeploy("[MANUAL DEPLOY] ".$deployOutput);
    } else {
        $deployMessage = "No pending push to deploy.";
    }

    header('Location: deploy.php'); // optional: append ?deployed=1
    exit;
}

// --------------------
// AJAX JSON response
// --------------------
if(isset($_GET['ajax'])){
    header('Content-Type: application/json');
    echo json_encode([
        'queue' => $queue,
        'deployOutput' => $deployMessage,
        'hasPending' => count(array_filter($queue, fn($q)=>!$q['deployed']))>0
    ]);
    exit;
}

// --------------------
// Page layout
// --------------------
$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<style>
/* Modern Deploy Page Styling */
.btn { 
    margin-top: 5px; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn:disabled { 
    opacity: 0.5; 
    cursor: not-allowed;
    transform: none !important;
}

.deploy-container { 
    max-width: 1000px; 
    margin: 30px auto; 
    padding: 0 20px;
}

.deploy-container h1 {
    font-size: 2em;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #1f2937;
}

.deploy-container h1 i {
    color: #3b82f6;
}

/* Commit List - Modern Card Design */
.deploy-commits { 
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #e2e8f0;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    max-height: 450px;
    overflow-y: auto;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.1);
}

.deploy-commits::-webkit-scrollbar {
    width: 8px;
}

.deploy-commits::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 4px;
}

.deploy-commits::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.5);
    border-radius: 4px;
}

.deploy-commits::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.7);
}

.deploy-commits ul { 
    list-style: none; 
    padding-left: 0; 
    margin: 0; 
}

.deploy-commits li { 
    padding: 12px 16px;
    margin-bottom: 8px;
    border-radius: 8px;
    background: rgba(255,255,255,0.03);
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.deploy-commits li:hover {
    background: rgba(255,255,255,0.06);
    transform: translateX(4px);
}

.deploy-commits li:last-child { 
    margin-bottom: 0;
}

.deploy-commits li .sha { 
    color: #60a5fa;
    font-weight: bold;
    font-size: 13px;
    font-family: monospace;
    background: rgba(59, 130, 246, 0.15);
    padding: 2px 8px;
    border-radius: 4px;
    display: inline-block;
    margin-right: 8px;
}

.deploy-commits li .message { 
    color: #e2e8f0;
    font-size: 14px;
}

.deploy-commits li.deployed { 
    background: rgba(16, 185, 129, 0.1);
    border-left-color: #10b981;
}

.deploy-commits li.deployed .sha {
    color: #10b981;
    background: rgba(16, 185, 129, 0.15);
}

.deploy-commits li.pending { 
    background: rgba(251, 191, 36, 0.1);
    border-left-color: #fbbf24;
    animation: pulse-pending 2s ease-in-out infinite;
}

.deploy-commits li.pending .sha {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.15);
}

@keyframes pulse-pending {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* Deploy Output - Terminal Style */
.deploy-output { 
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    color: #10b981;
    padding: 20px;
    border-radius: 12px;
    margin-top: 15px;
    margin-bottom: 20px;
    white-space: pre-wrap;
    max-height: 400px;
    overflow: auto;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.deploy-output:empty::before {
    content: 'No deployment output yet. Click "Deploy Latest Push" to start.';
    color: #6b7280;
    font-style: italic;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}

.status-deployed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

/* Form Styling */
#deployForm {
    margin-top: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .deploy-container {
        padding: 0 15px;
        margin: 20px auto;
    }
    
    .deploy-container h1 {
        font-size: 1.5em;
    }
    
    .deploy-commits,
    .deploy-output {
        font-size: 12px;
        padding: 15px;
        max-height: 300px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

.btn.loading {
    position: relative;
    pointer-events: none;
}

.btn.loading::after {
    content: '';
    position: absolute;
    right: 12px;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
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

    <div class="deploy-output" id="deployOutput"><?= htmlspecialchars($deployMessage) ?></div>

    <form method="POST" id="deployForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['deploy_csrf']) ?>">
        <button id="deploy-btn" type="submit" class="btn btn-primary" disabled>
            <i data-feather="zap"></i> Deploy Latest Push
        </button>
    </form>
</div>

<script src="https://unpkg.com/feather-icons"></script>
<script>feather.replace();</script>

<script>
// --------------------
// Live AJAX update
// --------------------
async function refreshQueue(){
    const res = await fetch('deploy.php?ajax=1');
    const data = await res.json();

    // Update button
    const btn = document.getElementById('deploy-btn');
    btn.disabled = !data.hasPending;

    // Update push list
    const queueDiv = document.getElementById('deployQueue');
    queueDiv.innerHTML = '<ul>' + data.queue.map(q=>`
        <li class="${q.deployed ? 'deployed' : 'pending'}">
            <span class="sha">${q.sha}</span> - <span class="message">${q.message}</span>
        </li>`).join('') + '</ul>';

    // Update deploy output
    const outDiv = document.getElementById('deployOutput');
    outDiv.innerHTML = data.deployOutput;
}

// initial load + interval
refreshQueue();
setInterval(refreshQueue, 3000);

// handle manual deploy without page reload
document.getElementById('deployForm').addEventListener('submit', async e=>{
    e.preventDefault();
    const formData = new FormData(e.target);
    await fetch('deploy.php', {method:'POST', body:formData});
    refreshQueue();
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
 