# Landing Business, Demo and CAPTCHA Update

## Public landing page

- Rebuilt the business-suitability section to match the supplied layout.
- The section is displayed immediately before the implementation pricing section.
- Business cards use a responsive three-card row with the remaining cards centered automatically.
- Reworked the demo/contact section into two matching cards: contact information on the left and the request form on the right.
- Added a modal arithmetic CAPTCHA. The modal opens after the visitor completes the demo form and clicks the submit button.
- A demo inquiry is stored only after the CAPTCHA token and answer are verified on the server.
- CAPTCHA challenges expire after five minutes and cannot be reused after successful validation.

## Landing Admin

- **Business Suitability** controls the section heading, icon, description and repeatable business-type cards.
- **Demo & CAPTCHA** controls contact details, form placeholders, success/error messages and all CAPTCHA popup labels.
- Both sections can be enabled or disabled from the landing admin.
- Added direct quick-edit links for both sections on the landing-admin dashboard.

## Security

- CAPTCHA challenge generation is session-based and server-validated.
- CAPTCHA generation and inquiry submission have separate IP rate limits.
- Optional environment setting:

```env
RATE_LIMIT_LANDING_CAPTCHA_PER_MINUTE=20
```

The existing inquiry limit remains controlled by:

```env
RATE_LIMIT_LANDING_INQUIRY_PER_MINUTE=5
```

## Deployment

No database migration is required. After deployment, clear Laravel caches:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
