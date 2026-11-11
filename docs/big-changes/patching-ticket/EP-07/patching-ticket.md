# EP-07 — Frontend UX & Flows

**Tanggal:** 2026-11-11
**Status:** In Review
**Plan:** `plan.md` (approved by user)

## Result

Implemented all 16 EP-07 lanes. `FLOW-P1-001`, `FLOW-P1-002`, and `FLOW-P2-001` were independently verified as resolved/stale. The remaining implementation lanes are source-reviewed and marked In Review.

## Evidence

- `npm run lint` — exit 0.
- `npm run typecheck` — exit 0.
- `npm run typecheck:unit` — exit 0.
- `npm run test:unit` — exit 0, 71 files / 786 tests.
- `podman exec -i -w /app d_form_app php artisan test` (EP-07 integration/feature regression set) — exit 0, 85 tests / 494 assertions.
- `npm run test:e2e:types` — exit 0.
- `podman exec -i -w /app d_form_app npm run build` — exit 0.
- `node scripts/build/check-bundle-budget.mjs` — exit 0; entry 15.6 kB gzip, largest non-entry 143.8 kB gzip.
- `git diff --check` — exit 0.

## Lane summary

- **A / FE-AUDIT-016:** Confirmation modal opts out of ReKa outside-pointer locking; mouse, keyboard, and loading regressions added. Architecture/security review approved. Browser execution is **Needs verification** because the E2E server emitted a stale Vite asset host/CSP mismatch.
- **B / FE-AUDIT-008 + FE-AUDIT-006-F2:** Object-argument QR hook, camera/submission/polling seams, generation guards, formatter extraction, and cleanup tests. Final architecture review approved.
- **C / FLOW-P1-003 + FLOW-P1-002-F1:** Reactive event-list hook, preserved reset dedupe, maxlength/error rendering, and 99/100/101 coverage. Architecture review approved.
- **D / AUDIT-P3-005:** Shared viewport-reveal composable, per-instance disconnect, reduced-motion behavior, and lifecycle tests. Architecture review approved.
- **E / AUDIT-P3-003:** Indonesian accessible names and focused keyboard assertions on the real controls. Review approved; full axe sweep is **Needs verification** because no axe dependency exists.
- **F1/F2 / FE-AUDIT-007:** Responsibility-based recruitment, docs, and event-form fragments with typed props/events and preserved validation styling. Reviews approved.
- **G / FE-AUDIT-005-F1, FE-AUDIT-006-F1/F3, FE-AUDIT-014:** Contract-specific invalid-response copy, truthful clipboard fallback, mixed-type draft serializer/restoration, and shared focus directive. Architecture/security reviews approved.
- **I / FLOW-P2-006:** Per-layout provide/inject topbar state, explicit provider errors, logout reset, and Inertia navigation E2E. Architecture review approved; browser execution is **Needs verification**.
- **H:** `FLOW-P1-001` Resolved, `FLOW-P1-002` Resolved, `FLOW-P2-001` Stale confirmed from committed source/tests.

## Browser gate limitation

The required desktop/mobile suites were invoked with the documented E2E environment. They could not complete: the local PHP server emitted Vite URLs at `192.168.0.170:5173`, while the CSP allowed `localhost:5173`; the browser therefore received the server shell without Vue-rendered controls. Full desktop/mobile runs also encountered pre-existing unrelated auth/form/OTP failures and exceeded the execution deadline. Targeted EP-07 specs were invoked after restarting local Vite, but the asset-host mismatch remained in the served HTML. No production environment was touched.

## Residuals

- **Needs verification:** real-browser execution of EP-07 UI specs after aligning the local Vite asset host with the E2E CSP; camera track lifecycle in a real permission/start-failure browser path; full axe sweep.
- `FLOW-P2-001` remains **Stale**, not reopened.
