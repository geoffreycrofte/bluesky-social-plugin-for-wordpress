# Phase 02: Codebase Refactoring - Research

**Researched:** 2026-02-17
**Domain:** WordPress PHP plugin decomposition, security hardening, PHPUnit testing, i18n
**Confidence:** HIGH (codebase read directly; patterns verified against official WP docs and community sources)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **Decomposition strategy:** Moderate grouping into ~5-6 service classes (Settings, Syndication, Display, AJAX, Cache, etc.) — not full extraction of every method into its own class
- **Includes:** Keep manual `require_once` in main plugin file — no Composer, no PSR-4, no autoloader
- **CSS backward compatibility:** CSS class names preserved as-is (`bluesky-social-integration-*`)
- **Templates:** Plugin-internal only for this phase; structure for easy theme override later (don't implement overrides now)
- **Security — godmode:** Keep debug sidebar but require `manage_options` capability for `?godmode` parameter access (admin-only)
- **Translations:** No .pot file regeneration needed; double-check all Phase 1 strings + any new strings are wrapped in WP i18n; include JS strings via wp_localize_script or wp.i18n
- **Text domain:** `social-integration-for-bluesky`

### Claude's Discretion

- DI pattern vs global/singleton for new service classes (pick what makes testing easier while keeping it simple)
- Whether Plugin_Setup becomes thin wrapper delegating to services, or gets replaced entirely (pick least risky)
- Template structure: plain PHP partials vs method-based renderers (pick cleanest for existing patterns)
- Test framework: WP PHPUnit with wp-env vs standalone PHPUnit with mocks (pick lightest effective setup)
- Test priority ordering: API/auth vs syndication vs settings (pick based on risk/complexity)
- Test isolation: mocks only vs mocks + optional integration tests (pick what's practical)
- Public AJAX nonce strategy vs rate limiting (pick based on WP security best practices)
- Syndication POST validation strictness (pick what prevents most real-world issues)

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

## Summary

This phase decomposes three monolithic classes into focused service classes, fixes identified security vulnerabilities, adds PHPUnit test coverage for critical paths, and audits all user-facing strings for proper i18n wrapping. No new features — pure structural improvement.

The codebase has been read directly, giving HIGH confidence in all findings about its current state. The primary challenge is Plugin_Setup.php which has grown to 3421 lines (updated count from HEAD, post Phase 1 additions — higher than the 2537 referenced in pre-Phase-1 requirements) and performs at least six distinct concerns: settings registration, admin rendering (870+ lines of inline HTML), AJAX handling, asset enqueuing, syndication orchestration, and block/widget registration.

The recommended approach is the "thin coordinator" pattern: Plugin_Setup retains hook registration but delegates to new service classes, which can be extracted incrementally with smoke tests between each extraction. This is less risky than a full replacement and preserves all WordPress hook registrations in one place.

For testing, standalone PHPUnit with Brain Monkey (mocks WP functions without needing a real WordPress install) is the lightest effective setup — no Docker, no wp-env, no database required for unit tests. This fits the constraint of no Composer autoloader for the plugin itself (Brain Monkey is a dev-only tool). Brain Monkey is actively maintained and used by Yoast, WP Engine, and other established plugin developers.

**Primary recommendation:** Extract service classes one concern at a time (Settings → Syndication → AJAX → Assets → Blocks), keep Plugin_Setup as thin coordinator registering hooks that delegate to services, use Brain Monkey for standalone unit tests, and pass translated strings to JS via wp_localize_script (consistent with existing pattern in codebase).

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | 9.x | PHP unit testing | WP core uses PHPUnit; 9.x compatible with PHP 7.4+ minimum |
| Brain Monkey | 2.x | Mock WP functions without WP install | Lets you test `get_option()`, `add_action()` etc. in isolation |
| Mockery | 1.x | Object mocking (Brain Monkey dependency) | Required by Brain Monkey; standard PHP mocking library |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Yoast PHPUnit Polyfills | 1.x | PHPUnit version compatibility shims | When targeting PHPUnit 7-10 from a single test suite |
| WP_Mock (10up) | Alt to Brain Monkey | Mock WP functions | When Brain Monkey is too heavy; simpler API but less flexible |

### No Autoloader — Manual Setup

Since the project uses no Composer, tests will need a manual bootstrap that:
1. Defines `ABSPATH`, `BLUESKY_PLUGIN_OPTIONS`, and other plugin constants
2. Requires the Brain Monkey autoload (from a dev-only vendor dir)
3. Requires class files under test via `require_once`

PHPUnit itself can be installed globally via Homebrew or as a PHAR — no Composer needed in the plugin directory.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Brain Monkey | WP PHPUnit full integration | Full WP integration needs Docker/wp-env — heavier than needed for unit tests |
| Brain Monkey | WP_Mock | WP_Mock is simpler but less feature-rich for hook assertion |
| wp_localize_script (JS i18n) | wp_set_script_translations + wp.i18n | wp_set_script_translations requires .pot compilation + JSON files per locale — heavier infra; wp_localize_script is consistent with existing codebase pattern |

**Installation (PHPUnit as PHAR, Brain Monkey via separate composer in /tests):**
```bash
# Download PHPUnit 9 phar (compatible with PHP 7.4+)
curl -L https://phar.phpunit.de/phpunit-9.phar -o phpunit.phar
chmod +x phpunit.phar

# Brain Monkey as dev dependency (separate from plugin, lives in /tests)
cd tests && composer require brain-wp/brain-monkey:^2
```

---

## Architecture Patterns

### Current State (What We're Refactoring)

```
classes/
├── BlueSky_Plugin_Setup.php     3421 lines — ALL concerns mixed together
│   ├── Hook registration (~70 lines in init_hooks)
│   ├── Settings registration + 30+ field render methods (~1500 lines)
│   ├── Admin page render_settings_page (~870 lines inline HTML)
│   ├── AJAX handlers (6 methods: fetch_posts, get_profile, async_posts, async_profile, async_auth, legacy)
│   ├── Asset enqueuing (admin + frontend)
│   ├── Syndication orchestration (syndicate_post_to_bluesky + syndicate_post_multi_account)
│   ├── Block registration + block render callbacks
│   └── Widget registration
├── BlueSky_Render_Front.php     1233 lines — rendering + some cache logic
│   ├── Shortcode handlers (2 methods)
│   ├── render_bluesky_posts_list (600+ lines of inline HTML)
│   ├── render_bluesky_profile_card (200+ lines)
│   └── Skeleton/async placeholder rendering
└── BlueSky_Discussion_Display.php  1416 lines — mixed concerns
    ├── Metabox registration + rendering
    ├── Admin AJAX handlers (refresh, unlink)
    ├── Frontend discussion injection (add_discussion_to_content)
    ├── Discussion rendering (stats, thread, replies)
    ├── Frontend script enqueuing
    └── Cache management
```

### Recommended Target Structure

```
classes/
├── BlueSky_Plugin_Setup.php         Thin coordinator — registers hooks only, delegates to services
├── BlueSky_API_Handler.php          Unchanged
├── BlueSky_Account_Manager.php      Unchanged
├── BlueSky_Helpers.php              Minor additions (normalize_bluesky_handle moved here)
├── BlueSky_Admin_Actions.php        Unchanged
├── BlueSky_Post_Metabox.php         Unchanged
│
├── [NEW] BlueSky_Settings_Service.php
│       register_settings(), add_settings_fields(), sanitize_settings()
│       Field render callbacks (all 20+ render_*_field methods)
│       settings_section_callback(), customization_section_callback(), discussions_section_callback()
│       handle_account_actions()
│       display_cache_status(), get_transient_expiration_time(), format_time_remaining()
│       render_settings_page() — the 870-line HTML page render
│       display_bluesky_logout_message()
│       add_admin_menu(), add_plugin_action_links()
│
├── [NEW] BlueSky_Syndication_Service.php
│       syndicate_post_to_bluesky() (transition_post_status callback)
│       syndicate_post_multi_account()
│       on_plugin_activation()
│
├── [NEW] BlueSky_AJAX_Service.php
│       ajax_fetch_bluesky_posts() (legacy, no nonce — fix applied here)
│       ajax_get_bluesky_profile() (legacy, no nonce — fix applied here)
│       ajax_async_posts() (has nonce)
│       ajax_async_profile() (has nonce)
│       ajax_async_auth() (has nonce, admin only)
│       clear_content_transients()
│
├── [NEW] BlueSky_Assets_Service.php
│       admin_enqueue_scripts()
│       frontend_enqueue_scripts()
│
├── [NEW] BlueSky_Blocks_Service.php
│       register_gutenberg_blocks()
│       register_widgets()
│       bluesky_profile_block_render()
│       bluesky_posts_block_render()
│       load_plugin_textdomain()
│
├── BlueSky_Render_Front.php         Keep as-is (1233 lines, focused on presentation)
│   Possible internal split: extract inline HTML into templates/partials
│
└── BlueSky_Discussion_Display.php   Split into 3 focused classes:
    ├── [NEW] BlueSky_Discussion_Metabox.php    (~350 lines)
    │         add_discussion_metabox(), render_discussion_metabox()
    │         enqueue_admin_scripts(), ajax_refresh_discussion(), ajax_unlink_discussion()
    ├── [NEW] BlueSky_Discussion_Renderer.php   (~400 lines)
    │         render_post_stats(), render_discussion_thread(), render_reply()
    │         render_reply_media(), time_ago(), fetch_and_render_discussion()
    │         get_discussion_html()
    └── [NEW] BlueSky_Discussion_Frontend.php   (~350 lines)
              add_discussion_to_content(), build_frontend_discussion()
              render_frontend_discussion(), get_frontend_discussion_html()
              render_frontend_thread(), render_frontend_reply()
              enqueue_frontend_scripts(), clear_discussion_caches()
```

### Pattern 1: Thin Coordinator (Recommended for Plugin_Setup)

**What:** Plugin_Setup retains hook registration (`init_hooks()`) but every callback delegates to an injected service. No business logic lives in Plugin_Setup.

**When to use:** When the monolith owns many hooks but logic is extractable — this is the least-risky refactor because hook registration order is preserved.

**Example:**
```php
// BlueSky_Plugin_Setup.php — after refactor
class BlueSky_Plugin_Setup {
    private $settings;
    private $ajax;
    private $syndication;
    private $assets;
    private $blocks;
    private $render_front;

    public function __construct(
        BlueSky_API_Handler $api_handler,
        BlueSky_Account_Manager $account_manager
    ) {
        $this->settings    = new BlueSky_Settings_Service($api_handler, $account_manager);
        $this->ajax        = new BlueSky_AJAX_Service($api_handler, $account_manager);
        $this->syndication = new BlueSky_Syndication_Service($api_handler, $account_manager);
        $this->assets      = new BlueSky_Assets_Service();
        $this->blocks      = new BlueSky_Blocks_Service($api_handler, $account_manager);
        $this->render_front = new BlueSky_Render_Front($api_handler);
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu',          [$this->settings, 'add_admin_menu']);
        add_action('admin_init',          [$this->settings, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this->assets, 'admin_enqueue_scripts']);
        // ... all other hooks delegating to services
    }
}
```

**Why this is safest:** WordPress hook registration in one place. No hook is accidentally skipped. Services can be tested in isolation without WordPress.

### Pattern 2: Service Class DI Pattern (for new service classes)

**What:** Service classes receive dependencies via constructor. They store options in `$this->options` at construction time — they do NOT call `get_option()` inside individual methods.

**When to use:** All new service classes.

**Example:**
```php
class BlueSky_AJAX_Service {
    private $api_handler;
    private $account_manager;
    private $options;

    public function __construct(
        BlueSky_API_Handler $api_handler,
        BlueSky_Account_Manager $account_manager
    ) {
        $this->api_handler     = $api_handler;
        $this->account_manager = $account_manager;
        $this->options         = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    }

    public function ajax_async_posts() {
        check_ajax_referer('bluesky_async_nonce', 'nonce');
        // use $this->api_handler, $this->options — no fresh get_option() calls
    }
}
```

### Pattern 3: Template Files as PHP Partials (for render_settings_page)

**What:** Large inline HTML in methods extracted to `templates/` files loaded via `include`. Method calls `ob_start()`, includes partial, returns `ob_get_clean()`.

**When to use:** When a method contains more than 50 lines of HTML-heavy output. Keeps method logic readable.

**Example:**
```php
// BlueSky_Settings_Service.php
public function render_settings_page() {
    $this->handle_account_actions();
    $options = $this->options;
    ob_start();
    include BLUESKY_PLUGIN_DIRECTORY . 'templates/admin/settings-page.php';
    echo ob_get_clean();
}
```

**Note:** Per locked decisions, templates are plugin-internal only. Structure for overridability (use a helper that checks theme dir first, plugin dir second) but do not implement theme override now.

### Anti-Patterns to Avoid

- **Moving hook registration into service constructors:** Services register their own hooks in constructor. This breaks expected order and mixes concerns. Only Plugin_Setup registers hooks.
- **Removing nopriv AJAX without replacement:** The `fetch_bluesky_posts` and `get_bluesky_profile` legacy endpoints need nonce protection added, but they are still needed as `nopriv` (public visitors use them). Do not remove the `nopriv` registration; add `check_ajax_referer()` to the handler body.
- **Instantiating services inside hook callbacks:** Services should be constructed at plugin load time, not inside hook callbacks.
- **Copy-paste extraction without updating $this references:** Methods reference `$this->helpers`, `$this->options` from Plugin_Setup. These don't exist in the new class until you add the constructor with correct properties.

---

## Specific Findings: Security Vulnerabilities

### 1. Legacy AJAX Endpoints (No Nonce)

**Location:** `BlueSky_Plugin_Setup::ajax_fetch_bluesky_posts()` (line 2724) and `ajax_get_bluesky_profile()` (line 2740)

**Current state:** Both are registered `nopriv` (public access) with no `check_ajax_referer()` call. The newer async equivalents (`ajax_async_posts`, `ajax_async_profile`) already have nonce checks via `check_ajax_referer('bluesky_async_nonce', 'nonce')`. The nonce is localized to the frontend via `blueskyAsync.nonce`.

**Fix:** These endpoints must remain `nopriv` (public visitors use them). Add nonce verification:
```php
public function ajax_fetch_bluesky_posts() {
    check_ajax_referer('bluesky_async_nonce', 'nonce'); // ADD THIS
    $limit = $this->options['posts_limit'] ?? 5;
    // ...
}
```

**WP Security best practice (HIGH confidence from official docs):** For `nopriv` endpoints, nonces are the primary CSRF defense. Guest nonces share user ID 0, so they don't provide per-user security, but they do confirm the request originated from your site's pages.

### 2. Debug Sidebar (godmode parameter)

**Location:** `BlueSky_Plugin_Setup::render_settings_page()` (line 2573)

**Current condition:**
```php
if (isset($_GET["godmode"]) || defined("WP_DEBUG") || defined("WP_DEBUG_DISPLAY")) {
```

**Decision (locked):** Keep the debug sidebar but require `manage_options` for `?godmode` access.

**Fix:**
```php
if (
    (isset($_GET["godmode"]) && current_user_can("manage_options")) ||
    defined("WP_DEBUG") ||
    defined("WP_DEBUG_DISPLAY")
) {
```

The settings page already requires `manage_options` to reach, but the explicit check clarifies intent and guards against future code path changes.

### 3. Syndication POST Data Validation

**Location:** `BlueSky_Plugin_Setup::syndicate_post_to_bluesky()` (lines 2924-2927)

**Current state:** Reads `$_POST["bluesky_dont_syndicate"]` directly during `transition_post_status` hook. This is WordPress core calling the hook during post save where `$_POST` contains form data. The `=== "1"` comparison is already strict.

**Recommended fix (Claude's discretion — strict):** Sanitize before comparison:
```php
$dont_syndicate_post = isset($_POST["bluesky_dont_syndicate"])
    ? sanitize_text_field(wp_unslash($_POST["bluesky_dont_syndicate"]))
    : "0";
if ($dont_syndicate || $dont_syndicate_post === "1") {
    return;
}
```

Also: add `current_user_can('edit_post', $post_id)` check before proceeding with syndication to ensure only authorized users can trigger it via form submission.

---

## Specific Findings: Method Extraction Map

### BlueSky_Plugin_Setup.php — All Methods by Extraction Target

| Method | Approx Lines | Extract To |
|--------|-------------|-----------|
| `on_plugin_activation` | 58-65 | BlueSky_Syndication_Service |
| `init_hooks` | 70-142 | Stays in Plugin_Setup (thin coordinator) |
| `load_plugin_textdomain` | 147-153 | BlueSky_Blocks_Service |
| `add_plugin_action_links` | 159-170 | BlueSky_Settings_Service |
| `add_admin_menu` | 175-187 | BlueSky_Settings_Service |
| `register_settings` | 192-253 | BlueSky_Settings_Service |
| `sanitize_settings` | 260-484 | BlueSky_Settings_Service |
| `normalize_bluesky_handle` | 495-511 | Move to BlueSky_Helpers as public static |
| `add_settings_fields` | 516-683 | BlueSky_Settings_Service |
| `settings_section_callback` | 684-693 | BlueSky_Settings_Service |
| `customization_section_callback` | 699-708 | BlueSky_Settings_Service |
| `discussions_section_callback` | 714-728 | BlueSky_Settings_Service |
| All `render_*_field` methods | 729-1399 | BlueSky_Settings_Service (~20 methods) |
| `display_cache_status` | 1400-1552 | BlueSky_Settings_Service |
| `get_transient_expiration_time` | 1553-1568 | BlueSky_Settings_Service |
| `format_time_remaining` | 1569-1643 | BlueSky_Settings_Service |
| `handle_account_actions` | 1644-1761 | BlueSky_Settings_Service |
| `render_settings_page` | 1762-2631 | BlueSky_Settings_Service (870+ lines) |
| `admin_enqueue_scripts` | 2636-2687 | BlueSky_Assets_Service |
| `frontend_enqueue_scripts` | 2692-2719 | BlueSky_Assets_Service |
| `ajax_fetch_bluesky_posts` | 2724-2735 | BlueSky_AJAX_Service (+ add nonce) |
| `ajax_get_bluesky_profile` | 2740-2750 | BlueSky_AJAX_Service (+ add nonce) |
| `ajax_async_posts` | 2755-2788 | BlueSky_AJAX_Service |
| `ajax_async_profile` | 2793-2826 | BlueSky_AJAX_Service |
| `ajax_async_auth` | 2831-2862 | BlueSky_AJAX_Service |
| `clear_content_transients` | 2868-2887 | BlueSky_AJAX_Service |
| `syndicate_post_to_bluesky` | 2893-3002 | BlueSky_Syndication_Service |
| `syndicate_post_multi_account` | 3010-3183 | BlueSky_Syndication_Service |
| `register_widgets` | 3188-3192 | BlueSky_Blocks_Service |
| `register_gutenberg_blocks` | 3197-3357 | BlueSky_Blocks_Service |
| `bluesky_profile_block_render` | 3371-3383 | BlueSky_Blocks_Service |
| `bluesky_posts_block_render` | 3397-3401 | BlueSky_Blocks_Service |
| `display_bluesky_logout_message` | 3408-3419 | BlueSky_Settings_Service |

**Observation:** BlueSky_Settings_Service will be the largest new class (~2300 lines), but has a clear single responsibility: all admin settings page concerns. The 870-line `render_settings_page` is the main candidate for template partial extraction within that class.

### BlueSky_Render_Front.php — Keep or Split?

**Recommendation:** Keep as one file for this phase. The class is already focused on presentation. The main improvement within the file: extract the ~550-line `render_bluesky_posts_list` HTML body and ~200-line `render_bluesky_profile_card` HTML body into `templates/frontend/` partials. The method becomes an orchestrator that prepares data and includes the partial.

### BlueSky_Discussion_Display.php — Split into 3

| New Class | Responsibility | Approx Lines |
|-----------|---------------|-------------|
| BlueSky_Discussion_Metabox | Admin metabox + admin AJAX | ~350 |
| BlueSky_Discussion_Renderer | Pure HTML rendering (stats, thread, replies) | ~400 |
| BlueSky_Discussion_Frontend | Frontend content injection + frontend scripts | ~350 |

The Renderer class is stateless rendering — easiest to test. Metabox and Frontend need nonce/cap checks.

**Key issue in Discussion_Display (fix during split):** The constructor calls `new BlueSky_Account_Manager()` internally (`$this->account_manager = new BlueSky_Account_Manager()`). During the split, update to accept it via constructor parameter for testability.

---

## Specific Findings: i18n Audit

### PHP Strings Missing i18n (confirmed by direct code reading)

| Location | String | Fix |
|----------|--------|-----|
| `Plugin_Setup.php:2732` | `"Could not fetch posts"` | Wrap in `__()` |
| `Plugin_Setup.php:2747` | `"Could not fetch profile"` | Wrap in `__()` |
| `Plugin_Setup.php:2836` | `"Unauthorized"` | Wrap in `__()` |
| `Discussion_Display.php:653` | `"Invalid permissions"` | Wrap in `__()` |
| `Discussion_Display.php:659` | `"No Bluesky post information found"` | Wrap in `__()` |
| `Discussion_Display.php:685` | `"Invalid permissions"` | Wrap in `__()` |
| `Discussion_Display.php:210` | `"Invalid Bluesky post data."` | Wrap in `__()` |
| `Post_Metabox.php:355` | `"Invalid nonce"` | Wrap in `__()` |
| `Post_Metabox.php:386` | `"Invalid nonce"` | Wrap in `__()` |

Note: These are strings passed to `wp_send_json_error()` — they're API responses consumed by JS. While not directly shown as translated text to users today, they should be translatable for consistency and future use.

### JS Strings Missing i18n (confirmed by direct code reading)

**File:** `assets/js/bluesky-async-loader.js` — all user-visible strings are hardcoded English:

- `"Connection to BlueSky failed. Please check your credentials."`
- `"Handle or app password is not configured."`
- `"Could not reach BlueSky servers: ..."`
- `"BlueSky rate limit exceeded. Please wait a few minutes before trying again."`
- `"Resets at ..."`
- `"BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA."`
- `"This BlueSky account has been taken down."`
- `"Invalid handle or password. Please check your credentials."`
- `"Connection to BlueSky successful!"`
- `"Log out from this account"`
- `"Could not check connection status."`
- `"Unable to load Bluesky content."`
- `"Connection failed: ..."`

**Fix approach — extend existing blueskyAsync object with i18n sub-object:**

```php
// In BlueSky_Assets_Service::admin_enqueue_scripts()
wp_localize_script('bluesky-async-loader', 'blueskyAsync', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('bluesky_async_nonce'),
    'i18n'    => [
        'connectionFailed'      => __('Connection to BlueSky failed. Please check your credentials.', 'social-integration-for-bluesky'),
        'missingCredentials'    => __('Handle or app password is not configured.', 'social-integration-for-bluesky'),
        'networkError'          => __('Could not reach BlueSky servers:', 'social-integration-for-bluesky'),
        'rateLimitExceeded'     => __('BlueSky rate limit exceeded. Please wait a few minutes before trying again.', 'social-integration-for-bluesky'),
        'rateLimitResetsAt'     => __('Resets at', 'social-integration-for-bluesky'),
        'authFactorRequired'    => __('BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA.', 'social-integration-for-bluesky'),
        'accountTakedown'       => __('This BlueSky account has been taken down.', 'social-integration-for-bluesky'),
        'invalidCredentials'    => __('Invalid handle or password. Please check your credentials.', 'social-integration-for-bluesky'),
        'connectionSuccess'     => __('Connection to BlueSky successful!', 'social-integration-for-bluesky'),
        'logoutLink'            => __('Log out from this account', 'social-integration-for-bluesky'),
        'connectionCheckFailed' => __('Could not check connection status.', 'social-integration-for-bluesky'),
        'contentLoadFailed'     => __('Unable to load Bluesky content.', 'social-integration-for-bluesky'),
        'connectionFallback'    => __('Connection failed:', 'social-integration-for-bluesky'),
    ],
]);
```

Then in JS: replace hardcoded strings with `blueskyAsync.i18n.connectionFailed` etc.

**Why wp_localize_script over wp_set_script_translations:** `wp_set_script_translations` requires `.pot` compilation + locale-specific JSON files created by translators, plus `wp-i18n` as a script dependency. The user decision is no .pot regeneration this phase, and the existing codebase uses `wp_localize_script` throughout for both admin and frontend scripts. Extending the existing `blueskyAsync` object with an `i18n` sub-key is consistent with the codebase pattern and zero additional infrastructure.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WP function mocking in tests | Custom stubs for `get_option`, `add_action`, etc. | Brain Monkey | Brain Monkey handles all WP hooks + functions; edge cases abound |
| PHP function patching | Custom workarounds | Brain Monkey + Patchwork | Patchwork (Brain Monkey's dep) handles function patching safely |
| PHPUnit compatibility shims | Custom base test class | Yoast PHPUnit Polyfills | Polyfills handle PHPUnit 7-10 API changes correctly |
| Nonce validation | Custom token system | `check_ajax_referer()` | WordPress nonces handle replay attacks and token lifecycle |
| String translation in JS | Custom i18n lookup | `wp_localize_script` with translated strings | Consistent with existing codebase; no extra infra |
| Template loading with fallback | Custom locate logic | WordPress `locate_template()` helper pattern | Built-in theme override mechanism; structure the code to use it later |

**Key insight:** The main "don't hand-roll" risk in this phase is building custom test infrastructure. Brain Monkey + PHPUnit covers ~95% of what's needed without a WordPress install.

---

## Common Pitfalls

### Pitfall 1: Hook Registration Order Changes
**What goes wrong:** Moving hook registrations from `init_hooks()` to service class constructors changes the order hooks are registered. WordPress processes hooks in registration order at the same priority. Certain functionality depends on setup order — e.g., `register_settings` must fire before `sanitize_callback` is invoked.
**Why it happens:** Natural impulse is to have each service register its own hooks in its constructor.
**How to avoid:** Keep ALL `add_action` / `add_filter` calls in Plugin_Setup's `init_hooks()`. Services expose public methods; Plugin_Setup registers them as callbacks. Services never call `add_action` themselves.
**Warning signs:** Settings save but don't sanitize; AJAX handlers return 400 bad request; admin menu appears in wrong order.

### Pitfall 2: $this->options Stale After Settings Save
**What goes wrong:** Service classes capture `$this->options = get_option(...)` in constructor. After `sanitize_settings()` saves new options, other service instances still hold the old values.
**Why it happens:** WordPress request cycle: construction happens at plugin load time; settings save happens during `admin_init`. The next page load gets fresh options.
**How to avoid:** This is the correct WordPress behavior — options update takes effect on next request. Do not try to refresh options mid-request. Document this clearly in comments.
**Warning signs:** Settings appear to not save until second page refresh (which is actually correct — first refresh IS after save).

### Pitfall 3: Method Still References $this When Extracted
**What goes wrong:** Extracting a method to a new service class fails because it calls `$this->helpers`, `$this->options`, `$this->api_handler` which no longer exist in the new class.
**Why it happens:** Copy-paste extraction without updating all internal references.
**How to avoid:** Create the full constructor with all dependencies before moving any methods. Run PHP syntax check after each extraction. Use grep to find dangling `$this->` references after copy.
**Warning signs:** PHP fatal error "Undefined property"; white screen of death on plugin load.

### Pitfall 4: normalize_bluesky_handle Duplication
**What goes wrong:** `normalize_bluesky_handle()` is a private method of Plugin_Setup used in both `sanitize_settings()` and the new account add logic. When extracted to Settings_Service, Account_Manager's `add_account()` also needs it.
**Why it happens:** The method is logically a utility that should live in Helpers, not Settings.
**How to avoid:** Move `normalize_bluesky_handle()` to `BlueSky_Helpers` as a `public static` method during this phase. Update all call sites. Account_Manager can then also use it.

### Pitfall 5: Discussion_Display Instantiates Account_Manager Internally
**What goes wrong:** `BlueSky_Discussion_Display::__construct()` calls `new BlueSky_Account_Manager()` directly rather than receiving it via DI. This creates a second Account_Manager instance and makes the class untestable.
**Why it happens:** The class was added in Phase 1 without full DI consideration.
**How to avoid:** During the split, update the new Discussion class constructors to accept `BlueSky_Account_Manager` as a parameter, matching the Plugin_Setup pattern.

### Pitfall 6: Test Bootstrap Requires Real WordPress
**What goes wrong:** Test bootstrap tries to load `wp-load.php`, pulling in actual WordPress, needing a database, failing in CI or on dev machines without WP installed.
**Why it happens:** Copying WP integration test patterns instead of unit test patterns.
**How to avoid:** Brain Monkey unit test bootstrap loads WITHOUT WordPress. Define constants manually:
```php
// tests/bootstrap.php
define('ABSPATH', '/tmp/');
define('BLUESKY_PLUGIN_OPTIONS', 'bluesky_settings');
define('BLUESKY_PLUGIN_VERSION', '1.5.0');
require_once __DIR__ . '/vendor/autoload.php';
// Brain Monkey stubs WP functions automatically via Monkey\setUp() in each test
```

### Pitfall 7: Render Methods Untestable Due to Inline HTML
**What goes wrong:** Methods that use `ob_start()` / `ob_get_clean()` combined with inline PHP/HTML are hard to assert on — you get one big HTML string.
**Why it happens:** Natural pattern for HTML generation in WordPress plugins.
**How to avoid:** Extract data preparation from HTML generation. Test the data layer (are correct variables passed, are correct conditions evaluated?) separately from HTML output. For HTML output tests, use simple `assertStringContainsString()` assertions on key markers.

---

## Code Examples

### Recommended Test Structure (Brain Monkey, no WP install)

```php
// tests/unit/BlueSky_AJAX_Service_Test.php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BlueSky_AJAX_Service_Test extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_ajax_async_posts_verifies_nonce() {
        // Arrange: stub WP functions
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('bluesky_async_nonce', 'nonce');
        Functions\expect('sanitize_text_field')->andReturnFirstArg();
        Functions\expect('wp_unslash')->andReturnFirstArg();
        Functions\expect('get_option')->andReturn([]);
        Functions\expect('wp_send_json_success')->once();

        // Act
        $_POST['nonce'] = 'test_nonce';
        $_POST['params'] = '{"theme":"system"}';

        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_manager = $this->createMock(BlueSky_Account_Manager::class);
        $service = new BlueSky_AJAX_Service($mock_api, $mock_manager);
        $service->ajax_async_posts();
    }

    public function test_ajax_async_auth_rejects_non_admin() {
        Functions\expect('check_ajax_referer')->once();
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);
        Functions\expect('wp_send_json_error')->once()->with('Unauthorized');
        Functions\expect('get_option')->andReturn([]);

        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_manager = $this->createMock(BlueSky_Account_Manager::class);
        $service = new BlueSky_AJAX_Service($mock_api, $mock_manager);
        $service->ajax_async_auth();
    }
}
```

### Recommended PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/unit</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">classes</directory>
        </include>
    </coverage>
</phpunit>
```

### Thin Coordinator Hook Registration

```php
// Plugin_Setup after refactor — registers hooks only, no logic
private function init_hooks() {
    // Internationalization
    add_action('init', [$this->blocks, 'load_plugin_textdomain']);
    add_filter('plugin_action_links_' . BLUESKY_PLUGIN_BASENAME, [$this->settings, 'add_plugin_action_links']);

    // Admin settings
    add_action('admin_menu',    [$this->settings, 'add_admin_menu']);
    add_action('admin_init',    [$this->settings, 'register_settings']);
    add_action('admin_notices', [$this->settings, 'display_bluesky_logout_message']);

    // Assets
    add_action('admin_enqueue_scripts', [$this->assets, 'admin_enqueue_scripts']);
    add_action('wp_enqueue_scripts',    [$this->assets, 'frontend_enqueue_scripts']);

    // Public AJAX (nonce check applied during extraction)
    add_action('wp_ajax_fetch_bluesky_posts',         [$this->ajax, 'ajax_fetch_bluesky_posts']);
    add_action('wp_ajax_nopriv_fetch_bluesky_posts',  [$this->ajax, 'ajax_fetch_bluesky_posts']);
    add_action('wp_ajax_get_bluesky_profile',         [$this->ajax, 'ajax_get_bluesky_profile']);
    add_action('wp_ajax_nopriv_get_bluesky_profile',  [$this->ajax, 'ajax_get_bluesky_profile']);
    add_action('wp_ajax_bluesky_async_posts',         [$this->ajax, 'ajax_async_posts']);
    add_action('wp_ajax_nopriv_bluesky_async_posts',  [$this->ajax, 'ajax_async_posts']);
    add_action('wp_ajax_bluesky_async_profile',       [$this->ajax, 'ajax_async_profile']);
    add_action('wp_ajax_nopriv_bluesky_async_profile',[$this->ajax, 'ajax_async_profile']);
    add_action('wp_ajax_bluesky_async_auth',          [$this->ajax, 'ajax_async_auth']);

    // Syndication
    register_activation_hook(BLUESKY_PLUGIN_FILE, [$this->syndication, 'on_plugin_activation']);
    add_action('transition_post_status', [$this->syndication, 'syndicate_post_to_bluesky'], 10, 3);

    // Blocks + Widgets
    add_action('widgets_init', [$this->blocks, 'register_widgets']);
    add_action('init',         [$this->blocks, 'register_gutenberg_blocks']);

    // Frontend rendering
    add_filter('wp_kses_allowed_html', [$this->render_front, 'allow_svg_tags'], 10, 2);
    add_shortcode('bluesky_profile',    [$this->render_front, 'bluesky_profile_card_shortcode']);
    add_shortcode('bluesky_last_posts', [$this->render_front, 'bluesky_last_posts_shortcode']);

    // Admin notices
    add_action('admin_notices', [$this->settings, 'display_bluesky_logout_message']);
}
```

---

## Test Priority Ordering (Claude's Discretion Recommendation)

Based on risk/complexity analysis of the actual codebase:

1. **BlueSky_AJAX_Service** — Highest risk. Public-facing, security-critical (nonce verification for nopriv endpoints), multiple code paths. Test: nonce verification applied, parameter sanitization, auth handler permission check (`current_user_can`), error responses are translatable.

2. **BlueSky_API_Handler** — Core. All features depend on it. Already has the cleanest DI (receives options via constructor). Test: `authenticate()` token caching/refresh with mocked HTTP responses, `fetch_bluesky_posts()` with filters, `create_for_account()` factory method sets account_id correctly.

3. **BlueSky_Syndication_Service** — High business value. Test: post status transition guard (`publish` to non-`publish` does nothing), `_bluesky_dont_syndicate` check, multi-account branch selection, post meta saving, duplicate syndication prevention (`_bluesky_syndicated` check).

4. **BlueSky_Settings_Service::sanitize_settings()** — Handles user input directly. Test: handle normalization (email passthrough, bare username gets `.bsky.social`, full handle unchanged), cache duration math, discussion settings validation, new account processing via `new_accounts` input.

5. **BlueSky_Helpers** — Utility class, mostly pure functions. Test: encryption/decryption round-trip, transient key construction includes account_id when provided, UUID generation returns correct format.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| All plugin logic in one class | Service class decomposition | 2019+ best practice | Testable, maintainable |
| Full WP install for all tests | Brain Monkey for unit tests | 2016+ | No database needed for unit tests |
| Singleton plugin main class | DI-enabled coordinator with injected services | Standard today | Dependencies swappable, testable |
| Debug params visible to anyone | Require `manage_options` for debug features | Security hygiene | Prevents info disclosure |
| Hardcoded English in JS | Translated strings via wp_localize_script i18n object | WP 5.0+ (wp.i18n) / wp_localize older pattern | Plugin translatable by community |

**Deprecated/outdated patterns found in current codebase:**
- Direct `$_POST` access without `wp_unslash()` + `sanitize_text_field()`: found in syndication methods and handle_account_actions
- `wp_send_json_error("plain string")` without translation: found in 6 locations (listed in i18n audit above)
- `new BlueSky_Account_Manager()` inside constructors instead of injection: found in Discussion_Display

---

## Open Questions

1. **normalize_bluesky_handle placement**
   - What we know: It's a private method in Plugin_Setup called during settings sanitization and account add logic. Account_Manager's `add_account()` does NOT normalize handles — it stores what's passed.
   - What's unclear: Should it move to Helpers (reusable) or stay in Settings_Service (settings concern)?
   - Recommendation: Move to `BlueSky_Helpers` as `public static function normalize_handle($handle)`. Update all call sites. Account_Manager can then also call it, ensuring consistent normalization everywhere handles are accepted.

2. **Legacy AJAX endpoints still in use?**
   - What we know: `ajax_fetch_bluesky_posts` and `ajax_get_bluesky_profile` are registered `nopriv` with no nonce check. The newer async variants (`ajax_async_posts`, `ajax_async_profile`) have nonce checks.
   - What's unclear: Whether any JS still calls the legacy endpoints, or if they've been superseded by the async variants.
   - Recommendation: Search `assets/js/` for `fetch_bluesky_posts` and `get_bluesky_profile` action strings before deciding whether to keep or remove the legacy endpoints. Add nonce check regardless; if unused, deprecate in this phase.

3. **render_settings_page template extraction scope**
   - What we know: The method is 870 lines of inline HTML, making Settings_Service the largest new class. Inline HTML is a maintenance burden.
   - What's unclear: Whether to extract all tab sections into partials during this phase, or just the overall structure.
   - Recommendation: Extract each tab section (Account, Customization, Styles, Discussions, Shortcodes, About) into separate `templates/admin/tab-*.php` partials. This reduces render_settings_page to ~50 lines of orchestration.

---

## Sources

### Primary (HIGH confidence)
- Direct codebase read: `classes/BlueSky_Plugin_Setup.php` (3421 lines, all methods catalogued)
- Direct codebase read: `classes/BlueSky_Render_Front.php` (1233 lines)
- Direct codebase read: `classes/BlueSky_Discussion_Display.php` (1416 lines, all methods catalogued)
- Direct codebase read: `classes/BlueSky_API_Handler.php`, `classes/BlueSky_Helpers.php`, `classes/BlueSky_Account_Manager.php`
- Direct codebase read: `assets/js/bluesky-async-loader.js` (all user-visible strings identified)
- `.planning/codebase/ARCHITECTURE.md` — existing architecture analysis
- `.planning/codebase/CONVENTIONS.md` — existing conventions analysis
- `.planning/research/PITFALLS.md` — existing pitfall research
- `.planning/codebase/TESTING.md` — existing test gap analysis

### Secondary (MEDIUM confidence)
- [WordPress Nonces documentation](https://developer.wordpress.org/apis/security/nonces/) — nopriv nonce behavior
- [WordPress Internationalization handbook](https://developer.wordpress.org/apis/internationalization/) — wp_localize_script vs wp_set_script_translations
- [Brain Monkey documentation](https://giuseppe-mazzapica.gitbook.io/brain-monkey) — WP function mocking
- [WordPress PHPUnit testing 2025](https://developer.wordpress.org/news/2025/12/how-to-add-automated-unit-tests-to-your-wordpress-plugin/) — official WP dev blog guidance
- [Patchstack AJAX security](https://patchstack.com/articles/patchstack-weekly-week-19-secure-ajax-endpoints/) — nopriv endpoint security model
- [Lexo advanced AJAX security 2025](https://www.lexo.ch/blog/2025/01/advanced-wordpress-ajax-security-three-ways-to-prevent-unauthorized-requests-secure-your-endpoints/) — defense layers for AJAX

### Tertiary (LOW confidence)
- WebSearch results on DI patterns in WordPress — confirm Brain Monkey is the dominant standalone unit test approach (could not verify via official WP docs, but multiple authoritative sources agree)

---

## Metadata

**Confidence breakdown:**
- Current codebase state: HIGH — read directly, method-by-method
- Decomposition groupings: HIGH — based on direct method analysis
- Security vulnerabilities: HIGH — confirmed by direct code inspection at specific line numbers
- i18n gaps: HIGH — confirmed by direct code inspection; all missing strings identified
- Test framework recommendation: MEDIUM — Brain Monkey is well-established but "lightest setup" claim based on community consensus, not official WP docs
- Architecture patterns: HIGH — standard WordPress plugin patterns, well-documented

**Research date:** 2026-02-17
**Valid until:** 2026-04-17 (stable domain; valid as long as classes are not heavily modified before planning begins)
