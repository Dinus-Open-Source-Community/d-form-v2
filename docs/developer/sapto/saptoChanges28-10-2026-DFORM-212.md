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
| `resources/js/components/modules/dashboard/FormAnswersViewToggle.vue` | Kontrol Kartu/Daftar dengan label, status `aria-pressed`, fokus terlihat, dan area tekan 40 px. |
| `resources/js/components/modules/dashboard/FormAnswerMobileCard.vue` | Ringkasan pengirim, status, waktu, jawaban non-berkas, lampiran, dan aksi review/detail. |
| `resources/js/components/modules/dashboard/FormAnswerMobileList.vue` | Baris ringkas yang membuka sheet detail. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | Tabel desktop tetap; komponen mobile ditampilkan di bawah breakpoint `md`; empty state tetap sama. |
| `docs/developer/sapto/design/DFORM-answers-mobile-views.md` | Spesifikasi perilaku pada bingkai 375 px dan 768 px. |

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| LSP pada komponen Vue dan halaman | Tidak ada error. Hook perlu pemeriksaan ulang setelah language server selesai mulai. |
| `npm run build` langsung di host | Gagal karena PHP tidak tersedia di host (`php: command not found` saat Wayfinder berjalan). |
| `npm run build` di container `d_form_app`, dari worktree | **Sukses**, 3.816 modul, selesai 14,54 detik. |
| Ukuran entry dari output Vite | Sebelum: `app-BQqUlGse.js` 75,94 kB (gzip 26,45 kB), dicatat TASK-208. Sesudah: `app-DJqQ_P62.js` 76,00 kB (gzip 26,51 kB). Perubahan tercatat +0,06 kB mentah / +0,06 kB gzip; angka sesudah memakai pembulatan output Vite, bukan pengukuran byte mentah. |
| ESLint untuk lima file sumber yang diubah | **Sukses**, tanpa output error atau warning. |
| `npx tsc --noEmit` | Gagal karena error TS2307 impor Vue SFC yang sudah umum di repo dan beberapa error di file lain; tidak ada error baru dari file tiket yang terlihat pada log build. |
| E2E dan screenshot | Belum dijalankan. Workspace menetapkan Sokudo YAML sebagai satu-satunya runner E2E, tetapi belum ada flow YAML yang tersedia; spesifikasi Playwright dari tiket tidak dapat dijalankan melalui runner tersebut. |

Baseline entry sebelumnya berasal dari build worktree TASK-208 pada container `d_form_app`. Hash `package-lock.json` pada worktree ini sama dengan baseline resmi (`802ca7f353b929c65bf9d1f10eace914a51908857d0e617ed8e6439c32d14ebf`), tetapi nilai yang dilaporkan di atas adalah ukuran output Vite yang dibulatkan.
