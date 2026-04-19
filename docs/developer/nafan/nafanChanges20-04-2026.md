# Nafan Changes — 20 April 2026

Dokumentasi ringkas untuk perubahan pada sesi tanggal **20-04-2026**. Fokus utama sesi ini adalah **mengubah field `category` dan `session` pada entitas Event dari single-value menjadi multi-token (CSV di database, `string[]` di API / frontend)**, sekaligus mendukung nilai custom (non-enum) selama bentuknya valid.

> Catatan penting: kolom `events.category` dan `events.session` berubah tipe menjadi `TEXT`. Wajib menjalankan `php artisan migrate`. API / Inertia payload untuk kedua field sekarang berbentuk `string[]` (tidak lagi `string`).

---

## Ringkasan (TL;DR)

- Kolom `events.category` dan `events.session` diperluas menjadi `TEXT` dan sekarang menyimpan beberapa token dalam format CSV (contoh: `"rkt,must"`, `"general,workshop"`).
- Dua field tersebut tetap mendukung nilai enum (`EventCategory`, `EventSession`) dan **juga** custom token (huruf / angka / spasi / `_` / `-`, maksimum 64 karakter) untuk event lama yang mengandung nilai bebas seperti `"must"` atau `"workshop"`.
- Tambahan service baru untuk membangun daftar opsi multi-select dari data yang sebenarnya ada di DB:
  - `App\Services\Event\EventCategoryOptionService`
  - `App\Services\Event\EventSessionOptionService`
- Tambahan helper token pusat untuk logika resolve enum + custom:
  - `App\Support\EventCategoryTokens`
  - `App\Support\EventSessionTokens`
- Tambahan custom validation rule dan trait normalisasi:
  - `App\Rules\CommaSeparatedEventCategories`, `App\Rules\CommaSeparatedEventSessions`
  - `App\Http\Requests\Concerns\NormalizesEventCategoryRequest`, `App\Http\Requests\Concerns\NormalizesEventSessionRequest`
- `App\Http\Resources\EventResource` kini mengembalikan `category` dan `session` sebagai `list<string>` (hasil explode + dedup + trim).
- Form admin:
  - Livewire/Filament (`CreateForm`, `EditForm`) diubah menjadi `Select::make(...)->multiple()` + `dehydrateStateUsing(...)` CSV, dan di Livewire display (`TableMode`, `EventDetail`) serta Blade `card-mode.blade.php` badge kini di-render per token.
  - Inertia/Vue (`Dashboard/Events/Create.vue`, `Edit.vue`, `Index.vue`, `Show.vue`, `Dashboard/User/EventDetail.vue`, `EventDetail.vue`) dipindah ke multi-select via `ComboboxTagInput` dan badge `v-for`.
- Tipe `IEvent` pada frontend: `category: string[]`, `session: string[]`. Dummy data disesuaikan.
- Controller `Dashboard\Events\EventController` menginjeksi kedua service opsi dan mengekspos `formOptions()` + `filterOptions()` berbasis data DB.
- Tambahan pengujian unit:
  - `tests/Unit/EventCategoryOptionServiceTest.php`
  - `tests/Unit/EventSessionOptionServiceTest.php`

---

## Daftar File yang Diubah / Ditambahkan

### Ditambahkan (Untracked)

