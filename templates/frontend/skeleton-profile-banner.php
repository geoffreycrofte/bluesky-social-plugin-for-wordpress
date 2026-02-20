<?php
/**
 * Skeleton loader for profile banner
 *
 * Displays placeholder layout matching the profile banner structure
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
<div class="bluesky-profile-banner bluesky-profile-banner-loading" aria-label="<?php esc_attr_e('Loading Bluesky Profile', 'social-integration-for-bluesky'); ?>">
    <!-- Banner placeholder -->
    <div class="bluesky-skeleton bluesky-skeleton-banner"></div>

    <!-- Avatar placeholder (overlapping banner) -->
    <div class="bluesky-skeleton bluesky-skeleton-avatar"></div>

    <!-- Profile info placeholders -->
    <div class="bluesky-profile-banner-content">
        <!-- Name placeholder -->
        <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-short"></div>

        <!-- Handle placeholder -->
        <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-short" style="width: 50%;"></div>

        <!-- Bio placeholder (2 lines) -->
        <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long"></div>
        <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long" style="width: 80%;"></div>

        <!-- Stats row placeholder (3 items) -->
        <div class="bluesky-profile-banner-stats">
            <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 80px;"></div>
            <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 80px;"></div>
            <div class="bluesky-skeleton bluesky-skeleton-text" style="width: 80px;"></div>
        </div>
    </div>
</div>
