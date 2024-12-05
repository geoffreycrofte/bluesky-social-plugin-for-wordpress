<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_posts_widget',
            esc_html( __( 'BlueSky Latest Posts', 'bluesky-social' ) ),
            ['description' => esc_html( __('Displays latest BlueSky posts', 'bluesky-social') )]
        );
    }

    public function widget( $args, $instance ) {
        $api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) );
        $bluesky = new BlueSky_Render_Front( $api_handler );
        $posts = $bluesky -> render_bluesky_posts_list();

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( __( 'BlueSky Latest Posts', 'bluesky-social' ) ) . $args['after_title'];
        echo $posts;
        echo $args['after_widget'];
    }
}