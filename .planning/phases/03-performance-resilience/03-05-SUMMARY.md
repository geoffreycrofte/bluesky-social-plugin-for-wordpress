---
phase: 03-performance-resilience
plan: 05
subsystem: resilience-integration
tags: [circuit-breaker, rate-limiter, request-cache, stale-while-revalidate, action-scheduler, api-handler, frontend-rendering]
dependency_graph:
  requires: [03-01, 03-02, 03-03, 03-04]
  provides: [fully-integrated-resilience-layer, stale-cache-serving, background-refresh]
  affects: [BlueSky_API_Handler, BlueSky_Render_Front, BlueSky_Helpers, frontend-templates]
tech_stack:
  added: []
  patterns: [3-layer-caching, stale-while-revalidate, background-refresh, request-deduplication]
key_files:
  created:
    - templates/frontend/stale-indicator.php
  modified:
    - classes/BlueSky_API_Handler.php
    - classes/BlueSky_Render_Front.php
    - classes/BlueSky_Helpers.php
decisions:
  - "3-layer cache strategy: request cache (static) → transient (DB) → API (network)"
  - "Cache duration from admin settings with 10 min default (600s) instead of 1 hour"
  - "Extended transient TTL (2x cache_duration) for stale-while-revalidate fallback"
  - "Freshness marker transient with normal TTL to detect staleness"
  - "Serve stale cache when circuit is open or rate limited (never empty/error if cache exists)"
  - "Background refresh via Action Scheduler with 5-min refreshing lock to prevent duplicates"
  - "Stale indicator template shows 'last updated X ago' message"
  - "Background refresh hook registered in API handler constructor (static flag prevents duplicates)"
metrics:
  duration_minutes: 4.0
  tasks_completed: 2
  commits: 2
  files_created: 1
  files_modified: 3
  test_status: passing (57 tests, 81 assertions)
  completed_date: 2026-02-19
---

# Phase 03 Plan 05: Resilience Integration Summary

**One-liner:** Full resilience layer integration with circuit breaker, rate limiter, request cache, and stale-while-revalidate serving across API handler and frontend rendering.

## Objective Achieved

Wired the circuit breaker, rate limiter, and request cache into the existing API handler and rendering pipeline. Implemented stale-while-revalidate caching for frontend display. Every API call is now protected by rate limiting and circuit breaking, every page render benefits from request deduplication, and frontend displays gracefully degrade with stale cached data.

## What Was Built

### Task 1: Resilience Layer Integration into API Handler

**Modified:** `classes/BlueSky_API_Handler.php`

**Added class properties:**
- `private $circuit_breaker` (BlueSky_Circuit_Breaker instance, lazily created when account_id is set)
- `private $rate_limiter` (BlueSky_Rate_Limiter instance, created in constructor)

**Integrated resilience flow for fetch_bluesky_posts():**

1. **REQUEST CACHE CHECK (first):** Build key via `BlueSky_Request_Cache::build_key()`, check with `::has()`, return cached value immediately. Zero API calls, zero transient lookups.

2. **TRANSIENT CACHE CHECK (second):** Existing transient cache check. If cache hit, also store in request cache and return.

3. **CIRCUIT BREAKER CHECK (before API call):** If account_id is set, create `BlueSky_Circuit_Breaker($this->account_id)` and check `is_available()`. If circuit is open, return stale cached data or false.

4. **RATE LIMITER CHECK (before API call):** Check `$this->rate_limiter->is_rate_limited($account_id)`. If rate limited, return stale cached data or false.

5. **MAKE API CALL:** Existing `wp_remote_get` logic.

6. **RATE LIMITER ON RESPONSE:** Call `$this->rate_limiter->check_rate_limit($response, $account_id)`. If rate limited (429), record circuit breaker failure and return stale cached data or false.

7. **CIRCUIT BREAKER ON RESPONSE:** On success → `$this->circuit_breaker->record_success()`. On failure → `$this->circuit_breaker->record_failure()`.

8. **CACHE DURATION RETRIEVAL:** Get cache TTL via `$this->options['cache_duration']['total_seconds'] ?? 600` (default changed from 1 hour to 10 minutes per plan spec).

