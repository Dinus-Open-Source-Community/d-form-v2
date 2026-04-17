# Nafan Changes — 17 April 2026

Dokumentasi ringkas dari perubahan pada sesi tanggal **17-04-2026**. Fokus utama sesi ini adalah menambahkan kolom `registered_count` pada entitas **Event**, memperbaiki validasi tanggal registrasi, menambah endpoint untuk mengecek status registrasi, dan memasang paket `laravel/mcp` + `laravel/boost`.

> Catatan untuk tim Frontend: ada perubahan *signature* helper rute dari Wayfinder (berubah dari `string | number` menjadi `{ id: string }`) yang perlu diperhatikan saat memanggil rute events.

---

## Ringkasan (TL;DR)

- Menambahkan kolom `registered_count` (unsigned smallint, default 0) pada tabel `events` via migration baru.
- Event sekarang mempunyai hitungan peserta terdaftar yang konsisten di backend, resource, factory, dan seeder.
- `EventService` diubah sehingga `registrationStatus()` dan `eventToInertiaArray()` dapat meng-resolve `registeredCount` secara otomatis dari event.
- Endpoint baru: `GET /dashboard/events/{event}/registration-status` yang mengembalikan JSON detail event + status registrasi (dapat dipakai untuk polling dari UI).
- Aturan validasi tanggal pada `StoreEventRequest` & `UpdateEventRequest` diperketat supaya rentang registrasi dan event konsisten.
- Tambah dependensi: `laravel/mcp` (runtime) dan `laravel/boost` (dev). File placeholder `routes/ai.php` dibuat untuk meletakkan registrasi MCP server di kemudian hari.
- Generator rute (Wayfinder) di sisi frontend di-regenerate: sekarang termasuk `store`, `update`, `destroy`, `restore`, dan argumen rute yang menerima object `{ id: string }`.

---

## Daftar File yang Diubah / Ditambahkan

### Ditambahkan (Untracked)
- `database/migrations/2026_04_17_000001_add_registered_count_to_events_table.php`
- `routes/ai.php`

### Dimodifikasi
- `app/Http/Controllers/Dashboard/Events/EventController.php`
- `app/Http/Requests/StoreEventRequest.php`
- `app/Http/Requests/UpdateEventRequest.php`
- `app/Http/Resources/EventResource.php`
- `app/Models/Event.php`
- `app/Services/Event/EventService.php`
- `composer.json` / `composer.lock`
- `database/factories/EventFactory.php`
- `database/seeders/EventSeeder.php`
- `resources/js/actions/App/Http/Controllers/Dashboard/Events/EventController.ts`
- `resources/js/routes/dashboard/events/index.ts`
- `routes/web/admin/event.php`

---

## Detail Perubahan Backend

### 1. Migration baru — `registered_count` pada tabel `events`

File: `database/migrations/2026_04_17_000001_add_registered_count_to_events_table.php`

Kolom ditambahkan setelah kolom `quota`, bertipe `unsignedSmallInteger`, default `0`. Migration idempotent (cek `Schema::hasColumn` sebelum menambah / drop).

```13:19:database/migrations/2026_04_17_000001_add_registered_count_to_events_table.php
        if (! Schema::hasColumn('events', 'registered_count')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedSmallInteger('registered_count')
                    ->default(0)
                    ->after('quota');
            });
        }
```

Jalankan:

```bash
php artisan migrate
```

### 2. `app/Models/Event.php`

- Menambahkan `registered_count` ke `$fillable` (baris 37).
- Cast `registered_count` sebagai `integer` (baris 53).
- Menyertakan `registered_count` di scope `forListPage()` agar ter-select saat listing admin (baris 93).

```34:39:app/Models/Event.php
        'registration_end',
        'location',
        'quota',
        'registered_count',
        'banner',
        'price',
```

### 3. `app/Http/Resources/EventResource.php`

Menambahkan field `registered_count` di payload resource (baris 25), sehingga semua konsumen resource (list, show, edit) akan menerima angka tersebut secara default.

```22:26:app/Http/Resources/EventResource.php
            'banner_url' => $this->banner ? Storage::disk('public')->url($this->banner) : null,
            'price' => $this->price,
            'quota' => $this->quota,
            'registered_count' => $this->registered_count,
            'session' => $this->session->value,
```

### 4. `app/Services/Event/EventService.php`

Perubahan utama:

- Method baru `resolveRegisteredCount()` — mengembalikan `registered_count` yang sudah di-clamp antara 0 dan `quota`:

