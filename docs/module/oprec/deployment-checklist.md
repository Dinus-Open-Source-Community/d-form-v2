# OpRec — Production Deployment Checklist (M11)

Checklist ini melengkapi [architecture.md §12](architecture.md) dan PRD §47–52.

## Infrastructure

- [ ] **Queue worker** berjalan (`php artisan queue:work --tries=3`) atau supervisor equivalent
- [ ] **Scheduler cron** aktif (`* * * * * php artisan schedule:run`)
- [ ] **Interview reminder command** terdaftar di scheduler (`recruitment:send-interview-reminders`)
- [ ] **Private storage** (`local` disk) tidak di-expose via public URL
- [ ] **`storage:link`** hanya untuk asset public (bukan CV/portfolio OpRec)

## Application config

- [ ] `APP_URL` production benar (email tracking link + QR)
- [ ] `MAIL_*` SMTP production dikonfigurasi dan diverifikasi
- [ ] `QUEUE_CONNECTION` bukan `sync` di production
- [ ] Rate limit `oprec-apply` dan `oprec-track` sesuai beban (lihat `AppServiceProvider`)
- [ ] `RecruitmentEmailTemplateSeeder` sudah dijalankan atau template disinkronkan

## Database & seed

- [ ] Migration recruitment tables applied
- [ ] `RoleSeeder` — role `recruitment-staff`, `recruitment-interviewer`, permissions
- [ ] `RecruitmentDivisionSeeder` — 4 divisi default
- [ ] Periode OpRec dibuat admin (draft → open → closed sesuai jadwal)

## Security verification

- [ ] Automated tests green: `php artisan test --filter=Recruitment`
- [ ] CV download hanya via authorized dashboard route
- [ ] Tracking session TTL 24 jam (`RecruitmentTrackingSession`)
- [ ] CSRF aktif pada form public (apply, track authenticate)
- [ ] Applicant documents stored on private disk

## Smoke test (production/staging)

1. Submit 1 application real (atau staging) → email konfirmasi diterima
2. Staff screening pass → schedule interview → email jadwal + QR inline
3. Attendance check-in → queue number muncul di tracking
4. Interviewer evaluate → staff final accept → email hasil
5. Applicant submit feedback post-completed
6. Staff export funnel CSV dari `/admin/recruitment/reports`

## Monitoring

- [ ] Monitor `failed_jobs` table / queue dashboard
- [ ] Monitor `email_logs` status `failed`
- [ ] Monitor `storage/logs/laravel.log` untuk `[SendRecruitment*Job]`
- [ ] Alert jika queue worker mati saat deadline pendaftaran

## Rollback plan

- [ ] Tutup periode (`closed`) untuk stop pendaftaran baru
- [ ] Backup DB sebelum deploy migration OpRec
- [ ] Dokumentasi manual resend email (queue retry atau re-dispatch job)

## UAT sign-off

Lihat [testing-strategy.md §9](testing-strategy.md) — semua checklist Applicant, Staff, Interviewer, Admin harus ditandai sebelum go-live.
