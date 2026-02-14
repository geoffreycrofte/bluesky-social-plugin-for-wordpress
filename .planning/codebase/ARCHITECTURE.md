# Architecture

**Analysis Date:** 2026-02-14

## Pattern Overview

**Overall:** WordPress Plugin with Class-Based Object-Oriented Design

**Key Characteristics:**
- Modular class-based architecture following WordPress conventions
- Hook-driven initialization (WordPress actions and filters)
- Layered separation between API communication, business logic, and presentation
- Dependency injection pattern for API handler across classes
- Caching via WordPress transients for API responses
- Gutenberg blocks and traditional widgets for content integration

## Layers

**API Layer:**
- Purpose: Handle all BlueSky API authentication, requests, and token management
- Location: `classes/BlueSky_API_Handler.php`
- Contains: API endpoint calls, token refresh logic, session management, post fetching, profile fetching
- Depends on: WordPress core (wp_remote_*), BlueSky_Helpers
- Used by: All classes that need BlueSky data (Plugin_Setup, Render_Front, Discussion_Display)

**Business Logic Layer:**
- Purpose: Orchestrate plugin features, hooks, settings, and admin functionality
- Location: `classes/BlueSky_Plugin_Setup.php` (2537 lines - main coordinator), `classes/BlueSky_Admin_Actions.php`
- Contains: Hook registration, settings management, post syndication logic, AJAX handlers, admin menu setup, asset enqueuing
- Depends on: BlueSky_API_Handler, BlueSky_Helpers
- Used by: WordPress core hooks, main plugin file

**Rendering/Presentation Layer:**
- Purpose: Convert BlueSky data into HTML output for frontend display
- Location: `classes/BlueSky_Render_Front.php` (1085 lines)
- Contains: Shortcode rendering, HTML generation, CSS styling, profile card rendering, posts feed rendering
- Depends on: BlueSky_API_Handler for data fetching
- Used by: WordPress shortcodes, widgets, Gutenberg blocks, frontend

**Metadata & Editor Layer:**
- Purpose: Manage post editor integration, syndication controls, and discussion threads
- Location: `classes/BlueSky_Post_Metabox.php` (439 lines), `classes/BlueSky_Discussion_Display.php` (1337 lines)
- Contains: Post meta boxes, syndication checkboxes, post preview, discussion thread display, meta registration
- Depends on: BlueSky_API_Handler
- Used by: WordPress post editor screens

**Utility Layer:**
- Purpose: Provide helper methods for common operations
- Location: `classes/BlueSky_Helpers.php` (266 lines)
- Contains: Transient key generation, encryption/decryption, admin URL helpers, notification display
- Depends on: WordPress core
- Used by: All other classes

**Widget Layer:**
- Purpose: Register and render WordPress widgets for sidebars
- Location: `classes/widgets/BlueSky_Posts_Widget.php`, `classes/widgets/BlueSky_Profile_Widget.php`
- Contains: WP_Widget extending classes, widget output rendering
- Depends on: BlueSky_Render_Front, BlueSky_API_Handler
- Used by: WordPress widget areas

**Block Layer (Frontend):**
- Purpose: Register Gutenberg blocks for content editing
- Location: `blocks/bluesky-posts-feed.js`, `blocks/bluesky-profile-card.js`, `blocks/bluesky-pre-publish-panel.js`
- Contains: Gutenberg block registration, inspector controls, edit UI
- Depends on: WordPress Gutenberg API, server-side rendering
- Used by: Gutenberg editor

## Data Flow

**Authentication Flow:**
1. User enters BlueSky handle and app password in admin settings (`classes/BlueSky_Plugin_Setup.php` settings page)
2. `BlueSky_API_Handler->authenticate()` creates session with BlueSky API
3. Access token stored in transient (1 hour expiration), refresh token stored (1 week expiration)
4. DID (Decentralized Identifier) cached alongside tokens
5. Token refresh uses refresh token when access token expires
6. On logout, all tokens deleted via `BlueSky_Admin_Actions->handle_bluesky_logout()`

**Post Fetching Flow:**
1. Request initiated from shortcode, widget, block, or AJAX call
2. `BlueSky_Render_Front->render_bluesky_posts_list()` called with attributes
3. Calls `BlueSky_API_Handler->fetch_bluesky_posts($limit, $no_replies, $no_reposts)`
4. API handler checks cache (transient key includes filter parameters)
5. If cached, returns cached data; otherwise authenticates and fetches from BlueSky API
6. Raw feed filtered by attributes (removes replies/reposts per settings)
7. Posts processed and returned as array
8. `BlueSky_Render_Front` converts to HTML with styling/themes
9. HTML with inline styles rendered to page

**Post Syndication Flow:**
1. WordPress post transitions to "publish" status → `transition_post_status` hook fired
2. `BlueSky_Plugin_Setup->syndicate_post_to_bluesky()` triggered
3. Checks: user authenticated, syndication enabled, post not older than activation date, not marked "don't syndicate"
4. Creates BlueSky post via API with WordPress permalink, excerpt, featured image
5. Stores BlueSky URI and thread metadata in post meta
6. `BlueSky_Discussion_Display` can then display discussion thread on post

**Discussion Thread Display Flow:**
1. Syndicated post has `_bluesky_syndicated` meta set
2. Admin views post → `BlueSky_Discussion_Display->add_discussion_metabox()` adds metabox
3. Metabox displays post stats (likes, reposts, replies)
4. AJAX button triggers `ajax_refresh_discussion` to fetch latest stats
5. Frontend post → `BlueSky_Discussion_Display->add_discussion_to_content()` appends discussion thread
6. Discussion displayed via `render_discussion_thread()` with Prism syntax highlighting

