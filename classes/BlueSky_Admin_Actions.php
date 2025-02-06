<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Admin_Actions {

    /**
     * Constructor
     * @param array $options Plugin settings
     */
    public function __construct() {
        add_action( 'admin_post_bluesky_logout', [$this, 'handle_bluesky_logout'] ); // Logged-in users
        add_action( 'admin_post_nopriv_bluesky_logout', [$this, 'handle_bluesky_logout'] ); // Non-logged-in users (if needed)
    }

    /**
     * Call for logout from the API Handler, manage the message creation and redirection.
     * @return never
     */
    public function handle_bluesky_logout() {
        // Check if nonce is valid
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'bluesky_logout_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'social-integration-for-bluesky' ) );
        }

        // Initialize your class and call logout
        $options = get_option(BLUESKY_PLUGIN_OPTIONS);
        $api = new BlueSky_API_Handler( $options );
        $helper = new BlueSky_Helpers();

        if ( $api -> logout() ) {
            set_transient('bluesky_logout_message', [
                'type' => 'success',
                'message' => __('Successfully logged out from Bluesky.', 'social-integration-for-bluesky')
            ], 30); // Message stored for 30 seconds
            wp_safe_redirect( $helper->get_the_admin_plugin_url() . '&amp;message=logout_success' );
            exit;
        } else {
            set_transient('bluesky_logout_message', [
                'type' => 'error',
                'message' => __('Logout failed. Please try again.', 'social-integration-for-bluesky')
            ], 30);
            wp_safe_redirect( $helper->get_the_admin_plugin_url() . '&amp;message=logout_failed' );
            exit;
        }
    }
}
