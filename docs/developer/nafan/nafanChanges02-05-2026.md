# Log perubahan & catatan teknis — 02 Mei 2026

Dokumen ini merangkum seluruh penambahan dan perubahan yang dilakukan pada sesi ini (M4 — Pendaftaran Member, back-end saja) agar **developer lain**—terutama **front-end**—dapat memahami konteks, kontrak API baru, dan hal-hal yang perlu diwiring di sisi Vue/Inertia.

Referensi lengkap implementasi tersedia di [`docs/05-m4-registration.md`](../../05-m4-registration.md).

---

## Ringkasan eksekutif

| Area | Apa yang dilakukan |
|------|--------------------|
| **Guard akses form** | Ditambah `FormAccessGuard` + `FormAccessStatus` — satu titik pemeriksaan untuk visibilitas, kuota, jendela pendaftaran, form tutup, dan duplikat submission. |
| **Kontrol submit** | `FormSubmissionController` sekarang menggunakan guard, menginkremen `registered_count` di dalam transaksi DB dengan `lockForUpdate` (anti-race condition), dan menangani multi-select sebagai array. |
| **Kontrol fill** | `FormFillController` menggunakan guard yang sama; mengirim `accessStatus` + `accessMessage` ke halaman sebagai pengganti boolean `alreadySubmitted`. |
| **RulesBuilder** | Ditambah dukungan `selectInput` dengan `is_multiple` — validasi `array` otomatis. |
| **Model** | `FormAnswer` kini punya relasi `belongsTo(Form)` dan `belongsTo(User)`. |
| **Migrasi** | Constraint `UNIQUE(user_id, form_id)` ditambahkan ke tabel `form_answers` di level DB. |
| **Admin submissions** | Endpoint baru `GET /dashboard/events/{event}/forms/{form}/submissions` untuk menampilkan daftar submission per form (dipage). |
| **Exception** | `QuotaExceededException` ditambahkan dan didaftarkan di `bootstrap/app.php` — respons otomatis berupa toast Inertia atau JSON 409. |
| **Dokumentasi** | `docs/05-m4-registration.md` — panduan lengkap kontrak API, props Inertia, format JSON `answers`, dan checklist implementasi FE. |

---

## 1. File baru

### 1.1 `app/Enums/FormAccessStatus.php`

Backed enum dengan 6 kasus:

| Nilai | Kondisi |
|-------|---------|
| `allowed` | Semua cek lolos |
| `not_visible` | `form.visible_for` tidak mencakup `public`/`participant` dan user bukan admin |
| `form_closed` | `form.closed_at` sudah lewat |
| `registration_not_open` | Di luar `event.registration_start`/`registration_end` |
| `quota_full` | `event.registered_count >= event.quota` |
| `already_submitted` | Sudah ada baris di `form_answers` untuk `(user_id, form_id)` ini |

Method yang tersedia:
- `message(): string` — pesan human-readable untuk toast / JSON error
- `isBlocked(): bool` — `true` untuk semua kasus kecuali `allowed`

### 1.2 `app/Services/Form/FormAccessGuard.php`

Service stateless dengan satu method publik:

```php
FormAccessGuard::check(Form $form, Event $event, User $user): FormAccessStatus
```

Dipakai di **`FormFillController`** dan **`FormSubmissionController`** — tidak perlu duplikasi cek di tempat lain.

Urutan prioritas cek: visibilitas → form tutup → jendela pendaftaran → kuota → duplikat.

> **Catatan admin:** User yang punya permission `events.list` (role admin) **dibebaskan** dari cek visibilitas, jendela pendaftaran, dan kuota — tapi tetap dicek duplikat submission.

### 1.3 `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionsController.php`

Controller invokable untuk admin melihat daftar submission sebuah form.

- Route: `GET /dashboard/events/{event}/forms/{form}/submissions` → `dashboard.events.forms.submissions`
- Otorisasi: `EventPolicy::view` (`events.view` permission)
- Output: paginator 25 per halaman, dirender ke halaman Inertia `Dashboard/Events/Forms/Submissions`

### 1.4 `app/Exceptions/QuotaExceededException.php`

Dilempar di dalam transaksi DB jika kuota habis saat `lockForUpdate`. Handler di `bootstrap/app.php` menangkapnya dan:
- Jika request Inertia (`X-Inertia` header): flash toast error + `redirect()->back()`
- Jika bukan Inertia: respons JSON `{ message: "..." }` dengan status **409**

### 1.5 `database/migrations/2026_05_02_100001_add_unique_to_form_answers.php`

Menambah constraint:
```sql
UNIQUE KEY form_answers_user_form_unique (user_id, form_id)
```

**Penting:** Jalankan `php artisan migrate` sebelum testing submission flow.

### 1.6 `docs/05-m4-registration.md`

Dokumentasi lengkap M4 — wajib dibaca FE sebelum mengimplementasikan halaman fill dan submissions. Isi:
- Semua routes + method HTTP
- Props Inertia lengkap per halaman
- Format JSON `answers` per tipe field
- Path file upload
- Tabel perilaku per `accessStatus`
- Checklist TypeScript dan Vue yang perlu dikerjakan FE

---

## 2. File yang dimodifikasi

### 2.1 `app/Models/FormAnswer.php`

Ditambahkan dua relasi Eloquent:

```php
public function form(): BelongsTo  // → Form
public function user(): BelongsTo  // → User
```

