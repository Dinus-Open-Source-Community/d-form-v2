# Bundle Baseline — DFORM-1487 (TASK-005)

Dokumen ini merekam baseline ukuran bundel frontend **sebelum** pekerjaan code splitting lanjutan, sekaligus membuktikan halaman Inertia benar-benar dimuat lazy. Angka di sini adalah pembanding resmi untuk pengukuran ulang di tiket 15.8.

Status: baseline terekam, tidak ada perubahan kode sumber pada tiket ini.

---

## 1. Metadata pengukuran

| Field | Nilai |
|---|---|
| Commit SHA | `f5a3fd9955b28166e3930e06914f35ed56961331` |
| Tanggal pengukuran | 26-10-2026 |
| `git status --porcelain` | _(kosong — worktree bersih saat pengukuran; dokumen baseline dan baris README ditambahkan sesudahnya)_ |
| Build host kind | **existing container** `d_form_app` (metode primer, bukan pinned image) |
| Image | `localhost/d-form-v2_app:latest` |
| Image digest | `localhost/d-form-v2_app@sha256:e78c13bdf2b85e5813e893b6f9fc3c72177dc15c99e414f907527de7d4a12a71` |
| Image id | `92e9c78cb211` |
| Node | `v24.22.1` |
| npm | `11.12.1` |
| PHP (dibutuhkan `wayfinder` saat build) | `8.4.27` |
| `sha256(package-lock.json)` | `802ca7f353b929c65bf9d1f10eace914a51908857d0e617ed8e6439c32d14ebf` |
| Build command | `podman exec -i -w /app d_form_app npm run build` |
| Build exit code | `0` (log 248 baris) |
| Build duration | `built in 15.88s` |
| Tail build output | `public/build/assets/index-DVGUZA9i.js 334.65 kB │ gzip: 99.40 kB` / `public/build/assets/index-DAWjRt64.js 462.60 kB │ gzip: 145.65 kB` / `✓ built in 15.88s` |

Catatan host: `d_form_app` mem-bind-mount root repositori ke `/app`. Saat pengukuran, checkout tersebut berada pada commit dan `package-lock.json` yang sama persis dengan worktree ini, sehingga hasilnya mewakili commit `f5a3fd9`. Ini juga environment yang akan dipakai pengukuran ulang 15.8.

### Alat ukur gzip

Container `d_form_app` memakai BusyBox gzip `1.37.0` yang tidak menyediakan `gzip --version`, sehingga pengukuran byte dilakukan di image pinned agar versi alat terekam dan reproducible:

| Field | Nilai |
|---|---|
| Measurement host | pinned image `node:22.14.0-bookworm-slim` |
| Image digest | `docker.io/library/node@sha256:1c18d9ab3af4585870b92e4dbc5cac5a0dc77dd13df1a5905cea89fc720eb05b` |
| `gzip --version` baris pertama | `gzip 1.12` |
| Gzip command | `gzip -9 -n -c <file> \| wc -c` |
| Mount | `podman run --rm -v "$PWD/public/build:/build:ro" node:22.14.0-bookworm-slim …` |

`-n` dipakai agar nama file dan timestamp tidak masuk header gzip, jadi byte deterministik.

### Aturan resolusi & ranking (identik untuk 15.8)

- **Entry resolution:** `resources/js/app.js` → `public/build/manifest.json` → field `file` = `assets/app-aFByLaEm.js`. Nama file itulah entry.
- **Chunk ranking:** semua `public/build/assets/*.js` **kecuali** entry, diurutkan menurun berdasarkan byte gzip. Tiga teratas dicatat dengan nama file emit persis + kategori.
- **Category:** diturunkan dari import graph manifest — `vendor` (Vue, Inertia, Reka UI), `page` (dijangkau dari `resources/js/pages/**`), `module` (dijangkau dari `resources/js/components/modules/**`), `other`. Chunk tanpa `src` (shared chunk) dikategorikan lewat leluhur `src` terdekat pada graph `imports` + `dynamicImports`.
- **Perbandingan:** nama file hash boleh berbeda setelah perubahan; yang dibandingkan adalah kategori + total per kategori. Chunk halaman dinilai terhadap budget **per kategori**, bukan per nama file.

