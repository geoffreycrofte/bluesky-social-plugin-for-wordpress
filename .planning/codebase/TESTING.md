# Testing Patterns

**Analysis Date:** 2026-02-14

## Test Framework

**Status:** No automated testing framework detected

**Observation:**
- No `phpunit.xml`, `jest.config.js`, `vitest.config.js`, or similar test configuration files found
- No `package.json` or `composer.json` for dependency management
- No test directories or test files (*.test.php, *.spec.php, *.test.js, *.spec.js) exist in codebase
- Plugin is a WordPress plugin using vanilla PHP and vanilla JavaScript without test automation

**Run Commands:**
- No test running infrastructure exists
- Manual testing required

## Test File Organization

**Current State:**
- Not applicable - no automated tests exist

**Recommendation for Future:**
- PHP unit tests should be placed in a `tests/` directory at plugin root
- Test file naming: `Test[ClassName].php` (e.g., `TestBlueSkyAPIHandler.php`)
- JavaScript tests should be in `assets/js/tests/` or `blocks/tests/`
- Use PHPUnit for PHP testing (WordPress standard)
- Use Jest or Vitest for JavaScript testing (WordPress block development standard)

## Test Structure

**Current PHP Code Structure** (used as reference for future tests):

Classes in `classes/` use constructor dependency injection:
```php
class BlueSky_Plugin_Setup {
    public function __construct(BlueSky_API_Handler $api_handler) {
        $this->api_handler = $api_handler;
        $this->helpers = new BlueSky_Helpers();
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS);
        $this->init_hooks();
    }
}
```

**JavaScript Code Structure** (for future test reference):

Functions are organized with IIFE pattern:
```javascript
(function ($) {
    // Private scope
    const navItems = document.querySelectorAll("#bluesky-main-nav-tabs a");
    const hideTabs = function () { ... };
    const showCurrent = function (currentNavItem) { ... };

    navItems.forEach((item) => {
        item.addEventListener("click", function (e) { ... });
    });
})(jQuery);
```

## Mocking

**Not Applicable:**
- No test framework currently in place means no mocking framework in use

**Testable Areas Identified** (for future mocking):

**PHP Mocking Needs:**
- WordPress functions: `get_option()`, `set_transient()`, `get_transient()`, `delete_transient()`, `wp_remote_post()`, `get_user_meta()`, `update_post_meta()`
- HTTP responses from BlueSky API in `BlueSky_API_Handler`
- Encryption/decryption in `BlueSky_Helpers` (test with real OpenSSL or mock it)

**JavaScript Mocking Needs:**
- `document.querySelector()` and `document.querySelectorAll()` in `bluesky-social-admin.js`
- `localStorage` operations for tab state persistence
- Event listeners and click handlers
- DOM manipulation

## Fixtures and Factories

**Test Data Needed** (for future implementation):

**WordPress Options Fixture:**
```php
$options = [
    'handle' => 'testuser.bsky.social',
    'app_password' => 'encrypted_password_here',
    'posts_limit' => 5,
    'theme' => 'system',
    'styles' => ['feed_layout' => 'default'],
    'enable_discussions' => true,
];
update_option(BLUESKY_PLUGIN_OPTIONS, $options);
```

**API Response Fixture** (BlueSky posts API):
```php
$api_response = [
    'feed' => [
        [
            'post' => [
                'uri' => 'at://...',
                'cid' => '...',
                'author' => [
                    'displayName' => 'User Name',
                    'handle' => 'user.bsky.social',
                    'avatar' => 'https://...',
                ],
                'record' => [
                    'text' => 'Post content',
                    'createdAt' => '2026-02-14T...',
                ],
            ],
        ],
    ],
];
```

**Encryption Fixture:**
```php
$plain_text = 'test_app_password';
$encrypted = $helpers->bluesky_encrypt($plain_text);
$decrypted = $helpers->bluesky_decrypt($encrypted);
assert($plain_text === $decrypted);
```

## Coverage

**Requirements:** No coverage enforcement detected

**Observation:**
- No code coverage tools configured or mentioned
- No CI/CD pipeline for automated testing observed
- Manual code review appears to be the quality control method

**Critical Areas for Future Testing** (High priority):

1. **Authentication Flow** - `BlueSky_API_Handler::authenticate()`
   - Handle transient caching
   - Token refresh logic
   - Encryption/decryption
   - Credential validation

2. **API Integration** - `BlueSky_API_Handler` methods
   - `get_bluesky_profile()` - profile fetch and caching
   - `get_bluesky_posts()` - feed fetch with filters
   - Error handling for network failures
   - Transient cache expiry

3. **Data Rendering** - `BlueSky_Render_Front` methods
   - Shortcode output generation
   - HTML sanitization with `wp_kses()`
   - Custom CSS generation from settings
   - SVG tag allowlisting

4. **Admin Settings** - `BlueSky_Plugin_Setup` methods
   - Settings registration and saving
   - AJAX endpoint security (nonce validation)
   - Plugin activation hook
   - Textdomain loading

5. **Post Syndication** - `BlueSky_Post_Metabox` and `BlueSky_Admin_Actions`
   - Meta box display and saving
   - Syndication trigger on post publish
   - Pre-publish panel for Gutenberg
   - Image extraction and feature image handling

