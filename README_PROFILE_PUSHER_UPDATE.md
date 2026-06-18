# HisebGhor Profile and Pusher Notifications

## Added

- Top-right user account menu in every authenticated accounting page.
- Profile page with profile-picture upload and secure password change.
- Database notification center with unread count, mark-read, and mark-all-read actions.
- Private Pusher channel per user: `private-hisebghor.user.{userId}`.
- Real-time event: `hisebghor-notification`.
- Automatic company-scoped notifications for successful create, update, delete, branding, user, role, and company-setup actions.
- Polling every 60 seconds as a fallback when Pusher is not configured or unavailable.
- Pusher REST delivery without adding another Composer dependency.

## Required environment values

Add these values to the deployed `.env` file:

```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap2
PUSHER_HOST=
```

`PUSHER_HOST` is optional. Leave it empty when using the normal Pusher Channels service.

## Deployment commands

```bash
php artisan down || true
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Make sure `storage` and `bootstrap/cache` are writable by the web-server user. Profile pictures are stored on the `public` disk but are streamed through an authenticated route, so the profile feature does not depend on a public storage symlink.
