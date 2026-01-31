<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/core/layout.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-stage" aria-hidden="true">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
            <div class="orb orb-3"></div>
            <div class="grid-wash"></div>
            <div class="sticker sticker-1">NO SLEEP</div>
            <div class="sticker sticker-2">⚡</div>
            <div class="sticker sticker-3">BRB</div>
        </div>
        <div class="login-box login-card">
            <div class="login-eyebrow">Unauthorized Humans Keep Out</div>
            <h2>Admin Login</h2>
            <p class="login-subtitle">You found the secret door. Be cool.</p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?= e($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="POST" action="<?= BASE_URL ?>/admin/login">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus placeholder="the chosen one">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••••">
                </div>
                
                <button type="submit" class="btn btn-primary login-submit">Beam Me In</button>
                <div class="login-hint">Tip: if you’re a bot, pretend to be a toaster.</div>
                <?= csrfField() ?>
            </form>
        </div>
    </div>
</body>
</html>
