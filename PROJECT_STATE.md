# 📍 LearnLedger — Project State

> **Read this before doing anything else.** This file is the single source of truth for what has actually been built, exactly how, and what's next. See `AI_SESSION_WORKFLOW.md` for how to use it at the start and end of a session.

**Last Updated:** 2026-09-26
**Current Phase:** Phase 0 — Monorepo scaffolded, API↔web connection wired (not yet verified live — see note below)
**Phase Status:** Requirements gathered, architecture decided (see `README.md` → Payments & Payout Architecture). Monorepo structure created: `apps/api` (real Laravel 13.17 skeleton) and `apps/web` (Next.js 16, TypeScript, Tailwind — builds clean). A `/api/ping` route + CORS config were added on the API side and a matching fetch on the homepage, so the connection can be verified as soon as `composer install` can run. This file is the seed for Phase 1.

**⚠️ Open blocker:** `apps/api` has no `vendor/` yet. This sandbox's network policy blocks `repo.packagist.org` (Composer's package registry), so `composer install` cannot run here. Run it in an environment with normal internet access:
```bash
cd apps/api && composer install && cp .env.example .env && php artisan key:generate
cd ../web && cp .env.local.example .env.local
```
Then `php artisan serve` (apps/api) + `npm run dev` (apps/web) and open `http://localhost:3000` — it should show a green "✅ Connected" card pulling JSON from the Laravel API.

---

## 🧱 Project Snapshot

- **What it is:** Subscription-based LMS. Students pay upfront (monthly/3-month/annual) for platform-wide course access; subscription revenue is split among instructors by actual student consumption, minus a platform fee; instructors are paid out on a schedule through an external payment provider. See `README.md` for the full feature list and the complete payments/payout design.
- **Stack:** Next.js (students) + Laravel (API + two Filament panels: `/instructor`, `/admin`) + PostgreSQL (partitioned ledger/event tables) + Redis + Laravel Reverb + Stripe (behind a `PaymentGatewayInterface`, second gateway addable later).
- **Structure:** Monorepo — `apps/web` (Next.js), `apps/api` (Laravel, hosts both Filament panels).
- **Why this shape:** See the starter kit this project was seeded from (`NEW_PROJECT_STARTER_KIT.md`) for the reusable stack/workflow decisions, and `README.md` for everything specific to this project — especially the deferred-revenue recognition model, which is the load-bearing decision the rest of the billing system depends on.

---

## ✅ Completed Work Log

*(Empty — nothing built yet. First rows land after Phase 1.)*

| Phase | Area | File(s) | Function / Endpoint / Component | What it does | Test | Postman | Date |
|---|---|---|---|---|---|---|---|
| 0 | Scaffold | `apps/api/` | Laravel 13.17 skeleton (real, from `laravel/laravel@13.x`) | Base app structure — `vendor/` not yet installed, see blocker above | — | — | 2026-09-26 |
| 0 | Scaffold | `apps/web/` | Next.js 16 (App Router, TS, Tailwind) | Student frontend base; `npm run build` verified clean | — | — | 2026-09-26 |
| 0 | Connection test | `apps/api/routes/api.php`, `apps/api/config/cors.php`, `apps/web/app/page.tsx` | `GET /api/ping` | Returns JSON `{message, app, time}`; homepage fetches it client-side and shows connected/error state | Manual (pending `composer install`) | — | 2026-09-26 |

---

## 🧠 Architecture & Library Decisions Log

**Carried forward from the starter kit** (see `NEW_PROJECT_STARTER_KIT.md` for full reasoning): integer-cents money, never floats · nginx + php-fpm + supervisord in Docker, never `artisan serve` · `clear_env=no` on every fpm pool · entrypoint never falls back to `.env.example` · `spatie/laravel-permission` checks are guard-aware, and this project has *two* legitimate guards on purpose (`web` for both Filament panels, `sanctum` for the student API) · `Cache::remember()` always `->toArray()` first · `queryClient.clear()` on every auth transition.

**New for this project:**

- **Deferred revenue recognition, not upfront.** The full rationale is in `README.md` §2 — this is the single decision the rest of the billing design depends on. Do not "simplify" this later without updating the refund logic (§7) that depends on it.
- **The ledger is append-only and is the only source of truth for money owed/paid.** No table, dashboard, or job may ever `UPDATE` a balance column. `instructor_balances` is a derived, rebuildable projection only — if code is ever found writing to it directly instead of deriving it from `ledger_entries`, that's a bug.
- **Every money-moving operation carries a persisted idempotency key** — recognition slices (`UNIQUE subscription_id, recognition_date`), ledger entries (`UNIQUE idempotency_key`), payout batches (`UNIQUE instructor_id, period_start, period_end`), and the provider call itself (`gateway_idempotency_key`, generated once, never regenerated on retry). Four independent layers, on purpose — any one of them alone would eventually get raced.
- **A payment provider timeout is its own state (`uncertain`), never coerced to success or failure.** Reconciliation happens by looking the transfer up via the stored idempotency key, not by guessing or blindly retrying.
- **Consumption weighting uses watched-seconds/read-completion per instructor per day**, not course count or a flat split — because "the instructors involved" in a given day's slice is a genuinely different set depending on what the student actually did that day.
- **Uneven splits use the largest-remainder method** (`README.md` §4) — never "give the remainder to whoever's first," which would be a small but real fairness bug repeated on every single recognition slice, every day, across 500K subscriptions.
- **Two Filament panels on one Laravel app** (`/instructor`, `/admin`), not two separate installs — shared spatie/laravel-permission + Filament Shield, distinguished by panel-level role gates.
- **`PaymentGatewayInterface` abstraction from day one**, even with only Stripe implemented — the requirement to support a second gateway later is a now-decision (interface shape), not a later-decision (which gateway).

