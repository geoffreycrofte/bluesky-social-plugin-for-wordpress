<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $profile - Profile data array
// $classes - Array of CSS classes
// $aria_label - Aria label for the banner
// $needs_gradient_fallback - Boolean flag for missing banner
?>

<div class="<?php echo esc_attr(implode(" ", $classes)); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">

    <?php do_action("bluesky_before_profile_banner_content", $profile); ?>

    <div class="bluesky-profile-banner-header <?php echo $needs_gradient_fallback ? 'bluesky-banner-gradient-pending' : ''; ?>"
         style="background-image: url(<?php echo esc_url($profile['banner'] ?? ''); ?>);"
         <?php if ($needs_gradient_fallback && !empty($profile['avatar'])): ?>
         data-avatar-url="<?php echo esc_url($profile['avatar']); ?>"
         <?php endif; ?>>
    </div>

    <div class="bluesky-profile-banner-content">
        <img class="bluesky-profile-banner-avatar"
             src="<?php echo esc_url($profile['avatar']); ?>"
             alt="<?php echo esc_attr($profile['displayName']); ?>"
             width="120"
             height="120"
             loading="lazy">

        <h2 class="bluesky-profile-banner-name">
            <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($profile['displayName']); ?>
            </a>
        </h2>

        <p class="bluesky-profile-banner-handle">
            <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>" target="_blank" rel="noopener noreferrer">
                @<?php echo esc_html($profile['handle']); ?>
            </a>
        </p>

        <?php if (!empty($profile['description'])): ?>
        <p class="bluesky-profile-banner-bio">
            <?php echo nl2br(esc_html($profile['description'])); ?>
        </p>
        <?php endif; ?>

        <div class="bluesky-profile-banner-stats">
            <span class="bluesky-stat">
                <strong><?php echo number_format_i18n(intval($profile['followersCount'])); ?></strong>
                <?php esc_html_e('Followers', 'social-integration-for-bluesky'); ?>
            </span>
            <span class="bluesky-stat">
                <strong><?php echo number_format_i18n(intval($profile['followsCount'])); ?></strong>
                <?php esc_html_e('Following', 'social-integration-for-bluesky'); ?>
            </span>
            <span class="bluesky-stat">
                <strong><?php echo number_format_i18n(intval($profile['postsCount'])); ?></strong>
                <?php esc_html_e('Posts', 'social-integration-for-bluesky'); ?>
            </span>
        </div>
    </div>

    <?php do_action("bluesky_after_profile_banner_content", $profile); ?>

</div>
