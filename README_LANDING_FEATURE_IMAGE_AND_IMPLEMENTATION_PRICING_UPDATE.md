# Landing Feature Image and Implementation Pricing Update

## Public landing page

- Feature-card media areas remain fixed at a responsive 16:9 ratio.
- Uploaded images now use `object-fit: contain` and a matching dark canvas, so the complete screenshot is visible instead of being cropped from the sides.
- The pricing section now follows the implementation-package layout:
  - package icon, name, tag, and recommended ribbon;
  - installation fee, maintenance fee, and server-hosting rows;
  - separate package feature lists;
  - an Important Notes divider and note cards.
- Default package pricing now includes Basic Cloud, Standard Cloud, and On-Premise / Private Deployment.

## Landing Admin

The **Pricing & Packages** editor now manages:

- package icon;
- Bangla and English package names, tags, descriptions, and recommended labels;
- installation, maintenance, and hosting labels, amounts, and notes;
- Bangla and English feature lists;
- Important Notes heading and note-card icons/content.

Feature-screen uploads still require an exact 16:9 image and show a full-image preview.

## Compatibility

Older saved pricing JSON is detected automatically. Legacy package and pricing-note structures are upgraded to the new default implementation-pricing structure, after which administrators can edit and save the new fields normally.

No database migration is required because landing-page content is stored in the existing JSON setting.
