<?php
$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || empty($payload['ref'])) {
    http_response_code(400);
    die('Invalid webhook payload');
}

$branch = basename($payload['ref']);

$allowedBranches = [
    'master',
    'admin-ajax-test',
];

if (!in_array($branch, $allowedBranches, true)) {
    http_response_code(200);
    die("Branch '$branch' ignored");
}

$repoPath = '/home/kuplyuta/appliances';

$cmd = "
cd $repoPath && \
git fetch origin 2>&1 && \
git checkout $branch 2>&1 && \
git pull origin $branch 2>&1
";

$output = shell_exec($cmd);

file_put_contents(
    __DIR__ . '/deploy.log',
    "[" . date('Y-m-d H:i:s') . "] Branch: $branch\n$output\n\n",
    FILE_APPEND
);

echo "Deployed branch: $branch";
