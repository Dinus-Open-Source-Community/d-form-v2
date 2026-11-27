# AGENTS.md: d-form-v2

## Mandatory reading

Read these before writing code:

- `docs/rules/general.md`: git flow, conventional commits, PR, environment and security.
- `docs/rules/front-end.md`: Vue, TypeScript, component, layout, accessibility, and data flow rules.
- `docs/rules/front-end-structure.md`: frontend folder structure, dependency direction, naming, and per-folder restrictions.
- `docs/rules/back-end.md`: Laravel 12, controller, request, model, observer, policy, storage.
- `CONTEXT.md`: project context covering architecture, domain, flows, and data schema. Read it every session. Do not ask again about anything already answered there.

### User rules (13 items, binding on all new and moved code)

1. Prefix `I`/`G`/`T` for interfaces, generics, and type aliases.
2. Use TypeScript utilities (`Pick`/`Omit`/`Partial`/`Readonly`/`ReturnType`) instead of duplicating types. Derive variants from the source type (`Partial<T>`, `Record<keyof T, ...>`), do not retype them.
3. `any` is forbidden. `unknown` is allowed only at a third-party boundary that is genuinely unknown, and must be narrowed immediately with a guard or runtime validation. Internal responses with a known contract must use explicit types.
4. Non-null assertion (`value!`) is forbidden. Use `?` and nullable handling: early return, fallback, or guard. The logical `!` operator and `!==` comparisons remain allowed.
5. Optimal and readable: avoid repeated allocation or serialization on hot paths.
6. Reusable and deduplicated: search for an existing implementation before writing a new one by symbol, path, or grep. One duplication leads to one hook, one `lib/` function, or one cross-cutting layer shared by every caller. Reusable behavior must live in one place, not be copied per task. Examples: the global rate limiter, input sanitization, query sanitization.
7. When a function takes more than three parameters, takes unrelated parameters, or the parameter combination is hard to read, use an object argument. The parameter count is a heuristic, not an absolute ban.
8. Use domain-specific names (`useBannerFilePicker`, `formatRupiahPrice`), not `useFile` or `formatDate2`.
9. Use human-readable names, not AI-slop names.
10. One function, one responsibility, one reason to change. Compose through calls. God functions are forbidden.
11. Softcode by default. Softcoding is required for scattered magic numbers, strings duplicated across files, and environment configuration. A hardcoded value shared across call sites needs a written one-line reason.
12. AI-slop names are forbidden: `foo`, `bar`, `tmp2`, `data2`, vague abbreviations, and `-Util`/`-Helper`/`-Manager` suffixes.
13. Write short documentation for exported functions, public class methods, functions with side effects, preconditions and invariants, non-obvious algorithms, and adapter boundaries. Trivial functions whose name and type already explain the behavior do not need a comment.

### Engineering principles

- **Best practice and optimal** apply to both code and feature behavior. When a better option exists, take it. When it is rejected, record the reason in one line (see Gate step 2).
- Success is **not measured in lines of code**. Many lines is not optimal, few lines is not optimal. What matters: performance, optimization, consistency, maintainability, scalability.
- **Follow existing patterns.** Introduce a new pattern only when the current one cannot meet the need, and record why.
- **Deduplication is not renaming.** Removing duplication by renaming a function is forbidden. Deduplication means one implementation, not one name.
- Avoid **if hell and nested conditionals**, **N+1 queries**, and complexity that creates tech debt. Measure Big-O on large data paths before writing.

### Typing (frontend)

- `strict: true`, with explicit types on every parameter and return.
- `unknown` is not used for internal responses with a known contract. At a genuinely unknown third-party boundary, `unknown` must be narrowed immediately with a guard or runtime validation.
- Type assertions (`as`) are forbidden when they only silence a type error. They are allowed after runtime validation, at a generated or library boundary, or as `as const` for literal inference. Record the reason next to the code, or in the changelog when it affects architecture.
- No suppression tricks (`@ts-ignore`, `@ts-expect-error`, `eslint-disable`, `noqa`). Fix the root cause.
- Every `.vue` file must use `<script setup lang="ts">`. Options API is forbidden.

### Frontend architecture

