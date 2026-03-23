<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky_Link_Metabox
 *
 * Provides a "Link to Bluesky" metabox (classic editor) and sidebar panel
 * (Gutenberg) so editors can manually associate an existing Bluesky post URL
 * with a WordPress post that was never auto-syndicated.
 *
 * After linking, the post meta mirrors the structure produced by automatic
 * syndication so the Discussion metabox and all status indicators work as
 * expected without any modifications to those components.
 */
class BlueSky_Link_Metabox
{
    public function __construct()
    {
        add_action("add_meta_boxes", [$this, "add_link_meta_box"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);
        add_action("wp_ajax_bluesky_link_post", [$this, "ajax_link_post"]);
    }

    /**
     * Register the classic-editor metabox.
     * Shown only on published posts that are not yet syndicated.
     * In Gutenberg, the sidebar panel (bluesky-link-panel.js) takes over.
     */
    public function add_link_meta_box()
    {
        global $post;
        if (!$post) {
            return;
        }

        // Only for published posts
        if ($post->post_status !== "publish") {
            return;
        }

        // Skip if already syndicated
        if (get_post_meta($post->ID, "_bluesky_syndicated", true)) {
            return;
        }

        // Skip in Gutenberg — sidebar panel handles it
        if (
            function_exists("use_block_editor_for_post") &&
            use_block_editor_for_post($post)
        ) {
            return;
        }

        add_meta_box(
            "bluesky_link_meta_box",
            esc_html__("Link to Bluesky", "social-integration-for-bluesky"),
            [$this, "render_link_meta_box"],
            "post",
            "side",
            "default",
        );
    }

    /**
     * Render the classic-editor metabox HTML.
     *
     * @param WP_Post $post
     */
    public function render_link_meta_box($post)
    {
        wp_nonce_field("bluesky_link_nonce", "bluesky_link_nonce");

        $i18n = [
            "success"      => esc_html__("Post linked! Reloading\u{2026}", "social-integration-for-bluesky"),
            "networkError" => esc_html__("Network error. Please try again.", "social-integration-for-bluesky"),
            "fallback"     => esc_html__("Failed to link post.", "social-integration-for-bluesky"),
        ];
        ?>
        <div class="bluesky-link-metabox">
            <p class="description" style="margin-bottom: 8px;">
                <?php esc_html_e(
                    "Already published this post on Bluesky? Paste the post URL to link it.",
                    "social-integration-for-bluesky",
                ); ?>
            </p>

            <label for="bluesky_post_url" class="screen-reader-text">
                <?php esc_html_e(
                    "Bluesky Post URL",
                    "social-integration-for-bluesky",
                ); ?>
            </label>
            <input
                type="url"
                id="bluesky_post_url"
                class="widefat"
                placeholder="https://bsky.app/profile/handle/post/…"
                value=""
                autocomplete="off"
            />
            <p class="description" style="margin-top: 4px; font-size: 11px; word-break: break-all;">
                <?php esc_html_e(
                    "e.g. https://bsky.app/profile/yourhandle.bsky.social/post/abc123",
                    "social-integration-for-bluesky",
                ); ?>
            </p>

            <div id="bluesky-link-message" style="display:none; margin-top: 8px;" role="status" aria-live="polite"></div>

            <button
                type="button"
                id="bluesky-link-post-btn"
                class="button button-primary"
                style="margin-top: 10px; width: 100%;"
            >
                <?php esc_html_e(
                    "Link to Bluesky",
                    "social-integration-for-bluesky",
                ); ?>
            </button>
        </div>

        <script type="text/javascript">
        (function () {
            var btn   = document.getElementById('bluesky-link-post-btn');
            var input = document.getElementById('bluesky_post_url');
            var msg   = document.getElementById('bluesky-link-message');
            var nonce = document.getElementById('bluesky_link_nonce');

            if (!btn || !input || !nonce) { return; }

            var i18n = <?php echo wp_json_encode($i18n); ?>;

            function showMessage(text, color) {
                var p = document.createElement('p');
                p.style.margin = '0';
                p.style.color  = color;
                p.textContent  = text;
                while (msg.firstChild) { msg.removeChild(msg.firstChild); }
                msg.appendChild(p);
                msg.style.display = 'block';
            }

            btn.addEventListener('click', function () {
                var url = input.value.trim();
                if (!url) { return; }

                btn.disabled = true;

                var data = new FormData();
                data.append('action',      'bluesky_link_post');
                data.append('nonce',       nonce.value);
                data.append('post_id',     '<?php echo esc_js((string) $post->ID); ?>');
                data.append('bluesky_url', url);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (response) {
                        if (response.success) {
                            showMessage(i18n.success, '#46b450');
                            setTimeout(function () { window.location.reload(); }, 1200);
                        } else {
                            showMessage(
                                (typeof response.data === 'string' && response.data)
                                    ? response.data
                                    : i18n.fallback,
                                '#d63638'
                            );
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        showMessage(i18n.networkError, '#d63638');
                        btn.disabled = false;
                    });
            });
        })();
        </script>
        <?php
    }

    /**
     * Enqueue the Gutenberg sidebar panel script.
     * Only loaded on published posts not yet syndicated.
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook)
    {
        if ("post.php" !== $hook && "post-new.php" !== $hook) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== "post") {
            return;
        }

        // Only for published posts
        if ($post->post_status !== "publish") {
            return;
        }

        // Only in Gutenberg
        if (
            !function_exists("use_block_editor_for_post") ||
            !use_block_editor_for_post($post)
        ) {
            return;
        }

        wp_enqueue_script(
            "bluesky-link-panel",
            BLUESKY_PLUGIN_FOLDER . "blocks/bluesky-link-panel.js",
            [
                "wp-plugins",
                "wp-edit-post",
                "wp-element",
                "wp-data",
                "wp-compose",
                "wp-components",
                "wp-i18n",
            ],
            BLUESKY_PLUGIN_VERSION,
            true,
        );

        wp_localize_script("bluesky-link-panel", "blueskyLinkData", [
            "ajaxUrl"      => admin_url("admin-ajax.php"),
            "nonce"        => wp_create_nonce("bluesky_link_nonce"),
            "postId"       => $post->ID,
            "isSyndicated" => (bool) get_post_meta($post->ID, "_bluesky_syndicated", true),
            "i18n"         => [
                "panelTitle"   => __("Link to Bluesky", "social-integration-for-bluesky"),
                "description"  => __("Already published this post on Bluesky? Paste the URL to link it.", "social-integration-for-bluesky"),
                "label"        => __("Bluesky Post URL", "social-integration-for-bluesky"),
                "placeholder"  => "https://bsky.app/profile/handle/post/\u2026",
                "button"       => __("Link to Bluesky", "social-integration-for-bluesky"),
                "linking"      => __("Linking\u2026", "social-integration-for-bluesky"),
                "successMsg"   => __("Post linked! Reloading\u2026", "social-integration-for-bluesky"),
                "networkError" => __("Network error. Please try again.", "social-integration-for-bluesky"),
                "fallbackError"=> __("Failed to link post.", "social-integration-for-bluesky"),
            ],
        ]);
    }

    /**
     * Parse a Bluesky post URL into components.
     *
     * Accepted format: https://bsky.app/profile/{handle_or_did}/post/{rkey}
     *
     * @param string $url Raw input URL
     * @return array|null ['identifier' => string, 'rkey' => string, 'url' => string] or null
     */
    private function parse_bluesky_url($url)
    {
        $url = trim($url);

        // Allow both handle (e.g. user.bsky.social) and DID (did:plc:...) formats
        $pattern = '#^https://bsky\.app/profile/([^/\s]+)/post/([a-zA-Z0-9]+)$#';
        if (!preg_match($pattern, $url, $matches)) {
            return null;
        }

        return [
            "identifier" => $matches[1],
            "rkey"       => $matches[2],
            "url"        => esc_url_raw($url),
        ];
    }

    /**
     * Resolve a Bluesky handle to its DID via com.atproto.identity.resolveHandle.
     * If $identifier is already a DID (starts with "did:"), it is returned as-is.
     * Returns null on failure.
     *
     * @param string $identifier Handle or DID
     * @return string|null Resolved DID or null on error
     */
    private function resolve_did($identifier)
    {
        // Already a DID — no resolution needed
        if (strpos($identifier, "did:") === 0) {
            return $identifier;
        }

        $response = wp_remote_get(
            "https://bsky.social/xrpc/com.atproto.identity.resolveHandle",
            [
                "timeout" => 10,
                "body"    => ["handle" => $identifier],
            ],
        );

        if (is_wp_error($response)) {
            return null;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body["did"]) ? $body["did"] : null;
    }

    /**
     * AJAX handler: validate the URL, resolve handle → DID, save post meta.
     */
    public function ajax_link_post()
    {
        if (
            !isset($_POST["nonce"]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST["nonce"])), "bluesky_link_nonce")
        ) {
            wp_send_json_error(
                __("Invalid nonce.", "social-integration-for-bluesky"),
            );
        }

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
        if (!$post_id || !current_user_can("edit_post", $post_id)) {
            wp_send_json_error(
                __("Insufficient permissions.", "social-integration-for-bluesky"),
            );
        }

