<?php
/*
Plugin Name: BlueSky Social Integration
Description: Integrates BlueSky social features into WordPress including: Post New Articles on BlueSky for you, Shortcode for Profile Card, and Widget to display your last posts.
Version: 1.0.0
Author: Geoffrey Crofte
*/

// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

define('BLUESKY_PLUGIN_VERSION', '1.0.0' );
define('BLUESKY_PLUGIN_FOLDER', plugin_dir_url(__FILE__) );
define('BLUESKY_PLUGIN_DIRECTORY_NAME', dirname( plugin_basename( __FILE__ ) ) );
define('BLUESKY_PLUGIN_OPTIONS', 'bluesky_settings' );
define('BLUESKY_PLUGIN_TRANSIENT', 'bluesky_cache_' . BLUESKY_PLUGIN_VERSION );

// Core features (attempt)
require_once( 'classes/BlueSky_Helpers.php' ); // V.1
require_once( 'classes/BlueSky_API_Handler.php' ); // V.1
require_once( 'classes/BlueSky_Plugin_Setup.php' ); // V.1
require_once( 'classes/BlueSky_Render_Front.php' ); // V.1

// Widgets
require_once( 'classes/widgets/BlueSky_Posts_Widget.php' );
require_once( 'classes/widgets/BlueSky_Profile_Widget.php' );

// Initialize the plugin
$bluesky_api_handler = new BlueSky_API_Handler( get_option( BLUESKY_PLUGIN_OPTIONS ) ); // V.1
$bluesky_social_integration = new BlueSky_Plugin_Setup( $bluesky_api_handler ); // V.1
$bluesky_render_front = new BlueSky_Render_Front( $bluesky_api_handler ); // V.1
