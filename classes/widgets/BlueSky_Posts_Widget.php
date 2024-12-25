<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_posts_widget',
            esc_html( __( 'BlueSky Latest Posts', 'social-integration-for-bluesky' ) ),
            ['description' => esc_html( __('Displays latest BlueSky posts', 'social-integration-for-bluesky') )]
        );
    }

    public function widget( $args, $instance ) {
        $api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) );
        $bluesky = new BlueSky_Render_Front( $api_handler );
        $posts = $bluesky -> render_bluesky_posts_list();

        $output = $args['before_widget'];
        $output .= $args['before_title'] . __( 'BlueSky Latest Posts', 'social-integration-for-bluesky' ) . $args['after_title'];
        $output .= $posts;
        $output .= $args['after_widget'];

        echo wp_kses( $output, wp_kses_allowed_html('post') );
    }
}