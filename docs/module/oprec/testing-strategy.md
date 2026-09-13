# OpRec — Testing Strategy

**Acuan:** PRD §46; [authorization.md](authorization.md); [domain-model.md](domain-model.md)

---

## 1. Tujuan

Memastikan OpRec production-ready dengan coverage:

- Functional workflows (happy path + edge cases)
- Permission & authorization boundaries
- Validation rules
- Security (guest tracking, file access, rate limit)
- Email delivery (queued, logged, deduped)

---

## 2. Struktur Test

```text
tests/
├── Feature/
│   └── Recruitment/
│       ├── ApplicationSubmissionTest.php
│       ├── ApplicantTrackingTest.php
│       ├── ScreeningWorkflowTest.php
│       ├── CorrectionRequestTest.php
│       ├── InterviewSchedulingTest.php
│       ├── AttendanceAndQueueTest.php
│       ├── EvaluationTest.php
│       ├── FinalSelectionTest.php
│       ├── FeedbackTest.php
│       ├── RecruitmentPermissionTest.php
│       ├── RecruitmentSecurityTest.php
│       └── InterviewReminderTest.php
└── Unit/
    └── Recruitment/
        ├── QueueServiceTest.php
        ├── RegistrationNumberGeneratorTest.php
        ├── TrackingPresenterTest.php
        └── RecruitmentEmailRendererTest.php
```

Jalankan:

```bash
php artisan test --filter=Recruitment
```

---

## 3. Test Matrix — Functional

### 3.1 Application Submission (OpRec-M2)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-01 | Submit valid application during open period | 201/redirect success, reg number generated, email queued |
| F-02 | Submit duplicate NIM same period | 422 validation error |
| F-03 | Submit when period closed | 403/redirect with error |
| F-04 | Submit semester 4 | 422 validation error |
| F-05 | Submit secondary division = primary | 422 validation error |
| F-06 | Submit CV non-PDF | 422 validation error |
| F-07 | Submit portfolio URL valid | Success, portfolio_type = url |
| F-08 | Submit portfolio PDF valid | Success, portfolio_type = file |
| F-09 | Submit without required fields | 422 per field |
| F-10 | Registration number format OPREC-YYYY-NNNNN | Pattern match |

### 3.2 Applicant Tracking (OpRec-M3)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-11 | Valid reg number + token | Access tracking dashboard |
| F-12 | Valid reg number, wrong token | 401/403 generic error |
| F-13 | Invalid reg number | 401/403 generic error |
| F-14 | Tracking shows stage, not internal notes | Response whitelist |
| F-15 | Rate limit exceeded on track login | 429 |

### 3.3 Screening (OpRec-M4)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-16 | Staff pass application | Stage → interview, email queued |
| F-17 | Staff revision without reason | 422 |
| F-18 | Staff reject without reason | 422 |
| F-19 | Staff revision with reason | revision_required flag, email queued |
| F-20 | Screening recorded in history | New row in recruitment_screenings |
| F-21 | Activity log entry created | recruitment_activity_logs |

### 3.4 Revision & Correction (OpRec-M5)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-22 | Applicant edit during revision window | Data updated, re-screening |
| F-23 | Applicant edit after verified | Blocked |
| F-24 | Correction request → staff approve → edit | Flow complete |
| F-25 | Correction request → staff reject | Status rejected, no edit |
| F-26 | All edits logged in activity | Audit trail |

### 3.5 Interview Scheduling (OpRec-M6)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-27 | Schedule passed applicant | Interview row created, email queued |
| F-28 | Interviewer assigned by primary division | Correct interviewer_id |
| F-29 | Staff reschedule interview | Updated schedule, email rescheduled |
| F-30 | Staff reassign interviewer | New interviewer_id, assignment email |

### 3.6 Reminders (OpRec-M6)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-31 | H-1 reminder 24h before | Email sent, h1_sent_at set |
| F-32 | H-1 not sent twice | Dedup |
| F-33 | H-2 reminder 2h before | Email sent, h2_sent_at set |
| F-34 | No reminder for cancelled interview | Skip |

### 3.7 Attendance & Queue (OpRec-M7)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-35 | Check-in via registration number | Attendance recorded |
| F-36 | Check-in via QR valid payload | Attendance recorded |
| F-37 | Duplicate check-in | Rejected/idempotent |
| F-38 | Queue FCFS: A before B | queue_number A < B |
| F-39 | Late arrival appended to tail | Last queue position |
| F-40 | No queue without attendance | Queue entry rejected |
| F-41 | No-show path | Status no_show |

### 3.8 Evaluation (OpRec-M8)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-42 | Interviewer submit scores 1-10 | Saved |
| F-43 | Score 0 or 11 rejected | 422 |
| F-44 | Interviewer evaluate unassigned | 403 |
| F-45 | Edit evaluation before lock | Success |
| F-46 | Edit evaluation after lock by interviewer | 403 |
| F-47 | Staff override after lock | Success + audit |

### 3.9 Final Selection (OpRec-M9)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-48 | Accept as AA with division | result=accepted, membership=aa |
| F-49 | Accept as Member with division | result=accepted, membership=member |
| F-50 | Accept without division | 422 |
| F-51 | Reject without reason | 422 |
| F-52 | Reject with internal + public message | Saved separately |
| F-53 | Final visible on tracking (public fields only) | TrackingPresenter |
| F-54 | Result email queued | email_logs |

### 3.10 Feedback (OpRec-M10)

| ID | Test Case | Expected |
|----|-----------|----------|
| F-55 | Submit feedback after completed | Saved |
| F-56 | Submit feedback before completed | Blocked |
| F-57 | Duplicate feedback | Blocked (unique per application) |
| F-58 | Invalid token feedback | 403 |

