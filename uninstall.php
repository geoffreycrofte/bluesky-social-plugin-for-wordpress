<?php
// If this file is called directly, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// Delete plugin options
delete_option( 'bluesky_settings' ); // Main plugin options
delete_option( 'bluesky_settings_secret' ); // API secret or key, if applicable

// Fetch all options
$options = wp_load_alloptions();
// Delete all transients related to the plugin
// Loop through options to find and delete relevant transients
foreach ( $options as $option_name => $option_value ) {
    if ( strpos( $option_name, '_transient_bluesky_' ) === 0 ) {
        // Strip '_transient_' to get the actual transient key
        $transient_key = str_replace( '_transient_', '', $option_name );
        delete_transient( $transient_key );
    }
}
