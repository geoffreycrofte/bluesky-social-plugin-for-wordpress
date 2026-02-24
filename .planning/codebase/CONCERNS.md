# Codebase Concerns

**Analysis Date:** 2026-02-14

## Tech Debt

**Monolithic Plugin Setup Class:**
- Issue: `BlueSky_Plugin_Setup.php` is 2537 lines, handling admin UI rendering, AJAX handlers, post syndication, settings sanitization, and multiple hooks
- Files: `classes/BlueSky_Plugin_Setup.php`
- Impact: Single file is too large to maintain, understand, and test. Changes to one concern (e.g., settings UI) risk breaking others (syndication logic). Difficult for future developers to locate specific functionality
- Fix approach: Extract concerns into separate classes - admin UI rendering (separate class), settings validation (separate class), AJAX handlers (separate class), syndication logic (separate class)

**Large Frontend Rendering Class:**
- Issue: `BlueSky_Render_Front.php` is 1085 lines, mixing shortcode handlers, profile card rendering, post feed rendering, styling concerns, and complex nested HTML templating
- Files: `classes/BlueSky_Render_Front.php`
- Impact: Difficult to modify display logic without breaking other features. Complex template nesting makes sanitization/escaping harder to verify
- Fix approach: Split into Profile Card renderer, Post Feed renderer, and Content Renderer for cleaner separation

**Large Discussion Display Class:**
- Issue: `BlueSky_Discussion_Display.php` is 1337 lines, containing metabox rendering, AJAX handlers, frontend display, reply threading, media rendering in single class
- Files: `classes/BlueSky_Discussion_Display.php`
- Impact: Multiple responsibilities in one file makes testing harder and increases risk of side effects when changing discussion display
- Fix approach: Separate into Discussion Formatter, Discussion Renderer, and Thread Builder

## Security Concerns

**Missing Nonce Protection on Public AJAX Endpoints:**
- Risk: `fetch_bluesky_posts` and `get_bluesky_profile` AJAX actions are registered for `wp_ajax_nopriv_*` (unauthenticated users) without nonce validation
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 80-96)
- Current mitigation: None - endpoints are public and allow caching bypass if nonce required
- Recommendations: Add optional nonce validation with graceful fallback for public endpoints, or restrict to authenticated only if data sensitivity doesn't require public access

**Unvalidated SQL LIKE Queries:**
- Risk: While using `$wpdb->options` placeholders, the LIKE patterns contain hard-coded option names. If these were dynamically built from user input (currently they aren't), SQL injection would be possible
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 343-349), `classes/BlueSky_Discussion_Display.php` (lines 1192-1198)
- Current mitigation: Patterns are hard-coded, so currently safe, but pattern is not best practice
- Recommendations: Use `$wpdb->prepare()` for all LIKE queries even with hard-coded values to establish safety pattern

**Post Syndication Bypass Risk:**
- Risk: Syndication check relies on post meta `_bluesky_dont_syndicate` but also checks `$_POST["bluesky_dont_syndicate"]` in same condition. No nonce validation on POST check before syndication happens
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 2250-2264)
- Current mitigation: Occurs inside `transition_post_status` hook which requires edit_post capability, but relies on post editor form submission nonce
- Recommendations: Verify nonce from post metabox form is present in `$_POST` before checking its value

**Debug Bar Exposed with WP_DEBUG:**
- Risk: Admin-facing debug information displayed when `WP_DEBUG` or `WP_DEBUG_DISPLAY` defined, but also accessible via `?godmode` GET parameter without authentication
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 2107-2109, 2110-2133)
- Current mitigation: Contains `var_dump()` of plugin options which could expose sensitive data including encrypted credentials
- Recommendations: Remove `?godmode` parameter check. If debug bar needed, restrict to localhost IP or specific role like administrator

**Encryption Key Storage:**
- Risk: Encryption key derived from a stored option `bluesky_settings_secret`. If WordPress options table is compromised, encryption security is compromised
- Files: `classes/BlueSky_Helpers.php` (lines 94-101)
- Current mitigation: Uses OpenSSL AES-256-CBC with random IV per encryption, key is hashed
- Recommendations: Use WordPress constant-based key instead of option (e.g., `WP_BLUESKY_SECRET`) or implement salting with site URL

