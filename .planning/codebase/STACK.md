# Technology Stack

**Analysis Date:** 2026-02-14

## Languages

**Primary:**
- PHP 7.4+ - Plugin backend, API integration, WordPress hooks and filters, data processing
- JavaScript (ES5+) - Gutenberg block implementation, admin UI interactions, frontend lightbox gallery
- CSS 3 - Styling for profile cards, posts feed, discussion display, admin interface

**Secondary:**
- HTML 5 - Template markup within PHP classes and rendered blocks

## Runtime

**Environment:**
- WordPress 5.0+ (tested up to 6.9)
- PHP 7.4 or later required

**Package Manager:**
- None - No external package managers used (npm, composer, etc.)
- No build tools or compilation pipeline required

## Frameworks

**Core:**
- WordPress 5.0+ - Plugin framework, hook system, options API, settings management
- Gutenberg - Block editor integration for posts feed and profile card blocks

**APIs & Libraries:**
- AT Protocol (ATProto) - BlueSky federation protocol for API communication
- BlueSky xRPC API - JSON-RPC API for authentication, feed operations, profile fetching

**Testing:**
- None - No test framework detected

**Build/Dev:**
- None - No build system detected

## Key Dependencies

**Critical:**
- OpenSSL PHP Extension - Encryption of stored BlueSky app password using AES-256-CBC (see `BlueSky_Helpers::bluesky_encrypt()` at `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_Helpers.php`)
- `wp_remote_post()` / `wp_remote_get()` - WordPress HTTP client for API calls
- `finfo_*()` - File type detection for image uploads

**Infrastructure:**
- WordPress Options API (`get_option()`, `update_option()`) - Configuration and settings storage
- WordPress Transients API (`get_transient()`, `set_transient()`) - Caching layer for API responses
- WordPress Meta API - Post meta for syndication status tracking

## Configuration

**Environment:**
- Configured entirely through WordPress admin settings page at `/wp-admin/options-general.php?page=bluesky-social-settings`
- No `.env` files or environment variables required
- Plugin activates with `register_activation_hook()` to create activation timestamp for syndication control

**Build:**
- No build configuration files detected
- No webpack, gulp, or other bundlers
- Assets served directly from `/assets/` directory

**Key Configuration Options Stored in Database:**
- BlueSky account handle (encrypted)
- BlueSky app password (encrypted with AES-256-CBC)
- Cache duration settings
- Display preferences (theme, layout, counters, embeds)
- Syndication settings (auto-post, no_replies, no_reposts)
- Discussion display options

## Platform Requirements

**Development:**
- PHP 7.4+ with OpenSSL extension enabled
- WordPress 5.0+ installed and running
- Access to WordPress admin panel
- Internet connection to reach BlueSky xRPC API at `https://bsky.social/xrpc/`

**Production:**
- Deployment: WordPress plugin installation in `/wp-content/plugins/`
- Server: PHP 7.4+ with OpenSSL extension, WordPress 5.0+
- Hosting must allow outbound HTTPS requests to BlueSky API
- Database access for WordPress options storage and transients

## External API Requirements

**BlueSky xRPC API (https://bsky.social/xrpc/):**
- Version: Implicit (no version specified in code, uses latest AT Protocol)
- Authentication: JWT tokens (access token with 1-hour expiry, refresh token with 1-week expiry)
- Endpoints Used:
  - `com.atproto.server.createSession` - Initial authentication
  - `com.atproto.server.refreshSession` - Token refresh
  - `app.bsky.feed.getAuthorFeed` - Fetch user's posts
  - `app.bsky.actor.getProfile` - Fetch user profile data
  - `app.bsky.feed.getPostThread` - Fetch post with replies
  - `app.bsky.feed.getPosts` - Fetch post statistics
  - `com.atproto.repo.uploadBlob` - Upload images for syndicated posts
  - `com.atproto.repo.createRecord` - Create syndicated posts

## Caching Strategy

**WordPress Transients:**
- Access tokens: 1 hour expiry (`HOUR_IN_SECONDS`)
- Refresh tokens: 1 week expiry (`WEEK_IN_SECONDS`)
- Profile cache: Configurable duration (default 1 hour)
- Posts feed cache: Configurable duration (default 1 hour)
- Discussion cache: 5 minutes expiry
- Cache keys prefixed with `bluesky_cache_{VERSION}` for version separation

**Cache Bypass:**
- Cache duration set to 0 disables caching for that resource
- Forced re-authentication available via `authenticate(true)` parameter

## Security

**Encryption:**
- BlueSky app password encrypted at rest using AES-256-CBC with OpenSSL
- Encryption key generated once per installation and stored in wp_options as hash
- IV (Initialization Vector) regenerated per encryption operation

**Token Management:**
- Access tokens stored as transients (not persistent across server restart)
- Refresh tokens stored as transients for token renewal
- DIDs (Decentralized Identifiers) stored as transients
- All tokens cleared on logout

**WordPress Security:**
- Nonce verification on logout action (`wp_verify_nonce()`)
- Option sanitization via `sanitize_settings()` callback
- `wp_kses` whitelist for SVG content in rendered posts
- Admin-only settings pages via `manage_options` capability check

---

*Stack analysis: 2026-02-14*
