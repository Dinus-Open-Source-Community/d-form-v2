<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecruitmentWhatsappGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    public function test_update_period_whatsapp_link_returns_redirect(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($admin)
            ->put(route('dashboard.recruitment.periods.update', $period), [
                'name' => $period->name,
                'whatsapp_group_url' => 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            ])
            ->assertRedirect();

        $this->assertSame(
            'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            $period->refresh()->whatsapp_group_url
        );
    }

    public function test_passed_screening_email_contains_whatsapp_link_when_period_has_one(): void
    {
        Mail::fake();

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create([
            'whatsapp_group_url' => 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
        ]);
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertRedirect();

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt')
                && str_contains($mail->bodyHtml, 'Gabung Grup WA');
        });
    }

    public function test_passed_screening_email_omits_whatsapp_block_without_link(): void
    {
        Mail::fake();

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create(['whatsapp_group_url' => null]);
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertRedirect();

        Mail::assertNotSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, 'Gabung Grup WA');
        });
    }

    public function test_period_show_props_contain_saved_whatsapp_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create([
            'whatsapp_group_url' => 'https://chat.whatsapp.com/XyZ1234567890AbCdEfGh',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $period->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period.whatsapp_group_url', 'https://chat.whatsapp.com/XyZ1234567890AbCdEfGh'));
    }

    public function test_store_whatsapp_link_with_tags_and_whitespace_is_normalized(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec WA Normalize',
                'whatsapp_group_url' => "  <b>https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt</b>\n",
            ])
            ->assertRedirect();

        $period = RecruitmentPeriod::query()->where('name', 'OpRec WA Normalize')->firstOrFail();

        $this->assertSame(
            'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            $period->whatsapp_group_url
        );
    }

    public function test_update_whatsapp_link_with_control_chars_is_normalized(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($admin)
            ->put(route('dashboard.recruitment.periods.update', $period), [
                'name' => $period->name,
                'whatsapp_group_url' => "https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt\x00\x1F  ",
            ])
            ->assertRedirect();

        $this->assertSame(
            'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            $period->refresh()->whatsapp_group_url
        );
    }

    public function test_store_rejects_non_https_whatsapp_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec WA Bad',
                'whatsapp_group_url' => 'http://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp_group_url');
    }

    public function test_update_rejects_non_https_whatsapp_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $period = RecruitmentPeriod::factory()->create();

        $this->actingAs($admin)
            ->putJson(route('dashboard.recruitment.periods.update', $period), [
                'name' => $period->name,
                'whatsapp_group_url' => 'http://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp_group_url');
    }
}
