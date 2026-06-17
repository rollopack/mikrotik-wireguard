<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/i18n.php';

$config = require __DIR__ . '/config.php';
$lang = loadLanguage($config['lang'] ?? 'en');

if (!isAuthEnabled()) {
    header('Location: setup.php');
    exit;
}

if (isLoggedIn($config)) {
    header('Location: index.php');
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (login($config, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = t($lang, 'auth.invalid_password');
}
?><!DOCTYPE html>
<html lang="<?php echo $config['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t($lang, 'auth.login_title'); ?> — <?php echo t($lang, 'site.title'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-card h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .login-card p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 0.4rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #3b82f6; }
        .btn {
            width: 100%;
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            background: #3b82f6;
            color: #fff;
            transition: background 0.2s;
        }
        .btn:hover { background: #2563eb; }
        .error-msg {
            background: #7f1d1d;
            color: #fca5a5;
            padding: 0.6rem 0.9rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1><?php echo t($lang, 'auth.login_title'); ?></h1>
        <p><?php echo t($lang, 'site.description'); ?></p>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="password"><?php echo t($lang, 'auth.password_label'); ?></label>
                <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
            </div>
            <button type="submit" class="btn"><?php echo t($lang, 'auth.login_btn'); ?></button>
        </form>
    </div>
</body>
</html>
