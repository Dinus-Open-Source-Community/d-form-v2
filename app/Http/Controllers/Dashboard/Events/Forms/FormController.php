<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\DuRedeemStatus;
use App\Enums\EventFormVisibility;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventFormRequest;
use App\Http\Requests\UpdateEventFormRequest;
use App\Models\DuCourse;
use App\Models\DuRedeemIssuance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Form\FormPurposeRules;
use App\Services\Du\DuCourseFieldService;
use App\Services\Du\DuCourseRepository;
use App\Services\Du\DuCourseSyncService;
use App\Services\Du\DuFormCourseResolver;
use App\Services\Form\ReferralCodeService;
use App\Services\Registration\FormAnswerFileStorage;
use App\Support\FormFieldTypeMapping;
use App\Support\LegacyFormImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    /** Pilihan jumlah baris jawaban per halaman; nilai lain jatuh ke default. */
    private const ANSWERS_PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private const ANSWERS_PER_PAGE_DEFAULT = 20;

    /** Sama dengan `FormAnswerReviewService::MAX_BULK_IDS`; select-all tidak pernah melewati batas batch. */
    private const BULK_SELECT_LIMIT = 100;

    public function __construct(
        private readonly FormAnswerFileStorage $formAnswerFiles,
        private readonly DuCourseFieldService $duCourseFieldService,
        private readonly DuCourseSyncService $duCourseSync,
        private readonly DuCourseRepository $duCourses,
        private readonly DuFormCourseResolver $duForms,
        private readonly ReferralCodeService $referralCodes,
    ) {
    }

    public function create(Event $event, Request $request): Response
    {
        $this->authorize('update', $event);

        $initial = FormPurposeRules::prefill($request->query('purpose'), $request->query('mode'));

        return Inertia::render('Dashboard/Events/Forms/Create', [
            'event' => $this->eventSummary($event),
            'siblingForms' => $this->siblingFormsPayload($event),
            'initialPurpose' => $initial['purpose'],
            'initialMode' => $initial['mode'],
        ]);
    }

    public function store(StoreEventFormRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        // Satu transaksi: validasi flag DU berjalan setelah form dibuat, jadi kegagalannya
        // tidak boleh meninggalkan form yatim.
        $form = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $event): Form {
            $form = $event->forms()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'success_content' => $this->normalizeSuccessContent($data['success_content'] ?? null),
                'closed_at' => $data['closed_at'],
                'visible_for' => $data['visible_for'],
                'banner_url' => $data['banner_url'] ?? null,
                'banner_caption' => $data['banner_caption'] ?? null,
                'metadata' => $this->normalizeMetadata($data['metadata'] ?? null),
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

            if (($form->metadata['purpose'] ?? null) === \App\Enums\FormPurpose::DKelas->value) {
                $this->duCourseFieldService->ensure($form);
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

                // Tanpa field tersimpan, guard ini tetap menolak lebih dari satu field ber-flag
                // dan flag pada tipe field yang tidak mendukung pilihan.
                $legacy['rows'] = $this->duCourseFieldService->enforceLockedOptionContent($form, $legacy['rows']);

                foreach ($legacy['rows'] as $fieldData) {
                    if (($form->metadata['purpose'] ?? null) === \App\Enums\FormPurpose::DKelas->value
                        && ($fieldData['name'] ?? null) === 'du_course_uid') {
                        continue;
                    }

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

            return $form;
        });

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

        // Body tidak dipercaya: nilai asing jatuh ke Pendaftaran single.
        $initial = FormPurposeRules::prefill($request->input('purpose'), $request->input('mode'));
        $isDKelas = $initial['purpose'] === FormPurpose::DKelas->value;

        // `mode` hanya input saat pembuatan: bukan kunci scope. Draf dengan tujuan yang sama
        // dilanjutkan apa adanya (registration_mode tersimpan dipertahankan), mode dari URL
        // hanya menentukan isi draf baru.
        $form = $event->forms()
            ->where('status', FormStatus::Draft->value)
            ->where('created_by', $userId)
            ->where(function (Builder $query) use ($initial): void {
                $query->where('metadata->purpose', $initial['purpose']);

                if ($initial['purpose'] !== FormPurpose::Registration->value) {
                    return;
                }

                // Draft lama tanpa tujuan eksplisit tetap dianggap Pendaftaran.
                $query->orWhereNull('metadata')->orWhereNull('metadata->purpose');
            })
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
                'metadata' => FormPurposeRules::apply([
                    'purpose' => $initial['purpose'],
                    'registration_mode' => $initial['mode'],
                ], null, false),
                'status' => FormStatus::Draft,
                'created_by' => $userId,
            ]);
        }

        if ($isDKelas) {
            $this->syncDuCourseCatalogIfEmpty($event);
            $this->duCourseFieldService->ensure($form);
        }

        // Field sistem dan opsi cache harus sudah ada pada respons pertama.
        $form->load(['formFields' => fn ($query) => $query->orderBy('order')]);

        return response()->json($this->draftSnapshot($event, $form));
    }

    /** Cache katalog DU kosong diisi sekali; kegagalan upstream tidak memblokir draft. */
    private function syncDuCourseCatalogIfEmpty(Event $event): void
    {
        try {
            $this->duCourseSync->syncIfEmpty();
        } catch (\Throwable $exception) {
            // Body upstream tidak pernah ikut dicatat.
            Log::warning('[FormController, storeDraft] DU catalog sync failed', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
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

    public function show(Request $request, Event $event, Form $form): Response
    {
        $this->guardFormOnEvent($event, $form);
        if (($form->metadata['purpose'] ?? null) === \App\Enums\FormPurpose::DKelas->value) {
            $this->duCourseFieldService->ensure($form);
        }

        $form->load([
            'formFields' => function ($q) {
                $q->orderBy('order');
            },
            'formSessions' => function ($q) {
                $q->withCount('attendances')->orderBy('session_date')->orderBy('id');
            },
            'referralCodes',
        ]);

        $duContext = $this->duDrawerContext($form);

        $fields = $form->formFields
            ->map(fn (FormField $f) => $this->formFieldToInertia($f, $duContext['active'] ?? null))
            ->values()
            ->all();

        $fileFieldNames = $form->formFields
            ->where('input_type', 'fileUpload')
            ->pluck('name')
            ->values()
            ->all();

        $filters = $this->submissionFilters($request);
        $hasActiveSubmissionFilters = $filters['search'] !== null
            || $filters['review_status'] !== null
            || $filters['referral'] !== null;

        $submissionQuery = FormAnswer::query()
            ->with([
                'user:id,name,email',
                'reviewer:id,name,email',
                'referralRedemption',
                'teamLeaderSubmission.referralRedemption',
                'duRedeemIssuance',
            ])
            ->where('form_id', $form->id)
            ->whereListedForOrganizerParticipantRoster();

        $this->applySubmissionFilters($submissionQuery, $filters);

        $submissionQuery->orderByDesc('created_at');

        $referralSort = $this->referralSort($request);
        if ($referralSort !== null) {
            $this->applyReferralSort($submissionQuery, $referralSort);
        }

        $perPage = $this->resolveAnswersPerPage($request);
        $filteredTotal = (clone $submissionQuery)->count();
        $lastPage = max(1, (int) ceil($filteredTotal / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);
        // Badge tab selalu menghitung seluruh jawaban; query tambahan hanya saat filter aktif.
        $submissionsCount = $hasActiveSubmissionFilters
            ? FormAnswer::query()
                ->where('form_id', $form->id)
                ->whereListedForOrganizerParticipantRoster()
                ->count()
            : $filteredTotal;

        $paginator = $submissionQuery->paginate($perPage, ['*'], 'page', $page);

        $submissions = collect($paginator->items())
            ->map(fn (FormAnswer $answer) => $this->submissionToInertia($answer, $fileFieldNames, $duContext))
            ->values()
            ->all();

        $selectableQuery = $this->bulkReviewEligibleQuery($form);
        // Filter tabel ikut berlaku agar "pilih semua" tidak mengambil baris yang tidak terlihat.
        // Filter status non-pending memang menghasilkan nol ID: baris itu tidak bisa direview massal.
        $this->applySubmissionFilters($selectableQuery, $filters);
        if ($referralSort !== null) {
            $this->applyReferralSort($selectableQuery, $referralSort);
        }
        $bulkSelectableTotal = (clone $selectableQuery)->count();
        $selectableSubmissionIds = $selectableQuery
            ->orderByDesc('created_at')
            ->limit(self::BULK_SELECT_LIMIT)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return Inertia::render('Dashboard/Events/Forms/Show', array_merge([
            'event' => $this->eventSummary($event),
            'form' => $this->formToInertia($form),
            'hasSubmissions' => $form->formAnswers()->exists(),
            'sessions' => $form->formSessions
                ->map(static fn ($session): array => [
                    'id' => $session->id,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'label' => $session->label,
                    'attendance_count' => (int) $session->attendances_count,
                ])
                ->values()
                ->all(),
            'fields' => $fields,
            'siblingForms' => $this->siblingFormsPayload($event, $form->id),
            'saveFieldsUrl' => route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form]),
            'updateFormUrl' => route('dashboard.events.forms.update', ['event' => $event, 'form' => $form]),
            'submissions' => $submissions,
            'submissionsCount' => $submissionsCount,
            'query' => [
                'search' => $filters['search'],
                'review_status' => $filters['review_status'],
                'referral' => $filters['referral'],
            ],
            'submissionsPagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'selectableSubmissionIds' => $selectableSubmissionIds,
            'bulkSelectableTotal' => $bulkSelectableTotal,
            'bulkSelectableLimit' => self::BULK_SELECT_LIMIT,
            'exportUrls' => [
                'csv' => route('dashboard.events.forms.answers-csv', ['event' => $event, 'form' => $form]),
                'zip' => route('dashboard.events.forms.answers-zip', ['event' => $event, 'form' => $form]),
            ],
            'formShare' => FormShareLinkController::payload($event, $form),
            'referral' => [
                'enabled' => $form->isReferralActive(),
                'codes' => $form->isReferralActive() ? $this->referralCodes->payloads($form) : [],
                'sort' => $referralSort,
                'direction' => $referralSort,
                'usage_url' => route('dashboard.events.forms.show', [
                    'event' => $event,
                    'form' => $form,
                    'tab' => 'jawaban',
                ], false),
            ],
        ], $duContext === null ? [] : ['duCatalog' => $duContext['catalog']]));
    }

    private function referralSort(Request $request): ?string
    {
        $value = $request->query('referral_sort');

        return is_string($value) && in_array(strtolower($value), ['asc', 'desc'], true)
            ? strtolower($value)
            : null;
    }

    private function applyReferralSort(Builder $query, string $direction): void
    {
        // Urutkan kode referral langsung, lalu grup bundle, lalu ketua tim, di SQL agar
        // pagination mengurutkan seluruh hasil, bukan hanya satu halaman.
        $referralCode = $this->referralCodeExpression();

        $query
            ->orderByRaw("CASE WHEN {$referralCode} IS NULL THEN 1 ELSE 0 END ASC")
            ->orderByRaw("{$referralCode} {$direction}")
            ->orderByDesc('form_answers.created_at');
    }

    /**
     * Ekspresi kode referral per baris jawaban: redemption langsung, lalu grup bundle,
     * lalu ketua tim. Dipakai bersama oleh sort dan filter agar SQL-nya tunggal.
     */
    private function referralCodeExpression(): string
    {
        return "COALESCE("
            . "(SELECT referral_codes.code FROM referral_redemptions "
            . "JOIN referral_codes ON referral_codes.id = referral_redemptions.referral_code_id "
            . "WHERE referral_redemptions.form_answer_id = form_answers.id LIMIT 1), "
            . "(SELECT referral_codes.code FROM referral_redemptions "
            . "JOIN referral_codes ON referral_codes.id = referral_redemptions.referral_code_id "
            . "WHERE referral_redemptions.form_id = form_answers.form_id "
            . "AND referral_redemptions.group_token = form_answers.group_token "
            . "AND form_answers.group_token IS NOT NULL LIMIT 1), "
            . "(SELECT referral_codes.code FROM referral_redemptions "
            . "JOIN referral_codes ON referral_codes.id = referral_redemptions.referral_code_id "
            . "WHERE referral_redemptions.form_answer_id = form_answers.leader_form_answer_id "
            . "AND form_answers.leader_form_answer_id IS NOT NULL LIMIT 1)"
            . ")";
    }

    /**
     * Filter daftar jawaban dari query string. Nilai di luar daftar diabaikan agar URL
     * yang diedit manual tidak menghasilkan error.
     *
     * @return array{search: string|null, review_status: string|null, referral: string|null}
     */
    private function submissionFilters(Request $request): array
    {
        $search = $request->query('search');
        $reviewStatus = $request->query('review_status');
        $referral = $request->query('referral');
        $search = is_string($search) ? trim($search) : '';

        return [
            'search' => $search === '' ? null : mb_substr($search, 0, 100),
            'review_status' => is_string($reviewStatus) && FormAnswerReviewStatus::tryFrom($reviewStatus) !== null
                ? $reviewStatus
                : null,
            'referral' => in_array($referral, ['with', 'without'], true) ? $referral : null,
        ];
    }

    /**
     * Terapkan pencarian dan filter ke daftar jawaban.
     *
     * Pencarian sengaja hanya menyentuh nama/email pengirim dan kode referral: memindai
     * kolom JSON `answers` memaksa full scan pada tabel domain besar.
     *
     * @param  array{search: string|null, review_status: string|null, referral: string|null}  $filters
     */
    private function applySubmissionFilters(Builder $query, array $filters): void
    {
        if ($filters['review_status'] !== null) {
            $query->where('review_status', $filters['review_status']);
        }

        $referralExpression = $this->referralCodeExpression();

        if ($filters['referral'] === 'with') {
            $query->whereRaw("{$referralExpression} IS NOT NULL");
        } elseif ($filters['referral'] === 'without') {
            $query->whereRaw("{$referralExpression} IS NULL");
        }

        if ($filters['search'] === null) {
            return;
        }

        // Wildcard milik pengguna di-escape agar `%` dan `_` dicari sebagai karakter biasa.
        $term = '%' . addcslashes($filters['search'], '%_\\') . '%';

        $query->where(static function (Builder $search) use ($term, $referralExpression): void {
            $search
                ->whereHas('user', static function (Builder $user) use ($term): void {
                    $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                })
                ->orWhereRaw("{$referralExpression} LIKE ?", [$term]);
        });
    }

    private function resolveAnswersPerPage(Request $request): int
    {
        $requested = $request->integer('per_page', self::ANSWERS_PER_PAGE_DEFAULT);

        return in_array($requested, self::ANSWERS_PER_PAGE_OPTIONS, true)
            ? $requested
            : self::ANSWERS_PER_PAGE_DEFAULT;
    }

    /**
     * Hanya baris yang lolos aturan bulk review: pending dan pendaftaran tunggal.
     *
     * @return \Illuminate\Database\Eloquent\Builder<FormAnswer>
     */
    private function bulkReviewEligibleQuery(Form $form): \Illuminate\Database\Eloquent\Builder
    {
        return FormAnswer::query()
            ->where('form_id', $form->id)
            ->whereListedForOrganizerParticipantRoster()
            ->where(static function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', FormAnswerReviewStatus::Pending->value);
            })
            ->whereNull('registration_role')
            ->where(static function ($query): void {
                $query->whereNull('group_token')->orWhere('group_token', '');
            })
            ->whereNull('member_confirmation_status');
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

        // D-Kelas memakai field sistem dan menyembunyikan toggle kustom; konversi saat
        // flag kustom masih aktif akan membekukan opsi yang tidak bisa dibuka lagi.
        if (($data['metadata']['purpose'] ?? null) === FormPurpose::DKelas->value
            && $form->purpose() !== FormPurpose::DKelas
            && $this->duCourseFieldService->hasFlaggedCustomField($form)) {
            throw ValidationException::withMessages([
                'metadata.purpose' => 'Matikan integrasi DU pada field kustom sebelum mengubah tujuan form menjadi D-Kelas.',
            ]);
        }

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
                $incomingSystemField = collect($data['fields'])
                    ->first(fn (array $field): bool => ($field['name'] ?? null) === 'du_course_uid');
                $systemField = $oldById->first(fn (FormField $field): bool => $field->name === 'du_course_uid');
                $data['fields'] = collect($data['fields'])
                    ->reject(fn (array $field): bool => ($field['name'] ?? null) === 'du_course_uid')
                    ->values()->all();
                if (($form->metadata['purpose'] ?? null) === \App\Enums\FormPurpose::DKelas->value) {
                    $this->duCourseFieldService->ensure($form);
                    $systemField = $form->formFields()->where('name', 'du_course_uid')->first();
                }

                // Row sistem tetap ditolak dari snapshot, tetapi teks yang boleh diedit
                // admin (label/description) diterapkan ke field hasil ensure().
                if ($systemField !== null && is_array($incomingSystemField)) {
                    $this->applySystemFieldEditableText($systemField, $incomingSystemField);
                }

                // Field dengan flag redeem DU membekukan konten opsinya sebelum ditulis.
                $data['fields'] = $this->duCourseFieldService
                    ->enforceLockedOptionContent($form, $data['fields']);

                $incomingIds = collect($data['fields'])->pluck('id')->filter()->all();
                if ($systemField !== null) {
                    $incomingIds[] = $systemField->id;
                }

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

    /**
     * Terapkan hanya teks yang boleh diedit admin pada field sistem DU.
     * Metadata, tipe, urutan, dan kunci klien lain tidak pernah dipakai.
     *
     * @param  array<string, mixed>  $incoming
     */
    private function applySystemFieldEditableText(FormField $field, array $incoming): void
    {
        $changed = false;

        $label = $incoming['label'] ?? null;
        if (is_string($label) && trim($label) !== '' && $label !== $field->label) {
            $field->label = $label;
            $changed = true;
        }

        if (array_key_exists('description', $incoming)) {
            $description = $incoming['description'];
            if ((is_string($description) || $description === null) && $description !== $field->description) {
                $field->description = $description;
                $changed = true;
            }
        }

        if ($changed) {
            $field->save();
        }
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
                'metadata' => $this->normalizeMetadata($data['metadata'] ?? null, $form),
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
            $attributes['metadata'] = $this->normalizeMetadata($data['metadata'], $form);
        }

        return $attributes;
    }

    /**
     * Terapkan aturan tujuan form pada metadata dari klien.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $metadata, ?Form $form = null): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        return FormPurposeRules::apply(
            $metadata,
            $form?->purpose()->value,
            $form !== null && $form->formAnswers()->exists(),
        );
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

    /** @param  Collection<int, DuCourse>|null  $activeCourses */
    private function formFieldToInertia(FormField $f, ?Collection $activeCourses = null): array
    {
        $meta = $f->metadata;
        if ($meta instanceof \Illuminate\Support\Collection) {
            $meta = $meta->all();
        } elseif (is_object($meta) && method_exists($meta, 'toArray')) {
            $meta = $meta->toArray();
        } else {
            $meta = (array) $meta;
        }

        return $this->duCourseFieldService->applyOptions([
            'id' => $f->id,
            'type' => FormFieldTypeMapping::toApiType($f->input_type),
            'label' => $f->label,
            'description' => $f->description,
            'name' => $f->name,
            'order' => $f->order,
            'metadata' => $meta,
            'is_append' => (bool) $f->is_append,
        ], null, $activeCourses);
    }

    /**
     * Konteks drawer DU untuk satu request: field kelas, katalog aktif, indeks saran,
     * indeks cache penuh, dan mapping opsi. Satu query cache, dipakai semua jawaban.
     *
     * @return array{
     *     field: FormField|null,
     *     active: Collection<int, DuCourse>,
     *     catalog: list<array{uid: string, title: string}>,
     *     mapping: array<string, array{course_uid: string, course_title: string}>,
     *     suggestions: array<string, DuCourse|null>,
     *     courses: array<string, DuCourse|null>,
     * }|null
     */
    private function duDrawerContext(Form $form): ?array
    {
        if (! $this->duForms->enabled($form)) {
            return null;
        }

        $field = $this->duForms->classField($form);
        $cachedCourses = $this->duCourses->cached();
        $activeCourses = $cachedCourses
            ->filter(static fn (DuCourse $course): bool => $course->is_active)
            ->values();

        return [
            'field' => $field,
            'active' => $activeCourses,
            'catalog' => $activeCourses
                ->map(static fn (DuCourse $course): array => [
                    'uid' => $course->uid,
                    'title' => $course->title,
                ])
                ->values()
                ->all(),
            'mapping' => $field === null ? [] : $this->duForms->mappingFor($field),
            'suggestions' => $this->duForms->activeSuggestions($activeCourses),
            'courses' => $this->duForms->activeSuggestions($cachedCourses),
        ];
    }

    /**
     * Payload DU satu jawaban. Mapping divalidasi terhadap cache penuh: UID yang hilang
     * berarti belum terhubung, UID tidak aktif tetap terhubung (DU yang berwenang).
     * Saran hanya berasal dari katalog aktif dan hanya saat belum ada mapping.
     *
     * @param  array{
     *     field: FormField|null,
     *     active: Collection<int, DuCourse>,
     *     catalog: list<array{uid: string, title: string}>,
     *     mapping: array<string, array{course_uid: string, course_title: string}>,
     *     suggestions: array<string, DuCourse|null>,
     *     courses: array<string, DuCourse|null>,
     * }  $context
     * @return array{
     *     enabled: bool,
     *     class_field: array{id: string, name: string, label: string}|null,
     *     class_value: string|null,
     *     mapping: array{course_uid: string, course_title: string, is_active: bool}|null,
     *     suggestion: array{course_uid: string, course_title: string, is_active: bool}|null,
     *     issuance: array{status: string, code: string|null, message: string|null}|null,
     *     can_issue: bool,
     * }
     */
    private function duPayload(FormAnswer $answer, array $context): array
    {
        $field = $context['field'];
        $fieldName = $field instanceof FormField ? $field->name : DuFormCourseResolver::SYSTEM_FIELD_NAME;
        $answers = is_array($answer->answers) ? $answer->answers : [];
        $rawValue = $answers[$fieldName] ?? null;
        $classValue = is_string($rawValue) && $rawValue !== '' ? $rawValue : null;

        $isSystemField = $field === null || $field->name === DuFormCourseResolver::SYSTEM_FIELD_NAME;
        $course = null;

        if ($classValue !== null) {
            $course = $isSystemField
                ? ($context['courses'][$classValue] ?? null)
                : $this->mappedCourse($context['mapping'][$classValue] ?? null, $context['courses']);
        }

        $mapping = $this->courseMapping($course);

        $issuance = $answer->duRedeemIssuance;
        $issuancePayload = $this->issuancePayload($issuance);

        return [
            'enabled' => true,
            'class_field' => $field === null ? null : [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
            ],
            'class_value' => $classValue,
            'mapping' => $mapping,
            'suggestion' => $mapping === null && $classValue !== null
                ? $this->courseMapping($context['suggestions'][$classValue] ?? null)
                : null,
            'issuance' => $issuancePayload,
            // Status `unknown` tidak pernah diulang otomatis.
            'can_issue' => $answer->review_status === FormAnswerReviewStatus::Accepted
                && $mapping !== null
                && ($issuance === null || in_array($issuance->status, [
                    DuRedeemStatus::Pending,
                    DuRedeemStatus::Failed,
                ], true)),
        ];
    }

    /**
     * Payload drawer per jawaban: konteks penuh untuk form DU-enabled, atau payload
     * khusus kode saat flag sudah dimatikan tetapi kode sudah pernah terbit.
     * Kode adalah hasil yang tidak bisa hilang; rollback flag tidak boleh menyembunyikannya.
     *
     * @param  array{
     *     field: FormField|null,
     *     active: Collection<int, DuCourse>,
     *     catalog: list<array{uid: string, title: string}>,
     *     mapping: array<string, array{course_uid: string, course_title: string}>,
     *     suggestions: array<string, DuCourse|null>,
     *     courses: array<string, DuCourse|null>,
     * }|null  $context
     * @return array<string, mixed>|null
     */
    private function duPayloadForAnswer(FormAnswer $answer, ?array $context): ?array
    {
        if ($context !== null) {
            return $this->duPayload($answer, $context);
        }

        if ($answer->duRedeemIssuance === null) {
            return null;
        }

        return [
            'enabled' => false,
            'class_field' => null,
            'class_value' => null,
            'mapping' => null,
            'suggestion' => null,
            'issuance' => $this->issuancePayload($answer->duRedeemIssuance),
            'can_issue' => false,
        ];
    }

    /** @return array{status: string, code: string|null, message: string|null}|null */
    private function issuancePayload(?DuRedeemIssuance $issuance): ?array
    {
        return $issuance === null ? null : [
            'status' => $issuance->status->value,
            'code' => $issuance->status === DuRedeemStatus::Issued ? $issuance->redeem_code : null,
            'message' => $issuance->status === DuRedeemStatus::Failed ? $issuance->failure_reason : null,
        ];
    }

    /**
     * Course cache untuk satu entri `du_option_map`; UID yang tidak ada di cache
     * berarti opsi sudah tidak terhubung.
     *
     * @param  array{course_uid: string, course_title: string}|null  $entry
     * @param  array<string, DuCourse|null>  $courses
     */
    private function mappedCourse(?array $entry, array $courses): ?DuCourse
    {
        $uid = $entry['course_uid'] ?? null;

        if (! is_string($uid)) {
            return null;
        }

        $course = $courses[$uid] ?? null;

        return $course instanceof DuCourse ? $course : null;
    }

    /** @return array{course_uid: string, course_title: string, is_active: bool}|null */
    private function courseMapping(?DuCourse $course): ?array
    {
        return $course === null ? null : [
            'course_uid' => $course->uid,
            'course_title' => $course->title,
            'is_active' => $course->is_active,
        ];
    }

    /**
     *
     * @param  list<string>  $fileFieldNames
     * @param  array{
     *     field: FormField|null,
     *     active: Collection<int, DuCourse>,
     *     catalog: list<array{uid: string, title: string}>,
     *     mapping: array<string, array{course_uid: string, course_title: string}>,
     *     suggestions: array<string, DuCourse|null>,
     *     courses: array<string, DuCourse|null>,
     * }|null  $duContext
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
     * referral: array<string, mixed>|null,
     * du: array{
     *     enabled: bool,
     *     class_field: array{id: string, name: string, label: string}|null,
     *     class_value: string|null,
     *     mapping: array{course_uid: string, course_title: string, is_active: bool}|null,
     *     suggestion: array{course_uid: string, course_title: string, is_active: bool}|null,
     *     issuance: array{status: string, code: string|null, message: string|null}|null,
     *     can_issue: bool,
     * }|null,
     * }
     *
     */
    private function submissionToInertia(FormAnswer $answer, array $fileFieldNames, ?array $duContext): array
    {
        $redemption = $answer->referralRedemption ?? $answer->teamLeaderSubmission?->referralRedemption;
        $referral = $redemption === null ? null : [
            'code' => $redemption->attribution_snapshot['code'] ?? $redemption->referralCode?->code,
            'attribution' => $redemption->attribution_snapshot['assigned_name']
                ?? $redemption->attribution_snapshot['attribution_name']
                ?? '',
            'unit' => $redemption->group_token === null ? 'single' : 'group',
            'redeemed_at' => $redemption->redeemed_at?->toISOString(),
        ];

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
            'referral' => $referral,
            'du' => $this->duPayloadForAnswer($answer, $duContext),
        ];
    }
}
