# OpRec — Routes & Pages

**Acuan:** PRD §12, §14; [authorization.md](authorization.md); [architecture.md](architecture.md)

---

## 1. Konvensi

- Route files: `routes/web/oprec.php` (public), `routes/web/admin/recruitment.php` (internal)
- Auto-loaded via `routes/web.php` glob pattern
- Route names: `open-recruitment.*` (public), `dashboard.recruitment.*` (admin)
- Inertia pages: PascalCase path mirror folder structure
- Wayfinder: regenerate setelah route ditambahkan

Perluas [`resources/js/lib/routes.ts`](../../../resources/js/lib/routes.ts):

```typescript
openRecruitment: {
  landing: '/open-recruitment',
  apply: '/open-recruitment/apply',
  track: '/open-recruitment/track',
  attendance: '/open-recruitment/attendance',
},
admin: {
  recruitment: {
    index: '/admin/recruitment',
    periods: { ... },
    applications: { ... },
    // ...
  },
},
```

---

## 2. Public Routes (No Auth)

| Method | URL | Route Name | Controller | Inertia Page |
|--------|-----|------------|------------|--------------|
| GET | `/open-recruitment` | `open-recruitment.landing` | `LandingController@index` | `OpenRecruitment/Landing` |
| GET | `/open-recruitment/apply` | `open-recruitment.apply` | `ApplicationController@create` | `OpenRecruitment/Apply` |
| POST | `/open-recruitment/apply` | `open-recruitment.apply.store` | `ApplicationController@store` | redirect |
| GET | `/open-recruitment/success` | `open-recruitment.success` | `ApplicationController@success` | `OpenRecruitment/Success` |
| GET | `/open-recruitment/track` | `open-recruitment.track.login` | `TrackingController@login` | `OpenRecruitment/Track/Login` |
| POST | `/open-recruitment/track` | `open-recruitment.track.authenticate` | `TrackingController@authenticate` | redirect |
| GET | `/open-recruitment/track/dashboard` | `open-recruitment.track.show` | `TrackingController@show` | `OpenRecruitment/Track/Show` |
| GET | `/open-recruitment/track/edit` | `open-recruitment.track.edit` | `TrackingController@edit` | `OpenRecruitment/Track/Edit` |
| PUT | `/open-recruitment/track` | `open-recruitment.track.update` | `TrackingController@update` | redirect |
| POST | `/open-recruitment/track/correction` | `open-recruitment.track.correction` | `CorrectionRequestController@store` | redirect |
| GET | `/open-recruitment/track/feedback` | `open-recruitment.track.feedback` | `FeedbackController@create` | `OpenRecruitment/Track/Feedback` |
| POST | `/open-recruitment/track/feedback` | `open-recruitment.track.feedback.store` | `FeedbackController@store` | redirect |
| GET | `/open-recruitment/attendance` | `open-recruitment.attendance` | `AttendanceController@index` | `OpenRecruitment/Attendance` |
| POST | `/open-recruitment/attendance/check-in` | `open-recruitment.attendance.check-in` | `AttendanceController@checkIn` | JSON/redirect |

**Middleware public:**

- `throttle:oprec-apply` pada POST apply
- `throttle:oprec-track` pada POST track authenticate
- `EnsureRecruitmentPeriodOpen` pada apply routes
- `EnsureTrackingSession` pada track dashboard/edit/update/correction/feedback

---

## 3. Admin Routes (Auth + Permission)

Base: `Route::prefix('admin/recruitment')->middleware(['auth', 'recruitment.access'])`

