<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Profile_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'bluesky_profile_widget',
            __( 'BlueSky Profile', 'bluesky-social' ),
            ['description' => 'Displays BlueSky profile card']
        );
    }

    public function widget($args, $instance) {
        $bluesky = new BlueSky_Social_Integration();
        $profile_card = $bluesky->bluesky_profile_card_shortcode();

        echo $args['before_widget'];
        echo $args['before_title'] . __( 'BlueSky Profile', 'bluesky-social' ) . $args['after_title'];
        echo $profile_card;
        echo $args['after_widget'];
    }
}