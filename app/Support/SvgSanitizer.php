<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/**
 * An SVG is a document, not a picture: it can carry scripts, event handlers and
 * references to other files. The logo is uploaded by an administrator, but it is
 * later served back to browsers, so the file is rewritten to a known-safe subset
 * once — on the way in — and what lands on disk is already harmless.
 */
class SvgSanitizer
{
    /**
     * Elements that make an SVG something other than a drawing.
     *
     * `animate` and `set` are here because they can rewrite any attribute of
     * another element at runtime, including `href`.
     */
    private const FORBIDDEN_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object', 'handler',
        'audio', 'video', 'animate', 'animatemotion', 'animatetransform', 'set',
    ];

    /**
     * The only references a logo needs: a fragment inside the same file, or an
     * image embedded in it.
     */
    private const REFERENCE_ATTRIBUTES = ['href', 'xlink:href', 'src', 'from', 'to', 'values'];

    /**
     * @throws InvalidArgumentException when the file is not an SVG we can make safe
     */
    public function sanitize(string $source): string
    {
        $source = trim($source);

        if ($source === '') {
            throw new InvalidArgumentException('Fajl je prazan.');
        }

        // Entity declarations are how an XML file reads other files or expands
        // into gigabytes. Neither belongs in a logo, so the file is refused
        // rather than repaired.
        if (preg_match('/<!ENTITY/i', $source) === 1) {
            throw new InvalidArgumentException('SVG sadrži deklaracije entiteta.');
        }

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        $loaded = $this->withoutXmlErrors(
            fn (): bool => $document->loadXML($source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)
        );

        if (! $loaded || ! $document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException('Fajl nije ispravan XML.');
        }

        if (strtolower($document->documentElement->nodeName) !== 'svg') {
            throw new InvalidArgumentException('Korenski element nije <svg>.');
        }

        $this->removeDoctype($document);
        $this->clean($document->documentElement);

        $svg = $document->saveXML();

        if ($svg === false) {
            throw new InvalidArgumentException('SVG se ne može zapisati.');
        }

        return $svg;
    }

    private function removeDoctype(DOMDocument $document): void
    {
        if ($document->doctype !== null) {
            $document->removeChild($document->doctype);
        }
    }

    /**
     * Depth first, because removing a node while walking forward would skip its
     * sibling.
     */
    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->nodeName), self::FORBIDDEN_ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }

                if (strtolower($child->nodeName) === 'style') {
                    $this->cleanStyleSheet($child);

                    continue;
                }

                $this->clean($child);

                continue;
            }

            if ($child instanceof DOMNode && $child->nodeType === XML_PI_NODE) {
                $element->removeChild($child);
            }
        }

        $this->cleanAttributes($element);
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->nodeName);
            $value = $attribute->nodeValue ?? '';

            // onload, onclick, onmouseover — the whole family.
            if (str_starts_with($name, 'on')) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if (in_array($name, self::REFERENCE_ATTRIBUTES, true) && ! $this->isSafeReference($value)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if ($name === 'style' && $this->pullsSomethingIn($value)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * A stylesheet cannot run code, but it can fetch fonts and images from
     * elsewhere. The rules stay, the fetching goes.
     */
    private function cleanStyleSheet(DOMElement $style): void
    {
        $css = $style->textContent;

        $css = preg_replace('/@import[^;]*;?/i', '', $css) ?? '';
        $css = preg_replace('/url\s*\([^)]*\)/i', 'none', $css) ?? '';

        $style->textContent = $css;
    }

    private function isSafeReference(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '#')) {
            return true;
        }

        return (bool) preg_match('#^data:image/(png|jpeg|gif|webp);base64,#i', $value);
    }

    private function pullsSomethingIn(string $value): bool
    {
        return preg_match('/url\s*\(|javascript:|@import/i', $value) === 1;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withoutXmlErrors(callable $callback): mixed
    {
        $previous = libxml_use_internal_errors(true);

        try {
            return $callback();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