- `database/migrations/2026_04_19_120000_extend_events_category_column.php`
- `database/migrations/2026_04_19_130000_extend_events_session_column.php`
- `app/Support/EventCategoryTokens.php`
- `app/Support/EventSessionTokens.php`
- `app/Services/Event/EventCategoryOptionService.php`
- `app/Services/Event/EventSessionOptionService.php`
- `app/Services/Event/EventService.php`
- `app/Rules/CommaSeparatedEventCategories.php`
- `app/Rules/CommaSeparatedEventSessions.php`
- `app/Http/Requests/Concerns/NormalizesEventCategoryRequest.php`
- `app/Http/Requests/Concerns/NormalizesEventSessionRequest.php`
- `app/Http/Requests/StoreEventRequest.php`
- `app/Http/Requests/UpdateEventRequest.php`
- `app/Http/Resources/EventResource.php`
- `app/Models/Event.php`
- `app/Livewire/Event/CreateForm.php`
- `app/Livewire/Event/EditForm.php`
- `app/Livewire/Event/ListPage.php`
- `database/factories/EventFactory.php`
- `database/seeders/EventSeeder.php`
- `lang/en/events.php`
- `lang/id/events.php`
- `resources/js/pages/Dashboard/Events/Create.vue`
- `resources/js/pages/Dashboard/Events/Edit.vue`
- `resources/js/pages/Dashboard/Events/Index.vue`
- `resources/js/lib/eventCategories.ts`
- `tests/Unit/EventCategoryOptionServiceTest.php`
- `tests/Unit/EventSessionOptionServiceTest.php`
- `tests/Feature/EventManagementTest.php`

### Dimodifikasi

- `app/Http/Controllers/Dashboard/Events/EventController.php`
- `app/Livewire/Event/TableMode.php`
- `app/Livewire/Event/EventDetail.php`
- `resources/views/livewire/event/card-mode.blade.php`
- `resources/js/pages/Dashboard/Events/Show.vue`
- `resources/js/pages/Dashboard/User/EventDetail.vue`
- `resources/js/pages/EventDetail.vue`
- `resources/js/types/event.d.ts`
- `resources/js/lib/dummyData.ts`

---

## Detail Perubahan Backend

### 1. Migration — kolom `events.category` dan `events.session` jadi `TEXT`

File: `database/migrations/2026_04_19_120000_extend_events_category_column.php` dan `database/migrations/2026_04_19_130000_extend_events_session_column.php`.

Keduanya mengubah kolom yang sebelumnya `string` menjadi `text`, dengan `down()` yang mengembalikan ke `string` dengan panjang aslinya.

```8:20:database/migrations/2026_04_19_130000_extend_events_session_column.php
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('session')->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('session', 50)->change();
        });
    }
```

Jalankan:

```bash
php artisan migrate
```

### 2. `app/Models/Event.php`

- Cast `category` dan `session` yang sebelumnya `EventCategory::class` / `EventSession::class` **dihapus**. Kolom sekarang adalah string CSV biasa; parsing dan formatting dilakukan di layer service / resource.
- Tambah dua Eloquent scope untuk filter: `forCategoryTokens(array $tokens)` dan `forSessionTokens(array $tokens)`. Pola query sama untuk keduanya — match persis atau substring CSV dengan `,` sebagai pembatas.

```83:100:app/Models/Event.php
    #[Scope]
    public function forCategoryTokens(Builder $query, array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($tokens): void {
            foreach ($tokens as $cat) {
                $outer->where(function (Builder $inner) use ($cat): void {
                    $inner->where('category', $cat)
                        ->orWhere('category', 'like', $cat.',%')
                        ->orWhere('category', 'like', '%,'.$cat.',%')
                        ->orWhere('category', 'like', '%,'.$cat);
                });
            }
        });
    }
```

Scope `forSessionTokens()` pada baris 107-124 memakai pola identik untuk kolom `session`. Scope lama `forListPage()` tetap mengikutsertakan `category` dan `session` (baris 138-139) supaya payload list tidak berubah.

### 3. Helper token — `EventCategoryTokens` & `EventSessionTokens`

Kedua helper ini mengekapsulasi logika resolusi satu token menjadi nilai enum backing (toleran terhadap huruf besar/kecil, spasi, snake-case, dan nama case) atau menerimanya sebagai custom token.

