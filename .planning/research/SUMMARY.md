# Project Research Summary

**Project:** Bluesky Social Plugin for WordPress - Multi-Account Support & Refactoring
**Domain:** WordPress Plugin — Social Media Integration
**Researched:** 2026-02-14
**Confidence:** MEDIUM-HIGH

## Executive Summary

This is a WordPress plugin integrating Bluesky social network functionality into WordPress sites. The plugin currently provides single-account authentication, profile/feed display via blocks/widgets/shortcodes, and auto-syndication of posts to Bluesky. Research shows that the existing technical stack (PHP 7.4+, WordPress 5.0+, vanilla JS) is appropriate and requires no framework migrations. The primary evolution needed is transitioning from single-account to multi-account support while decomposing a 2537-line monolithic class into maintainable components.

The recommended approach follows a layered service architecture with dependency injection, using WordPress's native Options API for account storage and the Action Scheduler library for reliable async operations. The critical path is: (1) multi-account foundation with proper migration, (2) systematic code decomposition into services, (3) enhanced customization system, and (4) performance/resilience improvements. This order ensures data integrity during migration while enabling advanced features that depend on the new architecture.

Key risks center on breaking existing user data during the multi-account migration, race conditions in async syndication, and undocumented Bluesky API rate limits causing cascade failures. Mitigation requires version-gated migrations with rollback capabilities, Action Scheduler for atomic job processing, and circuit breaker patterns for API rate limit handling. Research indicates competitors like Smash Balloon and Blog2Social succeed through visual polish and deep customization, suggesting these should be prioritized alongside multi-account support.

## Key Findings

### Recommended Stack

