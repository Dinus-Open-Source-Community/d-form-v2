<?php

namespace Tests\Unit;

use App\Services\Event\EventSessionOptionService;
use Tests\TestCase;

class EventSessionOptionServiceTest extends TestCase
{
    private EventSessionOptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventSessionOptionService::class);
    }

    public function test_returns_all_enum_options_when_no_raw_rows_resolve(): void
    {
        $options = $this->service->optionsForRawSessionRows([]);
        $values = collect($options)->pluck('value')->all();

        $this->assertContains('general', $values);
        $this->assertContains('programming', $values);
        $this->assertContains('network', $values);
        $this->assertContains('media_creative', $values);
        $this->assertContains('data', $values);
    }

    public function test_includes_all_enums_plus_custom_tokens_from_rows(): void
    {
        $options = $this->service->optionsForRawSessionRows(['general', 'programming', 'custom_session']);
        $values = collect($options)->pluck('value')->all();

        $this->assertContains('general', $values);
        $this->assertContains('programming', $values);
        $this->assertContains('network', $values);
        $this->assertContains('media_creative', $values);
        $this->assertContains('data', $values);
        $this->assertContains('custom_session', $values);
    }

    public function test_appends_custom_token_after_enums(): void
    {
        $options = $this->service->optionsForRawSessionRows(['general', 'keynote']);
        $values = collect($options)->pluck('value')->all();

        $this->assertContains('keynote', $values);

        $row = collect($options)->firstWhere('value', 'keynote');
        $this->assertNotNull($row);
        $this->assertSame('Keynote', $row['label']);
    }

    public function test_ignores_tokens_that_are_not_enum_and_not_valid_custom(): void
    {
        $options = $this->service->optionsForRawSessionRows(['bad@token', 'general', 'keynote']);
        $values = collect($options)->pluck('value')->all();

        $this->assertNotContains('bad@token', $values);
        $this->assertContains('general', $values);
        $this->assertContains('keynote', $values);
    }

    public function test_splits_comma_separated_session_row_into_tokens(): void
    {
        $options = $this->service->optionsForRawSessionRows(['general, workshop']);
        $values = collect($options)->pluck('value')->all();

        $this->assertContains('general', $values);
        $this->assertContains('workshop', $values);
        $this->assertNotContains('general, workshop', $values);

        $workshop = collect($options)->firstWhere('value', 'workshop');
        $this->assertNotNull($workshop);
        $this->assertSame('Workshop', $workshop['label']);
    }

    public function test_resolves_media_creative_from_spaced_token(): void
    {
        $options = $this->service->optionsForRawSessionRows(['Media Creative']);
        $values = collect($options)->pluck('value')->all();

        $this->assertContains('media_creative', $values);
        $this->assertNotContains('media creative', $values);
    }
}
