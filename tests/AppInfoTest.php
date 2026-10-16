<?php

require_once __DIR__ . '/run_tests.php';

class AppInfoTest extends TestCase {
    private string $tmp;

    public function setUp(): void {
        $this->tmp = sys_get_temp_dir() . '/wg-version-' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void {
        if (file_exists($this->tmp)) {
            unlink($this->tmp);
        }
    }

    public function testVersionReadsTrimmedFile(): void {
        file_put_contents($this->tmp, "2.6.0\n");
        $this->assertEquals('2.6.0', AppInfo::version($this->tmp));
    }

    public function testVersionMissingFileIsUnknown(): void {
        $this->assertEquals('unknown', AppInfo::version($this->tmp . '-missing'));
    }

    public function testVersionEmptyFileIsUnknown(): void {
        file_put_contents($this->tmp, "   \n");
        $this->assertEquals('unknown', AppInfo::version($this->tmp));
    }

    public function testDefaultVersionMatchesVersionFile(): void {
        $expected = trim((string)file_get_contents(__DIR__ . '/../VERSION'));
        $this->assertEquals($expected, AppInfo::version());
    }

    public function testRepositoryUrlIsHttps(): void {
        $this->assertTrue(str_starts_with(AppInfo::repositoryUrl(), 'https://'));
    }
}
