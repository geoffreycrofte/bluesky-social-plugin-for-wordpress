---
phase: 06-advanced-syndication
plan: 02
subsystem: syndication-control
tags: [category-rules, global-pause, settings-ui, routing]
dependency_graph:
  requires: [06-01]
  provides: [category-routing, syndication-pause]
  affects: [account-management, settings-ui]
tech_stack:
  added: []
  patterns: [category-filtering, OR-logic, exclude-priority]
key_files:
  created:
    - templates/admin/settings-category-rules.php
  modified:
    - classes/BlueSky_Account_Manager.php
    - classes/BlueSky_Settings_Service.php
    - templates/admin/settings-page.php
decisions:
  - slug: category-or-logic
    summary: Include rules use OR logic (any match syndicates)
    rationale: Allows flexible routing - posts in multiple categories syndicate if ANY are included
  - slug: exclude-priority
    summary: Exclude rules checked first with higher priority than include
    rationale: Security/control pattern - exclusions override inclusions for safety
  - slug: default-syndicate-all
    summary: Empty rules default to syndicating all categories
    rationale: Opt-out model - users don't need to configure rules for basic usage
  - slug: global-pause-warning-style
    summary: Global pause toggle shows warning color when enabled
    rationale: Visual prominence prevents accidental syndication blocking
metrics:
  duration_seconds: 200
  duration_formatted: "3.3 minutes"
  tasks_completed: 2
  files_created: 1
  files_modified: 3
  commits: 2
  completed_at: "2026-02-22T10:23:11Z"
---

# Phase 6 Plan 2: Category Rules and Global Pause Summary

**One-liner:** Per-account category routing with include/exclude rules and global syndication pause toggle

## Overview

Extended account data structure to support category-based syndication rules, allowing users to control which post categories syndicate to which accounts. Added global pause toggle for maintenance scenarios. Implemented OR logic for includes and priority for excludes with sensible defaults.

## Tasks Completed

### Task 1: Extend Account Manager with category_rules and add global pause setting
- **Commit:** `1a168d2`
- **Duration:** ~2 minutes
- **Changes:**
  - Added `category_rules` field to account data structure with `['include' => [], 'exclude' => []]` default
  - Updated `add_account()` to initialize category_rules for new accounts
  - Extended `update_account()` to handle category_rules updates with sanitization
  - Implemented `should_syndicate_to_account($post_id, $account_id)` method:
    - Returns true if no rules set (default behavior)
    - Checks exclude rules first - any excluded category blocks syndication
    - Applies OR logic for include rules - at least one match required
    - Handles edge case: posts with no categories + include rules = don't syndicate
  - Added `global_pause` to `sanitize_settings()` method in Settings Service
  - Created `render_global_pause_field()` with warning styling when enabled
  - Implemented `handle_category_rules_save()` to process form submissions with nonce verification

**Files modified:**
- `classes/BlueSky_Account_Manager.php` (+60 lines)
- `classes/BlueSky_Settings_Service.php` (+109 lines)

### Task 2: Create Category Rules tab UI and global pause toggle in settings page
- **Commit:** `b761561`
- **Duration:** ~1.3 minutes
- **Changes:**
  - Added Syndication tab to settings navigation (between Discussions and Shortcodes)
  - Created `templates/admin/settings-category-rules.php`:
    - Form with proper nonce field for security
    - Per-account cards showing name and handle
    - Two-column layout: Include categories | Exclude categories
    - Scrollable checkbox lists for each category section
    - Category post counts displayed for context
    - Empty state messages for no accounts or no categories
    - Save button submits category rules independently
  - Integrated global pause toggle into Syndication tab with form table layout
  - Added tab content section with global pause at top, category rules below
  - Included template via `plugin_dir_path(BLUESKY_PLUGIN_FILE)` pattern

**Files created:**
- `templates/admin/settings-category-rules.php` (111 lines)

**Files modified:**
- `templates/admin/settings-page.php` (+38 lines)

