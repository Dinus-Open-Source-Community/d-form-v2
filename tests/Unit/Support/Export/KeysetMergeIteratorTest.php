<?php

namespace Tests\Unit\Support\Export;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Support\Export\KeysetMergeIterator;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * K-way merge contract: the global order must stay `(created_at DESC, id DESC)`
 * across streams, so rows with an identical `created_at` interleave by id instead
 * of draining one stream first.
 */
class KeysetMergeIteratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_sort_values_interleave_by_identifier_across_streams(): void
    {
        $firstForm = Form::factory()->create();
        $secondForm = Form::factory()->create();

        // Id sengaja berselang-seling antar form: merge yang "menghabiskan stream A
        // dulu" akan menghasilkan urutan yang berbeda dari 4000,3000,2000,1000.
        $this->makeAnswer($firstForm->id, '00000000-0000-4000-8000-000000001000', '2026-01-01 10:00:00');
        $this->makeAnswer($firstForm->id, '00000000-0000-4000-8000-000000003000', '2026-01-01 10:00:00');
        $this->makeAnswer($secondForm->id, '00000000-0000-4000-8000-000000002000', '2026-01-01 10:00:00');
        $this->makeAnswer($secondForm->id, '00000000-0000-4000-8000-000000004000', '2026-01-01 10:00:00');

        $expected = DB::table('form_answers')
            ->whereIn('form_id', [$firstForm->id, $secondForm->id])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        self::assertSame([
            '00000000-0000-4000-8000-000000004000',
            '00000000-0000-4000-8000-000000003000',
            '00000000-0000-4000-8000-000000002000',
            '00000000-0000-4000-8000-000000001000',
        ], $expected);
        self::assertSame($expected, $this->mergeIds([$firstForm->id, $secondForm->id], pageSize: 2));
    }

    public function test_single_stream_merge_matches_direct_iteration(): void
    {
        $form = Form::factory()->create();

        foreach (['2026-01-01 10:00:00', '2026-01-01 11:00:00', '2026-01-01 12:00:00'] as $createdAt) {
            $this->makeAnswer($form->id, null, $createdAt);
        }

        $direct = array_map(
            static fn (FormAnswer $answer): string => (string) $answer->getKey(),
            iterator_to_array((new KeysetPageIterator(FormAnswer::query()->where('form_id', $form->id)))
                ->iterate(new KeysetPage('created_at', 'id', descending: true, pageSize: 2))),
        );

        self::assertSame($direct, $this->mergeIds([$form->id], pageSize: 2));
        self::assertCount(3, $direct);
    }

    public function test_per_stream_page_size_one_still_merges_without_gap_or_duplicate(): void
    {
        $firstForm = Form::factory()->create();
        $secondForm = Form::factory()->create();

        $this->makeAnswer($firstForm->id, null, '2026-01-01 10:00:00');
        $this->makeAnswer($firstForm->id, null, '2026-01-01 12:00:00');
        $this->makeAnswer($firstForm->id, null, '2026-01-01 14:00:00');
        $this->makeAnswer($secondForm->id, null, '2026-01-01 11:00:00');
        $this->makeAnswer($secondForm->id, null, '2026-01-01 13:00:00');

        $expected = DB::table('form_answers')
            ->whereIn('form_id', [$firstForm->id, $secondForm->id])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $merged = $this->mergeIds([$firstForm->id, $secondForm->id], pageSize: 1);

        self::assertSame($expected, $merged);
        self::assertCount(5, array_unique($merged));
    }

    public function test_no_streams_yields_nothing(): void
    {
        $merged = iterator_to_array(
            (new KeysetMergeIterator(new KeysetPage('created_at', 'id', descending: true, pageSize: 2), []))->iterate(),
        );

        self::assertSame([], $merged);
    }

    /**
     * @param  list<string>  $formIds
     * @return list<string>
     */
    private function mergeIds(array $formIds, int $pageSize): array
    {
        $streams = [];

        foreach ($formIds as $formId) {
            $streams[] = new KeysetPageIterator(
                FormAnswer::query()->where('form_id', $formId)->select('form_answers.*')
            );
        }

        $page = new KeysetPage('created_at', 'id', descending: true, pageSize: $pageSize);

        return array_values(array_map(
            static fn (FormAnswer $answer): string => (string) $answer->getKey(),
            iterator_to_array((new KeysetMergeIterator($page, $streams))->iterate()),
        ));
    }

    private function makeAnswer(string $formId, ?string $id, string $createdAt): FormAnswer
    {
        $attributes = ['form_id' => $formId, 'created_at' => $createdAt];

        if ($id !== null) {
            $attributes['id'] = $id;
        }

        return FormAnswer::factory()->create($attributes);
    }
}
