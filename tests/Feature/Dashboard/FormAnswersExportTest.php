<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** Export CSV dan ZIP jawaban form: kolom per field, folder per pengirim, lampiran. */
class FormAnswersExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function eventOwnedBy(User $owner): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
            'created_by' => $owner->id,
        ]);
    }

    private function form(Event $event): Form
    {
        return Form::factory()->create(['event_id' => $event->id]);
    }

    private function fields(Form $form): void
    {
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'nama',
            'label' => 'Nama Lengkap',
            'order' => 1,
            'metadata' => [],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'fileUpload',
            'name' => 'cv',
            'label' => 'CV',
            'order' => 2,
            'metadata' => [],
        ]);
    }

    /**
     * @return array{answer: FormAnswer, name: string, email: string}
     */
    private function answerWithFile(Event $event, Form $form, string $name, string $email, string $filename): array
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email]);
        $path = 'form-uploads/'.$form->id.'/'.$filename;
        Storage::disk('local')->put($path, 'file-content-'.$filename);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'answers' => [
                'nama' => $name,
                'cv' => $path,
            ],
        ]);

        return ['answer' => $answer, 'name' => $name, 'email' => $email];
    }

    public function test_owner_can_export_answers_csv_with_one_column_per_field_and_signed_file_urls(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $form = $this->form($event);
        $this->fields($form);
        $this->answerWithFile($event, $form, 'Alice', 'alice@example.test', 'cv-alice.pdf');

        $response = $this->actingAs($owner)
            ->get(route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $form]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('submission_id', $content);
        $this->assertStringContainsString('Nama Lengkap', $content);
        $this->assertStringContainsString('CV', $content);
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('alice@example.test', $content);
        $this->assertStringContainsString('signature=', $content);
        $this->assertStringContainsString('cv-alice.pdf', $content);
    }

    public function test_owner_can_export_answers_zip_with_one_folder_per_submitter_and_all_files(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $form = $this->form($event);
        $this->fields($form);
        $this->answerWithFile($event, $form, 'Alice', 'alice@example.test', 'cv-alice.pdf');
        $this->answerWithFile($event, $form, 'Bob', 'bob@example.test', 'cv-bob.pdf');

        $response = $this->actingAs($owner)
            ->get(route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $archivePath = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'dform-test-zip-');
        self::assertNotFalse($copy);
        copy($archivePath, $copy);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($copy) === true);

        try {
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $names[] = (string) $zip->getNameIndex($index);
            }

            $aliceFolder = $this->folderStartingWith($names, '001 - Alice - alice@example.test');
            $bobFolder = $this->folderStartingWith($names, '002 - Bob - bob@example.test');

            self::assertContains($aliceFolder.'/cv-alice.pdf', $names);
            self::assertContains($bobFolder.'/cv-bob.pdf', $names);
            self::assertContains('jawaban.csv', $names);
            self::assertNotContains($aliceFolder.'/answers.csv', $names);

            $combined = (string) $zip->getFromName('jawaban.csv');
            self::assertStringContainsString('Alice', $combined);
            self::assertStringContainsString('Bob', $combined);
        } finally {
            $zip->close();
            @unlink($copy);
        }
    }

    public function test_guest_folder_uses_the_name_field_instead_of_the_first_json_value(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $form = $this->form($event);
        $this->fields($form);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
            'invited_email' => 'guest@example.test',
            'answers' => [
                'nama' => 'Nama Tamu',
                'cv' => null,
            ],
        ]);

        $response = $this->actingAs($owner)
            ->get(route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]));

        $response->assertOk();

        $copy = tempnam(sys_get_temp_dir(), 'dform-test-zip-');
        self::assertNotFalse($copy);
        copy($response->baseResponse->getFile()->getPathname(), $copy);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($copy) === true);

        try {
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $names[] = (string) $zip->getNameIndex($index);
            }

            self::assertContains('001 - Nama Tamu - guest@example.test/', $names);
            self::assertStringContainsString('Nama Tamu', (string) $zip->getFromName('jawaban.csv'));
        } finally {
            $zip->close();
            @unlink($copy);
        }
    }

    public function test_unrelated_admin_cannot_export_answers(): void
    {
        $event = $this->eventOwnedBy($this->admin());
        $form = $this->form($event);

        $this->actingAs($this->admin())
            ->get(route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $form]))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]))
            ->assertForbidden();
    }

    public function test_member_is_redirected_by_organizer_middleware(): void
    {
        $event = $this->eventOwnedBy($this->admin());
        $form = $this->form($event);

        $this->actingAs($this->member())
            ->get(route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $form]))
            ->assertRedirect(route('dashboard.user.events'));
    }

    public function test_form_from_another_event_returns_not_found(): void
    {
        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $otherEvent = $this->eventOwnedBy($owner);
        $foreignForm = $this->form($otherEvent);

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $foreignForm]))
            ->assertNotFound();
    }

    public function test_zip_is_rejected_when_it_exceeds_the_file_limit(): void
    {
        config(['events.forms.answers_zip_max_files' => 1]);

        $owner = $this->admin();
        $event = $this->eventOwnedBy($owner);
        $form = $this->form($event);
        $this->fields($form);
        $this->answerWithFile($event, $form, 'Alice', 'alice@example.test', 'cv-alice.pdf');
        $this->answerWithFile($event, $form, 'Bob', 'bob@example.test', 'cv-bob.pdf');

        $this->actingAs($owner)
            ->get(route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]))
            ->assertStatus(413);
    }

    /**
     * @param  list<string>  $names
     */
    private function folderStartingWith(array $names, string $prefix): string
    {
        foreach ($names as $name) {
            if (str_starts_with($name, $prefix.'/')) {
                return $prefix;
            }
        }

        self::fail('Folder '.$prefix.' tidak ditemukan di arsip.');
    }
}
