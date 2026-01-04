<?php

require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');


$isWebhook = ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_GITHUB_EVENT'])
);

if ($isWebhook) {
    $payload = file_get_contents('php://input');
    $deployOutput = runDeploy();
    
    http_response_code(200);
    header('Content-Type: application/json');

    echo json_encode([
        'status' => 'ok',
        'message' => 'Deploy triggered by GitHub webhook'
    ]);

    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

$deployOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    $deployOutput = runDeploy();
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit; 
}

$lastCommit = trim(shell_exec(
    "cd " . escapeshellarg(REPO_PATH) . " && git log -1 --pretty=format:'%h - %s (%ci)'"
));

$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div style="max-width:600px;margin:40px auto;font-family:sans-serif;color:#111827;">
    <h1 style="margin-bottom:20px;">Git Deploy</h1>

    <div style="margin-bottom:20px;padding:15px;background:#f3f4f6;border-radius:6px;">
        <strong>Last Commit:</strong><br>
        <?= htmlspecialchars($lastCommit) ?>
    </div>

    <form method="POST">
        <?= csrfField() ?>
        <button type="submit" style="
            padding:10px 20px;
            border:none;
            border-radius:6px;
            background:#3b82f6;
            color:white;
            font-weight:600;
            cursor:pointer;
        ">
            Deploy Latest
        </button>
    </form>

    <?php if ($deployOutput): ?>
        <div style="
            margin-top:20px;
            padding:15px;
            background:#111827;
            color:#10b981;
            font-family:monospace;
            white-space:pre-wrap;
            border-radius:6px;
        ">
            <?= htmlspecialchars($deployOutput) ?>
        </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
