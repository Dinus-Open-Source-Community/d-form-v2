# Sapto Changes — 27 Oktober 2026 (DFORM-202)

Rate limit untuk dua endpoint publik/sensitif di `routes/web/admin/event.php`: submit form dan check-email. Menutup pelanggaran P1 nomor 4 (endpoint tanpa throttle), memakai layer limiter global yang sudah ada di `AppServiceProvider` — tanpa mekanisme baru dan tanpa limiter duplikat.

> **Nomor tiket:** `DFORM-202` (papan Sokudo: `TASK-202`).

| Kolom  | Isi |
| ------ | --- |
| Issue  | DFORM-202 |
| Commit | `fix(security): rate limit endpoint submit form dan check-email` |
| Branch | `task-202-tambahkan-rate-limit-pada-endpoint-submit-form-d` |

---

## Ringkasan (TL;DR)

| Route | Sebelum | Sesudah |
| ----- | ------- | ------- |
| `POST /joined/{event}/forms/{form}/submit` | tanpa throttle | `throttle:form-submit` — 10/menit per user per form |
| `GET /joined/users/check-email` | tanpa throttle | `throttle:auth-email-check` — limiter lama dipakai ulang (20/menit per IP) |
| `GET /joined/{event}/forms/{form}/fill` | tanpa throttle | **tetap tanpa throttle** (alasan di bawah) |

## Angka batas + alasan

| Limiter | Batas | Sumber angka | Alasan |
| ------- | ----- | ------------ | ------ |
| `form-submit` | 10 / menit / (user + event + form) | `config/events.php` → `FORMS_SUBMIT_RATE_LIMIT_PER_MINUTE` (default 10) | Submit normal 1×; sisa 9 kuota menampung retry validasi nyata (field wajib terlewat, unggahan gagal) tanpa membuka jalan spam `lockForUpdate` + baris jawaban sampah. |
| `auth-email-check` | 20 / menit / IP | `config/auth_throttle.php` (sudah ada) | Endpoint enumerasi email; **memakai ulang** limiter yang sudah dipakai `/auth/email-availability` (aturan 6: dedup, jangan definisikan limiter kedua). |

Tidak ada magic number di route maupun di provider: batas submit dibaca dari config (aturan 11), batas check-email dari config auth_throttle yang sudah ada.

## Kunci limiter

`form-submit` memakai kunci `{user_id}|{event}|{form}` (`sprintf` di `AppServiceProvider`, parameter route dinormalkan lewat `routeParameterKey`):

- Kuota satu peserta **tidak** memakan kuota peserta lain pada form yang sama (dibuktikan test `test_submit_quota_is_not_shared_between_participants`).
- Bucket juga terpisah per form (dibuktikan `test_submit_quota_is_scoped_per_form_for_the_same_user`).
- `throttle` berjalan sebelum `SubstituteBindings`, jadi parameter route masih skalar. Nilainya **dikanonikalisasi** (`strtolower(trim(...))`, atau `getKey()` bila sudah model) karena MySQL membandingkan UUID case-insensitive + `PAD SPACE`: tanpa ini, `/joined/<UUID-HURUF-BESAR>/...` atau UUID berspasi ekor tetap menulis ke form yang sama tetapi membuka bucket baru — throttle jadi bisa dilewati. Regresi dijaga `test_submit_limiter_key_ignores_uuid_case_and_surrounding_space`.
- `auth-email-check` (limiter lama) tetap satu definisi, tetapi kuncinya `$request->user()?->id ?? $request->ip()`: endpoint guest `/auth/email-availability` tetap per IP, sedangkan `check-email` dashboard memakai user id sehingga kuota satu IP bersama (NAT kantor/kampus) tidak memblokir peserta lain.

## Review keputusan (gate langkah 2)

- **Best practice?** Ya. Memakai `RateLimiter::for(...)` + `throttle:<nama>` — pola yang sama dengan 17 limiter yang sudah ada, bukan middleware/handler baru.
- **Optimal?** Ya. Limiter hanya membaca config dan parameter route (tanpa query DB) sehingga tidak menambah beban di jalur panas submit.
- **Opsi lain yang ditolak:**
  - Limiter aggregate per form (mis. 300/menit/form) untuk menahan serangan banyak akun: **ditolak** karena melanggar tujuan kunci per user (satu peserta bisa menghabiskan kuota peserta lain saat pendaftaran ramai). Pembuatan akun sendiri sudah dibatasi `auth-register` 3/menit/IP, sehingga vektor banyak-akun tidak bebas.
  - Limiter baru khusus check-email: **ditolak** karena duplikasi `auth-email-check`.
