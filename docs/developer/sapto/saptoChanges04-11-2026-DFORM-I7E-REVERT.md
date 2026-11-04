# Sapto Changes — 4 November 2026 (DFORM-I7E REVERT)

## Ringkasan

Redesign tab Jawaban (epic `dform-i7e`) di-revert atas keputusan user karena UI baru dinilai tidak sesuai. Tab Jawaban kembali persis ke keadaan `b9c3edf` (sebelum epic), lalu kode lama dirapikan agar mengikuti aturan `AGENTS.md` (prefix `I`/`G`/`T`).

| Issue       | Commit                                                               |
| ----------- | -------------------------------------------------------------------- |
| `dform-i7e` | `revert(forms): restore legacy answers tab UI`                       |
| `dform-i7e` | `refactor(answers): apply type prefix rule to restored answers code` |

## Yang di-revert

Enam commit UI/test epic, di-revert berurutan dari yang terbaru:

| Commit    | Isi                                        |
| --------- | ------------------------------------------ |
| `a32441c` | retry state untuk reload jawaban gagal     |
| `6efdbd2` | wait panel settings pada assertion e2e     |
| `d3c4202` | stabilisasi coverage e2e answers views     |
| `50ede2d` | pencarian, filter status, pagination klien |
| `fbad15a` | tampilan kartu (grid)                      |
| `157eab3` | tampilan tabel modern                      |

File yang hilang karena revert: `FormAnswersTableView.vue`, `FormAnswersCardGrid.vue`, `FormAnswersToolbar.vue`, `FormAnswersPaginationBar.vue`, `useFormAnswersView.ts`, `useFormAnswersListState.ts`, `useFormAnswersReload.ts`, `types/formAnswersView.ts`, `tests/e2e/form-answers-views.desktop.spec.ts`. File lama kembali: `FormAnswersViewToggle.vue`, `FormAnswerMobileCard.vue`, `useFormAnswerMobileView.ts`, `types/formAnswerMobileView.ts`, `Show.vue`, `tests/e2e/form-answers-mobile.spec.ts`.

Bukti: `git diff b9c3edf -- <path tab jawaban>` kosong sebelum perapian tipe; sesudah perapian hanya berisi tiga file rename tipe.

Changelog epic `saptoChanges03-11-2026-DFORM-216.md` **tidak dihapus**; isinya dipertahankan sebagai sejarah, bukan dokumentasi keadaan sekarang.

## Perapian aturan #1 (prefix `I`/`G`/`T`)

Hanya file tab Jawaban yang dipulihkan (bukan seluruh `resources/js`; sweep 260 deklarasi di 111 file adalah pekerjaan terpisah):

| File                                                                  | Sebelum                             | Sesudah                             |
| --------------------------------------------------------------------- | ----------------------------------- | ----------------------------------- |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue`                  | `type ShowTab`                      | `type TShowTab`                     |
| `resources/js/types/formAnswerMobileView.ts`                          | `export type FormAnswerMobileView`  | `export type TFormAnswerMobileView` |
| `resources/js/components/modules/dashboard/FormAnswersViewToggle.vue` | import/usage `FormAnswerMobileView` | `TFormAnswerMobileView`             |
| `resources/js/hooks/useFormAnswerMobileView.ts`                       | import/usage `FormAnswerMobileView` | `TFormAnswerMobileView`             |

Deklarasi lain di file tab (`FormAnswerDetailSheet`, `FormAnswerMobileCard`, `FormAnswerMobileList`, `lib/formSubmissionsUi.ts`) tidak memiliki `interface`/`type` lokal, jadi tidak ada yang diubah. Tipe bersama seperti `BackendField`, `BuilderField`, `FormSiblingOption` berada di `resources/js/types/**` yang dipakai lintas fitur; rename-nya sengaja tidak dilakukan di sini agar tidak mencampur refactor tab dengan perubahan global.

## Verifikasi

```text
$ npm run typecheck          -> PASS
$ npm run lint               -> PASS
$ podman exec -i -w /app d_form_app npm run build -> built in 15.49s
$ node scripts/build/check-bundle-budget.mjs      -> ok (entry 26,5 kB; 231 chunk <= 200 kB)
$ npx playwright test tests/e2e/form-answers-mobile.spec.ts \
    --project=chromium-mobile --retries=0
  -> 5 passed, 1 failed (auth flake: signIn berhenti di /auth/login; error context
     'expect(page).not.toHaveURL' — bukan assertion tab jawaban)
$ npx playwright test ... -g "section form dapat ditambah" --retries=1 -> passed
```

Catatan flake: kegagalan pertama adalah flake login yang sudah tercatat pada changelog epic (`signIn` tidak selalu berpindah dari `/auth/login`), bukan akibat revert; test yang sama hijau saat dijalankan ulang.

## Keputusan

- Baseline revert adalah `b9c3edf` (commit tepat sebelum commit epic pertama), bukan revert per-file, agar tidak ada sisa perilaku baru (search/pagination/card/table) yang tertinggal.
- Tiket epic `dform-i7e` dan turunannya ditutup dengan alasan "reverted by user" agar kanban sesuai kode.
- Perbaikan aturan #1 dibatasi ke file tab Jawaban; sweep `resources/js` lain (termasuk `components/ui/**` yang generated dan `types/form-builder.ts`) perlu tiket sendiri bila diinginkan.
