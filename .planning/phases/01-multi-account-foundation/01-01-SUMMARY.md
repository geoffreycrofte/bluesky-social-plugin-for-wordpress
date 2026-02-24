---
phase: 01-multi-account-foundation
plan: 01
subsystem: multi-account-data-layer
tags: [account-management, migration, data-model, foundation]

dependency_graph:
  requires: []
  provides:
    - BlueSky_Account_Manager class with CRUD operations
    - Version-gated migration from single to multi-account
    - Secure UUID generation helper
    - Feature toggle (enable_multi_account)
    - Post meta backfill for account associations
  affects:
    - bluesky_settings option (read for migration)
    - bluesky_accounts option (new multi-account storage)
    - bluesky_active_account option (tracks active account)
    - bluesky_global_settings option (account-agnostic settings)
    - bluesky_schema_version option (migration version tracking)

tech_stack:
  added:
    - classes/BlueSky_Account_Manager.php
  patterns:
    - Version-gated migration with emergency bypass constant
    - Idempotent migration checks
    - UUID v4 generation using random_bytes(16)
    - Account-keyed data structures

key_files:
  created:
    - classes/BlueSky_Account_Manager.php (453 lines, 13 methods)
  modified:
    - classes/BlueSky_Helpers.php (added bluesky_generate_secure_uuid static method)
    - social-integration-for-bluesky.php (wired Account Manager into plugin init)

decisions:
  - Feature toggle defaults to false (opt-in for users, protects existing single-account users)
  - Migration preserves encrypted app_password as-is (no re-encryption during migration)
  - Migration creates backup of old settings (bluesky_settings_backup)
  - Account Manager instantiates before API Handler (ensures migration runs early)
  - UUID generation is static method (can be called without instantiation)
  - Discussion account preference stored in global settings (not per-account)

metrics:
  duration_minutes: 2
  tasks_completed: 2
  commits: 2
  files_created: 1
  files_modified: 2
  completed_date: 2026-02-16
---

# Phase 01 Plan 01: Account Manager Foundation Summary

Multi-account data layer with CRUD operations, version-gated migration, and feature toggle infrastructure.

## What Was Built

Created the `BlueSky_Account_Manager` class that serves as the central data management layer for multi-account functionality. This includes:

1. **Account CRUD Operations**
   - `get_accounts()` - Returns all accounts (or single-account fallback when feature disabled)
   - `get_account($id)` - Fetches single account by UUID
   - `get_active_account()` - Returns the currently active account
   - `add_account($data)` - Creates new account with encrypted credentials
   - `remove_account($id)` - Deletes account and returns orphaned post count
   - `set_active_account($id)` - Switches active account
   - `update_account($id, $data)` - Partial update of account fields
   - `get_discussion_account()` - Returns account for discussion display
   - `set_discussion_account($id)` - Sets discussion display preference

2. **Version-Gated Migration**
   - `maybe_run_migration()` - Registered on `plugins_loaded` hook
   - `migrate_to_v2()` - Transforms single-account `bluesky_settings` to multi-account `bluesky_accounts`
   - `backfill_post_account_associations()` - Associates existing syndicated posts with migrated account
   - Idempotent checks prevent duplicate migrations
   - Emergency bypass via `BLUESKY_SKIP_MIGRATION` constant

3. **Feature Toggle**
   - `is_multi_account_enabled()` - Master switch that gates migration and multi-account behavior
   - Defaults to false (opt-in, protects existing users)
   - When disabled, `get_accounts()` returns single-account compatibility view

4. **Supporting Infrastructure**
   - Added `BlueSky_Helpers::bluesky_generate_secure_uuid()` static method
   - Uses `random_bytes(16)` for cryptographically secure UUIDs
   - Follows UUID v4 specification (version nibble 0100, variant bits 10)

## Integration Points

- **Plugin Initialization**: Account Manager loads after Helpers, before API Handler
- **Migration Hook**: Runs on `plugins_loaded` when feature enabled and schema version < 2
- **Data Model**:
  - `bluesky_accounts` - Array of account objects keyed by UUID
  - `bluesky_active_account` - UUID of currently active account
  - `bluesky_global_settings` - Account-agnostic settings (syndication options, styling, etc.)
  - `bluesky_schema_version` - Migration version tracker
  - `bluesky_settings_backup` - Backup of pre-migration settings

## Data Structure

Each account in `bluesky_accounts` contains:
```php
[
    'id' => 'uuid-v4',
    'name' => 'Account Name',
    'handle' => 'user.bsky.social',
    'app_password' => 'encrypted_string',
    'did' => 'did:plc:...',
    'is_active' => bool,
    'auto_syndicate' => bool,
    'owner_id' => int,
    'created_at' => timestamp
]
```

## Migration Behavior

When multi-account is enabled and schema version is 1:

1. Check if `bluesky_accounts` already exists (idempotency)
2. Read `bluesky_settings` for handle, app_password, did
3. Generate UUID for primary account
4. Copy encrypted credentials as-is (no re-encryption)
5. Create account entry with `is_active=true`, `auto_syndicate=true`
6. Save to `bluesky_accounts` and set as active via `bluesky_active_account`
7. Extract global settings (everything except handle/app_password/did) to `bluesky_global_settings`
8. Backup old settings to `bluesky_settings_backup` with timestamp
9. Backfill post meta: add `_bluesky_account_id` to all syndicated posts
10. Transform `_bluesky_syndication_bs_post_info` to account-keyed structure
11. Update `bluesky_schema_version` to 2

## Post Meta Backfill

For all posts with `_bluesky_syndicated = '1'`:
- Adds `_bluesky_account_id` meta with primary account UUID (if not already set)
- Transforms `_bluesky_syndication_bs_post_info` from plain JSON to `{account_uuid: data}` format
- Idempotent: skips posts that already have account associations

## Success Criteria Met

- [x] BlueSky_Account_Manager class provides complete CRUD for multi-account data
- [x] Migration transforms single-account bluesky_settings to multi-account bluesky_accounts
- [x] Feature toggle gates migration (only runs when enabled per user decision)
- [x] Post meta backfill associates existing syndicated posts with migrated account
- [x] All PHP files pass syntax check
- [x] Plugin loads without errors

## Deviations from Plan

None - plan executed exactly as written.

## Commits

1. **feat(01-01): create BlueSky_Account_Manager with CRUD, migration, and feature toggle** (3eb246f)
   - Created BlueSky_Account_Manager class (453 lines)
   - Added 13 methods for CRUD, migration, and feature toggle
   - Added secure UUID generation to BlueSky_Helpers
   - Implemented post meta backfill logic

2. **feat(01-01): wire Account Manager into plugin initialization** (e543ecd)
   - Added require_once for BlueSky_Account_Manager after BlueSky_Helpers
   - Instantiated Account Manager before API Handler
   - Migration hook auto-registers on plugins_loaded

## Self-Check: PASSED

Verified files exist:
```bash
FOUND: classes/BlueSky_Account_Manager.php
```

Verified commits exist:
```bash
FOUND: 3eb246f
FOUND: e543ecd
```

All files pass PHP syntax check. All required methods present. UUID generation uses secure random_bytes(16). Migration implements idempotency checks.

## Next Steps

This plan provides the foundation for all subsequent Phase 1 work:
- **Plan 02**: UI for account management (relies on CRUD methods)
- **Plan 03**: Multi-account syndication (uses get_active_account)
- **Plan 04**: Post edit account switching (uses set_active_account)
- **Plan 05**: Discussion display account selection (uses get/set_discussion_account)

The data layer is now ready for UI and syndication integration.
