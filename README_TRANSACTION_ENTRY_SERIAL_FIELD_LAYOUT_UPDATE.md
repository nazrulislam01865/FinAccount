# Transaction Entry Serial Field Layout Update

## What changed

The transaction entry form now displays every input field in one vertical serial order instead of a two-column layout.

This applies to:

- Date
- Transaction Head
- Amount
- Paid/Received Now
- Money Account when it appears
- Remaining Due when it appears
- Party when it appears
- Auto party notice when it appears
- Reference
- Description
- Attachment
- Action buttons

## Why

The previous layout placed fields side-by-side. The requested behavior is that fields should appear one after another, and any conditionally visible field should also appear in the same serial order when it becomes visible.

## Files changed

- `resources/views/transactions/create.blade.php`
- `resources/css/pages/hisebghor.css`
- `public/build/manifest.json`
- `public/build/assets/app-SERIALFORM20260714.css`

## Deploy commands

```bash
php artisan optimize:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
