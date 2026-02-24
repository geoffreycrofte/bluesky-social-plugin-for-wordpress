<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky Discussion Frontend
 *
 * Handles frontend content injection and frontend-specific rendering.
 */
class BlueSky_Discussion_Frontend
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
        $html .= $this->renderer->get_discussion_html($post_info);
        $html .= "</div>";

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
            $time_ago = $this->renderer->time_ago($record["createdAt"]);
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
            $html .= $this->renderer->render_reply_media($post["embed"]);
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
}
