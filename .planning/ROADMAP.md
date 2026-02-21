# Roadmap: Bluesky Social Integration for WordPress

## Overview

This roadmap transforms an existing single-account Bluesky WordPress plugin into a polished multi-account platform with enterprise-grade reliability. The journey begins with multi-account foundation and data migration, then systematically refactors the monolithic codebase into testable services, adds async syndication with resilience patterns, enhances error UX, delivers visual polish through display enhancements and deep customization, and finishes with advanced syndication features. Each phase delivers working, verifiable capabilities that build toward the complete vision.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Multi-Account Foundation** - Establish multi-account architecture and migrate existing data
- [ ] **Phase 2: Codebase Refactoring** - Decompose monolithic classes into testable services
- [x] **Phase 3: Performance & Resilience** - Async syndication with rate limit handling (completed 2026-02-19)
- [ ] **Phase 4: Error Handling & UX** - Actionable errors and health monitoring
- [x] **Phase 5: Profile & Content Display** - Profile banner and advanced feed layouts
- [ ] **Phase 6: Advanced Syndication** - Format options and category-based rules

## Phase Details

### Phase 1: Multi-Account Foundation
**Goal**: Users can connect and manage multiple Bluesky accounts with seamless migration from single-account
**Depends on**: Nothing (first phase)
**Requirements**: ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05, ACCT-06
**Success Criteria** (what must be TRUE):
  1. Admin can add multiple Bluesky accounts from settings page with handle and app password
  2. Each connected account shows current connection status (authenticated, expired, error)
  3. Admin can remove a connected account with confirmation
  4. Existing single-account data migrates to multi-account structure without data loss
  5. User can select which account(s) to syndicate to on a per-post basis in editor
  6. User can switch which account to display content from (profile cards, feeds)
**Plans:** 5 plans

Plans:
- [x] 01-01-PLAN.md -- Account Manager class with CRUD, migration, feature toggle, UUID generation
- [x] 01-02-PLAN.md -- Settings page multi-account UI with progressive disclosure
- [x] 01-03-PLAN.md -- Multi-account syndication execution and editor account selection
- [x] 01-04-PLAN.md -- Async pipeline account threading, cache scoping, discussion display
- [x] 01-05-PLAN.md -- End-to-end verification checkpoint (human verify)

### Phase 2: Codebase Refactoring
**Goal**: Codebase is decomposed into maintainable services with test coverage and security fixes
**Depends on**: Phase 1
**Requirements**: CODE-01, CODE-02, CODE-03, CODE-04, CODE-05, CODE-06
**Success Criteria** (what must be TRUE):
  1. Plugin_Setup class replaced by focused service classes with dependency injection
  2. Render_Front class split into template-based renderers
  3. Discussion_Display class decomposed into focused components
  4. Security vulnerabilities fixed (godmode auth-gated, nonce protection on AJAX, sanitized POST data)
  5. PHPUnit tests cover API handler, syndication, AJAX endpoints, and settings sanitization
  6. All user-facing strings wrapped in translation functions (no .pot regeneration needed)
**Plans:** 7 plans

Plans:
- [ ] 02-01-PLAN.md -- Test infrastructure (PHPUnit + Brain Monkey) and normalize_handle to Helpers
- [ ] 02-02-PLAN.md -- Plugin_Setup decomposition into 5 service classes + thin coordinator
- [ ] 02-03-PLAN.md -- Discussion_Display decomposition into Metabox, Renderer, Frontend
- [ ] 02-04-PLAN.md -- Template extraction for settings page, posts list, profile card
- [ ] 02-05-PLAN.md -- Security fixes (AJAX nonce, godmode, POST sanitization) + i18n (PHP + JS)
- [ ] 02-06-PLAN.md -- PHPUnit tests for AJAX, Syndication, Settings, Helpers
- [ ] 02-07-PLAN.md -- End-to-end verification checkpoint (automated + human verify)