---

## 2. Verifikasi lazy page loading (Requirement 11.1, 11.3, 11.4)

**11.1 — `import.meta.glob` tanpa `eager`.**

`resources/js/app.js:9`:

```js
const pages = import.meta.glob('./pages/**/*.vue');
```

Tidak ada objek opsi, jadi `eager` bernilai default `false`: setiap page menjadi dynamic import. Bukti pendukung:

- `grep -rn "eager" resources/js` → **tidak ada hasil** (tidak ada `eager: true` di mana pun).
- `resources/js/ssr.js:6` memakai pola yang sama untuk jalur SSR.

**11.2 — setiap page menjadi chunk sendiri.**

Dari `public/build/manifest.json` (hasil build di atas):

| Metrik | Nilai |
|---|---|
| Modul page di manifest (`src` di bawah `resources/js/pages/`) | 58 |
| File emit unik untuk page tersebut | 58 |
| `dynamicImports` pada entry `resources/js/app.js` | 59 (58 page + `error-message-*.js`) |
| Semua modul page muncul di `dynamicImports` entry | ya |
| `imports` statis entry | hanya `_vendor-inertia-cLorBHo-.js` dan `_vendor-vue-DicCB_a2.js` |

Artinya tidak ada kode page yang ikut ke entry: entry hanya menarik dua chunk vendor.

**11.3 — entry JS dan budget.**

- Entry JS gzip: **26.177 byte (25,56 KiB)** — jauh di bawah budget entry ≤ 350 kB gzip.
- Tidak ada chunk page > 200 kB gzip. Chunk page terbesar adalah `index-DVGUZA9i.js` (97,40 KiB gzip), yaitu `html5-qrcode` yang dimuat dari halaman scan; di bawahnya `vue3-lottie.es-CJBoFP4l.js` (78,54 KiB).
- Chunk terbesar keseluruhan bukan chunk page: `index-DAWjRt64.js` (141,83 KiB gzip) adalah bundel Tiptap/ProseMirror yang hanya dijangkau dari `components/modules/**` (kategori `module`). Dua chunk page terbesar adalah `index-DVGUZA9i.js` (97,40 KiB, `html5-qrcode`) dan `vue3-lottie.es-CJBoFP4l.js` (78,54 KiB) — keduanya dimuat lazy dari halaman pemakainya.

**11.4 — tidak ada bundel JS raksasa yang dimuat di first paint.** Entry 25,56 KiB gzip + dua chunk vendor (36,01 KiB + 34,63 KiB gzip) adalah satu-satunya JS yang dimuat saat first paint; total ±96,2 KiB gzip.

---

## 3. Tabel per-chunk (byte gzip, menurun)

Kategori mengikuti aturan di §1. Entry `app-aFByLaEm.js` tidak masuk tabel (dibahas terpisah).