```52:55:app/Services/Event/EventService.php
    public function resolveRegisteredCount(Event $event): int
    {
        return min(max(0, (int) $event->registered_count), (int) $event->quota);
    }
```

- `registrationStatus()` (baris 30) kini menerima `?int $registeredCount = null`. Jika tidak diberikan, otomatis di-resolve dari event. Signature lama (`int $registeredCount = 0`) diganti menjadi nullable.

- `eventToInertiaArray()` (baris 175) juga menjadi nullable pada parameter `$registeredCount`. Field `registered_count` ikut di-append ke payload.

```175:186:app/Services/Event/EventService.php
    public function eventToInertiaArray(Event $event, ?int $registeredCount = null): array
    {
        $registeredCount ??= $this->resolveRegisteredCount($event);

        return array_merge(
            (new EventResource($event))->resolve(request()),
            [
                'registration_status' => $this->registrationStatus($event, $registeredCount)->value,
                'registered_count' => $registeredCount,
            ]
        );
    }
```

- `create()` selalu mengeset `registered_count = 0` pada event baru (baris 127).
- `update()` men-clamp `registered_count` agar tidak melampaui `quota` baru ketika admin menurunkan kuota tanpa menyertakan `registered_count` di payload (baris 153-155):

```153:155:app/Services/Event/EventService.php
        if (array_key_exists('quota', $data) && ! array_key_exists('registered_count', $data)) {
            $data['registered_count'] = min($this->resolveRegisteredCount($event), (int) $data['quota']);
        }
```

### 5. `app/Http/Controllers/Dashboard/Events/EventController.php`

- Import `Illuminate\Http\JsonResponse` (baris 15).
- `index()` sekarang memetakan setiap event melalui `$this->eventService->eventToInertiaArray($event)` (sebelumnya langsung `EventResource::resolve()`), sehingga `registered_count` + `registration_status` ikut terkirim di list admin (baris 34-38).
- `show()` tidak lagi meng-hardcode `$registeredCount = 0`; memanggil `eventToInertiaArray($event)` saja (baris 81).
- Endpoint baru `registrationStatus(Event $event): JsonResponse` (baris 86-93):

```86:93:app/Http/Controllers/Dashboard/Events/EventController.php
    public function registrationStatus(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json(
            $this->eventService->eventToInertiaArray($event)
        );
    }
```

### 6. `routes/web/admin/event.php`

Route baru ditambahkan **sebelum** route `restore` (baris 7-8):

```7:8:routes/web/admin/event.php
    Route::get('/events/{event}/registration-status', [EventController::class, 'registrationStatus'])
        ->name('events.registration-status');
```

- Method: `GET`
- URL: `/dashboard/events/{event}/registration-status`
- Name: `dashboard.events.registration-status`
- Middleware: `auth` (inherit dari group)

### 7. Validasi — `Store/UpdateEventRequest`

Aturan tanggal diperketat di kedua request (baris 28-30). Sebelumnya hanya dicek `after:registration_start` pada `registration_end`. Sekarang menjamin kronologi penuh:

```28:30:app/Http/Requests/StoreEventRequest.php
            'registration_start' => ['required', 'date', 'before_or_equal:start_date'],
            'registration_end' => ['required', 'date', 'after:registration_start', 'before_or_equal:end_date'],
            'start_date' => ['required', 'date', 'after_or_equal:registration_start'],
```

Rule identik juga diterapkan di `app/Http/Requests/UpdateEventRequest.php` baris 28-30.

**Implikasi:** frontend harus memastikan form tidak mengirim tanggal di luar urutan `registration_start ≤ start_date` dan `registration_end ≤ end_date`, kalau tidak validasi backend akan gagal.

### 8. Factory & Seeder

- `database/factories/EventFactory.php` baris 29: default `registered_count => 0`.
- `database/seeders/EventSeeder.php` menambah `registered_count` pada ketiga event seed:
  - baris 28: `12` (event pertama, sebagian terisi)
  - baris 44: `100` (event kedua, penuh — akan berstatus `Full`)
  - baris 60: `0` (event ketiga, kosong)

Cukup jalankan `php artisan db:seed` (atau `migrate:fresh --seed`) untuk mencoba skenario `Open`, `Full`, dsb.

### 9. Dependensi Composer

File: `composer.json`

- `require`: `laravel/mcp ^0.6.7` (baris 22)
- `require-dev`: `laravel/boost ^2.4` (baris 35)

Jangan lupa jalankan:

```bash
composer install
```

File `routes/ai.php` dibuat sebagai placeholder untuk registrasi MCP server (`Laravel\Mcp\Facades\Mcp`). Masih berisi contoh terkomentari — aktifkan ketika kita benar-benar mengekspos MCP endpoint.

---

## Detail Perubahan Frontend

Semua perubahan frontend pada sesi ini berasal dari **regenerasi Wayfinder** (otomatis dari route backend). Tidak ada komponen React yang di-edit manual, tapi konsumen helper rute **wajib** menyesuaikan.

### 1. `resources/js/actions/App/Http/Controllers/Dashboard/Events/EventController.ts`

Tambahan helper aksi baru:

- `restore` (baris 3-58) — `POST /dashboard/events/{event}/restore`
- `store` (baris 148-180) — `POST /dashboard/events`
- `update` (baris 332-391) — `PUT|PATCH /dashboard/events/{event}`
- `destroy` (baris 393-453) — `DELETE /dashboard/events/{event}`

Helper lama (`index`, `create`, `show`, `edit`) tetap ada, tetapi signature argumen rute untuk `show`, `edit` berubah:

- Sebelumnya: `args: { event: string | number } | string | number | ...`
- Sekarang:   `args: { event: string | { id: string } } | string | { id: string } | ...`

Artinya kita dapat meneruskan langsung objek event: `show(event)` atau `edit({ id: event.id })` — tetapi kalau ada kode lama yang mengoper `number`, TypeScript akan protes. Periksa semua pemanggilan helper tersebut.

### 2. `resources/js/routes/dashboard/events/index.ts`

Sama seperti di atas — helper rute untuk halaman events di-regenerate. Tambahan export `restore`, `store`, `update`, `destroy` dan perubahan argumen menjadi `{ id: string }`. Jika kamu punya kode seperti:

```ts
events.show(eventId as number)
```

Ubah menjadi:

```ts
events.show(eventId)
events.show({ id: eventId })
events.show(event)
```

### 3. Endpoint baru untuk registrasi (belum ada helper TS)

Pada generator yang di-commit saat ini **belum** ter-include helper `registrationStatus`. Untuk sementara, panggil manual (contoh dengan axios):

```ts
await axios.get(`/dashboard/events/${event.id}/registration-status`)
```

Setelah Wayfinder di-regenerate berikutnya, kemungkinan akan muncul helper `registrationStatus` yang konsisten dengan yang lain — silakan gunakan itu ketika sudah tersedia.

---

## Dampak untuk Fitur Events

1. **List Admin**: setiap item event sekarang membawa `registered_count` dan `registration_status`. UI list dapat langsung menampilkan "X / quota" dan badge status tanpa request tambahan.
2. **Show Page**: field `registered_count` selalu tersedia dari server; tidak perlu lagi mengoper 0 dari controller.
3. **Edit/Update**: kalau admin menurunkan `quota` di form edit, backend otomatis menurunkan `registered_count` sehingga tidak pernah melebihi `quota` baru.
4. **Polling real-time**: gunakan endpoint `GET /dashboard/events/{event}/registration-status` untuk refresh status tanpa reload halaman.
5. **Validasi tanggal** lebih ketat — jadikan pesan error di form lebih informatif (misalnya "Tanggal mulai harus setelah periode registrasi dibuka").

---

## Checklist Sebelum Merge

- [ ] `composer install`
- [ ] `php artisan migrate` (atau `migrate:fresh --seed` di lokal)
- [ ] `npm install` lalu regenerate Wayfinder bila perlu (`php artisan wayfinder:generate` — cek command yang dipakai project)
- [ ] Audit pemanggilan `events.show(...)` / `events.edit(...)` di seluruh `resources/js` — pastikan argumen kompatibel dengan signature baru
- [ ] Smoke test: list, create, edit, show, delete, restore, dan endpoint `registration-status`
- [ ] Cek tampilan badge status registrasi (`Open`, `Closed`, `Full`, `NotYetOpen`) pada event hasil seeder

---

## Catatan Tambahan

- `routes/ai.php` belum di-register di `bootstrap/app.php`. Saat kita siap menggunakan MCP, daftarkan file ini melalui `withRouting(...)` atau sesuai panduan `laravel/mcp`.
- `laravel/boost` adalah dev-only; aman untuk di-skip di environment produksi.

Semoga membantu. Jika ada pertanyaan atau perlu breakdown lebih dalam, silakan tanyakan di channel backend/frontend masing-masing.