## Test Types

**Unit Tests** (for future):
- Scope: Individual methods in isolation, mocking WordPress and external API calls
- Approach: Test `BlueSky_Helpers` encryption/decryption independently
  - Test `BlueSky_API_Handler` token management with mocked transients
  - Test shortcode parameter parsing in `BlueSky_Render_Front`
  - Test metabox registration and CSS enqueuing

**Integration Tests** (for future):
- Scope: Interaction between classes and WordPress hooks
- Approach: Test full authentication flow (from login to token storage)
  - Test post syndication from metabox save to API call
  - Test shortcode rendering with real options (WordPress database required)
  - Test AJAX endpoints with WordPress admin environment

**End-to-End Tests** (not currently implemented):
- Framework: Not used
- Reason: WordPress plugin testing typically requires WordPress environment; E2E testing of BlueSky API integration would need real API or comprehensive mocking

## Common Patterns

**Async Testing** (JavaScript):
Currently used pattern for DOM operations in `bluesky-discussion-frontend.js`:
```javascript
function handleHashScroll() {
    if (window.location.hash === '#bluesky-discussion') {
        const discussionSection = document.querySelector('.bluesky-discussion-section');
        if (discussionSection) {
            setTimeout(() => {
                discussionSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
}
```

**Future Testing Pattern:**
```javascript
describe('handleHashScroll', () => {
    it('should scroll to discussion section when hash is present', (done) => {
        window.location.hash = '#bluesky-discussion';
        const mockElement = document.createElement('div');
        mockElement.classList.add('bluesky-discussion-section');

        // Mock scrollIntoView
        mockElement.scrollIntoView = jest.fn();

        handleHashScroll();

        setTimeout(() => {
            expect(mockElement.scrollIntoView).toHaveBeenCalled();
            done();
        }, 150);
    });
});
```

**Error Testing** (PHP):
Current pattern in `BlueSky_Helpers.php`:
```php
try {
    $encrypted = openssl_encrypt(...);
    if ($encrypted === false) {
        $this->add_admin_notice(__('Encryption failed...'));
        return false;
    }
} catch (Exception $e) {
    $this->add_admin_notice(
        sprintf(__('Encryption failed: %s'), $e->getMessage())
    );
    return false;
}
```

**Future Testing Pattern:**
```php
public function test_encryption_failure_handling() {
    // Mock openssl_encrypt to return false
    $helpers = new BlueSky_Helpers();

    // Should return false on failure
    $result = $helpers->bluesky_encrypt('test');
    $this->assertFalse($result);

    // Should trigger admin notice
    $this->assertTrue(has_action('admin_notices'));
}
```

**API Response Testing** (PHP):
Current pattern in `BlueSky_API_Handler.php`:
```php
$response = wp_remote_post($url, [...]);

if (is_wp_error($response)) {
    return false;
}

$body = json_decode(wp_remote_retrieve_body($response), true);

if (isset($body["accessJwt"]) && isset($body["refreshJwt"]) && isset($body["did"])) {
    // Success path
    set_transient(...);
    return true;
}

return false;
```

**Future Testing Pattern:**
```php
public function test_successful_authentication() {
    $mock_response = [
        'body' => json_encode([
            'accessJwt' => 'token123',
            'refreshJwt' => 'refresh456',
            'did' => 'did:plc:123',
        ]),
        'response' => ['code' => 200],
    ];

    // Mock wp_remote_post
    Mockery::mock('overload:WordPress\HTTP')
        ->shouldReceive('remote_post')
        ->andReturn($mock_response);

    $handler = new BlueSky_API_Handler(['handle' => 'user.bsky.social', 'app_password' => 'encrypted']);
    $result = $handler->authenticate();

    $this->assertTrue($result);
    $this->assertEquals('token123', get_transient('bluesky_cache_1.5.0-access-token'));
}
```

## Testing Gaps

**High-Priority Untested Areas:**

1. **Authentication Security** - `BlueSky_API_Handler::authenticate()`
   - Nonce validation for form submissions not tested
   - Token expiry and refresh logic needs verification
   - Encryption robustness not verified

2. **API Error Handling** - Network failures, rate limiting, invalid credentials
   - `BlueSky_API_Handler` silently returns `false` on errors
   - No retry logic tested
   - Error messages not consistently displayed to users

3. **Shortcode Output** - `BlueSky_Render_Front::bluesky_last_posts_shortcode()`
   - HTML sanitization effectiveness
   - XSS prevention with custom attribute filters
   - SVG tag allowlisting correctness

4. **AJAX Endpoint Security** - `BlueSky_Plugin_Setup` AJAX methods
   - Nonce validation missing validation testing
   - Capability checks not verified
   - Input sanitization not tested

5. **Transient Cache Strategy**
   - Cache expiry times not tested
   - Cache invalidation on settings change not verified
   - Stale cache fallback behavior not tested

6. **Post Syndication Logic** - `BlueSky_Post_Metabox` and `BlueSky_Admin_Actions`
   - Featured image extraction from content not tested
   - Post type filtering (only 'post' type) not verified
   - Activation date check for old posts not tested
   - Duplicate post detection not verified

---

*Testing analysis: 2026-02-14*
