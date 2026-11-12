<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `banner` is not a valid `form_fields.input_type`: a real banner is stored as
 * `fileUpload` + `metadata.builderType=banner`. The write boundary must answer
 * 422 instead of failing on the DB enum.
 */
class BannerFieldTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function fieldSavePath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form], false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides): array
    {
        return [
            'fields' => [array_merge([
                'id'       => (string) Str::uuid(),
                'label'    => 'Banner',
                'type'     => 'fileUpload',
                'name'     => 'form_banner',
                'order'    => 1,
                'metadata' => ['builderType' => 'banner', 'rules' => []],
            ], $overrides)],
        ];
    }

    public function test_api_banner_type_is_rejected_with_422_instead_of_500(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form  = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->postJson($this->fieldSavePath($event, $form), $this->payload(['type' => 'banner']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields.0.type']);

        $this->assertDatabaseCount('form_fields', 0);
    }

    public function test_file_upload_banner_payload_is_still_accepted(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form  = Form::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->post($this->fieldSavePath($event, $form), $this->payload([]))
            ->assertRedirect();

        $this->assertDatabaseHas('form_fields', [
            'form_id'    => $form->id,
            'name'       => 'form_banner',
            'input_type' => 'fileUpload',
        ]);
    }
}
