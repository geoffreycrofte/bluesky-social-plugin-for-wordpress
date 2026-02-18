# Phase 3: Performance & Resilience - Research

**Researched:** 2026-02-18
**Domain:** WordPress async operations, API resilience, caching strategies
**Confidence:** HIGH

## Summary

Phase 3 implements backend resilience infrastructure for the Bluesky plugin: async syndication via WordPress background jobs, circuit breaker pattern for API failure protection, exponential backoff for rate limit handling, and request-level cache deduplication. The research reveals that **Action Scheduler is the clear choice over WP-Cron** for async operations due to its persistent queue, retry logic, and proven track record processing millions of critical operations monthly. The Bluesky API provides generous rate limits (5,000 points/hour for writes) with standard HTTP 429 responses and rate limit headers, making detection straightforward. Circuit breaker implementations should use **per-account scoping with transient storage** for simplicity, and request-level deduplication can leverage PHP static variables for same-page API call prevention.

**Primary recommendation:** Use Action Scheduler for async syndication with per-account circuit breakers stored in transients, respect Bluesky's rate limit headers with exponential backoff (60s, 120s, 300s), and implement static variable caching for request-level deduplication while enhancing existing transient-based caching with stale-while-revalidate semantics.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Async Syndication Behavior:**
- Post publishes instantly, then a non-blocking admin notice says "Syndicating to Bluesky..." that updates when done
- Auto-retry up to 3 times with increasing delay on failure, then mark as failed and surface a manual "Retry" button in post list

**Rate Limit & Backoff Strategy:**
- Respect Bluesky's Retry-After header when available, fall back to own exponential backoff schedule if header absent
- When rate-limited on frontend (blocks/widgets): serve stale cached data with a subtle "last updated X ago" indicator — never show empty/error state if cache exists

**Circuit Breaker Policy:**
- Fixed thresholds: 3 consecutive failures triggers 15-minute cooldown (not admin-configurable)
- Circuit breaker scope: per-account — one account's failures don't block others
- When breaker is open: queue syndication requests and retry after cooldown (no data loss)

**Request Deduplication & Caching:**
- Same-page dedup: in-memory per-request cache (static variable during page render) prevents duplicate API calls for multiple blocks
- Also optimize existing transient cache strategy (review and improve, not just same-page dedup)
- Stale-while-revalidate pattern: serve cached data immediately, refresh in background
- Cache TTL: reuse the existing admin settings page cache duration option; default to 10 minutes if not set

### Claude's Discretion

- Async mechanism choice (WP-Cron vs Action Scheduler)
- Multi-account syndication order (parallel vs sequential)
- Rate limit tracking scope (per-account vs global)
- Rate limit state persistence method
- Circuit breaker recovery mechanism (auto vs half-open probe)
- Exact backoff schedule timing
- Stale-while-revalidate implementation approach
- Admin notice update mechanism (AJAX polling, heartbeat, or page reload)

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope

</user_constraints>

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Action Scheduler | 3.8+ | Async background job queue | Industry standard for WordPress async jobs; used by WooCommerce for millions of payments monthly; persistent queue with retry logic |
| WordPress Transient API | Core | Caching layer for API responses | Built-in WordPress caching; supports all major object cache backends (Redis, Memcached) |
| WordPress Heartbeat API | Core | Real-time admin notice updates | Core WordPress feature for live dashboard updates; runs every 15-60s on admin pages |

**Note:** Circuit breaker implementation will be custom-built using transients for state storage rather than adding an external PHP library dependency, as the plugin has no Composer dependencies and aims to remain dependency-free.

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP-Cron | Core | Fallback async mechanism | Only if Action Scheduler unavailable (unlikely on WordPress 5.0+) |
| wp_remote_get/post | Core | HTTP client with built-in retry | Already in use; examine response headers for rate limit info |

### Why Not WP-Cron?

| Issue | Problem | Action Scheduler Solution |
|-------|---------|--------------------------|
| **Trigger dependency** | Only runs on page visits; low-traffic sites see delayed execution | Independent queue runner; processes jobs reliably regardless of traffic |
| **No persistence** | Jobs lost on server restart or crash | Persistent database queue; jobs survive crashes |
| **No retry logic** | Silent failures with no recovery mechanism | Built-in retry with configurable attempts and backoff |
| **Overlapping execution** | High-traffic sites trigger same job multiple times | Batch processing with locking prevents overlap |
| **No failure tracking** | Zero visibility into failed jobs | Full audit trail of job status and failures |

