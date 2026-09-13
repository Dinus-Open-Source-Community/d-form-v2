# PRD — DOSCOM OpenRecruitment (OpRec)

**Product Name:** DOSCOM OpenRecruitment  
**Document Type:** Product Requirements Document  
**Version:** 1.0  
**Status:** Draft for Stakeholder Review  
**Target Platform:** Web  
**Primary Integration:** DOSCOM Dynamic Form  
**Target Users:** Applicant, Staff, Interviewer, Admin

---

# 1. Executive Summary

DOSCOM OpenRecruitment (OpRec) adalah sistem berbasis web untuk mengelola proses penerimaan anggota DOSCOM secara terintegrasi, mulai dari pendaftaran applicant, verifikasi dan seleksi berkas, penjadwalan interview, attendance, antrean interview, penilaian interviewer, seleksi akhir, penempatan divisi, hingga penyampaian hasil dan feedback.

Sistem akan menjadi bagian dari website Dynamic Form yang telah tersedia. Halaman utama recruitment akan tersedia melalui:

`/open-recruitment`

Applicant tidak perlu membuat akun atau melakukan login. Setelah mengirimkan pendaftaran, applicant akan menerima nomor pendaftaran dan akses tracking untuk melihat perkembangan proses recruitment.

Untuk internal organisasi, sistem menyediakan dashboard dan workflow khusus untuk Staff, Interviewer, dan Admin.

Tujuan utama sistem adalah menggantikan proses recruitment yang tersebar dan manual menjadi workflow terstruktur, transparan, terdokumentasi, dan dapat digunakan kembali pada setiap periode OpenRecruitment.

---

# 2. Background

Proses OpenRecruitment membutuhkan beberapa tahapan yang saling berhubungan:

1. Applicant mengisi data pendaftaran.
2. Applicant mengunggah dokumen.
3. Staff melakukan verifikasi dan screening.
4. Applicant yang lolos mendapatkan informasi interview.
5. Applicant hadir dan melakukan attendance.
6. Sistem menentukan nomor antrean interview berdasarkan urutan kehadiran.
7. Interviewer melakukan interview dan memberikan assessment.
8. Staff menentukan keputusan akhir.
9. Applicant mendapatkan hasil recruitment.
10. Applicant mengisi feedback terhadap proses OpenRecruitment.

Tanpa sistem terintegrasi, proses tersebut berpotensi menyebabkan:

- Data applicant tersebar.
- Rekap data manual.
- Sulit mengetahui posisi setiap applicant pada proses recruitment.
- Komunikasi dengan applicant dilakukan secara manual.
- Pengelolaan jadwal interview lebih sulit.
- Penilaian interviewer tidak terdokumentasi secara konsisten.
- Sulit melakukan audit keputusan recruitment.
- Sulit membuat laporan dan evaluasi untuk periode berikutnya.

OpRec dibangun untuk menjadikan seluruh proses tersebut berada dalam satu workflow yang terstruktur.

---

# 3. Problem Statement

## 3.1 Primary Problem

DOSCOM membutuhkan sistem yang dapat mengelola proses OpenRecruitment secara end-to-end tanpa mengharuskan applicant memiliki akun atau login.

## 3.2 Supporting Problems

1. Applicant membutuhkan cara sederhana untuk mendaftar.
2. Applicant membutuhkan informasi yang jelas mengenai progress pendaftaran.
3. Staff membutuhkan sistem untuk melakukan screening dan final selection.
4. Interviewer membutuhkan interface untuk melakukan assessment.
5. Panitia membutuhkan mekanisme interview attendance dan queue.
6. Sistem membutuhkan automated notification untuk setiap perubahan tahap.
7. Organisasi membutuhkan histori dan data recruitment yang dapat digunakan kembali.

---

# 4. Product Vision

> Menjadikan DOSCOM OpenRecruitment sebagai sistem recruitment terintegrasi yang memberikan pengalaman pendaftaran yang mudah bagi applicant serta proses seleksi yang terstruktur, transparan, dan terdokumentasi bagi organisasi.

---

# 5. Product Goals

## 5.1 Business Goals

- Memusatkan seluruh data recruitment dalam satu sistem.
- Mengurangi pekerjaan administrasi dan rekap manual.
- Menstandarkan proses screening dan interview.
- Mempermudah monitoring recruitment secara real-time.
- Meningkatkan transparansi status applicant.
- Menyediakan histori recruitment yang dapat digunakan untuk evaluasi.

## 5.2 Applicant Goals

Applicant dapat:

- Mendaftar dengan mudah.
- Mengetahui apakah pendaftaran berhasil.
- Mengetahui progress recruitment.
- Mendapatkan informasi interview.
- Melakukan attendance.
- Mendapatkan hasil recruitment.
- Memberikan feedback.

## 5.3 Internal Goals

Staff dapat:

- Melakukan screening.
- Meminta applicant memperbaiki data.
- Mengelola interview.
- Melakukan final selection.
- Menentukan membership type.
- Menentukan final division.

Interviewer dapat:

- Melihat interview yang menjadi tanggung jawabnya.
- Melihat applicant yang akan diinterview.
- Melakukan assessment.
- Melihat hasil final applicant.

Admin dapat:

- Mengelola konfigurasi sistem.
- Mengelola pengguna internal.
- Mengelola periode recruitment.
- Mengelola konfigurasi OpenRecruitment.

---

# 6. Success Metrics

Keberhasilan OpRec diukur melalui beberapa indikator:

| Metric | Target |
|---|---|
| Seluruh applicant terdata dalam satu sistem | 100% |
| Applicant dapat mengetahui status recruitment tanpa menghubungi panitia | 100% |
| Screening dilakukan melalui sistem | 100% |
| Assessment interviewer terdokumentasi | 100% |
| Attendance interview tercatat melalui sistem | 100% |
| Notifikasi perubahan tahap terkirim otomatis | >95% |
| Data final selection tersimpan dalam sistem | 100% |
| Feedback applicant terkumpul | Diukur per periode |
| Tidak ada duplicate application dalam satu periode | 0 |

Target numerik selain constraint utama dapat disesuaikan setelah periode OpRec pertama.

---

# 7. Target User

## 7.1 Applicant

Calon anggota DOSCOM yang ingin mengikuti OpenRecruitment.

Kebutuhan utama:

- Form pendaftaran.
- Upload dokumen.
- Tracking application.
- Informasi interview.
- Attendance.
- Hasil recruitment.
- Feedback.

