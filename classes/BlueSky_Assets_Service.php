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
            ]);
        }
    }
}
