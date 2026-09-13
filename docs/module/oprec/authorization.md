# OpRec — Authorization & RBAC

**Acuan:** PRD §33; Spatie Laravel Permission; [architecture.md](architecture.md)

---

## 1. Actor & Mekanisme Auth

| Actor | Auth Mechanism | Session |
|-------|----------------|---------|
| **Applicant** | Registration number + tracking token (guest) | Tidak ada Laravel auth session |
| **Staff** | Laravel session + Spatie role/permission | Ya |
| **Interviewer** | Laravel session + Spatie role/permission | Ya |
| **Admin** | Laravel session + Spatie role/permission | Ya |

Applicant **bukan** user Spatie. Akses applicant di-handle via:

- Middleware khusus `ValidateTrackingToken` — verifikasi token hash
- Rate limiting pada endpoint public
- Policy `GuestApplicationPolicy` atau logic di Service (bukan Model Policy tradisional)

---

## 2. Mapping Role PRD → Spatie

| PRD Role | Spatie Role | Deskripsi |
|----------|-------------|-----------|
| Admin | `admin` (existing) + permission `recruitment.*` | Full system management |
| Staff | `recruitment-staff` (baru) | Operational recruitment |
| Interviewer | `recruitment-interviewer` (baru) | Interview & evaluation only |
| Member (D-Form) | `member` (existing) | **Tidak** akses OpRec admin |

**Catatan:** User dapat memiliki multiple roles (mis. Staff + Interviewer) jika di-assign.

---

## 3. Permission Matrix

### 3.1 Daftar Permission (aktifkan stub di `RoleSeeder`)

```text
# Period & config (Admin)
recruitment.periods.list
recruitment.periods.create
recruitment.periods.view
recruitment.periods.edit
recruitment.periods.delete

# Divisions (Admin)
recruitment.divisions.list
recruitment.divisions.create
recruitment.divisions.view
recruitment.divisions.edit
recruitment.divisions.delete

# Interviewer assignment (Admin)
recruitment.interviewers.assign

# Applications (Staff)
recruitment.applications.list
recruitment.applications.view
recruitment.applications.export

# Screening (Staff)
recruitment.screening.review
recruitment.screening.decide

# Correction (Staff)
recruitment.corrections.review

# Interviews (Staff)
recruitment.interviews.schedule
recruitment.interviews.reschedule
recruitment.interviews.reassign

# Queue & Attendance (Staff + Interviewer read)
recruitment.queue.view
recruitment.queue.manage
recruitment.attendance.scan

# Evaluation (Interviewer)
recruitment.evaluations.submit
recruitment.evaluations.view

# Final selection (Staff ONLY)
recruitment.final.decide

# Reports (Staff + Admin)
recruitment.reports.view
recruitment.reports.export

# Email templates (Admin)
recruitment.templates.list
recruitment.templates.edit

# Activity log (Staff + Admin)
recruitment.activity.view

# Dashboard
recruitment.dashboard.view
```

### 3.2 Role → Permission Assignment

| Permission | Admin | Staff | Interviewer |
|------------|:-----:|:-----:|:-----------:|
| `recruitment.periods.*` | ✓ | — | — |
| `recruitment.divisions.*` | ✓ | view | — |
| `recruitment.interviewers.assign` | ✓ | — | — |
| `recruitment.applications.list/view/export` | ✓ | ✓ | assigned only |
| `recruitment.screening.*` | ✓ | ✓ | — |
| `recruitment.corrections.review` | ✓ | ✓ | — |
| `recruitment.interviews.schedule/reschedule/reassign` | ✓ | ✓ | — |
| `recruitment.queue.view` | ✓ | ✓ | ✓ |
| `recruitment.queue.manage` | ✓ | ✓ | ✓ (own session) |
| `recruitment.attendance.scan` | ✓ | ✓ | — |
| `recruitment.evaluations.submit` | ✓ | — | ✓ (assigned) |
| `recruitment.evaluations.view` | ✓ | ✓ | ✓ (assigned) |
| `recruitment.final.decide` | ✓ | ✓ | **✗** |
| `recruitment.reports.*` | ✓ | ✓ | — |
| `recruitment.templates.*` | ✓ | — | — |
| `recruitment.activity.view` | ✓ | ✓ | — |
| `recruitment.dashboard.view` | ✓ | ✓ | ✓ (subset) |

---

## 4. Policy Mapping

| Model | Policy | Method Examples |
|-------|--------|-----------------|
| `RecruitmentPeriod` | `RecruitmentPeriodPolicy` | viewAny, create, update, delete, open, close |
| `RecruitmentApplication` | `RecruitmentApplicationPolicy` | view, screen, export |
| `RecruitmentInterview` | `RecruitmentInterviewPolicy` | view, evaluate (interviewer_id match) |
| `RecruitmentEvaluation` | `RecruitmentEvaluationPolicy` | create, update (before lock), override (staff) |
| `RecruitmentFinalDecision` | `RecruitmentFinalDecisionPolicy` | create, update — **Staff only** |
| `RecruitmentDocument` | `RecruitmentDocumentPolicy` | download — staff/interviewer assigned |
| `RecruitmentEmailTemplate` | `RecruitmentEmailTemplatePolicy` | Admin only |

