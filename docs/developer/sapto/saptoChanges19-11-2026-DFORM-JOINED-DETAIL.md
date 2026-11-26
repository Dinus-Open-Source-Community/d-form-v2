# DFORM — Joined event detail and inline registration

| Issue | Commit |
| --- | --- |
| DFORM-JOINED-DETAIL | Pending |

## Changes

- `/joined/{event_segment}` now carries published registration-form summaries and renders registration inline.
- The legacy picker controller/page, route helper, route skeleton rule, old redirect, and picker-only assertions were removed; both legacy register URLs return 404.
- Existing form fill, submission, invitation, quota, review, participant-form, QR, and manual-code flows remain intact.
- Invalid persisted registration modes now report `unsupported_registration_mode` at the access boundary instead of appearing available.
- The member detail banner uses eager loading when present and retains an error fallback; the loading skeleton follows the detail page hierarchy.

## Verification

Independent validator run against this working tree:

| Check | Command | Exit | Result |
| --- | --- | --- | --- |
| Focused PHP | `podman exec -w /app d_form_app php artisan test <portal, registration, draft, purpose>` | 0 | 149 passed, 1,223 assertions |
| Pint | `podman exec -w /app d_form_app ./vendor/bin/pint --test <changed files>` | 0 | PASS (PSR 12), 11 files |
| ESLint | `npm run lint` | 0 | clean |
| Typecheck | `npm run typecheck` | 0 | clean |
| E2E spec types | `npm run test:e2e:types` | 0 | clean |
| Prettier (CI scope) | `npx prettier --check "resources/js/**/*.{ts,vue,js}" eslint.config.js "scripts/**/*.{js,mjs,ts}"` | 0 | all matched files formatted |
| Build | `podman exec -w /app d_form_app node node_modules/vite/bin/vite.js build` | 0 | built |
| SSR build | same with `--ssr` | 0 | built |
| Bundle budget | `node scripts/build/check-bundle-budget.mjs` | 0 | entry 15.7 kB gzip, largest chunk 142.2 kB gzip |
| Vitest | `podman exec -w /app d_form_app node_modules/.bin/vitest run` | 0 | 123 files, 1,241 tests passed |
| Browser E2E | `npx playwright test tests/e2e/joined-event-detail.spec.ts` | 0 | 15 passed (2.0m): desktop + mobile matrix, keyboard focus, breakpoints, banner, check-in |

Host `npm run build`/`npm run test:unit` fail only because the host `node_modules` lacks `@rollup/rollup-linux-x64-gnu`; container Node runs both.

## Status coverage

The inline registration list renders only for unregistered members, so the browser matrix covers the statuses that surface there plus the registered participant section: `allowed`, `not_visible`, `form_closed`, `registration_not_open`, `quota_full`, `unsupported_registration_mode`, `pending_team_confirmation` (invitation precedence), and `prerequisite_not_met`.

Three enum values are deliberately not asserted in the browser:

- `already_submitted` and `event_form_already_chosen` render as the registered detail surface instead of the inline list; both are covered by `FormRegistrationTest` at the access-guard level.
- `invitation_closed` is unreachable. `FormAccessGuard::duplicateOrInvitationStatus()` applies `excludeRejectedSubmissions()` before checking `isInvitationTerminal()`, so terminal member rows never reach that branch. This is required: declining or expiring an invitation deletes the member row so the member can register individually (`FormRegistrationTest::test_member_can_register_individually_after_declining_invitation`). The enum case and its UI label are now dead and need a product decision.

## Notes

Local development uses Podman rather than Docker. Lighthouse results must be measured under the same authenticated conditions and treated as local-dev evidence only; no production performance claim is made from them.
