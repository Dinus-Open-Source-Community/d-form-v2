<?php

namespace Tests\Feature\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

/**
 * Throttle proof for the manual DU issuance endpoint. The limiter key includes the form answer,
 * so retrying one submission never consumes another submission's quota; it is also scoped per
 * authenticated user.
 */
final class DuRedeemThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Rate limiter buckets live in the cache store; clear them so scenarios never leak.
        Cache::flush();

        $this->fakeSuccessfulDuIssuance();
    }

    public function test_du_issuance_route_rejects_the_request_after_the_configured_limit(): void
    {
        $admin = $this->admin();
        [$event, $form, $answer] = $this->duAnswer($admin);
        $url = $this->issueUrl($event, $form, $answer);
        $limit = (int) config('events.forms.du_issuance_rate_limit_per_minute', 10);

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->actingAs($admin)->postJson($url, [])->assertOk();
        }

        $this->actingAs($admin)
            ->postJson($url, [])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(1, $this->redeemApiCallCount());
    }

    public function test_du_issuance_quota_is_scoped_per_form_answer(): void
    {
        $admin = $this->admin();
        [$event, $form, $answerA] = $this->duAnswer($admin);
        $answerB = $this->acceptedAnswer($form, 'du-throttle-b@example.test');
        $limit = (int) config('events.forms.du_issuance_rate_limit_per_minute', 10);

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->actingAs($admin)->postJson($this->issueUrl($event, $form, $answerA), [])->assertOk();
        }

        $this->actingAs($admin)
            ->postJson($this->issueUrl($event, $form, $answerA), [])
            ->assertStatus(429);

        // A different submission in the same form has its own bucket, so B reaches DU too.
        $this->actingAs($admin)
            ->postJson($this->issueUrl($event, $form, $answerB), [])
            ->assertOk();

        $this->assertSame(2, $this->redeemApiCallCount());
    }

    public function test_du_issuance_quota_is_not_shared_between_admins(): void
    {
        $blocked = $this->admin();
        $other = $this->admin();
        // created_by null so both admins pass the event update policy.
        [$event, $form, $answer] = $this->duAnswer(null);
        $url = $this->issueUrl($event, $form, $answer);
        $limit = (int) config('events.forms.du_issuance_rate_limit_per_minute', 10);

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->actingAs($blocked)->postJson($url, [])->assertOk();
        }

        $this->actingAs($blocked)->postJson($url, [])->assertStatus(429);

        $this->actingAs($other)->postJson($url, [])->assertOk();

        $this->assertSame(1, $this->redeemApiCallCount());
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array{0: Event, 1: Form, 2: FormAnswer} */
    private function duAnswer(?User $owner): array
    {
        $event = Event::factory()->create(['created_by' => $owner?->id]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'metadata' => ['purpose' => 'd_kelas', 'registration_mode' => 'single'],
        ]);

        return [$event, $form, $this->acceptedAnswer($form, 'du-throttle@example.test')];
    }

    private function acceptedAnswer(Form $form, string $email): FormAnswer
    {
        return FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => User::factory()->create(['email' => $email])->id,
            'answers' => ['du_course_uid' => 'course-test'],
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);
    }

    private function issueUrl(Event $event, Form $form, FormAnswer $answer): string
    {
        return route('dashboard.events.forms.submissions.du-issuance', [
            'event' => $event,
            'form' => $form,
            'formAnswer' => $answer,
        ]);
    }

    private function redeemApiCallCount(): int
    {
        $count = 0;

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/api/external/redeem-codes')) {
                $count++;
            }
        }

        return $count;
    }

    private function fakeSuccessfulDuIssuance(): void
    {
        DuCourse::factory()->create(['uid' => 'course-test', 'title' => 'Test', 'is_active' => true]);

        Http::fake(fn () => Http::response([
            'success' => true,
            'data' => DuRedeemFixture::success('safe-test-code'),
        ], 201));
    }
}
