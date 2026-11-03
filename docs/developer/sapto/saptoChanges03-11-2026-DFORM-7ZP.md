# Sapto Changes — 3 November 2026 (DFORM-7ZP)

## Ringkasan

Modul broadcast legacy dihapus menyeluruh (keputusan user), bukan hanya file route. Modul baru `EmailBroadcast` tetap utuh. Tidak ada perubahan perilaku yang terlihat pengguna: dengan `FEATURE_BROADCAST=false` kedua modul memang tidak mendaftarkan route, dan dengan flag `true` route legacy yang tersisa sebelumnya sudah pasti 500 karena tabel `broadcasts` tidak punya migrasi aktif.

| Issue       | Commit                                                |
| ----------- | ----------------------------------------------------- |
| `dform-7zp` | `refactor(broadcast): remove legacy broadcast module` |

## Inventaris pemakaian sebelum penghapusan

| Path                                                                                                     | Peran                                                                | Pemakai                                                                                          | Aksi                               |
| -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ---------------------------------- |
| `routes/web/admin/broadcast.php`                                                                         | 13 route legacy                                                      | tidak ada name yang dipakai UI                                                                   | hapus                              |
| `app/Http/Controllers/Dashboard/Broadcasts/BroadcastController.php`                                      | controller legacy 13 aksi                                            | route di atas                                                                                    | hapus                              |
| `app/Models/Broadcast.php`                                                                               | model tabel `broadcasts` (migrasi hanya `.bak`)                      | controller legacy, `RecruitmentPeriodController`, policy, service, job, command, factory, 2 test | hapus setelah konsumen dibersihkan |
| `app/Models/BroadcastAttachment.php`                                                                     | relasi attachment legacy                                             | controller legacy                                                                                | hapus                              |
| `app/Policies/BroadcastPolicy.php`                                                                       | policy auto-discovered                                               | controller legacy                                                                                | hapus                              |
| `app/Http/Requests/Broadcast/` (6 file)                                                                  | validasi legacy                                                      | controller legacy                                                                                | hapus                              |
| `app/Services/Broadcast/` (4 file)                                                                       | service legacy                                                       | controller legacy, `SendBroadcastJob`, `SendScheduledBroadcastsCommand`, test                    | hapus                              |
| `app/Jobs/SendBroadcastJob.php`                                                                          | job legacy                                                           | `Services/Broadcast`, test                                                                       | hapus                              |
| `app/Console/Commands/SendScheduledBroadcastsCommand.php` (`broadcast:send-scheduled`)                   | command legacy                                                       | tidak dipakai scheduler                                                                          | hapus                              |
| `database/factories/BroadcastFactory.php`                                                                | factory legacy                                                       | 2 test                                                                                           | hapus                              |
| `resources/js/components/modules/dashboard/broadcast/` (9 file)                                          | UI legacy                                                            | hanya saling impor; tidak diimpor page/layout                                                    | hapus                              |
| `resources/js/lib/broadcastHub.ts`                                                                       | tipe/konstanta UI legacy                                             | folder UI legacy + 4 composable                                                                  | hapus                              |
| `resources/js/utils/composables/useBroadcast{Composer,ContextPrefill,SnapshotPicker,TrackingSummary}.ts` | composable legacy                                                    | 0 pemakai eksternal                                                                              | hapus                              |
| `tests/Feature/Broadcast/BroadcastHubTest.php`                                                           | 28 test legacy (seluruhnya skip)                                     | -                                                                                                | hapus                              |
| `RecruitmentPeriodController`                                                                            | method `broadcastsForPeriodTab`, `$broadcasts`, blok tab `broadcast` | tab broadcast legacy                                                                             | hapus bagian broadcast             |
| `RecruitmentPeriodTabPaginationTest`                                                                     | 1 test skip tab broadcast                                            | -                                                                                                | hapus test + import                |

Tidak dihapus karena dipakai modul baru: `app/Mail/BroadcastMail.php` + `resources/views/mail/broadcast-{html,text}.blade.php` (dipakai `Broadcasting/BroadcastTestController`, `Jobs/Broadcasting/SendBroadcastRecipientJob`, `EmailBroadcastFlowTest`), `pages/Dashboard/Broadcasts/{Index,Create,Show,Datasets}.vue`, blok `broadcasts` di `lib/routes.ts`, `components/modules/dashboard/broadcasts/BroadcastContentEditor.vue`, dan seluruh migrasi `*.bak`.

## Bukti route

```text
# sebelum
FEATURE_BROADCAST=false : 162 route, 0 route /admin/broadcasts
FEATURE_BROADCAST=true  : 189 route, 27 route /admin/broadcasts (4 route menuju controller legacy)
legacy: PUT|PATCH /{broadcast} update, POST /{broadcast}/send,
        POST /{broadcast}/retry, GET /{broadcast}/tracking

# sesudah
FEATURE_BROADCAST=false : 162 route, 0 route /admin/broadcasts
FEATURE_BROADCAST=true  : 185 route, 23 route /admin/broadcasts, 0 action legacy, 0 name duplikat
```

Name `dashboard.broadcasts.retry` yang sebelumnya terdaftar dua kali (legacy `/{broadcast}/retry` dan baru `/{broadcast}/retry-failed`) kini hanya menunjuk `/{broadcast}/retry-failed`, sesuai pemakaian `resources/js/lib/routes.ts`.

## Verifikasi

```text
$ grep -rn "Dashboard\\\\Broadcasts|App\\\\Models\\\\Broadcast|Services\\\\Broadcast\\\\|
          SendBroadcastJob|Requests\\\\Broadcast\\\\|broadcast:send-scheduled|BroadcastFactory|
          BroadcastAttachment|BroadcastPolicy" app tests routes database bootstrap config
→ hanya false positive EmailBroadcast* / Broadcasting
$ grep -rn "modules/dashboard/broadcast/|broadcastHub|useBroadcast*" resources/js → kosong
$ php artisan test tests/Feature/Recruitment/RecruitmentPeriodTabPaginationTest.php → 9 passed
$ php artisan test tests/Feature/Broadcasting → 15 passed, 2 skipped
$ composer test → 840 passed, 7 skipped (5.374 assertions)
$ npm run lint → PASS (0 error)
$ npm run typecheck → PASS
$ pint --test (2 file berubah) → PASS
```

Jumlah test skip turun dari 36 menjadi 7 karena 29 test legacy ikut terhapus (28 `BroadcastHubTest` + 1 test pagination broadcast).

## Keputusan

- Penghapusan menyeluruh dipilih user; alternatif hanya menghapus file route ditolak karena menyisakan model/policy/service legacy tanpa route.
- `BroadcastMail` dan view emailnya **tidak** dihapus: modul baru memakainya.
- Migrasi `*.bak` tidak disentuh (bukan migrasi aktif), sesuai instruksi.
- Tab broadcast pada halaman periode ikut dihapus karena satu-satunya konsumennya adalah UI legacy yang sudah tidak dirender; `?tab=broadcast` kini jatuh ke `peserta` seperti tab tak dikenal lainnya.
