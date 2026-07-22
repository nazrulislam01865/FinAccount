# Invoice/Receipt Download Layout Fix — 2026-07-22

## Fixed

- Downloaded PDFs no longer use fixed coordinates that allow totals and **Prepared By** to overlap.
- Line-item rows now use their actual wrapped content height.
- Empty placeholder rows were removed from PDF, browser view, and print output.
- Documents with many transactions automatically continue onto additional A4 pages.
- Totals, amount in words, notes, prepared-by details, signature, and footer are rendered only after all transaction rows on the final page.
- Replaced the shield graphic with a simple tick mark.
- Added responsive view and print rules to prevent clipping or overlap on different screen sizes and print engines.
- The same shared renderer remains in use for money receipts, sales, purchases, feed sales, feed purchases, and other invoice flows.

## Deployment

```bash
php artisan optimize:clear
sudo systemctl reload php8.4-fpm
```

Use the server's installed PHP-FPM version if it differs.
