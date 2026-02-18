# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-14)

**Core value:** WordPress users can seamlessly bridge their WordPress site and Bluesky presence — displaying Bluesky content on their site and syndicating WordPress posts to Bluesky — with zero silent failures and clear recovery paths when things go wrong.
**Current focus:** Phase 3 (Performance & Resilience) — Plan 02 complete

## Current Position

Phase: 3 of 7 (Performance & Resilience) — In progress (Plan 02/N complete)
Next: Phase 3, Plan 03 — Integrate circuit breaker, rate limiter, and request cache into API Handler
Last activity: 2026-02-18 — 03-02 complete (request-level cache implementation)

Progress: [██████████] 100% (Phase 1) | [██████████] 100% (Phase 2) | [█] Phase 3 in progress

## Performance Metrics

**Velocity:**
- Total plans completed: 13
- Average duration: ~5.0 minutes (automated plans)
- Total execution time: ~1.7 hours + 3 iterative testing sessions

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01 | 5 | ~20 min + testing | ~4 min |
| 02 | 6 | ~40 min | ~6.7 min |
| 03 | 2 (so far) | ~6 min | ~3 min |

**Recent Trend:**
- Plans 01-01 through 01-04: Automated execution (2-7 min each)
- Plan 01-05: Human verification with 3 rounds of bug fixes + 6 UX improvements
- Plan 02-01: Automated execution (11 min) — infrastructure + refactor
- Plan 02-02: Automated execution (<1 min) — service layer extraction (work pre-completed)
- Plan 02-03: Automated execution (4 min) — discussion display decomposition
- Plan 02-04: Automated execution (12.5 min) — template extraction
- Plan 02-05: Automated execution (3 min) — security fixes + i18n
- Plan 02-06: Automated execution (8 min) — test coverage for critical paths
- Plan 03-01: Automated execution (4 min) — circuit breaker & rate limiter with TDD
- Plan 03-02: Automated execution (2 min) — request-level cache with TDD

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Multi-account as shared (admin) + personal (author) — covers both brand accounts and individual authors
- Decompose monolithic classes before adding features — prevents making tech debt worse
- Async syndication via WP-Cron — prevents blocking post publish UI, handles API failures gracefully
- Feature toggle defaults to false (opt-in for users, protects existing single-account users) — 01-01
- Migration preserves encrypted app_password as-is (no re-encryption) — 01-01
- UUID generation is static method for flexibility — 01-01
- Multi-account section uses progressive disclosure (hidden by default, revealed by toggle) — 01-02
- Status determined by DID presence (authenticated if DID exists) — 01-02
- Authentication test runs immediately after adding account (validates credentials) — 01-02
- Factory method creates isolated per-account API handler instances (avoids auth state sharing) — 01-03
- Syndication branches on multi-account toggle at method entry (single-account path unchanged) — 01-03
- Selected accounts stored as JSON array in _bluesky_syndication_accounts post meta — 01-03
- Auto-syndicate accounts pre-selected for new posts when no explicit selection — 01-03
- Per-account results keyed by account UUID in _bluesky_syndication_bs_post_info — 01-03
- First successful account ID saved for backward compatibility with Discussion display — 01-03
- Cache keys include account_id when provided for multi-account scoping — 01-04
- Discussion display uses centralized helper for syndication info extraction (supports old/new formats) — 01-04
- Account selector uses progressive disclosure (only shown when 2+ accounts exist) — 01-04
- Nested forms replaced with div + JS standalone form submission — 01-05
- Auth testing uses direct createSession API call — 01-05
- Per-account transient scoping via account_id property on API handler — 01-05
- Handle normalization: email passthrough, bare username gets .bsky.social suffix — 01-05
- PHPUnit in tests/ with brain/monkey — tests run without WordPress core (02-01)
- Static utility methods on BlueSky_Helpers for cross-cutting concerns (02-01)
- Local by Flywheel PHP 8.3 is the usable PHP binary on this machine (MAMP PHP curl SSL broken) — 02-01
- Constructor DI pattern with optional account_manager parameter for service classes (02-02)
- Render_Front instantiated by Plugin_Setup and passed to Blocks_Service (02-02)
- Assets_Service standalone (no API/account dependencies) (02-02)
- All services instantiate BlueSky_Helpers locally (stateless utilities, no DI needed) (02-02)
- Renderer receives both API handler and Account Manager (02-03)
- Hook registration stays in class constructors (02-03)
- Discussion_Display.php kept as deprecation stub (02-03)
- Use plugin_dir_path(BLUESKY_PLUGIN_FILE) for template path resolution (02-04)
- Templates receive $this context from including methods via PHP scoping (02-04)
- CSS class names bluesky-social-integration-* preserved during refactoring (02-04)
- Legacy AJAX endpoints verify nonce via check_ajax_referer for public access (02-05)
- ?godmode parameter requires manage_options capability (admin-only debug access) (02-05)
- POST data sanitized with sanitize_text_field(wp_unslash()) in syndication (02-05)
- Text domain 'social-integration-for-bluesky' used consistently for i18n (02-05)
- JS i18n strings externalized via wp_localize_script i18n sub-object (02-05)
- PHPUnit 9.6 phar downloaded for test execution (no composer install needed) (02-06)
- WordPress time constants defined in bootstrap for API Handler tests (02-06)
- wpdb mocked with getMockBuilder(stdClass)->addMethods() pattern (02-06)
- Brain Monkey Functions\expect() for strict expectations, andReturn() for flexible mocks (02-06)
- Test organization: one test file per class with descriptive test method names (02-06)
- Circuit breaker uses 3-failure threshold with 15-minute cooldown (03-01)
- Rate limiter supports both numeric seconds and HTTP date Retry-After formats (03-01)
- Exponential backoff applies ±20% jitter to prevent thundering herd (03-01)
- Per-account resilience state isolation prevents one failing account from blocking others (03-01)
- Static variable cache for request-level deduplication (zero database queries) (03-02)
- MD5 serialized params for deterministic cache key generation (03-02)
- PHPUnit XML config standardizes test execution across project (03-02)

### Pending Todos

None yet.

### Blockers/Concerns

- ~~Plugin_Setup.php has grown larger with multi-account additions (Phase 2 refactoring will address)~~ — RESOLVED by 02-02 (now 148 lines)
- PHP binary path must be specified explicitly: `/Users/CRG/Library/Application Support/Local/lightning-services/php-8.3.8+0/bin/darwin/bin/php`

## Session Continuity

Last session: 2026-02-18
Stopped at: Completed 03-02-PLAN.md
Resume file: .planning/phases/03-performance-resilience/03-03-PLAN.md (if exists)

---
*State initialized: 2026-02-14*
*Last updated: 2026-02-18*