```16:48:app/Support/EventSessionTokens.php
    public static function tryResolveEnumBackingValue(string $token): ?string
    {
        $trimmed = trim($token);

        if ($trimmed === '') {
            return null;
        }

        $lower = Str::lower($trimmed);

        $fromLower = EventSession::tryFrom($lower);

        if ($fromLower !== null) {
            return $fromLower->value;
        }

        $snake = Str::snake(preg_replace('/\s+/u', ' ', $lower));
        $fromSnake = EventSession::tryFrom($snake);

        if ($fromSnake !== null) {
            return $fromSnake->value;
        }
```

Aturan custom token: 1-64 karakter, hanya huruf / angka / spasi / `_` / `-`, tidak bentrok dengan enum.

```54:67:app/Support/EventCategoryTokens.php
    public static function isValidCustomToken(string $token): bool
    {
        $t = trim($token);

        if ($t === '' || mb_strlen($t) > 64) {
            return false;
        }

        if (self::tryResolveEnumBackingValue($t) !== null) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}]+(?:[\p{L}\p{N} _-]*[\p{L}\p{N}]+)?$/u', $t);
    }
```

`normalizeCustomValue()` mengubah custom token menjadi bentuk kanonik `Str::lower(trim($token))` untuk disimpan.

### 4. Service opsi — `EventCategoryOptionService` & `EventSessionOptionService`

Method `forForm()` membaca nilai mentah `events.category` / `events.session` (bypass cast enum dengan `toBase()`), lalu mendelegasikan ke `optionsForRawCategoryRows()` / `optionsForRawSessionRows()`. Method kedua ini dibuat publik agar mudah diuji tanpa DB.

```19:28:app/Services/Event/EventSessionOptionService.php
    public function forForm(): array
    {
        $rows = Event::query()
            ->toBase()
            ->whereNotNull('session')
            ->where('session', '!=', '')
            ->pluck('session');

        return $this->optionsForRawSessionRows($rows);
    }
```

Setiap raw row **di-explode per koma** terlebih dahulu, barulah tiap segment di-resolve. Ini yang memungkinkan nilai lama `"general, workshop"` muncul sebagai dua opsi terpisah (`general` + `workshop`).

```41:68:app/Services/Event/EventSessionOptionService.php
        foreach ($rawSessionRows as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $hasAnyRow = true;

            foreach (explode(',', $raw) as $segment) {
                $trimmed = trim($segment);

                if ($trimmed === '') {
                    continue;
                }

                $enumVal = EventSessionTokens::tryResolveEnumBackingValue($trimmed);

                if ($enumVal !== null) {
                    continue;
                }

                if (! EventSessionTokens::isValidCustomToken($trimmed)) {
                    continue;
                }

                $v = EventSessionTokens::normalizeCustomValue($trimmed);
                $customValueToLabel[$v] ??= Str::title(Str::lower($trimmed));
            }
        }
```

Output: array `[{ value, label }]` yang selalu memuat semua enum (dengan label i18n) diikuti custom token yang valid, diurutkan alfabetis. Jika tidak ada baris valid, fallback ke `allEnumOptions()`.

Behaviour identik berlaku untuk `EventCategoryOptionService`.

### 5. Validation rule — `CommaSeparatedEventCategories` & `CommaSeparatedEventSessions`

Rule ini memastikan value string CSV yang disubmit benar-benar berisi enum atau custom token valid setelah di-explode.

```9:32:app/Rules/CommaSeparatedEventSessions.php
final class CommaSeparatedEventSessions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('validation.required', ['attribute' => $attribute]));

            return;
        }

        foreach (array_unique(array_filter(array_map('trim', explode(',', $value)))) as $part) {
            if (EventSessionTokens::tryResolveEnumBackingValue($part) !== null) {
                continue;
            }

            if (EventSessionTokens::isValidCustomToken($part)) {
                continue;
            }

            $fail(__('validation.enum', ['attribute' => $attribute]));

            return;
        }
    }
}
```

### 6. Trait normalisasi — `NormalizesEventCategoryRequest` & `NormalizesEventSessionRequest`

