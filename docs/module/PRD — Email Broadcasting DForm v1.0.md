# PRD — Email Broadcasting DForm v1.0

**Product:** DForm — Dynamic Form
**Module:** Email Broadcasting
**Version:** 1.0
**Status:** MVP Specification
**Target:** DForm Administrator / Superadmin
**Primary Goal:** menyediakan mekanisme pengiriman email massal yang terjadwal, terkontrol, asynchronous, dan dapat dilacak tanpa mengganggu proses utama aplikasi.

---

# 1. Product Overview

## 1.1 Background

DForm saat ini telah menangani berbagai data yang membutuhkan komunikasi melalui email, seperti:

* peserta event,
* applicant recruitment,
* user DForm,
* informasi seleksi,
* pengumuman,
* reminder,
* dan kebutuhan komunikasi lainnya.

Pengiriman email satu per satu atau melalui proses synchronous memiliki beberapa masalah:

* proses request dapat menjadi lambat,
* pengiriman banyak email dapat membebani server,
* tidak tersedia pengelolaan recipient secara terstruktur,
* sulit mengetahui email mana yang berhasil atau gagal,
* sulit melakukan retry,
* tidak ada mekanisme scheduling,
* email dengan banyak recipient berpotensi mengganggu proses aplikasi lainnya.

Karena itu, DForm membutuhkan modul **Email Broadcasting** yang menggunakan queue dan dapat mengelola recipient secara terpisah dari proses utama aplikasi.

---

# 2. Product Goal

Menyediakan fitur yang memungkinkan Superadmin:

1. membuat broadcast email,
2. memilih satu atau beberapa dataset recipient,
3. membuat recipient snapshot,
4. memeriksa dan mengubah recipient,
5. membuat email menggunakan Tiptap,
6. menggunakan personalization sederhana,
7. menambahkan attachment dan inline image,
8. melakukan preview dan test email,
9. menjadwalkan broadcast,
10. mengirim email melalui queue,
11. menggunakan random delay antar email,
12. melakukan automatic retry,
13. melakukan manual retry terhadap email yang gagal,
14. memantau status pengiriman.

---

# 3. Product Principles

Email Broadcasting v1.0 mengikuti prinsip:

### 3.1 Simple First

Fitur hanya menyediakan kebutuhan inti broadcasting.

Tidak menyediakan campaign automation, segmentation kompleks, analytics marketing, maupun fitur marketing automation.

### 3.2 Snapshot-Based

Dataset bukan target pengiriman langsung.

```text
Dataset
   ↓
Snapshot
   ↓
Broadcast
```

Setelah snapshot dibuat dan broadcast dijadwalkan, snapshot menjadi sumber data recipient untuk broadcast tersebut.

### 3.3 Asynchronous

Pengiriman email tidak boleh dilakukan dalam HTTP request utama.

```text
Admin Request
      ↓
Database
      ↓
Queue
      ↓
Email Job
      ↓
Mail Provider
```

### 3.4 Auditable

Setiap recipient memiliki status pengiriman sehingga Superadmin dapat mengetahui hasil broadcast.

---

# 4. Scope MVP

## 4.1 Included

### Broadcast Management

* Create broadcast
* Schedule broadcast
* Edit scheduled time
* Cancel scheduled broadcast
* Cancel processing broadcast
* View broadcast detail
* View broadcast history
* Delete broadcast

### Dataset

Existing:

* Event Participants
* Recruitment Applicants
* Users

Custom:

* Manual recipient entry
* CSV import

### Recipient

* Multiple datasets
* Generate snapshot
* Review snapshot
* Add recipient
* Edit recipient
* Remove recipient
* Duplicate detection
* Duplicate warning
* No automatic deduplication

### Email Composer

* Subject
* Tiptap WYSIWYG
* `{{name}}`
* `{{event_name}}`
* Attachments
* Inline images
* HTML sanitization

### Validation

* Email validation
* Attachment validation
* Image size validation
* Duplicate detection
* Template variable validation

### Testing

* Email preview
* Send test email

### Queue

