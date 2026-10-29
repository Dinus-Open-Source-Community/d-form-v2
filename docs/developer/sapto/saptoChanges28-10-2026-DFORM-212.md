# Sapto Changes — 28 Oktober 2026 (DFORM-212)

## Ringkasan

Tab Jawaban sekarang memakai tampilan Kartu atau Daftar di bawah 768 px. Pilihan disimpan di localStorage dan pertama kali membuka Kartu; tabel desktop dan jalur review yang ada tetap digunakan mulai 768 px.

| Issue | Commit |
| ----- | ------ |
| TASK-212 | `feat: tambah tampilan jawaban mobile kartu dan daftar` |

Branch: `task-212-redesign-the-mobile-answer-view-as-card-and-list`.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/hooks/useFormAnswerMobileView.ts` | Pilihan tampilan tersimpan, nilai tidak dikenal kembali ke Kartu, storage error memakai state in-memory. |
| `resources/js/types/formAnswerMobileView.ts` | Tipe mode tampilan dibagikan dari folder `types`, bukan diekspor oleh hook. |
| `resources/js/components/modules/dashboard/FormAnswersViewToggle.vue` | Kontrol Kartu/Daftar dengan label, status `aria-pressed`, fokus terlihat, dan area tekan 40 px. |
| `resources/js/components/modules/dashboard/FormAnswerMobileCard.vue` | Ringkasan pengirim, status, waktu, jawaban non-berkas, lampiran, dan aksi review/detail; memiliki test id untuk verifikasi breakpoint. |
| `resources/js/components/modules/dashboard/FormAnswerMobileList.vue` | Baris ringkas yang membuka sheet detail; memiliki test id untuk verifikasi breakpoint. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | Tabel desktop tetap; komponen mobile ditampilkan di bawah breakpoint `md`; empty state tetap sama. |
| `docs/developer/sapto/design/DFORM-answers-mobile-views.md` | Spesifikasi perilaku pada bingkai 375 px dan 768 px. |
| `tests/e2e/form-answers-mobile.spec.ts` | Verifikasi tampilan 375 px dan 1280 px, persistensi, nilai rusak, empty state, console, serta screenshot. |

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| LSP pada komponen Vue dan halaman | Tidak ada error. Hook perlu pemeriksaan ulang setelah language server selesai mulai. |
| `npm run build` langsung di host | Gagal karena PHP tidak tersedia di host (`php: command not found` saat Wayfinder berjalan). |
| `npm run build` di container `d_form_app`, dari worktree | **Sukses**, 3.816 modul, selesai 14,54 detik. |
| Ukuran entry dari output Vite | Sebelum: `app-BQqUlGse.js` 75,94 kB (gzip 26,45 kB), dicatat TASK-208. Sesudah revisi: 76,00 kB (gzip 26,50 kB). Perubahan tercatat +0,06 kB mentah / +0,05 kB gzip; angka sesudah memakai pembulatan output Vite, bukan pengukuran byte mentah. |
| ESLint untuk lima file sumber yang diubah | **Sukses**, tanpa output error atau warning. |
| `npx tsc --noEmit` | Gagal karena error TS2307 impor Vue SFC yang sudah umum di repo dan beberapa error di file lain; tidak ada error baru dari file tiket yang terlihat pada log build. |
| E2E hijau | `npx playwright test tests/e2e/form-answers-mobile.spec.ts` — **2 passed (11.0s)**. Cakupan: kartu default, daftar, localStorage sesudah reload, nilai `grid` kembali ke Kartu, empty state, tabel desktop, markup mobile tidak terpasang di desktop, dan tidak ada console error. Analytics eksternal dan placeholder banner di-stub untuk meniadakan kegagalan jaringan lingkungan (`seeyu.doscom.org` DNS dan banner 403). |
| E2E seluruh suite | `npx playwright test` — **28 passed (2.6m)**, termasuk kelima spec lama. |
| Screenshot | Terlampir pada TASK-212: kartu 375 px, daftar 375 px, empty state 375 px, dan tabel desktop 1280 px. |
| Build | `podman exec -i -w /app/.sokudo/worktrees/task-212-redesign-the-mobile-answer-view-as-card-and-list d_form_app npm run build` — **sukses**, 3.816 modul, 13,99 detik. Entry sesudah revisi: 76,00 kB (gzip 26,50 kB); baseline sebelumnya 75,94 kB (gzip 26,45 kB), TASK-208. |
| ESLint | ESLint pada sumber, type baru, dan spec E2E — **sukses**, tanpa error atau warning. |
| LSP | Type baru dan hook bersih; file Vue masih menunggu language server saat dipanggil. Diagnostic lain yang dilaporkan berasal dari file di luar perubahan (pre-existing SFC imports/`process`). |

UI sudah ada saat ronde revisi dimulai (`ade80de`), jadi red baseline sebelum implementasi tidak dapat direproduksi. Run pertama spec gagal karena `page.locator('table')` juga menangkap 14 tabel Debugbar; selector diperjelas ke `main table`. Output Playwright awal:

```text
Error: expect(locator).toHaveCount(expected) failed
Locator:  locator('table')
Expected: 0
Received: 14
```

Hook membaca dan membersihkan localStorage secara manual agar akses storage yang gagal dapat ditangkap dengan `try/catch`. Breakpoint baru dibaca saat `onMounted`: SSR dan hidrasi klien dimulai dengan markup Card yang sama, lalu desktop beralih ke tabel setelah hidrasi; test memeriksa console setelah navigasi, bukan raw HTML respons SSR. Fixture E2E menghapus form, jawaban, field, dan user buatannya di `afterEach` agar run berulang tidak menumpuk data.

Baseline entry sebelumnya berasal dari build worktree TASK-208 pada container `d_form_app`. Hash `package-lock.json` pada worktree ini sama dengan baseline resmi (`802ca7f353b929c65bf9d1f10eace914a51908857d0e617ed8e6439c32d14ebf`), tetapi nilai yang dilaporkan di atas adalah ukuran output Vite yang dibulatkan.

## Susulan: toggle pindah ke header tab

Sesudah merge, toggle Kartu/Daftar dipindah keluar dari kartu "Daftar jawaban" ke baris header, tepat di sebelah tab Editor | Jawaban.

| File | Perubahan |
| ---- | --------- |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | Toggle dirender di baris header (setelah `TabsList`) hanya saat tab `jawaban` aktif dan ada jawaban; blok toggle di dalam area jawaban dihapus; header dan grup kiri memakai `flex-wrap` agar tidak meluber di layar sempit. |

Perilaku yang disengaja: toggle tidak dirender di tab Editor (tidak ada yang bisa dilihat) dan tetap `md:hidden` sehingga desktop memakai tabel apa adanya. Pada 375 px lebar konten 343 px tidak cukup untuk tab (236 px) + toggle berlabel (190 px), jadi toggle turun ke baris kedua header — bukan bug.

| Pemeriksaan | Hasil |
| ----------- | ----- |
| ESLint `Show.vue` | **Sukses**, tanpa output (exit 0). |
| E2E `tests/e2e/form-answers-mobile.spec.ts` | **2 passed (32,3 s)** setelah toggle dipindah; spec memilih toggle lewat `getByRole('group', { name: 'Tampilan daftar jawaban' })`, bukan posisi, sehingga relokasi tidak memutusnya. |
| Verifikasi manual 375/430/700/767/1280 px | Toggle tampil hanya di tab Jawaban; 375–430 px turun ke baris kedua, 700–767 px sebaris dengan tab, 1280 px tidak dirender; pilihan `Daftar` tetap tersimpan setelah reload. |
