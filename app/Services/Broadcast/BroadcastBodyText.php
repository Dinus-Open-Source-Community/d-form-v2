<?php

namespace App\Services\Broadcast;

final class BroadcastBodyText
{
    /** Turunkan body_text polos dari body_html (null bila kosong). */
    public function fromHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }
}