- `.vue` files are UI only. `<script setup>` calls hooks, maps props to child components, and binds events. The layout is chosen by page domain. There is no requirement to name it `AppLayout`.
- `resources/js/hooks/` is the only home for stateful logic. Name files `useXxx`, use named exports, and no default export in new files.
- Complex object factories are named `createXxx` and live in builder or lib modules, not in hooks.
- **Builder exception:** factories and pure guards tied to the form builder lifecycle may be colocated in `resources/js/components/modules/builder/` (`formBuilderFieldFactory.ts`, `formBuilderPalette.ts`, `fieldMapping.ts`, `formBanner.ts`, `autosaveGuard.ts`, `dirtyFields.ts`, `optionImage.ts`). General pure helpers still belong in `lib/`.
- Imports keep the `@/` alias.
- Frontend structure, dependency direction, naming, and restrictions follow `docs/rules/front-end-structure.md`.
- Destructive actions (deleting files or data, installing or uninstalling packages) require a plan and explicit user confirmation first.

### Code splitting (frontend)

Required for frontend refactors. The target: no single oversized JS bundle; routes and heavy features load when used.

- **Route level:** `import.meta.glob('./pages/**/*.vue')` without `eager` for lazy page resolution. The target is route-level loading. The bundler may create shared chunks, so any claim that one page always becomes one chunk must be proven from build output. `eager: true` is forbidden.
- **Heavy vendors only in the chunk that renders them:** Tiptap, `chart.js`/`vue-chartjs`, `html5-qrcode`, `lottie`/`vue3-lottie`. Import them only from the page or component that renders them. Static imports from `app.js`, `bootstrap.js`, a layout, or `components/core/` are forbidden. Use `defineAsyncComponent` or a dynamic import.
- **Heavy components inside a page** (form builder, broadcast composer, scanner, chart) load asynchronously when they are not visible on first paint.
- **Shared vendors stay cacheable together** (Vue, Inertia, Reka UI or shadcn) through bundler configuration that is proven not to duplicate dependencies per page. `manualChunks` is used based on measurements, not as a default that can make a shared chunk too large.
- **Prefetch:** Inertia prefetch only for links that are likely used. Prefetching every page is forbidden.
- **Required evidence:** entry size plus the three largest chunks, before and after `npm run build` (run inside the container), recorded in the changelog. No "faster" claim without numbers.
- **Budget:** entry JS at or below 350 kB gzip. No page chunk above 200 kB gzip. A violation must be split further, or carry a one-line reason.

### Folder-based placement

One folder, one content role. Code that is not that folder's responsibility must not be placed there, even when it is related.

| Folder                                                                     | Role                       | Allowed content                                                                        | Forbidden                                                               |
| :------------------------------------------------------------------------- | :------------------------- | :------------------------------------------------------------------------------------- | :---------------------------------------------------------------------- |
| `resources/js/types/`                                                      | Cross-file type contract   | `interface` (`I…`), `type` (`T…`), type-only enum or union, `declare module`, `*.d.ts` | Runtime values, runtime enum or object, functions, `ref`, loose types   |
| `resources/js/lib/`                                                        | General pure functions     | Deterministic helpers and utilities (`formatRupiahPrice`, `isHttpUrl`)                 | `ref`/`watch`/lifecycle, DOM, `localStorage`, side effects              |
| `resources/js/hooks/`                                                      | Stateful logic             | `useXxx` composables with `__tests__` beside them, `index.ts` barrel                   | Pure functions without state, `.vue` components, type-only declarations |
| `resources/js/constants/`                                                  | Runtime constants          | Static cross-domain configuration values without behavior                              | Hooks, requests, queries, mutable global state                          |
| `resources/js/adapters/`                                                   | Third-party/browser seam   | `http/`, `browser/`, `scanner/`, `third-party/` adapters                               | Business rules, pages, layouts, pass-through wrappers without a seam    |
| `resources/js/plugins/`                                                    | Global Vue plugins         | Global plugin registration that is actually used                                       | Feature logic, data fetching, eager page-only vendors                   |
| `resources/js/directives/`                                                 | Custom Vue directives      | Reusable element-level directives that are actually used                               | Requests, business rules, redirects, authorization                      |
| `resources/js/components/core/`                                            | Global reusable components | Presentational components with explicit props and emits                                | Data fetching, business logic, heavy transformation                     |
| `resources/js/components/layout/`                                          | Layout support             | Navbar, sidebar, footer, user menu                                                     | Components used outside `layouts/`                                      |
| `resources/js/components/modules/`                                         | Feature components         | Components owned by one module                                                         | Cross-feature components (move them to `core/`)                         |
| `resources/js/components/ui/`                                              | Shadcn-Vue sources         | Library files as shipped                                                               | Direct edits; customize through `core/`                                 |
| `resources/js/layouts/`                                                    | Page layout templates      | `AuthenticatedLayout`, `GuestLayout`, `AdminLayout`                                    | Feature logic, module data fetching                                     |
| `resources/js/pages/`                                                      | Route-level pages          | Props to component mapping, `<Head>`                                                   | Reusable components (move them to `core/` or `modules/`)                |
| `resources/js/routes/`, `resources/js/actions/`, `resources/js/wayfinder/` | Framework output           | Generated files                                                                        | Manual edits                                                            |

