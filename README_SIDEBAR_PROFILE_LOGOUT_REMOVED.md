# Sidebar Profile and Logout Removed

Updated the accounting sidebar so the admin profile card and Logout button no longer appear at the bottom of the left sidebar.

## Changed files

- `resources/views/partials/accounting/sidebar.blade.php`
- `resources/css/pages/hisebghor.css`
- `resources/css/pages/accounting-navigation.css`
- Rebuilt Vite assets under `public/build`

## Notes

- Top-right profile menu remains available.
- Logout remains available from the top-right profile menu.
- Mobile sidebar drawer behavior remains unchanged.
- Sidebar scroll-position persistence remains unchanged.
