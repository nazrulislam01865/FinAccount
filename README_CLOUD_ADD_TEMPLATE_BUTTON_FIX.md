# Cloud Add Template Button Fix

## Problem

The Accounting Rule **+ Add Template** button depended entirely on the Vite `app.js` bundle. If the cloud server had a stale/missing Vite manifest or JavaScript asset, the button had no click handler and appeared to do nothing.

## Fix

- Added `public/js/hisebghor-setup-modals.js` as a direct, cache-versioned browser asset.
- Loaded the setup-modal controller directly from the accounting layout.
- Removed the setup-modal import from the Vite bundle to prevent duplicate click handlers.
- Added safe JSON parsing and idempotent modal initialization.
- The direct script supports create, edit, close, Escape, background click, defaults, and draft context.

This makes all setup modal buttons, including **+ Add Template**, work even when the main Vite bundle is stale or temporarily unavailable.
