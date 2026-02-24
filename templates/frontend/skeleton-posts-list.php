<?php
/**
 * Skeleton loader for posts list
 *
 * Displays placeholder post cards matching the posts list structure
 * while content is loading.
 *
 * @package BlueSky_Social_Integration
 * @since 1.7.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bluesky-social-integration-last-post bluesky-posts-loading" role="status" aria-busy="true" aria-label="<?php esc_attr_e('Loading Bluesky Posts', 'social-integration-for-bluesky'); ?>">
    <ul class="bluesky-social-integration-last-post-list">
        <?php for ($i = 0; $i < 3; $i++): ?>
        <li class="bluesky-social-integration-last-post-item bluesky-post-skeleton">
            <!-- Avatar placeholder -->
            <div class="bluesky-skeleton bluesky-skeleton-avatar bluesky-skeleton-avatar--post"></div>

            <div class="bluesky-social-integration-last-post-content">
                <!-- Name placeholder -->
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-name"></div>

                <!-- Handle placeholder -->
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-handle"></div>

                <!-- Content text placeholders (3 lines) -->
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long bluesky-skeleton-content-line"></div>
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long bluesky-skeleton-content-line"></div>
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-partial"></div>

                <!-- Engagement counters placeholder -->
                <div class="bluesky-skeleton-counters">
                    <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-counter"></div>
                    <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-counter"></div>
                    <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-counter"></div>
                </div>
            </div>
        </li>
        <?php endfor; ?>
    </ul>
</div>
