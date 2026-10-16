<?php

require_once __DIR__ . '/AuthTestCaseBase.php';

class authTest extends AuthTestCase {

    public function testGetAdminHashNoFile(): void {
        $this->removeHashFile();
        $this->assertNull(getAdminHash());
    }

    public function testGetAdminHashWithFile(): void {
        $this->createHashFile();
        $hash = getAdminHash();
        $this->assertNotNull($hash);
        $this->assertTrue(password_verify('test_password_123', $hash));
    }

    public function testGetAdminHashEmptyFile(): void {
        $this->createHashFile();
        file_put_contents($this->adminHashPath, '');
        clearstatcache(true, $this->adminHashPath);
        $this->assertNull(getAdminHash());
    }

    public function testIsAuthEnabledNoFile(): void {
        $this->removeHashFile();
        $this->assertFalse(isAuthEnabled());
    }

    public function testIsAuthEnabledWithFile(): void {
        $this->createHashFile();
        $this->assertTrue(isAuthEnabled());
    }

    public function testSaveAdminHashSetsRestrictivePermissions(): void {
        $path = sys_get_temp_dir() . '/wg-admin-hash-' . bin2hex(random_bytes(4));
        saveAdminHash('hash-value', $path);
        try {
            $this->assertEquals('hash-value', file_get_contents($path));
            clearstatcache(true, $path);
            // The file is written to a local filesystem here, where chmod is honored.
            $this->assertEquals(0600, fileperms($path) & 0777);
        } finally {
            @unlink($path);
        }
    }

    public function testSaveAdminHashDefaultPathIsReadable(): void {
        saveAdminHash('default-path-hash');
        clearstatcache(true, $this->adminHashPath);
        $this->assertEquals('default-path-hash', getAdminHash());
    }

    public function testLoginWithCorrectPassword(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $result = login('test_password_123');
        $this->assertTrue($result);
        $this->assertTrue($_SESSION['logged_in'] ?? false);
    }

    public function testLoginWithWrongPassword(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $result = login('wrong_password');
        $this->assertFalse($result);
    }

    public function testLoginWhenNoHash(): void {
        $this->removeHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $result = login('test_password_123');
        $this->assertFalse($result);
    }

    public function testIsLoggedInWhenNotLoggedIn(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $this->assertFalse(isLoggedIn());
    }

    public function testIsLoggedInWhenAuthDisabled(): void {
        $this->removeHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = ['logged_in' => true];
        $this->assertFalse(isLoggedIn());
    }

    public function testIsLoggedInWhenLoggedIn(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        login('test_password_123');
        $this->assertTrue(isLoggedIn());
    }

    public function testLogout(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        login('test_password_123');
        $this->assertTrue(isLoggedIn());
        logout();
        $this->assertFalse(isLoggedIn());
    }

    public function testBruteForceLockout(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $this->assertFalse(isBruteForceLocked(), 'Should not be locked initially');

        // 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            login('wrong_password');
        }

        $this->assertTrue(isBruteForceLocked(), 'Should be locked after 5 failed attempts');
        $this->assertFalse(login('test_password_123'), 'Correct password should fail when locked');
        $this->assertEquals(5, $_SESSION['login_attempts']);

        // Simulate lockout expiry
        $_SESSION['last_login_attempt'] = time() - 301;
        $this->assertFalse(isBruteForceLocked(), 'Should not be locked after timeout');
    }

    public function testBruteForceCounterResetOnSuccess(): void {
        $this->createHashFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // 3 failed attempts, then success
        login('wrong_password');
        login('wrong_password');
        login('wrong_password');
        $this->assertEquals(3, $_SESSION['login_attempts']);

        login('test_password_123');
        $this->assertEquals(0, $_SESSION['login_attempts'], 'Counter should reset on successful login');
    }

    public function testGetCsrfTokenGeneratesToken(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $token = getCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testGetCsrfTokenReturnsExistingToken(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $token1 = getCsrfToken();
        $token2 = getCsrfToken();
        $this->assertEquals($token1, $token2);
    }

    public function testValidateCsrfTokenValid(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $token = getCsrfToken();
        $this->assertTrue(validateCsrfToken($token));
    }

    public function testValidateCsrfTokenInvalid(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        getCsrfToken();
        $this->assertFalse(validateCsrfToken('invalid-token'));
    }

    public function testValidateCsrfTokenNull(): void {
        $this->assertFalse(validateCsrfToken(null));
    }

    public function testValidateCsrfTokenEmpty(): void {
        $this->assertFalse(validateCsrfToken(''));
    }
}
