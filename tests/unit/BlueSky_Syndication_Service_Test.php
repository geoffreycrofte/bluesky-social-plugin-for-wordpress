<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_Syndication_Service
 *
 * Covers:
 * - Post status transition guards
 * - "Don't syndicate" flag checking
 * - Capability verification
 * - Already-syndicated detection
 * - POST data sanitization
 */
class BlueSky_Syndication_Service_Test extends TestCase
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

    public function test_syndicate_skips_non_publish_transition()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->once()
            ->with(BLUESKY_PLUGIN_OPTIONS, [])
            ->andReturn([]);

        $post = new stdClass();
        $post->ID = 123;
        $post->post_type = 'post';

        // API should never be called for draft->draft transition
        $mock_api->expects($this->never())
            ->method('syndicate_post_to_bluesky');

        // Act
        $service = new BlueSky_Syndication_Service($mock_api, $mock_account_manager);
        $service->syndicate_post_to_bluesky('draft', 'draft', $post);
    }

    public function test_syndicate_skips_when_dont_syndicate_set()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->times(3) // Service constructor + new Account_Manager + Helpers in Account_Manager
            ->andReturn([]);

        $post = new stdClass();
        $post->ID = 123;
        $post->post_type = 'post';

        // Mock capability check
        Functions\expect('current_user_can')
            ->once()
            ->with('edit_post', 123)
            ->andReturn(true);

        // Mock permalink
        Functions\expect('get_permalink')
            ->once()
            ->with(123)
            ->andReturn('https://example.com/post-123');

        Functions\expect('do_action')
            ->once()
            ->with('bluesky_before_syndicating_post', 123);

        // Mock get_post_meta for dont_syndicate (returns truthy)
        Functions\expect('get_post_meta')
            ->once()
            ->with(123, '_bluesky_dont_syndicate', true)
            ->andReturn('1');

        // API should not be called
        $mock_api->expects($this->never())
            ->method('syndicate_post_to_bluesky');

        // Act
        $service = new BlueSky_Syndication_Service($mock_api, $mock_account_manager);
        $service->syndicate_post_to_bluesky('publish', 'draft', $post);
    }

    public function test_syndicate_checks_user_capability()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->once()
            ->with(BLUESKY_PLUGIN_OPTIONS, [])
            ->andReturn([]);

        $post = new stdClass();
        $post->ID = 456;
        $post->post_type = 'post';

        // User cannot edit post
        Functions\expect('current_user_can')
            ->once()
            ->with('edit_post', 456)
            ->andReturn(false);

        // API should not be called
        $mock_api->expects($this->never())
            ->method('syndicate_post_to_bluesky');

        // Act
        $service = new BlueSky_Syndication_Service($mock_api, $mock_account_manager);
        $service->syndicate_post_to_bluesky('publish', 'draft', $post);
    }

    public function test_syndicate_skips_already_syndicated()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->times(3) // Service constructor + new Account_Manager + Helpers in Account_Manager
            ->andReturn([]);

        $post = new stdClass();
        $post->ID = 789;
        $post->post_type = 'post';

        Functions\expect('current_user_can')
            ->once()
            ->with('edit_post', 789)
            ->andReturn(true);

        Functions\expect('get_permalink')
            ->once()
            ->with(789)
            ->andReturn('https://example.com/post-789');

        Functions\expect('do_action')
            ->once()
            ->with('bluesky_before_syndicating_post', 789);

        // Mock get_post_meta calls - dont_syndicate is false, but already syndicated
        Functions\expect('get_post_meta')
            ->times(2)
            ->andReturnUsing(function($post_id, $key, $single) {
                if ($key === '_bluesky_dont_syndicate') {
                    return false;
                }
                if ($key === '_bluesky_syndicated') {
                    return true; // Already syndicated
                }
                return false;
            });

        // Mock POST data check
        $_POST = [];

        Functions\expect('sanitize_text_field')
            ->never();

        // API should not be called for already-syndicated posts
        $mock_api->expects($this->never())
            ->method('syndicate_post_to_bluesky');

        // Act
        $service = new BlueSky_Syndication_Service($mock_api, $mock_account_manager);
        $service->syndicate_post_to_bluesky('publish', 'draft', $post);

        // Cleanup
        $_POST = [];
    }

    public function test_syndicate_sanitizes_post_data()
    {
        // Arrange
        $mock_api = $this->createMock(BlueSky_API_Handler::class);
        $mock_account_manager = $this->createMock(BlueSky_Account_Manager::class);

        Functions\expect('get_option')
            ->times(3) // Service constructor + new Account_Manager + Helpers in Account_Manager
            ->andReturn([]);

        $post = new stdClass();
        $post->ID = 999;
        $post->post_type = 'post';
        $post->post_title = 'Test Post';
        $post->post_content = 'Test content';
        $post->post_excerpt = 'Test excerpt';

        Functions\expect('current_user_can')
            ->once()
            ->with('edit_post', 999)
            ->andReturn(true);

        Functions\expect('get_permalink')
            ->once()
            ->with(999)
            ->andReturn('https://example.com/post-999');

        Functions\expect('do_action')
            ->times(2);

        // Mock get_post_meta - not syndicated, not dont_syndicate
        Functions\expect('get_post_meta')
            ->times(2)
            ->andReturn(false);

        // Mock POST data with potentially unsafe content
        $_POST['bluesky_dont_syndicate'] = '<script>alert("xss")</script>';

        // Expect sanitize_text_field and wp_unslash to be called
        Functions\expect('sanitize_text_field')
            ->once()
            ->andReturnUsing(function($arg) { return strip_tags($arg); });

        Functions\expect('wp_unslash')
            ->once()
            ->andReturnFirstArg();

        Functions\expect('has_post_thumbnail')
            ->once()
            ->with(999)
            ->andReturn(false);

        // Mock syndication (will be called)
        $mock_api->expects($this->once())
            ->method('syndicate_post_to_bluesky')
            ->willReturn(['uri' => 'at://did/post/abc', 'cid' => 'xyz']);

        Functions\expect('add_post_meta')
            ->once()
            ->andReturn(true);

        Functions\expect('update_post_meta')
            ->once();

        Functions\expect('wp_json_encode')
            ->once()
            ->andReturn('{"uri":"at://did/post/abc"}');

        // Act
        $service = new BlueSky_Syndication_Service($mock_api, $mock_account_manager);
        $service->syndicate_post_to_bluesky('publish', 'draft', $post);

        // Cleanup
        $_POST = [];
    }
}