---

## 🧩 General Conventions & Gotchas (carry forward to every future phase)

1. **Money is always `bigint` cents.** No float, no decimal column for anything that gets summed or split. Convert to display currency only at the presentation layer.
2. **Every recognition slice is created up front, at subscription-creation time**, as `pending` rows — the daily job only ever *advances* existing rows, never computes slice boundaries on the fly. This is what keeps the idempotency story simple.
3. **Never write a `ledger_entries` row outside of the same DB transaction as the state change it represents** (slice → recognized, batch → succeeded, etc.) — partial writes here are exactly the class of bug the whole design exists to prevent.
4. **A `payout_batches` row is claimed by `INSERT`, not by `SELECT` then `UPDATE`.** The unique constraint is the concurrency guard; a lock is defense-in-depth, not the primary mechanism.
5. **The `gateway_idempotency_key` on a payout batch is generated exactly once, at batch creation, and never regenerated on retry.** Regenerating it on retry would defeat the entire point of it.
6. **`consumption_events` is append-only** — it's an event log, not a current-state table. `lesson_progress` (current-state, for UI progress bars) is a separate, derived table updated from these events, not the same table wearing two hats.
7. **Comments are polymorphic on the commentable content (video or text lesson)**, with `parent_id` for threaded replies. Whether a reply is "from the instructor" is derived from the course's instructor relationship at query time, not trusted from client input — a denormalized flag may be cached for query performance, but the relationship is the source of truth.
8. **Refund voiding only ever touches `pending` slices.** A slice that's already `recognized` is never voided or clawed back — see the Decisions Log entry above.
9. **`instructor_balances` (or any other aggregate/projection table) must be rebuildable from `ledger_entries` alone**, from scratch, at any time. If a bug ever makes that untrue, the projection design is wrong, not just buggy.

---

## 🐛 Known Issues / Open Design Questions

Stated honestly, since these are deliberate simplifications, not oversights — revisit if real usage shows they matter:

- **Refund/recognition granularity is daily**, not sub-day. A refund requested mid-day still lets that day's slice recognize normally if the job already ran. Acceptable for a subscription product; would need revisiting for anything priced hourly.
- **Zero-consumption-day resolution policy** (README §3.4: unresolved slices roll forward, unattributed-forever slices retain as platform revenue) is a defensible default, not something the spec dictated — confirm this matches actual business intent before it's load-bearing in production.
- **No instructor-side payout dispute/appeal flow yet** — a `failed` batch is "flagged for manual review," but there's no defined admin workflow for resolving one yet.
- **No handling yet for an instructor leaving/being removed mid-term** with already-accrued-but-unpaid balances — needs a decision (pay out final balance immediately? hold per normal schedule?) before Phase 6.
- **Multi-currency is out of scope** as currently designed — `amount_cents` assumes a single platform currency. Would need a currency column + no-mixed-currency-arithmetic guard before supporting more.

---

## ➡️ Next Steps

Proposed phase breakdown — confirm before starting Phase 1:

1. **Phase 1 — Auth, profiles, roles.** Users (student/instructor/admin), name/picture/email, spatie/laravel-permission roles, both Filament panels scaffolded with Shield.
2. **Phase 2 — Course content (Filament instructor panel).** Courses → modules → lessons (video/text), resource links, quizzes. No student-facing consumption yet.
3. **Phase 3 — Subscription intake.** Plans, checkout, `subscriptions` + `revenue_recognition_slices` tables, Stripe charge on signup. No recognition or payouts yet — just getting money in correctly.
4. **Phase 4 — Consumption tracking + student progress.** `consumption_events`, `lesson_progress`, student-facing progress UI. This unblocks Phase 5's weighting.
5. **Phase 5 — Revenue recognition + ledger.** The daily recognition job, `ledger_entries`, largest-remainder split. The core of the whole system — budget real review time here.
6. **Phase 6 — Payout runs + provider integration + reconciliation.** `payout_batches`, `PaymentGatewayInterface` + `StripeGateway`, the uncertain/reconciliation state machine.
7. **Phase 7 — Refunds.** Slice voiding, provider refund call, admin-triggered flow.
8. **Phase 8 — Comments & replies**, instructor-highlighted, per lesson.
9. **Phase 9 — Certificates.** PDF generation job on course completion.
10. **Phase 10 — Notifications (Reverb).** Hybrid persisted-and-live, reusing the pattern proven on the previous project.
11. **Phase 11 — Live sessions.** Scheduling + notification fan-out to enrolled students.
12. **Phase 12 — Analytics dashboards.** Instructor (own courses/earnings), admin (platform-wide), built on the `instructor_balances` projection and progress data.
13. **Phase 13 — Dockerization.** Apply the starter kit's Docker defaults from day one this time — no rediscovery needed.

---

## ⚙️ Environment Notes

*(To be filled in once Phase 1 scaffolds the repo — placeholder structure only for now.)*

- `apps/web` → Next.js, student-facing
- `apps/api` → Laravel, hosts `/instructor` and `/admin` Filament panels plus the public REST API
- Reverb (WebSocket) — port TBD at scaffold time
- Payment provider secrets, `PLATFORM_FEE_PERCENT`, per-plan pricing — define in `.env.example` at Phase 3
