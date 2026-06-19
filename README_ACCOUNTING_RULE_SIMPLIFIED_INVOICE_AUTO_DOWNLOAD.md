# Accounting Rule Simplified + Auto Invoice Download Update

## Accounting Rule page simplified

The Accounting Rule modal has been simplified back to the earlier easy style:

- Code
- Name
- Category
- Party Type
- Debit Source
- Credit Source
- Allow partial payment / due split
- Generate sales invoice
- Invoice title
- Money account required
- Party required
- Active

The complex posting-line grid was removed from the UI.

The backend still keeps proper rule-based split posting through `accounting_rule_lines`.
When `Allow partial payment / due split` is enabled:

### Sales split rule

```text
Dr Selected Money Account      Paid Amount
Dr Party Receivable COA        Due Amount
    Cr Credit Source           Total Amount
```

### Purchase / expense split rule

```text
Dr Debit Source                Total Amount
    Cr Selected Money Account  Paid Amount
    Cr Party Payable COA       Due Amount
```

Normal rules still use the selected Debit Source and Credit Source with Total Amount on both sides.

## Sales invoice generation

Sales invoices are generated from posted transactions only when the selected accounting rule has `Generate sales invoice` enabled.

Invoice generation happens in:

```text
app/Services/Accounting/SalesInvoiceService.php
```

It is called after posting/updating transactions from:

```text
app/Services/Accounting/TransactionPostingService.php
app/Services/Accounting/TransactionUpdateService.php
```

Invoices are saved in:

```text
sales_invoices
```

## Automatic download

After a sales transaction is posted or updated and an invoice is generated, the system redirects to the Transaction Register and starts the invoice download automatically.

The automatic download uses:

```text
GET /sales-invoices/{salesInvoice}/download
```

The invoice is downloaded as a standalone HTML invoice file. The invoice view page also has:

- Download Invoice
- Print / Save PDF

This avoids adding a new PDF package dependency.

## Important routes

```text
sales-invoices.show
sales-invoices.download
transactions.invoice.generate
```

## Deployment commands

```bash
php artisan migrate --force
php artisan route:clear
php artisan optimize:clear
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
