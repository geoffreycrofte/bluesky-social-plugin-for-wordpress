---
phase: 01-multi-account-foundation
plan: 04
subsystem: frontend-display
tags: [async-loading, cache, discussion, ui, account-selector]
dependency_graph:
  requires: [01-02, 01-03]
  provides: [account-scoped-cache, async-account-support, discussion-account-display, account-selector-ui]
  affects: [frontend-blocks, widgets, async-handlers, discussion-display]
tech_stack:
  added: []
  patterns: [progressive-disclosure, per-account-api-handlers, account-scoped-cache-keys]
key_files:
  created: []
  modified:
    - classes/BlueSky_Helpers.php
    - classes/BlueSky_Plugin_Setup.php
    - classes/BlueSky_Render_Front.php
    - assets/js/bluesky-async-loader.js
    - classes/BlueSky_Discussion_Display.php
    - blocks/bluesky-profile-card.js
    - blocks/bluesky-posts-feed.js
    - classes/widgets/BlueSky_Profile_Widget.php
    - classes/widgets/BlueSky_Posts_Widget.php
decisions:
  - Cache keys include account_id when provided (backward compatible with null)
  - Async handlers create per-account API handlers only when account_id provided and multi-account enabled
  - Discussion display uses helper method to centralize syndication info extraction
  - Discussion thread fetched using configured discussion account's API handler
  - Account selector only shown when 2+ accounts exist (progressive disclosure)
  - Block account data passed via wp_localize_script pattern
metrics:
  duration_minutes: 7.1
  tasks_completed: 3
  files_modified: 9
  commits: 3
  completed_date: 2026-02-16
---

# Phase 1 Plan 04: Async Pipeline & Discussion Account Threading Summary

**One-liner:** Account-scoped async loading with per-account cache keys, discussion display using configured account, and progressive account selector UI in blocks/widgets.

## What Was Built

### Task 1: Account-Scoped Cache Keys & Async Handler Support
**Commit:** ac80c60

- Updated all cache key methods in `BlueSky_Helpers.php` to accept optional `account_id` parameter
- Added `clear_account_cache($account_id)` method for targeted cache clearing
- Updated three async AJAX handlers (`ajax_async_posts`, `ajax_async_profile`, `ajax_async_auth`) to:
  - Read `account_id` from params
  - Create per-account API handler when account_id provided and multi-account enabled
  - Pass account_id through to render methods
- Updated `BlueSky_Render_Front.php` shortcodes and render methods to accept and thread account_id
- Updated JS async loader to send account_id for auth requests
- **All changes backward compatible:** null/empty account_id produces identical behavior to current code

**Key pattern:** Cache keys transform from `bluesky_plugin_wordpress-profile` to `bluesky_plugin_wordpress-profile-{account_uuid}` when account_id provided.

### Task 2: Discussion Display for Account-Keyed Syndication
**Commit:** 3ea1c77

- Added `BlueSky_Account_Manager` instance to `BlueSky_Discussion_Display`
- Created `get_syndication_info_for_discussion($post_id)` helper method:
  - Handles old format (direct object with `uri` key)
  - Handles new account-keyed format (`{account_uuid: {uri, cid, ...}}`)
  - Reads configured discussion account preference
  - Falls back to first successful syndication if discussion account not found
  - Falls back to first entry as final fallback
- Updated all 5 references to `_bluesky_syndication_bs_post_info` to use helper method:
  - `render_discussion_metabox()`
  - `ajax_refresh_discussion()`
  - `render_frontend_discussion()`
  - `add_discussion_to_content()`
  - Internal helper method (single centralized reference)
- Updated `fetch_and_render_discussion()` to create per-account API handler when multi-account enabled
- **Backward compatible:** Old format posts and single-account mode work identically

**Key pattern:** Centralized syndication info extraction with format detection and graceful fallback chain.

### Task 3: Account Selector UI in Blocks & Widgets
**Commit:** ebffb97

