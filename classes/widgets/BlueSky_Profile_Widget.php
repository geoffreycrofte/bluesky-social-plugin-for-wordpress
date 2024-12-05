<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Profile_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_profile_widget',
            esc_html( __( 'BlueSky Profile', 'bluesky-social' ) ),
            ['description' => esc_html( __('Displays BlueSky profile card', 'bluesky-social') )]
        );
    }

    public function widget($args, $instance) {
        $api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) );
        $bluesky = new BlueSky_Render_Front( $api_handler );
        $profile_card = $bluesky -> render_bluesky_profile_card();

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( __( 'BlueSky Profile', 'bluesky-social' ) ) . $args['after_title'];
        echo $profile_card;
        echo $args['after_widget'];
    }
}