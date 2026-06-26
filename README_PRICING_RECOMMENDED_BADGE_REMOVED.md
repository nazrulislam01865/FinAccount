# Pricing Recommended Badge Removed

The pricing package recommendation ribbon has been removed from both the public landing page and the landing-admin package editor.

Removed backend fields:

- `packages.*.popular`
- `packages.*.popular_label.bn`
- `packages.*.popular_label.en`

The included migration removes these obsolete keys from existing `landing_page_settings.value` JSON. Existing package names, small tags, descriptions, fees, and features are unchanged.