* Redis
* Laravel Queue
* Per-recipient jobs
* Random delay
* Automatic retry ×3

### Monitoring

* Pending
* Processing
* Sent
* Failed
* Cancelled
* Attempts
* Error message
* Sent timestamp
* Manual Retry Failed

---

# 5. Explicitly Out of Scope

Untuk mencegah overengineering, fitur berikut **tidak termasuk v1.0**:

* Email campaign automation
* Drip campaign
* A/B testing
* Open-rate tracking
* Click-rate tracking
* Unsubscribe management
* Email marketing analytics
* Segmentation/filter builder
* Conditional recipient rules
* Multiple mail providers
* Multiple sender addresses
* Sender selection
* Email template marketplace
* Recurring broadcast
* Email scheduling berdasarkan timezone recipient
* AI-generated email
* Advanced personalization
* Dynamic variables selain `{{name}}` dan `{{event_name}}`
* Webhook analytics
* Bounce-management system khusus
* Email provider failover
* Campaign workflow builder

Ini penting. Kalau tidak dibatasi, modul broadcasting bisa berubah menjadi Mailchimp mini. Untuk DForm v1.0, itu tidak diperlukan.

---

# 6. User Role

## Superadmin

Untuk v1.0, hanya Superadmin yang memiliki permission broadcasting.

```text
Superadmin
├── View Broadcast
├── Create Broadcast
├── Generate Snapshot
├── Edit Snapshot
├── Schedule Broadcast
├── Edit Schedule
├── Cancel Broadcast
├── Send Test Email
├── Retry Failed
└── Delete Broadcast
```

Permission tetap sebaiknya menggunakan permission system DForm, bukan hardcode `role == superadmin`.

Contoh:

```text
email-broadcast.view
email-broadcast.create
email-broadcast.schedule
email-broadcast.cancel
email-broadcast.retry
email-broadcast.delete
```

Namun seluruh permission tersebut untuk v1.0 diberikan hanya kepada Superadmin.

---

# 7. Broadcast Lifecycle

Broadcast memiliki status:

```text
scheduled
processing
completed
cancelled
failed
```

Flow utama:

```text
SCHEDULED
    │
    ├──────────────→ CANCELLED
    │
    ↓
PROCESSING
    │
    ├──────────────→ CANCELLED
    │
    ↓
COMPLETED
```

`failed` digunakan apabila broadcast mengalami kegagalan proses secara keseluruhan, bukan sekadar satu recipient gagal.

Recipient failure ditangani pada level recipient.

---

# 8. Recipient Lifecycle

Recipient memiliki status:

```text
pending
processing
sent
failed
cancelled
```

Flow normal:

```text
pending
   ↓
processing
   ↓
sent
```

Jika gagal:

```text
processing
   ↓
failed
   ↓
retry
   ↓
processing
   ↓
sent / failed
```

Jika broadcast dibatalkan:

```text
pending
   ↓
cancelled
```

Recipient yang sudah `sent` tidak berubah menjadi `cancelled`.

---

# 9. End-to-End User Flow

```text
Create Broadcast
       ↓
Configure Broadcast
       ↓
Select Dataset(s)
       ↓
Generate Snapshot
       ↓
Review Recipients
       ↓
Resolve / Review Duplicate Warning
       ↓
Edit Snapshot if Needed
       ↓
Compose Email
       ↓
Preview
       ↓
Send Test Email
       ↓
Review
       ↓
Schedule
       ↓
Queue
       ↓
Processing
       ↓
Completed
```

---

# 10. Create Broadcast

Superadmin membuka:

```text
Email Broadcasting
        ↓
Create Broadcast
```

Form awal:

### Broadcast Information

| Field          | Required |
| -------------- | -------- |
| Broadcast Name | Yes      |
| Schedule Date  | Yes      |
| Schedule Time  | Yes      |
| Delay Minimum  | Yes      |
| Delay Maximum  | Yes      |

Contoh:

```text
Broadcast Name:
Pengumuman Hasil Open Recruitment

Schedule:
01 October 2026
19:00

Random Delay:
Min: 5 seconds
Max: 15 seconds
```

