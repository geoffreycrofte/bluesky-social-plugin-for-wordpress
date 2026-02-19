<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Plugin_Setup
{
    /**
     * Settings service instance
     * @var BlueSky_Settings_Service
     */
    private $settings;

    /**
     * AJAX service instance
     * @var BlueSky_AJAX_Service
     */
    private $ajax;

    /**
     * Syndication service instance
     * @var BlueSky_Syndication_Service
     */
    private $syndication;

    /**
     * Assets service instance
     * @var BlueSky_Assets_Service
     */
    private $assets;

    /**
     * Blocks service instance
     * @var BlueSky_Blocks_Service
     */
    private $blocks;

    /**
     * Render Front instance
     * @var BlueSky_Render_Front
     */
    private $render_front;

    /**
     * Admin notices instance
     * @var BlueSky_Admin_Notices
     */
    private $admin_notices;

    /**
     * Health dashboard instance
     * @var BlueSky_Health_Dashboard
     */
    private $health_dashboard;

    /**
     * Health monitor instance
     * @var BlueSky_Health_Monitor
     */
    private $health_monitor;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     */
    public function __construct(
        BlueSky_API_Handler $api_handler,
        ?BlueSky_Account_Manager $account_manager = null
    ) {
        $async_handler        = new BlueSky_Async_Handler($api_handler, $account_manager);
        $this->render_front   = new BlueSky_Render_Front($api_handler);
        $this->settings       = new BlueSky_Settings_Service($api_handler, $account_manager);
        $this->ajax           = new BlueSky_AJAX_Service($api_handler, $account_manager);
        $this->syndication    = new BlueSky_Syndication_Service($api_handler, $account_manager, $async_handler);
        $this->assets         = new BlueSky_Assets_Service();
        $this->blocks         = new BlueSky_Blocks_Service($api_handler, $account_manager, $this->render_front);
        $this->admin_notices  = new BlueSky_Admin_Notices($async_handler, $account_manager);
        $this->health_dashboard = new BlueSky_Health_Dashboard($account_manager);
        $this->health_monitor = new BlueSky_Health_Monitor();

        // On activation
        register_activation_hook(BLUESKY_PLUGIN_FILE, [
            $this->syndication,
            "on_plugin_activation",
        ]);

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     * Maps all hook registrations to the appropriate service.
     * Preserves exact hook names, priorities, and accepted_args from the original.
     */
    private function init_hooks()
    {
        // Internationalization
        add_action("init", [$this->blocks, "load_plugin_textdomain"]);
        add_filter("plugin_action_links_" . BLUESKY_PLUGIN_BASENAME, [
            $this->settings,
            "add_plugin_action_links",
        ]);

        // Admin menu and settings
        add_action("admin_menu", [$this->settings, "add_admin_menu"]);
        add_action("admin_init", [$this->settings, "register_settings"]);

        // Script and style enqueuing
        add_action("admin_enqueue_scripts", [$this->assets, "admin_enqueue_scripts"]);
        add_action("wp_enqueue_scripts", [$this->assets, "frontend_enqueue_scripts"]);

        // AJAX actions
        add_action("wp_ajax_fetch_bluesky_posts", [
            $this->ajax,
            "ajax_fetch_bluesky_posts",
        ]);
        add_action("wp_ajax_nopriv_fetch_bluesky_posts", [
            $this->ajax,
            "ajax_fetch_bluesky_posts",
        ]);

        add_action("wp_ajax_get_bluesky_profile", [
            $this->ajax,
            "ajax_get_bluesky_profile",
        ]);
        add_action("wp_ajax_nopriv_get_bluesky_profile", [
            $this->ajax,
            "ajax_get_bluesky_profile",
        ]);

        // Async loading AJAX actions
        add_action("wp_ajax_bluesky_async_posts", [
            $this->ajax,
            "ajax_async_posts",
        ]);
        add_action("wp_ajax_nopriv_bluesky_async_posts", [
            $this->ajax,
            "ajax_async_posts",
        ]);
        add_action("wp_ajax_bluesky_async_profile", [
            $this->ajax,
            "ajax_async_profile",
        ]);
        add_action("wp_ajax_nopriv_bluesky_async_profile", [
            $this->ajax,
            "ajax_async_profile",
        ]);
        add_action("wp_ajax_bluesky_async_auth", [
            $this->ajax,
            "ajax_async_auth",
        ]);
        add_action("wp_ajax_bluesky_set_discussion_account", [
            $this->ajax,
            "ajax_set_discussion_account",
        ]);

        // Widgets and Gutenberg blocks
        add_action("widgets_init", [$this->blocks, "register_widgets"]);
        add_action("init", [$this->blocks, "register_gutenberg_blocks"]);

        // Post syndication
        add_action(
            "transition_post_status",
            [$this->syndication, "syndicate_post_to_bluesky"],
            10,
            3,
        );

        // Messaging & Noticing
        add_action("admin_notices", [$this->settings, "display_bluesky_logout_message"]);
    }
}
