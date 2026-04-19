<?php

namespace Tests\Unit;

use App\Enums\EventCategory;
use App\Services\Event\EventCategoryOptionService;
use Tests\TestCase;

class EventCategoryOptionServiceTest extends TestCase
{
    private EventCategoryOptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EventCategoryOptionService::class);
    }

    public function test_returns_all_enum_options_when_no_raw_rows_resolve(): void
    {
        $options = $this->service->optionsForRawCategoryRows([]);

        $this->assertCount(count(EventCategory::cases()), $options);

        foreach (EventCategory::cases() as $case) {
            $this->assertTrue(
                collect($options)->contains(fn (array $row) => $row['value'] === $case->value)
            );
        }
    }

    public function test_includes_all_enums_plus_custom_tokens_from_rows(): void
    {
        $options = $this->service->optionsForRawCategoryRows([
            'rkt, recruitment',
            'RKT, etc',
        ]);

        $values = collect($options)->pluck('value')->all();

        foreach (EventCategory::cases() as $case) {
            $this->assertContains($case->value, $values);
        }

        foreach ($options as $row) {
            $this->assertArrayHasKey('value', $row);
            $this->assertArrayHasKey('label', $row);
            if (EventCategory::tryFrom($row['value']) instanceof EventCategory) {
                $this->assertSame(
                    strip_tags((string) EventCategory::from($row['value'])->getLabel()),
                    $row['label']
                );
            }
        }
    }

    public function test_appends_custom_token_must_after_enums(): void
    {
        $options = $this->service->optionsForRawCategoryRows(['rkt, must']);

        $values = collect($options)->pluck('value')->all();

        $this->assertContains('must', $values);
        $mustRow = collect($options)->firstWhere('value', 'must');
        $this->assertNotNull($mustRow);
        $this->assertSame('Must', $mustRow['label']);
    }

    public function test_ignores_tokens_that_are_not_enum_and_not_valid_custom(): void
    {
        $options = $this->service->optionsForRawCategoryRows([
            'rkt, bad@token, etc',
        ]);

        $values = collect($options)->pluck('value')->all();

        $this->assertNotContains('bad@token', $values);
        $this->assertContains('rkt', $values);
        $this->assertContains('etc', $values);
    }

    public function test_resolves_non_rkt_from_spaced_token(): void
    {
        $options = $this->service->optionsForRawCategoryRows(['Non Rkt']);

        $values = collect($options)->pluck('value')->all();
        $this->assertContains('non-rkt', $values);
    }
}
