# Receipt website-link fix

The receipt/invoice website is displayed as the direct canonical URL `https://bashiragro.com/`, but every clickable link now targets the canonical direct URL:

`https://bashiragro.com/?source=receipt`

This avoids the `www` redirect that could leave the homepage hero content hidden when Chrome opened the URL from a downloaded PDF.

Applied to:
- downloaded PDF receipts and invoices (explicit PDF URI annotation)
- browser receipt/invoice view
- browser print / Save as PDF

Deployment:

```bash
php artisan optimize:clear
sudo systemctl reload php8.4-fpm
```