---

## 4. Test Matrix — Permission

| ID | Test Case | Actor | Expected |
|----|-----------|-------|----------|
| P-01 | Access /admin/recruitment | member | 403 |
| P-02 | Access /admin/recruitment | staff | 200 |
| P-03 | Screen application | interviewer | 403 |
| P-04 | Final decision | interviewer | 403 |
| P-05 | Final decision | staff | 200 |
| P-06 | Manage periods | staff | 403 |
| P-07 | Manage periods | admin | 200 |
| P-08 | Edit email template | staff | 403 |
| P-09 | Evaluate assigned interview | interviewer | 200 |
| P-10 | Download CV unassigned | interviewer | 403 |
| P-11 | Download CV assigned | interviewer | 200 |
| P-12 | View other applicant tracking | applicant B token for A | 403 |

---

## 5. Test Matrix — Workflow Paths

Simulasi end-to-end (Feature test panjang atau Dusk/manual UAT):

### 5.1 Happy Path

```text
Apply → Screen Pass → Schedule → Reminder → Check-in → Queue → Evaluate → Accept AA → Feedback
```

### 5.2 Revision Path

```text
Apply → Screen Revision → Edit → Re-screen Pass → ... → Final
```

### 5.3 Rejection Path (Screening)

```text
Apply → Screen Reject → Tracking shows rejected → No interview
```

### 5.4 Rejection Path (Final)

```text
... → Evaluate → Final Reject → Email → Tracking
```

### 5.5 Late Arrival

```text
A check-in → B check-in → C late check-in → Queue order A, B, C
```

### 5.6 No Show

```text
Scheduled → No attendance → Staff mark no_show
```

### 5.7 Cancel

```text
Apply → Cancel → Cannot re-apply (unless staff reopen)
```

### 5.8 Reschedule

```text
Scheduled → Staff reschedule → New schedule email → Old reminder invalidated
```

---

## 6. Test Matrix — Security

| ID | Test Case | Expected |
|----|-----------|----------|
| S-01 | Direct URL to storage CV path | 403/404 |
| S-02 | Guessing application UUID | 403 |
| S-03 | Brute force tracking token (rate limit) | 429 after N attempts |
| S-04 | CSRF on apply form | 419 |
| S-05 | XSS in applicant name (stored) | Escaped on display |
| S-06 | Upload executable disguised as PDF | Rejected MIME check |
| S-07 | Oversized CV upload | 422 |

---

## 7. Test Matrix — Validation

| Rule | Test |
|------|------|
| NIM unique per period | F-02 |
| Semester 1-3 | F-04 |
| Secondary ≠ primary | F-05 |
| CV PDF only | F-06 |
| Email format | Invalid email → 422 |
| Phone format | Invalid phone → 422 |
| Score 1-10 | F-43 |

---

## 8. Factory & Seeder untuk Test

```text
database/factories/Recruitment/
├── RecruitmentPeriodFactory.php
├── RecruitmentApplicationFactory.php
├── RecruitmentInterviewFactory.php
└── ...
```

States:

- `RecruitmentPeriodFactory::open()`
- `RecruitmentApplicationFactory::passedScreening()`
- `RecruitmentApplicationFactory::scheduledInterview()`
- `RecruitmentApplicationFactory::completedAccepted()`

---

## 9. Manual / UAT Checklist (OpRec-M11)

### 9.1 Applicant Journey

- [ ] Buka landing, CTA aktif saat period open
- [ ] Isi form, upload CV PDF, submit
- [ ] Terima email konfirmasi dengan reg number
- [ ] Login tracking dengan reg number + token
- [ ] Lihat timeline progress
- [ ] (Jika revision) Edit data, re-submit
- [ ] (Jika scheduled) Lihat jadwal interview
- [ ] Check-in attendance, lihat queue number
- [ ] (Setelah final) Lihat hasil + membership + division
- [ ] Submit feedback

### 9.2 Staff Journey

- [ ] Dashboard KPI akurat
- [ ] List & filter applications
- [ ] Screening pass/revision/reject
- [ ] Schedule interview, assign interviewer
- [ ] Monitor queue live
- [ ] Scan attendance QR
- [ ] Final selection AA/Member/Reject
- [ ] Export CSV report

### 9.3 Interviewer Journey

- [ ] My Interviews list hari ini
- [ ] Buka detail applicant (limited fields)
- [ ] Isi evaluation 1-10 + recommendation
- [ ] Tidak bisa akses final decision

### 9.4 Admin Journey

- [ ] CRUD period (draft → open → closed)
- [ ] Manage divisions
- [ ] Assign interviewers
- [ ] Edit email templates
- [ ] View activity logs

---

## 10. Performance Testing (OpRec-M11)

| Scenario | Target |
|----------|--------|
| 100 concurrent apply submissions | No 5xx; queue absorbs email |
| Applicant list 1000+ rows | Pagination < 500ms |
| Queue board 50 waiting | Poll 10s responsive |

Tool: Apache Bench / k6 (optional).

---

## 11. Definition of Done — Testing (maps PRD §52)

Checklist akumulatif per milestone — detail di [milestone.md](milestone.md):

- [ ] Feature tests for M2–M10 critical paths green
- [ ] Permission tests green
- [ ] Security tests green
- [ ] Reminder dedup tests green
- [ ] UAT script executed & signed
- [ ] No P1 bugs open

---

## 12. CI Integration

Tambahkan ke pipeline existing:

```yaml
- name: Recruitment tests
  run: php artisan test --filter=Recruitment
```

Minimum gate untuk merge PR OpRec: all Recruitment tests pass.
