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
<div class="bluesky-social-integration-last-post bluesky-posts-loading" aria-label="<?php esc_attr_e('Loading Bluesky Posts', 'social-integration-for-bluesky'); ?>">
    <ul class="bluesky-social-integration-last-post-list">
        <?php for ($i = 0; $i < 3; $i++): ?>
        <li class="bluesky-social-integration-last-post-item bluesky-post-skeleton">
            <!-- Avatar placeholder -->
            <div class="bluesky-skeleton bluesky-skeleton-avatar" style="width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;"></div>

            <div class="bluesky-social-integration-last-post-content">
                <!-- Name placeholder -->
                <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 120px; height: 1em; margin-bottom: 0.4em;"></div>

                <!-- Handle placeholder -->
                <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 80px; height: 0.875em; margin-bottom: 0.6em;"></div>

                <!-- Content text placeholders (3 lines) -->
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long" style="margin-bottom: 0.4em;"></div>
                <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long" style="margin-bottom: 0.4em;"></div>
                <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 60%;"></div>

                <!-- Engagement counters placeholder -->
                <div style="display: flex; gap: 16px; margin-top: 12px;">
                    <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 40px; height: 1em;"></div>
                    <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 40px; height: 1em;"></div>
                    <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 40px; height: 1em;"></div>
                </div>
            </div>
        </li>
        <?php endfor; ?>
    </ul>
</div>
