<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h2>Admin Login</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?= e($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="POST" action="<?= BASE_URL ?>/admin/login">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
                <?= csrfField() ?>
            </form>
        </div>
    </div>
</body>
</html>