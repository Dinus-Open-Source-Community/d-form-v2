<?php

namespace Tests\Feature\Export;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Registration\FormAnswerFileStorage;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Byte-identity guard for the export CSV contracts.
 *
 * Fixtures are pinned (fixed ids, fixed timestamps, frozen clock, fixed page
 * size) so the streamed body is a pure function of the export contract: any
 * change in columns, order, BOM, CSV escaping, timezone formatting, or answer
 * summarization changes the hash. EP-04 refactored both controllers onto a
 * shared export seam and these hashes are the proof that the bytes did not move.
 *
 * Signed file URLs embed the scheme, host, expiry and a signature hashed over
 * both, so their bytes are a function of APP_URL rather than of the export
 * contract. The registration hash is therefore taken over a body where the
 * app-generated signed URL is replaced by a token that keeps its route path;
 * the URL itself is proved separately against the same generator the app uses.
 * This keeps the suite green under any APP_URL without loosening the columns,
 * order or payload comparison.
 */
class ExportCsvByteIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const FORM_ID = '11111111-1111-4111-8111-111111111111';

    private const LEADER_ANSWER_ID = '22222222-2222-4222-8222-222222222222';

    private const MEMBER_ANSWER_ID = '33333333-3333-4333-8333-333333333333';

    private const LEGACY_ANSWER_ID = '44444444-4444-4444-8444-444444444444';

    private const REGISTRATION_CSV_SHA256 = '5aa9fef7c9a18bd82ec1d3b583709a2e79e07236befa496aa6621798a353888b';

    private const INTERVIEW_CSV_SHA256 = '0e8c5f109e08dd6a33f9df906191b5697b9e10a1673d9bbc5fc999e43476c53c';

    private const APPLICANTS_CSV_SHA256 = 'c67d9965b2ef101dce84066b36d8464d696fbae1d49b33790c78697d2852d1df';

    public function test_registration_export_csv_bytes_are_stable(): void
    {
        $this->travelTo(Carbon::parse('2026-05-10 09:00:00'));
        config(['registration.event_export_page_size' => 2]);
        $this->seed(RoleSeeder::class);

        $admin = $this->admin();

        $event = Event::factory()->create([
            'created_by' => $admin->id,
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        Form::factory()->create([
            'id' => self::FORM_ID,
            'event_id' => $event->id,
            'title' => 'Fixture Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        foreach ([
            ['name' => 'full_name', 'label' => 'Nama lengkap', 'order' => 1, 'input_type' => 'input'],
            ['name' => 'skills', 'label' => 'Keahlian', 'order' => 2, 'input_type' => 'checkbox'],
            ['name' => 'payment_proof', 'label' => 'Bukti pembayaran', 'order' => 3, 'input_type' => 'fileUpload'],
        ] as $field) {
            FormField::factory()->create([
                ...$field,
                'form_id' => self::FORM_ID,
                'metadata' => [],
            ]);
        }

        $leader = User::factory()->create(['name' => 'Ketua Fixture', 'email' => 'ketua@example.test']);
        $member = User::factory()->create(['name' => 'Anggota Fixture', 'email' => 'anggota@example.test']);
        $legacyUser = User::factory()->create(['name' => 'Legacy Fixture', 'email' => 'legacy@example.test']);

        $leaderAnswer = FormAnswer::factory()->create([
            'id' => self::LEADER_ANSWER_ID,
            'form_id' => self::FORM_ID,
            'user_id' => $leader->id,
            'created_at' => '2026-05-10 08:00:00',
            'answers' => [
                'full_name' => 'Ketua Fixture',
                'skills' => ['PHP', 'SQL'],
                'payment_proof' => 'form-uploads/'.self::FORM_ID.'/bukti.png',
            ],
            'registration_role' => RegistrationRole::Leader,
            'member_confirmation_status' => MemberConfirmationStatus::Accepted,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'REG-0001',
            'group_token' => 'group-fixture',
        ]);

        FormAnswer::factory()->create([
            'id' => self::MEMBER_ANSWER_ID,
            'form_id' => self::FORM_ID,
            'user_id' => $member->id,
            'created_at' => '2026-05-10 08:00:00',
            'leader_form_answer_id' => $leaderAnswer->id,
            'answers' => ['full_name' => 'Anggota Fixture'],
            'registration_role' => RegistrationRole::Member,
            'member_confirmation_status' => MemberConfirmationStatus::Pending,
            'review_status' => FormAnswerReviewStatus::Pending,
        ]);

        $legacyAnswer = FormAnswer::factory()->create([
            'id' => self::LEGACY_ANSWER_ID,
            'form_id' => self::FORM_ID,
            'user_id' => $legacyUser->id,
            'created_at' => '2026-05-10 07:00:00',
            'answers' => ['full_name' => 'Baris Legacy'],
        ]);

        // `created_at` is only nullable for legacy/manual rows; the export tail pass
        // must still write them.
        DB::table('form_answers')->where('id', $legacyAnswer->id)->update(['created_at' => null]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();

        $content = $response->streamedContent();
        self::assertIsString($content);

        $content = $this->replaceSignedFileUrl($content, $leaderAnswer, 'payment_proof');

        $hash = hash('sha256', $content);

        self::assertSame(self::REGISTRATION_CSV_SHA256, $hash, 'Registration CSV bytes changed: '.$hash);
    }

    public function test_interview_export_csv_bytes_are_stable(): void
    {
        $this->travelTo(Carbon::parse('2026-05-10 09:00:00'));

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create(['name' => 'Staff Fixture', 'email' => 'staff@example.test']);
        $staff->assignRole('recruitment-staff');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $period = RecruitmentPeriod::factory()->create([
            'created_by' => $staff->id,
            'slug' => 'fixture-period',
        ]);

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => '2026-05-10',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        $evaluated = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => 'OPREC-2026-00001',
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $dataDivision->id,
            'full_name' => 'Pemohon Satu',
            'nim' => 'A11.2026.00001',
            'semester' => 4,
            'phone' => '081234567890',
            'personal_email' => 'satu@example.test',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $firstInterview = RecruitmentInterview::query()->create([
            'recruitment_application_id' => $evaluated->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => RecruitmentInterview::KIND_PRIMARY,
            'interviewer_id' => $staff->id,
            'scheduled_at' => '2026-05-10 09:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Completed,
        ]);

        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $evaluated->id,
            'recruitment_interview_id' => $firstInterview->id,
            'speaking_score' => 8,
            'technical_score' => 9,
            'attitude_score' => 8,
            'recommendation' => EvaluationRecommendation::Recommended,
            'notes' => 'Catatan evaluasi fixture.',
            'save_count' => 1,
            'evaluated_by' => $staff->id,
            'evaluated_at' => '2026-05-10 10:00:00',
        ]);

        RecruitmentAttendance::query()->create([
            'recruitment_application_id' => $evaluated->id,
            'recruitment_interview_session_id' => $session->id,
            'method' => AttendanceMethod::RegistrationNumber,
            'checked_in_at' => '2026-05-10 09:05:00',
            'checked_in_by' => $staff->id,
        ]);

        RecruitmentFinalDecision::query()->create([
            'recruitment_application_id' => $evaluated->id,
            'membership_type' => 'member',
            'final_division_id' => $dataDivision->id,
            'decided_by' => $staff->id,
            'decided_at' => '2026-05-10 11:00:00',
        ]);

        $pending = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => 'OPREC-2026-00002',
            'primary_division_id' => $dataDivision->id,
            'full_name' => 'Pemohon Dua',
            'nim' => 'A11.2026.00002',
            'semester' => 2,
            'phone' => '081234567891',
            'personal_email' => 'dua@example.test',
            'stage' => ApplicationStage::Screening,
            'result' => ApplicationResult::Accepted,
        ]);

        RecruitmentInterview::query()->create([
            'recruitment_application_id' => $pending->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            'interviewer_id' => $staff->id,
            'scheduled_at' => '2026-05-10 10:00:00',
            'location' => 'Gedung A',
            'room' => 'R-201',
            'status' => InterviewStatus::Waiting,
        ]);

        $response = $this->actingAs($staff)
            ->get(route('dashboard.recruitment.periods.interviews.export', $period));

        $response->assertOk();

        $content = $response->streamedContent();
        self::assertIsString($content);

        $hash = hash('sha256', $content);

        self::assertSame(self::INTERVIEW_CSV_SHA256, $hash, 'Interview CSV bytes changed: '.$hash);
    }

    public function test_applicants_export_csv_bytes_are_stable(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $admin = User::factory()->create(['name' => 'Admin Fixture', 'email' => 'admin@example.test']);
        $admin->assignRole('super-admin');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();

        $period = RecruitmentPeriod::factory()->create([
            'created_by' => $admin->id,
            'slug' => 'fixture-period',
        ]);

        $removedDivision = RecruitmentDivision::factory()->create(['name' => 'Divisi Terhapus']);
        $removedDivision->delete();

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => 'OPREC-2026-00002',
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $dataDivision->id,
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2026.00002',
            'semester' => 3,
            'submitted_at' => '2026-02-03 04:05:06',
            'stage' => ApplicationStage::Completed,
            'result' => ApplicationResult::Accepted,
        ]);

        RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'registration_number' => 'OPREC-2026-00001',
            'primary_division_id' => $removedDivision->id,
            'secondary_division_id' => null,
            'full_name' => 'Ani Wijaya',
            'nim' => 'A11.2026.00001',
            'semester' => 1,
            'submitted_at' => '2026-01-02 03:04:05',
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.recruitment.reports.export.applicants', ['period_id' => $period->id]));

        $response->assertOk();

        $content = $response->streamedContent();
        self::assertIsString($content);

        $hash = hash('sha256', $content);

        self::assertSame(self::APPLICANTS_CSV_SHA256, $hash, 'Applicants CSV bytes changed: '.$hash);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Fixture', 'email' => 'admin@example.test']);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Swap the app-generated signed file URL for a stable token.
     *
     * Regenerating the URL through the same seam the export uses means a drift
     * fails loudly instead of silently skipping the substitution. The token keeps
     * the route path (still byte compared), while the raw body must still carry
     * exactly one expiry and signature pair so an unsigned or dropped link cannot
     * hide behind the normalization.
     */
    private function replaceSignedFileUrl(string $content, FormAnswer $submission, string $field): string
    {
        $signedUrl = app(FormAnswerFileStorage::class)->temporaryUrl($submission, $field);

        self::assertSame(1, substr_count($content, $signedUrl), 'Export must embed the signed file URL generated for this host.');
        self::assertSame(1, substr_count($content, 'expires='), 'Export must emit exactly one signed URL expiry.');
        self::assertSame(1, substr_count($content, 'signature='), 'Export must emit exactly one signed URL signature.');

        $path = (string) parse_url($signedUrl, PHP_URL_PATH);

        return str_replace($signedUrl, '<<signed-file-url:'.$path.'>>', $content);
    }
}
