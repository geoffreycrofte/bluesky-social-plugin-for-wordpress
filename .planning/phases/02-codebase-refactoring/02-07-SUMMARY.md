---
phase: 02-codebase-refactoring
plan: 07
subsystem: verification
tags: [e2e-verification, human-testing, bug-fixes]
dependency-graph:
  requires: ["02-02", "02-03", "02-04", "02-05", "02-06"]
  provides: ["verified-phase-02", "bug-fixes"]
  affects: ["blocks", "ajax", "api-handler", "settings", "account-manager"]
tech-stack:
  runtime: [php-8.3, wordpress-6.x]
  testing: [manual-e2e]
---

## What Was Done

End-to-end human verification of the Phase 2 refactoring. All automated checks passed (27 PHPUnit tests, PHP syntax, security audit). Human testing uncovered 6 bugs that were fixed iteratively.

## Bugs Found and Fixed

1. **Block account selector ignored** — `BlueSky_Blocks_Service` render callbacks used the default API handler regardless of account selection. Added `resolve_api_handler()` to create per-account handlers.

2. **API Handler cache key collision** — `fetch_bluesky_posts()` and `get_bluesky_profile()` omitted `$this->account_id` from cache keys, causing all accounts to return the default account's cached data. Fixed by adding account_id as first parameter to transient key generation.

3. **Active Account inconsistency** — "Active Account (default)" in block selectors fell through to raw `bluesky_settings` credentials instead of the account marked `is_active`. Added active account fallback in both `BlueSky_Blocks_Service` and `BlueSky_AJAX_Service`.

4. **Discussion Thread Source select reload** — Select dropdown triggered a full form submission on change. Replaced with AJAX save endpoint (`bluesky_set_discussion_account`). Also added processing in `sanitize_settings()` so "Save Changes" persists the value.

5. **Discussion account display bug** — `get_discussion_account()` returns a string (UUID), but `render_discussion_account_field()` tried to access `['id']` on it. Fixed to use return value directly.

6. **PHP 8 deprecation + WordPress compliance** — Explicit nullable type `?BlueSky_Account_Manager` on constructor. Replaced `BLUESKY_SKIP_MIGRATION` constant with `bluesky_skip_migration` filter hook.

## Key Files

### key-files.created
_None (verification plan)_

### key-files.modified
- `classes/BlueSky_Blocks_Service.php` — resolve_api_handler with active account fallback
- `classes/BlueSky_AJAX_Service.php` — resolve_api_handler with active account fallback
- `classes/BlueSky_API_Handler.php` — account_id in cache keys for posts and profile
- `classes/BlueSky_Settings_Service.php` — discussion account display fix + sanitize_settings save
- `classes/BlueSky_Plugin_Setup.php` — nullable type hint, discussion AJAX hook registration
- `classes/BlueSky_Account_Manager.php` — filter hook instead of constant
- `assets/js/bluesky-social-admin.js` — AJAX save for discussion account select

## Decisions

- Discussion account saves via both AJAX (instant feedback) and sanitize_settings (form save) for reliability
- Block render and AJAX handlers share the same resolve_api_handler pattern: specific account → active account → default fallback
- Migration bypass uses WordPress filter pattern (`add_filter('bluesky_skip_migration', '__return_true')`) instead of PHP constant

## Self-Check: PASSED

- [x] Plugin activates without errors
- [x] Settings page loads, all tabs render
- [x] Block account selector switches between accounts correctly
- [x] "Active Account" option uses the is_active account consistently
- [x] Discussion Thread Source saves and persists across page reloads
- [x] Frontend AJAX content displays correctly per account
- [x] All 27 PHPUnit tests pass
- [x] All PHP files pass syntax check