Diperlukan oleh `FormSubmissionsController` saat eager load `with('user:id,name,email')`.

### 2.2 `app/Http/Controllers/Dashboard/Events/Forms/FormFillController.php`

**Perubahan penting untuk FE:** Props Inertia berubah.

| Sebelumnya | Sekarang |
|-----------|----------|
| `alreadySubmitted: bool` | Dihapus |
| *(tidak ada)* | `accessStatus: string` (nilai enum `FormAccessStatus`) |
| *(tidak ada)* | `accessMessage: string` (pesan human-readable, kosong jika `allowed`) |

Field `fields`, `form`, `event`, dan `submitUrl` tidak berubah.

### 2.3 `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php`

- Guard akses via `FormAccessGuard::check()` menggantikan cek `alreadySubmitted` inline
- Inkremen `registered_count` sekarang dilakukan di dalam `DB::transaction()` dengan `lockForUpdate` untuk mencegah race condition pada saat kuota hampir penuh
- `buildAnswers()` sekarang menangani `selectInput` dengan `metadata.is_multiple = true` sebagai array (serupa dengan `checkbox`)

### 2.4 `app/Services/Form/RulesBuilder.php`

Di `extractRulesFromFields`: jika `input_type === 'selectInput'` dan `metadata.is_multiple === true`, ditambahkan flag `is_multiple` ke ruleset.

Di `build`: flag `is_multiple` menghasilkan rule Laravel `nullable|array` (atau `required|array`).

### 2.5 `routes/web/admin/event.php`

Ditambah satu route baru:

```php
Route::get('/events/{event}/forms/{form}/submissions', FormSubmissionsController::class)
    ->name('events.forms.submissions');
```

### 2.6 `bootstrap/app.php`

Ditambah handler untuk `QuotaExceededException` di blok `withExceptions`.

---

## 3. Highlight untuk front-end developer

### 3.1 Props `Fill.vue` berubah — wajib diupdate

`alreadySubmitted` **sudah tidak dikirim** dari backend. Ganti semua pengecekan di `Fill.vue` dengan:

```ts
// Sebelumnya:
if (props.alreadySubmitted) { ... }

// Sekarang:
if (props.accessStatus !== 'allowed') { ... }
```

Gunakan `props.accessMessage` untuk menampilkan pesan yang sudah disiapkan backend (bukan hardcode string di FE). Lihat tabel lengkap di `docs/05-m4-registration.md` §3.

### 3.2 Halaman `Submissions.vue` perlu dibuat

Backend sudah siap di route `dashboard.events.forms.submissions`. FE perlu membuat:

```
resources/js/pages/Dashboard/Events/Forms/Submissions.vue
```

Props yang diterima:
```ts
{
  event: { id: string; title: string }
  form:  { id: string; title: string }
  submissions: LengthAwarePaginator<{
    id: string
    user: { id: string; name: string; email: string } | null
    answers: Record<string, unknown>
    submitted_at: string
  }>
}
```

Contoh pagination: gunakan `submissions.links` untuk render tombol halaman (sudah format standar Laravel paginator).

### 3.3 Tambah interface TypeScript baru

Di `resources/js/types/event.d.ts`, tambahkan:

```ts
interface IFormSubmission {
  id: string
  user: { id: string; name: string; email: string } | null
  answers: Record<string, unknown>
  submitted_at: string
}
```

Perbaiki juga tipe `IForm.closed_at` dari `string` menjadi `string | null` karena nilainya memang bisa `null`.

### 3.4 `selectInput` dengan `is_multiple` → kirim sebagai array

Jika sebuah field bertipe `select` dan `metadata.is_multiple === true`, kirim nilainya sebagai array, bukan string:

```ts
// Field biasa (single select)
formData.faculty = 'teknik'

// Multi-select
formData.interests = ['desain', 'coding']
```

Backend sudah menyimpan dan memvalidasinya sebagai `array`. Lihat `Fill.vue` yang sudah ada untuk contoh pola `checkbox` sebagai referensi.

### 3.5 Path file upload

Nilai `answers[fieldName]` untuk tipe `fileUpload` adalah **path relatif** di disk `public`:

```
form-uploads/{formId}/nama-file.pdf
```

Untuk menampilkan di FE:
```ts
const fileUrl = `/storage/${answers[fieldName]}`
// atau: route('storage', { path: answers[fieldName] })
```

Pastikan `php artisan storage:link` sudah dijalankan di environment masing-masing.

### 3.6 Link ke halaman submissions dari admin form show

Di `Show.vue` (admin form builder), tambahkan link ke halaman submissions:

```ts
route('dashboard.events.forms.submissions', { event: event.id, form: form.id })
```

---

## 4. Checklist sebelum PR

- [ ] `php artisan migrate` — jalankan migrasi constraint unique
- [ ] Update `Fill.vue`: ganti `alreadySubmitted` → `accessStatus`
- [ ] Buat `Submissions.vue` (lihat §3.2)
- [ ] Tambah `IFormSubmission` di `event.d.ts` (lihat §3.3)
- [ ] Perbaiki `IForm.closed_at` menjadi `string | null`
- [ ] `php vendor/bin/pint --test` lulus
- [ ] `php artisan test` lulus

---

*Rujukan: [`docs/05-m4-registration.md`](../../05-m4-registration.md) · [`docs/milestone.md`](../../milestone.md) · [`docs/prd.md`](../../prd.md)*
