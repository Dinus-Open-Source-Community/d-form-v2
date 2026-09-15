<?php

namespace Tests\Feature\Scan;

use App\Enums\FormAnswerReviewStatus;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Scan\GlobalScanResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GlobalScanResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_resolve_event_qr_by_submission_id(): void
    {
        [$event, $form] = $this->eventWithAcceptedAnswer();
        $answer = FormAnswer::query()->where('form_id', $form->id)->firstOrFail();

        $result = app(GlobalScanResolver::class)->resolve(
            json_encode(['v' => 1, 'submission_id' => $answer->id], JSON_THROW_ON_ERROR)
        );

        $this->assertSame('event', $result['kind']);
        $this->assertSame($answer->id, $result['answer']->id);
    }

    public function test_resolve_rejects_garbage(): void
    {
        $this->expectException(ValidationException::class);

        app(GlobalScanResolver::class)->resolve('bukan-qr-sama-sekali');
    }

    private function eventWithAcceptedAnswer(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $event = \App\Models\Event::factory()->create([
            'status' => \App\Enums\EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = \App\Models\Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [\App\Enums\EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
        $participant = User::factory()->create(['email' => 'resolver@example.test']);
        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-RES-001',
        ]);

        return [$event, $form];
    }
}
