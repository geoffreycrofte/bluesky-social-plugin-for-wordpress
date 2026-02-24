---
phase: 01-multi-account-foundation
plan: 02
subsystem: multi-account-ui
tags: [settings-ui, progressive-disclosure, account-management, admin-interface]

dependency_graph:
  requires:
    - BlueSky_Account_Manager class (from 01-01)
    - CRUD operations (get_accounts, add_account, remove_account, etc.)
  provides:
    - Multi-account toggle UI in settings page
    - Connected accounts list with status indicators
    - Add/remove account forms with nonce protection
    - Active account switcher
    - Auto-syndication toggles per account
    - Discussion account selector
  affects:
    - classes/BlueSky_Plugin_Setup.php (constructor, sanitize, render methods)
    - social-integration-for-bluesky.php (plugin initialization)
    - assets/js/bluesky-social-admin.js (progressive disclosure)
    - assets/css/bluesky-social-admin.css (account list styling)

tech_stack:
  added:
    - Multi-account settings UI with progressive disclosure
    - Account CRUD forms in WordPress admin
    - Status badge system (authenticated/error indicators)
  patterns:
    - Progressive disclosure (hide multi-account UI when toggle off)
    - Nonce verification for all form actions
    - Immediate authentication test after account addition
    - Confirmation dialogs for destructive actions
    - WordPress admin form-table layout

key_files:
  created: []
  modified:
    - classes/BlueSky_Plugin_Setup.php (added account_manager property, handle_account_actions method, multi-account rendering)
    - social-integration-for-bluesky.php (pass account_manager to Plugin Setup constructor)
    - assets/js/bluesky-social-admin.js (toggle handler, remove confirmation)
    - assets/css/bluesky-social-admin.css (account list table, status badges, active indicator)

decisions:
  - Multi-account section uses progressive disclosure (hidden by default, revealed by toggle)
  - Status determined by DID presence (authenticated if DID exists)
  - Auto-syndication toggle submits immediately on change (no save button needed)
  - Discussion account selector also submits on change for instant feedback
  - Remove account shows orphaned post count in success message
  - Switch account clears content transients to refresh cached data
  - Authentication test runs immediately after adding account (validates credentials)

metrics:
  duration_minutes: 3
  tasks_completed: 2
  commits: 2
  files_created: 0
  files_modified: 4
  completed_date: 2026-02-16
---

# Phase 01 Plan 02: Multi-Account UI Summary

Multi-account management interface with progressive disclosure, account CRUD forms, and status indicators.

## What Was Built

Created the admin UI for managing multiple Bluesky accounts, integrating with the Account Manager foundation from Plan 01. This includes:

1. **Multi-Account Toggle & Progressive Disclosure**
   - Added `enable_multi_account` checkbox in Account Settings tab
   - JavaScript-based progressive disclosure: section hidden when toggle off, slides down when enabled
   - Preserves existing single-account UX when feature disabled

2. **Account Manager Integration**
   - Added `account_manager` property to `BlueSky_Plugin_Setup` class
   - Updated constructor signature to accept Account Manager (nullable for backward compatibility)
   - Updated plugin initialization to pass Account Manager instance

3. **Account CRUD Forms**
   - **Add Account Form**: Name, Handle, App Password fields with inline authentication test
   - **Remove Account**: Confirmation dialog + orphaned post count reporting
   - **Switch Active Account**: Updates active account and clears content caches
   - **Toggle Auto-Syndication**: Per-account checkbox with instant save
   - All actions protected by WordPress nonces

4. **Connected Accounts Table**
   - WordPress-style `wp-list-table` with columns: Name, Handle, Status, Auto-Syndicate, Actions
   - Active account marked with visual badge
   - Status badges: authenticated (green checkmark), not authenticated (red X)
   - Actions: "Make Active" button (disabled if already active), "Remove" button

5. **Discussion Account Selector**
   - Dropdown in Discussions tab to choose which account's threads to display
   - Only visible when multi-account enabled
   - Submits on change for instant feedback

6. **Form Action Handler**
   - Added `handle_account_actions()` method called at top of `render_settings_page()`
   - Processes POST requests for all account operations
   - Uses `add_settings_error()` for admin notices (success/error/warning)
   - Authentication test after account addition updates DID if successful

## Integration Points

- **Plugin Setup Constructor**: Now accepts `BlueSky_Account_Manager` as second parameter (nullable)
- **Main Plugin File**: Passes `$bluesky_account_manager` to Plugin Setup
- **Settings Sanitization**: Added `enable_multi_account` boolean handling
- **Settings Fields**: New field registration for multi-account toggle and discussion account
- **JavaScript**: Progressive disclosure listener on page load and toggle change
- **CSS**: Status badge colors aligned with WordPress admin palette