## 7.2 Staff

Panitia yang melakukan pengelolaan applicant dan keputusan recruitment.

Kebutuhan utama:

- Applicant management.
- Screening.
- Revision handling.
- Interview management.
- Final selection.
- Placement.

## 7.3 Interviewer

Anggota yang bertugas melakukan interview.

Kebutuhan utama:

- Daftar applicant yang harus diinterview.
- Queue interview.
- Applicant information.
- Interview evaluation.
- Interview notes.
- Access terhadap hasil final.

## 7.4 Admin

Pengelola sistem.

Kebutuhan utama:

- User management.
- Role/permission.
- Recruitment period management.
- Division management.
- Interview configuration.
- Email configuration.
- System monitoring.

---

# 8. Recruitment Scope

## 8.1 Division

OpenRecruitment membuka empat divisi:

1. Pemrograman / Programming
2. Creative Media / Medcrev
3. Jaringan / Network
4. Data

## 8.2 Eligibility

Applicant hanya dapat mendaftar apabila:

- Merupakan mahasiswa yang memenuhi ketentuan DOSCOM.
- Semester berada pada rentang 1–3.
- Belum melakukan pendaftaran pada periode OpRec yang sama.

## 8.3 Application Limit

Satu applicant hanya dapat memiliki satu application pada satu Recruitment Period.

NIM menjadi salah satu identifier utama untuk mencegah duplicate application.

---

# 9. Application Data

## 9.1 Personal Information

| Field | Required |
|---|---|
| Full Name | Yes |
| Student ID / NIM | Yes |
| Semester | Yes |
| Phone Number | Yes |
| Personal Email | Yes |
| Student Email | Yes |
| Instagram Username | Yes |

### Validation

- NIM wajib unik dalam satu periode recruitment.
- Semester hanya menerima nilai 1–3.
- Email harus valid.
- Nomor telepon harus menggunakan format valid.
- Instagram username tidak perlu menyimpan URL penuh jika belum diperlukan.

---

# 10. Division Preference

## 10.1 Primary Division

Required.

Pilihan:

- Programming
- Creative Media
- Network
- Data

## 10.2 Secondary Division

Optional.

Secondary division tidak boleh sama dengan Primary Division.

Contoh valid:

> Primary: Programming  
> Secondary: Data

Contoh invalid:

> Primary: Programming  
> Secondary: Programming

---

# 11. Document Requirement

## 11.1 CV

- Required.
- Format PDF.
- Sistem harus melakukan validasi file type.
- Sistem harus membatasi ukuran file.
- File tidak boleh dapat diakses publik tanpa authorization.

## 11.2 Portfolio

Applicant dapat memberikan:

- URL portfolio; atau
- File portfolio PDF.

Sistem harus mengetahui jenis portfolio yang diberikan.

---

# 12. Application Flow

```text
/open-recruitment
       ↓
Landing Page
       ↓
Daftar Sekarang
       ↓
Application Form
       ↓
Submit
       ↓
Generate Registration Number
       ↓
Send Confirmation Email
       ↓
Screening
```

Setelah screening:

```text
Screening
   │
   ├── Revision Required
   │       ↓
   │   Applicant memperbaiki data
   │       ↓
   │   Screening kembali
   │
   ├── Rejected
   │
   └── Passed
           ↓
    Interview Scheduling
           ↓
    Interview Reminder
           ↓
       Attendance
           ↓
         Queue
           ↓
       Interview
           ↓
    Final Selection
           ↓
  Accepted / Rejected
           ↓
      Feedback
```

---

# 13. Application Lifecycle

Untuk menghindari satu status yang terlalu kompleks, sistem menggunakan konsep **application stage** dan status pendukung.

## 13.1 Application Stage

```text
Submitted
Screening
Interview
Final Review
Completed
```

## 13.2 Application Result

```text
Pending
Accepted
Rejected
```

## 13.3 Interview Status

```text
Not Scheduled
Scheduled
Checked In
Queued
Called
In Progress
Completed
No Show
```

## 13.4 Membership Type

Hanya tersedia apabila applicant diterima:

```text
AA
Member
```

## 13.5 Final Division

Division akhir dapat:

- Primary Division
- Secondary Division
- Division lain

Final Division tidak wajib mengikuti preferensi applicant karena kebutuhan organisasi dapat menyebabkan applicant ditempatkan pada divisi lain.

---

# 14. Applicant Tracking

Applicant tidak menggunakan akun.

Setelah submit, sistem memberikan:

- Registration Number
- Tracking URL / Tracking Credential

Contoh:

```text
Registration Number:
OPREC-2026-00123
```

Applicant dapat membuka halaman tracking untuk mengetahui:

- Status application.
- Progress stage.
- Interview schedule.
- Attendance status.
- Final result.
- Instruction berikutnya.

## 14.1 Security

Registration Number saja tidak boleh memberikan akses ke data sensitif.

Applicant hanya dapat melihat informasi yang relevan untuk dirinya sendiri.

Data berikut tidak boleh ditampilkan pada public tracking:

- Interviewer notes.
- Internal screening notes.
- Internal scoring detail.
- Internal staff activity.
- Data applicant lain.

---

# 15. Application Editing

Applicant dapat melakukan perubahan data **selama application belum selesai diverifikasi oleh petugas**.

Setelah application diverifikasi:

- Form menjadi read-only.
- Applicant tidak dapat mengubah data secara langsung.
- Applicant tetap dapat mengajukan permintaan koreksi.

## 15.1 Correction Request

Applicant dapat meminta koreksi data setelah data terkunci.

Alur:

```text
Applicant
   ↓
Request Correction
   ↓
Staff Review
   ↓
Approved / Rejected
```

Apabila approved:

```text
Applicant Edit
       ↓
Submit Correction
       ↓
Staff Re-verify
```

Seluruh perubahan harus tercatat dalam activity/history log.

---

# 16. Screening

Screening dilakukan oleh Staff.

Tujuan screening adalah memastikan data dan berkas applicant valid dan applicant dapat melanjutkan ke interview.

## 16.1 Screening Actions

Staff dapat memilih:

### Pass

Applicant lolos ke tahap interview.

### Revision Required

Applicant perlu memperbaiki data atau dokumen.

### Reject

Applicant tidak dapat melanjutkan recruitment.

## 16.2 Screening Reason

