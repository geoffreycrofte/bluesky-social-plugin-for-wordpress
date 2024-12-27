<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Render_Front {
    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * API Handler instance
     * @var BlueSky_API_Handler
     */
    private $api_handler;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     */
    public function __construct(BlueSky_API_Handler $api_handler) {
        $this->api_handler = $api_handler;
        $this->options = get_option( BLUESKY_PLUGIN_OPTIONS );
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Shortcodes
        add_shortcode('bluesky_profile', [$this, 'bluesky_profile_card_shortcode']);
        add_shortcode('bluesky_last_posts', [$this, 'bluesky_last_posts_shortcode']);
    }

    // Shortcode for BlueSky last posts
    public function bluesky_last_posts_shortcode($atts = []) {
        // Convert shortcode attributes to array and merge with defaults
        $attributes = shortcode_atts([
            'displayEmbeds' => true,
            'theme' => 'system',
            'numberOfPosts' => $this -> options['posts_limit']
        ], $atts);

        // Convert string boolean values to actual booleans
        $attributes['displayEmbeds'] = filter_var( $attributes['displayEmbeds'], FILTER_VALIDATE_BOOLEAN);

        return $this->render_bluesky_posts_list($attributes);
    }

    public function render_bluesky_posts_list( $attributes = [] ) {

        $limit = $this -> options['posts_limit'];
        // Set default attributes
        $defaults = [
            'displayEmbeds' => true,
            'theme' => 'light',
            'numberOfPosts' => $limit
        ];

        // Merge defaults with provided attributes
        $attributes = wp_parse_args( $attributes, $defaults );

        // Extract variables
        $display_embeds = $attributes['displayEmbeds'];
        $theme = $attributes['theme'];
        $number_of_posts = $attributes['numberOfPosts'];

        $posts = $this -> api_handler -> fetch_bluesky_posts( intval( $number_of_posts ) );

        if ( isset ( $posts ) && is_array( $posts ) ) {

            // Apply theme class
            $theme_class = 'theme-' . esc_attr( $theme );

            ob_start();
            do_action('bluesky_before_post_list_markup', $posts );
            ?>
            <div class="bluesky-social-integration-last-post <?php echo esc_attr( $theme_class ); ?>">
                <ul class="bluesky-social-integration-last-post-list">
                    <?php
                        do_action('bluesky_before_post_list_content', $posts );

                        foreach ( $posts as $post ):
                            do_action('bluesky_before_post_list_item_markup', $post );
                    ?>
                        <li class="bluesky-social-integration-last-post-item">

                            <?php do_action('bluesky_before_post_list_item_content', $post ); ?>

                            <a title="<?php echo esc_attr( __('Get to this post', 'social-integration-for-bluesky') ); ?>" href="<?php echo esc_url( $post['url'] ); ?>" class="bluesky-social-integration-last-post-link"><span class="screen-reader-text"><?php echo esc_html( __('Get to this post', 'social-integration-for-bluesky') ); ?></span></a>
                            <div class="bluesky-social-integration-last-post-header">
                                <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                                <img src="<?php echo esc_url( $post['account']['avatar'] ); ?>" width="42" height="42" alt="" class="avatar post-avatar">
                            </div>
                            <div class="bluesky-social-integration-last-post-content">                                
                                <p class="bluesky-social-integration-post-account-info-names">
                                    <?php //TODO: should I use aria-hidden on the name and handle to make it lighter for screenreaders? ?>
                                    <span class="bluesky-social-integration-post-account-info-name"><?php echo esc_html($post['account']['display_name']); ?></span>
                                    <span class="bluesky-social-integration-post-account-info-handle"><?php echo esc_html('@' . $post['account']['handle']); ?></span>
                                    <span class="bluesky-social-integration-post-account-info-date"><?php echo esc_html( human_time_diff( strtotime( $post['created_at'] ), current_time( 'U' ) ) ); ?></span>
                                </p>

                                <div class="bluesky-social-integration-post-content-text">
                                
                                <?php
                                // print post content 
                                echo nl2br( esc_html( $post['text'] ) );

                                // print the gallery of images if any
                                if ( ! empty( $post['images'] ) ) :
                                ?>
                                    <div class="bluesky-social-integration-post-gallery" style="--bluesky-gallery-nb: <?php echo esc_attr( count( $post['images'] ) ); ?>">
                                        <?php foreach ( $post['images'] as $image ) : ?>
                                        <a href="<?php echo esc_url( $image['url'] ); ?>"><?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?><img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo isset( $image['alt'] ) ? esc_attr( $image['alt'] ) : ''; ?>" <?php echo ! empty( $image['width'] ) && $image['width'] != '0' ? ' width="' . $image['width'] . '"' : ''; ?> <?php echo ! empty( $image['height'] ) && $image['height'] != '0' ? ' height="' . $image['height'] . '"' : ''; ?>></a>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                <?php endif; ?>

                                </div>

                                <?php
                                // displays potential media
                                if ( ! empty( $post['external_media']  )  && $display_embeds ) :

                                    if ( isset( $post['external_media']['uri'] ) && ( strpos( $post['external_media']['uri'] , 'youtu' ) ) ) :
                                        $helpers = new BlueSky_Helpers();
                                        $youtube_id = $helpers -> get_youtube_id( $post['external_media']['uri'] );

                                        if ( $youtube_id ):
                                            $post['external_media']['thumb'] = 'https://i.ytimg.com/vi/' . $youtube_id . '/maxresdefault.jpg';
                                        endif;
                                    endif;
                                ?>

                                <?php echo isset( $post['external_media']['uri'] ) ? '<a href="' . esc_url( $post['external_media']['uri'] ) . '" class="bluesky-social-integration-embedded-record' . ( isset( $post['external_media']['thumb'] ) ? ' has-image' : '' ) . '">' : ''; ?>
                                <div class="bluesky-social-integration-last-post-content">
                                    
                                    <div class="bluesky-social-integration-external-image">
                                        <?php echo isset( $post['external_media']['thumb'] ) ? '<img src="' . esc_url( $post['external_media']['thumb'] ) . '" loading="lazy" alt="">' : ''; ?>
                                    </div>
                                    <div class="bluesky-social-integration-external-content">
                                        <?php echo isset( $post['external_media']['title'] ) ? '<p class="bluesky-social-integration-external-content-title">' . esc_html( $post['external_media']['title'] ) . '</p>' : '';  ?>
                                        <?php echo isset( $post['external_media']['description'] ) ? '<p class="bluesky-social-integration-external-content-description">' . esc_html( $post['external_media']['description'] ) . '</p>' : '';  ?>
                                        <?php echo isset( $post['external_media']['uri'] ) ? '<p class="bluesky-social-integration-external-content-url"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" stroke-width="2"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path><path d="M3.6 9h16.8"></path><path d="M3.6 15h16.8"></path><path d="M11.5 3a17 17 0 0 0 0 18"></path><path d="M12.5 3a17 17 0 0 1 0 18"></path></svg>' . esc_html( explode( '/', $post['external_media']['uri'] )[2] ) . '</p>' : ''; ?>
                                    </div>
                                </div>
                                <?php

                                    echo isset( $post['external_media']['uri'] ) ? '</a>' : '';
                                endif;

                                // displays potential embeds
                                if ( ! empty( $post['embedded_media'] ) && $display_embeds ):
                                        if ( $post['embedded_media']['type'] === 'video' ):

                                        $video = $post['embedded_media'];
                                ?>
                                    <div class="embedded-video">
                                        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                                        
                                        <video controls playsinline poster="<?php echo esc_url( $video['thumbnail_url'] ); ?>">
                                            <?php // returns a .m3u8 playlist with at least 2 video quality 480p and 720p  ?>
                                            <source src="<?php echo esc_url( $video['playlist_url'] ); ?>" type="application/x-mpegURL">
                                            <img src="<?php echo esc_url( $video['thumbnail_url'] ); ?>"  alt="<?php echo esc_attr( isset( $video['alt'] ) ? $video['alt'] : '' ); ?>">
                                        </video>
                                    </div>
                                <?php 
                                        elseif ( $post['embedded_media']['type'] === 'record' ):
                                            $hasURL = isset( $post['embedded_media']['url'] ) && ! empty( $post['embedded_media']['url'] );
                                ?>
                                    <<?php echo $hasURL ? 'a href="' . esc_url( $post['embedded_media']['url'] ) . '"' : 'div'; ?> class="bluesky-social-integration-embedded-record">
                                        <div class="bluesky-social-integration-last-post-content">
                                            <p><small class="bluesky-social-integration-post-account-info-name"><?php echo esc_html($post['embedded_media']['author']['display_name']); ?></small></p>
                                            <p><?php echo nl2br( esc_html( $post['embedded_media']['text'] ) ); ?></p>
                                        </div>
                                    </<?php echo $hasURL ? 'a' : 'div'; ?>>
                                <?php 
                                    endif;
                                endif; ?>
                            </div>

                            <?php do_action('bluesky_after_post_list_item_content', $post ); ?>

                        </li>
                    <?php
                        do_action('bluesky_after_post_list_item_markup', $post );
                        endforeach;
                    
                    do_action('bluesky_after_post_list_content', $posts );
                    ?>
                </ul>
            </div>
            <?php
            do_action('bluesky_after_post_list_markup', $posts );
            return ob_get_clean();
        } else {
            return '<p class="bluesky-posts-block no-posts">' . __( 'No posts available.', 'social-integration-for-bluesky' ) . '</p>';
        }
    }

    // Shortcode for BlueSky profile card
    public function bluesky_profile_card_shortcode( $atts = [] ) {
        // Convert shortcode attributes to array and merge with defaults
        $attributes = shortcode_atts([
            'theme' => 'system',
            'styleClass' => '',
            'displayBanner' => true,
            'displayAvatar' => true,
            'displayCounters' => true,
            'displayBio' => true
        ], $atts);

        // Convert string boolean values to actual booleans
        $boolean_attrs = ['displayBanner', 'displayAvatar', 'displayCounters', 'displayBio'];
        foreach ( $boolean_attrs as $attr ) {
            $attributes[ $attr ] = filter_var( $attributes[ $attr ], FILTER_VALIDATE_BOOLEAN );
        }

        return $this -> render_bluesky_profile_card( $attributes );
    }

    /**
     * Render the BlueSky profile card
     * @param array $attributes Shortcode attributes
     * @return string HTML output
     */
    public function render_bluesky_profile_card( $attributes = [] ) {
        $profile = $this -> api_handler -> get_bluesky_profile();
        
        // TODO: write a fallback solution using cache
        if ( ! $profile ) {
            return '<p class="bluesky-social-integration-error">' . esc_html__('Unable to fetch BlueSky profile.', 'social-integration-for-bluesky') . '</p>';
        }

        $classes = [ 'bluesky-social-integration-profile-card', $attributes['styleClass'] ];
        
        if ( isset( $attributes['theme'] ) ) {
            $classes[] = 'theme-' . esc_attr( $attributes['theme'] );
        }
        
        $display_elements = ['Banner', 'Avatar', 'Counters', 'Bio'];
        foreach ( $display_elements as $element ) {
            $option_key = 'display' . $element;
            if ( isset( $attributes[ $option_key] ) && $attributes[ $option_key ] === false ) {
                $classes[] = 'no-' . strtolower( $element );
            }
        }

        $aria_label = is_array( apply_filters('bluesky_profile_card_classes', $classes, $profile ) ) ?? $classes;

        // translators: %s is the profile display used in an aria-label attribute
        $aria_label = sprintf(__('BlueSky Social Card of %s', 'social-integration-for-bluesky'), $profile['displayName']);
        $aria_label = apply_filters('bluesky_profile_card_aria_label', $aria_label, $profile );
        
        ob_start();
        do_action('bluesky_before_profile_card_markup', $profile );
        ?>
        <aside class="<?php echo esc_attr( implode(' ', $classes ) ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
            <?php do_action('bluesky_before_profile_card_content', $profile ); ?>
            <div class="bluesky-social-integration-image" style="--bluesky-social-integration-banner: url('<?php echo esc_url( $profile['banner'] ); ?>')">
                <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                <img class="avatar bluesky-social-integration-avatar" width="80" height="80" src="<?php echo esc_url( $profile['avatar'] ); ?>" alt="">
            </div>
            <div class="bluesky-social-integration-content">
                <p class="bluesky-social-integration-name"><?php echo esc_html( $profile['displayName'] ); ?></p>
                <p class="bluesky-social-integration-handle"><a href="https://bsky.app/profile/<?php echo esc_attr( $profile['handle'] ); ?>"><span>@</span><?php echo esc_html( $profile['handle'] ); ?></a></p>
                <p class="bluesky-social-integration-followers">
                    <span class="followers"><span class="nb"><?php echo esc_html( intval( $profile['followersCount'] ) ) . '</span>&nbsp;' . esc_html( __('Followers', 'social-integration-for-bluesky') ); ?></span>
                    <span class="follows"><span class="nb"><?php echo esc_html( intval( $profile['followsCount'] ) ) . '</span>&nbsp;' . esc_html( __('Following', 'social-integration-for-bluesky') ); ?></span>
                    <span class="posts"><span class="nb"><?php echo esc_html( intval( $profile['postsCount'] ) ) . '</span>&nbsp;' . esc_html( __('Posts', 'social-integration-for-bluesky') ); ?></span>
                </p>
                <p class="bluesky-social-integration-description"><?php echo nl2br( esc_html( $profile['description'] ) ); ?></p>
            </div>
            <?php do_action('bluesky_after_profile_card_content', $profile ); ?>
        </aside>
        <?php
        do_action('bluesky_after_profile_card_markup', $profile );
        return ob_get_clean();
    }
}
