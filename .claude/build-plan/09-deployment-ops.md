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

## Queue workers

None yet — no jobs are currently dispatched to a queue (`ShouldQueue` isn't used anywhere in the codebase as of this writing). `03-architecture.md` plans Horizon-managed queues (`realtime`/`bulk`/`reports`) once WhatsApp/email bulk-sending (Phase 1–2) is built. **When that lands, add the Horizon supervisor process (or `php artisan queue:work`) as a required always-running process here** — unlike scheduled commands, queue workers need their own persistent process (systemd service / Supervisor config), not a cron entry.

## Other always-on processes

None yet. Revisit when Reverb (real-time/WebSockets, see `03-architecture.md`) is added — it needs its own persistent server process too.

---
**Why this file exists:** cron/scheduled-job configuration lives on the server, not in the deployed code, so it's invisible to `git diff` and easy to forget when standing up a new environment or auditing an existing one. Treat this as the source of truth for "what background jobs does this app depend on" — check it against the actual server config during any deploy or environment setup.