---

# 11. Dataset Management

## 11.1 Existing Dataset

DForm menyediakan:

```text
Existing Dataset
├── Event Participants
├── Recruitment Applicants
└── Users
```

Superadmin dapat memilih beberapa dataset.

Contoh:

```text
Selected Dataset:

✓ Event Participants
✓ Recruitment Applicants
✓ Users
```

---

# 12. Multiple Dataset

Satu broadcast dapat menggunakan beberapa dataset.

Contoh:

```text
Broadcast
│
├── Event Participants
│
├── Recruitment Applicants
│
└── Custom CSV
```

Semua recipient kemudian dikumpulkan ke snapshot.

---

# 13. Custom Dataset

Custom dataset memiliki dua metode input.

## 13.1 Manual

```text
Name                 Email
-----------------------------------
Nafan                nafan@gmail.com
Budi                 budi@gmail.com
Andi                 andi@gmail.com

[+ Add Recipient]
```

`name` bersifat optional.

`email` wajib.

---

## 13.2 CSV

Format minimum:

```csv
email
andi@gmail.com
budi@gmail.com
```

Format dengan nama:

```csv
name,email
Andi,andi@gmail.com
Budi,budi@gmail.com
```

Rules:

* `email` wajib.
* `name` optional.
* invalid email ditolak.
* baris invalid ditampilkan dalam hasil import.
* recipient valid dapat masuk snapshot.

---

# 14. Generate Recipient Snapshot

Setelah dataset dipilih:

```text
[Generate Snapshot]
```

DForm mengambil recipient dari semua dataset yang dipilih.

Contoh:

```text
Event Participants       100
Recruitment Applicants    50
Users                     20
Custom CSV                30
--------------------------------
Total                    200
```

Kemudian dibuat:

```text
Recipient Snapshot
```

---

# 15. Duplicate Detection

DForm melakukan pengecekan duplicate email.

Contoh:

```text
Total recipients: 200
Unique emails:    187
Duplicates:       13
```

Tampilkan warning:

> 13 duplicate email addresses were detected.

Namun:

**DForm tidak otomatis menghapus duplicate.**

Contoh:

```text
andi@gmail.com
budi@gmail.com
andi@gmail.com
```

tetap menjadi:

```text
andi@gmail.com
budi@gmail.com
andi@gmail.com
```

Jika Superadmin melanjutkan, ketiganya tetap dikirim.

Superadmin dapat menghapus duplicate secara manual melalui snapshot.

---

# 16. Recipient Snapshot Editing

Setelah Generate Snapshot, Superadmin dapat:

### Add

```text
[+ Add Recipient]
```

### Edit

```text
Name
Email
```

### Remove

```text
[Delete]
```

### View

```text
No | Name | Email | Status
```

Sebelum schedule, status seluruh recipient:

```text
pending
```

---

# 17. Snapshot sebagai Source of Truth

Setelah snapshot dibuat:

```text
Dataset
   ↓
Snapshot
```

Perubahan dataset setelahnya **tidak memengaruhi broadcast**.

Contoh:

```text
Dataset
100 users

Generate Snapshot
100 users

Dataset berubah
120 users
```

Broadcast tetap:

```text
100 recipients
```

Jika Superadmin menghapus 5 recipient:

```text
Snapshot
95 recipients
```

Maka hanya 95 tersebut yang diproses.

---

# 18. Event Context

Broadcast memiliki optional:

```text
Event
[ Select Event ]
```

`{{event_name}}` selalu mengambil nilai dari event yang dipilih secara manual.

Contoh:

```text
Event:
Open Recruitment DOSCOM 2026
```

Maka:

```text
{{event_name}}
```

menjadi:

```text
Open Recruitment DOSCOM 2026
```

Event context tidak bergantung pada sumber dataset.

---

# 19. Email Composer

Composer menggunakan **Tiptap**.

Field:

```text
Subject
Email Content
```

Contoh:

```text
Subject:
Pengumuman Hasil Seleksi {{event_name}}
```

Content:

