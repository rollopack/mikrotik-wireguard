<?php

require_once __DIR__ . '/run_tests.php';

class HttpHardeningTest extends TestCase {
    public function testSetupPageSendsContentSecurityPolicy(): void {
        $page = file_get_contents(__DIR__ . '/../setup.php');

        $this->assertTrue(str_contains($page, 'Content-Security-Policy'));
        $this->assertTrue(str_contains($page, "script-src 'none'"));
    }

    public function testLoginPageDoesNotAllowInlineScripts(): void {
        $login = file_get_contents(__DIR__ . '/../login.php');

        $this->assertTrue(str_contains($login, "script-src 'none'"));
        $this->assertFalse(str_contains($login, "script-src 'self' 'unsafe-inline'"));
    }

    public function testApiRejectsUnknownServerInsteadOfFallingBack(): void {
        $api = file_get_contents(__DIR__ . '/../src/api.php');

        $this->assertTrue(str_contains($api, 'ConfigManager::validateRequestedServer'));
    }

    public function testPublicApiUsesTheSameCentralServerValidation(): void {
        $publicApi = file_get_contents(__DIR__ . '/../src/public-api.php');

        $this->assertTrue(str_contains($publicApi, 'ConfigManager::validateRequestedServer'));
        $this->assertFalse(
            str_contains($publicApi, 'ConfigManager::serverExists'),
            'public-api.php should delegate to ConfigManager::validateRequestedServer, not re-check serverExists'
        );
    }
}
