# Landing Feature Image Ratio Update

## Update

- Removed the exact 16:9 upload restriction from **Landing Admin → Screenshots & Feature Screens**.
- PNG, JPG, JPEG, WEBP, and GIF images up to 4 MB can now be uploaded in portrait, square, or landscape dimensions.
- The public landing page keeps a fixed 16:9 frame.
- Uploaded images use `object-fit: contain`, so the complete image is displayed without stretching or cropping.
- The Landing Admin preview uses the same 16:9 fitting behaviour.
- Invalid or unreadable image files are still rejected.

No database migration is required.
