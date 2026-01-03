<?php

session_start();
require_once __DIR__ . '/../config/init.php';

define('REPO_PATH', '/home/kuplyuta/appliances');
define('GITHUB_REPO_NAME', 'seowebsite');


if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}


$deployOutput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commands = [
        'git reset --hard',
        'git clean -fd',
        'git pull origin master'
    ];

    $output = [];
    foreach ($commands as $cmd) {
        exec("cd ".REPO_PATH." && $cmd 2>&1", $cmdOut, $exit);
        $output[] = "$ $cmd";
        $output = array_merge($output, $cmdOut);
    }

    $deployOutput = implode("\n", $output);
}


$lastCommit = trim(shell_exec("cd ".REPO_PATH." && git log -1 --pretty=format:'%h - %s (%ci)'"));

$pageName = 'deploy';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div style="max-width:600px;margin:40px auto;font-family:sans-serif;color:#111827;">
    <h1 style="margin-bottom:20px;">Git Deploy</h1>

    <div style="margin-bottom:20px;padding:15px;background:#f3f4f6;border-radius:6px;">
        <strong>Last Push:</strong><br>
        <?= htmlspecialchars($lastCommit) ?>
    </div>

    <form method="POST">
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

    <?php if($deployOutput): ?>
    <div style="margin-top:20px;padding:15px;background:#111827;color:#10b981;font-family:monospace;white-space:pre-wrap;border-radius:6px;">
        <?= htmlspecialchars($deployOutput) ?>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
