<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Http\Middleware\EnsureOrganizerDashboardAccess;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Regresi keyset pagination export CSV registrasi event: `form_answers.created_at`
 * hanya presisi detik sehingga tie lintas halaman wajib dipecah oleh `id`.
 */
class EventRegistrationExportOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_export_writes_every_row_once_in_created_at_then_id_desc_order_across_page_ties(): void
    {
        config(['registration.event_export_page_size' => 2]);

        [$event, $form] = $this->eventWithForm();

        foreach (range(1, 5) as $_) {
            $this->makeAnswer($form, '2026-01-01 10:00:00');
        }

        $this->makeAnswer($form, '2026-01-02 10:00:00');
        $this->makeAnswer($form, '2026-01-01 09:00:00');

        $rows = $this->exportRows($event);
        $dataRows = array_slice($rows, 1);

        $exportedIds = array_map(fn (array $row): string => (string) $row[0], $dataRows);

        // Ekspektasi diambil dari urutan DB sendiri, bukan `strcmp` PHP: collation
        // kolom `id` di MySQL tidak perlu diasumsikan sama dengan SQLite test.
        $expectedIds = DB::table('form_answers')
            ->where('form_id', $form->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        self::assertCount(7, $dataRows);
        self::assertSame($expectedIds, $exportedIds);
        self::assertCount(7, array_unique($exportedIds));
        self::assertSame('2026-01-02 10:00:00', $dataRows[0][11]);
    }

    /**
     * Event dengan lebih dari satu form memakai bentuk join (bukan `form_id IN`),
     * jadi judul per form wajib tetap diambil dari map form event, bukan kolom
     * join yang sudah dilepas.
     */
    public function test_multi_form_event_exports_each_rows_own_form_title(): void
    {
        config(['registration.event_export_page_size' => 2]);

        [$event, $firstForm] = $this->eventWithForm();
        $secondForm = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Second form',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        $firstAnswer = $this->makeAnswer($firstForm, '2026-01-01 10:00:00');
        $secondAnswer = $this->makeAnswer($secondForm, '2026-01-02 10:00:00');
        $thirdAnswer = $this->makeAnswer($secondForm, '2026-01-01 09:00:00');

        $dataRows = array_slice($this->exportRows($event), 1);

        self::assertCount(3, $dataRows);
        self::assertCount(3, array_unique(array_map(fn (array $row): string => (string) $row[0], $dataRows)));

        $titles = [];
        foreach ($dataRows as $row) {
            $titles[(string) $row[0]] = (string) $row[2];
        }

        self::assertSame('Registration', $titles[(string) $firstAnswer->getKey()]);
        self::assertSame('Second form', $titles[(string) $secondAnswer->getKey()]);
        self::assertSame('Second form', $titles[(string) $thirdAnswer->getKey()]);
    }

    public function test_rows_without_created_at_are_exported_once_after_the_keyset_pages(): void
    {
        config(['registration.event_export_page_size' => 2]);

        [$event, $form] = $this->eventWithForm();

        foreach (range(1, 5) as $_) {
            $this->makeAnswer($form, '2026-01-01 10:00:00');
        }

        // Kolom `created_at` nullable; Eloquent selalu mengisinya, jadi NULL hanya
        // mungkin berasal dari data legacy/manual. Baris seperti ini tidak bisa
        // dijangkau kursor non-NULL dan wajib tetap diekspor.
        $legacy = $this->makeAnswer($form, '2026-01-01 10:00:00');
        DB::table('form_answers')->where('id', $legacy->id)->update(['created_at' => null]);

        $dataRows = array_slice($this->exportRows($event), 1);
        $exportedIds = array_map(fn (array $row): string => (string) $row[0], $dataRows);

        self::assertCount(6, $dataRows);
        self::assertCount(6, array_unique($exportedIds));
        self::assertSame((string) $legacy->getKey(), end($exportedIds));
        self::assertSame('', $dataRows[5][11]);
    }

    public function test_export_paginates_with_keyset_cursor_and_never_uses_offset(): void
    {
        config(['registration.event_export_page_size' => 2]);

        [$event, $form] = $this->eventWithForm();

        foreach (range(1, 5) as $_) {
            $this->makeAnswer($form, '2026-01-01 10:00:00');
        }

        $queries = $this->exportQueries($event);

        foreach ($queries as $sql) {
            self::assertStringNotContainsString(' offset ', strtolower($sql));
        }

        $pageQueries = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'form_answers') && str_contains(strtolower($sql), ' limit ')
        ));

        // 5 baris / 2 per halaman = 3 halaman berisi (2, 2, 1) + satu tail pass
        // kosong untuk baris `created_at` NULL. Halaman keempat tidak dijalankan.
        self::assertCount(4, $pageQueries);

        $normalized = array_map(
            fn (string $sql): string => str_replace(['"', '`'], '', $sql),
            $pageQueries,
        );

        // Halaman kedua dan ketiga wajib memakai predikat kursor komposit, bukan
        // sekadar ORDER BY: tanpa ini test tetap hijau saat filter kursor hilang.
        foreach (array_slice($normalized, 1, 2) as $cursorSql) {
            self::assertStringContainsString('form_answers.created_at < ', $cursorSql);
            self::assertStringContainsString('form_answers.id < ', $cursorSql);
        }
    }

    public function test_row_inserted_between_pages_is_not_duplicated_or_missing(): void
    {
        config(['registration.event_export_page_size' => 2]);

        [$event, $form] = $this->eventWithForm();

        foreach (range(1, 5) as $_) {
            $this->makeAnswer($form, '2026-01-01 10:00:00');
        }

        // QueryExecuted dipancarkan setelah query dieksekusi, jadi insert di sini
        // benar-benar terjadi di antara halaman pertama dan kedua.
        $inserted = false;

        DB::listen(function (QueryExecuted $query) use (&$inserted, $form): void {
            if ($inserted) {
                return;
            }

            $sql = strtolower($query->sql);

            if (! str_contains($sql, 'from') || ! str_contains($sql, 'form_answers') || ! str_contains($sql, 'limit')) {
                return;
            }

            $inserted = true;

            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'created_at' => '2026-01-02 10:00:00',
            ]);
        });

        $dataRows = array_slice($this->exportRows($event), 1);
        $exportedIds = array_map(fn (array $row): string => (string) $row[0], $dataRows);

        // Baris baru punya created_at lebih baru sehingga berada di depan kursor dan
        // tidak ikut; yang dilarang adalah baris lama hilang atau tertulis dua kali.
        self::assertCount(5, $dataRows);
        self::assertCount(5, array_unique($exportedIds));
    }

    public function test_registration_export_audits_only_safe_metadata_and_counts_streamed_rows(): void
    {
        [$event, $form] = $this->eventWithForm();
        $participantOne = User::factory()->create([
            'name' => 'Private Participant One',
            'email' => 'private.participant.one@example.test',
        ]);
        $participantTwo = User::factory()->create([
            'name' => 'Private Participant Two',
            'email' => 'private.participant.two@example.test',
        ]);

        foreach ([$participantOne, $participantTwo] as $participant) {
            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => $participant->id,
            ]);
        }

        $actor = $this->admin();
        Log::spy();

        $response = $this->actingAs($actor)
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();
        self::assertCount(3, $this->csvRows($response->streamedContent()));

        Log::shouldHaveReceived('info')->once()->withArgs(
            static function (string $message, array $context) use ($actor, $event, $participantOne, $participantTwo): bool {
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'event_csv_export.generated'
                    && $context === [
                        'actor_id' => $actor->id,
                        'event_id' => $event->id,
                        'export_type' => 'registrations',
                        'row_count' => 2,
                    ]
                    && ! str_contains($encodedContext, $participantOne->name)
                    && ! str_contains($encodedContext, $participantOne->email)
                    && ! str_contains($encodedContext, $participantTwo->name)
                    && ! str_contains($encodedContext, $participantTwo->email);
            },
        );
    }

    /**
     * L-3: kegagalan di tengah stream tetap meninggalkan tepat satu audit record,
     * dengan jumlah baris data yang benar-benar sudah ditulis (header tidak dihitung).
     */
    public function test_registration_export_audits_partial_output_when_the_row_generator_throws(): void
    {
        [$event, $form] = $this->eventWithForm();

        foreach (range(1, 3) as $_) {
            FormAnswer::factory()->create(['form_id' => $form->id]);
        }

        // Tail pass (`created_at IS NULL`) berjalan setelah ketiga baris ditulis:
        // kegagalan di sini mensimulasikan error generator/DB di tengah stream.
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'is null') && str_contains($query->sql, 'form_answers')) {
                throw new RuntimeException('tail pass exploded');
            }
        });

        $actor = $this->admin();
        Log::spy();

        $response = $this->actingAs($actor)
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();

        $bufferLevel = ob_get_level();

        try {
            $response->streamedContent();
            self::fail('Kegagalan generator harus diteruskan ke pemanggil.');
        } catch (RuntimeException $exception) {
            self::assertSame('tail pass exploded', $exception->getMessage());
        } finally {
            // `streamedContent()` tidak menutup buffer-nya saat callback melempar.
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }

        Log::shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export.generated'
                && $context === [
                    'actor_id' => $actor->id,
                    'event_id' => $event->id,
                    'export_type' => 'registrations',
                    'row_count' => 3,
                ],
        );
    }

    public function test_registration_export_audit_failure_does_not_abort_the_csv_stream(): void
    {
        [$event, $form] = $this->eventWithForm();
        FormAnswer::factory()->create(['form_id' => $form->id]);
        $actor = $this->admin();

        Log::spy();
        Log::shouldReceive('error')->zeroOrMoreTimes()->withArgs(
            static function (string $message, array $context): bool {
                return $message === 'unhandled_exception'
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ($context['message'] ?? null) === RuntimeException::class.': [message redacted]'
                    && is_string($context['file'] ?? null)
                    && is_int($context['line'] ?? null)
                    && is_array($context['frames'] ?? null)
                    && is_string($context['correlation_id'] ?? null)
                    && Str::isUuid($context['correlation_id'])
                    && array_keys($context) === ['exception_class', 'message', 'file', 'line', 'frames', 'correlation_id'];
            },
        );
        Log::shouldReceive('info')->once()->andThrow(new RuntimeException('Logger unavailable'));
        Log::shouldReceive('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export_audit_failed'
                && $context['actor_id'] === $actor->id
                && $context['event_id'] === $event->id
                && $context['export_type'] === 'registrations'
                && $context['exception_class'] === RuntimeException::class,
        );

        $response = $this->actingAs($actor)
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();
        self::assertCount(2, $this->csvRows($response->streamedContent()));
    }

    public function test_both_event_exports_share_the_existing_recruitment_export_limiter_per_user(): void
    {
        config(['recruitment.export_rate_limit_per_minute' => 1]);
        [$event] = $this->eventWithForm();

        foreach ([
            'dashboard.events.exports.registrations-csv',
            'dashboard.events.exports.attendance-csv',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            self::assertNotNull($route);
            self::assertContains('throttle:recruitment-export', $route->gatherMiddleware());
        }

        $firstActor = $this->admin();
        $secondActor = $this->admin();

        $this->actingAs($firstActor)
            ->get(route('dashboard.events.exports.registrations-csv', $event))
            ->assertOk();

        $this->actingAs($firstActor)
            ->get(route('dashboard.events.exports.attendance-csv', $event))
            ->assertTooManyRequests();

        $this->actingAs($secondActor)
            ->get(route('dashboard.events.exports.attendance-csv', $event))
            ->assertOk();
    }

    public function test_member_cannot_export_registrations_csv_as_json(): void
    {
        [$event] = $this->eventWithForm();

        $member = User::factory()->create();
        $member->assignRole('member');

        // Middleware `organizer` sudah mengalihkan member ke dashboard (302)
        // sebelum controller; test ini menguji otorisasi controller/policy untuk
        // klien JSON, jadi middleware tersebut dilewati.
        $this->withoutMiddleware(EnsureOrganizerDashboardAccess::class);

        $this->actingAs($member)
            ->getJson(route('dashboard.events.exports.registrations-csv', $event))
            ->assertForbidden();
    }

    public function test_admin_who_did_not_create_the_event_gets_forbidden_json(): void
    {
        $owner = $this->admin();
        [$event] = $this->eventWithForm(['created_by' => $owner->id]);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.events.exports.registrations-csv', $event))
            ->assertForbidden();
    }

    public function test_unknown_event_id_returns_not_found(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('dashboard.events.exports.registrations-csv', Str::uuid()->toString()))
            ->assertNotFound();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(array $eventOverrides = []): array
    {
        $event = Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ], $eventOverrides));

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    private function makeAnswer(Form $form, string $createdAt): FormAnswer
    {
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'created_at' => $createdAt,
        ]);

        $answer->refresh();

        self::assertSame($createdAt, (string) $answer->getRawOriginal('created_at'));

        return $answer;
    }

    /**
     * @return list<list<string|null>>
     */
    private function exportRows(Event $event): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();

        $rows = $this->csvRows($response->streamedContent());

        self::assertNotSame([], $rows);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function exportQueries(Event $event): array
    {
        DB::enableQueryLog();

        try {
            $this->actingAs($this->admin())
                ->get(route('dashboard.events.exports.registrations-csv', $event))
                ->assertOk()
                ->streamedContent();

            return array_values(array_map(
                fn (array $entry): string => (string) ($entry['query'] ?? ''),
                DB::getQueryLog()
            ));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvRows(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        return array_values(array_map(
            fn (string $line): array => str_getcsv($line),
            array_filter(explode("\n", trim($content)), fn (string $line): bool => $line !== '')
        ));
    }
}
