<?php
require_once __DIR__ . '/../config/init.php';
define('REPO_PATH', '/home/kuplyuta/appliances'); 
define('DEPLOY_QUEUE', REPO_PATH.'/deploy_queue.json');

if(!isset($_SESSION['user_id'])){
    http_response_code(403);
    die('Access Denied. Please <a href="/admin/login">login</a> first.');
}

if(!isset($_SESSION['deploy_csrf'])){
    $_SESSION['deploy_csrf'] = bin2hex(random_bytes(32));
}

$pageName = 'deploy';
require BASE_PATH.'/views/admin/layout/header.php';
?>

<style>
.deploy-queue { font-family:'Fira Code', monospace; background:#1e1e1e; color:#d4d4d4; padding:16px; border-radius:6px;}
.deploy-queue ul { list-style:none; margin:0; padding:0; }
.deploy-queue li { padding:6px; border-bottom:1px solid rgba(255,255,255,0.05); }
.deploy-queue li:last-child{border-bottom:none;}
.deploy-queue li.deployed{opacity:0.5;}
.deploy-queue li span.sha{color:#569cd6; font-weight:bold;}
.deploy-queue li span.msg{color:#d4d4d4;}
.deploy-output{background:#111; color:#d4d4d4; padding:16px; border-radius:6px; font-family:'Fira Code', monospace; margin-top:20px; white-space:pre-wrap;}
</style>

<div class="page-header"><h1><i data-feather="zap"></i> Deploy Dashboard</h1></div>

<h2>Push Queue</h2>
<div class="deploy-queue">
    <ul id="deployQueue"></ul>
</div>

<button id="deployBtn" class="btn btn-primary" style="margin-top:10px;">
    <i data-feather="zap"></i> Deploy Latest Push
</button>

<h3>Deploy Output:</h3>
<pre id="deployOutput" class="deploy-output"></pre>

<script>
const csrfToken = "<?= $_SESSION['deploy_csrf'] ?>";

function fetchQueue(){
    fetch('/views/admin/deploy-api.php')
    .then(res => res.json())
    .then(data => {
        const ul = document.getElementById('deployQueue');
        ul.innerHTML = '';
        data.queue.forEach(item => {
            const li = document.createElement('li');
            if(item.deployed) li.classList.add('deployed');
            li.innerHTML = `<span class="sha">${item.sha}</span> - <span class="msg">${item.message}</span>` + (item.deployed?' <em>(deployed)</em>':'');
            ul.appendChild(li);
        });
    });
}

// Deploy latest via AJAX
document.getElementById('deployBtn').addEventListener('click',()=>{
    fetch('/views/admin/deploy-api.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'csrf_token='+csrfToken
    })
    .then(res=>res.json())
    .then(data=>{
        // update queue
        const ul = document.getElementById('deployQueue');
        ul.innerHTML = '';
        data.queue.forEach(item=>{
            const li = document.createElement('li');
            if(item.deployed) li.classList.add('deployed');
            li.innerHTML = `<span class="sha">${item.sha}</span> - <span class="msg">${item.message}</span>` + (item.deployed?' <em>(deployed)</em>':'');
            ul.appendChild(li);
        });
        document.getElementById('deployOutput').textContent = data.deployOutput;
    });
});

// Poll every 5s
setInterval(fetchQueue,5000);
fetchQueue();
</script>
