<?php
// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BlueSky Activity Logger
 *
 * Stores recent syndication events in a circular buffer using WordPress Options API.
 * Maintains up to 10 recent events with FIFO rotation.
 *
 * @since 1.6.0
 */
class BlueSky_Activity_Logger
{
    /**
     * Maximum number of events to store in the log
     * @var int
     */
    const MAX_EVENTS = 10;

    /**
     * Option key for storing activity log
     * @var string
     */
    const OPTION_KEY = 'bluesky_activity_log';

    /**
     * Log a syndication event
     *
     * @param string $type Event type (syndication_success, syndication_failed, syndication_partial, auth_expired, rate_limited, circuit_opened, circuit_closed)
     * @param string $message Human-readable summary of the event
     * @param int|null $post_id WordPress post ID if applicable
     * @param string|null $account_id Account UUID if applicable
     * @return bool Whether the event was logged successfully
     */
    public function log_event($type, $message, $post_id = null, $account_id = null)
    {
        // Create event array
        $event = [
            'time' => time(),
            'type' => $type,
            'message' => $message,
            'post_id' => $post_id,
            'account_id' => $account_id
        ];

        // Get existing log
        $log = get_option(self::OPTION_KEY, []);
        if (!is_array($log)) {
            $log = [];
        }

        // Append new event
        $log[] = $event;

        // Trim to max entries (remove oldest first)
        if (count($log) > self::MAX_EVENTS) {
            $log = array_slice($log, -self::MAX_EVENTS);
        }

        // Save back to options
        return update_option(self::OPTION_KEY, $log);
    }

    /**
     * Get recent events (newest first)
     *
     * @param int $count Number of events to return (default 10, max 10)
     * @return array Array of event arrays, sorted by time descending
     */
    public function get_recent_events($count = 10)
    {
        // Limit count to maximum
        $count = min($count, self::MAX_EVENTS);

        // Get log from options
        $log = get_option(self::OPTION_KEY, []);
        if (!is_array($log)) {
            return [];
        }

        // Sort by time descending (newest first)
        usort($log, function ($a, $b) {
            return $b['time'] - $a['time'];
        });

        // Return limited count
        return array_slice($log, 0, $count);
    }

    /**
     * Get events filtered by type
     *
     * @param string $type Event type to filter by
     * @return array Array of matching event arrays
     */
    public function get_events_by_type($type)
    {
        // Get log from options
        $log = get_option(self::OPTION_KEY, []);
        if (!is_array($log)) {
            return [];
        }

        // Filter by type
        return array_filter($log, function ($event) use ($type) {
            return isset($event['type']) && $event['type'] === $type;
        });
    }

    /**
     * Clear all logged events
     *
     * @return bool Whether the log was cleared successfully
     */
    public function clear_log()
    {
        return delete_option(self::OPTION_KEY);
    }

    /**
     * Format timestamp for display
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted time string
     */
    public static function format_event_time($timestamp)
    {
        $now = time();
        $diff = $now - $timestamp;

        // For events less than 24 hours old, use relative time
        if ($diff < DAY_IN_SECONDS) {
            return sprintf(
                __('%s ago', 'social-integration-for-bluesky'),
                human_time_diff($timestamp, $now)
            );
        }

        // For older events, use WordPress date format
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        return wp_date($date_format . ' ' . $time_format, $timestamp);
    }
}
