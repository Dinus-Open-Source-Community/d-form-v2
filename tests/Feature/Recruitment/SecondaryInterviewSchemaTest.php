<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\EvaluationRecommendation;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecondaryInterviewSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_kind_and_save_count_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('recruitment_interviews', 'interview_kind'));
        $this->assertTrue(Schema::hasColumn('recruitment_evaluations', 'save_count'));
    }

    public function test_application_can_have_primary_and_secondary_interviews_but_not_two_primaries(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $period = RecruitmentPeriod::factory()->create();
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $data->id,
        ]);

        $primarySession = $this->makeSession($period->id, $programming->id);
        $secondarySession = $this->makeSession($period->id, $data->id);

        $primary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $primarySession->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab',
            'room' => 'A101',
            'status' => 'waiting',
        ]);

        $secondary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $secondarySession->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab',
            'room' => 'A102',
            'status' => 'waiting',
        ]);

        $this->assertSame(2, $application->interviews()->count());
        $this->assertSame($primary->id, $application->primaryInterview()->first()?->id);
        $this->assertSame($secondary->id, $application->secondaryInterview()->first()?->id);

        $this->expectException(QueryException::class);

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $primarySession->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab',
            'room' => 'A103',
            'status' => 'waiting',
        ]);
    }

    public function test_each_interview_has_at_most_one_evaluation(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $period = RecruitmentPeriod::factory()->create();
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $data->id,
        ]);

        $primary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->makeSession($period->id, $programming->id)->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab',
            'room' => 'A101',
            'status' => 'waiting',
        ]);

        $secondary = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $this->makeSession($period->id, $data->id)->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab',
            'room' => 'A102',
            'status' => 'waiting',
        ]);

        $evaluator = User::factory()->create();

        foreach ([$primary, $secondary] as $interview) {
            RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_id' => $interview->id,
                'speaking_score' => 8,
                'technical_score' => 7,
                'attitude_score' => 9,
                'recommendation' => EvaluationRecommendation::Recommended,
                'notes' => 'Komunikatif dan menguasai dasar divisi.',
                'evaluated_by' => $evaluator->id,
                'evaluated_at' => now(),
            ]);
        }

        $this->assertSame(2, $application->evaluations()->count());

        $this->expectException(QueryException::class);

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_id' => $primary->id,
            'speaking_score' => 5,
            'technical_score' => 5,
            'attitude_score' => 5,
            'recommendation' => EvaluationRecommendation::NotRecommended,
            'notes' => 'Evaluasi duplikat yang harus ditolak.',
            'evaluated_by' => $evaluator->id,
            'evaluated_at' => now(),
        ]);
    }

    private function makeSession(string $periodId, string $divisionId): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $periodId,
            'recruitment_division_id' => $divisionId,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }
}
