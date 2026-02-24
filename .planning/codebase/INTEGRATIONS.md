# External Integrations

**Analysis Date:** 2026-02-14

## APIs & External Services

**BlueSky Social:**
- Service: AT Protocol Federation (BlueSky social network)
- What it's used for: User authentication, fetching profile data, fetching user's posts, posting syndication, fetching post threads with discussions
  - SDK/Client: None (uses native WordPress `wp_remote_post()` and `wp_remote_get()`)
  - API Base: `https://bsky.social/xrpc/`
  - Auth: JWT tokens (access_token and refresh_token obtained via OAuth-like session creation)
  - Implementation: `BlueSky_API_Handler` class at `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_API_Handler.php`

## Data Storage

**Databases:**
- Type: WordPress wp_options table
  - Connection: WordPress native database connection
  - Storage: Plugin settings stored in `bluesky_settings` option with serialized array
  - Secondary options:
    - `bluesky_settings_secret` - Encryption key for password storage
    - `bluesky_settings_activation_date` - Timestamp of plugin activation (used to filter old posts from syndication)
  - Client: WordPress Options API (`get_option()`, `update_option()`, `add_option()`)

**WordPress Transients (Caching Layer):**
- Access tokens: `bluesky_cache_{VERSION}-access-token` (1 hour)
- Refresh tokens: `bluesky_cache_{VERSION}-refresh-token` (1 week)
- DIDs: `bluesky_cache_{VERSION}-did` (persistent until cleared)
- Profile cache: `bluesky_cache_{VERSION}-profile` (configurable, default 1 hour)
- Posts cache: `bluesky_cache_{VERSION}-posts-{limit}-{replies}-{reposts}-{layout}` (configurable, default 1 hour)
- Discussion cache: `bluesky_cache_{VERSION}-discussions-{post_uri}-{depth}-{include_media}` (5 minutes)
- Logout messages: `bluesky_logout_message` (30 seconds)

**File Storage:**
- None - No direct file storage integration
- Images are uploaded temporarily during syndication to BlueSky via blob upload, not stored locally

**Caching:**
- WordPress Transients API - See above
- No external caching service (Redis, Memcached) required

## Authentication & Identity

**Auth Provider:**
- BlueSky App Password Authentication
  - Implementation: `authenticate()` method in `BlueSky_API_Handler` at `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_API_Handler.php:48-168`
  - Approach:
    1. User provides BlueSky handle and app password in WordPress admin settings
    2. App password encrypted using AES-256-CBC (see `BlueSky_Helpers::bluesky_encrypt()`)
    3. Initial session created via `com.atproto.server.createSession` endpoint
    4. Returns JWT access token (1 hour), refresh token (1 week), and DID (Decentralized Identifier)
    5. Tokens stored in WordPress transients
    6. Subsequent requests use access token; expired tokens refreshed via `com.atproto.server.refreshSession`
  - Logout: `logout()` method clears tokens and credentials from database

## Monitoring & Observability

**Error Tracking:**
- None - No external error tracking service integrated
- WordPress `error_log()` used for exceptions (see `BlueSky_API_Handler::logout()` line 186)

**Logs:**
- WordPress debug.log (if `WP_DEBUG_LOG` enabled)
- Admin notices displayed via `add_action('admin_notices')` for encryption errors

## CI/CD & Deployment

**Hosting:**
- Self-hosted WordPress installation (any provider supporting PHP 7.4+)
- No dependency on specific hosting platform

**CI Pipeline:**
- None - No automated CI/CD detected
- Manual deployment via WordPress plugin upload or FTP

## Environment Configuration

**Required env vars:**
- None - Plugin uses WordPress admin UI for configuration

**Configuration Storage:**
- WordPress admin settings page: `/wp-admin/options-general.php?page=bluesky-social-settings`
- Single serialized array option: `bluesky_settings` containing:
  - `handle` (encrypted)
  - `app_password` (encrypted)
  - `cache_duration` (array with `total_seconds`)
  - `no_replies` (boolean)
  - `no_reposts` (boolean)
  - `styles` (array with `feed_layout`, font sizes, theme settings)
  - `display_embeds` (boolean)
  - `syndicate_posts` (boolean)
  - `include_discussions` (boolean)
  - `discussions_depth` (integer)
  - `discussions_include_media` (boolean)

