<?php

declare(strict_types=1);

namespace Moves\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Sanitização defensiva do HTML produzido pelo editor visual. */
final class HtmlSanitizer
{
    private const TAGS = ['p','br','h2','h3','h4','blockquote','pre','code','strong','b','em','i','u','s','ul','ol','li','a','img','figure','figcaption','table','thead','tbody','tfoot','tr','th','td','hr','div','iframe'];
    private const ATTRIBUTES = ['href','title','target','rel','src','alt','class','colspan','rowspan','width','height','loading','allow','allowfullscreen','style','data-align'];
    private const CLASSES = [
        'moves-table-scroll',
        'moves-embed',
        'help-callout',
        'help-callout-note',
        'help-callout-tip',
        'help-callout-warning',
        'help-callout-important',
    ];

    public static function clean(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="moves-content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('moves-content-root');
        if (!$root) { return ''; }
        self::sanitizeChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
        return trim($result);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (!$node instanceof DOMElement) { continue; }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script','style','object','embed','form','input','button'], true)) { $parent->removeChild($node); continue; }
            if (!in_array($tag, self::TAGS, true)) {
                self::sanitizeChildren($node);
                while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, self::ATTRIBUTES, true)) { $node->removeAttribute($name); continue; }
                if ($name === 'href' && !self::safeLink($attribute->value)) { $node->removeAttribute($name); }
                if ($name === 'src' && (($tag === 'img' && !preg_match('#^/media/\d+$#', $attribute->value)) || ($tag === 'iframe' && !self::safeEmbed($attribute->value)))) { $node->removeAttribute($name); }
                if ($name === 'class') {
                    $classes = array_values(array_intersect(preg_split('/\s+/', trim($attribute->value)) ?: [], self::CLASSES));
                    $classes === [] ? $node->removeAttribute('class') : $node->setAttribute('class', implode(' ', $classes));
                }
                if ($name === 'data-align' && ($tag !== 'figure' || !in_array($attribute->value, ['left','center','right'], true))) { $node->removeAttribute($name); }
                if ($name === 'style') {
                    if ($tag !== 'figure' || preg_match('/^width:\s*(?:100%|[1-9]?\d(?:\.\d+)?%|(?:[4-9]\d|[1-9]\d{2,3})px)\s*;?$/i', trim($attribute->value)) !== 1) { $node->removeAttribute($name); }
                }
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') { $node->setAttribute('rel', 'noopener noreferrer'); }
            if ($tag === 'iframe' && !$node->hasAttribute('src')) { $parent->removeChild($node); continue; }
            self::sanitizeChildren($node);
        }
    }

    private static function safeLink(string $url): bool
    {
        return str_starts_with($url, '/') || str_starts_with($url, '#') || preg_match('#^(https?://|mailto:)#i', $url) === 1;
    }

    private static function safeEmbed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        return ($host === 'www.youtube-nocookie.com' && preg_match('#^/embed/[A-Za-z0-9_-]+$#', $path) === 1)
            || ($host === 'player.vimeo.com' && preg_match('#^/video/\d+$#', $path) === 1);
    }
}
