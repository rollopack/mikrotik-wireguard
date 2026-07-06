<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/../src/auth.php';

class authTest extends TestCase {
    private string $adminHashPath;
    private ?string $originalHash = null;
    private string $testHash;

    private int $reportingLevel;

    public function setUp(): void {
        $this->reportingLevel = error_reporting(E_ALL & ~E_WARNING);
        $this->adminHashPath = __DIR__ . '/../.admin-hash';
        if (file_exists($this->adminHashPath)) {
            $this->originalHash = file_get_contents($this->adminHashPath);
        }
        $this->testHash = password_hash('test_password_123', PASSWORD_BCRYPT);
    }

    public function tearDown(): void {
        error_reporting($this->reportingLevel);
        if ($this->originalHash !== null) {
            file_put_contents($this->adminHashPath, $this->originalHash);
        } else {
            if (file_exists($this->adminHashPath)) {
                unlink($this->adminHashPath);
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    private function createHashFile(): void {
        file_put_contents($this->adminHashPath, $this->testHash);
        clearstatcache(true, $this->adminHashPath);
    }

    private function removeHashFile(): void {
        if (file_exists($this->adminHashPath)) {
            unlink($this->adminHashPath);
        }
        clearstatcache(true, $this->adminHashPath);
    }

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
