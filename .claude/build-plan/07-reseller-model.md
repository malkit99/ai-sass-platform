# 07 — Reseller / White-Label Model

Confirmed requirement: **two-tier white-label reseller platform** — resellers sign up, get their own branded instance, and create/manage/bill their own client accounts underneath them. This is now a foundational requirement, not a later add-on (see updated `06-roadmap.md`).

**Correction from a deeper audit of the reference app (see `01-feature-inventory.md`):** the reference app's own "Account Manager" module is actually per-account WhatsApp *number/profile* management, not a reseller/tenant admin panel — no `/admin` route or visible reseller UI was found there. So this hierarchy design isn't something to copy screen-for-screen from the reference app; it's a deliberate addition on top of it. Useful supporting evidence, though: the reference app's **Invoices module already bills the tenant's own customers directly** (columns: Customer, Plan, Amount, Gateway), which is exactly the reseller→client billing shape this doc describes — just implemented at a single-tenant level there, without the reseller layer above it.

**Also relevant:** if resellers' clients need official Meta WhatsApp Cloud API numbers (not just the unofficial QR-bridge), you'll need Meta Tech Provider / Solution Partner approval — see `05-integrations.md`. That's a business/compliance step to start early, independent of the engineering work below.

## Account hierarchy

```
Super Admin (you, the platform owner)
  └── Reseller accounts
        └── Client accounts (the reseller's own customers)
              └── Users (staff/seats within a client account)
```

- `accounts` table gains: `account_type` (`super_admin` / `reseller` / `client`), `parent_account_id` (nullable — client points to its owning reseller; reseller's parent is null/platform)
- All existing tenant-scoping (`account_id` on leads/messages/tickets/etc.) stays exactly as designed — it now just also applies to reseller-owned client accounts, each fully isolated from every other reseller's clients
- A reseller must **never** be able to query another reseller's clients or data — enforce via the same global scope, keyed off the authenticated user's own account subtree, not a flat "is admin" flag

## White-labeling
- **Custom domain per reseller**: CNAME (e.g. `crm.theirbrand.com` → your app), resolved at request time to determine which reseller's branding/config applies
- **Branding**: logo, color theme, product name, favicon — stored per reseller account, injected into the shared UI shell at render time
- **Custom sender identities**: reseller's own SMTP-from-name/domain for emails, and ideally their own WhatsApp Business number, so end clients never see "your" platform name
- **Custom auth emails**: password reset / welcome emails should use the reseller's branding, not the platform's

## Billing & pricing
Two independent billing relationships to design for:
1. **Platform → Reseller**: you charge the reseller (flat SaaS fee, or usage-based on their total client count/messages/etc.) — this is standard recurring billing (Stripe/Chargebee)
2. **Reseller → Client**: the reseller sets their **own retail pricing and plans** for their clients, and collects payment from them directly

**Confirmed: Option A — reseller collects payment themselves**, via their own Stripe account (Stripe Connect). Money from their clients goes straight to the reseller's own bank account — the platform never touches it, and is not the merchant of record for those transactions. Your platform's job is just to enforce plan limits based on what the reseller has configured for each client, and to bill the reseller itself for their platform subscription.

What this means for the build:
- Each reseller goes through a one-time **Stripe Connect onboarding** flow (Stripe hosts the identity/bank-account verification — you don't build that part yourself)
- The reseller's client billing (subscriptions, invoices, payment methods) runs through *their* connected Stripe account using Stripe Connect's API
- Your platform→reseller billing (the fee you charge resellers to use the platform) is a separate, simpler, centralized Stripe subscription on your own account
- No payout/commission-splitting system needed on your side, since you're never holding the client's money in the first place

## Permissions & support tooling
- Reseller admins can: create/suspend/delete their own client accounts, set client-level plan limits (within whatever ceiling their own platform plan allows), view aggregated usage/analytics across their clients
- Reseller admins can **not**: see other resellers, impersonate platform-level settings, exceed limits the Super Admin set on the reseller's own plan
- Super Admin needs an **impersonation ("login as")** feature for support — jumping into any reseller's or client's account view — must be audit-logged (who impersonated whom, when) since it's a significant trust/security surface

## Limits & quota enforcement
- Reseller-level plan defines ceilings: max client accounts, max total seats/numbers/messages across all their clients
- Client-level plan (set by the reseller) must fit within the reseller's remaining quota — validate at client-plan-assignment time, not just at usage time
- Usage rollups (messages sent, calls made, storage used) need to aggregate both per-client and per-reseller for billing and limit checks

## Data model additions (extends `04-data-model.md`)
- `accounts.account_type`, `accounts.parent_account_id`
- `reseller_domains` — id, reseller_account_id, domain, ssl_status, verified_at
- `reseller_branding` — id, reseller_account_id, logo_url, primary_color, product_name, support_email
- `reseller_plans` — id, reseller_account_id, name, price, limits (json) — the plans a reseller offers *their* clients (distinct from `plans`, which is what the Super Admin offers resellers)
- `impersonation_logs` — id, actor_user_id, target_account_id, started_at, ended_at

## Roadmap impact
This pulls "Account Manager" work out of Phase 5 and into **Phase 0/1 (Foundation)** — the `account_type`/`parent_account_id` hierarchy and tenant-scoping must be designed in from day one, since retrofitting a reseller layer onto a flat multi-tenant schema later is a painful migration. Billing (Stripe Connect vs. centralized) can still be deferred slightly, but the *data model* for hierarchy and per-reseller branding should exist before other modules are built on top of it.
