# 02 — Recommended Tech Stack

## Signals from the reference app
- Clean, extension-less routes (`/crm`, `/email_marketing`, `/support_system/tickets`) — server-side routed, not a pure SPA
- Explicit cron-job requirement for bulk email (`/email_marketing_cron` hit every minute) — classic PHP/Laravel SaaS-script pattern, not a queue-native Node/Python setup
- Plan/trial expiry per account, "Account manager" module, "System Auto Updater" — typical of CodeCanyon-style Laravel SaaS boilerplates
- Heavy multi-API integration surface (WhatsApp Cloud API, telephony, email, social graph APIs, AI voice/chat) — favors a mature backend ecosystem with strong HTTP client/queue/job tooling

Given that, here is the recommended stack. This is a default, not a mandate — confirm before committing.

## Backend
- **Laravel 12 (PHP 8.2+)** — API-only backend. Best fit: first-class queues, scheduler (replaces manual cron per feature), Eloquent ORM, huge package ecosystem for PDF invoices, WhatsApp SDKs, etc.
- **Laravel Sanctum** for auth — SPA cookie-based sessions for the first-party dashboard, personal access tokens for third-party/API & Automation consumers (same underlying API, two auth modes)
- **Laravel Horizon** for monitoring Redis-backed queues (bulk WhatsApp/email sends, call campaign dialing, social post scheduling all need background workers)
- **Laravel Scheduler** replaces the manual "set up a cron every 1 minute" instruction the reference app requires — one system cron entry (`* * * * * php artisan schedule:run`) drives everything internally

## Frontend — CONFIRMED: fully decoupled SPA
- **Vue 3 + Vue Router + Pinia + Vuetify**, built and deployed as a separate application from Laravel — not Inertia.js.
  - **Why decoupled over Inertia:** this platform already requires a first-class public REST API (the "API & Automation" module — third-party integrations, Zapier-style automations, and the reseller ecosystem all depend on it). A decoupled SPA means the web dashboard is just one more consumer of that same versioned API, rather than maintaining Inertia-driven routes *and* a separate public API side by side. It also fits the white-label reseller model better — a reseller could eventually host their own branded frontend build against the same shared API.
  - **Vue Router** handles all client-side navigation/pages (replaces what Inertia would otherwise provide)
  - **Pinia** for global state (auth/session, current account context, cross-page data like unread counts) — needed here since there's no server-driven page-props flow
  - **Vuetify** still the component library: data tables (sort/filter/paginate), forms with validation, modals, nav drawers, date pickers, dark/light theming — matches the CRM's UI needs far better than Bootstrap's thinner, less Vue-native bindings
  - **Tradeoff accepted:** CORS configuration, Sanctum SPA auth setup, and API-versioning discipline are real ongoing overhead compared to Inertia — but avoids building two access patterns (UI routes + public API) to the same business logic
- **Plain Blade + Tailwind** only for the handful of pages Laravel itself must render directly (e.g. Sanctum's CSRF cookie bootstrap, any server-rendered email templates) — the marketing/login/dashboard UI all lives in the Vue SPA

## Database
- **MySQL 8** (matches ecosystem norms for this class of app, cheap hosting compatibility) or **PostgreSQL** if you want stronger JSON/full-text features — either works with Laravel equally well
- **Redis** for queues, cache, and session store

## Real-time
- **Laravel Reverb** (or Pusher/Soketi) for WebSocket push: live chat, omnichannel inbox, live call transcription, real-time dashboard counters

## File storage
- S3-compatible object storage (AWS S3, Cloudflare R2, or Backblaze B2) behind Laravel's filesystem abstraction — needed for call recordings, media library, invoices, imported CSVs

## Multi-tenancy
- Single database, `account_id`/`tenant_id` foreign key + global query scopes (simplest to build and to migrate later) rather than database-per-tenant, unless you already know you need hard data isolation for compliance reasons

## Third-party SaaS dependencies (see doc 05 for detail)
- Meta WhatsApp Cloud API, an unofficial WhatsApp bridge (e.g. Baileys, if you want the QR-session style "Whatsapp" module too), CallerDesk or similar telephony API, an LLM provider for AI chat/appointments/scoring, SMTP relay(s), Facebook/Instagram Graph API, WooCommerce REST API, Google Sheets API

## Why not Node/Next.js or Django instead?
Either would work technically. The recommendation favors Laravel specifically because:
1. The reference app's own behavior (manual cron, clean non-JSON routes, plan/trial gating) strongly resembles Laravel-based SaaS scripts, so tooling/packages for "clone this kind of app" are most mature there.
2. A huge amount of this feature set (invoices, tickets, CRM, subscriptions) has proven, maintained Laravel packages, cutting build time significantly versus hand-rolling in Node/Django.

If the team's existing skill set is strongly Node or Python instead, say so — that's a legitimate reason to override this default, and the architecture doc's concepts (queues, scheduler, real-time layer, tenant scoping) map directly onto NestJS+BullMQ or Django+Celery equally well.
