<?php

namespace Tests\Feature;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\ApplicationSubmitter;
use App\Services\Recruitment\RecruitmentRegistrationNumberIssuer;
use Database\Seeders\EventSeeder;
use Database\Seeders\OprecFormSeeder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OprecApplySubmitTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function seedBasics(): array
    {
        $this->seed([EventSeeder::class, RecruitmentDivisionSeeder::class, OprecFormSeeder::class]);

        $periodId = (string) Str::uuid();
        DB::table('recruitment_periods')->insert([
            'id' => $periodId,
            'name' => 'OPREC 2026',
            'slug' => 'oprec-2026',
            'status' => 'open',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $divisions = DB::table('recruitment_divisions')
            ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
            ->pluck('name')->all();

        $divisionId = DB::table('recruitment_divisions')
            ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
            ->value('id');

        return ['period_id' => $periodId, 'divisions' => $divisions, 'division_id' => $divisionId];
    }

    /** @return array<string, mixed> */
    private function servicePayload(string $divisionId, string $nim): array
    {
        return [
            'full_name' => 'Calon Anggota',
            'nim' => $nim,
            'semester' => 1,
            'phone' => '081234567890',
            'personal_email' => 'calon@gmail.com',
            'student_email' => 'calon@students.dinus.ac.id',
            'instagram_username' => 'calon',
            'primary_division_id' => $divisionId,
            'secondary_division_id' => null,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://example.com/portfolio',
            'twibbon_url' => 'https://instagram.com/p/twibbon-calon',
        ];
    }

    /** @return array<string, mixed> */
    private function validPayload(array $divisions): array
    {
        return [
            'full_name' => 'Calon Anggota',
            'nim' => 'A11.2026.99999',
            'semester' => '1',
            'phone' => '081234567890',
            'personal_email' => 'calon@gmail.com',
            'student_email' => 'calon@students.dinus.ac.id',
            'instagram_username' => 'calon.anggota',
            'primary_division_id' => $divisions[0],
            'secondary_division_id' => '',
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://example.com/portfolio',
            'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'instagram_follow_proof' => UploadedFile::fake()->image('follow.png', 640, 480),
            'twibbon_url' => 'https://instagram.com/p/twibbon-calon',
        ];
    }

    public function test_valid_payload_redirects_to_success_and_stores_application(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();

        $response = $this->post(route('recruitment.apply.store'), $this->validPayload($divisions));

        $response->assertRedirect(route('recruitment.success'));
        $this->assertDatabaseHas('recruitment_applications', [
            'nim' => 'A11.2026.99999',
            'full_name' => 'Calon Anggota',
        ]);
    }

    public function test_invalid_nim_format_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $payload = $this->validPayload($divisions);
        $payload['nim'] = 'NIM BURUK!';

        $response = $this->post(route('recruitment.apply.store'), $payload);

        $response->assertSessionHasErrors('nim');
    }

    public function test_duplicate_nim_in_same_period_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $this->post(route('recruitment.apply.store'), $this->validPayload($divisions));

        $response = $this->post(route('recruitment.apply.store'), $this->validPayload($divisions));

        $response->assertSessionHasErrors('nim');
    }

    public function test_same_primary_and_secondary_division_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $payload = $this->validPayload($divisions);
        $payload['secondary_division_id'] = $payload['primary_division_id'];

        $response = $this->post(route('recruitment.apply.store'), $payload);

        $response->assertSessionHasErrors('secondary_division_id');
    }

    public function test_nim_is_stored_in_uppercase(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $payload = $this->validPayload($divisions);
        $payload['nim'] = 'a11.2026.55555';

        $this->post(route('recruitment.apply.store'), $payload)
            ->assertRedirect(route('recruitment.success'));

        $this->assertDatabaseHas('recruitment_applications', ['nim' => 'A11.2026.55555']);
    }

    public function test_duplicate_nim_with_different_case_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();

        $this->post(route('recruitment.apply.store'), $this->validPayload($divisions))
            ->assertRedirect(route('recruitment.success'));

        $duplicate = $this->validPayload($divisions);
        $duplicate['nim'] = 'a11.2026.99999';

        $this->post(route('recruitment.apply.store'), $duplicate)
            ->assertSessionHasErrors('nim');

        $this->assertSame(1, RecruitmentApplication::query()->count());
    }

    public function test_nim_unique_conflict_is_reported_on_the_nim_field(): void
    {
        ['period_id' => $periodId, 'division_id' => $divisionId] = $this->seedBasics();
        Storage::fake('local');

        $period = RecruitmentPeriod::query()->findOrFail($periodId);

        RecruitmentApplication::query()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => 'OPREC-2026-00042',
            'tracking_token_hash' => bcrypt('token'),
            'full_name' => 'Pendaftar Lama',
            'nim' => 'A11.2026.99999',
            'semester' => 1,
            'phone' => '081234567890',
            'personal_email' => 'lama@gmail.com',
            'student_email' => 'lama@students.dinus.ac.id',
            'instagram_username' => 'lama',
            'primary_division_id' => $divisionId,
            'secondary_division_id' => null,
            'submitted_at' => now(),
        ]);

        try {
            app(ApplicationSubmitter::class)->submit(
                $period,
                $this->servicePayload($divisionId, 'A11.2026.99999'),
                UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
                null,
                UploadedFile::fake()->image('follow.jpg'),
            );
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nim', $exception->errors());
        }
    }

    public function test_registration_number_conflict_is_not_reported_on_the_nim_field(): void
    {
        ['period_id' => $periodId, 'division_id' => $divisionId] = $this->seedBasics();
        Storage::fake('local');

        $period = RecruitmentPeriod::query()->findOrFail($periodId);

        $issued = app(RecruitmentRegistrationNumberIssuer::class)->issue($period);
        DB::table('recruitment_registration_sequences')
            ->where('recruitment_period_id', $period->id)
            ->update(['last_sequence' => 0]);

        RecruitmentApplication::query()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => $issued,
            'tracking_token_hash' => bcrypt('token'),
            'full_name' => 'Pendaftar Lain',
            'nim' => 'A11.2026.88888',
            'semester' => 1,
            'phone' => '081234567891',
            'personal_email' => 'lain@gmail.com',
            'student_email' => 'lain@students.dinus.ac.id',
            'instagram_username' => 'lain',
            'primary_division_id' => $divisionId,
            'secondary_division_id' => null,
            'submitted_at' => now(),
        ]);

        try {
            app(ApplicationSubmitter::class)->submit(
                $period,
                $this->servicePayload($divisionId, 'A11.2026.77777'),
                UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
                null,
                UploadedFile::fake()->image('follow.jpg'),
            );
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_number', $exception->errors());
            $this->assertArrayNotHasKey('nim', $exception->errors());
        }
    }
}
