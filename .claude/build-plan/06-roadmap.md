# 06 — Build Roadmap (Phases)

Cloning all ~20 modules from the reference app at once is not realistic as a first build. Suggested phased approach — confirm/adjust before starting implementation.

## Phase 0 — Foundation — ✅ COMPLETE
- ✅ Laravel 12 project scaffold (MySQL, Redis/predis, Sanctum SPA+token auth) — decoupled Vue 3 SPA (Router+Pinia+Vuetify)
- ✅ **Reseller hierarchy**: `account_type` (super_admin/reseller/client) + `parent_account_id`, tenant scoping via a global scope on `Account` (+ reusable `BelongsToTenant` trait for future domain models) — verified: Super Admin sees everything, each reseller sees only its own subtree, each client sees only itself (`tests/Feature/TenantScopingTest.php`)
- ✅ **White-label**: `reseller_domains` + `reseller_branding` tables, `ResolveResellerDomain` middleware, `/api/branding` endpoint, and the SPA actually fetches + applies it (title, theme color, logo) — verified in-browser on `acme.localhost` / `beta.localhost`
- ✅ **Employee roles**: `User::ROLES` per tier, validated on save, enforced via `AccountPolicy` (403 for disallowed actions, 404 for out-of-subtree accounts via the tenant scope)
- ✅ **Plan/trial model**: seeded plans with `limits` json, `EnsureTrialNotExpired` middleware (402 once expired), reseller client-account quota enforcement (422 once limit reached)
- ✅ Dashboard shell: `AppShell.vue` with sidebar nav listing every module from `01-feature-inventory.md` (only Dashboard active; rest "Coming soon" pending their phase)
- ✅ CI: GitHub Actions running backend tests + frontend build check on every push/PR
- ⏭️ Deploy pipeline / staging environment — deliberately deferred: needs a real hosting decision (VPS, Forge, Vapor, etc.) before there's anything to configure; revisit when ready to put this in front of real users

**Billing model confirmed:** resellers collect their own client payments via Stripe Connect (their own bank account, not yours) — see `07-reseller-model.md` billing section. Platform→reseller billing is a separate, centralized Stripe subscription on your own account.

## Phase 1 — MVP core (CONFIRMED SCOPE: CRM + WhatsApp, both connection types)
- ✅ **CRM backend**: pipelines (auto-provisioned per client with a default 5-stage board), leads (with description field, real phone-number validation via libphonenumber matching WhatsApp's expected digits+country-code format), deals, labels, policies, tenant scoping — verified via `tests/Feature/CrmScopingTest.php`
- ✅ **CRM UI**: Kanban board (drag-and-drop between stages — see below), stat cards, search/filters (All/Hot/Recent), New Lead dialog with client- and server-side validation surfaced per-field, delete with confirmation
- ✅ **Activity log**: general-purpose audit trail (`activity_logs`, not CRM-specific — reusable by any future module), auto-pruned past 90 days; wired into lead create/update/delete; viewable via an app-bar drawer. Not in the original phase scope, added because it's needed everywhere eventually.
- ✅ **Reusable alert system**: Pinia-backed snackbar (success/warning/error/info) + themed SweetAlert2, callable from anywhere — established as the standard pattern for any future form/action feedback, not just CRM.
- ✅ **Frontend platform work** (not CRM-specific, but built alongside it and now the standing convention for every future module — see `frontend-modular-architecture` memory / `@layouts`+`@core` structure): modular `@layouts`/`@core`/per-module `views`+`stores`+`routes` architecture; full light/dark/system theming with a right-side customizer (skin, primary color, content width); vue-i18n localization (English/Hindi/Spanish/French) across the app chrome; mobile-responsive shell (collapsible nav drawer, collapsing app-bar search, Kanban columns that scroll within a min/max height range).
- 🔶 Messaging: **both** WhatsApp connection types —
  - Meta WhatsApp Cloud API (official, needs Meta Business verification, lower risk) — not started
  - Unofficial QR-session bridge (Baileys) — ✅ sub-phase 1a built (2026-07-28): connect via QR/pairing code, single send, inbound webhook, credit metering, module-wise backend + frontend. Sub-phases 1b (bulk/automation), 1c (templates/export/link-gen/call-responder/public API), 1d (bot builder) not started — full plan in `11-unofficial-whatsapp.md`
  - Build both behind the same `channels` abstraction (see `04-data-model.md`) so the rest of the app (CRM, inbox, automation) doesn't care which one a given number uses
- ⏭️ Omnichannel-lite: single unified inbox spanning both WhatsApp connection types — not started
- ⏭️ Basic dashboard stats — not started (the CRM stat cards exist; a real cross-module dashboard doesn't yet — `DashboardView.vue` is still the Phase-0 placeholder). The CRM's "Unread" stat is hardcoded to 0 pending this.

## Phase 2 — Marketing & support
- Email Marketing: campaigns, templates, contacts, SMTP config, scheduler-driven sending (no manual cron needed, unlike reference app)
- Support System: tickets + knowledge base
- Automation engine v1: simple trigger → action rules (e.g. "new lead" → "send WhatsApp template")

## Phase 3 — Commerce & social
- Commerce: products, orders, abandoned cart recovery, invoices
- Social Media: account connection, post scheduling/calendar, unified inbox, basic analytics
- Social Lead Master: normalize FB/IG/Google/webhook leads into the CRM lead queue

## Phase 4 — Voice & AI
- CallerDesk-equivalent: dialer, call logs, recordings, human agents
- IVR flow builder
- AI layer: chatbot, chat agents, AI lead scoring, AI appointments
- AI voice agents (highest cost/complexity — do last, validate ROI before committing)

## Phase 5 — Platform maturity
- Reseller billing: Stripe Connect onboarding flow for resellers, centralized Stripe subscription for platform→reseller fees, plan-limit enforcement tied to each reseller's configuration
- Account Manager UI polish: reseller-facing client management dashboard, usage/analytics rollups per reseller
- Impersonation ("login as") tooling for support, with audit logging
- System auto-updater if this is meant to be distributed/self-hosted per reseller (mirrors reference app's "System Auto Updater" branding) — not needed if it stays a single hosted multi-tenant platform
- Number Warmer / anti-ban tooling if the unofficial WhatsApp bridge is added later

## Decision points to revisit each phase
- Which channels matter most to the actual users — don't build CallerDesk voice if nobody asked for phone support.
- Real-time chat (Reverb/WebSockets) can be deferred with polling in Phase 1–2 and upgraded later without a data model change.
