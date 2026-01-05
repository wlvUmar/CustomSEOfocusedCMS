<?php

require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');
define('GITHUB_WEBHOOK_SECRET', getenv('GITHUB_WEBHOOK_SECRET') ?: 'your-webhook-secret');
define('WEBHOOK_LOG', '/tmp/github_webhook.log');

// Enhanced logging function
function webhookLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents(WEBHOOK_LOG, $logMessage, FILE_APPEND);
    
    // Also log to security log
    if (function_exists('securityLog')) {
        securityLog($message, $level);
    }
}

function verifyGitHubSignature() {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    webhookLog("Received webhook request");
    webhookLog("Signature header: " . ($signature ? 'present' : 'missing'));
    webhookLog("Payload size: " . strlen($payload) . " bytes");
    
    if (empty($signature)) {
        webhookLog("No signature provided", 'WARNING');
        return false;
    }
    
    $hash = 'sha256=' . hash_hmac('sha256', $payload, GITHUB_WEBHOOK_SECRET);
    $isValid = hash_equals($hash, $signature);
    
    webhookLog("Signature validation: " . ($isValid ? 'SUCCESS' : 'FAILED'), $isValid ? 'INFO' : 'WARNING');
    
    return $isValid;
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
        
        webhookLog("Deployment started by: $caller");
        
        foreach ($commands as $cmd) {
            $output .= "$ " . str_replace(escapeshellarg(REPO_PATH), '[REPO]', $cmd) . "\n";
            $result = shell_exec($cmd);
            $output .= $result . "\n";
            webhookLog("Command executed: " . substr($cmd, 0, 100));
        }
        
        $output .= "\n=== Deployment Completed Successfully ===\n";
        webhookLog("Deployment completed successfully");
        securityLog("Deploy executed by: $caller", 'INFO');
        
        return $output;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $output .= $error . "\n";
        webhookLog("Deploy failed: " . $e->getMessage(), 'ERROR');
        securityLog("Deploy failed ($caller): " . $e->getMessage(), 'ERROR');
        return $output;
    }
}

// Handle GitHub Webhook (NO AUTH - DEV FEATURE)
$isWebhook = ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_GITHUB_EVENT'])
);

if ($isWebhook) {
    // Log all incoming webhook details for debugging
    webhookLog("=== Webhook Request Received ===");
    webhookLog("GitHub Event: " . ($_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'none'));
    webhookLog("GitHub Delivery: " . ($_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? 'none'));
    webhookLog("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none'));
    webhookLog("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // DEV MODE: Skip signature verification - accept any request
    webhookLog("DEV MODE: Skipping signature verification");
    
    // Execute deployment automatically
    webhookLog("Executing auto-deployment from webhook");
    $deployOutput = runDeploy('github-webhook');
    
    http_response_code(200);
    header('Content-Type: application/json');
    
    webhookLog("Webhook processed successfully");
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Deploy triggered by GitHub webhook',
        'timestamp' => date('Y-m-d H:i:s'),
        'output' => $deployOutput
    ]);
    
    exit;
}

// For non-webhook requests, require authentication
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_URL . "/admin/login");
    exit;
}

$deployOutput = '';

// Handle manual POST deploy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'CSRF token validation failed';
        header("Location: " . BASE_URL . "/deploy.php");
        exit;
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

// Check if webhook log exists and get last entries
$webhookLogEntries = '';
if (file_exists(WEBHOOK_LOG)) {
    $logLines = file(WEBHOOK_LOG);
    $webhookLogEntries = implode('', array_slice($logLines, -20)); // Last 20 lines
}

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
    
    <!-- Webhook Log -->
    <?php if (!empty($webhookLogEntries)): ?>
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 15px; color: #303034;">Recent Webhook Activity</h2>
        <div style="background: #111827; color: #10b981; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
            <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars($webhookLogEntries) ?></pre>
        </div>
        <p style="font-size: 12px; color: #6b7280; margin-top: 10px;">
            Log file: <code>/tmp/github_webhook.log</code>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Webhook Configuration -->
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 15px; color: #303034;">GitHub Webhook Configuration</h2>
        
        <div style="padding: 15px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px; margin-bottom: 15px;">
            <strong style="color: #92400e;"><i data-feather="alert-triangle"></i> DEV MODE:</strong> 
            Webhook authentication is DISABLED. Any POST request with <code>X-GitHub-Event</code> header will trigger auto-deploy. 
            <strong>Remove this feature in production!</strong>
        </div>
        
        <div style="padding: 15px; background: #eff6ff; border-left: 3px solid #3b82f6; border-radius: 4px; margin-bottom: 15px;">
            <strong style="color: #1e40af;">Webhook URL:</strong>
            <code style="display: block; margin-top: 8px; padding: 8px; background: white; border-radius: 3px; color: #303034; word-break: break-all;">
                <?= htmlspecialchars(BASE_URL . '/deploy.php') ?>
            </code>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong style="color: #303034;">Simple Setup (No Secret Required):</strong>
            <ol style="margin: 10px 0 0 20px; color: #6b7280; font-size: 13px; line-height: 1.6;">
                <li>Go to GitHub repository Settings → Webhooks</li>
                <li>Click "Add webhook"</li>
                <li>Paste the Webhook URL above</li>
                <li>Set Content type to <code>application/json</code></li>
                <li>Select events: <code>Just the push event</code></li>
                <li><strong>Secret:</strong> Leave empty (not required in dev mode)</li>
                <li>Click "Add webhook"</li>
            </ol>
        </div>
        
        <div style="padding: 10px; background: #d1f4e0; border-left: 3px solid #059669; border-radius: 4px; font-size: 13px; color: #166534;">
            <strong><i data-feather="check"></i> Auto-Deploy Enabled:</strong> Every push to GitHub will automatically pull latest changes to the server.
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
