# Opening Balance Menu and Add Button Fix

Opening Balances is available from:

`Configuration -> Opening Balances`

The add button is on the top-right of the Opening Balances page:

`+ Add Opening Balance`

If the link or button is hidden after deployment, run migrations again. This update adds a permission sync migration so the existing role matrix receives:

- `opening_balances.view`
- `opening_balances.manage`

For a user who only has Manage permission, the add-only URL also works now:

`/opening-balances?action=add`

If the add button is still hidden, open:

`System -> Role Matrix`

Then enable these permissions for the user's role:

- View Opening Balances
- Manage Opening Balances

Run after upload:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
