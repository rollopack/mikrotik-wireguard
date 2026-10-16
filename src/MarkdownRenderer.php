<?php

/**
 * Minimal, dependency-free Markdown renderer.
 *
 * It supports the subset of Markdown used by the project README: ATX headings,
 * paragraphs (with hard line breaks), unordered/nested/ordered lists, fenced
 * code blocks, tables, blockquotes, images and links with inline bold/code.
 *
 * Input is assumed to be trusted first-party content (the repository README),
 * but every piece of text is HTML-escaped before formatting, and link URLs are
 * restricted to http/https/mailto plus relative and in-page references.
 */
class MarkdownRenderer
{
    /**
     * @param string $markdown Raw Markdown source.
     * @param array{link_base?:string,image_base?:string,from_heading?:string} $options
     *        Base URLs used to resolve relative links/images. Relative URLs are
     *        left untouched when the matching base is empty. When "from_heading"
     *        matches a heading (case-insensitive), everything before that heading
     *        is dropped.
     */
    public static function render(string $markdown, array $options = []): string
    {
        $linkBase = self::normalizeBase($options['link_base'] ?? '');
        $imageBase = self::normalizeBase($options['image_base'] ?? '');

        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        $fromHeading = trim((string)($options['from_heading'] ?? ''));
        if ($fromHeading !== '') {
            $start = self::findHeadingIndex($lines, $fromHeading);
            if ($start !== null) {
                $lines = array_slice($lines, $start);
            }
        }

        $count = count($lines);
        $out = [];
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Fenced code block
            if (preg_match('/^```\s*([\w+#-]*)\s*$/', $line, $m)) {
                $lang = $m[1];
                $code = [];
                $i++;
                while ($i < $count && !preg_match('/^```\s*$/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // closing fence
                $class = $lang !== '' ? ' class="language-' . self::escape($lang) . '"' : '';
                $out[] = '<pre><code' . $class . '>' . self::escape(implode("\n", $code)) . "\n</code></pre>";
                continue;
            }

            // ATX heading
            if (preg_match('/^(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
                $level = strlen($m[1]);
                $slug = self::slug(self::stripInline($m[2]));
                $id = $slug !== '' ? ' id="' . self::escape($slug) . '"' : '';
                $out[] = '<h' . $level . $id . '>' . self::inline($m[2], $linkBase, $imageBase) . '</h' . $level . '>';
                $i++;
                continue;
            }

            // Blockquote (consecutive lines)
            if (preg_match('/^>\s?/', $line)) {
                $parts = [];
                while ($i < $count && preg_match('/^>\s?(.*)$/', $lines[$i], $m)) {
                    $parts[] = $m[1];
                    $i++;
                }
                $out[] = '<blockquote><p>' . self::inline(implode(' ', $parts), $linkBase, $imageBase) . '</p></blockquote>';
                continue;
            }

            // Table (header row followed by a separator row)
            if (str_contains($line, '|') && isset($lines[$i + 1]) && self::isTableSeparator($lines[$i + 1])) {
                $header = self::splitRow($line);
                $i += 2;
                $rows = [];
                while ($i < $count && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
                    $rows[] = self::splitRow($lines[$i]);
                    $i++;
                }
                $html = "<table>\n<thead>\n<tr>";
                foreach ($header as $cell) {
                    $html .= '<th>' . self::inline($cell, $linkBase, $imageBase) . '</th>';
                }
                $html .= "</tr>\n</thead>\n<tbody>\n";
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach ($header as $index => $_) {
                        $html .= '<td>' . self::inline($row[$index] ?? '', $linkBase, $imageBase) . '</td>';
                    }
                    $html .= "</tr>\n";
                }
                $out[] = $html . "</tbody>\n</table>";
                continue;
            }

            // Unordered list
            if (preg_match('/^(\s*)[-*+]\s+(.*)$/', $line, $m)) {
                $items = [];
                while ($i < $count && preg_match('/^(\s*)[-*+]\s+(.*)$/', $lines[$i], $m)) {
                    $items[] = ['indent' => strlen($m[1]), 'text' => $m[2]];
                    $i++;
                }
                $out[] = self::renderList($items, $items[0]['indent'], 'ul', $linkBase, $imageBase);
                continue;
            }

            // Ordered list
            if (preg_match('/^(\s*)\d+\.\s+(.*)$/', $line, $m)) {
                $items = [];
                while ($i < $count && preg_match('/^(\s*)\d+\.\s+(.*)$/', $lines[$i], $m)) {
                    $items[] = ['indent' => strlen($m[1]), 'text' => $m[2]];
                    $i++;
                }
                $out[] = self::renderList($items, $items[0]['indent'], 'ol', $linkBase, $imageBase);
                continue;
            }

            // Paragraph
            $buf = [];
            while ($i < $count && trim($lines[$i]) !== '' && !self::isBlockStart($lines, $i)) {
                $buf[] = $lines[$i];
                $i++;
            }
            $html = '';
            $previousHardBreak = false;
            foreach ($buf as $index => $textLine) {
                $hardBreak = (bool)preg_match('/ {2,}$/', $textLine);
                if ($index > 0 && !$previousHardBreak) {
                    $html .= ' ';
                }
                $html .= self::inline(trim($textLine), $linkBase, $imageBase);
                if ($hardBreak) {
                    $html .= '<br>';
                }
                $previousHardBreak = $hardBreak;
            }
            $out[] = '<p>' . $html . '</p>';
        }

        return implode("\n", $out);
    }

    /**
     * Render a flat list of items, nesting deeper indentation recursively.
     *
     * @param array<int, array{indent:int, text:string}> $items
     */
    private static function renderList(array $items, int $indent, string $tag, string $linkBase, string $imageBase): string
    {
        $html = "<$tag>\n";
        $count = count($items);
        $k = 0;

        while ($k < $count) {
            $html .= '<li>' . self::inline($items[$k]['text'], $linkBase, $imageBase);

            $j = $k + 1;
            $sublist = [];
            while ($j < $count && $items[$j]['indent'] > $indent) {
                $sublist[] = $items[$j];
                $j++;
            }
            if ($sublist !== []) {
                $html .= "\n" . self::renderList($sublist, $sublist[0]['indent'], $tag, $linkBase, $imageBase);
            }

            $html .= "</li>\n";
            $k = $j;
        }

        return $html . "</$tag>";
    }

    /**
     * Apply inline formatting (code, images, links, bold). Text arrives already
     * escaped; inline code is protected from further processing.
     */
    private static function inline(string $text, string $linkBase, string $imageBase): string
    {
        $escaped = self::escape($text);

        $codeSpans = [];
        $escaped = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codeSpans): string {
            $codeSpans[] = $m[1];
            return "\x1A" . (count($codeSpans) - 1) . "\x1A";
        }, $escaped);