### 3.1 Dashboard

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment` | `dashboard.recruitment.index` | `Dashboard/Recruitment/Index` |

**Props:** KPI counts, funnel summary, today's interviews, active period.

### 3.2 Periods (Admin)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/periods` | `dashboard.recruitment.periods.index` | `Dashboard/Recruitment/Periods/Index` |
| GET | `/admin/recruitment/periods/create` | `dashboard.recruitment.periods.create` | `Dashboard/Recruitment/Periods/Create` |
| POST | `/admin/recruitment/periods` | `dashboard.recruitment.periods.store` | redirect |
| GET | `/admin/recruitment/periods/{period}` | `dashboard.recruitment.periods.show` | `Dashboard/Recruitment/Periods/Show` |
| GET | `/admin/recruitment/periods/{period}/edit` | `dashboard.recruitment.periods.edit` | `Dashboard/Recruitment/Periods/Edit` |
| PUT | `/admin/recruitment/periods/{period}` | `dashboard.recruitment.periods.update` | redirect |
| DELETE | `/admin/recruitment/periods/{period}` | `dashboard.recruitment.periods.destroy` | redirect |
| POST | `/admin/recruitment/periods/{period}/open` | `dashboard.recruitment.periods.open` | redirect |
| POST | `/admin/recruitment/periods/{period}/close` | `dashboard.recruitment.periods.close` | redirect |

### 3.3 Divisions (Admin)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/divisions` | `dashboard.recruitment.divisions.index` | `Dashboard/Recruitment/Divisions/Index` |
| POST | `/admin/recruitment/divisions` | `dashboard.recruitment.divisions.store` | redirect |
| PUT | `/admin/recruitment/divisions/{division}` | `dashboard.recruitment.divisions.update` | redirect |

### 3.4 Interviewer Assignment (Admin)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/interviewers` | `dashboard.recruitment.interviewers.index` | `Dashboard/Recruitment/Interviewers/Index` |
| POST | `/admin/recruitment/interviewers` | `dashboard.recruitment.interviewers.assign` | redirect |
| DELETE | `/admin/recruitment/interviewers/{assignment}` | `dashboard.recruitment.interviewers.unassign` | redirect |

### 3.5 Applications (Staff)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/applications` | `dashboard.recruitment.applications.index` | `Dashboard/Recruitment/Applications/Index` |
| GET | `/admin/recruitment/applications/{application}` | `dashboard.recruitment.applications.show` | `Dashboard/Recruitment/Applications/Show` |
| GET | `/admin/recruitment/applications/export` | `dashboard.recruitment.applications.export` | CSV download |
| GET | `/admin/recruitment/applications/{application}/documents/{type}` | `dashboard.recruitment.applications.documents.download` | file stream |

**Application Show — Tabs:**

- Overview (data pribadi, divisi)
- Documents (CV, portfolio preview/download)
- Screening (history + action buttons)
- Interview (schedule, attendance, queue, evaluation)
- Final Decision
- Activity Log

### 3.6 Screening Actions (Staff)

| Method | URL | Route Name |
|--------|-----|------------|
| POST | `/admin/recruitment/applications/{application}/screening/pass` | `dashboard.recruitment.applications.screening.pass` |
| POST | `/admin/recruitment/applications/{application}/screening/revision` | `dashboard.recruitment.applications.screening.revision` |
| POST | `/admin/recruitment/applications/{application}/screening/reject` | `dashboard.recruitment.applications.screening.reject` |
| POST | `/admin/recruitment/applications/{application}/verify` | `dashboard.recruitment.applications.verify` |

### 3.7 Correction Requests (Staff)

| Method | URL | Route Name |
|--------|-----|------------|
| POST | `/admin/recruitment/corrections/{correction}/approve` | `dashboard.recruitment.corrections.approve` |
| POST | `/admin/recruitment/corrections/{correction}/reject` | `dashboard.recruitment.corrections.reject` |

### 3.8 Interview Sessions & Scheduling (Staff)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/interview-sessions` | `dashboard.recruitment.interview-sessions.index` | `Dashboard/Recruitment/InterviewSessions/Index` |
| POST | `/admin/recruitment/interview-sessions` | `dashboard.recruitment.interview-sessions.store` | redirect |
| GET | `/admin/recruitment/interview-sessions/{session}` | `dashboard.recruitment.interview-sessions.show` | `Dashboard/Recruitment/InterviewSessions/Show` |
| POST | `/admin/recruitment/interview-sessions/{session}/schedule` | `dashboard.recruitment.interview-sessions.schedule` | bulk schedule |
| POST | `/admin/recruitment/interviews/{interview}/reschedule` | `dashboard.recruitment.interviews.reschedule` | redirect |
| POST | `/admin/recruitment/interviews/{interview}/reassign` | `dashboard.recruitment.interviews.reassign` | redirect |