Frontend kadang mengirim `category` / `session` sebagai array (`string[]`), kadang sebagai string CSV. Trait ini menyeragamkan payload menjadi CSV sebelum validasi rule dijalankan: menerima array/CSV, men-trim, dedup, menempatkan enum di depan (sesuai urutan `cases()`), custom di belakang (sort alfabetis).

```26:68:app/Http/Requests/Concerns/NormalizesEventSessionRequest.php
        $order = array_flip(array_map(
            static fn (EventSession $s) => $s->value,
            EventSession::cases()
        ));

        $enumParts = [];
        $customParts = [];

        foreach ($segments as $segment) {
            $t = trim((string) $segment);

            if ($t === '') {
                continue;
            }

            $enumVal = EventSessionTokens::tryResolveEnumBackingValue($t);

            if ($enumVal !== null) {
                $enumParts[$enumVal] = $enumVal;

                continue;
            }

            if (EventSessionTokens::isValidCustomToken($t)) {
                $v = EventSessionTokens::normalizeCustomValue($t);
                $customParts[$v] = $v;
            }
        }

        $enumOrdered = collect($enumParts)
            ->keys()
            ->sortBy(static fn (string $v) => $order[$v] ?? PHP_INT_MAX)
            ->values()
            ->all();

        $customOrdered = collect($customParts)
            ->keys()
            ->sort()
            ->values()
            ->all();

        $this->merge(['session' => implode(',', array_merge($enumOrdered, $customOrdered))]);
    }
```

### 7. `StoreEventRequest` & `UpdateEventRequest`

Kedua form request menggunakan dua trait normalisasi sekaligus dan rule CSV baru. Aturan dasar `required`, `string`, `max:2048`.

```26:48:app/Http/Requests/StoreEventRequest.php
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'registration_start' => ['required', 'date', 'before_or_equal:start_date'],
            'registration_end' => ['required', 'date', 'after:registration_start', 'before_or_equal:end_date'],
            'start_date' => ['required', 'date', 'after_or_equal:registration_start'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'quota' => ['required', 'integer', 'min:1', 'max:65535'],
            'price' => ['required', 'numeric', 'min:0'],
            'session' => ['required', 'string', 'max:2048', new CommaSeparatedEventSessions()],
            'category' => ['required', 'string', 'max:2048', new CommaSeparatedEventCategories()],
            'banner' => ['required', 'image', 'max:10240'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeNormalizedCategoryFromRequest();
        $this->mergeNormalizedSessionFromRequest();
```

`UpdateEventRequest` identik, kecuali `banner` berstatus `sometimes|nullable`.

### 8. `EventResource`

`category` dan `session` tidak lagi berupa string tunggal. Keduanya di-explode lewat helper `explodeCsv()` menjadi `list<string>` (sudah trim + dedup).

```14:37:app/Http/Resources/EventResource.php
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'banner' => $this->banner,
            'banner_url' => $this->banner ? Storage::disk('public')->url($this->banner) : null,
            'price' => $this->price,
            'quota' => $this->quota,
            'registered_count' => $this->registered_count,
            'session' => $this->sessionTokens(),
            'category' => $this->categoryTokens(),
            'status' => $this->status->value,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'registration_start' => $this->registration_start?->toIso8601String(),
            'registration_end' => $this->registration_end?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
```

Helper privat `categoryTokens()` / `sessionTokens()` keduanya delegasi ke `explodeCsv()` (baris 62-74).

### 9. `EventService` & Livewire `ListPage`

- `EventService` (method `paginateForAdminIndex` dsb.) sekarang menggunakan scope `forCategoryTokens(...)` dan `forSessionTokens(...)` (baris 88 dan 92) bukan lagi `whereIn('category', ...)` / `whereIn('session', ...)`.
- `App\Livewire\Event\ListPage::getQuery()` (baris 159 dan 162) memakai scope yang sama.

Efeknya filter "rkt" akan menangkap event berisi `"rkt,must"`, `"etc,rkt"`, atau `"rkt"`. Hal yang sama untuk session.

