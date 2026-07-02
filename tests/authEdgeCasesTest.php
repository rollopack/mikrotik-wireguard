<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/../src/auth.php';

class authEdgeCasesTest extends TestCase {
    private string $adminHashPath;
    private ?string $originalHash = null;
    private int $reportingLevel;

    public function setUp(): void {
        $this->reportingLevel = error_reporting(E_ALL & ~E_WARNING);
        $this->adminHashPath = __DIR__ . '/../.admin-hash';
        if (file_exists($this->adminHashPath)) {
            $this->originalHash = file_get_contents($this->adminHashPath);
        }
        // Clear session data but don't destroy — PHP 8.4 CLI rejects session_start() after echo
        $_SESSION = [];
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
        clearstatcache(true, $this->adminHashPath);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    // ── requireAuth tests via subprocess ────────────────────────────

    public function testRequireAuthInApiWhenNotLoggedInReturnsUnauthorized(): void {
        $this->removeHashFile();
        $this->createHashFile('password123');

        $output = $this->runRequireAuthTest('api.php', false);
        $data = json_decode($output, true);
        $this->assertNotNull($data, 'Should return JSON: ' . $output);
        $this->assertFalse($data['success']);
        $this->assertEquals('Unauthorized', $data['error']);
    }

    public function testRequireAuthInApiWhenLoggedInPasses(): void {
        $this->removeHashFile();
        $this->createHashFile('password123');

        $output = $this->runRequireAuthTest('api.php', true);
        $this->assertEquals('AUTH_OK', trim($output), 'Should pass auth');
    }

    public function testRequireRedirectsToSetupWhenNoHash(): void {
        $this->removeHashFile();

        $output = $this->runRequireAuthTest('index.php', false);
        // In CLI mode headers are no-ops, but exit terminates the process
        // We verify exit happens (no AUTH_OK output)
        $this->assertNotEquals('AUTH_OK', trim($output),
            'requireAuth should exit (redirect to setup.php) when not logged in and no hash file');
    }

    public function testRequireRedirectsToLoginWhenNotLoggedIn(): void {
        $this->createHashFile('password123');

        $output = $this->runRequireAuthTest('index.php', false);
        // In CLI mode headers are no-ops, but exit terminates the process
        $this->assertNotEquals('AUTH_OK', trim($output),
            'requireAuth should exit (redirect to login.php) when not logged in');
    }

    // ── requireCsrf tests via subprocess ────────────────────────────

    public function testRequireCsrfWithValidToken(): void {
        $output = $this->runRequireCsrfTest('valid');
        $this->assertEquals('CSRF_OK', trim($output));
    }

    public function testRequireCsrfWithInvalidTokenReturns403(): void {
        $output = $this->runRequireCsrfTest('invalid');
        $data = json_decode($output, true);
        $this->assertNotNull($data);
        $this->assertFalse($data['success']);
        $this->assertTrue(str_contains($data['error'], 'CSRF'));
    }

    public function testRequireCsrfWithMissingTokenReturns403(): void {
        $output = $this->runRequireCsrfTest('missing');
        $data = json_decode($output, true);
        $this->assertNotNull($data);
        $this->assertFalse($data['success']);
    }

    public function testRequireAuthInApiWhenAuthDisabledReturnsUnauthorized(): void {
        $this->removeHashFile();

        $output = $this->runRequireAuthTest('api.php', false);
        $data = json_decode($output, true);
        $this->assertNotNull($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Unauthorized', $data['error']);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function createHashFile(string $password = 'test_password'): void {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        file_put_contents($this->adminHashPath, $hash);
        clearstatcache(true, $this->adminHashPath);
    }

    private function removeHashFile(): void {
        if (file_exists($this->adminHashPath)) {
            unlink($this->adminHashPath);
        }
        clearstatcache(true, $this->adminHashPath);
    }

    private function runRequireAuthTest(string $scriptName, bool $loggedIn): string {
        $root = realpath(__DIR__ . '/..');
        $phpLoggedIn = $loggedIn ? 'true' : 'false';

        $code = <<<PHP
<?php
error_reporting(E_ALL & ~E_WARNING);
require_once '$root/src/auth.php';
\$_SERVER['SCRIPT_NAME'] = '/$scriptName';

session_start();
if ($phpLoggedIn) {
    \$_SESSION['logged_in'] = true;
    \$_SESSION['last_activity'] = time();
} else {
    \$_SESSION = [];
}
session_write_close();

requireAuth([]);
echo 'AUTH_OK';
PHP;

        return $this->runSubprocess($code);
    }

    private function runRequireCsrfTest(string $tokenType): string {
        $root = realpath(__DIR__ . '/..');

        $validToken = 'valid_csrf_token_64_chars_long_abcdefghijklmnopqrstuvwxyz';
        $setHeader = '';
        if ($tokenType === 'valid') {
            $setHeader = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '$validToken';";
        } elseif ($tokenType === 'invalid') {
            $setHeader = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid_token';";
        }

        $code = <<<PHP
<?php
error_reporting(E_ALL & ~E_WARNING);
require_once '$root/src/auth.php';

session_start();
\$_SESSION['csrf_token'] = '$validToken';
session_write_close();

$setHeader

requireCsrf();
echo 'CSRF_OK';
PHP;

        return $this->runSubprocess($code);
    }

    private function runSubprocess(string $code): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'auth_test_');
        file_put_contents($tmpFile, $code);

        $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
        unlink($tmpFile);

        return $output ?? '';
    }
}
