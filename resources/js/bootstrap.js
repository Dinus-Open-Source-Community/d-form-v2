// Konfigurasi axios global dipindah ke `resources/js/adapters/http/httpClient.ts`
// agar adapter HTTP menjadi satu-satunya seam. File ini dipertahankan karena
// `app.js` masih mengimpornya untuk bootstrap non-HTTP di masa depan.
