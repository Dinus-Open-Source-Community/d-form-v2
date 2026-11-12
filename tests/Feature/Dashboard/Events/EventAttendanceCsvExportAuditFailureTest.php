<?php

namespace Tests\Feature\Dashboard\Events;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Http\Controllers\Dashboard\Events\Exports\EventAttendanceCsvExportController;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * L-3: audit export CSV kehadiran wajib tercatat walau stream gagal di tengah
 * (generator melempar exception / client abort). Row count = baris yang sempat
 * dihasilkan, bukan 0, dan tidak ada exception yang ditelan.
 */
class EventAttendanceCsvExportAuditFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    public function test_audit_record_is_written_when_the_row_generator_fails_mid_stream(): void
    {
        [$event, $form] = $this->eventWithForm();

        $answer = FormAnswer::factory()->create(['form_id' => $form->id]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_at' => now()->startOfSecond(),
        ]);

        // Generator yang gagal setelah satu baris: menyimulasikan koneksi putus /
        // error di tengah stream, bukan exception yang ditelan.
        $this->app->instance(EventAttendanceCsvExportController::class, new class () extends EventAttendanceCsvExportController {
            /**
             * @return \Generator<int, list<mixed>>
             */
            protected function attendanceRows(
                Event $event,
                BundleGuestDisplayNameResolver $displayNameResolver,
                FormAnswerRecipientResolver $recipientResolver,
            ): \Generator {
                yield ['2026-01-01 00:00:00', 'first row'];

                throw new RuntimeException('stream failed');
            }
        });

        $actor = $this->admin();
        Log::spy();

        $response = $this->actingAs($actor)->get(route('dashboard.events.exports.attendance-csv', $event));
        $response->assertOk();

        $bufferLevel = ob_get_level();

        try {
            $response->streamedContent();
            $this->fail('Exception generator harus diteruskan ke caller.');
        } catch (RuntimeException $exception) {
            self::assertSame('stream failed', $exception->getMessage());
        } finally {
            // streamedContent() membuka output buffer; bersihkan walau exception.
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }

        Log::shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export.generated'
                && $context === [
                    'actor_id' => $actor->id,
                    'event_id' => $event->id,
                    'export_type' => 'attendance',
                    'row_count' => 1,
                ],
        );
    }
}
