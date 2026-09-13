# DOSCOM OpenRecruitment (OpRec) — Dokumentasi Modul

**Versi:** 1.0  
**Status:** Baseline development  
**Platform:** D-Form v2 (Laravel 12 + Vue 3 + Inertia.js)  
**PRD sumber:** [PRD — DOSCOM OpenRecruitment (OpRec)](../PRD%20—%20DOSCOM%20OpenRecruitment%20(OpRec).md)

---

## Tentang Modul

DOSCOM OpenRecruitment (OpRec) adalah sistem berbasis web untuk mengelola proses penerimaan anggota DOSCOM secara end-to-end: pendaftaran applicant tanpa login, screening berkas, penjadwalan interview, attendance & antrean, penilaian interviewer, seleksi akhir, penempatan divisi, hingga feedback.

Halaman publik utama: **`/open-recruitment`**  
Dashboard internal: **`/admin/recruitment`**

OpRec dibangun sebagai **modul domain terpisah** di atas platform D-Form v2, dengan reuse infrastruktur email, QR, file storage, dan pola arsitektur yang sudah ada.

---

## Daftar Dokumentasi

| Dokumen | Isi |
|---------|-----|
| [overview.md](overview.md) | Ringkasan produk, actor, journey, business rules |
| [architecture.md](architecture.md) | Arsitektur teknis, layer, integrasi D-Form |
| [domain-model.md](domain-model.md) | Entity, enum, state machine, transisi status |
| [database-design.md](database-design.md) | ERD, skema tabel, index, constraint |
| [authorization.md](authorization.md) | Role, permission matrix, policy mapping |
| [routes-and-pages.md](routes-and-pages.md) | Route map public + admin, halaman Inertia |
| [notifications.md](notifications.md) | Email matrix, template variables, queue/reminder |
| [milestone.md](milestone.md) | **Milestone development lengkap (M0–M11)** |
| [testing-strategy.md](testing-strategy.md) | Test matrix per workflow & permission |
| [decisions.md](decisions.md) | Keputusan stakeholder & open questions PRD §50 |

---

## Acuan Teknis Proyek

- [PRD D-Form v2](../../prd.md)
- [Milestone D-Form v2](../../milestone.md)
- [Pedoman back-end](../../rules/back-end.md)
- [Pedoman front-end](../../rules/front-end.md)
- [Struktur folder](../../02-directory-structure.md)

---

## Glosarium

| Istilah | Definisi |
|---------|----------|
| **Applicant** | Calon anggota yang mendaftar OpRec; tidak memerlukan akun/login |
| **Recruitment Period** | Satu siklus OpRec (mis. OpRec 2026); data antar periode terisolasi |
| **Registration Number** | Nomor pendaftaran unik, format `OPREC-{YEAR}-{SEQ}` |
| **Tracking Token** | Kredensial rahasia guest untuk akses halaman tracking (disimpan hashed) |
| **Staff** | Panitia yang melakukan screening, scheduling, final selection |
| **Interviewer** | Anggota yang menilai applicant pada tahap interview |
| **Stage** | Tahap proses aplikasi (submitted → screening → interview → final_review → completed) |
| **FCFS** | First Come, First Served — aturan antrean interview berdasarkan urutan check-in |

---

## Keadaan Implementasi Saat Ini

| Area | Status |
|------|--------|
| Admin route `/admin/recruitment` | Placeholder "Segera hadir" |
| Public `/open-recruitment` | Belum ada |
| Model / migrasi recruitment | Belum ada |
| Permission Spatie `recruitments.*` | Stub di `RoleSeeder`, masih di-comment |
| PRD produk | Lengkap (v1.0 draft) |
| Dokumentasi teknis | Folder `docs/module/oprec/` (dokumen ini) |

---

## Urutan Baca untuk Developer Baru

1. [overview.md](overview.md) — pahami produk & alur bisnis
2. [architecture.md](architecture.md) — pahami layer & integrasi
3. [domain-model.md](domain-model.md) + [database-design.md](database-design.md) — pahami data model
4. [authorization.md](authorization.md) + [routes-and-pages.md](routes-and-pages.md) — pahami akses & endpoint
5. [milestone.md](milestone.md) — mulai development sesuai fase
6. [decisions.md](decisions.md) — cek keputusan yang sudah/disepakati sebelum coding

---

## Kontak & Approval

Sebelum memulai coding (OpRec-M1), pastikan sign-off pada:

- **Product Owner** — scope MVP, user journey, business rules
- **Project Manager** — timeline milestone, resource
- **Tech Lead** — architecture, database design, security

Lihat checklist approval di [decisions.md](decisions.md).
