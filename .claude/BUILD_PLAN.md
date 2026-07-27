# Build Plan — Social CRM & Marketing Automation Platform

Reference application audited: `https://multicrm.delyntro.com` (Delyntro, v14.5), logged in as `rgcsm.pb@gmail.com` on 2026-07-27.

This is the index. Detailed docs live in `build-plan/`:

- [01 — Feature & Module Inventory](build-plan/01-feature-inventory.md)
- [02 — Recommended Tech Stack](build-plan/02-tech-stack.md)
- [03 — Architecture](build-plan/03-architecture.md)
- [04 — Core Data Model](build-plan/04-data-model.md)
- [05 — Third-Party Integrations](build-plan/05-integrations.md)
- [06 — Build Roadmap (Phases)](build-plan/06-roadmap.md)
- [07 — Reseller / White-Label Model](build-plan/07-reseller-model.md)
- [08 — Employees & Roles (Super Admin org + Reseller org)](build-plan/08-employees-roles.md)

## What this reference app is

A white-label, multi-tenant SaaS platform that unifies messaging channels (WhatsApp, Meta Cloud API, social DMs), voice (cloud telephony + AI voice agents), email marketing, support ticketing, a sales CRM/pipeline, lightweight e-commerce, and AI chat/automation — all under one dashboard, sold on a subscription/trial model with per-account expiry.

## Confirmed decisions
- **Multi-tenancy model: two-tier white-label reseller** (Super Admin → Reseller → Client → Users). This is now baked into Phase 0 of the roadmap and the architecture/data-model docs — see `07-reseller-model.md` for full detail. It is *not* deferred to a later phase, since retrofitting a reseller hierarchy onto a flat multi-tenant schema later is a costly migration.
- **Reseller billing: reseller collects their own client payments** via Stripe Connect (their own bank account) — the platform never touches that money. Platform→reseller fees are billed separately and centrally by you.
- **v1 scope confirmed: CRM + WhatsApp**, on top of the reseller-aware foundation (Phase 0) — see `06-roadmap.md` Phase 1. Everything else (email, support tickets, commerce, social, voice, AI) comes in later phases.
- **Employee/role model confirmed**: both the Super Admin org and each Reseller org get their own staff with fixed, tier-appropriate roles (not a custom permission builder for v1) — see `08-employees-roles.md`.
- **Tech stack confirmed: Laravel + MySQL + Redis + Inertia.js/Vue 3/Vuetify** (see `02-tech-stack.md`).
- **WhatsApp: both connection types from the start** — official Meta Cloud API *and* an unofficial QR-session bridge (e.g. Baileys). See `05-integrations.md` for what this means for v1 build scope.

## Next steps (discuss and confirm before building)

1. Decide which integrations beyond WhatsApp are **must-have for launch** vs. later (SMTP for transactional email is cheap/simple and likely needed even in v1 for account emails; CallerDesk telephony and AI voice are out of scope until Phase 4).
2. Ready to start scaffolding the Laravel project (Phase 0: reseller hierarchy + auth + tenant scoping) whenever you say go.
