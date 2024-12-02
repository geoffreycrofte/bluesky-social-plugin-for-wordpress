<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_posts_widget',
            __( 'BlueSky Latest Posts', 'bluesky-social' ),
            ['description' => 'Displays latest BlueSky posts']
        );
    }

    public function widget( $args, $instance ) {
        $bluesky = new BlueSky_Social_Integration();
        $posts = $bluesky -> render_bluesky_posts_list();

        echo $args['before_widget'];
        echo $args['before_title'] . __( 'BlueSky Latest Posts', 'bluesky-social' ) . $args['after_title'];
        echo $posts;
        echo $args['after_widget'];
    }
}