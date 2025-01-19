<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Post_Metabox {

    private $options;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_bluesky_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_bluesky_meta_box' ] );
        $this -> options = get_option( BLUESKY_PLUGIN_OPTIONS );
    }

    /**
     * Add the Bluesky meta box to the post editor
     */
    public function add_bluesky_meta_box() {
        add_meta_box(
            'bluesky_syndication_meta_box',
            esc_html__( 'Bluesky Syndication', 'social-integration-for-bluesky' ),
            [ $this, 'render_bluesky_meta_box' ],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the Bluesky meta box
     * 
     * @param WP_Post $post The post object
     */
    public function render_bluesky_meta_box( $post ) {
        // Retrieve the current value of the meta
        $default_value = $this -> options['auto_syndicate'] ?? '0';
        $dont_syndicate = get_post_meta( $post->ID, '_bluesky_dont_syndicate', true );
        $dont_syndicate = $dont_syndicate === '' ? ( $default_value ? '0' : '1'  ) : $dont_syndicate;

        // Render the checkbox
        ?>
        <div class="bluesky-meta-box-content">
            <label for="bluesky_dont_syndicate">
                <input type="checkbox" name="bluesky_dont_syndicate" id="bluesky_dont_syndicate" value="1" <?php checked( $dont_syndicate, '1' ); ?> aria-describedby="bluesky-dont-syndicate" />
                <?php esc_html_e( "Don't syndicate this post on Bluesky", 'social-integration-for-bluesky' ); ?>
            </label>
            <?php echo '<p class="description bluesky-metabox-description" id="bluesky-dont-syndicate">' . esc_html__( "Uncheck to avoid sending the post on Bluesky. Check to send this post on Bluesky.", 'social-integration-for-bluesky' ) . '</p>'; ?>
            
        </div>
        <?php
        // Add a nonce for security
        wp_nonce_field( 'bluesky_meta_box_nonce', 'bluesky_meta_box_nonce' );
    }

    /**
     * Save the Bluesky meta box
     * 
     * @param int $post_id The post ID
     */
    public function save_bluesky_meta_box( $post_id ) {
        // Verify the nonce
        if ( ! isset( $_POST['bluesky_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['bluesky_meta_box_nonce'], 'bluesky_meta_box_nonce' ) ) {
            return;
        }
    
        // Avoid autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
    
        // Check user permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    
        // Save or delete the meta value
        if ( isset( $_POST['bluesky_dont_syndicate'] ) ) {
            update_post_meta( $post_id, '_bluesky_dont_syndicate', '1' );
        } else {
            delete_post_meta( $post_id, '_bluesky_dont_syndicate' );
        }
    }
}
