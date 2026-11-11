# EP-07 — Frontend UX & Flows

| Issue | Commit |
| --- | --- |
| EP-07 | Pending — lane work is uncommitted |

Implemented the 16-ticket EP-07 frontend remediation across dialog hit-testing, QR scan boundaries, event search/list flows, viewport observer cleanup, accessibility names, recruitment/docs/form decomposition, OPRec draft/clipboard behavior, focus directive registration, and per-layout topbar state.

Verification: lint, typecheck, unit tests (71 files / 786 tests), integration/feature tests (85 tests / 494 assertions), E2E typecheck, container build, bundle budget, and diff check passed. Full browser suites were invoked but could not complete because the local E2E server served a Vite asset host excluded by its CSP; see `docs/big-changes/patching-ticket/EP-07/patching-ticket.md`.
