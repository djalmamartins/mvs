<?php

declare(strict_types=1);

namespace Moves\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Sanitização defensiva do HTML produzido pelo editor visual. */
final class HtmlSanitizer
{
    private const TAGS = ['p','br','h2','h3','h4','blockquote','pre','code','strong','b','em','i','u','s','ul','ol','li','a','img','figure','figcaption','table','thead','tbody','tr','th','td','hr','span','div'];
    private const ATTRIBUTES = ['href','title','target','rel','src','alt','class','colspan','rowspan','width','height'];

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
            if (in_array($tag, ['script','style','iframe','object','embed'], true)) { $parent->removeChild($node); continue; }
            if (!in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, self::ATTRIBUTES, true)) { $node->removeAttribute($name); continue; }
                if ($name === 'href' && !self::safeLink($attribute->value)) { $node->removeAttribute($name); }
                if ($name === 'src' && !preg_match('#^/media/\d+$#', $attribute->value)) { $node->removeAttribute($name); }
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') { $node->setAttribute('rel', 'noopener noreferrer'); }
            self::sanitizeChildren($node);
        }
    }

    private static function safeLink(string $url): bool
    {
        return str_starts_with($url, '/') || str_starts_with($url, '#') || preg_match('#^(https?://|mailto:)#i', $url) === 1;
    }
}