```text
Halo {{name}},

Terima kasih telah mengikuti proses
{{event_name}}.

Silakan membaca pengumuman yang terlampir.

Salam,
DOSCOM
```

---

# 20. Personalization

MVP hanya mendukung:

```text
{{name}}
{{event_name}}
```

## `{{name}}`

Jika recipient mempunyai nama:

```text
Nafan
```

hasil:

```text
Halo Nafan,
```

Jika tidak mempunyai nama:

```text
name = null
```

hasil:

```text
Halo Peserta,
```

---

## `{{event_name}}`

Admin memilih event:

```text
Open Recruitment DOSCOM 2026
```

Maka:

```text
{{event_name}}
```

diganti menjadi:

```text
Open Recruitment DOSCOM 2026
```

---

# 21. Unsupported Variables

Variable selain yang didukung:

```text
{{name}}
{{event_name}}
```

harus ditolak atau ditandai sebelum schedule.

Contoh:

```text
Halo {{name}},

{{division}}
```

`{{division}}` belum didukung.

UI memberikan error:

```text
Unsupported variable:
{{division}}
```

Broadcast tidak dapat dijadwalkan sampai diperbaiki.

---

# 22. Inline Image

Tiptap mendukung inline image.

Untuk v1.0:

```text
Image
 ↓
Validate
 ↓
Convert to Base64
 ↓
Store in email content
```

Maximum:

```text
1.5 MB / image
```

Ukuran yang digunakan adalah ukuran file asli sebelum Base64.

Contoh:

```text
logo.png
Original: 1.2 MB
→ Accepted
```

```text
banner.png
Original: 1.7 MB
→ Rejected
```

---

# 23. Attachments

Broadcast dapat memiliki multiple attachments.

Maximum:

```text
1.5 MB / file
```

Tidak ada total-size limit pada v1.0 berdasarkan keputusan saat ini.

Contoh:

```text
guide.pdf       1.2 MB ✓
schedule.xlsx   1.4 MB ✓
poster.png      1.6 MB ✗
```

Attachment disimpan selama broadcast masih ada.

---

# 24. Preview

Superadmin dapat melihat email sebelum schedule.

Preview harus memperlihatkan:

```text
From:
DOSCOM <no-reply@doscom.org>

Subject:
Pengumuman Open Recruitment

Content:
...

Attachments:
guide.pdf
```

Personalization menggunakan contoh recipient.

Misalnya:

```text
Name:
Nafan

Event:
Open Recruitment DOSCOM 2026
```

Preview:

```text
Halo Nafan,

Terima kasih telah mengikuti
Open Recruitment DOSCOM 2026.
```

---

# 25. Send Test Email

Sebelum schedule, Superadmin dapat:

```text
[Send Test Email]
```

Kemudian memasukkan email tujuan:

```text
Test Email:
admin@example.com

[Send Test]
```

Test email:

* tidak masuk recipient snapshot,
* tidak dihitung sebagai sent broadcast,
* tidak memengaruhi delivery statistics.

---

# 26. Schedule

Setelah seluruh konfigurasi valid:

```text
[Schedule Broadcast]
```

status:

```text
scheduled
```

Contoh:

```text
Schedule:
01 October 2026 19:00
```

Jika waktu schedule sudah tercapai, queue dapat mulai memproses broadcast.

---

# 27. Editing Scheduled Broadcast

Saat status:

```text
scheduled
```

Superadmin hanya dapat mengubah:

```text
Schedule Date
Schedule Time
```

Tidak dapat mengubah:

* recipient,
* dataset,
* snapshot,
* subject,
* content,
* attachment,
* event context,
* delay configuration.

Jika content salah:

```text
Cancel
   ↓
Create new Broadcast
```

Ini menjaga konsistensi data yang sudah masuk queue.

---

# 28. Cancellation

Broadcast dapat dibatalkan ketika:

```text
scheduled
processing
```

### Scheduled

```text
scheduled
    ↓
cancel
    ↓
cancelled
```

Tidak ada email yang dikirim.

### Processing

Misalnya:

```text
Total       100
Sent         70
Pending      30
```

