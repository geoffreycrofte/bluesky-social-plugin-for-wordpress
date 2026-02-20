<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Profile_Banner_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_profile_banner_widget',
            esc_html( __( 'Bluesky Profile Banner', 'social-integration-for-bluesky' ) ),
            ['description' => esc_html( __('Displays Bluesky profile banner with full or compact layout', 'social-integration-for-bluesky') )]
        );
    }

    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $layout = !empty($instance['layout']) ? $instance['layout'] : 'full';
        $account_id = !empty($instance['account_id']) ? $instance['account_id'] : '';

        // Create API handler with per-account support
        $account_manager = new BlueSky_Account_Manager();
        $api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) );

        if (!empty($account_id) && $account_manager->is_multi_account_enabled()) {
            $account = $account_manager->get_account($account_id);
            if ($account) {
                $api_handler = BlueSky_API_Handler::create_for_account($account);
            }
        }

        $bluesky = new BlueSky_Render_Front( $api_handler );
        $profile_banner = $bluesky->render_profile_banner(['layout' => $layout, 'account_id' => $account_id]);

        $output = $args['before_widget'];
        if (!empty($title)) {
            $output .= $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        $output .= $profile_banner;
        $output .= $args['after_widget'];

        echo wp_kses( $output, wp_kses_allowed_html('post') );
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $layout = !empty($instance['layout']) ? $instance['layout'] : 'full';
        $account_id = !empty($instance['account_id']) ? $instance['account_id'] : '';

        $account_manager = new BlueSky_Account_Manager();

        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title (optional):', 'social-integration-for-bluesky'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            <small><?php esc_html_e('Leave blank to hide title', 'social-integration-for-bluesky'); ?></small>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('layout')); ?>">
                <?php esc_html_e('Layout:', 'social-integration-for-bluesky'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('layout')); ?>" name="<?php echo esc_attr($this->get_field_name('layout')); ?>">
                <option value="full" <?php selected($layout, 'full'); ?>>
                    <?php esc_html_e('Full Banner', 'social-integration-for-bluesky'); ?>
                </option>
                <option value="compact" <?php selected($layout, 'compact'); ?>>
                    <?php esc_html_e('Compact Card', 'social-integration-for-bluesky'); ?>
                </option>
            </select>
        </p>
        <?php

        // Only show account selector if multi-account enabled and 2+ accounts exist
        if ($account_manager->is_multi_account_enabled()) {
            $accounts = $account_manager->get_accounts();
            if (count($accounts) >= 1) {
                ?>
                <p>
                    <label for="<?php echo esc_attr($this->get_field_id('account_id')); ?>">
                        <?php esc_html_e('Display Account:', 'social-integration-for-bluesky'); ?>
                    </label>
                    <select class="widefat" id="<?php echo esc_attr($this->get_field_id('account_id')); ?>" name="<?php echo esc_attr($this->get_field_name('account_id')); ?>">
                        <option value="" <?php selected($account_id, ''); ?>>
                            <?php esc_html_e('Active Account (default)', 'social-integration-for-bluesky'); ?>
                        </option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo esc_attr($account['id']); ?>" <?php selected($account_id, $account['id']); ?>>
                                <?php echo esc_html(sprintf('%s (@%s)', $account['name'] ?? $account['handle'], $account['handle'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <?php
            }
        }
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['layout'] = !empty($new_instance['layout']) ? sanitize_text_field($new_instance['layout']) : 'full';
        $instance['account_id'] = !empty($new_instance['account_id']) ? sanitize_text_field($new_instance['account_id']) : '';
        return $instance;
    }
}
