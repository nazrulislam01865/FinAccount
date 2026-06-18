# HisebGhor Fleet-Style Role Access, Branding and Footer

This update ports the Fleet Management sample's company-scoped role matrix and user access behavior into HisebGhor while preserving the existing accounting engine and safe-delete workflow.

## Roles and permissions

- Default roles: Super Admin, Admin User, Accountant, Data Entry User and Viewer.
- Custom company roles can be created from **System > Role Matrix**.
- View and Manage permissions are independent for every supported module.
- Manage-only users can open an add screen without seeing the list. After saving, they return to the add screen with a permission message.
- Delete Records is an independent protected permission. Only Super Admin can grant or revoke it.
- Branding Settings remains permanently restricted to Super Admin.
- Existing company owners/system admins are migrated to the protected Super Admin role.
- Existing accounting users are migrated to Data Entry User.

## User management

- Company-scoped users and roles.
- Create users and assign a role.
- Account statuses: Active, Inactive, Stand By and Disabled.
- Inactive/standby/disabled users are logged out and denied protected access.
- Only Super Admin can assign the Super Admin role or change another user's password.
- A non-Super Admin cannot edit a Super Admin account.
- The final active Super Admin cannot be removed or disabled.

## Permission-aware interface

- Sidebar modules are shown only when the assigned role has View or Manage access.
- Create, Edit and Delete controls are hidden independently.
- Dashboard shortcuts are hidden when the role cannot open the target module.
- Other Master Data only shows master sections the role is allowed to open.
- Login redirects users to the first module their role permits.

## Safe delete preserved

The previous safe-delete workflow was not removed. A role needs both the module's Manage permission and Delete Records permission. Deletion still provides dependency preview, explicit confirmation, relationship detachment, dependent deactivation, and incomplete transaction/journal repair behavior.

## Branding and footer

- **System > Branding Settings** is Super Admin only.
- Upload/change the company logo (PNG, JPG, JPEG, SVG or WebP, maximum 5 MB).
- Upload/change the favicon (ICO, PNG, JPG, JPEG or WebP, maximum 1 MB).
- The uploaded logo is used in the accounting sidebar and authentication pages.
- The uploaded favicon is used in the browser tab.
- The Fleet-style footer is included in accounting and authentication layouts.

## Deployment

```bash
php artisan down || true
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```
