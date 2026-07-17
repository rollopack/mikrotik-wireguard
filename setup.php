<?php
// Copyright (C) 2026 Rolland Gabriel (https://github.com/rollopack)
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/i18n.php';

$config = require __DIR__ . '/config.php';
$lang = loadLanguage($config['lang'] ?? 'en');

if (isAuthEnabled()) {
    header('Location: login.php');
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$error = null;
$setupCsrfToken = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['_csrf_token'] ?? '';
    if (!hash_equals($setupCsrfToken, $submittedToken)) {
        $error = t($lang, 'auth.setup_csrf_error');
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (strlen($password) < 8) {
            $error = t($lang, 'auth.setup_minlength');
        } elseif ($password !== $confirm) {
            $error = t($lang, 'auth.setup_mismatch');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            file_put_contents(__DIR__ . '/.admin-hash', $hash);
            header('Location: login.php');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="<?php echo $config['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t($lang, 'auth.setup_title'); ?> — <?php echo t($lang, 'site.title'); ?></title>
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
        .setup-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .setup-card h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .setup-card p {
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
        .info-msg {
            background: #1e3a5f;
            color: #93c5fd;
            padding: 0.6rem 0.9rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1><?php echo t($lang, 'auth.setup_title'); ?></h1>
        <p><?php echo t($lang, 'auth.setup_desc'); ?></p>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="info-msg"><?php echo t($lang, 'auth.setup_info'); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf_token" value="<?php echo $setupCsrfToken; ?>">
            <div class="form-group">
                <label for="password"><?php echo t($lang, 'auth.setup_new_password'); ?></label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm"><?php echo t($lang, 'auth.setup_confirm'); ?></label>
                <input type="password" id="confirm" name="confirm" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn"><?php echo t($lang, 'auth.setup_btn'); ?></button>
        </form>
    </div>
</body>
</html>
