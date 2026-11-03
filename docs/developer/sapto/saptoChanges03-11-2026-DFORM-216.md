# Sapto Changes — 3 November 2026 (DFORM-216)

## Ringkasan

Menyelesaikan tab Jawaban dengan dua tampilan v2—Tabel sebagai default desktop dan
Kartu—beserta toolbar pencarian/filter, pagination client-side, review inline, dan
perilaku mobile yang tetap dapat memilih Daftar atau Kartu.

| Issue | Commit |
| --- | --- |
| dform-i7e / DFORM-216 — model view v2 dan toggle Tabel/Kartu | `157eab3` |
| DFORM-216 — tabel jawaban desktop | `157eab3` |
| DFORM-216 — grid kartu desktop dengan satu implementasi kartu | `fbad15a` |
| DFORM-216 — toolbar pencarian/filter dan pagination | `50ede2d` |
| DFORM-216 — review E2E dan stabilisasi auth fixture | `d3c4202` |

## Perubahan

- Mengganti model view v1 menjadi `TFormAnswersView = 'table' | 'card'`; tidak ada
  lagi Split atau `canSplit`.
- Menjadikan Tabel default pada desktop dan mempertahankan pilihan view di
  `localStorage` dengan fallback aman ke Tabel/Daftar bila nilai invalid.
- Menambah tabel semantik dengan label field, kolom lampiran, preview nilai aman,
  badge status, aksi Terima/Tolak/Detail, dan dukungan nilai kosong/guest.
- Menambah `surface?: 'mobile' | 'desktop'` pada `FormAnswerMobileCard.vue`.
  `FormAnswersCardGrid.vue` memakai `surface="desktop"`, sehingga markup kartu
  tidak diduplikasi; surface desktop memakai kotak nilai dan footer link/aksi ikon.
- Menambah toolbar pencarian nama/email, filter Semua/Menunggu/Diterima/Ditolak,
  jumlah hasil, toggle view, serta pagination dengan pilihan ukuran 25/50/100.
- Memperbarui review agar tabel/kartu tetap berada di state tampilan saat partial
  reload submissions; kegagalan reload memiliki pesan dan tombol Coba lagi.
- Menambah fixture E2E untuk label field, lampiran, status campuran, nilai array/
  boolean/null, guest, empty total, empty hasil filter, persistence, dan pagination.

## Keputusan dan alternatif

Pendekatan ini mengikuti v2 dan keputusan Gate 0: dua tampilan utama saja, dengan
satu komponen kartu untuk surface mobile dan desktop. Split ditolak karena sudah
dibatalkan pada requirements/design v2. Pagination tetap client-side karena payload
Inertia saat ini masih mengirim seluruh `submissions`; pagination server-side perlu
perubahan controller/kontrak payload dan menjadi follow-up terpisah untuk dataset besar.

Tidak ada perubahan controller, route, migration, atau `lang/`. Nama
`FormAnswerMobileCard.vue` dipertahankan sementara untuk kompatibilitas; rename
lintas pemakai dicatat sebagai follow-up terpisah.

## Gate dan verifikasi

- Gate 0: mockup v2 Tabel + Kartu + toolbar dibuat dan disetujui user sebelum Task 2.
- `npm run typecheck`: lulus.
- `npm run lint`: lulus.
- Prettier check pada file fitur dan E2E: lulus.
- `E2E_BASE_URL=http://localhost:3000 E2E_ADMIN_EMAIL=stress-user-1@stress.test E2E_ADMIN_PASSWORD=password npm run test:e2e -- tests/e2e/form-answers-views.desktop.spec.ts tests/e2e/form-answers-mobile.spec.ts`: `16 passed`.
- Pemeriksaan runtime dilakukan terhadap container aplikasi yang berjalan; tidak ada
  klaim build bundle baru karena dependency dan route tidak berubah pada tiket ini.

## Checklist

- [x] Tabel default desktop.
- [x] Kartu desktop memakai `surface="desktop"` tanpa duplikasi markup.
- [x] Mobile mempertahankan testid `form-answer-mobile-card` dan pilihan Daftar/Kartu.
- [x] Toolbar search/filter/result count/view toggle.
- [x] Pagination ukuran 25/50/100.
- [x] Empty total berbeda dari empty hasil filter.
- [x] Review status tanpa full page reload.
- [x] Tidak ada perubahan di luar scope backend/translation/dependency.
