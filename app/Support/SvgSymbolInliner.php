<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Resolves `<use>` references into the geometry they stand for.
 *
 * dompdf draws SVG through php-svg-lib, which scales a `<symbol>` by the size of
 * the root viewport instead of the size the `<use>` asks for. A logo built from
 * 1480 tiles of a `viewBox="0 0 1.09 1.09"` symbol came out scaled 88.55 / 1.09
 * — over eighty times too big — and since nothing clips an image to its box, the
 * ink covered the whole page while the image box itself measured a tidy 42px.
 *
 * A `<use>` of a symbol means: place the symbol's children, translated to x/y and
 * scaled from the symbol's viewBox to the requested width/height. Writing that
 * out as a plain `<g transform="…">` is what the file already meant, and it is
 * something php-svg-lib renders correctly.
 */
class SvgSymbolInliner
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    /**
     * A `<use>` may point at something holding another `<use>`. Each pass resolves
     * one level; the cap stops a file that references itself in a circle.
     */
    private const MAX_PASSES = 10;

    /**
     * Growth guard. Every reference is a copy, so a small file with many `<use>`
     * elements can expand into a very large one.
     */
    private const MAX_RESULT_BYTES = 8 * 1024 * 1024;

    /**
     * @throws InvalidArgumentException when the file cannot be read, or expands beyond reason
     */
    public function inline(string $svg): string
    {
        if (! str_contains($svg, '<use')) {
            return $svg;
        }

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        $loaded = @$document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if (! $loaded || ! $document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException('SVG se ne može pročitati.');
        }

        $xpath = new DOMXPath($document);

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $uses = $xpath->query('//*[local-name()="use"]');

            if ($uses === false || $uses->length === 0) {
                break;
            }

            foreach (iterator_to_array($uses) as $use) {
                $this->resolve($document, $xpath, $use);
            }
        }

        // Anything still standing is a circular reference; it draws nothing.
        $this->removeAll($xpath, '//*[local-name()="use"]');
        $this->removeAll($xpath, '//*[local-name()="symbol"]');

        $result = $document->saveXML();

        if ($result === false) {
            throw new InvalidArgumentException('SVG se ne može zapisati.');
        }

        if (strlen($result) > self::MAX_RESULT_BYTES) {
            throw new InvalidArgumentException('SVG se pri razrešavanju referenci previše uvećao.');
        }

        return $result;
    }

    private function resolve(DOMDocument $document, DOMXPath $xpath, DOMElement $use): void
    {
        $target = $this->target($xpath, $use);

        if ($target === null) {
            $use->parentNode?->removeChild($use);

            return;
        }

        $group = $document->createElementNS(self::SVG_NAMESPACE, 'g');
        $transform = $this->transform($use, $target);

        if ($transform !== '') {
            $group->setAttribute('transform', $transform);
        }

        // Presentation set on the `<use>` is inherited by what it places.
        foreach (['class', 'style', 'fill', 'stroke', 'stroke-width', 'opacity'] as $attribute) {
            if ($use->hasAttribute($attribute)) {
                $group->setAttribute($attribute, $use->getAttribute($attribute));
            }
        }

        $children = in_array(strtolower($target->localName), ['symbol', 'g', 'svg'], true)
            ? iterator_to_array($target->childNodes)
            : [$target];

        foreach ($children as $child) {
            $group->appendChild($child->cloneNode(true));
        }

        $use->parentNode?->replaceChild($group, $use);
    }

    private function target(DOMXPath $xpath, DOMElement $use): ?DOMElement
    {
        $href = $use->getAttribute('href') ?: $use->getAttributeNS(self::XLINK_NAMESPACE, 'href');

        if (! str_starts_with($href, '#') || strlen($href) < 2) {
            return null;
        }

        $found = $xpath->query(sprintf('//*[@id=%s]', $this->quote(substr($href, 1))));

        $target = $found === false ? null : $found->item(0);

        // A node cannot place itself, and a node cannot place its own ancestor.
        if (! $target instanceof DOMElement || $this->contains($target, $use)) {
            return null;
        }

        return $target;
    }

    /**
     * The transform list that turns the target's own coordinates into the place
     * and size the `<use>` asks for.
     */
    private function transform(DOMElement $use, DOMElement $target): string
    {
        $parts = [];

        if ($use->hasAttribute('transform')) {
            $parts[] = $use->getAttribute('transform');
        }

        $x = (float) ($use->getAttribute('x') ?: 0);
        $y = (float) ($use->getAttribute('y') ?: 0);

        $viewBox = $this->viewBox($target);
        $width = (float) ($use->getAttribute('width') ?: 0);
        $height = (float) ($use->getAttribute('height') ?: 0);

        if ($viewBox === null || $width <= 0 || $height <= 0) {
            if ($x !== 0.0 || $y !== 0.0) {
                $parts[] = $this->translate($x, $y);
            }

            return implode(' ', $parts);
        }

        [$viewBoxX, $viewBoxY, $viewBoxWidth, $viewBoxHeight] = $viewBox;

        $scaleX = $width / $viewBoxWidth;
        $scaleY = $height / $viewBoxHeight;

        if (strtolower(trim($target->getAttribute('preserveAspectRatio'))) === 'none') {
            $parts[] = $this->translate($x, $y);
            $parts[] = sprintf('scale(%s %s)', $this->number($scaleX), $this->number($scaleY));
        } else {
            // Default is xMidYMid meet: one scale for both axes, centred in the box.
            $scale = min($scaleX, $scaleY);

            $parts[] = $this->translate(
                $x + ($width - $viewBoxWidth * $scale) / 2,
                $y + ($height - $viewBoxHeight * $scale) / 2,
            );

            if (abs($scale - 1.0) > 1e-9) {
                $parts[] = sprintf('scale(%s)', $this->number($scale));
            }
        }

        if ($viewBoxX !== 0.0 || $viewBoxY !== 0.0) {
            $parts[] = $this->translate(-$viewBoxX, -$viewBoxY);
        }

        return implode(' ', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function viewBox(DOMElement $element): ?array
    {
        if (! $element->hasAttribute('viewBox')) {
            return null;
        }

        $parts = preg_split('/[\s,]+/', trim($element->getAttribute('viewBox'))) ?: [];

        if (count($parts) !== 4) {
            return null;
        }

        [$x, $y, $width, $height] = array_map('floatval', $parts);

        return $width > 0 && $height > 0 ? [$x, $y, $width, $height] : null;
    }

    private function translate(float $x, float $y): string
    {
        if (abs($x) < 1e-9 && abs($y) < 1e-9) {
            return '';
        }

        return sprintf('translate(%s %s)', $this->number($x), $this->number($y));
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }

    private function contains(DOMElement $ancestor, DOMElement $node): bool
    {
        for ($current = $node; $current !== null; $current = $current->parentNode) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    private function quote(string $value): string
    {
        return str_contains($value, "'")
            ? sprintf('concat(\'%s\')', str_replace("'", "', \"'\", '", $value))
            : sprintf("'%s'", $value);
    }

    private function removeAll(DOMXPath $xpath, string $query): void
    {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return;
        }

        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
