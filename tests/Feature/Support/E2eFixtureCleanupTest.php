<?php

namespace Tests\Feature\Support;

use App\Enums\EmailLogStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Support\E2e\FixtureIdentity;
use App\Support\E2e\FixtureScope;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi FE-AUDIT-004-F5: review submission membuat `email_logs` yang
 * mereferensikan event/form_answer/user, sehingga cleanup harus menghapus
 * anak tersebut lebih dulu. Test ini boot DB + HTTP, jadi berada di
 * `tests/Feature/Support`.
 */
class E2eFixtureCleanupTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'e2e+20260102T030405-abcdef+o0123456789ab';

    public function test_cleanup_succeeds_and_removes_all_rows_after_submission_review(): void
    {
        $this->seed(RoleSeeder::class);

        $identity = FixtureIdentity::fromMarker(self::MARKER);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $owner = User::factory()->create(['email' => $identity->email()]);

        $event = Event::factory()->create([
            'slug' => $identity->slug(),
            'created_by' => $owner->id,
        ]);

        // Event model regenerates the slug from the title on save; the e2e fixture
        // inserts rows directly, so pin the marker slug the same way.
        DB::table('events')->where('id', $event->id)->update(['slug' => $identity->slug()]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => $identity->suffixed('Form 1'),
            'metadata' => ['marker' => self::MARKER],
            'created_by' => $owner->id,
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => $identity->suffixed('Field 1'),
        ]);

        $submission = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.forms.submissions.review', [
                'event' => $event->id,
                'form' => $form->id,
                'formAnswer' => $submission->id,
            ]), ['review_status' => FormAnswerReviewStatus::Accepted->value])
            ->assertOk();

        $this->assertSame(1, EmailLog::query()
            ->where('event_id', $event->id)
            ->where('form_answer_id', $submission->id)
            ->count());

        // Log tanpa event (event_id NULL) tetap milik marker lewat user_id; log milik
        // user non-marker tidak boleh ikut terhapus.
        $otherUser = User::factory()->create();

        $markerUserLog = EmailLog::query()->create([
            'event_id' => null,
            'user_id' => $owner->id,
            'recipient_email' => $owner->email,
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
        ]);

        $foreignUserLog = EmailLog::query()->create([
            'event_id' => null,
            'user_id' => $otherUser->id,
            'recipient_email' => $otherUser->email,
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->artisan('e2e:cleanup', [
            '--marker' => self::MARKER,
            '--slug' => $identity->slug(),
        ])->assertSuccessful();

        foreach (FixtureScope::forMarker(self::MARKER)->counts() as $table => $count) {
            $this->assertSame(0, $count, "table {$table} must be empty after cleanup");
        }

        $this->assertSame(0, EmailLog::query()->where('event_id', $event->id)->count());
        $this->assertSame(0, EmailLog::query()->whereKey($markerUserLog->id)->count());
        $this->assertSame(1, EmailLog::query()->whereKey($foreignUserLog->id)->count());
    }
}
