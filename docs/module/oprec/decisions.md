# OpRec — Keputusan Stakeholder & Open Questions

**Acuan:** PRD §50  
**Status:** Baseline untuk sign-off sebelum OpRec-M1 (coding)

---

## 1. Cara Menggunakan Dokumen Ini

Setiap keputusan memiliki:

- **ID** — referensi ke PRD
- **Pertanyaan** — yang perlu disepakati
- **Rekomendasi tim** — default untuk memulai development
- **Status** — `proposed` | `approved` | `deferred`
- **Keputusan final** — diisi setelah stakeholder meeting

Tim development **dapat memulai OpRec-M1** dengan rekomendasi default jika keputusan belum di-override, kecuali item marked **BLOCKER**.

---

## 2. Keputusan PRD §50



### Decision 01 — Verifikasi Berkas


|                     |                                                                                                        |
| ------------------- | ------------------------------------------------------------------------------------------------------ |
| **Pertanyaan**      | Apakah verifikasi berkas dilakukan oleh Staff atau Admin?                                              |
| **Rekomendasi**     | **Staff** melakukan operational screening; Admin mengelola konfigurasi dan memiliki override privilege |
| **Alasan**          | Admin tidak menjadi bottleneck; Staff closer to operational flow                                       |
| **Impact teknis**   | Permission `recruitment.screening.`* → Staff role; Admin inherits via `recruitment.`*                  |
| **Status**          | `proposed`                                                                                             |
| **Keputusan final** | *Pending PO sign-off*                                                                                  |


---



### Decision 02 — Cancellation & Re-apply


|                     |                                                                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Apakah applicant yang cancel dapat mendaftar kembali pada periode yang sama?                                                           |
| **Rekomendasi**     | **Tidak dapat** submit ulang setelah cancellation kecuali Staff melakukan **reopen** manual                                            |
| **Alasan**          | Mencegah abuse; NIM slot tetap reserved; Staff control edge cases                                                                      |
| **Impact teknis**   | Unique `(period_id, nim)` tetap; `result = cancelled`; Staff action `reopen` clears cancel + allows re-apply OR manual new application |
| **Status**          | `proposed`                                                                                                                             |
| **Keputusan final** | *Pending PO sign-off*                                                                                                                  |


---



### Decision 03 — Reschedule Interview


|                     |                                                                                                             |
| ------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Siapa yang dapat melakukan reschedule — Staff only atau Staff + Admin?                                      |
| **Rekomendasi**     | **Staff** dapat reschedule (permission `recruitment.interviews.reschedule`); Admin implicit via full access |
| **Alasan**          | Operational agility; Admin tidak perlu involved untuk routine reschedule                                    |
| **Impact teknis**   | Route + policy on Staff role                                                                                |
| **Status**          | `proposed`                                                                                                  |
| **Keputusan final** | *Pending PO sign-off*                                                                                       |


---



### Decision 04 — Interview Recommendation Wording


|                     |                                                                                                          |
| ------------------- | -------------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Wording untuk interviewer: "Recommended / Not Recommended" vs "Lulus Interview / Tidak Lulus Interview"? |
| **Rekomendasi**     | **Recommended / Not Recommended** (enum `recommended`, `not_recommended`)                                |
| **Alasan**          | Final selection tetap tanggung jawab Staff; wording tidak imply final decision                           |
| **Impact teknis**   | UI label + enum values; i18n ID: "Rekomendasi / Tidak Direkomendasikan"                                  |
| **Status**          | `proposed`                                                                                               |
| **Keputusan final** | *Pending PO sign-off*                                                                                    |


---



### Decision 05 — Public Rejection Reason


|                     |                                                                                                                      |
| ------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Apakah applicant melihat alasan internal secara detail?                                                              |
| **Rekomendasi**     | **Terpisah:** Staff isi `internal_reason` (admin only) + `public_message` (applicant-facing, lebih aman)             |
| **Alasan**          | Privacy & fairness; hindari internal assessment mentah ke applicant                                                  |
| **Impact teknis**   | Kolom terpisah di `recruitment_final_decisions` dan screening reject; TrackingPresenter expose `public_message` only |
| **Status**          | `proposed`                                                                                                           |
| **Keputusan final** | *Pending PO sign-off*                                                                                                |


---



## 3. Keputusan Teknis Tambahan



### Decision 06 — Tracking Session Mechanism


|                     |                                                                                                             |
| ------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Setelah validasi reg number + token, bagaimana maintain session applicant?                                  |
| **Rekomendasi**     | **Laravel session** dengan key `oprec_tracking_application_id` + TTL 24 jam; require re-auth setelah expire |
| **Alternatif**      | Signed URL dengan expiry 90 hari (lebih convenient, less secure if URL leaked)                              |
| **Impact teknis**   | Middleware `EnsureTrackingSession`; session regenerate on login                                             |
| **Status**          | `proposed`                                                                                                  |
| **Keputusan final** | *Pending Tech Lead sign-off*                                                                                |


---



### Decision 07 — Registration Number Format


|                     |                                                            |
| ------------------- | ---------------------------------------------------------- |
| **Pertanyaan**      | Format exact registration number?                          |
| **Rekomendasi**     | `OPREC-{YEAR}-{SEQ:5}` — contoh `OPREC-2026-00123`         |
| **Implementasi**    | `recruitment_registration_sequences` table dengan row lock |
| **Status**          | `approved` (sesuai PRD §14)                                |
| **Keputusan final** | Sesuai PRD                                                 |


