<?php
// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlueSky Account Manager
 *
 * Manages multi-account data storage, CRUD operations, version-gated migration,
 * and feature toggle checking. This is the foundation for multi-account functionality.
 *
 * @since 1.5.0
 */
class BlueSky_Account_Manager {
    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Constructor
     * Loads settings and registers migration hook.
     */
    public function __construct() {
        $this->options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
        add_action('plugins_loaded', [$this, 'maybe_run_migration']);
    }

    /**
     * Check if multi-account feature is enabled
     *
     * @return bool True if multi-account is enabled
     */
    public function is_multi_account_enabled() {
        return isset($this->options['enable_multi_account']) ? (bool) $this->options['enable_multi_account'] : false;
    }

    /**
     * Maybe run migration on plugins_loaded
     * Only runs when multi-account is enabled and schema version is outdated.
     */
    public function maybe_run_migration() {
        // Emergency bypass for migration issues — use: add_filter('bluesky_skip_migration', '__return_true');
        if (apply_filters('bluesky_skip_migration', false)) {
            return;
        }

        // Only run migration if multi-account is enabled
        if (!$this->is_multi_account_enabled()) {
            return;
        }

        // Check schema version
        $schema_version = get_option('bluesky_schema_version', 1);

        if ($schema_version < 2) {
            $result = $this->migrate_to_v2();

            if (is_wp_error($result)) {
                error_log('BlueSky migration failed: ' . $result->get_error_message());
                add_action('admin_notices', function() use ($result) {
                    if (current_user_can('manage_options')) {
                        printf(
                            '<div class="notice notice-error"><p>%s</p></div>',
                            sprintf(
                                esc_html__('BlueSky migration failed: %s', 'social-integration-for-bluesky'),
                                esc_html($result->get_error_message())
                            )
                        );
                    }
                });
            } else {
                update_option('bluesky_schema_version', 2);
            }
        }
    }

    /**
     * Migrate from single-account to multi-account structure
     *
     * @return true|WP_Error True on success, WP_Error on failure
     */
    public function migrate_to_v2() {
        // Idempotency check: if accounts already exist, migration is done
        if (get_option('bluesky_accounts')) {
            return true;
        }

        // Read current settings
        $old_settings = get_option(BLUESKY_PLUGIN_OPTIONS, []);

        // If no handle or app_password, this is a fresh install - nothing to migrate
        if (empty($old_settings['handle']) || empty($old_settings['app_password'])) {
            return true;
        }

        // Generate UUID for primary account
        $uuid = BlueSky_Helpers::bluesky_generate_secure_uuid();

        // Try to get DID from settings first, then from transient (where authenticate() stores it)
        $helpers = new BlueSky_Helpers();
        $did = !empty($old_settings['did']) ? $old_settings['did'] : get_transient($helpers->get_did_transient_key());

        // Create primary account entry
        $primary_account = [
            'id' => $uuid,
            'name' => 'Primary Account',
            'handle' => $old_settings['handle'],
            'app_password' => $old_settings['app_password'], // Already encrypted, copy as-is
            'did' => $did ?: '',
            'is_active' => true,
            'auto_syndicate' => true,
            'owner_id' => 0,
            'created_at' => time()
        ];

        // Save to new bluesky_accounts option
        $accounts = [$uuid => $primary_account];
        update_option('bluesky_accounts', $accounts);

        // Set active account
        update_option('bluesky_active_account', $uuid);

        // Separate global settings from account-specific settings
        $global_settings = $old_settings;
        unset($global_settings['handle']);
        unset($global_settings['app_password']);
        unset($global_settings['did']);
        update_option('bluesky_global_settings', $global_settings);

        // Backup old settings
        update_option('bluesky_settings_backup', $old_settings);
        update_option('bluesky_settings_backup_date', time());

        // Backfill post account associations
        $this->backfill_post_account_associations($uuid);

        return true;
    }