        $raw_url = isset($_POST["bluesky_url"])
            ? esc_url_raw(wp_unslash($_POST["bluesky_url"]))
            : "";

        if (empty($raw_url)) {
            wp_send_json_error(
                __("Please provide a Bluesky post URL.", "social-integration-for-bluesky"),
            );
        }

        $parsed = $this->parse_bluesky_url($raw_url);
        if (!$parsed) {
            wp_send_json_error(
                __("Invalid Bluesky URL. Expected: https://bsky.app/profile/handle/post/postid", "social-integration-for-bluesky"),
            );
        }

        // Resolve handle to DID so the AT URI matches what Bluesky's API returns
        // and what app.bsky.feed.getPosts / getPostThread require.
        $did = $this->resolve_did($parsed["identifier"]);
        if (!$did) {
            wp_send_json_error(
                __("Could not resolve the Bluesky handle. Please check the URL and try again.", "social-integration-for-bluesky"),
            );
        }

        $post_info = [
            "uri"             => "at://" . $did . "/app.bsky.feed.post/" . $parsed["rkey"],
            "cid"             => "",
            "url"             => $parsed["url"],
            "syndicated_at"   => time(),
            "success"         => true,
            "manually_linked" => true,
        ];

        // Store using the legacy single-object format.
        // BlueSky_Discussion_Metabox::get_syndication_info_for_discussion()
        // detects this format via isset($data['uri']) and returns it as-is,
        // so no changes are needed in the Discussion metabox.
        update_post_meta(
            $post_id,
            "_bluesky_syndication_bs_post_info",
            wp_json_encode($post_info),
        );
        update_post_meta($post_id, "_bluesky_syndicated", true);
        update_post_meta($post_id, "_bluesky_syndication_status", "completed");

        wp_send_json_success([
            "message" => __("Post linked successfully.", "social-integration-for-bluesky"),
            "url"     => $post_info["url"],
        ]);
    }
}
