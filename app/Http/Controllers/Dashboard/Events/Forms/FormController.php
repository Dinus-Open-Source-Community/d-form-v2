<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventFormRequest;
use App\Http\Requests\UpdateEventFormRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Registration\FormAnswerFileStorage;
use App\Support\FormFieldTypeMapping;
use App\Support\LegacyFormImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function __construct(private readonly FormAnswerFileStorage $formAnswerFiles)
    {
    }

    public function create(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Dashboard/Events/Forms/Create', [
            'event' => $this->eventSummary($event),
            'siblingForms' => $this->siblingFormsPayload($event),
        ]);
    }

    public function store(StoreEventFormRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        $form = $event->forms()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'success_content' => $this->normalizeSuccessContent($data['success_content'] ?? null),
            'closed_at' => $data['closed_at'],
            'visible_for' => $data['visible_for'],
            'banner_url' => $data['banner_url'] ?? null,
            'banner_caption' => $data['banner_caption'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Banner legacy (data: URL) tidak punya validasi penolakan — konversi
        // otomatis ke form-banners/{formId} agar DB hanya simpan path.
        $banner = LegacyFormImage::convertFormBanner($form->banner_url, $form->id);
        if ($banner['converted']) {
            $form->update(['banner_url' => $banner['value']]);
        } elseif ($banner['failed']) {
            Log::warning('[FormController, store] legacy banner auto-convert failed', [
                'form_id' => $form->id,
                'hint' => 'php artisan forms:migrate-legacy-images --write',
            ]);
        }

        if (!empty($data['fields'])) {
            // Lapis kedua: konversi data: URL legacy (optionChoices[].imageUrl, dll.)
            // ke storage; URL/path normal no-op.
            $legacy = LegacyFormImage::convertFieldRows($data['fields'], $form->id);
            if ($legacy['converted'] > 0 || $legacy['failed'] > 0) {
                Log::warning('[FormController, store] legacy data: image auto-convert', [
                    'form_id' => $form->id,
                    'converted' => $legacy['converted'],
                    'failed' => $legacy['failed'],
                    'hint' => 'php artisan forms:migrate-legacy-images --write',
                ]);
            }

            foreach ($legacy['rows'] as $fieldData) {
                $form->formFields()->create([
                    'id' => $fieldData['id'] ?? \Illuminate\Support\Str::uuid()->toString(),
                    'input_type' => FormFieldTypeMapping::toInputType($fieldData['type']),
                    'label' => $fieldData['label'],
                    'description' => $fieldData['description'] ?? null,
                    'name' => $fieldData['name'],
                    'metadata' => $fieldData['metadata'] ?? [],
                    'order' => $fieldData['order'],
                    'is_append' => (bool) ($fieldData['is_append'] ?? false),
                ]);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.form.create.success'),
        ]);

        return redirect()->route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
    }

    /** Titik masuk draft server-side untuk halaman "buat form" (wizard). */
    public function storeDraft(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $userId = (string) $request->user()->id;
        abort_unless(Form::viewerOwnsEvent($event, $userId), 403);

        $form = $event->forms()
            ->where('status', FormStatus::Draft->value)
            ->where('created_by', $userId)
            ->with(['formFields' => fn ($query) => $query->orderBy('order')])
            ->orderBy('created_at')
            ->first();

        if ($form === null) {
            $form = $event->forms()->create([
                'title' => '',
                'description' => '',
                'success_content' => null,
                'closed_at' => null,
                'visible_for' => [EventFormVisibility::Public->value],
                'banner_url' => null,
                'banner_caption' => null,
                'metadata' => null,
                'status' => FormStatus::Draft,
                'created_by' => $userId,
            ]);

            // Draft baru tidak punya field; hindari query tambahan saat snapshot.
            $form->setRelation('formFields', collect());
        }

        return response()->json($this->draftSnapshot($event, $form));
    }

    /**
     *
     * @return array{form: array<string, mixed>, fields: list<array<string, mixed>>}
     *
     */
    private function draftSnapshot(Event $event, Form $form): array
    {
        return [
            'form' => array_merge($this->formToInertia($form), [
                'edit_url' => route('dashboard.events.forms.show', ['event' => $event, 'form' => $form], false),
            ]),
            'fields' => $form->formFields
                ->map(fn (FormField $field): array => $this->formFieldToInertia($field))
                ->values()
                ->all(),
        ];
    }

    public function show(Event $event, Form $form): Response
    {
        $this->guardFormOnEvent($event, $form);

        $form->load(['formFields' => function ($q) {
            $q->orderBy('order');
        }]);

        $fields = $form->formFields
            ->map(fn (FormField $f) => $this->formFieldToInertia($f))
            ->values()
            ->all();

        $fileFieldNames = $form->formFields
            ->where('input_type', 'fileUpload')
            ->pluck('name')
            ->values()
            ->all();

        $submissionQuery = FormAnswer::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->where('form_id', $form->id)
            ->whereListedForOrganizerParticipantRoster()
            ->orderByDesc('created_at');

        $submissions = (clone $submissionQuery)
            ->get()
            ->map(fn (FormAnswer $answer) => $this->submissionToInertia($answer, $fileFieldNames))
            ->values()
            ->all();

        return Inertia::render('Dashboard/Events/Forms/Show', [
            'event' => $this->eventSummary($event),
            'form' => $this->formToInertia($form),
            'fields' => $fields,
            'siblingForms' => $this->siblingFormsPayload($event, $form->id),
            'saveFieldsUrl' => route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form]),
            'updateFormUrl' => route('dashboard.events.forms.update', ['event' => $event, 'form' => $form]),
            'submissions' => $submissions,
            'submissionsCount' => $submissionQuery->count(),
            'formShare' => FormShareLinkController::payload($event, $form),
        ]);
    }

    public function edit(Event $event, Form $form): RedirectResponse
    {
        $this->guardFormOnEvent($event, $form);

        return redirect()->route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
    }

    public function update(UpdateEventFormRequest $request, Event $event, Form $form)
    {
        $this->guardFormOnEvent($event, $form);
        $data = $request->validated();

        // Lapis kedua (pre-transaction): konversi data: URL legacy yang lolos
        // validasi (forms.banner_url, optionChoices[].imageUrl) ke storage.
        $banner = LegacyFormImage::convertFormBanner($data['banner_url'] ?? null, $form->id);
        if ($banner['converted']) {
            $data['banner_url'] = $banner['value'];
        }
        if (isset($data['fields']) && is_array($data['fields'])) {
            $legacy = LegacyFormImage::convertFieldRows($data['fields'], $form->id);
            $data['fields'] = $legacy['rows'];
        } else {
            $legacy = ['converted' => 0, 'failed' => 0];
        }
        if ($banner['converted'] || $banner['failed'] || $legacy['converted'] > 0 || $legacy['failed'] > 0) {
            Log::warning('[FormController, update] legacy data: image auto-convert', [
                'form_id' => $form->id,
                'banner_converted' => $banner['converted'],
                'banner_failed' => $banner['failed'],
                'fields_converted' => $legacy['converted'],
                'fields_failed' => $legacy['failed'],
                'hint' => 'php artisan forms:migrate-legacy-images --write',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($form, $data, $request) {
            $form->update($this->formAttributesFrom(
                $data,
                $form,
                $request->isDraftAutosave(),
                $request->isStatusDraftTransition(),
            ));

            if (isset($data['fields'])) {
                $oldById = $form->formFields->keyBy('id');
                $incomingIds = collect($data['fields'])->pluck('id')->filter()->all();

                $toDelete = $oldById->keys()->diff($incomingIds);
                if ($toDelete->isNotEmpty()) {
                    $form->formFields()->whereIn('id', $toDelete)->delete();
                }

                foreach ($data['fields'] as $fieldData) {
                    $id = $fieldData['id'] ?? null;
                    $attrs = [
                        'input_type' => FormFieldTypeMapping::toInputType($fieldData['type']),
                        'label' => $fieldData['label'],
                        'name' => $fieldData['name'],
                        'metadata' => $fieldData['metadata'] ?? [],
                        'order' => $fieldData['order'],
                        'description' => $fieldData['description'] ?? null,
                        'is_append' => (bool) ($fieldData['is_append'] ?? false),
                    ];

                    if (is_string($id) && $id !== '' && $oldById->has($id)) {
                        $oldById->get($id)->update($attrs);
                    } else {
                        $form->formFields()->create(array_merge($attrs, [
                            'id' => (is_string($id) && $id !== '') ? $id : (string) \Illuminate\Support\Str::uuid(),
                        ]));
                    }
                }
            }
        });

        // Autosave global (background, tanpa navigasi) → JSON.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $form->fresh()->status->value,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.form.edit.success'),
        ]);

        return redirect()->route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
    }

    public function destroy(Event $event, Form $form): RedirectResponse
    {
        $this->guardFormOnEvent($event, $form);
        $form->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.form.delete.success'),
        ]);

        return redirect()->route('dashboard.events.show', ['event' => $event]);
    }

    private function guardFormOnEvent(Event $event, Form $form): void
    {
        abort_unless($form->event_id === $event->id, 404);
        // Draft tidak pernah terlihat di luar pembuat event: jawab 404 agar
        // keberadaan draft tidak bisa ditebak dari status code.
        abort_unless($form->isDraftVisibleTo($event, auth()->user()), 404);
        $this->authorize('update', $event);
    }

    /**
     *
     * Atribut yang ditulis ke `forms`.
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     */
    private function formAttributesFrom(
        array $data,
        Form $form,
        bool $isDraftAutosave,
        bool $isStatusDraftTransition = false,
    ): array {
        if ($isStatusDraftTransition) {
            return ['status' => FormStatus::Draft];
        }

        if (! $isDraftAutosave) {
            return [
                'title' => $data['title'],
                'description' => $data['description'],
                'success_content' => $this->normalizeSuccessContent($data['success_content'] ?? null),
                'closed_at' => $data['closed_at'],
                'visible_for' => $data['visible_for'],
                'banner_url' => $data['banner_url'] ?? null,
                'banner_caption' => $data['banner_caption'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                // Publikasi hanya terjadi setelah seluruh validasi di atas lolos.
                'status' => $this->resolveStatus($form, $data),
            ];
        }

        $attributes = [];

        // ConvertEmptyStringsToNull mengubah '' menjadi null; `title` dan
        // `description` NOT NULL, jadi nilai kosong disimpan sebagai ''.
        foreach (['title', 'description'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = (string) ($data[$key] ?? '');
            }
        }

        if (array_key_exists('closed_at', $data)) {
            $attributes['closed_at'] = $data['closed_at'];
        }

        // `visible_for` juga NOT NULL: null berarti "belum diisi", jangan ditulis.
        if (is_array($data['visible_for'] ?? null) && $data['visible_for'] !== []) {
            $attributes['visible_for'] = $data['visible_for'];
        }

        foreach (['banner_url', 'banner_caption'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        if (array_key_exists('success_content', $data)) {
            $attributes['success_content'] = $this->normalizeSuccessContent($data['success_content']);
        }

        if (array_key_exists('metadata', $data)) {
            $attributes['metadata'] = $data['metadata'];
        }

        return $attributes;
    }

    /**
     *
     * Draft tetap draft sampai `publish` dikirim; `publish=false` tidak
     * menurunkan form yang sudah published.
     *
     * @param  array<string, mixed>  $data
     *
     */
    private function resolveStatus(Form $form, array $data): FormStatus
    {
        if (isset($data['status'])) {
            return FormStatus::from($data['status']);
        }

        return ! empty($data['publish']) ? FormStatus::Published : $form->status;
    }

    private function eventSummary(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
        ];
    }

    /**
     *
     * Other forms in the same event (for prerequisite picker).
     *
     * @return list<array{id: string, title: string}>
     *
     */
    private function siblingFormsPayload(Event $event, ?string $excludeFormId = null): array
    {
        $query = $event->forms()->published()->orderBy('title');

        if ($excludeFormId !== null) {
            $query->where('id', '!=', $excludeFormId);
        }

        return $query
            ->get(['id', 'title'])
            ->map(fn (Form $f) => [
                'id' => $f->id,
                'title' => $f->title,
            ])
            ->values()
            ->all();
    }

    private function formToInertia(Form $form): array
    {
        $closed = $form->closed_at;
        $closedStr = $closed
            ? $closed->format('Y-m-d\TH:i')
            : null;

        $meta = $form->metadata;
        if ($meta === null) {
            $meta = [];
        } elseif (!is_array($meta)) {
            $meta = (array) $meta;
        }

        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'success_content' => $form->success_content,
            'closed_at' => $closedStr,
            'visible_for' => $form->visible_for
                ? $form->visible_for->map(fn (EventFormVisibility $e) => $e->value)->values()->all()
                : [],
            'event_id' => $form->event_id,
            'banner_url' => $form->banner_url,
            'banner_caption' => $form->banner_caption,
            'metadata' => $meta,
            'purpose' => $form->purpose()->value,
            'status' => $form->status->value,
            'is_draft' => $form->isDraft(),
        ];
    }

    private function normalizeSuccessContent(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '<p></p>') {
            return null;
        }

        return $raw;
    }

    private function formFieldToInertia(FormField $f): array
    {
        $meta = $f->metadata;
        if ($meta instanceof \Illuminate\Support\Collection) {
            $meta = $meta->all();
        } elseif (is_object($meta) && method_exists($meta, 'toArray')) {
            $meta = $meta->toArray();
        } else {
            $meta = (array) $meta;
        }

        return [
            'id' => $f->id,
            'type' => FormFieldTypeMapping::toApiType($f->input_type),
            'label' => $f->label,
            'description' => $f->description,
            'name' => $f->name,
            'order' => $f->order,
            'metadata' => $meta,
            'is_append' => (bool) $f->is_append,
        ];
    }

    /**
     *
     * @param  list<string>  $fileFieldNames
     * @return array{
     * id: string,
     * user: array{id: string, name: string, email: string}|null,
     * answers: array<string, mixed>,
     * submitted_at: string,
     * review_status: string|null,
     * reviewed_at: string|null,
     * reviewed_by: string|null,
     * reviewer: array{id: string, name: string, email: string}|null,
     * registration_role: string|null,
     * member_confirmation_status: string|null,
     * group_token: string|null,
     * }
     *
     */
    private function submissionToInertia(FormAnswer $answer, array $fileFieldNames): array
    {
        return [
            'id' => $answer->id,
            'user' => $answer->user
                ? [
                    'id' => $answer->user->id,
                    'name' => $answer->user->name,
                    'email' => $answer->user->email,
                ]
                : null,
            'answers' => $this->formAnswerFiles->answerUrls(
                $answer,
                is_array($answer->answers) ? $answer->answers : [],
                $fileFieldNames,
            ),
            'submitted_at' => $answer->created_at->toISOString(),
            'review_status' => $answer->review_status?->value,
            'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $answer->reviewed_by,
            'reviewer' => $answer->reviewer
                ? [
                    'id' => $answer->reviewer->id,
                    'name' => $answer->reviewer->name,
                    'email' => $answer->reviewer->email,
                ]
                : null,
            'registration_role' => $answer->registration_role?->value,
            'member_confirmation_status' => $answer->member_confirmation_status?->value,
            'group_token' => $answer->group_token,
        ];
    }
}
