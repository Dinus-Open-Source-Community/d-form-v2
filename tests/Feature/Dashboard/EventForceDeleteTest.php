<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EmailLogStatus;
use App\Enums\FormStatus;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Event\EventService;
use App\Services\Registration\FormAnswerFileStorage;
use App\Support\LegacyFormImage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Hapus permanen acara terarsip: seluruh child row, berkas storage, dan
 * agregat harian ikut dibersihkan tanpa menyentuh acara lain.
 */
class EventForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWER_FILE = 'jawaban.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Acara terarsip lengkap: satu form published, satu field, satu jawaban,
     * satu email log, satu attendance, dan berkas banner + jawaban di storage.
     *
     * @return array{0: Event, 1: Form, 2: FormAnswer}
     */
    private function archivedEventWithData(User $owner): array
    {
        Storage::fake('public');
        Storage::fake('local');

        $event = Event::factory()->create(['created_by' => $owner->id]);
        Storage::disk('public')->put((string) $event->banner, 'banner-bytes');

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => FormStatus::Published,
        ]);

        FormField::factory()->create(['form_id' => $form->id]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
        ]);

        EmailLog::query()->create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'recipient_email' => $owner->email,
            'status' => EmailLogStatus::Sent,
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_at' => now(),
        ]);

        // Jawaban bisa berada di local (baru) maupun public (legacy), dan banner
        // form legacy disimpan di form-banners/{formId} pada disk public.
        Storage::disk('local')->put(FormAnswerFileStorage::DIRECTORY_PREFIX."/{$form->id}/".self::ANSWER_FILE, 'answer-bytes');
        Storage::disk('public')->put(FormAnswerFileStorage::DIRECTORY_PREFIX."/{$form->id}/legacy.pdf", 'legacy-answer-bytes');
        Storage::disk('public')->put(LegacyFormImage::FIELD_DIRECTORY_PREFIX."/{$form->id}/cover.png", 'image-bytes');
        Storage::disk('public')->put(LegacyFormImage::BANNER_DIRECTORY_PREFIX."/{$form->id}/banner.png", 'form-banner-bytes');

        $event->delete();

        return [$event, $form, $answer];
    }

    public function test_owner_can_force_delete_archived_event_and_every_child_row(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();
        [$event, $form, $answer] = $this->archivedEventWithData($admin);
        $banner = $event->banner;

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
        $this->assertDatabaseMissing('form_fields', ['form_id' => $form->id]);
        $this->assertDatabaseMissing('form_answers', ['id' => $answer->id]);
        $this->assertDatabaseMissing('event_attendances', ['event_id' => $event->id]);
        $this->assertDatabaseMissing('email_logs', ['event_id' => $event->id]);
        Storage::disk('public')->assertMissing($banner);
        Storage::disk('public')->assertMissing(LegacyFormImage::FIELD_DIRECTORY_PREFIX."/{$form->id}/cover.png");
        Storage::disk('public')->assertMissing(LegacyFormImage::BANNER_DIRECTORY_PREFIX."/{$form->id}/banner.png");
        Storage::disk('public')->assertMissing(FormAnswerFileStorage::DIRECTORY_PREFIX."/{$form->id}/legacy.pdf");
        Storage::disk('local')->assertMissing(
            FormAnswerFileStorage::DIRECTORY_PREFIX."/{$form->id}/".self::ANSWER_FILE
        );
    }

    public function test_non_owner_admin_gets_403_and_nothing_is_deleted(): void
    {
        $owner = $this->admin();
        $intruder = $this->admin();
        [$event] = $this->archivedEventWithData($owner);

        $this->actingAs($intruder)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertForbidden();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_super_admin_can_force_delete_another_admins_archived_event(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        [$event] = $this->archivedEventWithData($this->admin());

        $this->actingAs($superAdmin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_active_event_cannot_be_force_deleted(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertStatus(409);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_second_force_delete_returns_404_without_decrementing_aggregate_again(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);
        $today = now()->toDateString();

        // Surplus sintetis agar decrement kedua terlihat: 10 - 1 = 9.
        DB::table('event_daily_registrations')
            ->where('registration_date', $today)
            ->update(['count' => 10]);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertSame(9, (int) DB::table('event_daily_registrations')
            ->where('registration_date', $today)
            ->value('count'));

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertNotFound();

        $this->assertSame(9, (int) DB::table('event_daily_registrations')
            ->where('registration_date', $today)
            ->value('count'));
    }

    public function test_service_returns_404_when_locked_event_row_is_already_gone(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);

        app(EventService::class)->forceDelete($event);

        // Instance lama masih "trashed", tetapi baris di database sudah tidak ada:
        // pembacaan ulang ber-lock di dalam transaksi harus menghasilkan 404.
        $this->expectException(NotFoundHttpException::class);

        app(EventService::class)->forceDelete($event);
    }

    public function test_purge_decrements_daily_registration_aggregate_without_touching_other_dates(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);
        DB::table('event_daily_registrations')->insertOrIgnore([
            ['registration_date' => '2020-01-01', 'count' => 4],
        ]);

        $this->actingAs($admin)->delete(route('dashboard.events.force-delete', $event));

        $today = now()->toDateString();
        $this->assertDatabaseHas('event_daily_registrations', ['registration_date' => $today, 'count' => 0]);
        $this->assertDatabaseHas('event_daily_registrations', ['registration_date' => '2020-01-01', 'count' => 4]);
    }

    public function test_purge_never_drives_daily_registration_aggregate_below_zero(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);
        $today = now()->toDateString();
        DB::table('event_daily_registrations')->where('registration_date', $today)->update(['count' => 0]);

        $this->actingAs($admin)->delete(route('dashboard.events.force-delete', $event));

        $this->assertSame(0, (int) DB::table('event_daily_registrations')
            ->where('registration_date', $today)
            ->value('count'));
    }

    public function test_purge_succeeds_when_banner_file_is_already_missing(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);
        Storage::disk('public')->delete((string) $event->banner);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertRedirect(route('dashboard.events.archived'));
    }

    public function test_draft_form_of_archived_event_is_purged_too(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);

        // Draft sengaja tidak diarsipkan oleh observer, jadi barisnya masih aktif
        // dan akan menahan force delete event lewat FK RESTRICT bila tidak dipurge.
        $draft = Form::factory()->create([
            'event_id' => $event->id,
            'created_by' => $admin->id,
            'status' => FormStatus::Draft,
        ]);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $event))
            ->assertRedirect(route('dashboard.events.archived'));

        $this->assertDatabaseMissing('forms', ['id' => $draft->id]);
    }

    public function test_force_delete_leaves_other_events_untouched(): void
    {
        $admin = $this->admin();
        [$event] = $this->archivedEventWithData($admin);
        [$otherEvent, $otherForm] = $this->archivedEventWithData($admin);

        $this->actingAs($admin)->delete(route('dashboard.events.force-delete', $event));

        $this->assertSoftDeleted('events', ['id' => $otherEvent->id]);
        // Baris anak acara lain masih ada (tidak ikut dipurge) dan tetap terarsip
        // karena observer sudah soft-delete saat acaranya diarsipkan.
        $this->assertSoftDeleted('forms', ['id' => $otherForm->id]);
        $this->assertDatabaseHas('form_fields', ['form_id' => $otherForm->id]);
    }

    public function test_sixth_force_delete_within_a_minute_is_throttled(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $archivedEvents = Event::factory()->count(6)->create(['created_by' => $admin->id]);
        $archivedEvents->each(static function (Event $event): void {
            $event->delete();
        });

        $archivedEvents->take(5)->each(function (Event $event) use ($admin): void {
            $this->actingAs($admin)
                ->delete(route('dashboard.events.force-delete', $event))
                ->assertRedirect(route('dashboard.events.archived'));
        });

        $blocked = $archivedEvents->last();
        $this->assertNotNull($blocked);

        $this->actingAs($admin)
            ->delete(route('dashboard.events.force-delete', $blocked))
            ->assertStatus(429);

        $this->assertSoftDeleted('events', ['id' => $blocked->id]);
    }
}