9. **STORE IN CACHES:** Store result in both transient cache (with extended TTL: `cache_duration * 2`) AND request cache. Set freshness marker transient `{cache_key}_fresh` with normal `cache_duration` TTL.

**Applied same pattern to:**
- `get_bluesky_profile()` – Request cache → transient → circuit breaker → rate limiter → API call → record result → cache in both layers
- `syndicate_post_to_bluesky()` – Only added circuit breaker + rate limiter checks (no caching for write operations). On 429 response, check rate limiter and record on circuit breaker.

**Helper methods added:**
- `get_circuit_breaker()` – Lazy initialization of circuit breaker when account_id is set
- `get_stale_cache($cache_key)` – Try `get_transient($cache_key)` for stale fallback (data cached with 2x TTL may exist after freshness expires)

**Method signatures unchanged:** The resilience layer is internal — callers don't know about it. Return types and parameters stay the same for backward compatibility.

### Task 2: Stale-While-Revalidate Rendering

**Modified:** `classes/BlueSky_Render_Front.php`, `classes/BlueSky_Helpers.php`
**Created:** `templates/frontend/stale-indicator.php`

**Added to BlueSky_Helpers:**

- `public static function time_ago($timestamp)` – Convert Unix timestamp to human-readable "X minutes ago", "X hours ago" format using WordPress `human_time_diff()` function.

- `public static function is_cache_fresh($cache_key)` – Check if `{cache_key}_fresh` transient exists. Returns boolean.

- `public static function schedule_cache_refresh($cache_key, $account_id, $params)` – Schedule Action Scheduler job if not already scheduled. Sets refreshing lock transient `{cache_key}_refreshing` with 5-minute TTL to prevent duplicate jobs.

**Stale-while-revalidate in Render_Front:**

In `render_bluesky_posts_list()` and `render_bluesky_profile_card()`:

1. Call API handler's fetch method (which now has request cache + transient cache + resilience built in)
2. After getting data, check if data is stale using `BlueSky_Helpers::is_cache_fresh($cache_key)`
3. If data is stale (freshness expired but data exists):
   - Schedule background refresh via `BlueSky_Helpers::schedule_cache_refresh()` if Action Scheduler is available
   - Get cache timestamp from freshness transient for "last updated" calculation
   - Include stale indicator template with `$time_ago` variable set

**Created stale-indicator.php template:**

```php
<div class="bluesky-stale-indicator" style="font-size: 0.8em; color: #666; margin-top: 5px;">
    <?php
    printf(
        esc_html__('Last updated %s ago', 'social-integration-for-bluesky'),
        esc_html($time_ago)
    );
    ?>
</div>
```

Template uses proper escaping (`esc_html()`) and text domain (`'social-integration-for-bluesky'`).

**Background refresh hook registered in API handler:**

Added to `BlueSky_API_Handler::__construct()`:

```php
static $hooks_registered = false;
if (!$hooks_registered) {
    add_action('bluesky_refresh_cache', [__CLASS__, 'background_refresh_cache'], 10, 1);
    $hooks_registered = true;
}
```

**Background refresh callback:**

- `background_refresh_cache($args)` – Action Scheduler callback that:
  1. Determines cache type from key (profile vs posts)
  2. Creates API handler for the account
  3. Fetches fresh data (bypassing cache)
  4. Updates transient with fresh data (2x TTL for stale fallback)
  5. Sets freshness marker transient (normal TTL)
  6. Clears refreshing lock

- `refresh_profile_cache($cache_key, $account_id)` – Background profile refresh
- `refresh_posts_cache($cache_key, $account_id, $params)` – Background posts refresh

**AJAX endpoints:** No changes needed. The API handler already has request cache integrated, so `ajax_async_posts()` and `ajax_async_profile()` automatically benefit from request-level deduplication.

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

1. **PHP syntax check:** ✅ All modified files pass `php -l`
2. **Test suite:** ✅ All 57 tests passing (81 assertions)
3. **3-layer cache check order verified:** ✅ Request cache → transient → API
4. **Circuit breaker integration verified:** ✅ Checked before every API call (when account_id set)
5. **Rate limiter integration verified:** ✅ Checked before every API call and after response for 429
6. **Stale cache fallback verified:** ✅ Served when circuit is open or rate limited
7. **Stale detection verified:** ✅ `is_cache_fresh()` checks in both posts and profile rendering
8. **Indicator rendering verified:** ✅ Template included when cache is stale with proper escaping and text domain

