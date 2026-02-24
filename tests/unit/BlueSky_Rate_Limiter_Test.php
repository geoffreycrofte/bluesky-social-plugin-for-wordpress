<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_Rate_Limiter
 *
 * Covers:
 * - HTTP 429 detection
 * - Retry-After header parsing (numeric seconds and HTTP date)
 * - Exponential backoff with jitter when no header present
 * - Per-account rate limit state persistence
 * - Rate limit expiration checks
 */
class BlueSky_Rate_Limiter_Test extends TestCase
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

    public function test_check_rate_limit_returns_false_for_non_429_responses()
    {
        // Arrange - 200 OK response
        $response = ['body' => 'success'];
        $account_id = 'account-1';

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(200);

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertFalse($result, 'Should return false for non-429 responses');
    }

    public function test_check_rate_limit_returns_true_for_429_response()
    {
        // Arrange - 429 Too Many Requests
        $response = ['body' => 'rate limited'];
        $account_id = 'account-2';
        $now = time();

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(429);

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with($response, 'retry-after')
            ->andReturn(''); // No header

        // Get current attempt count
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-2')
            ->andReturn(false); // First attempt

        // Set rate limit expiry (60s base for first attempt + jitter)
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_limit_account-2', \Mockery::type('int'), \Mockery::type('int'))
            ->andReturn(true);

        // Increment attempt counter
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-2', 1, \Mockery::type('int'))
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertTrue($result, 'Should return true for 429 responses');
    }

    public function test_check_rate_limit_extracts_retry_after_numeric_seconds()
    {
        // Arrange - 429 with Retry-After: 120 (seconds)
        $response = ['body' => 'rate limited'];
        $account_id = 'account-3';
        $now = time();

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(429);

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with($response, 'retry-after')
            ->andReturn('120'); // 120 seconds

        // Should set rate limit to now + 120
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_limit_account-3', \Mockery::on(function($val) use ($now) {
                return $val >= $now + 120 && $val <= $now + 122; // Allow small timing variance
            }), 120)
            ->andReturn(true);

        // Reset attempt counter after explicit header
        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-3')
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertTrue($result);
    }

    public function test_check_rate_limit_parses_retry_after_http_date()
    {
        // Arrange - 429 with Retry-After: HTTP date format
        $response = ['body' => 'rate limited'];
        $account_id = 'account-4';
        $future_time = time() + 180;
        $http_date = gmdate('D, d M Y H:i:s', $future_time) . ' GMT';

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(429);

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with($response, 'retry-after')
            ->andReturn($http_date);

        // Should set rate limit to parsed timestamp
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_limit_account-4', \Mockery::on(function($val) use ($future_time) {
                return abs($val - $future_time) <= 2; // Allow small timing variance
            }), \Mockery::type('int'))
            ->andReturn(true);

        // Reset attempt counter after explicit header
        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-4')
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertTrue($result);
    }

    public function test_check_rate_limit_uses_exponential_backoff_without_header()
    {
        // Arrange - 429 without Retry-After header, attempt 2
        $response = ['body' => 'rate limited'];
        $account_id = 'account-5';

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(429);

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with($response, 'retry-after')
            ->andReturn(''); // No header

        // Second attempt (already failed once)
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-5')
            ->andReturn(1);

        // Should use 120s base (attempt 2) with jitter
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_limit_account-5', \Mockery::on(function($val) {
                $now = time();
                // 120s ± 20% jitter = 96-144 seconds
                return $val >= $now + 96 && $val <= $now + 144;
            }), \Mockery::type('int'))
            ->andReturn(true);

        // Increment to attempt 2
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-5', 2, \Mockery::type('int'))
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_rate_limited_returns_true_when_active()
    {
        // Arrange - rate limit active for 60 more seconds
        $account_id = 'account-6';
        $expires_at = time() + 60;

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-6')
            ->andReturn($expires_at);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->is_rate_limited($account_id);

        // Assert
        $this->assertTrue($result, 'Should return true when rate limit is active');
    }

    public function test_is_rate_limited_returns_false_when_expired()
    {
        // Arrange - rate limit expired 10 seconds ago
        $account_id = 'account-7';
        $expired_at = time() - 10;

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-7')
            ->andReturn($expired_at);

        // Clean up expired rate limit
        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_rate_limit_account-7')
            ->andReturn(true);

        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-7')
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->is_rate_limited($account_id);

        // Assert
        $this->assertFalse($result, 'Should return false when rate limit has expired');
    }

    public function test_is_rate_limited_returns_false_when_no_rate_limit()
    {
        // Arrange - no rate limit set
        $account_id = 'account-8';

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-8')
            ->andReturn(false);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->is_rate_limited($account_id);

        // Assert
        $this->assertFalse($result, 'Should return false when no rate limit exists');
    }

    public function test_get_retry_after_returns_remaining_seconds()
    {
        // Arrange - rate limit expires in 90 seconds
        $account_id = 'account-9';
        $expires_at = time() + 90;

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-9')
            ->andReturn($expires_at);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->get_retry_after($account_id);

        // Assert - should be around 90 seconds (allow 2s variance)
        $this->assertGreaterThanOrEqual(88, $result);
        $this->assertLessThanOrEqual(92, $result);
    }

    public function test_get_retry_after_returns_zero_when_not_rate_limited()
    {
        // Arrange - no rate limit
        $account_id = 'account-10';

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-10')
            ->andReturn(false);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->get_retry_after($account_id);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_different_accounts_have_independent_rate_limits()
    {
        // Arrange - account-11 is rate limited, account-12 is not
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-11')
            ->andReturn(time() + 60);

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_limit_account-12')
            ->andReturn(false);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $limited_11 = $limiter->is_rate_limited('account-11');
        $limited_12 = $limiter->is_rate_limited('account-12');

        // Assert
        $this->assertTrue($limited_11, 'Account 11 should be rate limited');
        $this->assertFalse($limited_12, 'Account 12 should not be rate limited');
    }

    public function test_exponential_backoff_caps_at_300_seconds()
    {
        // Arrange - 429 without Retry-After, attempt 10 (should cap at 300s)
        $response = ['body' => 'rate limited'];
        $account_id = 'account-13';

        Functions\expect('is_wp_error')
            ->once()
            ->with($response)
            ->andReturn(false);

        Functions\expect('wp_remote_retrieve_response_code')
            ->once()
            ->with($response)
            ->andReturn(429);

        Functions\expect('wp_remote_retrieve_header')
            ->once()
            ->with($response, 'retry-after')
            ->andReturn(''); // No header

        // 10th attempt
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-13')
            ->andReturn(9);

        // Should cap at 300s base with jitter (240-360s)
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_limit_account-13', \Mockery::on(function($val) {
                $now = time();
                // 300s ± 20% jitter = 240-360 seconds
                return $val >= $now + 240 && $val <= $now + 360;
            }), \Mockery::type('int'))
            ->andReturn(true);

        // Increment to attempt 10
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_rate_attempts_account-13', 10, \Mockery::type('int'))
            ->andReturn(true);

        $limiter = new BlueSky_Rate_Limiter();

        // Act
        $result = $limiter->check_rate_limit($response, $account_id);

        // Assert
        $this->assertTrue($result);
    }
}