- Types shared across files go to `types/`. Local types stay in the file that uses them.
- `resources/js/utils/` is legacy only. New files must not be created there. New pure functions go to `lib/`, new stateful composables go to `hooks/`, and cross-file contracts go to `types/`. Legacy files move only during a migration scope or when touched for a relevant fix.
- Folders outside the table must not be used to park code. A new folder needs a written one-line reason in the changelog.
- Non-shadcn components already inside `components/ui/**` are historical tech debt. Do not add more and do not use them as examples.
- Do not create global `helpers/`, `common/`, `misc/`, `services/`, or `data/` folders as dumping grounds. Create a domain module, or use an established seam.
- `pages/`, `layouts/`, and `components/core/` must not hold domain business logic. `components/ui/` must not be edited directly.
- `types/` must not contain runtime objects, runtime enums, factories, or side effects. `lib/` must not touch reactive state, DOM, storage, or network.
- `hooks/` must not import pages and must not become a home for pure utilities without state.
- `pages/` and components must not call third-party or browser APIs directly when a matching adapter exists.
- `adapters/` may import third-party and browser APIs, but must not import pages, layouts, components, or domain business rules. `unknown` must be narrowed inside the adapter.
- `plugins/` and `directives/` are conditional. Do not create them for a single local configuration, or for behavior that belongs in `bootstrap`, a component, or a hook.
- Do not create an `index.ts` barrel in every folder. Barrels are allowed only on stable public seams.
- Do not create global `services/`, `repositories/`, `helpers/`, `common/`, `misc/`, `api/`, `mappers/`, `transformers/`, `managers/`, or `controllers/` folders as dumping grounds.
- The backend follows `docs/rules/back-end.md`. This table covers `resources/js` only.

### Agent workflow

- Classify every work item before planning: `feature`, `bug`, `security`, `performance`, `refactor`, `migration`, `infrastructure`, `documentation`, or `dependency`.
- Change flow: scope and classification, reconnaissance and evidence, dependency and caller mapping with risk notes, brainstorming, best-practice and trade-off review, writing the plan, user approval, tests and regression tests, implementation, validation, code review, documentation and Plane update, commit, monitoring, then rollback or follow-up when indicators fail.
- Feature work and behavior changes use `brainstorming`, then `writing-plans` with user approval, then TDD, then execution. Bugs use `systematic-debugging` before any change.
- **Split work across subagents** by capability: recon, implementation, review, test. One agent must not do the whole task. Every lane states its write scope, the evidence already gathered, and who owns validation (see Skill and MCP usage).
- **Ask early and often.** Guessing, inventing APIs, or writing code from assumptions is forbidden. When a requirement is unclear, ask before writing. Do not persuade with claims that lack evidence. The agent is the team lead: split work, set the standard, validate results. Details are in Verification before delivery.
- **Do not commit automatically.** Finish the work, leave it in the working tree, and report the changed files. Run `git add` or `git commit` only when the user asks. When asked, make one commit per completed work item, not one per step. This keeps the worktree history short.
- **Run every PHP command inside the container.** Use Docker or Podman depending on the active environment, but keep one runtime per session. Running project PHP from the host is forbidden. Tools missing from the image (for example `k6`) may run on the host.
- Stage specific paths with `git add <path>`. Never use `git add -A`.
- Never commit `Makefile` or `database/seeders/UserSeeder.php`.
- Commits are English only, without emoji, and follow Conventional Commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `perf:`, or `security:`.
- No push unless asked.
- Changes to `AGENTS.md` or any `SKILL.md` must be shown and confirmed by the user first.

