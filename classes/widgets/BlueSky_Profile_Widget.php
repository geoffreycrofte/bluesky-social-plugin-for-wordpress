<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Profile_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_profile_widget',
            esc_html( __( 'BlueSky Profile', 'social-integration-for-bluesky' ) ),
            ['description' => esc_html( __('Displays BlueSky profile card', 'social-integration-for-bluesky') )]
        );
    }

    public function widget($args, $instance) {
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
        $profile_card = $bluesky -> render_bluesky_profile_card(['account_id' => $account_id]);
        $styles = $bluesky -> get_inline_custom_styles('profile');

        $output = $args['before_widget'];
        $output .= $args['before_title'] . __( 'BlueSky Profile', 'social-integration-for-bluesky' ) . $args['after_title'];
        $output .= $profile_card;
        $output .= $args['after_widget'];

        echo wp_kses( $output, wp_kses_allowed_html('post') ) . "\n" . $styles;
    }

    public function form($instance) {
        $account_id = !empty($instance['account_id']) ? $instance['account_id'] : '';

        $account_manager = new BlueSky_Account_Manager();

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
        $instance['account_id'] = !empty($new_instance['account_id']) ? sanitize_text_field($new_instance['account_id']) : '';
        return $instance;
    }
}