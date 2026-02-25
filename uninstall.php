<?php
// If this file is called directly, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

global $wpdb;

// =============================================================================
// 1. Delete plugin options
// =============================================================================
delete_option( 'bluesky_settings' );              // Main plugin options (BLUESKY_PLUGIN_OPTIONS)
delete_option( 'bluesky_settings_secret' );        // Encryption key
delete_option( 'bluesky_settings__activation_date' ); // Activation date (from v1.3.0)

// Multi-account options
delete_option( 'bluesky_accounts' );               // Multi-account data
delete_option( 'bluesky_active_account' );          // Active account UUID
delete_option( 'bluesky_global_settings' );         // Global multi-account settings
delete_option( 'bluesky_schema_version' );          // DB schema version
delete_option( 'bluesky_settings_backup' );         // Migration backup
delete_option( 'bluesky_settings_backup_date' );    // Migration backup date

// Activity log
delete_option( 'bluesky_activity_log' );

// Auth errors
delete_option( 'bluesky_account_auth_errors' );

// =============================================================================
// 2. Delete all transients related to the plugin
// =============================================================================

// Pattern-based transient cleanup via direct DB query (most efficient)
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_bluesky_cache_%'
     OR option_name LIKE '_transient_timeout_bluesky_cache_%'
     OR option_name LIKE '_transient_bluesky_circuit_%'
     OR option_name LIKE '_transient_timeout_bluesky_circuit_%'
     OR option_name LIKE '_transient_bluesky_failures_%'
     OR option_name LIKE '_transient_timeout_bluesky_failures_%'
     OR option_name LIKE '_transient_bluesky_rate_limit_%'
     OR option_name LIKE '_transient_timeout_bluesky_rate_limit_%'
     OR option_name LIKE '_transient_bluesky_rate_attempts_%'
     OR option_name LIKE '_transient_timeout_bluesky_rate_attempts_%'
     OR option_name LIKE '_transient_bluesky_discussion_%'
     OR option_name LIKE '_transient_timeout_bluesky_discussion_%'
     OR option_name LIKE '_transient_bluesky_health_%'
     OR option_name LIKE '_transient_timeout_bluesky_health_%'
     OR option_name LIKE '_transient_bluesky_logout_%'
     OR option_name LIKE '_transient_timeout_bluesky_logout_%'
     OR option_name LIKE '_transient_bluesky_refreshing_%'
     OR option_name LIKE '_transient_timeout_bluesky_refreshing_%'"
);

// =============================================================================
// 3. Delete post meta created by the plugin
// =============================================================================
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN (
         '_bluesky_dont_syndicate',
         '_bluesky_syndicated',
         '_bluesky_syndication_accounts',
         '_bluesky_syndication_text',
         '_bluesky_syndication_status',
         '_bluesky_syndication_scheduled',
         '_bluesky_syndication_bs_post_info',
         '_bluesky_syndication_failed_accounts',
         '_bluesky_syndication_accounts_completed',
         '_bluesky_syndication_retry_count',
         '_bluesky_account_id'
     )"
);

// =============================================================================
// 4. Delete user meta created by the plugin
// =============================================================================
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta}
     WHERE meta_key IN (
         'bluesky_expired_creds_dismissed',
         'bluesky_circuit_breaker_dismissed'
     )"
);

// =============================================================================
// 5. Clean up Action Scheduler entries (if Action Scheduler is available)
// =============================================================================
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
    // Check if the actionscheduler tables exist
    $as_table = $wpdb->prefix . 'actionscheduler_actions';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $as_table ) ) === $as_table ) {
        $wpdb->query(
            "DELETE FROM {$as_table}
             WHERE hook = 'bluesky_retry_syndicate'
             AND status IN ('pending', 'failed')"
        );
    }
}
