# Scheduled jobs (skeleton — jobs arrive in Phases 22/24)

- Scripts here run via cron/CLI only, never over HTTP (`cron/` is blocked
  in the root `.htaccess` and guarded by `index.php`).
- Every script must verify `CRON_TOKEN` from `.env` when triggered by URL,
  and refuse to run if it is missing or mismatched.
- Planned jobs: scheduled database backups + cleanup (Phase 24),
  campaign expiry (Phases 19–21).
- Live jobs (Phase 22): `send-notifications.php` (every minute — delivers
  the queued queue with rate limits + retries), `pickup-reminders.php`
  (daily 08:00 — queues tomorrow's pickup reminders).

Example cPanel cron (hourly backup check, Phase 24):

    0 * * * * /usr/bin/php /home/USER/public_html/cron/backup.php >> /dev/null 2>&1
