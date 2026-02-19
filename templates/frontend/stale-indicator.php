<?php
/**
 * Stale cache indicator template
 *
 * Displays "last updated X ago" message when serving stale cached data.
 *
 * @package BlueSky_Social_Integration
 * @since 1.6.0
 *
 * @var string $time_ago Human-readable time since last update (e.g., "5 minutes")
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bluesky-stale-indicator" style="font-size: 0.8em; color: #666; margin-top: 5px;">
    <?php
    printf(
        esc_html__('Last updated %s ago', 'social-integration-for-bluesky'),
        esc_html($time_ago)
    );
    ?>
</div>
