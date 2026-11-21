<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use App\Services\Form\FormSubmissionLimit;
use App\Services\Form\GuestFormToken;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class GuestFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_guest_post_persists_an_active_hashed_guest_answer(): void
    {
        [$event, $form, $field] = $this->eligibleForm();
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        $response = $this->withSession(['guest_form_access.' . $form->id => true])->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])->post(
            route('dashboard.forms.submission', compact('event', 'form')),
            [$field->name => 'valid guest answer'],
        );

        $response->assertRedirect(route('dashboard.events.forms.submitted', compact('event', 'form')));
        $this->assertDatabaseHas('form_answers', [
            'form_id' => $form->id,
            'user_id' => null,
            'guest_token_hash' => hash('sha256', $cookie['raw']),
            'is_active' => 1,
        ]);
        $this->assertSame(1, FormAnswer::query()->where('form_id', $form->id)->count());
    }

    public function test_guest_post_with_marker_and_malformed_cookie_returns_not_found_without_writing(): void
    {
        [$event, $form, $field] = $this->eligibleForm();

        $this->withSession(['guest_form_access.'.$form->id => true])
            ->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, 'malformed-token')
            ->post(route('dashboard.forms.submission', compact('event', 'form')), [$field->name => 'answer'])
            ->assertNotFound();
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_guest_post_without_cookie_or_marker_returns_not_found_without_writing(): void
    {
        [$event, $form, $field] = $this->eligibleForm();
        $response = $this->post(route('dashboard.forms.submission', compact('event', 'form')), [$field->name => 'answer']);
        $response->assertNotFound();
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_guest_upload_is_removed_when_required_validation_rejects(): void
    {
        Storage::fake('local');
        [$event, $form, $field] = $this->eligibleForm();
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'guest_file',
            'input_type' => 'fileUpload',
            'metadata' => ['rules' => ['mimes' => 'pdf']],
        ]);
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        $this->withSession(['guest_form_access.'.$form->id => true])
            ->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])
            ->post(route('dashboard.forms.submission', compact('event', 'form')), [
                'guest_file' => UploadedFile::fake()->createWithContent('guest.pdf', '%PDF-1.7'),
            ])
            ->assertSessionHasErrors($field->name);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guest_upload_is_removed_when_configured_cap_rejects(): void
    {
        Storage::fake('local');
        [$event, $form, $field] = $this->eligibleForm(['allow_multiple_submissions' => true, 'max_submissions' => 1]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'guest_file',
            'input_type' => 'fileUpload',
            'metadata' => ['rules' => ['mimes' => 'pdf']],
        ]);
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => GuestFormToken::hash($cookie['raw']), 'is_active' => 1]);
        $this->withSession(['guest_form_access.'.$form->id => true])
            ->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])
            ->post(route('dashboard.forms.submission', compact('event', 'form')), [
                $field->name => 'valid guest answer',
                'guest_file' => UploadedFile::fake()->createWithContent('guest.pdf', '%PDF-1.7'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guest_post_rejects_required_and_unknown_fields(): void
    {
        [$event, $form, $field] = $this->eligibleForm();
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        $this->withSession(['guest_form_access.' . $form->id => true])->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])->post(route('dashboard.forms.submission', compact('event', 'form')), ['unexpected' => 'value'])
            ->assertSessionHasErrors($field->name);
        $this->assertDatabaseCount('form_answers', 0);
    }

    public function test_rejected_guest_row_does_not_consume_configured_cap(): void
    {
        [$event, $form, $field] = $this->eligibleForm(['allow_multiple_submissions' => true, 'max_submissions' => 2]);
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        $hash = GuestFormToken::hash($cookie['raw']);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 0, 'review_status' => 'rejected']);
        $this->withSession(['guest_form_access.' . $form->id => true])->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])->post(route('dashboard.forms.submission', compact('event', 'form')), [$field->name => 'valid guest answer'])
            ->assertRedirect();
    }

    public function test_guest_count_is_actor_specific_from_authenticated_users(): void
    {
        $form = Form::factory()->create(['metadata' => ['allow_multiple_submissions' => true, 'max_submissions' => 2]]);
        $user = User::factory()->create();
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => $user->id, 'is_active' => 1]);
        $this->assertSame(0, FormSubmissionLimit::activeGuestCount($form, hash('sha256', 'guest')));
    }

    public function test_shared_post_requires_csrf_and_route_throttle(): void
    {
        [$event, $form] = $this->eligibleForm();
        $middleware = Route::getRoutes()->getByName('dashboard.forms.submission')->middleware();
        $this->assertContains('web', $middleware);
        $this->assertContains('throttle:form-submit', $middleware);
        $this->assertContains(ValidateCsrfToken::class, Route::getMiddlewareGroups()['web']);
    }

    public function test_guest_submission_does_not_create_user_or_registration_side_effect(): void
    {
        [$event, $form, $field] = $this->eligibleForm();
        $response = $this->get(route('dashboard.events.forms.fill', compact('event', 'form')));
        $cookie = $this->guestCookieFromResponse($response);
        $this->withSession(['guest_form_access.' . $form->id => true])->withUnencryptedCookie(GuestFormToken::COOKIE_NAME, $cookie['wire'])
            ->post(route('dashboard.forms.submission', compact('event', 'form')), [$field->name => 'valid guest answer']);
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseHas('form_answers', ['form_id' => $form->id, 'user_id' => null]);
    }

    /** @return array{wire: string, raw: string} */
    private function guestCookieFromResponse(TestResponse $response): array
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== GuestFormToken::COOKIE_NAME) {
                continue;
            }

            $wireValue = $cookie->getValue();
            $decryptedValue = $this->app['encrypter']->decrypt($wireValue, false);
            $this->assertIsString($decryptedValue);

            return ['wire' => $wireValue, 'raw' => CookieValuePrefix::remove($decryptedValue)];
        }

        $this->fail('Guest token cookie was not issued.');
    }

    private function eligibleForm(array $metadata = []): array
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'status' => FormStatus::Published,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => null,
            'metadata' => array_merge(['purpose' => 'other', 'allow_guest_submissions' => true], $metadata),
        ]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'guest_name',
            'metadata' => ['rules' => ['required' => true, 'min' => 1, 'max' => 255], 'type' => 'text'],
        ]);
        return [$event, $form, $field];
    }

    public function test_guest_token_hash_lookup_index_allows_repeat_active_rows(): void
    {
        $this->assertTrue(Schema::hasColumn('form_answers', 'guest_token_hash'));
        $this->assertTrue(Schema::hasIndex('form_answers', 'form_answers_active_guest_token_form_index'));
        $indexes = Schema::getIndexes('form_answers');
        $index = collect($indexes)->firstWhere('name', 'form_answers_active_guest_token_form_index');
        $this->assertNotNull($index);
        $this->assertFalse((bool) ($index['unique'] ?? false));
        $form = Form::factory()->create(['metadata' => ['allow_multiple_submissions' => true, 'max_submissions' => 2]]);
        $hash = hash('sha256', 'repeat');
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);
        $this->assertSame(2, FormSubmissionLimit::activeGuestCount($form, $hash));
    }

    public function test_guest_count_only_includes_active_guest_rows(): void
    {
        $form = Form::factory()->create(['metadata' => ['allow_multiple_submissions' => true, 'max_submissions' => 3]]);
        $hash = hash('sha256', 'guest');
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 0, 'review_status' => 'rejected']);
        $this->assertSame(1, FormSubmissionLimit::activeGuestCount($form, $hash));
    }

    public function test_guest_lock_transaction_keeps_cap_invariant(): void
    {
        $form = Form::factory()->create(['metadata' => ['allow_multiple_submissions' => true, 'max_submissions' => 1]]);
        $hash = GuestFormToken::hash('transaction-guest');
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);

        $this->assertSame(1, FormSubmissionLimit::activeGuestCount(Form::query()->lockForUpdate()->findOrFail($form->id), $hash));
        $this->expectException(ValidationException::class);
        FormSubmissionLimit::assertGuestAvailable($form->fresh(), $hash);
    }

    public function test_guest_limit_rejects_at_configured_cap(): void
    {
        $form = Form::factory()->create(['metadata' => ['allow_multiple_submissions' => true, 'max_submissions' => 2]]);
        $hash = hash('sha256', 'guest');
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);
        FormAnswer::factory()->create(['form_id' => $form->id, 'user_id' => null, 'guest_token_hash' => $hash, 'is_active' => 1]);
        $this->expectException(ValidationException::class);
        FormSubmissionLimit::assertGuestAvailable($form, $hash);
    }
}
