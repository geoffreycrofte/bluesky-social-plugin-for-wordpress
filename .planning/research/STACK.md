# Technology Stack

**Domain:** WordPress Plugin — Multi-Account Bluesky Integration
**Researched:** 2026-02-14
**Confidence:** MEDIUM

## Current Stack (Preserve)

The existing stack is appropriate for the plugin's scope. No framework migration needed.

### Core Framework
| Technology | Version | Purpose | Why Keep |
|------------|---------|---------|----------|
| PHP | 7.4+ | Plugin backend | WordPress minimum, broad hosting compatibility |
| WordPress | 5.0+ | Plugin framework | Required platform, Gutenberg blocks need 5.0+ |
| JavaScript (ES5+) | - | Gutenberg blocks, admin UI | No build tools = no transpilation, ES5 ensures compatibility |
| CSS 3 | - | Styling | Direct asset serving, no preprocessor needed |

### Infrastructure
| Technology | Version | Purpose | Why Keep |
|------------|---------|---------|----------|
| WordPress Options API | Core | Settings storage | Standard for plugin config, multi-account data fits |
| WordPress Transients API | Core | Caching layer | Account-scoped caching, configurable TTL |
| WordPress Meta API | Core | Post syndication tracking | Per-post syndication status, account associations |
| OpenSSL Extension | PHP built-in | Credential encryption | AES-256-CBC for app passwords at rest |

### External API
| Technology | Endpoint | Purpose | Notes |
|------------|----------|---------|-------|
| AT Protocol xRPC | bsky.social/xrpc/ | All Bluesky operations | JWT auth, undocumented rate limits |

## Recommended Additions

### For Async Syndication
| Technology | Purpose | Why | Confidence |
|------------|---------|-----|------------|
| Action Scheduler (by Automattic) | Reliable async job processing | Better than WP-Cron: guaranteed execution, locking, retry, logging. Used by WooCommerce. | MEDIUM |

**Alternative considered:** WP-Cron alone — unreliable on low-traffic sites, no locking, no retry.

**Installation:** Include as library (no Composer needed — copy files to plugin) or require as dependency.

### For Testing
| Technology | Purpose | Why | Confidence |
|------------|---------|-----|------------|
| PHPUnit | Unit + integration tests | WordPress standard, WP test framework built on it | HIGH |
| WP_UnitTestCase | WordPress integration testing | Provides test database, factory methods, hook testing | HIGH |
| wp-env or Local WP | Test environment | Reproducible test environment for CI | MEDIUM |

### For Code Quality
| Technology | Purpose | Why | Confidence |
|------------|---------|-----|------------|
| PHPCS + WordPress Coding Standards | Code style enforcement | WordPress.org review compliance, consistent code | HIGH |
| PHPStan (level 5) | Static analysis | Catch type errors, undefined methods before runtime | MEDIUM |

## What NOT to Add

| Technology | Why Requested | Why NOT |
|------------|--------------|---------|
| Composer | Dependency management | Plugin has zero PHP dependencies. Adding Composer adds complexity for contributors without benefit. Ship Action Scheduler as bundled library. |
| npm/webpack/build tools | JS bundling, SCSS compilation | Current JS is simple Gutenberg blocks. No TypeScript, no React beyond what Gutenberg provides. Build tools add contributor friction for minimal gain. |
| REST API custom endpoints | Alternative to AJAX | Current AJAX endpoints work. REST API adds surface area without clear user benefit. Consider for v3 if headless WordPress support needed. |
| React/Vue for admin UI | Modern frontend | WordPress admin UI conventions use jQuery + vanilla JS. Custom framework would feel foreign in wp-admin context. Gutenberg provides React where needed. |
| Redis/Memcached | Object caching | Transients work for current scale. Object cache is hosting-dependent. Plugin shouldn't require specific caching backend. |
| Custom database tables | Account/settings storage | Options API handles multi-account data well up to ~20 accounts. Custom tables add migration complexity. Revisit only if performance proves insufficient. |

## Build & Development Setup

**Current:** No build step. Assets served directly from `/assets/`. This is a strength — zero setup friction.

**Recommended additions only:**
```bash
# Testing (one-time setup)
composer require --dev phpunit/phpunit
composer require --dev wp-phpunit/wp-phpunit

# Code quality (one-time setup)
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs
```

**Note:** Composer for dev dependencies only (testing/linting). Not shipped with plugin. No production Composer dependency.

## WordPress.org Compliance

| Requirement | Current Status | Action Needed |
|-------------|---------------|---------------|
| No minified-only JS | ✓ Source files present | None |
| Proper escaping | Partial — `wp_kses` used | Audit all output for `esc_html`, `esc_attr`, `esc_url` |
| Nonce validation | Partial — missing on some AJAX | Add to all public AJAX endpoints |
| Capability checks | ✓ `manage_options` used | Extend for author-level account management |
| Sanitize inputs | ✓ `sanitize_settings` callback | Extend for multi-account settings |
| No direct file operations | ✓ | None |

## Migration Strategy

### From Single-Account to Multi-Account
```
Phase 1: Add Settings_Manager wrapper (no data structure change)
Phase 2: Add Account entity + repository (new data structure alongside old)
Phase 3: Migration routine: old → new, preserve old as backup
Phase 4: Remove old structure after verification period
```

### Database Schema Evolution
```
v1 (current):  bluesky_settings → {handle, app_password, ...}
v2 (target):   bluesky_accounts → [{id, handle, app_password, ...}]
               bluesky_global_settings → {cache_duration, ...}
               bluesky_active_account → 'uuid'
               bluesky_schema_version → 2
```

## Sources

- WordPress Plugin Handbook (developer.wordpress.org)
- Current codebase analysis
- Action Scheduler documentation (actionscheduler.org)
- WordPress Coding Standards (github.com/WordPress/WordPress-Coding-Standards)

---
*Stack research: 2026-02-14*
