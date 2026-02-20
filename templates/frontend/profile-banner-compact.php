<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $profile - Profile data array
// $classes - Array of CSS classes
// $aria_label - Aria label for the card
// $needs_gradient_fallback - Boolean flag for missing banner
?>

<aside class="<?php echo esc_attr(implode(" ", array_filter($classes))); ?>"
     aria-label="<?php echo esc_attr($aria_label); ?>"
     <?php if ($needs_gradient_fallback && !empty($profile['avatar'])): ?>
     data-avatar-url="<?php echo esc_url($profile['avatar']); ?>"
     <?php endif; ?>>

    <?php do_action("bluesky_before_profile_card_content", $profile); ?>

    <div class="bluesky-social-integration-image"
         style="<?php if (!$needs_gradient_fallback && !empty($profile['banner'])): ?>--bluesky-profile-banner: url(<?php echo esc_url($profile['banner']); ?>)<?php endif; ?>;">

        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
        <img class="avatar bluesky-social-integration-avatar"
             src="<?php echo esc_url($profile['avatar']); ?>"
             alt=""
             width="60"
             height="60"
             loading="lazy">

        <div class="bluesky-social-integration-content">
            <p class="bluesky-social-integration-name">
                <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($profile['displayName']); ?>
                </a>
            </p>

            <p class="bluesky-social-integration-handle">
                <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>" target="_blank" rel="noopener noreferrer">
                    <span>@</span><?php echo esc_html($profile['handle']); ?>
                </a>
            </p>

            <?php if (!empty($profile['description'])): ?>
            <p class="bluesky-social-integration-description">
                <?php echo nl2br(esc_html($profile['description'])); ?>
            </p>
            <?php endif; ?>

            <p class="bluesky-social-integration-followers">
                <span class="followers"><span class="nb"><?php echo esc_html(number_format_i18n(intval($profile['followersCount']))); ?></span>&nbsp;<?php esc_html_e('Followers', 'social-integration-for-bluesky'); ?></span>
                <span class="follows"><span class="nb"><?php echo esc_html(number_format_i18n(intval($profile['followsCount']))); ?></span>&nbsp;<?php esc_html_e('Following', 'social-integration-for-bluesky'); ?></span>
                <span class="posts"><span class="nb"><?php echo esc_html(number_format_i18n(intval($profile['postsCount']))); ?></span>&nbsp;<?php esc_html_e('Posts', 'social-integration-for-bluesky'); ?></span>
            </p>
        </div>
    </div>

    <?php do_action("bluesky_after_profile_card_content", $profile); ?>

</aside>
