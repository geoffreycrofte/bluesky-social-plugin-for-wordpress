<?php
/**
 * Tests for BlueSky_Request_Cache
 *
 * @package Bluesky_Social_Integration
 */

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class Test_BlueSky_Request_Cache extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// CRITICAL: Flush static cache before each test for isolation
		BlueSky_Request_Cache::flush();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_null_for_non_existent_key() {
		$result = BlueSky_Request_Cache::get( 'non_existent_key' );
		$this->assertNull( $result );
	}

	public function test_set_stores_value_and_get_returns_it() {
		$key   = 'test_key';
		$value = array( 'data' => 'test_value' );

		BlueSky_Request_Cache::set( $key, $value );
		$result = BlueSky_Request_Cache::get( $key );

		$this->assertSame( $value, $result );
	}

	public function test_has_returns_false_for_non_existent_key() {
		$result = BlueSky_Request_Cache::has( 'non_existent_key' );
		$this->assertFalse( $result );
	}

	public function test_has_returns_true_after_set() {
		$key = 'test_key';
		BlueSky_Request_Cache::set( $key, 'value' );

		$result = BlueSky_Request_Cache::has( $key );
		$this->assertTrue( $result );
	}

	public function test_build_key_returns_deterministic_key_for_same_method_and_params() {
		$method = 'getProfile';
		$params = array( 'actor' => 'user.bsky.social' );

		$key1 = BlueSky_Request_Cache::build_key( $method, $params );
		$key2 = BlueSky_Request_Cache::build_key( $method, $params );

		$this->assertSame( $key1, $key2 );
	}

	public function test_build_key_returns_different_keys_for_different_params() {
		$method  = 'getProfile';
		$params1 = array( 'actor' => 'user1.bsky.social' );
		$params2 = array( 'actor' => 'user2.bsky.social' );

		$key1 = BlueSky_Request_Cache::build_key( $method, $params1 );
		$key2 = BlueSky_Request_Cache::build_key( $method, $params2 );

		$this->assertNotSame( $key1, $key2 );
	}

	public function test_build_key_returns_different_keys_for_different_methods() {
		$params = array( 'actor' => 'user.bsky.social' );

		$key1 = BlueSky_Request_Cache::build_key( 'getProfile', $params );
		$key2 = BlueSky_Request_Cache::build_key( 'getAuthorFeed', $params );

		$this->assertNotSame( $key1, $key2 );
	}

	public function test_flush_clears_all_cached_data() {
		BlueSky_Request_Cache::set( 'key1', 'value1' );
		BlueSky_Request_Cache::set( 'key2', 'value2' );

		$this->assertTrue( BlueSky_Request_Cache::has( 'key1' ) );
		$this->assertTrue( BlueSky_Request_Cache::has( 'key2' ) );

		BlueSky_Request_Cache::flush();

		$this->assertFalse( BlueSky_Request_Cache::has( 'key1' ) );
		$this->assertFalse( BlueSky_Request_Cache::has( 'key2' ) );
	}

	public function test_cache_survives_within_same_test_method() {
		// Simulates same PHP request
		BlueSky_Request_Cache::set( 'persistent_key', 'persistent_value' );

		// Simulate doing other work...
		$dummy = 'work';

		// Cache should still be there
		$result = BlueSky_Request_Cache::get( 'persistent_key' );
		$this->assertSame( 'persistent_value', $result );
	}

	public function test_after_flush_previously_cached_data_is_gone() {
		BlueSky_Request_Cache::set( 'test_key', 'test_value' );
		$this->assertSame( 'test_value', BlueSky_Request_Cache::get( 'test_key' ) );

		BlueSky_Request_Cache::flush();

		$this->assertNull( BlueSky_Request_Cache::get( 'test_key' ) );
	}
}
