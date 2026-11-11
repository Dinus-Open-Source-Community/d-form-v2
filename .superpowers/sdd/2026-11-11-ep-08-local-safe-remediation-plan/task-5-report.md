# Task 5 report — form submission orchestration

## Status

Implemented the smallest safe extraction: validation, participant-email
resolution, bundle duplicate preflight, and answer mapping now run through a
typed `FormSubmissionOrchestrator`. The controller still owns HTTP guards,
validation redirects and secret-safe old input, flash messages, transactions,
quota reservation, after-commit dispatch/delay, and success/fill redirects.

No production/deployment configuration, credentials, migration source/test,
portal route/controller, or pre-existing dirty form-builder/backend form-draft
path was changed. All PHP commands ran in the local `d_form_app` container.

## Files changed by Task 5

- `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php`
- `app/Services/Registration/FormSubmissionContext.php`
- `app/Services/Registration/FormSubmissionPayload.php`
- `app/Services/Registration/FormSubmissionOrchestrator.php`
- `tests/Feature/Forms/FormSubmissionControllerCharacterizationTest.php`
- `docs/big-changes/patching-ticket/EP-08.md`
- This report

The following pre-existing dirty paths were left untouched:

- `app/Services/Upload/UploadInspector.php`
- `resources/js/components/core/ConfirmationModal.vue`
- `tests/Feature/Security/UploadContentValidationTest.php`
- `tests/Unit/Rules/SafeUploadFileTest.php`

## Characterization/anomaly coverage

`FormSubmissionControllerCharacterizationTest` is named with `BE_P3_002` and
covers:

- single mode and successful non-owner redirect;
- other mode without quota reservation;
- team mode and after-commit confirmation dispatch;
- bundle mode leader/guest answer mapping;
- blank/space-only member email;
- scalar member-email payload and scalar answer payload;
- duplicate submission and quota conflict without extra writes;
- bundle duplicate guest email without extra writes;
- secret-answer old-input redaction.

The existing `FormRegistrationTest` and `FormSubmissionBoundaryTest` remain
unchanged and continue to cover the wider registration, upload, transaction,
and redirect contract.

## Red/green evidence

- Initial characterization run: exit `1`. This was a test-fixture assertion
  mistake: the duplicate form-access guard intentionally redirects without a
  validation-error bag. No production failure was inferred. The assertion was
  corrected before baseline comparison.
- Baseline after corrected characterization tests, before extraction:
  `podman exec -i -w /app d_form_app php artisan test --filter='FormSubmissionControllerCharacterizationTest|FormRegistrationTest' --env=testing`
  exit `0`; 98 tests, 676 assertions passed.
- After extraction, the same focused command: exit `0`; 98 tests, 676
  assertions passed.
- Existing boundary regression suite,
  `podman exec -i -w /app d_form_app php artisan test tests/Feature/Forms/FormSubmissionBoundaryTest.php --env=testing`:
  exit `0`; 32 tests, 163 assertions passed.
- Pint focused check for the controller, three new service/DTO files, and
  characterization test: exit `0`; 5 files passed.
- `git diff --check`: exit `0`.

Both PHPUnit runs emitted the pre-existing PHPUnit doc-comment metadata warning
and the configured redundant-index-drop skip; neither changed the exit status.

## Safety boundaries and preserved behavior

- `FormSubmissionContext` carries typed domain context and an array payload,
  never the raw `Request` object.
- `FormSubmissionPayload` makes mode, mapped answers, normalized participant
  emails, and bundle guest-id resolution explicit.
- Existing `FormSubmissionRules`, `MemberEmailListRules`,
  `BundleGuestDuplicateChecker`, `FormAnswerInputMapper`, and submitter
  contracts remain in use.
- Mode-specific ordering is preserved, including bundle duplicate preflight
  before upload mapping and the existing team mapping order.
- Controller transaction boundaries, duplicate/concurrency guards, quota
  reservations, upload cleanup ownership, after-commit jobs/delays, flash
  messages, and redirects were not moved into the orchestrator.

## Browser check

The assigned browser spec was attempted:

`npm run test:e2e:desktop -- tests/e2e/form-submission-boundary.desktop.spec.ts`

Exit `1`: the local browser harness was unavailable because `E2E_ARTISAN` is
empty. No browser result is claimed. No credentials were inspected or supplied
to bypass this boundary.

## Concerns

- Browser/E2E verification remains pending until the local harness provides
  `E2E_ARTISAN`, `E2E_BASE_URL`, and the explicitly configured test credentials.
- The controller remains responsible for persistence and HTTP translation by
  design; further extraction would broaden scope and risk changing transaction
  or dispatch semantics.

## Fix round 1 — reviewer findings

### Finding 1: participant input boundary

Root cause: the context intentionally carried the request payload for the
validator, but the orchestrator also read `team_member_emails` directly from
that boundary array when calling `MemberEmailListRules::resolve`.

Fix: participant-list rules now validate the boundary value first, and the
orchestrator passes only the validated `team_member_emails` value to
`MemberEmailListRules::resolve`. The context comment now explicitly documents
that its array is validator-boundary input, not a post-validation data source.

### Finding 2: after-commit evidence

The characterization test now asserts the dispatched
`SendRegistrationConfirmationJob` has `afterCommit === true`, rather than only
asserting that a job was pushed.

### Fix-round verification commands and output

1. Command:

   `podman exec -i -w /app d_form_app php artisan test --filter='FormSubmissionControllerCharacterizationTest|FormRegistrationTest' --env=testing`

   Exit: `0`

   Output: `Tests: 98 passed (676 assertions)`; duration `21.74s`.
   The existing PHPUnit doc-comment metadata warning and redundant-index-drop
   skip were emitted and did not affect the exit status.

2. Command:

   `podman exec -i -w /app d_form_app php artisan test tests/Feature/Forms/FormSubmissionBoundaryTest.php --env=testing`

   Exit: `0`

   Output: `Tests: 32 passed (163 assertions)`; duration `4.59s`.
   The configured redundant-index-drop skip was emitted and did not affect the
   exit status.

3. Command:

   `podman exec -i -w /app d_form_app ./vendor/bin/pint --test app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php app/Services/Registration/FormSubmissionContext.php app/Services/Registration/FormSubmissionOrchestrator.php app/Services/Registration/FormSubmissionPayload.php tests/Feature/Forms/FormSubmissionControllerCharacterizationTest.php`

   Exit: `0`

   Output: `PASS` under `PSR 12`, `5 files`.

4. Command:

   `git diff --check`

   Exit: `0`; no output.

All fix-round PHP commands ran only in the local `d_form_app` container. The
unrelated dirty paths present before this fix round, including the form-builder
and frontend redesign paths, remain untouched.
