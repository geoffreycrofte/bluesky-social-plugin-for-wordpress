<?php
// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlueSky Health Dashboard Widget
 *
 * Provides a WordPress admin dashboard widget showing plugin health summary:
 * - Account statuses
 * - Last syndication result
 * - API health (circuit breaker)
 * - Cache status
 * - Pending retries
 * - Recent activity log
 *
 * @since 1.6.0
 */
class BlueSky_Health_Dashboard
{
    /**
     * Account Manager instance
     * @var BlueSky_Account_Manager
     */
    private $account_manager;

    /**
     * Constructor
     *
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     */
    public function __construct(BlueSky_Account_Manager $account_manager)
    {
        $this->account_manager = $account_manager;

        // Register dashboard widget
        add_action('wp_dashboard_setup', [$this, 'register_widget']);

        // Register AJAX handler for manual refresh
        add_action('wp_ajax_bluesky_refresh_health', [$this, 'handle_refresh']);
    }

    /**
     * Register the dashboard widget
     */
    public function register_widget()
    {
        wp_add_dashboard_widget(
            'bluesky_health_widget',
            __('Bluesky Integration Health', 'social-integration-for-bluesky'),
            [$this, 'render_widget']
        );
    }

    /**
     * Render the dashboard widget
     */
    public function render_widget()
    {
        // Try to get cached health data first (5-minute cache)
        $health_data = get_transient('bluesky_health_data');

        if ($health_data === false) {
            // No cache — collect fresh data
            $health_data = $this->collect_health_data();
            // Cache for 5 minutes (300 seconds)
            set_transient('bluesky_health_data', $health_data, 300);
        }

        // Render widget HTML
        echo '<div class="bluesky-health-widget">';

        // Account Status Section (open by default)
        echo '<details open class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('Account Status', 'social-integration-for-bluesky') . '</summary>';
        if (!empty($health_data['accounts'])) {
            echo '<ul class="bluesky-health-accounts">';
            foreach ($health_data['accounts'] as $account) {
                echo '<li>';
                echo '<span class="dashicons ' . esc_attr($account['icon']) . '"></span>';
                echo '<strong>' . esc_html($account['handle']) . '</strong>: ' . esc_html($account['status']);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('No accounts configured.', 'social-integration-for-bluesky') . '</p>';
        }
        echo '</details>';

        // Last Syndication Section
        echo '<details class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('Last Syndication', 'social-integration-for-bluesky') . '</summary>';
        echo '<p>' . esc_html($health_data['last_syndication']) . '</p>';
        echo '</details>';

        // API Health Section
        echo '<details class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('API Health', 'social-integration-for-bluesky') . '</summary>';
        echo '<p>' . esc_html($health_data['api_health']) . '</p>';
        echo '</details>';

        // Cache Status Section
        echo '<details class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('Cache Status', 'social-integration-for-bluesky') . '</summary>';
        echo '<p>' . esc_html($health_data['cache_status']) . '</p>';
        echo '</details>';

        // Pending Retries Section
        echo '<details class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('Pending Retries', 'social-integration-for-bluesky') . '</summary>';
        echo '<p>' . esc_html($health_data['pending_retries']) . '</p>';
        echo '</details>';

        // Recent Activity Section
        echo '<details class="bluesky-health-detail">';
        echo '<summary>' . esc_html__('Recent Activity', 'social-integration-for-bluesky') . '</summary>';
        if (!empty($health_data['recent_activity'])) {
            echo '<ul class="bluesky-health-activity">';
            foreach ($health_data['recent_activity'] as $event) {
                echo '<li>';
                echo '<span class="bluesky-activity-time">' . esc_html($event['time']) . '</span>';
                echo esc_html($event['message']);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="description">' . esc_html__('No recent activity recorded.', 'social-integration-for-bluesky') . '</p>';
        }
        echo '</details>';

        // Footer with refresh button and link to settings
        $settings_url = admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME . '#health');
        echo '<p class="bluesky-widget-footer" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
        echo '<button type="button" class="button button-small" id="bluesky-refresh-health">';
        echo '<span class="dashicons dashicons-update"></span> ' . esc_html__('Refresh', 'social-integration-for-bluesky');
        echo '</button> ';
        echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('View detailed health', 'social-integration-for-bluesky') . '</a>';
        echo '</p>';

        echo '</div>'; // End .bluesky-health-widget

        // Inline JavaScript for refresh button
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#bluesky-refresh-health').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).find('.dashicons').addClass('spin');
                $.post(ajaxurl, {
                    action: 'bluesky_refresh_health',
                    nonce: '<?php echo wp_create_nonce("bluesky_refresh_health"); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }).always(function() {
                    btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                });
            });
        });
        </script>
        <style>
        .dashicons.spin {
            animation: rotation 1s infinite linear;
        }
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(359deg); }
        }
        </style>
        <?php
    }

    /**
     * Collect health data from various sources
     *
     * @return array Health data array
     */
    private function collect_health_data()
    {
        $data = [
            'accounts' => [],
            'last_syndication' => __('No recent syndication', 'social-integration-for-bluesky'),
            'api_health' => __('All systems operational', 'social-integration-for-bluesky'),
            'cache_status' => '',
            'pending_retries' => '0',
            'recent_activity' => []
        ];

        // 1. Account Statuses
        $accounts = $this->account_manager->get_accounts();
        foreach ($accounts as $account) {
            $account_id = $account['id'] ?? '';
            $handle = $account['handle'] ?? '';

            // Check circuit breaker status
            $circuit_breaker = new BlueSky_Circuit_Breaker($account_id);
            $is_open = ! $circuit_breaker->is_available();

            // Check rate limiter status
            $rate_limiter = new BlueSky_Rate_Limiter();
            $is_rate_limited = $rate_limiter->is_rate_limited($account_id);

            // Determine status
            if ($is_open) {
                $icon = 'dashicons-warning';
                $status = __('Circuit open', 'social-integration-for-bluesky');
            } elseif ($is_rate_limited) {
                $icon = 'dashicons-clock';
                $status = __('Rate limited', 'social-integration-for-bluesky');
            } elseif (empty($account['did'])) {
                $icon = 'dashicons-warning';
                $status = __('Auth issue', 'social-integration-for-bluesky');
            } else {
                $icon = 'dashicons-yes-alt';
                $status = __('Active', 'social-integration-for-bluesky');
            }

            $data['accounts'][] = [
                'handle' => $handle,
                'icon' => $icon,
                'status' => $status
            ];
        }

        // 2. Last Syndication (from Activity Logger)
        $activity_logger = new BlueSky_Activity_Logger();
        $recent_events = $activity_logger->get_recent_events(1);
        if (!empty($recent_events)) {
            $last_event = $recent_events[0];
            $time_str = BlueSky_Activity_Logger::format_event_time($last_event['time']);
            $data['last_syndication'] = $time_str . ': ' . $last_event['message'];
        }

        // 3. API Health (Circuit Breaker summary)
        $broken_accounts = [];
        foreach ($accounts as $account) {
            $account_id = $account['id'] ?? '';
            $circuit_breaker = new BlueSky_Circuit_Breaker($account_id);
            if (! $circuit_breaker->is_available()) {
                $broken_accounts[] = $account['handle'] ?? $account_id;
            }
        }
        if (!empty($broken_accounts)) {
            $data['api_health'] = sprintf(
                __('Circuit open for: %s', 'social-integration-for-bluesky'),
                implode(', ', $broken_accounts)
            );
        }

        // 4. Cache Status
        $helpers = new BlueSky_Helpers();
        $profile_cached = get_transient($helpers->get_profile_transient_key()) !== false;
        $posts_cached = get_transient($helpers->get_posts_transient_key()) !== false;

        if ($profile_cached && $posts_cached) {
            $data['cache_status'] = __('Profile and posts cached', 'social-integration-for-bluesky');
        } elseif ($profile_cached) {
            $data['cache_status'] = __('Profile cached, posts not cached', 'social-integration-for-bluesky');
        } elseif ($posts_cached) {
            $data['cache_status'] = __('Posts cached, profile not cached', 'social-integration-for-bluesky');
        } else {
            $data['cache_status'] = __('No cache active', 'social-integration-for-bluesky');
        }

        // 5. Pending Retries
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_bluesky_syndication_status',
                    'value' => ['pending', 'retrying', 'rate_limited', 'circuit_open'],
                    'compare' => 'IN'
                ]
            ]
        ];
        $query = new WP_Query($args);
        $count = $query->found_posts;
        $data['pending_retries'] = sprintf(
            _n('%d post pending', '%d posts pending', $count, 'social-integration-for-bluesky'),
            $count
        );

        // 6. Recent Activity (last 5 events)
        $recent_events = $activity_logger->get_recent_events(5);
        foreach ($recent_events as $event) {
            $data['recent_activity'][] = [
                'time' => BlueSky_Activity_Logger::format_event_time($event['time']),
                'message' => $event['message']
            ];
        }

        return $data;
    }

    /**
     * AJAX handler for manual refresh
     */
    public function handle_refresh()
    {
        // Verify nonce
        check_ajax_referer('bluesky_refresh_health', 'nonce');

        // Delete transient cache
        delete_transient('bluesky_health_data');

        // Send success response
        wp_send_json_success();
    }
}