### Gate before writing code

The order applies according to the change classification. A gate that does not apply needs a written reason.

1. **Minimum tests by classification:**
    - UI or user flow: Playwright E2E.
    - Controller or API: feature or integration test.
    - Pure function: unit test.
    - Query or database: query test plus `EXPLAIN`.
    - Queue or job: job test plus retry and idempotency test.
    - Security: regression test plus authorization test.
    - Refactor without behavior change: existing suite plus regression check.
    - Styling, copy, docs: lint plus visual verification.
2. **Decision review.** The plan or changelog must state whether the solution is best practice and optimal, and which alternatives were rejected with reasons.
3. **Implementation.** Production code is written after the plan is approved and the minimum tests exist.
4. **Stress test when risky:** public or sensitive endpoints, list, export, or report paths, locks or counters, uploads, batch processing, queries on large domain tables, queue or scheduler changes, and anything affecting concurrency or idempotency.
5. For small changes outside the stress criteria, a regression test and the relevant query plan are enough.

Exception: typos, copy, pure styling, and documentation need lint plus visual verification only.

### Definition of Done

A change is done when:

- behavior matches the acceptance criteria;
- the relevant tests pass;
- no open Critical or High finding remains;
- the relevant lint, typecheck, and build pass;
- security impact has been reviewed;
- performance impact is measured where relevant;
- migrations have a clear forward path and rollback or mitigation;
- the relevant GitHub Actions or CI run finished, or its failure has evidence and a recorded reason;
- E2E is complete for UI and user-flow changes;
- the developer changelog in `docs/developer/<developer>/` is written;
- the Plane work item is updated;
- the change is left uncommitted until the user asks for a commit;
- no secret or local dataset is included in the commit.

### Verification before delivery

Run the checks that match the change and report every command with its exit status. A claim without a command and its result is not evidence.

1. `./vendor/bin/pint --test` inside the container, plus `npx prettier --check <changed files>`.
2. `npm run lint`, `npm run typecheck`, `npm run typecheck:unit`.
3. `npm run test:unit` for frontend unit tests.
4. `php artisan test` inside the container (`podman exec -i -w /app d_form_app php artisan test`, or the Docker equivalent) for PHP unit and feature (integration) tests. Use `--testsuite=Unit` or `--testsuite=Feature` while iterating, but run both before delivery.
5. `npm run test:e2e` for Playwright. Run `npm run test:e2e:mobile` too when the change touches UI. Run `npm run test:e2e:types` when E2E files change.
6. `npm run build`.

Rules:

- CI is the source of truth. Local checks must match the jobs in `.github/workflows/quality.yml`. A green local run does not excuse a failing CI, and a failing CI must be fixed before the work is reported done.
- Playwright is the only E2E tool. Drive the real UI through `npm run test:e2e` or the `playwright` MCP. Do not guess UI behavior.
- When a check fails, fix the root cause. Suppression is forbidden: no `@ts-ignore`, `eslint-disable`, `noqa`, skipped tests, weakened assertions, or reruns used to hide flakiness.
- If the test itself is wrong, fix the test and state why in the work item or commit.
- Re-run every suite affected by the change. A change in shared code (`lib/`, hooks, adapters, policies, services) requires the full suite.
- Code must pass CI and follow the existing rules and patterns. Do not add a dependency, abstraction, or configuration just to make a check pass.
- Do not guess. If a requirement, command, API, or expected behavior is unclear, ask the user before writing code. State the open question and the options you see.
- No over-engineering. Take the smallest change that fully solves the task. No speculative options, wrappers, extension points, or "for later" code.
- Ask early and often. Several small questions cost less than one wrong implementation. Do not fill gaps with assumptions.
- Leave the finished change uncommitted. Report the changed files and the verification results, then wait for the commit instruction.

### Evidence level

