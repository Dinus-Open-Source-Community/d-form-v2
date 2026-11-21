<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormSession;
use App\Services\Form\FormSessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSessionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_uses_application_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-07 00:30:00', 'Asia/Jakarta'));

        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-07']);
        FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-14']);

        $schedule = app(FormSessionSchedule::class);

        $this->assertSame('2026-12-07', $schedule->todayFor($form)?->session_date?->toDateString());
        $this->assertSame(['2026-12-07', '2026-12-14'], $schedule->datesFor($form));
        $this->assertSame(2, $schedule->totalFor($form));
    }

    public function test_today_is_null_when_the_date_is_not_scheduled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-08 10:00:00', 'Asia/Jakarta'));

        $form = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        FormSession::factory()->create(['form_id' => $form->id, 'session_date' => '2026-12-07']);

        $this->assertNull(app(FormSessionSchedule::class)->todayFor($form));
    }
}