Cancel:

```text
Sent         70
Cancelled    30
```

Recipient yang sudah berhasil dikirim tidak dibatalkan.

---

# 29. Random Delay

Broadcast memiliki:

```text
delay_min
delay_max
```

Contoh:

```text
Min: 5 sec
Max: 15 sec
```

Setiap recipient mendapatkan random delay.

```text
Recipient A → +7 sec
Recipient B → +13 sec
Recipient C → +5 sec
Recipient D → +11 sec
```

DForm **tidak menggunakan `sleep()`** pada worker.

Job dijadwalkan menggunakan Laravel Queue delay.

---

# 30. Queue Architecture

Arsitektur:

```text
                    DForm
                      │
                Create Broadcast
                      │
                      ↓
                Database Record
                      │
                      ↓
                Laravel Queue
                      │
                    Redis
                      │
             ┌────────┼────────┐
             ↓        ↓        ↓
           Job A    Job B    Job C
             ↓        ↓        ↓
           Email    Email    Email
```

Setiap recipient memiliki job sendiri.

Keuntungannya:

* satu email gagal tidak menghentikan seluruh broadcast,
* retry lebih mudah,
* tracking lebih jelas,
* cancellation lebih mudah,
* proses web request tidak blocking.

---

# 31. Automatic Retry

Maximum automatic retry:

```text
3 attempts
```

Contoh:

```text
Attempt 1
   ↓
Failed

Attempt 2
   ↓
Failed

Attempt 3
   ↓
Failed

FINAL STATUS = failed
```

Jika berhasil pada attempt kedua:

```text
Attempt 1 → failed
Attempt 2 → sent
```

Status akhir:

```text
sent
```

---

# 32. Manual Retry

Pada detail broadcast:

```text
Total: 150

Sent:       142
Failed:       5
Pending:      3
```

Superadmin dapat:

```text
[Retry Failed]
```

Hanya recipient:

```text
status = failed
```

yang diproses ulang.

---

# 33. Delivery Tracking

Setiap recipient minimal menyimpan:

```text
email
name
status
attempts
sent_at
failed_at
error_message
created_at
updated_at
```

Detail:

```text
Name: Nafan
Email: nafan@gmail.com
Status: Sent
Attempts: 1
Sent At: 2026-10-01 19:04:12
```

Jika gagal:

```text
Status: Failed
Attempts: 3
Error:
Connection timeout
```

---

# 34. Broadcast Monitoring

Halaman detail:

```text
Pengumuman Open Recruitment
──────────────────────────────

Status: Processing

Total       150
Sent        120
Pending      20
Failed       10

Progress
████████████████░░░░ 80%
```

Recipient table:

| Name  | Email                                     | Status | Attempts | Sent At |
| ----- | ----------------------------------------- | ------ | -------: | ------- |
| Nafan | [nafan@gmail.com](mailto:nafan@gmail.com) | Sent   |        1 | 19:02   |
| Budi  | [budi@gmail.com](mailto:budi@gmail.com)   | Sent   |        2 | 19:03   |
| Andi  | [andi@gmail.com](mailto:andi@gmail.com)   | Failed |        3 | -       |

---

# 35. Broadcast Database

Untuk MVP, struktur database yang disarankan:

## `email_broadcasts`

```text
id
name
scheduled_at

delay_min
delay_max

event_id nullable

subject
content

status

total_recipients
total_sent
total_failed

started_at nullable
completed_at nullable
cancelled_at nullable

created_by

created_at
updated_at
```

---

## `email_broadcast_recipients`

```text
id
broadcast_id

name nullable
email

status

attempts

sent_at nullable
failed_at nullable

error_message nullable

created_at
updated_at
```

---

## `email_broadcast_attachments`

```text
id
broadcast_id

file_name
file_path
mime_type
file_size

created_at
updated_at
```

Untuk v1.0, kita **tidak perlu** membuat:

```text
email_broadcast_jobs
email_delivery_events
email_open_tracking
email_click_tracking
email_campaign_segments
```

Laravel Queue sudah menangani job-nya sendiri.

