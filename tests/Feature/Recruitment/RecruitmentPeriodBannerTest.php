<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentPeriodBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_store_tanpa_banner_tetap_sukses_dan_banner_url_null(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec Tanpa Banner',
            ])
            ->assertRedirect();

        $period = RecruitmentPeriod::query()->where('name', 'OpRec Tanpa Banner')->firstOrFail();

        $this->assertNull($period->banner);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('period.banner_url', null));
    }

    public function test_store_dengan_banner_tersimpan_dan_banner_url_ada(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec Dengan Banner',
                'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 675),
            ])
            ->assertRedirect();

        $period = RecruitmentPeriod::query()->where('name', 'OpRec Dengan Banner')->firstOrFail();

        $this->assertNotNull($period->banner);
        $this->assertStringStartsWith('recruitment/banners/', $period->banner);
        Storage::disk('public')->assertExists($period->banner);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'period.banner_url',
                url('/storage/'.$period->banner)
            ));
    }

    public function test_update_ganti_banner_menghapus_file_lama(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $period = RecruitmentPeriod::factory()->create(['created_by' => $admin->id]);
        Storage::disk('public')->put('recruitment/banners/old.jpg', 'fake-old');
        $period->update(['banner' => 'recruitment/banners/old.jpg']);

        $this->actingAs($admin)
            ->patch(route('dashboard.recruitment.periods.update', $period), [
                'banner' => UploadedFile::fake()->image('new.jpg', 800, 400),
            ])
            ->assertRedirect();

        $period->refresh();

        $this->assertNotSame('recruitment/banners/old.jpg', $period->banner);
        Storage::disk('public')->assertMissing('recruitment/banners/old.jpg');
        Storage::disk('public')->assertExists($period->banner);
    }

    public function test_update_tanpa_banner_tidak_mengubah_banner(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $period = RecruitmentPeriod::factory()->create([
            'created_by' => $admin->id,
            'banner' => 'recruitment/banners/keep.jpg',
        ]);
        Storage::disk('public')->put('recruitment/banners/keep.jpg', 'fake-keep');

        $this->actingAs($admin)
            ->patch(route('dashboard.recruitment.periods.update', $period), [
                'description' => 'Deskripsi diperbarui.',
            ])
            ->assertRedirect();

        $period->refresh();

        $this->assertSame('recruitment/banners/keep.jpg', $period->banner);
        Storage::disk('public')->assertExists('recruitment/banners/keep.jpg');
    }

    public function test_store_menolak_file_bukan_gambar(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec File Salah',
                'banner' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('banner');

        $this->assertDatabaseMissing('recruitment_periods', ['name' => 'OpRec File Salah']);
    }
}
