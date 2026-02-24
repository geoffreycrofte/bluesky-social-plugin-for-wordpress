# Codebase Structure

**Analysis Date:** 2026-02-14

## Directory Layout

```
bluesky-social-plugin-for-wordpress/
├── assets/                    # Static files (CSS, JS, images)
│   ├── css/                   # Stylesheets for frontend and admin
│   ├── js/                    # JavaScript for frontend and admin functionality
│   └── img/                   # Images and SVG assets
├── blocks/                    # Gutenberg block definitions (JavaScript)
├── classes/                   # PHP classes (core plugin logic)
│   └── widgets/               # WordPress widget classes
├── languages/                 # Localization files (if generated)
├── .planning/                 # GSD planning documents
│   └── codebase/              # This analysis directory
├── .github/                   # GitHub workflows and templates
├── social-integration-for-bluesky.php  # Main plugin file (entry point)
├── uninstall.php              # Plugin uninstall cleanup
├── README.md                  # Plugin documentation
└── readme.txt                 # WordPress plugin directory readme
```

## Directory Purposes

**assets/:**
- Purpose: All static frontend and admin resources
- Contains: CSS stylesheets, JavaScript files, SVG/PNG images
- Key files:
  - `css/bluesky-social-posts.css`: Posts feed styling
  - `css/bluesky-social-profile.css`: Profile card styling
  - `css/bluesky-social-admin.css`: Admin page styling
  - `css/bluesky-discussion-display.css`: Discussion thread styling
  - `js/bluesky-social-admin.js`: Admin page interactions
  - `js/bluesky-discussion-frontend.js`: Frontend discussion display
  - `js/bluesky-social-lightbox.js`: Lightbox for images

**blocks/:**
- Purpose: Gutenberg block JavaScript definitions
- Contains: Block registration, edit interfaces, inspector controls
- Key files:
  - `bluesky-posts-feed.js`: Posts block with layout options
  - `bluesky-profile-card.js`: Profile card block with display toggles
  - `bluesky-pre-publish-panel.js`: Editor pre-publish panel for syndication

**classes/:**
- Purpose: Core plugin PHP classes
- Contains: All business logic, API handling, rendering
- Key files:
  - `BlueSky_API_Handler.php`: API communication and auth (911 lines)
  - `BlueSky_Plugin_Setup.php`: Main orchestrator and hook registration (2537 lines)
  - `BlueSky_Render_Front.php`: Frontend rendering and shortcodes (1085 lines)
  - `BlueSky_Discussion_Display.php`: Discussion thread display (1337 lines)
  - `BlueSky_Post_Metabox.php`: Post editor metabox and syndication UI (439 lines)
  - `BlueSky_Helpers.php`: Utility functions (266 lines)
  - `BlueSky_Admin_Actions.php`: Admin form action handlers (49 lines)
  - `widgets/BlueSky_Posts_Widget.php`: WordPress sidebar widget for posts
  - `widgets/BlueSky_Profile_Widget.php`: WordPress sidebar widget for profile

**languages/:**
- Purpose: Localization/translation files
- Contains: `.pot` template and language-specific `.po`/`.mo` files
- Generated: Yes (via translation tools)

**.planning/codebase/:**
- Purpose: GSD analysis and planning documents
- Contains: ARCHITECTURE.md, STRUCTURE.md, and other analysis files
- Note: Created by GSD mapper tools for implementation guidance

## Key File Locations

**Entry Points:**
- `social-integration-for-bluesky.php`: Plugin initialization file
  - Loads: Defines constants, requires all class files, instantiates main objects
  - Version: 1.5.0
  - Requires: PHP 7.4+, WordPress 5.0+

**Configuration:**
- No separate config files; all settings stored in WordPress `options` table
- Settings key: `bluesky_settings`
- Activation hooks defined in `BlueSky_Plugin_Setup->on_plugin_activation()`
- Installation/uninstall: `uninstall.php` handles cleanup

**Core Logic:**
- `classes/BlueSky_API_Handler.php`: All BlueSky API calls
- `classes/BlueSky_Plugin_Setup.php`: Hook registration and settings UI
- `classes/BlueSky_Render_Front.php`: Data-to-HTML conversion
- `classes/BlueSky_Discussion_Display.php`: Discussion thread management
- `classes/BlueSky_Post_Metabox.php`: Post editor integration

**Frontend Assets:**
- Styles enqueued in `BlueSky_Plugin_Setup->frontend_enqueue_scripts()`
- Scripts enqueued in same method
- Inline styles generated dynamically in `BlueSky_Render_Front->render_inline_custom_styles_posts()`

**Admin Assets:**
- Styles enqueued in `BlueSky_Plugin_Setup->admin_enqueue_scripts()`
- Scripts for metabox in `BlueSky_Post_Metabox->enqueue_metabox_scripts()`
- Settings page rendered by `BlueSky_Plugin_Setup->render_settings_page()`