---

# 36. Dataset Architecture

Karena dataset dapat berasal dari berbagai sumber, konsep dataset bisa dibuat ringan.

Untuk reusable dataset:

### `email_datasets`

```text
id
name
source_type
source_id nullable
created_by
created_at
updated_at
```

`source_type`:

```text
event_participants
recruitment_applicants
users
custom
```

Untuk custom dataset:

### `email_dataset_recipients`

```text
id
dataset_id
name nullable
email
created_at
updated_at
```

Namun ada satu prinsip:

> **Broadcast tidak bergantung pada dataset setelah snapshot dibuat.**

---

# 37. Relationship

```text
email_datasets
       │
       │
       ↓
email_dataset_recipients
       │
       │ Generate Snapshot
       ↓
email_broadcast_recipients
       ↑
       │
email_broadcasts
       │
       ├── email_broadcast_attachments
       │
       └── event
```

---

# 38. Hard Delete

Ketika Superadmin menghapus broadcast:

```text
Delete Broadcast
      ↓
Recipients deleted
      ↓
Attachments deleted
      ↓
Physical attachment files deleted
      ↓
Broadcast deleted
```

Database foreign key sebaiknya menggunakan cascade untuk child records.

Namun attachment storage juga harus dihapus secara eksplisit melalui service/application logic.

---

# 39. Global Mail Configuration

Email Broadcasting menggunakan konfigurasi email global DForm.

```text
DForm
   │
   └── Global Mail Configuration
            │
            ├── Mailer
            ├── Host
            ├── Port
            ├── Username
            ├── Password
            └── Fixed Sender
```

Contoh:

```text
From Name:
DOSCOM

From Address:
no-reply@doscom.org
```

Broadcast tidak memiliki konfigurasi provider sendiri.

---

# 40. Validation Rules

## Broadcast

* name wajib
* scheduled date wajib
* scheduled time wajib
* delay minimum wajib
* delay maximum wajib
* `delay_min <= delay_max`
* recipient minimal 1
* subject wajib
* content wajib

## Recipient

* email wajib
* email valid
* name optional

## Attachment

```text
max 1.5 MB / file
```

## Inline Image

```text
max 1.5 MB / original file
```

## Variable

Hanya:

```text
{{name}}
{{event_name}}
```

---

# 41. Security

Karena email content berasal dari rich text editor, DForm harus melakukan sanitization terhadap HTML.

Tujuannya mencegah:

* arbitrary JavaScript,
* malicious HTML,
* unsafe attributes,
* dangerous URLs.

Content yang disimpan dan dikirim harus melalui allowlist HTML yang sesuai dengan output Tiptap.

Selain itu:

* hanya authorized user yang dapat membuat broadcast,
* attachment harus divalidasi MIME dan size,
* recipient email harus divalidasi,
* file path tidak boleh berasal langsung dari input user,
* broadcast action harus melalui authorization layer.

---

# 42. Acceptance Criteria MVP

## Create

**Given** Superadmin berada di Email Broadcasting
**When** memilih Create Broadcast
**Then** Superadmin dapat mengisi konfigurasi broadcast.

---

## Dataset

**Given** Superadmin memilih dataset
**When** menambahkan beberapa dataset
**Then** seluruh recipient dari dataset tersebut dapat digunakan untuk snapshot.

---

## Snapshot

**Given** dataset sudah dipilih
**When** Superadmin menekan Generate Snapshot
**Then** DForm membuat recipient snapshot.

---

## Duplicate

**Given** terdapat email yang sama
**When** snapshot dibuat
**Then** DForm menampilkan duplicate warning.

**And**

DForm tidak menghapus duplicate secara otomatis.

---

## Edit Snapshot

**Given** snapshot sudah dibuat
**When** Superadmin mengubah recipient
**Then** perubahan hanya berlaku pada broadcast tersebut.

---

## Preview

**Given** email sudah dibuat
**When** Superadmin membuka Preview
**Then** DForm menampilkan hasil rendering email.

---

## Test Email

