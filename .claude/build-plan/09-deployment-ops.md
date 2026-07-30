# 09 — Deployment Ops: Cron & Scheduled Jobs

Living checklist of everything the production server must have configured that isn't captured by just deploying the code. **Update this file every time a new scheduled command, queue worker, or cron entry is added anywhere in the app** — this is the one place to check before/after any production deploy so nothing silently stops running.

## Required server cron entry (one, always)

Laravel's scheduler needs exactly **one** system cron entry, which then dispatches everything registered in `routes/console.php`:

```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Without this single line, **every** scheduled task below silently never runs — there's no per-task cron entry to configure, just this one dispatcher.

## Scheduled tasks currently registered (`routes/console.php`)

| Command | Schedule | Purpose | Added |
|---|---|---|---|
| `app:prune-activity-logs` | daily | Deletes `activity_logs` rows older than 90 days (`app/Console/Commands/PruneActivityLogs.php`) so the audit trail table doesn't grow unbounded across every tenant. | 2026-07-28 |
| `app:sync-whatsapp-channel-statuses` | every 5 minutes | Reconciles `channels.status` against the bridge's live connection state (`app/Console/Commands/SyncWhatsappChannelStatuses.php`) — a bridge crash/restart never fires a disconnect webhook, so a channel can sit marked "connected" long after the bridge lost that session; this catches the drift proactively instead of only surfacing it as a confusing error the next time someone tries to send. | 2026-07-28 |
| `app:process-recurring-whatsapp-campaigns` | every 15 minutes | Spins off the next scheduled run for bulk campaigns with "Enable Recurring Schedule" set (`app/Console/Commands/ProcessRecurringWhatsappCampaigns.php`) — clones the original campaign's config into a fresh child campaign + recipient batch each cycle and pushes `next_run_at` forward by the chosen frequency (daily/weekly/monthly). Without this cron entry, a recurring campaign's `next_run_at` just sits there forever and nothing new ever gets sent. | 2026-07-28 |
| `app:prune-whatsapp-call-logs` | daily | Deletes `whatsapp_call_logs` rows older than 90 days (`app/Console/Commands/PruneWhatsappCallLogs.php`) — same reasoning as `app:prune-activity-logs`: a channel that goes idle/disconnected (not deleted, so no FK cascade to rely on) would otherwise leave call history growing unbounded forever. | 2026-07-30 |
| `app:prune-whatsapp-groups` | daily | Deletes `whatsapp_groups` rows not synced (or, if never synced, not discovered) in 90 days (`app/Console/Commands/PruneWhatsappGroups.php`) — uses `last_synced_at` when set rather than `created_at`, so a group discovered long ago but exported recently isn't wrongly pruned. `whatsapp_group_participants` cascade-deletes with its parent group automatically (FK `ON DELETE CASCADE`), no separate prune command needed. | 2026-07-30 |
| `app:prune-whatsapp-messages` | daily | Deletes `messages` rows older than 90 days (`app/Console/Commands/PruneWhatsappMessages.php`) — powers Message History (screenshots-free feature, `11-unofficial-whatsapp.md`). Prunes individual message rows only, not their parent `conversations` — a conversation is lightweight contact/lead metadata, not log data, so it stays around even once its old messages are gone. Verified live: seeded a 95-day-old and a 10-day-old message inside a rolled-back transaction, confirmed only the 95-day-old one was deleted. | 2026-07-30 |

## Queue workers

`SendCampaignMessageJob` (`app/Jobs/Whatsapp/SendCampaignMessageJob.php`) is queued via `ShouldQueue` — one job per bulk-campaign recipient, dispatched with a computed delay for anti-ban pacing (see `11-unofficial-whatsapp.md`). **Requires a running queue worker** (`php artisan queue:work`, or Horizon once installed per `03-architecture.md`'s `realtime`/`bulk`/`reports` queue plan) as an always-on process — without one, campaign messages are queued but never actually sent. Uses the default `QUEUE_CONNECTION=redis` connection/queue.

## Other always-on processes

- **`whatsapp-bridge/` (Node/Baileys service)** — added 2026-07-28, see `11-unofficial-whatsapp.md`. Must run as its own always-on process (systemd service / PM2), separate from PHP-FPM/nginx, listening only on an internal address (`WHATSAPP_BRIDGE_URL` in Laravel's `.env` / `PORT` in the bridge's own `.env` — dev default `127.0.0.1:3001`). Holds live Baileys socket connections per WhatsApp instance plus their session auth state under `whatsapp-bridge/sessions/` — that directory must persist across deploys/restarts (back it up or move it to a persistent volume before this goes to production) or every connected number will need to re-scan its QR code. Dev: `cd whatsapp-bridge && npm run dev`.

Revisit when Reverb (real-time/WebSockets, see `03-architecture.md`) is added — it needs its own persistent server process too.

---
**Why this file exists:** cron/scheduled-job configuration lives on the server, not in the deployed code, so it's invisible to `git diff` and easy to forget when standing up a new environment or auditing an existing one. Treat this as the source of truth for "what background jobs does this app depend on" — check it against the actual server config during any deploy or environment setup.
