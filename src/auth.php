<?php

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function getAdminHash(): ?string
{
    $hashFile = __DIR__ . '/../.admin-hash';
    if (file_exists($hashFile)) {
        $hash = trim(file_get_contents($hashFile));
        return $hash !== '' ? $hash : null;
    }
    return null;
}

function isAuthEnabled(): bool
{
    return getAdminHash() !== null;
}

function isBruteForceLocked(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['last_login_attempt'] ?? 0;

    if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
        return true;
    }

    if ($attempts >= 5 && (time() - $lastAttempt) >= 300) {
        $_SESSION['login_attempts'] = 0;
    }

    return false;
}

function isLoggedIn(): bool
{
    if (!isAuthEnabled()) {
        return false;
    }

    startSession();

    if (empty($_SESSION['logged_in'])) {
        return false;
    }

    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        logout();
        return false;
    }
    $_SESSION['last_activity'] = time();

    return true;
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'api.php') !== false) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!isAuthEnabled()) {
            header('Location: setup.php');
            exit;
        }
        header('Location: login.php');
        exit;
    }
}

function login(string $password): bool
{
    if (isBruteForceLocked()) {
        return false;
    }

    $hash = getAdminHash();
    if ($hash === null) {
        return false;
    }

    if (password_verify($password, $hash)) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_attempts'] = 0;
        return true;
    }

    startSession();
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_login_attempt'] = time();

    return false;
}

function getCsrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    startSession();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
        exit;
    }
}

function logout(): void
{
    startSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        setcookie(
            session_name(),
            '',
            time() - 42000,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }

    session_destroy();
}
