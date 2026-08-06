<?php

namespace App\Extraction\Support;

use DOMDocument;
use DOMXPath;

/**
 * Strips boilerplate markup from an HTML page and returns the readable text.
 */
class HtmlTextExtractor
{
    private const REMOVE_TAGS = ['script', 'style', 'noscript', 'nav', 'header', 'footer', 'aside', 'svg', 'form', 'iframe'];

    public static function extract(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        foreach (self::REMOVE_TAGS as $tag) {
            foreach (iterator_to_array($xpath->query("//{$tag}") ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = $document->textContent ?? '';

        return trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{2,}/', "\n", $text)) ?? '');
    }
}
