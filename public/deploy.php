<?php
$cmd = "cd /home/kuplyuta/appliances && \
git stash save 'auto-stash before deploy' 2>&1 && \
git pull origin master 2>&1 && \
git stash pop 2>&1";

echo "<pre>$cmd\n";
$output = shell_exec($cmd);
echo $output;
