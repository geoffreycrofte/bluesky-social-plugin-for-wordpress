<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Render_Front
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
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     */
    public function __construct(BlueSky_API_Handler $api_handler)
    {
        $this->api_handler = $api_handler;
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS);
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Shortcodes
        add_shortcode("bluesky_profile", [
            $this,
            "bluesky_profile_card_shortcode",
        ]);
        add_shortcode("bluesky_last_posts", [
            $this,
            "bluesky_last_posts_shortcode",
        ]);
        // Some extensions for wp_kses
        add_filter("wp_kses_allowed_html", [$this, "allow_svg_tags"], 10, 2);
    }

    /**
     * Adds SVG into the post wp_kses_allowed_html in the 'post' context.
     *
     * @param mixed $allowed_tags
     * @param mixed $context
     *
     * @return array The initial array of the array updated.
     */
    function allow_svg_tags($allowed_tags, $context)
    {
        if ($context === "post") {
            // Add SVG support to the default allowed tags
            $svg_elements = [
                "svg" => [
                    "xmlns" => true,
                    "viewBox" => true,
                    "width" => true,
                    "height" => true,
                    "fill" => true,
                    "stroke" => true,
                    "stroke-width" => true,
                    "class" => true,
                    "aria-hidden" => true,
                    "role" => true,
                ],
                "g" => [],
                "path" => [
                    "d" => true,
                    "fill" => true,
                    "stroke" => true,
                    "stroke-width" => true,
                ],
                "circle" => [
                    "cx" => true,
                    "cy" => true,
                    "r" => true,
                    "fill" => true,
                    "stroke" => true,
                ],
                "rect" => [
                    "x" => true,
                    "y" => true,
                    "width" => true,
                    "height" => true,
                    "fill" => true,
                    "stroke" => true,
                ],
                "polygon" => [
                    "points" => true,
                    "fill" => true,
                    "stroke" => true,
                ],
            ];

            return array_merge($allowed_tags, $svg_elements);
        }

        return $allowed_tags;
    }

    /**
     * Shortcode for BlueSky last posts
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function bluesky_last_posts_shortcode($atts = [])
    {
        // Convert shortcode attributes to array and merge with defaults
        $attributes = wp_parse_args($atts, [
            "theme" => $this->options["theme"] ?? "system",
            "displayembeds" => !$this->options["no_embeds"] ?? false,
            "noreplies" => $this->options["no_replies"] ?? true,
            "noreposts" => $this->options["no_reposts"] ?? true,
            "numberofposts" => $this->options["posts_limit"] ?? 5,
            "nocounters" => $this->options["no_counters"] ?? false,
            "account_id" => "",
            "layout" => "",
        ]);

        // Convert string boolean values to actual booleans
        $attributes["displayembeds"] = filter_var(
            $attributes["displayembeds"],
            FILTER_VALIDATE_BOOLEAN,
        );
        $attributes["noreplies"] = filter_var(
            $attributes["noreplies"],
            FILTER_VALIDATE_BOOLEAN,
        );
        $attributes["noreposts"] = filter_var(
            $attributes["noreposts"],
            FILTER_VALIDATE_BOOLEAN,
        );
        $attributes["nocounters"] = filter_var(
            $attributes["nocounters"],
            FILTER_VALIDATE_BOOLEAN,
        );
        return $this->render_bluesky_posts_list($attributes);
    }

    /**
     * Render the BlueSky last posts list
     * @param array $attributes Shortcode attributes
     * @return string HTML output
     */
    public function render_bluesky_posts_list($attributes = [])
    {
        // Set default attributes
        $defaults = [
            "theme" => $this->options["theme"] ?? "system",
            "displayembeds" => !$this->options["no_embeds"] ?? false,
            "noreplies" => $this->options["no_replies"] ?? true,
            "noreposts" => $this->options["no_reposts"] ?? true,
            "numberofposts" => $this->options["posts_limit"] ?? 5,
            "nocounters" => $this->options["no_counters"] ?? false,
            "account_id" => "",
            "layout" => "",
        ];

        // Merge defaults with provided attributes
        $attributes = wp_parse_args($attributes, $defaults);

        // Extract variables
        $display_embeds = $attributes["displayembeds"];
        $no_replies = $attributes["noreplies"];
        $no_reposts = $attributes["noreposts"];
        $theme = $attributes["theme"];
        $number_of_posts = $attributes["numberofposts"];
        $no_counters = $attributes["nocounters"];
        $account_id = $attributes["account_id"];
        $layout = !empty($attributes["layout"]) ? $attributes["layout"] : ($this->options["styles"]["feed_layout"] ?? "default");

        // Normalize "compact" alias to "layout_2"
        if ($layout === "compact") {
            $layout = "layout_2";
        }

        // Cache-first: check transient before making any API call
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers->get_posts_transient_key(
            $account_id,
            intval($number_of_posts),
            (bool) $no_replies,
            $no_reposts,
        );
        $cached_posts = get_transient($cache_key);
        $is_fresh = BlueSky_Helpers::is_cache_fresh($cache_key);
        $cache_timestamp = null;

        if ($cached_posts !== false) {
            // Fast path: render from cache (no API call)
            $posts = $cached_posts;

            // If cache is stale, schedule background refresh
            if (!$is_fresh) {
                BlueSky_Helpers::schedule_cache_refresh($cache_key, $account_id, [
                    'limit' => intval($number_of_posts),
                    'no_replies' => (bool) $no_replies,
                    'no_reposts' => $no_reposts,
                ]);
                // Get cache timestamp from freshness marker (it stores the time)
                $freshness_key = $cache_key . '_fresh';
                $cache_timestamp = get_transient($freshness_key);
                if (false === $cache_timestamp) {
                    // Freshness marker expired - estimate from transient expiry
                    $cache_timestamp = time() - 600; // Assume 10 minutes old
                }
            }
        } elseif ((!defined('DOING_AJAX') || !DOING_AJAX) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
            // No cache and not an AJAX/REST request: return skeleton placeholder
            $params = wp_json_encode([
                "theme" => $theme,
                "numberofposts" => $number_of_posts,
                "noreplies" => $no_replies,
                "noreposts" => $no_reposts,
                "nocounters" => $no_counters,
                "displayembeds" => $display_embeds,
                "account_id" => $account_id,
            ]);
            return $this->render_posts_skeleton($theme, $layout, $params);
        } else {
            // AJAX request: fetch fresh data
            $posts = $this->api_handler->fetch_bluesky_posts(
                intval($number_of_posts),
                (bool) $no_replies,
                $no_reposts,
            );
        }

        // Apply theme class
        $classes = " theme-" . esc_attr($theme);
        // Apply layout class
        $classes .= " display-" . esc_attr($layout);

        // For layout_2, fetch profile data for the header
        $profile = null;
        if ($layout === 'layout_2') {
            $profile_cache_key = $helpers->get_profile_transient_key($account_id ?: null);
            $profile = get_transient($profile_cache_key);
            if ($profile === false) {
                $profile = $this->api_handler->get_bluesky_profile();
            }
        }

        // Render template
        ob_start();
        do_action("bluesky_before_post_list_markup", $posts);
        add_action("wp_head", [$this, "render_inline_custom_styles_posts"]);

        // Spectra plugin may remove wp_head styles for some reasons. Try in wp_footer.
        if (defined("UAGB_PLUGIN_DIR")) {
            add_action("wp_footer", [
                $this,
                "render_inline_custom_styles_posts",
            ]);
        }

        include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/posts-list.php';

        // Render stale indicator if cache is stale
        if (!$is_fresh && null !== $cache_timestamp) {
            $time_ago = BlueSky_Helpers::time_ago($cache_timestamp);
            include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/stale-indicator.php';
        }

        do_action("bluesky_after_post_list_markup", $posts);
        return ob_get_clean();
    }

    /**
     * Render the BlueSky post content
     * @param array $post Post data
     * @return string HTML output
     */
    public function render_bluesky_post_content($post)
    {
        if (isset($post["facets"]) && is_array($post["facets"])) {
            $text = $post["text"];
            $replacements = [];
            $replacement_length = 0;
            $replacement = "";

            foreach ($post["facets"] as $facet) {
                $start = $facet["index"]["byteStart"];
                $end = $facet["index"]["byteEnd"];
                $length = $end - $start;
                $substring = substr($text, $start, $length);

                if (
                    $facet["features"][0]['$type'] ===
                    "app.bsky.richtext.facet#link"
                ) {
                    $uri = $facet["features"][0]["uri"];
                    $replacement =
                        '<a href="' .
                        esc_url($uri) .
                        '">' .
                        esc_html($substring) .
                        "</a>";
                } elseif (
                    $facet["features"][0]['$type'] ===
                    "app.bsky.richtext.facet#tag"
                ) {
                    $tag = $facet["features"][0]["tag"];
                    $replacement =
                        '<a href="https://bsky.app/hashtag/' .
                        esc_attr($tag) .
                        '">' .
                        esc_html($substring) .
                        "</a>";
                }

                $replacements[] = [
                    "start" => $start + $replacement_length,
                    "end" => $end + $replacement_length,
                    "string" => $substring,
                    "replacement" => $replacement,
                ];

                $replacement_length += strlen($replacement) - $length;
            }

            foreach ($replacements as $replacement) {
                $text = substr_replace(
                    $text,
                    $replacement["replacement"],
                    $replacement["start"],
                    $replacement["end"] - $replacement["start"],
                );
            }
            return nl2br($text);
        } else {
            return nl2br(esc_html($post["text"]));
        }
    }

    // Shortcode for BlueSky profile card
    public function bluesky_profile_card_shortcode($atts = [])
    {
        // Convert shortcode attributes to array and merge with defaults
        $attributes = shortcode_atts(
            [
                "theme" => $this->options["theme"] ?? "system",
                "styleClass" => "",
                "displaybanner" => true,
                "displayavatar" => true,
                "displaycounters" => true,
                "displaybio" => true,
                "account_id" => "",
                "layout" => $this->options["styles"]["profile_layout"] ?? "default",
            ],
            $atts,
        );

        // Convert string boolean values to actual booleans
        $boolean_attrs = [
            "displaybanner",
            "displayavatar",
            "displaycounters",
            "displaybio",
        ];
        foreach ($boolean_attrs as $attr) {
            $attributes[$attr] = filter_var(
                $attributes[$attr],
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        return $this->render_bluesky_profile_card($attributes);
    }

    /**
     * Render the profile card in compact layout
     * @param array $profile Profile data
     * @param array $attributes Block/shortcode attributes
     * @param bool $is_fresh Whether the cache is fresh
     * @param int|null $cache_timestamp Cache timestamp for stale indicator
     * @return string HTML output
     */
    private function render_profile_compact($profile, $attributes, $is_fresh, $cache_timestamp)
    {
        $theme = $attributes["theme"] ?? ($this->options["theme"] ?? "system");

        // Check for missing banner - set flag for gradient fallback
        $needs_gradient_fallback = empty($profile['banner']);

        // Build CSS classes - reuse the main profile card class with a compact modifier
        $classes = [
            'bluesky-social-integration-profile-card',
            'display-compact',
            $attributes["styleClass"] ?? "",
            "theme-{$theme}",
        ];

        // Add display toggle classes (same as default layout)
        $display_elements = ["banner", "avatar", "counters", "bio"];
        foreach ($display_elements as $element) {
            $option_key = "display" . $element;
            if (
                isset($attributes[$option_key]) &&
                $attributes[$option_key] === false
            ) {
                $classes[] = "no-" . strtolower($element);
            }
        }

        $aria_label = sprintf(
            __("BlueSky Social Card of %s", "social-integration-for-bluesky"),
            $profile["displayName"] ?? '',
        );

        ob_start();
        do_action("bluesky_before_profile_card_markup", $profile);

        // Render stale indicator if cache is stale
        if (!$is_fresh && null !== $cache_timestamp) {
            $time_ago = BlueSky_Helpers::time_ago($cache_timestamp);
            include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/stale-indicator.php';
        }

        include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/profile-banner-compact.php';

        do_action("bluesky_after_profile_card_markup", $profile);
        return ob_get_clean();
    }

    /**
     * Render the BlueSky profile card
     * @param array $attributes Shortcode attributes
     * @return string HTML output
     */
    public function render_bluesky_profile_card($attributes = [])
    {
        // Extract account_id for cache scoping
        $account_id = $attributes["account_id"] ?? "";

        // Cache-first: check transient before making any API call
        $helpers = new BlueSky_Helpers();
        $profile_cache_key = $helpers->get_profile_transient_key($account_id);
        $cached_profile = get_transient($profile_cache_key);
        $is_fresh = BlueSky_Helpers::is_cache_fresh($profile_cache_key);
        $cache_timestamp = null;

        if ($cached_profile !== false) {
            // Fast path: use cached profile
            $profile = $cached_profile;

            // If cache is stale, schedule background refresh
            if (!$is_fresh) {
                BlueSky_Helpers::schedule_cache_refresh($profile_cache_key, $account_id, []);
                // Get cache timestamp from freshness marker
                $freshness_key = $profile_cache_key . '_fresh';
                $cache_timestamp = get_transient($freshness_key);
                if (false === $cache_timestamp) {
                    // Freshness marker expired - estimate from transient expiry
                    $cache_timestamp = time() - 600; // Assume 10 minutes old
                }
            }
        } elseif ((!defined('DOING_AJAX') || !DOING_AJAX) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
            // No cache and not AJAX/REST: return skeleton placeholder
            $classes_arr = [
                "bluesky-social-integration-profile-card",
                $attributes["styleClass"] ?? "",
            ];
            if (isset($attributes["theme"])) {
                $classes_arr[] = "theme-" . esc_attr($attributes["theme"]);
            }
            $params = wp_json_encode([
                "theme" => $attributes["theme"] ?? ($this->options["theme"] ?? "system"),
                "styleClass" => $attributes["styleClass"] ?? "",
                "displaybanner" => $attributes["displaybanner"] ?? true,
                "displayavatar" => $attributes["displayavatar"] ?? true,
                "displaycounters" => $attributes["displaycounters"] ?? true,
                "displaybio" => $attributes["displaybio"] ?? true,
                "account_id" => $account_id,
                "layout" => !empty($attributes["layout"]) ? $attributes["layout"] : ($this->options["styles"]["profile_layout"] ?? "default"),
            ]);
            return $this->render_profile_skeleton(implode(" ", $classes_arr), $params);
        } else {
            // AJAX request: fetch fresh data
            $profile = $this->api_handler->get_bluesky_profile();
        }

        if (!$profile) {
            return '<p class="bluesky-social-integration-error">' .
                esc_html__(
                    "Unable to fetch BlueSky profile.",
                    "social-integration-for-bluesky",
                ) .
                "</p>";
        }

        $layout = !empty($attributes["layout"]) ? $attributes["layout"] : ($this->options["styles"]["profile_layout"] ?? "default");

        // Compact layout uses a different template
        if ($layout === "compact") {
            return $this->render_profile_compact($profile, $attributes, $is_fresh, $cache_timestamp);
        }

        $classes = [
            "bluesky-social-integration-profile-card",
            $attributes["styleClass"] ?? "",
        ];

        if (isset($attributes["theme"])) {
            $classes[] = "theme-" . esc_attr($attributes["theme"]);
        }

        $display_elements = ["banner", "avatar", "counters", "bio"];
        foreach ($display_elements as $element) {
            $option_key = "display" . $element;
            if (
                isset($attributes[$option_key]) &&
                $attributes[$option_key] === false
            ) {
                $classes[] = "no-" . strtolower($element);
            }
        }

        $aria_label =
            is_array(
                apply_filters(
                    "bluesky_profile_card_classes",
                    $classes,
                    $profile,
                ),
            ) ?? $classes;

        // translators: %s is the profile display used in an aria-label attribute
        $aria_label = sprintf(
            __("BlueSky Social Card of %s", "social-integration-for-bluesky"),
            $profile["displayName"],
        );
        $aria_label = apply_filters(
            "bluesky_profile_card_aria_label",
            $aria_label,
            $profile,
        );

        // Render template
        ob_start();
        do_action("bluesky_before_profile_card_markup", $profile);
        add_action("wp_head", [$this, "render_inline_custom_styles_profile"]);
        include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/profile-card.php';

        // Render stale indicator if cache is stale
        if (!$is_fresh && null !== $cache_timestamp) {
            $time_ago = BlueSky_Helpers::time_ago($cache_timestamp);
            include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/stale-indicator.php';
        }

        do_action("bluesky_after_profile_card_markup", $profile);
        return ob_get_clean();
    }

    /**
     * Generate the inline style for each custom one saved in the plugin options.
     * The output will vary depending if you are in the admin or the front-end of the website.
     * - One <style> + selector + custom prop CSS per customisation in the admin (for JS use)
     * - One <style> mixing all the custom props in one selector for the front-end.
     *
     * @param string $type "posts" or "profile" if targetting specific styles, or "all" for all.
     * @return string|void The <style> elements usually displayed between <head> tags. Or nothing if $options[customisation]'s missing
     */
    public function get_inline_custom_styles($type = "all")
    {
        $options = $this->options;

        if (
            !isset($options["customisation"]) ||
            !is_array($options["customisation"])
        ) {
            return;
        }

        $output =
            "\n" . "<!-- Added by Social Integration for Bluesky -->" . "\n";
        $custom = $options["customisation"];

        // The piece of code for Profile Card Customisation
        if (
            isset($custom["profile"]) &&
            is_array($custom["profile"]) &&
            ($type === "all" || $type === "profile")
        ) {
            $output .= !is_admin()
                ? '<style id="bluesky-profile-custom-styles">' . "\n"
                : "";
            $output .= !is_admin()
                ? ".bluesky-social-integration-profile-card {" . "\n"
                : "";

            foreach ($custom["profile"] as $element => $props) {
                if (is_array($props)) {
                    foreach ($props as $k => $prop) {
                        $custom_prop =
                            "--bluesky-profile-custom-" . $element . "-" . $k;
                        $output .= is_admin()
                            ? '<style id="bluesky' .
                                esc_attr($custom_prop) .
                                '">' .
                                "\n"
                            : "";
                        $output .= is_admin()
                            ? ".bluesky-social-integration-profile-card {" .
                                "\n"
                            : "";
                        $output .=
                            "\t" .
                            esc_attr($custom_prop) .
                            ": " .
                            intval($prop["value"]) .
                            "px!important;" .
                            "\n";
                        $output .= is_admin() ? "}" . "\n" : "";
                        $output .= is_admin() ? "</style>" . "\n" : "";
                    }
                }
            }

            $output .= !is_admin() ? "}" . "\n" : "";
            $output .= !is_admin() ? "</style>" . "\n" : "";
        }

        // The piece of code for Feed Customisation
        if (
            isset($custom["posts"]) &&
            is_array($custom["posts"]) &&
            ($type === "all" || $type === "posts")
        ) {
            $output .= !is_admin()
                ? '<style id="bluesky-posts-custom-styles">' . "\n"
                : "";
            $output .= !is_admin()
                ? ".bluesky-social-integration-last-post {" . "\n"
                : "";

            foreach ($custom["posts"] as $element => $props) {
                if (is_array($props)) {
                    foreach ($props as $k => $prop) {
                        $custom_prop =
                            "--bluesky-posts-custom-" . $element . "-" . $k;
                        $output .= is_admin()
                            ? '<style id="bluesky' .
                                esc_attr($custom_prop) .
                                '">' .
                                "\n"
                            : "";
                        $output .= is_admin()
                            ? ".bluesky-social-integration-last-post {" . "\n"
                            : "";
                        $output .=
                            "\t" .
                            esc_attr($custom_prop) .
                            ": " .
                            intval($prop["value"]) .
                            "px!important;" .
                            "\n";
                        $output .= is_admin() ? "}" . "\n" : "";
                        $output .= is_admin() ? "</style>" . "\n" : "";
                    }
                }
            }

            $output .= !is_admin() ? "}" . "\n" : "";
            $output .= !is_admin() ? "</style>" . "\n" : "";
        }

        $output .= "<!-- END OF Social Integration for Bluesky -->" . "\n";

        return $output;
    }

    /**
     * Simply print the output of get_inline_custom_styles() method.
     *
     * @return string|void
     */
    public function render_inline_custom_styles()
    {
        echo $this->get_inline_custom_styles();
    }

    /**
     * Print the output of get_inline_custom_styles() only for posts
     *
     * @return string|void
     */
    public function render_inline_custom_styles_posts()
    {
        echo $this->get_inline_custom_styles("posts");
    }

    /**
     * Print the output of get_inline_custom_styles() only for profile
     *
     * @return string|void
     */
    public function render_inline_custom_styles_profile()
    {
        echo $this->get_inline_custom_styles("profile");
    }

    /**
     * Render a skeleton placeholder for the posts feed
     * @param string $theme Theme class
     * @param string $layout Layout class
     * @param string $params JSON-encoded render parameters
     * @return string HTML skeleton
     */
    private function render_posts_skeleton($theme, $layout, $params)
    {
        $classes = " theme-" . esc_attr($theme);
        $classes .= " display-" . esc_attr($layout);

        ob_start();
        ?>
        <aside class="bluesky-social-integration-last-post<?php echo esc_attr($classes); ?> bluesky-async-placeholder" data-bluesky-async="posts" data-bluesky-params="<?php echo esc_attr($params); ?>" aria-label="<?php esc_attr_e("Loading Bluesky Posts", "social-integration-for-bluesky"); ?>">
            <ul class="bluesky-social-integration-last-post-list">
                <?php for ($i = 0; $i < 3; $i++): ?>
                <li class="bluesky-social-integration-last-post-item">
                    <div class="bluesky-social-integration-last-post-header">
                        <span class="bluesky-skeleton-box" style="width:42px;height:42px;border-radius:50%;display:inline-block;"></span>
                    </div>
                    <div class="bluesky-social-integration-last-post-content">
                        <p class="bluesky-social-integration-post-account-info-names">
                            <span class="bluesky-skeleton-box" style="width:120px;height:1em;display:inline-block;"></span>
                            <span class="bluesky-skeleton-box" style="width:80px;height:1em;display:inline-block;"></span>
                        </p>
                        <div class="bluesky-social-integration-post-content-text">
                            <span class="bluesky-skeleton-box" style="width:100%;height:1em;display:block;margin-bottom:0.4em;"></span>
                            <span class="bluesky-skeleton-box" style="width:75%;height:1em;display:block;"></span>
                        </div>
                    </div>
                </li>
                <?php endfor; ?>
            </ul>
        </aside>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a skeleton placeholder for the profile card
     * @param string $classes CSS class string
     * @param string $params JSON-encoded render parameters
     * @return string HTML skeleton
     */
    private function render_profile_skeleton($classes, $params)
    {
        ob_start();
        ?>
        <aside class="<?php echo esc_attr($classes); ?> bluesky-async-placeholder" data-bluesky-async="profile" data-bluesky-params="<?php echo esc_attr($params); ?>" aria-label="<?php esc_attr_e("Loading Bluesky Profile", "social-integration-for-bluesky"); ?>">
            <div class="bluesky-social-integration-image">
                <span class="bluesky-skeleton-box" style="width:80px;height:80px;border-radius:50%;display:inline-block;"></span>
            </div>
            <div class="bluesky-social-integration-content">
                <p class="bluesky-social-integration-name"><span class="bluesky-skeleton-box" style="width:150px;height:1.2em;display:inline-block;"></span></p>
                <p class="bluesky-social-integration-handle"><span class="bluesky-skeleton-box" style="width:120px;height:1em;display:inline-block;"></span></p>
                <p class="bluesky-social-integration-followers">
                    <span class="bluesky-skeleton-box" style="width:200px;height:1em;display:inline-block;"></span>
                </p>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }

}
