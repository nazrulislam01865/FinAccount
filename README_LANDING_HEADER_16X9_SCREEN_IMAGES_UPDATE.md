# Landing Header and 16:9 Feature Image Update

## Updated behavior

- Removed the fixed **Login** and **Landing Admin** buttons from the public landing-page header.
- Removed the same two access buttons from the mobile landing menu.
- The main public call-to-action button remains available.
- Feature-screen image areas now always render at a **16:9** aspect ratio.
- Every feature card keeps its own image upload field in **Landing Admin → Landing Page → Screenshots & Feature Screens**.
- New feature image uploads are validated on both the browser and server and must use an exact 16:9 ratio, such as **1600×900** or **1920×1080**.
- Selecting an image in Landing Admin now shows an immediate 16:9 preview and a clear validation error when the image ratio is incorrect.
- Replacing an image continues to use the existing `public/uploads/landing/screens` storage flow; no database migration is required.

## After deployment

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Make sure the web-server user can write to `public/uploads/landing/screens`.
