# Landing Page Responsive Header Fix

Implemented a responsive landing header fix so the system Login, Landing Admin, and CTA buttons no longer overlap on tablet/mobile widths.

## Updated file

- `resources/views/landing/components/styles.blade.php`

## What changed

- Desktop header buttons now move into the mobile menu at `1120px` and below.
- The header keeps only the logo, language toggle, and mobile menu button on smaller screens.
- Logo width, language toggle, and menu button sizes are reduced for small mobile screens.
- Horizontal overflow is prevented on the landing page.
- Mobile menu still contains System Login, Landing Admin, and CTA buttons, so no access is removed.

## Result

The landing page header now behaves like this:

- Desktop: full navigation and action buttons show normally.
- Tablet/mobile: action buttons are hidden from the top row and shown inside the hamburger menu.
- Small mobile: logo and controls shrink cleanly without overlap.
