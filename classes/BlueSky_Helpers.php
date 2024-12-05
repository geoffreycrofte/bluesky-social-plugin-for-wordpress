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
        if (empty($stringToEncrypt)) {
            return false;
        }

        $encryption_key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            $stringToEncrypt,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );

        if ($encrypted === false) {
            return false;
        }

        // Combine IV and encrypted string with a delimiter
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypts a string using OpenSSL encryption
     * 
     * @param string $stringToDecrypt The string to decrypt
     * @return string|false The decrypted string or false on failure
     */
    public function bluesky_decrypt($stringToDecrypt) {
        if (empty($stringToDecrypt)) {
            return false;
        }

        $encryption_key = $this->get_encryption_key();
        
        // Decode the combined string
        $decoded = base64_decode($stringToDecrypt);
        list($iv, $encrypted) = explode('::', $decoded, 2);

        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );
    }
}