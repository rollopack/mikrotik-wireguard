<?php

function loadLanguage(string $code): array {
    $file = __DIR__ . '/lang/' . basename($code) . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/lang/en.php';
    }
    return require $file;
}

function t(array $lang, string $key, string $default = ''): string {
    return $lang[$key] ?? ($default ?: $key);
}

function t_e(array $lang, string $key, string $default = ''): void {
    echo t($lang, $key, $default);
}

function jsTranslations(array $lang): array {
    $js = [];
    foreach ($lang as $key => $value) {
        if (str_starts_with($key, 'js.')) {
            $js[$key] = $value;
        }
    }
    return $js;
}
