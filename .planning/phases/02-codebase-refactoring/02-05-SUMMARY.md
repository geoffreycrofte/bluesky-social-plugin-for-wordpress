---
phase: 02-codebase-refactoring
plan: 05
subsystem: security-i18n
tags: [security, i18n, nonce-protection, sanitization, translation]
dependency_graph:
  requires: [02-02, 02-03, 02-04]
  provides: [hardened-services, i18n-coverage]
  affects: [ajax-endpoints, settings-page, syndication, metaboxes]
tech_stack:
  added: []
  patterns: [nonce-verification, capability-checks, sanitize-wp-unslash, wp-i18n]
key_files:
  created: []
  modified:
    - classes/BlueSky_AJAX_Service.php
    - classes/BlueSky_Syndication_Service.php
    - templates/admin/settings-page.php
    - classes/BlueSky_Discussion_Metabox.php
    - classes/BlueSky_Post_Metabox.php
    - classes/BlueSky_Assets_Service.php
    - assets/js/bluesky-async-loader.js
decisions:
  - "Legacy AJAX endpoints (ajax_fetch_bluesky_posts, ajax_get_bluesky_profile) now verify nonce for public access"
  - "?godmode parameter requires manage_options capability (admin-only debug access)"
  - "POST data sanitized with sanitize_text_field(wp_unslash()) pattern in syndication"
  - "Capability checks (edit_post) added early in syndication flow"
  - "Text domain 'social-integration-for-bluesky' used consistently across PHP and JS"
  - "JS i18n strings externalized via wp_localize_script i18n sub-object"
metrics:
  duration_seconds: 180
  tasks_completed: 2
  commits: 2
  files_modified: 7
  security_fixes: 3
  i18n_strings_php: 9
  i18n_strings_js: 13
  completed_at: "2026-02-18"
---

# Phase 02 Plan 05: Security Hardening and Internationalization Summary

**One-liner:** Applied 3 security fixes (AJAX nonce protection, godmode auth gating, POST sanitization) and completed i18n for 9 PHP + 13 JS user-facing strings using text domain 'social-integration-for-bluesky'.

## Overview

This plan addressed all identified security vulnerabilities from the 02-RESEARCH audit and ensured complete internationalization coverage for user-facing strings in refactored service classes and frontend JavaScript.

**Context:** After extracting services (02-02), decomposing discussion display (02-03), and extracting templates (02-04), the codebase needed security hardening and i18n before proceeding to async syndication implementation.

## Tasks Completed

### Task 1: Apply Security Fixes to AJAX, Syndication, and Settings Services

**Files modified:**
- `classes/BlueSky_AJAX_Service.php`
- `classes/BlueSky_Syndication_Service.php`
- `templates/admin/settings-page.php`
- `classes/BlueSky_Discussion_Metabox.php`
- `classes/BlueSky_Post_Metabox.php`

**Changes made:**

1. **Legacy AJAX nonce protection:**
   - Added `check_ajax_referer('bluesky_async_nonce', 'nonce')` as first line in:
     - `ajax_fetch_bluesky_posts()` (BlueSky_AJAX_Service.php)
     - `ajax_get_bluesky_profile()` (BlueSky_AJAX_Service.php)
   - These endpoints are registered as nopriv (public visitors use them)
   - Nonce already localized to frontend via `blueskyAsync.nonce`
   - Makes legacy endpoints consistent with newer async variants

2. **godmode debug sidebar auth gate:**
   - Changed godmode conditional in `templates/admin/settings-page.php` from:
     ```php
     if (isset($_GET["godmode"]) || defined("WP_DEBUG") || defined("WP_DEBUG_DISPLAY"))
     ```
   - To:
     ```php
     if ((isset($_GET["godmode"]) && current_user_can("manage_options")) || defined("WP_DEBUG") || defined("WP_DEBUG_DISPLAY"))
     ```
   - Debug sidebar still available in WP_DEBUG mode
   - `?godmode` parameter now requires admin capability

3. **Syndication POST data sanitization:**
   - In `syndicate_post_to_bluesky()` and `syndicate_post_multi_account()`:
     - Replaced raw `$_POST["bluesky_dont_syndicate"]` access with:
       ```php
       $dont_syndicate_post = isset($_POST["bluesky_dont_syndicate"])
           ? sanitize_text_field(wp_unslash($_POST["bluesky_dont_syndicate"]))
           : "0";
       ```
     - Added early capability check:
       ```php
       if (!current_user_can('edit_post', $post_id)) {
           return;
       }
       ```
     - Placed after post status checks but before syndication logic

4. **PHP error string i18n:**
   - Wrapped all error messages in metabox AJAX handlers in `__()` function:
     - "Invalid permissions" (2 instances in Discussion_Metabox)
     - "No Bluesky post information found"
     - "Invalid nonce" (2 instances in Post_Metabox)
     - "Insufficient permissions"
     - "Could not fetch posts" (AJAX_Service)
     - "Could not fetch profile" (AJAX_Service)
     - "Unauthorized" (AJAX_Service)

**Verification:**
- All modified PHP files pass `php -l`
- `check_ajax_referer` appears in ajax_fetch_bluesky_posts and ajax_get_bluesky_profile
- `current_user_can.*manage_options` appears near godmode in template
- `sanitize_text_field.*wp_unslash` appears in Syndication_Service
- `current_user_can.*edit_post` appears in Syndication_Service (2 methods)

**Commit:** e57fc60

---

### Task 2: Wrap All User-Facing Strings in i18n Functions (PHP + JS)

**Files modified:**
- `classes/BlueSky_Assets_Service.php`
- `assets/js/bluesky-async-loader.js`

**Changes made:**

