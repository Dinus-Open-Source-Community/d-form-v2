<?php

namespace Tests\Feature\Dashboard\User;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Event\EventService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Portal "/joined" wajib dibatasi, memilih kolom eksplisit, dan menandai
 * pemotongan lewat props totalJoined/truncated agar UI bisa memberi info.
 */
class JoinedEventsPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_portal_membatasi_jumlah_event_dan_memilih_kolom_eksplisit(): void
    {
        $member = $this->member();

        $latest = null;

        for ($i = 0; $i < EventService::USER_JOINED_LIMIT + 5; $i++) {
            $event = $this->joinEvent($member, $i);

            $latest ??= $event;
        }

        DB::enableQueryLog();
        $response = $this->actingAs($member)->get(route('dashboard.user.events'));
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $response->assertOk()->assertInertia(
            fn ($page) => $page
            ->component('Dashboard/User/Events')
            ->has('events', EventService::USER_JOINED_LIMIT)
            ->where('events.0.id', $latest->id)
            ->where('totalJoined', EventService::USER_JOINED_LIMIT + 5)
            ->where('truncated', true)
        );

        $eventsSql = $queries->first(
            fn (string $sql): bool => str_starts_with($sql, 'select "id", "slug"')
                && str_contains($sql, 'from "events" where')
        );

        $this->assertNotNull($eventsSql);
        $this->assertStringStartsWith('select "id", "slug", "title", "description"', $eventsSql);
        $this->assertCount(
            0,
            $queries->filter(fn (string $sql): bool => str_contains($sql, 'select * from "events"'))
        );
        $this->assertStringNotContainsString('"answers"', $eventsSql);
        $this->assertStringContainsString('limit '.EventService::USER_JOINED_LIMIT, $eventsSql);
    }

    public function test_portal_tidak_menandai_truncated_bila_event_masih_di_bawah_batas(): void
    {
        $member = $this->member();

        for ($i = 0; $i < 3; $i++) {
            $this->joinEvent($member, $i);
        }

        $this->actingAs($member)
            ->get(route('dashboard.user.events'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/User/Events')
                ->has('events', 3)
                ->where('totalJoined', 3)
                ->where('truncated', false)
            );
    }

    /** Buat event terpublikasi beserta form dan jawaban peserta agar muncul di portal "/joined". */
    private function joinEvent(User $member, int $daysAgo): Event
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->subDays($daysAgo)->toDateString(),
            'end_date' => now()->subDays($daysAgo)->toDateString(),
            'registration_start' => now()->subDays(30),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create(['event_id' => $event->id]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
        ]);

        return $event;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }
}
