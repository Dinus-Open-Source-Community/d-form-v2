<?php

namespace Tests\Unit\Support\Export;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Keyset paging contract: no OFFSET, total order across `(sort value, id)` ties,
 * exact page-size boundary, caller ordering/paging rejected, and a page that does
 * not select the cursor columns failing fast.
 */
class KeysetPageIteratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_offset_and_no_gap_or_duplicate_when_sort_values_tie(): void
    {
        $form = Form::factory()->create();

        foreach (range(1, 5) as $_) {
            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'created_at' => '2026-01-01 10:00:00',
            ]);
        }

        DB::enableQueryLog();

        try {
            $ids = $this->iterateIds(FormAnswer::query()->where('form_id', $form->id), pageSize: 2);
            $queries = $this->pageQueries();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $expected = DB::table('form_answers')
            ->where('form_id', $form->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        self::assertSame($expected, $ids);
        self::assertCount(5, array_unique($ids));

        foreach ($queries as $sql) {
            self::assertStringNotContainsString('offset', strtolower($sql));
        }

        // 5 baris / 2 = halaman (2, 2, 1); halaman pendek mengakhiri iterasi.
        self::assertCount(3, $queries);
    }

    public function test_exact_page_size_multiple_stops_after_one_empty_probe(): void
    {
        $form = Form::factory()->create();

        foreach (['2026-01-01 10:00:00', '2026-01-01 11:00:00', '2026-01-01 12:00:00', '2026-01-01 13:00:00'] as $createdAt) {
            FormAnswer::factory()->create(['form_id' => $form->id, 'created_at' => $createdAt]);
        }

        DB::enableQueryLog();

        try {
            $ids = $this->iterateIds(FormAnswer::query()->where('form_id', $form->id), pageSize: 2);
            $queries = $this->pageQueries();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        self::assertCount(4, $ids);
        self::assertCount(4, array_unique($ids));
        self::assertSame('2026-01-01 13:00:00', FormAnswer::query()->findOrFail($ids[0])->getRawOriginal('created_at'));

        // (2, 2, kosong): halaman penuh terakhir wajib diikuti satu probe kosong.
        self::assertCount(3, $queries);
    }

    public function test_pages_yields_full_page_collections_in_order(): void
    {
        $form = Form::factory()->create();

        foreach (range(1, 3) as $index) {
            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'created_at' => sprintf('2026-01-0%d 10:00:00', $index),
            ]);
        }

        $iterator = new KeysetPageIterator(FormAnswer::query()->where('form_id', $form->id));
        $sizes = [];

        foreach ($iterator->pages(new KeysetPage('created_at', 'id', pageSize: 2)) as $page) {
            $sizes[] = $page->count();
        }

        self::assertSame([2, 1], $sizes);
    }

    public function test_null_sort_value_rows_are_excluded_and_streamed_separately(): void
    {
        $form = Form::factory()->create();
        $answers = [];

        foreach (['2026-01-01 10:00:00', '2026-01-01 11:00:00', '2026-01-01 12:00:00', '2026-01-01 13:00:00'] as $createdAt) {
            $answers[] = FormAnswer::factory()->create(['form_id' => $form->id, 'created_at' => $createdAt]);
        }

        // NULL tidak bisa dibandingkan oleh predikat kursor, jadi baris legacy ini
        // wajib jatuh ke pass terpisah (`whereNull`) dan bukan hilang diam-diam
        // dari keluarga query yang sama.
        DB::table('form_answers')
            ->whereIn('id', [$answers[0]->id, $answers[1]->id])
            ->update(['created_at' => null]);

        $dated = $this->iterateIds(FormAnswer::query()->where('form_id', $form->id), pageSize: 2);

        $undated = $this->iterateIds(
            FormAnswer::query()->where('form_id', $form->id)->whereNull('created_at'),
            pageSize: 2,
            sortColumn: 'id',
        );

        self::assertSame([$answers[3]->id, $answers[2]->id], $dated);
        self::assertCount(2, $undated);
        self::assertCount(4, array_unique([...$dated, ...$undated]));
    }

    /**
     * @return array<string, array{0: callable(Builder<FormAnswer>): Builder<FormAnswer>}>
     */
    public static function preOrderedBaseQueries(): array
    {
        return [
            'orderBy' => [static fn (Builder $query): Builder => $query->orderBy('created_at')],
            'orderByDesc' => [static fn (Builder $query): Builder => $query->orderByDesc('created_at')],
            'limit' => [static fn (Builder $query): Builder => $query->limit(10)],
            'offset' => [static fn (Builder $query): Builder => $query->offset(10)],
        ];
    }

    /**
     * @param  callable(Builder<FormAnswer>): Builder<FormAnswer>  $decorate
     */
    #[DataProvider('preOrderedBaseQueries')]
    public function test_base_query_with_ordering_or_paging_is_rejected(callable $decorate): void
    {
        $form = Form::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('owns ORDER BY/LIMIT/OFFSET');

        new KeysetPageIterator($decorate(FormAnswer::query()->where('form_id', $form->id)));
    }

    public function test_non_progressing_cursor_aborts_instead_of_looping_forever(): void
    {
        // Builder palsu yang selalu mengembalikan dua baris identik: kursor tidak
        // mungkin maju (mis. presisi sort value hilang antara DB dan binding).
        $builder = new class (FormAnswer::query()->getQuery(), new FormAnswer()) extends Builder {
            /** @var Collection<int, FormAnswer> */
            public Collection $rows;

            public function __construct(QueryBuilder $query, FormAnswer $model)
            {
                parent::__construct($query);

                $this->model = $model;
            }

            public function clone()
            {
                return $this;
            }

            public function get($columns = ['*'])
            {
                return $this->rows;
            }
        };

        $builder->rows = new Collection([
            $this->rawAnswer('00000000-0000-4000-8000-000000000001'),
            $this->rawAnswer('00000000-0000-4000-8000-000000000002'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('made no progress');

        foreach ((new KeysetPageIterator($builder))
            ->iterate(new KeysetPage('created_at', 'id', descending: true, pageSize: 2)) as $_) {
        }
    }

    public function test_page_query_missing_cursor_columns_fails_fast(): void
    {
        $form = Form::factory()->create();

        foreach (range(1, 2) as $_) {
            FormAnswer::factory()->create(['form_id' => $form->id, 'created_at' => '2026-01-01 10:00:00']);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must select created_at and id');

        // Page penuh tanpa kolom `created_at` di `select`: kursor tidak bisa
        // dibentuk, jadi kegagalan harus jelas, bukan token kosong beberapa
        // halaman kemudian.
        $this->iterateIds(FormAnswer::query()->where('form_id', $form->id)->select('id'), pageSize: 2);
    }

    /**
     * @param  Builder<FormAnswer>  $query
     * @return list<string>
     */
    private function iterateIds(Builder $query, int $pageSize, string $sortColumn = 'created_at'): array
    {
        $iterator = new KeysetPageIterator($query);
        $ids = [];

        foreach ($iterator->iterate(new KeysetPage($sortColumn, 'id', descending: true, pageSize: $pageSize)) as $answer) {
            $ids[] = (string) $answer->getKey();
        }

        return $ids;
    }

    /**
     * Model dengan atribut mentah tanpa menyentuh database.
     */
    private function rawAnswer(string $id): FormAnswer
    {
        $answer = new FormAnswer();
        $answer->setRawAttributes([
            'id' => $id,
            'created_at' => '2026-01-01 10:00:00',
        ], true);

        return $answer;
    }

    /**
     * @return list<string>
     */
    private function pageQueries(): array
    {
        return array_values(array_map(
            fn (array $entry): string => (string) ($entry['query'] ?? ''),
            array_filter(
                DB::getQueryLog(),
                fn (array $entry): bool => str_contains((string) ($entry['query'] ?? ''), 'from "form_answers"')
            )
        ));
    }
}
