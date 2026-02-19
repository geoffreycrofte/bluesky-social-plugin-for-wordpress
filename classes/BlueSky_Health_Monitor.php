<?php
// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlueSky Health Monitor
 *
 * Integrates with WordPress Site Health API (Tools > Site Health) to provide:
 * - Pass/fail status checks for accounts, credentials, and circuit breaker
 * - Debug information section with plugin version, API details, and per-account state
 *
 * @since 1.6.0
 */
class BlueSky_Health_Monitor
{
    /**
     * Account Manager instance
     * @var BlueSky_Account_Manager
     */
    private $account_manager;

    /**
     * Constructor
     * Registers Site Health filters for tests and debug info.
     */
    public function __construct()
    {
        $this->account_manager = new BlueSky_Account_Manager();

        // Register WordPress Site Health integration
        add_filter('site_status_tests', [$this, 'register_health_tests']);
        add_filter('debug_information', [$this, 'register_debug_info']);
    }

    /**
     * Register health tests with WordPress Site Health
     *
     * @param array $tests Existing tests
     * @return array Modified tests with Bluesky checks
     */
    public function register_health_tests($tests)
    {
        // All tests are 'direct' (synchronous) — they check local state, not live API
        $tests['direct']['bluesky_accounts_configured'] = [
            'label' => __('Bluesky accounts configured', 'social-integration-for-bluesky'),
            'test'  => [$this, 'test_accounts_configured'],
        ];

        $tests['direct']['bluesky_credentials_valid'] = [
            'label' => __('Bluesky credentials valid', 'social-integration-for-bluesky'),
            'test'  => [$this, 'test_credentials_valid'],
        ];

        $tests['direct']['bluesky_circuit_breaker'] = [
            'label' => __('Bluesky API connections', 'social-integration-for-bluesky'),
            'test'  => [$this, 'test_circuit_breaker'],
        ];

        return $tests;
    }

