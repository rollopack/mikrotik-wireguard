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

    public function testLanguageFilesHaveNoDuplicateKeys(): void {
        foreach (['en', 'it'] as $code) {
            $source = file_get_contents(__DIR__ . '/../src/lang/' . $code . '.php');
            preg_match_all("/^\s*'([^']+)'\s*=>/m", $source, $matches);
            $duplicates = array_keys(array_filter(
                array_count_values($matches[1]),
                static fn(int $count): bool => $count > 1
            ));
            $this->assertEquals([], $duplicates, "Duplicate translation keys in $code.php");
        }
    }

    public function testEnglishAndItalianHaveSameKeys(): void {
        $en = array_keys(require __DIR__ . '/../src/lang/en.php');
        $it = array_keys(require __DIR__ . '/../src/lang/it.php');
        sort($en);
        sort($it);
        $this->assertEquals($en, $it, 'EN and IT language files must expose the same keys');
    }
}
