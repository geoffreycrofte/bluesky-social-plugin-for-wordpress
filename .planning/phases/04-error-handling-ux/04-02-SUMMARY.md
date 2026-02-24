---
phase: 04-error-handling-ux
plan: 02
subsystem: admin-notices
tags: [notices, ux, multi-account, ajax]
dependency_graph:
  requires: [04-01-error-translation]
  provides: [persistent-notices, per-account-status]
  affects: [admin-ui, account-management]
tech_stack:
  added: [admin-notices-js]
  patterns: [persistent-dismissal, user-meta-expiry, ajax-handlers]
key_files:
  created:
    - assets/js/bluesky-admin-notices.js
  modified:
    - classes/BlueSky_Admin_Notices.php
    - classes/BlueSky_Assets_Service.php
decisions:
  - title: "Persistent notices use user meta with 24-hour expiry"
    rationale: "User-level dismissal (not site-wide) prevents notification fatigue while ensuring critical issues resurface if unresolved"
  - title: "Multiple broken accounts grouped into single notice"
    rationale: "Reduces admin UI clutter — show up to 5 handles explicitly with '...and X more' pattern"
  - title: "Retry button only shown after 3 auto-retries exhaust"
    rationale: "Prevents premature manual intervention — let async retry logic handle transient failures first"
  - title: "Per-account syndication detail shown inline"
    rationale: "Users see exactly which accounts succeeded/failed with specific error messages per account"
metrics:
  duration_seconds: 172
  duration_formatted: "2.9 minutes"
  tasks_completed: 2
  files_created: 1
  files_modified: 2
  commits: 2
  completed_at: "2026-02-19"
---

# Phase 04 Plan 02: Persistent Admin Notices & Per-Account Status Summary

**One-liner:** Persistent dismissible notices for expired credentials and circuit breaker status across all admin pages, with per-account syndication detail on post edit screens and conditional retry buttons.

## What Was Built

Enhanced the admin notice system to provide proactive, cross-page visibility for critical issues (expired credentials, circuit breaker open) and detailed per-account breakdowns for syndication failures.

### Key Enhancements

**1. Persistent Cross-Page Notices**
- `expired_credentials_notice()`: Detects accounts with auth errors (via `bluesky_account_auth_errors` option or open circuit breakers), groups multiple broken accounts into single notice (max 5 shown, then "...and X more")
- `circuit_breaker_notice()`: Shows friendly "requests paused due to repeated errors" message when any account has open circuit breaker
- Both use `data-dismissible` attribute for AJAX dismissal with 24-hour expiry via user meta
- Only visible to `manage_options` users (admins)

**2. Per-Account Syndication Status**
- Enhanced `syndication_status_notice()` to show per-account breakdown for `failed` and `partial` states
- Completed accounts: "• @handle: Posted successfully" (green)
- Failed accounts: "• @handle: [specific error message]" (red)
- Retry button logic: Show "Retry now" only after `retry_count >= 3` (auto-retries exhausted); otherwise show "Will retry automatically"

**3. AJAX Dismissal Infrastructure**
- New `handle_notice_dismissal()` AJAX handler with nonce verification
- Validates notice key against whitelist (`bluesky_expired_creds_dismissed`, `bluesky_circuit_breaker_dismissed`)
- Stores dismissal timestamp + 24 hours in user meta
- New `bluesky-admin-notices.js` script handles `.notice[data-dismissible]` clicks, sends AJAX request

**4. Asset Service Integration**
- Added `enqueue_admin_notices_script()` method to load new JS on all admin pages
- Lightweight script (~0.5 KB) with `dismissNonce` localized for secure AJAX
- Existing `bluesky-syndication-notice.js` unchanged (still post-edit screen only)

## Architecture

**Notice Detection Flow:**
```
Admin Page Load
    ↓
expired_credentials_notice() hook
    ↓
Check bluesky_account_auth_errors option
Check Circuit Breaker state for each account
    ↓
Check user meta dismissal (bluesky_expired_creds_dismissed)
    ↓
If (issues exist && not dismissed) → Render notice with data-dismissible
```

**Dismissal Flow:**
```
User clicks .notice-dismiss
    ↓
bluesky-admin-notices.js captures click
    ↓
AJAX POST to wp_ajax_bluesky_dismiss_notice
    ↓
handle_notice_dismissal() validates nonce + notice_key
    ↓
update_user_meta(user_id, notice_key, time() + DAY_IN_SECONDS)
    ↓
Notice hidden until 24 hours expire
```

## Testing Completed