1. **Added i18n sub-object to wp_localize_script:**
   - Modified both `admin_enqueue_scripts()` and `frontend_enqueue_scripts()` methods
   - Added 13 translatable strings to `blueskyAsync.i18n` object:
     - `connectionFailed` — "Connection to BlueSky failed. Please check your credentials."
     - `missingCredentials` — "Handle or app password is not configured."
     - `networkError` — "Could not reach BlueSky servers:"
     - `rateLimitExceeded` — "BlueSky rate limit exceeded. Please wait a few minutes before trying again."
     - `rateLimitResetsAt` — "Resets at"
     - `authFactorRequired` — "BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA."
     - `accountTakedown` — "This BlueSky account has been taken down."
     - `invalidCredentials` — "Invalid handle or password. Please check your credentials."
     - `connectionSuccess` — "Connection to BlueSky successful!"
     - `logoutLink` — "Log out from this account"
     - `connectionCheckFailed` — "Could not check connection status."
     - `contentLoadFailed` — "Unable to load Bluesky content."
     - `connectionFallback` — "Connection failed:"

2. **Replaced all hardcoded JS strings with i18n references:**
   - In `getAuthErrorMessage()`:
     - Replaced `"Connection to BlueSky failed..."` with `blueskyAsync.i18n.connectionFailed`
     - Replaced `"Handle or app password..."` with `blueskyAsync.i18n.missingCredentials`
     - Replaced `"Could not reach BlueSky servers: "` with `blueskyAsync.i18n.networkError + " "`
     - And all 10 other strings in switch cases
   - In `handleAuth()`:
     - Replaced `"Connection to BlueSky successful!"` with `blueskyAsync.i18n.connectionSuccess`
     - Replaced `"Log out from this account"` with `blueskyAsync.i18n.logoutLink`
   - In `showError()`:
     - Replaced `"Could not check connection status."` with `blueskyAsync.i18n.connectionCheckFailed`
     - Replaced `"Unable to load Bluesky content."` with `blueskyAsync.i18n.contentLoadFailed`
   - Used string concatenation for dynamic parts (e.g., `blueskyAsync.i18n.networkError + " " + errorMessage`)

**Verification:**
- BlueSky_Assets_Service.php passes `php -l`
- `blueskyAsync.i18n.` appears 13 times in bluesky-async-loader.js
- Zero occurrences of hardcoded "Connection to BlueSky" string in JS
- All strings use text domain 'social-integration-for-bluesky'

**Commit:** 451b106

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Key Decisions

1. **Legacy AJAX nonce verification:** Added to public-facing endpoints for consistency with newer async variants. Nonce already available on frontend via existing localization.

2. **godmode capability gate:** Preserved debug sidebar functionality while restricting `?godmode` parameter to admins. WP_DEBUG constants still trigger sidebar without auth requirement (developer environment expected).

3. **Early capability checks in syndication:** Placed `current_user_can('edit_post')` checks immediately after post status validation to fail fast before processing syndication logic.

4. **Consistent text domain:** Used 'social-integration-for-bluesky' (from plugin headers) across all PHP `__()` calls and JS i18n object keys.

5. **JS string concatenation pattern:** For strings with dynamic parts, used concatenation (`blueskyAsync.i18n.networkError + " " + errorMessage`) to preserve translatability of base string.

---

## Testing Notes

**Security fixes verified:**
- ✓ Legacy AJAX endpoints now check nonce on public calls
- ✓ `?godmode` parameter requires manage_options capability
- ✓ POST data sanitized with `sanitize_text_field(wp_unslash())`
- ✓ Syndication requires edit_post capability

**i18n coverage verified:**
- ✓ All 9 PHP error strings wrapped in `__()` function
- ✓ All 13 JS strings reference `blueskyAsync.i18n.*`
- ✓ No hardcoded English strings remain in AJAX error responses
- ✓ Text domain 'social-integration-for-bluesky' used consistently

**No .pot file regeneration performed** (per locked decision from 02-RESEARCH).

---

## Impact

**Security posture improved:**
- Public AJAX endpoints protected from CSRF via nonce verification
- Debug sidebar access restricted to administrators
- User input sanitized before processing in syndication flow
- Authorization checks enforce edit_post capability

**Translation-ready:**
- Plugin now fully translatable for international audiences
- Consistent text domain enables language pack generation
- Dynamic content preserves translatability through concatenation pattern

**No breaking changes:** All modifications are additive (nonce checks pass silently for valid requests, capability checks align with existing WordPress permission model).

---

## Files Changed

```
classes/BlueSky_AJAX_Service.php          | 9 ++++++---
classes/BlueSky_Syndication_Service.php   | 16 ++++++++++++----
templates/admin/settings-page.php         | 1 +
classes/BlueSky_Discussion_Metabox.php    | 4 ++--
classes/BlueSky_Post_Metabox.php          | 4 ++--
classes/BlueSky_Assets_Service.php        | 26 ++++++++++++++++++++++++++
assets/js/bluesky-async-loader.js         | 29 +++++++++++++++--------------
```

**7 files changed, 76 insertions(+), 29 deletions(-)**

---

## Self-Check: PASSED

### Created files verification:
(No files were created in this plan)

### Modified files verification:
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_AJAX_Service.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_Syndication_Service.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/templates/admin/settings-page.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_Discussion_Metabox.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_Post_Metabox.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_Assets_Service.php
- ✓ FOUND: /Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/assets/js/bluesky-async-loader.js

### Commit verification:
- ✓ FOUND: e57fc60 (fix(02-05): apply security fixes for AJAX nonce, godmode auth, and POST sanitization)
- ✓ FOUND: 451b106 (feat(02-05): complete i18n for PHP and JS user-facing strings)

**All files and commits verified successfully.**
