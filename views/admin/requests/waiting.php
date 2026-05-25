<?php $lang = $_SESSION['lang'] ?? 'en'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang === 'ru' ? 'Ожидание' : 'Kutilmoqda'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .waiting-container {
            background: white;
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 400px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            margin: 0 auto 30px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }
        
        p {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
        }
        
        .message {
            margin-top: 30px;
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="waiting-container">
        <div class="spinner"></div>
        <h1><?php echo $lang === 'ru' ? 'Ожидание' : 'Kutilmoqda'; ?></h1>
        <p>
            <?php echo $lang === 'ru' 
                ? 'Ваш запрос обрабатывается. Пожалуйста, подождите...' 
                : 'Sizning so\'rovingiz qayta ishlanyapti. Iltimos kuting...'; ?>
        </p>
        <div class="message">
            <?php echo $lang === 'ru' 
                ? 'Эта страница обновится автоматически' 
                : 'Sahifa avtomatik yangilanadi'; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 3 seconds
        setTimeout(() => {
            location.reload();
        }, 3000);
    </script>
</body>
</html>
