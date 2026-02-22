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
        $sanitized["global_pause"] = isset($input["global_pause"]) ? 1 : 0;
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
        $sanitized["styles"]["profile_layout"] =
            isset($input["styles"]["profile_layout"]) &&
            in_array($input["styles"]["profile_layout"], ["default", "compact"])
                ? esc_attr($input["styles"]["profile_layout"])
                : "default";

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

        // Save discussion account if submitted with the form
        if (isset($_POST['bluesky_set_discussion_account']) && $this->account_manager) {
            $discussion_id = sanitize_text_field(wp_unslash($_POST['bluesky_discussion_account'] ?? ''));
            $this->account_manager->set_discussion_account($discussion_id);
        }

        // Sync global display settings to bluesky_global_settings for multi-account mode
        $global_settings = get_option('bluesky_global_settings', []);
        if (!empty($global_settings)) {
            $global_settings['styles']['profile_layout'] = $sanitized['styles']['profile_layout'];
            $global_settings['styles']['feed_layout'] = $sanitized['styles']['feed_layout'];
            update_option('bluesky_global_settings', $global_settings);
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
     * Render global pause field
     */
    public function render_global_pause_field()
    {
        $global_pause = $this->options["global_pause"] ?? 0;

        echo '<input id="' .
            esc_attr(BLUESKY_PLUGIN_OPTIONS . "_global_pause") .
            '" type="checkbox" name="bluesky_settings[global_pause]" value="1" ' .
            checked(1, $global_pause, false) .
            ' aria-describedby="bluesky-global-pause-desc" />';

        echo '<span class="description bluesky-description" id="bluesky-global-pause-desc" style="' .
            ($global_pause ? 'color: #d63638; font-weight: 500;' : '') . '">' .
            esc_html(
                __(
                    "Pause all syndication to Bluesky. When enabled, no posts will be syndicated to any account. Use this for maintenance or troubleshooting.",
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
        $discussion_account_id = $this->account_manager->get_discussion_account() ?? '';

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
     * Display health section (replaces cache-only view)
     */
    public function display_health_section()
    {
        // Create instances of needed services
        $circuit_breaker_class_exists = class_exists('BlueSky_Circuit_Breaker');
        $rate_limiter_class_exists = class_exists('BlueSky_Rate_Limiter');
        $activity_logger_class_exists = class_exists('BlueSky_Activity_Logger');

        $output = '<aside class="bluesky-health-section" id="health">';
        $output .= '<h3 class="bluesky-health-title">' . esc_html__('Plugin Health', 'social-integration-for-bluesky') . '</h3>';

        // Account Status Block
        $output .= '<div class="bluesky-health-block">';
        $output .= '<h4>' . esc_html__('Account Status', 'social-integration-for-bluesky') . '</h4>';

        if ($this->account_manager) {
            $accounts = $this->account_manager->get_accounts();
            if (!empty($accounts)) {
                $output .= '<ul class="bluesky-health-accounts">';
                foreach ($accounts as $account) {
                    $account_id = $account['id'] ?? '';
                    $handle = $account['handle'] ?? '';

                    // Check circuit breaker status
                    $is_open = false;
                    if ($circuit_breaker_class_exists) {
                        $circuit_breaker = new BlueSky_Circuit_Breaker($account_id);
                        $is_open = ! $circuit_breaker->is_available();
                    }

                    // Check rate limiter status
                    $is_rate_limited = false;
                    if ($rate_limiter_class_exists) {
                        $rate_limiter = new BlueSky_Rate_Limiter();
                        $is_rate_limited = $rate_limiter->is_rate_limited($account_id);
                    }

                    // Determine status and icon
                    if ($is_open) {
                        $icon = 'dashicons-warning';
                        $status = __('Circuit open', 'social-integration-for-bluesky');
                        $color = 'color: #d63638;';
                    } elseif ($is_rate_limited) {
                        $icon = 'dashicons-clock';
                        $status = __('Rate limited', 'social-integration-for-bluesky');
                        $color = 'color: #dba617;';
                    } elseif (empty($account['did'])) {
                        $icon = 'dashicons-warning';
                        $status = __('Auth issue', 'social-integration-for-bluesky');
                        $color = 'color: #d63638;';
                    } else {
                        $icon = 'dashicons-yes-alt';
                        $status = __('Active', 'social-integration-for-bluesky');
                        $color = 'color: #00a32a;';
                    }

                    $output .= '<li>';
                    $output .= '<span class="dashicons ' . esc_attr($icon) . '" style="' . esc_attr($color) . '"></span> ';
                    $output .= '<strong>' . esc_html($handle) . '</strong>: ' . esc_html($status);
                    $output .= '</li>';
                }
                $output .= '</ul>';
            } else {
                $output .= '<p>' . esc_html__('No accounts configured.', 'social-integration-for-bluesky') . '</p>';
            }
        } else {
            $output .= '<p>' . esc_html__('Account manager not available.', 'social-integration-for-bluesky') . '</p>';
        }
        $output .= '</div>';

        // API Health Block
        $output .= '<div class="bluesky-health-block">';
        $output .= '<h4>' . esc_html__('API Health', 'social-integration-for-bluesky') . '</h4>';

        $broken_accounts = [];
        if ($this->account_manager && $circuit_breaker_class_exists) {
            $accounts = $this->account_manager->get_accounts();
            foreach ($accounts as $account) {
                $account_id = $account['id'] ?? '';
                $circuit_breaker = new BlueSky_Circuit_Breaker($account_id);
                if (! $circuit_breaker->is_available()) {
                    $broken_accounts[] = $account['handle'] ?? $account_id;
                }
            }
        }

        if (!empty($broken_accounts)) {
            $output .= '<p>' . sprintf(
                esc_html__('Circuit breaker open for: %s', 'social-integration-for-bluesky'),
                esc_html(implode(', ', $broken_accounts))
            ) . '</p>';
        } else {
            $output .= '<p>' . esc_html__('All systems operational', 'social-integration-for-bluesky') . '</p>';
        }
        $output .= '</div>';

        // Cache Status Block (preserved from original display_cache_status)
        $output .= '<div class="bluesky-health-block">';
        $output .= '<h4>' . esc_html__('Cache Status', 'social-integration-for-bluesky') . '</h4>';

        $helpers = $this->helpers;
        $profile_transient = get_transient($helpers->get_profile_transient_key());
        $posts_transient = get_transient($helpers->get_posts_transient_key());
        $access_token_transient = get_transient($helpers->get_access_token_transient_key());
        $refresh_token_transient = get_transient($helpers->get_refresh_token_transient_key());

        // Profile cache status
        $output .= '<p><strong>' . esc_html__('Profile Card Cache:', 'social-integration-for-bluesky') . '</strong> ';
        if ($profile_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time($helpers->get_profile_transient_key());
            $output .= sprintf(
                esc_html__('Active (expires in %s)', 'social-integration-for-bluesky'),
                '<code>' . esc_html($this->format_time_remaining($time_remaining)) . '</code>'
            );
        } else {
            $output .= esc_html__('Not cached', 'social-integration-for-bluesky');
        }
        $output .= '</p>';

        // Posts cache status
        $output .= '<p><strong>' . esc_html__('Posts Feed Cache:', 'social-integration-for-bluesky') . '</strong> ';
        if ($posts_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time($helpers->get_posts_transient_key());
            $output .= sprintf(
                esc_html__('Active (expires in %s)', 'social-integration-for-bluesky'),
                '<code>' . esc_html($this->format_time_remaining($time_remaining)) . '</code>'
            );
        } else {
            $output .= esc_html__('Not cached', 'social-integration-for-bluesky');
        }
        $output .= '</p>';

        // Access Token cache status
        $output .= '<p><strong>' . esc_html__('Access Token Cache:', 'social-integration-for-bluesky') . '</strong> ';
        if ($access_token_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time($helpers->get_access_token_transient_key());
            $output .= sprintf(
                esc_html__('Active (expires in %s)', 'social-integration-for-bluesky'),
                '<code>' . esc_html($this->format_time_remaining($time_remaining)) . '</code>'
            );
        } else {
            $output .= esc_html__('Not cached', 'social-integration-for-bluesky');
        }
        $output .= '</p>';

        // Refresh Token cache status
        $output .= '<p><strong>' . esc_html__('Refresh Token Cache:', 'social-integration-for-bluesky') . '</strong> ';
        if ($refresh_token_transient !== false) {
            $time_remaining = $this->get_transient_expiration_time($helpers->get_refresh_token_transient_key());
            $output .= sprintf(
                esc_html__('Active (expires in %s)', 'social-integration-for-bluesky'),
                '<code>' . esc_html($this->format_time_remaining($time_remaining)) . '</code>'
            );
        } else {
            $output .= esc_html__('Not cached', 'social-integration-for-bluesky');
        }
        $output .= '</p>';

        $output .= '</div>';

        // Recent Activity Block
        $output .= '<div class="bluesky-health-block">';
        $output .= '<h4>' . esc_html__('Recent Activity', 'social-integration-for-bluesky') . '</h4>';

        if ($activity_logger_class_exists) {
            $activity_logger = new BlueSky_Activity_Logger();
            $recent_events = $activity_logger->get_recent_events(5);

            if (!empty($recent_events)) {
                $output .= '<ul class="bluesky-health-activity">';
                foreach ($recent_events as $event) {
                    $output .= '<li>';
                    $output .= '<span class="bluesky-activity-time">' . esc_html(BlueSky_Activity_Logger::format_event_time($event['time'])) . '</span>';
                    $output .= esc_html($event['message']);
                    $output .= '</li>';
                }
                $output .= '</ul>';
            } else {
                $output .= '<p class="description">' . esc_html__('No recent activity recorded.', 'social-integration-for-bluesky') . '</p>';
            }
        } else {
            $output .= '<p class="description">' . esc_html__('Activity logger not available.', 'social-integration-for-bluesky') . '</p>';
        }

        $output .= '</div>';

        $output .= '</aside>';

        return $output;
    }

    /**
     * Display cache status (kept for backward compatibility)
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
     * Handle category rules save
     */
    private function handle_category_rules_save()
    {
        if (!isset($_POST['bluesky_save_category_rules'])) {
            return;
        }

        if (!$this->account_manager) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_bluesky_category_rules_nonce']) ||
            !wp_verify_nonce($_POST['_bluesky_category_rules_nonce'], 'bluesky_category_rules_nonce')) {
            add_settings_error(
                'bluesky_messages',
                'bluesky_category_rules_error',
                __('Security check failed. Please try again.', 'social-integration-for-bluesky'),
                'error'
            );
            return;
        }

        // Get category rules from POST
        $category_rules_data = isset($_POST['bluesky_category_rules']) && is_array($_POST['bluesky_category_rules'])
            ? $_POST['bluesky_category_rules']
            : [];

        $accounts_updated = 0;

        foreach ($category_rules_data as $account_id => $rules) {
            $account_id = sanitize_text_field($account_id);

            $include_categories = isset($rules['include']) && is_array($rules['include'])
                ? array_map('intval', $rules['include'])
                : [];

            $exclude_categories = isset($rules['exclude']) && is_array($rules['exclude'])
                ? array_map('intval', $rules['exclude'])
                : [];

            $category_rules = [
                'include' => $include_categories,
                'exclude' => $exclude_categories
            ];

            $result = $this->account_manager->update_account($account_id, ['category_rules' => $category_rules]);

            if (!is_wp_error($result)) {
                $accounts_updated++;
            }
        }

        if ($accounts_updated > 0) {
            add_settings_error(
                'bluesky_messages',
                'bluesky_category_rules_success',
                sprintf(
                    _n(
                        'Category rules updated for %d account.',
                        'Category rules updated for %d accounts.',
                        $accounts_updated,
                        'social-integration-for-bluesky'
                    ),
                    $accounts_updated
                ),
                'success'
            );
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

        // Handle category rules save
        $this->handle_category_rules_save();

        // Load template
        ob_start();
        include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/admin/settings-page.php';
        echo ob_get_clean();
    }
}
