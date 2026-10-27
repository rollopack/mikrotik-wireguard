<?php

require_once __DIR__ . '/run_tests.php';

/**
 * Covers the on-demand modal delivery introduced to keep the modal markup out
 * of the initial page weight. The endpoint itself needs a session, so these
 * tests assert its contract by reading the source.
 */
class ModalFragmentTest extends TestCase {
    private const FRAGMENTS = [
        'add',
        'edit',
        'delete',
        'confirm',
        'export',
        'export-vpn-ips',
        'about',
    ];

    public function testEveryFragmentIsDeclaredInTheWhitelist(): void {
        $endpoint = file_get_contents(__DIR__ . '/../src/modal.php');

        foreach (self::FRAGMENTS as $fragment) {
            $this->assertTrue(
                str_contains($endpoint, "'{$fragment}' => '{$fragment}'"),
                "Fragment '{$fragment}' is missing from MODAL_FRAGMENTS"
            );
        }
    }

    public function testEachFragmentFileRendersASingleBackdrop(): void {
        foreach (self::FRAGMENTS as $fragment) {
            $file = __DIR__ . '/../src/views/modals/' . $fragment . '.php';
            $this->assertTrue(is_file($file), "Missing fragment file: {$fragment}.php");

            $markup = file_get_contents($file);
            $this->assertEquals(1, substr_count($markup, 'class="modal-backdrop"'),
                "Fragment '{$fragment}' must contain exactly one modal backdrop");
            $this->assertEquals(1, substr_count($markup, 'aria-hidden="true"'),
                "Fragment '{$fragment}' must start hidden");
        }
    }

    public function testFragmentsWithoutABackdropInThePage(): void {
        $page = file_get_contents(__DIR__ . '/../index.php');

        // index.php only provides the mount point.
        $this->assertEquals(0, substr_count($page, 'class="modal-backdrop"'));
        $this->assertTrue(str_contains($page, '<div id="modalHost"></div>'));
    }

    public function testEndpointRequiresAuthenticationAndRejectsUnknownTypes(): void {
        $endpoint = file_get_contents(__DIR__ . '/../src/modal.php');

        $this->assertTrue(str_contains($endpoint, 'require_once __DIR__ . \'/auth.php\''));
        $this->assertTrue(str_contains($endpoint, 'isAuthEnabled()'));
        $this->assertTrue(str_contains($endpoint, 'isLoggedIn()'));
        $this->assertTrue(str_contains($endpoint, 'http_response_code(401)'));
        $this->assertTrue(str_contains($endpoint, 'http_response_code(400)'));
        $this->assertTrue(str_contains($endpoint, 'isset(MODAL_FRAGMENTS[$type])'));
    }

    public function testEndpointValidatesTheServerParameter(): void {
        $endpoint = file_get_contents(__DIR__ . '/../src/modal.php');

        $this->assertTrue(str_contains($endpoint, 'ConfigManager::validateRequestedServer'));
    }

    public function testEndpointSendsNoStoreAndNoSniffHeaders(): void {
        $endpoint = file_get_contents(__DIR__ . '/../src/modal.php');

        $this->assertTrue(str_contains($endpoint, "header('Cache-Control: no-store')"));
        $this->assertTrue(str_contains($endpoint, "header('X-Content-Type-Options: nosniff')"));
        $this->assertTrue(str_contains($endpoint, "header('Content-Type: text/html; charset=utf-8')"));
    }

    public function testFrontendLoadsFragmentsOnceAndReportsFailures(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        foreach (self::FRAGMENTS as $fragment) {
            $backdrop = [
                'add' => 'addModalBackdrop',
                'edit' => 'editModalBackdrop',
                'delete' => 'deleteModalBackdrop',
                'confirm' => 'confirmModalBackdrop',
                'export' => 'exportModalBackdrop',
                'export-vpn-ips' => 'exportVpnIpsModalBackdrop',
                'about' => 'aboutModalBackdrop',
            ][$fragment];

            $pattern = "/['\"]?" . preg_quote($fragment, '/') . "['\"]?: '" . preg_quote($backdrop, '/') . "'/";
            $this->assertTrue(preg_match($pattern, $script) === 1,
                "MODAL_BACKDROPS must map '{$fragment}' to '{$backdrop}'");
        }

        $this->assertTrue(str_contains($script, 'src/modal.php?type='));
        $this->assertTrue(str_contains($script, 'loadedModals.has(type)'));
        $this->assertTrue(str_contains($script, "t('js.modal_load_error')"));
    }

    public function testModalAuthenticationAvoidsDuplicateChecksOnFirstLoad(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $this->assertTrue(str_contains($script, 'async function ensureModalWithSession(type)'));
        $this->assertTrue(str_contains($script, 'loadedModals.has(type) && !(await checkSession())'));
        $this->assertTrue(str_contains($script, 'return ensureModal(type)'));
    }
}