Untuk Revision Required dan Reject, Staff wajib memberikan alasan.

Reason dapat berupa:

- Data tidak lengkap.
- Data tidak valid.
- Dokumen tidak sesuai.
- Dokumen tidak dapat dibaca.
- Informasi tidak sesuai.
- Persyaratan tidak terpenuhi.
- Other.

Staff dapat menambahkan notes tambahan.

---

# 17. Interview Scheduling

Applicant yang lolos screening akan mendapatkan interview schedule.

Schedule minimal memiliki:

- Date.
- Start time.
- End time / estimated duration.
- Location.
- Room.
- Division.
- Assigned interviewer.

Interviewer ditentukan berdasarkan division.

Contoh:

```text
Programming applicant
        ↓
Programming interviewer
```

Satu applicant hanya memiliki satu interviewer pada satu interview.

---

# 18. Interview Reminder

Sistem mengirimkan reminder otomatis:

### H-1

Berisi:

- Tanggal.
- Waktu.
- Lokasi.
- Ruangan.
- Instruksi.
- Registration Number.

### H-2 Jam

Berisi reminder singkat:

- Waktu interview.
- Lokasi.
- Ruangan.
- Instruksi attendance.

Sistem harus mencegah email reminder terkirim dua kali untuk event yang sama.

---

# 19. Interview Attendance

Applicant wajib melakukan attendance sebelum mendapatkan queue number.

Metode attendance:

- QR Code; atau
- Registration Number.

## 19.1 Attendance Flow

```text
Applicant datang
       ↓
Scan QR / Input Registration Number
       ↓
System validates applicant
       ↓
Attendance Recorded
       ↓
Queue Number Generated
```

## 19.2 Late Applicant

Applicant yang datang terlambat tetap diperbolehkan interview.

Namun:

> Applicant yang terlambat masuk ke antrean paling belakang pada sesi interview yang sedang berjalan.

---

# 20. Interview Queue

Sistem menggunakan **First Come, First Served**.

Queue number diberikan berdasarkan urutan applicant melakukan attendance.

Contoh:

```text
09:01 → Applicant A → Queue #01
09:04 → Applicant B → Queue #02
09:07 → Applicant C → Queue #03
```

Applicant yang belum melakukan attendance tidak mendapatkan queue number.

## 20.1 Queue Status

```text
Waiting
Called
In Progress
Completed
```

Staff/Interviewer dapat mengetahui:

- Current queue.
- Next queue.
- Waiting applicants.
- Completed applicants.

---

# 21. Interview Assessment

Interview dilakukan oleh satu interviewer.

Interviewer memberikan penilaian terhadap tiga aspek:

| Aspect | Range |
|---|---|
| Speaking | 1–10 |
| Technical | 1–10 |
| Attitude | 1–10 |

Nilai tidak menggunakan weighted score.

Nilai hanya digunakan sebagai **assessment information** untuk membantu proses final selection.

## 21.1 Interview Result

Interviewer memberikan recommendation:

```text
Accepted / Recommended
Rejected / Not Recommended
```

Terminologi internal dapat menggunakan istilah **Recommendation**, sedangkan keputusan final tetap dilakukan oleh Staff.

## 21.2 Notes

Optional.

Interviewer dapat mencatat:

- Observasi applicant.
- Hal yang perlu diperhatikan.
- Kelebihan.
- Kekurangan.
- Catatan tambahan.

---

# 22. Interview Evaluation Editing

Interviewer dapat mengubah assessment sebelum interview berpindah ke interview berikutnya / interview session berikutnya sesuai mekanisme sistem.

Setelah evaluation dikunci oleh sistem:

- Evaluation tidak dapat diubah oleh interviewer biasa.
- Perubahan setelah locking hanya dapat dilakukan oleh Staff/Admin dengan activity log.

---

# 23. Interviewer Access

Interviewer hanya dapat melihat data applicant yang diperlukan untuk proses interview.

Minimal:

- Name.
- NIM.
- Semester.
- Division preference.
- CV.
- Portfolio.
- Interview schedule.
- Queue status.

Interviewer dapat:

- Membuka applicant.
- Melihat queue.
- Mengisi assessment.
- Mengedit assessment sebelum locked.
- Melihat final result.

Interviewer tidak boleh mengubah:

- Screening decision.
- Final decision.
- Membership type.
- Final division.

---

# 24. Final Selection

Final selection dilakukan oleh Staff setelah interview selesai.

Staff menentukan:

```text
Accepted - AA
Accepted - Member
Rejected
```

## 24.1 Rejection Reason

Jika applicant ditolak setelah interview, Staff wajib memberikan alasan.

Reason dapat berupa:

- Tidak memenuhi kriteria recruitment.
- Hasil interview.
- Tidak sesuai kebutuhan organisasi.
- Attendance/interview issue.
- Other.

Reason internal dapat berbeda dengan alasan yang ditampilkan kepada applicant.

---

# 25. Final Placement

Applicant yang diterima harus memiliki:

- Membership Type
- Final Division

Membership Type:

```text
AA
Member
```

Final Division:

```text
Programming
Creative Media
Network
Data
Other available division
```

Staff dapat menempatkan applicant ke:

- Primary Division.
- Secondary Division.
- Division lain.

Hal ini memungkinkan keputusan final mempertimbangkan kebutuhan organisasi dan hasil assessment.

---

# 26. Final Result Communication

Setelah Staff menetapkan hasil final:

### Accepted

Applicant mendapatkan email yang berisi:

- Status diterima.
- Membership type.
- Final division.
- Informasi lanjutan yang relevan.

### Rejected

Applicant mendapatkan email yang berisi:

- Status tidak diterima.
- Informasi/alasan yang telah ditentukan Staff.
- Pesan penutup dari DOSCOM.

Internal notes tidak boleh dikirim secara mentah kepada applicant.

---

# 27. Applicant Feedback

Feedback tersedia setelah recruitment selesai.

Applicant dapat memberikan:

### Rating

Contoh:

- Kemudahan pendaftaran.
- Kejelasan informasi.
- Kemudahan tracking.
- Pengalaman interview.
- Pelayanan panitia.

Skala dapat menggunakan 1–5.

### Text Feedback

> Apa yang menurut kamu perlu diperbaiki pada program OpenRecruitment DOSCOM?

Feedback digunakan sebagai bahan evaluasi periode berikutnya.

---

# 28. Email Notification Matrix

