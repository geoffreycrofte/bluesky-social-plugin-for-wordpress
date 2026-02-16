<?php
/*
Plugin Name: Social Integration for BlueSky
Description: Integrates BlueSky social features into WordPress including: Post New Articles on BlueSky for you, Shortcode for Profile Card, and Widget to display your last posts. You can also use shortcodes <code>[bluesky_profile]</code> and <code>[bluesky_last_posts]</code>.
Version: 1.5.0
Requires at least: 5.0
Requires PHP: 7.4
Author: Geoffrey Crofte
Author URI: https://geoffreycrofte.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: social-integration-for-bluesky
Domain Path: /languages
*/

// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

define("BLUESKY_PLUGIN_VERSION", "1.5.0");
define("BLUESKY_PLUGIN_FILE", __FILE__);
define("BLUESKY_PLUGIN_BASENAME", plugin_basename(__FILE__));
define("BLUESKY_PLUGIN_FOLDER", plugin_dir_url(__FILE__));
define("BLUESKY_PLUGIN_DIRECTORY_NAME", dirname(plugin_basename(__FILE__)));
define("BLUESKY_PLUGIN_SETTING_PAGENAME", "bluesky-social-settings");
define("BLUESKY_PLUGIN_OPTIONS", "bluesky_settings");
define("BLUESKY_PLUGIN_TRANSIENT", "bluesky_cache_" . BLUESKY_PLUGIN_VERSION);

// Core features (attempt)
require_once "classes/BlueSky_Helpers.php"; // V.1
require_once "classes/BlueSky_Account_Manager.php"; // V.1.5.0
require_once "classes/BlueSky_API_Handler.php"; // V.1
require_once "classes/BlueSky_Plugin_Setup.php"; // V.1
require_once "classes/BlueSky_Render_Front.php"; // V.1
require_once "classes/BlueSky_Post_Metabox.php"; // V.1.1
require_once "classes/BlueSky_Admin_Actions.php"; // V.1.4.0
require_once "classes/BlueSky_Discussion_Display.php"; // V.1.5.0

// Widgets
require_once "classes/widgets/BlueSky_Posts_Widget.php";
require_once "classes/widgets/BlueSky_Profile_Widget.php";

// Initialize the plugin
$bluesky_account_manager = new BlueSky_Account_Manager(); // V.1.5.0
$bluesky_api_handler = new BlueSky_API_Handler(
    get_option(BLUESKY_PLUGIN_OPTIONS),
); // V.1
$bluesky_social_integration = new BlueSky_Plugin_Setup($bluesky_api_handler, $bluesky_account_manager); // V.1
$bluesky_render_front = new BlueSky_Render_Front($bluesky_api_handler); // V.1
new BlueSky_Admin_Actions(); // V.1.4.0
new BlueSky_Post_Metabox(); // V.1.1.0
new BlueSky_Discussion_Display($bluesky_api_handler); // V.1.5.0
