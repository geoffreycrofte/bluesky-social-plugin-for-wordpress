<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky Discussion Display
 *
 * Handles displaying Bluesky post stats and discussion thread
 * Can be used in metabox or as frontend display
 */
class BlueSky_Discussion_Display
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
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     */
    public function __construct($api_handler)
    {
        $this->api_handler = $api_handler;
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS);
        $this->account_manager = new BlueSky_Account_Manager();

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

        // Frontend hooks
        add_filter("the_content", [$this, "add_discussion_to_content"]);
        add_action("wp_enqueue_scripts", [$this, "enqueue_frontend_scripts"]);
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
        $discussion_html = $this->get_discussion_html($post_info);
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
     * Get discussion HTML (with caching)
     * @param array $post_info Bluesky post information
     * @return string HTML content
     */
    public function get_discussion_html($post_info)
    {
        // Check cache (5 minutes)
        $cache_key = "bluesky_discussion_" . md5($post_info["uri"]);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Fetch fresh data
        $html = $this->fetch_and_render_discussion($post_info);

        // Cache for 5 minutes
        set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

        return $html;
    }

    /**
     * Fetch and render discussion
     * @param array $post_info Bluesky post information
     * @return string HTML content
     */
    private function fetch_and_render_discussion($post_info)
    {
        // Use per-account API handler if multi-account enabled
        $api_handler = $this->api_handler;
        if ($this->account_manager->is_multi_account_enabled()) {
            $discussion_account_id = $this->account_manager->get_discussion_account();
            if ($discussion_account_id) {
                $account = $this->account_manager->get_account($discussion_account_id);
                if ($account) {
                    $api_handler = BlueSky_API_Handler::create_for_account($account);
                }
            }
        }

        // Get post stats
        $post_data = $api_handler->get_post_stats($post_info["uri"]);

        if (!$post_data) {
            return '<div class="bluesky-discussion-error">' .
                "<p>" .
                esc_html__(
                    "Unable to fetch post data from Bluesky.",
                    "social-integration-for-bluesky",
                ) .
                "</p>" .
                "</div>";
        }

        // Get thread with replies
        $thread = $api_handler->get_post_thread($post_info["uri"]);

        $html = "";

        // Render stats
        $html .= $this->render_post_stats($post_data);

        // Render discussion/replies
        if (
            $thread &&
            isset($thread["replies"]) &&
            !empty($thread["replies"])
        ) {
            $html .= $this->render_discussion_thread($thread["replies"]);
        } else {
            $html .= '<div class="bluesky-no-replies">';
            $html .=
                "<p>" .
                esc_html__(
                    "No replies yet. Be the first to comment on Bluesky!",
                    "social-integration-for-bluesky",
                ) .
                "</p>";
            $html .= "</div>";
        }

        return $html;
    }

    /**
     * Render post statistics
     * @param array $post_data Post data from Bluesky
     * @return string HTML
     */
    private function render_post_stats($post_data)
    {
        $stats = [
            "likes" => $post_data["likeCount"] ?? 0,
            "reposts" => $post_data["repostCount"] ?? 0,
            "replies" => $post_data["replyCount"] ?? 0,
            "quotes" => $post_data["quoteCount"] ?? 0,
        ];

        $html = '<div class="bluesky-post-stats">';
        $html .= '<div class="bluesky-stats-grid">';

        // Likes
        $html .= '<div class="bluesky-stat-item">';
        $html .= '<span class="bluesky-stat-icon">❤️</span>';
        $html .=
            '<span class="bluesky-stat-label">' .
            esc_html__("Likes", "social-integration-for-bluesky") .
            "</span>";
        $html .=
            '<span class="bluesky-stat-count">' .
            esc_html(number_format($stats["likes"])) .
            "</span>";
        $html .= "</div>";

        // Reposts
        $html .= '<div class="bluesky-stat-item">';
        $html .= '<span class="bluesky-stat-icon">🔄</span>';
        $html .=
            '<span class="bluesky-stat-label">' .
            esc_html__("Reposts", "social-integration-for-bluesky") .
            "</span>";
        $html .=
            '<span class="bluesky-stat-count">' .
            esc_html(number_format($stats["reposts"])) .
            "</span>";
        $html .= "</div>";

        // Replies
        $html .= '<div class="bluesky-stat-item">';
        $html .= '<span class="bluesky-stat-icon">💬</span>';
        $html .=
            '<span class="bluesky-stat-label">' .
            esc_html__("Replies", "social-integration-for-bluesky") .
            "</span>";
        $html .=
            '<span class="bluesky-stat-count">' .
            esc_html(number_format($stats["replies"])) .
            "</span>";
        $html .= "</div>";

        // Quotes
        $html .= '<div class="bluesky-stat-item">';
        $html .= '<span class="bluesky-stat-icon">💭</span>';
        $html .=
            '<span class="bluesky-stat-label">' .
            esc_html__("Quotes", "social-integration-for-bluesky") .
            "</span>";
        $html .=
            '<span class="bluesky-stat-count">' .
            esc_html(number_format($stats["quotes"])) .
            "</span>";
        $html .= "</div>";

        $html .= "</div>"; // .bluesky-stats-grid
        $html .= "</div>"; // .bluesky-post-stats

        return $html;
    }

    /**
     * Render discussion thread
     * @param array $replies Array of reply posts
     * @return string HTML
     */
    private function render_discussion_thread($replies)
    {
        $html = '<div class="bluesky-discussion-thread">';
        $html .=
            "<h3>" .
            esc_html__(
                "Discussion on Bluesky",
                "social-integration-for-bluesky",
            ) .
            "</h3>";

        foreach ($replies as $reply) {
            $html .= $this->render_reply($reply);
        }

        $html .= "</div>";

        return $html;
    }

    /**
     * Render a single reply
     * @param array $reply Reply data
     * @param int $depth Current depth level
     * @return string HTML
     */
    private function render_reply($reply, $depth = 0)
    {
        if (!isset($reply["post"])) {
            return "";
        }

        $post = $reply["post"];
        $author = $post["author"] ?? [];
        $record = $post["record"] ?? [];

        $html =
            '<div class="bluesky-reply" data-depth="' . esc_attr($depth) . '">';

        // Reply header with author info
        $html .= '<div class="bluesky-reply-header">';

        if (!empty($author["avatar"])) {
            $html .=
                '<img src="' .
                esc_url($author["avatar"]) .
                '" alt="' .
                esc_attr($author["displayName"] ?? $author["handle"]) .
                '" class="bluesky-reply-avatar" />';
        }

        $html .= '<div class="bluesky-reply-author">';
        $html .=
            '<span class="bluesky-reply-author-name">' .
            esc_html(
                $author["displayName"] ?? ($author["handle"] ?? "Unknown"),
            ) .
            "</span>";
        $html .=
            '<span class="bluesky-reply-author-handle">@' .
            esc_html($author["handle"] ?? "unknown") .
            "</span>";
        $html .= "</div>";

        // Time ago
        if (!empty($record["createdAt"])) {
            $time_ago = $this->time_ago($record["createdAt"]);
            $html .=
                '<span class="bluesky-reply-time">' .
                esc_html($time_ago) .
                "</span>";
        }

        $html .= "</div>"; // .bluesky-reply-header

        // Reply content
        $html .= '<div class="bluesky-reply-content">';
        $html .= "<p>" . esc_html($record["text"] ?? "") . "</p>";
        $html .= "</div>";

        // Reply stats
        $like_count = $post["likeCount"] ?? 0;
        $reply_count = $post["replyCount"] ?? 0;
        $repost_count = $post["repostCount"] ?? 0;

        if ($like_count > 0 || $reply_count > 0 || $repost_count > 0) {
            $html .= '<div class="bluesky-reply-stats">';

            if ($like_count > 0) {
                $html .=
                    '<span class="bluesky-reply-stat">❤️ ' .
                    esc_html(number_format($like_count)) .
                    "</span>";
            }

            if ($repost_count > 0) {
                $html .=
                    '<span class="bluesky-reply-stat">🔄 ' .
                    esc_html(number_format($repost_count)) .
                    "</span>";
            }

            if ($reply_count > 0) {
                $html .=
                    '<span class="bluesky-reply-stat">💬 ' .
                    esc_html(number_format($reply_count)) .
                    "</span>";
            }

            $html .= "</div>";
        }

        // Nested replies
        if (
            isset($reply["replies"]) &&
            !empty($reply["replies"]) &&
            $depth < 5
        ) {
            $html .= '<div class="bluesky-reply-children">';
            foreach ($reply["replies"] as $child_reply) {
                $html .= $this->render_reply($child_reply, $depth + 1);
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // .bluesky-reply

        return $html;
    }

    /**
     * Calculate time ago from timestamp
     * @param string $datetime ISO 8601 datetime string
     * @return string Human-readable time ago
     */
    private function time_ago($datetime)
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return sprintf(
                _n(
                    "%s second ago",
                    "%s seconds ago",
                    $diff,
                    "social-integration-for-bluesky",
                ),
                $diff,
            );
        }

        $diff = floor($diff / 60);
        if ($diff < 60) {
            return sprintf(
                _n(
                    "%s minute ago",
                    "%s minutes ago",
                    $diff,
                    "social-integration-for-bluesky",
                ),
                $diff,
            );
        }

        $diff = floor($diff / 60);
        if ($diff < 24) {
            return sprintf(
                _n(
                    "%s hour ago",
                    "%s hours ago",
                    $diff,
                    "social-integration-for-bluesky",
                ),
                $diff,
            );
        }

        $diff = floor($diff / 24);
        if ($diff < 7) {
            return sprintf(
                _n(
                    "%s day ago",
                    "%s days ago",
                    $diff,
                    "social-integration-for-bluesky",
                ),
                $diff,
            );
        }

        $diff = floor($diff / 7);
        if ($diff < 4) {
            return sprintf(
                _n(
                    "%s week ago",
                    "%s weeks ago",
                    $diff,
                    "social-integration-for-bluesky",
                ),
                $diff,
            );
        }

        $diff = floor($diff / 4);
        return sprintf(
            _n(
                "%s month ago",
                "%s months ago",
                $diff,
                "social-integration-for-bluesky",
            ),
            $diff,
        );
    }

    /**
     * AJAX handler to refresh discussion
     */
    public function ajax_refresh_discussion()
    {
        check_ajax_referer("bluesky_discussion_nonce", "nonce");

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

        if (!$post_id || !current_user_can("edit_post", $post_id)) {
            wp_send_json_error("Invalid permissions");
        }

        $post_info = $this->get_syndication_info_for_discussion($post_id);

        if (!$post_info) {
            wp_send_json_error("No Bluesky post information found");
        }

        // Clear cache
        $cache_key = "bluesky_discussion_" . md5($post_info["uri"]);
        delete_transient($cache_key);

        // Fetch fresh data
        $html = $this->fetch_and_render_discussion($post_info);

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
            wp_send_json_error("Invalid permissions");
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

    /**
     * Render discussion for frontend display (reusable)
     * @param int $post_id WordPress post ID
     * @param array $args Display arguments
     * @return string HTML content
     */
    public function render_frontend_discussion($post_id, $args = [])
    {
        $defaults = [
            "show_stats" => true,
            "show_replies" => true,
            "max_replies" => 10,
            "class" => "bluesky-frontend-discussion",
        ];

        $args = wp_parse_args($args, $defaults);

        $post_info = $this->get_syndication_info_for_discussion($post_id);

        if (!$post_info) {
            return "";
        }

        if (!isset($post_info["uri"])) {
            return "";
        }

        $html = '<div class="' . esc_attr($args["class"]) . '">';
        $html .= $this->get_discussion_html($post_info);
        $html .= "</div>";

        return $html;
    }

    /**
     * Add discussion section to post content
     * @param string $content Post content
     * @return string Modified content
     */
    public function add_discussion_to_content($content)
    {
        // Only on single posts
        if (!is_singular("post") || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // Check if discussions are enabled
        $discussions_enabled = $this->options["discussions"]["enable"] ?? 0;
        if (!$discussions_enabled) {
            return $content;
        }

        global $post;
        if (!$post) {
            return $content;
        }

        // Check if post is syndicated
        $is_syndicated = get_post_meta($post->ID, "_bluesky_syndicated", true);
        if (!$is_syndicated) {
            return $content;
        }

        // Get post info using helper method
        $post_info = $this->get_syndication_info_for_discussion($post->ID);

        if (!$post_info || !isset($post_info["uri"]) || !isset($post_info["url"])) {
            return $content;
        }

        // Build discussion section
        $discussion_html = $this->build_frontend_discussion($post_info);

        // Add discussion section after content
        $content .= $discussion_html;

        return $content;
    }

    /**
     * Build frontend discussion HTML
     * @param array $post_info Bluesky post information
     * @return string HTML content
     */
    private function build_frontend_discussion($post_info)
    {
        $show_nested = $this->options["discussions"]["show_nested"] ?? 1;
        $nested_collapsed =
            $this->options["discussions"]["nested_collapsed"] ?? 1;
        $show_stats = $this->options["discussions"]["show_stats"] ?? 1;
        $show_reply_link =
            $this->options["discussions"]["show_reply_link"] ?? 1;
        $show_media = $this->options["discussions"]["show_media"] ?? 1;

        // Get current user's DID to check for muted/blocked users
        $current_user_did = $this->options["handle"] ?? "";

        // Check if comments are open
        $comments_open = comments_open();

        // Get theme setting
        $theme = $this->options["theme"] ?? "system";
        $theme_attr =
            $theme !== "system" ? ' data-theme="' . esc_attr($theme) . '"' : "";

        $html =
            '<section class="bluesky-discussion-section"' . $theme_attr . ">";

        // Construct proper Bluesky URL from URI (DID-based, future-proof)
        $uri_parts = explode("/", $post_info["uri"]);
        $did = $uri_parts[2] ?? "";
        $rkey = $uri_parts[4] ?? "";
        $bluesky_url = "";
        if ($did && $rkey) {
            $bluesky_url =
                "https://bsky.app/profile/" . $did . "/post/" . $rkey;
        }

        // If comments are open, note that tabs will be managed by theme/comments
        // Otherwise show standalone
        if (!$comments_open) {
            $html .= '<div class="bluesky-discussion-standalone">';
            $html .= '<div class="bluesky-discussion-title-row">';
            $html .=
                "<h2>" .
                esc_html__(
                    "Bluesky Discussion",
                    "social-integration-for-bluesky",
                ) .
                "</h2>";
            if ($bluesky_url) {
                $html .=
                    '<a href="' .
                    esc_url($bluesky_url) .
                    '" target="_blank" rel="noopener noreferrer" class="bluesky-discussion-link">' .
                    esc_html__(
                        "View on Bluesky",
                        "social-integration-for-bluesky",
                    ) .
                    "</a>";
            }
            $html .= "</div>";
        } else {
            $html .= '<div class="bluesky-discussion-with-comments">';
            $html .= '<div class="bluesky-discussion-title-row">';
            $html .=
                "<h2>" .
                esc_html__(
                    "Bluesky Discussion",
                    "social-integration-for-bluesky",
                ) .
                "</h2>";
            if ($bluesky_url) {
                $html .=
                    '<a href="' .
                    esc_url($bluesky_url) .
                    '" target="_blank" rel="noopener noreferrer" class="bluesky-discussion-link">' .
                    esc_html__(
                        "View on Bluesky",
                        "social-integration-for-bluesky",
                    ) .
                    "</a>";
            }
            $html .= "</div>";
        }

        // Get discussion data
        $discussion_html = $this->get_frontend_discussion_html(
            $post_info,
            $show_nested,
            $nested_collapsed,
            $show_stats,
            $show_reply_link,
            $show_media,
            $current_user_did,
        );
        $html .= $discussion_html;

        $html .= "</div>"; // Close standalone or with-comments div
        $html .= "</section>"; // Close bluesky-discussion-section

        return $html;
    }

    /**
     * Get frontend discussion HTML with options
     * @param array $post_info Post information
     * @param bool $show_nested Show nested replies
     * @param bool $nested_collapsed Collapse nested replies
     * @param bool $show_stats Show stats
     * @param bool $show_reply_link Show reply link
     * @param bool $show_media Show media attachments
     * @param string $current_user_did Current user's DID for filtering
     * @return string HTML
     */
    private function get_frontend_discussion_html(
        $post_info,
        $show_nested,
        $nested_collapsed,
        $show_stats,
        $show_reply_link,
        $show_media,
        $current_user_did,
    ) {
        // Check cache
        $cache_key = "bluesky_frontend_discussion_" . md5($post_info["uri"]);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get thread with replies
        $thread = $this->api_handler->get_post_thread($post_info["uri"]);

        $html = "";

        // Render discussion/replies
        if (
            $thread &&
            isset($thread["replies"]) &&
            !empty($thread["replies"])
        ) {
            $html .= $this->render_frontend_thread(
                $thread["replies"],
                $show_nested,
                $nested_collapsed,
                $show_stats,
                $show_reply_link,
                $show_media,
                $current_user_did,
            );
        } else {
            $html .= '<div class="bluesky-no-replies">';
            $html .=
                "<p>" .
                esc_html__(
                    "No replies yet. Be the first to comment on Bluesky!",
                    "social-integration-for-bluesky",
                ) .
                "</p>";
            $html .= "</div>";
        }

        // Cache for 5 minutes
        set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

        return $html;
    }

    /**
     * Render frontend discussion thread
     * @param array $replies Array of reply posts
     * @param bool $show_nested Show nested replies
     * @param bool $nested_collapsed Collapse nested replies
     * @param bool $show_stats Show stats
     * @param bool $show_reply_link Show reply link
     * @param bool $show_media Show media attachments
     * @param string $current_user_did Current user's DID for filtering
     * @return string HTML
     */
    private function render_frontend_thread(
        $replies,
        $show_nested,
        $nested_collapsed,
        $show_stats,
        $show_reply_link,
        $show_media,
        $current_user_did,
    ) {
        $html = '<div class="bluesky-discussion-thread">';
        $html .= '<ul class="bluesky-discussion-list">';

        foreach ($replies as $reply) {
            $html .= $this->render_frontend_reply(
                $reply,
                0,
                $show_nested,
                $nested_collapsed,
                $show_stats,
                $show_reply_link,
                $show_media,
                $current_user_did,
            );
        }

        $html .= "</ul>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render a single reply for frontend
     * @param array $reply Reply data
     * @param int $depth Current depth level
     * @param bool $show_nested Show nested replies
     * @param bool $nested_collapsed Collapse nested replies
     * @param bool $show_stats Show stats
     * @param bool $show_reply_link Show reply link
     * @param bool $show_media Show media attachments
     * @param string $current_user_did Current user's DID for filtering
     * @return string HTML
     */
    private function render_frontend_reply(
        $reply,
        $depth,
        $show_nested,
        $nested_collapsed,
        $show_stats,
        $show_reply_link,
        $show_media,
        $current_user_did,
    ) {
        if (!isset($reply["post"])) {
            return "";
        }

        $post = $reply["post"];
        $author = $post["author"] ?? [];
        $record = $post["record"] ?? [];

        // Check if user is muted or blocked by viewer
        $viewer = $author["viewer"] ?? [];
        if (
            !empty($current_user_did) &&
            (($viewer["muted"] ?? false) || ($viewer["blocking"] ?? false))
        ) {
            return ""; // Skip muted/blocked users
        }

        // Check if this reply has children
        $has_children =
            isset($reply["replies"]) &&
            !empty($reply["replies"]) &&
            $show_nested &&
            $depth < 5;

        // Build Bluesky URL from URI using DID
        $uri_parts = explode("/", $post["uri"]);
        $did = $uri_parts[2] ?? "";
        $rkey = $uri_parts[4] ?? "";
        $author_url = "";
        if ($did && $rkey) {
            $author_url = "https://bsky.app/profile/" . $did . "/post/" . $rkey;
        }

        $html =
            '<li class="bluesky-reply" data-depth="' .
            esc_attr($depth) .
            '" itemscope itemtype="https://schema.org/Comment">';

        // Reply header with author info
        $html .= '<header class="bluesky-reply-header">';

        if (!empty($author["avatar"])) {
            $html .=
                '<img src="' .
                esc_url($author["avatar"]) .
                '" alt="' .
                esc_attr($author["displayName"] ?? $author["handle"]) .
                '" class="bluesky-reply-avatar" itemprop="image" loading="lazy" />';
        }

        $html .=
            '<div class="bluesky-reply-author" itemprop="author" itemscope itemtype="https://schema.org/Person">';
        $html .=
            '<span class="bluesky-reply-author-name" itemprop="name">' .
            esc_html(
                $author["displayName"] ?? ($author["handle"] ?? "Unknown"),
            ) .
            "</span>";
        $html .=
            '<span class="bluesky-reply-author-handle">@' .
            esc_html($author["handle"] ?? "unknown") .
            "</span>";
        $html .= "</div>";

        // Time ago
        if (!empty($record["createdAt"])) {
            $time_ago = $this->time_ago($record["createdAt"]);
            $html .=
                '<time class="bluesky-reply-time" datetime="' .
                esc_attr($record["createdAt"]) .
                '" itemprop="dateCreated">' .
                esc_html($time_ago) .
                "</time>";
        }

        $html .= "</header>"; // .bluesky-reply-header

        // Reply content
        $html .= '<div class="bluesky-reply-content" itemprop="text">';
        $html .= "<p>" . esc_html($record["text"] ?? "") . "</p>";
        $html .= "</div>";

        // Media attachments (images, videos, etc.)
        if ($show_media && isset($post["embed"])) {
            $html .= $this->render_reply_media($post["embed"]);
        }

        // Reply stats and actions
        if ($show_stats || $show_reply_link) {
            $like_count = $post["likeCount"] ?? 0;
            $reply_count = $post["replyCount"] ?? 0;
            $repost_count = $post["repostCount"] ?? 0;

            $html .= '<div class="bluesky-reply-footer">';

            if (
                $show_stats &&
                ($like_count > 0 || $reply_count > 0 || $repost_count > 0)
            ) {
                $html .= '<div class="bluesky-reply-stats">';

                if ($like_count > 0) {
                    $html .=
                        '<span class="bluesky-reply-stat">❤️ ' .
                        esc_html(number_format($like_count)) .
                        "</span>";
                }

                if ($repost_count > 0) {
                    $html .=
                        '<span class="bluesky-reply-stat">🔄 ' .
                        esc_html(number_format($repost_count)) .
                        "</span>";
                }

                if ($reply_count > 0) {
                    $html .=
                        '<span class="bluesky-reply-stat">💬 ' .
                        esc_html(number_format($reply_count)) .
                        "</span>";
                }

                $html .= "</div>";
            }

            if ($show_reply_link && $author_url) {
                $html .=
                    '<a href="' .
                    esc_url($author_url) .
                    '" target="_blank" rel="noopener noreferrer" class="bluesky-reply-link">' .
                    esc_html__(
                        "Reply on Bluesky",
                        "social-integration-for-bluesky",
                    ) .
                    "</a>";
            }

            $html .= "</div>"; // .bluesky-reply-footer
        }

        // Nested replies with collapse functionality
        if ($has_children) {
            $collapsed_class = $nested_collapsed ? " collapsed" : "";
            $html .=
                '<div class="bluesky-reply-children' . $collapsed_class . '">';

            if ($nested_collapsed) {
                $reply_count = count($reply["replies"]);
                $html .=
                    '<button class="bluesky-toggle-replies" data-collapsed-text="' .
                    esc_attr(
                        sprintf(
                            _n(
                                "Show %d reply",
                                "Show %d replies",
                                $reply_count,
                                "social-integration-for-bluesky",
                            ),
                            $reply_count,
                        ),
                    ) .
                    '" data-expanded-text="' .
                    esc_attr(
                        __("Hide replies", "social-integration-for-bluesky"),
                    ) .
                    '">' .
                    esc_html(
                        sprintf(
                            _n(
                                "Show %d reply",
                                "Show %d replies",
                                $reply_count,
                                "social-integration-for-bluesky",
                            ),
                            $reply_count,
                        ),
                    ) .
                    "</button>";
                $html .= '<ul class="bluesky-nested-replies">';
            } else {
                $html .= '<ul class="bluesky-nested-replies">';
            }

            foreach ($reply["replies"] as $child_reply) {
                $html .= $this->render_frontend_reply(
                    $child_reply,
                    $depth + 1,
                    $show_nested,
                    $nested_collapsed,
                    $show_stats,
                    $show_reply_link,
                    $show_media,
                    $current_user_did,
                );
            }

            $html .= "</ul>"; // .bluesky-nested-replies
            $html .= "</div>"; // .bluesky-reply-children
        }

        $html .= "</li>"; // .bluesky-reply

        return $html;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts()
    {
        if (!is_singular("post")) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $discussions_enabled = $this->options["discussions"]["enable"] ?? 0;
        if (!$discussions_enabled) {
            return;
        }

        $is_syndicated = get_post_meta($post->ID, "_bluesky_syndicated", true);
        if (!$is_syndicated) {
            return;
        }

        wp_enqueue_style(
            "bluesky-discussion-frontend",
            BLUESKY_PLUGIN_FOLDER .
                "assets/css/bluesky-discussion-frontend.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );

        wp_enqueue_script(
            "bluesky-discussion-frontend",
            BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-discussion-frontend.js",
            [],
            BLUESKY_PLUGIN_VERSION,
            true,
        );
    }

    /**
     * Clear all discussion caches
     * Called when plugin settings are updated
     */
    public function clear_discussion_caches()
    {
        global $wpdb;

        // Delete all discussion transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_bluesky_discussion_%'
             OR option_name LIKE '_transient_timeout_bluesky_discussion_%'
             OR option_name LIKE '_transient_bluesky_frontend_discussion_%'
             OR option_name LIKE '_transient_timeout_bluesky_frontend_discussion_%'",
        );

        // Clear object cache if available
        if (function_exists("wp_cache_flush")) {
            wp_cache_flush();
        }
    }

    /**
     * Render media attachments for a reply
     * @param array $embed Embed data from post
     * @return string HTML
     */
    private function render_reply_media($embed)
    {
        $html = "";

        // Images
        if (isset($embed["images"]) && is_array($embed["images"])) {
            $html .= '<div class="bluesky-reply-media bluesky-reply-images">';

            $image_count = count($embed["images"]);
            $grid_class =
                $image_count === 1
                    ? "single"
                    : ($image_count === 2
                        ? "double"
                        : "grid");

            $html .= '<div class="bluesky-images-grid ' . $grid_class . '">';

            foreach ($embed["images"] as $image) {
                $image_url = $image["fullsize"] ?? ($image["thumb"] ?? "");
                $alt_text = $image["alt"] ?? "";

                if ($image_url) {
                    // Check if it's a GIF/sticker from known services
                    $is_gif =
                        strpos($image_url, "media.tenor.com") !== false ||
                        strpos($image_url, "giphy.com") !== false ||
                        preg_match('/\.(gif|webp)$/i', $image_url);

                    $html .=
                        '<div class="bluesky-image-wrapper' .
                        ($is_gif ? " is-animated" : "") .
                        '">';
                    $html .=
                        '<img src="' .
                        esc_url($image_url) .
                        '" alt="' .
                        esc_attr($alt_text) .
                        '" class="bluesky-reply-image" loading="lazy" decoding="async" />';
                    $html .= "</div>";
                }
            }

            $html .= "</div>"; // .bluesky-images-grid
            $html .= "</div>"; // .bluesky-reply-media
        }

        // Video
        if (
            isset($embed["video"]) ||
            (isset($embed['$type']) &&
                strpos($embed['$type'], "app.bsky.embed.video") !== false)
        ) {
            $playlist_url =
                $embed["playlist"] ?? ($embed["video"]["playlist"] ?? "");
            $thumbnail_url =
                $embed["thumbnail"] ?? ($embed["video"]["thumbnail"] ?? "");
            $alt_text = $embed["alt"] ?? ($embed["video"]["alt"] ?? "");

            if ($playlist_url) {
                $html .=
                    '<div class="bluesky-reply-media bluesky-reply-video">';
                $html .=
                    '<video controls preload="metadata" class="bluesky-video"' .
                    ($thumbnail_url
                        ? ' poster="' . esc_attr($thumbnail_url) . '"'
                        : "") .
                    ">";
                $html .=
                    '<source src="' .
                    esc_url($playlist_url) .
                    '" type="application/x-mpegURL">';
                $html .= esc_html(
                    __(
                        "Your browser does not support the video tag.",
                        "social-integration-for-bluesky",
                    ),
                );
                $html .= "</video>";
                $html .= "</div>";
            }
        }

        // External link preview
        if (isset($embed["external"])) {
            $external = $embed["external"];
            $html .= '<div class="bluesky-reply-media bluesky-reply-external">';
            $html .=
                '<a href="' .
                esc_url($external["uri"] ?? "#") .
                '" target="_blank" rel="noopener noreferrer" class="bluesky-external-link">';

            if (!empty($external["thumb"])) {
                $html .=
                    '<div class="bluesky-external-thumb"><img src="' .
                    esc_url($external["thumb"]) .
                    '" alt="" loading="lazy" decoding="async" /></div>';
            }

            $html .= '<div class="bluesky-external-content">';
            if (!empty($external["title"])) {
                $html .=
                    '<div class="bluesky-external-title">' .
                    esc_html($external["title"]) .
                    "</div>";
            }
            if (!empty($external["description"])) {
                $html .=
                    '<div class="bluesky-external-description">' .
                    esc_html($external["description"]) .
                    "</div>";
            }
            if (!empty($external["uri"])) {
                $html .=
                    '<div class="bluesky-external-url">' .
                    esc_html(parse_url($external["uri"], PHP_URL_HOST)) .
                    "</div>";
            }
            $html .= "</div>"; // .bluesky-external-content

            $html .= "</a>";
            $html .= "</div>"; // .bluesky-reply-external
        }

        return $html;
    }
}
