# TuitionHub — Product Decisions Register

**Status:** Living register. Entries marked **Approved** are the source of truth for sprint planning.
**Recorded:** 2026-10-19 (sprint start, Monday)
**Supersedes:** the phase plans in `docs/reference/TuitionHub_Business_Plan.md`, `docs/reference/TuitionHub_Details.md` and the `[P1]/[P2]/[P3]` tags in `docs/reference/TuitionHub_ER_Schema.md` wherever they conflict with an approved decision below. C1–C7 are closed; do not reopen them without a new founder decision recorded here.

Related: `docs/API_CONTRACT.md` (draft API), `docs/reference/TuitionHub_ER_Schema.md` (canonical starting schema).

---

## Sprint timing

- Sprint planning starts **2026-10-19**. The nominal four-week window ends **2026-11-15**.
- The Claude Pro subscription start/end dates have **not** been supplied. Plans are written as Day 1, Day 2, … and must not claim to fit a subscription end date.
- **Founder to insert:** Claude Pro expiry date = `____-__-__`.

---

## C1 — Sprint product scope — **Approved**

**Decision.** The target is a demo-able product in a four-week sprint, not the old year-long phase plan. The required operational journey is:

`institute → class → teacher → student enrolled → session → attendance recorded → parent sees attendance`

Also in sprint:

- Minimal public class catalogue with filters for subject, grade and district.
- Local development OTP (see OTP decision below).
- Invoice/commission demo and a commission-accrual admin view.
- Demo-only boost purchase **record**.
- Fictional seed data.
- Prepared in parallel (non-code): investor pitch deck, one-pager, demo video, market research, legal research.

**Clarification — catalogue vs marketplace.** Minimal catalogue browsing and filtering is in sprint. The **full marketplace is deferred to Month 3+**: reviews, ratings, featured placement/slots, paid ranking/placement, advanced/natural ranking and broader marketplace functionality.

**Implications.**
- The boost-purchase API only records a clearly labelled demo purchase. It must not activate, or influence, catalogue placement, ordering or ranking.
- Preserve the current MagicPattern UI; connect existing screens to the API rather than redesigning them.
- Marketplace tables (`class_listings`, `reviews`, `review_responses`, `featured_listings`, `saved_listings`, `search_queries`) are not built in sprint.

## C2 — Future money flow — **Approved**

**Decision.** The future business model is platform collection: the parent pays the institute's tuition fee plus the TuitionHub commission; TuitionHub would eventually collect the total, owe the institute its agreed fee and retain the commission.

**Implications for this sprint.**
- Only a mock/demo payment flow and a database ledger are enabled.
- No real money collection, no institute payout, no settlement scheduling, no real merchant-account routing.
- No payment-gateway integration, no webhooks from real gateways.

## C3 — Commission and invoice rules — **Approved**

**Decision.**
- Commission is added **on top of** the institute's advertised class fee and must be shown transparently before checkout.
- Starting default: **Rs 100 flat** for the demo example. Support a configurable flat amount or a percentage rule for future higher-value classes. A class-specific rule overrides an institute rule, which overrides the platform default.
- Worked example: institute fee Rs 2,500 + TuitionHub fee Rs 100 = parent total Rs 2,600; amount owed to institute Rs 2,500.
- **No universal 2% rule.** Do not copy `institute_settings.platform_commission_pct = 2.0` or `parent_pays_commission` from the canonical schema.
- Do not display or imply a payout/settlement date.
- Admin commission rows and all simulated payments are labelled `DEMO`/`TEST`. Accrued commission is not collected revenue or profit and must not be presented as such.
- Fee amounts and commission rules are **snapshotted** on each invoice/payment; later rule or fee changes never alter existing invoices or payments.

**Implications.** Invoice money fields: `subtotal` = institute tuition due; `platform_fee` = TuitionHub commission; `total = subtotal + platform_fee`. Payment `amount = total`, `platform_fee = commission`, `net_to_institute = subtotal` (amount owed, not settled). See `docs/API_CONTRACT.md` §11.

## C4 — Stack and repository — **Approved**

**Decision.**
- One monorepo. The existing React + TypeScript + Vite + Tailwind frontend stays at the repository root.
- Backend: Laravel 13 under `/api`, PHP 8.3+, MySQL, Laravel Sanctum for first-party SPA cookie/session auth.
- No Flutter during the sprint.
- No broad frontend restructure or UI redesign.

**Implications.** Sanctum is used in SPA (stateful cookie) mode; no mobile bearer-token flow in sprint. Exact framework/runtime versions are subject to C9 verification.

## C5 — Canonical schema — **Approved**

