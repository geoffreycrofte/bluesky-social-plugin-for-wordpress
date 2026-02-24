# Coding Conventions

**Analysis Date:** 2026-02-14

## Naming Patterns

**Classes:**
- PascalCase with `BlueSky_` prefix for all main classes
- Examples: `BlueSky_API_Handler`, `BlueSky_Plugin_Setup`, `BlueSky_Render_Front`
- Location: `classes/` directory - one class per file named to match class name
- Widget classes extend `WP_Widget`: `BlueSky_Posts_Widget`, `BlueSky_Profile_Widget` in `classes/widgets/`

**Functions:**
- snake_case for all functions
- Public methods: descriptive names like `authenticate()`, `register_settings()`, `admin_enqueue_scripts()`
- Private helper methods: prefixed with `init_`, `get_`, `set_`, or `register_` to indicate purpose
  - Examples: `init_hooks()`, `get_profile_transient_key()`, `set_transient()`
- WordPress hook callbacks use full action/filter name: `on_plugin_activation()`, `ajax_fetch_bluesky_posts()`

**Variables:**
- snake_case throughout (PHP convention)
- Private properties in classes use `$this->` convention
- Examples: `$this->options`, `$this->api_handler`, `$access_token`, `$bluesky_api_url`
- Array keys use snake_case: `$body["accessJwt"]` (following API response structure)

**Constants:**
- UPPERCASE with underscores
- Plugin-specific constants prefixed with `BLUESKY_`
- Examples: `BLUESKY_PLUGIN_VERSION`, `BLUESKY_PLUGIN_OPTIONS`, `BLUESKY_PLUGIN_TRANSIENT`, `BLUESKY_PLUGIN_FILE`
- Defined in main plugin file: `social-integration-for-bluesky.php`

**Transient Keys:**
- Constructed dynamically with helper methods in `BlueSky_Helpers` class
- Methods: `get_profile_transient_key()`, `get_posts_transient_key()`, `get_access_token_transient_key()`, `get_refresh_token_transient_key()`, `get_did_transient_key()`
- Base transient key: `BLUESKY_PLUGIN_TRANSIENT` + suffix, versioned by plugin version

**JavaScript:**
- camelCase for variables and functions
- Examples from `assets/js/bluesky-social-admin.js`: `hideTabs()`, `showCurrent()`, `toggleDiscussionFields()`, `enableDiscussionsCheckbox`
- IDs and classes use kebab-case: `bluesky-social-integration-admin`, `bluesky-tab-button`, `bluesky-toggle-replies`
- Data attributes use kebab-case: `data-var`, `data-tab`, `data-collapsed-text`, `data-expanded-text`

**CSS Classes:**
- kebab-case, namespaced with `bluesky-` prefix
- Examples: `bluesky-social-integration-profile-card`, `bluesky-social-integration-last-post`, `bluesky-debug-sidebar`, `bluesky-tab-content`

## Code Style

**Formatting:**
- No formal linter or formatter detected in codebase
- PHP spacing: 4 spaces indentation in most files
- Some inconsistency: `BlueSky_Helpers.php` uses `$this -> options` (spaces around arrow), while most others use `$this->options` (no spaces)
- Opening braces: K&R style (same line for classes and functions)
- String quotes: Double quotes preferred for PHP strings containing variables, single quotes for literals

**PHP Syntax:**
- Modern PHP 7.4+ allowed (requires PHP 7.4 minimum per plugin header)
- Access modifiers explicitly used: `private`, `public`, `protected`
- Type hints not consistently used but gradually being adopted
- Short ternary operator used: `$limit ?? $this->options['posts_limit'] ?? 5`

**JavaScript Syntax:**
- Immediately Invoked Function Expression (IIFE) pattern wraps code in admin scripts: `(function ($) { ... })(jQuery)`
- Modern ES6 features used: arrow functions, `const`, `let`, template literals
- `'use strict';` declaration in frontend scripts
- Consistent semicolon usage at line endings

## Import Organization

**PHP:**
- All classes loaded via `require_once` in main plugin file: `social-integration-for-bluesky.php`
- Load order: Helpers first, then API Handler, then Plugin Setup, then Render/Admin/Discussion classes, then widgets
- Version comments added to requires: `// V.1`, `// V.1.5.0`
- Each class file starts with direct access prevention:
  ```php
  if (!defined("ABSPATH")) {
      exit();
  }
  // OR
  if ( ! defined('ABSPATH') ) {
      exit;
  }
  ```

**JavaScript:**
- WordPress `wp_enqueue_script()` and `wp_enqueue_style()` used for dependencies
- Block scripts declare dependencies in array: `["wp-plugins", "wp-edit-post", "wp-element", "wp-data", "wp-components", "wp-i18n"]`
- Global variables passed via `wp_localize_script()` for AJAX and data

