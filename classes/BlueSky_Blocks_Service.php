<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Blocks_Service
{
    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * API Handler instance
     * @var BlueSky_API_Handler
     */
    private $api_handler;

    /**
     * Account Manager instance
     * @var BlueSky_Account_Manager
     */
    private $account_manager;

    /**
     * Render Front instance
     * @var BlueSky_Render_Front
     */
    private $render_front;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     * @param BlueSky_Render_Front $render_front Render front instance
     */
    public function __construct(
        BlueSky_API_Handler $api_handler,
        BlueSky_Account_Manager $account_manager = null,
        BlueSky_Render_Front $render_front = null
    ) {
        $this->api_handler = $api_handler;
        $this->account_manager = $account_manager;
        $this->render_front = $render_front;
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    }

    /**
     * Load plugin text domain for internationalization
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            "social-integration-for-bluesky",
            false,
            BLUESKY_PLUGIN_DIRECTORY_NAME . "/languages",
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets()
    {
        register_widget("BlueSky_Posts_Widget");
        register_widget("BlueSky_Profile_Widget");
    }

    /**
     * Register Gutenberg blocks
     */
    public function register_gutenberg_blocks()
    {
        // Prepare account data for blocks
        $account_options = [
            ["value" => "", "label" => __("Active Account (default)", "social-integration-for-bluesky")]
        ];

        if ($this->account_manager && $this->account_manager->is_multi_account_enabled()) {
            $accounts = $this->account_manager->get_accounts();
            foreach ($accounts as $account_key => $account) {
                $account_options[] = [
                    "value" => $account["id"] ?? $account_key,
                    "label" => sprintf(
                        "%s (@%s)",
                        $account["name"] ?? $account["handle"] ?? '',
                        $account["handle"] ?? ''
                    )
                ];
            }
        }

        // Register Posts Feed
        wp_register_script(
            "bluesky-posts-block",
            BLUESKY_PLUGIN_FOLDER . "blocks/bluesky-posts-feed.js",
            [
                "wp-blocks",
                "wp-element",
                "wp-block-editor",
                "wp-components",
                "wp-server-side-render",
            ],
            BLUESKY_PLUGIN_VERSION,
            [
                "in_footer" => true,
                "strategy" => "defer",
            ],
        );

        wp_localize_script(
            "bluesky-posts-block",
            "blueskyBlockData",
            ["accounts" => $account_options]
        );

        register_block_type("bluesky-social/posts", [
            "api_version" => 2,
            "editor_script" => "bluesky-posts-block",
            "render_callback" => [$this, "bluesky_posts_block_render"],
            "attributes" => [
                "displayembeds" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "noreplies" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "noreposts" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "nocounters" => [
                    "type" => "boolean",
                    "default" => false,
                ],
                "theme" => [
                    "type" => "string",
                    "default" => "system",
                ],
                "numberofposts" => [
                    "type" => "integer",
                    "default" =>
                        get_option(BLUESKY_PLUGIN_OPTIONS)["posts_limit"] ?? 5,
                ],
                "accountId" => [
                    "type" => "string",
                    "default" => "",
                ],
            ],
        ]);

        // Register Profile Card
        wp_register_script(
            "bluesky-profile-block",
            BLUESKY_PLUGIN_FOLDER . "blocks/bluesky-profile-card.js",
            [
                "wp-blocks",
                "wp-element",
                "wp-block-editor",
                "wp-components",
                "wp-server-side-render",
            ],
            BLUESKY_PLUGIN_VERSION,
            [
                "in_footer" => true,
                "strategy" => "defer",
            ],
        );

        wp_localize_script(
            "bluesky-profile-block",
            "blueskyBlockData",
            ["accounts" => $account_options]
        );

        register_block_type("bluesky-social/profile", [
            "api_version" => 2,
            "attributes" => [
                "displaybanner" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "displayavatar" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "displaycounters" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "displaybio" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "theme" => [
                    "type" => "string",
                    "default" => "system",
                ],
                "classname" => [
                    "type" => "string",
                    "default" => "",
                ],
                "style" => [
                    "type" => "string",
                    "default" => "default",
                ],
                "accountId" => [
                    "type" => "string",
                    "default" => "",
                ],
            ],
            "styles" => [
                [
                    "name" => "default",
                    "label" => __("Rounded", "social-integration-for-bluesky"),
                    "isDefault" => true,
                ],
                [
                    "name" => "outline",
                    "label" => __("Outline", "social-integration-for-bluesky"),
                ],
                [
                    "name" => "squared",
                    "label" => __("Squared", "social-integration-for-bluesky"),
                ],
            ],
            "editor_script" => "bluesky-profile-block",
            "render_callback" => [$this, "bluesky_profile_block_render"],
        ]);
    }

    /**
     * Renders the BlueSky profile card block
     *
     * @param array $attributes Block attributes including:
     *                         - displaybanner (bool) Whether to show the profile banner
     *                         - displayavatar (bool) Whether to show the profile avatar
     *                         - displaycounters (bool) Whether to show follower/following counts
     *                         - displaybio (bool) Whether to show the profile bio
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     *                         - classname (string) A custom string classname
     * @return string HTML markup for the profile card
     */
    public function bluesky_profile_block_render($attributes = [])
    {
        // Convert camelCase block attributes to snake_case for PHP rendering
        if (isset($attributes['accountId'])) {
            $attributes['account_id'] = $attributes['accountId'];
        }

        // Get the style class
        $style_class = !empty($attributes["classname"])
            ? $attributes["classname"]
            : "is-style-default";

        // Add the style class to the attributes for the render function
        $attributes["styleClass"] = $style_class;

        $api_handler = $this->resolve_api_handler($attributes['account_id'] ?? '');
        $render_front = new BlueSky_Render_Front($api_handler);
        return $render_front->render_bluesky_profile_card($attributes);
    }

    /**
     * Renders the BlueSky posts feed block
     *
     * @param array $attributes Block attributes including:
     *                         - displayembeds (bool) Whether to show embedded media in posts
     *                         - noreplies (bool) Whether to show replies in posts
     *                         - noreposts (bool) Whether to show reposts in posts
     *                         - nocounters (bool) Whether to hide like/repost/reply/quote counters
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     *                         - numberofposts (int) Number of posts to display (1-10)
     * @return string HTML markup for the posts feed
     */
    public function bluesky_posts_block_render($attributes = [])
    {
        // Convert camelCase block attributes to snake_case for PHP rendering
        if (isset($attributes['accountId'])) {
            $attributes['account_id'] = $attributes['accountId'];
        }

        $api_handler = $this->resolve_api_handler($attributes['account_id'] ?? '');
        $render_front = new BlueSky_Render_Front($api_handler);
        return $render_front->render_bluesky_posts_list($attributes);
    }

    /**
     * Resolve the correct API handler for a given account_id.
     * Returns a per-account handler if account_id is provided and multi-account is enabled,
     * otherwise returns the default handler.
     *
     * @param string $account_id Account UUID or empty string
     * @return BlueSky_API_Handler
     */
    private function resolve_api_handler($account_id)
    {
        if ($this->account_manager && $this->account_manager->is_multi_account_enabled()) {
            // Specific account selected
            if (!empty($account_id)) {
                $account = $this->account_manager->get_account($account_id);
                if ($account) {
                    return BlueSky_API_Handler::create_for_account($account);
                }
            }

            // "Active Account (default)" — use the account marked is_active
            $active = $this->account_manager->get_active_account();
            if ($active) {
                return BlueSky_API_Handler::create_for_account($active);
            }
        }
        return $this->api_handler;
    }
}
