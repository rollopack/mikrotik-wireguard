<?php

require_once __DIR__ . '/run_tests.php';

class EditPeerSavingFeedbackTest extends TestCase {
    private function functionBody(string $script, string $name): string {
        $start = strpos($script, 'function ' . $name . '(');
        $this->assertTrue($start !== false, "Function {$name} not found in app.js");

        $end = strpos($script, "\n}", $start);
        $this->assertTrue($end !== false, "Function {$name} has no closing brace");

        return substr($script, $start, $end - $start);
    }

    public function testEditSubmitShowsAndRestoresSavingStateAroundTheApiCall(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'submitEditPeer');
        $savingPosition = strpos($body, 'setEditPeerSaving(form, true)');
        $requestPosition = strpos($body, "apiPost('update_peer'");
        $restoredPosition = strpos($body, 'setEditPeerSaving(form, false)', $requestPosition ?: 0);

        $this->assertTrue($savingPosition !== false && $savingPosition < $requestPosition,
            'The form must show progress before awaiting the rename API');
        $this->assertTrue(str_contains($body, "form.dataset.saving === 'true'"),
            'Repeated submissions must be ignored while a rename is in progress');
        $this->assertTrue(str_contains($body, 'finally {'));
        $this->assertTrue($restoredPosition !== false && $restoredPosition > $requestPosition,
            'The form must restore its submit button after the API attempt');
    }

    public function testEditSavingStateIsVisibleAndAccessible(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'setEditPeerSaving');

        $this->assertTrue(str_contains($body, 'button.disabled = true'));
        $this->assertTrue(str_contains($body, "setAttribute('aria-busy', 'true')"));
        $this->assertTrue(str_contains($body, 'spinner spinner-inline'));
        $this->assertTrue(str_contains($body, "t('js.saving')"));
        $this->assertTrue(str_contains($body, 'button.disabled = false'));
    }

    public function testSavingLabelExistsInBothLanguages(): void {
        $en = file_get_contents(__DIR__ . '/../src/lang/en.php');
        $it = file_get_contents(__DIR__ . '/../src/lang/it.php');

        $this->assertTrue(str_contains($en, "'js.saving'"));
        $this->assertTrue(str_contains($it, "'js.saving'"));
    }
}
