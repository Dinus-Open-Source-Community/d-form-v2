<?php

namespace App\Services\Broadcasting;

use App\Models\EmailBroadcast;

/**
 * Personalization MVP: hanya {{name}} dan {{event_name}}.
 */
class BroadcastPersonalization
{
    public const FALLBACK_NAME = 'Peserta';

    /** @var array<int, string> */
    public const ALLOWED_VARIABLES = ['name', 'event_name'];

    /**
     * @return array<int, string> daftar variable tidak didukung, mis. ["division"]
     */
    public function unsupportedVariables(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);

        $found = array_unique($matches[1] ?? []);
        $unsupported = [];

        foreach ($found as $variable) {
            if (! in_array($variable, self::ALLOWED_VARIABLES, true)) {
                $unsupported[] = '{{'.$variable.'}}';
            }
        }

        return array_values($unsupported);
    }

    public function render(?string $text, ?string $name, ?string $eventName): string
    {
        $displayName = trim((string) $name) !== '' ? trim((string) $name) : self::FALLBACK_NAME;
        $resolvedEvent = $eventName ?? '';

        return str_replace(
            ['{{name}}', '{{ name }}', '{{event_name}}', '{{ event_name }}'],
            [$this->escape($displayName), $this->escape($displayName), $this->escape($resolvedEvent), $this->escape($resolvedEvent)],
            (string) $text,
        );
    }

    /**
     * Render untuk HTML yang sudah disanitasi — nilai disisipkan sebagai plain text.
     */
    public function renderHtml(?string $html, ?string $name, ?string $eventName): string
    {
        return $this->render($html, $name, $eventName);
    }

    public function eventNameFor(EmailBroadcast $broadcast): string
    {
        $broadcast->loadMissing('event');

        return (string) ($broadcast->event?->title ?? '');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
