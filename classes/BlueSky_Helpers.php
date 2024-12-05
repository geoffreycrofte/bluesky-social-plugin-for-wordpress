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
     * Check if encryption is available
     * 
     * @return bool True if encryption is available
     */
    private function is_encryption_available() {
        return extension_loaded('openssl') && function_exists('openssl_encrypt');
    }

    /**
     * Add admin notice
     * 
     * @param string $message The message to display
     * @param string $type The notice type (error, warning, success, info)
     */
    private function add_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            if (current_user_can('manage_options')) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($type),
                    sprintf(__('BlueSky Error: %s', 'bluesky-social-integration'), esc_html($message))
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
        $secret_key = get_option(BLUESKY_PLUGIN_OPTIONS . '_secret');
        if (empty($secret_key) || $secret_key === false) {
            $secret_key = bin2hex(random_bytes(32));
            add_option(BLUESKY_PLUGIN_OPTIONS . '_secret', $secret_key);
        }
        return hash('sha256', $secret_key);
    }

    /**
     * Encrypts a string using OpenSSL encryption
     * 
     * @param string $stringToEncrypt The string to encrypt
     * @return string|false The encrypted string or false on failure
     */
    public function bluesky_encrypt($stringToEncrypt) {
        if ( ! $this -> is_encryption_available() ) {
            $this -> add_admin_notice( __('OpenSSL encryption is not available on your server.', 'bluesky-social') );
            error_log('BlueSky: OpenSSL encryption is not available');
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
                $this -> add_admin_notice( __('Encryption failed. Please check your server configuration.', 'bluesky-social') );
                return false;
            }

            return base64_encode($iv . '::' . $encrypted);
        } catch ( Exception $e ) {
            $this -> add_admin_notice(
                sprintf( __('Encryption failed: %s', 'bluesky-social'), $e->getMessage() )
            );
            error_log('BlueSky: Encryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypts a string using OpenSSL encryption
     * 
     * @param string $stringToDecrypt The string to decrypt
     * @return string|false The decrypted string or false on failure
     */
    public function bluesky_decrypt($stringToDecrypt) {
        if ( ! $this -> is_encryption_available() ) {
            $this -> add_admin_notice( __('OpenSSL encryption is not available on your server.', 'bluesky-social') );
            error_log('BlueSky: OpenSSL encryption is not available');
            return false;
        }

        if (empty($stringToDecrypt)) {
            return false;
        }

        try {
            $encryption_key = $this->get_encryption_key();
            
            $decoded = base64_decode($stringToDecrypt);
            if ($decoded === false) {
                $this -> add_admin_notice( __('Invalid encrypted data format.', 'bluesky-social') );
                return false;
            }

            $parts = explode('::', $decoded, 2);
            if (count($parts) !== 2) {
                $this -> add_admin_notice( __('Corrupted encrypted data.', 'bluesky-social') );
                return false;
            }

            list($iv, $encrypted) = $parts;

            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $encryption_key,
                0,
                $iv
            );

            if ( $decrypted === false ) {
                $this -> add_admin_notice( __('Decryption failed. The data might be corrupted or the encryption key has changed.', 'bluesky-social') );
                return false;
            }

            return $decrypted;
        } catch (Exception $e) {
            $this -> add_admin_notice(
                sprintf(__('Decryption failed: %s', 'bluesky-social'), $e->getMessage())
            );
            error_log('BlueSky: Decryption failed - ' . $e->getMessage());
            return false;
        }
    }
}