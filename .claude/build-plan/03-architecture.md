# 03 — Architecture

## High-level shape

```
                     ┌─────────────────────────┐
                     │   Vue 3 SPA (separate)   │  Vue Router + Pinia + Vuetify
                     │   deployed independently  │  talks to API over HTTPS/JSON
                     └────────────┬─────────────┘
                                  │ REST API (Sanctum: SPA cookies + tokens)
                     ┌────────────▼─────────────┐
                     │   Laravel 12 API backend │
                     │  (modular, one codebase)  │
                     │                           │
                     │  Modules (as Laravel      │
                     │  packages or namespaced    │
                     │  domains):                 │
                     │   - CRM / Pipelines        │
                     │   - Messaging (WhatsApp,   │
                     │     Meta Cloud API, Chat)  │
                     │   - Voice (CallerDesk)     │
                     │   - Email Marketing        │
                     │   - Support Tickets        │
                     │   - Commerce               │
                     │   - Social Media           │
                     │   - Automation/Workflow    │
                     │   - Account/Billing        │
                     └──────┬─────────┬───────────┘
                            │         │
                 ┌──────────▼───┐ ┌───▼─────────────┐
                 │ Redis (queue,│ │ MySQL/Postgres    │
                 │ cache, pubsub)│ │ (tenant-scoped)  │
                 └──────┬────────┘ └──────────────────┘
                        │
            ┌───────────▼────────────┐
            │   Queue Workers          │  Bulk WhatsApp/email sends,
            │   (Horizon)              │  call campaigns, post scheduling,
            └───────────┬────────────┘  webhook delivery, AI scoring
                        │
      ┌─────────────────┼───────────────────────────────┐
      │                 │                                │
┌─────▼─────┐   ┌───────▼────────┐   ┌──────────────────▼────┐
│ WhatsApp/  │   │ Telephony/AI   │   │ Social/Email/Sheets/   │
│ Meta Cloud │   │ Voice provider │   │ WooCommerce APIs       │
│ API        │   │ (CallerDesk,   │   │                        │
│            │   │ Ultravox, etc) │   │                        │
└────────────┘   └────────────────┘   └────────────────────────┘
```

## Modularity strategy
Build it as **one Laravel codebase with clearly namespaced domain modules** (e.g. `app/Domain/Crm`, `app/Domain/Messaging`, `app/Domain/Voice`), not 10 microservices. At this stage, microservices add operational overhead (service discovery, distributed transactions, multi-deploy) that isn't justified until you have real scaling pain in one specific module (most likely: bulk messaging throughput or AI voice call handling).

Each domain module should own:
- Its own migrations/models
- Its own queue jobs
- Its own webhook receiver routes (e.g. `/webhooks/meta`, `/webhooks/woocommerce`)
- Its own versioned API routes/controllers (e.g. `/api/v2/crm/leads`) — the **same** endpoints serve both the Vue SPA and third-party API consumers (see `02-tech-stack.md` for why this is one API, not two)
- A thin "integration" layer wrapping the external API client, so provider swaps (e.g. swapping CallerDesk for another telephony vendor) stay contained

## API-first design (since frontend and backend are decoupled)
- All endpoints live under `/api/v2/...`, versioned from day one — matches the reference app's own `POST /api/v2/whatsapp/send/*` pattern
- Two auth modes on the same routes: **Sanctum SPA mode** (cookie-based, CSRF-protected) for the first-party Vue dashboard; **Sanctum personal access tokens** for the "API & Automation" module's third-party/API consumers. Route-level policies (see `08-employees-roles.md`) apply identically regardless of auth mode.
- CORS must allow only the SPA's own origin(s) — including each reseller's custom domain (see `07-reseller-model.md`) if resellers get their own frontend later

## Multi-tenancy, reseller hierarchy & plans
- Confirmed model: **two-tier white-label reseller** (Super Admin → Reseller → Client → Users). Full detail in `07-reseller-model.md` — read that doc alongside this section.
- Every tenant-owned row carries `account_id`. Use a global Eloquent scope so queries are automatically tenant-filtered, keyed to the authenticated user's account subtree (a reseller's scope includes its clients; a client's scope is just itself) — reduces the single biggest security risk in this kind of app (cross-tenant/cross-reseller data leaks).
- `accounts` table holds `account_type`, `parent_account_id`, plan, trial/expiry date, feature-flag limits (e.g. max WhatsApp numbers, max seats) — mirrors the reference app's "Free trial / Expire date" card, extended with the hierarchy fields.
- Feature gating middleware checks the account's plan before allowing access to gated modules (e.g. AI Voice might be a higher-tier add-on), and additionally checks that a client's plan fits within its parent reseller's remaining quota.
- Request-time domain resolution: incoming host header → reseller (via `reseller_domains`) → applies that reseller's branding and scopes the login/session to that reseller's client accounts.

## Background processing (replaces the reference app's manual-cron pattern)
- One system cron: `* * * * * php artisan schedule:run`
- Laravel's scheduler dispatches jobs internally for: campaign sends, abandoned-cart follow-ups, number-warmer activity, subscription expiry checks, social post publishing at scheduled time
- Horizon queues separated by priority: `realtime` (chat/webhook delivery), `bulk` (mass sends), `reports` (analytics rollups)

## Real-time layer
- Laravel Reverb (self-hosted WebSockets) broadcasting channels per-account (`private-account.{id}.inbox`, `private-account.{id}.calls`) for: Omnichannel inbox, Live Chat, CRM pipeline updates, live call dashboard, social inbox

## Webhooks (inbound)
Central webhook ingestion should be **one entry point per provider**, verified via provider signature (Meta's `X-Hub-Signature-256`, WooCommerce's webhook secret, etc.), which then dispatches an internal event — keeps the "Command Center" lead-aggregation idea (FB/IG/Google/webhooks → one queue) architecturally clean: it's just several inbound webhook handlers all writing to the same `leads` table with a `source` column.

## Security considerations specific to this domain
- API keys/tokens for every third-party integration (Meta, CallerDesk, SMTP, social platforms) must be encrypted at rest (Laravel's built-in encrypted casts) and scoped per-account, never shared across tenants
- Rate-limit outbound bulk-send jobs per provider's actual API limits (WhatsApp Cloud API has strict per-number throughput tiers) to avoid account bans — this is presumably exactly why the reference app has a "Number Warmer" module
- Webhook endpoints must verify signatures before trusting payloads (do not trust `source` claims in inbound webhook bodies blindly)
