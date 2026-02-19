<?php
/**
 * Async Syndication Handler for Bluesky
 *
 * Handles background job scheduling and processing for post syndication using
 * Action Scheduler. Provides retry logic, circuit breaker integration, and
 * rate limit checking. Falls back to synchronous execution if Action Scheduler
 * is not available.
 *
 * @package BlueSky_Social_Integration
 * @since 1.6.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BlueSky_Async_Handler {
    /**
     * API Handler instance
     *
     * @var BlueSky_API_Handler
     */
    private $api_handler;

    /**
     * Account Manager instance
     *
     * @var BlueSky_Account_Manager|null
     */
    private $account_manager;

    /**
     * Retry delays in seconds for attempts 1, 2, 3
     *
     * @var array
     */
    private const RETRY_DELAYS = [60, 120, 300];

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Constructor
     *
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager|null $account_manager Account manager instance
     */
    public function __construct(BlueSky_API_Handler $api_handler, BlueSky_Account_Manager $account_manager = null) {
        $this->api_handler = $api_handler;
        $this->account_manager = $account_manager;

        // Register Action Scheduler hooks
        add_action('bluesky_async_syndicate', [$this, 'process_syndication'], 10, 3);
        add_action('bluesky_retry_syndicate', [$this, 'process_syndication'], 10, 3);
    }

    /**
     * Schedule syndication job
     *
     * @param int $post_id WordPress post ID
     * @param array $account_ids Array of account UUIDs to syndicate to
     * @return bool True if scheduled successfully, false otherwise
     */
    public function schedule_syndication($post_id, $account_ids) {
        // Check if Action Scheduler is available
        if (!function_exists('as_schedule_single_action')) {
            // Fall back to synchronous syndication
            return $this->syndicate_synchronously($post_id, $account_ids);
        }

        // Schedule immediate job
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

        // Set post meta for status tracking
        update_post_meta($post_id, '_bluesky_syndication_status', 'pending');
        update_post_meta($post_id, '_bluesky_syndication_scheduled', time());

        return true;
    }

    /**
     * Process syndication job (sequential multi-account processing)
     *
     * @param int $post_id WordPress post ID
     * @param array $account_ids Array of account UUIDs
     * @param int $attempt Attempt number (1-indexed)
     * @return void
     */
    public function process_syndication($post_id, $account_ids, $attempt = 1) {
        // Get post object
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Prepare post data
        $permalink = get_permalink($post_id);
        $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 30, '...');
        $image_url = '';

        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'large');
        }

        if (empty($image_url)) {
            preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        // Get existing syndication results
        $existing_info_json = get_post_meta($post_id, '_bluesky_syndication_bs_post_info', true);
        $syndication_results = !empty($existing_info_json) ? json_decode($existing_info_json, true) : [];
        if (!is_array($syndication_results)) {
            $syndication_results = [];
        }

        // Get all accounts
        $all_accounts = $this->account_manager ? $this->account_manager->get_accounts() : [];

        // Process each account SEQUENTIALLY
        foreach ($account_ids as $account_id) {
            // Skip if already syndicated successfully
            if (isset($syndication_results[$account_id]) && !empty($syndication_results[$account_id]['success'])) {
                continue;
            }

            // Get account data
            if (!isset($all_accounts[$account_id])) {
                $this->mark_failed($post_id, $account_id, 'Account not found', []);
                continue;
            }
            $account = $all_accounts[$account_id];

            // Check circuit breaker
            $breaker = new BlueSky_Circuit_Breaker($account_id);
            if (!$breaker->is_available()) {
                // Queue for retry after cooldown
                $this->queue_for_cooldown($post_id, [$account_id], $attempt);

                // Log circuit breaker event
                $logger = new BlueSky_Activity_Logger();
                $logger->log_event('circuit_opened', sprintf('Circuit breaker opened for @%s', $account['handle'] ?? 'unknown'), null, $account_id);
                continue;
            }

            // Check rate limiter
            $limiter = new BlueSky_Rate_Limiter();
            if ($limiter->is_rate_limited($account_id)) {
                // Schedule retry after rate limit expires
                $retry_after = $limiter->get_retry_after($account_id);
                $this->schedule_retry($post_id, [$account_id], $attempt, $retry_after);

                // Log rate limit event
                $logger = new BlueSky_Activity_Logger();
                $logger->log_event('rate_limited', sprintf('Rate limited while syndicating to @%s', $account['handle'] ?? 'unknown'), $post_id, $account_id);
                continue;
            }

            // Create per-account API handler
            $api = BlueSky_API_Handler::create_for_account($account);

            // Syndicate to this account
            $result = $api->syndicate_post_to_bluesky(
                $post->post_title,
                $permalink,
                $excerpt,
                $image_url
            );

            // Check for rate limiting (response would be false on failure)
            // Note: Rate limiter needs access to raw response, not just false
            // This is a limitation we'll handle in future - for now track via circuit breaker

            if ($result !== false && is_array($result)) {
                // Success
                $breaker->record_success();
                $this->mark_success($post_id, $account_id, $result, $account);

                // Log success event
                $logger = new BlueSky_Activity_Logger();
                $logger->log_event('syndication_success', sprintf('Post "%s" syndicated to @%s', get_the_title($post_id), $account['handle'] ?? 'unknown'), $post_id, $account_id);

                // Clear auth errors for this account on success
                $auth_errors = get_option('bluesky_account_auth_errors', []);
                if (isset($auth_errors[$account_id])) {
                    unset($auth_errors[$account_id]);
                    update_option('bluesky_account_auth_errors', $auth_errors);
                }
            } else {
                // Failure
                $breaker->record_failure();

                // Determine error type and translate
                $error_data = $this->detect_error_type($api, $account_id);
                $translated = BlueSky_Error_Translator::translate_error($error_data, 'syndication');

                // Track auth errors
                if ($error_data['status'] === 401 || in_array($error_data['code'], ['AuthenticationRequired', 'InvalidToken', 'ExpiredToken'])) {
                    $auth_errors = get_option('bluesky_account_auth_errors', []);
                    $auth_errors[$account_id] = ['handle' => $account['handle'] ?? 'unknown', 'time' => time()];
                    update_option('bluesky_account_auth_errors', $auth_errors);
                }

                if ($attempt < self::MAX_ATTEMPTS) {
                    // Schedule retry
                    $this->schedule_retry($post_id, [$account_id], $attempt + 1);
                } else {
                    // Max attempts reached
                    $this->mark_failed($post_id, $account_id, $translated['message'], $account);

                    // Log failure event
                    $logger = new BlueSky_Activity_Logger();
                    $logger->log_event('syndication_failed', sprintf('Post "%s" failed for @%s: %s', get_the_title($post_id), $account['handle'] ?? 'unknown', $translated['message']), $post_id, $account_id);
                }
            }
        }

        // Update overall status
        $this->update_overall_status($post_id);
    }

    /**
     * Schedule retry with exponential backoff
     *
     * @param int $post_id WordPress post ID
     * @param array $account_ids Array of account UUIDs
     * @param int $attempt Attempt number (1-indexed)
     * @param int|null $custom_delay Custom delay in seconds (overrides exponential backoff)
     * @return void
     */
    private function schedule_retry($post_id, $account_ids, $attempt, $custom_delay = null) {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        // Determine delay
        $delay = $custom_delay ?? self::RETRY_DELAYS[min($attempt - 1, count(self::RETRY_DELAYS) - 1)];

        // Schedule retry
        as_schedule_single_action(
            time() + $delay,
            'bluesky_retry_syndicate',
            [
                'post_id' => $post_id,
                'account_ids' => $account_ids,
                'attempt' => $attempt
            ],
            'bluesky-syndication'
        );

        // Update status
        update_post_meta($post_id, '_bluesky_syndication_status', 'retrying');
    }

    /**
     * Queue syndication for retry after circuit breaker cooldown
     *
     * @param int $post_id WordPress post ID
     * @param array $account_ids Array of account UUIDs
     * @param int $attempt Current attempt number
     * @return void
     */
    private function queue_for_cooldown($post_id, $account_ids, $attempt) {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        // Circuit breaker cooldown is 15 minutes (900 seconds)
        $cooldown_remaining = 900;

        // Schedule retry after cooldown
        as_schedule_single_action(
            time() + $cooldown_remaining,
            'bluesky_retry_syndicate',
            [
                'post_id' => $post_id,
                'account_ids' => $account_ids,
                'attempt' => $attempt
            ],
            'bluesky-syndication'
        );

        // Update status
        update_post_meta($post_id, '_bluesky_syndication_status', 'circuit_open');
    }

    /**
     * Detect error type from API handler state
     *
     * @param BlueSky_API_Handler $api API handler instance
     * @param string $account_id Account ID
     * @return array Error data array with 'code', 'message', 'status' keys
     */
    private function detect_error_type($api, $account_id)
    {
        // Check if rate limited
        $limiter = new BlueSky_Rate_Limiter();
        if ($limiter->is_rate_limited($account_id)) {
            return [
                'code' => 'RateLimitExceeded',
                'message' => 'Rate limit exceeded',
                'status' => 429
            ];
        }

        // Check if circuit breaker is open (multiple failures)
        $breaker = new BlueSky_Circuit_Breaker($account_id);
        if (!$breaker->is_available()) {
            return [
                'code' => 'CircuitOpen',
                'message' => 'Circuit breaker open',
                'status' => 503
            ];
        }

        // Generic failure (could be auth, network, or API error)
        // Without detailed error info from API handler, we return a generic error
        return [
            'code' => 'Unknown',
            'message' => 'Syndication failed',
            'status' => 0
        ];
    }

    /**
     * Mark account syndication as failed
     *
     * @param int $post_id WordPress post ID
     * @param string $account_id Account UUID
     * @param string $reason Failure reason
     * @param array $account Account data (for handle lookup)
     * @return void
     */
    private function mark_failed($post_id, $account_id, $reason = '', $account = []) {
        // Get existing results
        $existing_info_json = get_post_meta($post_id, '_bluesky_syndication_bs_post_info', true);
        $syndication_results = !empty($existing_info_json) ? json_decode($existing_info_json, true) : [];
        if (!is_array($syndication_results)) {
            $syndication_results = [];
        }

        // Mark this account as failed
        $syndication_results[$account_id] = [
            'uri' => '',
            'cid' => '',
            'url' => '',
            'syndicated_at' => time(),
            'success' => false,
            'error' => $reason
        ];

        // Save results
        update_post_meta($post_id, '_bluesky_syndication_bs_post_info', wp_json_encode($syndication_results));

        // Update failed accounts list with handle and error message
        $failed_accounts_json = get_post_meta($post_id, '_bluesky_syndication_failed_accounts', true);
        $failed_accounts = !empty($failed_accounts_json) ? json_decode($failed_accounts_json, true) : [];
        if (!is_array($failed_accounts)) {
            $failed_accounts = [];
        }

        $handle = $account['handle'] ?? 'unknown';
        $failed_accounts[$handle] = [
            'account_id' => $account_id,
            'error' => $reason
        ];

        update_post_meta($post_id, '_bluesky_syndication_failed_accounts', wp_json_encode($failed_accounts));
    }

    /**
     * Mark account syndication as successful
     *
     * @param int $post_id WordPress post ID
     * @param string $account_id Account UUID
     * @param array $result Syndication result from API
     * @param array $account Account data (for handle lookup)
     * @return void
     */
    private function mark_success($post_id, $account_id, $result, $account = []) {
        // Get existing results
        $existing_info_json = get_post_meta($post_id, '_bluesky_syndication_bs_post_info', true);
        $syndication_results = !empty($existing_info_json) ? json_decode($existing_info_json, true) : [];
        if (!is_array($syndication_results)) {
            $syndication_results = [];
        }

        // Add successful result
        $syndication_results[$account_id] = [
            'uri' => $result['uri'] ?? '',
            'cid' => $result['cid'] ?? '',
            'url' => $result['url'] ?? '',
            'syndicated_at' => time(),
            'success' => true
        ];

        // Save results
        update_post_meta($post_id, '_bluesky_syndication_bs_post_info', wp_json_encode($syndication_results));

        // Track completed accounts (store handles for display)
        $completed_json = get_post_meta($post_id, '_bluesky_syndication_accounts_completed', true);
        $completed = !empty($completed_json) ? json_decode($completed_json, true) : [];
        if (!is_array($completed)) {
            $completed = [];
        }

        $handle = $account['handle'] ?? 'unknown';
        if (!in_array($handle, $completed)) {
            $completed[] = $handle;
        }
        update_post_meta($post_id, '_bluesky_syndication_accounts_completed', wp_json_encode($completed));
    }

    /**
     * Update overall syndication status based on per-account results
     *
     * @param int $post_id WordPress post ID
     * @return void
     */
    private function update_overall_status($post_id) {
        $existing_info_json = get_post_meta($post_id, '_bluesky_syndication_bs_post_info', true);
        $syndication_results = !empty($existing_info_json) ? json_decode($existing_info_json, true) : [];

        if (empty($syndication_results) || !is_array($syndication_results)) {
            return;
        }

        $success_count = 0;
        $total_count = count($syndication_results);

        foreach ($syndication_results as $result) {
            if (!empty($result['success'])) {
                $success_count++;
            }
        }

        // Determine status
        if ($success_count === 0) {
            $status = 'failed';
        } elseif ($success_count === $total_count) {
            $status = 'completed';
        } else {
            $status = 'partial';
        }

        update_post_meta($post_id, '_bluesky_syndication_status', $status);

        // If at least one succeeded, mark post as syndicated
        if ($success_count > 0) {
            add_post_meta($post_id, '_bluesky_syndicated', true, true);

            // Find first successful account for backward compatibility
            foreach ($syndication_results as $account_id => $result) {
                if (!empty($result['success'])) {
                    update_post_meta($post_id, '_bluesky_account_id', $account_id);
                    break;
                }
            }
        }
    }

    /**
     * Synchronous fallback when Action Scheduler is not available
     *
     * @param int $post_id WordPress post ID
     * @param array $account_ids Array of account UUIDs
     * @return bool True on success, false on failure
     */
    private function syndicate_synchronously($post_id, $account_ids) {
        // Get post object
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Prepare post data
        $permalink = get_permalink($post_id);
        $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 30, '...');
        $image_url = '';

        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'large');
        }

        if (empty($image_url)) {
            preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        // Get all accounts
        $all_accounts = $this->account_manager ? $this->account_manager->get_accounts() : [];
        $syndication_results = [];
        $first_successful_account = null;

        foreach ($account_ids as $account_id) {
            if (!isset($all_accounts[$account_id])) {
                continue;
            }

            $account = $all_accounts[$account_id];
            $api = BlueSky_API_Handler::create_for_account($account);

            $result = $api->syndicate_post_to_bluesky(
                $post->post_title,
                $permalink,
                $excerpt,
                $image_url
            );

            if ($result !== false && is_array($result)) {
                $syndication_results[$account_id] = [
                    'uri' => $result['uri'] ?? '',
                    'cid' => $result['cid'] ?? '',
                    'url' => $result['url'] ?? '',
                    'syndicated_at' => time(),
                    'success' => true
                ];

                if ($first_successful_account === null) {
                    $first_successful_account = $account_id;
                }
            } else {
                $syndication_results[$account_id] = [
                    'uri' => '',
                    'cid' => '',
                    'url' => '',
                    'syndicated_at' => time(),
                    'success' => false
                ];
            }
        }

        // Save results
        update_post_meta($post_id, '_bluesky_syndication_bs_post_info', wp_json_encode($syndication_results));

        // Determine overall syndication status
        $has_success = false;
        $all_success = true;
        foreach ($syndication_results as $result) {
            if (!empty($result['success'])) {
                $has_success = true;
            } else {
                $all_success = false;
            }
        }

        // Set syndication status for admin column display
        if ($all_success) {
            update_post_meta($post_id, '_bluesky_syndication_status', 'completed');
        } elseif ($has_success) {
            update_post_meta($post_id, '_bluesky_syndication_status', 'partial');
        } else {
            update_post_meta($post_id, '_bluesky_syndication_status', 'failed');
        }

        if ($has_success) {
            add_post_meta($post_id, '_bluesky_syndicated', true, true);
            if ($first_successful_account) {
                update_post_meta($post_id, '_bluesky_account_id', $first_successful_account);
            }
        }

        return $has_success;
    }
}