- **PHP (BlueSky_Plugin_Setup.php):**
  - Built account options array in `register_gutenberg_blocks()`
  - Added `wp_localize_script` to pass account data as `blueskyBlockData` to both blocks
  - Added `accountId` attribute (type: string, default: "") to both block registrations

- **Gutenberg Blocks (JS):**
  - Added `accountId` attribute to both block definitions
  - Added account selector `PanelBody` before display options panel
  - Used `SelectControl` with options from `window.blueskyBlockData.accounts`
  - Progressive disclosure: selector only shown when `accounts.length > 1` (2+ real accounts)
  - Follows existing `wp.element.createElement` pattern (no JSX)

- **Classic Widgets (PHP):**
  - Added `form()` method with account dropdown (only shown when multi-account enabled and 1+ accounts)
  - Added `update()` method to sanitize account_id
  - Updated `widget()` method to:
    - Read account_id from instance
    - Create per-account API handler when account_id provided
    - Pass account_id to render methods

**Key pattern:** Progressive disclosure — UI only appears when needed (2+ accounts). Default (empty) uses active account.

## Deviations from Plan

None - plan executed exactly as written.

## Technical Decisions

1. **Cache key backward compatibility:** Null account_id parameter preserves existing behavior (no suffix added). This ensures single-account sites and existing code continue working without changes.

2. **Discussion account fallback chain:** Configured discussion account → first successful syndication → first entry. This ensures discussion threads display even if the configured account wasn't used for syndication.

3. **Progressive disclosure pattern:** Account selectors only appear when multiple accounts exist. This prevents UI clutter for single-account users and makes multi-account features opt-in at the UI level.

4. **Per-account API handler creation:** Async handlers check both account_id presence AND multi-account enabled flag before creating isolated API handler. This prevents unnecessary handler creation in single-account mode.

5. **Centralized syndication info extraction:** Single helper method handles both old and new post meta formats. This prevents code duplication and ensures consistent format handling across 5 different code locations.

## Integration Points

**Connects to:**
- 01-02: Uses `BlueSky_Account_Manager` for account data and multi-account checks
- 01-03: Reads account-keyed `_bluesky_syndication_bs_post_info` structure

**Enables:**
- 01-05: Provides account selector UI foundation that settings page can reference
- Phase 2+: Account-scoped cache and async loading ready for per-author accounts

## Testing Notes

**Critical paths to verify:**
1. Frontend blocks with account selector → select account → verify correct account's data loads
2. Discussion thread on multi-account syndicated post → verify uses configured discussion account
3. Cache clearing on account change → verify no stale data pollution
4. Single-account mode → verify no account selectors appear, all behavior identical to pre-Phase-1
5. Old format syndicated posts → verify discussion display still works (backward compatibility)

## Self-Check

Verifying implementation claims:

**Created files:** None (all modifications to existing files)

**Modified files:**
- [x] classes/BlueSky_Helpers.php — account_id parameters added to cache key methods
- [x] classes/BlueSky_Plugin_Setup.php — async handlers updated, block localization added
- [x] classes/BlueSky_Render_Front.php — render methods accept account_id
- [x] assets/js/bluesky-async-loader.js — auth handler sends account_id
- [x] classes/BlueSky_Discussion_Display.php — helper method and per-account API handler
- [x] blocks/bluesky-profile-card.js — account selector and accountId attribute
- [x] blocks/bluesky-posts-feed.js — account selector and accountId attribute
- [x] classes/widgets/BlueSky_Profile_Widget.php — form/update/widget with account_id
- [x] classes/widgets/BlueSky_Posts_Widget.php — form/update/widget with account_id

**Commits:**
- [x] ac80c60 — Task 1 (cache keys, async handlers, render methods, JS loader)
- [x] 3ea1c77 — Task 2 (discussion display, helper method, per-account API handler)
- [x] ebffb97 — Task 3 (block UI, widget UI, localized data)

## Self-Check: PASSED

All claimed files exist and contain expected implementation. All 3 commits verified in git log. All tasks completed successfully with backward compatibility maintained.
