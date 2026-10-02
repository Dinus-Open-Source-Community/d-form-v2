# Task 8 Report — Periods Show

## Fix tab race (d049277)
File: `resources/js/pages/Dashboard/Recruitment/Periods/Show.vue` (satu-satunya file tersentuh).

Bug: klik tab dari Settings → 2 request (abort + 200, props.tab benar) tapi panel
tak ganti + query hilang. Sebab: `onFinish` request-abort me-null-kan `expectTab`,
lalu watcher melihat `expectTab === null` + `activeTab === 'settings'` → early-return
(guard echo-save) mengabaikan respons asli.

Perubahan perilaku (satu):
- Watcher hanya adopsi bila `props.tab` (dinormalisasi) COCOK `expectTab`;
  respons basi diabaikan, `expectTab` dikonsumsi (null) hanya saat cocok.
  Bila `expectTab === null` (echo PUT save tanpa query) → return, pin bertahan.
- `onFinish` hanya `tabNavigating = false` (tak lagi null-kan `expectTab`,
  sehingga abort tak menghapus ekspektasi navigasi yang masih berjalan).
- `onError` baru: `tabNavigating = false` + `expectTab = null` tanpa ubah `activeTab`.
- `onSettingsSaved` (pin) dan URL strategy (`replace` + `{tab: undefined}` peserta) tak berubah.

Checklist logika:
- [x] Klik cepat 2x tab: `expectTab` tertimpa target kedua; respons basi
  (`props.tab` = target pertama ≠ `expectTab`) diabaikan; respons kedua cocok → adopsi.
- [x] Save settings tetap pin: echo tanpa query saat `expectTab null` → diabaikan,
  `activeTab` tetap `settings` + URL di-replace ke `?tab=settings`.
- [x] Refresh/deep-link: `initialTab()` baca `?tab=` dari URL, tak tersentuh.