**Settings Flow:**
1. Admin page registered via `BlueSky_Plugin_Setup->add_admin_menu()`
2. `BlueSky_Plugin_Setup->render_settings_page()` outputs HTML form
3. Settings saved via WordPress Settings API to `bluesky_settings` option
4. Settings cached in class instances on instantiation
5. Updates to settings require page refresh to reload option value

**State Management:**
- **Plugin Options:** Stored in WordPress `options` table under key `bluesky_settings`
- **Tokens:** Stored in WordPress transients (temporary cache) with TTL
- **Post Meta:** Syndication status, URIs, and thread data stored as post meta
- **Caching Strategy:**
  - API responses cached in transients with configurable duration (default 1 hour)
  - Cache keys include filter parameters to allow different cached versions
  - Cache invalidation on post syndication or manual refresh

## Key Abstractions

**BlueSky_API_Handler:**
- Purpose: Abstract BlueSky API communication and token management
- Examples: `classes/BlueSky_API_Handler.php`
- Pattern: Single responsibility (API communication only), dependency injection of options
- Provides: `authenticate()`, `fetch_bluesky_posts()`, `fetch_bluesky_profile()`, `logout()`

**BlueSky_Render_Front:**
- Purpose: Abstract presentation logic for posts and profiles
- Examples: `classes/BlueSky_Render_Front.php`
- Pattern: Receives data from API handler, converts to HTML, manages styling
- Provides: `render_bluesky_posts_list()`, `bluesky_profile_card_shortcode()`, `bluesky_last_posts_shortcode()`

**BlueSky_Helpers:**
- Purpose: Abstract common utility operations
- Examples: `classes/BlueSky_Helpers.php`
- Pattern: Static-like helper methods, no persistent state
- Provides: Transient key generation, encryption, admin URLs, notifications

**BlueSky_Plugin_Setup:**
- Purpose: Centralize WordPress hook registration and plugin orchestration
- Examples: `classes/BlueSky_Plugin_Setup.php`
- Pattern: Constructor registers all hooks, methods handle specific WordPress hooks
- Provides: Settings management, asset enqueuing, AJAX handlers, block/widget registration

## Entry Points

**Plugin Initialization:**
- Location: `social-integration-for-bluesky.php` (main plugin file)
- Triggers: WordPress plugin loader on activation
- Responsibilities: Define constants, require class files, instantiate main objects
- Instantiates: `BlueSky_API_Handler`, `BlueSky_Plugin_Setup`, `BlueSky_Render_Front`, `BlueSky_Admin_Actions`, `BlueSky_Post_Metabox`, `BlueSky_Discussion_Display`

**Settings Page:**
- Location: `classes/BlueSky_Plugin_Setup->render_settings_page()`
- Triggers: User navigates to BlueSky Settings in WordPress admin
- Responsibilities: Display form, handle authentication UI, show connection status

**Shortcodes:**
- `[bluesky_profile]` → `BlueSky_Render_Front->bluesky_profile_card_shortcode()`
- `[bluesky_last_posts]` → `BlueSky_Render_Front->bluesky_last_posts_shortcode()`
- Triggers: Shortcode parsing in post/page content
- Responsibilities: Parse attributes, fetch data, render HTML

**Gutenberg Blocks:**
- `bluesky-social/posts` → `BlueSky_Plugin_Setup->register_gutenberg_blocks()`
- `bluesky-social/profile` → Similar registration
- Triggers: Block insertion in Gutenberg editor
- Responsibilities: Edit UI, server-side rendering

**AJAX Endpoints:**
- `wp_ajax_fetch_bluesky_posts` → `BlueSky_Plugin_Setup->ajax_fetch_bluesky_posts()`
- `wp_ajax_get_bluesky_profile` → `BlueSky_Plugin_Setup->ajax_get_bluesky_profile()`
- `wp_ajax_refresh_bluesky_discussion` → `BlueSky_Discussion_Display->ajax_refresh_discussion()`
- Triggers: JavaScript on frontend (lazy loading, updates)
- Responsibilities: Return JSON data, handle caching

## Error Handling

**Strategy:** Graceful degradation with admin notifications

**Patterns:**
- API Handler methods return `false` on error (no exceptions thrown)
- `is_wp_error()` checks on all `wp_remote_*()` calls
- Missing credentials return early with false
- Failed authentication shows admin notice via `BlueSky_Helpers->show_admin_notice()`
- AJAX errors return JSON with error flag
- Frontend rendering falls back to empty content if data unavailable
- Missing options don't crash, use defaults

## Cross-Cutting Concerns

**Logging:** No formal logging framework. Issues visible via:
- WordPress admin notices (user-facing)
- PHP error logs (server-side)
- JavaScript console (frontend issues)

**Validation:**
- Input validation via `filter_var()` for booleans
- Output sanitization via `wp_kses()` for HTML
- Nonce verification for POST/form submissions
- Permission checks via `current_user_can()`

**Authentication:**
- BlueSky handle and app password required for all API calls
- JWT tokens (access and refresh) manage session state
- WordPress admin authentication separate (via `admin_post` hooks)
- Nonce tokens on logout forms

**Caching:**
- Transient-based caching with configurable duration
- Cache keys include parameters for different variations
- Manual refresh buttons trigger cache invalidation
- Synced posts don't use cache (fresh fetch)

---

*Architecture analysis: 2026-02-14*