## Implementation Highlights

### Category Filtering Logic
```php
// Exclude rules checked first (higher priority)
if (!empty($exclude_rules)) {
    foreach ($post_category_ids as $cat_id) {
        if (in_array($cat_id, $exclude_rules)) {
            return false; // Excluded category found
        }
    }
}

// Include rules use OR logic (any match syndicates)
if (!empty($include_rules)) {
    if (empty($post_category_ids)) {
        return false; // No categories on post
    }
    foreach ($post_category_ids as $cat_id) {
        if (in_array($cat_id, $include_rules)) {
            return true; // Found included category
        }
    }
    return false; // No included categories found
}
```

### UI Pattern
- Two-column grid layout for include/exclude sections
- Scrollable checkbox containers (max-height: 200px)
- Category post counts for user context
- Per-account cards with visual separation
- Help text explaining logic at account level
- Global pause toggle with conditional warning styling

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

1. ✓ `category_rules` field exists in account data structure
2. ✓ `should_syndicate_to_account()` method implemented in Account Manager
3. ✓ `global_pause` setting registered in Settings Service
4. ✓ `handle_category_rules_save()` exists and is called from `render_settings_page()`
5. ✓ `templates/admin/settings-category-rules.php` exists with form, checkboxes, and nonce
6. ✓ Syndication tab exists in settings navigation
7. ✓ Global pause toggle rendered in Syndication tab

## Success Criteria Met

- [x] Category Rules tab shows in settings with all accounts and their category mappings
- [x] Include/exclude checkboxes save per-account category rules
- [x] `should_syndicate_to_account()` method applies OR logic for includes, priority for excludes
- [x] Global pause toggle is available and saves to plugin options
- [x] Default behavior (no rules) syndicates all categories

## Integration Points

**Requires:**
- Account Manager (`get_accounts()`, `get_account()`, `update_account()`)
- WordPress categories API (`get_categories()`, `get_the_category()`)
- Settings Service form handling

**Provides:**
- `BlueSky_Account_Manager::should_syndicate_to_account()` - category filtering method
- `BlueSky_Settings_Service::render_global_pause_field()` - global pause toggle UI
- Category rules UI in settings for per-account configuration

**Affects:**
- Future syndication logic will need to check `should_syndicate_to_account()` before syndicating
- Future syndication logic will need to check `global_pause` option before any syndication
- Account data structure now includes `category_rules` field

## Next Steps

1. **Phase 6 Plan 3 (Async Syndication Integration):**
   - Update `Syndication_Service` or `Async_Handler` to check `global_pause` before scheduling
   - Call `should_syndicate_to_account()` during multi-account syndication to filter accounts
   - Add test coverage for category filtering logic

2. **Future Enhancements:**
   - Category rules bulk edit (apply same rules to multiple accounts)
   - Category rules import/export
   - Preview which posts would syndicate with current rules
   - Category rule templates (e.g., "News only", "Blog posts only")

## Self-Check

Verifying plan claims against actual implementation:

**Created files check:**
```bash
[ -f "templates/admin/settings-category-rules.php" ] && echo "FOUND" || echo "MISSING"
```
Result: FOUND ✓

**Modified files check:**
```bash
git log --oneline --all | grep -E "(1a168d2|b761561)"
```
Result:
- `b761561` feat(06-02): create Syndication tab with category rules UI and global pause toggle ✓
- `1a168d2` feat(06-02): add category rules and global pause to account/settings ✓

**Commits exist:**
- Commit 1a168d2: Task 1 changes to Account Manager and Settings Service ✓
- Commit b761561: Task 2 changes to settings UI and template creation ✓

## Self-Check: PASSED

All files created, all commits exist, all verification checks passed. Plan executed successfully with no deviations.

---

**Plan completed:** 2026-02-22T10:23:11Z
**Total duration:** 3.3 minutes (200 seconds)
**Commits:** 2 (1a168d2, b761561)