| Event | Recipient | Email |
|---|---|---|
| Application Submitted | Applicant | Registration confirmation |
| Revision Required | Applicant | Correction request |
| Passed Screening | Applicant | Passed screening |
| Interview Scheduled | Applicant | Interview schedule |
| H-1 Interview | Applicant | Interview reminder |
| H-2 Hour Interview | Applicant | Interview reminder |
| Final Accepted | Applicant | Acceptance announcement |
| Final Rejected | Applicant | Rejection announcement |
| Correction Request Received | Staff | Correction notification |
| Interview Assignment | Interviewer | Interview assignment |

Email harus menggunakan template yang dapat dikonfigurasi oleh Admin.

---

# 29. Staff Dashboard

Dashboard Staff minimal menampilkan:

```text
Total Applicants
Pending Screening
Revision Required
Passed Screening
Interview Scheduled
Interviewed
Final Review
Accepted
Rejected
```

Dashboard juga dapat menampilkan:

- Applicant per division.
- Interview hari ini.
- Interview in progress.
- Waiting queue.
- No-show applicant.
- Recruitment conversion.

---

# 30. Interviewer Dashboard

Dashboard Interviewer minimal:

```text
Today's Interview
Waiting Queue
Current Applicant
Completed Interviews
```

Interviewer dapat langsung membuka applicant yang menjadi tanggung jawabnya.

---

# 31. Admin Dashboard & Management

Admin dapat mengelola:

## Recruitment Period

Contoh:

```text
OpRec 2026
Status: Active
Start Date
End Date
```

Satu periode mempunyai:

- Registration window.
- Interview period.
- Finalization period.

## Division

Admin dapat mengelola:

- Division name.
- Description.
- Active/inactive state.
- Interviewer assignment.

## Internal User

Admin dapat mengelola:

- User.
- Role.
- Active/inactive state.
- Division assignment untuk Interviewer.

## Email Template

Admin dapat mengelola:

- Subject.
- Body.
- Available variables.
- Active/inactive.

---

# 32. Recruitment Period

OpRec harus dirancang sebagai sistem multi-period.

Contoh:

```text
OpRec 2026
OpRec 2027
OpRec 2028
```

Data antar periode tidak boleh bercampur.

Setiap applicant terhubung dengan satu Recruitment Period.

Recruitment Period memiliki:

```text
Draft
Open
Closed
Archived
```

### Open

Applicant dapat mendaftar.

### Closed

Pendaftaran baru tidak dapat dilakukan.

### Archived

Periode selesai dan hanya dapat digunakan untuk viewing/reporting.

---

# 33. Authorization

## Applicant

Public access terbatas pada:

- Landing page.
- Application form.
- Tracking.
- Correction request.
- Feedback.

## Staff

Dapat:

- View applicants.
- Screening.
- Revision.
- Interview management.
- Final selection.
- Placement.

## Interviewer

Dapat:

- View assigned interviews.
- View necessary applicant information.
- Attendance/queue terkait interview.
- Submit evaluation.
- View final result.

## Admin

Memiliki system-level management access.

---

# 34. Audit Trail

Sistem wajib menyimpan activity log untuk aktivitas penting.

Contoh:

```text
Staff A
Screening decision
Pending → Passed
Timestamp

Staff B
Final decision
Pending → Accepted - AA
Timestamp

Interviewer C
Updated interview evaluation
Timestamp
```

Activity log minimal menyimpan:

- Actor.
- Action.
- Entity.
- Previous value.
- New value.
- Timestamp.

Untuk data sensitif, informasi yang dicatat harus mempertimbangkan privacy dan security.

---

# 35. Functional Requirements

## FR-01 — Recruitment Landing Page

System shall provide `/open-recruitment`.

Acceptance Criteria:

- Applicant dapat melihat informasi program.
- Applicant dapat melihat persyaratan.
- Applicant dapat melihat timeline.
- Applicant dapat menemukan CTA daftar.
- CTA hanya aktif ketika recruitment period Open.

---

## FR-02 — Application Form

System shall provide recruitment application form.

Acceptance Criteria:

- Semua required field divalidasi.
- Applicant hanya dapat submit sekali dalam satu periode.
- NIM duplicate ditolak.
- Semester selain 1–3 ditolak.
- Division secondary tidak boleh sama dengan primary.
- CV harus PDF.
- Portfolio dapat berupa URL atau PDF.

---

## FR-03 — Application Submission

System shall generate unique registration number setelah submission berhasil.

Acceptance Criteria:

- Registration number unik.
- Applicant melihat confirmation screen.
- Confirmation email dikirim.
- Applicant dapat melakukan tracking.

---

## FR-04 — Screening

System shall allow Staff to review submitted application.

Acceptance Criteria:

- Staff dapat melihat seluruh data yang diperlukan.
- Staff dapat Pass.
- Staff dapat Revision Required.
- Staff dapat Reject.
- Revision/Reject membutuhkan reason.
- Semua decision tercatat dalam history.

---

## FR-05 — Correction

Applicant shall be able to request correction.

Acceptance Criteria:

- Applicant dapat membuat correction request.
- Staff dapat approve/reject.
- Approved request memungkinkan applicant memperbaiki data.
- Semua perubahan tercatat.

---

## FR-06 — Interview Scheduling

System shall support interview scheduling.

Acceptance Criteria:

- Applicant dapat memiliki interview date/time.
- Applicant memiliki location dan room.
- Interviewer ditentukan berdasarkan division.
- Applicant menerima email schedule.

---

## FR-07 — Reminder

System shall automatically send H-1 and H-2 hour reminders.

Acceptance Criteria:

- Reminder hanya dikirim untuk scheduled interview.
- Reminder tidak dikirim untuk cancelled/rescheduled event yang sudah tidak relevan.
- Duplicate notification dicegah.

---

## FR-08 — Attendance

System shall record interview attendance.

Acceptance Criteria:

- Applicant dapat check-in menggunakan QR atau registration number.
- Attendance timestamp dicatat.
- Applicant hanya dapat check-in sesuai recruitment interview rules.
- Duplicate check-in dicegah.

---

## FR-09 — Queue

System shall generate queue number after attendance.

Acceptance Criteria:

- Queue menggunakan first-come-first-served.
- Applicant tanpa attendance tidak mempunyai queue.
- Late applicant masuk ke queue terakhir.
- Queue dapat dimonitor oleh internal user.

---