**Given** email valid
**When** Superadmin mengirim Test Email
**Then** email test dikirim tanpa mengubah recipient broadcast.

---

## Schedule

**Given** broadcast valid
**When** Superadmin menekan Schedule
**Then** status menjadi `scheduled`.

---

## Queue

**Given** `scheduled_at` telah tercapai
**When** queue memproses broadcast
**Then** recipient diproses secara asynchronous.

---

## Random Delay

**Given** delay range 5–15 detik
**When** recipient jobs dibuat
**Then** setiap recipient memperoleh delay random dalam range tersebut.

---

## Retry

**Given** pengiriman gagal
**When** retry otomatis berjalan
**Then** sistem mencoba kembali hingga maksimum 3 attempt.

---

## Cancel

**Given** broadcast berstatus Scheduled atau Processing
**When** Superadmin melakukan Cancel
**Then** broadcast menjadi `cancelled`.

Recipient yang sudah `sent` tetap `sent`.

---

## Retry Failed

**Given** terdapat recipient `failed`
**When** Superadmin memilih Retry Failed
**Then** recipient tersebut kembali diproses.

---

## Delete

**Given** Superadmin menghapus broadcast
**When** deletion berhasil
**Then** broadcast, recipients, attachments, dan file attachment terkait ikut dihapus.

---

# 43. Suggested UI Structure

Sidebar:

```text
DForm
│
├── Dashboard
├── Events
├── Recruitment
├── Attendance
├── Email Broadcasting
│
└── ...
```

Email Broadcasting:

```text
Email Broadcasting

[+ Create Broadcast]

──────────────────────────────────

Name                         Status       Schedule
Pengumuman Recruitment       Completed    01 Oct 19:00
Reminder Seminar             Processing   02 Oct 08:00
Hasil Seleksi                Cancelled    03 Oct 19:00
```

Detail:

```text
← Back

Pengumuman Recruitment
Status: Processing

Schedule
01 October 2026 · 19:00

Recipients
150

┌─────────┬─────────┬─────────┬─────────┐
│ Total   │ Sent    │ Failed  │ Pending │
│ 150     │ 130     │ 5       │ 15      │
└─────────┴─────────┴─────────┴─────────┘

[Cancel Broadcast]
[Retry Failed]
```

---

# 44. Recommended Create Wizard

Supaya form tidak terlalu panjang, aku menyarankan wizard sederhana:

```text
01 Configuration
       ↓
02 Dataset
       ↓
03 Recipients
       ↓
04 Email
       ↓
05 Preview
       ↓
06 Schedule
```

### Step 1 — Configuration

```text
Broadcast Name
Schedule
Delay Range
Event
```

### Step 2 — Dataset

```text
Existing Dataset
Custom Dataset
```

### Step 3 — Recipients

```text
Generate Snapshot

Total: 150
Duplicate: 3

[Edit Recipients]
```

### Step 4 — Email

```text
Subject
Tiptap Editor
Attachments
```

### Step 5 — Preview

```text
Email Preview

[Send Test Email]
```

### Step 6 — Schedule

```text
Summary

Recipients: 150
Schedule: 1 Oct 2026 19:00
Delay: 5–15 sec

[Schedule Broadcast]
```

---

# 45. MVP Technical Architecture

Dengan stack DForm saat ini:

```text
Laravel 12
     │
     ├── Email Broadcasting Module
     │
     ├── Laravel Mail
     │
     ├── Laravel Queue
     │
     └── Redis
           │
           ↓
       Queue Workers
           │
           ↓
      Global Mail Provider
```

Frontend:

```text
Vue 3
   +
Inertia 2
   +
Tailwind CSS 4
   +
shadcn-vue
   +
Tiptap
```

Storage:

```text
Broadcast
   │
   ├── Attachment
   │
   └── Inline Image
          ↓
       Base64
```

---

# 46. Definition of Done — MVP

Email Broadcasting v1.0 dianggap selesai apabila:

