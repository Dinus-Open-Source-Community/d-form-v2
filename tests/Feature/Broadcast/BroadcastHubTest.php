<?php

namespace Tests\Feature\Broadcast;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\SendBroadcastJob;
use App\Mail\BroadcastMail;
use App\Models\Broadcast;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Broadcast\BroadcastDispatchService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcastHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_b01_create_with_valid_event_id_returns_locked_prefill(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.broadcasts.create', ['event_id' => $event->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('prefill.event_id', $event->id)
                ->where('prefill.locked_event', true)
                ->where('prefill.event_title', $event->title));
    }

    public function test_b02_recruitment_applicants_without_period_id_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('dashboard.broadcasts.store'), [
                'name' => 'Info OpRec',
                'source' => 'recruitment_applicants',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_id')
            ->assertJsonPath('errors.period_id.0', 'Pilih periode dulu — sumber pelamar wajib menyertakan period_id.');
    }

    public function test_b03_event_participants_without_event_id_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta',
                'source' => 'event_participants',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id')
            ->assertJsonPath('errors.event_id.0', 'Pilih event dulu — sumber peserta event wajib menyertakan event_id.');
    }

    public function test_b04_context_admin_can_send_to_own_event_but_not_others(): void
    {
        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();

        $ownEvent = Event::factory()->create(['created_by' => $contextAdmin->id]);
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta X',
                'source' => 'event_participants',
                'event_id' => $ownEvent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'name' => 'Info Peserta X',
            'event_id' => $ownEvent->id,
        ]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Info Peserta Y',
                'source' => 'event_participants',
                'event_id' => $otherEvent->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('broadcasts', ['name' => 'Info Peserta Y']);
    }

    public function test_b05_snapshot_is_stored_as_source_of_truth(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $memberA = User::factory()->create(['email' => 'peserta-a@example.com']);
        $memberB = User::factory()->create(['email' => 'peserta-b@example.com']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberA->id]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberB->id]);

        $this->actingAs($this->superAdmin())
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Pengumuman Event',
                'source' => 'event_participants',
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $broadcast = Broadcast::query()->where('name', 'Pengumuman Event')->firstOrFail();

        $this->assertSame(2, $broadcast->recipient_count);

        $snapshot = $broadcast->recipient_snapshot;

        $this->assertSame('event_participants', $snapshot['source']);
        $this->assertSame($event->id, $snapshot['event_id']);
        $this->assertSame(2, $snapshot['total']);
        $this->assertSame(
            ['peserta-a@example.com', 'peserta-b@example.com'],
            array_column($snapshot['recipients'], 'email')
        );

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.broadcasts.show', $broadcast))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.total', 2)
                ->where('broadcast.recipient_count', 2));
    }

    public function test_b06_show_follows_context_authorization(): void
    {
        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();

        $ownEvent = Event::factory()->create(['created_by' => $contextAdmin->id]);
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);

        $ownBroadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $ownEvent->id,
        ]);
        $otherBroadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $otherEvent->id,
        ]);

        $this->actingAs($contextAdmin)
            ->get(route('dashboard.broadcasts.show', $ownBroadcast))
            ->assertOk();

        $this->actingAs($contextAdmin)
            ->get(route('dashboard.broadcasts.show', $otherBroadcast))
            ->assertForbidden();
    }

    public function test_b07_event_emails_normalized_before_dedup(): void
    {
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $member = User::factory()->create(['email' => 'Peserta@example.com']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => 'PESERTA@EXAMPLE.COM',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Pengumuman Dedup',
                'source' => 'event_participants',
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $broadcast = Broadcast::query()->where('name', 'Pengumuman Dedup')->firstOrFail();

        $this->assertSame(1, $broadcast->recipient_count);
        $this->assertSame(
            ['peserta@example.com'],
            array_column($broadcast->recipient_snapshot['recipients'], 'email')
        );
    }

    public function test_b08_cross_model_authorization_returns_403_not_500(): void
    {
        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);
        $ownEvent = Event::factory()->create(['created_by' => $contextAdmin->id]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Regresi Otorisasi',
                'source' => 'event_participants',
                'event_id' => $otherEvent->id,
            ])
            ->assertForbidden();

        $this->actingAs($contextAdmin)
            ->get(route('dashboard.broadcasts.create', ['event_id' => $ownEvent->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('prefill.event_id', $ownEvent->id)
                ->where('prefill.locked_event', true)
                ->has('allowed_events'));
    }

    public function test_b09_edit_draft_keeps_snapshot_unchanged(): void
    {
        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $member = User::factory()->create(['email' => 'peserta-b09@example.com']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $member->id]);

        $this->actingAs($admin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Broadcast Konten',
                'source' => 'event_participants',
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $broadcast = Broadcast::query()->where('name', 'Broadcast Konten')->firstOrFail();
        $originalSnapshot = $broadcast->recipient_snapshot;

        $this->actingAs($admin)
            ->put(route('dashboard.broadcasts.update', $broadcast), [
                'subject' => 'Subjek Baru',
                'body_html' => '<p>Halo <strong>dunia</strong></p>',
            ])
            ->assertRedirect();

        $broadcast->refresh();

        $this->assertSame('Subjek Baru', $broadcast->subject);
        $this->assertSame('<p>Halo <strong>dunia</strong></p>', $broadcast->body_html);
        $this->assertSame('Halo dunia', $broadcast->body_text);
        $this->assertSame($originalSnapshot, $broadcast->recipient_snapshot);
    }

    public function test_b10_edit_processing_is_locked(): void
    {
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'status' => Broadcast::STATUS_PROCESSING,
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('dashboard.broadcasts.update', $broadcast), [
                'subject' => 'Ubah Terkunci',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('broadcasts', [
            'id' => $broadcast->id,
            'subject' => 'Ubah Terkunci',
        ]);
    }

    public function test_b11_send_now_dispatches_per_recipient_jobs(): void
    {
        Bus::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $memberA = User::factory()->create(['email' => 'kirim-a@example.com']);
        $memberB = User::factory()->create(['email' => 'kirim-b@example.com']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberA->id]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $memberB->id]);

        $this->actingAs($admin)
            ->post(route('dashboard.broadcasts.store'), [
                'name' => 'Broadcast Kirim',
                'subject' => 'Info Penting',
                'body_html' => '<p>Halo {{nama}}</p>',
                'source' => 'event_participants',
                'event_id' => $event->id,
            ])
            ->assertRedirect();

        $broadcast = Broadcast::query()->where('name', 'Broadcast Kirim')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dashboard.broadcasts.send', $broadcast))
            ->assertRedirect();

        $this->assertSame(Broadcast::STATUS_PROCESSING, $broadcast->refresh()->status);

        Bus::assertDispatched(SendBroadcastJob::class, 2);
    }

    public function test_b12_send_now_by_non_owner_is_forbidden(): void
    {
        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);

        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $otherEvent->id,
            'status' => Broadcast::STATUS_DRAFT,
        ]);

        $this->actingAs($contextAdmin)
            ->post(route('dashboard.broadcasts.send', $broadcast))
            ->assertForbidden();

        $this->assertSame(Broadcast::STATUS_DRAFT, $broadcast->refresh()->status);
    }

    public function test_b13_index_own_period_returns_only_its_broadcasts(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $otherPeriod = RecruitmentPeriod::factory()->create(['created_by' => $otherOwner->id]);

        $first = Broadcast::factory()->create([
            'name' => 'Broadcast Period A1',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);
        $second = Broadcast::factory()->create([
            'name' => 'Broadcast Period A2',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);
        Broadcast::factory()->create([
            'name' => 'Broadcast Period Lain',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $otherPeriod->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.broadcasts.index', ['period_id' => $period->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period.id', $period->id)
                ->where('broadcasts', function (array $broadcasts) use ($first, $second): bool {
                    $ids = array_column($broadcasts, 'id');
                    sort($ids);

                    $expected = [$first->id, $second->id];
                    sort($expected);

                    return $ids === $expected;
                }));
    }

    public function test_b14_index_other_period_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);

        Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard.broadcasts.index', ['period_id' => $period->id]))
            ->assertForbidden();
    }

    public function test_b15_index_without_period_id_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('dashboard.broadcasts.index'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_id')
            ->assertJsonPath('errors.period_id.0', 'Pilih periode dulu — daftar broadcast wajib menyertakan period_id.');
    }

    public function test_b16_index_excludes_event_scoped_broadcasts(): void
    {
        $owner = User::factory()->create();

        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $event = Event::factory()->create();

        $periodBroadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);
        Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'period_id' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.broadcasts.index', ['period_id' => $period->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('broadcasts', function (array $broadcasts) use ($periodBroadcast): bool {
                    return array_column($broadcasts, 'id') === [$periodBroadcast->id];
                }));
    }

    public function test_b18_preview_renders_subject_body_with_sample_name(): void
    {
        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'subject' => 'Halo Peserta',
            'body_html' => '<p>Hai {{nama}}, selamat datang!</p>',
            'status' => Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 1,
                'recipients' => [['email' => 'peserta@example.com', 'name' => 'Budi']],
            ],
            'recipient_count' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.broadcasts.preview', $broadcast));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Halo Peserta', false);
        $response->assertSee('Hai Budi', false);
        $response->assertDontSee('{{nama}}', false);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.broadcasts.preview', $broadcast))
            ->assertForbidden();
    }

    public function test_b19_test_send_delivers_one_mail_and_rejects_invalid(): void
    {
        Mail::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'subject' => 'Info Uji',
            'body_html' => '<p>Halo {{nama}}</p>',
            'status' => Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 0,
                'recipients' => [],
            ],
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.test', $broadcast), ['email' => 'coba@example.com'])
            ->assertOk()
            ->assertJson(['sent' => true, 'email' => 'coba@example.com']);

        Mail::assertSent(BroadcastMail::class, 1);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.test', $broadcast), ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->actingAs(User::factory()->create())
            ->postJson(route('dashboard.broadcasts.test', $broadcast), ['email' => 'coba@example.com'])
            ->assertForbidden();
    }

    public function test_b20_attachments_crud_capped_and_attached_to_outgoing_mail(): void
    {
        Storage::fake();
        Mail::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'subject' => 'Info Lampiran',
            'body_html' => '<p>Lihat lampiran</p>',
            'status' => Broadcast::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('catatan.txt', 'text-txt-tidak-valid'),
            ])
            ->assertUnprocessable();

        $first = $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('dokumen.pdf', "%PDF-1.4\ntes lampiran"),
            ])
            ->assertCreated()
            ->assertJsonPath('attachment.original_name', 'dokumen.pdf');

        Storage::disk()->assertExists($first->json('attachment.path'));
        $this->assertDatabaseHas('broadcast_attachments', ['id' => $first->json('attachment.id')]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), ['email' => 'x'])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('foto.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00tess"),
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('info.pdf', "%PDF-1.4\nketiga"),
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('lebih.pdf', "%PDF-1.4\nkeempat"),
            ])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.test', $broadcast), ['email' => 'coba@example.com'])
            ->assertOk();

        Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $mail): bool => count($mail->attachments()) === 3);

        $path = (string) $first->json('attachment.path');

        $this->actingAs($admin)
            ->deleteJson(route('dashboard.broadcasts.attachments.destroy', [
                'broadcast' => $broadcast->id,
                'attachment' => $first->json('attachment.id'),
            ]))
            ->assertOk();

        $this->assertDatabaseMissing('broadcast_attachments', ['id' => $first->json('attachment.id')]);
        Storage::disk()->assertMissing($path);
    }

    public function test_b21_retry_failed_redispatches_only_failed(): void
    {
        Bus::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 2,
                'recipients' => [
                    ['email' => 'ok@example.com', 'name' => 'Ok'],
                    ['email' => 'gagal@example.com', 'name' => 'Gagal'],
                ],
            ],
            'recipient_count' => 2,
        ]);

        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'ok@example.com',
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'sent_at' => now(),
        ]);
        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'gagal@example.com',
            'status' => EmailLogStatus::Failed,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => 'smtp down',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $broadcast))
            ->assertOk()
            ->assertJson(['retried' => 1]);

        Bus::assertDispatched(SendBroadcastJob::class, fn (SendBroadcastJob $job): bool => $job->recipientEmail === 'gagal@example.com');
        Bus::assertDispatchedTimes(SendBroadcastJob::class, 1);

        $clean = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 1,
                'recipients' => [['email' => 'baru@example.com', 'name' => 'Baru']],
            ],
            'recipient_count' => 1,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $clean))
            ->assertOk()
            ->assertJson(['retried' => 0]);

        $busy = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $busy))
            ->assertStatus(422);
    }

    public function test_b22_cancel_scheduled_returns_to_draft_and_reschedulable(): void
    {
        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $scheduled = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.cancel', $scheduled))
            ->assertOk()
            ->assertJson(['status' => Broadcast::STATUS_DRAFT]);

        $this->assertSame(Broadcast::STATUS_DRAFT, $scheduled->refresh()->status);
        $this->assertNull($scheduled->refresh()->scheduled_at);

        $this->actingAs($admin)
            ->put(route('dashboard.broadcasts.update', $scheduled), ['scheduled_at' => now()->addDays(2)->toDateTimeString()])
            ->assertRedirect();

        $this->assertSame(Broadcast::STATUS_SCHEDULED, $scheduled->refresh()->status);

        $busy = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.cancel', $busy))
            ->assertStatus(422);

        $this->assertSame(Broadcast::STATUS_PROCESSING, $busy->refresh()->status);

        $other = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_SCHEDULED,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('dashboard.broadcasts.cancel', $other))
            ->assertForbidden();

        $this->assertSame(Broadcast::STATUS_SCHEDULED, $other->refresh()->status);
    }

    public function test_b23_tracking_returns_per_recipient_rows_and_summary(): void
    {
        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 3,
                'recipients' => [
                    ['email' => 'kirim@example.com', 'name' => 'Kirim'],
                    ['email' => 'gagal@example.com', 'name' => 'Gagal'],
                    ['email' => 'tunggu@example.com', 'name' => 'Tunggu'],
                ],
            ],
            'recipient_count' => 3,
        ]);

        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'kirim@example.com',
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'sent_at' => now(),
        ]);
        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'gagal@example.com',
            'status' => EmailLogStatus::Failed,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => 'coba 1',
        ]);
        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'gagal@example.com',
            'status' => EmailLogStatus::Failed,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => 'coba 2',
        ]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.broadcasts.tracking', $broadcast));

        $response->assertOk()->assertJson([
            'summary' => ['total' => 3, 'sent' => 1, 'failed' => 1, 'pending' => 1],
        ]);

        $rows = collect($response->json('rows'))->keyBy('email');

        $this->assertSame('sent', $rows['kirim@example.com']['status']);
        $this->assertSame(1, $rows['kirim@example.com']['attempts']);
        $this->assertNotNull($rows['kirim@example.com']['sent_at']);

        $this->assertSame('failed', $rows['gagal@example.com']['status']);
        $this->assertSame(2, $rows['gagal@example.com']['attempts']);
        $this->assertNotNull($rows['gagal@example.com']['failed_at']);

        $this->assertSame('pending', $rows['tunggu@example.com']['status']);
        $this->assertSame(0, $rows['tunggu@example.com']['attempts']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('dashboard.broadcasts.tracking', $broadcast))
            ->assertForbidden();
    }

    /** Berkas uploadунку nyata (magic bytes) agar validasi mimes lolos di fake disk. */
    private function fakeUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bcast-test');

        if ($path === false) {
            $this->fail('Gagal membuat berkas temp untuk upload uji.');
        }

        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_b24_retry_by_non_owner_is_forbidden(): void
    {
        Bus::fake();

        $contextAdmin = User::factory()->create();
        $contextAdmin->givePermissionTo('events.view');

        $otherAdmin = User::factory()->create();
        $otherEvent = Event::factory()->create(['created_by' => $otherAdmin->id]);

        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $otherEvent->id,
            'status' => Broadcast::STATUS_DRAFT,
        ]);

        $this->actingAs($contextAdmin)
            ->postJson(route('dashboard.broadcasts.retry', $broadcast))
            ->assertForbidden();

        Bus::assertNotDispatched(SendBroadcastJob::class);
        $this->assertSame(Broadcast::STATUS_DRAFT, $broadcast->refresh()->status);
    }

    public function test_b25_attachments_reject_non_owner_and_locked_broadcast(): void
    {
        Storage::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $draft = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_DRAFT,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('dashboard.broadcasts.attachments.store', $draft), [
                'file' => $this->fakeUpload('dokumen.pdf', "%PDF-1.4\ntes"),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('broadcast_attachments', 0);

        $locked = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $locked), [
                'file' => $this->fakeUpload('dokumen.pdf', "%PDF-1.4\ntes"),
            ])
            ->assertForbidden();
    }

    public function test_b26_retry_flips_to_processing_and_full_success_marks_sent(): void
    {
        Bus::fake();
        Mail::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_DRAFT,
            'created_by' => $admin->id,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 2,
                'recipients' => [
                    ['email' => 'ok@example.com', 'name' => 'Ok'],
                    ['email' => 'gagal@example.com', 'name' => 'Gagal'],
                ],
            ],
            'recipient_count' => 2,
        ]);

        EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'gagal@example.com',
            'status' => EmailLogStatus::Failed,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => 'smtp down',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $broadcast))
            ->assertOk()
            ->assertJson(['retried' => 1]);

        $this->assertSame(Broadcast::STATUS_PROCESSING, $broadcast->refresh()->status);

        (new SendBroadcastJob($broadcast->id, 'gagal@example.com'))
            ->handle(app(BroadcastDispatchService::class));

        $this->assertSame(Broadcast::STATUS_PROCESSING, $broadcast->refresh()->status);

        (new SendBroadcastJob($broadcast->id, 'ok@example.com'))
            ->handle(app(BroadcastDispatchService::class));

        $this->assertSame(Broadcast::STATUS_SENT, $broadcast->refresh()->status);

        $partial = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
            'created_by' => $admin->id,
            'recipient_snapshot' => [
                'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
                'event_id' => $event->id,
                'total' => 2,
                'recipients' => [
                    ['email' => 'baik@example.com', 'name' => 'Baik'],
                    ['email' => 'buruk@example.com', 'name' => 'Buruk'],
                ],
            ],
            'recipient_count' => 2,
        ]);

        EmailLog::query()->create([
            'broadcast_id' => $partial->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'baik@example.com',
            'status' => EmailLogStatus::Sent,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'sent_at' => now(),
        ]);
        EmailLog::query()->create([
            'broadcast_id' => $partial->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => 'buruk@example.com',
            'status' => EmailLogStatus::Failed,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => 'tetap gagal',
        ]);

        (new SendBroadcastJob($partial->id, 'baik@example.com'))
            ->handle(app(BroadcastDispatchService::class));

        $this->assertSame(Broadcast::STATUS_PROCESSING, $partial->refresh()->status);
    }
    public function test_b27_retry_from_processing_allowed_only_with_failed_recipients(): void
    {
        Bus::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $snapshot = fn (array $recipients): array => [
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'total' => count($recipients),
            'recipients' => $recipients,
        ];
        $log = fn (Broadcast $broadcast, string $email, EmailLogStatus $status): void => EmailLog::query()->create([
            'broadcast_id' => $broadcast->id,
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'recipient_email' => $email,
            'status' => $status,
            'notification_type' => EmailNotificationType::BroadcastSent,
            'error_message' => $status === EmailLogStatus::Failed ? 'smtp down' : null,
            'sent_at' => $status === EmailLogStatus::Sent ? now() : null,
        ]);

        $stuck = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
            'recipient_snapshot' => $snapshot([
                ['email' => 'ok@example.com', 'name' => 'Ok'],
                ['email' => 'gagal@example.com', 'name' => 'Gagal'],
            ]),
            'recipient_count' => 2,
        ]);
        $log($stuck, 'ok@example.com', EmailLogStatus::Sent);
        $log($stuck, 'gagal@example.com', EmailLogStatus::Failed);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $stuck))
            ->assertOk()
            ->assertJson(['retried' => 1]);

        Bus::assertDispatched(SendBroadcastJob::class, fn (SendBroadcastJob $job): bool => $job->recipientEmail === 'gagal@example.com');
        Bus::assertDispatchedTimes(SendBroadcastJob::class, 1);
        $this->assertSame(Broadcast::STATUS_PROCESSING, $stuck->refresh()->status);

        $clean = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_PROCESSING,
            'recipient_snapshot' => $snapshot([['email' => 'solo@example.com', 'name' => 'Solo']]),
            'recipient_count' => 1,
        ]);
        $log($clean, 'solo@example.com', EmailLogStatus::Sent);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $clean))
            ->assertStatus(422);

        $this->assertSame(Broadcast::STATUS_PROCESSING, $clean->refresh()->status);

        $done = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_SENT,
            'recipient_snapshot' => $snapshot([['email' => 'tuntas@example.com', 'name' => 'Tuntas']]),
            'recipient_count' => 1,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.retry', $done))
            ->assertStatus(422);

        Bus::assertDispatchedTimes(SendBroadcastJob::class, 1);
    }
    public function test_b28_show_includes_attachments_list(): void
    {
        Storage::fake();

        $admin = $this->superAdmin();
        $event = Event::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => $event->id,
            'status' => Broadcast::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('dokumen.pdf', "%PDF-1.4\ntes"),
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('dashboard.broadcasts.attachments.store', $broadcast), [
                'file' => $this->fakeUpload('foto.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00tess"),
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->get(route('dashboard.broadcasts.show', $broadcast))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 2)
                ->where('attachments', function (array $attachments): bool {
                    $names = array_column($attachments, 'name');
                    sort($names);

                    if ($names !== ['dokumen.pdf', 'foto.jpg']) {
                        return false;
                    }

                    foreach ($attachments as $row) {
                        foreach (['id', 'name', 'size', 'mime'] as $key) {
                            if (! array_key_exists($key, $row)) {
                                return false;
                            }
                        }

                        if (! is_int($row['size'])) {
                            return false;
                        }
                    }

                    return true;
                }));
    }
    public function test_b17_period_show_broadcast_tab_returns_period_broadcasts(): void
    {
        $owner = User::factory()->create();
        $owner->givePermissionTo('recruitment.periods.view');

        $period = RecruitmentPeriod::factory()->create(['created_by' => $owner->id]);
        $otherPeriod = RecruitmentPeriod::factory()->create();

        $first = Broadcast::factory()->create([
            'name' => 'Tab Broadcast 1',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);
        $second = Broadcast::factory()->create([
            'name' => 'Tab Broadcast 2',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $period->id,
        ]);
        Broadcast::factory()->create([
            'name' => 'Tab Broadcast Lain',
            'source' => Broadcast::SOURCE_RECRUITMENT_APPLICANTS,
            'period_id' => $otherPeriod->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $period->id, 'tab' => 'broadcast']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'broadcast')
                ->where('broadcasts.current_page', 1)
                ->where('broadcasts.per_page', 15)
                ->where('broadcasts.total', 2)
                ->has('broadcasts.data', 2)
                ->where('broadcasts.data', function (array $rows) use ($first, $second): bool {
                    $ids = array_column($rows, 'id');
                    sort($ids);

                    $expected = [$first->id, $second->id];
                    sort($expected);

                    return $ids === $expected;
                }));
    }
}
