<?php
/**
 * Rate Limiter for Bluesky API calls
 *
 * Detects HTTP 429 (Too Many Requests) responses and manages rate limiting
 * state per account. Supports:
 * - Retry-After header parsing (numeric seconds and HTTP date format)
 * - Exponential backoff with jitter when no header is provided
 * - Per-account rate limit tracking via transients
 *
 * @package BlueSky_Social_Integration
 * @since 2.0.0
 */

class BlueSky_Rate_Limiter {
    /**
     * Base delays for exponential backoff (in seconds)
     * Attempt 1: 60s, Attempt 2: 120s, Attempt 3+: 300s
     *
     * @var array
     */
    private const BACKOFF_DELAYS = [60, 120, 300];

    /**
     * Jitter percentage (±20%)
     *
     * @var float
     */
    private const JITTER_PERCENT = 0.2;

    /**
     * Check if a response indicates rate limiting
     *
     * If response is HTTP 429, extracts Retry-After header or applies
     * exponential backoff. Stores rate limit state in transients.
     *
     * @param array|WP_Error $response wp_remote_* response
     * @param string         $account_id Account UUID
     * @return bool True if rate limited, false otherwise
     */
    public function check_rate_limit( $response, $account_id ) {
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 429 !== $status_code ) {
            return false;
        }

        // Rate limited - check for Retry-After header
        $retry_after_header = wp_remote_retrieve_header( $response, 'retry-after' );

        if ( ! empty( $retry_after_header ) ) {
            // Server provided explicit retry time
            $retry_seconds = $this->parse_retry_after( $retry_after_header );
            $retry_until = time() + $retry_seconds;

            set_transient( $this->get_rate_limit_key( $account_id ), $retry_until, $retry_seconds );

            // Reset attempt counter when server provides explicit timing
            delete_transient( $this->get_attempt_key( $account_id ) );
        } else {
            // No header - use exponential backoff
            $attempt = (int) get_transient( $this->get_attempt_key( $account_id ) );
            $delay = $this->calculate_backoff( $attempt + 1 );
            $retry_until = time() + $delay;

            set_transient( $this->get_rate_limit_key( $account_id ), $retry_until, $delay );

            // Increment attempt counter
            set_transient( $this->get_attempt_key( $account_id ), $attempt + 1, WEEK_IN_SECONDS );
        }

        return true;
    }

    /**
     * Check if an account is currently rate limited
     *
     * @param string $account_id Account UUID
     * @return bool True if rate limited, false otherwise
     */
    public function is_rate_limited( $account_id ) {
        $rate_limit_until = get_transient( $this->get_rate_limit_key( $account_id ) );

        if ( false === $rate_limit_until ) {
            return false;
        }

        // Check if rate limit has expired
        if ( time() >= $rate_limit_until ) {
            // Clean up expired rate limit
            delete_transient( $this->get_rate_limit_key( $account_id ) );
            delete_transient( $this->get_attempt_key( $account_id ) );
            return false;
        }

        return true;
    }

    /**
     * Get seconds until rate limit expires
     *
     * @param string $account_id Account UUID
     * @return int Seconds until retry allowed (0 if not rate limited)
     */
    public function get_retry_after( $account_id ) {
        $rate_limit_until = get_transient( $this->get_rate_limit_key( $account_id ) );

        if ( false === $rate_limit_until ) {
            return 0;
        }

        $remaining = $rate_limit_until - time();
        return max( 0, $remaining );
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * @param int $attempt Attempt number (1-indexed)
     * @return int Delay in seconds
     */
    private function calculate_backoff( $attempt ) {
        // Map attempt to delay index (cap at max delay)
        $index = min( $attempt - 1, count( self::BACKOFF_DELAYS ) - 1 );
        $base_delay = self::BACKOFF_DELAYS[ $index ];

        // Add jitter (±20%)
        $jitter_range = $base_delay * self::JITTER_PERCENT;
        $jitter = rand( -$jitter_range, $jitter_range );

        return (int) ( $base_delay + $jitter );
    }

    /**
     * Parse Retry-After header
     *
     * Supports two formats:
     * - Numeric seconds: "120"
     * - HTTP date: "Wed, 21 Oct 2015 07:28:00 GMT"
     *
     * @param string $retry_after Header value
     * @return int Seconds to wait
     */
    private function parse_retry_after( $retry_after ) {
        // Try numeric format first
        if ( is_numeric( $retry_after ) ) {
            return (int) $retry_after;
        }

        // Try HTTP date format
        $timestamp = strtotime( $retry_after );
        if ( false !== $timestamp ) {
            $seconds = $timestamp - time();
            return max( 0, $seconds );
        }

        // Fallback to 60 seconds if parsing fails
        return 60;
    }

    /**
     * Get transient key for rate limit timestamp
     *
     * @param string $account_id Account UUID
     * @return string
     */
    private function get_rate_limit_key( $account_id ) {
        return 'bluesky_rate_limit_' . $account_id;
    }

    /**
     * Get transient key for retry attempt counter
     *
     * @param string $account_id Account UUID
     * @return string
     */
    private function get_attempt_key( $account_id ) {
        return 'bluesky_rate_attempts_' . $account_id;
    }
}
