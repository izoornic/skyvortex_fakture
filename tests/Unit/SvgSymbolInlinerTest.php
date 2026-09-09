<?php

namespace Tests\Unit;

use App\Support\SvgSymbolInliner;
use PHPUnit\Framework\TestCase;

class SvgSymbolInlinerTest extends TestCase
{
    private SvgSymbolInliner $inliner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inliner = new SvgSymbolInliner;
    }

    public function test_a_file_without_references_is_left_alone(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

        $this->assertSame($svg, $this->inliner->inline($svg));
    }

    public function test_a_symbol_is_replaced_by_the_shapes_it_holds(): void
    {
        $inlined = $this->inliner->inline($this->tiles());

        $this->assertStringNotContainsString('<use', $inlined);
        $this->assertStringNotContainsString('<symbol', $inlined);
        $this->assertStringContainsString('<rect', $inlined);
        $this->assertStringContainsString('#0f766e', $inlined);
    }

    /**
     * The `<use>` places the symbol where and at what size it says. Its own
     * transform stays outermost, the placement follows it.
     */
    public function test_the_placement_of_the_symbol_is_kept(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<symbol id="tile" viewBox="0 0 1.09 1.09"><rect width="1.09" height="1.09"/></symbol>'
            .'<use width="1.09" height="1.09" transform="translate(176.38 29.58) scale(0.79)" href="#tile"/>'
            .'</svg>'
        );

        $this->assertStringContainsString('transform="translate(176.38 29.58) scale(0.79)"', $inlined);
    }

    public function test_a_symbol_asked_for_at_another_size_is_scaled(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<symbol id="tile" viewBox="0 0 1 1"><rect width="1" height="1"/></symbol>'
            .'<use x="5" y="7" width="10" height="10" href="#tile"/>'
            .'</svg>'
        );

        $this->assertStringContainsString('transform="translate(5 7) scale(10)"', $inlined);
    }

    public function test_an_offset_view_box_is_pulled_back_to_the_origin(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<symbol id="tile" viewBox="4 6 2 2"><rect x="4" y="6" width="2" height="2"/></symbol>'
            .'<use width="2" height="2" href="#tile"/>'
            .'</svg>'
        );

        $this->assertStringContainsString('translate(-4 -6)', $inlined);
    }

    public function test_a_reference_to_a_plain_shape_copies_that_shape(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<defs><circle id="dot" cx="1" cy="1" r="1" fill="#123456"/></defs>'
            .'<use x="10" y="20" href="#dot"/>'
            .'</svg>'
        );

        $this->assertStringNotContainsString('<use', $inlined);
        $this->assertStringContainsString('<circle', $inlined);
        $this->assertStringContainsString('transform="translate(10 20)"', $inlined);
    }

    public function test_presentation_set_on_the_reference_is_carried_over(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<symbol id="tile" viewBox="0 0 1 1"><rect width="1" height="1"/></symbol>'
            .'<use width="1" height="1" class="cls-1" fill="#abcdef" href="#tile"/>'
            .'</svg>'
        );

        $this->assertStringContainsString('class="cls-1"', $inlined);
        $this->assertStringContainsString('fill="#abcdef"', $inlined);
    }

    public function test_a_reference_inside_a_symbol_is_resolved_too(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<symbol id="dot" viewBox="0 0 1 1"><circle cx="0.5" cy="0.5" r="0.5"/></symbol>'
            .'<symbol id="pair" viewBox="0 0 2 1"><use width="1" height="1" href="#dot"/><use x="1" width="1" height="1" href="#dot"/></symbol>'
            .'<use width="2" height="1" href="#pair"/>'
            .'</svg>'
        );

        $this->assertStringNotContainsString('<use', $inlined);
        $this->assertSame(2, substr_count($inlined, '<circle'));
    }

    public function test_a_reference_that_points_nowhere_is_dropped(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<use width="1" height="1" href="#nema-ga"/><rect width="10" height="10"/>'
            .'</svg>'
        );

        $this->assertStringNotContainsString('<use', $inlined);
        $this->assertStringContainsString('<rect', $inlined);
    }

    /**
     * A group placing itself would copy forever.
     */
    public function test_a_circular_reference_does_not_run_away(): void
    {
        $inlined = $this->inliner->inline(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<g id="krug"><rect width="1" height="1"/><use href="#krug"/></g>'
            .'</svg>'
        );

        $this->assertStringNotContainsString('<use', $inlined);
        $this->assertLessThan(100_000, strlen($inlined));
    }

    /**
     * A tile logo made of many instances — the shape that covered a whole page.
     */
    private function tiles(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 200 50">'
            .'<defs><symbol id="tile" viewBox="0 0 1.09 1.09"><rect width="1.09" height="1.09" fill="#0f766e"/></symbol></defs>';

        for ($i = 0; $i < 10; $i++) {
            $svg .= sprintf('<use width="1.09" height="1.09" transform="translate(%d 0) scale(4)" xlink:href="#tile"/>', $i * 5);
        }

        return $svg.'</svg>';
    }
}
