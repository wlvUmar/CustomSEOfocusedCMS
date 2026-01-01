<?php
$cmd = "cd /home/kuplyuta/appliances && \
git stash save 'auto-stash before deploy' 2>&1 && \
git pull origin master 2>&1 && \
git stash pop 2>&1";

$output = shell_exec($cmd);

// Make output HTML-friendly
$outputHtml = htmlspecialchars($output);

// Color-code common messages
$outputHtml = preg_replace('/Already up-to-date/', '<span style="color:green;">Already up-to-date</span>', $outputHtml);
$outputHtml = preg_replace('/No local changes to save/', '<span style="color:orange;">No local changes to save</span>', $outputHtml);
$outputHtml = preg_replace('/Auto-merging/', '<span style="color:blue;">Auto-merging</span>', $outputHtml);
$outputHtml = preg_replace('/CONFLICT/', '<span style="color:red;font-weight:bold;">CONFLICT</span>', $outputHtml);

echo "<pre style='background:#111;color:#eee;padding:15px;border-radius:5px;'>$outputHtml</pre>";


