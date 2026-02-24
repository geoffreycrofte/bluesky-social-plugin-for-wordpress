<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_Helpers
 *
 * Covers:
 * - Handle normalization (email, bare username, full handle, custom domain)
 * - Encryption/decryption roundtrip
 * - Transient key construction
 */
class BlueSky_Helpers_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_normalize_handle_email_passthrough()
    {
        // Arrange - email addresses should pass through unchanged
        $email = 'user@example.com';

        // Act
        $result = BlueSky_Helpers::normalize_handle($email);

        // Assert
        $this->assertEquals('user@example.com', $result);
    }

    public function test_normalize_handle_bare_username_gets_suffix()
    {
        // Arrange - bare usernames should get .bsky.social suffix
        $bare_username = 'alice';

        // Act
        $result = BlueSky_Helpers::normalize_handle($bare_username);

        // Assert
        $this->assertEquals('alice.bsky.social', $result);
    }

    public function test_normalize_handle_full_handle_unchanged()
    {
        // Arrange - full handles with .bsky.social should remain unchanged
        $full_handle = 'alice.bsky.social';

        // Act
        $result = BlueSky_Helpers::normalize_handle($full_handle);

        // Assert
        $this->assertEquals('alice.bsky.social', $result);
    }

    public function test_normalize_handle_custom_domain()
    {
        // Arrange - custom domains (with dot) should remain unchanged
        $custom_domain = 'alice.example.com';

        // Act
        $result = BlueSky_Helpers::normalize_handle($custom_domain);

        // Assert
        $this->assertEquals('alice.example.com', $result);
    }

    public function test_encrypt_decrypt_roundtrip()
    {
        // Arrange
        Functions\expect('get_option')
            ->andReturnUsing(function($key) {
                if ($key === BLUESKY_PLUGIN_OPTIONS) {
                    return [];
                }
                if ($key === BLUESKY_PLUGIN_OPTIONS . '_secret') {
                    return 'test-secret-key-12345678901234567890123456789012';
                }
                return false;
            });

        Functions\expect('add_option')
            ->never(); // Secret key already exists

        Functions\expect('current_user_can')
            ->andReturn(true);

        Functions\expect('add_action')
            ->andReturn(true);

        $helpers = new BlueSky_Helpers();
        $original_value = 'my-secret-password';

        // Act - encrypt then decrypt
        $encrypted = $helpers->bluesky_encrypt($original_value);
        $decrypted = $helpers->bluesky_decrypt($encrypted);

        // Assert
        $this->assertNotEquals($original_value, $encrypted); // Should be encrypted
        $this->assertEquals($original_value, $decrypted); // Should decrypt back to original
    }

    public function test_get_transient_key_includes_account_id()
    {
        // Arrange
        Functions\expect('get_option')
            ->once()
            ->with(BLUESKY_PLUGIN_OPTIONS)
            ->andReturn([]);

        $helpers = new BlueSky_Helpers();
        $account_id = 'test-uuid-123';

        // Act - get transient key with account_id
        $profile_key = $helpers->get_profile_transient_key($account_id);
        $access_token_key = $helpers->get_access_token_transient_key($account_id);

        // Assert - account_id should be included in the key
        $this->assertStringContainsString($account_id, $profile_key);
        $this->assertStringContainsString($account_id, $access_token_key);
        $this->assertStringContainsString(BLUESKY_PLUGIN_TRANSIENT, $profile_key);
        $this->assertStringContainsString('profile', $profile_key);
        $this->assertStringContainsString('access-token', $access_token_key);
    }

    public function test_get_transient_key_without_account_id()
    {
        // Arrange
        Functions\expect('get_option')
            ->once()
            ->with(BLUESKY_PLUGIN_OPTIONS)
            ->andReturn([]);

        $helpers = new BlueSky_Helpers();

        // Act - get transient key without account_id
        $profile_key = $helpers->get_profile_transient_key();

        // Assert - should not contain a UUID
        $this->assertStringContainsString(BLUESKY_PLUGIN_TRANSIENT, $profile_key);
        $this->assertStringContainsString('profile', $profile_key);
        // Should end with -profile (no account_id suffix)
        $this->assertEquals(BLUESKY_PLUGIN_TRANSIENT . '-profile', $profile_key);
    }

    public function test_bluesky_generate_secure_uuid()
    {
        // Act
        $uuid1 = BlueSky_Helpers::bluesky_generate_secure_uuid();
        $uuid2 = BlueSky_Helpers::bluesky_generate_secure_uuid();

        // Assert
        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid2);
        $this->assertNotEquals($uuid1, $uuid2); // Should be unique
    }
}
