<?php
// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlueSky Error Translator
 *
 * Translates raw Bluesky API errors into user-friendly messages
 * following the "explain + action" pattern.
 *
 * @since 1.6.0
 */
class BlueSky_Error_Translator
{
    /**
     * Translate raw API error into user-friendly message
     *
     * @param array $error_data Error data with keys: code, message, status
     * @param string $context Context of the error (auth, syndication, fetch, general)
     * @return array Associative array with 'message' and 'action' keys
     */
    public static function translate_error($error_data, $context = 'general')
    {
        $code = $error_data['code'] ?? '';
        $status = $error_data['status'] ?? 0;

        // Authentication errors (HTTP 401 or auth-related codes)
        if (
            $status === 401 ||
            $code === 'AuthenticationRequired' ||
            $code === 'InvalidToken' ||
            $code === 'ExpiredToken'
        ) {
            return [
                'message' => __('We couldn\'t connect to Bluesky. Your credentials may have expired.', 'social-integration-for-bluesky'),
                'action' => [
                    'label' => __('Go to Settings', 'social-integration-for-bluesky'),
                    'url' => admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME)
                ]
            ];
        }

        // Rate limiting (HTTP 429)
        if ($status === 429 || $code === 'RateLimitExceeded') {
            return [
                'message' => __('Bluesky is temporarily rate limiting requests. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Network errors (HTTP 0, 503, or network-related code)
        if ($status === 0 || $status === 503 || $code === 'NetworkError') {
            return [
                'message' => __('Couldn\'t reach Bluesky right now. This is usually temporary. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Invalid handle configuration
        if ($code === 'InvalidHandle') {
            return [
                'message' => __('The Bluesky handle appears to be invalid. Please check that you entered it correctly.', 'social-integration-for-bluesky'),
                'action' => [
                    'label' => __('Go to Settings', 'social-integration-for-bluesky'),
                    'url' => admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME)
                ]
            ];
        }

        // Bad request (HTTP 400)
        if ($status === 400) {
            return [
                'message' => __('Bluesky couldn\'t process this request. The post content may have an issue.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Permission errors (HTTP 403)
        if ($status === 403) {
            return [
                'message' => __('You don\'t have permission for this action on Bluesky. Your account settings may need updating.', 'social-integration-for-bluesky'),
                'action' => [
                    'label' => __('Go to Settings', 'social-integration-for-bluesky'),
                    'url' => admin_url('options-general.php?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME)
                ]
            ];
        }

        // Server errors (HTTP 500+)
        if ($status >= 500) {
            return [
                'message' => __('Bluesky is experiencing issues right now. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                'action' => null
            ];
        }

        // Unknown error - use context-specific generic message
        return self::generic_error($context);
    }

    /**
     * Get generic fallback error message by context
     *
     * @param string $context Error context (auth, syndication, fetch, general)
     * @return array Associative array with 'message' and 'action' keys
     */
    private static function generic_error($context)
    {
        switch ($context) {
            case 'auth':
                return [
                    'message' => __('Something went wrong while connecting to Bluesky. Please try again.', 'social-integration-for-bluesky'),
                    'action' => null
                ];

            case 'syndication':
                return [
                    'message' => __('We couldn\'t post to Bluesky right now. Your post will be retried automatically.', 'social-integration-for-bluesky'),
                    'action' => null
                ];

            case 'fetch':
                return [
                    'message' => __('We couldn\'t load Bluesky content right now. Please try refreshing the page.', 'social-integration-for-bluesky'),
                    'action' => null
                ];

            case 'general':
            default:
                return [
                    'message' => __('Something went wrong with the Bluesky connection. Please try again.', 'social-integration-for-bluesky'),
                    'action' => null
                ];
        }
    }

    /**
     * Get friendly circuit breaker status message
     *
     * @param bool $is_open Whether the circuit breaker is open
     * @return string Friendly status message
     */
    public static function circuit_breaker_message($is_open)
    {
        if ($is_open) {
            return __('Bluesky requests are paused due to repeated errors. They\'ll resume automatically.', 'social-integration-for-bluesky');
        }

        return __('Bluesky connection is healthy.', 'social-integration-for-bluesky');
    }

    /**
     * Get friendly syndication status message
     *
     * @param string $status Syndication status (pending, retrying, rate_limited, circuit_open, completed, failed, partial)
     * @param array $extra_data Additional data for context
     * @return string Friendly status message
     */
    public static function syndication_status_message($status, $extra_data = [])
    {
        switch ($status) {
            case 'pending':
                return __('Syndicating to Bluesky...', 'social-integration-for-bluesky');

            case 'retrying':
                return __('Retrying syndication to Bluesky...', 'social-integration-for-bluesky');

            case 'rate_limited':
                return __('Rate limited — will retry automatically.', 'social-integration-for-bluesky');

            case 'circuit_open':
                return __('Bluesky requests paused due to repeated errors. Will resume automatically.', 'social-integration-for-bluesky');

            case 'completed':
                return __('Successfully posted to Bluesky.', 'social-integration-for-bluesky');

            case 'failed':
                return __('Couldn\'t post to Bluesky.', 'social-integration-for-bluesky');

            case 'partial':
                return __('Posted to some Bluesky accounts. Some accounts had issues.', 'social-integration-for-bluesky');

            default:
                return __('Unknown syndication status.', 'social-integration-for-bluesky');
        }
    }

    /**
     * Format action link as HTML
     *
     * @param array|null $action Action array with 'label' and 'url' keys, or null
     * @return string HTML link string or empty string
     */
    public static function format_action_link($action)
    {
        if (!$action || !isset($action['label']) || !isset($action['url'])) {
            return '';
        }

        return sprintf(
            '<a href="%s" class="bluesky-error-action">%s</a>',
            esc_url($action['url']),
            esc_html($action['label'])
        );
    }
}
