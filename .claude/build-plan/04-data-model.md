# 04 — Core Data Model (starting point)

Not exhaustive — this is the entity set needed to support the MVP + near-term modules. Expand per-module as each is actually built.

## Tenancy & identity
- `accounts` — id, name, plan_id, trial_expires_at, status
- `users` — id, account_id, name, email, password_hash, role
- `plans` — id, name, price, limits (json: max_numbers, max_seats, features[])

## CRM
- `pipelines` — id, account_id, name (supports the "CRM Pipelines" multi-pipeline module)
- `pipeline_stages` — id, pipeline_id, name, order
- `leads` — id, account_id, pipeline_id, stage_id, name, contact info, source (facebook/instagram/google/webhook/manual), ai_score, is_hot, last_activity_at
- `deals` — id, lead_id, value, currency, status (open/won/lost)
- `labels` — id, account_id, name, color
- `lead_labels` — pivot

## Messaging (Omnichannel)
- `channels` — id, account_id, type (whatsapp_unofficial/whatsapp_cloud_api/instagram/facebook/webchat), credentials (encrypted), status
- `conversations` — id, account_id, channel_id, lead_id (nullable), last_message_at
- `messages` — id, conversation_id, direction (in/out), body, media_url, status (sent/delivered/read/failed), sent_by (user_id or 'automation')

## Email Marketing
- `email_campaigns` — id, account_id, template_id, subject, status, scheduled_at
- `email_templates` — id, account_id, name, html
- `contacts` — id, account_id, email, list/group tags
- `smtp_servers` — id, account_id, host, port, credentials (encrypted)
- `email_events` — id, campaign_id, contact_id, type (sent/delivered/opened/clicked/bounced)

## Voice (CallerDesk-equivalent)
- `calls` — id, account_id, from, to, direction, status, duration, recording_url, agent_id (nullable), is_ai_handled
- `ivr_flows` — id, account_id, name, flow_definition (json)
- `agents` — id, account_id, user_id (human) or is_ai, status

## Support System
- `tickets` — id, account_id, subject, customer_id, priority, status, department_id, assigned_to
- `ticket_messages` — id, ticket_id, body, author

## Commerce
- `products` — id, account_id, name, price, stock
- `orders` — id, account_id, customer_id, status, total
- `carts` — id, account_id, customer_id, status (active/abandoned/converted)
- `invoices` — id, account_id, order_id (nullable), amount, status, pdf_path

## Social Media
- `social_accounts` — id, account_id, platform, credentials (encrypted)
- `posts` — id, account_id, social_account_id, content, media, scheduled_at, status, published_at
- `social_inbox_items` — id, account_id, social_account_id, type (comment/dm), body, external_id

## Automation
- `automation_rules` — id, account_id, trigger (event name), conditions (json), actions (json)
- `automation_logs` — id, rule_id, triggered_at, result

## Shared/cross-cutting
- `webhooks_inbound_log` — id, provider, payload, verified, processed_at (audit trail, useful for debugging integration issues)
- `api_tokens` — id, account_id, provider, encrypted_credentials, scopes

This maps cleanly onto the module inventory in doc 01 — each top-level sidebar module corresponds to one or two of the entity groups above, which is a useful sanity check when scoping actual build tickets.