* [ ] Superadmin dapat membuat broadcast.
* [ ] Broadcast langsung memiliki status Scheduled setelah dijadwalkan.
* [ ] Multiple dataset dapat digunakan.
* [ ] Event Participants dapat digunakan.
* [ ] Recruitment Applicants dapat digunakan.
* [ ] Users dapat digunakan.
* [ ] Custom recipient dapat dibuat manual.
* [ ] CSV dapat diimport.
* [ ] Snapshot dapat dibuat.
* [ ] Snapshot dapat direview.
* [ ] Snapshot dapat diedit.
* [ ] Duplicate dapat dideteksi.
* [ ] Duplicate tidak dihapus otomatis.
* [ ] Tiptap dapat digunakan.
* [ ] `{{name}}` bekerja.
* [ ] Fallback `{{name}}` → `Peserta` bekerja.
* [ ] `{{event_name}}` bekerja.
* [ ] Event context dapat dipilih manual.
* [ ] Preview bekerja.
* [ ] Test email bekerja.
* [ ] Attachment ≤ 1,5 MB dapat digunakan.
* [ ] Inline image ≤ 1,5 MB dapat digunakan.
* [ ] Inline image disimpan sebagai Base64.
* [ ] Broadcast menggunakan global mail configuration.
* [ ] Fixed sender digunakan.
* [ ] Queue menggunakan Redis.
* [ ] Pengiriman dilakukan per recipient.
* [ ] Random delay bekerja.
* [ ] Automatic retry maksimal 3 kali.
* [ ] Manual Retry Failed bekerja.
* [ ] Recipient delivery status tercatat.
* [ ] Broadcast dapat dibatalkan saat Scheduled.
* [ ] Broadcast dapat dibatalkan saat Processing.
* [ ] Scheduled broadcast hanya dapat mengubah schedule.
* [ ] Broadcast Completed dapat dihapus.
* [ ] Hard delete membersihkan child data.
* [ ] Attachment fisik ikut dihapus.
* [ ] Authorization hanya mengizinkan Superadmin pada MVP.
* [ ] HTML email disanitasi.

---

## 47. Ringkasan Arsitektur Final

Kalau dipadatkan, inti desain **Email Broadcasting DForm v1.0** adalah:

```text
                       ┌─────────────────┐
                       │    SUPERADMIN   │
                       └────────┬────────┘
                                │
                                ↓
                       ┌─────────────────┐
                       │ Email Broadcast │
                       └────────┬────────┘
                                │
              ┌─────────────────┼─────────────────┐
              ↓                 ↓                 ↓
          Configuration      Dataset           Email
              │                 │                 │
              │          ┌──────┼──────┐          │
              │          ↓      ↓      ↓          │
              │        Event  Recruit  Users      │
              │          │      │      │          │
              │          └──────┼──────┘          │
              │                 ↓                 │
              │          Custom Dataset           │
              │                 │                 │
              └─────────────────┼─────────────────┘
                                ↓
                       Generate Snapshot
                                ↓
                       Recipient Snapshot
                                ↓
                      Review / Edit / Warn
                                ↓
                         Preview + Test
                                ↓
                            SCHEDULED
                                ↓
                         Laravel Queue
                                ↓
                              Redis
                                ↓
                    ┌───────────┼───────────┐
                    ↓           ↓           ↓
                  Job 1       Job 2       Job N
                    ↓           ↓           ↓
                 Random      Random      Random
                  Delay       Delay       Delay
                    ↓           ↓           ↓
                 Mailer      Mailer      Mailer
                    └───────────┼───────────┘
                                ↓
                       Delivery Tracking
                                ↓
                  ┌─────────────┴─────────────┐
                  ↓                           ↓
                SENT                       FAILED
                                             ↓
                                       Retry × 3
                                             ↓
                                      Retry Failed
```

**Kesimpulan desain:** v1.0 ini sengaja berhenti di level **transactional bulk email**, bukan berubah menjadi platform email marketing. Dataset → snapshot → queue → per-recipient tracking adalah empat komponen inti; sisanya hanya mendukung alur tersebut. Dengan batasan ini, implementasinya cukup realistis untuk ditambahkan ke DForm tanpa membuat modul menjadi terlalu besar.
