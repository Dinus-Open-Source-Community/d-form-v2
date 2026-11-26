<?php

namespace Tests\Feature\Forms;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\DuRedeemIssuance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Form\FormAnswerReviewService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

final class DuRedeemIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_du_accept_does_not_issue(): void
    {
        Http::fake();
        [$event, $form, $answer] = $this->answer(['metadata' => ['registration_mode' => 'single']]);
        app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());
        Http::assertNothingSent();
        $this->assertDatabaseMissing('du_redeem_issuances', ['form_answer_id' => $answer->id]);
    }

    public function test_success_persists_issued_code_after_local_acceptance(): void
    {
        $this->seedCourse('course-test', 'Test');
        $this->fakeRedeem(201);
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);
        $result = app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());
        $this->assertSame(FormAnswerReviewStatus::Accepted, $answer->refresh()->review_status);
        $this->assertSame(DuRedeemStatus::Issued, $result['du_issuance']->status);
        $this->assertDatabaseHas('du_redeem_issuances', ['form_answer_id' => $answer->id, 'status' => 'issued', 'redeem_code' => 'safe-test-code']);
        Http::assertSentCount(1);
    }

    public function test_success_persists_the_full_redeem_response(): void
    {
        $this->seedCourse('course-a', 'Kelas A');
        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'uid' => '7e484e3e',
            'code' => 'DU-X7K2-9B4Z',
            'course_uid' => 'course-a',
            'course_title' => 'Kelas A',
            'target_email' => 'buyer@example.test',
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
            'expires_at' => '2027-01-31T23:59:59Z',
            'created_at' => '2026-11-08T10:00:00Z',
        ]], 201)]);
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']], 'Kelas A');

        app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());

        $this->assertDatabaseHas('du_redeem_issuances', [
            'form_answer_id' => $answer->id,
            'redeem_code' => 'DU-X7K2-9B4Z',
            'du_uid' => '7e484e3e',
            'course_uid' => 'course-a',
            'course_title' => 'Kelas A',
            'target_email' => 'buyer@example.test',
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail();
        $this->assertSame('7e484e3e', $issuance->response_payload['uid'] ?? null);
        $this->assertNotNull($issuance->expires_at);
        $this->assertTrue($issuance->expires_at->equalTo(Carbon::parse('2027-01-31T23:59:59Z')));
        $this->assertNotNull($issuance->du_created_at);
        $this->assertTrue($issuance->du_created_at->equalTo(Carbon::parse('2026-11-08T10:00:00Z')));
    }

    public function test_persists_the_response_course_uid_when_it_differs_from_the_request(): void
    {
        $this->seedCourse('course-test', 'Test');
        $this->fakeRedeem(201, 'course-canonical');
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);

        $result = app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());

        $this->assertSame(DuRedeemStatus::Issued, $result['du_issuance']->status);
        $this->assertDatabaseHas('du_redeem_issuances', ['form_answer_id' => $answer->id, 'course_uid' => 'course-canonical']);
        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail();
        $this->assertSame('course-canonical', $issuance->response_payload['course_uid'] ?? null);
        Http::assertSent(fn (Request $request): bool => $request['course_uid'] === 'course-test');
    }

    public function test_malformed_response_dates_persist_as_null(): void
    {
        $this->seedCourse('course-test', 'Test');
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => array_merge(DuRedeemFixture::success('safe-test-code'), ['expires_at' => 'not-a-date', 'created_at' => 'also-broken']),
        ], 201)]);
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);

        $result = app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());

        $this->assertSame(DuRedeemStatus::Issued, $result['du_issuance']->status);
        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->firstOrFail();
        $this->assertNull($issuance->expires_at);
        $this->assertNull($issuance->du_created_at);
    }

    public function test_existing_issuance_without_response_columns_still_reads(): void
    {
        $answer = FormAnswer::factory()->create(['review_status' => FormAnswerReviewStatus::Accepted]);
        DuRedeemIssuance::query()->create([
            'form_answer_id' => $answer->id,
            'status' => DuRedeemStatus::Issued,
            'redeem_code' => 'DU-OLD',
        ]);

        $issuance = $answer->refresh()->duRedeemIssuance;

        $this->assertNotNull($issuance);
        $this->assertSame('DU-OLD', $issuance->redeem_code);
        $this->assertNull($issuance->du_uid);
        $this->assertNull($issuance->response_payload);
    }

    public function test_du_failure_keeps_local_acceptance(): void
    {
        $this->seedCourse('course-test', 'Test');
        $this->fakeRedeem(422);
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);
        app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());
        $this->assertSame(FormAnswerReviewStatus::Accepted, $answer->refresh()->review_status);
        $this->assertNotNull($answer->registration_code);
        $this->assertDatabaseHas('du_redeem_issuances', ['form_answer_id' => $answer->id, 'status' => 'failed', 'course_uid' => 'course-test']);
    }

    public function test_issuance_resolves_a_stored_title_to_the_cached_uid(): void
    {
        $this->seedCourse('course-a', 'Kelas A');
        $this->fakeRedeem(201, 'course-a');
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']], 'Kelas A');

        $result = app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());

        $this->assertSame(DuRedeemStatus::Issued, $result['du_issuance']->status);
        $this->assertDatabaseHas('du_redeem_issuances', ['form_answer_id' => $answer->id, 'course_uid' => 'course-a']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/external/redeem-codes')
            && $request['course_uid'] === 'course-a');
        Http::assertSentCount(1);
    }

    public function test_issuance_fails_closed_when_cached_course_is_missing(): void
    {
        Http::fake();
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);

        $result = app(FormAnswerReviewService::class)->reviewOne($event, $form, $answer, FormAnswerReviewStatus::Accepted, User::factory()->create());

        $this->assertSame(DuRedeemStatus::Failed, $result['du_issuance']->status);
        $this->assertSame('Kelas DU tidak ditemukan. Jalankan sync kelas lalu coba lagi.', $result['du_issuance']->message);
        $this->assertDatabaseHas('du_redeem_issuances', ['form_answer_id' => $answer->id, 'status' => 'failed']);
        Http::assertNothingSent();
    }

    public function test_bulk_du_review_is_rejected_for_both_decisions(): void
    {
        [$event, $form, $answer] = $this->answer(['metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single']]);
        foreach ([FormAnswerReviewStatus::Accepted, FormAnswerReviewStatus::Rejected] as $status) {
            try {
                app(FormAnswerReviewService::class)->reviewMany($event, $form, new Collection([$answer->id]), $status, User::factory()->create());
                self::fail('D-Kelas bulk review should fail.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                self::assertSame(422, $exception->getStatusCode());
            }
        }
    }

    public function test_manual_route_cannot_issue_answer_from_another_form(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $form = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        $otherForm = Form::factory()->create(['event_id' => $event->id, 'metadata' => ['purpose' => 'd_kelas']]);
        $answer = FormAnswer::factory()->create(['form_id' => $otherForm->id, 'review_status' => FormAnswerReviewStatus::Accepted]);
        $this->actingAs($admin)->postJson(route('dashboard.events.forms.submissions.du-issuance', ['event' => $event, 'form' => $form, 'formAnswer' => $answer]), [])->assertNotFound();
    }

    /** @return array{0: Event, 1: Form, 2: FormAnswer} */
    private function answer(array $formAttributes = [], string $storedCourseValue = 'course-test'): array
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(array_merge(['event_id' => $event->id], $formAttributes));
        $answer = FormAnswer::factory()->create(['form_id' => $form->id, 'answers' => ['du_course_uid' => $storedCourseValue], 'review_status' => FormAnswerReviewStatus::Pending]);
        return [$event, $form, $answer];
    }

    private function seedCourse(string $uid, string $title): void
    {
        DuCourse::factory()->create(['uid' => $uid, 'title' => $title, 'is_active' => true]);
    }

    private function fakeRedeem(int $status, string $courseUid = 'course-test'): void
    {
        Http::fake(fn () => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('safe-test-code', $courseUid),
        ], $status));
    }
}
