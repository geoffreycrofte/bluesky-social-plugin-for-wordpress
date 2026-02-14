# Bluesky Social Integration for WordPress

## What This Is

The definitive Bluesky integration for WordPress — a plugin that lets site owners display their Bluesky profile and posts beautifully on their site, syndicate WordPress content to multiple Bluesky accounts, and manage everything through an intuitive admin experience. Built for both Gutenberg and classic WordPress, with deep customization and polished UX.

## Core Value

WordPress users can seamlessly bridge their WordPress site and Bluesky presence — displaying Bluesky content on their site and syndicating WordPress posts to Bluesky — with zero silent failures and clear recovery paths when things go wrong.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. Inferred from existing codebase. -->

- ✓ Single Bluesky account authentication (handle + app password) — existing
- ✓ Post syndication from WordPress to Bluesky on publish — existing
- ✓ Profile card display via shortcode and Gutenberg block — existing
- ✓ Posts feed display via shortcode, Gutenberg block, and widget — existing
- ✓ Discussion thread display on syndicated posts — existing
- ✓ Pre-publish syndication panel in Gutenberg editor — existing
- ✓ Basic theme/styling options for displayed content — existing
- ✓ Transient-based caching for API responses — existing
- ✓ Encrypted credential storage (AES-256-CBC) — existing

### Active

<!-- Current scope. Building toward these. -->

**Multi-Account Support:**
- [ ] Admin can connect multiple shared Bluesky accounts
- [ ] Authors can connect their own personal Bluesky account
- [ ] Per-post selection of which account(s) to syndicate to
- [ ] Account management UI with connection status per account

**Profile Banner Display:**
- [ ] Full Bluesky-style profile banner (header image + overlaid avatar, name, bio, follower/following counts)
- [ ] Available as Gutenberg block and shortcode
- [ ] Customizable layout and styling options

**Syndication Format Options:**
- [ ] Global default format setting (basic link vs rich card with image/excerpt)
- [ ] Per-post format override in editor
- [ ] Live preview of syndicated post before publishing

**Visual Styling Customization:**
- [ ] Color, font, and layout options for all displayed Bluesky content
- [ ] Live preview of customization changes in admin/editor
- [ ] Theme-aware defaults that adapt to the active WordPress theme

**Content Filtering:**
- [ ] Filter displayed posts: include/exclude replies, reposts
- [ ] Date range filtering for displayed content
- [ ] Keyword or hashtag-based filtering

**Syndication Rules:**
- [ ] Auto-post vs manual syndication toggle per account
- [ ] Category-based syndication rules (e.g., only syndicate "Blog" category)
- [ ] Scheduling support for syndicated posts

**Display Layout Options:**
- [ ] Grid vs list layout for posts feed
- [ ] Card style variants
- [ ] Configurable post count and pagination

**Both Block Editor + Classic Support:**
- [ ] All display features available as Gutenberg blocks with inspector controls
- [ ] All display features available as classic widgets and shortcodes
- [ ] Consistent feature parity between both approaches

**Error Handling & UX:**
- [ ] No silent failures — every error surfaces with actionable message
- [ ] Clear recovery paths for common issues (expired tokens, API downtime, rate limits)
- [ ] Structured logging for debugging (wp-content/debug.log)
- [ ] Admin dashboard widget for plugin health (last syndication, API status)

**Performance & API Resilience:**
- [ ] Rate limit detection with exponential backoff
- [ ] Request deduplication (multiple blocks on same page share one API call)
- [ ] Async syndication via WP-Cron (don't block post publish UI)
- [ ] Smart cache management to prevent transient bloat

**Codebase Quality:**
- [ ] Decompose monolithic classes (Plugin Setup 2537 lines, Render Front 1085 lines, Discussion Display 1337 lines)
- [ ] Fix security vulnerabilities (remove ?godmode, add nonce protection on public AJAX, validate syndication POST data)
- [ ] Add test coverage for critical paths (API handler, syndication, AJAX endpoints, settings sanitization)
- [ ] Extract rendering into template files instead of inline PHP strings

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Real-time chat/DM integration with Bluesky — high complexity, not core to display/syndication value
- Mobile app companion — web-first, WordPress admin is the interface
- OAuth-based Bluesky login for WordPress users — separate auth concern, app passwords sufficient
- Bulk backfill syndication of historical posts — complex edge cases, defer to future milestone
- Video post support in syndication — Bluesky video API still evolving, defer until stable

## Context

**Existing codebase:** Functional WordPress plugin with single-account Bluesky integration. Already published to WordPress plugin directory. Core features work but codebase has accumulated tech debt — three monolithic PHP classes (2537, 1337, and 1085 lines), zero test coverage, and several security concerns flagged in analysis.

**Technical ecosystem:** WordPress plugin (PHP 7.4+, no build tools, no package manager). Uses AT Protocol / Bluesky xRPC API with JWT-based auth. All state stored in WordPress options table and transients.

**Key challenge:** Evolving from single-account to multi-account fundamentally changes the data model — credentials, tokens, and settings currently assume one account. This is the highest-risk architectural change.

**User expectations:** Plugin users expect WordPress-quality admin UX — settings pages that preview changes, clear feedback on every action, and graceful handling of API issues. The Bluesky API has unspecified rate limits, so resilience must be built in proactively.

## Constraints

- **Tech stack**: PHP 7.4+ with WordPress 5.0+ — no Node.js build pipeline, no Composer dependencies. Assets served directly
- **API dependency**: Bluesky xRPC API with undocumented rate limits — must handle gracefully
- **Backward compatibility**: Existing users have single-account settings stored — migration path required
- **No external services**: Plugin must work without external proxy or middleware — direct WordPress-to-Bluesky communication only
- **WordPress.org guidelines**: Must comply with plugin directory requirements (no minified-only JS, proper escaping, nonce validation)

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Multi-account as shared (admin) + personal (author) | Covers both use cases: brand accounts and individual authors | — Pending |
| Global syndication format with per-post override | Reduces friction (set once) while preserving flexibility | — Pending |
| Async syndication via WP-Cron | Prevents blocking post publish UI, handles API failures gracefully | — Pending |
| Decompose monolithic classes before adding features | Prevents making tech debt worse while building on top of it | — Pending |
| Both Gutenberg + classic support | WordPress ecosystem still split, can't exclude either audience | — Pending |

---
*Last updated: 2026-02-14 after initialization*
