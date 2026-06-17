<?php

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
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

function isLoggedIn(array $config): bool
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

function requireAuth(array $config): void
{
    if (!isLoggedIn($config)) {
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

function login(array $config, string $password): bool
{
    $hash = getAdminHash();
    if ($hash === null) {
        return false;
    }

    if (password_verify($password, $hash)) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        return true;
    }

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
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