## Technical Implementation Details

### Caching Strategy

**3-Layer Cache Architecture:**

| Layer | Storage | Scope | TTL | Purpose |
|-------|---------|-------|-----|---------|
| Request Cache | Static variable | Single PHP request | Request lifetime | Deduplicate API calls within same page render |
| Transient Cache | Database | Cross-request | 2x cache_duration | Stale fallback data |
| Freshness Marker | Database | Cross-request | 1x cache_duration | Detect staleness |

**Cache Duration:** Defaults to 10 minutes (600 seconds) instead of 1 hour, configurable via admin settings `$options['cache_duration']['total_seconds']`.

**Stale-While-Revalidate Flow:**

1. **Transient exists + freshness marker exists** → Serve fresh cached data
2. **Transient exists + freshness marker expired** → Serve stale cached data + show indicator + schedule background refresh
3. **Transient expired** → Make API call (protected by circuit breaker + rate limiter)

### Resilience Integration Points

**API Handler Methods:**

- `fetch_bluesky_posts()` – Full resilience (read operation with caching)
- `get_bluesky_profile()` – Full resilience (read operation with caching)
- `syndicate_post_to_bluesky()` – Circuit breaker + rate limiter only (write operation, no caching)

**Resilience Checks:**

1. Circuit breaker available check (`is_available()`)
2. Rate limiter pre-flight check (`is_rate_limited()`)
3. Rate limiter response check (`check_rate_limit()` for 429)
4. Circuit breaker result recording (`record_success()` / `record_failure()`)

**Graceful Degradation:**

When circuit is open or rate limited:
- Return stale cached data if available
- Return false if no stale data
- **Never** return empty/error state if cache exists (per user decision)

### Action Scheduler Integration

**Background Refresh Jobs:**

- **Hook:** `bluesky_refresh_cache`
- **Group:** `bluesky-cache-refresh`
- **Guard:** Function existence check (`function_exists('as_schedule_single_action')`)
- **Lock:** Refreshing transient prevents duplicate scheduling (5-min TTL)

**Job Arguments:**

```php
[
    'cache_key' => $cache_key,
    'account_id' => $account_id,
    'params' => [...], // Fetch parameters
]
```

### Multi-Account Support

All resilience primitives are account-scoped:

- Circuit breaker state: Per account (via `$account_id` in constructor)
- Rate limit state: Per account (via `$account_id` in transient keys)
- Cache keys: Per account (via `$account_id` in transient key generation)
- Background refresh: Per account (API handler created with `create_for_account()`)

## Files Changed

### Created

- `templates/frontend/stale-indicator.php` (23 lines) – Stale cache indicator template

### Modified

- `classes/BlueSky_API_Handler.php` (+231 lines, -8 lines)
  - Added circuit breaker and rate limiter properties
  - Integrated 3-layer cache check in fetch methods
  - Added resilience checks and result recording
  - Registered background refresh hook
  - Implemented background refresh callbacks

- `classes/BlueSky_Render_Front.php` (+30 lines)
  - Added stale cache detection in render methods
  - Scheduled background refresh for stale cache
  - Rendered stale indicator template when cache is stale

- `classes/BlueSky_Helpers.php` (+50 lines)
  - Added `time_ago()` static method
  - Added `is_cache_fresh()` static method
  - Added `schedule_cache_refresh()` static method

## Testing

**Test Execution:**

```bash
"/Users/CRG/Library/Application Support/Local/lightning-services/php-8.3.8+0/bin/darwin/bin/php" \
  tests/phpunit.phar --configuration tests/phpunit.xml
```

**Results:**

```
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

.........................................................         57 / 57 (100%)

Time: 00:00.695, Memory: 32.91 MB

OK (57 tests, 81 assertions)
```

All existing tests pass. No new test failures introduced by resilience integration.

## Self-Check: PASSED

