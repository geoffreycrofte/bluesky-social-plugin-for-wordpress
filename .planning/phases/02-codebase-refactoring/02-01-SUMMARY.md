---
phase: 02-codebase-refactoring
plan: 01
subsystem: testing

tags: [phpunit, brain-monkey, mocking, php, helpers, refactoring]

# Dependency graph
requires:
  - phase: 01-multi-account-foundation
    provides: BlueSky_Helpers, BlueSky_Account_Manager, BlueSky_API_Handler classes to test
provides:
  - PHPUnit 9 test runner with Brain Monkey WP function mocking
  - BlueSky_Helpers::normalize_handle() public static method
  - Smoke test confirming infrastructure works
affects:
  - 02-02-PLAN (all subsequent plans use this test infrastructure)
  - 02-03-PLAN
  - 02-04-PLAN

# Tech tracking
tech-stack:
  added:
    - "PHPUnit 9.6.34 (phpunit.phar)"
    - "brain/monkey ^2 (via tests/composer.json)"
    - "mockery/mockery 1.6.12 (transitive dependency)"
    - "antecedent/patchwork 2.2.3 (transitive dependency)"
  patterns:
    - "Tests live in tests/unit/, bootstrap at tests/bootstrap.php"
    - "WP constants defined in bootstrap — no wp-load.php required"
    - "Brain Monkey setUp/tearDown in each TestCase"
    - "Run via: php phpunit.phar --configuration phpunit.xml"
    - "Composer installed separately in tests/ subdirectory (not project root)"

key-files:
  created:
    - "phpunit.xml"
    - "tests/bootstrap.php"
    - "tests/composer.json"
    - "tests/composer.lock"
    - "tests/unit/.gitkeep"
    - "tests/unit/SmokeTest.php"
    - ".gitignore"
  modified:
    - "classes/BlueSky_Helpers.php"
    - "classes/BlueSky_Plugin_Setup.php"

key-decisions:
  - "brain/monkey is the correct package name (not brain-wp/brain-monkey as originally specified in plan)"
  - "Local by Flywheel PHP 8.3 used as PHP binary (MAMP PHP curl has SSL chain issues with old OpenSSL 1.0.2u)"
  - "Composer installed in tests/ subdirectory (not project root) to keep test deps separate"
  - "phpunit.phar added to .gitignore (not committed, each dev downloads per plan instructions)"
  - "Plugin_Setup wrapper kept as thin delegation — private method preserved for Plan 02 to remove"

patterns-established:
  - "Static utility methods on BlueSky_Helpers: reusable cross-cutting concerns live as public static"
  - "Test bootstrap defines WP constants + requires autoloader + requires class files only — no WP core"

# Metrics
duration: 11min
completed: 2026-02-17
---

# Phase 02 Plan 01: Test Infrastructure + normalize_handle Summary

**PHPUnit 9 + Brain Monkey test infrastructure operational; normalize_handle extracted to BlueSky_Helpers as public static method replacing the duplicate private implementation in Plugin_Setup**

## Performance

- **Duration:** 11 min
- **Started:** 2026-02-17T20:57:08Z
- **Completed:** 2026-02-17T21:08:00Z
- **Tasks:** 2
- **Files modified:** 9 (7 created, 2 modified)

## Accomplishments
- PHPUnit 9.6.34 runs with Brain Monkey mocking — zero WordPress core required
- Smoke test passes confirming infrastructure is ready for service extraction tests in subsequent plans
- normalize_handle lives in BlueSky_Helpers as a testable public static method
- Plugin_Setup delegates to the static helper (no behavior change, backward compatible)
- .gitignore created to prevent phar and vendor artifacts from being committed

## Task Commits

Each task was committed atomically:

1. **Task 1: Set up PHPUnit + Brain Monkey test infrastructure** - `28c780c` (chore)
2. **Task 2: Move normalize_handle to BlueSky_Helpers as public static** - `0e8ffb0` (refactor)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `phpunit.xml` - PHPUnit config: Unit suite pointing to tests/unit/, bootstrap at tests/bootstrap.php
- `tests/bootstrap.php` - Defines WP constants, loads Brain Monkey autoloader, requires class files
- `tests/composer.json` - Declares brain/monkey ^2 dev dependency
- `tests/composer.lock` - Locked dependency versions
- `tests/unit/.gitkeep` - Placeholder to track empty directory in git
- `tests/unit/SmokeTest.php` - Smoke test: extends TestCase, uses Brain Monkey setUp/tearDown
- `.gitignore` - Ignores phpunit.phar, tests/vendor/, .phpunit.result.cache
- `classes/BlueSky_Helpers.php` - Added `public static normalize_handle($handle)` method
- `classes/BlueSky_Plugin_Setup.php` - Replaced 14-line normalize_bluesky_handle() body with single delegation call

## Decisions Made
- **brain/monkey package name**: The plan specified `brain-wp/brain-monkey` but the correct Packagist name is `brain/monkey`. Auto-corrected.
- **Local by Flywheel PHP**: MAMP PHP 8.2 has old curl (OpenSSL 1.0.2u) with SSL chain errors. Used Local by Flywheel's PHP 8.3.8 which uses macOS SecureTransport.
- **Composer in tests/ subdirectory**: Keeps test dependencies isolated from plugin root, avoiding confusion with plugin's own potential future composer.json.
- **Plugin_Setup wrapper preserved**: The private `normalize_bluesky_handle()` wrapper in Plugin_Setup is intentionally kept thin for now. It will be removed when Plugin_Setup is refactored in Plan 02.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Corrected Brain Monkey package name**
- **Found during:** Task 1 (composer install)
- **Issue:** Plan specified `brain-wp/brain-monkey` which does not exist on Packagist. Correct name is `brain/monkey`.
- **Fix:** Updated tests/composer.json to use `brain/monkey: ^2`
- **Files modified:** tests/composer.json
- **Verification:** `composer install` succeeded, brain/monkey 2.7.0 installed
- **Committed in:** 28c780c (Task 1 commit)

**2. [Rule 3 - Blocking] Used Local by Flywheel PHP instead of MAMP PHP for Composer**
- **Found during:** Task 1 (composer install)
- **Issue:** MAMP PHP 8.2 curl uses OpenSSL 1.0.2u which fails SSL certificate chain validation for packagist.org
- **Fix:** Used Local by Flywheel's PHP 8.3.8 binary (uses macOS SecureTransport) for running Composer
- **Files modified:** None (infrastructure-only change)
- **Verification:** Composer install completed successfully, all 4 packages installed
- **Committed in:** 28c780c (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 bug/wrong package name, 1 blocking/PHP binary)
**Impact on plan:** Both fixes were necessary to unblock the task. No scope creep. All plan objectives achieved.

## Issues Encountered
- MAMP PHP curl SSL issues blocked initial Composer install. Resolved by using Local by Flywheel PHP 8.3 which has SecureTransport SSL support.

## User Setup Required
None — PHPUnit phar is gitignored. To run tests locally:
1. `curl -L https://phar.phpunit.de/phpunit-9.phar -o phpunit.phar && chmod +x phpunit.phar`
2. `cd tests && [path-to-php] [path-to-composer] install`
3. `[path-to-php] phpunit.phar --configuration phpunit.xml`

## Next Phase Readiness
- Test infrastructure ready for Plan 02 (extract BlueSky_API_Handler logic)
- normalize_handle is now testable as a static method — can write unit tests for edge cases
- Brain Monkey is loaded — WP functions can be mocked in any TestCase

---
*Phase: 02-codebase-refactoring*
*Completed: 2026-02-17*
