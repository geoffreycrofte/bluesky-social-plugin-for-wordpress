# Phase 4: Error Handling & UX - Research

**Researched:** 2026-02-19
**Domain:** WordPress admin UX patterns, error messaging, health monitoring
**Confidence:** HIGH

## Summary

Phase 4 transforms raw API failures into user-friendly guidance across three WordPress admin surfaces: contextual error messages (post edit screen), persistent admin notices (cross-page critical alerts), and health monitoring (dashboard widget, settings page, Site Health integration). Research reveals that **WordPress provides robust native APIs for all three surfaces** — `admin_notices` action for banners, `wp_add_dashboard_widget()` for widgets, `site_status_tests` filter for health checks, and `debug_information` filter for debug info. The Bluesky API returns standard HTTP 429 responses with rate limit headers and clear error codes, making detection straightforward. User-friendly error messaging research emphasizes **plain language, actionable next steps, and avoiding blame**, with inline contextual placement preferred over modal dialogs or generic error pages.

**Primary recommendation:** Use WordPress native admin notice APIs with user meta for dismissible persistence (24-hour return), implement health dashboard using `wp_add_dashboard_widget()` with manual refresh button (no Heartbeat auto-refresh), integrate Site Health via `site_status_tests` for pass/fail checks and `debug_information` for diagnostics, and craft error messages following the "explain + action" pattern with friendly conversational tone.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Error message style:**
- Errors appear in two places: inline contextual (next to the action that failed) AND persistent admin notices for critical issues needing attention across pages
- Friendly & plain tone throughout: "We couldn't post to Bluesky right now. Your credentials may have expired." — approachable, no jargon
- Action links (e.g., "Go to Settings" button) included only for critical errors like auth failures and config issues; transient errors (rate limits, timeouts) just describe the situation
- No visible severity levels — all errors look the same visually; message content itself conveys urgency; no color-coded red/yellow/blue distinction

**Health dashboard design:**
- Three locations: WP admin dashboard widget, plugin settings page (rework existing cache status area in last tab), and WP Site Health integration
- Dashboard widget shows full summary: account statuses, last syndication time/result, API health (circuit breaker state), cache status, and pending retries
- Dashboard widget uses manual refresh button (no auto-refresh via Heartbeat)
- WP Site Health integration includes pass/fail status checks ("Bluesky connection active", "API responding", "Credentials valid") plus a debug info section with API version, rate limit state, cache stats, account count

**Re-auth & recovery flows:**
- Expired credentials trigger a persistent admin notice banner with link to settings page — no inline re-auth forms
- Multiple broken accounts grouped into a single notice: "Accounts X and Y need re-authentication" — not one notice per account
- Notices are dismissible but return after 24 hours if the issue persists
- Partial syndication failures show per-account status list on the post edit screen: "Account A: Posted successfully. Account B: Failed — expired credentials."

**Retry & timing display:**
- Rate-limited/retrying posts show simple status text: "Rate limited — will retry automatically." No countdown timers or estimated times
- Circuit breaker open state visible to users with friendly explanation: "Bluesky requests paused due to repeated errors. Will resume automatically."
- Recent activity log (last 5-10 syndication events across posts) shown in the health widget for diagnosing recurring issues

### Claude's Discretion

- Retry button timing: whether to show immediately or only after auto-retries exhaust (based on Phase 3 async handler behavior)
- Exact wording of error messages for each error type
- Layout and styling of the dashboard widget and per-account status list
- How to structure the Site Health debug info section
- Activity log data structure and storage approach

### Specific Ideas (OUT OF CONTEXT.md)

- Rework the existing cache status area in the last settings tab into the health section (don't add a new tab, enhance what's there)
- Use WP Site Health best practices for plugin integration (proper test registration, debug info sections)
- Grouped notice pattern for multi-account issues reduces visual noise in admin

</user_constraints>

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Admin Notices API | Core 2.5+ | Display dismissible admin banners | Native WordPress notification system; every admin notices must use admin_notices action hook |
| WordPress Dashboard Widgets API | Core 2.7+ | Add custom admin dashboard widgets | wp_add_dashboard_widget() is the standard way to add dashboard content; used by core and thousands of plugins |
| WordPress Site Health API | Core 5.2+ | Plugin health checks and debug info | Native health monitoring system; site_status_tests filter for tests, debug_information filter for debug data |
| WordPress User Meta API | Core | Store dismissal state per-user | get_user_meta()/update_user_meta() for persistent notice dismissal tracking |
| WordPress Transients API | Core | Store temporary health data | Already in use; ideal for health widget data with short TTL (5-10 minutes) |

**Note:** All required APIs are WordPress core functions — no external dependencies needed.

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Options API | Core | Store activity log data | For storing recent syndication events (last 5-10) persistently |
| WordPress AJAX API | Core | Handle manual refresh button | wp_ajax_{action} hooks for authenticated widget refresh |
| Dashicons | Core | Status icons for health widget | Standard WordPress icon font; dashicons-yes-alt (success), dashicons-warning (partial), dashicons-dismiss (failed) |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Admin Notices API | Custom HTML in admin footer | Would miss standard WordPress dismiss functionality and screen reader support |
| Dashboard Widget API | Custom admin page | Would lose prominent visibility; users rarely check separate health pages unless prompted |
| Site Health integration | Custom health page | Would miss WP core troubleshooting workflow where users check Site Health first when having issues |
| User Meta for dismissals | Transients with user ID | Transients auto-expire; losing dismissal state after expiry creates poor UX |

**Installation:**

```php
// No installation needed - all WordPress core APIs
// Example usage already in codebase via BlueSky_Admin_Notices class
```

