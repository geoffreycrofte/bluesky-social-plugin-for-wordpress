<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky Discussion Metabox
 *
 * Handles admin metabox registration, rendering, and admin AJAX handlers.
 */
class BlueSky_Discussion_Metabox
{
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
     * Discussion Renderer instance
     * @var BlueSky_Discussion_Renderer
     */
    private $renderer;

    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     * @param BlueSky_Discussion_Renderer $renderer Renderer instance
     */
    public function __construct($api_handler, $account_manager, $renderer)
    {
        $this->api_handler = $api_handler;
        $this->account_manager = $account_manager;
        $this->renderer = $renderer;
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS);

        // Add metabox
        add_action("add_meta_boxes", [$this, "add_discussion_metabox"]);

        // Enqueue admin scripts
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_scripts"]);

        // AJAX handlers
        add_action("wp_ajax_refresh_bluesky_discussion", [
            $this,
            "ajax_refresh_discussion",
        ]);
        add_action("wp_ajax_unlink_bluesky_discussion", [
            $this,
            "ajax_unlink_discussion",
        ]);
    }

    /**
     * Get syndication info for discussion display
     * Handles both old format (direct object) and new format (account-keyed)
     *
     * @param int $post_id WordPress post ID
     * @return array|null Bluesky post info or null if not found
     */
    private function get_syndication_info_for_discussion($post_id)
    {
        $raw = get_post_meta($post_id, '_bluesky_syndication_bs_post_info', true);
        if (empty($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        // Check if this is the OLD format (single blob with 'uri' key directly)
        // vs NEW format (account-keyed structure)
        if (isset($data['uri'])) {
            // Old format — return as-is for backward compatibility
            return $data;
        }

        // New account-keyed format: {account_uuid: {uri, cid, ...}, ...}
        if ($this->account_manager->is_multi_account_enabled()) {
            // Get configured discussion account
            $discussion_account_id = $this->account_manager->get_discussion_account();

            // Try the configured discussion account first
            if ($discussion_account_id && isset($data[$discussion_account_id])) {
                return $data[$discussion_account_id];
            }

            // Fall back to first successful syndication
            foreach ($data as $account_id => $info) {
                if (!empty($info['uri']) && (!isset($info['success']) || $info['success'])) {
                    return $info;
                }
            }
        }

        // Final fallback: return first entry
        return reset($data) ?: null;
    }

    /**
     * Add discussion metabox to post editor
     */
    public function add_discussion_metabox()
    {
        // Only add if post is syndicated
        global $post;

        if (!$post) {
            return;
        }

        $is_syndicated = get_post_meta($post->ID, "_bluesky_syndicated", true);

        if ($is_syndicated) {
            add_meta_box(
                "bluesky_discussion_metabox",
                __("Bluesky Discussion", "social-integration-for-bluesky"),
                [$this, "render_discussion_metabox"],
                "post",
                "normal", // Display below editor
                "default",
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if ("post.php" !== $hook && "post-new.php" !== $hook) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $is_syndicated = get_post_meta($post->ID, "_bluesky_syndicated", true);

        if (!$is_syndicated) {
            return;
        }

        wp_enqueue_style(
            "bluesky-discussion-display",
            BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-discussion-display.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );

        wp_enqueue_script(
            "bluesky-discussion-display",
            BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-discussion-display.js",
            ["jquery"],
            BLUESKY_PLUGIN_VERSION,
            true,
        );

        wp_localize_script(
            "bluesky-discussion-display",
            "blueskyDiscussionData",
            [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce("bluesky_discussion_nonce"),
                "postId" => $post->ID,
                "loadingText" => __(
                    "Loading discussion...",
                    "social-integration-for-bluesky",
                ),
            ],
        );
    }

    /**
     * Render the discussion metabox
     * @param WP_Post $post The post object
     */
    public function render_discussion_metabox($post)
    {
        // Get syndication info using helper method
        $post_info = $this->get_syndication_info_for_discussion($post->ID);

        if (!$post_info) {
            echo "<p>" .
                esc_html__(
                    "No Bluesky post information found.",
                    "social-integration-for-bluesky",
                ) .
                "</p>";
            return;
        }

        if (!isset($post_info["uri"])) {
            echo "<p>" .
                esc_html__(
                    "Invalid Bluesky post data.",
                    "social-integration-for-bluesky",
                ) .
                "</p>";
            return;
        }

        // Get cached discussion or fetch new
        $discussion_html = $this->renderer->get_discussion_html($post_info);
        ?>
        <div class="bluesky-discussion-container" id="bluesky-discussion-container">
            <div class="bluesky-discussion-header">
                <div class="bluesky-discussion-link">
                    <a href="<?php echo esc_url(
                        $post_info["url"],
                    ); ?>" target="_blank" rel="noopener noreferrer">
                        <svg width="20" height="20" viewBox="0 0 166 146" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 5px;">
                            <path d="M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z" fill="#1185FE"/>
                        </svg>
                        <?php esc_html_e(
                            "View on Bluesky",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                </div>
                <div class="bluesky-discussion-actions">
                    <button type="button" class="button button-secondary bluesky-refresh-discussion" id="bluesky-refresh-discussion">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e(
                            "Refresh",
                            "social-integration-for-bluesky",
                        ); ?>
                    </button>
                    <button type="button" class="button button-link-delete bluesky-unlink-discussion" id="bluesky-unlink-discussion">
                        <span class="dashicons dashicons-editor-unlink"></span>
                        <?php esc_html_e(
                            "Unlink",
                            "social-integration-for-bluesky",
                        ); ?>
                    </button>
                </div>
            </div>

            <div class="bluesky-discussion-content" id="bluesky-discussion-content">
                <?php echo $discussion_html; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to refresh discussion
     */
    public function ajax_refresh_discussion()
    {
        check_ajax_referer("bluesky_discussion_nonce", "nonce");

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

        if (!$post_id || !current_user_can("edit_post", $post_id)) {
            wp_send_json_error(__("Invalid permissions", "social-integration-for-bluesky"));
        }

        $post_info = $this->get_syndication_info_for_discussion($post_id);

        if (!$post_info) {
            wp_send_json_error(__("No Bluesky post information found", "social-integration-for-bluesky"));
        }

        // Clear cache
        $cache_key = "bluesky_discussion_" . md5($post_info["uri"]);
        delete_transient($cache_key);

        // Fetch fresh data
        $html = $this->renderer->fetch_and_render_discussion($post_info);

        // Cache for 5 minutes
        set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

        wp_send_json_success(["html" => $html]);
    }

    /**
     * AJAX handler to unlink discussion (remove syndication post meta)
     */
    public function ajax_unlink_discussion()
    {
        check_ajax_referer("bluesky_discussion_nonce", "nonce");

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

        if (!$post_id || !current_user_can("edit_post", $post_id)) {
            wp_send_json_error(__("Invalid permissions", "social-integration-for-bluesky"));
        }

        // Clear discussion cache first
        $post_info = $this->get_syndication_info_for_discussion($post_id);
        if ($post_info && isset($post_info["uri"])) {
            $cache_key = "bluesky_discussion_" . md5($post_info["uri"]);
            delete_transient($cache_key);
        }

        // Remove syndication meta
        delete_post_meta($post_id, "_bluesky_syndication_bs_post_info");
        delete_post_meta($post_id, "_bluesky_syndicated");

        wp_send_json_success();
    }
}