## Known Issues

**API Authentication Token Lifecycle Not Fully Robust:**
- Symptoms: Access token stored in transient for 1 hour, refresh token for 1 week. If refresh token expires, user must re-authenticate
- Files: `classes/BlueSky_API_Handler.php` (lines 158-159)
- Trigger: User inactive for > 7 days, then tries to use plugin
- Workaround: Admin can re-authenticate via settings page

**Incomplete Fallback for Profile Card:**
- Symptoms: When profile fetch fails, displays error message instead of cached profile
- Files: `classes/BlueSky_Render_Front.php` (lines 819-826), TODO comment present
- Trigger: BlueSky API downtime or network failure
- Workaround: None - profile card is blank during API outage

**YouTube Embed Thumbnail Generation:**
- Symptoms: Constructs YouTube thumbnail URL directly assuming maxresdefault.jpg always exists, falls back to default if thumbnail doesn't generate
- Files: `classes/BlueSky_Render_Front.php` (lines 381-390)
- Trigger: Some videos don't have maxresdefault available
- Workaround: Plugin handles gracefully by showing fallback, but could improve by trying other quality levels

**Transient-Based Sessions at Risk:**
- Symptoms: Access and refresh tokens stored as transients which WordPress can delete if object cache flushes or expiration occurs
- Files: `classes/BlueSky_API_Handler.php` (lines 158-159, 64-66)
- Trigger: Cache flush operations, long-running requests during token expiration window
- Workaround: Transients fall back to false and trigger re-authentication

## Performance Bottlenecks

**Synchronous API Calls During Post Publishing:**
- Problem: When WordPress post transitions to "publish" status, plugin immediately calls BlueSky API to syndicate post
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 2239-2300+)
- Cause: Synchronous `wp_remote_post()` in `transition_post_status` hook blocks post save UI until API responds
- Improvement path: Move syndication to async action queue (WP-Cron or Background Process) to prevent UI blocking

**Unbounded API Response Processing:**
- Problem: Feed fetches 100 items from API when filtering replies, then processes all in memory
- Files: `classes/BlueSky_API_Handler.php` (line 268)
- Cause: Fetching 100 to filter down to requested limit wastes bandwidth and processing
- Improvement path: Use API pagination with filters applied server-side, or implement smarter client-side filtering

**CSS/JS Enqueued Inline Without Versioning:**
- Problem: Styles are enqueued for every shortcode render in template, not optimized
- Files: `classes/BlueSky_Render_Front.php` (lines 319-335)
- Cause: Post-level style enqueuing instead of page-level detection
- Improvement path: Hook into template detection to enqueue once per page load

## Fragile Areas

**Post Metabox AJAX Handlers:**
- Files: `classes/BlueSky_Post_Metabox.php` (lines 225-280+)
- Why fragile: Multiple AJAX actions (`_get_post_url`, `_get_bluesky_post`, `_get_discussion`) rely on shared nonce and permission checks. Each has slightly different permission model. Changes to one permission check could break others silently
- Safe modification: Extract common permission/nonce logic to helper method. Add logging to each AJAX endpoint. Write integration tests covering all three flows
- Test coverage: No test files present for AJAX handlers

**Settings Sanitization Logic:**
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 200-376)
- Why fragile: Complex nested array sanitization with multiple conditional branches. Cache clearing tied to specific setting comparisons. One missed setting in comparison breaks cache invalidation silently
- Safe modification: Create separate Settings model class with strict property definitions. Add unit tests for each setting type
- Test coverage: No test files for settings validation

**Discussion Thread Rendering:**
- Files: `classes/BlueSky_Discussion_Display.php` (lines 600-1180)
- Why fragile: Deeply nested HTML generation with conditional media rendering. Missing media type handling could cause undefined array access
- Safe modification: Use template files instead of PHP strings. Add safety checks for all array keys before use
- Test coverage: No tests for thread rendering logic

## Scaling Limits