## FR-10 — Interview Evaluation

System shall allow assigned interviewer to evaluate applicant.

Acceptance Criteria:

- Speaking 1–10.
- Technical 1–10.
- Attitude 1–10.
- Recommendation tersedia.
- Notes optional.
- Interviewer hanya dapat mengevaluasi applicant yang menjadi assignment-nya.

---

## FR-11 — Final Selection

System shall allow Staff to determine final applicant result.

Acceptance Criteria:

- Accepted AA.
- Accepted Member.
- Rejected.
- Rejection reason wajib.
- Final division wajib untuk accepted applicant.

---

## FR-12 — Notification

System shall notify applicant when major process stages change.

Acceptance Criteria:

- Email sesuai event terkirim.
- Template configurable.
- Delivery status dapat dicatat.

---

## FR-13 — Tracking

System shall provide applicant tracking.

Acceptance Criteria:

- Applicant dapat melihat current stage.
- Applicant dapat melihat relevant timeline.
- Interview schedule dapat ditampilkan.
- Final result dapat ditampilkan.
- Informasi internal tidak ditampilkan.

---

## FR-14 — Feedback

System shall provide post-recruitment feedback.

Acceptance Criteria:

- Applicant dapat memberikan rating.
- Applicant dapat memberikan text feedback.
- Feedback terkait dengan recruitment period.
- Feedback tidak dapat dimanipulasi applicant lain.

---

# 36. Non-Functional Requirements

## NFR-01 — Security

- Authentication internal harus diterapkan.
- RBAC harus diterapkan.
- File CV/portfolio tidak boleh public tanpa authorization.
- Applicant tracking tidak boleh membocorkan data applicant lain.
- Sensitive activity harus tercatat.
- Form harus menerapkan validation dan sanitization.
- Upload harus memiliki MIME/type/size validation.
- Proteksi terhadap common web vulnerabilities wajib diterapkan.

## NFR-02 — Performance

Target awal:

- Halaman public responsif.
- Dashboard menggunakan pagination.
- Applicant list tidak memuat seluruh data sekaligus.
- File tidak disimpan sebagai binary langsung di database.
- Proses email dapat dijalankan asynchronous menggunakan queue.

## NFR-03 — Availability

System diharapkan tersedia sepanjang periode recruitment, dengan perhatian khusus pada:

- Hari pembukaan pendaftaran.
- Deadline pendaftaran.
- Hari interview.

## NFR-04 — Scalability

System harus dapat digunakan kembali untuk recruitment period berikutnya tanpa perlu membuat aplikasi baru.

## NFR-05 — Maintainability

- Code mengikuti coding standard project utama.
- Business logic recruitment dipisahkan dari generic Dynamic Form logic.
- Feature dapat diuji secara terpisah.
- Database relationship terdokumentasi.

---

# 37. Integration with Dynamic Form

OpRec menggunakan Dynamic Form sebagai bagian dari platform yang sudah ada.

## Responsibilities Dynamic Form

- Form rendering.
- Generic field system.
- Form submission infrastructure.
- Generic validation.
- Generic form storage apabila relevan.

## Responsibilities OpRec

- Recruitment Period.
- Applicant lifecycle.
- Screening.
- Interview.
- Attendance.
- Queue.
- Evaluation.
- Final selection.
- Placement.
- Notification.
- Tracking.

Prinsip utama:

> Dynamic Form adalah generic form engine, sedangkan OpRec adalah business domain recruitment.

Dengan pemisahan tersebut, fitur OpRec tidak mengubah Dynamic Form menjadi domain-specific recruitment system.

---

# 38. Recommended Domain Model

Secara konseptual, entity utama:

```text
RecruitmentPeriod
    │
    ├── Applications
    │      │
    │      ├── Applicant
    │      ├── Documents
    │      ├── DivisionPreferences
    │      ├── Screening
    │      ├── InterviewAssignment
    │      ├── FinalDecision
    │      └── Feedback
    │
    ├── Divisions
    │
    ├── InterviewSessions
    │      │
    │      └── InterviewAssignments
    │
    ├── Notifications
    │
    └── ActivityLogs
```

Entity detail final akan ditentukan pada TRD dan database design.

---

# 39. Recommended Status Transition

```text
SUBMITTED
    │
    ▼
SCREENING
    │
    ├── REVISION_REQUIRED
    │        │
    │        └──────────→ SCREENING
    │
    ├── REJECTED
    │
    └── PASSED
             │
             ▼
       INTERVIEW_SCHEDULED
             │
             ▼
       WAITING_ATTENDANCE
             │
             ▼
           QUEUED
             │
             ▼
         INTERVIEWING
             │
             ▼
         INTERVIEWED
             │
             ▼
         FINAL_REVIEW
             │
        ┌────┴─────┐
        ▼          ▼
    ACCEPTED     REJECTED
```

Untuk accepted applicant:

```text
ACCEPTED
   ├── Membership Type: AA / Member
   └── Final Division
```

---

# 40. Edge Cases

System harus menangani minimal kondisi berikut:

### Duplicate Application

Applicant mencoba mendaftar dua kali menggunakan NIM yang sama.

Expected:

> Application ditolak.

### Recruitment Closed

Applicant membuka form setelah deadline.

Expected:

> Form tidak dapat dikirim.

### Applicant Cancels Application

Applicant memilih cancel.

Expected:

- Application berubah menjadi Cancelled.
- Applicant tidak dapat melanjutkan recruitment.
- Action dicatat dalam activity log.

### Applicant Misses Interview

Applicant tidak melakukan attendance.

Expected:

- Status menjadi No Show atau sesuai rule yang ditetapkan.
- Staff dapat melakukan reschedule bila diperlukan.

### Late Arrival

Applicant melakukan attendance setelah interview session berjalan.

Expected:

> Applicant mendapatkan posisi terakhir dalam queue aktif.

### Interviewer Tidak Tersedia

System harus memungkinkan Staff/Admin mengubah interviewer assignment.

### Applicant Mengajukan Correction

Correction harus melalui approval Staff apabila application sudah terkunci.

### Applicant Rejected

Applicant tidak dapat kembali ke tahap interview tanpa keputusan resmi Staff untuk membuka kembali application.

---

# 41. Reporting

System minimal menyediakan:

## Applicant Report

- Total applicant.
- Applicant per division.
- Applicant per semester.
- Applicant per status.
- Applicant per period.

## Recruitment Funnel

