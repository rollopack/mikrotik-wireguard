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

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

header("Content-Security-Policy: default-src 'self'; font-src 'self' https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:;");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    startSession();
    if (isBruteForceLocked()) {
        $error = t($lang, 'auth.too_many_attempts');
    } else {
        $password = $_POST['password'] ?? '';
        if (login($password)) {
            header('Location: index.php');
            exit;
        }
        $error = t($lang, 'auth.invalid_password');
    }
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
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card">
        <div class="login-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
        </div>
        <h1><?php echo t($lang, 'auth.login_title'); ?></h1>
        <p><?php echo t($lang, 'site.description'); ?></p>

        <hr class="divider">

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
