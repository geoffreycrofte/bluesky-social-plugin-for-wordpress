# Requirements: Bluesky Social Integration for WordPress

**Defined:** 2026-02-14
**Core Value:** WordPress users can seamlessly bridge their site and Bluesky presence with zero silent failures and clear recovery paths.

## v1 Requirements

### Multi-Account

- [ ] **ACCT-01**: Admin can connect multiple shared Bluesky accounts from settings page
- [ ] **ACCT-02**: Admin can remove a connected Bluesky account
- [ ] **ACCT-03**: Admin can view connection status for each account (authenticated, expired, error)
- [ ] **ACCT-04**: Per-post selection of which account(s) to syndicate to
- [ ] **ACCT-05**: Existing single-account settings migrate to multi-account structure without data loss
- [ ] **ACCT-06**: Account switcher for selecting active display account

### Profile Display (Enhance Existing)

Existing profile card display (shortcode, block, widget) stays. These add new capabilities:

- [ ] **PROF-01**: New profile banner variant displaying Bluesky-style header image with overlaid avatar, name, bio, follower/following counts
- [ ] **PROF-02**: Profile banner available as Gutenberg block with inspector controls
- [ ] **PROF-03**: Profile banner available as shortcode and classic widget

### Content Display (Enhance Existing)

Existing posts feed with replies/reposts toggle stays. These add new options:

- [ ] **DISP-01**: User can choose grid or list layout for posts feed (extending existing feed)
- [ ] **DISP-02**: User can filter displayed posts by date range
- [ ] **DISP-03**: User can filter displayed posts by hashtag
- [ ] **DISP-04**: Existing reply/repost filtering preserved and enhanced in new UI

### Customization

- [ ] **CUST-01**: Color themes (red, blue, green, pink, yellow, violet) each supporting dark and light mode
- [ ] **CUST-02**: Typography controls (font family, weight, line-height) for displayed Bluesky content
- [ ] **CUST-03**: Live preview of customization changes in admin/editor
- [ ] **CUST-04**: Pre-built style presets for quick setup
- [ ] **CUST-05**: Both Gutenberg blocks and classic widgets support all customization options

### Syndication (Enhance Existing)

Existing auto-syndication on publish and pre-publish preview stay. These add options:

- [ ] **SYND-01**: Global default syndication format setting (basic link vs rich card with image/excerpt)
- [ ] **SYND-02**: Per-post format override in editor
- [ ] **SYND-03**: Category-based syndication rules (e.g., only syndicate "Blog" category)
- [ ] **SYND-04**: Auto-post vs manual syndication toggle per account

### Error Handling & UX

- [ ] **UX-01**: Every API error surfaces with actionable message explaining what happened and how to fix it
- [ ] **UX-02**: Expired tokens show clear re-authentication prompt, not silent failure
- [ ] **UX-03**: Rate limit hits show user-friendly "temporarily unavailable" with retry timing
- [ ] **UX-04**: Admin dashboard widget shows plugin health (last syndication, API status, account health)

### Performance & Resilience

- [ ] **PERF-01**: Plugin detects HTTP 429 responses and applies exponential backoff
- [ ] **PERF-02**: Multiple Bluesky blocks/shortcodes on same page share a single API call (request deduplication)
- [ ] **PERF-03**: Post syndication runs asynchronously via background job (doesn't block publish UI)
- [ ] **PERF-04**: Circuit breaker stops all API requests for 15min after 3 consecutive failures

### Codebase Quality

- [ ] **CODE-01**: Plugin_Setup class (2537 lines) decomposed into focused service classes
- [ ] **CODE-02**: Render_Front class (1085 lines) split into focused renderers
- [ ] **CODE-03**: Discussion_Display class (1337 lines) split into focused components
- [ ] **CODE-04**: Security fixes: remove ?godmode, add nonce protection on public AJAX, validate syndication POST data
- [ ] **CODE-05**: PHPUnit test coverage for API handler, syndication, AJAX endpoints, settings sanitization
- [ ] **CODE-06**: All user-facing strings wrapped in `__()` / `_e()` for translation, .pot file regenerated

## v2 Requirements

### Analytics & Insights
- **ANLY-01**: Admin dashboard showing syndication success/failure rates
- **ANLY-02**: Per-post engagement metrics (likes, reposts, replies) displayed in post list

### Developer Extensibility
- **DEV-01**: Action/filter hooks for syndication, rendering, and account events
- **DEV-02**: Custom template overrides for theme developers

### Advanced Syndication
- **SYND-05**: Scheduled syndication (post now, syndicate later)
- **SYND-06**: Thread creation for long-form content (1/n posts)

### Per-Author Accounts
- **ACCT-07**: WordPress authors can connect their own personal Bluesky account
- **ACCT-08**: Author account used by default for their posts

## Out of Scope

| Feature | Reason |
|---------|--------|
| Real-time live updates | Performance killer, polling drains resources |
| Comment replies from WordPress | API complexity, threading issues, spam risk |
| Bulk backfill of historical posts | Complex edge cases, defer to future |
| Video hosting/uploads | Storage costs, complexity — link to Bluesky media only |
| Multi-platform syndication | Scope creep — Bluesky focus only |
| AI content generation | Quality concerns, scope creep |
| Auto-follow features | Spam behavior, ToS risk |
| Infinite scroll | Accessibility issues, memory leaks — use "load more" |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| ACCT-01 | TBD | Pending |
| ACCT-02 | TBD | Pending |
| ACCT-03 | TBD | Pending |
| ACCT-04 | TBD | Pending |
| ACCT-05 | TBD | Pending |
| ACCT-06 | TBD | Pending |
| PROF-01 | TBD | Pending |
| PROF-02 | TBD | Pending |
| PROF-03 | TBD | Pending |
| DISP-01 | TBD | Pending |
| DISP-02 | TBD | Pending |
| DISP-03 | TBD | Pending |
| DISP-04 | TBD | Pending |
| CUST-01 | TBD | Pending |
| CUST-02 | TBD | Pending |
| CUST-03 | TBD | Pending |
| CUST-04 | TBD | Pending |
| CUST-05 | TBD | Pending |
| SYND-01 | TBD | Pending |
| SYND-02 | TBD | Pending |
| SYND-03 | TBD | Pending |
| SYND-04 | TBD | Pending |
| UX-01 | TBD | Pending |
| UX-02 | TBD | Pending |
| UX-03 | TBD | Pending |
| UX-04 | TBD | Pending |
| PERF-01 | TBD | Pending |
| PERF-02 | TBD | Pending |
| PERF-03 | TBD | Pending |
| PERF-04 | TBD | Pending |
| CODE-01 | TBD | Pending |
| CODE-02 | TBD | Pending |
| CODE-03 | TBD | Pending |
| CODE-04 | TBD | Pending |
| CODE-05 | TBD | Pending |
| CODE-06 | TBD | Pending |

**Coverage:**
- v1 requirements: 32 total
- Mapped to phases: 0
- Unmapped: 32

---
*Requirements defined: 2026-02-14*
*Last updated: 2026-02-14 after initial definition*
