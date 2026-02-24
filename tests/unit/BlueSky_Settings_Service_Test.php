<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_Settings_Service
 *
 * Covers:
 * - Settings sanitization
 * - Handle normalization via BlueSky_Helpers
 * - Cache duration validation
 * - Password preservation
 */
class BlueSky_Settings_Service_Test extends TestCase
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

    public function test_sanitize_settings_normalizes_handle_email()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->andReturn([]);

        Functions\expect('add_option')
            ->andReturn(true);

        Functions\expect('sanitize_text_field')
            ->once()
            ->with('user@example.com')
            ->andReturn('user@example.com');

        // Mock delete_transient and wpdb for clear_content_transients
        Functions\expect('delete_transient')
            ->once();

        global $wpdb;
        $wpdb = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query', 'prepare', 'esc_like'])
            ->getMock();
        $wpdb->options = 'wp_options';
        $wpdb->method('query')->willReturn(0);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);

        $input = [
            'handle' => 'user@example.com',
            'customisation' => [],
            'cache_duration' => ['minutes' => 0, 'hours' => 1, 'days' => 0]
        ];

        Functions\expect('absint')
            ->times(3)
            ->andReturnFirstArg();

        // Act
        $service = new BlueSky_Settings_Service($mock_api, $mock_account_manager);
        $result = $service->sanitize_settings($input);

        // Assert - email should pass through unchanged
        $this->assertEquals('user@example.com', $result['handle']);
    }

    public function test_sanitize_settings_normalizes_bare_username()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->andReturn([]);

        Functions\expect('add_option')
            ->andReturn(true);

        Functions\expect('sanitize_text_field')
            ->once()
            ->with('alice')
            ->andReturn('alice');

        Functions\expect('delete_transient')
            ->once();

        global $wpdb;
        $wpdb = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query', 'prepare', 'esc_like'])
            ->getMock();
        $wpdb->options = 'wp_options';
        $wpdb->method('query')->willReturn(0);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);

        $input = [
            'handle' => 'alice',
            'customisation' => [],
            'cache_duration' => ['minutes' => 0, 'hours' => 1, 'days' => 0]
        ];

        Functions\expect('absint')
            ->times(3)
            ->andReturnFirstArg();

        // Act
        $service = new BlueSky_Settings_Service($mock_api, $mock_account_manager);
        $result = $service->sanitize_settings($input);

        // Assert - bare username should get .bsky.social suffix
        $this->assertEquals('alice.bsky.social', $result['handle']);
    }

    public function test_sanitize_settings_normalizes_full_handle()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->andReturn([]);

        Functions\expect('add_option')
            ->andReturn(true);

        Functions\expect('sanitize_text_field')
            ->once()
            ->with('alice.bsky.social')
            ->andReturn('alice.bsky.social');

        Functions\expect('delete_transient')
            ->once();

        global $wpdb;
        $wpdb = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query', 'prepare', 'esc_like'])
            ->getMock();
        $wpdb->options = 'wp_options';
        $wpdb->method('query')->willReturn(0);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);

        $input = [
            'handle' => 'alice.bsky.social',
            'customisation' => [],
            'cache_duration' => ['minutes' => 0, 'hours' => 1, 'days' => 0]
        ];

        Functions\expect('absint')
            ->times(3)
            ->andReturnFirstArg();

        // Act
        $service = new BlueSky_Settings_Service($mock_api, $mock_account_manager);
        $result = $service->sanitize_settings($input);

        // Assert - full handle should remain unchanged
        $this->assertEquals('alice.bsky.social', $result['handle']);
    }

    public function test_sanitize_settings_validates_cache_duration()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->andReturn([]);

        Functions\expect('add_option')
            ->andReturn(true);

        Functions\expect('sanitize_text_field')
            ->once()
            ->andReturn('user.bsky.social');

        Functions\expect('delete_transient')
            ->once();

        global $wpdb;
        $wpdb = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query', 'prepare', 'esc_like'])
            ->getMock();
        $wpdb->options = 'wp_options';
        $wpdb->method('query')->willReturn(0);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);

        $input = [
            'handle' => 'user.bsky.social',
            'customisation' => [],
            'cache_duration' => [
                'minutes' => 30,
                'hours' => 2,
                'days' => 1
            ]
        ];

        Functions\expect('absint')
            ->times(3)
            ->andReturnUsing(function($arg) { return abs((int)$arg); });

        // Act
        $service = new BlueSky_Settings_Service($mock_api, $mock_account_manager);
        $result = $service->sanitize_settings($input);

        // Assert - cache duration should be calculated correctly
        $this->assertEquals(30, $result['cache_duration']['minutes']);
        $this->assertEquals(2, $result['cache_duration']['hours']);
        $this->assertEquals(1, $result['cache_duration']['days']);
        $this->assertEquals(30 * 60 + 2 * 3600 + 1 * 86400, $result['cache_duration']['total_seconds']);
    }

    public function test_sanitize_settings_preserves_encrypted_password()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        $existing_password = 'encrypted-password-data';

        Functions\expect('get_option')
            ->andReturnUsing(function($key) use ($existing_password) {
                if ($key === BLUESKY_PLUGIN_OPTIONS) {
                    return ['app_password' => $existing_password];
                }
                return [];
            });

        Functions\expect('add_option')
            ->andReturn(true);

        Functions\expect('sanitize_text_field')
            ->once()
            ->andReturn('user.bsky.social');

        Functions\expect('delete_transient')
            ->once();

        global $wpdb;
        $wpdb = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query', 'prepare', 'esc_like'])
            ->getMock();
        $wpdb->options = 'wp_options';
        $wpdb->method('query')->willReturn(0);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);

        // Input with no new password provided (empty string)
        $input = [
            'handle' => 'user.bsky.social',
            'app_password' => '',
            'customisation' => [],
            'cache_duration' => ['minutes' => 0, 'hours' => 1, 'days' => 0]
        ];

        Functions\expect('absint')
            ->times(3)
            ->andReturnFirstArg();

        // Act
        $service = new BlueSky_Settings_Service($mock_api, $mock_account_manager);
        $result = $service->sanitize_settings($input);

        // Assert - existing encrypted password should be preserved
        $this->assertEquals($existing_password, $result['app_password']);
    }
}
