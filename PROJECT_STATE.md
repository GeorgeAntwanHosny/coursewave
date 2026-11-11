<div align="center">

# 📍 CourseWave: Project Overview

**A subscription learning platform that splits every payment fairly among instructors, built in 13 phases, each shipped with automated tests.**

`Last updated: 2026-11-08` · `Status: all 13 phases complete`

</div>

---

## 🎯 What it is

Students subscribe once (monthly, 3-month or annual) and access every instructor's courses. Each payment is recognized day by day, **split by what the student actually learned**, minus a platform fee, and **paid out to instructors through Stripe**, accurately and exactly once. See [`docs/PAYMENTS_DESIGN.md`](docs/PAYMENTS_DESIGN.md).

## 🧰 Stack

| Layer | Technology |
|---|---|
| Student app | Next.js 16, TypeScript, Tailwind, shadcn/ui, TanStack Query, Zustand |
| Instructor and admin | Filament 3 (two panels on one Laravel app) |
| API | Laravel 13, PHP 8.4, Sanctum |
| Data | PostgreSQL 17 (partitioned ledger), Redis |
| Real-time | Laravel Reverb and Echo |
| Payments | Stripe Connect behind `PaymentGatewayInterface` |
| Infra | Docker Compose, nginx, php-fpm, supervisord, Caddy |

## 📊 At a glance

| | |
|---|---|
| Phases | **13** |
| Automated backend tests | **≈ 240** |
| Containers | **9** (one API image in five roles) |
| Roles | student, instructor, admin |
| Scale target | 500K subscriptions, tens of millions of records |

---

## ✅ What was built

| # | Phase | What it delivers | Tests |
|:-:|---|---|--:|
| 1 | **Auth, roles, panels** | Registration and login, three roles, two Filament panels, route protection, light and dark theme | 7 |
| 2 | **Course content** | Courses, modules, lessons (video or text), resource links, quizzes, public catalog | 4 |
| 3 | **Subscriptions** | Plans, Stripe Checkout, webhook-authoritative confirmation, plan switching | 14 |
| 4 | **Consumption tracking** | Watch and read heartbeats, lesson progress, quiz taking and grading, learning UI | 16 |
| 5 | **Revenue recognition and ledger** | Daily recognition slices, largest-remainder split, partitioned append-only ledger, rebuildable balances | 10 |
| 6 | **Payouts** | Exactly-once claims, provider idempotency, uncertain-state reconciliation, attempt logs, Stripe Connect onboarding | 20 |
| 7 | **Refunds** | Self-service cancel, refund of unused days only, idempotent provider calls, admin tools | 15 |
| 8 | **Comments** | Threaded discussions on every lesson, instructor replies highlighted, moderation | 14 |
| 9 | **Certificates** | Completion detection, PDF generation, public verification page | 24 |
| 10 | **Notifications** | Stored and live over WebSockets, three surfaces (student app and both panels) | 40 |
| 11 | **Live sessions** | Scheduling, countdown and join window, at-most-once reminders | 39 |
| 12 | **Analytics** | Instructor and admin dashboards, deep-dive analytics, attention lists, demo-data seeder | 38 |
| 13 | **Dockerization** | One-command demo and production, secrets generator, smoke tests, HTTPS overlay, landing page | n/a |

---

## 🧠 Key architecture decisions

| Decision | Why |
|---|---|
| **Deferred revenue, recognized daily** | Simple, fair refunds; no instructor clawback |
| **Append-only ledger as the only source of truth** | Full audit trail; balances rebuildable at any time |
| **Idempotency keys everywhere money moves** | Retries and parallel runs can never double-pay |
| **Payout claimed by `INSERT` with a unique constraint** | A database constraint cannot be raced; a lock can expire |
| **"Uncertain" provider outcome is its own state** | Silence is never assumed to be success or failure |
| **Consumption-weighted split, largest-remainder** | Pay follows real value; exact to the cent |
| **`PaymentGatewayInterface`** | A second gateway is a new class, not a rewrite |
| **Webhook-authoritative subscriptions** | The browser redirect is never trusted |
| **Ownership scoped at query and policy layers** | Instructors only ever see their own data |
| **Analytics from services, widgets only format** | Every dashboard figure is testable without the UI |
| **Notifications that cannot break their trigger** | Sent after commit, exactly once, through `SafeNotify` |
| **One image, many roles** | One build; web, workers, scheduler and WebSockets never drift |
| **Exactly one scheduler** | Money jobs run once |
| **Demo and production are separate modes** | Demo seeders refuse to run in production |

## 📐 Engineering conventions

- Money is always `bigint` cents
- Every ledger write shares a transaction with the state change it represents
- Every endpoint returns `{ success, message, data, errors }`
- Every form has zod and a Form Request, a disabled submit while loading, and four explicit states
- Every feature ships its PHPUnit tests and Postman entry before frontend work starts
- Every UI component is responsive and supports light and dark mode
- Loading uses skeletons that match the final layout; errors surface as toasts

---

## 🚀 Running it

```powershell
.\scripts\setup.ps1 -Mode demo          # realistic data, all seeders
.\scripts\setup.ps1 -Mode production    # clean stack, one admin
.\scripts\smoke-test.ps1                # prove it is healthy
```

Full guide: [`DOCKER.md`](DOCKER.md).

## 🗺️ Roadmap

Ideas for the next iterations:

- Filament toast popups for live notifications
- Resend guard for payout reconciliation, matching refunds
- Stripe webhooks for refund updates and payout-account changes
- Subscription auto-renewal
- Instructor dispute and appeal flow
- A student "my learning" summary (hours, streaks)
- CSV export and a custom date picker for analytics
- A daily analytics rollup table at very large scale
- Object storage for certificate PDFs and a zero-downtime deploy