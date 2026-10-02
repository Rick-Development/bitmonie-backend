# Bitmonie Bug Fix Notes - 2026-05-06

## Queue And Scheduler Checks

Run these on the VPS before testing the app flows:

```bash
cd /home/bitmonie/api
/usr/local/apps/php84/bin/php artisan queue:failed
/usr/local/apps/php84/bin/php artisan schedule:list
ps -ef | grep "queue:work" | grep -v grep
supervisorctl status
crontab -l
```

Expected scheduler cron:

```bash
* * * * * cd /home/bitmonie/api && /usr/local/apps/php84/bin/php artisan schedule:run >> /dev/null 2>&1
```

If the queue worker is not active, queued deposit/withdrawal emails and queued transaction jobs may not run.

The savings interest scheduler now writes command output to:

```bash
storage/logs/savings-scheduler.log
```

Check it after the next 00:05 Africa/Lagos scheduler run, or run the command manually:

```bash
/usr/local/apps/php84/bin/php artisan savings:process-interest
```

## Transaction History And Details

Updated authenticated endpoints:

```http
GET /api/user/buy-sell/buy/history
GET /api/user/buy-sell/sell/history
GET /api/user/buy-sell/buy/{merchant_reference}/details
GET /api/user/buy-sell/sell/{merchant_reference}/details
```

History rows now include:

- `transaction_id`
- `transaction_hash`
- `rate`
- `date_time`
- `status`
- `internal_status`
- `provider_status`
- `failure_reason`
- `details_endpoint`

The detail endpoints fetch the latest provider state where available, reconcile the local status, and return the full local/provider payload needed by mobile for a transaction detail screen and Quidax dispute support.

## New Migration

```bash
/usr/local/apps/php84/bin/php artisan migrate --path=database/migrations/2026_05_06_000001_add_provider_tracking_to_ramp_transactions_table.php
```

This adds provider tracking fields to `ramp_transactions`:

- `provider_transaction_id`
- `transaction_hash`
- `provider_status`
- `provider_status_checked_at`

The duplicate `jobs` and `failed_jobs` migrations were made idempotent so full `php artisan migrate` can continue safely when those tables already exist.

## Deployment

```bash
cd /home/bitmonie/api
unzip -o bitmonie_bug_report_fixes_20260506.zip
/usr/local/apps/php84/bin/php /home/bitmonie/composer dump-autoload
/usr/local/apps/php84/bin/php artisan migrate
/usr/local/apps/php84/bin/php artisan optimize:clear
/usr/local/apps/php84/bin/php artisan queue:restart
```

If migration is still blocked by older project migrations, run the new migration directly:

```bash
/usr/local/apps/php84/bin/php artisan migrate --path=database/migrations/2026_05_06_000001_add_provider_tracking_to_ramp_transactions_table.php
```
