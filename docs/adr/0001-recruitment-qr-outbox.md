# ADR-0001: Durable recruitment QR dispatch and ambiguous delivery recovery

- **Status:** Accepted with an operational residual
- **Date:** 10 November 2026
- **Context:** EP-02 recruitment QR bulk delivery

## Context

Bulk QR email delivery needs a durable record of each intended send, controlled spacing, and protection against duplicate queue jobs. A database transaction cannot atomically commit with an external queue or SMTP provider. In particular, a worker can stop after SMTP accepts a message but before the application records that the message was sent.

## Decision

- Persist each QR delivery intent in `recruitment_qr_dispatch_outboxes`, keyed by its `email_logs` row, with states `pending`, `queued`, `sending`, `sent`, and `failed`.
- Create the email log and outbox intent in the same application database transaction.
- Dispatch only due `pending` rows in bounded batches (maximum 100 per poll), preserving the configured schedule horizon and send spacing.
- Let the job claim `sending` before contacting SMTP, then persist `sent` or a public-safe failure state. The delivery key and outbox claim prevent a repeated queue job from creating another delivery attempt while the state is already in flight.
- Return known pre-acceptance transport failures to the queue's retry path. If a later attempt finds an ambiguous interrupted `sending` state, fail closed rather than automatically sending again. An operator must reconcile the delivery and initiate a resend when appropriate.

## Operations added after implementation review

- **No scheduler exists in this repository** (`CONTEXT.md` §3/§14.5), so the request path enqueues
  every intent it reserves itself. `recruitment:dispatch-qr-outbox` stays the recovery net for
  intents left `pending`, and the QR page load also runs the bounded recovery behind a cache lock
  (at most one poll at a time, same batch/horizon as the command). A page load can therefore never
  flood the queue: it drains at most one batch of 100 intents inside the 60 s horizon per poll.
- **Lost `queued` job recovery.** A `queued` intent whose job disappeared (Redis flush,
  `queue:clear`, undecodable payload) is detected by `available_at` older than the grace window
  (`recruitment.qr_queued_stale_seconds`, default 900 s) and re-enqueued under a lease: the row is
  leased to `now` so it is re-queued once per grace window, never on every poll. Re-push is safe
  because the job claim (`queued -> sending`) is a row-locked conditional update: if the original
  job ever appears, one of the two wins the claim and the other returns without sending.
- **Cross-process spacing.** The first slot of each bulk send/poll is reserved under
  `Cache::lock('recruitment-qr-slot-lock')` against a monotonic pointer (floored by the durable DB
  anchor), so two bulk sends for different periods cannot start on the same slot. If the lock
  cannot be taken the send degrades to the DB anchor only: spacing may be tighter, delivery can
  still not be duplicated because the outbox claim is authoritative.
- **Operator reconciliation is a real operation.** `recruitment:reconcile-qr-outbox {emailLogId}
  {sent|failed}` atomically writes the terminal state to the outbox and its `email_logs` row, and
  refuses any row that is not `sending`. This is the missing half of the fail-closed rule: without
  it a stuck `sending` row kept the email log `queued` forever, which excluded the applicant from
  `eligible()` permanently and never drained the queue badge.
- **Read plans.** The pending poll orders by `available_at` only (extra tiebreakers forced a
  filesort over the whole match set; ties are irrelevant because the claim is idempotent). The
  anchor is read as two single-status index scans; `status IN (...)` filesorts. `staleSendingCount()`
  rides the `status` prefix of `(status, available_at)` and filters `updated_at` afterwards; there
  is no `(status, updated_at)` index.
- **Rollback cost.** `down()` of the `tracking_token_generation` migration drops a column, which on
  MariaDB 10.11 rebuilds `recruitment_applications` with `ALGORITHM=COPY` (blocking, ~table+index
  size of temporary disk at 1M rows). Treat it as an emergency-only, maintenance-window operation.

## How to run the MySQL concurrency and plan suite

`tests/Feature/Recruitment/RecruitmentQrOutboxClaimConcurrencyTest.php` and the EXPLAIN plan guard
in `RecruitmentQrDispatchRecoveryTest` need a real MySQL engine and skip themselves under the default
SQLite suite. The harness is committed as `phpunit.race.xml`:

```bash
podman exec -i d_form_db mysql -uroot -psecret -e "CREATE DATABASE IF NOT EXISTS d_form_test"
podman exec -e DB_DATABASE=d_form_test -w /app d_form_app php artisan migrate --force
podman exec -w /app d_form_app ./vendor/bin/phpunit -c phpunit.race.xml --filter='RecruitmentQr'
```

The tests only run against a database whose name ends in `_test`, `_testing`, or `_e2e`; any other
name makes them skip, so they cannot touch a shared database. Invariants asserted there: at most one
dispatch per outbox row; `dispatch_attempts` increments at most once per claim; a concurrent claim
waits on the row lock instead of succeeding; the pending poll plan is covering and filesort-free.

## Alternatives considered

1. **Dispatch directly from the bulk request without an outbox.** Rejected because a process/queue failure could lose the intent after the request returns.
2. **Automatically retry every stale `sending` row.** Rejected because SMTP may already have accepted the message, so retry can create a duplicate.
3. **Claim exactly-once delivery using a database transaction around SMTP.** Rejected as infeasible for generic SMTP: the application database cannot participate in the provider's transaction.
4. **Provider idempotency key or delivery acknowledgement/webhook.** Deferred until the configured provider exposes a suitable contract and operational support.

## Consequences

- Pending intents can be recovered in bounded polls and duplicate queue jobs are guarded by durable state.
- The system offers neither exactly-once nor guaranteed at-least-once delivery for an ambiguous SMTP outcome. An interrupted `sending` row can require manual reconciliation/resend.
- Operators need visibility into pending, failed, and long-lived `sending` rows. Production scheduler topology and failed-job driver must be verified before deployment.
- Bulk selection still materializes the eligible recipient set and reserves rows individually.
  Measured (SQLite `:memory:`, `Queue::fake()`, 7 s spacing): 25 recipients → 159 queries / 0.07 s;
  1000 → 6009 queries / 4.0 MB peak / 2.07 s, i.e. ~6 queries and ~4 KB per recipient, linear. A
  20k-applicant period is therefore inferable at ~120k queries, ~40 s and ~80 MB inside the HTTP
  request. Accepted for now (a bulk send of that size is an operator action, and the ADR requires a
  benchmark before a rewrite). Three identified reductions, each of which must preserve
  `submitted_at` ordering and per-row spacing: (1) select ids only and chunk the reservation loop,
  (2) fold the eligibility recheck into the locking read to save one query per candidate, (3) move
  the reservation loop into a queued job. Note that an offset-paginated `lazy()` is incorrect here
  because inserting `email_logs` shrinks the predicate and would skip rows.
