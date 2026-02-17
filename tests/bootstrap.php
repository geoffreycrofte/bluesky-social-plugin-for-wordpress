<?php
/**
 * PHPUnit bootstrap file for Bluesky Social Plugin tests.
 *
 * Defines WordPress constants and loads Brain Monkey for WP function mocking.
 * Does NOT require WordPress core files or wp-load.php.
 */

// Define WordPress constants required by plugin classes
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'BLUESKY_PLUGIN_VERSION' ) ) {
    define( 'BLUESKY_PLUGIN_VERSION', '1.5.0' );
}

if ( ! defined( 'BLUESKY_PLUGIN_FILE' ) ) {
    define( 'BLUESKY_PLUGIN_FILE', dirname( __DIR__ ) . '/social-integration-for-bluesky.php' );
}

if ( ! defined( 'BLUESKY_PLUGIN_BASENAME' ) ) {
    define( 'BLUESKY_PLUGIN_BASENAME', 'bluesky-social-plugin-for-wordpress/social-integration-for-bluesky.php' );
}

if ( ! defined( 'BLUESKY_PLUGIN_FOLDER' ) ) {
    define( 'BLUESKY_PLUGIN_FOLDER', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'BLUESKY_PLUGIN_DIRECTORY_NAME' ) ) {
    define( 'BLUESKY_PLUGIN_DIRECTORY_NAME', 'bluesky-social-plugin-for-wordpress' );
}

if ( ! defined( 'BLUESKY_PLUGIN_SETTING_PAGENAME' ) ) {
    define( 'BLUESKY_PLUGIN_SETTING_PAGENAME', 'bluesky-social-settings' );
}

if ( ! defined( 'BLUESKY_PLUGIN_OPTIONS' ) ) {
    define( 'BLUESKY_PLUGIN_OPTIONS', 'bluesky_settings' );
}

if ( ! defined( 'BLUESKY_PLUGIN_TRANSIENT' ) ) {
    define( 'BLUESKY_PLUGIN_TRANSIENT', 'bluesky_cache_' . BLUESKY_PLUGIN_VERSION );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
    define( 'WEEK_IN_SECONDS', 604800 );
}

// Load Brain Monkey via autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load classes under test
require_once dirname( __DIR__ ) . '/classes/BlueSky_Helpers.php';
require_once dirname( __DIR__ ) . '/classes/BlueSky_Account_Manager.php';
require_once dirname( __DIR__ ) . '/classes/BlueSky_API_Handler.php';
require_once dirname( __DIR__ ) . '/classes/BlueSky_AJAX_Service.php';
require_once dirname( __DIR__ ) . '/classes/BlueSky_Syndication_Service.php';
require_once dirname( __DIR__ ) . '/classes/BlueSky_Settings_Service.php';
