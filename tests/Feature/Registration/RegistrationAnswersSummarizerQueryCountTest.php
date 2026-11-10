<?php

namespace Tests\Feature\Registration;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Registration\RegistrationAnswersSummarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BE-P2-034: `buildSummary()` used to read `form_fields` once per submission, so a
 * 300-row export added 300 queries. Field definitions are memoized per form id.
 */
class RegistrationAnswersSummarizerQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_definitions_are_queried_once_for_300_submissions(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'team_name',
            'label' => 'Nama tim',
            'order' => 1,
            'input_type' => 'input',
            'metadata' => [],
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'full_name',
            'label' => 'Nama lengkap',
            'order' => 2,
            'input_type' => 'input',
            'metadata' => [],
        ]);

        $answers = FormAnswer::factory()
            ->count(300)
            ->create([
                'form_id' => $form->id,
                'answers' => ['team_name' => 'Tim Fixture', 'full_name' => 'Peserta Fixture'],
            ]);

        $summarizer = app(RegistrationAnswersSummarizer::class);

        DB::enableQueryLog();

        try {
            $summaries = [];
            foreach ($answers as $answer) {
                $summaries[] = $summarizer->summarize($answer);
            }

            $fieldQueries = array_filter(
                DB::getQueryLog(),
                fn (array $entry): bool => str_contains((string) ($entry['query'] ?? ''), 'form_fields')
            );
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        self::assertCount(300, $summaries);
        self::assertCount(1, $fieldQueries);
        self::assertSame(['Nama tim' => 'Tim Fixture', 'Nama lengkap' => 'Peserta Fixture'], $summaries[0]);
        self::assertSame($summaries[0], $summaries[299]);
    }

    public function test_fields_are_memoized_per_form_id_not_globally(): void
    {
        $firstForm = Form::factory()->create(['event_id' => Event::factory()->create()->id]);
        $secondForm = Form::factory()->create(['event_id' => Event::factory()->create()->id]);

        foreach ([$firstForm, $secondForm] as $form) {
            FormField::factory()->create([
                'form_id' => $form->id,
                'name' => 'full_name',
                'label' => 'Nama '.$form->id,
                'order' => 1,
                'input_type' => 'input',
                'metadata' => [],
            ]);
        }

        $summarizer = app(RegistrationAnswersSummarizer::class);

        DB::enableQueryLog();

        try {
            foreach ([$firstForm, $secondForm, $firstForm, $secondForm] as $form) {
                $answer = FormAnswer::factory()->create([
                    'form_id' => $form->id,
                    'answers' => ['full_name' => 'Peserta'],
                ]);

                $summarizer->summarize($answer);
            }

            $fieldQueries = array_filter(
                DB::getQueryLog(),
                fn (array $entry): bool => str_contains((string) ($entry['query'] ?? ''), 'form_fields')
            );
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        self::assertCount(2, $fieldQueries);
    }
}