```text
Total Application
       ↓
Passed Screening
       ↓
Scheduled Interview
       ↓
Interviewed
       ↓
Accepted
       ↓
AA / Member
```

## Interview Report

- Interview attendance.
- No-show.
- Average Speaking.
- Average Technical.
- Average Attitude.
- Interview recommendation.
- Result per interviewer.
- Result per division.

Nilai statistik digunakan sebagai informasi evaluasi dan tidak otomatis menentukan acceptance.

---

# 42. Success/Fairness Considerations

Untuk menjaga konsistensi recruitment:

1. Rubric Speaking, Technical, dan Attitude harus terdokumentasi.
2. Interviewer menggunakan kriteria yang sama untuk aspek yang sama.
3. Interviewer tidak menentukan final result.
4. Final decision dicatat bersama actor dan timestamp.
5. Perubahan data penting tercatat di activity log.
6. Internal assessment tidak diberikan kepada applicant kecuali memang menjadi kebijakan organisasi.
7. Applicant memperoleh informasi status secara konsisten melalui tracking dan email.

---

# 43. Out of Scope — MVP

Fitur berikut tidak termasuk MVP:

- Applicant account/login.
- Mobile native application.
- AI automatic interview scoring.
- AI automatic applicant rejection.
- Automatic division recommendation berbasis AI.
- Onboarding management.
- Payroll/HRIS integration.
- WhatsApp recruitment automation.
- Advanced psychometric testing.
- Video interview system.
- Payment system.
- Capacity-based automatic division allocation.

Fitur tersebut dapat dipertimbangkan pada fase selanjutnya.

---

# 44. Proposed MVP

MVP harus mampu menjalankan seluruh workflow utama:

```text
Landing Page
      ↓
Application
      ↓
Confirmation
      ↓
Screening
      ↓
Revision / Pass / Reject
      ↓
Interview Scheduling
      ↓
Reminder
      ↓
Attendance
      ↓
Queue
      ↓
Interview
      ↓
Evaluation
      ↓
Final Selection
      ↓
AA / Member / Reject
      ↓
Tracking
      ↓
Feedback
```

Dengan kata lain:

> MVP bukan hanya "form pendaftaran", tetapi seluruh recruitment journey sampai final result.

---

# 45. Development Milestones

## Milestone 1 — Requirement & Product Foundation

**Objective:** Menetapkan domain dan workflow OpRec.

### Scope

- Finalisasi PRD.
- Finalisasi business rules.
- Finalisasi status transition.
- Finalisasi roles.
- Finalisasi application flow.

### Smoke Test

- Semua actor dan flow dapat dijelaskan tanpa ambiguity.

### Definition of Done

- PRD approved.
- Open questions resolved.
- Scope MVP disetujui.

---

# Milestone 2 — Recruitment Foundation

**Objective:** Membuat foundation recruitment dan recruitment period.

### Scope

- Recruitment Period.
- Division.
- Applicant.
- Application.
- Registration Number.
- Application validation.
- Dynamic Form integration.
- Landing page.

### Smoke Test

Applicant dapat submit application dan mendapatkan registration number.

### Definition of Done

- Data tersimpan benar.
- Duplicate application dicegah.
- Recruitment deadline berjalan.
- Applicant dapat melihat confirmation.

---

# Milestone 3 — Screening

**Objective:** Staff dapat memproses application.

### Scope

- Applicant dashboard.
- Screening.
- Revision Required.
- Reject.
- Reason.
- Applicant editing.
- Correction request.

### Smoke Test

Application dapat:

```text
Submit
→ Screening
→ Revision
→ Re-submit
→ Pass
```

### Definition of Done

- Workflow screening berjalan.
- History tercatat.
- Notification berjalan.

---

# Milestone 4 — Interview Management

**Objective:** Mengelola scheduling, attendance, dan queue.

### Scope

- Interview session.
- Interview assignment.
- Interviewer assignment by division.
- Schedule.
- QR attendance.
- Registration-number attendance.
- Queue system.
- Late handling.
- Reminder email.

### Smoke Test

```text
Applicant
→ Scheduled
→ Check-in
→ Queue #01
→ Called
```

### Definition of Done

- Queue berjalan FCFS.
- Late applicant masuk queue terakhir.
- Duplicate attendance dicegah.
- Reminder berjalan.

---

# Milestone 5 — Interview Evaluation

**Objective:** Interviewer dapat menjalankan interview dan assessment.

### Scope

- Interviewer dashboard.
- Applicant detail.
- Speaking.
- Technical.
- Attitude.
- Recommendation.
- Notes.
- Evaluation lock.

### Smoke Test

Interviewer dapat menyelesaikan interview applicant dan menyimpan assessment.

### Definition of Done

- Score validation 1–10.
- Assignment validation.
- Evaluation tersimpan.
- Evaluation tidak dapat diedit setelah locked.

---

# Milestone 6 — Final Selection & Placement

**Objective:** Staff dapat menetapkan hasil recruitment.

### Scope

- Final review.
- Accepted AA.
- Accepted Member.
- Rejected.
- Rejection reason.
- Final division.
- Result email.

### Smoke Test

Applicant menghasilkan salah satu:

```text
Accepted - AA
Accepted - Member
Rejected
```

### Definition of Done

- Final decision hanya dapat dilakukan Staff.
- Accepted applicant memiliki membership dan final division.
- Rejected applicant memiliki reason.
- Applicant dapat melihat final result.

---

# Milestone 7 — Tracking, Feedback & Reporting

**Objective:** Menyelesaikan candidate experience dan evaluasi recruitment.

### Scope

- Applicant tracking.
- Timeline.
- Feedback.
- Dashboard.
- Recruitment funnel.
- Interview report.
- Activity log review.

### Smoke Test

Applicant dapat melihat lifecycle lengkap dari registration sampai final result.

### Definition of Done

- Tracking bekerja.
- Feedback tersimpan.
- Report dapat digunakan untuk evaluasi.

---

# Milestone 8 — QA & Production

**Objective:** Menyiapkan production release.

### Scope

- Functional testing.
- Permission testing.
- Security testing.
- Email testing.
- Upload testing.
- Performance testing.
- UAT.

### Smoke Test

Simulasi recruitment penuh menggunakan dummy applicant.

### Definition of Done

- Tidak ada blocker.
- UAT disetujui.
- Backup tersedia.
- Monitoring aktif.
- Production deployment berhasil.