---



### Decision 08 — Division Scope (Global vs Per Period)


|                     |                                                                                     |
| ------------------- | ----------------------------------------------------------------------------------- |
| **Pertanyaan**      | Apakah divisi tied to period atau global?                                           |
| **Rekomendasi**     | **Global** dengan seed 4 divisi; `is_active` flag; period references division by FK |
| **Alasan**          | Divisi DOSCOM stabil; simplify seeding                                              |
| **Status**          | `proposed`                                                                          |
| **Keputusan final** | *Pending Tech Lead sign-off*                                                        |


---



### Decision 09 — Dynamic Form Integration Depth (MVP)


|                     |                                                                                       |
| ------------------- | ------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Apakah application form menggunakan Form Builder D-Form atau hardcoded fields?        |
| **Rekomendasi**     | **Hardcoded fields** di `recruitment_applications` untuk MVP (PRD §9–11 fixed fields) |
| **Alasan**          | Lifecycle kompleks; validasi spesifik; faster delivery                                |
| **Future**          | Optional link `form_id` on period for extra custom fields post-MVP                    |
| **Status**          | `approved` (sesuai architecture.md)                                                   |
| **Keputusan final** | Hardcoded MVP                                                                         |


---



### Decision 10 — email_logs Extension


|                     |                                                                 |
| ------------------- | --------------------------------------------------------------- |
| **Pertanyaan**      | Bagaimana korelasi email ke recruitment application?            |
| **Rekomendasi**     | Tambah nullable `recruitment_application_id` FK di `email_logs` |
| **Alternatif**      | Polymorphic notifiable                                          |
| **Status**          | `proposed`                                                      |
| **Keputusan final** | *Pending Tech Lead sign-off*                                    |


---



### Decision 11 — Queue Real-time (MVP)


|                     |                                            |
| ------------------- | ------------------------------------------ |
| **Pertanyaan**      | WebSocket vs polling untuk queue board?    |
| **Rekomendasi**     | **Polling 10 detik** untuk MVP             |
| **Future**          | Laravel Echo + Reverb post-MVP jika needed |
| **Status**          | `proposed`                                 |
| **Keputusan final** | *Pending Tech Lead sign-off*               |


---



### Decision 12 — Data Retention


|                     |                                                                                  |
| ------------------- | -------------------------------------------------------------------------------- |
| **Pertanyaan**      | Berapa lama data recruitment disimpan?                                           |
| **Rekomendasi**     | **Deferred** — period `archived` = read-only indefinite; hard delete manual only |
| **Alasan**          | PRD §48: kebijakan retention ditentukan PO + organisasi                          |
| **Status**          | `deferred`                                                                       |
| **Keputusan final** | *Pending PO + organisasi*                                                        |


---



### Decision 13 — NIM Validation Format


|                     |                                                                                               |
| ------------------- | --------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Apakah NIM divalidasi dengan regex kampus spesifik?                                           |
| **Rekomendasi**     | MVP: non-empty string, max 50 chars, unique per period; regex dapat ditambah later via config |
| **Status**          | `proposed`                                                                                    |
| **Keputusan final** | *Pending PO sign-off*                                                                         |


---



### Decision 14 — Spatie Role Naming


|                     |                                                                                                      |
| ------------------- | ---------------------------------------------------------------------------------------------------- |
| **Pertanyaan**      | Role baru atau extend admin?                                                                         |
| **Rekomendasi**     | Roles baru: `recruitment-staff`, `recruitment-interviewer`; Admin existing dapat all `recruitment.`* |
| **Status**          | `proposed`                                                                                           |
| **Keputusan final** | *Pending Tech Lead sign-off*                                                                         |


---



## 4. Sign-off Checklist

Sebelum **OpRec-M1** coding dimulai:

### Product Owner

- [x] Scope MVP (PRD §44) approved
- [x] Business rules §51 reviewed
- [x] Decision 01, 02, 05, 13 resolved
- [x] User journey applicant + staff approved



### Project Manager

- [x] [milestone.md](milestone.md) timeline agreed
- [x] Resource allocation confirmed
- [x] Risk register reviewed (PRD §49)



### Tech Lead

- [x] [architecture.md](architecture.md) approved
- [x] [database-design.md](database-design.md) approved
- [x] [authorization.md](authorization.md) approved
- [x] Decision 06, 08, 09, 10, 11, 14 resolved
- [x] Integration dengan D-Form feasible

---



## 5. Change Log


| Tanggal    | Versi | Perubahan                                        |
| ---------- | ----- | ------------------------------------------------ |
| 2026-09-07 | 1.0   | Initial baseline dari PRD §50 + keputusan teknis |


---



## 6. Escalation

Jika keputusan BLOCKER tidak resolved dalam 5 hari kerja:

1. Tech Lead dokumentasikan opsi A/B dengan trade-off
2. PO pilih opsi via written approval (comment di doc atau issue tracker)
3. Update status ke `approved` + isi **Keputusan final**

**BLOCKER items for M1:** Decision 09 (resolved), Decision 08, Decision 14

**BLOCKER items for M2:** Decision 06, Decision 07 (resolved), Decision 10

**Non-blocker (can default):** Decision 01–05, 11, 13