### 10. Livewire form — `CreateForm` & `EditForm`

Filament `Select::make('session')` dan `Select::make('category')` sekarang multi-select dan dehidrasi ke string CSV.

```106:135:app/Livewire/Event/CreateForm.php
            Select::make('session')
                ->label(ucfirst(__('events.session')))
                ->options(EventSession::class)
                ->multiple()
                ->required()
                ->native(false)
                ->dehydrateStateUsing(function (mixed $state): string {
                    if (! is_array($state)) {
                        return is_string($state) ? trim($state) : '';
                    }

                    $parts = array_values(array_unique(array_filter(array_map('trim', $state))));

                    return implode(',', $parts);
                }),
            Select::make('category')
                ->label(__('events.categories'))
                ->options(EventCategory::class)
                ->multiple()
                ->required()
                ->native(false)
                ->dehydrateStateUsing(function (mixed $state): string {
                    if (! is_array($state)) {
                        return is_string($state) ? trim($state) : '';
                    }

                    $parts = array_values(array_unique(array_filter(array_map('trim', $state))));

                    return implode(',', $parts);
                }),
```

`EditForm::mount()` mengubah CSV dari DB menjadi array supaya state awal Select kompatibel (`app/Livewire/Event/EditForm.php` baris 176-185).

### 11. Livewire display — `TableMode` & `EventDetail`

Dua display infolist kini render multi-badge per token. Setiap `TextEntry::make('category' | 'session')` menggunakan kombinasi `state()` + `formatStateUsing()` + `badge()`.

```58:65:app/Livewire/Event/TableMode.php
                        TextEntry::make('category')
                            ->state(fn ($record) => self::explodeCsv($record['category'] ?? ''))
                            ->formatStateUsing(fn (string $state) => EventCategory::tryFrom($state)?->getLabel() ?? $state)
                            ->badge(),
                        TextEntry::make('session')
                            ->state(fn ($record) => self::explodeCsv($record['session'] ?? ''))
                            ->formatStateUsing(fn (string $state) => EventSession::tryFrom($state)?->getLabel() ?? $state)
                            ->badge(),
```

Helper privat `TableMode::explodeCsv()` (baris 120-137) dan `EventDetail::explodeCsv()` (akhir file) memiliki implementasi sama.

### 12. Blade `resources/views/livewire/event/card-mode.blade.php`

Loop token `category` dan `session`, lalu render badge terpisah. Fallback label memakai `Str::title(Str::of(...)->replace(['_','-'], ' '))` untuk custom token yang tidak punya entri terjemahan.

### 13. Factory & Seeder

- `database/factories/EventFactory.php`: `'category'` dan `'session'` disimpan sebagai **string `->value`** dari enum (bukan instance enum).
- `database/seeders/EventSeeder.php`: semua event seed mengikuti pola yang sama (`EventCategory::X->value`, `EventSession::X->value`). Tidak ada struktur yang berubah selain tipe nilai.

### 14. `EventController::formOptions()` & `filterOptions()`

Controller menerima injeksi `EventCategoryOptionService` dan `EventSessionOptionService`, lalu mengekspos opsi select berbasis data dinamis.

```23:28:app/Http/Controllers/Dashboard/Events/EventController.php
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventCategoryOptionService $eventCategoryOptionService,
        private readonly EventSessionOptionService $eventSessionOptionService,
    ) {
    }
```

```154:176:app/Http/Controllers/Dashboard/Events/EventController.php
    private function formOptions(): array
    {
        return [
            'categories' => $this->eventCategoryOptionService->forForm(),
            'sessions' => $this->eventSessionOptionService->forForm(),
        ];
    }

    private function filterOptions(): array
    {
        return [
            ...$this->formOptions(),
            'statuses' => collect(EventStatus::cases())
                ->map(fn (EventStatus $s) => [
                    'value' => $s->value,
                    'label' => $s->getLabel(),
                ])
                ->values()
                ->all(),
        ];
    }
```

