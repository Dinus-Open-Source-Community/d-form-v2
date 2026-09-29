<?php

namespace App\Services\Broadcasting;

/**
 * Sanitasi HTML output Tiptap dengan allowlist.
 * Mencegah XSS tanpa menambah dependensi baru.
 */
class BroadcastHtmlSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3',
        'blockquote', 'code', 'pre', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'span', 'div', 'img',
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'span' => ['style'],
        'p' => ['style'],
        'div' => ['style'],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
    ];

    public function sanitize(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $wrapper = $doc->getElementsByTagName('div')->item(0);

        if (! $wrapper instanceof \DOMElement) {
            return '';
        }

        $this->sanitizeNode($wrapper);

        $clean = '';
        foreach ($wrapper->childNodes as $child) {
            $clean .= $doc->saveHTML($child) ?: '';
        }

        return $clean;
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        /** @var \DOMNode[] $children */
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Unwrap: pindahkan children ke parent lalu hapus elemen.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $this->sanitizeAttributes($child, $tag);
                $this->sanitizeNode($child);
            } elseif ($child instanceof \DOMComment) {
                $node->removeChild($child);
            }
        }
    }

    private function sanitizeAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        /** @var \DOMAttr[] $attrs */
        $attrs = iterator_to_array($el->attributes);

        foreach ($attrs as $attr) {
            $name = strtolower($attr->name);

            if (str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->name);

                continue;
            }

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);

                continue;
            }

            $value = trim($attr->value);

            if (in_array($name, ['href', 'src'], true)) {
                if (! $this->isSafeUrl($value, $tag)) {
                    $el->removeAttribute($attr->name);
                } elseif ($name === 'href') {
                    $el->setAttribute('rel', 'noopener noreferrer');
                }
            }

            if ($name === 'style') {
                $el->setAttribute('style', $this->sanitizeStyle($value));
            }
        }
    }

    private function isSafeUrl(string $url, string $tag): bool
    {
        if ($tag === 'img' && str_starts_with($url, 'data:image/')) {
            // Inline image PRD: base64 di dalam konten.
            return (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-zA-Z0-9+/=]+$#', preg_replace('/\s+/', '', $url));
        }

        return str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'mailto:');
    }

    private function sanitizeStyle(string $style): string
    {
        // Hanya izinkan properti text-align dan color sederhana; sisanya dibuang.
        $allowedProps = ['text-align', 'color', 'background-color'];
        $out = [];

        foreach (explode(';', $style) as $decl) {
            [$prop, $val] = array_pad(explode(':', $decl, 2), 2, '');
            $prop = strtolower(trim($prop));
            $val = trim($val);

            if (! in_array($prop, $allowedProps, true) || $val === '') {
                continue;
            }

            if (! preg_match('/^[a-zA-Z0-9#(),.\s%-]+$/', $val)) {
                continue;
            }

            $out[] = $prop.': '.$val;
        }

        return implode('; ', $out);
    }
}
