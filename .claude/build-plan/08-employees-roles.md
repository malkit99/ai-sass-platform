# 08 — Employees & Roles (Super Admin org + Reseller org)

Both the Super Admin (you) and every Reseller need more than one login — they'll each want to add their own staff with *limited* permissions, not hand out the owner account to everyone. This doc defines that for both tiers. Client-tier staff (seats within a client account, e.g. CRM/WhatsApp agents) already exist conceptually in `04-data-model.md`'s `users` table — this doc makes the same mechanism explicit at the Super Admin and Reseller tiers too.

## The pattern is the same at every tier

```
accounts (account_type: super_admin | reseller | client)
  └── users (belong to one account, have a role scoped to that account's tier)
```

A "Super Admin employee" and a "Reseller employee" are the same underlying thing — a `users` row with `account_id` pointing at the Super Admin account or a specific Reseller account, and a `role` that determines what they can do *within that account's scope*. The tenant-scoping rules from `03-architecture.md` already ensure a Reseller employee can never see another Reseller's data; this doc adds the permission layer on top (what an employee can do *within* their own account).

## Super Admin org (your internal team)

Recommended starter roles (fixed set for v1 — see "keep it simple" note below):

| Role | Can do | Can't do |
|---|---|---|
| **Owner** (you) | Everything | — |
| **Platform Support** | View/impersonate any Reseller or Client account for troubleshooting (audit-logged, per `07-reseller-model.md`) | Change billing config, platform-wide settings, or delete accounts |
| **Platform Sales/Onboarding** | Create new Reseller accounts, view Reseller sales pipeline | Access any Reseller's client data or support tickets |
| **Platform Billing Ops** | View/manage Reseller subscription billing & invoices (platform→reseller side) | Access support tickets, messaging content, or client data |
| **Platform Developer/Ops** | System settings, API keys, feature flags, deployment-related config | Access customer data unless explicitly impersonating (audit-logged) |

## Reseller org (each reseller's own team)

Same idea, scoped to that reseller's own clients only:

| Role | Can do | Can't do |
|---|---|---|
| **Reseller Owner/Admin** | Full control of their own clients: create/suspend/delete client accounts, set client plans, configure branding/white-label, connect their Stripe Connect account | See other resellers, exceed the quota the Super Admin set on their own plan |
| **Reseller Support Agent** | Access support tickets/live chat/omnichannel inbox across their reseller's clients | Create/delete client accounts, change billing/plans, change branding |
| **Reseller Sales/Onboarding** | Create new client accounts under their reseller, assign plans (within reseller's quota) | Access support tickets or messaging content |
| **Reseller Billing Staff** | View invoices/payment status for their reseller's clients | Change client plan permissions or branding |

## Data model additions (extends `04-data-model.md`)

Keep this simple for v1 — a **fixed enum of roles per account_type**, not a fully dynamic permission-builder UI (that's real added complexity with no confirmed need yet; add it later only if a reseller actually asks for custom roles):

- `users.role` — enum, meaning depends on `users.account_id`'s `accounts.account_type`:
  - `super_admin` accounts: `owner`, `platform_support`, `platform_sales`, `platform_billing`, `platform_developer`
  - `reseller` accounts: `owner`, `support_agent`, `sales`, `billing`
  - `client` accounts: `owner`, `agent` (already implied — CRM/WhatsApp/support seats)
- `impersonation_logs` (already defined in `07-reseller-model.md`) covers the audit trail for Platform Support and Reseller Owner "login as" actions

## Enforcement approach
- A single Laravel authorization Policy/Gate class per domain area (e.g. `ClientAccountPolicy`, `BillingPolicy`, `SupportTicketPolicy`) checks **both** (a) does this user's `role` allow the action, and (b) is the target record inside their own account subtree (tenant scope from `03-architecture.md`). Two checks, one place — don't scatter role checks through controllers.
- Seat limits: number of employees a Reseller (or Super Admin) can add should be gated by their own plan's `limits` json (same mechanism already used for client-facing plan limits) — e.g. a reseller's plan might cap them at "5 team seats."

## Roadmap placement
- **Phase 0 (Foundation):** the `users.role` field and basic policy checks for Super Admin + Reseller tiers go in now, alongside the account hierarchy work already pulled forward — auth without any role concept would need reworking later.
- **Not needed for v1 launch:** a custom/dynamic permission builder, per-reseller custom roles, or granular field-level permissions — the fixed role tables above are enough until a real customer asks for more.