## UI/UX Flow

**When Multi-Account Disabled (Default):**
- Settings page identical to current single-account experience
- Multi-account section completely hidden
- No UI complexity added

**When Multi-Account Enabled:**
1. Toggle checkbox reveals multi-account section with slide-down animation
2. Connected accounts table shows all accounts with status and actions
3. Add account form appears below table
4. Discussion account dropdown appears in Discussions tab

**Account Addition Flow:**
1. Admin fills Name, Handle, App Password
2. Submit triggers nonce verification
3. Password encrypted via `BlueSky_Helpers::bluesky_encrypt()`
4. Account added to storage via Account Manager
5. Authentication test attempts to connect
6. If auth succeeds: DID saved, success notice shown
7. If auth fails: warning notice shown (account still added but flagged)

**Account Removal Flow:**
1. Admin clicks "Remove" button
2. JavaScript confirmation dialog: "Remove this account? Discussion threads for posts syndicated with this account will no longer load."
3. If confirmed, POST with nonce verification
4. Account Manager removes account and returns orphaned post count
5. Success notice shows count if > 0

**Account Switching Flow:**
1. Admin clicks "Make Active" on non-active account
2. POST with nonce verification
3. Account Manager updates active account
4. Content transients cleared (forces refresh of cached Bluesky data)
5. Success notice shown

## Status Indicators

- **Authenticated** (green): Account has `did` field populated (successful authentication occurred)
- **Not Authenticated** (red): Account missing `did` field (credentials may be invalid)

Status determined by DID presence rather than live API check (avoids blocking admin page load).

## Success Criteria Met

- [x] Multi-account toggle works as progressive disclosure (hidden by default per user decision)
- [x] Account CRUD operations flow through settings page to Account Manager
- [x] All form actions have CSRF protection via nonces
- [x] Account status (authenticated/not authenticated) displayed per account
- [x] Discussion account global setting available in Discussions tab
- [x] No visual regressions when multi-account is disabled
- [x] JavaScript handles toggle animation and remove confirmation
- [x] CSS provides clean styling consistent with WordPress admin

## Deviations from Plan

None - plan executed exactly as written.

## Commits

1. **feat(01-02): add multi-account UI to settings page** (621a801)
   - Added Account Manager property to Plugin Setup constructor
   - Created handle_account_actions() for CRUD form processing
   - Implemented add/remove/switch account operations
   - Added render_multi_account_toggle() with accounts table
   - Added render_discussion_account_field() for discussion source
   - All form actions use proper nonce verification

2. **feat(01-02): add progressive disclosure JS and account list CSS** (1fe9357)
   - Added multi-account toggle handler with slideDown/slideUp animation
   - Added remove account confirmation dialog
   - Added CSS for account list table with column sizing
   - Added status badge styles (green/red/yellow)
   - Added active account indicator badge

## Self-Check: PASSED

Verified all pattern matches:
```bash
FOUND: enable_multi_account (3 occurrences in Plugin Setup)
FOUND: handle_account_actions (method definition and call)
FOUND: bluesky_add_account (form field, nonce, POST handler)
FOUND: bluesky_remove_account (form field, nonce, POST handler)
FOUND: bluesky_discussion_account (field registration, nonce, render method)
FOUND: private $account_manager (property declaration)
FOUND: __construct(...BlueSky_Account_Manager... (constructor signature)
FOUND: check_admin_referer('bluesky_add_account') (nonce verification)
FOUND: check_admin_referer('bluesky_remove_account_*') (nonce verification)
FOUND: check_admin_referer('bluesky_switch_account_*') (nonce verification)
FOUND: new BlueSky_Plugin_Setup(..., $bluesky_account_manager) (main plugin file)
FOUND: multi-account (2 occurrences in JS)
FOUND: bluesky-account-list (5 occurrences in CSS)
FOUND: bluesky-remove-account-btn (confirmation handler in JS)
FOUND: bluesky-status-badge (CSS styles)
```

All commits verified:
```bash
FOUND: 621a801
FOUND: 1fe9357
```

## Next Steps

This plan completes the UI foundation for multi-account management. Subsequent plans will:
- **Plan 03**: Multi-account syndication (use active account for new posts)
- **Plan 04**: Post edit account switching (change syndication account per post)
- **Plan 05**: Discussion display account selection (use selected account for thread display)

The settings page now provides administrators with full control over multiple Bluesky accounts while maintaining the simple single-account experience for users who don't need the feature.
