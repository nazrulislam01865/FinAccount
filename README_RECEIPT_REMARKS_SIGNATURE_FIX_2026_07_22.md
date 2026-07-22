# Receipt remarks and digital signature correction

Updated the shared receipt/invoice renderer used by browser print and PDF download.

- Full remarks are displayed on separate lines instead of being shortened with `...`.
- Pipe-separated transaction details such as transaction head, previous due, and remaining due are printed on the second/third lines.
- The table row height was increased for short receipts so multi-line remarks remain inside the row.
- The prepared user's name is placed above the signature line.
- `DIGITAL SIGNATURE` is printed below the signature line.
- These changes apply to every document using the shared template/PDF renderer, including money receipts, sales invoices, purchase invoices, feed purchases, and feed sales.
