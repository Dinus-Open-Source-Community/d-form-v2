# Kebutuhan Frontend ↔ Backend (gap documentation)

Folder ini berisi **dokumentasi PRD-style** yang memetakan apa yang sudah ada di UI (Vue + Inertia) versus apa yang **belum** disediakan oleh service/controller/API backend, agar tim bisa menyelaraskan backlog implementasi.

## Isi

| Dokumen | Deskripsi |
|---------|-----------|
| [frontend-backend-requirements-prd.md](./frontend-backend-requirements-prd.md) | Dokumen utama: ringkasan eksekutif, matriks gap, kebutuhan per domain (user story, kontrak data proposal, kriteria penerimaan, prioritas), non-fungsional |

**Versi dokumen utama:** lihat header di `frontend-backend-requirements-prd.md`.

## Hubungan dengan dokumen lain

- **[docs/prd.md](../../../../prd.md)** — visi produk, MVP, dan model data tingkat tinggi. Dokumen di folder `requires/` **tidak mengganti** PRD tersebut; fokusnya pada **celah implementasi** konkret terhadap UI yang sudah dibuat.
- **[saptoChanges19-04-2026.md](../saptoChanges19-04-2026.md)** — changelog desain/dashboard Sapto (apa yang sudah diubah di sesi tersebut).
- **[nafanChanges17-04-2026.md](../../nafan/nafanChanges17-04-2026.md)** — perubahan backend terkait `registered_count`, validasi tanggal event, endpoint `registration-status`.

## Cara memakai (tim)

1. **Backend:** gunakan matriks gap dan bagian per-domain sebagai acuan ticket (route, payload, validasi, policy).
2. **Frontend:** gunakan dokumen yang sama untuk mengetahui kapan `dummyData` / perilaku placeholder bisa diganti props/API sungguhan.
3. **QA:** kriteria penerimaan per domain dapat dijadikan dasar skenario uji integrasi.

## Pembaruan

Saat modul baru selesai di backend, perbarui matriks dan tandai baris terkait sebagai “tersedia” agar dokumen tetap menjadi sumber kebenaran operasional.
