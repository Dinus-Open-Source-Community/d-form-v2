<?php

namespace Database\Factories\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecruitmentApplication>
 */
class RecruitmentApplicationFactory extends Factory
{
    protected $model = RecruitmentApplication::class;

    public function definition(): array
    {
        return [
            'recruitment_period_id' => RecruitmentPeriod::factory(),
            'registration_number' => 'OPREC-'.now()->year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'tracking_token_hash' => Hash::make('test-tracking-token-'.Str::random(16)),
            'full_name' => $this->faker->name(),
            'nim' => strtoupper($this->faker->unique()->bothify('A11.####.#####')),
            'semester' => $this->faker->numberBetween(1, 3),
            'phone' => '08'.$this->faker->numerify('##########'),
            'personal_email' => $this->faker->safeEmail(),
            'student_email' => $this->faker->userName().'@students.udinus.ac.id',
            'instagram_username' => $this->faker->userName(),
            'primary_division_id' => RecruitmentDivision::factory(),
            'secondary_division_id' => null,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'is_verified' => false,
            'revision_required' => false,
            'submitted_at' => now(),
        ];
    }

    public function withTrackingToken(string $plainToken): static
    {
        return $this->state(fn (): array => [
            'tracking_token_hash' => Hash::make($plainToken),
        ]);
    }
}
