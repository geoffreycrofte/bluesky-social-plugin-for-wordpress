<?php
/**
 * Request-level cache for Bluesky API responses.
 *
 * Provides static in-memory caching to deduplicate API calls within a single PHP request.
 * When multiple blocks/shortcodes render on the same page with identical parameters,
 * this cache ensures only one API call is made.
 *
 * The cache is stored in a static variable and lives only for the duration of the current
 * PHP request (page load). It requires zero database queries and has minimal overhead.
 *
 * @package Bluesky_Social_Integration
 * @since 1.5.0
 */

class BlueSky_Request_Cache {

	/**
	 * In-memory cache storage.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Get cached value for a given key.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null Cached value if exists, null otherwise.
	 */
	public static function get( $key ) {
		return self::$cache[ $key ] ?? null;
	}

	/**
	 * Store a value in the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @return void
	 */
	public static function set( $key, $value ) {
		self::$cache[ $key ] = $value;
	}

	/**
	 * Check if a key exists in the cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True if key exists, false otherwise.
	 */
	public static function has( $key ) {
		return array_key_exists( $key, self::$cache );
	}

	/**
	 * Build a deterministic cache key from method name and parameters.
	 *
	 * Creates a consistent key for the same method + params combination,
	 * allowing request deduplication across multiple blocks/shortcodes.
	 *
	 * @param string $method API method name (e.g., 'getProfile', 'getAuthorFeed').
	 * @param array  $params API parameters.
	 * @return string Cache key.
	 */
	public static function build_key( $method, $params ) {
		return 'bluesky_' . $method . '_' . md5( serialize( $params ) );
	}

	/**
	 * Clear all cached data.
	 *
	 * Useful for testing and edge cases where cache needs to be reset.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = array();
	}
}
