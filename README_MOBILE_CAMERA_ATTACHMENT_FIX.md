# Mobile Camera Attachment Fix

This update fixes transaction receipt capture on phone and tablet devices.

## What changed

- Mobile/tablet no longer depends only on the browser file picker capture hint.
- A visible `Take Photo` button now starts the device camera with `navigator.mediaDevices.getUserMedia`.
- The captured photo is converted into a normal uploaded image file and attached to the transaction form.
- A `Browse Image` fallback remains available when a browser blocks camera access.
- Desktop/laptop devices continue to show the normal file upload control.
- The camera UI appears only on touch/coarse-pointer devices, so laptop/desktop users keep the file upload workflow.

## Important browser requirement

Direct in-browser camera access generally requires HTTPS on real mobile devices. Localhost is allowed for development, but LAN/IP HTTP URLs may be blocked by the browser. If blocked, the fallback `Browse Image` button still works.

## Files changed

- `resources/views/transactions/create.blade.php`
- `resources/js/pages/transaction-entry.js`
- `resources/css/pages/hisebghor.css`
- `public/build/*`
