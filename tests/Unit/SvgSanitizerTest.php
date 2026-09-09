<?php

namespace Tests\Unit;

use App\Support\SvgSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SvgSanitizerTest extends TestCase
{
    private SvgSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new SvgSanitizer;
    }

    public function test_the_drawing_itself_survives(): void
    {
        $clean = $this->sanitizer->sanitize(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" width="120" height="40">
                <title>Logo</title>
                <rect width="120" height="40" fill="#0f766e"/>
                <path d="M10 10 L30 30 Z" stroke="#fff" stroke-width="2"/>
                <text x="40" y="26" font-size="14" fill="#ffffff">Firma</text>
            </svg>
            SVG);

        $this->assertStringContainsString('viewBox="0 0 120 40"', $clean);
        $this->assertStringContainsString('fill="#0f766e"', $clean);
        $this->assertStringContainsString('M10 10 L30 30 Z', $clean);
        $this->assertStringContainsString('Firma', $clean);
    }

    public function test_scripts_are_removed(): void
    {
        $clean = $this->sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script><rect width="10" height="10"/></svg>'
        );

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<rect', $clean);
    }

    public function test_event_handlers_are_removed(): void
    {
        $clean = $this->sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle cx="5" cy="5" r="5" onclick="steal()" onmouseover="x()"/></svg>'
        );

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringContainsString('<circle', $clean);
    }

    public function test_foreign_objects_and_animations_are_removed(): void
    {
        $clean = $this->sanitizer->sanitize(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><iframe src="https://zlo.example"></iframe></body></foreignObject>
                <a><set attributeName="href" to="javascript:alert(1)"/></a>
            </svg>
            SVG);

        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_references_to_other_files_are_removed(): void
    {
        $clean = $this->sanitizer->sanitize(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                <image href="https://zlo.example/pixel.png" width="1" height="1"/>
                <use xlink:href="https://zlo.example/payload.svg#x"/>
                <a href="javascript:alert(1)"><rect width="10" height="10"/></a>
                <use href="#saban"/>
            </svg>
            SVG);

        $this->assertStringNotContainsString('zlo.example', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);

        // A reference inside the same file is how symbols are drawn; it stays.
        $this->assertStringContainsString('#saban', $clean);
    }

    public function test_an_embedded_raster_stays(): void
    {
        $pixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $clean = $this->sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="'.$pixel.'" width="1" height="1"/></svg>'
        );

        $this->assertStringContainsString('data:image/png;base64,', $clean);
    }

    public function test_stylesheets_keep_their_rules_but_lose_their_fetching(): void
    {
        $clean = $this->sanitizer->sanitize(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <style>@import url("https://zlo.example/x.css"); .cls-1 { fill: #ff0000; } .cls-2 { fill: url(https://zlo.example/p.png); }</style>
                <rect class="cls-1" width="10" height="10"/>
            </svg>
            SVG);

        $this->assertStringContainsString('fill: #ff0000', $clean);
        $this->assertStringNotContainsString('@import', $clean);
        $this->assertStringNotContainsString('zlo.example', $clean);
    }

    public function test_style_attributes_that_pull_something_in_are_removed(): void
    {
        $clean = $this->sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:#fff" width="1" height="1"/><rect style="background:url(https://zlo.example/p.png)" width="1" height="1"/></svg>'
        );

        $this->assertStringContainsString('style="fill:#fff"', $clean);
        $this->assertStringNotContainsString('zlo.example', $clean);
    }

    public function test_entity_declarations_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sanitizer->sanitize(<<<'SVG'
            <?xml version="1.0"?>
            <!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
            <svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>
            SVG);
    }

    public function test_something_that_is_not_an_svg_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sanitizer->sanitize('<html><body><p>Ovo nije logo</p></body></html>');
    }

    public function test_binary_rubbish_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sanitizer->sanitize("\x89PNG\r\n\x1a\n".random_bytes(32));
    }
}