| # | Emitted chunk (`public/build/assets/…`) | gzip bytes | gzip KiB | Category |
|---:|---|---:|---:|---|
| 1 | `index-DAWjRt64.js` | 145236 | 141.83 | module |
| 2 | `index-DVGUZA9i.js` | 99738 | 97.40 | page |
| 3 | `vue3-lottie.es-CJBoFP4l.js` | 80421 | 78.54 | page |
| 4 | `index-7gjyMSGW.js` | 57618 | 56.27 | module |
| 5 | `Show-BtQ34rzO.js` | 41089 | 40.13 | page |
| 6 | `vendor-vue-DicCB_a2.js` | 36878 | 36.01 | vendor |
| 7 | `vendor-inertia-cLorBHo-.js` | 35464 | 34.63 | vendor |
| 8 | `form-kdY8Chhx.js` | 20059 | 19.59 | page |
| 9 | `DatePicker.vue_vue_type_script_setup_true_lang-XVr30zU6.js` | 14325 | 13.99 | page |
| 10 | `DashboardTopbar.vue_vue_type_script_setup_true_lang-roJCzfPN.js` | 12897 | 12.59 | page |
| 11 | `Docs-BWe3Sf7K.js` | 12592 | 12.30 | page |
| 12 | `Global-CK5JsN6O.js` | 11769 | 11.49 | page |
| 13 | `index-M4Wh_aH4.js` | 11755 | 11.48 | vendor |
| 14 | `error-message-KiFpDa-0.js` | 11577 | 11.31 | page |
| 15 | `FieldEditor.vue_vue_type_script_setup_true_lang-DKfdJJfs.js` | 11545 | 11.27 | page |
| 16 | `PopperContent-D2KZLUlR.js` | 10719 | 10.47 | page |
| 17 | `Submissions-D0bqICwe.js` | 9731 | 9.50 | page |
| 18 | `EventDashboardForm-CGduDB0Y.js` | 9301 | 9.08 | page |
| 19 | `Index-CJDOsVXA.js` | 9136 | 8.92 | page |
| 20 | `Show-DyKbT2ab.js` | 8769 | 8.56 | page |
| 21 | `Show-QZwvNJ3O.js` | 8312 | 8.12 | page |
| 22 | `FormBuilder-DFVhmzUt.js` | 8287 | 8.09 | page |
| 23 | `Fill-DeIME5TU.js` | 7618 | 7.44 | page |
| 24 | `useFormFillPage-BTsg3auS.js` | 6889 | 6.73 | page |
| 25 | `Features-BIkHTXgo.js` | 6532 | 6.38 | page |
| 26 | `Registrants-CIy4bXwF.js` | 6510 | 6.36 | page |
| 27 | `Show-Com8Gq1L.js` | 6473 | 6.32 | page |
| 28 | `DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-CvEzFA3m.js` | 6279 | 6.13 | page |
| 29 | `Index-BHHy6wHQ.js` | 5811 | 5.67 | page |
| 30 | `Index-BBbI-ZmA.js` | 5709 | 5.58 | page |
| 31 | `Event-BSiM_0NQ.js` | 5570 | 5.44 | page |
| 32 | `EventDetail-B1rtFArm.js` | 5541 | 5.41 | page |
| 33 | `EventDetail-ChD7zMyb.js` | 5408 | 5.28 | page |
| 34 | `shadcnDateFormat-B70oNnAE.js` | 5366 | 5.24 | page |
| 35 | `Teleport-BnJ4SQeN.js` | 5323 | 5.20 | page |
| 36 | `Create-B5zZfPd8.js` | 5072 | 4.95 | page |
| 37 | `LandingLayout.vue_vue_type_script_setup_true_lang-Cdmz506Z.js` | 5016 | 4.90 | page |
| 38 | `VerifyOtp-DGtGcxS2.js` | 4987 | 4.87 | page |
| 39 | `Show-Cj-Ch-yB.js` | 4975 | 4.86 | page |
| 40 | `Apply-CzFzdKW8.js` | 4896 | 4.78 | page |
| 41 | `BroadcastContentEditor-CQa_vFuX.js` | 4871 | 4.76 | page |
| 42 | `useAuthTabs-CdIkiYP6.js` | 4829 | 4.72 | page |
| 43 | `TeamInvitation-B86zlkko.js` | 4708 | 4.60 | page |
| 44 | `Show-v8sIMKVX.js` | 4668 | 4.56 | page |
| 45 | `Show-mspBxbr-.js` | 4655 | 4.55 | page |
| 46 | `Index-Co1UIuRH.js` | 4465 | 4.36 | page |
| 47 | `Profile-Bs0msz0H.js` | 4204 | 4.11 | page |
| 48 | `EventCalendar.vue_vue_type_script_setup_true_lang-D-FH0bxl.js` | 4097 | 4.00 | page |
| 49 | `Edit-JK9GvqRC.js` | 4061 | 3.97 | page |
| 50 | `FormFieldAnswerDisplay.vue_vue_type_script_setup_true_lang-B2rRqXiZ.js` | 3679 | 3.59 | page |
| 51 | `EventFilterBar.vue_vue_type_script_setup_true_lang-CV2F81P1.js` | 3536 | 3.45 | page |
| 52 | `TipTapEditor-Pj3laGH-.js` | 3439 | 3.36 | page |
| 53 | `EventRegistration-BynTQaup.js` | 3430 | 3.35 | page |
| 54 | `Index-DHSOQFUW.js` | 3279 | 3.20 | page |
| 55 | `index-Bopxoc5f.js` | 3248 | 3.17 | page |
| 56 | `Index-BswSun6J.js` | 3177 | 3.10 | page |
| 57 | `Create-Ogj17Tfz.js` | 3175 | 3.10 | page |
| 58 | `UserAvatarFallback.vue_vue_type_script_setup_true_lang-DWtUDUfW.js` | 2981 | 2.91 | page |
| 59 | `Events-ALaJeEIV.js` | 2957 | 2.89 | page |
| 60 | `Laporan-D2NfuaFQ.js` | 2892 | 2.82 | page |
| 61 | `InterviewRowCard.vue_vue_type_script_setup_true_lang-C9ETNind.js` | 2743 | 2.68 | page |
| 62 | `SecretParty-DprRRaA1.js` | 2676 | 2.61 | page |
| 63 | `PopoverTrigger.vue_vue_type_script_setup_true_lang-B7Kh2JjT.js` | 2632 | 2.57 | page |
| 64 | `DialogTitle-D-ItuM4l.js` | 2623 | 2.56 | page |
| 65 | `EventRegistrationPickForm-UFT81OTc.js` | 2593 | 2.53 | page |
| 66 | `LoginController-TZI0X15k.js` | 2473 | 2.42 | page |
| 67 | `Checkbox.vue_vue_type_script_setup_true_lang-DTRbVp3K.js` | 2460 | 2.40 | page |
| 68 | `Index-F7GLNqo6.js` | 2426 | 2.37 | page |
| 69 | `Create-BtV3qT3p.js` | 2281 | 2.23 | page |
| 70 | `Create-Cx9sZACB.js` | 2205 | 2.15 | page |
| 71 | `AppPagination.vue_vue_type_script_setup_true_lang-C192zHUf.js` | 2198 | 2.15 | page |
| 72 | `Index-k__dFMBQ.js` | 2130 | 2.08 | page |
| 73 | `dummyData-DX4Gr1am.js` | 2109 | 2.06 | page |
| 74 | `Edit-pSNokRVp.js` | 2044 | 2.00 | page |
| 75 | `CategoryChart-BYrIWfRP.js` | 2015 | 1.97 | page |
| 76 | `SplitDateTimeField.vue_vue_type_script_setup_true_lang-BS5jhtzp.js` | 2011 | 1.96 | page |
| 77 | `Create-D9TYuuZN.js` | 1986 | 1.94 | page |
| 78 | `ConfirmationModal.vue_vue_type_script_setup_true_lang-C11le_Gt.js` | 1941 | 1.90 | page |
| 79 | `PeriodBannerField.vue_vue_type_script_setup_true_lang-tgtKeXJj.js` | 1921 | 1.88 | page |
| 80 | `Index-nCejGSOI.js` | 1908 | 1.86 | page |
| 81 | `SimpleSelect.vue_vue_type_script_setup_true_lang-ChMwrsGz.js` | 1897 | 1.85 | page |
| 82 | `TabsTrigger.vue_vue_type_script_setup_true_lang-CkB-TsXY.js` | 1883 | 1.84 | page |
| 83 | `Login-CI3bq9Ps.js` | 1872 | 1.83 | page |
| 84 | `Presence-C9pRMPu6.js` | 1860 | 1.82 | page |
| 85 | `RovingFocusGroup-B8VAbq6X.js` | 1855 | 1.81 | page |
| 86 | `SubmitSuccess-DKDDOIOy.js` | 1845 | 1.80 | page |
| 87 | `DashboardFocusLayout.vue_vue_type_script_setup_true_lang-Y3P-UP8E.js` | 1782 | 1.74 | page |
| 88 | `Index-B8HyiClu.js` | 1773 | 1.73 | page |
| 89 | `RegistrationChart-Wfe0salH.js` | 1760 | 1.72 | page |
| 90 | `Show-WPb7D0qq.js` | 1727 | 1.69 | page |
| 91 | `ResetPassword-B5_jADJm.js` | 1662 | 1.62 | page |
| 92 | `index-B6cKOBrZ.js` | 1562 | 1.53 | page |
| 93 | `Success-DfZIRo3m.js` | 1558 | 1.52 | page |
| 94 | `Switch.vue_vue_type_script_setup_true_lang-TgTdY0s7.js` | 1549 | 1.51 | page |
| 95 | `ForgotPassword-D9IYIPbK.js` | 1537 | 1.50 | page |
| 96 | `isValueEqualOrExist-4oxsOvrZ.js` | 1457 | 1.42 | page |
| 97 | `LocalLottie.vue_vue_type_script_setup_true_lang-Bv0VeTrt.js` | 1434 | 1.40 | page |
| 98 | `OpRecFeedbackForm.vue_vue_type_script_setup_true_lang-L3AlfYSD.js` | 1353 | 1.32 | page |
| 99 | `Edit--CL39_JE.js` | 1268 | 1.24 | page |
| 100 | `Datasets-rVCznovV.js` | 1246 | 1.22 | page |
| 101 | `Error-BLwZguTh.js` | 1236 | 1.21 | page |
| 102 | `Attendance-Cqnjh_K7.js` | 1232 | 1.20 | page |
| 103 | `DialogContent.vue_vue_type_script_setup_true_lang-BDp-FiC_.js` | 1200 | 1.17 | page |
| 104 | `HomeCTA.vue_vue_type_script_setup_true_lang-CncAXSp4.js` | 1198 | 1.17 | page |
| 105 | `useAutosaveSync-DzmL2Um-.js` | 1157 | 1.13 | page |
| 106 | `KpiCard.vue_vue_type_script_setup_true_lang-DjOk9an0.js` | 1125 | 1.10 | page |
| 107 | `SeoHead.vue_vue_type_script_setup_true_lang-Dm_2TLEO.js` | 1125 | 1.10 | page |
| 108 | `usePageFlashToast-BlaAeVaB.js` | 1116 | 1.09 | page |
| 109 | `useBuilderImageUpload-Bp7hHKi7.js` | 1095 | 1.07 | page |
| 110 | `formFieldOptions-mg-tvTWH.js` | 1085 | 1.06 | page |
| 111 | `RovingFocusItem-D5Pudm8B.js` | 1041 | 1.02 | page |
| 112 | `VisuallyHiddenInput-KgGEeLH_.js` | 1015 | 0.99 | page |
| 113 | `FormFillLayout.vue_vue_type_script_setup_true_lang-DXEsutat.js` | 932 | 0.91 | page |
| 114 | `Feedback-DFPJxVdU.js` | 924 | 0.90 | page |
| 115 | `TabsContent.vue_vue_type_script_setup_true_lang-CcsxfjCP.js` | 911 | 0.89 | page |
| 116 | `FormAnswerReviewController-5KYgJ2qi.js` | 885 | 0.86 | page |
| 117 | `Spinner.vue_vue_type_script_setup_true_lang-DnbLQPhT.js` | 884 | 0.86 | page |
| 118 | `Register-CqsPfkq4.js` | 808 | 0.79 | page |
| 119 | `Auth-DCHSdbhs.js` | 805 | 0.79 | page |
| 120 | `Login-D0jw1F0Z.js` | 805 | 0.79 | page |
| 121 | `Input.vue_vue_type_script_setup_true_lang-CVwtK_Z6.js` | 787 | 0.77 | page |
| 122 | `rocket-B_qzd_Zh.js` | 739 | 0.72 | page |
| 123 | `EventBannerImage.vue_vue_type_script_setup_true_lang-BXeIW51z.js` | 733 | 0.72 | page |
| 124 | `createLucideIcon-DNJ14G-F.js` | 716 | 0.70 | page |
| 125 | `formBanner-Y529tLsn.js` | 711 | 0.69 | page |
| 126 | `DashboardLayout.vue_vue_type_script_setup_true_lang-C_v3PzCZ.js` | 691 | 0.67 | page |
| 127 | `Textarea.vue_vue_type_script_setup_true_lang-n5dQWAhm.js` | 669 | 0.65 | page |
| 128 | `Separator.vue_vue_type_script_setup_true_lang-RQm13FZw.js` | 666 | 0.65 | page |
| 129 | `dateTime-C0c1CZx5.js` | 643 | 0.63 | page |
| 130 | `DialogFooter.vue_vue_type_script_setup_true_lang-BC_8WeNl.js` | 638 | 0.62 | page |
| 131 | `TableHeader.vue_vue_type_script_setup_true_lang-wSJca6Hu.js` | 629 | 0.61 | page |
| 132 | `lazyRichTextEditors-AXQ3Lrsn.js` | 586 | 0.57 | page |
| 133 | `EmptyState.vue_vue_type_script_setup_true_lang-uwR5GsOT.js` | 569 | 0.56 | page |
| 134 | `Label.vue_vue_type_script_setup_true_lang-ucOLZuTi.js` | 562 | 0.55 | page |
| 135 | `index-C_SHgnPd.js` | 540 | 0.53 | page |
| 136 | `EventController-Di-B4gXv.js` | 532 | 0.52 | page |
| 137 | `DialogTitle.vue_vue_type_script_setup_true_lang-BMrr2CR8.js` | 496 | 0.48 | page |
| 138 | `VisuallyHidden-CvHwNrL_.js` | 496 | 0.48 | page |
| 139 | `index-DleaOTlm.js` | 490 | 0.48 | page |
| 140 | `formParagraphContent-DmII1Uvo.js` | 481 | 0.47 | page |
| 141 | `DeferredRender.vue_vue_type_script_setup_true_lang-BbhWh9ko.js` | 473 | 0.46 | page |
| 142 | `Progress.vue_vue_type_script_setup_true_lang-DXi11gAP.js` | 450 | 0.44 | page |
| 143 | `CardTitle.vue_vue_type_script_setup_true_lang-Le9W1Rik.js` | 416 | 0.41 | page |
| 144 | `index-BtbNOeJ4.js` | 410 | 0.40 | page |
| 145 | `Card.vue_vue_type_script_setup_true_lang-D9JJ1OAg.js` | 386 | 0.38 | page |
| 146 | `FormParagraphContent.vue_vue_type_script_setup_true_lang-DIUGw8EK.js` | 364 | 0.36 | page |
| 147 | `TooltipProvider.vue_vue_type_script_setup_true_lang-BuufYsEd.js` | 352 | 0.34 | page |
| 148 | `DialogDescription-B3yVcDB7.js` | 342 | 0.33 | page |
| 149 | `thumbs-down-Bd40yZKB.js` | 326 | 0.32 | page |
| 150 | `InterviewRowCard-DylE6x1y.js` | 319 | 0.31 | page |
| 151 | `qr-code-B4hoFU2Q.js` | 316 | 0.31 | page |
| 152 | `TiptapRichHtml.vue_vue_type_script_setup_true_lang-DlQK5L9A.js` | 305 | 0.30 | page |
| 153 | `image-off-DQuDEOO3.js` | 301 | 0.29 | page |
| 154 | `star-HSlsO5Qz.js` | 298 | 0.29 | page |
| 155 | `CardDescription.vue_vue_type_script_setup_true_lang-BN2h5aZ_.js` | 294 | 0.29 | page |
| 156 | `CardContent.vue_vue_type_script_setup_true_lang-DZjZTT_P.js` | 281 | 0.27 | page |
| 157 | `eye-off-C_3iH9PG.js` | 278 | 0.27 | page |
| 158 | `calendar-days-CM0dvwym.js` | 272 | 0.27 | page |
| 159 | `file-spreadsheet-CTwJ12XE.js` | 267 | 0.26 | page |
| 160 | `clipboard-list-cv5SMXdF.js` | 266 | 0.26 | page |
| 161 | `toggle-right-tRmirhme.js` | 264 | 0.26 | page |
| 162 | `calendar-range-D30JzvOC.js` | 262 | 0.26 | page |
| 163 | `settings-Bj-Lwft6.js` | 260 | 0.25 | page |
| 164 | `file-text-DQ-tMAN3.js` | 257 | 0.25 | page |
| 165 | `image-plus-CPWkiWkb.js` | 257 | 0.25 | page |
| 166 | `shield-check-euEwOjge.js` | 256 | 0.25 | page |
| 167 | `calendar-clock-BBHppBQX.js` | 255 | 0.25 | page |
| 168 | `image-up-DrQPohhW.js` | 253 | 0.25 | page |
| 169 | `layers-FOa0c_yB.js` | 247 | 0.24 | page |
| 170 | `file-stack-D1oxyEzd.js` | 243 | 0.24 | page |
| 171 | `user-plus-B2a57gc7.js` | 238 | 0.23 | page |
| 172 | `inbox-BHkcpjWP.js` | 237 | 0.23 | page |
| 173 | `clipboard-check-BsaP-Kb_.js` | 236 | 0.23 | page |
| 174 | `send-ByHUVeIQ.js` | 235 | 0.23 | page |
| 175 | `save-VobwKsHD.js` | 233 | 0.23 | page |
| 176 | `refresh-cw-DKJaqhtz.js` | 231 | 0.23 | page |
| 177 | `pencil-DppolB6n.js` | 229 | 0.22 | page |
| 178 | `image-2Gs6y_Zr.js` | 228 | 0.22 | page |
| 179 | `users-PDOWE04F.js` | 228 | 0.22 | page |
| 180 | `map-pin-Cb7-4gi-.js` | 226 | 0.22 | page |
| 181 | `eventBannerAspect-BOl-6J1X.js` | 225 | 0.22 | page |
| 182 | `ticket-CiWU09BY.js` | 224 | 0.22 | page |
| 183 | `trash-2-DlbMFgnb.js` | 224 | 0.22 | page |
| 184 | `usePrimitiveElement-Btf46FwO.js` | 220 | 0.21 | page |
| 185 | `copy-5Tie4lJR.js` | 211 | 0.21 | page |
| 186 | `calendar-DSVQf82O.js` | 207 | 0.20 | page |
| 187 | `mail-ucOv9BbV.js` | 205 | 0.20 | page |
| 188 | `external-link-P1lcMjXj.js` | 204 | 0.20 | page |
| 189 | `eye-B1gPJbUq.js` | 203 | 0.20 | page |
| 190 | `grip-vertical-C62hKj5S.js` | 200 | 0.20 | page |
| 191 | `chart-column-lDeRl58H.js` | 199 | 0.19 | page |
| 192 | `download-CqgsyHyv.js` | 197 | 0.19 | page |
| 193 | `upload-CNEFX8Rm.js` | 197 | 0.19 | page |
| 194 | `lock-BObqUBCy.js` | 195 | 0.19 | page |
| 195 | `log-out-3JZ0JTuz.js` | 194 | 0.19 | page |
| 196 | `circle-alert-CODAsS0N.js` | 190 | 0.19 | page |
| 197 | `smartphone-D7gC6fMB.js` | 189 | 0.18 | page |
| 198 | `user-CJevJ_dJ.js` | 188 | 0.18 | page |
| 199 | `rotate-ccw-Bf3Ux3jy.js` | 187 | 0.18 | page |
| 200 | `x-Bcic4jZl.js` | 186 | 0.18 | page |
| 201 | `user-round-CFwEaq9i.js` | 181 | 0.18 | page |
| 202 | `circle-x-gjrlFX0T.js` | 180 | 0.18 | page |
| 203 | `_commonjsHelpers-Cpj98o6Y.js` | 179 | 0.17 | page |
| 204 | `text-align-start-hnA1jLhj.js` | 178 | 0.17 | page |
| 205 | `trending-up-Dl_W3-KO.js` | 177 | 0.17 | page |
| 206 | `useDirection-CjXT4GcE.js` | 172 | 0.17 | page |
| 207 | `arrow-left-DNzUFoLm.js` | 170 | 0.17 | page |
| 208 | `arrow-right-DQCxPR3a.js` | 170 | 0.17 | page |
| 209 | `circle-check-BVw3pelp.js` | 170 | 0.17 | page |
| 210 | `clock-3-B-crHDZ8.js` | 170 | 0.17 | page |
| 211 | `search-CJaXZKD3.js` | 170 | 0.17 | page |
| 212 | `arrow-down-D2QGQdvV.js` | 169 | 0.17 | page |
| 213 | `clock-eXIckc6v.js` | 169 | 0.17 | page |
| 214 | `arrow-up-D9Jm9aM8.js` | 166 | 0.16 | page |
| 215 | `eventCategories-mDmbkkls.js` | 160 | 0.16 | page |
| 216 | `plus-9b6Vy8AM.js` | 156 | 0.15 | page |
| 217 | `isHttpUrl-BZ3Sg6Dz.js` | 154 | 0.15 | page |
| 218 | `circle-dot-CDsFigXI.js` | 153 | 0.15 | page |
| 219 | `chevron-left-BvOgdkTf.js` | 149 | 0.15 | page |
| 220 | `chevron-down-ldd60Zbp.js` | 146 | 0.14 | page |
| 221 | `chevron-right-DncpadzD.js` | 146 | 0.14 | page |
| 222 | `check-CMhI9Nfz.js` | 143 | 0.14 | page |
| 223 | `chevron-up-CONgXbtr.js` | 143 | 0.14 | page |
| 224 | `formCheckboxAnswers-C-j5Fff0.js` | 142 | 0.14 | page |
| 225 | `minus-rK1MJEpT.js` | 136 | 0.13 | page |
| 226 | `circle-CKPvKOgM.js` | 133 | 0.13 | page |
| 227 | `_plugin-vue_export-helper-DlAUqK2U.js` | 102 | 0.10 | page |

