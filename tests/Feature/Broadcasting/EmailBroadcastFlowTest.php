<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use App\Models\User;
use App\Services\Broadcasting\BroadcastHtmlSanitizer;
use App\Services\Broadcasting\BroadcastPersonalization;
use App\Services\Broadcasting\BroadcastSnapshotService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailBroadcastFlowTest extends TestCase
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

    public function test_personalization_fallback_and_unsupported_detection(): void
    {
        $p = app(BroadcastPersonalization::class);

        $this->assertSame('Halo Peserta,', $p->render('Halo {{name}},', null, 'Ev'));
        $this->assertSame('Halo Nafan,', $p->render('Halo {{name}},', 'Nafan', 'Ev'));
        $this->assertSame(['{{division}}'], $p->unsupportedVariables('Hi {{division}}'));
        $this->assertSame([], $p->unsupportedVariables('Hi {{name}} {{event_name}}'));
    }

    public function test_html_sanitizer_strips_script_and_js_urls(): void
    {
        $clean = app(BroadcastHtmlSanitizer::class)->sanitize(
            '<p>Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><a href="https://example.com">ok</a>'
        );

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('https://example.com', $clean);
    }

    public function test_snapshot_does_not_deduplicate_but_reports_duplicates(): void
    {
        $admin = $this->superAdmin();

        $broadcast = EmailBroadcast::query()->create([
            'name' => 'Test',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $summary = app(BroadcastSnapshotService::class)->generate($broadcast, [], [
            ['name' => 'Andi', 'email' => 'andi@gmail.com'],
            ['name' => 'Budi', 'email' => 'budi@gmail.com'],
            ['name' => 'Andi 2', 'email' => 'andi@gmail.com'],
        ]);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['unique']);
        $this->assertSame(1, $summary['duplicates']);
        $this->assertSame(3, $broadcast->refresh()->total_recipients);
        $this->assertSame(3, $broadcast->recipients()->count());
    }

    public function test_full_http_flow_create_snapshot_content_schedule_cancel(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        // Create
        $create = $this->post(route('dashboard.broadcasts.store'), [
            'name' => 'Pengumuman',
            'schedule_date' => now()->addDay()->format('Y-m-d'),
            'schedule_time' => '19:00',
            'delay_min' => 5,
            'delay_max' => 15,
        ]);
        $create->assertRedirect();
        $broadcast = EmailBroadcast::query()->first();
        $this->assertNotNull($broadcast);

        // Snapshot
        $snap = $this->post(route('dashboard.broadcasts.snapshot.generate', $broadcast), [
            'datasets' => [],
            'manual' => [
                ['name' => 'Nafan', 'email' => 'nafan@gmail.com'],
            ],
        ]);
        $snap->assertRedirect();
        $this->assertSame(1, $broadcast->refresh()->total_recipients);

        // Content valid
        $content = $this->post(route('dashboard.broadcasts.content.save', $broadcast), [
            'subject' => 'Halo {{name}}',
            'content' => '<p>Halo {{name}} di {{event_name}}</p>',
        ]);
        $content->assertRedirect();

        // Content invalid variable
        $bad = $this->post(route('dashboard.broadcasts.content.save', $broadcast), [
            'subject' => 'Hi {{division}}',
            'content' => '<p>Hi</p>',
        ]);
        $bad->assertSessionHasErrors('subject');

        // Schedule
        $sched = $this->post(route('dashboard.broadcasts.schedule', $broadcast));
        $sched->assertRedirect();
        $this->assertSame('scheduled', $broadcast->refresh()->status->value);

        // Cancel
        $cancel = $this->post(route('dashboard.broadcasts.cancel', $broadcast));
        $cancel->assertRedirect();
        $this->assertSame('cancelled', $broadcast->refresh()->status->value);
    }

    public function test_send_test_does_not_touch_stats(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $broadcast = EmailBroadcast::query()->create([
            'name' => 'T',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'subject' => 'Halo {{name}}',
            'content' => '<p>Halo {{name}}</p>',
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $this->post(route('dashboard.broadcasts.test', $broadcast), ['email' => 'admin@example.com'])
            ->assertRedirect();

        Mail::assertSent(\App\Mail\BroadcastMail::class, 1);
        $this->assertSame(0, $broadcast->refresh()->total_sent);
    }
}
