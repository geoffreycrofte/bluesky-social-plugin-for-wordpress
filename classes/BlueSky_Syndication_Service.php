<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

class BlueSky_Syndication_Service
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
     * Plugin activation hook
     */
    public function on_plugin_activation()
    {
        // Adds activation date if it doesn't exist.
        // Used later to block syndication for older posts.
        if (!get_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date")) {
            add_option(BLUESKY_PLUGIN_OPTIONS . "_activation_date", time());
        }
    }

    /**
     * Syndicate post to BlueSky
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function syndicate_post_to_bluesky($new_status, $old_status, $post)
    {
        if (
            "publish" === $new_status &&
            "publish" !== $old_status &&
            "post" === $post->post_type
        ) {
            $post_id = $post->ID;

            // Multi-account branching
            $account_manager = new BlueSky_Account_Manager();

            if ($account_manager->is_multi_account_enabled()) {
                $this->syndicate_post_multi_account($post_id, $post);
                return;
            }

            // Single-account syndication (existing code unchanged)
            $permalink = get_permalink($post_id);

            do_action("bluesky_before_syndicating_post", $post_id);

            // Check if the post should be syndicated (metabox option)
            // This metabox is set by the default global setting, or manually by the post editor.
            $dont_syndicate = get_post_meta(
                $post_id,
                "_bluesky_dont_syndicate",
                true,
            );
            if (
                $dont_syndicate ||
                (isset($_POST["bluesky_dont_syndicate"]) &&
                    $_POST["bluesky_dont_syndicate"] === "1")
            ) {
                return;
            }

            // Check if the post is already syndicated
            // because the action can be triggered multiple times by WordPress
            $is_syndicated = get_post_meta(
                $post_id,
                "_bluesky_syndicated",
                true,
            );
            if ($is_syndicated) {
                return;
            }

            // Get post excerpt
            $excerpt = "";
            if (!empty($post->post_excerpt)) {
                $excerpt = $post->post_excerpt;
            } else {
                // Generate excerpt from content if not set
                $excerpt = wp_trim_words($post->post_content, 30, "...");
            }

            // Get post image (featured image or first image in content)
            $image_url = "";

            // Try to get featured image first
            if (has_post_thumbnail($post_id)) {
                $image_url = get_the_post_thumbnail_url($post_id, "large");
            }

            // If no featured image, try to find first image in content
            if (empty($image_url)) {
                preg_match(
                    '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i',
                    $post->post_content,
                    $matches,
                );
                if (!empty($matches[1])) {
                    $image_url = $matches[1];
                }
            }

            $bluesky_post_info = $this->api_handler->syndicate_post_to_bluesky(
                $post->post_title,
                $permalink,
                $excerpt,
                $image_url,
            );

            // if it's supposed to be syndicated, add a meta to the post
            if (!$dont_syndicate) {
                $post_meta = add_post_meta(
                    $post_id,
                    "_bluesky_syndicated",
                    true,
                    true,
                );

                // Save Bluesky post information if syndication was successful
                if (
                    $bluesky_post_info !== false &&
                    is_array($bluesky_post_info)
                ) {
                    update_post_meta(
                        $post_id,
                        "_bluesky_syndication_bs_post_info",
                        wp_json_encode($bluesky_post_info),
                    );
                }
            }

            do_action("bluesky_after_syndicating_post", $post_id, $post_meta);
        }
    }

    /**
     * Syndicate post to multiple BlueSky accounts
     *
     * @param int $post_id WordPress post ID
     * @param WP_Post $post Post object
     */
    private function syndicate_post_multi_account($post_id, $post)
    {
        do_action("bluesky_before_syndicating_post", $post_id);

        // Check if the post should be syndicated (metabox option)
        $dont_syndicate = get_post_meta(
            $post_id,
            "_bluesky_dont_syndicate",
            true
        );
        if (
            $dont_syndicate ||
            (isset($_POST["bluesky_dont_syndicate"]) &&
                $_POST["bluesky_dont_syndicate"] === "1")
        ) {
            return;
        }

        // Get selected accounts from post meta
        $account_manager = new BlueSky_Account_Manager();
        $selected_accounts_json = get_post_meta(
            $post_id,
            "_bluesky_syndication_accounts",
            true
        );
        $selected_account_ids = !empty($selected_accounts_json)
            ? json_decode($selected_accounts_json, true)
            : [];

        // If no accounts selected, get accounts with auto_syndicate enabled
        if (empty($selected_account_ids)) {
            $all_accounts = $account_manager->get_accounts();
            $selected_account_ids = [];
            foreach ($all_accounts as $acct_key => $account) {
                if (!empty($account['auto_syndicate'])) {
                    $selected_account_ids[] = $account['id'] ?? $acct_key;
                }
            }
        }

        // If still no accounts, return
        if (empty($selected_account_ids)) {
            return;
        }

        // Prepare post data
        $permalink = get_permalink($post_id);

        // Get post excerpt
        $excerpt = "";
        if (!empty($post->post_excerpt)) {
            $excerpt = $post->post_excerpt;
        } else {
            $excerpt = wp_trim_words($post->post_content, 30, "...");
        }

        // Get post image (featured image or first image in content)
        $image_url = "";
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, "large");
        }

        // If no featured image, try to find first image in content
        if (empty($image_url)) {
            preg_match(
                '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i',
                $post->post_content,
                $matches
            );
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        // Get existing syndication results to check per-account syndication status
        $existing_info_json = get_post_meta(
            $post_id,
            "_bluesky_syndication_bs_post_info",
            true
        );
        $existing_info = !empty($existing_info_json)
            ? json_decode($existing_info_json, true)
            : [];

        // Syndicate to each selected account
        $syndication_results = is_array($existing_info) ? $existing_info : [];
        $all_accounts = $account_manager->get_accounts();
        $first_successful_account = null;

        foreach ($selected_account_ids as $account_id) {
            // Skip if already syndicated to this account
            if (isset($syndication_results[$account_id]) &&
                !empty($syndication_results[$account_id]['success'])) {
                continue;
            }

            // Get account data
            if (!isset($all_accounts[$account_id])) {
                continue;
            }
            $account = $all_accounts[$account_id];

            // Create per-account API handler
            $api = BlueSky_API_Handler::create_for_account($account);

            // Syndicate to this account
            $result = $api->syndicate_post_to_bluesky(
                $post->post_title,
                $permalink,
                $excerpt,
                $image_url
            );

            // Store result in account-keyed structure
            if ($result !== false && is_array($result)) {
                $syndication_results[$account_id] = [
                    'uri' => $result['uri'] ?? '',
                    'cid' => $result['cid'] ?? '',
                    'url' => $result['url'] ?? '',
                    'syndicated_at' => time(),
                    'success' => true
                ];

                // Track first successful account for backward compatibility
                if ($first_successful_account === null) {
                    $first_successful_account = $account_id;
                }
            } else {
                $syndication_results[$account_id] = [
                    'uri' => '',
                    'cid' => '',
                    'url' => '',
                    'syndicated_at' => time(),
                    'success' => false
                ];
            }
        }

        // Save results
        update_post_meta(
            $post_id,
            "_bluesky_syndication_bs_post_info",
            wp_json_encode($syndication_results)
        );

        // Mark as syndicated if at least one account succeeded
        $has_any_success = false;
        foreach ($syndication_results as $result) {
            if (!empty($result['success'])) {
                $has_any_success = true;
                break;
            }
        }

        if ($has_any_success) {
            $post_meta = add_post_meta(
                $post_id,
                "_bluesky_syndicated",
                true,
                true
            );

            // Save first successful account ID for backward compatibility
            if ($first_successful_account !== null) {
                update_post_meta(
                    $post_id,
                    "_bluesky_account_id",
                    $first_successful_account
                );
            }

            do_action("bluesky_after_syndicating_post", $post_id, $post_meta);
        }
    }
}