---

## 4. Rekap kategori dan tiga chunk terbesar

| Kategori | Jumlah chunk | Total gzip byte | Total gzip KiB |
|---|---:|---:|---:|
| vendor | 3 | 84.097 | 82,13 |
| page | 222 | 691.721 | 675,51 |
| module | 2 | 202.854 | 198,10 |
| other | 0 | 0 | 0,00 |
| **Total non-entry** | **227** | **978.672** | **955,73** |

Catatan: total `page` adalah penjumlahan seluruh chunk halaman di disk, bukan berat satu halaman — setiap halaman hanya memuat chunk-nya sendiri.

**Tiga chunk JS terbesar (gzip):**

| Peringkat | File emit | gzip byte | gzip KiB | Kategori | Isi |
|---:|---|---:|---:|---|---|
| 1 | `index-DAWjRt64.js` | 145.236 | 141,83 | module | Tiptap + ProseMirror (dipakai `TipTapEditor.vue`, `BroadcastContentEditor.vue`) |
| 2 | `index-DVGUZA9i.js` | 99.738 | 97,40 | page | `html5-qrcode` (halaman scan) |
| 3 | `vue3-lottie.es-CJBoFP4l.js` | 80.421 | 78,54 | page | `vue3-lottie` (dimuat lazy dari halaman yang memakai lottie) |

