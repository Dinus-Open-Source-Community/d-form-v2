<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use Database\Seeders\OprecFormSeeder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentPeriodListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        // Halaman apply dirender langsung di GET /recruitment, sehingga
        // definisi form wajib ada agar halaman tidak 503.
        $this->seed(OprecFormSeeder::class);
    }

    public function test_recruitment_page_exposes_only_currently_open_period(): void
    {
        $open = RecruitmentPeriod::query()->create([
            'name' => 'OpRec Aktif',
            'slug' => 'oprec-aktif',
            'status' => RecruitmentPeriodStatus::Open,
            'description' => 'Periode yang sedang dibuka.',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        RecruitmentPeriod::query()->create([
            'name' => 'OpRec Tutup',
            'slug' => 'oprec-tutup',
            'status' => RecruitmentPeriodStatus::Closed,
            'description' => 'Sudah ditutup.',
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subWeek(),
        ]);

        $this->get(route('recruitment.apply'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Apply')
                ->where('registration.is_open', true)
                ->where('period.id', $open->id)
                ->where('period.name', 'OpRec Aktif'));
    }

    public function test_recruitment_page_excludes_open_period_outside_window(): void
    {
        $available = RecruitmentPeriod::factory()->open()->create([
            'name' => 'OpRec Jalan',
        ]);

        RecruitmentPeriod::factory()->open()->create([
            'name' => 'OpRec Belum Buka',
            'registration_opens_at' => now()->addWeek(),
            'registration_closes_at' => now()->addMonths(2),
        ]);

        RecruitmentPeriod::factory()->open()->create([
            'name' => 'OpRec Kedaluwarsa',
            'registration_opens_at' => now()->subMonths(2),
            'registration_closes_at' => now()->subDay(),
        ]);

        $this->get(route('recruitment.apply'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Apply')
                ->where('registration.is_open', true)
                ->where('period.id', $available->id)
                ->where('period.name', 'OpRec Jalan'));
    }

    public function test_recruitment_page_returns_no_period_when_nothing_available(): void
    {
        $this->get(route('recruitment.apply'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Apply')
                ->where('registration.is_open', false)
                ->where('period', null));
    }
}
