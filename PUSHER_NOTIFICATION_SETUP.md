# FleetMan Pusher Notification Setup

Add these values to the production `.env` file from the Pusher Channels application dashboard:

```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap2
```

`PUSHER_HOST` is optional and should normally remain unset when using Pusher Channels.

Run after deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Add the Laravel scheduler to the server crontab so document, licence and contract reminders are checked every day:

```cron
* * * * * cd /var/www/FleetManagement && php artisan schedule:run >> /dev/null 2>&1
```

The reminder command runs daily at 08:00 in `Asia/Dhaka`. It may also be tested manually:

```bash
php artisan fleet:send-reminders
php artisan fleet:send-reminders --date=2026-06-09
```

Database notifications and the one-minute browser polling fallback work even before Pusher credentials are configured. Pusher enables instant delivery without refreshing the page.
