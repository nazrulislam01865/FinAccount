# Receipt and Invoice Corrections (2026-07-22)

Implemented across money receipts, sales invoices, purchase invoices, feed purchases and feed sales:

- Uses the same shared Blade layout and shared PDF renderer.
- Bundled Bashir Agro logo fallback works without a public storage symlink.
- Company email removed from printed header.
- Long remarks wrap across multiple lines in the PDF table.
- Prepared-by email removed.
- Prepared user name appears in the digital signature area.
- User company position is optional and only printed when present.
- Downloaded money receipt uses the download date as Receipt Date and Prepared Date.
- Critical print styling is embedded with the shared template, so cloud deployments do not depend on a rebuilt Vite CSS file.

## Required deployment commands

```bash
php artisan migrate --force
php artisan optimize:clear
sudo systemctl reload php8.4-fpm
```

If a different PHP-FPM version is installed, reload that matching service instead.
