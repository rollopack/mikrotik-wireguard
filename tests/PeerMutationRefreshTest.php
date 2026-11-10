<?php

require_once __DIR__ . '/run_tests.php';

class PeerMutationRefreshTest extends TestCase {
    private function functionBody(string $script, string $name): string {
        $start = strpos($script, 'function ' . $name . '(');
        $this->assertTrue($start !== false, "Function {$name} not found in app.js");

        $end = strpos($script, "\n}", $start);
        $this->assertTrue($end !== false, "Function {$name} has no closing brace");

        return substr($script, $start, $end - $start);
    }

    public function testPeerUpdatesRenderFromTheExistingList(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'updatePeerLocally');

        $this->assertTrue(str_contains($body, 'peerMutationVersion++'));
        $this->assertTrue(str_contains($body, "allPeers.find(p => p['.id'] === id)"));
        $this->assertTrue(str_contains($body, 'Object.assign(peer, updates)'));
        $this->assertTrue(str_contains($body, 'applyFiltersAndSort()'));
        $this->assertFalse(str_contains($body, 'loadPeers()'));
    }

    public function testPeerRemovalRendersFromTheExistingList(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');
        $body = $this->functionBody($script, 'removePeerLocally');

        $this->assertTrue(str_contains($body, 'peerMutationVersion++'));
        $this->assertTrue(str_contains($body, "p['.id'] !== id"));
        $this->assertTrue(str_contains($body, 'applyFiltersAndSort()'));
        $this->assertFalse(str_contains($body, 'loadPeers()'));
    }

    public function testSuccessfulPeerMutationsUseLocalUpdatesWithMissingPeerFallback(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        foreach ([
            'submitEditPeer' => "updatePeerLocally(id, { name }, highlightIp)",
            'submitDeletePeer' => 'removePeerLocally(id)',
            'togglePeer' => 'updatePeerLocally(id, { disabled: newDisabled })',
        ] as $function => $updateCall) {
            $body = $this->functionBody($script, $function);
            $this->assertTrue(str_contains($body, $updateCall),
                "{$function} must update the in-memory peer list after success");
            $this->assertTrue(str_contains($body, 'loadPeers()'),
                "{$function} must retain a full refresh fallback if the peer is missing locally");
        }
    }

    public function testInFlightRefreshCannotOverwriteACompletedLocalMutation(): void {
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        foreach (['loadPeers', 'refreshPeers'] as $function) {
            $body = $this->functionBody($script, $function);
            $this->assertTrue(str_contains($body, 'const requestMutationVersion = peerMutationVersion'));
            $this->assertTrue(str_contains($body, 'requestMutationVersion !== peerMutationVersion'));
        }
    }
}