**Sources:**
- [WordPress Admin Notices API](https://developer.wordpress.org/reference/hooks/admin_notices/)
- [WordPress Dashboard Widgets API](https://developer.wordpress.org/apis/dashboard-widgets/)
- [WordPress Site Health API](https://developer.wordpress.org/apis/site-health/)
- [site_status_tests Hook](https://developer.wordpress.org/reference/hooks/site_status_tests/)
- [debug_information Filter](https://developer.wordpress.org/reference/hooks/debug_information/)

## Architecture Patterns

### Recommended Project Structure

```
classes/
├── BlueSky_Admin_Notices.php          # Already exists - enhance for persistent notices
├── BlueSky_Health_Dashboard.php       # New - dashboard widget implementation
├── BlueSky_Health_Monitor.php         # New - Site Health integration
├── BlueSky_Error_Translator.php       # New - API error → user-friendly message mapper
└── BlueSky_Activity_Logger.php        # New - recent syndication event tracking
```

### Pattern 1: Persistent Dismissible Admin Notices

**What:** Admin notices that persist across pages, can be dismissed, but return after 24 hours if issue persists

**When to use:** Critical issues requiring user action (expired credentials, failed accounts requiring re-authentication)

**Example:**

```php
// Source: WordPress Admin Notices best practices + persist-admin-notices-dismissal pattern
// https://www.alexgeorgiou.gr/persistently-dismissible-notices-wordpress/
class BlueSky_Admin_Notices {

    /**
     * Display persistent admin notice for expired credentials
     */
    public function expired_credentials_notice() {
        // Check if notice was dismissed within 24 hours
        $notice_key = 'bluesky_expired_creds_notice';
        $dismissed_until = get_user_meta(get_current_user_id(), $notice_key, true);

        if ($dismissed_until && time() < $dismissed_until) {
            return; // Still dismissed
        }

        // Check if any accounts have expired credentials
        $account_manager = new BlueSky_Account_Manager();
        $broken_accounts = $account_manager->get_accounts_with_status('auth_expired');

        if (empty($broken_accounts)) {
            return; // No issues
        }

        // Build grouped notice
        $account_names = array_column($broken_accounts, 'handle');
        $settings_url = admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME);

        echo '<div class="notice notice-error is-dismissible" data-dismissible="' . esc_attr($notice_key) . '">';
        echo '<p>';
        if (count($account_names) === 1) {
            printf(
                __('The Bluesky account %s needs re-authentication. <a href="%s">Update credentials</a>', 'social-integration-for-bluesky'),
                '<strong>' . esc_html($account_names[0]) . '</strong>',
                esc_url($settings_url)
            );
        } else {
            printf(
                __('Bluesky accounts %s need re-authentication. <a href="%s">Update credentials</a>', 'social-integration-for-bluesky'),
                '<strong>' . esc_html(implode(', ', $account_names)) . '</strong>',
                esc_url($settings_url)
            );
        }
        echo '</p></div>';
    }

    /**
     * Handle AJAX dismissal
     */
    public function handle_notice_dismissal() {
        check_ajax_referer('bluesky_dismiss_notice', 'nonce');

        $notice_key = sanitize_key($_POST['notice_key'] ?? '');
        if (!$notice_key) {
            wp_send_json_error();
        }

        // Store dismissal with 24-hour expiry
        $dismissed_until = time() + DAY_IN_SECONDS;
        update_user_meta(get_current_user_id(), $notice_key, $dismissed_until);

        wp_send_json_success();
    }
}
```

**JavaScript for dismissal:**

```javascript
// Enhanced dismiss handler that persists via AJAX
jQuery(document).on('click', '.notice[data-dismissible] .notice-dismiss', function() {
    var notice = jQuery(this).closest('.notice');
    var noticeKey = notice.data('dismissible');

    jQuery.post(ajaxurl, {
        action: 'bluesky_dismiss_notice',
        notice_key: noticeKey,
        nonce: blueskyAdmin.dismissNonce
    });
});
```

**Key insight:** WordPress `.is-dismissible` class provides UI only — persistence requires custom AJAX + user meta. Using 24-hour return ensures critical issues resurface if not resolved.

### Pattern 2: Dashboard Widget with Manual Refresh

**What:** Custom admin dashboard widget showing plugin health summary with manual refresh button

**When to use:** High-level status overview visible immediately upon admin login

**Example:**

```php
// Source: WordPress Dashboard Widgets API + WP Beginner guide
// https://developer.wordpress.org/apis/dashboard-widgets/
// https://www.wpbeginner.com/wp-themes/how-to-add-custom-dashboard-widgets-in-wordpress/
class BlueSky_Health_Dashboard {

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
        add_action('wp_ajax_bluesky_refresh_health', [$this, 'refresh_health_data']);
    }

    /**
     * Register dashboard widget
     */
    public function register_widget() {
        wp_add_dashboard_widget(
            'bluesky_health_widget',
            __('Bluesky Integration Health', 'social-integration-for-bluesky'),
            [$this, 'render_widget']
        );
    }

    /**
     * Render widget content
     */
    public function render_widget() {
        // Get health data from transient (cache for 5 minutes)
        $cache_key = 'bluesky_health_data';
        $health_data = get_transient($cache_key);

        if (false === $health_data) {
            $health_data = $this->collect_health_data();
            set_transient($cache_key, $health_data, 5 * MINUTE_IN_SECONDS);
        }

        ?>
        <div class="bluesky-health-widget">
            <div class="bluesky-health-section">
                <h4><?php _e('Account Status', 'social-integration-for-bluesky'); ?></h4>
                <ul class="bluesky-account-list">
                    <?php foreach ($health_data['accounts'] as $account): ?>
                        <li>
                            <span class="dashicons dashicons-<?php echo esc_attr($account['icon']); ?>"
                                  style="color:<?php echo esc_attr($account['color']); ?>;"></span>
                            <?php echo esc_html($account['handle']); ?>
                            <span class="bluesky-account-status"><?php echo esc_html($account['status']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bluesky-health-section">
                <h4><?php _e('Last Syndication', 'social-integration-for-bluesky'); ?></h4>
                <p>
                    <?php if ($health_data['last_syndication']['time']): ?>
                        <?php echo esc_html($health_data['last_syndication']['time']); ?> —
                        <strong><?php echo esc_html($health_data['last_syndication']['result']); ?></strong>
                    <?php else: ?>
                        <?php _e('No recent syndication', 'social-integration-for-bluesky'); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="bluesky-health-section">
                <h4><?php _e('API Health', 'social-integration-for-bluesky'); ?></h4>
                <p><?php echo esc_html($health_data['circuit_breaker_status']); ?></p>
                <p><small><?php echo esc_html($health_data['cache_status']); ?></small></p>
            </div>

            <div class="bluesky-health-section">
                <h4><?php _e('Recent Activity', 'social-integration-for-bluesky'); ?></h4>
                <ul class="bluesky-activity-log">
                    <?php foreach ($health_data['recent_activity'] as $event): ?>
                        <li>
                            <span class="bluesky-activity-time"><?php echo esc_html($event['time']); ?></span>
                            <?php echo esc_html($event['message']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <p class="bluesky-widget-footer">
                <button type="button" class="button button-small" id="bluesky-refresh-health">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh', 'social-integration-for-bluesky'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME . '#health')); ?>">
                    <?php _e('View detailed health', 'social-integration-for-bluesky'); ?>
                </a>
            </p>
        </div>

        <script>
        jQuery('#bluesky-refresh-health').on('click', function(e) {
            e.preventDefault();
            var btn = jQuery(this);
            btn.prop('disabled', true).find('.dashicons').addClass('spin');

            jQuery.post(ajaxurl, {
                action: 'bluesky_refresh_health',
                nonce: '<?php echo wp_create_nonce('bluesky_refresh_health'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload(); // Simple reload to show fresh data
                }
            }).always(function() {
                btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            });
        });
        </script>
        <?php
    }

    /**
     * Collect health data from various sources
     */
    private function collect_health_data() {
        $account_manager = new BlueSky_Account_Manager();
        $accounts = $account_manager->get_all_accounts();

        $health_data = [
            'accounts' => [],
            'last_syndication' => ['time' => null, 'result' => null],
            'circuit_breaker_status' => __('All systems operational', 'social-integration-for-bluesky'),
            'cache_status' => '',
            'recent_activity' => []
        ];

        // Account statuses
        foreach ($accounts as $account) {
            $breaker = new BlueSky_Circuit_Breaker($account['id']);
            $limiter = new BlueSky_Rate_Limiter();

            if (!$breaker->is_available()) {
                $status = __('Paused', 'social-integration-for-bluesky');
                $icon = 'clock';
                $color = '#f0b849';
            } elseif ($limiter->is_rate_limited($account['id'])) {
                $status = __('Rate limited', 'social-integration-for-bluesky');
                $icon = 'clock';
                $color = '#f0b849';
            } else {
                $status = __('Active', 'social-integration-for-bluesky');
                $icon = 'yes-alt';
                $color = '#46b450';
            }

            $health_data['accounts'][] = [
                'handle' => $account['handle'],
                'status' => $status,
                'icon' => $icon,
                'color' => $color
            ];
        }

        // Recent activity from logger
        $logger = new BlueSky_Activity_Logger();
        $health_data['recent_activity'] = $logger->get_recent_events(5);

        return $health_data;
    }

    /**
     * AJAX handler for refresh button
     */
    public function refresh_health_data() {
        check_ajax_referer('bluesky_refresh_health', 'nonce');

        // Clear health data transient to force fresh collection
        delete_transient('bluesky_health_data');

        wp_send_json_success(['message' => __('Health data refreshed', 'social-integration-for-bluesky')]);
    }
}
```

**Key insight:** Manual refresh button clears transient cache and reloads page. Simpler than Heartbeat API polling; user controls when to check status. Transient caching (5 min) prevents excessive DB queries when dashboard is viewed frequently.

### Pattern 3: Site Health Integration

**What:** Register custom health tests (pass/fail checks) and debug info sections in Tools → Site Health

**When to use:** Deep diagnostics for troubleshooting; appears in WordPress core health workflow

**Example:**

```php
// Source: WordPress Site Health API + community examples
// https://developer.wordpress.org/apis/site-health/
// https://gist.github.com/thelovekesh/688c6cffc1668e618c4b4c53039b3dcc
class BlueSky_Health_Monitor {

    public function __construct() {
        // Register health checks
        add_filter('site_status_tests', [$this, 'register_health_tests']);

        // Register debug info
        add_filter('debug_information', [$this, 'register_debug_info']);
    }

    /**
     * Register custom Site Health tests
     */
    public function register_health_tests($tests) {
        // Direct tests (run synchronously)
        $tests['direct']['bluesky_accounts_authenticated'] = [
            'label' => __('Bluesky Accounts Authenticated', 'social-integration-for-bluesky'),
            'test' => [$this, 'test_accounts_authenticated']
        ];

        $tests['direct']['bluesky_circuit_breaker'] = [
            'label' => __('Bluesky API Circuit Breaker', 'social-integration-for-bluesky'),
            'test' => [$this, 'test_circuit_breaker']
        ];

        // Async tests (run via REST API to avoid timeouts)
        $tests['async']['bluesky_api_connection'] = [
            'label' => __('Bluesky API Connection', 'social-integration-for-bluesky'),
            'test' => rest_url('bluesky/v1/health/api-connection'),
            'has_rest' => true,
            'async_direct_test' => [$this, 'test_api_connection']
        ];

        return $tests;
    }

    /**
     * Test: All accounts authenticated
     */
    public function test_accounts_authenticated() {
        $account_manager = new BlueSky_Account_Manager();
        $accounts = $account_manager->get_all_accounts();

        if (empty($accounts)) {
            return [
                'label' => __('No Bluesky accounts configured', 'social-integration-for-bluesky'),
                'status' => 'recommended',
                'badge' => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'blue',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('No Bluesky accounts are currently configured. Add an account to start syndicating posts.', 'social-integration-for-bluesky')
                ),
                'actions' => sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME),
                    __('Configure Accounts', 'social-integration-for-bluesky')
                ),
                'test' => 'bluesky_accounts_authenticated'
            ];
        }

        $expired_accounts = [];
        foreach ($accounts as $account) {
            // Check if credentials are expired (would be tracked via last_auth_error)
            $api = BlueSky_API_Handler::create_for_account($account);
            if (!$api->authenticate()) {
                $expired_accounts[] = $account['handle'];
            }
        }

        if (!empty($expired_accounts)) {
            return [
                'label' => __('Bluesky accounts need re-authentication', 'social-integration-for-bluesky'),
                'status' => 'critical',
                'badge' => [
                    'label' => __('Security', 'social-integration-for-bluesky'),
                    'color' => 'red',
                ],
                'description' => sprintf(
                    '<p>%s</p><ul><li>%s</li></ul>',
                    __('The following Bluesky accounts have expired credentials:', 'social-integration-for-bluesky'),
                    implode('</li><li>', array_map('esc_html', $expired_accounts))
                ),
                'actions' => sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME),
                    __('Update Credentials', 'social-integration-for-bluesky')
                ),
                'test' => 'bluesky_accounts_authenticated'
            ];
        }

        return [
            'label' => __('All Bluesky accounts authenticated', 'social-integration-for-bluesky'),
            'status' => 'good',
            'badge' => [
                'label' => __('Security', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                sprintf(
                    _n(
                        '%d Bluesky account is properly authenticated.',
                        '%d Bluesky accounts are properly authenticated.',
                        count($accounts),
                        'social-integration-for-bluesky'
                    ),
                    count($accounts)
                )
            ),
            'test' => 'bluesky_accounts_authenticated'
        ];
    }

    /**
     * Test: Circuit breaker status
     */
    public function test_circuit_breaker() {
        $account_manager = new BlueSky_Account_Manager();
        $accounts = $account_manager->get_all_accounts();

        $open_breakers = [];
        foreach ($accounts as $account) {
            $breaker = new BlueSky_Circuit_Breaker($account['id']);
            if (!$breaker->is_available()) {
                $open_breakers[] = $account['handle'];
            }
        }

        if (!empty($open_breakers)) {
            return [
                'label' => __('Bluesky API requests paused', 'social-integration-for-bluesky'),
                'status' => 'recommended',
                'badge' => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'orange',
                ],
                'description' => sprintf(
                    '<p>%s</p><p>%s</p>',
                    __('The circuit breaker is currently open for some accounts due to repeated API errors. Requests will resume automatically after the cooldown period.', 'social-integration-for-bluesky'),
                    sprintf(__('Affected accounts: %s', 'social-integration-for-bluesky'), implode(', ', array_map('esc_html', $open_breakers)))
                ),
                'test' => 'bluesky_circuit_breaker'
            ];
        }

        return [
            'label' => __('Bluesky circuit breaker operational', 'social-integration-for-bluesky'),
            'status' => 'good',
            'badge' => [
                'label' => __('Performance', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __('All Bluesky accounts have healthy circuit breaker status.', 'social-integration-for-bluesky')
            ),
            'test' => 'bluesky_circuit_breaker'
        ];
    }

    /**
     * Test: API connection (async)
     */
    public function test_api_connection() {
        // This would be called via REST endpoint or directly
        // Test actual API connectivity
        $account_manager = new BlueSky_Account_Manager();
        $accounts = $account_manager->get_all_accounts();

        if (empty($accounts)) {
            return [
                'label' => __('No accounts to test', 'social-integration-for-bluesky'),
                'status' => 'good',
                'badge' => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'blue',
                ],
                'description' => '<p>' . __('No Bluesky accounts configured.', 'social-integration-for-bluesky') . '</p>',
                'test' => 'bluesky_api_connection'
            ];
        }

        // Test first account
        $account = reset($accounts);
        $api = BlueSky_API_Handler::create_for_account($account);

        if ($api->authenticate()) {
            return [
                'label' => __('Bluesky API responding', 'social-integration-for-bluesky'),
                'status' => 'good',
                'badge' => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'blue',
                ],
                'description' => '<p>' . __('Successfully connected to Bluesky API.', 'social-integration-for-bluesky') . '</p>',
                'test' => 'bluesky_api_connection'
            ];
        }

        return [
            'label' => __('Bluesky API connection failed', 'social-integration-for-bluesky'),
            'status' => 'critical',
            'badge' => [
                'label' => __('Performance', 'social-integration-for-bluesky'),
                'color' => 'red',
            ],
            'description' => '<p>' . __('Could not connect to Bluesky API. This may be temporary network issue or API outage.', 'social-integration-for-bluesky') . '</p>',
            'test' => 'bluesky_api_connection'
        ];
    }

    /**
     * Register debug information
     */
    public function register_debug_info($info) {
        $account_manager = new BlueSky_Account_Manager();
        $accounts = $account_manager->get_all_accounts();

        $info['bluesky'] = [
            'label' => __('Bluesky Integration', 'social-integration-for-bluesky'),
            'fields' => [
                'plugin_version' => [
                    'label' => __('Plugin Version', 'social-integration-for-bluesky'),
                    'value' => BLUESKY_PLUGIN_VERSION,
                ],
                'account_count' => [
                    'label' => __('Connected Accounts', 'social-integration-for-bluesky'),
                    'value' => count($accounts),
                ],
                'api_endpoint' => [
                    'label' => __('API Endpoint', 'social-integration-for-bluesky'),
                    'value' => 'https://bsky.social/xrpc/',
                ],
                'cache_duration' => [
                    'label' => __('Cache Duration', 'social-integration-for-bluesky'),
                    'value' => sprintf(__('%d seconds', 'social-integration-for-bluesky'), get_option(BLUESKY_PLUGIN_OPTIONS)['cache_duration']['total_seconds'] ?? 600),
                ],
                'action_scheduler' => [
                    'label' => __('Action Scheduler Available', 'social-integration-for-bluesky'),
                    'value' => function_exists('as_schedule_single_action') ? __('Yes', 'social-integration-for-bluesky') : __('No', 'social-integration-for-bluesky'),
                ],
            ]
        ];

        // Add per-account circuit breaker and rate limit state
        foreach ($accounts as $account) {
            $breaker = new BlueSky_Circuit_Breaker($account['id']);
            $limiter = new BlueSky_Rate_Limiter();

            $info['bluesky']['fields']['account_' . $account['id'] . '_status'] = [
                'label' => sprintf(__('Account: %s', 'social-integration-for-bluesky'), $account['handle']),
                'value' => sprintf(
                    'Circuit: %s | Rate Limited: %s',
                    $breaker->is_available() ? 'Closed' : 'Open',
                    $limiter->is_rate_limited($account['id']) ? 'Yes' : 'No'
                ),
                'private' => true, // Don't include in copy/paste
            ];
        }

        return $info;
    }
}
```

**Key insight:** Site Health tests have three statuses: `good`, `recommended`, `critical`. Tests must return specific array structure. Use `async` for slow operations (API calls), `direct` for fast checks (local data). `private` fields in debug info excluded from copy/paste text.

### Pattern 4: Error Message Translation

**What:** Map raw API errors to user-friendly messages following UX writing principles

**When to use:** Every place where API errors are displayed to users

**Example:**

```php
// Source: Error message UX best practices
// https://blog.logrocket.com/ux-design/writing-clear-error-messages-ux-guidelines-examples/
// https://www.nngroup.com/articles/error-message-guidelines/
class BlueSky_Error_Translator {

    /**
     * Translate API error to user-friendly message
     *
     * @param array|null $error_data Error data from API handler (code, message, status)
     * @param string $context Context where error occurred ('auth', 'syndication', 'fetch')
     * @return array ['message' => string, 'action' => string|null]
     */
    public static function translate_error($error_data, $context = 'general') {
        if (!$error_data || !isset($error_data['code'])) {
            return self::generic_error($context);
        }

        $code = $error_data['code'];
        $status = $error_data['status'] ?? 0;

        // Authentication errors
        if ($code === 'AuthenticationRequired' || $code === 'InvalidToken' || $status === 401) {
            return [
                'message' => __('We couldn\'t connect to Bluesky. Your credentials may have expired.', 'social-integration-for-bluesky'),
                'action' => [
                    'label' => __('Update Credentials', 'social-integration-for-bluesky'),
                    'url' => admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME)
                ]
            ];
        }

        // Rate limiting
        if ($code === 'RateLimitExceeded' || $status === 429) {
            return [
                'message' => __('Bluesky is temporarily rate limiting requests. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                'action' => null // No user action needed
            ];
        }

        // Network errors
        if ($status === 0 || $status === 503) {
            return [
                'message' => __('Couldn\'t reach Bluesky right now. This is usually temporary. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Invalid handle
        if ($code === 'InvalidHandle') {
            return [
                'message' => __('The Bluesky handle appears to be invalid. Please check that you entered it correctly.', 'social-integration-for-bluesky'),
                'action' => [
                    'label' => __('Check Settings', 'social-integration-for-bluesky'),
                    'url' => admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME)
                ]
            ];
        }

        // Invalid request (400)
        if ($status === 400) {
            return [
                'message' => __('Bluesky couldn\'t process this request. The post content may have an issue.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Generic fallback
        return self::generic_error($context);
    }

    /**
     * Generic error message when specific translation not available
     */
    private static function generic_error($context) {
        $messages = [
            'auth' => __('Something went wrong while connecting to Bluesky. Please try again.', 'social-integration-for-bluesky'),
            'syndication' => __('We couldn\'t post to Bluesky right now. Your post will be retried automatically.', 'social-integration-for-bluesky'),
            'fetch' => __('We couldn\'t load Bluesky content right now. Please try refreshing the page.', 'social-integration-for-bluesky'),
            'general' => __('Something went wrong with the Bluesky connection. Please try again.', 'social-integration-for-bluesky'),
        ];

        return [
            'message' => $messages[$context] ?? $messages['general'],
            'action' => null
        ];
    }

    /**
     * Get circuit breaker status message
     */
    public static function circuit_breaker_message($is_open) {
        if ($is_open) {
            return __('Bluesky requests are paused due to repeated errors. They\'ll resume automatically in 15 minutes.', 'social-integration-for-bluesky');
        }
        return __('Bluesky connection is healthy.', 'social-integration-for-bluesky');
    }
}
```

**Key insight:** Error messages follow "explain + action" pattern. Avoid technical jargon (use "credentials" not "auth tokens"). Avoid blame language ("you entered wrong" → "appears to be invalid"). Keep under 14 words when possible (90% comprehension rate). Distinguish errors needing user action (include link) from transient errors (just explain).

### Anti-Patterns to Avoid

- **Modal dialogs for errors:** Blocks workflow; users dismiss without reading. Use inline notices instead.
- **Technical error codes visible to users:** "Error 429" means nothing; "Rate limited" is clearer.
- **Auto-refresh via Heartbeat for health widget:** Creates unnecessary server load; manual refresh gives user control.
- **Separate admin page for health:** Gets buried; dashboard widget ensures visibility.
- **Countdown timers for retries:** Creates expectation of precision; simple "will retry automatically" is better.
- **One notice per broken account:** Creates banner spam; group into single notice.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Admin notice persistence | Custom session storage or cookies | WordPress User Meta API | Survives logout, multi-device compatible, respects user permissions |
| Dashboard widgets | Custom admin menu page | wp_add_dashboard_widget() | Integrates with WP dashboard layout, screen options, drag-drop ordering |
| Health checks | Custom diagnostics page | Site Health API | Appears in WP core troubleshooting workflow users already know |
| Error message mapping | Inline conditionals everywhere | Centralized translator class | Single source of truth; consistent messaging; easier to update copy |
| Activity logging | Custom database table | Options API with circular buffer | Leverages existing WP infrastructure; auto-cleanup via array rotation |

**Key insight:** WordPress admin UX patterns exist for every surface this phase needs. Custom implementations miss accessibility features (screen reader labels), responsive design, and user familiarity.

## Common Pitfalls

### Pitfall 1: Notice Fatigue from Excessive Banners

**What goes wrong:** Plugin shows separate admin notice for every issue; users have 5+ red banners and start ignoring all of them

**Why it happens:** Developer treats each error independently without considering cumulative UX

**How to avoid:** Group related issues into single notice (e.g., "3 accounts need re-authentication" not 3 separate notices). Make notices dismissible with 24-hour return.

**Warning signs:** Users report "too many notifications", dismiss rate above 90%, no action taken on critical notices

**Source:** [WordPress Admin Notices best practices](https://www.compilenrun.com/docs/framework/wordpress/wordpress-advanced-development/wordpress-admin-notices/) — "Too many notices create notice fatigue where users start ignoring all notices"

### Pitfall 2: Assuming Dismissible = Persistent

**What goes wrong:** Developer adds `is-dismissible` class thinking notice won't reappear; notice returns every page load

**Why it happens:** WordPress `is-dismissible` only provides UI (dismiss button + JavaScript); doesn't store dismissal state

**How to avoid:** Implement AJAX handler that saves dismissal timestamp in user meta; check meta before displaying notice

**Warning signs:** Users complain notice "keeps coming back", dismiss button does nothing permanent

**Code example:**

```php
// BAD - Notice returns every page load
echo '<div class="notice notice-error is-dismissible">';
echo '<p>Error message</p></div>';

// GOOD - Dismissal persists via user meta
$dismissed = get_user_meta(get_current_user_id(), 'my_notice_dismissed', true);
if (!$dismissed || time() > $dismissed) {
    echo '<div class="notice notice-error is-dismissible" data-dismissible="my_notice">';
    echo '<p>Error message</p></div>';
}
```

**Source:** [Persistently Dismissible Notices WordPress](https://www.alexgeorgiou.gr/persistently-dismissible-notices-wordpress/)

### Pitfall 3: Overusing Heartbeat API for Real-Time Updates

**What goes wrong:** Plugin hooks into Heartbeat to auto-refresh health widget every 15-60 seconds; causes unnecessary server load and database queries

**Why it happens:** Developer wants "live" status updates without considering performance cost

**How to avoid:** Use manual refresh button for health widgets; only use Heartbeat for time-sensitive updates where user is actively waiting (e.g., post edit screen showing "Syndicating..." status)

**Warning signs:** Slow admin dashboard, elevated server load on admin pages, frequent DB queries every 15s

**Source:** Phase 3 research + WordPress Heartbeat API docs — Heartbeat runs every 15-60s on admin pages automatically

### Pitfall 4: Insufficient Error Message Context

**What goes wrong:** Generic "Error occurred" message leaves users confused about what happened and what to do

**Why it happens:** Developer just passes through API error without translating to user context

**How to avoid:** Always include: (1) What happened, (2) Why it might have happened, (3) What user should do. Example: "We couldn't post to Bluesky right now. Your credentials may have expired. [Update Credentials]"

**Warning signs:** Support requests asking "what does this error mean?", users don't take corrective action

**Source:** [NN/g Error Message Guidelines](https://www.nngroup.com/articles/error-message-guidelines/) — "Error messages should state what the issue is in plain language, then what the user can do about it"

### Pitfall 5: Exposing Technical Details to Non-Technical Users

**What goes wrong:** Error shows "HTTP 401", "Invalid JWT", "XRpcError" — terms meaningless to WordPress users

**Why it happens:** Developer uses API error codes/messages directly instead of translating

**How to avoid:** Create error translator class that maps technical codes to plain language. Show technical details only in debug logs or Site Health debug info (not primary error messages).

**Warning signs:** Users ask "what is JWT?", screenshots of errors posted to support forums asking for translation

**Source:** [Error Messages and UX Complete Guide](https://darksquare.org/error-messages-and-ux-a-complete-guide/) — "Write error messages in plain language and a conversational tone that anyone can understand. Avoid jargon, technical terms, or vague statements"

### Pitfall 6: Blocking UI During Async Operations

**What goes wrong:** User publishes post, sees spinner forever if syndication is slow/failing

**Why it happens:** Async handler exists but error states don't update UI properly

**How to avoid:** Always show initial "Syndicating..." notice immediately, use Heartbeat to update notice when status changes to completed/failed. Timeout after reasonable period (2-3 min) with "Taking longer than expected" message.

**Warning signs:** Users report "stuck" after publishing, confusion about whether post was syndicated

**Source:** Phase 3 implementation decisions — async handler + Heartbeat status updates

## Code Examples

Verified patterns from WordPress core and documentation:

### Dismissible Admin Notice with Persistence

```php
// Source: https://www.alexgeorgiou.gr/persistently-dismissible-notices-wordpress/
// Register AJAX handler
add_action('wp_ajax_bluesky_dismiss_notice', function() {
    check_ajax_referer('bluesky_dismiss_notice', 'nonce');

    $notice_key = sanitize_key($_POST['notice_key'] ?? '');
    $dismissed_until = time() + DAY_IN_SECONDS; // 24 hours

    update_user_meta(get_current_user_id(), $notice_key, $dismissed_until);
    wp_send_json_success();
});

// Display notice with dismissal check
add_action('admin_notices', function() {
    $notice_key = 'bluesky_example_notice';
    $dismissed_until = get_user_meta(get_current_user_id(), $notice_key, true);

    if ($dismissed_until && time() < $dismissed_until) {
        return; // Still dismissed
    }

    ?>
    <div class="notice notice-error is-dismissible" data-dismissible="<?php echo esc_attr($notice_key); ?>">
        <p><?php _e('Error message here', 'social-integration-for-bluesky'); ?></p>
    </div>

    <script>
    jQuery(document).on('click', '.notice[data-dismissible] .notice-dismiss', function() {
        var notice = jQuery(this).closest('.notice');
        var noticeKey = notice.data('dismissible');

        jQuery.post(ajaxurl, {
            action: 'bluesky_dismiss_notice',
            notice_key: noticeKey,
            nonce: '<?php echo wp_create_nonce('bluesky_dismiss_notice'); ?>'
        });
    });
    </script>
    <?php
});
```

### Dashboard Widget Registration

```php
// Source: https://developer.wordpress.org/apis/dashboard-widgets/
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'bluesky_health',                                    // Widget ID
        __('Bluesky Health', 'social-integration-for-bluesky'), // Title
        'render_bluesky_health_widget'                       // Callback
    );
});

function render_bluesky_health_widget() {
    // Widget content here
    echo '<p>' . __('Health status...', 'social-integration-for-bluesky') . '</p>';
}
```

### Site Health Test Registration

```php
// Source: https://developer.wordpress.org/reference/hooks/site_status_tests/
add_filter('site_status_tests', function($tests) {
    $tests['direct']['bluesky_health'] = [
        'label' => __('Bluesky Connection', 'social-integration-for-bluesky'),
        'test' => 'test_bluesky_connection'
    ];
    return $tests;
});

function test_bluesky_connection() {
    // Test logic here
    $is_healthy = true; // Replace with actual check

    if ($is_healthy) {
        return [
            'label' => __('Bluesky connection active', 'social-integration-for-bluesky'),
            'status' => 'good',
            'badge' => [
                'label' => __('Performance', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => '<p>' . __('Successfully connected to Bluesky.', 'social-integration-for-bluesky') . '</p>',
            'test' => 'bluesky_health'
        ];
    }

    return [
        'label' => __('Bluesky connection failed', 'social-integration-for-bluesky'),
        'status' => 'critical',
        'badge' => [
            'label' => __('Performance', 'social-integration-for-bluesky'),
            'color' => 'red',
        ],
        'description' => '<p>' . __('Could not connect to Bluesky API.', 'social-integration-for-bluesky') . '</p>',
        'actions' => sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME),
            __('Check Settings', 'social-integration-for-bluesky')
        ),
        'test' => 'bluesky_health'
    ];
}
```

### Debug Information Registration

```php
// Source: https://developer.wordpress.org/reference/hooks/debug_information/
add_filter('debug_information', function($info) {
    $info['bluesky'] = [
        'label' => __('Bluesky Integration', 'social-integration-for-bluesky'),
        'fields' => [
            'version' => [
                'label' => __('Plugin Version', 'social-integration-for-bluesky'),
                'value' => BLUESKY_PLUGIN_VERSION,
            ],
            'accounts' => [
                'label' => __('Connected Accounts', 'social-integration-for-bluesky'),
                'value' => 3, // Replace with actual count
            ],
            'api_status' => [
                'label' => __('API Status', 'social-integration-for-bluesky'),
                'value' => 'Healthy', // Replace with actual status
                'private' => false, // Include in copy/paste
            ],
        ]
    ];

    return $info;
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom admin notice HTML in every file | Centralized notice service class with translation layer | Phase 4 (this phase) | Consistent messaging, easier updates, single source of truth |
| No notice persistence | User meta tracking with 24-hour return | WordPress 4.2+ | Reduces notice fatigue while ensuring critical issues resurface |
| Separate health page | Dashboard widget integration | WordPress 2.7+ | Higher visibility, immediate status on login |
| No structured health checks | Site Health API integration | WordPress 5.2+ (2019) | Appears in core WP troubleshooting workflow |
| Generic "error occurred" | Context-aware friendly messages | UX writing best practices 2020+ | Higher recovery rate, reduced support burden |
| Technical error codes shown to users | Error translator with plain language | Modern UX standards | Users understand what happened and what to do |

**Deprecated/outdated:**
- **Showing errors only in logs:** Silent failures confuse users; always surface errors in UI with recovery guidance
- **Color-coded severity levels (red/yellow/blue):** Creates visual noise; message content conveys urgency naturally
- **Auto-refresh via Heartbeat for health data:** Unnecessary server load; manual refresh gives user control
- **Separate notice per account:** Notice spam; group related issues

## Open Questions

1. **Activity Log Storage Duration**
   - What we know: Need last 5-10 syndication events for health widget
   - What's unclear: Best storage mechanism (option vs. custom table vs. post meta aggregation)
   - Recommendation: Use single option with circular array buffer (rotate when > 10 entries); simplest implementation, minimal DB impact

2. **Retry Button Timing**
   - What we know: Phase 3 async handler does automatic 3-attempt retry with exponential backoff
   - What's unclear: Show manual retry immediately or only after auto-retries exhaust?
   - Recommendation: Show after auto-retries complete (3 attempts = ~6-8 minutes total). Showing immediately might encourage unnecessary manual retries before auto-retry completes.

3. **Error Message Copy Edge Cases**
   - What we know: Standard errors (auth, rate limit, network) have clear friendly messages
   - What's unclear: How to handle unknown API errors (new error codes Bluesky may add)
   - Recommendation: Generic fallback message: "Something went wrong with the Bluesky connection. Please try again." Log technical details for debugging.

4. **Health Widget Caching Duration**
   - What we know: Should cache to avoid excessive DB queries
   - What's unclear: Optimal TTL for health widget transient
   - Recommendation: 5 minutes. Balances freshness (status changes visible quickly) with performance (dashboard loads fast). Manual refresh overrides cache when user wants immediate update.

5. **Multi-Account Notice Grouping Threshold**
   - What we know: Group broken accounts into single notice
   - What's unclear: At what count does listing all accounts become overwhelming?
   - Recommendation: Show up to 5 account names explicitly, then "...and 3 more" pattern. Keeps notice readable even with many accounts.

## Sources

### PRIMARY (HIGH confidence)

**WordPress Core APIs:**
- [WordPress Admin Notices Hook](https://developer.wordpress.org/reference/hooks/admin_notices/) - Core admin notice display mechanism
- [WordPress Dashboard Widgets API](https://developer.wordpress.org/apis/dashboard-widgets/) - Official dashboard widget documentation
- [WordPress Site Health API](https://developer.wordpress.org/apis/site-health/) - Site Health tabs and navigation
- [site_status_tests Hook](https://developer.wordpress.org/reference/hooks/site_status_tests/) - Registering custom health checks
- [debug_information Filter](https://developer.wordpress.org/reference/hooks/debug_information/) - Adding debug info sections
- [wp_add_dashboard_widget() Function](https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/) - Dashboard widget registration

**Bluesky API:**
- [Bluesky Rate Limits Documentation](https://docs.bsky.app/docs/advanced-guides/rate-limits) - Official rate limit specs, headers, HTTP 429 responses
- [Bluesky API Discussion: Rate Limits](https://github.com/bluesky-social/atproto/discussions/697) - Community discussion on rate limiting

**UX Writing Best Practices:**
- [NN/g: Error Message Guidelines](https://www.nngroup.com/articles/error-message-guidelines/) - Nielsen Norman Group's error message research
- [LogRocket: Writing Clear Error Messages](https://blog.logrocket.com/ux-design/writing-clear-error-messages-ux-guidelines-examples/) - UX design error messaging principles
- [UX Content Collective: How to Write Error Messages](https://uxcontent.com/how-to-write-error-messages/) - Actionable error message patterns

### SECONDARY (MEDIUM confidence)

**Implementation Patterns:**
- [Persistently Dismissible Notices WordPress](https://www.alexgeorgiou.gr/persistently-dismissible-notices-wordpress/) - User meta persistence pattern
- [GitHub: WP Dismissible Notices Handler](https://github.com/julien731/WP-Dismissible-Notices-Handler) - Library implementing persistent dismissal
- [GitHub: WP Admin Notices](https://github.com/Pressmodo/wp-admin-notices) - Helper library for admin notices
- [How to Register Custom Health Checks in Site Health](https://yourwpweb.com/2025/09/26/how-to-register-custom-health-checks-in-site-health-with-php-in-wordpress/) - 2025 tutorial on Site Health integration
- [GitHub Gist: Add Site Health Tests](https://gist.github.com/thelovekesh/688c6cffc1668e618c4b4c53039b3dcc) - Community examples

**Community Best Practices:**
- [WPBeginner: How to Add Custom Dashboard Widgets](https://www.wpbeginner.com/wp-themes/how-to-add-custom-dashboard-widgets-in-wordpress/) - Dashboard widget tutorial
- [Tanner Record: How to Add Plugin Info to WordPress Site Health](https://www.tannerrecord.com/how-to-add-plugin-info-to-wordpress-site-health/) - Site Health debug info examples
- [Smashing Magazine: Designing Better Error Messages UX](https://www.smashingmagazine.com/2022/08/error-messages-ux-design/) - Error message UX design patterns
- [CXL: Error Messages Best Practices](https://cxl.com/blog/error-messages/) - Conversion-focused error messaging

### TERTIARY (LOW confidence)

- WordPress plugin directory examples (Dashboard Widgets Suite, persist-admin-notices-dismissal) - Used for validation only
- Community forum discussions on Bluesky rate limiting - Verified against official documentation

## Metadata

**Confidence breakdown:**
- WordPress APIs: HIGH - All documented in official WordPress Developer Handbook, core functionality since WP 2.5-5.2
- Bluesky API errors: HIGH - Verified via official Bluesky docs, matches standard HTTP error patterns, confirmed HTTP 429 + rate limit headers
- UX writing patterns: HIGH - Research-backed guidelines from Nielsen Norman Group, LogRocket, multiple authoritative UX sources agree on principles
- Implementation patterns: MEDIUM - Community patterns verified against official WordPress docs; persistent dismissal pattern used by multiple established plugins

**Research date:** 2026-02-19
**Valid until:** 60 days (stable WordPress core APIs; Bluesky API error codes unlikely to change)

---

## RESEARCH COMPLETE

**Phase:** 04 - Error Handling & UX
**Confidence:** HIGH

### Key Findings

- WordPress provides native APIs for all three required surfaces (admin notices, dashboard widgets, Site Health)
- Bluesky API follows standard HTTP error patterns (429 for rate limits, 401 for auth, standard headers)
- User-friendly error messaging follows "explain + action" pattern with plain language, no blame, under 14 words
- Persistent admin notices require custom AJAX handler + user meta storage (WordPress provides UI only)
- Dashboard widgets should use manual refresh (not Heartbeat auto-refresh) to minimize server load
- Site Health integration provides two extension points: `site_status_tests` for pass/fail checks, `debug_information` for diagnostics

### File Created

`.planning/phases/04-error-handling-ux/04-RESEARCH.md`

### Confidence Assessment

| Area | Level | Reason |
|------|-------|--------|
| WordPress APIs | HIGH | Official documentation, core functionality, verified in codebase |
| Bluesky Error Codes | HIGH | Official Bluesky docs, standard HTTP patterns, confirmed via API testing |
| UX Writing Patterns | HIGH | Research-backed from Nielsen Norman Group, multiple authoritative sources |
| Implementation Patterns | MEDIUM | Community patterns verified against official docs, proven in production plugins |

### Open Questions

- Activity log storage: Recommend single option with circular array buffer (simplest, minimal DB impact)
- Retry button timing: Recommend show after auto-retries exhaust (~6-8 min) to avoid premature manual retries
- Error message edge cases: Generic fallback + logging for unknown errors
- Health widget cache TTL: Recommend 5 minutes (balances freshness and performance)
- Multi-account notice threshold: Show up to 5 accounts explicitly, then "...and X more"

### Ready for Planning

Research complete. Planner can now create task files for:
- Enhancing BlueSky_Admin_Notices class with persistent dismissal
- Creating BlueSky_Health_Dashboard class for dashboard widget
- Creating BlueSky_Health_Monitor class for Site Health integration
- Creating BlueSky_Error_Translator class for friendly error messages
- Creating BlueSky_Activity_Logger class for recent event tracking
- Integrating error translator into existing error display points
- Reworking settings page health section
