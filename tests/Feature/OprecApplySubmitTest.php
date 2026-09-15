<?php

namespace Tests\Feature;

use Database\Seeders\EventSeeder;
use Database\Seeders\OprecFormSeeder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        return ['period_id' => $periodId, 'divisions' => $divisions];
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
        ];
    }

    public function test_valid_payload_redirects_to_success_and_stores_application(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();

        $response = $this->post(route('open-recruitment.apply.store'), $this->validPayload($divisions));

        $response->assertRedirect(route('open-recruitment.success'));
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

        $response = $this->post(route('open-recruitment.apply.store'), $payload);

        $response->assertSessionHasErrors('nim');
    }

    public function test_duplicate_nim_in_same_period_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $this->post(route('open-recruitment.apply.store'), $this->validPayload($divisions));

        $response = $this->post(route('open-recruitment.apply.store'), $this->validPayload($divisions));

        $response->assertSessionHasErrors('nim');
    }

    public function test_same_primary_and_secondary_division_is_rejected(): void
    {
        ['divisions' => $divisions] = $this->seedBasics();
        $payload = $this->validPayload($divisions);
        $payload['secondary_division_id'] = $payload['primary_division_id'];

        $response = $this->post(route('open-recruitment.apply.store'), $payload);

        $response->assertSessionHasErrors('secondary_division_id');
    }
}
