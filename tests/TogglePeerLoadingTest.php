<?php

require_once __DIR__ . '/run_tests.php';

class TogglePeerLoadingTest extends TestCase {
    private function functionBody(string $script, string $name): string {
        $start = strpos($script, 'function ' . $name . '(');
        $this->assertTrue($start !== false, "Function {$name} not found in app.js");

        $end = strpos($script, "\n}", $start);
        $this->assertTrue($end !== false, "Function {$name} has no closing brace");

        return substr($script, $start, $end - $start);
    }

    public function testToggleButtonIsPassedToTheActionImmediately(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        $this->assertTrue(str_contains(
            $script,
            "case 'toggle-peer': togglePeer(el.dataset.id, el.dataset.disabled === 'true', el); break;"
        ));
    }

    public function testEnableShowsSpinnerBeforeSessionCheck(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'togglePeer');
        $spinnerPosition = strpos($body, 'setPeerToggleLoading(button, true)');
        $sessionPosition = strpos($body, 'await checkSession()');

        $this->assertTrue($spinnerPosition !== false, 'Enable action must show the spinner');
        $this->assertTrue($sessionPosition !== false, 'Toggle must still check the session');
        $this->assertTrue($spinnerPosition < $sessionPosition,
            'Enable spinner must be shown before the asynchronous session check');
    }

    public function testDisableShowsSpinnerOnlyWhenConfirmed(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'togglePeer');

        $this->assertTrue(str_contains($body, 'openConfirmModal'));
        $this->assertTrue(str_contains($body, 'setPeerToggleLoading(button, true)'));
        $this->assertTrue(str_contains($body, 'await apiPost(\'toggle_peer\''));
    }

    public function testToggleRestoresTheButtonAfterFailure(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'togglePeer');

        $this->assertTrue(str_contains($body, 'setPeerToggleLoading(button, false)'));
        $this->assertTrue(str_contains($script, 'function setPeerToggleLoading(button, isLoading)'));
        $this->assertTrue(str_contains($script, 'spinner-inline'));
    }
}
