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

    public function testAuthPagesServeFontsLocally(): void {
        foreach (['login.php', 'setup.php'] as $file) {
            $page = file_get_contents(__DIR__ . '/../' . $file);

            $this->assertFalse(str_contains($page, 'fonts.googleapis.com'), "{$file} must not use Google Fonts");
            $this->assertFalse(str_contains($page, 'fonts.gstatic.com'), "{$file} must not use Google Fonts");
            $this->assertTrue(str_contains($page, "font-src 'self'"), "{$file} CSP must allow only local fonts");
            $this->assertTrue(str_contains($page, 'assets/css/fonts.css'), "{$file} must load the local font stylesheet");
        }
    }

    public function testTheFontFilesNeededByTheStylesheetExist(): void {
        $css = file_get_contents(__DIR__ . '/../assets/css/fonts.css');

        $this->assertTrue(str_contains($css, '../fonts/outfit-latin.woff2'));
        $this->assertTrue(str_contains($css, '../fonts/outfit-latin-ext.woff2'));
        $this->assertTrue(is_file(__DIR__ . '/../assets/fonts/outfit-latin.woff2'));
        $this->assertTrue(is_file(__DIR__ . '/../assets/fonts/outfit-latin-ext.woff2'));
        $this->assertTrue(is_file(__DIR__ . '/../assets/fonts/OFL.txt'));
    }

    public function testDashboardDoesNotCallTheRouterWhileRendering(): void {
        $page = file_get_contents(__DIR__ . '/../index.php');

        // Peer data comes from the API after paint, never from a blocking call
        // during the document request.
        $this->assertFalse(str_contains($page, 'ClientFactory::create'));
        $this->assertFalse(str_contains($page, '->getPeers()'));
        $this->assertFalse(str_contains($page, 'initialPeers'));
    }

    public function testDashboardAssetsAreVersionedAndDeferred(): void {
        $page = file_get_contents(__DIR__ . '/../index.php');

        $this->assertTrue(str_contains($page, 'assets/css/app.css?v='));
        $this->assertTrue(str_contains($page, 'assets/js/app.js?v='));
        $this->assertTrue(str_contains($page, 'defer'));
        $this->assertFalse(str_contains($page, 'fonts.googleapis.com'));
        $this->assertFalse(str_contains($page, 'fonts.gstatic.com'));
    }

    public function testDashboardComputesTheAssetVersionOncePerRequest(): void {
        $page = file_get_contents(__DIR__ . '/../index.php');

        $this->assertEquals(1, substr_count($page, 'AppInfo::assetVersion()'));
        $this->assertTrue(str_contains($page, 'json_encode($assetVersionValue)'));
    }

    public function testApiPeerPayloadIsNotCachedByBrowsers(): void {
        $api = file_get_contents(__DIR__ . '/../src/api.php');

        $this->assertTrue(str_contains($api, "header('Cache-Control: no-store')"));
    }
}