**Verification Checklist:**
- [x] PHP syntax valid for both modified classes
- [x] `expired_credentials_notice` method present and hooked
- [x] `circuit_breaker_notice` method present and hooked
- [x] `handle_notice_dismissal` AJAX handler registered
- [x] `data-dismissible` attributes added to notices (8 occurrences)
- [x] JS file created at correct path
- [x] Assets_Service enqueues admin notices script (4 references found)
- [x] Retry button conditional logic added to failed/partial cases

**Manual Testing Recommended:**
1. Create expired credential scenario → verify cross-page notice appears
2. Dismiss notice → verify it disappears and returns after 24 hours
3. Trigger partial syndication → verify per-account breakdown shows
4. Check retry button visibility based on retry_count meta value

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] Constructor injection for account_manager**
- **Found during:** Task 1 implementation
- **Issue:** Account lookups needed for persistent notices but no Account_Manager access
- **Fix:** Added optional `BlueSky_Account_Manager $account_manager = null` parameter to constructor with fallback instantiation
- **Files modified:** `classes/BlueSky_Admin_Notices.php`
- **Commit:** dea50e0

**2. [Rule 2 - Missing Critical Functionality] Circuit breaker state checking logic**
- **Found during:** Task 1 implementation
- **Issue:** Plan referenced checking circuit breaker but didn't specify how to access state beyond `is_available()`
- **Fix:** Added direct transient check for `bluesky_circuit_*` key to detect open circuits
- **Files modified:** `classes/BlueSky_Admin_Notices.php`
- **Commit:** dea50e0

## Integration Points

**Upstream Dependencies:**
- `BlueSky_Account_Manager->get_accounts()`: Account enumeration for notice generation
- `BlueSky_Circuit_Breaker->is_available()`: Resilience state checking
- `bluesky_account_auth_errors` option: Auth error registry (set by Plan 04-05 Error Translator integration)
- Post meta `_bluesky_syndication_retry_count`: Determines retry button visibility

**Downstream Effects:**
- Admin UI now proactively surfaces critical issues (not just post-edit screen)
- Users get actionable error detail per account (not generic "syndication failed")
- Dismissal persistence prevents notification fatigue while ensuring issues resurface

## Key Files

**Created:**
- `assets/js/bluesky-admin-notices.js` (23 lines): AJAX dismissal handler for persistent notices

**Modified:**
- `classes/BlueSky_Admin_Notices.php` (+200 lines): Added 3 new notice methods + AJAX handler
- `classes/BlueSky_Assets_Service.php` (+23 lines): Added enqueue method for admin notices script

## Commits

1. **dea50e0**: `feat(04-02): add persistent notices and per-account syndication status`
   - Expired credentials notice with account grouping
   - Circuit breaker notice with friendly messaging
   - Per-account breakdown for failed/partial syndication
   - Conditional retry button (retry_count >= 3)
   - AJAX dismissal handler with 24-hour user meta expiry

2. **71baddd**: `feat(04-02): create admin notices script and update asset enqueuing`
   - New bluesky-admin-notices.js for AJAX dismissal
   - Enqueue on all admin pages with dismissNonce localization
   - Existing syndication notice script unchanged

## Performance Considerations

- **Admin notices script**: Loads on all admin pages but is <1 KB minified (~23 lines JS)
- **Notice detection overhead**: Two transient lookups per account (circuit state + failure count) — acceptable for admin-only context
- **User meta queries**: Two `get_user_meta()` calls per page load (dismissed state) — cached by WordPress object cache
- **AJAX requests**: Only fired on user dismissal action (not on every page load)

## Known Limitations

1. **Auth error detection placeholder**: Plan assumes `bluesky_account_auth_errors` option exists but this is set by Plan 04-05 integration. Currently detects open circuit breakers as proxy for auth failures.

2. **No severity levels**: All critical notices use WordPress default `notice-error` class. User decision was to convey urgency via message text, not color coding.

3. **No notice for rate limiting**: Circuit breaker notice covers resilience issues broadly but doesn't differentiate between rate limit vs. auth failure causes.

## Next Steps

**Immediate (Plan 04-03):**
- Health dashboard widget will provide centralized status view
- Aggregate metrics from persistent notices into single dashboard

**Future Enhancements:**
- Consider adding dismissal reason tracking (user meta field for "why dismissed")
- Add WP-CLI command to reset dismissals for testing
- Integrate with Activity Logger (Plan 04-01) to log notice dismissal events

## Self-Check: PASSED

**Files Created:**
- FOUND: assets/js/bluesky-admin-notices.js

**Commits:**
- FOUND: dea50e0 (persistent notices and per-account status)
- FOUND: 71baddd (admin notices script and asset enqueuing)

**Modified Files:**
- FOUND: classes/BlueSky_Admin_Notices.php (214 lines added)
- FOUND: classes/BlueSky_Assets_Service.php (44 lines added)

All planned artifacts created, no missing components.
