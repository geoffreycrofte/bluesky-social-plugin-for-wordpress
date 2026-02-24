---
phase: 01-multi-account-foundation
plan: 03
subsystem: syndication
tags: [multi-account, post-syndication, per-account-api, metabox, gutenberg, rest-api]

# Dependency graph
requires:
  - phase: 01-01
    provides: BlueSky_Account_Manager with get_accounts() and is_multi_account_enabled()
  - phase: 01-02
    provides: Multi-account settings UI with account storage and toggle
provides:
  - Multi-account syndication loop that creates Bluesky posts on each selected account
  - Per-account API handler factory method (BlueSky_API_Handler::create_for_account)
  - Account selection UI in both classic editor metabox and Gutenberg pre-publish panel
  - Per-account syndication results stored in account-keyed JSON structure
  - _bluesky_syndication_accounts post meta for selected accounts
affects: [01-04, 01-05, phase-2]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Factory method pattern for per-account API handler instances"
    - "Account-keyed JSON structure in post meta for multi-account results"
    - "Branching syndication logic based on multi-account toggle"
    - "Progressive disclosure in metabox (account selection hidden when single-account)"

key-files:
  created: []
  modified:
    - classes/BlueSky_API_Handler.php
    - classes/BlueSky_Plugin_Setup.php
    - classes/BlueSky_Post_Metabox.php
    - blocks/bluesky-pre-publish-panel.js

key-decisions:
  - "Factory method creates isolated per-account API handler instances (avoids auth state sharing)"
  - "Syndication branches on multi-account toggle at method entry (single-account path unchanged)"
  - "Selected accounts stored as JSON array in _bluesky_syndication_accounts post meta"
  - "Auto-syndicate accounts pre-selected for new posts when no explicit selection"
  - "Per-account results keyed by account UUID in _bluesky_syndication_bs_post_info"
  - "First successful account ID saved for backward compatibility with Discussion display"

patterns-established:
  - "Multi-account branching pattern: check toggle → route to multi-account method → preserve single-account path"
  - "Account selection UI pattern: checkboxes with auto-syndicate pre-selection, disabled when 'Don't syndicate' checked"
  - "Gutenberg data pattern: compose withSelect + withDispatch for meta updates"

# Metrics
duration: 3min
completed: 2026-02-16
---

# Phase 01 Plan 03: Multi-Account Syndication Summary

**Per-post account selection in classic/Gutenberg editors with syndication loop creating Bluesky posts on each selected account, storing account-keyed results**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-16T19:17:03Z
- **Completed:** 2026-02-16T19:20:36Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Per-account API handler factory enables isolated authentication per account
- Syndication loops over selected accounts, creating Bluesky posts on each
- Account selection UI in both classic editor metabox and Gutenberg pre-publish panel
- Per-account syndication results stored in account-keyed JSON structure
- Single-account syndication path completely unchanged (backward compatible)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add per-account API handler factory and update syndication loop** - `a0b551c` (feat)
2. **Task 2: Add account selection UI in classic editor and Gutenberg** - `6eea225` (feat)

## Files Created/Modified
- `classes/BlueSky_API_Handler.php` - Added static factory method create_for_account() for per-account API handler instances
- `classes/BlueSky_Plugin_Setup.php` - Added multi-account branching in syndicate_post_to_bluesky() and new syndicate_post_multi_account() method
- `classes/BlueSky_Post_Metabox.php` - Added account_manager instance, registered _bluesky_syndication_accounts post meta, rendered account selection checkboxes in metabox, localized account data for Gutenberg
- `blocks/bluesky-pre-publish-panel.js` - Added renderAccountSelection() method with CheckboxControl for each account, integrated with WordPress data store via compose()

## Decisions Made
- **Factory method approach:** BlueSky_API_Handler::create_for_account() creates isolated instances rather than modifying existing API handler. This avoids auth state sharing between accounts and maintains clean separation.
- **Branching at entry point:** Multi-account check happens immediately in syndicate_post_to_bluesky() before any single-account code runs. This ensures single-account path remains unchanged.
- **Auto-syndicate pre-selection:** New posts automatically select accounts with auto_syndicate=true when no explicit selection exists. Falls back to this on publish if _bluesky_syndication_accounts is empty.
- **Backward compatibility:** Saved _bluesky_account_id pointing to first successful account for Discussion display (existing code expects single account ID).
- **Account-keyed structure:** _bluesky_syndication_bs_post_info stores results as `{account_uuid: {uri, cid, url, syndicated_at, success}}` rather than single object. Enables per-account status tracking.
- **Inline JS for interaction:** Used inline script in classic editor to disable account checkboxes when "Don't syndicate" is checked (simple DOM manipulation, no build step needed).
- **Gutenberg compose pattern:** Used compose() with withSelect + withDispatch rather than separate HOCs. Cleaner data flow for meta updates.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation proceeded smoothly. All expected files and methods existed as documented in prior plan summaries.

## User Setup Required

None - no external service configuration required. Multi-account syndication works once multi-account toggle is enabled in settings UI (completed in 01-02).

## Next Phase Readiness
- Multi-account syndication foundation complete
- Ready for Phase 01-04: Multi-Account Discussion Display (needs to detect account-keyed post info structure)
- Ready for Phase 01-05: Testing & Validation (syndication UI and execution ready for comprehensive tests)
- Blocked on: None

## Self-Check

Verifying claimed files and commits exist:

**Files modified:**
- classes/BlueSky_API_Handler.php - FOUND
- classes/BlueSky_Plugin_Setup.php - FOUND
- classes/BlueSky_Post_Metabox.php - FOUND
- blocks/bluesky-pre-publish-panel.js - FOUND

**Commits:**
- a0b551c (Task 1) - FOUND
- 6eea225 (Task 2) - FOUND

**Self-Check: PASSED** - All claimed files and commits verified.

---
*Phase: 01-multi-account-foundation*
*Completed: 2026-02-16*
