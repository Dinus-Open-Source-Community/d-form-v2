<?php

namespace Tests\Feature\Security;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill security:sanitize-stored-html untuk HTML lama yang tersimpan sebelum sanitasi input ada.
 */
class SanitizeStoredHtmlCommandTest extends TestCase
{
    use RefreshDatabase;

    private const DIRTY = '<script>alert(1)</script><p>ok</p>';

    public function test_command_membersihkan_empat_kolom_html_lama(): void
    {
        $event = Event::factory()->create(['description' => self::DIRTY]);
        $form = Form::factory()->create(['success_content' => self::DIRTY]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
            'description' => self::DIRTY,
            'metadata' => ['builderType' => 'paragraph', 'content' => self::DIRTY],
        ]);

        $this->artisan('security:sanitize-stored-html')->assertExitCode(0);

        $this->assertSame('<p>ok</p>', $event->fresh()?->description);
        $this->assertSame('<p>ok</p>', $form->fresh()?->success_content);
        $this->assertSame('<p>ok</p>', $field->fresh()?->description);

        $content = $field->fresh()?->metadata['content'] ?? null;
        $this->assertSame('<p>ok</p>', $content);
    }

    public function test_command_tidak_menulis_baris_yang_tidak_berubah(): void
    {
        $plain = 'Deskripsi acara biasa & apa adanya.';
        $event = Event::factory()->create(['description' => $plain]);
        $updatedAt = $event->fresh()?->updated_at;

        $this->artisan('security:sanitize-stored-html')->assertExitCode(0);

        $fresh = $event->fresh();
        $this->assertSame($plain, $fresh?->description);
        $this->assertTrue($updatedAt?->equalTo($fresh?->updated_at));
    }

    public function test_dry_run_tidak_mengubah_data(): void
    {
        $event = Event::factory()->create(['description' => self::DIRTY]);
        $form = Form::factory()->create(['success_content' => self::DIRTY]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
            'description' => self::DIRTY,
            'metadata' => ['builderType' => 'paragraph', 'content' => self::DIRTY],
        ]);

        $this->artisan('security:sanitize-stored-html', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame(self::DIRTY, $event->fresh()?->description);
        $this->assertSame(self::DIRTY, $form->fresh()?->success_content);
        $this->assertSame(self::DIRTY, $field->fresh()?->description);
        $this->assertSame(self::DIRTY, $field->fresh()?->metadata['content'] ?? null);
    }
}