**Secrets location:**
- WordPress wp_options table (encrypted field)
- Encryption key stored as option `bluesky_settings_secret` (hashed and salted)

## Webhooks & Callbacks

**Incoming:**
- None - Plugin does not accept webhooks from BlueSky

**Outgoing:**
- None - Plugin does not send webhooks to external services
- One-way pull integration only (fetches data from BlueSky, posts to BlueSky)

## WordPress Integration Points

**Hooks Used:**
- `init` - Load plugin text domain, register Gutenberg blocks
- `admin_menu` - Add settings page
- `admin_init` - Register settings and sections
- `admin_enqueue_scripts` - Load admin CSS/JS
- `wp_enqueue_scripts` - Load frontend CSS/JS
- `wp_ajax_fetch_bluesky_posts` - AJAX endpoint for fetching posts
- `wp_ajax_get_bluesky_profile` - AJAX endpoint for fetching profile
- `widgets_init` - Register custom widgets
- `transition_post_status` - Auto-syndicate posts to BlueSky on publish
- `admin_notices` - Display logout messages
- `plugin_action_links_{plugin}` - Add settings link to plugin list
- `wp_kses_allowed_html` - Allow SVG tags in post content

**Filters Used:**
- `wp_kses_allowed_html` - Extend allowed HTML to include SVG elements

**Shortcodes Registered:**
- `[bluesky_profile]` - Display BlueSky profile card
- `[bluesky_last_posts]` - Display latest BlueSky posts feed

**Custom Post Meta:**
- `bluesky_syndication_disabled` - Boolean per-post setting to disable syndication
- `bluesky_post_uri` - URI of syndicated post on BlueSky
- `bluesky_post_url` - Web URL of syndicated post on BlueSky

**Widgets Registered:**
- `BlueSky_Posts_Widget` - Widget for displaying latest posts
- `BlueSky_Profile_Widget` - Widget for displaying profile card

**Gutenberg Blocks Registered:**
- `bluesky-social/posts` - Posts feed block (server-side render)
- `bluesky-social/profile` - Profile card block (server-side render)
- `bluesky-social/pre-publish-panel` - Editor panel for syndication preview

## Image Handling

**External Image Upload:**
- Post featured images and first content images uploaded to BlueSky as blobs during syndication
- Uses `upload_image_blob()` method at `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/classes/BlueSky_API_Handler.php:390-457`
- Supports both local file paths and remote URLs
- MIME type detection using PHP `finfo_*()` functions
- Endpoint: `com.atproto.repo.uploadBlob`

## Media Embed Support

**Supported Embed Types in Feed Display:**
- Image galleries (with lightbox modal)
- Videos (with playlist and thumbnail)
- External links (with Open Graph card)
- Quoted posts (embedded records)
- Starter packs
- YouTube URLs (detected and embedded)

**Lightbox Gallery:**
- Implemented in `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/assets/js/bluesky-social-lightbox.js`
- No external library - custom vanilla JavaScript implementation
- Supports keyboard navigation and touch swipe

## Post Syndication Flow

**Trigger:**
- Hooks into WordPress `transition_post_status` when post transitions to `publish`
- Only syndicated if:
  - Plugin is activated after post creation (checks `bluesky_settings_activation_date`)
  - Syndication enabled in settings
  - Post not explicitly disabled for syndication (post meta `bluesky_syndication_disabled`)

**Syndication Process:**
1. Fetch post title, excerpt, permalink, featured image URL
2. If image available: upload as blob to BlueSky
3. Create post record with:
   - Text: title + excerpt (respecting 300 char limit)
   - Embed: external embed with thumbnail image and link card
   - Facets: link facets for URLs in text
4. Return post URI and construct web URL: `https://bsky.app/profile/{handle}/post/{rkey}`
5. Store BlueSky post URI and URL in post meta for reference

**Preview in Editor:**
- `BlueSky_Post_Metabox` class displays syndicated post preview below Gutenberg editor
- Shows post on BlueSky, discussions, and engagement counters

---

*Integration audit: 2026-02-14*