**Decision.**
- `docs/reference/TuitionHub_ER_Schema.md` is the canonical starting schema.
- `docs/reference/TuitionHub_Details.md` Part II and `docs/reference/TuitionHub_ER_Schema (1).html` are reference/legacy only, not authoritative.
- Implement only the sprint subset; do not build a table just because it exists in the canonical schema.
- The canonical schema does not specify every nullability/default and contains old settlement and 2%-commission assumptions. Adaptations are documented (see `docs/API_CONTRACT.md` §2); incompatible fields are never copied silently.
- No settlement/payout schema in this sprint.

## C6 — Guardian links and minors — **Approved**

**Decision.**
- Remove the automatic 72-hour child account lockout/restriction rule.
- Institute staff create the student record and enrol the student. A parent links using a **single-use, expiring invitation code**.
- Acceptance requires an authenticated guardian account whose verified phone matches the invited phone. Codes are stored hashed, are single-use and expire.
- Every parent→child data read is enforced on the Laravel backend through an **active** link. Links record `linked_by_user_id`, `linked_at`, `revoked_by_user_id`, `revoked_at`, plus an audit event. Revocation history is preserved (no hard deletes).
- No student/guardian ID uploads, NIC collection, selfie verification or real minor data in the demo. Fictional data only.
- OTP in local log mode is **not** real phone verification and must never be described as such.

**Implications.** The prototype still contains 72-hour copy and state (e.g. `src/pages/auth/StudentSignup.tsx`, `src/pages/auth/Auth.tsx`, `src/components/home/StudentSection.tsx`, `src/pages/student/ConnectGuardian.tsx`, `src/types/index.ts`, `src/data/accounts.ts`). Removing it is a separate, approved frontend task — not done in this docs change.

## C7 — Study / social features — **Approved**

**Decision.** Study, student meetups, map-based social discovery and in-app chat are excluded from the sprint. Do not delete their prototype files, but they must not be exposed in active navigation or treated as live features. Do not implement Study/chat tables or APIs.

**Implications.** The prototype currently exposes `/study` and `/messages` in `studentNav` (`src/components/app/navConfig.tsx`) and routes them in `src/App.tsx`. Hiding these from active navigation is a separate frontend task — not done in this docs change.

---

## OTP approach — **Approved**

- Provider-based OTP service behind an `OTP_DRIVER` setting.
- Local development uses `OTP_DRIVER=log`: a random six-digit code is written to the local Laravel log/terminal only.
- The code is **never** returned in an API response and **never** stored in plaintext (hash only).
- Enforce expiry, attempt limits, resend cooldown and rate limits.
- The `log` driver **fails closed** when `APP_ENV=production`. If a real SMS provider fails, the request fails — never silently fall back to logs.
- The login UI shows a visible `DEV MODE — no SMS is sent` banner **only** when local dev OTP mode is enabled.
- Real SMS (Dialog / Notify.lk) is an optional stretch goal, not a baseline dependency.

## No-external-blocker rule — **Approved**

Every sprint deliverable must be demonstrable without any external account, API key, merchant approval, SMS sender-ID registration or third-party sign-off. External integrations (SMS, payment gateways, maps) are optional stretch work behind drivers/flags; their absence must never block the sprint baseline.

---

## Known unresolved follow-ups (not silently decided)

### C8 — `users.type` vs multi-role `user_roles` — **Open, needs founder confirmation**

The canonical schema has both a single `users.type` discriminator and per-institute `user_roles`. `docs/API_CONTRACT.md` **proposes** `user_roles` (scoped by `institute_id`) as authoritative for authorisation, with `users.type` dropped or treated as non-authoritative. Founder confirmation is required **before** final role migrations and policies are written. Until then, role/permission mappings in the API contract are proposals.

### C9 — Framework/runtime versions — **Environment verification, not a product decision**

Laravel 13 / PHP 8.3+ / MySQL / Sanctum versions must be verified against the actual local environment and current official documentation before scaffolding. This is a technical check, not a scope decision.

Status (2026-10-27): Verified locally; one check pending (Windows PHP security build) — see docs/DECISION_LOG.md D-003.

### Other open items raised while drafting the API contract

These are recorded so they are not silently decided; current proposals are in `docs/API_CONTRACT.md`.

1. **Exams/marks screens.** Exams are excluded from the sprint API, but the prototype exposes Exams/Marks entries in parent, student, teacher and institute navigation. Whether to hide them or label them "coming soon" needs a decision before the frontend-connection task.
2. **Teacher assignment consent.** The contract assigns an existing teacher profile to an institute immediately. Whether the teacher must accept (invitation flow) is open.
3. **Who may bill / revoke.** Mapping of "billing staff" and "institute manager" onto the canonical roles is a proposal pending C8.
4. **Same child at two institutes.** Students are institute-managed records in sprint; cross-institute de-duplication/merge is deferred.