Every audit or review finding must carry an evidence level:

- `Confirmed`: proven from source or tests.
- `Reproduced`: reproduced successfully.
- `Measured`: backed by numbers or a benchmark.
- `Inferred`: conclusion drawn from several pieces of evidence.
- `Needs verification`: cannot be proven without a specific runtime or dataset.

### Exception handling and response contract

- `400`: malformed request.
- `401`: missing or invalid authentication.
- `403`: identified user without permission.
- `404`: resource not found.
- `405`: unsupported HTTP method.
- `409`: conflict or duplicate state.
- `413`: content too large.
- `415`: unsupported media type.
- `422`: request content cannot be processed or validation failed.
- `429`: rate limit.
- `500`: internal failure without a more specific mapping.
- `502`: upstream gateway returned an invalid response.
- `503`: service temporarily unavailable or overloaded.
- `504`: upstream timeout.

Other codes follow the [IANA HTTP Status Code Registry](https://www.iana.org/assignments/http-status-codes). Unlisted codes must not be created or used without an official standard. `419` Laravel Page Expired is a framework-specific exception and must be documented as such. Internal errors must not be sent raw to users. Every endpoint must describe its error shape, its validation fields, and the difference between Inertia and JSON responses.

### Observability

Observability is recommended in general, and required during development, audit, debugging, and incident reproduction for the path being worked on:

- request and correlation ID;
- structured logging;
- job ID and attempt logging;
- redaction of tokens, passwords, sensitive files, and PII;
- public-safe error messages;
- no swallowed exception without a log entry.

Global metrics such as latency, error rate, queue lag, and failed jobs are required when the tooling exists, or when the path is a high-risk endpoint.

### Migration, queue, and dependency safety

- Risky schema changes use expand, migrate, contract.
- Avoid blocking operations on large tables. Consider index size and lock behavior.
- Migrations must not depend on dummy data.
- Destructive migrations need a backup and rollback plan plus explicit approval.
- Jobs need clear retry and backoff, timeout, observability, and idempotency.
- A new package needs a reason, an owner, license and vulnerability review, and bundle impact review for frontend dependencies.
- The lockfile must change consistently with the manifest.
- Removed dependencies must be recorded before removal.
- Source code must not be sent to external services without approval and a secret check.

### Waiver process

A rule exception must record the rule broken, the technical reason, the risk, the alternatives considered, the owner, and a review date. A waiver must not cover missing tests or an error that is not yet understood.

### Backend

- Security is mandatory, not optional: validation in Form Requests, authorization on every endpoint through policy or middleware, parameter binding without SQL string interpolation, output escaping, CSRF, upload type and size whitelists, and secrets only from config or environment.
- Public and sensitive endpoints need rate limits. The limiter is one global layer shared by every caller (see user rule 6).
- Validation happens at the request boundary. Normalization belongs to a value object or input mapper. Encoding follows the output context. SQL uses parameter binding or the query builder. Shared validation or normalization may be centralized, but do not build one global sanitizer that becomes a god function.
- Treat output according to context: HTML text, HTML attribute, URL, JavaScript, JSON, or sanitized rich HTML.
- Middleware handles broad area access, roles, and feature flags. Form Requests handle authorization tied to the input or the requested resource. Policies handle resource and domain rules. Controllers only call authorization that has not run yet. Do not duplicate checks without a reason.
- External side effects such as email, webhooks, file deletion, and queue dispatch that depend on committed data must use after-commit behavior. Jobs must be safe against retries and duplicates.
- Before adding a query: check N+1 with eager loading, check indexes on filter and sort columns, and check the execution plan on large data.

### Seeder and test data

- Seeders must use **factories** from `database/factories/`. Do not write literal rows in a seeder.
- **Exception:** `database/seeders/Stress/**` uses the bulk query builder instead of factories because the target volume is one million rows per table. Factories remain required for regular seeders.
- Stress data has one entry point: `php artisan stress:seed` (`database/seeders/Stress/**`). The default scale is **1,000,000 rows per domain table**, adjustable through `STRESS_SCALE`.
- **Domain tables** must hold one million rows with valid relations: `users`, `events`, `forms`, `form_fields`, `form_answers`, `event_attendances`, `email_logs`, `email_datasets`, `email_dataset_recipients`, `email_broadcasts`, `email_broadcast_recipients`, `email_broadcast_attachments`, and every `recruitment_*` table that stores transaction data.
- **Reference and infrastructure tables** keep small, realistic counts instead of one million: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`, `recruitment_divisions`, `recruitment_email_templates`, `recruitment_registration_sequences`, `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`. Reason: blowing up permission pivots or fabricating queues and sessions does not test domain queries. (`event_categories` is not a table. Event category is the `events.category` column.)
- The seeder writes a manifest of sample IDs to `storage/app/stress/manifest.json` for the harness in `scripts/stress/`.
- Large datasets are generated locally on demand and **never committed**. `UserSeeder.php` is never committed either.

### Change documentation

- Write one changelog per work item in `docs/developer/<developer>/`. The folder name is the developer handle, for example `sapto` or `nafan`.
- File name follows the pattern already used in that folder: `<developer>Changes<DD-MM-YYYY>-DFORM-<N>.md`. One file per work item.
- Fill the file before reporting the task done. Required content:
    - work item reference and the planned commit (the hash is filled after the manual commit)
    - date and developer
    - what changed, grouped by area: backend, frontend, database, config
    - why it changed, including rejected alternatives and the reason
    - evidence: commands run, exit status, test counts, and build size when relevant
    - risks, rollback, and follow-up
    - the files and migrations a reviewer should read first
- Add one line to `docs/developer/<developer>/README.md` linking the file, when that folder has a README.
- Do not rewrite the god document `saptoChanges27-09-2026.md`.
- Keep the changelog with the change. Do not commit it separately from the code it describes.

### Plane

- Plane is the only issue tracker.
- Agents may create work items, fill fields, comment, and move an item up to **Ready to QA**.
- Agents must not move an item to **Done**. QA moves it after verification, or the human owner closes it when no QA applies.
- Moving an item to **Cancelled**, archiving, or deleting a work item, module, or project requires explicit approval.
- Work items follow the shape used in `docs/plane/backlog-dform.md`: summary, scope, acceptance criteria, technical notes, references.
- Test steps belong in the Regression Checklist page, not repeated inside every work item.

## Agent skills

### Skill and MCP usage

Agents and subagents must use the available assets before working manually.

- **Skills:** load the relevant native skill (`brainstorming`, `writing-plans`, `systematic-debugging`, `test-driven-development`, `impeccable`/`better-interface`, `playwright-cli`, `code-review`) instead of inventing a process.
- **Code map:** use `graphify` when `graphify-out/graph.json` exists. Verify symbols, callers, and snippets through `codebase-memory-mcp`. Use grep only for string literals, error messages, config, non-code files, or when the graph fails. Grep is not the first step.
- **Browser and E2E:** use the `playwright` or `agent-browser` MCP, do not guess UI behavior.
- **Cheapest sufficient tier:** scout for a quick provisional lookup, verify as the default, auditor for negative or exhaustive claims. Include `check_index_coverage` for every cited path.
- **Subagents:** only for genuinely separate work. State the write scope, the graph or tier evidence already gathered, and the validation owner. Continue the same session for revisions. Do not spawn a new one for the same purpose.
- **Evidence, not claims:** read every subagent result from its artifact before reporting it as complete.
- **Optimal:** one task, one cheapest sufficient tool. Do not call heavy MCP tools repeatedly for data already in context.

### Issue tracker

Plane is the primary and only tracker for all future work.

### Triage and labels

- Intake items land in the `Triage` state. The intake owner accepts, declines, snoozes, or marks them as duplicates.
- Work item labels: `frontend`, `backend`, `database`, `infra`, `bug`, `security`, `performance`, `tech-debt`, `ux-copy`, `blocked`, `needs-spec`, `needs-test`, `qa-found`, `customer-reported`, `du`.
- Use one layer label plus at most two other labels per item. Do not use module names as labels.
- `blocked` and `needs-spec` require a reason in the comment and are removed once resolved.

### Domain docs

Single context: one `CONTEXT.md` at the root, mandatory reading as stated above. ADRs go to `docs/adr/` when the first architecture decision is made. The folder is created at that moment, not earlier.
