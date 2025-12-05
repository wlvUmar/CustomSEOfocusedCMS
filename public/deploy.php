<?php
$cmd = "cd /home/kuplyuta/appliances && git pull 2>&1";
echo "<pre>$cmd\n";
$output = shell_exec($cmd);
echo $output;

