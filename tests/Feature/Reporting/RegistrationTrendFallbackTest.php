<?php

namespace Tests\Feature\Reporting;

use App\Models\FormAnswer;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fallback tren registrasi (agregat harian kosong) harus benar, memakai index
 * `form_answers.created_at` saja, dan di-cache agar tidak memindai 1 juta baris
 * pada setiap request.
 */
class RegistrationTrendFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * Factory memicu FormAnswerObserver yang mengisi agregat; hapus agregat agar
     * jalur fallback yang diuji benar-benar berjalan.
     */
    private function createAnswersWithoutAggregate(int $count, ?\Illuminate\Support\Carbon $createdAt = null): void
    {
        FormAnswer::factory()->count($count)->create(
            $createdAt === null ? [] : ['created_at' => $createdAt]
        );

        DB::table('event_daily_registrations')->delete();
    }

    public function test_fallback_menghitung_bulan_dari_form_answers_saat_agregat_kosong(): void
    {
        $this->assertSame(0, DB::table('event_daily_registrations')->count());

        $this->createAnswersWithoutAggregate(3);
        $this->createAnswersWithoutAggregate(2, now()->subMonthNoOverflow());

        $trend = app(EventReportingQuery::class)->registrationTrendOverMonths(6);

        $this->assertSame(6, count($trend));
        $this->assertSame(2, $trend[4]['count']);
        $this->assertSame(3, $trend[5]['count']);
    }

    public function test_fallback_di_cache_dan_tidak_memindai_form_answers_lagi(): void
    {
        $this->createAnswersWithoutAggregate(2);

        $query = app(EventReportingQuery::class);
        $first = $query->registrationTrendOverMonths(6);

        DB::enableQueryLog();
        $second = $query->registrationTrendOverMonths(6);
        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode(' | '));
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString('form_answers', $sql);
    }

    public function test_fallback_tidak_join_tabel_forms_atau_events(): void
    {
        $this->createAnswersWithoutAggregate(2);

        DB::enableQueryLog();
        app(EventReportingQuery::class)->registrationTrendOverMonths(6);
        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode(' | '));
        DB::disableQueryLog();

        $this->assertStringContainsString('form_answers', $sql);
        $this->assertStringNotContainsString('forms', $sql);
        $this->assertStringNotContainsString('events', $sql);
    }

    public function test_fallback_sama_dengan_agregat_setelah_rebuild(): void
    {
        $this->createAnswersWithoutAggregate(2);
        $this->createAnswersWithoutAggregate(1, now()->subMonthNoOverflow());

        $query = app(EventReportingQuery::class);
        $fallback = $query->registrationTrendOverMonths(6);

        $this->artisan('analytics:rebuild-daily-registrations')->assertSuccessful();
        Cache::flush();

        $this->assertSame($fallback, $query->registrationTrendOverMonths(6));
    }
}
