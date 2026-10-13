# TuitionHub — Claude Code Project Instructions

## Mission
Build a secure, maintainable, working TuitionHub product for the Sri Lankan tuition industry. Use the approved product documents and existing repository as the source of truth.

## Read these before major work
- `docs/PRODUCT_DECISIONS.md` — latest approved product decisions; these supersede older conflicting descriptions.
- `docs/ER_SCHEMA.md` — data model reference.
- `docs/SETUP_GUIDE.md` — current project setup notes; verify version-sensitive instructions before relying on them.
- `docs/TUITIONHUB_DETAILS.md` and `docs/BUSINESS_PLAN.md` — product/business context.
- `docs/ARCHITECTURE.md`, `docs/API_CONTRACT.md`, `docs/SECURITY_PLAN.md`, and `docs/DECISION_LOG.md` if present.

If these paths differ in the actual repo, locate the equivalent files and update this instruction file. Do not assume a listed file exists until verified.

## UI/UX boundaries
- The existing MagicPattern frontend is a prototype with an established visual direction, not a frozen final product.
- Preserve its overall brand, global colour palette, typography, spacing language, navigation and shared component patterns by default.
- You may add missing product features, interactions, loading/empty/error/success states, form validation, responsive fixes, accessibility improvements and local screen refinements.
- Prefer targeted improvements and reusing existing components over rebuilding screens.
- Do not make global theme changes or a wholesale redesign without first explaining the problem, evidence, affected screens and smallest viable alternative, then waiting for founder approval.
- Do not present mock data or inactive buttons as finished functionality.

## Engineering rules
- Inspect existing code before choosing architecture or editing files.
- For multi-file changes, start with a plan and list files, migrations, API changes, permissions, edge cases and tests. Wait for approval before implementation when the change is high-risk or broad.
- Prefer small, reviewable tasks over broad rewrites.
- Follow established framework conventions and verify current official documentation for version-sensitive package/framework decisions.
- Keep business logic out of UI components where possible; validate all critical inputs on the server.
- Enforce authorization server-side for every protected resource and record. Test cross-user, cross-role and cross-institute access.
- Use database transactions for multi-step writes. Use idempotency for payment/webhook operations where applicable.
- Treat payment, settlement, refund and audit history carefully. Never simulate a real successful payment from a client-side button.
- Use public UUIDs for public routes when the data model specifies them; do not expose internal IDs unnecessarily.
- Do not invent product behaviour. Record contradictions and unresolved questions in the decision log rather than silently choosing.
- Never hardcode credentials, commit `.env`, expose secret tokens, or use real student/parent/payment data in tests.
- Do not run destructive database commands, broad migrations, major dependency upgrades or destructive Git operations without explaining the impact and getting approval.

## Workflow
1. Check `git status` and confirm the current branch before making changes.
2. Read relevant docs and inspect adjacent code/tests.
3. For complex work, use plan/read-only mode and present a file-by-file plan.
4. Implement one coherent scope; avoid unrelated refactors.
5. Add/update unit, feature, API and authorization tests as appropriate.
6. Run relevant tests, linters, builds and browser checks where available.
7. Review the diff for security and regressions; report what was not tested.
8. Update docs/DECISION_LOG.md when an approved product or technical decision changes.
9. Summarize changed files, commands run, test results, risks and next steps. Never claim success without evidence.

## Definition of done
A task is done only when its acceptance criteria are met, server validation/authorization are covered, relevant tests pass, the diff is reviewable, UI behaviour is checked where applicable, and known limitations are documented.