**No Import Aliasing:**
- Classes instantiated directly: `new BlueSky_Helpers()`, `new BlueSky_API_Handler()`
- No namespace usage (WordPress plugin style)

## Error Handling

**Patterns:**
- WordPress error responses checked with `is_wp_error($response)`
- Try-catch blocks used for encryption operations in `BlueSky_Helpers.php`
- Exception catching: `catch (Exception $e)` with message extraction via `$e->getMessage()`
- Silent failures common: Returns `false` without throwing exceptions
- Admin notices via `add_action('admin_notices')` for user-facing errors
- Examples from `BlueSky_API_Handler`:
  ```php
  if (is_wp_error($response)) {
      return false;
  }
  ```

**Validation:**
- `isset()` checks for array keys before access
- `empty()` checks for falsy values
- Null coalescing operator: `$this->options["theme"] ?? "system"`
- Data sanitization with `esc_attr()`, `esc_html()`, `wp_kses()` for output
- Transient and option checks for cached/stored data

## Logging

**Framework:** WordPress admin notices only, no dedicated logging library

**Patterns:**
- Admin notices displayed to users with capability check: `current_user_can('manage_options')`
- Exception messages included in notice text: `sprintf('...%s...', $e->getMessage())`
- Notice types: `'error'`, `'warning'`, `'success'`, `'info'` passed to `add_admin_notice()`
- No debug logging to files or external services observed
- Examples: encryption failures, auth issues displayed as dismissible admin notices

## Comments

**When to Comment:**
- PHPDoc blocks for all class properties and public methods
- Comments on complex logic blocks, especially in API handling and data transformation
- TODOs noted: `//TODO: should I use aria-hidden...`, `// TODO: write a fallback solution using cache`
- Inline comments for non-obvious conditional logic

**PHPDoc/JSDoc:**
- PHPDoc format used consistently for class properties and methods:
  ```php
  /**
   * Brief description
   * @var type
   * @param type $name Description
   * @return type Description
   */
  ```
- Example from `BlueSky_API_Handler`:
  ```php
  /**
   * Authenticate the user by creating and storing an access and refresh jwt.
   *
   * @param mixed $force Wether or not to force the refresh token creation.
   * @return bool
   */
  ```

**JSDoc in JavaScript:**
- Block comments used for function descriptions in `bluesky-discussion-frontend.js`
- Single-line comments explain logic steps
- Example:
  ```javascript
  /**
   * Initialize tabs functionality
   */
  function initTabs() { ... }
  ```

## Function Design

**Size:**
- Most functions in 30-50 lines range
- Largest files: `BlueSky_Plugin_Setup.php` (2537 lines), `BlueSky_Discussion_Display.php` (1337 lines), `BlueSky_Render_Front.php` (1085 lines) contain multiple methods
- Methods stay focused on single responsibility

**Parameters:**
- Consistent use of array parameters for configuration: `$atts = []` for shortcode attributes
- Shortcodes use `wp_parse_args()` to merge defaults: `wp_parse_args($atts, ['theme' => $this->options["theme"] ?? "system", ...])`
- Callback methods receive standard WordPress parameters (e.g., `$hook` in `enqueue_metabox_scripts($hook)`)

**Return Values:**
- Explicit boolean returns for success/failure: `return true;` or `return false;`
- HTML strings returned from render methods: `bluesky_profile_card_shortcode()`, `bluesky_last_posts_shortcode()`
- Array returns from data fetch methods: `get_bluesky_profile()`, `get_bluesky_posts()`
- Void functions used for hooks/actions that don't return (enqueue, register)

## Module Design

**Exports:**
- All classes instantiated in main plugin file and stored in global scope
- No module exports per se; WordPress hooks serve as "exports" to external code
- Public methods called via hook callbacks: `add_action('admin_menu', [$this, 'add_admin_menu'])`

**Barrel Files:**
- No barrel files or index files
- Classes loaded individually in main plugin file in dependency order

**Singleton Pattern:**
- Plugin classes instantiated once in main file:
  ```php
  $bluesky_api_handler = new BlueSky_API_Handler(...);
  $bluesky_social_integration = new BlueSky_Plugin_Setup($bluesky_api_handler);
  ```
- Same instances reused for methods throughout request lifecycle
- Transients used to cache data across requests (tokens, posts, profile info)

**Dependency Injection:**
- Constructor injection used: `BlueSky_Plugin_Setup($bluesky_api_handler)`, `BlueSky_Render_Front($api_handler)`
- API handler passed to dependent classes for consistent authentication state
- Helpers instantiated as needed within methods: `$helpers = new BlueSky_Helpers();`

---

*Convention analysis: 2026-02-14*
