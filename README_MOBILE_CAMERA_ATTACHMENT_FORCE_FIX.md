# Mobile Camera Attachment Force Fix

This update corrects the Transaction Entry attachment UI so phones and tablets show the camera capture interface instead of the desktop upload-file box.

## Changes

- Mobile/tablet attachment area now shows **Take receipt photo / Open Camera** first.
- The desktop **Upload receipt or reference file / Choose Files** block is hidden on touch mobile/tablet screens through CSS media queries, not JavaScript only.
- The **Open Camera** button uses `navigator.mediaDevices.getUserMedia()` to open the device camera inside the page.
- The file picker is no longer automatically opened when camera access fails.
- A fallback **Choose from Gallery** button appears only after the camera cannot be opened.
- Added clearer camera status/error messages, including HTTPS guidance.
- Desktop/laptop still keeps the normal multi-file upload option.

## Important

Direct browser camera access on mobile usually requires HTTPS. On plain HTTP or IP-based access, many mobile browsers block `getUserMedia()`. In that case, the screen now shows a clear message instead of unexpectedly opening the file browser.

## Files changed

- `resources/views/transactions/create.blade.php`
- `resources/js/pages/transaction-entry.js`
- `resources/css/pages/hisebghor.css`
- `public/build/*`
