# Transaction Entry Sale Feed Flow Update

Updated the generic Transaction Entry page for Sale transactions so the sale form now follows the requested sequential flow:

1. Transaction Head
2. What are you selling? (dynamic Business Area master data)
3. Customer (active Party records where type = Customer)
4. Warehouse / Location (active Feed Setup warehouses)
5. Feed Items section using the Feed Sale style stock-aware item rows
6. Total Amount (calculated from feed item lines only)
7. Other Charges
8. Total Bill (Total Amount + Other Charges, posted as the transaction amount)
9. Received Amount
10. Receive Account
11. Existing reference, description, attachment, and action buttons

## Notes

- `selling_type` now stores the selected Feed Business Area code.
- Business Area dropdown is loaded from `feed_business_areas`.
- Warehouse is required for Sale business-area transactions.
- The customer dropdown is limited to active Customer parties.
- The feed item section reads active feed items and current stock balances by warehouse.
- Posting is blocked in the browser if a requested feed quantity exceeds available stock.
- The normal accounting posting flow remains unchanged: the computed Total Bill is submitted as `amount`, and payment/receivable behavior continues through existing accounting rules.
- Production Vite assets were rebuilt.
