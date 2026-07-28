# 11 — Unofficial WhatsApp Module (Baileys QR-bridge) — Build Plan

Scope derived from `screenshots/30-64` (reference app's "Whatsapp" sidebar module) plus the existing inventory in `01-feature-inventory.md` (Whatsapp section) and the v1 commitment already recorded in `05-integrations.md` and `04-data-model.md` (`channels.type = whatsapp_unofficial`). This doc breaks that module into buildable sub-phases under Phase 1's "Messaging" item in `06-roadmap.md`.

## Architecture decision that must be made before coding starts

**Baileys is a Node.js library with no maintained PHP equivalent.** Laravel cannot host the WhatsApp socket connection itself. Recommended shape:

- A small **Node.js "WhatsApp bridge" service** (separate process/deployment from the Laravel API) owns all Baileys sessions — one Baileys socket per connected instance.
- It exposes an internal HTTP API (not public) for Laravel to call: create instance, get QR/pairing code, send message (text/media/group), fetch group participants, disconnect/logout.
- It pushes events **back** to Laravel via signed webhook calls (or a Redis pub/sub queue Laravel already has via Horizon) for: connection status changes, inbound messages, delivery/read receipts.
- Session auth state (Baileys' `useMultiFileAuthState` or a DB-backed equivalent) must survive restarts — store encrypted per-instance session blobs, keyed by `channels.id`, in the bridge service's own storage (or S3/object storage per `02-tech-stack.md`'s file-storage choice), not inside Laravel's DB.
- This mirrors the reference app's UI exactly: `Profile` screen exposing `instance_id` + `access_token` per connected number (screenshot 32/64) is really "credentials Laravel uses to address a specific Baileys session on the bridge."

This is a real infra addition (a second runtime, PHP + Node side by side) — flag it explicitly to whoever owns hosting/deployment before Phase 1a starts; it affects `09-deployment-ops.md`.

**Known gap to verify early:** Baileys has no reliable support for WhatsApp *voice calls* (only call-offer events, not audio). The reference app's "Call Responder" (screenshot 52 — auto-reply to missed/answered/rejected calls) likely just reacts to Baileys' call-offer webhook event and auto-rejects + sends a text reply — it does **not** mean real call handling. Confirm this against current Baileys docs before promising the feature; if unsupported, cut it or scope it down to "auto-reject + auto-text-reply on call-offer event" only.

## Data model additions (extends `04-data-model.md`)

Builds on the existing `channels` / `conversations` / `messages` tables — no need to duplicate those.

- `channels.credentials` (already encrypted json) stores, for `whatsapp_unofficial` rows: `instance_id`, `access_token` (for the module's own REST API, screenshots 38-50), bridge-service instance reference. `channels.status` covers disconnected/connecting/connected/logged_out.
- `whatsapp_groups` — id, channel_id, group_jid, name, participant_count, last_synced_at
- `whatsapp_group_participants` — id, group_id, phone, synced_at (populated on-demand by the Export Participants feature, screenshot 37)
- `whatsapp_campaigns` — id, account_id, channel_id (nullable if "apply to all accounts"), name, contact_group_id (nullable), message_type (text_media/buttons/list/poll/template), body, media_url, spintax_enabled (bool), url_shortener_enabled (bool), emoji_randomizer (bool), warm_up_mode (bool), min_interval_seconds, max_interval_seconds, schedule_window (daytime/nighttime/odd/even/any), recurring (none/daily/weekly/monthly), scheduled_at, status (draft/scheduled/running/completed/failed)
- `whatsapp_campaign_recipients` — id, campaign_id, phone, status (pending/sent/delivered/failed), sent_at, error
- `whatsapp_autoresponders` — id, channel_id (nullable = all accounts), enabled, message_type, body, media_url
- `whatsapp_chatbot_rules` — id, channel_id, enabled, target (all/individual/group), match_type (contains/exact), name, keywords (json array), message_type, body, media_url
- `whatsapp_templates` — id, account_id, name, type (text/text_image/text_video/text_document/text_audio/text_buttons/text_lists/interactive_buttons/text_carousel/poll), content (json — shape depends on type)
- `whatsapp_bot_flows` — id, channel_id, name, flow_definition (json — nodes/edges, same pattern as `ivr_flows.flow_definition` already planned for voice), status (draft/active), source (scratch/template/imported)
- `whatsapp_call_responder_settings` — id, channel_id, auto_reject_enabled, reply_delay_seconds, missed_call_reply, after_call_reply, rejected_call_reply, missed_before_answer_reply
- `whatsapp_short_links` — id, account_id, channel_id, reference_name, phone, message, slug, clicks (for the Link Generator, screenshot 53 — `wa.me` deep links + QR)
- `whatsapp_credit_ledger` — id, account_id, delta, reason (message_sent/bulk_sent/plan_reset/manual_adjustment), balance_after, created_at — the "Available Credits: 98 / Plan Limit: 100" mechanic (feature-inventory line 13). **Decided: ledger, built in 1a.** The fast-read balance itself lives in its own `whatsapp_credit_balances` table (account_id, credits_remaining) rather than a column on `accounts` — see `schema-design-preference` memory (new concerns get their own table, not bolted onto core tables).

Existing `api_tokens` table can likely be reused for the module's public REST API (screenshots 38-50) rather than inventing a new token table — scope the token to `provider: whatsapp_unofficial` and `channel_id`.

## Sub-phases (all still inside roadmap Phase 1's "Messaging" line item)

### 1a — Connection + core send — ✅ built (2026-07-28)
- Bridge hosting decided: own process (systemd/PM2), colocated on the same box as Laravel initially, addressed over `localhost`/internal network only — move to a dedicated VM/container later purely as a config change once WhatsApp instance count makes that worthwhile. Recorded in `09-deployment-ops.md`.
- Backend follows the module-wise convention: controllers under `app/Http/Controllers/Api/Whatsapp/`, routes in their own `routes/api/whatsapp.php` wired into `routes/api.php` via `Route::prefix('whatsapp')->group(...)` — see `backend-modular-controllers` memory.
- Built: `channels`/`conversations`/`messages` tables + models, `ChannelPolicy`, `whatsapp_credit_balances` + `whatsapp_credit_ledger`, `BridgeClient` HTTP service, `ChannelController` (create/QR/pairing-code/status/logout), `MessageController` (single send, decrements credits), `WebhookController` (HMAC-signature-verified inbound events from the bridge), and the `whatsapp-bridge/` Node service itself (Baileys session manager, QR/pairing-code flow, inbound message forwarding).
- Frontend: `views/whatsapp/` (`WhatsappView` + `ChannelsList`/`ConnectAccountDialog`/`SendMessageDialog`), `stores/whatsapp/whatsapp.js`, `router/routes/whatsapp.js`, sidebar entry enabled in `modules.js`.
- Verified via curl smoke test: signed webhook accepted/rejected correctly, connection status + inbound message correctly written to `channels`/`conversations`/`messages`, auth-protected routes reject unauthenticated requests identically to existing modules.
- Not yet exercised with a real phone (needs a live QR scan) — do that manually before considering 1a fully done end-to-end.

### 1a original scope (for reference — see "built" note above)
- Node bridge service skeleton: create instance, QR generation, pairing-code flow, session persistence, disconnect/logout, status webhook back to Laravel
- Laravel: `channels` CRUD for whatsapp_unofficial, `POST /api/whatsapp/instances/{channel}/qr`, connection-status polling or Reverb push to the "Connect WhatsApp" UI (screenshot 64)
- Send Single Message (text/media/template) — screenshot 31 — writes to `messages`/`conversations`
- Inbound message webhook from bridge → `conversations`/`messages`, so Omnichannel inbox has real data from day one
- Profile screen (screenshot 32): show instance_id/access_token, logout
- Contacts (screenshot 63): basic group contact list, enable/disable

### 1b — Bulk + automation + metering — ✅ built (2026-07-28)
- Built: `whatsapp_campaigns`/`whatsapp_campaign_recipients` + `CampaignController` (per-recipient `SendCampaignMessageJob` dispatched with server-enforced cumulative delay — min 3s/max 300s floor+ceiling regardless of client input, doubled further under warm-up mode), `whatsapp_autoresponders` + `AutoresponderController`, `whatsapp_chatbot_rules` + `ChatbotRuleController`, a small `Spintax` parser (`{a|b|c}` random-choice, nested-brace safe), and a `DashboardController` (credits/messages-sent/bulk-delivered/active-rule stat aggregates).
- Chatbot rules and the autoresponder are now wired into `WebhookController::handleInboundMessage` — chatbot keyword matches take priority, autoresponder is the unconditional fallback, both channel-specific and account-wide (`channel_id = null`) rules supported; auto-replies decrement credits and get skipped (not queued/retried) when the balance is 0.
- Frontend: WhatsApp module restructured with a local feature sidebar (Dashboard/Accounts/Bulk messaging/Autoresponder/Chatbot) instead of a flat page, matching the reference app's layout (screenshot 30) — `WhatsappDashboardPanel` (stat cards, message-distribution bars, performance summary), `BulkCampaignsPanel`+`NewCampaignDialog`, `AutoresponderPanel`+`AutoresponderDialog`, `ChatbotPanel`+`ChatbotRuleDialog`.
- Verified via tinker: dashboard aggregate queries run clean, campaign/recipient relations work, spintax renders (including nested groups), chatbot keyword matching (contains/exact) confirmed both ways. Not yet verified with a real live bulk send (would need to actually message real numbers) — the anti-ban interval math and credit decrement path are covered, the live-send path reuses the already-verified `BridgeClient::sendMessage` from 1a.

### 1b original scope (for reference — see "built" note above)
- Bulk messaging / campaigns (screenshot 33) — recipient targeting (contact group), scheduling window, min/max send interval, warm-up mode, recurring schedule — **anti-ban pacing is not optional here**, per `05-integrations.md`: enforce min/max interval server-side regardless of what the UI sends, cap messages/minute per instance
- Spintax message randomization (`{Hi|Hello|Hola}` syntax, screenshot 34) — small parser, no external dependency needed
- URL shortener (feeds `whatsapp_short_links` or a generic shortener if one already exists elsewhere)
- Autoresponder (screenshot 34) and keyword Chatbot (screenshot 34/35) — both are simple rule-match-and-reply; chatbot adds keyword matching (contains/exact) and target scoping (all/individual/group)
- Credit/usage metering: decrement on every successful send, surface on the WhatsApp Analytics dashboard (screenshot 30), gate via `plans.limits` per the existing plan-permission model (feature-inventory line 120)

### 1c — Auxiliary tools
- Templates (screenshot 36) — the 10 message types listed above; likely shared/reused by Bulk messaging, Chatbot, and Bot Builder message nodes rather than duplicated per-feature
- Export Participants (screenshot 37) — bridge fetches group metadata + participants on demand, cached into `whatsapp_group_participants`, CSV download
- Link Generator (screenshot 53) — pure Laravel, no bridge dependency (`wa.me` links are just URLs)
- Call Responder (screenshot 52) — **only after the Baileys call-event feasibility check above is resolved**
- Public REST API (screenshots 38-50) — versioned endpoints mirroring the reference app's shape (`create_instance`, `get_qrcode`, `send`, `send_group`, media/file variants) but under this project's own `/api/v1/whatsapp/*` convention rather than copying the exact reference URLs; access_token-per-instance auth via `api_tokens`

### 1d — Bot Builder (last, deliberately scoped down from the reference app)
- Reuse the `flow_definition` json pattern already planned for `ivr_flows` (voice) — same visual-builder concept, different node vocabulary
- v1 node set only: Start, Text, Text Input (name/number/email/phone), Condition, Webhook (outbound HTTP), End — enough for real linear/branching flows
- Defer the long tail from screenshots 60-62 (Zapier, Make.com, Pabbly, Chatwoot, OpenAI, Anthropic, Mistral, ElevenLabs, NocoDB, Segment, Groq, Zendesk, Posthog, Perplexity, DeepSeek, WooCommerce, etc. — 25+ integrations) to build **on demand**, not upfront. Ship the flow engine + Webhook node first; a webhook node can reach most of those services without a dedicated integration.
- Marketplace/template library (screenshot 57) and JSON import/export (screenshot 58) are cheap once flow_definition exists — low priority but low cost, can ride along with 1d
- **Do not build all 25+ integration nodes as part of "Phase 1" under any interpretation** — this alone is a multi-month effort in the reference app; treat it as its own future phase once the flow engine is proven with the Webhook escape hatch.

## Frontend
Follow `10-frontend-conventions.md` exactly: `views/whatsapp/` with one `WhatsappView.vue` shell + per-feature sub-views/components (dashboard, connect, send, bulk, autoresponder, chatbot, templates, api, form-builder is out of scope here — it's a generic "Form Builder" module, not WhatsApp-specific, see note below), `stores/whatsapp/`, `router/routes/whatsapp.js`. Sidebar entry list matches the reference app's feature list (feature-inventory line 12) minus Form Builder.

**Note on Form Builder (screenshot 51):** it's a generic drag-and-drop form tool, not WhatsApp-specific — the reference app just happens to nest it under the WhatsApp sidebar. Recommend building it later as its own standalone module (or folding into Automation engine v1 in Phase 2) rather than inside this WhatsApp plan.

## Open decisions
1. ~~Node bridge service hosting~~ — **decided**: own process, colocated on the same box initially (see 1a note above).
2. ~~Credit ledger vs. simple counter~~ — **decided**: ledger + dedicated balance table (see data model note above).
3. Call Responder feasibility with Baileys — spike this early since it gates whether 1c includes it at all.
4. Whether the public REST API (1c) should be versioned alongside the future official "API & Automation" v2 module (`01-feature-inventory.md` line 111) or kept fully separate per-channel — recommend separate for now (different auth model: per-instance access_token vs. account-level API tokens), revisit when the general API & Automation module is actually scoped.
