<?php

require_once __DIR__ . '/run_tests.php';

/**
 * The "add peer" dialog refreshes the table only when it actually created a
 * peer: cancelling must not cost another get_peers round trip.
 */
class AddPeerModalFlowTest extends TestCase {
    private function functionBody(string $script, string $name): string {
        $start = strpos($script, 'function ' . $name . '(');
        $this->assertTrue($start !== false, "Function {$name} not found in app.js");

        $end = strpos($script, "\n}", $start);
        $this->assertTrue($end !== false, "Function {$name} has no closing brace");

        return substr($script, $start, $end - $start);
    }

    public function testClosingTheAddDialogReloadsOnlyAfterACreation(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'closeAddModal');

        $this->assertTrue(
            preg_match('/if \(addPeerCreated\) \{\s*addPeerCreated = false;\s*loadPeers\(\);/', $body) === 1,
            'closeAddModal must call loadPeers() only when a peer was created'
        );
        $this->assertEquals(1, substr_count($body, 'loadPeers()'),
            'closeAddModal must reload the table in exactly one place');
    }

    public function testTheCreatedFlagIsResetOnOpenAndSetOnSuccess(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        $this->assertTrue(str_contains($script, 'let addPeerCreated = false;'));
        $this->assertTrue(str_contains($this->functionBody($script, 'openAddModal'), 'addPeerCreated = false;'));
        $this->assertTrue(str_contains($this->functionBody($script, 'submitAddPeer'), 'addPeerCreated = true;'));
    }
}
