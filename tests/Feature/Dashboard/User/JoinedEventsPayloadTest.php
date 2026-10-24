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
 * Portal "/joined" wajib dibatasi dan memilih kolom eksplisit agar payload
 * tidak tumbuh mengikuti jumlah event yang pernah diikuti peserta.
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
        $member = User::factory()->create();
        $member->assignRole('member');

        $latest = null;

        for ($i = 0; $i < EventService::USER_JOINED_LIMIT + 5; $i++) {
            $event = Event::factory()->create([
                'status' => EventStatus::Published,
                'start_date' => now()->subDays($i)->toDateString(),
                'end_date' => now()->subDays($i)->toDateString(),
                'registration_start' => now()->subDays(30),
                'registration_end' => now()->addDays(30),
            ]);

            $form = Form::factory()->create(['event_id' => $event->id]);

            FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => $member->id,
            ]);

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
        );

        $eventsSql = $queries->first(
            fn (string $sql): bool => str_starts_with($sql, 'select')
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
}