    /**
     * Test: Check if Bluesky accounts are configured
     *
     * @return array Site Health test result
     */
    public function test_accounts_configured()
    {
        $accounts = $this->account_manager->get_all_accounts();
        $account_count = count($accounts);

        if ($account_count === 0) {
            return [
                'label'       => __('No Bluesky accounts configured', 'social-integration-for-bluesky'),
                'status'      => 'recommended',
                'badge'       => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'blue',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('Your site is not currently connected to any Bluesky accounts. To enable syndication and display features, add at least one account in the plugin settings.', 'social-integration-for-bluesky')
                ),
                'actions'     => sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME),
                    __('Go to Bluesky Settings', 'social-integration-for-bluesky')
                ),
                'test'        => 'bluesky_accounts_configured',
            ];
        }

        return [
            'label'       => __('Bluesky accounts configured', 'social-integration-for-bluesky'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Performance', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                sprintf(
                    _n(
                        'Your site is connected to %d Bluesky account.',
                        'Your site is connected to %d Bluesky accounts.',
                        $account_count,
                        'social-integration-for-bluesky'
                    ),
                    $account_count
                )
            ),
            'test'        => 'bluesky_accounts_configured',
        ];
    }

    /**
     * Test: Check if Bluesky credentials are valid
     *
     * Checks the auth errors registry for known authentication failures.
     *
     * @return array Site Health test result
     */
    public function test_credentials_valid()
    {
        $auth_errors = get_option('bluesky_account_auth_errors', []);

        if (!empty($auth_errors)) {
            $affected_accounts = [];
            foreach ($auth_errors as $account_id => $error_data) {
                $account = $this->account_manager->get_account_by_id($account_id);
                if ($account) {
                    $affected_accounts[] = $account['handle'];
                }
            }

            $error_count = count($affected_accounts);

            return [
                'label'       => __('Bluesky accounts need re-authentication', 'social-integration-for-bluesky'),
                'status'      => 'critical',
                'badge'       => [
                    'label' => __('Security', 'social-integration-for-bluesky'),
                    'color' => 'red',
                ],
                'description' => sprintf(
                    '<p>%s</p><ul><li>%s</li></ul>',
                    sprintf(
                        _n(
                            '%d Bluesky account has authentication errors and needs to be re-authenticated:',
                            '%d Bluesky accounts have authentication errors and need to be re-authenticated:',
                            $error_count,
                            'social-integration-for-bluesky'
                        ),
                        $error_count
                    ),
                    implode('</li><li>', array_map('esc_html', $affected_accounts))
                ),
                'actions'     => sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME),
                    __('Go to Bluesky Settings', 'social-integration-for-bluesky')
                ),
                'test'        => 'bluesky_credentials_valid',
            ];
        }

        return [
            'label'       => __('All Bluesky credentials valid', 'social-integration-for-bluesky'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Security', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __('All Bluesky accounts are properly authenticated and ready to use.', 'social-integration-for-bluesky')
            ),
            'test'        => 'bluesky_credentials_valid',
        ];
    }

    /**
     * Test: Check circuit breaker state
     *
     * @return array Site Health test result
     */
    public function test_circuit_breaker()
    {
        $accounts = $this->account_manager->get_all_accounts();
        $open_breakers = [];

        foreach ($accounts as $account) {
            $breaker = new BlueSky_Circuit_Breaker($account['id']);
            if (!$breaker->is_request_allowed()) {
                $open_breakers[] = $account['handle'];
            }
        }

        if (!empty($open_breakers)) {
            $breaker_count = count($open_breakers);

            return [
                'label'       => __('Bluesky API requests paused', 'social-integration-for-bluesky'),
                'status'      => 'recommended',
                'badge'       => [
                    'label' => __('Performance', 'social-integration-for-bluesky'),
                    'color' => 'orange',
                ],
                'description' => sprintf(
                    '<p>%s</p><p>%s</p><ul><li>%s</li></ul>',
                    sprintf(
                        _n(
                            '%d Bluesky account has experienced repeated API failures and is currently in a cooldown period:',
                            '%d Bluesky accounts have experienced repeated API failures and are currently in cooldown periods:',
                            $breaker_count,
                            'social-integration-for-bluesky'
                        ),
                        $breaker_count
                    ),
                    __('Requests to these accounts will resume automatically after the cooldown period expires (15 minutes). This is normal behavior when the API is experiencing issues.', 'social-integration-for-bluesky'),
                    implode('</li><li>', array_map('esc_html', $open_breakers))
                ),
                'test'        => 'bluesky_circuit_breaker',
            ];
        }

        return [
            'label'       => __('Bluesky API connections healthy', 'social-integration-for-bluesky'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Performance', 'social-integration-for-bluesky'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __('All Bluesky accounts are responding normally with no circuit breaker activations.', 'social-integration-for-bluesky')
            ),
            'test'        => 'bluesky_circuit_breaker',
        ];
    }

    /**
     * Register debug information with WordPress Site Health
     *
     * @param array $info Existing debug info
     * @return array Modified debug info with Bluesky section
     */
    public function register_debug_info($info)
    {
        $options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
        $accounts = $this->account_manager->get_all_accounts();

        // Calculate cache duration in minutes
        $cache_seconds = isset($options['total_seconds']) ? (int) $options['total_seconds'] : 600;
        $cache_minutes = round($cache_seconds / 60);

        $fields = [
            'plugin_version' => [
                'label' => __('Plugin Version', 'social-integration-for-bluesky'),
                'value' => BLUESKY_PLUGIN_VERSION,
            ],
            'account_count' => [
                'label' => __('Account Count', 'social-integration-for-bluesky'),
                'value' => count($accounts),
            ],
            'api_endpoint' => [
                'label' => __('API Endpoint', 'social-integration-for-bluesky'),
                'value' => 'https://bsky.social/xrpc/',
            ],
            'cache_duration' => [
                'label' => __('Cache Duration', 'social-integration-for-bluesky'),
                'value' => sprintf(
                    _n('%d minute', '%d minutes', $cache_minutes, 'social-integration-for-bluesky'),
                    $cache_minutes
                ),
            ],
            'action_scheduler' => [
                'label' => __('Action Scheduler', 'social-integration-for-bluesky'),
                'value' => function_exists('as_schedule_single_action')
                    ? __('Available', 'social-integration-for-bluesky')
                    : __('Not available', 'social-integration-for-bluesky'),
            ],
        ];

        // Add per-account resilience state (marked as private)
        foreach ($accounts as $account) {
            $circuit_breaker = new BlueSky_Circuit_Breaker($account['id']);
            $rate_limiter = new BlueSky_Rate_Limiter();

            $circuit_status = $circuit_breaker->is_request_allowed()
                ? __('Closed', 'social-integration-for-bluesky')
                : __('Open', 'social-integration-for-bluesky');

            $rate_limited = $rate_limiter->is_rate_limited($account['id'])
                ? __('Yes', 'social-integration-for-bluesky')
                : __('No', 'social-integration-for-bluesky');

            $fields["account_{$account['id']}_status"] = [
                'label'   => sprintf(
                    __('Account: %s', 'social-integration-for-bluesky'),
                    esc_html($account['handle'])
                ),
                'value'   => sprintf(
                    __('Circuit: %s | Rate Limited: %s', 'social-integration-for-bluesky'),
                    $circuit_status,
                    $rate_limited
                ),
                'private' => true,
            ];
        }

        $info['bluesky'] = [
            'label'  => __('Bluesky Integration', 'social-integration-for-bluesky'),
            'fields' => $fields,
        ];

        return $info;
    }
}
