<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $profile - Profile data array
// $classes - Array of CSS classes
// $aria_label - Aria label for the card
?>

<aside class="<?php echo esc_attr(
    implode(" ", $classes),
); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">

    <?php do_action("bluesky_before_profile_card_content", $profile); ?>

    <div class="bluesky-social-integration-image" style="--bluesky-social-integration-banner: url(<?php echo isset(
        $profile["banner"],
    )
        ? esc_url($profile["banner"])
        : BLUESKY_PLUGIN_FOLDER . "/assets/img/banner@2x.png"; ?>)">
        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
?>
        <img class="avatar bluesky-social-integration-avatar" width="80" height="80" src="<?php echo esc_url(
            $profile["avatar"],
        ); ?>" alt="">
    </div>

    <div class="bluesky-social-integration-content">
        <h2 class="bluesky-social-integration-name"><?php echo esc_html(
            $profile["displayName"],
        ); ?></h2>
        <p class="bluesky-social-integration-handle"><a href="https://bsky.app/profile/<?php echo esc_attr(
            $profile["handle"],
        ); ?>" aria-label="<?php echo esc_attr(sprintf(
            /* translators: %s is the Bluesky handle */
            __("@%s on Bluesky", "social-integration-for-bluesky"),
            $profile["handle"]
        )); ?>"><span>@</span><?php echo esc_html($profile["handle"]); ?></a></p>
        <p class="bluesky-social-integration-followers">
            <span class="followers"><span class="nb"><?php echo esc_html(
                intval($profile["followersCount"]),
            ) .
                "</span>&nbsp;" .
                esc_html(
                    __("Followers", "social-integration-for-bluesky"),
                ); ?></span>
            <span class="follows"><span class="nb"><?php echo esc_html(
                intval($profile["followsCount"]),
            ) .
                "</span>&nbsp;" .
                esc_html(
                    __("Following", "social-integration-for-bluesky"),
                ); ?></span>
            <span class="posts"><span class="nb"><?php echo esc_html(
                intval($profile["postsCount"]),
            ) .
                "</span>&nbsp;" .
                esc_html(
                    __("Posts", "social-integration-for-bluesky"),
                ); ?></span>
        </p>
        <?php if (isset($profile["description"])) { ?>
            <p class="bluesky-social-integration-description"><?php echo nl2br(
                esc_html($profile["description"]),
            ); ?></p>
        <?php } ?>
    </div>

    <?php do_action("bluesky_after_profile_card_content", $profile); ?>

</aside>
