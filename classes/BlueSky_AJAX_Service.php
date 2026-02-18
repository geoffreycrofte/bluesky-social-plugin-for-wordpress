<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_AJAX_Service
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
     * AJAX handler for fetching BlueSky posts
     */
    public function ajax_fetch_bluesky_posts()
    {
        check_ajax_referer('bluesky_async_nonce', 'nonce');

        $limit = $this->options["posts_limit"] ?? 5;
        $posts = $this->api_handler->fetch_bluesky_posts($limit);

        if ($posts !== false) {
            wp_send_json_success($posts);
        } else {
            wp_send_json_error(__("Could not fetch posts", "social-integration-for-bluesky"));
        }
        wp_die();
    }

    /**
     * AJAX handler for fetching BlueSky profile
     */
    public function ajax_get_bluesky_profile()
    {
        check_ajax_referer('bluesky_async_nonce', 'nonce');

        $profile = $this->api_handler->get_bluesky_profile();

        if ($profile) {
            wp_send_json_success($profile);
        } else {
            wp_send_json_error(__("Could not fetch profile", "social-integration-for-bluesky"));
        }
        wp_die();
    }

    /**
     * AJAX handler for async posts rendering
     */
    public function ajax_async_posts()
    {
        check_ajax_referer("bluesky_async_nonce", "nonce");

        $params = isset($_POST["params"]) ? json_decode(
            sanitize_text_field(wp_unslash($_POST["params"])),
            true,
        ) : [];

        $attributes = [
            "theme" => sanitize_text_field($params["theme"] ?? ($this->options["theme"] ?? "system")),
            "numberofposts" => intval($params["numberofposts"] ?? ($this->options["posts_limit"] ?? 5)),
            "noreplies" => filter_var($params["noreplies"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "noreposts" => filter_var($params["noreposts"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "nocounters" => filter_var($params["nocounters"] ?? false, FILTER_VALIDATE_BOOLEAN),
            "displayembeds" => filter_var($params["displayembeds"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "account_id" => sanitize_text_field($params["account_id"] ?? ""),
        ];

        // Create per-account API handler
        $api_handler = $this->resolve_api_handler($attributes["account_id"]);

        $render = new BlueSky_Render_Front($api_handler);
        $html = $render->render_bluesky_posts_list($attributes);

        wp_send_json_success(["html" => $html]);
    }

    /**
     * AJAX handler for async profile rendering
     */
    public function ajax_async_profile()
    {
        check_ajax_referer("bluesky_async_nonce", "nonce");

        $params = isset($_POST["params"]) ? json_decode(
            sanitize_text_field(wp_unslash($_POST["params"])),
            true,
        ) : [];

        $attributes = [
            "theme" => sanitize_text_field($params["theme"] ?? ($this->options["theme"] ?? "system")),
            "styleClass" => sanitize_text_field($params["styleClass"] ?? ""),
            "displaybanner" => filter_var($params["displaybanner"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "displayavatar" => filter_var($params["displayavatar"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "displaycounters" => filter_var($params["displaycounters"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "displaybio" => filter_var($params["displaybio"] ?? true, FILTER_VALIDATE_BOOLEAN),
            "account_id" => sanitize_text_field($params["account_id"] ?? ""),
        ];

        // Create per-account API handler
        $api_handler = $this->resolve_api_handler($attributes["account_id"]);

        $render = new BlueSky_Render_Front($api_handler);
        $html = $render->render_bluesky_profile_card($attributes);

        wp_send_json_success(["html" => $html]);
    }

    /**
     * AJAX handler for async auth check (admin only)
     */
    public function ajax_async_auth()
    {
        check_ajax_referer("bluesky_async_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(__("Unauthorized", "social-integration-for-bluesky"));
            return;
        }

        // Check if account_id provided for per-account auth check
        $account_id = isset($_POST["account_id"]) ? sanitize_text_field(wp_unslash($_POST["account_id"])) : "";

        if (!empty($account_id) && $this->account_manager && $this->account_manager->is_multi_account_enabled()) {
            $account = $this->account_manager->get_account($account_id);
            if ($account) {
                $api = BlueSky_API_Handler::create_for_account($account);
            } else {
                $api = new BlueSky_API_Handler($this->options);
            }
        } else {
            $api = new BlueSky_API_Handler($this->options);
        }

        $auth = $api->authenticate();

        $result = ["authenticated" => $auth];
        if (!$auth) {
            $result["error"] = $api->get_last_auth_error();
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for setting the discussion account
     */
    public function ajax_set_discussion_account()
    {
        check_ajax_referer('bluesky_discussion_account', '_bluesky_discussion_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'social-integration-for-bluesky'));
            return;
        }

        if (!$this->account_manager) {
            wp_send_json_error(__('Account manager not available', 'social-integration-for-bluesky'));
            return;
        }

        $account_id = sanitize_text_field(wp_unslash($_POST['bluesky_discussion_account'] ?? ''));
        $result = $this->account_manager->set_discussion_account($account_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Discussion account updated.', 'social-integration-for-bluesky'));
        }
    }

    /**
     * Resolve the correct API handler for a given account_id.
     * When account_id is empty and multi-account is enabled, uses the active account.
     *
     * @param string $account_id Account UUID or empty string
     * @return BlueSky_API_Handler
     */
    private function resolve_api_handler($account_id)
    {
        if ($this->account_manager && $this->account_manager->is_multi_account_enabled()) {
            if (!empty($account_id)) {
                $account = $this->account_manager->get_account($account_id);
                if ($account) {
                    return BlueSky_API_Handler::create_for_account($account);
                }
            }

            $active = $this->account_manager->get_active_account();
            if ($active) {
                return BlueSky_API_Handler::create_for_account($active);
            }
        }
        return $this->api_handler;
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
}