**Files created:**
```bash
[ -f "templates/frontend/stale-indicator.php" ] && echo "FOUND: templates/frontend/stale-indicator.php" || echo "MISSING"
```
Result: ✅ FOUND

**Commits exist:**
```bash
git log --oneline --all | grep -q "bbcce50" && echo "FOUND: bbcce50" || echo "MISSING: bbcce50"
git log --oneline --all | grep -q "69a9694" && echo "FOUND: 69a9694" || echo "MISSING: 69a9694"
```
Result: ✅ Both commits found

**Modified files exist and contain expected patterns:**

```bash
# Request cache integration
grep -q "BlueSky_Request_Cache::build_key" classes/BlueSky_API_Handler.php && echo "✅ Request cache integrated"

# Circuit breaker integration
grep -q "get_circuit_breaker()" classes/BlueSky_API_Handler.php && echo "✅ Circuit breaker integrated"

# Rate limiter integration
grep -q "rate_limiter->is_rate_limited" classes/BlueSky_API_Handler.php && echo "✅ Rate limiter integrated"

# Stale detection
grep -q "is_cache_fresh" classes/BlueSky_Render_Front.php && echo "✅ Stale detection integrated"

# Background refresh
grep -q "schedule_cache_refresh" classes/BlueSky_Helpers.php && echo "✅ Background refresh helpers added"
```

Result: ✅ All patterns verified

## Performance Impact

**Positive impacts:**

1. **Request deduplication:** Multiple blocks/shortcodes on same page make 1 API call instead of N
2. **Reduced database queries:** Request cache eliminates redundant transient lookups
3. **Graceful degradation:** Stale cache served instead of empty/error states (better UX)
4. **Background refresh:** Stale data updates asynchronously (no user-facing latency)
5. **Circuit breaker:** Failing accounts don't block other accounts (per-account isolation)

**Cache overhead:**

- Static variable cache: Negligible (in-memory PHP array)
- Freshness marker transients: 1 additional transient per cache key (small overhead)
- Refreshing lock transients: 1 transient during refresh (expires after 5 min)

**API call reduction:**

- Without request cache: N identical shortcodes = N API calls
- With request cache: N identical shortcodes = 1 API call (deduplication)
- Example: Page with 3 profile cards for same account: 3 API calls → 1 API call (67% reduction)

## Integration Points

**Upstream dependencies (requires):**

- `BlueSky_Circuit_Breaker` (from 03-01)
- `BlueSky_Rate_Limiter` (from 03-01)
- `BlueSky_Request_Cache` (from 03-02)
- Action Scheduler integration (from 03-03)

**Downstream impacts (affects):**

- `BlueSky_AJAX_Service` – Automatically benefits from request cache (no code changes)
- `BlueSky_Blocks_Service` – Automatically benefits from request cache (no code changes)
- Frontend rendering – All posts/profile displays now show stale indicators when appropriate
- Admin settings – Cache duration setting now controls resilience layer TTL

## Success Criteria Met

- [x] All tasks executed (2/2)
- [x] Each task committed individually with proper format
- [x] All deviations documented (none)
- [x] SUMMARY.md created with substantive content
- [x] Self-check performed and passed
- [x] API handler has 3-layer cache: request (static) → transient (database) → API (network)
- [x] Circuit breaker checked before every API call (when account_id set)
- [x] Rate limiter checked before every API call
- [x] Rate limiter checks response for 429 after every API call
- [x] Stale cache served when circuit is open or rate limited
- [x] Frontend shows "last updated X ago" for stale data
- [x] Background refresh scheduled for stale cache (Action Scheduler)
- [x] No duplicate refresh jobs (refreshing lock transient)
- [x] Method signatures unchanged (backward compatible)

## Next Steps

With the resilience layer fully integrated, the API handler and frontend rendering are now production-ready for high-traffic scenarios. The system gracefully degrades under API failures or rate limiting, serving stale cached data instead of empty states.

**Phase 03 status:** 5 of N plans complete. The performance and resilience primitives are now fully integrated into the request flow.

**Recommended continuation:** If Phase 03 has additional plans (e.g., performance monitoring, cache warming, etc.), continue with 03-06. Otherwise, proceed to Phase 04 (Error UX) to ensure users have clear visibility into errors and recovery paths.
