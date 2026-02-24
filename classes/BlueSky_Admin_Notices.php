<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Admin_Notices
{
    /**
     * Async handler instance for retry functionality
     * @var BlueSky_Async_Handler
     */
    private $async_handler;

    /**
     * Account manager instance for account lookups
     * @var BlueSky_Account_Manager|null
     */
    private $account_manager;

    /**
     * Constructor
     * @param BlueSky_Async_Handler $async_handler Async handler instance
     * @param BlueSky_Account_Manager|null $account_manager Account manager instance
     */
    public function __construct(BlueSky_Async_Handler $async_handler, BlueSky_Account_Manager $account_manager = null)
    {
        $this->async_handler = $async_handler;
        $this->account_manager = $account_manager ?: new BlueSky_Account_Manager();

        // Register hooks
        add_action('admin_notices', [$this, 'expired_credentials_notice']);
        add_action('admin_notices', [$this, 'circuit_breaker_notice']);
        add_action('admin_notices', [$this, 'syndication_status_notice']);
        add_filter('heartbeat_received', [$this, 'check_syndication_status'], 10, 2);
        add_action('wp_ajax_bluesky_retry_syndication', [$this, 'handle_retry']);
        add_action('wp_ajax_bluesky_dismiss_notice', [$this, 'handle_notice_dismissal']);

        // Post list column
        add_filter('manage_posts_columns', [$this, 'add_syndication_column']);
        add_action('manage_posts_custom_column', [$this, 'render_syndication_column'], 10, 2);
    }

    /**
     * Display persistent notice for expired credentials
     * Shows across all admin pages until dismissed (returns after 24 hours)
     */
    public function expired_credentials_notice()
    {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for accounts with auth errors
        $auth_errors = get_option('bluesky_account_auth_errors', []);

        // Also check circuit breakers for auth-related failures
        $accounts = $this->account_manager->get_accounts();
        $broken_accounts = [];

        foreach ($accounts as $account) {
            $account_id = $account['id'];

            // Check if account is in auth_errors registry
            if (in_array($account_id, $auth_errors)) {
                $broken_accounts[$account_id] = $account['handle'];
                continue;
            }

            // Check if circuit breaker is open (could be auth failure)
            $circuit_breaker = new BlueSky_Circuit_Breaker($account_id);
            if (!$circuit_breaker->is_available()) {
                // Check if failure is auth-related by checking transient
                $state = get_transient('bluesky_circuit_' . $account_id);
                if ($state && isset($state['status']) && $state['status'] === 'open') {
                    // Assume auth failure if circuit is open (could be refined)
                    $broken_accounts[$account_id] = $account['handle'];
                }
            }
        }

        if (empty($broken_accounts)) {
            return;
        }

        // Check if notice is dismissed
        $dismissed_until = get_user_meta(get_current_user_id(), 'bluesky_expired_creds_dismissed', true);
        if ($dismissed_until && time() < $dismissed_until) {
            return; // Still dismissed
        }

        // Build message
        $settings_url = admin_url('options-general.php?page=social_integration_for_bluesky');

        if (count($broken_accounts) === 1) {
            $handle = reset($broken_accounts);
            $message = sprintf(
                __('The Bluesky account %s needs re-authentication. <a href="%s">Update credentials</a>', 'social-integration-for-bluesky'),
                '<strong>@' . esc_html($handle) . '</strong>',
                esc_url($settings_url)
            );
        } else {
            // Show up to 5 accounts explicitly
            $handles = array_values($broken_accounts);
            $display_handles = array_slice($handles, 0, 5);
            $remaining = count($handles) - count($display_handles);

            $handles_text = implode(', ', array_map(function($h) {
                return '@' . esc_html($h);
            }, $display_handles));

            if ($remaining > 0) {
                $handles_text .= sprintf(__(' ...and %d more', 'social-integration-for-bluesky'), $remaining);
            }

            $message = sprintf(
                __('Bluesky accounts %s need re-authentication. <a href="%s">Update credentials</a>', 'social-integration-for-bluesky'),
                '<strong>' . $handles_text . '</strong>',
                esc_url($settings_url)
            );
        }

        echo '<div class="notice notice-error is-dismissible" data-dismissible="bluesky_expired_creds_dismissed">';
        echo '<p>' . $message . '</p>';
        echo '</div>';
    }

    /**
     * Display persistent notice for circuit breaker open
     * Shows across all admin pages until dismissed (returns after 24 hours)
     */
    public function circuit_breaker_notice()
    {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if any accounts have open circuit breakers
        $accounts = $this->account_manager->get_accounts();
        $open_breakers = [];

        foreach ($accounts as $account) {
            $circuit_breaker = new BlueSky_Circuit_Breaker($account['id']);
            if (!$circuit_breaker->is_available()) {
                $open_breakers[] = $account['handle'];
            }
        }

        if (empty($open_breakers)) {
            return;
        }

        // Check if notice is dismissed
        $dismissed_until = get_user_meta(get_current_user_id(), 'bluesky_circuit_breaker_dismissed', true);
        if ($dismissed_until && time() < $dismissed_until) {
            return; // Still dismissed
        }

        // Friendly explanation
        $message = __('Bluesky requests paused due to repeated errors. Will resume automatically.', 'social-integration-for-bluesky');

        echo '<div class="notice notice-warning is-dismissible" data-dismissible="bluesky_circuit_breaker_dismissed">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    /**
     * Display syndication status notice on post edit screen
     */
    public function syndication_status_notice()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        global $post;
        if (!$post || !isset($post->ID)) {
            return;
        }

        $status = get_post_meta($post->ID, '_bluesky_syndication_status', true);
        if (empty($status)) {
            return;
        }

        $retry_nonce = wp_create_nonce('bluesky_retry_syndication');

        switch ($status) {
            case 'pending':
                echo '<div class="notice notice-info bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p><span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>';
                echo esc_html__('Syndicating to Bluesky...', 'social-integration-for-bluesky');
                echo '</p></div>';
                break;

            case 'completed':
                $account_count = get_post_meta($post->ID, '_bluesky_syndication_accounts_completed', true);
                $account_count = $account_count ? count($account_count) : 1;
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>';
                /* translators: %d: number of accounts */
                echo esc_html(sprintf(_n(
                    'Successfully syndicated to %d Bluesky account.',
                    'Successfully syndicated to %d Bluesky accounts.',
                    $account_count,
                    'social-integration-for-bluesky'
                ), $account_count));
                echo '</p></div>';
                break;

            case 'failed':
                $failed_accounts = get_post_meta($post->ID, '_bluesky_syndication_failed_accounts', true);
                $retry_count = (int) get_post_meta($post->ID, '_bluesky_syndication_retry_count', true);

                echo '<div class="notice notice-error bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p>';

                // Show per-account detail
                if (is_array($failed_accounts) && !empty($failed_accounts)) {
                    echo '<strong>' . esc_html__('Syndication failed:', 'social-integration-for-bluesky') . '</strong><br>';
                    foreach ($failed_accounts as $handle => $error_info) {
                        $error_msg = is_array($error_info) && isset($error_info['error']) ? $error_info['error'] : __('Unknown error', 'social-integration-for-bluesky');
                        echo '<span style="margin-left:10px;">• @' . esc_html($handle) . ': ' . esc_html($error_msg) . '</span><br>';
                    }
                } else {
                    echo esc_html__('Failed to syndicate to Bluesky.', 'social-integration-for-bluesky') . '<br>';
                }

                // Show retry button only after auto-retries exhaust (max 3 retries)
                if ($retry_count >= 3) {
                    echo '<a href="#" class="bluesky-retry-syndication" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr($retry_nonce) . '">';
                    echo esc_html__('Retry now', 'social-integration-for-bluesky');
                    echo '</a>';
                } else {
                    echo '<em>' . esc_html__('Will retry automatically.', 'social-integration-for-bluesky') . '</em>';
                }

                echo '</p></div>';
                break;

            case 'retrying':
                $attempt = (int) get_post_meta($post->ID, '_bluesky_syndication_retry_count', true);
                echo '<div class="notice notice-info bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p><span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>';
                /* translators: %d: retry attempt number */
                echo esc_html(sprintf(__('Retrying syndication to Bluesky (attempt %d)...', 'social-integration-for-bluesky'), $attempt + 1));
                echo '</p></div>';
                break;

            case 'partial':
                $completed = get_post_meta($post->ID, '_bluesky_syndication_accounts_completed', true);
                $failed = get_post_meta($post->ID, '_bluesky_syndication_failed_accounts', true);
                $retry_count = (int) get_post_meta($post->ID, '_bluesky_syndication_retry_count', true);

                echo '<div class="notice notice-warning bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p>';
                echo '<strong>' . esc_html__('Partial syndication:', 'social-integration-for-bluesky') . '</strong><br>';

                // Show completed accounts
                if (is_array($completed) && !empty($completed)) {
                    foreach ($completed as $handle) {
                        echo '<span style="margin-left:10px;color:#46b450;">• @' . esc_html($handle) . ': ' . esc_html__('Posted successfully', 'social-integration-for-bluesky') . '</span><br>';
                    }
                }

                // Show failed accounts with errors
                if (is_array($failed) && !empty($failed)) {
                    foreach ($failed as $handle => $error_info) {
                        $error_msg = is_array($error_info) && isset($error_info['error']) ? $error_info['error'] : __('Unknown error', 'social-integration-for-bluesky');
                        echo '<span style="margin-left:10px;color:#dc3232;">• @' . esc_html($handle) . ': ' . esc_html($error_msg) . '</span><br>';
                    }
                }

                // Show retry button only after auto-retries exhaust
                if ($retry_count >= 3) {
                    echo '<a href="#" class="bluesky-retry-syndication" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr($retry_nonce) . '">';
                    echo esc_html__('Retry failed accounts', 'social-integration-for-bluesky');
                    echo '</a>';
                } else {
                    echo '<em>' . esc_html__('Will retry failed accounts automatically.', 'social-integration-for-bluesky') . '</em>';
                }

                echo '</p></div>';
                break;

            case 'circuit_open':
                echo '<div class="notice notice-warning bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p>';
                echo esc_html__('Syndication paused due to API issues. Will retry automatically in 15 minutes.', 'social-integration-for-bluesky');
                echo '</p></div>';
                break;

            case 'rate_limited':
                echo '<div class="notice notice-warning bluesky-syndication-notice" data-post-id="' . esc_attr($post->ID) . '">';
                echo '<p>';
                echo esc_html__('Syndication paused due to rate limiting. Will retry automatically when limit resets.', 'social-integration-for-bluesky');
                echo '</p></div>';
                break;
        }
    }

    /**
     * Handle Heartbeat API check for syndication status
     *
     * @param array $response Heartbeat response data
     * @param array $data Data sent via heartbeat
     * @return array Modified response
     */
    public function check_syndication_status($response, $data)
    {
        // Check if this heartbeat is for syndication status
        if (empty($data['bluesky_check_syndication'])) {
            return $response;
        }

        $post_id = absint($data['bluesky_check_syndication']);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return $response;
        }

        // Get syndication status
        $status = get_post_meta($post_id, '_bluesky_syndication_status', true);

        $response['bluesky_syndication'] = [
            'post_id' => $post_id,
            'status' => $status,
            'timestamp' => time(),
        ];

        // Add extra data based on status
        if ($status === 'completed') {
            $completed_accounts = get_post_meta($post_id, '_bluesky_syndication_accounts_completed', true);
            $response['bluesky_syndication']['account_count'] = is_array($completed_accounts) ? count($completed_accounts) : 1;
        } elseif ($status === 'failed' || $status === 'partial') {
            $failed_accounts = get_post_meta($post_id, '_bluesky_syndication_failed_accounts', true);
            if (is_array($failed_accounts)) {
                $response['bluesky_syndication']['failed_accounts'] = array_keys($failed_accounts);
            }
        }

        return $response;
    }

    /**
     * Handle AJAX retry syndication request
     */
    public function handle_retry()
    {
        // Verify nonce
        check_ajax_referer('bluesky_retry_syndication', 'nonce');

        // Get and validate post ID
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Invalid post or insufficient permissions.', 'social-integration-for-bluesky')]);
        }

        // Get account IDs to retry
        $failed_accounts_meta = get_post_meta($post_id, '_bluesky_syndication_failed_accounts', true);

        // If we have failed accounts, retry only those; otherwise retry all configured accounts
        if (is_array($failed_accounts_meta) && !empty($failed_accounts_meta)) {
            $account_ids = array_keys($failed_accounts_meta);
        } else {
            // Get all configured accounts as fallback
            $account_manager = new BlueSky_Account_Manager();
            $accounts = $account_manager->get_all_accounts();
            $account_ids = array_column($accounts, 'id');
        }

        if (empty($account_ids)) {
            wp_send_json_error(['message' => __('No accounts found to retry.', 'social-integration-for-bluesky')]);
        }

        // Clear failed status metadata
        delete_post_meta($post_id, '_bluesky_syndication_failed_accounts');
        delete_post_meta($post_id, '_bluesky_syndication_retry_count');

        // Schedule syndication
        $this->async_handler->schedule_syndication($post_id, $account_ids);

        wp_send_json_success([
            'message' => __('Syndication retry scheduled.', 'social-integration-for-bluesky'),
            'post_id' => $post_id,
        ]);
    }

    /**
     * Add syndication status column to posts list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_syndication_column($columns)
    {
        // Insert before date column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['bluesky_syndication'] = __('Bluesky', 'social-integration-for-bluesky');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    /**
     * Render syndication status in post list column
     *
     * @param string $column_name Column name
     * @param int $post_id Post ID
     */
    public function render_syndication_column($column_name, $post_id)
    {
        if ($column_name !== 'bluesky_syndication') {
            return;
        }

        $status = get_post_meta($post_id, '_bluesky_syndication_status', true);

        // Fallback: check legacy meta for posts syndicated before async handler
        if (empty($status) && get_post_meta($post_id, '_bluesky_syndicated', true)) {
            $status = 'completed';
        }

        switch ($status) {
            case 'pending':
            case 'retrying':
                echo '<span class="dashicons dashicons-update" style="color:#f0b849;" title="' . esc_attr__('Syndicating...', 'social-integration-for-bluesky') . '"></span>';
                break;

            case 'completed':
                echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" title="' . esc_attr__('Syndicated', 'social-integration-for-bluesky') . '"></span>';
                break;

            case 'failed':
                $retry_nonce = wp_create_nonce('bluesky_retry_syndication');
                echo '<span class="dashicons dashicons-dismiss" style="color:#dc3232;" title="' . esc_attr__('Failed', 'social-integration-for-bluesky') . '"></span> ';
                echo '<a href="#" class="bluesky-retry-syndication" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($retry_nonce) . '" title="' . esc_attr__('Retry', 'social-integration-for-bluesky') . '">';
                echo '<span class="dashicons dashicons-controls-repeat" style="font-size:14px;"></span></a>';
                break;

            case 'partial':
                echo '<span class="dashicons dashicons-warning" style="color:#f0b849;" title="' . esc_attr__('Partially syndicated', 'social-integration-for-bluesky') . '"></span>';
                break;

            case 'circuit_open':
                echo '<span class="dashicons dashicons-clock" style="color:#f0b849;" title="' . esc_attr__('Waiting for cooldown', 'social-integration-for-bluesky') . '"></span>';
                break;

            case 'rate_limited':
                echo '<span class="dashicons dashicons-clock" style="color:#f0b849;" title="' . esc_attr__('Rate limited', 'social-integration-for-bluesky') . '"></span>';
                break;

            default:
                echo '<span style="color:#888;">—</span>';
                break;
        }
    }

    /**
     * Handle AJAX notice dismissal
     * Stores dismissal with 24-hour expiry in user meta
     */
    public function handle_notice_dismissal()
    {
        // Verify nonce
        check_ajax_referer('bluesky_dismiss_notice', 'nonce');

        // Get and validate notice key
        $notice_key = isset($_POST['notice_key']) ? sanitize_key($_POST['notice_key']) : '';

        // Validate notice_key is one of the allowed keys
        $allowed_keys = ['bluesky_expired_creds_dismissed', 'bluesky_circuit_breaker_dismissed'];
        if (!in_array($notice_key, $allowed_keys)) {
            wp_send_json_error(['message' => __('Invalid notice key.', 'social-integration-for-bluesky')]);
        }

        // Store dismissal with 24-hour expiry
        update_user_meta(get_current_user_id(), $notice_key, time() + DAY_IN_SECONDS);

        wp_send_json_success();
    }
}
