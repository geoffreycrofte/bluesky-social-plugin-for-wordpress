<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Post_Metabox
{
    private $options;
    private $account_manager;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action("add_meta_boxes", [$this, "add_bluesky_meta_box"]);
        add_action("save_post", [$this, "save_bluesky_meta_box"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_metabox_scripts"]);
        add_action("init", [$this, "register_post_meta"]);
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS);
        $this->account_manager = new BlueSky_Account_Manager();
        // Register the AJAX handler
        $this->register_ajax_handler();
    }

    /**
     * Register post meta for Gutenberg
     */
    public function register_post_meta()
    {
        register_post_meta("post", "_bluesky_dont_syndicate", [
            "show_in_rest" => true,
            "single" => true,
            "type" => "string",
            "default" => "",
            "auth_callback" => function () {
                return current_user_can("edit_posts");
            },
        ]);

        register_post_meta("post", "_bluesky_syndication_accounts", [
            "show_in_rest" => true,
            "single" => true,
            "type" => "string", // JSON-encoded array of account UUIDs
            "default" => "",
            "auth_callback" => function () {
                return current_user_can("edit_posts");
            },
        ]);

        register_post_meta("post", "_bluesky_syndication_text", [
            "show_in_rest" => true,
            "single" => true,
            "type" => "string",
            "default" => "",
            "sanitize_callback" => "sanitize_textarea_field",
            "auth_callback" => function () {
                return current_user_can("edit_posts");
            },
        ]);
    }

    /**
     * Enqueue scripts and styles for the metabox
     */
    public function enqueue_metabox_scripts($hook)
    {
        // Only load on post edit screen
        if ("post.php" !== $hook && "post-new.php" !== $hook) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== "post") {
            return;
        }

        wp_enqueue_style(
            "bluesky-metabox-preview",
            BLUESKY_PLUGIN_FOLDER . "assets/css/bluesky-metabox-preview.css",
            [],
            BLUESKY_PLUGIN_VERSION,
        );

        // Enqueue pre-publish panel for Gutenberg
        if (
            function_exists("use_block_editor_for_post") &&
            use_block_editor_for_post($post)
        ) {
            // Enqueue character counter utility first
            wp_enqueue_script(
                "bluesky-character-counter",
                BLUESKY_PLUGIN_FOLDER . "assets/js/bluesky-character-counter.js",
                [],
                BLUESKY_PLUGIN_VERSION,
                true
            );

            wp_enqueue_script(
                "bluesky-pre-publish-panel",
                BLUESKY_PLUGIN_FOLDER . "blocks/bluesky-pre-publish-panel.js",
                [
                    "wp-plugins",
                    "wp-edit-post",
                    "wp-element",
                    "wp-data",
                    "wp-components",
                    "wp-i18n",
                    "bluesky-character-counter",
                ],
                BLUESKY_PLUGIN_VERSION,
                true,
            );

            // Create nonce for the metabox
            $nonce = wp_create_nonce("bluesky_meta_box_nonce");

            $options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
            $localize_data = [
                "nonce" => $nonce,
                "postId" => $post->ID,
                "globalPaused" => !empty($options['global_pause']),
                "settingsUrl" => admin_url('options-general.php?page=bluesky-social-settings#syndication'),
            ];

            // Add account data if multi-account is enabled
            if ($this->account_manager->is_multi_account_enabled()) {
                $localize_data["multiAccountEnabled"] = true;
                // Only pass safe fields to JS (no app_password/did)
                $accounts = $this->account_manager->get_accounts();
                $safe_accounts = [];
                foreach ($accounts as $account) {
                    $safe_accounts[] = [
                        'id' => $account['id'] ?? '',
                        'name' => $account['name'] ?? '',
                        'handle' => $account['handle'] ?? '',
                        'auto_syndicate' => !empty($account['auto_syndicate']),
                        'category_rules' => $account['category_rules'] ?? ['include' => [], 'exclude' => []],
                    ];
                }
                $localize_data["accounts"] = $safe_accounts;
            } else {
                $localize_data["multiAccountEnabled"] = false;
                $localize_data["accounts"] = [];
            }

            wp_localize_script(
                "bluesky-pre-publish-panel",
                "blueskyPrePublishData",
                $localize_data
            );
        }
    }

    /**
     * Add the Bluesky meta box to the post editor (classic editor only)
     * In Gutenberg, the sidebar panel handles all syndication controls.
     */
    public function add_bluesky_meta_box()
    {
        global $post;

        // Skip meta box in Gutenberg — sidebar panel handles it
        if (
            $post &&
            function_exists("use_block_editor_for_post") &&
            use_block_editor_for_post($post)
        ) {
            return;
        }

        add_meta_box(
            "bluesky_syndication_meta_box",
            esc_html__("Bluesky Syndication", "social-integration-for-bluesky"),
            [$this, "render_bluesky_meta_box"],
            "post",
            "side",
            "default",
        );
    }

    /**
     * Render the Bluesky meta box
     *
     * @param WP_Post $post The post object
     */
    public function render_bluesky_meta_box($post)
    {
        // Check if the plugin option for activation date exists.
        if (get_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date") === false) {
            add_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date", time());
        }
        // Retrieve the current value of the meta
        $default_value = $this->options["auto_syndicate"] ?? 0;
        // but check is post older than plugin activation date
        $default_value =
            $default_value === 1
                ? (strtotime($post->post_date) <
                get_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date")
                    ? 0
                    : $default_value)
                : 0;

        $dont_syndicate = get_post_meta(
            $post->ID,
            "_bluesky_dont_syndicate",
            true,
        );
        $dont_syndicate =
            $dont_syndicate === ""
                ? ($default_value
                    ? "0"
                    : "1")
                : $dont_syndicate;
        // Render the checkbox
        ?>

        <div class="bluesky-meta-box-content">
            <?php
            $options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
            if (!empty($options['global_pause'])) :
                $settings_url = admin_url('options-general.php?page=bluesky-social-settings#syndication');
            ?>
            <div class="bluesky-global-pause-warning">
                <strong><?php esc_html_e('Syndication is globally paused.', 'social-integration-for-bluesky'); ?></strong>
                <a href="<?php echo esc_url($settings_url); ?>" class="bluesky-global-pause-link"><?php esc_html_e('Manage in Settings', 'social-integration-for-bluesky'); ?> &rarr;</a>
            </div>
            <?php endif; ?>
            <label for="bluesky_dont_syndicate">
                <input type="checkbox" name="bluesky_dont_syndicate" id="bluesky_dont_syndicate" value="1" <?php checked(
                    $dont_syndicate,
                    "1",
                ); ?> aria-describedby="bluesky-dont-syndicate" />

                <?php esc_html_e(
                    "Don't syndicate this post on Bluesky",
                    "social-integration-for-bluesky",
                ); ?>
                <?php // Add a nonce for security

        wp_nonce_field("bluesky_meta_box_nonce", "bluesky_meta_box_nonce"); ?>
            </label>

            <p class="description bluesky-metabox-description" id="bluesky-dont-syndicate"><?php esc_html_e(
                "Check to avoid sending the post on Bluesky. Uncheck to send this post on Bluesky.",
                "social-integration-for-bluesky",
            ); ?></p>

            <?php
        // Multi-account selection UI
        if ($this->account_manager->is_multi_account_enabled()) {
            $accounts = $this->account_manager->get_accounts();
            $selected_json = get_post_meta(
                $post->ID,
                "_bluesky_syndication_accounts",
                true
            );
            $selected = $selected_json ? json_decode($selected_json, true) : [];

            // If new post and no selection yet, pre-select auto-syndicate accounts
            if (
                empty($selected) &&
                get_post_status($post->ID) !== "publish"
            ) {
                $selected = [];
                foreach ($accounts as $account) {
                    if (!empty($account["auto_syndicate"])) {
                        $selected[] = $account["id"];
                    }
                }
            }

            if (!empty($accounts)) {
                echo '<div class="bluesky-account-selection" id="bluesky-account-selection">';
                echo '<p><strong>' .
                    esc_html__(
                        "Syndicate to:",
                        "social-integration-for-bluesky"
                    ) .
                    "</strong></p>";

                foreach ($accounts as $account_key => $account) {
                    $acct_id = $account["id"] ?? $account_key;
                    $checked = in_array($acct_id, $selected)
                        ? "checked"
                        : "";
                    printf(
                        '<label class="bluesky-account-label"><input type="checkbox" name="bluesky_syndication_accounts[]" value="%s" %s class="bluesky-account-checkbox"> %s (@%s)</label>',
                        esc_attr($acct_id),
                        $checked,
                        esc_html($account["name"] ?? ''),
                        esc_html($account["handle"] ?? '')
                    );
                }

                echo "</div>";

                // Add inline script to disable account checkboxes when "Don't syndicate" is checked
                ?>
                <script type="text/javascript">
                (function() {
                    var dontSyndicateCheckbox = document.getElementById('bluesky_dont_syndicate');
                    var accountCheckboxes = document.querySelectorAll('.bluesky-account-checkbox');
                    var accountSelection = document.getElementById('bluesky-account-selection');

                    function toggleAccountSelection() {
                        var disabled = dontSyndicateCheckbox.checked;
                        accountCheckboxes.forEach(function(checkbox) {
                            checkbox.disabled = disabled;
                        });
                        if (accountSelection) {
                            accountSelection.style.opacity = disabled ? '0.5' : '1';
                        }
                    }

                    if (dontSyndicateCheckbox) {
                        dontSyndicateCheckbox.addEventListener('change', toggleAccountSelection);
                        toggleAccountSelection(); // Run on load
                    }
                })();
                </script>
                <?php
            }
        }
        ?>
        </div>
        <?php
    }

    /**
     * Save the Bluesky meta box
     *
     * @param int $post_id The post ID
     */
    public function save_bluesky_meta_box($post_id)
    {
        // Verify the nonce
        if (
            !isset($_POST["bluesky_meta_box_nonce"]) ||
            !wp_verify_nonce(
                $_POST["bluesky_meta_box_nonce"],
                "bluesky_meta_box_nonce",
            )
        ) {
            return;
        }

        // Avoid autosave
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can("edit_post", $post_id)) {
            return;
        }

        // Save or delete the meta value
        if (isset($_POST["bluesky_dont_syndicate"])) {
            update_post_meta($post_id, "_bluesky_dont_syndicate", "1");
        } else {
            delete_post_meta($post_id, "_bluesky_dont_syndicate");
        }

        // Save selected accounts (multi-account mode)
        if (isset($_POST["bluesky_syndication_accounts"])) {
            $accounts = array_map(
                "sanitize_text_field",
                $_POST["bluesky_syndication_accounts"]
            );
            update_post_meta(
                $post_id,
                "_bluesky_syndication_accounts",
                wp_json_encode($accounts)
            );
        } else {
            // Clear selection if no accounts checked
            delete_post_meta($post_id, "_bluesky_syndication_accounts");
        }
    }

    /**
     * Register AJAX handler for saving meta box data
     */
    public function register_ajax_handler()
    {
        add_action("wp_ajax_save_bluesky_meta_box", [
            $this,
            "ajax_save_bluesky_meta_box",
        ]);
        add_action("wp_ajax_get_bluesky_post_preview", [
            $this,
            "ajax_get_bluesky_post_preview",
        ]);
    }

    /**
     * Handle AJAX request to save meta box data
     */
    public function ajax_save_bluesky_meta_box()
    {
        // Verify the nonce
        if (
            !isset($_POST["bluesky_meta_box_nonce"]) ||
            !wp_verify_nonce(
                $_POST["bluesky_meta_box_nonce"],
                "bluesky_meta_box_nonce",
            )
        ) {
            wp_send_json_error(__("Invalid nonce", "social-integration-for-bluesky"));
        }

        // Check user permissions
        if (!current_user_can("edit_post", $_POST["post_id"])) {
            wp_send_json_error(__("Insufficient permissions", "social-integration-for-bluesky"));
        }

        // Save or delete the meta value
        if (
            isset($_POST["bluesky_dont_syndicate"]) &&
            $_POST["bluesky_dont_syndicate"] === "1"
        ) {
            update_post_meta($_POST["post_id"], "_bluesky_dont_syndicate", "1");
        } else {
            delete_post_meta($_POST["post_id"], "_bluesky_dont_syndicate");
        }

        wp_send_json_success("Meta box data saved");
    }

    /**
     * Handle AJAX request to get Bluesky post preview
     */
    public function ajax_get_bluesky_post_preview()
    {
        // Verify the nonce
        if (
            !isset($_POST["nonce"]) ||
            !wp_verify_nonce($_POST["nonce"], "bluesky_meta_box_nonce")
        ) {
            wp_send_json_error(__("Invalid nonce", "social-integration-for-bluesky"));
        }

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

        // Check user permissions
        if (!current_user_can("edit_post", $post_id)) {
            wp_send_json_error(__("Insufficient permissions", "social-integration-for-bluesky"));
        }

        // Get post data from AJAX or from saved post
        $title = isset($_POST["title"])
            ? sanitize_text_field($_POST["title"])
            : "";
        $content = isset($_POST["content"])
            ? wp_kses_post($_POST["content"])
            : "";
        $excerpt = isset($_POST["excerpt"])
            ? sanitize_textarea_field($_POST["excerpt"])
            : "";

        // If no data from AJAX, try to get from post
        if (empty($title) && $post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                $title = $post->post_title;
                $content = $post->post_content;
                $excerpt = $post->post_excerpt;
            }
        }

        // Generate excerpt if not provided
        if (empty($excerpt) && !empty($content)) {
            $excerpt = wp_trim_words(wp_strip_all_tags($content), 30, "...");
        }

        // Get image (featured or first in content)
        $image_url = "";
        $image_alt = "";

        if ($post_id > 0 && has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, "medium");
            $image_alt = get_post_meta(
                get_post_thumbnail_id($post_id),
                "_wp_attachment_image_alt",
                true,
            );
        }

        // If no featured image, try to find first image in content
        if (empty($image_url) && !empty($content)) {
            preg_match(
                '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i',
                $content,
                $matches,
            );
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        // Build preview text similar to what will be posted
        $char_limit = 300;
        $preview_text = "";
        $title_trimmed = wp_trim_words($title, 20, "...");

        $preview_text = $title_trimmed;

        // Add excerpt if there's space
        if (!empty($excerpt)) {
            $excerpt_clean = wp_strip_all_tags($excerpt);
            $space_for_excerpt = $char_limit - strlen($preview_text) - 10;

            if ($space_for_excerpt > 50) {
                $excerpt_trimmed = mb_substr(
                    $excerpt_clean,
                    0,
                    $space_for_excerpt,
                );
                $last_space = mb_strrpos($excerpt_trimmed, " ");
                if ($last_space !== false && $last_space > 30) {
                    $excerpt_trimmed = mb_substr(
                        $excerpt_trimmed,
                        0,
                        $last_space,
                    );
                }
                $excerpt_trimmed = trim($excerpt_trimmed) . "...";
                $preview_text .= "\n" . $excerpt_trimmed;
            }
        }

        // Generate preview HTML
        $html = '<div class="bluesky-preview-card">';

        // Text preview
        $html .= '<div class="bluesky-preview-text">';
        $html .=
            "<strong>" .
            esc_html__("Post Text:", "social-integration-for-bluesky") .
            "</strong>";
        $html .= "<p>" . nl2br(esc_html($preview_text)) . "</p>";
        $html .=
            '<small class="bluesky-char-count">' .
            sprintf(
                esc_html__(
                    "Characters: %d / 300",
                    "social-integration-for-bluesky",
                ),
                strlen($preview_text),
            ) .
            "</small>";
        $html .= "</div>";

        // Link card preview
        if (!empty($image_url)) {
            $html .= '<div class="bluesky-preview-linkcard">';
            $html .=
                "<strong>" .
                esc_html__("Link Card:", "social-integration-for-bluesky") .
                "</strong>";
            $html .= '<div class="bluesky-linkcard-preview">';

            if (!empty($image_url)) {
                $html .= '<div class="bluesky-linkcard-image">';
                $html .=
                    '<img src="' .
                    esc_url($image_url) .
                    '" alt="' .
                    esc_attr($image_alt) .
                    '" />';
                $html .= "</div>";
            }

            $html .= '<div class="bluesky-linkcard-content">';
            $html .=
                '<div class="bluesky-linkcard-title">' .
                esc_html(wp_trim_words($title, 15, "...")) .
                "</div>";

            if (!empty($excerpt)) {
                $html .=
                    '<div class="bluesky-linkcard-description">' .
                    esc_html(
                        wp_trim_words(wp_strip_all_tags($excerpt), 30, "..."),
                    ) .
                    "</div>";
            }

            $html .=
                '<div class="bluesky-linkcard-url">' .
                esc_html(get_site_url()) .
                "</div>";
            $html .= "</div>";
            $html .= "</div>";
            $html .= "</div>";
        } else {
            $html .= '<div class="bluesky-preview-note">';
            $html .=
                "<p><em>" .
                esc_html__(
                    "No image found. The post will include a text link instead of a link card.",
                    "social-integration-for-bluesky",
                ) .
                "</em></p>";
            $html .= "</div>";
        }

        $html .= "</div>";

        wp_send_json_success(["html" => $html]);
    }
}
