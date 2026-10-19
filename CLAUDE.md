# TuitionHub — Claude Code Project Instructions

## Mission
Build a secure, maintainable, working TuitionHub product for the Sri Lankan tuition industry. Use the approved product documents and existing repository as the source of truth.

## Sources of truth
- `docs/PRODUCT_DECISIONS.md` — **source of truth for approved sprint scope** (C1–C7, OTP approach, no-external-blocker rule) and the register of open items (C8, C9, others). Approved decisions supersede older phase plans and conflicting descriptions.
- `docs/API_CONTRACT.md` — **draft** `/api/v1` contract, sprint table groups and documented schema adaptations. Changes to it need approval and a changelog entry.
- `docs/reference/TuitionHub_ER_Schema.md` — **canonical starting schema**. Implement only the sprint subset and follow the adaptations in the API contract; never silently copy incompatible fields (e.g. 2% commission, settlement columns).
- Reference/legacy only (not authoritative): `docs/reference/TuitionHub_ER_Schema (1).html`, Part II of `docs/reference/TuitionHub_Details.md`.
- Context: `docs/reference/TuitionHub_Details.md`, `docs/reference/TuitionHub_Business_Plan.md`, `docs/reference/TuitionHub_Setup_Guide.md` (verify version-sensitive setup instructions before relying on them), `docs/reference/TuitionHub_Claude_Workflow_Guide.md`.

`docs/reference/` files are local working copies and may be untracked in Git. Do not assume any other doc exists until verified; if a needed document is missing, stop and say which one rather than recreating it.

## Repository layout (monorepo)
- Existing React + TypeScript + Vite + Tailwind frontend (MagicPattern prototype) stays at the **repository root** (`src/`, `package.json`, `vite.config.ts`, …).
- Laravel 13 API (PHP 8.3+, MySQL, Sanctum SPA cookie auth) lives under **`/api`**. Exact versions are verified against the environment and official docs first (C9).
- No broad frontend restructure.

## Sprint baseline
- Four-week demo sprint starting 2026-10-19. Core journey: institute → class → teacher → student enrolled → session → attendance recorded → parent sees attendance; plus minimal public catalogue (subject/grade/district filters), local dev OTP, invoice/commission demo, commission-accrual admin view, demo-only boost purchase record, fictional seed data.
- **Excluded from the sprint:** Flutter/mobile apps; Study, meetups, map-based social discovery and chat; exams/marks; full marketplace (reviews, ratings, featured placement, paid or advanced ranking); real payment collection, gateways, settlements and payouts; real SMS as a dependency.
- Every deliverable must be demonstrable without external accounts, keys or approvals (no-external-blocker rule).

## Approval gate
- Do not write application code, change architecture, scaffold Laravel, install packages, or create/run database migrations until the specific plan or task has been explicitly approved.
- Do not resolve open items (e.g. C8 `users.type` vs `user_roles`) silently; record contradictions and questions in the open-items section of `docs/PRODUCT_DECISIONS.md` and ask.

## UI/UX boundaries
- The existing MagicPattern frontend is a prototype with an established visual direction, not a frozen final product.
- Preserve its overall brand, global colour palette, typography, spacing language, navigation and shared component patterns by default. Connect existing screens to the API rather than redesigning them.
- You may add missing product features, interactions, loading/empty/error/success states, form validation, responsive fixes, accessibility improvements and local screen refinements.
- Prefer targeted improvements and reusing existing components over rebuilding screens.
- Do not make global theme changes or a wholesale redesign without first explaining the problem, evidence, affected screens and smallest viable alternative, then waiting for founder approval.
- Do not present mock data or inactive buttons as finished functionality. Excluded features (C7 Study/chat, etc.) must not appear in active navigation as live features; keep their prototype files.

## Engineering rules
- Inspect existing code before choosing architecture or editing files.
- For multi-file changes, start with a plan and list files, migrations, API changes, permissions, edge cases and tests. Wait for approval before implementation when the change is high-risk or broad.
- Prefer small, reviewable tasks over broad rewrites.
- Follow established framework conventions and verify current official documentation for version-sensitive package/framework decisions.
- Keep business logic out of UI components where possible; validate all critical inputs on the server (Form Requests).
- Enforce authorization server-side (Policies/Gates) for every protected resource and record. Scope every tenant-scoped query by institute, assignment or active guardian link **before** returning data. Test cross-user, cross-role and cross-institute access.
- Use database transactions for multi-step writes. Money writes (payment attempts, demo completion, commission accrual, boost purchases) must be idempotent and protected by unique indexes.
- Treat payment, commission and audit history carefully. Snapshot fee and commission values on invoices/payments. Never simulate a real successful payment from a client-side button; demo payment completion is server-flagged and never available in production.
- Label every simulated payment, commission accrual and boost record `DEMO`/`TEST`. Never show or imply payout/settlement dates or present accrued commission as collected revenue.
- Use public UUIDs for public routes; do not expose internal IDs.
- Child privacy: no NIC, ID uploads, selfies or real minor data; parent access only through an active, audited guardian link; dev-log OTP is not real phone verification and must not be described as such.
- OTP and invitation codes are hashed at rest, never returned (except the one-time invitation code to its issuer), never logged outside the local `OTP_DRIVER=log` channel, and the log driver fails closed in production.
- Do not invent product behaviour.
- Never hardcode credentials, commit `.env`, expose secret tokens, or use real student/parent/payment data in tests.
- Do not run destructive database commands, broad migrations, major dependency upgrades or destructive Git operations without explaining the impact and getting approval.

## Workflow
1. Check `git status` and confirm the current branch before making changes. Do not work directly on `main`.
2. Read the relevant docs above and inspect adjacent code/tests.
3. For complex work, use plan/read-only mode and present a file-by-file plan.
4. Implement one coherent scope; avoid unrelated refactors.
5. Add/update unit, feature, API and authorization tests as appropriate.
6. Run relevant tests, linters, builds and browser checks where available.
7. Review the diff for security and regressions; report what was not tested.
8. Update `docs/PRODUCT_DECISIONS.md` when an approved product or technical decision changes, and the `docs/API_CONTRACT.md` changelog when the contract changes.
9. Summarize changed files, commands run, test results, risks and next steps. Never claim success without evidence.

## Definition of done
A task is done only when its acceptance criteria are met, server validation/authorization are covered, relevant tests pass, the diff is reviewable, UI behaviour is checked where applicable, and known limitations are documented.
