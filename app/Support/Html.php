<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

class Html
{
    protected const ALLOWED = ['p', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'h5', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'blockquote'];

    protected const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'noscript', 'template', 'head', 'title', 'meta', 'link'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! preg_match('/<[a-z][^>]*>/i', $html) && preg_match('/&lt;\/?[a-z][^&]*&gt;/i', $html)) {
            return static::clean(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (! preg_match('/<[a-z][^>]*>/i', $html)) {
            $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return collect(preg_split('/\R{2,}/u', trim($text)))
                ->map(fn (string $paragraph) => '<p>'.preg_replace('/\R/u', '<br>', e(trim($paragraph))).'</p>')
                ->implode('');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="elio-clean-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//div[@id="elio-clean-root"]')?->item(0);

        if (! $root) {
            return e(strip_tags($html));
        }

        return trim(preg_replace('/(<br>\s*){3,}/', '<br><br>', static::render($root)));
    }

    protected static function render(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= e($child->nodeValue);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                continue;
            }

            if ($tag === 'br') {
                $html .= '<br>';

                continue;
            }

            $inner = static::render($child);

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));

                $html .= preg_match('~^(https?://|mailto:|tel:|/(?!/)|#)~i', $href)
                    ? '<a href="'.e($href).'"'.(preg_match('~^https?://~i', $href) ? ' target="_blank" rel="noopener nofollow"' : '').">{$inner}</a>"
                    : $inner;

                continue;
            }

            $html .= in_array($tag, self::ALLOWED, true) ? "<{$tag}>{$inner}</{$tag}>" : $inner;
        }

        return $html;
    }
}