The existing stack is solid and requires no major changes. WordPress plugin development patterns dictate using native WordPress APIs (Options, Transients, Meta) rather than custom solutions. The research recommends strategic additions rather than migrations: Action Scheduler for async job reliability (addressing WP-Cron's unreliability on low-traffic sites), PHPUnit with WP_UnitTestCase for testing infrastructure, and PHPCS with WordPress Coding Standards for code quality enforcement.

**Core technologies:**
- PHP 7.4+ with WordPress 5.0+: Broad hosting compatibility, Gutenberg block support
- WordPress Options/Meta/Transients APIs: Standard data persistence patterns for multi-account data
- Action Scheduler (by Automattic): Guaranteed async execution with locking, retry, and logging (used by WooCommerce)
- OpenSSL for AES-256-CBC encryption: Secure app password storage at rest
- AT Protocol xRPC via bsky.social: JWT auth with undocumented rate limits requiring defensive coding

**Critical "what NOT to add":**
- No Composer for production dependencies (adds contributor friction)
- No npm/webpack/build tools (current vanilla JS is appropriate)
- No custom database tables (Options API handles ~20 accounts well)
- No Redis/Memcached requirements (Transients work for current scale)

### Expected Features

Research analyzing Smash Balloon, Blog2Social, and Jetpack Social identifies clear feature tiers.

**Must have (table stakes):**
- Multi-account authentication with secure storage (MISSING - top priority)
- Profile card/banner display with feed rendering (banner MISSING)
- Auto-syndication with per-post opt-out and preview (basic version DONE)
- Clear error messages and connection status (needs UX polish)
- Gutenberg blocks, widgets, shortcodes (DONE)
- Caching with configurable TTL (DONE)
- Responsive design with dark/light modes (DONE)

**Should have (competitive advantage):**
- Multi-account UI with account switching and per-block account selection
- Deep customization (color schemes, typography, layouts) - Smash Balloon's retention driver
- Advanced syndication (global format + per-post override, category rules)
- Content filtering (hashtags, date ranges, exclusions)
- Discussion threading (unique to Bluesky, already DONE)
- Actionable error messages with recovery paths
- Profile banner display (Bluesky-style immersive presence)

**Defer (v2+):**
- Analytics dashboard (moderate value, high complexity)
- Scheduled posting (power user feature requiring queue infrastructure)
- Developer hooks/API (extensibility for advanced users)
- Onboarding wizard (polish feature after core is solid)

**Anti-features to avoid:**
- Unlimited posts display (API limits, performance issues)
- Real-time updates (polling kills performance)
- Two-way comment integration (API complexity, spam risk)
- Auto-posting without preview (brand risk)
- Infinite scroll (accessibility issues, memory leaks)

### Architecture Approach

The research recommends decomposing the monolithic `BlueSky_Plugin_Setup.php` (2537 lines) into a layered service architecture with clear component boundaries. Services handle business logic and return data objects; controllers orchestrate services and handle WordPress integration; repositories encapsulate data access; renderers generate HTML from data objects.

**Major components:**
1. **Account_Service** — Account CRUD, switching, ownership tracking (foundation for multi-account)
2. **Post_Service / Discussion_Service** — Fetch and transform Bluesky content with account-scoped caching
3. **Syndication_Service** — Async WordPress-to-Bluesky publishing via Action Scheduler
4. **Settings_Manager / Cache_Manager** — Infrastructure wrappers around WordPress APIs for testability
5. **Rendering_Service** — Template-based HTML generation with theme compatibility
6. **Admin/AJAX/Block/Widget Controllers** — UI routing and request handling

**Critical patterns:**
- Dependency injection via constructors (testability, explicit dependencies)
- Repository pattern for all data access (centralized, easier to migrate)
- Account-scoped caching (prevent cache pollution between accounts)
- Event-driven operations (custom WordPress actions for extensibility)
- Progressive disclosure in UI (hide multi-account complexity when single account)

**Decomposition build order:**
1. Foundation (Settings_Manager, Cache_Manager, Service_Container, Hook_Loader)
2. Data layer (Account entity, Account_Repository, Account_Service, migration routine)
3. Service extraction (Post_Service, Discussion_Service, Syndication_Service)
4. Controller layer (Admin_Controller, AJAX_Controller, Block_Controller)

### Critical Pitfalls

1. **Breaking existing settings during multi-account migration** — Version-gated migration with rollback, preserve original settings, migrate post meta from `_bluesky_syndicated` to include account associations, admin verification notice
2. **Race conditions in async syndication** — WP-Cron unreliability on low-traffic sites, no locking causes duplicate posts. Use Action Scheduler with atomic meta checks (`add_post_meta(..., unique=true)`), idempotency keys, token refresh mid-queue
3. **Bluesky API rate limit cascade** — Undocumented per-DID rate limits (429 errors). Implement exponential backoff, circuit breaker after 3 consecutive failures, request deduplication across shortcodes, priority queue (syndication > discussion > feed)
4. **Decomposing without interface contracts** — Hook registration order changes break functionality. Extract interfaces first, audit all hook priorities before extraction, incremental commits with smoke testing, use facade pattern during transition
5. **Multi-account post ownership ambiguity** — Existing `_bluesky_uri` lacks account association. Add `_bluesky_account_id` meta, backfill from DIDs in URIs, orphan detection on account deletion

## Implications for Roadmap

Based on research, the roadmap should follow a dependency-driven structure that prioritizes data integrity and architectural foundation before advanced features.

### Phase 1: Multi-Account Foundation
**Rationale:** All advanced features (per-block accounts, advanced syndication, author connections) depend on multi-account infrastructure. Must establish data model and migration path before proceeding.
**Delivers:** Account entity, Account_Repository, Account_Service, migration routine from single to multi-account with rollback
**Addresses:** Multi-account authentication (table stakes), account switching UI (differentiator)
**Avoids:** Pitfall #1 (data loss during migration), Pitfall #5 (post ownership ambiguity)
**Stack needs:** WordPress Options API for account storage, OpenSSL for credential encryption
**Architecture:** Account entity, Account_Repository, Settings_Manager wrapper

### Phase 2: Core Refactoring & Infrastructure
**Rationale:** Decomposing monolithic class enables testability, maintainability, and advanced features. Must happen early before adding complexity. Establishes patterns for all future development.
**Delivers:** Service_Container, Hook_Loader, Settings_Manager, Cache_Manager, extracted service classes (Post_Service, Discussion_Service, Syndication_Service)
**Addresses:** Code quality (P1), testability, technical debt reduction
**Avoids:** Pitfall #4 (interface violations during decomposition), Pitfall #7 (cache collision)
**Stack needs:** PHPUnit + WP_UnitTestCase for testing infrastructure
**Architecture:** Full service layer implementation with dependency injection

### Phase 3: Async Syndication & Resilience
**Rationale:** Current synchronous syndication blocks page loads and lacks retry. Multi-account increases API volume, requiring robust queue handling and rate limit defense.
**Delivers:** Action Scheduler integration, async syndication with retry, rate limit handling, circuit breaker pattern, atomic deduplication
**Addresses:** Reliable syndication (table stakes), performance at scale
**Avoids:** Pitfall #2 (race conditions), Pitfall #3 (rate limit cascade)
**Stack needs:** Action Scheduler library (bundled, not Composer dependency)
**Architecture:** Syndication_Service with queue integration

### Phase 4: Advanced Display & Customization
**Rationale:** Competitor research shows deep customization drives retention (Smash Balloon model). Profile banner completes table stakes, advanced customization provides competitive edge.
**Delivers:** Profile banner display, color scheme editor, typography controls, layout engine (grid/masonry), CSS variable system, live preview
**Addresses:** Profile banner (table stakes), deep customization (differentiator), visual polish
**Stack needs:** CSS 3 with custom property system
**Architecture:** Rendering_Service with template overrides, customization settings repository

### Phase 5: Advanced Syndication Features
**Rationale:** Builds on multi-account and async foundation. Global format + per-post override provides flexibility requested by power users.
**Delivers:** Global syndication format settings, per-post format override, category-based rules, format preview
**Addresses:** Syndication format options (P2), category rules (P2)
**Stack needs:** WordPress Meta API for per-post settings
**Architecture:** Syndication_Service enhancements

### Phase 6: Security, Testing & Polish
**Rationale:** Final phase before release ensures WordPress.org compliance and production readiness.
**Delivers:** Security audit (nonce validation, escaping, capability checks), comprehensive test coverage, error UX improvements, debug code removal, settings export/import
**Addresses:** WordPress.org compliance, security fixes (P1), error UX (P1)
**Avoids:** Pitfall #14 (debug leaks), Pitfall #6 (encryption key rotation), Pitfall #13 (missing translations)
**Stack needs:** PHPCS + WordPress Coding Standards, PHPStan for static analysis

### Phase Ordering Rationale

- **Multi-account first:** Architectural foundation; changing data model later is risky and expensive
- **Refactoring second:** Clean architecture enables rapid feature development; delaying creates technical debt
- **Async/resilience third:** Performance issues compound with multi-account; better to solve early
- **Display/customization fourth:** User-facing polish with clear value, benefits from solid architecture
- **Advanced syndication fifth:** Power user features built on proven foundation
- **Security/testing sixth:** Final validation before release, easier with clean codebase

This order minimizes rework (establish patterns early), reduces risk (data migration isolated and tested), and delivers incremental value (each phase produces working features).

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 3 (Async Syndication):** Action Scheduler API integration patterns, WordPress action hooks for queue jobs, specific error handling for AT Protocol edge cases
- **Phase 5 (Advanced Syndication):** AT Protocol rich embed format specifications, category taxonomy edge cases

Phases with standard patterns (skip research-phase):
- **Phase 1 (Multi-Account):** WordPress Options API patterns well-documented, migration routines are standard
- **Phase 2 (Refactoring):** Service layer and dependency injection are established PHP patterns
- **Phase 4 (Display/Customization):** CSS variable systems and WordPress block customization controls are well-documented
- **Phase 6 (Security/Testing):** WordPress.org review guidelines and PHPCS standards are explicit

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Current stack appropriate, WordPress APIs well-documented, Action Scheduler battle-tested by WooCommerce |
| Features | HIGH | Strong competitor analysis (Smash Balloon, Blog2Social, Jetpack Social), clear differentiation between table stakes and differentiators |
| Architecture | MEDIUM | Service layer patterns established, but specific decomposition order requires care to avoid breaking changes |
| Pitfalls | MEDIUM | Migration and async risks identified from codebase analysis, but Bluesky API rate limits undocumented (requires defensive coding) |

**Overall confidence:** MEDIUM-HIGH

### Gaps to Address

- **Bluesky API rate limits:** AT Protocol documentation lacks rate limit specifics. Solution: Implement conservative limits (10 req/min), monitor 429 responses, adjust dynamically
- **Action Scheduler integration specifics:** General patterns known, but specific hook registration and error handling needs validation during Phase 3 planning
- **Multi-account scalability threshold:** Research suggests Options API works well up to ~20 accounts, but actual performance threshold unknown. Solution: Monitor and consider custom table migration if needed in future
- **WordPress multisite compatibility:** Not researched in depth. Flag for validation during testing phase
- **Translation/i18n completeness:** Current state unknown. Audit needed in Phase 6

## Sources

### Primary (HIGH confidence)
- Current codebase analysis (.planning/CONCERNS.md, BlueSky_Plugin_Setup.php, all plugin files)
- WordPress Plugin Handbook (developer.wordpress.org)
- WordPress Coding Standards (github.com/WordPress/WordPress-Coding-Standards)
- Action Scheduler documentation (actionscheduler.org)
- AT Protocol API documentation (atproto.com)

### Secondary (MEDIUM confidence)
- Smash Balloon plugin suite analysis (Instagram Feeds, Facebook Feeds, Twitter Feeds)
- Blog2Social multi-platform publishing plugin patterns
- Jetpack Social functionality review
- WooCommerce architecture (enterprise WordPress plugin patterns)
- WordPress REST API and Block Editor Handbook

### Tertiary (LOW confidence)
- Community discussions on Bluesky API rate limits (inferred from codebase comments)
- WordPress multisite compatibility assumptions (needs validation)

---
*Research completed: 2026-02-14*
*Ready for roadmap: yes*