### 4.1 Policy Rules Kritis

```text
Interviewer::updateFinalDecision()     → DENY (always)
Interviewer::screenApplication()     → DENY
Interviewer::viewApplication()       → ALLOW if interviewer_id matches assignment
Staff::decideFinal()                 → ALLOW if has recruitment.final.decide
Member::accessRecruitmentAdmin()     → DENY
Applicant::viewOtherApplication()    → DENY (token scope)
Applicant::viewInternalNotes()       → DENY
```

---

## 5. Middleware

| Middleware | Route Group | Fungsi |
|------------|-------------|--------|
| `auth` | `/admin/recruitment/*` | Wajib login |
| `permission:recruitment.dashboard.view` atau custom `recruitment.access` | Admin recruitment | Gate minimal |
| `ValidateTrackingToken` | `/open-recruitment/track/{token}/*` | Verifikasi token |
| `throttle:oprec-apply` | POST apply | Rate limit pendaftaran |
| `throttle:oprec-track` | POST track login | Anti brute-force token |

### 5.1 Custom Middleware: `EnsureRecruitmentPeriodOpen`

Untuk route public apply — cek period status `open` dan within registration window.

---

## 6. Guest Applicant Authorization

### 6.1 Tracking Token Flow

1. Saat submit: generate random token (min 32 bytes), hash dengan `Hash::make()`, simpan hash
2. Plain token dikirim **sekali** via email konfirmasi
3. Applicant submit reg number + token di `/open-recruitment/track`
4. Server: `Hash::check($plainToken, $application->tracking_token_hash)`
5. On success: set session key `oprec_tracking_{application_id}` dengan TTL (opsional) atau require token setiap request

**Rekomendasi MVP:** Signed URL dengan expiry panjang (90 hari) ATAU session setelah validasi token — lihat [decisions.md](decisions.md).

### 6.2 Field Visibility (TrackingPresenter Whitelist)

| Field | Applicant | Staff | Interviewer |
|-------|:---------:|:-----:|:-----------:|
| full_name, NIM, semester | ✓ | ✓ | ✓ |
| contact info | ✓ (own) | ✓ | ✓ |
| stage, result | ✓ | ✓ | ✓ |
| interview schedule | ✓ | ✓ | ✓ |
| queue number, status | ✓ | ✓ | ✓ |
| final division, membership | ✓ (if accepted) | ✓ | ✓ |
| CV, portfolio | ✓ (own) | ✓ | ✓ |
| screening notes | ✗ | ✓ | ✗ |
| evaluation scores | ✗ | ✓ | ✓ |
| evaluation notes | ✗ | ✓ | ✓ |
| internal rejection reason | ✗ | ✓ | ✗ |
| public rejection message | ✓ | ✓ | ✓ |
| activity log | ✗ | ✓ | ✗ |

---

## 7. File Download Authorization

Endpoint: `GET /admin/recruitment/applications/{id}/documents/{type}`

| Actor | Rule |
|-------|------|
| Staff | `recruitment.applications.view` |
| Interviewer | assigned to application's interview |
| Applicant | via tracking token session + own application only |

Direct URL ke storage path: **FORBIDDEN** — always via controller.

---

## 8. Implementasi di RoleSeeder

Update [`database/seeders/RoleSeeder.php`](../../../database/seeders/RoleSeeder.php):

1. Uncomment dan rename `recruitments.*` → `recruitment.*` (singular, konsisten)
2. Tambah roles `recruitment-staff`, `recruitment-interviewer`
3. Assign permissions sesuai matrix §3.2
4. Admin role dapat all `recruitment.*`

---

## 9. Testing Permission (PHPUnit)

Contoh test cases (lihat [testing-strategy.md](testing-strategy.md)):

```text
✓ Staff can screen application
✓ Interviewer cannot screen application
✓ Interviewer can evaluate assigned application
✓ Interviewer cannot evaluate unassigned application
✓ Interviewer cannot make final decision
✓ Member cannot access /admin/recruitment
✓ Applicant with valid token can view own tracking
✓ Applicant cannot view other application with wrong token
✓ Unauthenticated cannot download CV
```

---

## 10. Inertia Shared Props

Extend `HandleInertiaRequests` dengan props opsional:

```typescript
can_access_recruitment: boolean
can_manage_recruitment_periods: boolean
can_screen_applications: boolean
can_evaluate_interviews: boolean
recruitment_role: 'admin' | 'staff' | 'interviewer' | null
```

Gunakan di sidebar untuk show/hide menu Rekrutmen sub-items.
