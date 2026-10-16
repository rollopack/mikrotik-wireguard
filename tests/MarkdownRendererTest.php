<?php

require_once __DIR__ . '/run_tests.php';

class MarkdownRendererTest extends TestCase {

    public function testFromHeadingSkipsEarlierSections(): void {
        $md = "# Title\n\nIntro.\n\n## Features\n\n- a\n\n## Configuration\n\nConfig text.\n";
        $html = MarkdownRenderer::render($md, ['from_heading' => 'Configuration']);
        $this->assertTrue(str_starts_with($html, '<h2 id="configuration">'));
        $this->assertFalse(str_contains($html, 'Features'));
        $this->assertFalse(str_contains($html, 'Intro'));
        $this->assertTrue(str_contains($html, 'Config text'));
    }

    public function testFromHeadingUnknownKeepsEverything(): void {
        $html = MarkdownRenderer::render("# Title\n\nBody.\n", ['from_heading' => 'Nope']);
        $this->assertTrue(str_contains($html, 'Title'));
        $this->assertTrue(str_contains($html, 'Body'));
    }

    public function testFromHeadingIsCaseInsensitive(): void {
        $md = "## Features\n\n- a\n\n## Configuration\n\nConfig text.\n";
        $html = MarkdownRenderer::render($md, ['from_heading' => 'configuration']);
        $this->assertTrue(str_starts_with($html, '<h2 id="configuration">'));
    }

    public function testHeadingGetsLevelAndId(): void {
        $html = MarkdownRenderer::render("## Public API\n");
        $this->assertEquals('<h2 id="public-api">Public API</h2>', $html);
    }

    public function testParagraphWithBoldAndInlineCode(): void {
        $html = MarkdownRenderer::render("A **bold** word and `code`.\n");
        $this->assertEquals('<p>A <strong>bold</strong> word and <code>code</code>.</p>', $html);
    }

    public function testEscapesHtmlInText(): void {
        $html = MarkdownRenderer::render("Use <script>alert(1)</script> here\n");
        $this->assertFalse(str_contains($html, '<script>'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;'));
    }

    public function testFencedCodeIsNotInlineProcessed(): void {
        $html = MarkdownRenderer::render("```bash\necho \"**not bold**\"\n```\n");
        $this->assertTrue(str_contains($html, '<pre><code class="language-bash">'));
        $this->assertTrue(str_contains($html, '**not bold**'));
        $this->assertFalse(str_contains($html, '<strong>'));
    }

    public function testTableRendering(): void {
        $md = "| Key | Description |\n|-----|-------------|\n| `lang` | UI language |\n";
        $html = MarkdownRenderer::render($md);
        $this->assertTrue(str_contains($html, '<table>'));
        $this->assertTrue(str_contains($html, '<th>Key</th>'));
        $this->assertTrue(str_contains($html, '<td><code>lang</code></td>'));
        $this->assertTrue(str_contains($html, '<td>UI language</td>'));
    }

    public function testUnorderedList(): void {
        $html = MarkdownRenderer::render("- one\n- two\n");
        $this->assertEquals("<ul>\n<li>one</li>\n<li>two</li>\n</ul>", $html);
    }

    public function testNestedListStaysInsideParentItem(): void {
        $html = MarkdownRenderer::render("- parent\n  - child\n");
        $this->assertTrue(str_contains($html, "parent\n<ul>"));
        $this->assertTrue(str_contains($html, '<li>child</li>'));
    }

    public function testOrderedList(): void {
        $html = MarkdownRenderer::render("1. a\n2. b\n");
        $this->assertEquals("<ol>\n<li>a</li>\n<li>b</li>\n</ol>", $html);
    }

    public function testBlockquote(): void {
        $html = MarkdownRenderer::render("> note\n");
        $this->assertEquals('<blockquote><p>note</p></blockquote>', $html);
    }

    public function testRelativeLinkUsesBaseAsExternal(): void {
        $html = MarkdownRenderer::render("[API](API.md)\n", ['link_base' => 'https://example.com/blob/main/']);
        $this->assertTrue(str_contains($html, 'href="https://example.com/blob/main/API.md"'));
        $this->assertTrue(str_contains($html, 'target="_blank"'));
    }

    public function testAnchorLinkIsPreserved(): void {
        $html = MarkdownRenderer::render("[Deployment](#deployment)\n", ['link_base' => 'https://example.com/blob/main/']);
        $this->assertTrue(str_contains($html, 'href="#deployment"'));
        $this->assertFalse(str_contains($html, 'target="_blank"'));
    }

    public function testJavascriptUrlIsNotLinked(): void {
        $html = MarkdownRenderer::render("[x](javascript:alert(1))\n");
        $this->assertFalse(str_contains($html, '<a '));
        $this->assertTrue(str_contains($html, 'x'));
    }

    public function testHardLineBreakFromTrailingSpaces(): void {
        $html = MarkdownRenderer::render("line one  \nline two\n");
        $this->assertEquals('<p>line one<br>line two</p>', $html);
    }

    public function testImageKeepsRelativeSrcByDefault(): void {
        $html = MarkdownRenderer::render("![Alt](screenshots/a.png)\n");
        $this->assertTrue(str_contains($html, 'src="screenshots/a.png"'));
        $this->assertTrue(str_contains($html, 'alt="Alt"'));
    }
}
