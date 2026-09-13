<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RecruitmentEmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentPublicApplyTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $openPeriod;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $dataDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        $this->seed(RecruitmentEmailTemplateSeeder::class);

        Storage::fake('local');
        Queue::fake();

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $this->openPeriod = RecruitmentPeriod::factory()->open()->create([
            'name' => 'OpRec 2026',
        ]);

        RecruitmentRegistrationSequence::query()->create([
            'recruitment_period_id' => $this->openPeriod->id,
            'last_sequence' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2024.01234',
            'semester' => 2,
            'phone' => '081234567890',
            'personal_email' => 'budi@gmail.com',
            'student_email' => 'budi@students.udinus.ac.id',
            'instagram_username' => 'budisantoso',
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => $this->dataDivision->id,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/budi',
            'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ], $overrides);
    }

    public function test_guest_can_view_landing_page(): void
    {
        $this->get(route('open-recruitment.landing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Landing')
                ->where('registration.is_open', true));
    }

    public function test_submit_valid_application_during_open_period(): void
    {
        $response = $this->post(route('open-recruitment.apply.store'), $this->validPayload());

        $response->assertRedirect(route('open-recruitment.success'));

        $application = RecruitmentApplication::query()->where('nim', 'A11.2024.01234')->first();
        $this->assertNotNull($application);
        $this->assertMatchesRegularExpression('/^OPREC-\d{4}-\d{5}$/', $application->registration_number);

        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class);

        Storage::disk('local')->assertExists($application->document()->firstOrFail()->cv_path);
    }

    public function test_submit_duplicate_nim_same_period_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload())->assertRedirect();

        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'personal_email' => 'other@gmail.com',
        ]))
            ->assertSessionHasErrors('nim');
    }

    public function test_submit_when_period_closed_is_rejected(): void
    {
        $this->openPeriod->update(['status' => RecruitmentPeriodStatus::Closed]);

        $this->post(route('open-recruitment.apply.store'), $this->validPayload())
            ->assertSessionHasErrors('period');
    }

    public function test_submit_semester_four_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'semester' => 4,
        ]))->assertSessionHasErrors('semester');
    }

    public function test_submit_secondary_division_same_as_primary_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'secondary_division_id' => $this->programming->id,
        ]))->assertSessionHasErrors('secondary_division_id');
    }

    public function test_submit_cv_non_pdf_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'cv' => UploadedFile::fake()->create('cv.docx', 120, 'application/msword'),
        ]))->assertSessionHasErrors('cv');
    }

    public function test_submit_portfolio_url_is_stored(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload())->assertRedirect();

        $document = RecruitmentApplication::query()->firstOrFail()->document()->firstOrFail();
        $this->assertSame('url', $document->portfolio_type);
        $this->assertSame('https://portfolio.example.com/budi', $document->portfolio_url);
    }

    public function test_submit_portfolio_pdf_file_is_stored(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'portfolio_type' => 'file',
            'portfolio_url' => null,
            'portfolio_file' => UploadedFile::fake()->create('portfolio.pdf', 100, 'application/pdf'),
        ]))->assertRedirect();

        $document = RecruitmentApplication::query()->firstOrFail()->document()->firstOrFail();
        $this->assertSame('file', $document->portfolio_type);
        $this->assertNotNull($document->portfolio_path);
        Storage::disk('local')->assertExists($document->portfolio_path);
    }

    public function test_submit_without_required_fields_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), [])
            ->assertSessionHasErrors([
                'full_name',
                'nim',
                'semester',
                'phone',
                'personal_email',
                'student_email',
                'instagram_username',
                'primary_division_id',
                'portfolio_type',
                'cv',
            ]);
    }

    public function test_registration_number_format_matches_spec(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'nim' => 'A11.2024.09999',
        ]))->assertRedirect();

        $number = RecruitmentApplication::query()->where('nim', 'A11.2024.09999')->value('registration_number');
        $this->assertMatchesRegularExpression('/^OPREC-2026-00001$/', $number);
    }

    public function test_success_page_shows_registration_number_once(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload());

        $application = RecruitmentApplication::query()->firstOrFail();

        $this->get(route('open-recruitment.success'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Success')
                ->where('registrationNumber', $application->registration_number));
    }
}
