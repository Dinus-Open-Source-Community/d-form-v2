<?php

namespace Tests\Feature\Scan;

use App\Http\Controllers\Dashboard\Scan\GlobalScanStreamController;
use Tests\TestCase;

class StreamCursorTest extends TestCase
{
    private function toCursorIso(?string $cursor): ?string
    {
        $controller = app(GlobalScanStreamController::class);
        $method = new \ReflectionMethod(GlobalScanStreamController::class, 'toCursorIso');
        $method->setAccessible(true);

        return $method->invoke($controller, $cursor);
    }

    public function test_to_cursor_iso_parses_evt_id_timestamp_after_second_colon(): void
    {
        $iso = '2026-09-14T10:00:00+07:00';
        $cursor = 'evt:123e4567-e89b-12d3-a456-426614174000:'.$iso;

        $this->assertSame($iso, $this->toCursorIso($cursor));
    }

    public function test_to_cursor_iso_returns_null_for_rec_id_without_timestamp(): void
    {
        // rec: ids embed no timestamp, so resume degrades to full feed.
        $this->assertNull($this->toCursorIso('rec:123e4567-e89b-12d3-a456-426614174000'));
    }

    public function test_to_cursor_iso_returns_null_for_garbage(): void
    {
        $this->assertNull($this->toCursorIso('not-a-cursor'));
        $this->assertNull($this->toCursorIso(null));
    }
}
