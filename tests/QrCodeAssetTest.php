<?php

require_once __DIR__ . '/run_tests.php';

class QrCodeAssetTest extends TestCase {
    public function testQrCodeLibraryIsAvailableForTheDashboard(): void {
        $library = file_get_contents(__DIR__ . '/../assets/js/qrcode-generator.min.js');

        $this->assertTrue($library !== false);
        $this->assertTrue(strpos($library, 'qrcode') !== false);
        $this->assertTrue(strpos($library, 'createSvgTag') !== false);
    }

    public function testBothConfigurationModalsExposeQrCodeTabs(): void {
        $addModal = file_get_contents(__DIR__ . '/../src/views/modals/add.php');
        $exportModal = file_get_contents(__DIR__ . '/../src/views/modals/export.php');
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        $this->assertEquals(1, substr_count($addModal, 'data-tab="qrcode"'));
        $this->assertEquals(1, substr_count($exportModal, 'data-tab="qrcode"'));
        $this->assertTrue(strpos($addModal, 'tab-qrcode') !== false);
        $this->assertTrue(strpos($exportModal, 'tab-export-qrcode') !== false);
        $this->assertTrue(strpos($script, 'switchAddTab(el.dataset.tab)') !== false);
        $this->assertTrue(strpos($script, 'switchExportTab(el.dataset.tab)') !== false);
        $this->assertTrue(strpos($script, 'renderQrCode') !== false);
        $this->assertTrue(strpos($script, 'clearQrCode') !== false);
    }

    public function testQrCodeLibraryIsNotLoadedWithTheInitialPage(): void {
        $page = file_get_contents(__DIR__ . '/../index.php');
        $script = file_get_contents(__DIR__ . '/../assets/js/app.js');

        // The encoder is only fetched when a configuration is displayed.
        $this->assertFalse(str_contains($page, 'qrcode-generator.min.js'));
        $this->assertTrue(str_contains($script, "QR_LIBRARY_URL = 'assets/js/qrcode-generator.min.js'"));
        $this->assertTrue(str_contains($script, 'function loadQrLibrary()'));
    }
}