### 3.9 Queue & Attendance (Staff)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/queue/{session}` | `dashboard.recruitment.queue.show` | `Dashboard/Recruitment/Queue/Show` |
| POST | `/admin/recruitment/queue/{session}/call-next` | `dashboard.recruitment.queue.call-next` | JSON |
| POST | `/admin/recruitment/queue/{entry}/complete` | `dashboard.recruitment.queue.complete` | JSON |
| GET | `/admin/recruitment/attendance-scan` | `dashboard.recruitment.attendance-scan` | `Dashboard/Recruitment/AttendanceScan` |
| POST | `/admin/recruitment/attendance-scan` | `dashboard.recruitment.attendance-scan.store` | JSON |

**Reuse UX:** Referensi [`AttendanceScanController`](../../../app/Http/Controllers/Dashboard/Events/AttendanceScanController.php) untuk kamera QR.

### 3.10 Final Selection (Staff)

| Method | URL | Route Name |
|--------|-----|------------|
| POST | `/admin/recruitment/applications/{application}/final/accept-aa` | `dashboard.recruitment.applications.final.accept-aa` |
| POST | `/admin/recruitment/applications/{application}/final/accept-member` | `dashboard.recruitment.applications.final.accept-member` |
| POST | `/admin/recruitment/applications/{application}/final/reject` | `dashboard.recruitment.applications.final.reject` |

### 3.11 Interviewer Routes (Policy-gated)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/my-interviews` | `dashboard.recruitment.my-interviews.index` | `Dashboard/Recruitment/MyInterviews/Index` |
| GET | `/admin/recruitment/my-interviews/{application}` | `dashboard.recruitment.my-interviews.show` | `Dashboard/Recruitment/MyInterviews/Show` |
| POST | `/admin/recruitment/my-interviews/{application}/evaluate` | `dashboard.recruitment.my-interviews.evaluate` | redirect |
| GET | `/admin/recruitment/my-interviews/queue/{session}` | `dashboard.recruitment.my-interviews.queue` | `Dashboard/Recruitment/MyInterviews/Queue` |

### 3.12 Reports (Staff + Admin)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/reports` | `dashboard.recruitment.reports.index` | `Dashboard/Recruitment/Reports/Index` |
| GET | `/admin/recruitment/reports/funnel` | `dashboard.recruitment.reports.funnel` | JSON/props |
| GET | `/admin/recruitment/reports/interviews` | `dashboard.recruitment.reports.interviews` | JSON/props |
| GET | `/admin/recruitment/reports/export` | `dashboard.recruitment.reports.export` | CSV |

### 3.13 Settings (Admin)

| Method | URL | Route Name | Page |
|--------|-----|------------|------|
| GET | `/admin/recruitment/settings/templates` | `dashboard.recruitment.settings.templates` | `Dashboard/Recruitment/Settings/Templates` |
| PUT | `/admin/recruitment/settings/templates/{template}` | `dashboard.recruitment.settings.templates.update` | redirect |
| GET | `/admin/recruitment/activity-logs` | `dashboard.recruitment.activity-logs` | `Dashboard/Recruitment/ActivityLogs/Index` |

---

## 4. Halaman Inertia — Struktur Folder