**Token Expiration During Large Batch Operations:**
- Current capacity: Access token expires in 1 hour, refresh token in 1 week
- Limit: Long-running batch operations (fetching historical posts, massive syndication) will hit token expiration mid-operation
- Scaling path: Implement token refresh mid-operation, or convert to async background jobs that can handle interruption/resumption

**Transient Storage for Caching:**
- Current capacity: Depends on WordPress database/cache backend. No limit on number of transients created
- Limit: High-traffic sites with many shortcodes could create thousands of unique transient keys, bloating database
- Scaling path: Implement time-based key rotation, limit number of cached variations (fewer layout/limit combinations), use object cache instead of database

**API Rate Limiting Not Handled:**
- Current capacity: BlueSky API has unspecified rate limits
- Limit: Multiple shortcodes on same page all fetch independently; no request deduplication or rate limit backoff
- Scaling path: Implement request queue with deduplication, add exponential backoff on 429 responses

## Test Coverage Gaps

**No Tests for AJAX Endpoints:**
- What's not tested: `ajax_fetch_bluesky_posts()`, `ajax_get_bluesky_profile()`, `ajax_refresh_discussion()`, `ajax_get_post_url()`, `ajax_get_bluesky_post()`, `ajax_get_discussion()`
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 2207-2232), `classes/BlueSky_Post_Metabox.php` (lines 225-280), `classes/BlueSky_Discussion_Display.php` (lines 573-620)
- Risk: AJAX handlers could silently fail, return malformed JSON, or expose errors to frontend without detection
- Priority: High - AJAX is core interactive feature

**No Tests for Settings Sanitization:**
- What's not tested: Complex conditional logic in `sanitize_settings()` for all input combinations
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 200-376)
- Risk: Invalid settings silently saved, breaking display or syndication without warning
- Priority: High - bad settings propagate to all pages using shortcodes

**No Tests for API Handler:**
- What's not tested: Token refresh logic, error handling, post processing, profile fetching
- Files: `classes/BlueSky_API_Handler.php`
- Risk: API integration bugs only surface in production with real BlueSky accounts
- Priority: High - core functionality

**No Tests for Post Syndication:**
- What's not tested: Syndication trigger, metabox persistence, image upload to BlueSky, content formatting
- Files: `classes/BlueSky_Plugin_Setup.php` (lines 2239+), `classes/BlueSky_Post_Metabox.php`
- Risk: Posts could be malformed when syndicated, user-visible failures only in production
- Priority: Medium - critical but less frequently tested path

**No Tests for HTML Output:**
- What's not tested: Shortcode output rendering, profile card HTML, post feed HTML, discussion thread HTML
- Files: `classes/BlueSky_Render_Front.php`, `classes/BlueSky_Discussion_Display.php`
- Risk: XSS vulnerabilities in rendering, broken HTML structure, accessibility failures not caught
- Priority: Medium - security-relevant

## Dependencies at Risk

**Outdated Prism.js Library:**
- Risk: `assets/js/prism.min.js` appears to be syntax highlighter, no version info available, potentially outdated
- Impact: If used for user-generated content display, could have XSS vulnerabilities
- Migration plan: Update to latest Prism.js, or replace with PHP syntax highlighting if not needed client-side

## Missing Critical Features

**No Logging System:**
- Problem: Errors caught silently with `is_wp_error()` checks and early returns. Admin has no visibility into what fails
- Blocks: Debugging production issues, monitoring plugin health
- Recommendation: Add structured logging to `wp-content/debug.log`, at minimum for API failures and AJAX errors

**No Admin Dashboard Widget:**
- Problem: No way to see syndication status or recent activity from WordPress dashboard
- Blocks: Quick monitoring of plugin health
- Recommendation: Add dashboard widget showing last syndication time, post count, API status

**No Bulk Syndication Tool:**
- Problem: Can only syndicate posts going forward, no way to backfill existing posts to BlueSky
- Blocks: Users migrating from other platforms can't sync past content
- Recommendation: Add admin tool to bulk-syndicate posts by date range or category

---

*Concerns audit: 2026-02-14*
