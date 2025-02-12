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
        $api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) );
        $bluesky = new BlueSky_Render_Front( $api_handler );
        $profile_card = $bluesky -> render_bluesky_profile_card();
        $styles = $bluesky -> get_inline_custom_styles('posts');

        $output = $args['before_widget'];
        $output .= $args['before_title'] . __( 'BlueSky Profile', 'social-integration-for-bluesky' ) . $args['after_title'];
        $output .= $profile_card;
        $output .= $args['after_widget'];

        echo wp_kses( $output, wp_kses_allowed_html('post') ) . "\n" . $styles;
    }
}