    /**
     * Backfill post meta for previously syndicated posts
     *
     * @param string $primary_account_id UUID of the primary account
     */
    public function backfill_post_account_associations($primary_account_id) {
        global $wpdb;

        // Find all posts that were syndicated (have _bluesky_syndicated = '1')
        $syndicated_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                '_bluesky_syndicated',
                '1'
            )
        );

        foreach ($syndicated_posts as $post) {
            // Add account ID meta only if not already set (idempotency)
            $existing_account_id = get_post_meta($post->post_id, '_bluesky_account_id', true);
            if (empty($existing_account_id)) {
                add_post_meta($post->post_id, '_bluesky_account_id', $primary_account_id, true);
            }

            // Transform existing syndication info to account-keyed structure
            $existing_info = get_post_meta($post->post_id, '_bluesky_syndication_bs_post_info', true);
            if (!empty($existing_info) && is_string($existing_info)) {
                // If it's a JSON string, convert to account-keyed format
                $decoded = json_decode($existing_info, true);
                if ($decoded !== null) {
                    $account_keyed = [
                        $primary_account_id => $decoded
                    ];
                    update_post_meta($post->post_id, '_bluesky_syndication_bs_post_info', json_encode($account_keyed));
                }
            }
        }
    }

    /**
     * Get all accounts
     *
     * @return array Array of account objects
     */
    public function get_accounts() {
        // If multi-account is disabled, return single account from current settings
        if (!$this->is_multi_account_enabled()) {
            $settings = get_option(BLUESKY_PLUGIN_OPTIONS, []);
            if (!empty($settings['handle']) && !empty($settings['app_password'])) {
                return [
                    'default' => [
                        'id' => 'default',
                        'name' => 'Default Account',
                        'handle' => $settings['handle'],
                        'app_password' => $settings['app_password'],
                        'did' => !empty($settings['did']) ? $settings['did'] : get_transient((new BlueSky_Helpers())->get_did_transient_key()),
                        'is_active' => true,
                        'auto_syndicate' => true,
                        'owner_id' => 0,
                        'created_at' => 0
                    ]
                ];
            }
            return [];
        }

        return get_option('bluesky_accounts', []);
    }

    /**
     * Get a single account by ID
     *
     * @param string $account_id Account UUID
     * @return array|null Account data or null if not found
     */
    public function get_account($account_id) {
        $accounts = $this->get_accounts();
        return isset($accounts[$account_id]) ? $accounts[$account_id] : null;
    }

    /**
     * Get the active account
     *
     * @return array|null Active account data or null if none found
     */
    public function get_active_account() {
        $active_id = get_option('bluesky_active_account');
        $accounts = $this->get_accounts();

        // If active ID is set and exists, return it
        if ($active_id && isset($accounts[$active_id])) {
            return $accounts[$active_id];
        }

        // Fall back to first account
        if (!empty($accounts)) {
            return reset($accounts);
        }

        return null;
    }

    /**
     * Add a new account
     *
     * @param array $data Account data (handle, app_password required)
     * @return string|WP_Error Account ID on success, WP_Error on failure
     */
    public function add_account($data) {
        // Validate required fields
        if (empty($data['handle'])) {
            return new WP_Error('missing_handle', __('Account handle is required.', 'social-integration-for-bluesky'));
        }
        if (empty($data['app_password'])) {
            return new WP_Error('missing_password', __('App password is required.', 'social-integration-for-bluesky'));
        }

        // Check for duplicate handle
        $existing_accounts = get_option('bluesky_accounts', []);
        $normalized_handle = strtolower(sanitize_text_field($data['handle']));
        foreach ($existing_accounts as $existing) {
            if (strtolower($existing['handle'] ?? '') === $normalized_handle) {
                return new WP_Error(
                    'duplicate_handle',
                    sprintf(
                        __('An account with handle "%s" already exists.', 'social-integration-for-bluesky'),
                        $data['handle']
                    )
                );
            }
        }

        // Generate UUID
        $uuid = BlueSky_Helpers::bluesky_generate_secure_uuid();

        // Encrypt app password
        $helpers = new BlueSky_Helpers();
        $encrypted_password = $helpers->bluesky_encrypt($data['app_password']);
        if ($encrypted_password === false) {
            return new WP_Error('encryption_failed', __('Failed to encrypt app password.', 'social-integration-for-bluesky'));
        }

        // Create account entry
        $account = [
            'id' => $uuid,
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : 'Account',
            'handle' => sanitize_text_field($data['handle']),
            'app_password' => $encrypted_password,
            'did' => isset($data['did']) ? sanitize_text_field($data['did']) : '',
            'is_active' => false, // Will be set to true if first account
            'auto_syndicate' => isset($data['auto_syndicate']) ? (bool) $data['auto_syndicate'] : true,
            'owner_id' => isset($data['owner_id']) ? intval($data['owner_id']) : 0,
            'created_at' => time(),
            'category_rules' => ['include' => [], 'exclude' => []]
        ];

        // Get existing accounts
        $accounts = get_option('bluesky_accounts', []);

        // If this is the first account, mark it as active
        if (empty($accounts)) {
            $account['is_active'] = true;
            update_option('bluesky_active_account', $uuid);
        }

        // Add account
        $accounts[$uuid] = $account;
        update_option('bluesky_accounts', $accounts);

        return $uuid;
    }

    /**
     * Remove an account
     *
     * @param string $account_id Account UUID
     * @return int|WP_Error Orphaned post count on success, WP_Error on failure
     */
    public function remove_account($account_id) {
        $accounts = get_option('bluesky_accounts', []);

        if (!isset($accounts[$account_id])) {
            return new WP_Error('account_not_found', __('Account not found.', 'social-integration-for-bluesky'));
        }

        // Count orphaned posts
        global $wpdb;
        $orphaned_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                '_bluesky_account_id',
                $account_id
            )
        );

        // Remove account
        unset($accounts[$account_id]);

        // If removing active account, switch to first remaining
        $active_id = get_option('bluesky_active_account');
        if ($active_id === $account_id && !empty($accounts)) {
            $first_account = reset($accounts);
            $this->set_active_account($first_account['id']);
        } elseif (empty($accounts)) {
            delete_option('bluesky_active_account');
        }

        update_option('bluesky_accounts', $accounts);

        // Clear account cache
        do_action('bluesky_clear_account_cache', $account_id);

        return intval($orphaned_count);
    }

    /**
     * Set the active account
     *
     * @param string $account_id Account UUID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function set_active_account($account_id) {
        $accounts = get_option('bluesky_accounts', []);

        if (!isset($accounts[$account_id])) {
            return new WP_Error('account_not_found', __('Account not found.', 'social-integration-for-bluesky'));
        }

        // Update is_active flag on all accounts
        foreach ($accounts as $id => $account) {
            $accounts[$id]['is_active'] = ($id === $account_id);
        }

        update_option('bluesky_accounts', $accounts);
        update_option('bluesky_active_account', $account_id);

        return true;
    }

    /**
     * Update an account
     *
     * @param string $account_id Account UUID
     * @param array $data Account data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_account($account_id, $data) {
        $accounts = get_option('bluesky_accounts', []);

        if (!isset($accounts[$account_id])) {
            return new WP_Error('account_not_found', __('Account not found.', 'social-integration-for-bluesky'));
        }

        // Update allowed fields
        if (isset($data['name'])) {
            $accounts[$account_id]['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['handle'])) {
            $accounts[$account_id]['handle'] = sanitize_text_field($data['handle']);
        }
        if (isset($data['did'])) {
            $accounts[$account_id]['did'] = sanitize_text_field($data['did']);
        }
        if (isset($data['auto_syndicate'])) {
            $accounts[$account_id]['auto_syndicate'] = (bool) $data['auto_syndicate'];
        }
        if (isset($data['app_password'])) {
            // Encrypt new password
            $helpers = new BlueSky_Helpers();
            $encrypted_password = $helpers->bluesky_encrypt($data['app_password']);
            if ($encrypted_password === false) {
                return new WP_Error('encryption_failed', __('Failed to encrypt app password.', 'social-integration-for-bluesky'));
            }
            $accounts[$account_id]['app_password'] = $encrypted_password;
        }
        if (isset($data['category_rules'])) {
            $accounts[$account_id]['category_rules'] = [
                'include' => array_map('intval', $data['category_rules']['include'] ?? []),
                'exclude' => array_map('intval', $data['category_rules']['exclude'] ?? [])
            ];
        }

        update_option('bluesky_accounts', $accounts);

        return true;
    }

    /**
     * Check if a post should be syndicated to a specific account based on category rules
     *
     * @param int $post_id Post ID
     * @param string $account_id Account UUID
     * @return bool True if post should be syndicated, false otherwise
     */
    public function should_syndicate_to_account($post_id, $account_id) {
        $account = $this->get_account($account_id);
        if (!$account) {
            return false;
        }

        // Get category rules for this account
        $category_rules = $account['category_rules'] ?? ['include' => [], 'exclude' => []];
        $include_rules = $category_rules['include'] ?? [];
        $exclude_rules = $category_rules['exclude'] ?? [];

        // If no rules are set (both empty), syndicate everything
        if (empty($include_rules) && empty($exclude_rules)) {
            return true;
        }

        // Get post categories
        $post_categories = get_the_category($post_id);
        $post_category_ids = [];
        foreach ($post_categories as $category) {
            $post_category_ids[] = $category->term_id;
        }

        // Check exclude rules first (higher priority)
        if (!empty($exclude_rules)) {
            foreach ($post_category_ids as $cat_id) {
                if (in_array($cat_id, $exclude_rules)) {
                    return false; // Post has an excluded category
                }
            }
        }

        // Check include rules (OR logic)
        if (!empty($include_rules)) {
            // If include rules exist but post has no categories, don't syndicate
            if (empty($post_category_ids)) {
                return false;
            }

            // Post needs at least one included category
            foreach ($post_category_ids as $cat_id) {
                if (in_array($cat_id, $include_rules)) {
                    return true; // Found at least one included category
                }
            }
            return false; // Post has categories but none are included
        }

        // If only exclude rules exist and post passed the exclude check, syndicate
        return true;
    }

    /**
     * Get the discussion display account
     *
     * @return string|null Account ID or null
     */
    public function get_discussion_account() {
        $global_settings = get_option('bluesky_global_settings', []);

        if (isset($global_settings['discussion_account'])) {
            return $global_settings['discussion_account'];
        }

        // Fall back to active account
        $active = $this->get_active_account();
        return $active ? ($active['id'] ?? null) : null;
    }

    /**
     * Set the discussion display account
     *
     * @param string $account_id Account UUID
     * @return bool True on success
     */
    public function set_discussion_account($account_id) {
        $global_settings = get_option('bluesky_global_settings', []);
        $global_settings['discussion_account'] = $account_id;
        update_option('bluesky_global_settings', $global_settings);

        return true;
    }
}