        $escaped = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            static function (array $m) use ($imageBase): string {
                $src = self::resolveUrl($m[2], $imageBase);
                if ($src === null) {
                    return $m[1];
                }
                return '<img src="' . self::escape($src) . '" alt="' . $m[1] . '" loading="lazy">';
            },
            $escaped
        );

        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            static function (array $m) use ($linkBase): string {
                $url = self::resolveUrl($m[2], $linkBase);
                if ($url === null) {
                    return $m[1];
                }
                $external = (bool)preg_match('#^https?://#i', $url);
                $attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . self::escape($url) . '"' . $attrs . '>' . $m[1] . '</a>';
            },
            $escaped
        );

        $escaped = (string)preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', (string)$escaped);

        return (string)preg_replace_callback('/\x1A(\d+)\x1A/', static function (array $m) use ($codeSpans): string {
            return '<code>' . $codeSpans[(int)$m[1]] . '</code>';
        }, $escaped);
    }

    /**
     * Resolve a Markdown URL: allowed schemes pass through, in-page anchors are
     * kept, relative paths are prefixed with $base, anything else is rejected.
     */
    private static function resolveUrl(string $url, string $base): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $m)) {
            $scheme = strtolower($m[1]);
            if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return null;
            }
            return $url;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '//')) {
            return $url;
        }

        if ($base === '') {
            return $url;
        }

        return $base . ltrim($url, '/');
    }

    /**
     * Index of the first heading whose text matches $heading (case-insensitive).
     *
     * @param array<int, string> $lines
     */
    private static function findHeadingIndex(array $lines, string $heading): ?int
    {
        $target = mb_strtolower(trim($heading));
        foreach ($lines as $index => $line) {
            if (preg_match('/^#{1,6}\s+(.*?)\s*#*\s*$/', $line, $m)) {
                if (mb_strtolower(trim(self::stripInline($m[1]))) === $target) {
                    return $index;
                }
            }
        }

        return null;
    }

    private static function normalizeBase($base): string
    {
        $base = trim((string)$base);
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Heading text with inline markup stripped, used to build the anchor id.
     */
    private static function stripInline(string $text): string
    {
        $text = (string)preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        return str_replace(['**', '`', '*'], '', $text);
    }

    private static function slug(string $text): string
    {
        $text = strtolower($text);
        $text = (string)preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * @return array<int, string>
     */
    private static function splitRow(string $row): array
    {
        return array_map('trim', explode('|', trim(trim($row), '|')));
    }

    private static function isTableSeparator(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '-')) {
            return false;
        }
        return (bool)preg_match('/^\|?[\s:|-]+\|?$/', $line);
    }

    /**
     * Whether the line at $i starts a block that must break a paragraph.
     *
     * @param array<int, string> $lines
     */
    private static function isBlockStart(array $lines, int $i): bool
    {
        $line = $lines[$i];

        if (preg_match('/^```/', $line) || preg_match('/^#{1,6}\s/', $line) || preg_match('/^>\s?/', $line)) {
            return true;
        }
        if (preg_match('/^\s*([-*+]|\d+\.)\s+/', $line)) {
            return true;
        }
        if (isset($lines[$i + 1]) && str_contains($line, '|') && self::isTableSeparator($lines[$i + 1])) {
            return true;
        }

        return false;
    }
}