Dengan ini, setiap halaman admin yang memuat form atau filter events otomatis menampilkan enum + custom token yang memang ada di DB.

---

## Detail Perubahan Frontend

### 1. Tipe — `resources/js/types/event.d.ts`

`IEvent.category` dan `IEvent.session` keduanya `string[]`. Pastikan semua konsumen event di-adjust.

### 2. Helper — `resources/js/lib/eventCategories.ts`

Helper kecil yang dipakai banyak halaman:

```ts
export function toCategoryList(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map((v) => String(v).trim()).filter(Boolean);
    }
    if (typeof value === 'string') {
        return value
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    }
    return [];
}

export function primaryCategory(value: unknown): string {
    return toCategoryList(value)[0] ?? '';
}
```

Dipakai sebagai adaptor untuk nilai lama (CSV string) maupun baru (`string[]`).

### 3. Dummy data — `resources/js/lib/dummyData.ts`

Semua field `category` dan `session` pada `dummyEvents` diubah menjadi array (`['rkt']`, `['general']`, dst).

### 4. Form create/edit — `Dashboard/Events/Create.vue` & `Edit.vue`

- Input session dan category memakai `ComboboxTagInput` dengan `:max-tags="maxSessionTags"` / `:max-tags="maxCategoryTags"` (= panjang list opsi), `:allow-custom="false"`.
- `form.session` / `form.category` internal disimpan sebagai CSV string supaya kompatibel dengan `ComboboxTagInput`. Saat submit, `form.transform(...)` mengubah keduanya ke `string[]` memakai `toTokenList(...)`.
- Di `Edit.vue`, state awal dibentuk dari `toTokenList(eventData.session)` dan `toTokenList(eventData.category)` lalu `.join(',')`.

Contoh submit (dari `Create.vue`):

```ts
form.transform((data) => ({
    ...data,
    category: toTokenList(data.category),
    session: toTokenList(data.session),
}));
```

### 5. List — `Dashboard/Events/Index.vue`

- Helper lokal `eventTokenList(...)` dipakai untuk mengubah `event.category` / `event.session` jadi `string[]` pada filter client-side dan badge `v-for`.
- Filter: `events.filter((e) => eventTokenList(e.category).includes(filterCategory.value))` dan versi session serupa.

### 6. Detail — `Dashboard/Events/Show.vue`

Meta block "Session" kini memetakan token menjadi label:

```ts
{
    title: 'Session',
    value: parseCategories(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
    icon: Clock,
}
```

Badge kategori juga sudah `v-for` dari `parseCategories(event.category)`.

### 7. Halaman publik & user

- `resources/js/pages/Dashboard/User/EventDetail.vue` dan `resources/js/pages/EventDetail.vue` memakai `toCategoryList(...)` untuk menampilkan session/category (mendukung banyak badge/label, dan mem-`join(', ')` label session).
- Helper `primaryCategory(...)` dipakai untuk kebutuhan color map tunggal (mis. warna banner) bila hanya boleh 1 warna.

---

## Bahasa & i18n

Ditambahkan key `events.categories` di `lang/en/events.php` dan `lang/id/events.php` (mengikuti `events.category`). Dipakai di form admin Filament (`Select::make('category')->label(__('events.categories'))`).

---

## Pengujian

- `tests/Unit/EventCategoryOptionServiceTest.php` — 5 test case:
  1. `returns_all_enum_options_when_no_raw_rows_resolve`
  2. `includes_all_enums_plus_custom_tokens_from_rows`
  3. `appends_custom_token_must_after_enums`
  4. `ignores_tokens_that_are_not_enum_and_not_valid_custom`
  5. `resolves_non_rkt_from_spaced_token`
- `tests/Unit/EventSessionOptionServiceTest.php` — 6 test case (termasuk `splits_comma_separated_session_row_into_tokens` yang menjamin "general, workshop" menghasilkan 2 token dan `resolves_media_creative_from_spaced_token`).