```text
resources/js/pages/
├── OpenRecruitment/
│   ├── Landing.vue
│   ├── Apply.vue
│   ├── Success.vue
│   ├── Attendance.vue
│   └── Track/
│       ├── Login.vue
│       ├── Show.vue
│       ├── Edit.vue
│       └── Feedback.vue
└── Dashboard/
    └── Recruitment/
        ├── Index.vue                    # Dashboard KPI
        ├── Periods/
        │   ├── Index.vue
        │   ├── Create.vue
        │   ├── Edit.vue
        │   └── Show.vue
        ├── Divisions/
        │   └── Index.vue
        ├── Interviewers/
        │   └── Index.vue
        ├── Applications/
        │   ├── Index.vue
        │   └── Show.vue
        ├── InterviewSessions/
        │   ├── Index.vue
        │   └── Show.vue
        ├── Queue/
        │   └── Show.vue
        ├── AttendanceScan.vue
        ├── MyInterviews/
        │   ├── Index.vue
        │   ├── Show.vue
        │   └── Queue.vue
        ├── Reports/
        │   └── Index.vue
        ├── Settings/
        │   └── Templates.vue
        └── ActivityLogs/
            └── Index.vue
```

---

## 5. Layout

| Area | Layout | Catatan |
|------|--------|---------|
| Public OpRec | `PublicLayout.vue` (baru) atau layout marketing existing | Header DOSCOM, footer, no sidebar |
| Admin OpRec | `DashboardLayout.vue` | Existing; extend sidebar sub-menu Rekrutmen |

### 5.1 Sidebar Sub-menu (DashboardSidebar)

Under "Rekrutmen":

- Dashboard
- Applications (Staff)
- Interview Sessions (Staff)
- Queue Monitor (Staff)
- Attendance Scan (Staff)
- My Interviews (Interviewer)
- Reports
- Periods (Admin)
- Divisions (Admin)
- Interviewers (Admin)
- Email Templates (Admin)
- Activity Logs (Admin)

Visibility per permission — lihat [authorization.md](authorization.md) §10.

---

## 6. Composables

| File | Fungsi |
|------|--------|
| `useRecruitmentApplicationsPage.ts` | Filter, pagination, table state |
| `useRecruitmentApplicationShow.ts` | Tab state, screening actions |
| `useRecruitmentQueue.ts` | Poll queue every 10s (MVP) |
| `useRecruitmentTracking.ts` | Timeline stage computation |
| `useRecruitmentEvaluationForm.ts` | Score validation 1–10 |
| `useAttendanceScanner.ts` | QR decode — reuse dari events |

---

## 7. Breadcrumbs

Perluas [`resources/js/lib/breadcrumbs.ts`](../../../resources/js/lib/breadcrumbs.ts):

```text
Rekrutmen → Applications → {registration_number}
Rekrutmen → Periods → OpRec 2026
Rekrutmen → My Interviews → {applicant_name}
```

---

## 8. Route File Skeleton

### `routes/web/oprec.php`

```php
Route::prefix('open-recruitment')->name('open-recruitment.')->group(function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing');
    Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
    Route::post('/apply', [ApplicationController::class, 'store'])
        ->middleware(['EnsureRecruitmentPeriodOpen', 'throttle:oprec-apply'])
        ->name('apply.store');
    // ... track, attendance, feedback
});
```

### `routes/web/admin/recruitment.php`

```php
Route::prefix('admin/recruitment')
    ->middleware(['auth', 'recruitment.access'])
    ->name('dashboard.recruitment.')
    ->group(function () {
        Route::get('/', [RecruitmentDashboardController::class, 'index'])->name('index');
        Route::resource('periods', RecruitmentPeriodController::class);
        // ... applications, interviews, queue, etc.
    });
```

---

## 9. Milestone Mapping

| Routes/Pages | Milestone |
|--------------|-----------|
| Public landing + apply | OpRec-M2 |
| Track pages | OpRec-M3 |
| Applications + screening | OpRec-M4 |
| Track edit + correction | OpRec-M5 |
| Interview sessions + schedule | OpRec-M6 |
| Attendance + queue | OpRec-M7 |
| My Interviews + evaluate | OpRec-M8 |
| Final selection actions | OpRec-M9 |
| Reports + feedback + templates | OpRec-M10 |

Lihat [milestone.md](milestone.md) untuk detail deliverable per fase.
