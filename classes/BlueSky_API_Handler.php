<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_API_Handler
{
    /**
     * Base URL for BlueSky API
     * @var string
     */
    private $bluesky_api_url = "https://bsky.social/xrpc/";

    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Authenticated user's DID (Decentralized Identifier)
     * @var string|null
     */
    private $did = null;

    /**
     * Access token for authenticated requests
     * @var string|null
     */
    private $access_token = null;

    /**
     * Last authentication error details
     * @var array|null
     */
    private $last_auth_error = null;

    /**
     * Account ID for multi-account transient scoping
     * @var string|null
     */
    private $account_id = null;

    /**
     * Constructor
     * @param array $options Plugin settings
     */
    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Factory method to create an API handler for a specific account
     *
     * @param array $account Account array from BlueSky_Account_Manager
     * @return BlueSky_API_Handler Configured API handler instance
     */
    public static function create_for_account($account)
    {
        // Build minimal options array that the API handler needs
        $options = [
            'handle' => $account['handle'],
            'app_password' => $account['app_password'] // Already encrypted in storage
        ];

        $instance = new self($options);
        // Set account_id so transient keys are scoped per-account
        $instance->account_id = $account['id'] ?? null;
        return $instance;
    }

    /**
     * Authenticate the user by creating and storing an access and refresh jwt.
     *
     * @param mixed $force Wether or not to force the refresh token creation.
     * @return bool
     */
    public function authenticate($force = false)
    {
        $this->last_auth_error = null;

        // Check if credentials are set
        if (
            !isset($this->options["handle"]) ||
            !isset($this->options["app_password"])
        ) {
            $this->last_auth_error = [
                "code" => "MissingCredentials",
                "message" => "Handle or app password is not configured.",
                "status" => 0,
            ];
            return false;
        }

        $helpers = new BlueSky_Helpers();
        $access_tkey = $helpers->get_access_token_transient_key($this->account_id);
        $refresh_tkey = $helpers->get_refresh_token_transient_key($this->account_id);
        $did_tkey = $helpers->get_did_transient_key($this->account_id);

        // Retrieve saved tokens from transients
        $access_token = get_transient($access_tkey);
        $refresh_token = get_transient($refresh_tkey);
        $did = get_transient($did_tkey);

        // If an access token exists and hasn't expired, use it
        if ($access_token && !$force) {
            $this->access_token = $access_token;
            $this->did = $did;
            return true;
        }

        // Check if refresh token exists to renew the access token
        if ($refresh_token && !$force) {
            $response = wp_remote_post(
                $this->bluesky_api_url . "com.atproto.server.refreshSession",
                [
                    "timeout" => 15,
                    "body" => wp_json_encode([
                        "refreshJwt" => $refresh_token,
                    ]),
                    "headers" => [
                        "Authorization" => "Bearer " . $refresh_token,
                        "Content-Type" => "application/json",
                    ],
                ],
            );

            if (is_wp_error($response)) {
                $this->last_auth_error = [
                    "code" => "NetworkError",
                    "message" => $response->get_error_message(),
                    "status" => 0,
                ];
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (
                isset($body["accessJwt"]) &&
                isset($body["refreshJwt"]) &&
                isset($body["did"])
            ) {
                // Save new tokens
                set_transient(
                    $access_tkey,
                    $body["accessJwt"],
                    HOUR_IN_SECONDS,
                );
                set_transient(
                    $refresh_tkey,
                    $body["refreshJwt"],
                    WEEK_IN_SECONDS,
                );
                set_transient($did_tkey, $body["did"]);

                $this->access_token = $body["accessJwt"];
                $this->did = $body["did"];
                return true;
            }

            // Force re-authentication if refresh token is invalid
            delete_transient($refresh_tkey);
            delete_transient($access_tkey);
            delete_transient($did_tkey);

            return $this->authenticate($force);
        }

        // No valid tokens,
        // or forced authentication,
        // then proceed with full authentication
        $password = $this->options["app_password"];
        $password = $helpers->bluesky_decrypt($password);

        $response = wp_remote_post(
            $this->bluesky_api_url . "com.atproto.server.createSession",
            [
                "timeout" => 15,
                "body" => wp_json_encode([
                    "identifier" => $this->options["handle"],
                    "password" => $password,
                ]),
                "headers" => [
                    "Content-Type" => "application/json",
                ],
            ],
        );

        if (is_wp_error($response)) {
            $this->last_auth_error = [
                "code" => "NetworkError",
                "message" => $response->get_error_message(),
                "status" => 0,
            ];
            return false;
        }

        $http_status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (
            isset($body["did"]) &&
            isset($body["accessJwt"]) &&
            isset($body["refreshJwt"])
        ) {
            // Save tokens in transients
            set_transient($access_tkey, $body["accessJwt"], HOUR_IN_SECONDS);
            set_transient($refresh_tkey, $body["refreshJwt"], WEEK_IN_SECONDS);
            set_transient($did_tkey, $body["did"]);

            $this->did = $body["did"];
            $this->access_token = $body["accessJwt"];
            return true;
        }

        // Capture error details from the API response
        $this->last_auth_error = [
            "code" => $body["error"] ?? "UnknownError",
            "message" => $body["message"] ?? "",
            "status" => $http_status,
        ];

        // Add rate limit info if available
        $ratelimit_remaining = wp_remote_retrieve_header(
            $response,
            "ratelimit-remaining",
        );
        if ($ratelimit_remaining !== "") {
            $this->last_auth_error["ratelimit_remaining"] =
                $ratelimit_remaining;
            $this->last_auth_error["ratelimit_reset"] =
                wp_remote_retrieve_header($response, "ratelimit-reset");
        }

        return false;
    }

    /**
     * Get the last authentication error details
     *
     * @return array|null Error array with 'code', 'message', 'status' keys, or null if no error
     */
    public function get_last_auth_error()
    {
        return $this->last_auth_error;
    }

    /**
     * Destroy the refresh token and its recordings in database to logout the user.
     *
     * @return bool
     */
    public function logout()
    {
        $helpers = new BlueSky_Helpers();
        try {
            // clean the transient of the jwt
            $this->cleanup_session_data($helpers);
            // clean cached content (profile, posts) so stale data isn't served
            $this->cleanup_content_transients($helpers);
            // clean the handle and app_password options
            $this->cleanup_login_options();
            // done.
            return true;
        } catch (Exception $e) {
            error_log("Bluesky Exception during logout: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean the session information
     *
     * @param mixed $helpers
     * @param mixed $access_tkey
     * @return void
     */
    private function cleanup_session_data($helpers)
    {
        delete_transient($helpers->get_access_token_transient_key());
        delete_transient($helpers->get_refresh_token_transient_key());
        delete_transient($helpers->get_did_transient_key());

        $this->access_token = null;
        $this->did = null;
    }

    /**
     * Clean the login and password options
     *
     * @return void
     */
    private function cleanup_login_options()
    {
        $options = $this->options;
        unset($options["handle"]);
        unset($options["app_password"]);

        update_option(BLUESKY_PLUGIN_OPTIONS, $options);
        $this->options = $options; // shoudn't be necessary, but just in case.
    }

    /**
     * Clear cached content transients (profile and all posts variants)
     *
     * @param BlueSky_Helpers $helpers
     * @return void
     */
    private function cleanup_content_transients($helpers)
    {
        delete_transient($helpers->get_profile_transient_key());

        global $wpdb;
        $prefix = BLUESKY_PLUGIN_TRANSIENT . '-posts-';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like($prefix) . '%',
                '_transient_timeout_' . $wpdb->esc_like($prefix) . '%',
            ),
        );
    }

    /**
     * Fetch posts from BlueSky feed
     * @param int $limit Number of posts to fetch (default 10)
     * @return array|false Processed posts or false on failure
     */
    public function fetch_bluesky_posts(
        $limit = 10,
        $no_replies = true,
        $no_reposts = true,
    ) {
        $helpers = new BlueSky_Helpers();
        $no_replies = $no_replies ?? ($this->options["no_replies"] ?? true);
        $no_reposts = $no_reposts ?? ($this->options["no_reposts"] ?? true);
        $cache_key = $helpers->get_posts_transient_key(
            $this->account_id,
            $limit,
            $no_replies,
            $no_reposts,
        );
        $cache_duration =
            $this->options["cache_duration"]["total_seconds"] ?? 3600; // Default 1 hour

        // Skip cache if duration is 0
        if ($cache_duration > 0) {
            $cached_posts = get_transient($cache_key);
            if ($cached_posts !== false) {
                return $cached_posts;
            }
        }

        // Ensure authentication
        if (!$this->authenticate()) {
            return false;
        }

        // Sanitize limit
        $limit = max(1, min(10, intval($limit)));

        $response = wp_remote_get(
            $this->bluesky_api_url . "app.bsky.feed.getAuthorFeed",
            [
                "timeout" => 15,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                ],
                "body" => [
                    "actor" => $this->did,
                    "limit" => $no_replies || $no_reposts ? 100 : $limit, // Fetch more to account for replies
                ],
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $raw_posts = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($raw_posts["feed"])) {
            return false;
        }

        $filteredFeed = array_filter($raw_posts["feed"], function ($entry) use (
            $no_replies,
            $no_reposts,
            $helpers,
        ) {
            if (!isset($entry["post"])) {
                return false;
            }

            $post = $entry["post"];

            // Filter out replies (check if 'reply' key exists in record)
            if (isset($post["record"]["reply"]) && $no_replies) {
                return false;
            }

            // Filter out reposts (check if 'reason' key exists and is a repost)
            if (
                isset($entry["reason"]) &&
                $entry["reason"]['$type'] ===
                    "app.bsky.feed.defs#reasonRepost" &&
                $no_reposts
            ) {
                return false;
            }

            return true; // Keep original posts
        });

        // Reset array keys
        $posts = array_values($filteredFeed);

        if ($no_replies) {
            // Limit to the requested number of posts
            $posts = array_slice($posts, 0, $limit);
        }

        // Process and normalize posts
        $processed_posts = $this->process_posts($posts ?? []);

        // Sort by most recent first
        usort($processed_posts, function ($a, $b) {
            return strtotime($b["created_at"]) - strtotime($a["created_at"]);
        });

        // Cache the posts if caching is enabled
        if ($cache_duration > 0) {
            set_transient($cache_key, $processed_posts, $cache_duration);
        }

        return $processed_posts;
    }

    /**
     * Fetch BlueSky profile
     * @return array|false Profile data or false on failure
     */
    public function get_bluesky_profile()
    {
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers->get_profile_transient_key($this->account_id);
        $cache_duration =
            $this->options["cache_duration"]["total_seconds"] ?? 3600; // Default 1 hour

        // Skip cache if duration is 0
        if ($cache_duration > 0) {
            $cached_profile = get_transient($cache_key);
            if ($cached_profile !== false) {
                return $cached_profile;
            }
        }

        if (!$this->authenticate()) {
            return false;
        }

        $response = wp_remote_get(
            $this->bluesky_api_url . "app.bsky.actor.getProfile",
            [
                "timeout" => 15,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                ],
                "body" => [
                    "actor" => $this->did,
                ],
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        // Cache the profile if caching is enabled
        if ($cache_duration > 0) {
            set_transient($cache_key, $decoded, $cache_duration);
        }

        return $decoded;
    }

    /**
     * Upload an image to Bluesky as a blob
     * @param string $image_url URL or path to the image
     * @return array|false Blob data on success, false on failure
     */
    private function upload_image_blob($image_url)
    {
        if (!$this->authenticate()) {
            return false;
        }

        // Get image data
        $image_data = null;
        $mime_type = null;

        // Check if it's a local file path or URL
        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
            // It's a URL, fetch it
            $response = wp_remote_get($image_url, [
                "timeout" => 15,
            ]);
            if (is_wp_error($response)) {
                return false;
            }
            $image_data = wp_remote_retrieve_body($response);
            $mime_type = wp_remote_retrieve_header($response, "content-type");
        } else {
            // It's a local file path
            if (!file_exists($image_url)) {
                return false;
            }
            $image_data = file_get_contents($image_url);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $image_url);
            finfo_close($finfo);
        }

        if (empty($image_data)) {
            return false;
        }

        // Ensure mime type is set
        if (empty($mime_type)) {
            $mime_type = "image/jpeg"; // Default fallback
        }

        // Upload blob to Bluesky
        $response = wp_remote_post(
            $this->bluesky_api_url . "com.atproto.repo.uploadBlob",
            [
                "timeout" => 30,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                    "Content-Type" => $mime_type,
                ],
                "body" => $image_data,
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body["blob"])) {
            return false;
        }

        return $body["blob"];
    }

    /**
     * Syndicate a post to BlueSky with rich media
     * @param string $title Post title
     * @param string $permalink Post URL
     * @param string $excerpt Post excerpt (optional)
     * @param string $image_url Post thumbnail or first image URL (optional)
     * @return array|false Post information array on success, false on failure
     */
    public function syndicate_post_to_bluesky(
        $title,
        $permalink,
        $excerpt = "",
        $image_url = "",
    ) {
        if (!$this->authenticate()) {
            return false;
        }

        // Bluesky character limit
        $char_limit = 300;

        // Build the post text respecting character limits
        // Format: Title + Excerpt (if space allows)
        $text = "";
        $title_trimmed = wp_trim_words($title, 20, "...");

        // Calculate space for URL (will be in card, but we'll add it to text too)
        $url_length = strlen($permalink);

        // Build text with title
        $text = $title_trimmed;

        // Add excerpt if there's space and it's provided
        if (!empty($excerpt)) {
            $excerpt_clean = wp_strip_all_tags($excerpt);
            $excerpt_clean = preg_replace("/\s+/", " ", $excerpt_clean); // Normalize whitespace

            // Calculate remaining space for excerpt
            // Account for: current text + newlines + URL + some buffer
            $space_for_excerpt = $char_limit - strlen($text) - $url_length - 10; // 10 for spacing and buffer

            if ($space_for_excerpt > 50) {
                // Only add excerpt if meaningful space available
                $excerpt_trimmed = mb_substr(
                    $excerpt_clean,
                    0,
                    $space_for_excerpt,
                );
                // Trim to last complete word
                $last_space = mb_strrpos($excerpt_trimmed, " ");
                if ($last_space !== false && $last_space > 30) {
                    $excerpt_trimmed = mb_substr(
                        $excerpt_trimmed,
                        0,
                        $last_space,
                    );
                }
                $excerpt_trimmed = trim($excerpt_trimmed) . "...";
                $text .= "\n\n" . $excerpt_trimmed;
            }
        }

        // Ensure text doesn't exceed limit
        if (strlen($text) > $char_limit - 10) {
            $text = mb_substr($text, 0, $char_limit - 13) . "...";
        }

        // Post data structure
        $post_data = [
            '$type' => "app.bsky.feed.post",
            "text" => $text,
            "createdAt" => gmdate("c"),
        ];

        // If we have an image, create an external embed (link card with thumbnail)
        if (!empty($image_url)) {
            $blob = $this->upload_image_blob($image_url);

            if ($blob !== false) {
                // Create external embed with thumbnail
                $post_data["embed"] = [
                    '$type' => "app.bsky.embed.external",
                    "external" => [
                        "uri" => $permalink,
                        "title" => wp_trim_words($title, 15, "..."),
                        "description" => !empty($excerpt)
                            ? wp_trim_words(
                                wp_strip_all_tags($excerpt),
                                30,
                                "...",
                            )
                            : "",
                        "thumb" => $blob,
                    ],
                ];
            }
        }

        // If no image embed was created, fall back to adding URL with facets
        if (!isset($post_data["embed"])) {
            $text_with_url = $text . "\n\n" . $permalink;
            $link_start = strlen($text) + 2; // Position after text and newlines
            $link_end = $link_start + strlen($permalink);

            $post_data["text"] = $text_with_url;
            $post_data["facets"] = [
                [
                    "index" => [
                        "byteStart" => $link_start,
                        "byteEnd" => $link_end,
                    ],
                    "features" => [
                        [
                            '$type' => "app.bsky.richtext.facet#link",
                            "uri" => $permalink,
                        ],
                    ],
                ],
            ];
        }

        $response = wp_remote_post(
            $this->bluesky_api_url . "com.atproto.repo.createRecord",
            [
                "timeout" => 15,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                    "Content-Type" => "application/json",
                ],
                "body" => wp_json_encode([
                    "repo" => $this->did,
                    "collection" => "app.bsky.feed.post",
                    "record" => $post_data,
                ]),
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body["uri"])) {
            return false;
        }

        // Extract post information from the response
        $uri_parts = explode("/", $body["uri"]);
        $post_rkey = end($uri_parts);

        // Get the handle from options, or fetch from profile if not available
        $handle = $this->options["handle"] ?? "";
        if (empty($handle)) {
            $profile = $this->get_bluesky_profile();
            $handle = $profile["handle"] ?? "";
        }

        // Construct the Bluesky post URL
        // If handle is still not available, use DID-based URL format
        if (!empty($handle)) {
            $bluesky_post_url =
                "https://bsky.app/profile/" . $handle . "/post/" . $post_rkey;
        } else {
            // Fallback: construct URL using DID (though handle-based is preferred)
            $bluesky_post_url =
                "https://bsky.app/profile/" .
                $this->did .
                "/post/" .
                $post_rkey;
        }

        // Build comprehensive post info array
        $post_info = [
            "uri" => $body["uri"],
            "cid" => $body["cid"] ?? "",
            "url" => $bluesky_post_url,
            "created_at" => $body["record"]["createdAt"] ?? gmdate("c"),
            "text" => $body["record"]["text"] ?? $text,
            "rkey" => $post_rkey,
            "handle" => $handle,
            "did" => $this->did,
        ];

        return $post_info;
    }

    /**
     * Process raw BlueSky posts into a normalized format
     * @param array $raw_posts Raw posts from BlueSky API
     * @return array Processed posts
     */
    private function process_posts($raw_posts)
    {
        return array_map(function ($post) {
            $post = $post["post"];

            // Extract embedded images
            $images = [];
            if (isset($post["embed"]["images"])) {
                foreach ($post["embed"]["images"] as $image) {
                    $images[] = [
                        "url" => $image["fullsize"] ?? ($image["thumb"] ?? ""),
                        "alt" => $image["alt"] ?? "",
                        "width" => $image["aspectRatio"]["width"] ?? 0,
                        "height" => $image["aspectRatio"]["height"] ?? 0,
                    ];
                }
            }

            // Extract external media
            $external_media = null;
            if (isset($post["embed"]["external"])) {
                $external_media = [
                    "uri" => $post["embed"]["external"]["uri"],
                    "title" => $post["embed"]["external"]["title"] ?? "",
                    "alt" => $post["embed"]["external"]["alt"] ?? "",
                    "thumb" => $post["embed"]["external"]["thumb"] ?? "",
                    "description" =>
                        $post["embed"]["external"]["description"] ?? "",
                ];
            } elseif (isset($post["embed"]["media"])) {
                $external_media = [
                    "uri" => $post["embed"]["media"]["external"]["uri"],
                    "title" =>
                        $post["embed"]["media"]["external"]["title"] ?? "",
                    "alt" => $post["embed"]["media"]["external"]["alt"] ?? "",
                    "thumb" =>
                        $post["embed"]["media"]["external"]["thumb"] ?? "",
                    "description" =>
                        $post["embed"]["media"]["external"]["description"] ??
                        "",
                ];
            }

            // Check for video embed
            $embedded_media = $this->extract_embedded_media($post);

            $end0fPostURI = isset($post["uri"])
                ? explode("/", $post["uri"])
                : [];
            return [
                "text" => $post["record"]["text"] ?? "No text",
                "langs" => $post["record"]["langs"] ?? ["en"],
                "url" =>
                    "https://bsky.app/profile/" .
                    ($post["author"]["handle"] ?? "") .
                    "/post/" .
                    (isset($post["uri"]) ? end($end0fPostURI) : ""),
                "created_at" => $post["record"]["createdAt"] ?? "",
                "account" => [
                    "did" => $post["author"]["did"] ?? "",
                    "handle" => $post["author"]["handle"] ?? "",
                    "display_name" => $post["author"]["displayName"] ?? "",
                    "avatar" => $post["author"]["avatar"] ?? "",
                ],
                "images" => $images,
                "external_media" => $external_media,
                "embedded_media" => $embedded_media,
                "counts" => [
                    "reply" => $post["replyCount"] ?? "",
                    "repost" => $post["repostCount"] ?? "",
                    "like" => $post["likeCount"] ?? "",
                    "quote" => $post["quoteCount"] ?? "",
                ],
                "facets" => $post["record"]["facets"] ?? [],
            ];
        }, $raw_posts);
    }

    /**
     * Get Bluesky post thread with replies and stats
     * @param string $post_uri The AT Protocol URI of the post
     * @return array|false Thread data or false on failure
     */
    public function get_post_thread($post_uri)
    {
        if (!$this->authenticate()) {
            return false;
        }

        $response = wp_remote_get(
            $this->bluesky_api_url . "app.bsky.feed.getPostThread",
            [
                "timeout" => 15,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                ],
                "body" => [
                    "uri" => $post_uri,
                    "depth" => 10, // Get up to 10 levels of replies
                ],
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body["thread"])) {
            return false;
        }

        return $body["thread"];
    }

    /**
     * Get Bluesky post statistics and details
     * @param string $post_uri The AT Protocol URI of the post
     * @return array|false Post data with stats or false on failure
     */
    public function get_post_stats($post_uri)
    {
        if (!$this->authenticate()) {
            return false;
        }

        $response = wp_remote_get(
            $this->bluesky_api_url . "app.bsky.feed.getPosts",
            [
                "timeout" => 15,
                "headers" => [
                    "Authorization" => "Bearer " . $this->access_token,
                ],
                "body" => [
                    "uris" => [$post_uri],
                ],
            ],
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body["posts"]) || empty($body["posts"])) {
            return false;
        }

        return $body["posts"][0];
    }

    /**
     * Extract embedded media from post
     * @param array $post Post data
     * @return array|null Embedded media details
     */
    private function extract_embedded_media($post)
    {
        $embedded_media = null;
        // Video embed
        if (
            isset($post["embed"]["video"]) ||
            (isset($post["embed"]['$type']) &&
                strstr($post["embed"]['$type'], "app.bsky.embed.video"))
        ) {
            $video_embed = $post["embed"];
            $embedded_media = [
                "type" => "video",
                "alt" => $video_embed["alt"] ?? "",
                "width" => $video_embed["aspectRatio"]["width"] ?? null,
                "height" => $video_embed["aspectRatio"]["height"] ?? null,
                "playlist_url" => $video_embed["playlist"] ?? "",
                "thumbnail_url" => $video_embed["thumbnail"] ?? "",
            ];
        }

        // Record (embedded post) embed
        elseif (
            isset($post["embed"]["record"]) ||
            (isset($post["embed"]['$type']) &&
                strstr($post["embed"]['$type'], "app.bsky.embed.record"))
        ) {
            // For some reasons, sometimes the API returns record in the record array. (multi embedded items?)
            $record_embed = isset($post["embed"]["record"]["record"])
                ? $post["embed"]["record"]["record"]
                : $post["embed"]["record"];

            if ("app.bsky.graph.starterpack" == $record_embed['$type']) {
                $end0fURI = explode("/", $post["embed"]["record"]["uri"]);
                $author = $post["embed"]["record"]["creator"];
                $embedded_media = [
                    "type" => "starterpack",
                    "author" => [
                        "did" => $author["did"] ?? "",
                        "handle" => $author["handle"] ?? "",
                        "display_name" => $author["displayName"] ?? "",
                    ],
                    "title" => $record_embed["name"] ?? "",
                    "text" => $record_embed["description"] ?? "",
                    "created_at" => $record_embed["createdAt"] ?? "",
                    "like_count" => $record_embed["likeCount"] ?? 0,
                    "reply_count" => $record_embed["replyCount"] ?? 0,
                    "url" =>
                        "https://bsky.app/starter-pack/" .
                        ($author["handle"] ?? "") .
                        "/" .
                        end($end0fURI),
                ];
            } else {
                $end0fURI = explode("/", $record_embed["uri"]);
                $embedded_media = [
                    "type" => "record",
                    "author" => [
                        "did" => $record_embed["author"]["did"] ?? "",
                        "handle" => $record_embed["author"]["handle"] ?? "",
                        "display_name" =>
                            $record_embed["author"]["displayName"] ?? "",
                    ],
                    "text" => $record_embed["value"]["text"] ?? "",
                    "created_at" => $record_embed["value"]["createdAt"] ?? "",
                    "like_count" => $record_embed["likeCount"] ?? 0,
                    "reply_count" => $record_embed["replyCount"] ?? 0,
                    "url" =>
                        "https://bsky.app/profile/" .
                        ($record_embed["author"]["handle"] ?? "") .
                        "/post/" .
                        ($record_embed["uri"] ? end($end0fURI) : ""),
                ];
            }

            // Check if the embedded record has its own media (like a video)
            if (isset($record_embed["value"]["embed"]["video"])) {
                $embedvideo = $record_embed["value"]["embed"];
                $embedded_media["embedded_video"] = [
                    "type" => "video",
                    "alt" => $embedvideo["alt"] ?? "",
                    "width" => $embedvideo["aspectRatio"]["width"] ?? null,
                    "height" => $embedvideo["aspectRatio"]["height"] ?? null,
                    "playlist_url" => $embedvideo["playlist"] ?? "",
                    "thumbnail_url" => $embedvideo["thumbnail"] ?? "",
                ];
            }
        }

        return $embedded_media;
    }
}
