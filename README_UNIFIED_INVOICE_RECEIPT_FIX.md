# Unified Invoice and Receipt Fix

This update replaces separate PDF layouts with one shared PDF renderer:

- `app/Services/Accounting/UnifiedDocumentPdfService.php`
- Due receipts use it through `PaymentReceiptPdfService`.
- Sales, general purchase, asset purchase, feed sale, and feed purchase invoices use it through `SalesInvoicePdfService`.
- The existing browser print views use `resources/views/accounting/partials/unified-document.blade.php`.

The totals block is completed before the Prepared By section is positioned, preventing overlap. Header columns and document titles are width-constrained to remain inside the A4 border.

After deployment:

```bash
php artisan optimize:clear
```

No migration is required.
