<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/../src/auth.php';

class PublicApiAuthTest extends TestCase {
    protected string $tokenPath;
    protected ?string $originalToken = null;
    protected int $reportingLevel;

    public function setUp(): void {
        $this->reportingLevel = error_reporting(E_ALL & ~E_WARNING);
        $this->tokenPath = __DIR__ . '/../.api-token';
        if (file_exists($this->tokenPath)) {
            $this->originalToken = file_get_contents($this->tokenPath);
        }
    }

    public function tearDown(): void {
        error_reporting($this->reportingLevel);
        if ($this->originalToken !== null) {
            file_put_contents($this->tokenPath, $this->originalToken);
        } else {
            if (file_exists($this->tokenPath)) {
                unlink($this->tokenPath);
            }
        }
        clearstatcache(true, $this->tokenPath);
    }

    protected function createTokenFile(string $token): void {
        saveApiToken($token, $this->tokenPath);
        clearstatcache(true, $this->tokenPath);
    }

    protected function removeTokenFile(): void {
        if (file_exists($this->tokenPath)) {
            unlink($this->tokenPath);
        }
        clearstatcache(true, $this->tokenPath);
    }

    public function testGetApiTokenNoFile(): void {
        $this->removeTokenFile();
        $this->assertNull(getApiToken());
    }

    public function testGetApiTokenWithFile(): void {
        $this->createTokenFile('abc123token456');
        $this->assertEquals('abc123token456', getApiToken());
    }

    public function testGetApiTokenTrimsWhitespace(): void {
        $this->createTokenFile("abc123token456\n");
        $this->assertEquals('abc123token456', getApiToken());
    }

    public function testGetApiTokenEmptyFile(): void {
        $this->createTokenFile('');
        $this->assertNull(getApiToken());
    }

    public function testIsApiTokenValidMatching(): void {
        $this->createTokenFile('super-secret-token');
        $this->assertTrue(isApiTokenValid('super-secret-token'));
    }

    public function testIsApiTokenValidWrong(): void {
        $this->createTokenFile('super-secret-token');
        $this->assertFalse(isApiTokenValid('wrong-token'));
    }

    public function testIsApiTokenValidNull(): void {
        $this->createTokenFile('super-secret-token');
        $this->assertFalse(isApiTokenValid(null));
    }

    public function testIsApiTokenValidEmpty(): void {
        $this->createTokenFile('super-secret-token');
        $this->assertFalse(isApiTokenValid(''));
    }

    public function testIsApiTokenValidNoFile(): void {
        $this->removeTokenFile();
        $this->assertFalse(isApiTokenValid('anything'));
    }

    public function testSaveApiTokenRestrictsPermissions(): void {
        $path = sys_get_temp_dir() . '/wg-api-token-' . bin2hex(random_bytes(4));
        try {
            saveApiToken('tok-value', $path);
            $this->assertEquals('tok-value', file_get_contents($path));
            clearstatcache(true, $path);
            // Local filesystem here, where chmod is honored.
            $this->assertEquals(0600, fileperms($path) & 0777);
        } finally {
            @unlink($path);
        }
    }
}
