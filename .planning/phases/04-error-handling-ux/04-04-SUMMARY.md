---
phase: 04-error-handling-ux
plan: 04
subsystem: error-handling
tags: [site-health, diagnostics, monitoring, wordpress-core]
dependency_graph:
  requires: [BlueSky_Account_Manager, BlueSky_Circuit_Breaker, BlueSky_Rate_Limiter]
  provides: [WordPress Site Health integration, diagnostic tests, debug information]
  affects: [Tools > Site Health UI]
tech_stack:
  added: [WordPress Site Health API]
  patterns: [filter-based integration, direct tests, debug info registration]
key_files:
  created:
    - classes/BlueSky_Health_Monitor.php
  modified:
    - social-integration-for-bluesky.php
    - classes/BlueSky_Plugin_Setup.php
decisions: []
metrics:
  duration_seconds: 119
  tasks_completed: 2
  files_created: 1
  files_modified: 2
  commits: 2
  completed_date: 2026-02-19
---

# Phase 04 Plan 04: WordPress Site Health Integration Summary

**One-liner:** WordPress Site Health integration with 3 pass/fail status checks (accounts configured, credentials valid, circuit breaker) and debug info section showing plugin version, API details, and per-account resilience state.

## What Was Built

Created `BlueSky_Health_Monitor` class that integrates with WordPress core Site Health feature (Tools > Site Health). Provides both diagnostic status checks and detailed debug information for troubleshooting plugin issues.

### Components Created

**1. BlueSky_Health_Monitor Class** (321 lines)
- Registers `site_status_tests` filter for pass/fail health checks
- Registers `debug_information` filter for diagnostics section
- Three direct (synchronous) health tests:
  - **Accounts Configured**: Checks if accounts exist, prompts to add if none
  - **Credentials Valid**: Scans auth error registry, flags accounts needing re-authentication
  - **Circuit Breaker**: Detects accounts in cooldown from repeated failures
- Debug info section with:
  - Plugin version, account count, API endpoint
  - Cache duration (formatted as minutes)
  - Action Scheduler availability
  - Per-account resilience state (circuit status, rate limiting) — marked private

### Integration Points

- **Plugin Bootstrap**: `social-integration-for-bluesky.php` requires the new class
- **Plugin_Setup**: Instantiates `BlueSky_Health_Monitor` in constructor
- **WordPress Core**: Filters hooked automatically, no manual registration needed
- **UI Location**: Tools > Site Health > Status (for tests), Tools > Site Health > Info (for debug section)

## Technical Details

### Site Health Test Format

All tests return standard WordPress Site Health arrays:
```php
[
    'label'       => string,              // Test result title
    'status'      => 'good'|'recommended'|'critical',
    'badge'       => [
        'label' => 'Performance'|'Security',
        'color' => 'blue'|'red'|'orange'
    ],
    'description' => '<p>...</p>',        // HTML explanation
    'actions'     => '<a>...</a>',        // Optional action link
    'test'        => 'test_id_string'
]
```

### Status Mappings

| Test | Good | Recommended | Critical |
|------|------|-------------|----------|
| **Accounts** | Has accounts | No accounts (setup needed) | N/A |
| **Credentials** | All valid | N/A | Auth errors detected |
| **Circuit Breaker** | All closed | Some open (cooldown) | N/A |

### Debug Info Structure

The `bluesky` section in Site Health > Info shows:
- **Public fields**: Version, counts, endpoint, cache, scheduler status
- **Private fields**: Per-account circuit breaker and rate limiter state (only visible to admins with debug mode)

### Internationalization

All user-facing strings use text domain `social-integration-for-bluesky` with proper pluralization via `_n()` for account counts.

## Task Execution

| Task | Name | Commit | Duration | Files |
|------|------|--------|----------|-------|
| 1 | Create BlueSky_Health_Monitor with Site Health tests | 2ca2b77 | ~60s | classes/BlueSky_Health_Monitor.php |
| 2 | Register Health Monitor in plugin bootstrap | bd4ff6d | ~59s | social-integration-for-bluesky.php, classes/BlueSky_Plugin_Setup.php |

**Total Duration:** 119 seconds (~2 minutes)

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

✅ All verification criteria met:

1. **site_status_tests filter**: Adds 3 Bluesky checks (accounts, credentials, circuit breaker)
2. **debug_information filter**: Adds 'bluesky' section with all specified fields
3. **Health test format**: All tests return correct WordPress Site Health array structure
4. **Per-account debug fields**: Marked as `'private' => true`
5. **Class instantiation**: Registered in `BlueSky_Plugin_Setup` constructor
6. **PHP syntax**: No errors in all modified files
7. **Required patterns**: 13 method/filter references found (expected 7+)

## Integration Architecture

```
WordPress Core Site Health
    ├─ site_status_tests filter → BlueSky_Health_Monitor::register_health_tests()
    │   ├─ test_accounts_configured()
    │   ├─ test_credentials_valid()
    │   └─ test_circuit_breaker()
    │
    └─ debug_information filter → BlueSky_Health_Monitor::register_debug_info()
        └─ 'bluesky' section with plugin metrics + per-account state

BlueSky_Health_Monitor
    ├─ Reads from: BlueSky_Account_Manager (get_all_accounts)
    ├─ Reads from: bluesky_account_auth_errors option (auth registry)
    ├─ Creates: BlueSky_Circuit_Breaker instances per account
    ├─ Creates: BlueSky_Rate_Limiter instances per account
    └─ Registered by: BlueSky_Plugin_Setup (constructor)
```

## User Experience

### For Site Administrators

**Tools > Site Health > Status** shows:
- ✅ Green checkmark: "Bluesky accounts configured" (when accounts exist)
- ✅ Green checkmark: "All Bluesky credentials valid" (when no auth errors)
- ✅ Green checkmark: "Bluesky API connections healthy" (when no open breakers)
- ⚠️ Orange recommendation: "Bluesky API requests paused" (during cooldown)
- ❌ Red critical: "Bluesky accounts need re-authentication" (on auth failure)

**Tools > Site Health > Info > Bluesky Integration** shows:
- Plugin version, account count, API endpoint, cache duration
- Action Scheduler availability
- Per-account circuit/rate limit state (admin-only)

### For Troubleshooting

When users experience issues:
1. Visit **Tools > Site Health**
2. Check **Status** tab for immediate problems (auth, cooldowns)
3. Check **Info** tab for detailed diagnostics
4. Action links point directly to plugin settings for fixes

## Success Criteria Met

✅ Site Health shows 3 pass/fail tests (accounts, credentials, circuit breaker)
✅ Site Health debug info section shows plugin version, account count, API endpoint, cache duration, Action Scheduler status, per-account resilience state
✅ All test results use correct status values (good/recommended/critical)
✅ No PHP errors when no accounts are configured
✅ Health tests use correct WordPress Site Health API return format
✅ Per-account debug fields marked as private
✅ Class instantiated in Plugin_Setup

## Self-Check: PASSED

**Created files exist:**
```
FOUND: classes/BlueSky_Health_Monitor.php
```

**Commits exist:**
```
FOUND: 2ca2b77
FOUND: bd4ff6d
```

**Modified files verified:**
```
FOUND: social-integration-for-bluesky.php (require_once added)
FOUND: classes/BlueSky_Plugin_Setup.php (property + instantiation added)
```

All files, commits, and integrations verified successfully.

---

**Plan Status:** ✅ Complete
**Next Plan:** 04-05 (if exists) or Phase 05
**Dependencies Resolved:** All requirements from Phase 03 (resilience components) available
**Ready for:** Phase completion and progression to Display customization (Phase 05)
