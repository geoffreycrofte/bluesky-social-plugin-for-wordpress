<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $this (the BlueSky_Settings_Service instance)

// Get accounts
$accounts = $this->account_manager ? $this->account_manager->get_accounts() : [];

// Get all categories
$categories = get_categories(['hide_empty' => false]);

// Check if we have data to display
$has_accounts = !empty($accounts);
$has_categories = !empty($categories);
?>

<div class="bluesky-category-rules-section">
    <?php if (!$has_accounts) : ?>
        <p class="description">
            <?php esc_html_e('Connect at least one Bluesky account to configure category rules.', 'social-integration-for-bluesky'); ?>
        </p>
    <?php elseif (!$has_categories) : ?>
        <p class="description">
            <?php esc_html_e('Create categories in WordPress to set up syndication rules.', 'social-integration-for-bluesky'); ?>
        </p>
    <?php else : ?>
        <form method="post" action="">
            <?php wp_nonce_field('bluesky_category_rules_nonce', '_bluesky_category_rules_nonce'); ?>

            <?php foreach ($accounts as $account_id => $account) :
                $account_name = $account['name'] ?? __('Unknown Account', 'social-integration-for-bluesky');
                $account_handle = $account['handle'] ?? '';
                $category_rules = $account['category_rules'] ?? ['include' => [], 'exclude' => []];
                $include_categories = $category_rules['include'] ?? [];
                $exclude_categories = $category_rules['exclude'] ?? [];
            ?>
                <div class="bluesky-category-rules-account" style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
                    <h4 style="margin-top: 0;">
                        <?php echo esc_html($account_name); ?>
                        <?php if ($account_handle) : ?>
                            <span style="color: #666; font-weight: normal; font-size: 0.9em;">
                                (<?php echo esc_html($account_handle); ?>)
                            </span>
                        <?php endif; ?>
                    </h4>

                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e('Include: Only syndicate posts with these categories. Exclude: Never syndicate posts with these categories. If no rules are set, all posts syndicate.', 'social-integration-for-bluesky'); ?>
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Include Categories -->
                        <div>
                            <h5 style="margin-top: 0; margin-bottom: 10px;">
                                <?php esc_html_e('Include Categories', 'social-integration-for-bluesky'); ?>
                            </h5>
                            <p class="description" style="margin-bottom: 10px;">
                                <?php esc_html_e('Only syndicate posts with at least one of these categories (OR logic).', 'social-integration-for-bluesky'); ?>
                            </p>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php foreach ($categories as $category) : ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input
                                            type="checkbox"
                                            name="bluesky_category_rules[<?php echo esc_attr($account_id); ?>][include][]"
                                            value="<?php echo esc_attr($category->term_id); ?>"
                                            <?php checked(in_array($category->term_id, $include_categories)); ?>
                                        />
                                        <?php echo esc_html($category->name); ?>
                                        <span style="color: #666; font-size: 0.9em;">(<?php echo esc_html($category->count); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Exclude Categories -->
                        <div>
                            <h5 style="margin-top: 0; margin-bottom: 10px;">
                                <?php esc_html_e('Exclude Categories', 'social-integration-for-bluesky'); ?>
                            </h5>
                            <p class="description" style="margin-bottom: 10px;">
                                <?php esc_html_e('Never syndicate posts with any of these categories (higher priority than include).', 'social-integration-for-bluesky'); ?>
                            </p>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php foreach ($categories as $category) : ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input
                                            type="checkbox"
                                            name="bluesky_category_rules[<?php echo esc_attr($account_id); ?>][exclude][]"
                                            value="<?php echo esc_attr($category->term_id); ?>"
                                            <?php checked(in_array($category->term_id, $exclude_categories)); ?>
                                        />
                                        <?php echo esc_html($category->name); ?>
                                        <span style="color: #666; font-size: 0.9em;">(<?php echo esc_html($category->count); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <p class="submit">
                <button type="submit" name="bluesky_save_category_rules" class="button button-primary button-large">
                    <?php esc_html_e('Save Category Rules', 'social-integration-for-bluesky'); ?>
                </button>
            </p>
        </form>
    <?php endif; ?>
</div>
