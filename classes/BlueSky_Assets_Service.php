<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Assets_Service
{
    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts()
    {
        // Enqueue admin notices script (persistent dismissal handler)
        $this->enqueue_admin_notices_script();

        // Enqueue syndication notice script on post edit screens
        $this->enqueue_syndication_notice_script();

        wp_enqueue_style(
            "bluesky-social-admin",
            BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-social-admin.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );
        wp_enqueue_style(
            "bluesky-social-profile",
            BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-social-profile.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );
        wp_enqueue_style(
            "bluesky-social-primsjs-css",
            BLUESKY_PLUGIN_FOLDER . "assets/css/prism.min.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );
        wp_enqueue_style(
            "bluesky-social-posts",
            BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-social-posts.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );
        wp_enqueue_script(
            "bluesky-social-script",
            BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-social-admin.js",
            ["jquery"],
            BLUESKY_PLUGIN_VERSION,
            ["in_footer" => true, "strategy" => "defer"],
        );
        wp_enqueue_script(
            "bluesky-social-prismjs-js",
            BLUESKY_PLUGIN_FOLDER . "assets/js/prism.min.js",
            [],
            BLUESKY_PLUGIN_VERSION,
            ["in_footer" => true, "strategy" => "defer"],
        );
        wp_enqueue_script(
            "bluesky-async-loader",
            BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-async-loader.js",
            [],
            BLUESKY_PLUGIN_VERSION,
            ["in_footer" => true, "strategy" => "defer"],
        );
        wp_localize_script("bluesky-async-loader", "blueskyAsync", [
            "ajaxUrl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("bluesky_async_nonce"),
            "i18n" => [
                "connectionFailed" => __("Connection to BlueSky failed. Please check your credentials.", "social-integration-for-bluesky"),
                "missingCredentials" => __("Handle or app password is not configured.", "social-integration-for-bluesky"),
                "networkError" => __("Could not reach BlueSky servers:", "social-integration-for-bluesky"),
                "rateLimitExceeded" => __("BlueSky rate limit exceeded. Please wait a few minutes before trying again.", "social-integration-for-bluesky"),
                "rateLimitResetsAt" => __("Resets at", "social-integration-for-bluesky"),
                "authFactorRequired" => __("BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA.", "social-integration-for-bluesky"),
                "accountTakedown" => __("This BlueSky account has been taken down.", "social-integration-for-bluesky"),
                "invalidCredentials" => __("Invalid handle or password. Please check your credentials.", "social-integration-for-bluesky"),
                "connectionSuccess" => __("Connection to BlueSky successful!", "social-integration-for-bluesky"),
                "logoutLink" => __("Log out from this account", "social-integration-for-bluesky"),
                "connectionCheckFailed" => __("Could not check connection status.", "social-integration-for-bluesky"),
                "contentLoadFailed" => __("Unable to load Bluesky content.", "social-integration-for-bluesky"),
                "connectionFallback" => __("Connection failed:", "social-integration-for-bluesky"),
            ],
        ]);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts()
    {
        if (!is_admin()) {
            wp_enqueue_style(
                "bluesky-social-style-profile",
                BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-social-profile.css",
                [],
                BLUESKY_PLUGIN_VERSION,
            );
            wp_enqueue_style(
                "bluesky-social-style-posts",
                BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-social-posts.css",
                [],
                BLUESKY_PLUGIN_VERSION,
            );
            wp_enqueue_script(
                "bluesky-async-loader",
                BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-async-loader.js",
                [],
                BLUESKY_PLUGIN_VERSION,
                ["in_footer" => true, "strategy" => "defer"],
            );
            wp_localize_script("bluesky-async-loader", "blueskyAsync", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce("bluesky_async_nonce"),
                "i18n" => [
                    "connectionFailed" => __("Connection to BlueSky failed. Please check your credentials.", "social-integration-for-bluesky"),
                    "missingCredentials" => __("Handle or app password is not configured.", "social-integration-for-bluesky"),
                    "networkError" => __("Could not reach BlueSky servers:", "social-integration-for-bluesky"),
                    "rateLimitExceeded" => __("BlueSky rate limit exceeded. Please wait a few minutes before trying again.", "social-integration-for-bluesky"),
                    "rateLimitResetsAt" => __("Resets at", "social-integration-for-bluesky"),
                    "authFactorRequired" => __("BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA.", "social-integration-for-bluesky"),
                    "accountTakedown" => __("This BlueSky account has been taken down.", "social-integration-for-bluesky"),
                    "invalidCredentials" => __("Invalid handle or password. Please check your credentials.", "social-integration-for-bluesky"),
                    "connectionSuccess" => __("Connection to BlueSky successful!", "social-integration-for-bluesky"),
                    "logoutLink" => __("Log out from this account", "social-integration-for-bluesky"),
                    "connectionCheckFailed" => __("Could not check connection status.", "social-integration-for-bluesky"),
                    "contentLoadFailed" => __("Unable to load Bluesky content.", "social-integration-for-bluesky"),
                    "connectionFallback" => __("Connection failed:", "social-integration-for-bluesky"),
                ],
            ]);

            // Enqueue Color Thief from CDN for gradient fallback
            wp_enqueue_script(
                "color-thief",
                "https://cdn.jsdelivr.net/npm/colorthief@2.4.0/dist/color-thief.min.js",
                [],
                "2.4.0",
                true,
            );

            // Enqueue profile banner gradient script
            wp_enqueue_script(
                "bluesky-profile-banner-gradient",
                BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-profile-banner-gradient.js",
                ["color-thief"],
                BLUESKY_PLUGIN_VERSION,
                ["in_footer" => true, "strategy" => "defer"],
            );
        }
    }

    /**
     * Enqueue admin notices script
     * Handles AJAX dismissal of persistent notices (expired credentials, circuit breaker)
     * Loads on all admin pages (lightweight script < 1KB)
     */
    private function enqueue_admin_notices_script()
    {
        wp_enqueue_script(
            'bluesky-admin-notices',
            BLUESKY_PLUGIN_FOLDER . 'assets/js/bluesky-admin-notices.js',
            ['jquery'],
            BLUESKY_PLUGIN_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );

        wp_localize_script('bluesky-admin-notices', 'blueskyAdminNotices', [
            'dismissNonce' => wp_create_nonce('bluesky_dismiss_notice'),
        ]);
    }

    /**
     * Enqueue syndication notice script on post edit screens
     * Only loads when post has syndication status (performance optimization)
     */
    private function enqueue_syndication_notice_script()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        global $post;
        if (!$post || !isset($post->ID)) {
            return;
        }

        // Only load script if post has syndication status
        $status = get_post_meta($post->ID, '_bluesky_syndication_status', true);
        if (empty($status)) {
            return;
        }

        wp_enqueue_script(
            'bluesky-syndication-notice',
            BLUESKY_PLUGIN_FOLDER . 'assets/js/bluesky-syndication-notice.js',
            ['jquery', 'heartbeat'],
            BLUESKY_PLUGIN_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );

        wp_localize_script('bluesky-syndication-notice', 'blueskyNotice', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'retryNonce' => wp_create_nonce('bluesky_retry_syndication'),
            'i18n' => [
                'syndicating' => __('Syndicating to Bluesky...', 'social-integration-for-bluesky'),
                'completed_single' => __('Successfully syndicated to Bluesky.', 'social-integration-for-bluesky'),
                'completed_multiple' => __('Successfully syndicated to %d Bluesky accounts.', 'social-integration-for-bluesky'),
                'failed' => __('Failed to syndicate to Bluesky accounts: %s', 'social-integration-for-bluesky'),
                'partial' => __('Partially syndicated: some accounts failed.', 'social-integration-for-bluesky'),
                'retrying' => __('Retrying syndication to Bluesky...', 'social-integration-for-bluesky'),
                'retry_now' => __('Retry now', 'social-integration-for-bluesky'),
                'retry_failed' => __('Retry failed', 'social-integration-for-bluesky'),
                'retry_error' => __('Failed to retry syndication. Please try again.', 'social-integration-for-bluesky'),
                'unknown_account' => __('unknown', 'social-integration-for-bluesky'),
            ],
        ]);
    }
}
