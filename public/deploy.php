<?php
// path: ./views/admin/deploy.php

session_start();
require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');
define('DEPLOY_FILE', REPO_PATH.'/deploy.json');

// Ensure deploy.json exists
if (!file_exists(DEPLOY_FILE)) {
    file_put_contents(DEPLOY_FILE, json_encode(['queue'=>[], 'deployOutput'=>'']));
}

// --------------------
// Logging function
// --------------------
function logDeploy($msg) {
    $logFile = REPO_PATH.'/deploy.log';
    $line = "[".date('Y-m-d H:i:s')."] ".$msg.PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Load deploy queue
$deployData = json_decode(file_get_contents(DEPLOY_FILE), true);
$queue = $deployData['queue'] ?? [];
$deployOutput = $deployData['deployOutput'] ?? '';

// --------------------
// Handle GitHub Webhook (queue only, no auth required)
// --------------------
$headers = function_exists('getallheaders') ? getallheaders() : [];
$isWebhook = ($_SERVER['REQUEST_METHOD'] === 'POST') &&
             (!empty($headers['X-GitHub-Event']) || !empty($_SERVER['HTTP_X_GITHUB_EVENT']));

if ($isWebhook) {
    $payload = file_get_contents('php://input');
    logDeploy("[WEBHOOK] Headers: ".json_encode($headers));
    logDeploy("[WEBHOOK] Payload: ".$payload);

    $data = json_decode($payload, true);
    if ($data && isset($data['repository']['name'])) {
        $repoName = $data['repository']['name'];
        $branch = $data['ref'] ?? '';

        $targetBranches = ['refs/heads/master', 'refs/heads/main'];

        if ($repoName === GITHUB_REPO_NAME && in_array($branch, $targetBranches)) {
            $commits = $data['commits'] ?? [];
            foreach ($commits as $commit) {
                $queue[] = [
                    'sha' => substr($commit['id'] ?? '', 0, 7),
                    'message' => $commit['message'] ?? '',
                    'timestamp' => $commit['timestamp'] ?? date('c'),
                    'deployed' => false
                ];
            }

            // Keep only the latest 5 commits
            $queue = array_slice($queue, -5);

            file_put_contents(DEPLOY_FILE, json_encode(['queue'=>$queue,'deployOutput'=>$deployOutput]));
            logDeploy("[WEBHOOK] Push queued from {$repoName} on {$branch}");
        }
    } else {
        logDeploy("[WEBHOOK] Invalid payload or missing repository name.");
    }

    http_response_code(200);
    exit;
}

// --------------------
// Admin authentication for manual deploy
// --------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

// CSRF token for manual deploy
if (!isset($_SESSION['deploy_csrf'])) {
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

// --------------------
// Manual deploy
// --------------------
$manualDeployed = false;
$deployMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $_SESSION['deploy_csrf']) {
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

        $latest['deployed'] = true;
        file_put_contents(DEPLOY_FILE, json_encode(['queue'=>$queue,'deployOutput'=>$deployOutput]));
        logDeploy("[MANUAL DEPLOY] ".$deployOutput);
    } else {
        $deployMessage = "No pending push to deploy.";
    }

    header('Location: deploy.php');
    exit;
}

// --------------------
// AJAX JSON response
// --------------------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'queue' => $queue,
        'deployOutput' => $deployMessage,
        'hasPending' => count(array_filter($queue, fn($q) => !$q['deployed'])) > 0
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
        /* path: ./public/css/admin/deploy.css */

    .deploy-container {
        max-width: 900px;
        margin: 30px auto;
        padding: 0 20px;
        font-family: 'Segoe UI', sans-serif;
        color: #1f2937;
    }

    .deploy-container h1 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.8em;
        margin-bottom: 20px;
        color: #111827;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        color: white;
    }
    .btn-primary:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(59,130,246,0.4);
    }
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .deploy-commits {
        background: #f9fafb;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid #e5e7eb;
    }
    .deploy-commits ul { list-style:none; padding:0; margin:0; }
    .deploy-commits li {
        padding: 10px;
        margin-bottom: 6px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .deploy-commits li.pending { background: #fef3c7; }
    .deploy-commits li.deployed { background: #d1fae5; }
    .deploy-commits li .sha { font-family: monospace; font-weight: bold; margin-right: 6px; }

    .deploy-output {
        background: #111827;
        color: #10b981;
        padding: 15px;
        border-radius: 10px;
        font-family: monospace;
        font-size: 13px;
        max-height: 300px;
        overflow: auto;
        white-space: pre-wrap;
    }

    @media(max-width:768px){
        .deploy-container { padding: 0 15px; margin: 20px auto; }
        .deploy-container h1 { font-size: 1.5em; }
        .deploy-commits, .deploy-output { font-size: 12px; padding: 10px; max-height: 200px; }
        .btn { width: 100%; justify-content: center; }
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
// Live AJAX update
async function refreshQueue(){
    const res = await fetch('deploy.php?ajax=1');
    const data = await res.json();

    const btn = document.getElementById('deploy-btn');
    btn.disabled = !data.hasPending;

    const queueDiv = document.getElementById('deployQueue');
    queueDiv.innerHTML = '<ul>' + data.queue.map(q=>`
        <li class="${q.deployed ? 'deployed' : 'pending'}">
            <span class="sha">${q.sha}</span> - <span class="message">${q.message}</span>
        </li>`).join('') + '</ul>';

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
