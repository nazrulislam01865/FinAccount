# Transaction Attachment Update

Implemented a simple transaction attachment workflow for HisebGhor.

## What changed

- Transaction Entry now supports attachments.
- Desktop and laptop screens show a file upload option.
- Phone and tablet screens show a camera capture option for receipt/reference images.
- Attachments are stored against the posted transaction only.
- Drafts do not store file contents; users must choose/capture the attachment again before final posting.
- Transaction Register shows attachment links for each posted transaction.
- Edit Transaction shows existing attachments and allows adding more or removing old ones.
- Attachments are company-isolated and served through authenticated routes.
- Deleting a transaction also deletes its stored attachment files.

## Database

Added migration:

```text
database/migrations/2026_06_18_030000_create_transaction_attachments_table.php
```

It creates:

```text
transaction_attachments
```

## Validation

Allowed files:

```text
jpg, jpeg, png, webp, pdf, doc, docx, xls, xlsx, csv, txt
```

Maximum size:

```text
10 MB per file
```

Maximum files per submission:

```text
5
```

## Routes

```text
transactions.attachments.show
transactions.attachments.destroy
```

## Required deployment

```bash
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run `php artisan storage:link` if the public storage link is missing.
