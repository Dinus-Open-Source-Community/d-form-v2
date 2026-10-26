# Sapto Changes — 27 Oktober 2026 (DFORM-TBD)

Revert kolom pratinjau live di form builder, lalu menggantinya dengan solusi yang lebih sederhana: sidebar navigasi dashboard tidak dirender di halaman builder formulir agar kanvas dapat lebar penuh.

> **Nomor tiket:** `DFORM-TBD` — belum ada tiket Jira saat dikerjakan.

| Kolom  | Isi |
| ------ | --- |
| Issue  | DFORM-TBD (belum dibuat) |
| Commit | lihat `git log --oneline -4` (`Revert "…"` ×3) + commit sidebar |
| Branch | `dev-zapp` |

---

## Ringkasan (TL;DR)

| Area | Sebelum | Sesudah |
| ---- | ------- | ------- |
| Kolom pratinjau live (desktop) | ada, kolom kanan 390 px | **dihapus** |
| Tombol Pratinjau | disembunyikan saat kolom live tampil | kembali seperti semula (modal) |
| Sidebar navigasi di `/admin/events/{event}/forms/{form}` | tampil (256 px) | **tidak dirender** (editor + tab Jawaban) |
| Sidebar navigasi di halaman dashboard lain | tampil | tetap tampil |

## Alasan revert

Hasil visual kolom pratinjau live dinilai tidak memuaskan oleh pemilik produk. Alih-alih menyetel ulang desainnya, ruang horizontal dialihkan dengan memangkas navigasi yang tidak dipakai saat mengedit form.

Tiga commit fitur dikembalikan berurutan sehingga tree kembali **persis** ke `724e938` (`git diff 724e938 HEAD` kosong):

1. `git revert -m 1 f0793df` — membatalkan merge spec e2e.
2. `git revert a0ba1ee` — membatalkan unit test, alias loader, dan penyesuaian spec.
3. `git revert ac4ca61` — membatalkan komponen `FormPreviewBody`, `FormBuilderLivePreview`, hook, lib, dan dokumen tiket.

## Perubahan yang dipertahankan

| File | Perubahan |
| ---- | --------- |
| `resources/js/layouts/DashboardLayout.vue` | `FULL_WIDTH_PAGE_COMPONENTS` = `Dashboard/Events/Forms/Show` + `Dashboard/Events/Forms/Create`; `DashboardSidebar` hanya dirender di luar daftar itu |
| `resources/js/components/modules/dashboard/DashboardTopbar.vue` | prop `showNavigationTrigger` (default `true`); tombol buka sidebar disembunyikan saat sidebar tidak dirender |

Tab **Jawaban** otomatis ikut karena berada di komponen halaman yang sama. Navigasi keluar tetap tersedia lewat tombol kembali di topbar.

## Review keputusan (gate langkah 2)

- **Best practice?** Ya. Layout yang tahu halaman mana yang butuh lebar penuh adalah pola yang sudah dipakai `DashboardFocusLayout` (`isFormBuilderPage`), jadi tidak menambah pola baru. Alternatif `layout: [Layout, { props }]` **tidak dipakai** karena adapter Inertia Vue hanya menerima array komponen layout, bukan pasangan komponen+props.
- **Optimal?** Ya. `v-if` menghapus node sidebar dari DOM (bukan `display:none`), jadi tidak ada biaya render menu 20+ item, dan `SidebarInset` tidak punya margin bergantung sidebar sehingga tidak ada ruang sisa.
- **Opsi lain:** menambah prop layout dari controller (butuh perubahan back-end untuk urusan tampilan) dan memakai `layout` berbentuk fungsi `(h, page) => …` (benar secara framework, tetapi menyimpang dari pola repo dan lebih sulit dibaca). Keduanya ditolak.

## Verifikasi

- `npx eslint` pada dua file yang diubah: bersih.
- Playwright (skrip sementara, dihapus setelah dipakai): `[data-slot="sidebar"]` = **0** di halaman form (editor dan tab Jawaban), = **1** di `/profile` (regresi), `scrollWidth` 1590 < `clientWidth` 1600 → tanpa overflow horizontal.
- Suite e2e penuh: **25 lulus, 1 gagal** — `otp-verification › paste lima digit…` gagal saat satu suite (state OTP dari test sebelumnya) dan lulus saat dijalankan sendiri; tidak menyentuh layout dashboard.
- Gate e2e-lahu: tidak ditambah spec permanen karena perubahan ini murni tata letak (node dihapus, tanpa logika baru) dan fixture form stress tidak dimiliki stabil per user uji.

## Checklist

- [x] Sidebar hilang di editor dan tab Jawaban
- [x] Sidebar tetap ada di halaman dashboard lain
- [x] Tidak ada overflow horizontal di 1600 px
- [x] Fitur pratinjau live benar-benar hilang dari tree (diff kosong terhadap `724e938`)
