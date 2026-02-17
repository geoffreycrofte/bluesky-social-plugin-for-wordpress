<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $posts - Array of posts data
// $classes - CSS classes string
// $layout - Layout type (default or layout_2)
// $display_embeds - Boolean for displaying embeds
// $no_counters - Boolean for hiding counters
// $this - BlueSky_Render_Front instance for method calls
?>

<?php if (isset($posts) && is_array($posts) && count($posts) > 0): ?>

<aside class="bluesky-social-integration-last-post<?php echo esc_attr(
    $classes,
); ?>" aria-label="<?php esc_attr_e(
   "List of the latest Bluesky Posts",
   "social-integration-for-bluesky",
); ?>">

    <?php if ($layout === "layout_2") {
        $profile_helpers = new BlueSky_Helpers();
        $profile_cache_key = $profile_helpers->get_profile_transient_key();
        $profile = get_transient($profile_cache_key);
        if ($profile === false && (!defined('DOING_AJAX') || !DOING_AJAX) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
            // No cached profile: render a small profile skeleton
            ?>
            <div class="bluesky-social-integration-profile-card-embedded bluesky-async-placeholder">
                <div class="bluesky-social-integration-image">
                    <span class="avatar bluesky-social-integration-avatar bluesky-skeleton-box" style="width:40px;height:40px;display:inline-block;"></span>
                    <div class="bluesky-social-integration-content">
                        <div class="bluesky-social-integration-content-names">
                            <p class="bluesky-social-integration-name"><span class="bluesky-skeleton-box" style="width:100px;height:1em;display:inline-block;"></span></p>
                            <p class="bluesky-social-integration-handle"><span class="bluesky-skeleton-box" style="width:80px;height:1em;display:inline-block;"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php } elseif ($profile) {
            // Cached profile: render full embedded profile
        ?>
    <div class="bluesky-social-integration-profile-card-embedded">
        <div class="bluesky-social-integration-image" style="--bluesky-social-integration-banner: url(<?php echo isset(
            $profile["banner"],
        )
            ? esc_url($profile["banner"])
            : BLUESKY_PLUGIN_FOLDER .
                "/assets/img/banner@2x.png"; ?>)">
            <?php
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
        ?>
            <img class="avatar bluesky-social-integration-avatar" width="40" height="40" src="<?php echo esc_url(
                $profile["avatar"],
            ); ?>" alt="">

            <div class="bluesky-social-integration-content">
                <div class="bluesky-social-integration-content-names">
                    <p class="bluesky-social-integration-name"><?php echo esc_html(
                        $profile["displayName"],
                    ); ?></p>
                    <p class="bluesky-social-integration-handle"><span>@</span><?php echo esc_html(
                        $profile["handle"],
                    ); ?></p>
                </div>
                <a class="bluesky-social-integration-profile-button" href="https://bsky.app/profile/<?php echo esc_attr(
                    $profile["handle"],
                ); ?>"><span class="screen-reader-text"><?php esc_html_e(
   "See Bluesky Profile",
   "social-integration-for-bluesky",
); ?></span><svg width="27" height="24" viewBox="0 0 27 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.1474 1.77775C18.0519 4.08719 14.7224 8.76976 13.4999 11.2827C12.2774 8.76994 8.94803 4.08714 5.85245 1.77775C3.61891 0.111357 -7.41864e-07 -1.17801 -7.41864e-07 2.92481C-7.41864e-07 3.7442 0.47273 9.80811 0.749991 10.7926C1.71375 14.2152 5.22563 15.0881 8.34952 14.5598C2.88903 15.4834 1.49994 18.5426 4.49985 21.6018C10.1973 27.4118 12.6887 20.144 13.3274 18.2817C13.4444 17.9403 13.4992 17.7806 13.5 17.9164C13.5008 17.7806 13.5556 17.9403 13.6726 18.2817C14.311 20.144 16.8024 27.412 22.5002 21.6018C25.5001 18.5426 24.1111 15.4832 18.6505 14.5598C21.7745 15.0881 25.2864 14.2152 26.25 10.7926C26.5273 9.80801 27 3.74411 27 2.92481C27 -1.17801 23.381 0.111357 21.1476 1.77775H21.1474Z" fill="currentColor"/>
</svg></a>
            </div>
        </div>
    </div>
        <?php }
    } ?>

    <ul class="bluesky-social-integration-last-post-list">

        <?php
        do_action("bluesky_before_post_list_content", $posts);

        foreach ($posts as $post):
            do_action(
                "bluesky_before_post_list_item_markup",
                $post,
            ); ?>

        <li class="bluesky-social-integration-last-post-item">

            <?php do_action(
                "bluesky_before_post_list_item_content",
                $post,
            ); ?>

            <a title="<?php echo esc_attr(
                __(
                    "Get to this post",
                    "social-integration-for-bluesky",
                ),
            ); ?>" href="<?php echo esc_url(
   $post["url"],
); ?>" class="bluesky-social-integration-last-post-link"><span class="screen-reader-text"><?php echo esc_html(
   __("Get to this post", "social-integration-for-bluesky"),
); ?></span></a>
            <div class="bluesky-social-integration-last-post-header">
                <?php
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
            ?>
                <img src="<?php echo esc_url(
                    $post["account"]["avatar"],
                ); ?>" width="42" height="42" alt="" class="avatar post-avatar">
            </div>
            <div class="bluesky-social-integration-last-post-content">
                <p class="bluesky-social-integration-post-account-info-names">
                    <?php
            //TODO: should I use aria-hidden on the name and handle to make it lighter for screenreaders?
            ?>
                    <span class="bluesky-social-integration-post-account-info-name"><?php echo esc_html(
                        $post["account"]["display_name"],
                    ); ?></span>
                    <span class="bluesky-social-integration-post-account-info-handle"><?php echo esc_html(
                        "@" . $post["account"]["handle"],
                    ); ?></span>
                    <span class="bluesky-social-integration-post-account-info-date"><?php echo str_replace(
                        " ",
                        " ",
                        esc_html(
                            human_time_diff(
                                strtotime($post["created_at"]),
                                current_time("U"),
                            ),
                        ),
                    ); ?></span>
                </p>

                <div class="bluesky-social-integration-post-content-text"<?php echo isset(
                    $post["langs"],
                ) && is_array($post["langs"])
                    ? ' lang="' . $post["langs"][0] . '"'
                    : ""; ?>>

                <?php
                echo $this->render_bluesky_post_content($post);

                // print the gallery of images if any
                if (!empty($post["images"]) && $display_embeds):

                    wp_enqueue_style(
                        "bluesky-social-lightbox",
                        BLUESKY_PLUGIN_FOLDER .
                            "assets/css/bluesky-social-lightbox.css",
                        [],
                        BLUESKY_PLUGIN_VERSION,
                    );
                    wp_enqueue_script(
                        "bluesky-social-lightbox",
                        BLUESKY_PLUGIN_FOLDER .
                            "assets/js/bluesky-social-lightbox.js",
                        [],
                        BLUESKY_PLUGIN_VERSION,
                        [
                            "in_footer" => true,
                            "strategy" => "defer",
                        ],
                    );
                    ?>
                    <div class="bluesky-social-integration-post-gallery" style="--bluesky-gallery-nb: <?php echo esc_attr(
                        count($post["images"]),
                    ); ?>">
                        <?php foreach (
                            $post["images"]
                            as $image
                        ): ?>
                        <a href="<?php echo esc_url(
                            $image["url"],
                        ); ?>" class="bluesky-gallery-image"><?php
                            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                            ?><img src="<?php echo esc_url(
   $image["url"],
); ?>" alt="<?php echo isset($image["alt"])
   ? esc_attr($image["alt"])
   : ""; ?>" <?php echo !empty($image["width"]) && $image["width"] != "0"
   ? ' width="' . esc_attr($image["width"]) . '"'
   : ""; ?> <?php echo !empty($image["height"]) && $image["height"] != "0"
    ? ' height="' . esc_attr($image["height"]) . '"'
    : ""; ?> loading="lazy"></a>
                        <?php endforeach; ?>
                    </div>

                <?php
                endif;
                ?>

                </div>

                <?php
                // displays potential media
                if (
                    !empty($post["external_media"]) &&
                    $display_embeds
                ):
                    if (
                        isset($post["external_media"]["uri"]) &&
                        strpos(
                            $post["external_media"]["uri"],
                            "youtu",
                        )
                    ):
                        $helpers = new BlueSky_Helpers();
                        $youtube_id = $helpers->get_youtube_id(
                            $post["external_media"]["uri"],
                        );

                        if ($youtube_id):
                            $post["external_media"]["thumb"] =
                                "https://i.ytimg.com/vi/" .
                                $youtube_id .
                                "/maxresdefault.jpg";
                        endif;
                    endif; ?>

                <?php echo isset($post["external_media"]["uri"])
                    ? '<a href="' .
                        esc_url($post["external_media"]["uri"]) .
                        '" class="bluesky-social-integration-embedded-record is-external_media' .
                        (isset($post["external_media"]["thumb"])
                            ? " has-image"
                            : "") .
                        '">'
                    : ""; ?>
                <div class="bluesky-social-integration-last-post-content">

                    <div class="bluesky-social-integration-external-image">
                        <?php
                    // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                    ?>
                        <?php echo isset(
                            $post["external_media"]["thumb"],
                        )
                            ? '<img src="' .
                                esc_url(
                                    $post["external_media"][
                                        "thumb"
                                    ],
                                ) .
                                '" loading="lazy" alt="">'
                            : ""; ?>
                    </div>
                    <div class="bluesky-social-integration-external-content">
                        <?php echo isset(
                            $post["external_media"]["title"],
                        )
                            ? '<p class="bluesky-social-integration-external-content-title">' .
                                esc_html(
                                    $post["external_media"][
                                        "title"
                                    ],
                                ) .
                                "</p>"
                            : ""; ?>
                        <?php echo isset(
                            $post["external_media"]["description"],
                        )
                            ? '<p class="bluesky-social-integration-external-content-description">' .
                                esc_html(
                                    $post["external_media"][
                                        "description"
                                    ],
                                ) .
                                "</p>"
                            : ""; ?>
                        <?php echo isset(
                            $post["external_media"]["uri"],
                        )
                            ? '<p class="bluesky-social-integration-external-content-url"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" stroke-width="2"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path><path d="M3.6 9h16.8"></path><path d="M3.6 15h16.8"></path><path d="M11.5 3a17 17 0 0 0 0 18"></path><path d="M12.5 3a17 17 0 0 1 0 18"></path></svg>' .
                                esc_html(
                                    explode(
                                        "/",
                                        $post["external_media"][
                                            "uri"
                                        ],
                                    )[2],
                                ) .
                                "</p>"
                            : ""; ?>
                    </div>
                </div>
                <?php echo isset($post["external_media"]["uri"])
                    ? "</a>"
                    : "";
                endif;

                // displays potential embeds
                if (
                    !empty($post["embedded_media"]) &&
                    $display_embeds
                ):
                    if (
                        $post["embedded_media"]["type"] === "video"
                    ):
                        $video = $post["embedded_media"]; ?>
                    <div class="blueksy-social-integration-embedded-video">
                        <?php
                        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                        ?>

                        <video controls playsinline poster="<?php echo esc_url(
                            $video["thumbnail_url"],
                        ); ?>">
                            <?php
                        // returns a .m3u8 playlist with at least 2 video quality 480p and 720p
                        ?>
                            <source src="<?php echo esc_url(
                                $video["playlist_url"],
                            ); ?>" type="application/x-mpegURL">
                            <?php
                        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                        ?>
                            <img src="<?php echo esc_url(
                                $video["thumbnail_url"],
                            ); ?>"  alt="<?php echo esc_attr(
   isset($video["alt"]) ? $video["alt"] : "",
); ?>">
                        </video>
                    </div>
                <?php
                    elseif (
                        $post["embedded_media"]["type"] === "record"
                    ):
                        $hasURL =
                            isset($post["embedded_media"]["url"]) &&
                            !empty(
                                $post["embedded_media"]["url"]
                            ); ?>
                    <<?php echo $hasURL
                        ? 'a href="' .
                            esc_url(
                                $post["embedded_media"]["url"],
                            ) .
                            '"'
                        : "div"; ?> class="bluesky-social-integration-embedded-record is-embedded_media">
                        <div class="bluesky-social-integration-last-post-content">
                            <p><small class="bluesky-social-integration-post-account-info-name"><?php echo esc_html(
                                $post["embedded_media"]["author"][
                                    "display_name"
                                ],
                            ); ?></small></p>
                            <p><?php echo nl2br(
                                esc_html(
                                    $post["embedded_media"]["text"],
                                ),
                            ); ?></p>
                        </div>
                    </<?php echo $hasURL ? "a" : "div"; ?>>
                <?php
                    elseif (
                        $post["embedded_media"]["type"] ===
                        "starterpack"
                    ):
                        $hasURL =
                            isset($post["embedded_media"]["url"]) &&
                            !empty(
                                $post["embedded_media"]["url"]
                            ); ?>
                <<?php echo $hasURL
                    ? 'a href="' .
                        esc_url($post["embedded_media"]["url"]) .
                        '"'
                    : "div"; ?> class="bluesky-social-integration-embedded-record">
                    <div class="bluesky-social-integration-external-image">
                        <svg fill="none" width="40" viewBox="0 0 24 24" height="40"><defs><linearGradient x1="0" y1="0" x2="100%" y2="0" gradientTransform="rotate(45)" id="sky_gkpWQFtGs17eaqFdD5GTv"><stop offset="0" stop-color="#0A7AFF"></stop><stop offset="1" stop-color="#59B9FF"></stop></linearGradient></defs><path fill="url(#sky_gkpWQFtGs17eaqFdD5GTv)" fill-rule="evenodd" clip-rule="evenodd" d="M11.26 5.227 5.02 6.899c-.734.197-1.17.95-.973 1.685l1.672 6.24c.197.734.951 1.17 1.685.973l6.24-1.672c.734-.197 1.17-.951.973-1.685L12.945 6.2a1.375 1.375 0 0 0-1.685-.973Zm-6.566.459a2.632 2.632 0 0 0-1.86 3.223l1.672 6.24a2.632 2.632 0 0 0 3.223 1.861l6.24-1.672a2.631 2.631 0 0 0 1.861-3.223l-1.672-6.24a2.632 2.632 0 0 0-3.223-1.861l-6.24 1.672Z"></path><path fill="url(#sky_gkpWQFtGs17eaqFdD5GTv)" fill-rule="evenodd" clip-rule="evenodd" d="M15.138 18.411a4.606 4.606 0 1 0 0-9.211 4.606 4.606 0 0 0 0 9.211Zm0 1.257a5.862 5.862 0 1 0 0-11.724 5.862 5.862 0 0 0 0 11.724Z"></path></svg>
                    </div>
                    <div class="bluesky-social-integration-last-post-content">
                        <p>
                            <span class="bluesky-social-integration-post-starterpack-name"><?php echo esc_html(
                                $post["embedded_media"]["title"],
                            ); ?></span> • <small class="bluesky-social-integration-post-account-info-name"><?php echo esc_html(
   $post["embedded_media"]["author"]["display_name"],
); ?></small></p>
                        <p><?php echo nl2br(
                            esc_html(
                                $post["embedded_media"]["text"],
                            ),
                        ); ?></p>
                    </div>
                </<?php echo $hasURL ? "a" : "div"; ?>>
            <?php
                    endif;
                endif;
                ?>
            </div>

            <?php if (
                !$no_counters &&
                !empty($post["counts"]) &&
                ($post["counts"]["like"] > 0 ||
                    $post["counts"]["repost"] > 0 ||
                    $post["counts"]["reply"] > 0 ||
                    $post["counts"]["quote"] > 0)
            ): ?>
                <div class="bluesky-social-integration-post-counters">
                    <?php if ($post["counts"]["like"] > 0): ?>
                        <span class="bluesky-counter bluesky-counter-likes" title="<?php echo esc_attr(
                            sprintf(
                                _n(
                                    "%s like",
                                    "%s likes",
                                    $post["counts"]["like"],
                                    "social-integration-for-bluesky",
                                ),
                                number_format_i18n(
                                    $post["counts"]["like"],
                                ),
                            ),
                        ); ?>">
                            <span class="bluesky-counter-icon" aria-hidden="true">❤️</span>
                            <span class="bluesky-counter-value"><?php echo esc_html(
                                number_format_i18n(
                                    $post["counts"]["like"],
                                ),
                            ); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($post["counts"]["repost"] > 0): ?>
                        <span class="bluesky-counter bluesky-counter-reposts" title="<?php echo esc_attr(
                            sprintf(
                                _n(
                                    "%s repost",
                                    "%s reposts",
                                    $post["counts"]["repost"],
                                    "social-integration-for-bluesky",
                                ),
                                number_format_i18n(
                                    $post["counts"]["repost"],
                                ),
                            ),
                        ); ?>">
                            <span class="bluesky-counter-icon" aria-hidden="true">🔄</span>
                            <span class="bluesky-counter-value"><?php echo esc_html(
                                number_format_i18n(
                                    $post["counts"]["repost"],
                                ),
                            ); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($post["counts"]["reply"] > 0): ?>
                        <span class="bluesky-counter bluesky-counter-replies" title="<?php echo esc_attr(
                            sprintf(
                                _n(
                                    "%s reply",
                                    "%s replies",
                                    $post["counts"]["reply"],
                                    "social-integration-for-bluesky",
                                ),
                                number_format_i18n(
                                    $post["counts"]["reply"],
                                ),
                            ),
                        ); ?>">
                            <span class="bluesky-counter-icon" aria-hidden="true">💬</span>
                            <span class="bluesky-counter-value"><?php echo esc_html(
                                number_format_i18n(
                                    $post["counts"]["reply"],
                                ),
                            ); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($post["counts"]["quote"] > 0): ?>
                        <span class="bluesky-counter bluesky-counter-quotes" title="<?php echo esc_attr(
                            sprintf(
                                _n(
                                    "%s quote",
                                    "%s quotes",
                                    $post["counts"]["quote"],
                                    "social-integration-for-bluesky",
                                ),
                                number_format_i18n(
                                    $post["counts"]["quote"],
                                ),
                            ),
                        ); ?>">
                            <span class="bluesky-counter-icon" aria-hidden="true">💭</span>
                            <span class="bluesky-counter-value"><?php echo esc_html(
                                number_format_i18n(
                                    $post["counts"]["quote"],
                                ),
                            ); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php do_action(
                "bluesky_after_post_list_item_content",
                $post,
            ); ?>

        </li>

        <?php do_action(
            "bluesky_after_post_list_item_markup",
            $post,
        );
        endforeach;

        do_action("bluesky_after_post_list_content", $posts);
        ?>

    </ul>
</aside>

<?php else: ?>

<div class="bluesky-social-integration-last-post<?php echo esc_attr(
    $classes,
); ?> has-no-posts">
    <svg fill="none" width="64" viewBox="0 0 24 24" height="64">
        <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M3 4a1 1 0 0 1 1-1h1a8.003 8.003 0 0 1 7.75 6.006A7.985 7.985 0 0 1 19 6h1a1 1 0 0 1 1 1v1a8 8 0 0 1-8 8v4a1 1 0 1 1-2 0v-7a8 8 0 0 1-8-8V4Zm2 1a6 6 0 0 1 6 6 6 6 0 0 1-6-6Zm8 9a6 6 0 0 1 6-6 6 6 0 0 1-6 6Z"></path>
    </svg>
    <p class="bluesky-posts-block no-posts"><?php esc_html_e(
        "No posts available.",
        "social-integration-for-bluesky",
    ); ?></p>
</div>

<?php endif; ?>
