---
phase: 01-multi-account-foundation
plan: 05
subsystem: ui
tags: [wordpress, multi-account, e2e-testing, ux]

# Dependency graph
requires:
  - phase: 01-01
    provides: Account Manager CRUD, migration, feature toggle
  - phase: 01-02
    provides: Settings page multi-account UI
  - phase: 01-03
    provides: Multi-account syndication and editor selection
  - phase: 01-04
    provides: Async pipeline threading, cache scoping, discussion display
provides:
  - Human-verified end-to-end multi-account flow
  - Bug fixes for nested forms, auth testing, per-account transient scoping
  - UX improvements: email login, handle auto-completion, debug bar, unlink button
affects: [02-codebase-refactoring]

# Tech tracking
tech-stack:
  added: []
  patterns: [normalize-handle pattern, JS template repeater for dynamic forms, standalone form submission outside Settings API]

key-files:
  created: []
  modified:
    - classes/BlueSky_Plugin_Setup.php
    - classes/BlueSky_API_Handler.php
    - classes/BlueSky_Account_Manager.php
    - classes/BlueSky_Post_Metabox.php
    - classes/BlueSky_Discussion_Display.php
    - assets/js/bluesky-social-admin.js
    - assets/js/bluesky-discussion-display.js
    - blocks/bluesky-pre-publish-panel.js

key-decisions:
  - "Nested HTML forms replaced with div containers + JS standalone form submission"
  - "Auth testing uses direct createSession API call (not authenticate() method)"
  - "Per-account transient keys via account_id property on API handler"
  - "Add Account is JS repeater, processed on Save Changes (not immediate submit)"
  - "Handle normalization: email passthrough, bare username gets .bsky.social suffix"

patterns-established:
  - "Account action pattern: div.bluesky-account-action + JS submitAccountAction() for forms inside Settings API form"
  - "Handle normalization: normalize_bluesky_handle() for consistent identifier handling"

# Metrics
duration: 3 sessions (iterative bug fixing with human testing)
completed: 2026-02-17
---

# Plan 05: End-to-End Verification Checkpoint Summary

**Human-verified multi-account flow with 3 rounds of bug fixes and 6 UX improvements**

## Performance

- **Duration:** ~3 sessions of iterative testing and fixing
- **Completed:** 2026-02-17
- **Tasks:** 1 (human verification checkpoint)
- **Files modified:** 8

## Accomplishments
- All 7 test groups from Phase 1 verification plan passed
- Fixed critical nested form architecture (HTML forbids nested forms)
- Fixed per-account auth isolation (scoped transient keys)
- Added 6 UX improvements requested during testing

## Bug Fixes (3 Rounds)

### Round 1
- **Nested forms**: Replaced inner `<form>` with `<div>` + JS standalone form submission
- **Nonce conflict**: Custom nonce field names per account action
- **Gutenberg SSR**: Added `REST_REQUEST` check alongside `DOING_AJAX`

### Round 2
- **Add Account redesign**: JS template repeater instead of immediate form submit
- **Duplicate accounts**: Added case-insensitive handle duplicate check
- **Undefined 'id' key**: Defensive `$account['id'] ?? $account_key` throughout
- **Migration status**: "Credentials Configured" status for migrated accounts

### Round 3
- **Auth test failure**: Direct `createSession` API call instead of broken `authenticate()` return
- **Per-account isolation**: `$account_id` property on API handler for scoped transients
- **Syndication routing**: Multi-account path uses per-account API handler factory correctly
- **Post metabox errors**: Defensive 'id' access in account iteration

## UX Improvements (6)
1. Email/handle login with smart `.bsky.social` auto-completion
2. Info text above account listing table explaining active account role
3. Multi-account debug bar entries (accounts, active, global settings, schema version)
4. Legacy login fields grayed out when multi-account is enabled
5. Unlink button in discussion metabox with confirm dialog
6. Auto-refresh preview in pre-publish panel when opened

## Files Modified
- `classes/BlueSky_Plugin_Setup.php` — Form architecture, handle normalization, debug bar, legacy field graying
- `classes/BlueSky_API_Handler.php` — Per-account transient scoping via account_id property
- `classes/BlueSky_Account_Manager.php` — Duplicate check, defensive 'id' access, migration DID fix
- `classes/BlueSky_Post_Metabox.php` — Defensive 'id' access in account iteration
- `classes/BlueSky_Discussion_Display.php` — Unlink button and AJAX handler
- `assets/js/bluesky-social-admin.js` — Standalone form helper, repeater, legacy field toggle
- `assets/js/bluesky-discussion-display.js` — Unlink button handler with confirm
- `blocks/bluesky-pre-publish-panel.js` — Auto-refresh on publish sidebar open

## Decisions Made
- Nested forms are invalid HTML — replaced with JS-based standalone form submission
- Auth testing bypasses authenticate() method to avoid transient interference
- Handle normalization happens server-side in sanitize_settings (not client-side)
- Unlink removes both _bluesky_syndicated and _bluesky_syndication_bs_post_info meta

## Deviations from Plan
The verification plan was a human checkpoint — deviations are the bug fixes and UX improvements that emerged from real user testing, which is exactly the purpose of this checkpoint.

## Issues Encountered
- Three rounds of iterative bug fixing required before approval
- The nested form issue was architectural (HTML spec limitation) requiring a design change
- Per-account auth isolation required adding account_id to the API handler class

## Next Phase Readiness
- Phase 1 complete — multi-account foundation verified end-to-end
- Ready for Phase 2: Codebase Refactoring
- Key concern for Phase 2: Plugin_Setup.php is now even larger after multi-account additions

---
*Phase: 01-multi-account-foundation*
*Completed: 2026-02-17*
