# FleetMan numbered pagination and list table scroll fix

Updated areas:

- Fuel Recharge list
- Driver Log / Attendance list
- Global FleetMan list table scrolling
- Desktop sidebar behavior

What changed:

1. Fuel Recharge and Driver Log now use server-side page-wise pagination with numbered page buttons.
2. Previous/Next-only pagination was replaced with page numbers and ellipsis for long page sets.
3. KPI counters now use backend summaries for the full filtered result set instead of only the current page rows.
4. Backend `/records` responses now include `pagination.summary` for list counters.
5. List table content scrolls inside the table container, so long tables do not push the whole page endlessly.
6. Table headers stay sticky inside the table scroll area.
7. Desktop sidebar is fixed to the viewport, so it no longer moves upward when the page scrolls.
8. Existing split JS assets were regenerated directly into `public/js/dist`, so deployment does not require a local frontend rebuild.

After deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
```