- **`GET /fill` tidak di-throttle:** halaman baca idempoten di belakang `auth`, tanpa lock/kuota/tulis; men-throttle page load menambah 429 pada refresh/navigasi normal tanpa menutup vektor tulis apa pun.
- **Ikutan kecil:** `authThrottleLimit()` sekarang menerima `string|int|null` dan menormalkan kunci ke string, karena `Request::ip()` bertipe `?string` (7 temuan type-checker di file yang sama) — perilaku untuk nilai non-null tidak berubah.

## Kontrak respons 429

Tidak ada perubahan kontrak. `throttle` mengeluarkan 429 + `Retry-After`; `bootstrap/app.php` sudah menyalin header rate limit ke respons Inertia dan `useGlobalHttpStatusToast` (`resources/js/hooks/useGlobalHttpStatusToast.ts`) sudah menangani 429. Test baru memverifikasi `Retry-After` hadir.

**Catatan (tidak diubah di tiket ini):** body JSON 429 untuk request Inertia di `bootstrap/app.php` masih berbunyi "Terlalu banyak pencarian…" karena ditulis khusus untuk pencarian acara. Endpoint baru memakai jalur yang sama, jadi copy itu tidak akurat untuk submit form. Pesan generik `resources/js/lib/error-message.ts` menutupi sebagian kasus, tetapi penyatuan copy butuh perubahan global + penyesuaian spec `event-search` — di luar kontrak tiket ini, dicatat sebagai follow-up.

## Bukti merah → hijau

Merah (implementasi di-stash, hanya test baru yang aktif):

```
FAIL  Tests\Feature\Forms\FormSubmissionThrottleTest
⨯ submit beyond limit returns 429 without saving answer      Expected 429 but received 302
✓ submit below limit still saves answer
⨯ submit quota is not shared between participants            Expected 429 but received 302
⨯ submit quota is scoped per form for the same user          Expected 429 but received 302
⨯ submit limiter key ignores uuid case and surrounding space  Rate limiter [form-submit] is not defined
⨯ check email beyond limit returns 429                       Expected 429 but received 200
✓ check email below limit answers json
Tests: 5 failed, 2 passed (30 assertions)
```

Hijau (implementasi aktif):

```
PASS  Tests\Feature\Forms\FormSubmissionThrottleTest
✓ submit beyond limit returns 429 without saving answer
✓ submit below limit still saves answer
✓ submit quota is not shared between participants
✓ submit quota is scoped per form for the same user
✓ submit limiter key ignores uuid case and surrounding space
✓ check email beyond limit returns 429
✓ check email below limit answers json
Tests: 7 passed (77 assertions)
```

Test `submit beyond limit` menuntaskan kuota dengan submit yang ditolak validasi (tidak menulis baris), lalu submit valid berikutnya harus 429 dan `form_answers` untuk form itu tetap 0 — jadi "tidak ada jawaban tersimpan setelah kena limit" benar-benar diuji.

## Verifikasi

| Cek | Hasil |
| --- | ----- |
| `route:list --path=joined` | `POST …/submit` → `ThrottleRequests:form-submit`; `GET …/check-email` → `ThrottleRequests:auth-email-check` |
| `php artisan test tests/Feature/Forms/FormSubmissionThrottleTest.php` | 7 lulus (77 assertion) |
| `php artisan test tests/Feature/Forms tests/Feature/Auth tests/Feature/Stress` | 249 lulus (1695 assertion), 0 gagal |
| `./vendor/bin/pint --test` (4 file tersentuh) | PASS |
| Playwright penuh (`E2E_BASE_URL=http://127.0.0.1:3001 npx playwright test`) | **26 lulus, 0 gagal** (2,2 menit) — dijalankan terhadap server worktree, bukan `:3000` |

Catatan lingkungan: `:3000` menyajikan checkout root, jadi e2e diarahkan ke container kedua dari image `d-form-v2_app:latest` pada port 3001 dengan workdir worktree. Worktree butuh `vendor` nyata (bukan symlink ke `/app/vendor`, karena autoloader Composer akan memetakan `App\` ke checkout root) dan salinan `database/seeders/Stress` (dibutuhkan classmap autoload). Container uji dihapus setelah run.

## Checklist

- [x] `form-submit` didefinisikan di `AppServiceProvider` (satu layer global) dan angka batas di config
- [x] `check-email` memakai `auth-email-check` yang sudah ada (tanpa limiter duplikat)
- [x] Kedua route memakai `throttle:*` (dibuktikan `route:list`)
- [x] Test: 429 submit + tidak ada jawaban tersimpan setelah limit, 429 check-email, jalur normal tetap sukses
- [x] Bukti merah-sebelum-hijau ditempel di atas
- [x] `pint --test` bersih
- [x] Suite Playwright penuh hijau (26/26)
- [x] Keputusan `GET /fill` ditulis (tidak di-throttle + alasan)
