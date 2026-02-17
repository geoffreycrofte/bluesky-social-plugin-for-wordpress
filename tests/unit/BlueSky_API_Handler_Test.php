<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_API_Handler
 *
 * Covers:
 * - Factory method for per-account instances
 * - Authentication token caching
 * - Transient key scoping by account_id
 */
class BlueSky_API_Handler_Test extends TestCase
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

    public function test_create_for_account_sets_account_id()
    {
        // Arrange
        $account = [
            'id' => 'test-uuid-123',
            'handle' => 'alice.bsky.social',
            'app_password' => 'encrypted-password-here'
        ];

        // Act
        $handler = BlueSky_API_Handler::create_for_account($account);

        // Assert
        $this->assertInstanceOf(BlueSky_API_Handler::class, $handler);

        // Use reflection to check private account_id property
        $reflection = new ReflectionClass($handler);
        $property = $reflection->getProperty('account_id');
        $property->setAccessible(true);
        $this->assertEquals('test-uuid-123', $property->getValue($handler));
    }

    public function test_create_for_account_sets_credentials()
    {
        // Arrange
        $account = [
            'id' => 'test-uuid-456',
            'handle' => 'bob.bsky.social',
            'app_password' => 'encrypted-app-password'
        ];

        // Act
        $handler = BlueSky_API_Handler::create_for_account($account);

        // Assert - check options array has correct credentials
        $reflection = new ReflectionClass($handler);
        $options_property = $reflection->getProperty('options');
        $options_property->setAccessible(true);
        $options = $options_property->getValue($handler);

        $this->assertEquals('bob.bsky.social', $options['handle']);
        $this->assertEquals('encrypted-app-password', $options['app_password']);
    }

    public function test_authenticate_caches_token_in_transient()
    {
        // Arrange
        $options = [
            'handle' => 'user.bsky.social',
            'app_password' => 'encrypted-password'
        ];

        $handler = new BlueSky_API_Handler($options);

        // Mock get_option for BlueSky_Helpers constructor and get_encryption_key
        Functions\expect('get_option')
            ->andReturn([]);

        // Mock add_option for encryption key initialization
        Functions\expect('add_option')
            ->andReturn(true);

        // Mock translation functions
        Functions\expect('esc_html__')
            ->andReturnFirstArg();

        Functions\expect('current_user_can')
            ->andReturn(true);

        Functions\expect('add_action')
            ->andReturn(true);

        // Mock get_transient to return false (not cached)
        Functions\expect('get_transient')
            ->times(3)
            ->andReturn(false);

        // Mock wp_remote_post for authentication request
        Functions\expect('wp_remote_post')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'did' => 'did:plc:test123',
                    'accessJwt' => 'access-token-jwt',
                    'refreshJwt' => 'refresh-token-jwt'
                ])
            ]);

        Functions\expect('is_wp_error')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->andReturn(200);

        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode([
                'did' => 'did:plc:test123',
                'accessJwt' => 'access-token-jwt',
                'refreshJwt' => 'refresh-token-jwt'
            ]));

        Functions\expect('wp_json_encode')
            ->once()
            ->andReturnFirstArg();

        // Expect set_transient to be called for access token, refresh token, and did
        Functions\expect('set_transient')
            ->times(3)
            ->andReturn(true);

        // Act
        $result = $handler->authenticate();

        // Assert
        $this->assertTrue($result);
    }

    public function test_authenticate_uses_cached_token()
    {
        // Arrange
        $options = [
            'handle' => 'user.bsky.social',
            'app_password' => 'encrypted-password'
        ];

        $handler = new BlueSky_API_Handler($options);

        // Mock get_option for BlueSky_Helpers constructor
        Functions\expect('get_option')
            ->once()
            ->with(BLUESKY_PLUGIN_OPTIONS)
            ->andReturn([]);

        // Mock get_transient to return cached values
        Functions\expect('get_transient')
            ->times(3)
            ->andReturnUsing(function($key) {
                if (strpos($key, 'access-token') !== false) {
                    return 'cached-access-token';
                }
                if (strpos($key, 'refresh-token') !== false) {
                    return 'cached-refresh-token';
                }
                if (strpos($key, 'did') !== false) {
                    return 'did:plc:cached123';
                }
                return false;
            });

        // Expect NO wp_remote_post call (using cache)
        Functions\expect('wp_remote_post')
            ->never();

        // Act
        $result = $handler->authenticate();

        // Assert
        $this->assertTrue($result);
    }
}