---

# 46. Testing Strategy

Testing minimal mencakup:

## Functional Testing

- Application.
- Screening.
- Correction.
- Scheduling.
- Attendance.
- Queue.
- Interview.
- Final selection.
- Tracking.
- Feedback.

## Permission Testing

Contoh:

> Interviewer mencoba mengubah final decision → harus ditolak.

> Applicant mencoba melihat applicant lain → harus ditolak.

## Validation Testing

- Semester > 3.
- Duplicate NIM.
- Duplicate secondary/primary.
- Invalid file.
- Invalid email.
- Invalid tracking credential.

## Workflow Testing

Simulasikan:

```text
Happy Path
Revision Path
Rejection Path
Late Arrival
No Show
Correction
Cancellation
Reschedule
```

---

# 47. Security Requirements

Karena aplikasi memproses data pribadi mahasiswa dan dokumen CV, aspek security menjadi requirement utama.

Minimal:

- HTTPS.
- Authorization berbasis role.
- Private file storage.
- Signed/private access untuk document.
- CSRF protection.
- Rate limiting pada public tracking/application endpoint.
- Secure session management.
- Input validation.
- Upload restriction.
- Audit log.
- Backup.
- Principle of least privilege.

Applicant tidak boleh mengakses data applicant lainnya hanya dengan mengganti ID/registration number pada URL.

---

# 48. Data Privacy

Data yang disimpan dapat mencakup:

- Nama.
- NIM.
- Email.
- Nomor telepon.
- Instagram.
- CV.
- Portfolio.
- Interview score.
- Interview notes.
- Final decision.

Maka sistem perlu menetapkan:

- Siapa yang boleh melihat masing-masing data.
- Berapa lama data disimpan.
- Kapan data diarsipkan.
- Siapa yang dapat mengekspor data.
- Bagaimana data recruitment periode lama diakses.

Kebijakan retention final ditentukan bersama Product Owner dan organisasi.

---

# 49. Risks & Mitigation

| Risk | Impact | Mitigation |
|---|---|---|
| Applicant sangat banyak | High | Pagination, queue, async email |
| Banyak applicant mengakses deadline | High | Load/performance testing |
| Email gagal terkirim | High | Queue + retry + delivery logging |
| QR attendance error | Medium | Fallback registration number |
| Interviewer berhalangan | Medium | Reassignment |
| Applicant terlambat | Medium | Queue terakhir |
| Data applicant bocor | Very High | Private storage + authorization |
| Requirement berubah saat development | High | Scope freeze per milestone |
| Staff lupa menyelesaikan decision | Medium | Dashboard pending action |
| Applicant salah data | Medium | Revision/correction workflow |

---

# 50. Open Questions / Decisions Required

PRD ini sudah dapat menjadi baseline development, tetapi beberapa keputusan berikut perlu disahkan stakeholder sebelum implementasi final.

## Decision 01 — Verifikasi Berkas

Perlu ditetapkan secara eksplisit:

> Apakah verifikasi berkas dilakukan oleh Staff atau Admin?

Rekomendasi:

> Staff melakukan operational screening; Admin mengelola konfigurasi dan memiliki override privilege.

Ini menjaga Admin tidak menjadi bottleneck recruitment.

## Decision 02 — Cancellation

Perlu ditentukan apakah applicant yang cancel dapat mendaftar kembali pada periode yang sama.

Rekomendasi:

> Tidak dapat submit ulang setelah cancellation kecuali Staff melakukan reopen.

## Decision 03 — Reschedule

Perlu ditentukan siapa yang dapat melakukan reschedule:

- Staff only; atau
- Staff + Admin.

Rekomendasi:

> Staff dapat reschedule.

## Decision 04 — Interview Recommendation

Perlu disepakati apakah wording yang tampil kepada interviewer adalah:

> Recommended / Not Recommended

atau:

> Lulus Interview / Tidak Lulus Interview

Rekomendasi:

> Gunakan **Recommendation**, karena final selection tetap menjadi tanggung jawab Staff.

## Decision 05 — Public Rejection Reason

Perlu ditentukan apakah applicant melihat alasan internal secara detail.

Rekomendasi:

> Staff mengisi internal reason, kemudian sistem menggunakan public rejection message yang lebih aman untuk applicant.

---

# 51. Product Rules Summary

Sebagai ringkasan business rules:

```text
1. Applicant hanya boleh mendaftar sekali per recruitment period.
2. Applicant hanya boleh semester 1–3.
3. Pendaftaran hanya tersedia selama recruitment period Open.
4. Applicant dapat mengedit data sampai application diverifikasi.
5. Setelah verified, perubahan menggunakan correction request.
6. Screening memiliki Pass, Revision Required, dan Reject.
7. Revision/Reject wajib memiliki reason.
8. Interviewer ditentukan berdasarkan division.
9. Satu applicant memiliki satu interviewer.
10. Interview assessment terdiri dari Speaking, Technical, Attitude.
11. Setiap aspek memiliki score 1–10.
12. Tidak ada weighted score.
13. Interviewer memberikan recommendation, bukan final decision.
14. Attendance harus dilakukan sebelum mendapatkan queue.
15. Queue menggunakan First Come, First Served.
16. Applicant terlambat tetap dapat interview tetapi masuk queue terakhir.
17. Staff menentukan final decision.
18. Final result dapat berupa AA, Member, atau Rejected.
19. Accepted applicant wajib memiliki final division.
20. Final division dapat berbeda dari preference applicant.
21. Applicant dapat cancel application.
22. Final rejection wajib memiliki reason.
23. Applicant menerima notification pada major stage transition.
24. Reminder interview dikirim H-1 dan H-2 jam.
25. Applicant dapat melihat progress melalui tracking.
26. Applicant dapat memberikan feedback setelah proses selesai.
27. Data tiap Recruitment Period harus terisolasi.
28. Aktivitas penting harus dicatat dalam audit trail.
```

---

# 52. Definition of Done — Product

OpRec dianggap selesai untuk production apabila:

