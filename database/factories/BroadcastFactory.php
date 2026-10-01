<?php

namespace Database\Factories;

use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    protected $model = Broadcast::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'source' => Broadcast::SOURCE_EVENT_PARTICIPANTS,
            'event_id' => null,
            'period_id' => null,
            'scheduled_at' => null,
            'send_delay_seconds' => 0,
            'status' => Broadcast::STATUS_DRAFT,
            'recipient_snapshot' => null,
            'recipient_count' => 0,
        ];
    }
}
