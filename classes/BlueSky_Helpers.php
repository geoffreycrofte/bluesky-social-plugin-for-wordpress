<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Helpers {
    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Constructor
     */
    public function __construct() {
        $this -> options = get_option( BLUESKY_PLUGIN_OPTIONS );
    }

    /**
     * Get the profile transient key
     * @return string
     */
    public function get_profile_transient_key() {
        return BLUESKY_PLUGIN_TRANSIENT . '-profile';
    }

    /**
     * Get the posts transient key
     * @return string
     */
    public function get_posts_transient_key( $limit = null, $no_replies = false ) {
        return BLUESKY_PLUGIN_TRANSIENT . '-posts-' . ( $limit ?? $this -> options['posts_limit'] ?? 5 ) . '-' . ( $no_replies ? 'no-replies' : 'all' );
    }

    /**
     * Get the access token transient key
     * @return string
     */
    public function get_access_token_transient_key() {
        return BLUESKY_PLUGIN_TRANSIENT . '-access-token';
    }

    /**
     * Get the refresh token transient key
     * @return string
     */
    public function get_refresh_token_transient_key() {
        return BLUESKY_PLUGIN_TRANSIENT . '-refresh-token';
    }

    /**
     * Get the "did" transient key
     * @return string
     */
    public function get_did_transient_key() {
        return BLUESKY_PLUGIN_TRANSIENT . '-did';
    }

    /**
     * Check if encryption is available
     * 
     * @return bool True if encryption is available
     */
    private function is_encryption_available() {
        return extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' );
    }

    /**
     * Add admin notice
     * 
     * @param string $message The message to display
     * @param string $type The notice type (error, warning, success, info)
     */
    private function add_admin_notice( $message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            if (current_user_can('manage_options')) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $type ),
                    // translators: %s is the error message
                    sprintf( esc_html( __('BlueSky Error: %s', 'social-integration-for-bluesky') ), esc_html( $message ) )
                );
            }
        });
    }

    /**
     * Get encryption key
     * 
     * @return string The encryption key
     */
    private function get_encryption_key() {
        $secret_key = get_option( BLUESKY_PLUGIN_OPTIONS . '_secret' );
        if ( empty( $secret_key ) || $secret_key === false ) {
            $secret_key = bin2hex( random_bytes( 32 ) );
            add_option( BLUESKY_PLUGIN_OPTIONS . '_secret', $secret_key );
        }
        return hash( 'sha256', $secret_key );
    }

    /**
     * Encrypts a string using OpenSSL encryption
     * 
     * @param string $stringToEncrypt The string to encrypt
     * @return string|false The encrypted string or false on failure
     */
    public function bluesky_encrypt($stringToEncrypt) {
        if ( ! $this -> is_encryption_available() ) {
            $this -> add_admin_notice( esc_html__('OpenSSL encryption is not available on your server.', 'social-integration-for-bluesky') );
            return false;
        } 

        if ( empty( $stringToEncrypt ) ) {
            return false;
        }

        try {
            $encryption_key = $this->get_encryption_key();
            $iv = openssl_random_pseudo_bytes(16);
            
            $encrypted = openssl_encrypt(
                $stringToEncrypt,
                'AES-256-CBC',
                $encryption_key,
                0,
                $iv
            );

            if ( $encrypted === false ) {
                $this -> add_admin_notice( esc_html__('Encryption failed. Please check your server configuration.', 'social-integration-for-bluesky') );
                return false;
            }

            return base64_encode($iv . '::' . $encrypted);
        } catch ( Exception $e ) {
            $this -> add_admin_notice(
                // translators: %s is the error message
                sprintf( esc_html__('Encryption failed: %s', 'social-integration-for-bluesky'), $e->getMessage() )
            );
            return false;
        }
    }

    /**
     * Decrypts a string using OpenSSL encryption
     * 
     * @param string $stringToDecrypt The string to decrypt
     * @return string|false The decrypted string or false on failure
     */
    public function bluesky_decrypt( $stringToDecrypt ) {
        if ( ! $this -> is_encryption_available() ) {
            $this -> add_admin_notice( esc_html__('OpenSSL encryption is not available on your server.', 'social-integration-for-bluesky') );
            return false;
        }

        if (empty($stringToDecrypt)) {
            return false;
        }

        try {
            $encryption_key = $this->get_encryption_key();
            
            $decoded = base64_decode($stringToDecrypt);
            if ($decoded === false) {
                $this -> add_admin_notice( esc_html__('Invalid encrypted data format.', 'social-integration-for-bluesky') );
                return false;
            }

            $parts = explode('::', $decoded, 2);
            if (count($parts) !== 2) {
                $this -> add_admin_notice( esc_html__('Corrupted encrypted data.', 'social-integration-for-bluesky') );
                return false;
            }

            list( $iv, $encrypted ) = $parts;

            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $encryption_key,
                0,
                $iv
            );

            if ( $decrypted === false ) {
                $this -> add_admin_notice(esc_html__('Decryption failed. The data might be corrupted or the encryption key has changed.', 'social-integration-for-bluesky') );
                return false;
            }

            return $decrypted;
        } catch (Exception $e) {
            $this -> add_admin_notice(
                // translators: %s is the error message
                sprintf( esc_html__('Decryption failed: %s', 'social-integration-for-bluesky'), $e->getMessage() )
            );
            return false;
        }
    }

    /**
     * Regex to get the Youtube ID
     * 
     * @param string $url The URL from which you get the ID
     * @return string The string or null
     */
    public function get_youtube_id( $url ) {
        // Here is a sample of the URLs this regex matches: (there can be more content after the given URL that will be ignored)
        // http://youtu.be/dQw4w9WgXcQ
        // http://www.youtube.com/embed/dQw4w9WgXcQ
        // http://www.youtube.com/watch?v=dQw4w9WgXcQ
        // http://www.youtube.com/?v=dQw4w9WgXcQ
        // http://www.youtube.com/v/dQw4w9WgXcQ
        // http://www.youtube.com/e/dQw4w9WgXcQ
        // http://www.youtube.com/user/username#p/u/11/dQw4w9WgXcQ
        // http://www.youtube.com/sandalsResorts#p/c/54B8C800269D7C1B/0/dQw4w9WgXcQ
        // http://www.youtube.com/watch?feature=player_embedded&v=dQw4w9WgXcQ
        // http://www.youtube.com/?feature=player_embedded&v=dQw4w9WgXcQ

        // It also works on the youtube-nocookie.com URL with the same above options.
        // It will also pull the ID from the URL in an embed code (both iframe and object tags)
        preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match );
        
        return isset( $match[1] ) ? $match[1] : null;
    }

    /**
     * Recursively sanitize a data returning an intval of the data or null is something else was present.
     * 
     * @param mixed $data
     * @return array|int|null
     */
    public function sanitize_int_recursive( $data ) {
        if ( is_array( $data ) &&  ! isset( $data['value'] ) ) {
            return array_map( [ $this, 'sanitize_int_recursive' ], $data );
        } elseif ( is_array( $data ) &&  isset( $data['value'] ) ) {
            $data['default'] = intval($data['default']);
            $data['min'] = intval($data['min']);
            $data['value'] = empty( $data['value'] ) ? 0 : intval( $data['value'] );
            $data['value'] = $data['value'] >= $data['min'] ? $data['value'] : $data['default'];

            return array_map( [ $this, 'sanitize_int_recursive' ], $data );
        }
        return is_numeric( $data ) ? intval( $data ) : null;
    }

    /**
     * Return the admin URL for this plugin.
     *
     * @return string
     */
    public function get_the_admin_plugin_url() {
        return esc_url( get_admin_url( null, 'options-general.php' ) . '?page=' . BLUESKY_PLUGIN_SETTING_PAGENAME );
    }
}