- [ ] Applicant dapat melakukan pendaftaran.
- [ ] Duplicate registration tidak dapat dilakukan.
- [ ] Deadline bekerja.
- [ ] Screening dapat dilakukan.
- [ ] Revision workflow berjalan.
- [ ] Correction workflow berjalan.
- [ ] Interview schedule dapat dibuat.
- [ ] Interviewer assignment berjalan berdasarkan division.
- [ ] Reminder email berjalan.
- [ ] Attendance berjalan.
- [ ] Queue berjalan.
- [ ] Late applicant ditangani.
- [ ] Interview evaluation berjalan.
- [ ] Final selection berjalan.
- [ ] Membership type tersimpan.
- [ ] Final division tersimpan.
- [ ] Applicant tracking berjalan.
- [ ] Final notification berjalan.
- [ ] Feedback berjalan.
- [ ] Dashboard tersedia.
- [ ] Audit trail tersedia.
- [ ] Role/permission telah diuji.
- [ ] Security testing selesai.
- [ ] UAT disetujui.
- [ ] Production deployment berhasil.

---

# 53. Recommended Stakeholder Approval

Sebelum development dimulai, tiga stakeholder utama memberikan approval terhadap:

## Product Owner

Approve:

- Problem.
- Product goal.
- User journey.
- Scope.
- Business rules.
- Applicant experience.

## Project Manager

Approve:

- Timeline.
- Milestone.
- Resource.
- Risk.
- Release plan.

## Tech Lead

Approve:

- Architecture direction.
- Integration dengan Dynamic Form.
- Data ownership.
- Security.
- Scalability.
- Technical feasibility.

---

# 54. Final Product Definition

DOSCOM OpenRecruitment bukan hanya form pendaftaran.

Produk ini didefinisikan sebagai:

> **Applicant Tracking and Recruitment Management System untuk mengelola seluruh proses OpenRecruitment DOSCOM dari application hingga final selection dan placement.**

Core journey:

```text
┌─────────────────────────────────────────────┐
│                APPLICANT                    │
└─────────────────────────────────────────────┘
                     │
                     ▼
               Application
                     │
                     ▼
              Document Screening
                     │
        ┌────────────┼─────────────┐
        │            │             │
     Revision      Reject         Pass
        │                          │
        └──────→ Re-submit         ▼
                              Interview
                                  │
                                  ▼
                              Attendance
                                  │
                                  ▼
                                Queue
                                  │
                                  ▼
                               Interview
                                  │
                                  ▼
                               Review
                                  │
                     ┌────────────┴────────────┐
                     │                         │
                  Rejected                 Accepted
                                               │
                                  ┌────────────┴────────────┐
                                  ▼                         ▼
                                 AA                      Member
                                  │
                                  ▼
                            Final Division
                                  │
                                  ▼
                               Feedback
```

---

# 55. Conclusion

Dengan scope yang telah ditentukan, OpRec memiliki tiga nilai utama:

### Untuk Applicant

**Simple, transparent, trackable**

Applicant cukup mendaftar tanpa account, menerima registration number, mengikuti progress, menerima informasi interview, dan mendapatkan hasil akhir melalui satu sistem.

### Untuk Staff

**Structured, manageable, auditable**

Staff memiliki workflow screening, correction, interview management, final decision, dan placement tanpa harus mengelola data secara manual melalui banyak tools.

### Untuk DOSCOM

**Reusable recruitment infrastructure**

Sistem tidak dibuat hanya untuk satu event, tetapi menjadi platform yang dapat digunakan kembali untuk:

```text
OpRec 2026
OpRec 2027
OpRec 2028
...
```

dengan data setiap periode tetap terpisah dan dapat digunakan untuk evaluasi recruitment berikutnya.

---

# Appendix A — Suggested MVP Priority

| Feature | Priority |
|---|---|
| Recruitment Landing Page | Must Have |
| Application Form | Must Have |
| Registration Number | Must Have |
| Applicant Tracking | Must Have |
| Screening | Must Have |
| Revision Required | Must Have |
| Correction Request | Should Have |
| Interview Scheduling | Must Have |
| Email Notification | Must Have |
| Interview Reminder | Must Have |
| Attendance | Must Have |
| Queue | Must Have |
| Interview Assessment | Must Have |
| Final Selection | Must Have |
| Membership Type | Must Have |
| Final Division | Must Have |
| Feedback | Should Have |
| Reporting | Should Have |
| Audit Trail | Must Have |
| Advanced Analytics | Could Have |
| AI Assistance | Future |

---

# Appendix B — Recommended Next Documentation

Setelah PRD ini disepakati stakeholder, dokumentasi berikutnya sebaiknya dibuat secara berurutan:

```text
PRD
 │
 ▼
TRD
 │
 ├── Architecture
 ├── Domain Model
 ├── Database / ERD
 ├── Authorization
 ├── Notification Architecture
 ├── Queue Architecture
 └── Dynamic Form Integration
 │
 ▼
DESIGN
 │
 ├── Applicant UI
 ├── Staff Dashboard
 ├── Interviewer Dashboard
 ├── Admin Dashboard
 └── Email Template
 │
 ▼
MILESTONE
 │
 ▼
DEVELOPMENT
 │
 ▼
QA / UAT
 │
 ▼
PRODUCTION
```

**Status dokumen:** Ready for stakeholder review, dengan beberapa *decision points* di bagian 50 yang perlu disahkan sebelum technical design dikunci.

---

## Appendix C — Dokumentasi Teknis & Milestone (tersedia)

Dokumentasi teknis dan milestone development lengkap tersedia di folder [`docs/module/oprec/`](oprec/README.md):

| Dokumen | Path |
|---------|------|
| Index modul | [oprec/README.md](oprec/README.md) |
| Overview produk | [oprec/overview.md](oprec/overview.md) |
| Arsitektur (TRD) | [oprec/architecture.md](oprec/architecture.md) |
| Domain model & state machine | [oprec/domain-model.md](oprec/domain-model.md) |
| Database design / ERD | [oprec/database-design.md](oprec/database-design.md) |
| Authorization & RBAC | [oprec/authorization.md](oprec/authorization.md) |
| Routes & halaman Inertia | [oprec/routes-and-pages.md](oprec/routes-and-pages.md) |
| Notifikasi & email | [oprec/notifications.md](oprec/notifications.md) |
| **Milestone development (M0–M11)** | [oprec/milestone.md](oprec/milestone.md) |
| Testing strategy | [oprec/testing-strategy.md](oprec/testing-strategy.md) |
| Keputusan stakeholder | [oprec/decisions.md](oprec/decisions.md) |

**Relasi D-Form v2:** milestone OpRec terpisah dari [milestone D-Form v2](../../milestone.md); lihat bagian "Modul OpRec" di dokumen tersebut.