## Naming Conventions

**Files:**
- Class files: `BlueSky_[FeatureName].php` (PascalCase with underscores)
- Block files: `bluesky-[feature-name].js` (kebab-case)
- Stylesheet: `bluesky-[module-name].css` (kebab-case)
- JavaScript: `bluesky-[module-name].js` (kebab-case)

**Directories:**
- Feature directories: lowercase with hyphens (e.g., `assets`, `blocks`, `classes`)
- Subdirectories within features: lowercase (e.g., `widgets`, `css`, `js`, `img`)

**PHP Classes:**
- Format: `class BlueSky_[Feature]` (PascalCase after BlueSky_)
- Examples: `BlueSky_API_Handler`, `BlueSky_Plugin_Setup`, `BlueSky_Helpers`

**WordPress Hooks:**
- Actions: `bluesky_*` prefix for custom actions
- Filters: `bluesky_*` prefix for custom filters
- AJAX: `wp_ajax_[action_name]` registered on `wp_ajax_` hooks

**Constants:**
- Format: `BLUESKY_[FEATURE]_[NAME]` (SCREAMING_SNAKE_CASE)
- Examples: `BLUESKY_PLUGIN_VERSION`, `BLUESKY_PLUGIN_OPTIONS`, `BLUESKY_PLUGIN_SETTING_PAGENAME`

**Settings Keys:**
- Main options key: `bluesky_settings`
- Post meta keys: `_bluesky_*` (underscore prefix)
- Transient keys: `bluesky_cache_*` (version-specific prefix)

## Where to Add New Code

**New Feature:**
- Primary code: Create new class file in `classes/BlueSky_[Feature].php`
- Hook registration: Add to `BlueSky_Plugin_Setup->init_hooks()`
- Instantiation: Add to main plugin file if persistent, or instantiate in Setup class if needed
- Tests: Not currently tested; should add test file

**New Component/Module:**
- Frontend output: Add method to `classes/BlueSky_Render_Front.php`
- Rendering logic: Implement using existing pattern with parameters
- Styling: Add CSS to corresponding file in `assets/css/bluesky-[module].css`
- JavaScript: Add to `assets/js/bluesky-[module].js`

**Gutenberg Blocks:**
- New block: Create `blocks/bluesky-[feature-name].js`
- Register in: `BlueSky_Plugin_Setup->register_gutenberg_blocks()`
- Server-side render: Method in `BlueSky_Render_Front.php` or appropriate class

**Utilities:**
- Shared helpers: Add static method to `classes/BlueSky_Helpers.php`
- Password encryption/decryption: Use `bluesky_encrypt()` / `bluesky_decrypt()` in Helpers
- Transient management: Use `get_*_transient_key()` helpers for cache keys

**Widgets:**
- New widget: Create `classes/widgets/BlueSky_[Name]_Widget.php`
- Extend: `WP_Widget` class
- Register: Add to `BlueSky_Plugin_Setup->register_widgets()`

**Admin Pages:**
- Settings page rendered by `BlueSky_Plugin_Setup->render_settings_page()`
- Add new sections inside existing settings form
- Use `do_settings_sections()` and `settings_fields()` for consistent styling

**AJAX Endpoints:**
- Add handler method to appropriate class
- Register in constructor with `add_action('wp_ajax_[action]', [$this, 'method'])`
- Return JSON response
- Verify nonce for security

## Special Directories

**assets/:**
- Purpose: Frontend and admin static assets
- Generated: No (manually created and maintained)
- Committed: Yes (all files committed to git)
- Cache busting: Version number appended to enqueued scripts

**.github/:**
- Purpose: GitHub workflows and templates
- Generated: No
- Committed: Yes (workflow definitions)
- Contains: CI/CD configurations if present

**.planning/codebase/:**
- Purpose: GSD mapping and planning documents
- Generated: Yes (by GSD mapper tools)
- Committed: Yes (documentation only)
- Update: Regenerate when architecture changes significantly

**languages/:**
- Purpose: Translation files for internationalization
- Generated: Partially (template created, translations added)
- Committed: Yes (translation files)
- Text domain: `social-integration-for-bluesky`

## Code Organization Principles

**Single Responsibility:**
- Each class handles one major concern (API, rendering, admin, etc.)
- Classes injected with dependencies, not creating them
- Example: `BlueSky_API_Handler` only handles API calls

**Dependency Injection:**
- `BlueSky_API_Handler` passed to `Render_Front`, `Discussion_Display`, etc.
- Options loaded once and stored on instantiation
- Reduces repeated database queries

**Hook-Driven Architecture:**
- All initialization via WordPress hooks
- Constructor registers hooks, methods handle them
- Follows WordPress plugin conventions

**Caching Strategy:**
- Response caching via WordPress transients
- Cache keys include parameters for variations
- Manual refresh available for most features

---

*Structure analysis: 2026-02-14*
