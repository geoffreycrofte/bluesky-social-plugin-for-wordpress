# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-14)

**Core value:** WordPress users can seamlessly bridge their WordPress site and Bluesky presence — displaying Bluesky content on their site and syndicating WordPress posts to Bluesky — with zero silent failures and clear recovery paths when things go wrong.
**Current focus:** Phase 4 (Error Handling UX) — Plan 02 complete

## Current Position

Phase: 4 of 7 (Error Handling UX) — Plan 02 complete
Next: Plan 03 (Health Dashboard Widget)
Last activity: 2026-02-19 — 04-02 complete (Persistent Admin Notices)

Progress: [██████████] 100% (Phase 1) | [██████████] 100% (Phase 2) | [██████████] 100% (Phase 3) | [████░░░░░░] 40% (Phase 4)

## Performance Metrics

**Velocity:**
- Total plans completed: 19
- Average duration: ~3.5 minutes (automated plans)
- Total execution time: ~2.2 hours + 4 iterative testing sessions

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01 | 5 | ~20 min + testing | ~4 min |
| 02 | 6 | ~40 min | ~6.7 min |
| 03 | 6 | ~24 min | ~4.0 min |
| 04 | 2 | ~5.1 min | ~2.6 min |

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
- Plan 03-03: Automated execution (3 min) — async syndication handler with Action Scheduler
- Plan 03-04: Automated execution (2.6 min) — admin notification system with Heartbeat API
- Plan 03-05: Automated execution (4 min) — resilience integration (3-layer cache + stale-while-revalidate)
- Plan 03-06: Checkpoint (~8 min) — E2E verification, 2 bug fixes, human approved
- Plan 04-01: Automated execution (2.2 min) — error translation & activity logging utility classes
- Plan 04-02: Automated execution (2.9 min) — persistent admin notices with per-account status

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
- Action Scheduler integration with function_exists guard for graceful degradation (03-03)
- Retry delays hardcoded as class constants: 60s, 120s, 300s (03-03)
- Validation stays in Syndication_Service, execution moves to Async_Handler (03-03)
- Post meta _bluesky_syndication_status tracks: pending, retrying, circuit_open, rate_limited, completed, partial, failed (03-03)
- Circuit breaker cooldown: 15 minutes (hardcoded in queue_for_cooldown) (03-03)
- Rate limiter provides custom retry delay overriding exponential backoff (03-03)
- Heartbeat API for live updates (no custom polling endpoints needed) (03-04)
- Retry button delegates to Async_Handler->schedule_syndication() (reuses existing logic) (03-04)
- Script only loads on post edit screens with syndication status (performance) (03-04)
- Heartbeat stops polling on final states: completed, failed, partial (no unnecessary requests) (03-04)
- Post list column shows icons with dashicons (no custom assets needed) (03-04)
- Nonce created server-side via wp_create_nonce() and verified via check_ajax_referer() (03-04)
- 3-layer cache strategy (request→transient→API) with stale-while-revalidate serving (03-05)
- Cache duration default changed from 1 hour to 10 minutes (600s) for faster staleness detection (03-05)
- Serve stale cache when circuit is open or rate limited (never empty/error if cache exists) (03-05)
- Background refresh via Action Scheduler with 5-min refreshing lock to prevent duplicates (03-05)
- Freshness marker transient ({cache_key}_fresh) with normal TTL to detect staleness (03-05)
- Sync paths set _bluesky_syndication_status for column display + legacy _bluesky_syndicated fallback (03-06)
- Layout_2 profile header fetched by renderer, not template-level transient lookup (03-06)
- Action links only for critical errors (auth/config), not transient errors (04-01)
- Circular buffer max 10 events with FIFO rotation for activity log (04-01)
- Static utility class pattern for Error Translator, instantiable for Activity Logger (04-01)
- Persistent notices use user meta with 24-hour expiry (per-user dismissal) (04-02)
- Multiple broken accounts grouped into single notice (max 5 shown explicitly) (04-02)
- Retry button only shown after 3 auto-retries exhaust (retry_count >= 3) (04-02)
- Per-account syndication detail shown inline with specific error messages (04-02)

### Pending Todos

None yet.

### Blockers/Concerns

- ~~Plugin_Setup.php has grown larger with multi-account additions (Phase 2 refactoring will address)~~ — RESOLVED by 02-02 (now 148 lines)
- PHP binary path must be specified explicitly: `/Users/CRG/Library/Application Support/Local/lightning-services/php-8.3.8+0/bin/darwin/bin/php`

## Session Continuity

Last session: 2026-02-19
Stopped at: Completed 04-02-PLAN.md
Resume file: .planning/phases/04-error-handling-ux/04-02-SUMMARY.md

---
*State initialized: 2026-02-14*
*Last updated: 2026-02-19*
