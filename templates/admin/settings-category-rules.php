<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $this (the BlueSky_Settings_Service instance)

// Get accounts
$accounts = $this->account_manager ? $this->account_manager->get_accounts() : [];

// Filter to only auto-syndicate accounts
$syndicate_accounts = array_filter($accounts, function ($account) {
    return !empty($account['auto_syndicate']);
});

// Get all categories
$categories = get_categories(['hide_empty' => false]);

// Check if we have data to display
$has_accounts = !empty($accounts);
$has_syndicate_accounts = !empty($syndicate_accounts);
$has_categories = !empty($categories);
?>

<div class="bluesky-category-rules-section">
    <?php if (!$has_accounts) : ?>
        <p class="description">
            <?php esc_html_e('Connect at least one Bluesky account to configure category rules.', 'social-integration-for-bluesky'); ?>
        </p>
    <?php elseif (!$has_syndicate_accounts) : ?>
        <p class="description">
            <?php esc_html_e('Enable auto-syndicate on at least one account to configure category rules.', 'social-integration-for-bluesky'); ?>
        </p>
    <?php elseif (!$has_categories) : ?>
        <p class="description">
            <?php esc_html_e('Create categories in WordPress to set up syndication rules.', 'social-integration-for-bluesky'); ?>
        </p>
    <?php else : ?>
            <?php foreach ($syndicate_accounts as $account_id => $account) :
                $account_name = $account['name'] ?? __('Unknown Account', 'social-integration-for-bluesky');
                $account_handle = $account['handle'] ?? '';
                $category_rules = $account['category_rules'] ?? ['include' => [], 'exclude' => []];
                $include_categories = $category_rules['include'] ?? [];
                $exclude_categories = $category_rules['exclude'] ?? [];
            ?>
                <div class="bluesky-category-rules-account">
                    <h4>
                        <?php echo esc_html($account_name); ?>
                        <?php if ($account_handle) : ?>
                            <span class="account-handle">
                                (<?php echo esc_html($account_handle); ?>)
                            </span>
                        <?php endif; ?>
                    </h4>
                    <div class="category-include-exclude-container">
                        <!-- Include Categories -->
                        <div>
                            <h5>
                                <?php esc_html_e('Include Categories', 'social-integration-for-bluesky'); ?>
                            </h5>
                            <p class="description">
                                <?php esc_html_e('Only syndicate posts with at least one of these categories (OR logic).', 'social-integration-for-bluesky'); ?>
                            </p>
                            <div class="categories-container">
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
                            <h5>
                                <?php esc_html_e('Exclude Categories', 'social-integration-for-bluesky'); ?>
                            </h5>
                            <p class="description">
                                <?php esc_html_e('Never syndicate posts with any of these categories (higher priority than include).', 'social-integration-for-bluesky'); ?>
                            </p>
                            <div class="categories-container">
                                <?php foreach ($categories as $category) : ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="bluesky_category_rules[<?php echo esc_attr($account_id); ?>][exclude][]"
                                            value="<?php echo esc_attr($category->term_id); ?>"
                                            <?php checked(in_array($category->term_id, $exclude_categories)); ?>
                                        />
                                        <?php echo esc_html($category->name); ?>
                                        <span class="small-description">(<?php echo esc_html($category->count); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
    <?php endif; ?>
</div>
