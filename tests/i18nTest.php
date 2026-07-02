<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/../src/i18n.php';

class i18nTest extends TestCase {
    public function testLoadLanguageReturnsEnglishByDefault(): void {
        $lang = loadLanguage('en');
        $this->assertTrue(is_array($lang));
        $this->assertEquals('WireGuard Peer Manager - ResNovae', $lang['site.title']);
    }

    public function testLoadLanguageReturnsItalian(): void {
        $lang = loadLanguage('it');
        $this->assertTrue(is_array($lang));
        $this->assertEquals('WireGuard Peer Manager - ResNovae', $lang['site.title']);
    }

    public function testLoadLanguageFallsBackToEnglish(): void {
        $lang = loadLanguage('invalid_code');
        $this->assertTrue(is_array($lang));
        $this->assertEquals('WireGuard Peer Manager - ResNovae', $lang['site.title']);
    }

    public function testLoadLanguageFallsBackToEnglishFromEmptyString(): void {
        $lang = loadLanguage('');
        $this->assertTrue(is_array($lang));
        $this->assertEquals('WireGuard Peer Manager - ResNovae', $lang['site.title']);
    }

    public function testTWithExistingKey(): void {
        $lang = loadLanguage('en');
        $this->assertEquals('Total Peers', t($lang, 'stats.total_peers'));
    }

    public function testTWithMissingKeyReturnsKey(): void {
        $lang = loadLanguage('en');
        $this->assertEquals('nonexistent_key', t($lang, 'nonexistent_key'));
    }

    public function testTWithMissingKeyAndDefaultReturnsDefault(): void {
        $lang = loadLanguage('en');
        $this->assertEquals('My Default', t($lang, 'nonexistent_key', 'My Default'));
    }

    public function testTEchoesTranslation(): void {
        $lang = loadLanguage('en');
        ob_start();
        t_e($lang, 'stats.total_peers');
        $output = ob_get_clean();
        $this->assertEquals('Total Peers', $output);
    }

    public function testJsTranslationsOnlyReturnsJsKeys(): void {
        $lang = loadLanguage('en');
        $js = jsTranslations($lang);
        foreach ($js as $key => $value) {
            $this->assertStringStartsWith('js.', $key);
        }
    }

    public function testJsTranslationsContainsExpectedKeys(): void {
        $lang = loadLanguage('en');
        $js = jsTranslations($lang);
        $this->assertTrue(isset($js['js.col_name']));
        $this->assertTrue(isset($js['js.col_ip']));
        $this->assertEquals('Name & Comment', $js['js.col_name']);
    }

    public function testJsTranslationsExcludesNonJsKeys(): void {
        $lang = loadLanguage('en');
        $js = jsTranslations($lang);
        $this->assertFalse(isset($js['site.title']));
        $this->assertFalse(isset($js['stats.total_peers']));
    }

    public function testTWithItalianTranslation(): void {
        $lang = loadLanguage('it');
        $this->assertEquals('Nome', t($lang, 'table.name'));
    }

    public function testTWithItalianMissingKeyFallsBack(): void {
        $lang = loadLanguage('it');
        $this->assertEquals('nonexistent_key', t($lang, 'nonexistent_key'));
    }
}
