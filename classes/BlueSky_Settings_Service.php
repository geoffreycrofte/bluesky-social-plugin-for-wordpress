<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Settings_Service
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
     * Helpers instance
     * @var BlueSky_Helpers
     */
    private $helpers;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     * @param BlueSky_Account_Manager $account_manager Account manager instance
     */
    public function __construct(BlueSky_API_Handler $api_handler, BlueSky_Account_Manager $account_manager = null)
    {
        $this->api_handler = $api_handler;
        $this->account_manager = $account_manager;
        $this->helpers = new BlueSky_Helpers();
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    }

    /**
     * Add a link to the setting page
     */
    public function add_plugin_action_links(array $links)
    {
        $url = $this->helpers->get_the_admin_plugin_url();
        $settings_link =
            '<a href="' .
            esc_url($url) .
            '">' .
            esc_html__("Settings", "social-integration-for-bluesky") .
            "</a>";
        $links[] = $settings_link;
        return $links;
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu()
    {
        add_options_page(
            esc_html__(
                "Social Integration for BlueSky",
                "social-integration-for-bluesky",
            ),
            esc_html__("BlueSky Settings", "social-integration-for-bluesky"),
            "manage_options",
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            [$this, "render_settings_page"],
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        $submit_button =
            '<p class="submit"><button type="submit" class="button button-large button-primary">' .
            __("Save Changes") .
            "</button></p>";

        register_setting("bluesky_settings_group", BLUESKY_PLUGIN_OPTIONS, [
            "sanitize_callback" => [$this, "sanitize_settings"],
        ]);

        add_settings_section(
            "bluesky_main_settings",
            esc_html__(
                "BlueSky Account Settings",
                "social-integration-for-bluesky",
            ),
            [$this, "settings_section_callback"],
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            [
                "before_section" =>
                    '<div id="account" aria-hidden="false" class="bluesky-social-integration-admin-content">',
                //I tried get_submit_button() but it couldn't work for some reasons.
                "after_section" => $submit_button . "\n\n" . "</div>",
                "section_class" => "bluesky-main-settings",
            ],
        );

        add_settings_section(
            "bluesky_customization_settings",
            esc_html__(
                "BlueSky Customization Settings",
                "social-integration-for-bluesky",
            ),
            [$this, "customization_section_callback"],
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            [
                "before_section" =>
                    '<div id="customization" aria-hidden="false" class="bluesky-social-integration-admin-content">',
                "after_section" => $submit_button . "\n\n" . "</div>",
                "section_class" => "bluesky-customization-settings",
            ],
        );

        add_settings_section(
            "bluesky_discussions_settings",
            esc_html__(
                "BlueSky Discussions Settings",
                "social-integration-for-bluesky",
            ),
            [$this, "discussions_section_callback"],
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            [
                "before_section" =>
                    '<div id="discussions" aria-hidden="false" class="bluesky-social-integration-admin-content">',
                "after_section" => $submit_button . "\n\n" . "</div>",
                "section_class" => "bluesky-discussions-settings",
            ],
        );

        $this->add_settings_fields();
    }

    /**
     * Sanitize settings before saving
     * @param array $input The settings array to sanitize
     * @return array Sanitized settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = [];
        $helpers = $this->helpers;
        $current_options = $this->options;

        // Handle encryption for secret key
        $secret_key = get_option(BLUESKY_PLUGIN_OPTIONS . "_secret");
        if (empty($secret_key) || $secret_key === false) {
            add_option(
                BLUESKY_PLUGIN_OPTIONS . "_secret",
                bin2hex(random_bytes(32)),
            );
        }

        // Handle encryption for password
        if (isset($input["app_password"]) && !empty($input["app_password"])) {
            $sanitized["app_password"] = $helpers->bluesky_encrypt(
                $input["app_password"],
            );
        } else {
            $sanitized["app_password"] = $this->options["app_password"] ?? "";
        }

        // Sanitize de customization values (support int value only at the moment)
        $sanitized["customisation"] = $helpers->sanitize_int_recursive(
            $input["customisation"],
        );

        // Sanitize other fields
        $sanitized["handle"] = isset($input["handle"])
            ? BlueSky_Helpers::normalize_handle(sanitize_text_field($input["handle"]))
            : "";
        $sanitized["enable_multi_account"] = !empty($input["enable_multi_account"]);
        $sanitized["auto_syndicate"] = isset($input["auto_syndicate"]) ? 1 : 0;
        $sanitized["no_replies"] = isset($input["no_replies"]) ? 1 : 0;
        $sanitized["no_embeds"] = isset($input["no_embeds"]) ? 1 : 0;
        $sanitized["no_reposts"] = isset($input["no_reposts"]) ? 1 : 0;
        $sanitized["no_counters"] = isset($input["no_counters"]) ? 1 : 0;
        $sanitized["theme"] = isset($input["theme"])
            ? sanitize_text_field($input["theme"])
            : "system";
        $sanitized["posts_limit"] = isset($input["posts_limit"])
            ? min(10, max(1, intval($input["posts_limit"])))
            : 5;

        $minutes = isset($input["cache_duration"]["minutes"])
            ? absint($input["cache_duration"]["minutes"])
            : 0;
        $hours = isset($input["cache_duration"]["hours"])
            ? absint($input["cache_duration"]["hours"])
            : 0;
        $days = isset($input["cache_duration"]["days"])
            ? absint($input["cache_duration"]["days"])
            : 0;

        $sanitized["cache_duration"] = [
            "minutes" => $minutes,
            "hours" => $hours,
            "days" => $days,
            "total_seconds" => $minutes * 60 + $hours * 3600 + $days * 86400,
        ];

        // Sanitize Discussions settings
        $sanitized["discussions"]["enable"] = isset(
            $input["discussions"]["enable"],
        )
            ? 1
            : 0;
        $sanitized["discussions"]["show_nested"] = isset(
            $input["discussions"]["show_nested"],
        )
            ? 1
            : 0;
        $sanitized["discussions"]["nested_collapsed"] = isset(
            $input["discussions"]["nested_collapsed"],
        )
            ? 1
            : 0;
        $sanitized["discussions"]["show_stats"] = isset(
            $input["discussions"]["show_stats"],
        )
            ? 1
            : 0;
        $sanitized["discussions"]["show_reply_link"] = isset(
            $input["discussions"]["show_reply_link"],
        )
            ? 1
            : 0;
        $sanitized["discussions"]["show_media"] = isset(
            $input["discussions"]["show_media"],
        )
            ? 1
            : 0;

        // Check if discussion settings changed - clear cache if they did
        $discussion_settings_changed = false;
        if (isset($current_options["discussions"])) {
            foreach ($sanitized["discussions"] as $key => $value) {
                if (
                    !isset($current_options["discussions"][$key]) ||
                    $current_options["discussions"][$key] !== $value
                ) {
                    $discussion_settings_changed = true;
                    break;
                }
            }
        } else {
            $discussion_settings_changed = true;
        }

        // Clear discussion caches if settings changed
        if ($discussion_settings_changed) {
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_bluesky_discussion_%'
                 OR option_name LIKE '_transient_timeout_bluesky_discussion_%'
                 OR option_name LIKE '_transient_bluesky_frontend_discussion_%'
                 OR option_name LIKE '_transient_timeout_bluesky_frontend_discussion_%'",
            );
        }

        // Clear content caches if the account handle changed
        $old_handle = $current_options["handle"] ?? "";
        $new_handle = $sanitized["handle"] ?? "";
        if ($old_handle !== $new_handle) {
            $this->clear_content_transients();
        }

        // Sanitize Layouts
        $sanitized["styles"]["feed_layout"] =
            isset($input["styles"]["feed_layout"]) &&
            in_array($input["styles"]["feed_layout"], ["default", "layout_2"])
                ? esc_attr($input["styles"]["feed_layout"])
                : "default";

        // If we go from any layout to 'layout_2' then make some custom setup.
        if (
            isset($current_options["styles"]["feed_layout"]) &&
            $current_options["styles"]["feed_layout"] !== "layout_2" &&
            $sanitized["styles"]["feed_layout"] === "layout_2"
        ) {
            $sanitized["no_replies"] = 1;
            $sanitized["no_embeds"] = 1;
            $sanitized["no_reposts"] = 1;
            $sanitized["no_counters"] = 0;
        }

        // Process new accounts added via the repeater
        if (!empty($input['new_accounts']) && is_array($input['new_accounts']) && $this->account_manager) {
            foreach ($input['new_accounts'] as $new_account) {
                $handle = BlueSky_Helpers::normalize_handle(sanitize_text_field($new_account['handle'] ?? ''));
                $app_password = $new_account['app_password'] ?? '';
                $name = sanitize_text_field($new_account['name'] ?? '');

                if (empty($handle) || empty($app_password)) {
                    continue; // Skip incomplete entries
                }

                $account_data = [
                    'name' => !empty($name) ? $name : $handle,
                    'handle' => $handle,
                    'app_password' => $app_password, // add_account() encrypts
                    'auto_syndicate' => true,
                    'owner_id' => get_current_user_id()
                ];

                $account_id = $this->account_manager->add_account($account_data);

                if (is_wp_error($account_id)) {
                    add_settings_error(
                        'bluesky_messages',
                        'bluesky_account_error',
                        $account_id->get_error_message(),
                        'error'
                    );
                    continue;
                }

                // Test authentication with a direct API call (avoid transient interference)
                $auth_response = wp_remote_post(
                    'https://bsky.social/xrpc/com.atproto.server.createSession',
                    [
                        'timeout' => 15,
                        'body' => wp_json_encode([
                            'identifier' => $handle,
                            'password' => $app_password,
                        ]),
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                    ]
                );

                $auth_body = !is_wp_error($auth_response)
                    ? json_decode(wp_remote_retrieve_body($auth_response), true)
                    : null;

                if ($auth_body && isset($auth_body['did'])) {
                    $this->account_manager->update_account($account_id, ['did' => $auth_body['did']]);
                    add_settings_error(
                        'bluesky_messages',
                        'bluesky_account_success_' . $account_id,
                        sprintf(__('Account "%s" added and authenticated.', 'social-integration-for-bluesky'), $handle),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'bluesky_messages',
                        'bluesky_account_warning_' . $account_id,
                        sprintf(__('Account "%s" added but could not authenticate. Check credentials.', 'social-integration-for-bluesky'), $handle),
                        'warning'
                    );
                }
            }
        }

        // Check if activation date exists (plugin activation before v1.3.0 wouldn't have it)
        if (!get_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date")) {
            add_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date", time());
        }

        return $sanitized;
    }

    /**
     * Add individual settings fields
     */
    private function add_settings_fields()
    {
        $fields = [
            "bluesky_handle" => [
                "label" => __(
                    "BlueSky Handle",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_handle_field",
                "section" => "bluesky_main_settings",
            ],
            "bluesky_app_password" => [
                "label" => __(
                    "BlueSky Password",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_password_field",
                "section" => "bluesky_main_settings",
            ],
            "bluesky_enable_multi_account" => [
                "label" => __(
                    "Multi-Account Support",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_multi_account_toggle",
                "section" => "bluesky_main_settings",
            ],
            "bluesky_auto_syndicate" => [
                "label" => __(
                    "Auto-Syndicate Posts",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_syndicate_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_theme" => [
                "label" => __("Theme", "social-integration-for-bluesky"),
                "callback" => "render_theme_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_posts_limit" => [
                "label" => __(
                    "Number of Posts to Display",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_posts_limit_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_no_replies" => [
                "label" => __(
                    "Do not display replies",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_no_replies_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_no_reposts" => [
                "label" => __(
                    "Do not display reposts",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_no_reposts_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_no_embeds" => [
                "label" => __(
                    "Do not display embeds",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_no_embeds_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_no_counters" => [
                "label" => __(
                    "Do not display counters",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_no_counters_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_cache_duration" => [
                "label" => __(
                    "Cache Duration",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_cache_duration_field",
                "section" => "bluesky_customization_settings",
            ],
            "bluesky_enable_discussions" => [
                "label" => __(
                    "Enable Bluesky Discussions",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_enable_discussions_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussion_account" => [
                "label" => __(
                    "Discussion Thread Source",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussion_account_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussions_show_nested" => [
                "label" => __(
                    "Display Multiple Levels of Replies",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussions_show_nested_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussions_nested_collapsed" => [
                "label" => __(
                    "Collapse Nested Replies by Default",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussions_nested_collapsed_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussions_show_stats" => [
                "label" => __(
                    "Display Like and Share Counters",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussions_show_stats_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussions_show_reply_link" => [
                "label" => __(
                    "Display Reply Links",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussions_show_reply_link_field",
                "section" => "bluesky_discussions_settings",
            ],
            "bluesky_discussions_show_media" => [
                "label" => __(
                    "Display Images and Attachments",
                    "social-integration-for-bluesky",
                ),
                "callback" => "render_discussions_show_media_field",
                "section" => "bluesky_discussions_settings",
            ],
        ];

        foreach ($fields as $id => $field) {
            add_settings_field(
                $id,
                '<label for="' .
                    esc_attr(
                        BLUESKY_PLUGIN_OPTIONS .
                            "_" .
                            str_replace("bluesky_", "", $id),
                    ) .
                    '">' .
                    esc_html($field["label"]) .
                    "</label>",
                [$this, $field["callback"]],
                BLUESKY_PLUGIN_SETTING_PAGENAME,
                $field["section"],
            );
        }
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback()
    {
        echo "<p>" .
            esc_html(
                __(
                    "Enter your BlueSky account details to enable social integration.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</p>";
    }

    /**
     * Customization section callback
     */
    public function customization_section_callback()
    {
        echo "<p>" .
            esc_html(
                __(
                    "Start customizing how your feed and profile card are displayed.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</p>";
    }

    /**
     * Discussions section callback
     */
    public function discussions_section_callback()
    {
        echo "<p>" .
            esc_html(
                __(
                    "Display Bluesky discussions on your posts. This feature only works for posts that have been syndicated to Bluesky.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</p>";
    }

    /**
     * Render handle field
     */
    public function render_handle_field()
    {
        $handle = $this->options["handle"] ?? "";
        echo '<input type="text" id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_handle") .
            '" name="bluesky_settings[handle]" value="' .
            esc_attr($handle) .
            '" aria-describedby="bluesky-handle-description" />';
        echo '<p class="description" id="bluesky-handle-description">' .
            esc_html(
                __(
                    "Your e-mail address or Bluesky handle (e.g. user.bsky.social).",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</p>";
    }

    /**
     * Render password field
     * If a password is already set, show a placeholder instead of the actual password
     */
    public function render_password_field()
    {
        $password = $this->options["app_password"] ?? "";
        $login = $this->options["handle"] ?? "";

        // Don't show the actual password, just a placeholder if it exists
        $placeholder = !empty($password) && !empty($login) ? "••••••••" : "";

        echo '<input type="password" id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_app_password") .
            '" name="bluesky_settings[app_password]" value="" placeholder="' .
            esc_attr($placeholder) .
            '" aria-describedby="bluesky-password-description" />';

        if (!empty($password) && !empty($login)) {
            echo '<p class="description" id="bluesky-password-description">' .
                esc_html(
                    __(
                        "Leave empty to keep the current password.",
                        "social-integration-for-bluesky",
                    ),
                ) .
                "</p>";
        } else {
            echo '<p class="description" id="bluesky-password-description">' .
                wp_kses_post(
                    sprintf(
                        // translators: %1$s opening link tag, %2$s the closing link tag, %3$s new line insertion
                        __(
                            'Instead of using your password, you can use an %1$sApp Password%2$s available on BlueSky.%3$sNo need to authorize access to your direct messages, this plugin does not need it.',
                            "social-integration-for-bluesky",
                        ),
                        '<a href="https://bsky.app/settings/app-passwords" target="_blank">',
                        "</a>",
                        "<br>",
                    ),
                ) .
                "</p>";
        }

        // Connection check via AJAX — no blocking API call during page render
        if (!empty($password) && !empty($login)) { ?>
            <div aria-live="polite" aria-atomic="true" id="bluesky-connection-test" class="description bluesky-connection-check bluesky-async-placeholder" data-bluesky-async="auth" data-bluesky-logout-url="<?php echo esc_url(
                admin_url(
                    "admin-post.php?action=bluesky_logout&nonce=" .
                        wp_create_nonce("bluesky_logout_nonce"),
                ),
            ); ?>">
                <p>
                    <?php esc_html_e(
                        "Checking connection...",
                        "social-integration-for-bluesky",
                    ); ?>
                </p>
            </div>
        <?php }
    }

    /**
     * Render multi-account toggle and management section
     */
    public function render_multi_account_toggle()
    {
        if (!$this->account_manager) {
            echo '<p class="description">' . esc_html__('Account Manager not available.', 'social-integration-for-bluesky') . '</p>';
            return;
        }

        $multi_account_enabled = $this->account_manager->is_multi_account_enabled();

        echo '<label>';
        echo '<input type="checkbox" id="bluesky-enable-multi-account" name="bluesky_settings[enable_multi_account]" value="1" ' .
             checked(true, $multi_account_enabled, false) . ' />';
        echo ' ' . esc_html__('Enable Multi-Account Support', 'social-integration-for-bluesky');
        echo '</label>';
        echo '<p class="description">' .
             esc_html__('Connect multiple Bluesky accounts for syndication and display switching.', 'social-integration-for-bluesky') .
             '</p>';

        // Multi-account section (shown/hidden by JavaScript)
        echo '<div id="bluesky-multi-account-section" style="display:none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';

        echo '<h3>' . esc_html__('Connected Accounts', 'social-integration-for-bluesky') . '</h3>';
        echo '<p class="description">' .
             esc_html__('Active account is the default account used for widgets, shortcodes & blocks if no other account is specified.', 'social-integration-for-bluesky') .
             '</p>';

        // Get accounts
        $accounts = $this->account_manager->get_accounts();
        $active_account = $this->account_manager->get_active_account();
        $active_account_id = $active_account['id'] ?? '';

        if (!empty($accounts)) {
            echo '<table class="wp-list-table widefat fixed striped bluesky-account-list" style="margin-bottom: 20px;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Name', 'social-integration-for-bluesky') . '</th>';
            echo '<th>' . esc_html__('Handle', 'social-integration-for-bluesky') . '</th>';
            echo '<th class="column-status">' . esc_html__('Status', 'social-integration-for-bluesky') . '</th>';
            echo '<th class="column-auto-syndicate">' . esc_html__('Auto-Syndicate', 'social-integration-for-bluesky') . '</th>';
            echo '<th class="column-actions">' . esc_html__('Actions', 'social-integration-for-bluesky') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($accounts as $account_key => $account) {
                // Defensive: use array key as fallback if 'id' is missing
                $account_id = $account['id'] ?? $account_key;
                $is_active = ($account_id === $active_account_id);
                $row_class = $is_active ? 'bluesky-account-active' : '';

                echo '<tr class="' . esc_attr($row_class) . '">';

                // Name
                echo '<td>';
                echo esc_html($account['name'] ?? __('Unknown', 'social-integration-for-bluesky'));
                if ($is_active) {
                    echo ' <span class="bluesky-active-indicator">' . esc_html__('Active', 'social-integration-for-bluesky') . '</span>';
                }
                echo '</td>';

                // Handle
                echo '<td>' . esc_html($account['handle'] ?? '') . '</td>';

                // Status - check if authenticated
                echo '<td>';
                if (!empty($account['did'])) {
                    echo '<span class="bluesky-status-badge bluesky-status-authenticated">';
                    echo '<span class="dashicons dashicons-yes-alt"></span> ';
                    echo esc_html__('Authenticated', 'social-integration-for-bluesky');
                    echo '</span>';
                } elseif (!empty($account['app_password'])) {
                    // Has credentials but no DID yet (e.g., just migrated) — not an error
                    echo '<span class="bluesky-status-badge bluesky-status-pending">';
                    echo '<span class="dashicons dashicons-clock"></span> ';
                    echo esc_html__('Credentials Configured', 'social-integration-for-bluesky');
                    echo '</span>';
                } else {
                    echo '<span class="bluesky-status-badge bluesky-status-error">';
                    echo '<span class="dashicons dashicons-warning"></span> ';
                    echo esc_html__('Not Authenticated', 'social-integration-for-bluesky');
                    echo '</span>';
                }
                echo '</td>';

                // Auto-syndicate toggle
                echo '<td style="text-align: center;">';
                echo '<div class="bluesky-account-action" style="display: inline;">';
                wp_nonce_field('bluesky_toggle_auto_syndicate_' . $account_id, '_bluesky_toggle_nonce_' . esc_attr($account_id), true, true);
                echo '<input type="hidden" name="account_id" value="' . esc_attr($account_id) . '" />';
                echo '<input type="checkbox" name="auto_syndicate" value="1" class="bluesky-auto-syndicate-toggle" ' .
                     checked(true, $account['auto_syndicate'] ?? false, false) .
                     ' />';
                echo '<input type="hidden" name="bluesky_toggle_auto_syndicate" value="1" />';
                echo '</div>';
                echo '</td>';

                // Actions
                echo '<td>';

                // Make Active button
                if (!$is_active) {
                    echo '<div class="bluesky-account-action" style="display: inline; margin-right: 8px;">';
                    wp_nonce_field('bluesky_switch_account_' . $account_id, '_bluesky_switch_nonce_' . esc_attr($account_id), true, true);
                    echo '<input type="hidden" name="account_id" value="' . esc_attr($account_id) . '" />';
                    echo '<button type="button" name="bluesky_switch_account" class="button button-small bluesky-action-btn">' .
                         esc_html__('Make Active', 'social-integration-for-bluesky') .
                         '</button>';
                    echo '</div>';
                }

                // Remove button
                echo '<div class="bluesky-account-action" style="display: inline;">';
                wp_nonce_field('bluesky_remove_account_' . $account_id, '_bluesky_remove_nonce_' . esc_attr($account_id), true, true);
                echo '<input type="hidden" name="account_id" value="' . esc_attr($account_id) . '" />';
                echo '<button type="button" name="bluesky_remove_account" class="button button-small bluesky-remove-account-btn bluesky-action-btn">' .
                     esc_html__('Remove', 'social-integration-for-bluesky') .
                     '</button>';
                echo '</div>';

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . esc_html__('No accounts connected yet.', 'social-integration-for-bluesky') . '</p>';
        }

        // Add New Account — JS repeater (saved with "Save Changes")
        echo '<h3>' . esc_html__('Add New Account', 'social-integration-for-bluesky') . '</h3>';
        echo '<div id="bluesky-new-accounts-list"></div>';

        // Hidden template for JS cloning
        echo '<template id="bluesky-new-account-template">';
        echo '<div class="bluesky-new-account-row" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px;">';
        echo '<table class="form-table" style="margin: 0;">';
        echo '<tr>';
        echo '<th scope="row"><label for="bluesky_settings_new_accounts-__INDEX__-name">' . esc_html__('Account Name', 'social-integration-for-bluesky') . '</label></th>';
        echo '<td><input type="text" id="bluesky_settings_new_accounts-__INDEX__-name" name="bluesky_settings[new_accounts][__INDEX__][name]" class="regular-text" placeholder="' .
             esc_attr__('e.g., Personal Account', 'social-integration-for-bluesky') . '" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="bluesky_settings_new_accounts-__INDEX__-handle">' . esc_html__('Bluesky Handle', 'social-integration-for-bluesky') . ' *</label></th>';
        echo '<td><input type="text" id="bluesky_settings_new_accounts-__INDEX__-handle" name="bluesky_settings[new_accounts][__INDEX__][handle]" class="regular-text" placeholder="user.bsky.social" aria-describedby="bluesky-new-handle-desc-__INDEX__" />';
        echo '<p class="description" id="bluesky-new-handle-desc-__INDEX__">' .
             esc_html__('Your e-mail address or Bluesky handle (e.g. user.bsky.social).', 'social-integration-for-bluesky') .
             '</p></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="bluesky_settings_new_accounts-__INDEX__-app_password">' . esc_html__('App Password', 'social-integration-for-bluesky') . ' *</label></th>';
        echo '<td>';
        echo '<input type="password" id="bluesky_settings_new_accounts-__INDEX__-app_password" name="bluesky_settings[new_accounts][__INDEX__][app_password]" class="regular-text" />';
        echo '<p class="description">' .
             wp_kses_post(sprintf(
                 __('Create an %1$sApp Password%2$s on Bluesky.', 'social-integration-for-bluesky'),
                 '<a href="https://bsky.app/settings/app-passwords" target="_blank">',
                 '</a>'
             )) .
             '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '<button type="button" class="button bluesky-remove-new-account" style="margin-top: 8px;">' .
             esc_html__('Cancel', 'social-integration-for-bluesky') .
             '</button>';
        echo '</div>';
        echo '</template>';

        echo '<button type="button" id="bluesky-add-account-btn" class="button button-primary">' .
             esc_html__('Add Account', 'social-integration-for-bluesky') .
             '</button>';
        echo '<p class="description" id="bluesky-add-account-hint" style="display:none; margin-top: 8px;">' .
             esc_html__('Click "Save Changes" to save the new account.', 'social-integration-for-bluesky') .
             '</p>';

        echo '</div>'; // End multi-account section
    }

    /**
     * Render auto-syndicate field
     */
    public function render_syndicate_field()
    {
        $auto_syndicate = $this->options["auto_syndicate"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_auto_syndicate") .
            '" type="checkbox" name="bluesky_settings[auto_syndicate]" value="1" ' .
            checked(1, $auto_syndicate, false) .
            ' aria-describedby="bluesky-auto-syndicate-desc" />';

        echo '<span class="description bluesky-description" id="bluesky-auto-syndicate-desc">' .
            esc_html(
                __(
                    "Automatically syndicate new posts to BlueSky. You can change this behaviour post by post while editing it.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render theme field
     */
    public function render_theme_field()
    {
        $theme = $this->options["theme"] ?? "system";
        echo '<select name="bluesky_settings[theme]" id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_theme") .
            '">';
        echo '<option value="system" ' .
            selected("system", $theme, false) .
            ">" .
            esc_html(
                __("System Preference", "social-integration-for-bluesky"),
            ) .
            "</option>";
        echo '<option value="light" ' .
            selected("light", $theme, false) .
            ">" .
            esc_html(__("Light", "social-integration-for-bluesky")) .
            "</option>";
        echo '<option value="dark" ' .
            selected("dark", $theme, false) .
            ">" .
            esc_html(__("Dark", "social-integration-for-bluesky")) .
            "</option>";
        echo "</select>";
    }

    /**
     * Render posts limit field
     */
    public function render_posts_limit_field()
    {
        $limit = $this->options["posts_limit"] ?? 5;
        echo '<input type="number" min="1" max="10" id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_posts_limit") .
            '" name="bluesky_settings[posts_limit]" value="' .
            esc_attr($limit) .
            '" />';
        echo '<p class="description">' .
            esc_html(
                __(
                    "Enter the number of posts to display (1-10) - 5 is set by default",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</p>";
    }

    /**
     * Render no replies field
     */
    public function render_no_replies_field()
    {
        $no_replies = $this->options["no_replies"] ?? 1;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_no_replies") .
            '" type="checkbox" name="bluesky_settings[no_replies]" value="1" ' .
            checked(1, $no_replies, false) .
            ' aria-describedby="bluesky-no_replies-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_replies-desc">' .
            esc_html(
                __(
                    "If checked, your replies will not be displayed in your feed.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render no repost field
     */
    public function render_no_reposts_field()
    {
        $no_reposts = $this->options["no_reposts"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_no_reposts") .
            '" type="checkbox" name="bluesky_settings[no_reposts]" value="1" ' .
            checked(1, $no_reposts, false) .
            ' aria-describedby="bluesky-no_reposts-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_reposts-desc">' .
            esc_html(
                __(
                    "If checked, the reposts won't be displayed in your feed.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render no embeds field
     */
    public function render_no_embeds_field()
    {
        $no_embeds = $this->options["no_embeds"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_no_embeds") .
            '" type="checkbox" name="bluesky_settings[no_embeds]" value="1" ' .
            checked(1, $no_embeds, false) .
            ' aria-describedby="bluesky-no_embeds-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_embeds-desc">' .
            esc_html(
                __(
                    "If checked, videos, images, and link cards won't be displayed in your feed.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render no counters field
     */
    public function render_no_counters_field()
    {
        $no_counters = $this->options["no_counters"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_no_counters") .
            '" type="checkbox" name="bluesky_settings[no_counters]" value="1" ' .
            checked(1, $no_counters, false) .
            ' aria-describedby="bluesky-no_counters-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_counters-desc">' .
            esc_html(
                __(
                    "If checked, like, repost, reply, and quote counters won't be displayed in your feed.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render cache duration field
     */
    public function render_cache_duration_field()
    {
        $cache_duration = $this->options["cache_duration"] ?? [
            "minutes" => 0,
            "hours" => 1,
            "days" => 0,
        ]; ?>
        <div class="cache-duration-fields">
            <label>
                <input type="number"
                        min="0"
                        id="bluesky_settings_cache_duration"
                        name="bluesky_settings[cache_duration][days]"
                        value="<?php echo esc_attr($cache_duration["days"]); ?>"
                        style="width: 60px;">
                <?php echo esc_html(
                    __("Days", "social-integration-for-bluesky"),
                ); ?>
            </label>
            <label>
                <input type="number"
                        min="0"
                        name="bluesky_settings[cache_duration][hours]"
                        value="<?php echo esc_attr(
                            $cache_duration["hours"],
                        ); ?>"
                        style="width: 60px;">
                <?php echo esc_html(
                    __("Hours", "social-integration-for-bluesky"),
                ); ?>
            </label>
            <label>
                <input type="number"
                        min="0"
                        name="bluesky_settings[cache_duration][minutes]"
                        value="<?php echo esc_attr(
                            $cache_duration["minutes"],
                        ); ?>"
                        style="width: 60px;">
                <?php echo esc_html(
                    __("Minutes", "social-integration-for-bluesky"),
                ); ?>
            </label>
        </div>
        <p class="description">
            <?php echo esc_html(
                __(
                    "Set to 0 in all fields to disable the cache.",
                    "social-integration-for-bluesky",
                ),
            ); ?>
        </p>
        <?php
    }

    /**
     * Render enable discussions field
     */
    public function render_enable_discussions_field()
    {
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_enable_discussions") .
            '" type="checkbox" name="bluesky_settings[discussions][enable]" value="1" ' .
            checked(1, $enable_discussions, false) .
            ' aria-describedby="bluesky-enable_discussions-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-enable_discussions-desc">' .
            esc_html(
                __(
                    "Enable Bluesky discussion display below your syndicated posts.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render discussion account field
     */
    public function render_discussion_account_field()
    {
        if (!$this->account_manager || !$this->account_manager->is_multi_account_enabled()) {
            echo '<p class="description">' .
                 esc_html__('This option is only available when multi-account support is enabled.', 'social-integration-for-bluesky') .
                 '</p>';
            return;
        }

        $accounts = $this->account_manager->get_accounts();
        $discussion_account = $this->account_manager->get_discussion_account();
        $discussion_account_id = $discussion_account['id'] ?? '';

        if (empty($accounts)) {
            echo '<p class="description">' .
                 esc_html__('No accounts available. Please add an account first.', 'social-integration-for-bluesky') .
                 '</p>';
            return;
        }

        echo '<div class="bluesky-account-action" style="display: inline-block;">';
        wp_nonce_field('bluesky_discussion_account', '_bluesky_discussion_nonce', true, true);
        echo '<select name="bluesky_discussion_account" class="bluesky-discussion-account-select">';

        foreach ($accounts as $account) {
            echo '<option value="' . esc_attr($account['id']) . '" ' .
                 selected($account['id'], $discussion_account_id, false) . '>';
            echo esc_html($account['name']) . ' (' . esc_html($account['handle']) . ')';
            echo '</option>';
        }

        echo '</select>';
        echo '<input type="hidden" name="bluesky_set_discussion_account" value="1" />';
        echo '</div>';
        echo '<p class="description">' .
             esc_html__('Choose which account\'s Bluesky thread to display for discussions.', 'social-integration-for-bluesky') .
             '</p>';
    }

    /**
     * Render discussions show nested field
     */
    public function render_discussions_show_nested_field()
    {
        $show_nested = $this->options["discussions"]["show_nested"] ?? 1;
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_discussions_show_nested") .
            '" type="checkbox" name="bluesky_settings[discussions][show_nested]" value="1" ' .
            checked(1, $show_nested, false) .
            ($enable_discussions ? "" : " disabled") .
            ' aria-describedby="bluesky-discussions_show_nested-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-discussions_show_nested-desc">' .
            esc_html(
                __(
                    "Display multiple levels of replies (replies to replies).",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render discussions nested collapsed field
     */
    public function render_discussions_nested_collapsed_field()
    {
        $nested_collapsed =
            $this->options["discussions"]["nested_collapsed"] ?? 1;
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;
        $show_nested = $this->options["discussions"]["show_nested"] ?? 1;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_discussions_nested_collapsed") .
            '" type="checkbox" name="bluesky_settings[discussions][nested_collapsed]" value="1" ' .
            checked(1, $nested_collapsed, false) .
            ($enable_discussions && $show_nested ? "" : " disabled") .
            ' aria-describedby="bluesky-discussions_nested_collapsed-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-discussions_nested_collapsed-desc">' .
            esc_html(
                __(
                    "Collapse nested replies by default (only available if multiple levels are enabled).",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render discussions show stats field
     */
    public function render_discussions_show_stats_field()
    {
        $show_stats = $this->options["discussions"]["show_stats"] ?? 1;
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_discussions_show_stats") .
            '" type="checkbox" name="bluesky_settings[discussions][show_stats]" value="1" ' .
            checked(1, $show_stats, false) .
            ($enable_discussions ? "" : " disabled") .
            ' aria-describedby="bluesky-discussions_show_stats-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-discussions_show_stats-desc">' .
            esc_html(
                __(
                    "Display like and share counters for individual replies.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render discussions show reply link field
     */
    public function render_discussions_show_reply_link_field()
    {
        $show_reply_link =
            $this->options["discussions"]["show_reply_link"] ?? 1;
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_discussions_show_reply_link") .
            '" type="checkbox" name="bluesky_settings[discussions][show_reply_link]" value="1" ' .
            checked(1, $show_reply_link, false) .
            ($enable_discussions ? "" : " disabled") .
            ' aria-describedby="bluesky-discussions_show_reply_link-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-discussions_show_reply_link-desc">' .
            esc_html(
                __(
                    "Display a link to reply on Bluesky for each reply.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Render discussions show media field
     */
    public function render_discussions_show_media_field()
    {
        $show_media = $this->options["discussions"]["show_media"] ?? 1;
        $enable_discussions = $this->options["discussions"]["enable"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_discussions_show_media") .
            '" type="checkbox" name="bluesky_settings[discussions][show_media]" value="1" ' .
            checked(1, $show_media, false) .
            ($enable_discussions ? "" : " disabled") .
            ' aria-describedby="bluesky-discussions_show_media-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-discussions_show_media-desc">' .
            esc_html(
                __(
                    "Display images, videos, and other attachments in replies.",
                    "social-integration-for-bluesky",
                ),
            ) .
            "</span>";
    }

    /**
     * Display cache status
     */
    private function display_cache_status()
    {
        $helpers = $this->helpers;
        $profile_transient = get_transient(
            $helpers->get_profile_transient_key(),
        );
        $posts_transient = get_transient($helpers->get_posts_transient_key());
        $access_token_transient = get_transient(
            $helpers->get_access_token_transient_key(),
        );
        $refresh_token_transient = get_transient(
            $helpers->get_refresh_token_transient_key(),
        );

        $output = '<aside class="bluesky-cache-status">';

        $output .=
            '<h3 class="bluesky-cache-title">' .
            esc_html__(
                "Current cache status:",
                "social-integration-for-bluesky",
            ) .
            "</h3>";

        // Profile cache status
        $output .=
            "<p><strong>" .
            esc_html(
                __("Profile Card Cache:", "social-integration-for-bluesky"),
            ) .
            "</strong> ";
        if ($profile_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time(
                $helpers->get_profile_transient_key(),
            );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html(
                    __(
                        "Active (expires in %s)",
                        "social-integration-for-bluesky",
                    ),
                ),
                "<code>" .
                    esc_html($this->format_time_remaining($time_remaining)) .
                    "</code>",
            );
        } else {
            $output .= esc_html(
                __("Not cached", "social-integration-for-bluesky"),
            );
        }
        $output .= "</p>";

        // Posts cache status
        $output .=
            "<p><strong>" .
            esc_html(
                __("Posts Feed Cache:", "social-integration-for-bluesky"),
            ) .
            "</strong> ";
        if ($posts_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time(
                $helpers->get_posts_transient_key(),
            );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html(
                    __(
                        "Active (expires in %s)",
                        "social-integration-for-bluesky",
                    ),
                ),
                "<code>" .
                    esc_html($this->format_time_remaining($time_remaining)) .
                    "</code>",
            );
        } else {
            $output .= esc_html(
                __("Not cached", "social-integration-for-bluesky"),
            );
        }
        $output .= "</p>";

        $output .= "<hr>";

        // Access Token cache status
        $output .=
            "<p><strong>" .
            esc_html(
                __("Access Token Cache:", "social-integration-for-bluesky"),
            ) .
            "</strong> ";
        if ($access_token_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time(
                $helpers->get_access_token_transient_key(),
            );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html(
                    __(
                        "Active (expires in %s)",
                        "social-integration-for-bluesky",
                    ),
                ),
                "<code>" .
                    esc_html($this->format_time_remaining($time_remaining)) .
                    "</code>",
            );
        } else {
            $output .= esc_html(
                __("Not cached", "social-integration-for-bluesky"),
            );
        }
        $output .= "</p>";

        // Refresh Token cache status
        $output .=
            "<p><strong>" .
            esc_html(
                __("Refresh Token Cache:", "social-integration-for-bluesky"),
            ) .
            "</strong> ";
        if ($refresh_token_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time(
                $helpers->get_refresh_token_transient_key(),
            );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html(
                    __(
                        "Active (expires in %s)",
                        "social-integration-for-bluesky",
                    ),
                ),
                "<code>" .
                    esc_html($this->format_time_remaining($time_remaining)) .
                    "</code>",
            );
        } else {
            $output .= esc_html(
                __("Not cached", "social-integration-for-bluesky"),
            );
        }
        $output .= "</p>";
        $output .= "</aside>";

        return $output;
    }

    /**
     * Get transient expiration time in seconds
     */
    private function get_transient_expiration_time($transient_name)
    {
        $timeout_key = "_transient_timeout_" . $transient_name;

        $timeout = get_option($timeout_key);

        if ($timeout) {
            return $timeout - time();
        }

        return 0;
    }

    /**
     * Format time remaining in human-readable format
     */
    private function format_time_remaining($seconds)
    {
        if ($seconds <= 0) {
            return esc_html(__("expired", "social-integration-for-bluesky"));
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remaining_seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            // translators: %d is the number of days
            $parts[] = sprintf(
                esc_html(
                    _n(
                        "%d day",
                        "%d days",
                        $days,
                        "social-integration-for-bluesky",
                    ),
                ),
                $days,
            );
        }
        if ($hours > 0) {
            // translators: %d is the number of hours
            $parts[] = sprintf(
                esc_html(
                    _n(
                        "%d hour",
                        "%d hours",
                        $hours,
                        "social-integration-for-bluesky",
                    ),
                ),
                $hours,
            );
        }
        if ($minutes > 0) {
            // translators: %d is the number of minutes
            $parts[] = sprintf(
                esc_html(
                    _n(
                        "%d minute",
                        "%d minutes",
                        $minutes,
                        "social-integration-for-bluesky",
                    ),
                ),
                $minutes,
            );
        }
        if (empty($parts) || $remaining_seconds > 0) {
            // translators: %d is the number of seconds
            $parts[] = sprintf(
                esc_html(
                    _n(
                        "%d second",
                        "%d seconds",
                        $remaining_seconds,
                        "social-integration-for-bluesky",
                    ),
                ),
                $remaining_seconds,
            );
        }

        return implode(", ", $parts);
    }

    /**
     * Handle account management actions (add, remove, switch, toggle)
     */
    private function handle_account_actions()
    {
        if (!$this->account_manager) {
            return;
        }

        // Note: Add account is handled in sanitize_settings() (submitted with Save Changes)

        // Remove account
        if (isset($_POST['bluesky_remove_account'])) {
            $account_id = sanitize_text_field($_POST['account_id'] ?? '');
            check_admin_referer('bluesky_remove_account_' . $account_id, '_bluesky_remove_nonce_' . $account_id);

            $orphaned_count = $this->account_manager->remove_account($account_id);

            if (is_wp_error($orphaned_count)) {
                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_error',
                    $orphaned_count->get_error_message(),
                    'error'
                );
            } else {
                $message = __('Account removed successfully.', 'social-integration-for-bluesky');
                if ($orphaned_count > 0) {
                    $message .= ' ' . sprintf(
                        _n(
                            '%d post was associated with this account.',
                            '%d posts were associated with this account.',
                            $orphaned_count,
                            'social-integration-for-bluesky'
                        ),
                        $orphaned_count
                    );
                }

                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_success',
                    $message,
                    'success'
                );
            }
        }

        // Switch active account
        if (isset($_POST['bluesky_switch_account'])) {
            $account_id = sanitize_text_field($_POST['account_id'] ?? '');
            check_admin_referer('bluesky_switch_account_' . $account_id, '_bluesky_switch_nonce_' . $account_id);

            $result = $this->account_manager->set_active_account($account_id);

            if (is_wp_error($result)) {
                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_error',
                    $result->get_error_message(),
                    'error'
                );
            } else {
                // Clear content transients for the old account
                $this->clear_content_transients();

                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_success',
                    __('Active account switched successfully.', 'social-integration-for-bluesky'),
                    'success'
                );
            }
        }

        // Toggle auto-syndication
        if (isset($_POST['bluesky_toggle_auto_syndicate'])) {
            $account_id = sanitize_text_field($_POST['account_id'] ?? '');
            check_admin_referer('bluesky_toggle_auto_syndicate_' . $account_id, '_bluesky_toggle_nonce_' . $account_id);

            $auto_syndicate = !empty($_POST['auto_syndicate']);
            $result = $this->account_manager->update_account($account_id, ['auto_syndicate' => $auto_syndicate]);

            if (is_wp_error($result)) {
                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_error',
                    $result->get_error_message(),
                    'error'
                );
            }
        }

        // Set discussion account
        if (isset($_POST['bluesky_set_discussion_account'])) {
            check_admin_referer('bluesky_discussion_account', '_bluesky_discussion_nonce');

            $account_id = sanitize_text_field($_POST['bluesky_discussion_account'] ?? '');
            $result = $this->account_manager->set_discussion_account($account_id);

            if (is_wp_error($result)) {
                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_error',
                    $result->get_error_message(),
                    'error'
                );
            } else {
                add_settings_error(
                    'bluesky_messages',
                    'bluesky_account_success',
                    __('Discussion account updated successfully.', 'social-integration-for-bluesky'),
                    'success'
                );
            }
        }
    }

    /**
     * Clear all Bluesky content transients (profile, posts).
     * Called when the account changes or on logout.
     */
    public function clear_content_transients()
    {
        $helpers = $this->helpers;

        // Clear profile transient
        delete_transient($helpers->get_profile_transient_key());

        // Clear posts transients for all parameter combinations
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
     * Display logout messages based on the BLueSky_Admin_Actions method and redirection.
     *
     * @return void
     */
    public function display_bluesky_logout_message()
    {
        if ($message_data = get_transient("bluesky_logout_message")) {
            $class =
                $message_data["type"] === "success" ? "updated" : "error"; ?>

        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($message_data["message"]); ?></p>
        </div>

        <?php delete_transient("bluesky_logout_message");
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        // Handle account actions first
        $this->handle_account_actions();
        // Render all tabs immediately — auth check happens via AJAX
        $auth = true;
        ?>
        <main class="bluesky-social-integration-admin">
            <header role="banner" class="privacy-settings-header">
                <div class="privacy-settings-title-section">
                    <h1>
                        <svg width="64" height="56" viewBox="0 0 166 146" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z" fill="#1185FE"/>
                        </svg>
                        <?php echo esc_html__(
                            "Social Integration for BlueSky",
                            "social-integration-for-bluesky",
                        ); ?>
                    </h1>
                </div>

                <nav id="bluesky-main-nav-tabs" role="navigation" class="privacy-settings-tabs-wrapper" aria-label="<?php esc_attr_e(
                    "Bluesky Settings Menu",
                    "social-integration-for-bluesky",
                ); ?>">
                    <a href="#account" aria-controls="account" class="privacy-settings-tab active" aria-current="true">
                        <?php esc_html_e(
                            "Account Settings",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>

                    <?php if ($auth) { ?>
                    <a href="#customization" aria-controls="customization" class="privacy-settings-tab">
                        <?php esc_html_e(
                            "Customization",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                    <?php } ?>

                    <?php if ($auth) { ?>
                    <a href="#styles" aria-controls="styles" class="privacy-settings-tab">
                        <?php esc_html_e(
                            "Styles",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                    <?php } ?>

                    <?php if ($auth) { ?>
                    <a href="#discussions" aria-controls="discussions" class="privacy-settings-tab">
                        <?php echo esc_html__(
                            "Discussions",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                    <?php } ?>

                    <?php if ($auth) { ?>
                    <a href="#shortcodes" aria-controls="shortcodes" class="privacy-settings-tab">
                        <?php echo esc_html__(
                            "The shortcodes",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                    <?php } ?>

                    <a href="#about" aria-controls="about" class="privacy-settings-tab">
                        <?php echo esc_html__(
                            "About",
                            "social-integration-for-bluesky",
                        ); ?>
                    </a>
                </nav>
            </header>

            <div class="bluesky-social-integration-options">
                <form method="post" action="options.php">

                    <?php
                    settings_fields("bluesky_settings_group");
                    do_settings_sections(BLUESKY_PLUGIN_SETTING_PAGENAME);

                    $style_layout = !isset(
                        $this->options["styles"]["feed_layout"],
                    )
                        ? "default"
                        : $this->options["styles"]["feed_layout"];
                    ?>

                    <div id="styles" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__(
                            "Styles",
                            "social-integration-for-bluesky",
                        ); ?></h2>

                        <p><?php echo esc_html__(
                            "Decide how you want your Bluesky blocks to look like!",
                            "social-integration-for-bluesky",
                        ); ?></p>

                        <h3><?php echo esc_html__(
                            "Customize Feed Layout",
                            "social-integration-for-bluesky",
                        ); ?></h3>

                        <p><?php echo esc_html__(
                            'Pick the layout that suits you best. Be careful, some of them could come later, and with specific pre-defined options. (e.g. "no-replies" by default)',
                            "social-integration-for-bluesky",
                        ); ?></p>

                        <div class="bluesky-social-integration-layout-options">
                            <label for="bluesky_settings_feed_layout_default">
                                <input id="bluesky_settings_feed_layout_default" type="radio" name="bluesky_settings[styles][feed_layout]" value="default"<?php echo $style_layout ===
                                "default"
                                    ? ' checked="checked"'
                                    : ""; ?>>

                                <span class="screen-reader-text"><?php echo esc_html__(
                                    "Default layout",
                                    "social-integration-for-bluesky",
                                ); ?></span>

                                <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                                    "/assets/img/layout-default.svg"; ?>" alt="" width="150" height="163">
                            </label>

                            <label for="bluesky_settings_feed_layout_2">
                                <input id="bluesky_settings_feed_layout_2" type="radio" name="bluesky_settings[styles][feed_layout]" value="layout_2"<?php echo $style_layout ===
                                "layout_2"
                                    ? ' checked="checked"'
                                    : ""; ?>>

                                <span class="screen-reader-text"><?php echo esc_html__(
                                    "Light Weight Layout",
                                    "social-integration-for-bluesky",
                                ); ?></span>

                                <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                                    "/assets/img/layout-layout_2.svg"; ?>" alt="" width="150" height="163">
                            </label>
                        </div>

                        <?php submit_button(
                            null,
                            "primary large",
                            null,
                            true,
                        ); ?>

                        <h3><?php echo esc_html__(
                            "Customize Font Styling",
                            "social-integration-for-bluesky",
                        ); ?></h3>

                        <p><?php echo esc_html__(
                            "Tweak the display of each block by customizing the font sizes. Find the best balance!",
                            "social-integration-for-bluesky",
                        ); ?></p>

                        <div class="bluesky-custom-styles-output" hidden>
                            <?php
                            $render_front = new BlueSky_Render_Front(
                                $this->api_handler,
                            );
                            $render_front->render_inline_custom_styles();
                            ?>
                        </div>

                        <div class="bluesky-social-integration-large-content">
                            <section class="bluesky-social-integration-interactive" aria-label="[bluesky_profile]">
                                <div class="bluesky-social-integration-interactive-visual">
                                    <?php echo do_shortcode(
                                        "[bluesky_profile]",
                                    ); ?>
                                </div>
                                <div class="bluesky-social-integration-interactive-editor">
                                    <?php
                                    $profile_data =
                                        $this->options["customisation"][
                                            "profile"
                                        ] ?? [];

                                    $profile_inputs = [
                                        "name" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Name/Pseudo",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 20,
                                                "var" =>
                                                    "--bluesky-profile-custom-name-fs",
                                            ],
                                        ],
                                        "handle" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Nickhandle",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 14,
                                                "var" =>
                                                    "--bluesky-profile-custom-handle-fs",
                                            ],
                                        ],
                                        "followers" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Counters",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 16,
                                                "var" =>
                                                    "--bluesky-profile-custom-followers-fs",
                                            ],
                                        ],
                                        "description" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Biography",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 16,
                                                "var" =>
                                                    "--bluesky-profile-custom-description-fs",
                                            ],
                                        ],
                                    ];
                                    ?>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php foreach (
                                                $profile_inputs
                                                as $element => $properties
                                            ) {
                                                $index = 0; ?>
                                            <tr>
                                                <th scope="row">
                                                    <?php foreach (
                                                        $properties
                                                        as $prop => $data
                                                    ) { ?>

                                                    <label for="bluesky_custom_profile_<?php echo esc_attr(
                                                        $element . "_" . $prop,
                                                    ); ?>"<?php echo $index > 0
    ? 'class="screen-reader-text"'
    : ""; ?>>
                                                        <?php echo esc_html(
                                                            $data["label"],
                                                        ); ?>
                                                    </label>

                                                    <?php $index++;} ?>
                                                </th>
                                                <td>
                                                    <?php foreach (
                                                        $properties
                                                        as $prop => $data
                                                    ) { ?>
                                                    <span class="bluesky-input-widget">
                                                        <input type="number"
                                                            id="bluesky_custom_profile_<?php echo esc_attr(
                                                                $element .
                                                                    "_" .
                                                                    $prop,
                                                            ); ?>"

                                                            name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                                $element,
                                                            ); ?>][<?php echo esc_attr(
    $prop,
); ?>][value]"

                                                            placeholder="<?php echo esc_attr(
                                                                $data[
                                                                    "default"
                                                                ],
                                                            ); ?>"

                                                            data-var="<?php echo esc_attr(
                                                                $data["var"],
                                                            ); ?>"

                                                            aria-labelledby="bluesky_custom_profile_<?php echo esc_attr(
                                                                $element .
                                                                    "_" .
                                                                    $prop,
                                                            ); ?> bluesky_custom_profile_<?php echo esc_attr(
     $element . "_" . $prop,
 ); ?>_unit"

                                                            class="bluesky-custom-unit"

                                                            min="<?php echo esc_attr(
                                                                $data["min"],
                                                            ); ?>"

                                                            value="<?php echo isset(
                                                                $profile_data[
                                                                    $element
                                                                ][$prop][
                                                                    "value"
                                                                ],
                                                            ) &&
                                                            intval(
                                                                $profile_data[
                                                                    $element
                                                                ][$prop][
                                                                    "value"
                                                                ],
                                                            ) >= $data["min"]
                                                                ? intval(
                                                                    $profile_data[
                                                                        $element
                                                                    ][$prop][
                                                                        "value"
                                                                    ],
                                                                )
                                                                : ""; ?>"

                                                            autocomplete="off"
                                                        >
                                                        <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                            $element,
                                                        ); ?>][<?php echo esc_attr(
    $prop,
); ?>][default]" value="<?php echo esc_attr($data["default"]); ?>">

                                                        <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                            $element,
                                                        ); ?>][<?php echo esc_attr(
    $prop,
); ?>][min]" value="<?php echo esc_attr($data["min"]); ?>">

                                                        <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_profile_<?php echo esc_attr(
                                                            $element .
                                                                "_" .
                                                                $prop,
                                                        ); ?>_unit">px</abbr>
                                                    </span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php
                                            } ?>
                                            <tr class="bluesky-submit-in-table">
                                                <td colspan="2">
                                                <?php submit_button(
                                                    null,
                                                    "primary large",
                                                    null,
                                                    false,
                                                ); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="bluesky-social-integration-interactive" aria-label="[bluesky_last_posts]">
                                <div class="bluesky-social-integration-interactive-visual">
                                    <?php echo do_shortcode(
                                        "[bluesky_last_posts]",
                                    ); ?>
                                </div>
                                <div class="bluesky-social-integration-interactive-editor">
                                    <?php
                                    $posts_data =
                                        $this->options["customisation"][
                                            "posts"
                                        ] ?? [];

                                    $posts_inputs = [
                                        "account-info-names" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Name/Pseudo",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 16,
                                                "var" =>
                                                    "--bluesky-posts-custom-account-info-names-fs",
                                            ],
                                        ],
                                        "handle" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Nickhandle",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 14,
                                                "var" =>
                                                    "--bluesky-posts-custom-handle-fs",
                                            ],
                                        ],
                                        "post-content" => [
                                            "fs" => [
                                                "label" => __(
                                                    "Post Content",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 15,
                                                "var" =>
                                                    "--bluesky-posts-custom-post-content-fs",
                                            ],
                                        ],
                                        "external-content-title" => [
                                            "fs" => [
                                                "label" => __(
                                                    "External Title",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 18,
                                                "var" =>
                                                    "--bluesky-posts-custom-external-content-title-fs",
                                            ],
                                        ],
                                        "external-content-description" => [
                                            "fs" => [
                                                "label" => __(
                                                    "External Description",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 14,
                                                "var" =>
                                                    "--bluesky-posts-custom-external-content-description-fs",
                                            ],
                                        ],
                                        "external-content-url" => [
                                            "fs" => [
                                                "label" => __(
                                                    "External URL",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 16,
                                                "var" =>
                                                    "--bluesky-posts-custom-external-content-url-fs",
                                            ],
                                        ],
                                        "starterpack-name" => [
                                            "fs" => [
                                                "label" => __(
                                                    "StarterPack Name",
                                                    "social-integration-for-bluesky",
                                                ),
                                                "min" => 10,
                                                "default" => 18,
                                                "var" =>
                                                    "--bluesky-posts-custom-starterpack-name-fs",
                                            ],
                                        ],
                                    ];
                                    ?>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php foreach (
                                                $posts_inputs
                                                as $element => $properties
                                            ) {
                                                $index = 0; ?>
                                            <tr>
                                                <th scope="row">
                                                    <?php foreach (
                                                        $properties
                                                        as $prop => $data
                                                    ) { ?>

                                                    <label for="bluesky_custom_posts_<?php echo esc_attr(
                                                        $element . "_" . $prop,
                                                    ); ?>"<?php echo $index > 0
    ? 'class="screen-reader-text"'
    : ""; ?>>
                                                        <?php echo esc_html(
                                                            $data["label"],
                                                        ); ?>
                                                    </label>

                                                    <?php $index++;} ?>
                                                </th>
                                                <td>
                                                    <?php foreach (
                                                        $properties
                                                        as $prop => $data
                                                    ) { ?>
                                                    <span class="bluesky-input-widget">
                                                        <input type="number"
                                                            id="bluesky_custom_posts_<?php echo esc_attr(
                                                                $element .
                                                                    "_" .
                                                                    $prop,
                                                            ); ?>"

                                                            name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                                $element,
                                                            ); ?>][<?php echo esc_attr(
    $prop,
); ?>][value]"

                                                            placeholder="<?php echo esc_attr(
                                                                $data[
                                                                    "default"
                                                                ],
                                                            ); ?>"

                                                            data-var="<?php echo esc_attr(
                                                                $data["var"],
                                                            ); ?>"

                                                            aria-labelledby="bluesky_custom_posts_<?php echo esc_attr(
                                                                $element .
                                                                    "_" .
                                                                    $prop,
                                                            ); ?> bluesky_custom_posts_<?php echo esc_attr(
     $element . "_" . $prop,
 ); ?>_unit"

                                                            class="bluesky-custom-unit"

                                                            min="<?php echo esc_attr(
                                                                $data["min"],
                                                            ); ?>"

                                                            value="<?php echo isset(
                                                                $posts_data[
                                                                    $element
                                                                ][$prop][
                                                                    "value"
                                                                ],
                                                            ) &&
                                                            intval(
                                                                $posts_data[
                                                                    $element
                                                                ][$prop][
                                                                    "value"
                                                                ],
                                                            ) >= $data["min"]
                                                                ? intval(
                                                                    $posts_data[
                                                                        $element
                                                                    ][$prop][
                                                                        "value"
                                                                    ],
                                                                )
                                                                : ""; ?>"

                                                            autocomplete="off"
                                                        >

                                                        <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                            $element,
                                                        ); ?>][<?php echo esc_attr(
    $prop,
); ?>][default]" value="<?php echo esc_attr($data["default"]); ?>">

                                                        <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                            $element,
                                                        ); ?>][<?php echo esc_attr(
    $prop,
); ?>][min]" value="<?php echo esc_attr($data["min"]); ?>">

                                                        <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_posts_<?php echo esc_attr(
                                                            $element .
                                                                "_" .
                                                                $prop,
                                                        ); ?>_unit">px</abbr>
                                                    </span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php
                                            } ?>
                                            <tr class="bluesky-submit-in-table">
                                                <td colspan="2">
                                                <?php submit_button(
                                                    null,
                                                    "primary large",
                                                    null,
                                                    false,
                                                ); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                        </div>
                    </div>


                    <div id="shortcodes" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__(
                            "About the shortcodes",
                            "social-integration-for-bluesky",
                        ); ?></h2>
                        <?php // translators: %1$s is the the bluesky profile shortcode, %2$s is the bluesky last posts shortcode.
        ?>
                        <p><?php echo sprintf(
                            esc_html__(
                                'You can use the following shortcodes to display your BlueSky profile and posts: %1$s and %2$s.',
                                "social-integration-for-bluesky",
                            ),
                            "<code>[bluesky_profile]</code>",
                            "<code>[bluesky_last_posts]</code>",
                        ); ?></p>

                        <p><?php echo esc_html__(
                            "By default, the shortcodes use the global settings, but you can decide to override them thanks to the attributes described on this page.",
                            "social-integration-for-bluesky",
                        ); ?></p>

                        <p><?php echo esc_html__(
                            "You can also use the Gutenberg blocks to display the profile card and posts feed.",
                            "social-integration-for-bluesky",
                        ); ?></p>

                        <?php if ($auth) { ?>

                        <h2><?php echo esc_html__(
                            "Shortcodes Demo",
                            "social-integration-for-bluesky",
                        ); ?></h2>

                        <div class="bluesky-social-demo container">
                            <h3><?php echo esc_html__(
                                "Profile Card",
                                "social-integration-for-bluesky",
                            ); ?> <code>[bluesky_profile]</code></h3>
                            <p><?php echo esc_html__(
                                "The profile shortcode will display your BlueSky profile card. It uses the following attributes:",
                                "social-integration-for-bluesky",
                            ); ?></p>
                            <ul>
                                <li><code>displaybanner</code> - <?php echo esc_html__(
                                    "Whether to display the profile banner. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>displayavatar</code> - <?php echo esc_html__(
                                    "Whether to display the profile avatar. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>displaycounters</code> - <?php echo esc_html__(
                                    "Whether to display follower/following counts. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>displaybio</code> - <?php echo esc_html__(
                                    "Whether to display the profile bio. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>theme</code> - <?php echo esc_html__(
                                    'The theme to use for displaying the profile. Options are "light", "dark", and "system". Default is "system".',
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>classname</code> - <?php echo esc_html__(
                                    "Additional CSS class to apply to the profile card.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                            </ul>

                            <p><?php echo esc_html__(
                                "This is how your BlueSky profile card will look like:",
                                "social-integration-for-bluesky",
                            ); ?></p>

                            <div class="demo">
                                <div class="demo-profile">
                                    <?php echo do_shortcode(
                                        "[bluesky_profile]",
                                    ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="bluesky-social-demo container">
                            <h3><?php echo esc_html__(
                                "Last Posts Feed",
                                "social-integration-for-bluesky",
                            ); ?> <code>[bluesky_last_posts]</code></h3>

                            <p><?php echo esc_html__(
                                "The last posts shortcode will display your last posts feed. It uses the following attributes:",
                                "social-integration-for-bluesky",
                            ); ?></p>
                            <ul>
                                <li><code>displayembeds</code> - <?php echo esc_html__(
                                    "Whether to display embedded media in the posts. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>displayimages</code> - <?php echo esc_html__(
                                    "Whether to display embedded images in the posts. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>noreplies</code> - <?php echo esc_html__(
                                    "Whether to hide your replies, or include them in your feed. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>noreposts</code> - <?php echo esc_html__(
                                    "Whether to hide the reposts, or include them in your feed. Default is true.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>numberofposts</code> - <?php echo esc_html__(
                                    "The number of posts to display. Default is 5.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>nocounters</code> - <?php echo esc_html__(
                                    "Whether to hide like, repost, reply, and quote counters. Default is false.",
                                    "social-integration-for-bluesky",
                                ); ?></li>
                                <li><code>theme</code> - <?php echo esc_html__(
                                    'The theme to use for displaying the posts. Options are "light", "dark", and "system". Default is "system".',
                                    "social-integration-for-bluesky",
                                ); ?></li>
                            </ul>

                            <p><?php echo esc_html__(
                                "This is how your last posts feed will look like:",
                                "social-integration-for-bluesky",
                            ); ?></p>

                            <div class="demo">
                                <div class="demo-posts">
                                    <?php echo do_shortcode(
                                        '[bluesky_last_posts numberofposts="3"]',
                                    ); ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <div id="about" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__(
                            "About this plugin",
                            "social-integration-for-bluesky",
                        ); ?></h2>
                        <?php // translators: %s is the name of the developer.
        ?>
                        <p><?php echo sprintf(
                            esc_html__(
                                "This plugin is written by %s.",
                                "social-integration-for-bluesky",
                            ),
                            '<a href="https://geoffreycrofte.com" target="_blank"><strong>Geoffrey Crofte</strong></a>',
                        ); ?><br><?php echo esc_html__(
    "This extension is not an official BlueSky plugin.",
    "social-integration-for-bluesky",
); ?></p>

                        <?php // translators: %1$s is the link opening tag, %2$s closing link tag.
        ?>
                        <p>
                            <?php echo sprintf(
                                esc_html__(
                                    'Need help with something? Have a suggestion? %1$sAsk away%2$s.',
                                    "social-integration-for-bluesky",
                                ),
                                '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/#new-topic-0" target="_blank">',
                                "</a>",
                            ); ?><br>
                            <?php echo sprintf(
                                esc_html__(
                                    'You want to contribute to this project? %1$sHere is the Github Repository%2$s.',
                                    "social-integration-for-bluesky",
                                ),
                                '<a href="https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress" target="_blank">',
                                "</a>",
                            ); ?>
                        </p>

                        <?php $title = __(
                            "Rate this plugin on WordPress.org",
                            "social-integration-for-bluesky",
                        ); ?>

                        <?php // translators: %1$s is the link opening tag, %2$s closing link tag.
        ?>
                        <p><?php echo sprintf(
                            esc_html__(
                                'Want to support the plugin? %1$sGive a review%2$s',
                                "social-integration-for-bluesky",
                            ),
                            '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/reviews/" target="_blank" title="' .
                                esc_attr($title) .
                                '">',
                            " ⭐️⭐️⭐️⭐️⭐️</a>",
                        ); ?></p>

                        <h2><?php echo esc_html__(
                            "Some Plugin Engine Info",
                            "social-integration-for-bluesky",
                        ); ?></h2>
                        <?php echo $this->display_cache_status(); ?>
                    </div>

                </form>
            </div>

            <?php if (
                isset($_GET["godmode"]) ||
                defined("WP_DEBUG") ||
                defined("WP_DEBUG_DISPLAY")
            ) { ?>
            <aside class="bluesky-debug-sidebar is-collapsed">
                <button class="bluesky-open-button" type="button" aria-expanded="false" aria-controls="bluesky-debug-bar">
                    <span class="screen-reader-text"><?php esc_html_e(
                        "Debug Bar",
                        "social-integration-for-bluesky",
                    ); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><path fill="currentColor" d="M8.06561801,18.9432081 L14.565618,4.44320807 C14.7350545,4.06523433 15.1788182,3.8961815 15.5567919,4.06561801 C15.9032679,4.2209348 16.0741922,4.60676263 15.9697642,4.9611247 L15.934382,5.05679193 L9.43438199,19.5567919 C9.26494549,19.9347657 8.82118181,20.1038185 8.44320807,19.934382 C8.09673215,19.7790652 7.92580781,19.3932374 8.03023576,19.0388753 L8.06561801,18.9432081 L14.565618,4.44320807 L8.06561801,18.9432081 Z M2.21966991,11.4696699 L6.46966991,7.21966991 C6.76256313,6.9267767 7.23743687,6.9267767 7.53033009,7.21966991 C7.79659665,7.48593648 7.8208027,7.90260016 7.60294824,8.19621165 L7.53033009,8.28033009 L3.81066017,12 L7.53033009,15.7196699 C7.8232233,16.0125631 7.8232233,16.4874369 7.53033009,16.7803301 C7.26406352,17.0465966 6.84739984,17.0708027 6.55378835,16.8529482 L6.46966991,16.7803301 L2.21966991,12.5303301 C1.95340335,12.2640635 1.9291973,11.8473998 2.14705176,11.5537883 L2.21966991,11.4696699 L6.46966991,7.21966991 L2.21966991,11.4696699 Z M16.4696699,7.21966991 C16.7359365,6.95340335 17.1526002,6.9291973 17.4462117,7.14705176 L17.5303301,7.21966991 L21.7803301,11.4696699 C22.0465966,11.7359365 22.0708027,12.1526002 21.8529482,12.4462117 L21.7803301,12.5303301 L17.5303301,16.7803301 C17.2374369,17.0732233 16.7625631,17.0732233 16.4696699,16.7803301 C16.2034034,16.5140635 16.1791973,16.0973998 16.3970518,15.8037883 L16.4696699,15.7196699 L20.1893398,12 L16.4696699,8.28033009 C16.1767767,7.98743687 16.1767767,7.51256313 16.4696699,7.21966991 Z"></path></svg>
                </button>
                <div id="bluesky-debug-bar" class="bluesky-debug-sidebar-content" aria-hidden="true">
                    <h2><?php esc_html_e(
                        "Debug Bar",
                        "social-integration-for-bluesky",
                    ); ?></h2>
                    <details>
                        <summary><?php esc_html_e(
                            "Former plugin's options (kept for retro-compatibility)",
                            "social-integration-for-bluesky",
                        ); ?></summary>
                        <?php echo $this->helpers->war_dump($this->options); ?>
                    </details>
                    <details>
                        <summary><?php esc_html_e(
                            "Multi-Account: Accounts",
                            "social-integration-for-bluesky",
                        ); ?></summary>
                        <?php echo $this->helpers->war_dump(get_option('bluesky_accounts', [])); ?>
                    </details>
                    <details>
                        <summary><?php esc_html_e(
                            "Multi-Account: Active Account",
                            "social-integration-for-bluesky",
                        ); ?></summary>
                        <?php echo $this->helpers->war_dump(get_option('bluesky_active_account', '')); ?>
                    </details>
                    <details>
                        <summary><?php esc_html_e(
                            "Multi-Account: Global Settings",
                            "social-integration-for-bluesky",
                        ); ?></summary>
                        <?php echo $this->helpers->war_dump(get_option('bluesky_global_settings', [])); ?>
                    </details>
                    <details>
                        <summary><?php esc_html_e(
                            "Multi-Account: Schema Version",
                            "social-integration-for-bluesky",
                        ); ?></summary>
                        <?php echo $this->helpers->war_dump(get_option('bluesky_schema_version', 1)); ?>
                    </details>
                </div>
            </aside>
           <?php } ?>
        </main>
        <?php
    }
}
