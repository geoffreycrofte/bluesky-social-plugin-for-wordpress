<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky Discussion Renderer
 *
 * Pure HTML rendering for Bluesky discussions and replies.
 * No WordPress hooks or side effects - just takes data in, returns HTML out.
 */
class BlueSky_Discussion_Renderer
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
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     */
    public function __construct($api_handler, $account_manager)
    {
        $this->api_handler = $api_handler;
        $this->account_manager = $account_manager;
    }

    /**
     * Render post statistics
     * @param array $post_data Post data from Bluesky
     * @return string HTML
     */
    public function render_post_stats($post_data)
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
    public function render_discussion_thread($replies)
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
    public function render_reply($reply, $depth = 0)
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
     * Render media attachments for a reply
     * @param array $embed Embed data from post
     * @return string HTML
     */
    public function render_reply_media($embed)
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
                    '" alt="' . esc_attr($external["title"] ?? "") . '" loading="lazy" decoding="async" /></div>';
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

    /**
     * Calculate time ago from timestamp
     * @param string $datetime ISO 8601 datetime string
     * @return string Human-readable time ago
     */
    public function time_ago($datetime)
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
     * Fetch and render discussion
     * @param array $post_info Bluesky post information
     * @return string HTML content
     */
    public function fetch_and_render_discussion($post_info)
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
}
