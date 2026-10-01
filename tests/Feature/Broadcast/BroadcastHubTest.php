<?php

namespace Tests\Feature\Broadcast;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
                ->where('broadcasts', function (array $broadcasts) use ($first, $second): bool {
                    $ids = array_column($broadcasts, 'id');
                    sort($ids);

                    $expected = [$first->id, $second->id];
                    sort($expected);

                    return $ids === $expected;
                }));
    }
}
