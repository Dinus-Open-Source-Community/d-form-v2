<?php

namespace Tests\Feature\Recruitment;

use Database\Seeders\OprecFormSeeder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        // Form apply dirender langsung di GET /recruitment, sehingga
        // definisi form wajib ada agar halaman tidak 503.
        $this->seed(OprecFormSeeder::class);
    }

    public function test_recruitment_root_renders_apply_form(): void
    {
        $this->get('/recruitment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Apply'));
    }

    public function test_recruitment_apply_url_is_gone(): void
    {
        $this->get('/recruitment/apply')->assertNotFound();
    }

    public function test_named_apply_route_resolves_to_recruitment_root(): void
    {
        $this->assertSame(
            url('/recruitment'),
            route('recruitment.apply')
        );
    }
}