---

## 5. Checklist komparabilitas untuk 15.8

Pengukuran ulang **wajib** memakai nilai berikut agar bisa dibandingkan dengan tabel di atas. Bila salah satu berbeda, baseline ini void dan harus diukur ulang di environment 15.8 sebelum klaim before/after ditulis.

- [ ] Build host kind: existing container `d_form_app`
- [ ] Image digest: `localhost/d-form-v2_app@sha256:e78c13bd…12a71`
- [ ] Node `v24.22.1`
- [ ] npm `11.12.1`
- [ ] `sha256(package-lock.json)` = `802ca7f353b929c65bf9d1f10eace914a51908857d0e617ed8e6439c32d14ebf`; bila berbeda, baseline void (tidak ada pembandingan berkualifikasi)
- [ ] Gzip `1.12` (image `node:22.14.0-bookworm-slim` digest `…720eb05b`)
- [ ] Build command `podman exec -i -w /app d_form_app npm run build`
- [ ] Entry resolution via `manifest.json` → `file` untuk `resources/js/app.js`
- [ ] Chunk ranking: `public/build/assets/*.js` minus entry, urut byte gzip menurun
- [ ] Kategori dari import graph manifest (`vendor`/`page`/`module`/`other`)

---

## 6. Attachment yang diverifikasi

| File | Bagian yang diperiksa |
|---|---|
| `resources/js/app.js` | baris 9 — `import.meta.glob('./pages/**/*.vue')` tanpa `eager` |
| `vite.config.js` | baris 36–45 — `build.rollupOptions.output.manualChunks` (`vendor-vue`, `vendor-inertia`) |
| `package.json` | baris 6 — script `build`: `vite build` |