Kedua test class meng-extend `Tests\TestCase` (bukan `PHPUnit\Framework\TestCase`) agar container Laravel (khususnya translator) aktif. Tes dijalankan tanpa driver SQLite karena seluruh input diberikan manual lewat `optionsForRawCategoryRows(...)` / `optionsForRawSessionRows(...)`.

Jalankan:

```bash
php artisan test --filter="EventCategoryOptionServiceTest|EventSessionOptionServiceTest"
```

---

## Perintah Deploy / Dev

Urutan yang disarankan setelah pull:

1. `composer install`
2. Bila masih error `RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider not found` (cache PHP lama):
   - Hapus `bootstrap/cache/packages.php` dan `bootstrap/cache/services.php`.
   - `php artisan package:discover --ansi`.
3. `php artisan migrate` — mengubah `events.category` dan `events.session` menjadi `TEXT`.
4. `php artisan optimize:clear`
5. (Opsional) `php artisan db:seed` atau `php artisan migrate:fresh --seed` untuk data contoh.
6. `php artisan test --filter="EventCategoryOptionServiceTest|EventSessionOptionServiceTest"` untuk smoke test service.
7. `npm install` lalu `npm run dev` / `npm run build`.

---

## Dampak untuk Fitur Events

1. **List admin** (`Dashboard/Events/Index`): badge category ditampilkan `v-for`, filter session sekarang juga multi-token (akan ditulis ke depannya).
2. **Create/Edit admin**: admin dapat memilih banyak category **dan** banyak session. Event lama dengan nilai custom (`"must"`, `"workshop"`) tetap dapat dibuka, dipilih, dan disimpan.
3. **Show admin & detail user/landing**: label session dirangkai dari seluruh token (mis. "General, Workshop"), tidak lagi hanya token pertama.
4. **Livewire (Filament) list & detail**: badge per token, aman untuk CSV berisi banyak nilai.
5. **Blade card-mode**: badge category dan session tampil berdampingan; custom token mendapat label fallback "Title Case".
6. **API / Inertia payload**: field `category` dan `session` kini `string[]`. Konsumen baru wajib memakai array; konsumen lama yang masih `string` akan broken. Helper `toCategoryList` / `parseCategories` / `eventTokenList` pada Vue di-desain agar toleran terhadap keduanya selama migrasi.

---

## Checklist Sebelum Merge

- [ ] `composer install` + `php artisan migrate` di environment dev
- [ ] `php artisan test --filter="EventCategoryOptionServiceTest|EventSessionOptionServiceTest"` lolos
- [ ] Cek manual form Create/Edit: bisa pilih >1 session dan >1 category, lalu submit; cek value di DB berbentuk CSV
- [ ] Cek event lama dengan value `"rkt,must"` dan `"general,workshop"`: muncul di select options dan di badge detail
- [ ] Cek filter list admin untuk session/category dengan single & multiple token
- [ ] Cek Livewire table-mode, card-mode, dan event detail infolist menampilkan badge terpisah

---

## Catatan Tambahan

- Peringatan linter statis pada `forCategoryTokens(...)` / `forSessionTokens(...)` di `EventService.php` (Argument 1 expected Builder, array given) bersifat **false positive**. Scope Eloquent yang memakai atribut `#[Scope]` memang dipanggil dengan argumen geser (builder di-inject otomatis). Pola yang sama sudah dipakai untuk `forCategoryTokens` sebelum sesi ini.
- Peringatan TypeScript pada `Index.vue` (`Record<string, unknown>` vs `RequestPayload`) juga pre-existing; tidak berubah di sesi ini.
- `bootstrap/cache/packages.php` dan `bootstrap/cache/services.php` tidak perlu di-commit; keduanya memang ter-generate otomatis.

Semoga membantu. Kalau ada bagian yang kurang jelas atau perlu breakdown lebih dalam, silakan tanyakan di channel backend/frontend.
