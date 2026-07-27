# 01 — Feature & Module Inventory

Captured directly from the running app (deep pass #2, 2026-07-27 — logged in as `rgcsm.pb@gmail.com`). This revises and corrects the first shallow pass: two modules were mislabeled below (see **CORRECTED** notes).

## Dashboard
- Overview widgets: WooCommerce order notifications, Google Sheets import/sync shortcut, "What's New" changelog viewer
- "Command Center" (= Social Lead Master) — unified incoming-lead feed from Facebook, Instagram, Google, and generic webhooks
- Account status card: plan name, trial/expiry date, quick links
- Live WhatsApp analytics widget with credit meter (see below)

## Whatsapp (unofficial / QR-session channel)
Full sidebar on this module: **Send Single Message, Profile, Bulk messaging, Autoresponder, Chatbot, Templates, Export participants, API (WhatsApp REST), Form Builder (QuickForms drag-drop), Call Responder (auto-responder to phone calls), Link Generator (QR codes/links), Bot Builder, Contacts**, plus a live **WhatsApp Analytics** dashboard.
- **Usage-metered by credits**: "Available Credits: 100 / Plan Limit: 100" — every send consumes credits, tied to the account's plan. This is the core usage-metering mechanism to replicate.
- Stats: messages sent, bulk delivered %, active autoresponder rules, active chatbots, message distribution (direct/bulk/auto/chatbot), API calls/failures

## WhatsApp Live Chat (`/whatsapp_livechat`)
- Unified WhatsApp inbox with **lifecycle stages**: New Lead → Hot Lead → Cold Lead → Qualified → Customer → Payment
- **Team Inbox** shift-based queues: Morning team / Afternoon team / Evening team
- Filters: Chats, Groups, Mine, Unassigned, Starred, Meta Ads (tracks leads that came from Meta ad clicks)

## CallerDesk (cloud telephony)
- Dashboard: total/answered/missed calls, active agents, call volume chart, answer rate
- IVR Flows (visual flow builder)
- AI Voice: Realtime dashboard, Active AI Calls (live transcription), AI Agents ("Ultravox" callers), AI Analytics
- Dialer, Call Logs, Recordings, Human Agents, Campaigns (bulk dialer), Settings/API config

## Email Marketing
- Dashboard, Campaigns, Templates, Contacts, Unsubscribes, SMTP Servers (multi-server), Email Verifier
- Requires a server cron hitting `/email_marketing_cron` every minute for bulk sends

## Support System
- Tickets (status: Open/In Progress/Pending/Resolved/Closed; priority: Low/Medium/High/Urgent; department; assignee), Knowledge Base

## Commerce
- Dashboard, Reports, Settings
- Orders, Catalog, Customers, Payments
- Abandoned Carts (recovery), Automations, Automation Logs, Chatbot (commerce-specific bot)

## Social Media
- Dashboard, Accounts, Create Post, Scheduled Posts, Content Calendar
- Inbox (unified comments & DMs), Automation (auto-reply/bots)
- Analytics, Media Library, Settings, API Settings, Documentation

## Meta Cloud API — this is a full parallel enterprise suite, not a small config screen
This module is far bigger than the others — effectively a second, more "enterprise" implementation of most of the platform, built specifically on Meta's official WhatsApp Business Platform. Its sidebar (`/meta_api`) has ~50 sub-pages grouped as:
- **Overview**: Dashboard, Connections (Meta Business Accounts)
- **Messaging**: Templates, Single Send, Broadcast (smart campaign engine), Drip Campaigns, Messages log
- **Team Inbox**: Shared Inbox, Quick Replies
- **Automation**: Workflows (Zapier-style builder), Contacts (lifecycle mgmt), Smart Segments (dynamic audiences), Chatbot, Blacklist
- **Commerce**: Products, Orders, Chat Widgets (website widget builder)
- **AI Intelligence**: AI Brain (persona & knowledge), Intent Insights (AI heatmaps), AI Engine (configure AI providers), AI Intents (routing rules), AI Training (knowledge base Q&A), AI Analytics
- **Developer**: Dev Console (logs/debugger), API Keys, Webhooks (outgoing webhook builder), Integrations
- **Enterprise**: App Store (plugins/addons), SLA Matrix, Health Monitor, Compliance (opt-in & GDPR), Usage & Billing (credits/tracking), **Approval Assistant (Meta App Review & Setup)**, Settings
- **Sales & CRM** (duplicated from main CRM, but Meta-Cloud-API-scoped): Sales Pipelines, Revenue Forecast, Quotations, Follow-ups
- **Marketing**: Automation Builder (cross-channel journeys), Email Marketing, Lead Scoring, Lead Attribution
- **Support & Help Desk** (duplicated): Ticket System, Live Chat, Knowledge Base, SLA Policies, CSAT/NPS
- **Omnichannel Hub**: Platform Hub, Channel Manager (FB/IG/Telegram/SMS/Email)
- **Resources**: Documentation, **Tech Provider Guide**, **SaaS Provider Application**

**Important implication for the reseller model**: the presence of a "Tech Provider Guide" and "SaaS Provider Application" confirms that to let *your resellers' clients* each connect their own official WhatsApp Business number under your platform, **you need to become a Meta Tech Provider / Solution Partner** — a formal Meta approval process, not just an API integration task. See `05-integrations.md` (updated) and `07-reseller-model.md`.

## CRM
- Default "Sales Pipeline" board (`/crm`): leads, deals, Kanban + list view, filters (Hot/Unread/Has Deal/AI Scored/Recent), stats (total leads, revenue, hot leads, unread, won deals), Analytics, Forecast, Labels, Settings, Sync

## CRM Pipelines (`/ai_pipeline`) — **CORRECTED**
- A **separate multi-pipeline deal tracker** ("CRM Deals Pipeline") distinct from the single default Sales Pipeline board above — lets you create multiple named pipelines (e.g. one per product line or team)

## Omnichannel (`/omnichannel`)
- Confirmed channel list: **WhatsApp, Facebook, Instagram, X/Twitter, LinkedIn, Telegram** — one combined inbox plus per-channel inbox views

## Number Warmer (`/number_warmer`)
- Groups, Logs, Settings (delays/limits/AI)
- Purpose: simulates natural conversations between your own WhatsApp numbers to build sender reputation ("warm up") before running bulk campaigns, reducing ban risk
- Automated via cron: `*/3 * * * * wget "https://.../number_warmer/cron_warmup?key=..."` — or manual "Run Round" per group

## AI Appointments (`/ai_appointments`)
- AI detects and books appointments **automatically from WhatsApp conversation content**, or manual creation
- Calendar, Availability, Settings; lifecycle: Pending → Confirmed → Completed/Cancelled

## Chat Agents (`/whatsapp_livechat_agents`) — **CORRECTED**
- This is **human agent management for the live-chat support team** (add/manage support staff, online/active status) — not AI bot agents. (AI-side chat automation lives in the separate "AI Chatbot" module and in Meta Cloud API's "AI Brain"/"AI Intents".)

## Social Lead Master (`/social_lead_master`) — the "Command Center"
- Normalizes leads from **Facebook, Google, LinkedIn, JustDial, OLX, 99acres, MagicBricks** and more into one queue — note the India-specific classifieds/real-estate portals (JustDial, OLX India, 99acres, MagicBricks), a strong signal the reference product targets the Indian SMB/real-estate/classifieds market
- Dashboard/Analytics, All Leads, Import/Export (CSV/XLSX), Lead Sources, Field Mapping, Assignment Rules (auto-routing), Sync Logs, Webhook Logs
- Live dashboard auto-refreshes every 20s; tracks sync health/failures per source

## Account Manager (`/account_manager`) — **CORRECTED**
- This is actually **"WhatsApp profiles" management** — add/manage multiple connected WhatsApp numbers/accounts under this one tenant. It is **not** tenant/reseller account management as originally assumed. (Our own reseller hierarchy in `07-reseller-model.md` is still the right design for our build — it just isn't copied from an equivalent module in the reference app, since no reseller-admin UI was found here; see note below.)

## File Manager
- Browser-based file storage, quota shown (e.g. "100MB Total"), categorized by media type (Image/Video/PDF/Document/Audio/Zip/Other)

## Tools
- Small utility submenu: **Caption**, **Group manager** (much smaller than expected — not a large grab-bag)

## Invoices
- **Confirmed: the tenant account bills its own customers directly.** Table columns: Invoice, Customer, Plan, Amount, Gateway, Status, Notifications, Created, Actions. "2 GATEWAYS ACTIVE" shown.
- This is direct evidence the reference app's per-tenant account already supports "bill your own customers on a plan, via your own payment gateway" — exactly the reseller→client billing pattern in `07-reseller-model.md`, just implemented at the single-tenant level here (no visible separate reseller/super-admin panel was found — `/admin` 404s).

## Google Sheets
- Dashboard, Connections, Sheets, Import Center, Export Center, Mapping Rules, Auto Sync, Activity Logs, Documentation

## WooCommerce
- Store URL + Consumer Key/Secret (WooCommerce REST API) + Webhook URL setup (for "Order created"/"Order updated" topics)
- Reuses the same WhatsApp feature set (send/profile/bulk/autoresponder/chatbot/templates) scoped to order notifications, plus Shop Bot and Activity Log

## AI Chatbot
- WhatsApp-attached AI auto-reply bot; stats: total conversations, active bots, connected accounts

## API & Automation (`/api_automation`) — full REST API v2, confirmed endpoints
- Dashboard (active tokens, total API calls, 24h calls, success rate), Tokens, Automations, Webhooks, Logs, API Docs
- Documented example endpoints:
  - `POST /api/v2/ai/chat` — body includes `model: "openai/gpt-4o-mini"`, implying an **AI gateway abstraction that routes to multiple model providers** by a `provider/model` string (OpenRouter-style), not a single hardcoded LLM vendor
  - `POST /api/v2/ai/complete`
  - `POST /api/v2/whatsapp/send/*` — 13 message types
  - Full CRUD `POST /api/v2/crm/leads`

## Profile / Plan page (`/profile/index/plan`)
- Confirms the **plan permission model**: a plan is a flat list of gated feature flags, e.g. Number Warmer, Bulk messaging, Chatbot, Autoresponder, Send button/list messages, REST API, Send polls, Meta API, Advanced features, URL Shortener, OpenAI Generate Content/Image, Image editor. This maps directly onto the `plans.limits` json field in `04-data-model.md`.

---
**Confidence note:** this pass clicked into every top-level sidebar module and most first-level sub-pages; second-level detail (e.g. exact fields inside Commerce → Orders, or every Meta Cloud API sub-screen) was not opened everywhere — flag any specific screen you want audited further and I'll go deeper on just that one.