### Phase 3: Performance & Resilience
**Goal**: Plugin handles API failures gracefully with async syndication and intelligent rate limiting
**Depends on**: Phase 2
**Requirements**: PERF-01, PERF-02, PERF-03, PERF-04
**Success Criteria** (what must be TRUE):
  1. Post syndication happens asynchronously after publish (doesn't block user)
  2. Multiple Bluesky blocks on same page make only one API call per account
  3. Plugin detects HTTP 429 rate limit responses and backs off exponentially
  4. After 3 consecutive API failures, plugin stops requests for 15 minutes (circuit breaker)
**Plans:** 6/6 plans complete

Plans:
- [ ] 03-01-PLAN.md -- Circuit breaker + rate limiter (TDD)
- [ ] 03-02-PLAN.md -- Request-level cache deduplication (TDD)
- [ ] 03-03-PLAN.md -- Async syndication handler with Action Scheduler
- [ ] 03-04-PLAN.md -- Admin notices with Heartbeat + retry UI
- [ ] 03-05-PLAN.md -- Integration wiring into API handler + stale-while-revalidate
- [ ] 03-06-PLAN.md -- End-to-end verification (automated + human)

### Phase 4: Error Handling & UX
**Goal**: Users receive clear, actionable error messages with visible plugin health status
**Depends on**: Phase 3
**Requirements**: UX-01, UX-02, UX-03, UX-04
**Success Criteria** (what must be TRUE):
  1. Every API error shows user-friendly message explaining what happened and how to fix it
  2. Expired authentication tokens trigger re-authentication prompt, not silent failure
  3. Rate limit errors display "temporarily unavailable" with retry timing estimate
  4. Admin dashboard widget shows plugin health: last syndication time, API status, account health
**Plans:** 5 plans

Plans:
- [ ] 04-01-PLAN.md -- Error Translator + Activity Logger foundation classes
- [ ] 04-02-PLAN.md -- Enhanced Admin Notices with persistent dismissal + per-account status
- [ ] 04-03-PLAN.md -- Health Dashboard widget + Settings page health section rework
- [ ] 04-04-PLAN.md -- WordPress Site Health integration (tests + debug info)
- [ ] 04-05-PLAN.md -- Integration wiring + E2E human verification

### Phase 5: Profile & Content Display
**Goal**: Users can display Bluesky-style profile banners and enhanced feed with GIF support, skeleton loaders, and polished states
**Depends on**: Phase 4
**Requirements**: PROF-01, PROF-02, PROF-03, DISP-04 (DISP-01, DISP-02, DISP-03 dropped by user)
**Success Criteria** (what must be TRUE):
  1. Profile banner displays header image with overlaid avatar, name, bio, and follower counts (Bluesky-style)
  2. Profile banner available as Gutenberg block with inspector controls
  3. Profile banner available as shortcode and classic widget
  4. Existing reply/repost filters work seamlessly with feed enhancements
**Plans:** 4 plans

Plans:
- [ ] 05-01-PLAN.md -- Profile banner renderer with two variants (full + compact) and CSS
- [ ] 05-02-PLAN.md -- Feed enhancements: GIF detection, skeleton loaders, empty/stale states
- [ ] 05-03-PLAN.md -- Profile banner Gutenberg block, shortcode, widget, gradient fallback JS
- [ ] 05-04-PLAN.md -- Integration wiring + E2E human verification

### Phase 6: Advanced Syndication
**Goal**: Users can configure editable syndication text with character counting, category-based routing rules per account, and global syndication controls
**Depends on**: Phase 5
**Requirements**: SYND-01, SYND-02, SYND-03, SYND-04
**Success Criteria** (what must be TRUE):
  1. Per-post editable syndication text with live grapheme-based character counter (300 limit)
  2. Editable text available in both Gutenberg sidebar panel and pre-publish panel
  3. Category-based syndication rules (e.g., "only syndicate posts in Blog category to Account A")
  4. Global syndication pause toggle in settings
**Plans:** 4 plans

Plans:
- [ ] 06-01-PLAN.md -- Editable post text meta, character counter utility, Gutenberg sidebar panel
- [ ] 06-02-PLAN.md -- Category rules settings tab, account data extension, global pause toggle
- [ ] 06-03-PLAN.md -- Pre-publish panel extension, syndication flow wiring (custom text, filtering, pause)
- [ ] 06-04-PLAN.md -- End-to-end verification checkpoint (human verify)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Multi-Account Foundation | 5/5 | Complete | 2026-02-17 |
| 2. Codebase Refactoring | 0/7 | Not started | - |
| 3. Performance & Resilience | 0/TBD | Complete    | 2026-02-19 |
| 4. Error Handling & UX | 0/TBD | Not started | - |
| 5. Profile & Content Display | 4/4 | Complete | 2026-02-20 |
| 6. Advanced Syndication | 0/4 | Not started | - |

---
*Roadmap created: 2026-02-14*
*Last updated: 2026-02-17*