**Sources:**
- [Action Scheduler FAQ](https://actionscheduler.org/faq/)
- [Action Scheduler WordPress.org Plugin](https://wordpress.org/plugins/action-scheduler/)
- [WordPress VIP: Action Scheduler](https://docs.wpvip.com/wordpress-on-vip/action-scheduler/)
- [Replacing WordPress Cron Jobs with Action Scheduler](https://bookingwp.com/wordpress-cron-action-scheduler/)

**Installation:**

Action Scheduler can be embedded directly in the plugin (recommended approach used by WooCommerce):

```bash
# No external installation needed - bundle Action Scheduler library directly in plugin
# Drop action-scheduler directory into includes/ and require the autoloader
```

## Architecture Patterns

### Recommended Project Structure

```
classes/
├── BlueSky_Async_Handler.php           # Schedules/processes async syndication jobs
├── BlueSky_Circuit_Breaker.php         # Per-account circuit breaker implementation
├── BlueSky_Rate_Limiter.php            # Rate limit detection and exponential backoff
├── BlueSky_Request_Cache.php           # Static variable cache for same-page dedup
└── BlueSky_Syndication_Queue.php       # Manages syndication retry queue
```

### Pattern 1: Action Scheduler Job Queue

**What:** Schedule syndication as async background job that runs independently of page requests

**When to use:** Every post publish event in multi-account mode

**Example:**
```php
// Source: Action Scheduler documentation + plugin codebase patterns
class BlueSky_Async_Handler {

    /**
     * Schedule async syndication job
     */
    public function schedule_syndication($post_id, $account_ids) {
        // Check if Action Scheduler is available
        if (!function_exists('as_schedule_single_action')) {
            error_log('Action Scheduler not available, falling back to immediate syndication');
            return $this->syndicate_immediately($post_id, $account_ids);
        }

        // Schedule job to run immediately in background
        as_schedule_single_action(
            time(),
            'bluesky_async_syndicate',
            [
                'post_id' => $post_id,
                'account_ids' => $account_ids,
                'attempt' => 1
            ],
            'bluesky-syndication'
        );

        // Store job reference in post meta for status tracking
        update_post_meta($post_id, '_bluesky_syndication_status', 'pending');
        update_post_meta($post_id, '_bluesky_syndication_scheduled', time());
    }

    /**
     * Process async syndication job
     */
    public function process_syndication($post_id, $account_ids, $attempt = 1) {
        foreach ($account_ids as $account_id) {
            // Check circuit breaker before attempting
            $breaker = new BlueSky_Circuit_Breaker($account_id);
            if (!$breaker->is_available()) {
                // Queue for retry after cooldown
                $this->queue_retry($post_id, $account_id, $attempt);
                continue;
            }

            // Attempt syndication
            $api = BlueSky_API_Handler::create_for_account($account);
            $result = $api->syndicate_post_to_bluesky(...);

            if ($result === false) {
                $breaker->record_failure();

                // Retry logic
                if ($attempt < 3) {
                    $this->schedule_retry($post_id, $account_id, $attempt + 1);
                } else {
                    // Mark as failed after 3 attempts
                    $this->mark_failed($post_id, $account_id);
                }
            } else {
                $breaker->record_success();
                $this->mark_success($post_id, $account_id, $result);
            }
        }
    }

    /**
     * Schedule retry with exponential backoff
     */
    private function schedule_retry($post_id, $account_id, $attempt) {
        $delays = [60, 120, 300]; // 1min, 2min, 5min
        $delay = $delays[$attempt - 1] ?? 300;

        as_schedule_single_action(
            time() + $delay,
            'bluesky_async_syndicate',
            [
                'post_id' => $post_id,
                'account_ids' => [$account_id],
                'attempt' => $attempt
            ],
            'bluesky-syndication'
        );
    }
}
```

### Pattern 2: Circuit Breaker with Transient Storage

**What:** Track per-account failure rate and open circuit after threshold to prevent cascading failures

**When to use:** Wrap all Bluesky API calls

**Example:**
```php
// Pattern inspired by Ganesha circuit breaker architecture
class BlueSky_Circuit_Breaker {
    private $account_id;
    private $failure_threshold = 3;     // Open after 3 failures
    private $cooldown_seconds = 900;    // 15 minutes

    public function __construct($account_id) {
        $this->account_id = $account_id;
    }

    /**
     * Check if requests are allowed
     */
    public function is_available() {
        $state = $this->get_state();

        if ($state['status'] === 'open') {
            // Check if cooldown has expired
            if (time() >= $state['open_until']) {
                // Transition to half-open for testing
                $this->set_state('half_open', time());
                return true;
            }
            return false; // Circuit still open
        }

        return true; // Closed or half-open
    }

    /**
     * Record successful request
     */
    public function record_success() {
        $state = $this->get_state();

        if ($state['status'] === 'half_open') {
            // Successful request in half-open closes circuit
            $this->set_state('closed', 0);
        }

        // Reset failure count on success
        delete_transient($this->get_failure_key());
    }

    /**
     * Record failed request
     */
    public function record_failure() {
        $failure_count = (int) get_transient($this->get_failure_key());
        $failure_count++;

        set_transient($this->get_failure_key(), $failure_count, HOUR_IN_SECONDS);

        if ($failure_count >= $this->failure_threshold) {
            // Open circuit
            $this->set_state('open', time() + $this->cooldown_seconds);

            // Log for monitoring
            error_log(sprintf(
                'Bluesky: Circuit breaker opened for account %s after %d failures',
                $this->account_id,
                $failure_count
            ));
        }
    }

    private function get_state() {
        $state = get_transient($this->get_state_key());
        if ($state === false) {
            return ['status' => 'closed', 'open_until' => 0];
        }
        return $state;
    }

    private function set_state($status, $open_until) {
        set_transient(
            $this->get_state_key(),
            ['status' => $status, 'open_until' => $open_until],
            DAY_IN_SECONDS
        );
    }

    private function get_state_key() {
        return 'bluesky_circuit_' . $this->account_id;
    }

    private function get_failure_key() {
        return 'bluesky_failures_' . $this->account_id;
    }
}
```

### Pattern 3: Rate Limit Detection & Exponential Backoff

**What:** Detect HTTP 429 responses, extract Retry-After header, and implement exponential backoff schedule

**When to use:** Wrap all API calls in API handler

**Example:**
```php
// Based on Bluesky rate limit documentation
class BlueSky_Rate_Limiter {

    /**
     * Check response for rate limiting and handle accordingly
     *
     * @param array|WP_Error $response wp_remote_* response
     * @param string $account_id Account identifier
     * @return bool True if rate limited
     */
    public function check_rate_limit($response, $account_id) {
        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);

        if ($status === 429) {
            // Extract Retry-After header (seconds or HTTP date)
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');

            if (!empty($retry_after)) {
                // Parse retry-after (could be seconds or HTTP date)
                $wait_seconds = $this->parse_retry_after($retry_after);
            } else {
                // Fallback to exponential backoff if no header
                $attempt = $this->get_retry_attempt($account_id);
                $wait_seconds = $this->calculate_backoff($attempt);
            }

            // Store rate limit state
            $this->set_rate_limited($account_id, $wait_seconds);

            error_log(sprintf(
                'Bluesky: Rate limited for account %s, retry after %d seconds',
                $account_id,
                $wait_seconds
            ));

            return true;
        }

        // Also check for rate limit headers even on success
        $remaining = wp_remote_retrieve_header($response, 'ratelimit-remaining');
        if ($remaining !== '' && (int)$remaining < 10) {
            error_log(sprintf(
                'Bluesky: Rate limit warning for account %s, only %d requests remaining',
                $account_id,
                $remaining
            ));
        }

        return false;
    }

    /**
     * Check if account is currently rate limited
     */
    public function is_rate_limited($account_id) {
        $limit_until = get_transient('bluesky_rate_limit_' . $account_id);

        if ($limit_until === false) {
            return false;
        }

        if (time() >= $limit_until) {
            delete_transient('bluesky_rate_limit_' . $account_id);
            return false;
        }

        return true;
    }

    /**
     * Get seconds until rate limit expires
     */
    public function get_retry_after($account_id) {
        $limit_until = get_transient('bluesky_rate_limit_' . $account_id);
        if ($limit_until === false) {
            return 0;
        }
        return max(0, $limit_until - time());
    }

    /**
     * Calculate exponential backoff delay
     * Using delays: 60s, 120s, 300s (1min, 2min, 5min)
     */
    private function calculate_backoff($attempt) {
        $base_delays = [60, 120, 300];
        $delay = $base_delays[min($attempt - 1, 2)] ?? 300;

        // Add jitter (±20%) to prevent thundering herd
        $jitter = $delay * 0.2 * (mt_rand(0, 200) - 100) / 100;
        return (int)($delay + $jitter);
    }

    private function parse_retry_after($retry_after) {
        // Could be seconds or HTTP date format
        if (is_numeric($retry_after)) {
            return (int)$retry_after;
        }

        $timestamp = strtotime($retry_after);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        // Fallback to default
        return 60;
    }

    private function set_rate_limited($account_id, $wait_seconds) {
        set_transient(
            'bluesky_rate_limit_' . $account_id,
            time() + $wait_seconds,
            $wait_seconds + 60 // Add buffer to TTL
        );

        // Increment retry attempt counter
        $attempts = (int) get_transient('bluesky_rate_attempts_' . $account_id);
        set_transient(
            'bluesky_rate_attempts_' . $account_id,
            $attempts + 1,
            HOUR_IN_SECONDS
        );
    }

    private function get_retry_attempt($account_id) {
        return (int) get_transient('bluesky_rate_attempts_' . $account_id) ?: 1;
    }
}
```

### Pattern 4: Request-Level Cache Deduplication

**What:** Use static variables to cache API results during a single page request, preventing duplicate calls when multiple blocks/shortcodes render

**When to use:** All API fetch methods (profile, posts)

**Example:**
```php
// Based on WordPress WP_Object_Cache pattern and static caching research
class BlueSky_Request_Cache {
    private static $cache = [];

    /**
     * Get cached value if exists
     */
    public static function get($key) {
        return self::$cache[$key] ?? null;
    }

    /**
     * Set cached value
     */
    public static function set($key, $value) {
        self::$cache[$key] = $value;
    }

    /**
     * Check if key exists
     */
    public static function has($key) {
        return isset(self::$cache[$key]);
    }

    /**
     * Build cache key from parameters
     */
    public static function build_key($method, $params) {
        return 'bluesky_' . $method . '_' . md5(serialize($params));
    }
}

// Usage in API Handler:
class BlueSky_API_Handler {
    public function fetch_bluesky_posts($limit = 10, $no_replies = true, $no_reposts = true) {
        // Check request-level cache first
        $request_cache_key = BlueSky_Request_Cache::build_key('posts', [
            'account_id' => $this->account_id,
            'limit' => $limit,
            'no_replies' => $no_replies,
            'no_reposts' => $no_reposts
        ]);

        if (BlueSky_Request_Cache::has($request_cache_key)) {
            return BlueSky_Request_Cache::get($request_cache_key);
        }

        // Then check transient cache (existing code)...
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers->get_posts_transient_key(
            $this->account_id,
            $limit,
            $no_replies,
            $no_reposts
        );

        $cached_posts = get_transient($cache_key);
        if ($cached_posts !== false) {
            // Store in request cache too
            BlueSky_Request_Cache::set($request_cache_key, $cached_posts);
            return $cached_posts;
        }

        // ... existing API call code ...

        // Store in both caches
        if ($cache_duration > 0) {
            set_transient($cache_key, $processed_posts, $cache_duration);
        }
        BlueSky_Request_Cache::set($request_cache_key, $processed_posts);

        return $processed_posts;
    }
}
```

### Pattern 5: Stale-While-Revalidate Cache Strategy

**What:** Serve cached data immediately even if expired, schedule background refresh via Action Scheduler

**When to use:** Frontend API calls (blocks, widgets, shortcodes) where user experience matters more than absolute freshness

**Example:**
```php
// Inspired by 10up Async-Transients pattern
class BlueSky_API_Handler {

    public function fetch_bluesky_posts_with_stale($limit = 10, $no_replies = true, $no_reposts = true) {
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers->get_posts_transient_key(
            $this->account_id,
            $limit,
            $no_replies,
            $no_reposts
        );
        $cache_duration = $this->options['cache_duration']['total_seconds'] ?? 600; // Default 10 min

        // Check request cache first
        $request_cache_key = BlueSky_Request_Cache::build_key('posts', [
            'account_id' => $this->account_id,
            'limit' => $limit,
            'no_replies' => $no_replies,
            'no_reposts' => $no_reposts
        ]);

        if (BlueSky_Request_Cache::has($request_cache_key)) {
            return BlueSky_Request_Cache::get($request_cache_key);
        }

        // Check transient cache
        $cached_data = get_transient($cache_key);
        $freshness_key = $cache_key . '_fresh';
        $is_fresh = get_transient($freshness_key) !== false;

        if ($cached_data !== false) {
            // We have cached data
            if (!$is_fresh && !$this->is_refresh_scheduled($cache_key)) {
                // Data is stale and no refresh scheduled - schedule background refresh
                $this->schedule_background_refresh($cache_key, $limit, $no_replies, $no_reposts);
            }

            // Return cached data (even if stale)
            BlueSky_Request_Cache::set($request_cache_key, $cached_data);
            return $cached_data;
        }

        // No cache exists - fetch synchronously
        return $this->fetch_bluesky_posts($limit, $no_replies, $no_reposts);
    }

    /**
     * Schedule background cache refresh
     */
    private function schedule_background_refresh($cache_key, $limit, $no_replies, $no_reposts) {
        if (!function_exists('as_schedule_single_action')) {
            return; // Action Scheduler not available
        }

        as_schedule_single_action(
            time(),
            'bluesky_refresh_cache',
            [
                'account_id' => $this->account_id,
                'cache_key' => $cache_key,
                'limit' => $limit,
                'no_replies' => $no_replies,
                'no_reposts' => $no_reposts
            ],
            'bluesky-cache-refresh'
        );

        // Mark as scheduled to prevent duplicate refresh jobs
        set_transient($cache_key . '_refreshing', true, 300); // 5 min lock
    }

    private function is_refresh_scheduled($cache_key) {
        return get_transient($cache_key . '_refreshing') !== false;
    }

    /**
     * Background job to refresh stale cache
     */
    public function refresh_cache_background($account_id, $cache_key, $limit, $no_replies, $no_reposts) {
        // Fetch fresh data
        $fresh_data = $this->fetch_bluesky_posts($limit, $no_replies, $no_reposts);

        if ($fresh_data !== false) {
            // Update cache with fresh data
            $cache_duration = $this->options['cache_duration']['total_seconds'] ?? 600;
            set_transient($cache_key, $fresh_data, $cache_duration * 2); // Extended TTL for stale fallback
            set_transient($cache_key . '_fresh', true, $cache_duration); // Freshness marker
        }

        // Clear refresh lock
        delete_transient($cache_key . '_refreshing');
    }
}
```

### Pattern 6: Non-Blocking Admin Notice Updates

**What:** Display "Syndicating to Bluesky..." notice that updates to success/failure via WordPress Heartbeat API

**When to use:** Post publish page after async syndication scheduled

**Example:**
```php
// Admin notice with Heartbeat API integration
class BlueSky_Admin_Notices {

    public function __construct() {
        add_action('admin_notices', [$this, 'syndication_status_notice']);
        add_filter('heartbeat_received', [$this, 'check_syndication_status'], 10, 2);
    }

    /**
     * Display syndication status notice
     */
    public function syndication_status_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'post') {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $status = get_post_meta($post->ID, '_bluesky_syndication_status', true);

        if ($status === 'pending') {
            ?>
            <div class="notice notice-info bluesky-syndication-notice" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <p>
                    <span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
                    <?php esc_html_e('Syndicating to Bluesky...', 'social-integration-for-bluesky'); ?>
                </p>
            </div>
            <?php
        } elseif ($status === 'completed') {
            $accounts = json_decode(get_post_meta($post->ID, '_bluesky_syndication_accounts_completed', true), true);
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('Successfully syndicated to %d Bluesky account(s).', 'social-integration-for-bluesky'),
                        count($accounts ?? [])
                    );
                    ?>
                </p>
            </div>
            <?php
        } elseif ($status === 'failed') {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <?php esc_html_e('Syndication to Bluesky failed. ', 'social-integration-for-bluesky'); ?>
                    <a href="#" class="bluesky-retry-syndication" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Retry now', 'social-integration-for-bluesky'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Check syndication status via Heartbeat API
     */
    public function check_syndication_status($response, $data) {
        if (empty($data['bluesky_check_syndication'])) {
            return $response;
        }

        $post_id = (int) $data['bluesky_check_syndication'];
        $status = get_post_meta($post_id, '_bluesky_syndication_status', true);

        $response['bluesky_syndication'] = [
            'post_id' => $post_id,
            'status' => $status,
            'timestamp' => time()
        ];

        if ($status === 'completed') {
            $accounts = json_decode(get_post_meta($post_id, '_bluesky_syndication_accounts_completed', true), true);
            $response['bluesky_syndication']['accounts'] = count($accounts ?? []);
        }

        return $response;
    }
}

// JavaScript to handle Heartbeat updates
?>
<script>
jQuery(document).ready(function($) {
    var $notice = $('.bluesky-syndication-notice');
    if ($notice.length) {
        var postId = $notice.data('post-id');

        // Hook into Heartbeat API
        $(document).on('heartbeat-send', function(e, data) {
            data.bluesky_check_syndication = postId;
        });

        $(document).on('heartbeat-tick', function(e, data) {
            if (data.bluesky_syndication && data.bluesky_syndication.post_id == postId) {
                if (data.bluesky_syndication.status === 'completed') {
                    // Update notice to success
                    $notice.removeClass('notice-info').addClass('notice-success is-dismissible');
                    $notice.html('<p>' +
                        '<?php esc_html_e('Successfully syndicated to Bluesky.', 'social-integration-for-bluesky'); ?>' +
                    '</p>');
                } else if (data.bluesky_syndication.status === 'failed') {
                    // Update notice to error
                    $notice.removeClass('notice-info').addClass('notice-error is-dismissible');
                    $notice.html('<p>' +
                        '<?php esc_html_e('Syndication to Bluesky failed.', 'social-integration-for-bluesky'); ?>' +
                        ' <a href="#" class="bluesky-retry-syndication" data-post-id="' + postId + '">' +
                        '<?php esc_html_e('Retry now', 'social-integration-for-bluesky'); ?>' +
                        '</a></p>');
                }
            }
        });
    }
});
</script>
```

### Anti-Patterns to Avoid

- **Using WP-Cron for critical async operations:** WP-Cron is unreliable on low-traffic sites and has no persistence or retry logic
- **Global circuit breaker:** Would cause one account's failures to block all accounts; use per-account scoping instead
- **Ignoring Retry-After headers:** Bluesky provides specific timing guidance; respect it to avoid extended rate limiting
- **Blocking post publish on syndication:** User experience degradation; always syndicate async
- **Empty/error states when cache exists:** Users prefer stale data over nothing; serve cached data with timestamp indicator
- **No jitter in exponential backoff:** Creates thundering herd problem when many requests retry simultaneously

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Async job queue | Custom WP-Cron wrapper with database tracking | Action Scheduler | Proven at scale (50K+ jobs/hour), battle-tested by WooCommerce, handles edge cases you haven't thought of |
| HTTP retry logic | Custom retry loops with sleep() | wp_remote_* with proper error handling | WordPress handles connection issues, timeouts, and response parsing reliably |
| Cache invalidation | Manual transient deletion tracking | WordPress Transient API with TTL | Auto-expiration, database cleanup, object cache backend support built-in |
| Admin live updates | Custom AJAX polling loop | WordPress Heartbeat API | Optimized interval, shared across plugins, battery-aware on mobile |

**Key insight:** WordPress provides mature, tested solutions for all these problems. Custom implementations add maintenance burden and miss edge cases that core has handled for years. The only component we're custom-building is the circuit breaker, because existing PHP libraries (Ganesha, eljam/circuit-breaker) require Composer dependencies, and the plugin aims to remain dependency-free. A simple transient-based circuit breaker fits the plugin's architecture better.

## Common Pitfalls

### Pitfall 1: Action Scheduler Timestamp Confusion

**What goes wrong:** Using `time()` schedules jobs for immediate execution, but Action Scheduler's queue runner only processes on the next run cycle (15-60s delay). Developers expect "immediate" to mean instant, leading to confusion when jobs don't run immediately.

**Why it happens:** Action Scheduler processes jobs in batches during queue runner cycles, not synchronously when scheduled.

**How to avoid:**
- Schedule with `time()` for "as soon as possible" (next queue run)
- Use `time() + DELAY` for explicit delays
- Don't expect millisecond precision; Action Scheduler is for background tasks, not real-time operations
- For truly immediate execution in tests, manually trigger `ActionScheduler::runner()->run()`

**Warning signs:** Jobs scheduled but not executing; "immediate" jobs taking 15-60s to run in production

**Source:** [Action Scheduler FAQ](https://actionscheduler.org/faq/)

### Pitfall 2: Transient Bloat from Unique Cache Keys

**What goes wrong:** Creating unique transient keys for every parameter combination (limit, filters, layouts) causes database bloat. WordPress stores transients as autoloaded options by default, slowing down every page load.

**Why it happens:** Each unique combination of parameters generates a new transient key, and expired transients aren't auto-deleted from the database.

**How to avoid:**
- **Limit parameter combinations:** Don't include minor variations (e.g., layout) in cache keys unless absolutely necessary
- **Use consistent defaults:** Normalize parameters to reduce key variations
- **Set reasonable TTLs:** Don't cache forever; transients should expire naturally
- **Periodic cleanup:** Use `delete_expired_transients()` or a plugin like [Transient Cleaner](https://wordpress.org/plugins/transient-cleaner/)
- **Consider object cache:** For high-traffic sites, use Redis/Memcached which don't have autoload issues

**Warning signs:** Large `wp_options` table; slow page loads despite caching; hundreds of `_transient_` rows in database

**Source:** [The Art of the WordPress Transient](https://deliciousbrains.com/the-art-of-the-wordpress-transient/)

### Pitfall 3: Heartbeat API Performance Impact

**What goes wrong:** Heartbeat API runs every 15-60 seconds, sending POST requests to `admin-ajax.php`. On shared hosting or resource-constrained servers, this can consume PHP workers and slow down the entire admin experience.

**Why it happens:** Every active admin session runs its own heartbeat loop. With multiple logged-in admins, this creates constant background load.

**How to avoid:**
- **Only send data when needed:** Don't add heartbeat data on every page; check context first
- **Throttle on plugin side:** Only check syndication status for posts that are actively syndicating (status='pending')
- **Clean up when done:** Remove heartbeat listeners once syndication completes
- **Let users disable:** Popular caching plugins (WP Rocket, Heartbeat Control) let users adjust intervals or disable Heartbeat

**Warning signs:** High `admin-ajax.php` usage; slow admin page loads; PHP worker exhaustion on shared hosting

**Source:** [Taming the Heartbeat API](https://deliciousbrains.com/taming-the-heartbeat-api/)

### Pitfall 4: Circuit Breaker False Positives

**What goes wrong:** Circuit opens during legitimate API maintenance or network blips, blocking valid requests for 15 minutes even after service recovers.

**Why it happens:** Fixed failure thresholds don't account for transient issues vs systemic failures.

**How to avoid:**
- **Half-open state testing:** After cooldown, allow one test request to check if service recovered before fully closing circuit
- **Failure count decay:** Reset failure count on first success, not gradually
- **Separate error types:** Don't count 401 (bad credentials) the same as 503 (service down); different handling strategies
- **User visibility:** Log circuit breaker events so admins know why syndication stopped

**Warning signs:** Circuit stays open despite service recovery; users report "syndication stopped working" after brief API hiccup

**Source:** [Building Resilient Systems: Circuit Breakers and Retry Patterns](https://dasroot.net/posts/2026/01/building-resilient-systems-circuit-breakers-retry-patterns/)

### Pitfall 5: Rate Limit Tracking Scope Confusion

**What goes wrong:** Using global rate limit tracking when Bluesky enforces per-account limits, or vice versa. One account hits limit and incorrectly blocks all accounts.

**Why it happens:** Unclear API documentation; developers make assumptions about limit scope.

**How to avoid:**
- **Verify with documentation:** Bluesky enforces per-DID (account) limits: 5,000 points/hour per account
- **Per-account transient keys:** Use `bluesky_rate_limit_{account_id}` not `bluesky_rate_limit_global`
- **Test with multiple accounts:** Verify one rate-limited account doesn't block others
- **Log rate limit headers:** Monitor `ratelimit-remaining` per account to see independent tracking

**Warning signs:** One account's rate limit blocks all accounts; rate limit hits don't match documented per-account limits

**Source:** [Bluesky Rate Limits Documentation](https://docs.bsky.app/docs/advanced-guides/rate-limits)

### Pitfall 6: Stale-While-Revalidate Race Conditions

**What goes wrong:** Multiple concurrent requests for stale cache trigger multiple background refresh jobs, wasting resources and potentially hitting rate limits.

**Why it happens:** No locking mechanism to prevent duplicate refresh scheduling.

**How to avoid:**
- **Refresh lock transient:** Set `{cache_key}_refreshing` transient when scheduling refresh
- **Check lock before scheduling:** Skip refresh if lock exists
- **TTL on lock:** Set lock TTL to expected refresh duration + buffer (e.g., 5 minutes) so stale locks don't persist
- **Action Scheduler's built-in deduplication:** Action Scheduler won't schedule duplicate pending jobs if you use consistent job names

**Warning signs:** Multiple refresh jobs scheduled for same cache key; rate limit hits from refresh floods; database queue bloat

**Source:** [10up Async-Transients](https://github.com/10up/Async-Transients)

## Code Examples

All patterns shown above with full working implementations.

## Bluesky API Rate Limits

### Write Operations (Per Account)

| Operation | Cost | Hourly Limit | Daily Limit |
|-----------|------|--------------|-------------|
| **CREATE** (post, like, follow) | 3 points | ~1,666 creates | ~11,666 creates |
| **UPDATE** (profile, handle) | 2 points | ~2,500 updates | ~17,500 updates |
| **DELETE** (post, like) | 1 point | ~5,000 deletes | ~35,000 deletes |

**Total budget:** 5,000 points/hour; 35,000 points/day per account (DID)

### Read Operations (Hosted PDS)

| Endpoint | Rate Limit | Scope |
|----------|------------|-------|
| **Overall API** | 3,000 requests per 5 minutes | Per IP |
| **Session creation** | 30 per 5 min / 300 per day | Per account |
| **Handle updates** | 10 per 5 min / 50 per day | Per account |

### AppView Endpoints

Public endpoints (`api.bsky.app`, `public.api.bsky.app`) have "generous rate limits" with no documented specifics. No authentication required for public data.

### HTTP Headers

Responses include standard rate limit headers:
- `ratelimit-limit`: Total requests allowed in time window
- `ratelimit-remaining`: Requests remaining in current window
- `ratelimit-reset`: Timestamp when limit resets
- `ratelimit-policy`: Policy description (e.g., "5000;w=3600" = 5000 per hour)
- `retry-after`: Seconds to wait before retry (on HTTP 429)

**Sources:**
- [Bluesky Rate Limits Documentation](https://docs.bsky.app/docs/advanced-guides/rate-limits)
- [AT Protocol Rate Limits Discussion](https://github.com/bluesky-social/atproto/discussions/697)

### Implications for Plugin

1. **Syndication rate:** 1,666 posts/hour per account = ~28 posts/minute. Far exceeds typical WordPress blog publishing frequency.
2. **Multi-account:** Each account has independent 5K point/hour budget; no global pool.
3. **Frontend displays:** Use generous AppView limits for public data (profile, posts); no auth needed.
4. **Exponential backoff:** Start with Retry-After header value; fallback to 60s, 120s, 300s schedule if absent.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WP-Cron for async | Action Scheduler library | 2017+ (WooCommerce adoption) | Persistent queue, retry logic, audit trail |
| Manual cache invalidation | Transient TTL + stale-while-revalidate | 2018+ (PWA patterns) | Better UX; users see stale data instead of loading spinners |
| Polling for status updates | WordPress Heartbeat API | Core since WP 3.6 (2013) | Standardized interval, battery-aware, shared across plugins |
| External queue services (RabbitMQ, Redis Queue) | Embedded Action Scheduler | 2020+ (simplification trend) | No external dependencies; works on shared hosting |
| Custom circuit breaker per plugin | Standardized patterns (but no WP core solution) | 2020+ (microservices influence) | Consistent failure handling; still no blessed WordPress library |

**Deprecated/outdated:**
- **WP-Cron for critical operations:** Still used for non-critical tasks (auto-updates, scheduled posts) but shouldn't be relied upon for user-facing features
- **Synchronous post publish hooks:** Modern plugins use async patterns to avoid blocking UI
- **Silent failures:** Error tracking and user notifications are now expected, not optional

## Open Questions

1. **Multi-account syndication order: parallel vs sequential?**
   - What we know: Sequential is simpler (loop over accounts); parallel is faster (concurrent HTTP requests)
   - What's unclear: Does Bluesky API have undocumented per-IP limits that parallel requests could hit?
   - Recommendation: Start with **sequential syndication** for safety. Bluesky's per-account limits (5K points/hour) are generous enough that sequential is fast enough for typical use. Parallel adds complexity (managing concurrent wp_remote_post calls) with minimal benefit for typical 2-3 account setups. Consider parallel only if user testing shows unacceptable delays (>5s) for 5+ accounts.

2. **Circuit breaker recovery: auto-recover vs half-open probe?**
   - What we know: Auto-recover immediately closes circuit after cooldown; half-open sends test request first
   - What's unclear: Which pattern better handles intermittent Bluesky API issues?
   - Recommendation: Use **half-open state** pattern. After 15-minute cooldown, allow one test request before fully closing circuit. If test fails, reopen and extend cooldown. This prevents premature closure during extended outages while still allowing quick recovery from transient issues. Implementation shown in circuit breaker pattern above.

3. **Exact backoff schedule timing?**
   - What we know: User specified "increasing delay" for 3 retries
   - What's unclear: Specific seconds for each retry attempt
   - Recommendation: **60s, 120s, 300s** (1min, 2min, 5min). This matches common exponential backoff patterns, gives Bluesky API time to recover, and keeps total retry window under 10 minutes. Add ±20% jitter to prevent thundering herd if multiple posts fail simultaneously.

4. **Admin notice update mechanism: AJAX polling, Heartbeat, or page reload?**
   - What we know: Heartbeat is WordPress standard; AJAX polling is custom; page reload is simple
   - What's unclear: Performance impact of Heartbeat for potentially many concurrent syndications
   - Recommendation: **WordPress Heartbeat API** for active syndication notices, fallback to page reload if Heartbeat unavailable. Heartbeat provides good UX (15-60s updates) without custom polling infrastructure. For hosts that disable Heartbeat, syndication still works—user just won't see live updates (acceptable degradation). **Implementation note:** Only attach heartbeat listener when post meta shows `_bluesky_syndication_status='pending'` to minimize performance impact.

## Sources

### Primary (HIGH confidence)

- [Action Scheduler FAQ](https://actionscheduler.org/faq/) - Async job queue features and reliability
- [Action Scheduler WordPress.org](https://wordpress.org/plugins/action-scheduler/) - Installation and version info
- [WordPress VIP: Action Scheduler](https://docs.wpvip.com/wordpress-on-vip/action-scheduler/) - Enterprise usage patterns
- [Bluesky Rate Limits Documentation](https://docs.bsky.app/docs/advanced-guides/rate-limits) - Official API limits and headers
- [WordPress Heartbeat API Handbook](https://developer.wordpress.org/plugins/javascript/heartbeat-api/) - Core feature documentation
- [WordPress Transient API](https://developer.wordpress.org/apis/transients/) - Caching system documentation
- [Ganesha Circuit Breaker GitHub](https://github.com/ackintosh/ganesha) - Circuit breaker pattern implementation
- [WP_Object_Cache Class Reference](https://developer.wordpress.org/reference/classes/wp_object_cache/) - Request-level caching

### Secondary (MEDIUM confidence)

- [Replacing WP-Cron with Action Scheduler](https://bookingwp.com/wordpress-cron-action-scheduler/) - Migration patterns and benefits
- [The Art of the WordPress Transient](https://deliciousbrains.com/the-art-of-the-wordpress-transient/) - Transient bloat prevention
- [10up Async-Transients](https://github.com/10up/Async-Transients) - Stale-while-revalidate pattern
- [Taming the Heartbeat API](https://deliciousbrains.com/taming-the-heartbeat-api/) - Performance optimization
- [Building Resilient Systems: Circuit Breakers](https://dasroot.net/posts/2026/01/building-resilient-systems-circuit-breakers-retry-patterns/) - Circuit breaker best practices
- [Bluesky API Rate Limits Discussion](https://github.com/bluesky-social/atproto/discussions/697) - Community clarifications
- [php-static-caching GitHub](https://github.com/truongwp/php-static-caching) - Static variable caching implementation

### Tertiary (LOW confidence)

None - all research findings verified with authoritative sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Action Scheduler's adoption by WooCommerce and WordPress VIP validates production readiness; core WordPress features (Transient API, Heartbeat) are documented and stable
- Architecture: HIGH - Patterns drawn from official documentation and production WordPress plugins; circuit breaker adapted from established PHP implementations
- Pitfalls: HIGH - Common issues documented across multiple WordPress performance resources; Bluesky rate limits from official API docs

**Research date:** 2026-02-18
**Valid until:** ~60 days (WordPress ecosystem stable; Bluesky API generally stable but could evolve)

**Codebase-specific findings:**
- Existing plugin already uses transient-based caching with per-account scoping
- API handler has factory method pattern for per-account instances
- No existing async implementation (syndication runs synchronously in `transition_post_status` hook)
- No circuit breaker or rate limit handling beyond basic error detection
- Transient keys already include account_id parameter for multi-account support
- Cache duration already configurable in admin settings (reuse for this phase)
