<?php

require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');
define('GITHUB_WEBHOOK_SECRET', getenv('GITHUB_WEBHOOK_SECRET') ?: 'your-webhook-secret');


function verifyGitHubSignature() {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    if (empty($signature)) {
        return false;
    }
    
    $hash = 'sha256=' . hash_hmac('sha256', $payload, GITHUB_WEBHOOK_SECRET);
    return hash_equals($hash, $signature);
}


function runDeploy($caller = 'manual') {
    $output = '';
    
    try {
        $commands = [
            "cd " . escapeshellarg(REPO_PATH) . " && pwd",
            "cd " . escapeshellarg(REPO_PATH) . " && git fetch origin main 2>&1",
            "cd " . escapeshellarg(REPO_PATH) . " && git reset --hard origin/main 2>&1",
            "cd " . escapeshellarg(REPO_PATH) . " && git log -1 --oneline 2>&1"
        ];
        
        $output .= "=== Deployment Started (" . date('Y-m-d H:i:s') . ") ===\n";
        $output .= "Caller: " . $caller . "\n\n";
        
        foreach ($commands as $cmd) {
            $output .= "$ " . str_replace(escapeshellarg(REPO_PATH), '[REPO]', $cmd) . "\n";
            $result = shell_exec($cmd);
            $output .= $result . "\n";
        }
        
        $output .= "\n=== Deployment Completed Successfully ===\n";
        securityLog("Deploy executed by: $caller", 'INFO');
        
        return $output;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $output .= $error . "\n";
        securityLog("Deploy failed ($caller): " . $e->getMessage(), 'ERROR');
        return $output;
    }
}

// Handle GitHub Webhook (no auth required)
$isWebhook = ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_GITHUB_EVENT'])
);

if ($isWebhook) {
    // Verify webhook signature
    if (!verifyGitHubSignature()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        securityLog("Invalid GitHub webhook signature attempt", 'WARNING');
        exit;
    }
    
    // Execute deployment
    $deployOutput = runDeploy('github-webhook');
    
    http_response_code(200);
    header('Content-Type: application/json');
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Deploy triggered by GitHub webhook',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    exit;
}

// For non-webhook requests, require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

$deployOutput = '';

// Handle manual POST deploy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    $deployOutput = runDeploy('manual-admin');
}

// Get last commit info
$lastCommit = trim(shell_exec(
    "cd " . escapeshellarg(REPO_PATH) . " && git log -1 --pretty=format:'%h - %s (%ci)' 2>&1"
));

$deploymentStatus = trim(shell_exec(
    "cd " . escapeshellarg(REPO_PATH) . " && git status --short 2>&1"
));

$currentBranch = trim(shell_exec(
    "cd " . escapeshellarg(REPO_PATH) . " && git rev-parse --abbrev-ref HEAD 2>&1"
));

$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="zap"></i> Git Deployment</h1>
</div>

<div style="max-width:800px;margin:0 auto;">
    
    <!-- Deployment Status -->
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 15px; color: #303034;">Repository Status</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div style="padding: 10px; background: #f3f4f6; border-radius: 4px;">
                <strong style="color: #6b7280;">Current Branch:</strong><br>
                <code style="color: #303034;"><?= htmlspecialchars($currentBranch) ?></code>
            </div>
            <div style="padding: 10px; background: #f3f4f6; border-radius: 4px;">
                <strong style="color: #6b7280;">Last Commit:</strong><br>
                <code style="color: #303034;"><?= htmlspecialchars($lastCommit) ?></code>
            </div>
        </div>
        
        <?php if (trim($deploymentStatus)): ?>
            <div style="padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; margin-bottom: 15px; border-radius: 4px;">
                <strong style="color: #92400e;"><i data-feather="alert-triangle"></i> Uncommitted Changes Detected</strong>
                <pre style="margin-top: 8px; font-size: 12px; color: #111827; overflow-x: auto;"><?= htmlspecialchars($deploymentStatus) ?></pre>
            </div>
        <?php else: ?>
            <div style="padding: 10px; background: #d1f4e0; border-left: 3px solid #059669; border-radius: 4px;">
                <strong style="color: #166534;"><i data-feather="check"></i> Working Directory Clean</strong>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Deploy Form -->
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 15px; color: #303034;">Manual Deployment</h2>
        
        <form method="POST" style="margin-bottom: 15px;">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                <i data-feather="send"></i> Deploy Latest
            </button>
        </form>
        
        <p style="font-size: 13px; color: #6b7280; margin: 0;">
            <i data-feather="info"></i> Manual deployment will pull latest changes from origin/main branch.
        </p>
    </div>
    
    <!-- Webhook Configuration -->
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 15px; color: #303034;">GitHub Webhook Configuration</h2>
        
        <div style="padding: 15px; background: #eff6ff; border-left: 3px solid #3b82f6; border-radius: 4px; margin-bottom: 15px;">
            <strong style="color: #1e40af;">Webhook URL:</strong>
            <code style="display: block; margin-top: 8px; padding: 8px; background: white; border-radius: 3px; color: #303034; word-break: break-all;">
                <?= htmlspecialchars(BASE_URL . '/deploy.php') ?>
            </code>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong style="color: #303034;">Setup Instructions:</strong>
            <ol style="margin: 10px 0 0 20px; color: #6b7280; font-size: 13px; line-height: 1.6;">
                <li>Go to GitHub repository Settings → Webhooks</li>
                <li>Click "Add webhook"</li>
                <li>Paste the Webhook URL above</li>
                <li>Set Content type to <code>application/json</code></li>
                <li>Select events: <code>Push events</code></li>
                <li>Set Secret to: <code><?= htmlspecialchars(GITHUB_WEBHOOK_SECRET) ?></code></li>
                <li>Click "Add webhook"</li>
            </ol>
        </div>
        
        <div style="padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px; font-size: 13px; color: #92400e;">
            <strong><i data-feather="alert-triangle"></i> Security Note:</strong> Set the webhook secret as an environment variable <code>GITHUB_WEBHOOK_SECRET</code>. Update <code>.env</code> file with a strong secret key.
        </div>
    </div>
    
    <!-- Deployment Output -->
    <?php if ($deployOutput): ?>
        <div style="background: white; padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-bottom: 15px; color: #303034;">Deployment Output</h2>
            <div style="
                background: #111827;
                color: #10b981;
                padding: 15px;
                border-radius: 4px;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
                font-size: 12px;
                line-height: 1.4;
                white-space: pre-wrap;
                word-wrap: break-word;
                overflow-x: auto;
            ">
<?= htmlspecialchars($deployOutput) ?>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
