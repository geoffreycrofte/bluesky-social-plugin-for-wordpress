# Phase 2: Codebase Refactoring - Context

**Gathered:** 2026-02-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Decompose three monolithic PHP classes (Plugin_Setup 3000+ lines, Render_Front 1085 lines, Discussion_Display 1337 lines) into focused, testable service classes. Fix security vulnerabilities. Add test coverage for critical paths. Verify all user-facing strings are translatable per WP standards (PHP and JS). No new features — pure structural improvement.

</domain>

<decisions>
## Implementation Decisions

### Decomposition strategy
- Moderate grouping into ~5-6 service classes (Settings, Syndication, Display, AJAX, Cache, etc.)
- Not full extraction of every method into its own class — group related methods together
- Keep manual require_once includes in main plugin file — no Composer, no PSR-4, no autoloader
- CSS class names preserved as-is (bluesky-social-integration-*) — backward compatibility for user custom CSS

### Template approach
- Structure templates so theme overridability is easy to add later, but don't implement it now
- Templates are plugin-internal only for this phase

### Security
- `?godmode` parameter: Keep debug sidebar but require `manage_options` capability (admin-only access)
- Syndication POST data: Claude's discretion on strict vs lenient validation

### Translations
- No .pot file regeneration needed
- Double-check all strings created during Phase 1 (and this phase) are wrapped in proper WP i18n functions
- Include JS strings — use wp_localize_script or wp.i18n patterns as appropriate
- Follow existing text domain: 'social-integration-for-bluesky'

### Claude's Discretion
- DI pattern vs current global/singleton pattern for new service classes (pick what makes testing easier while keeping it simple)
- Whether Plugin_Setup becomes thin wrapper delegating to services, or gets replaced entirely (pick least risky approach)
- Template structure: plain PHP partials vs method-based renderers (pick cleanest for existing patterns)
- Test framework: WP PHPUnit with wp-env vs standalone PHPUnit with mocks (pick lightest effective setup)
- Test priority ordering: API/auth vs syndication vs settings (pick based on risk/complexity)
- Test isolation: mocks only vs mocks + optional integration tests (pick what's practical)
- Public AJAX nonce strategy vs rate limiting (pick based on WP security best practices)
- Syndication POST validation strictness (pick what prevents most real-world issues)

</decisions>

<specifics>
## Specific Ideas

- Plugin_Setup.php has grown significantly with multi-account additions from Phase 1 — this is the most urgent decomposition target
- The existing code pattern uses `get_option()` and `new BlueSky_Helpers()` inside methods — new services should be compatible with this style unless DI is chosen
- Existing class methods are public and may be called by WordPress hooks (add_action/add_filter) — extraction must preserve hook registration

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-codebase-refactoring*
*Context gathered: 2026-02-17*
