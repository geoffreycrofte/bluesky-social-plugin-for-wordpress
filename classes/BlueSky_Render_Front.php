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

    // Shortcode for BlueSky profile card
    public function bluesky_last_posts_shortcode() {
        return $this -> render_bluesky_posts_list();
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
        $attributes = wp_parse_args($attributes, $defaults);

        // Extract variables
        $display_embeds = $attributes['displayEmbeds'];
        $theme = $attributes['theme'];
        $number_of_posts = $attributes['numberOfPosts'];

        $posts = $this -> api_handler -> fetch_bluesky_posts( intval( $number_of_posts ) );

        if ( isset ( $posts ) && is_array( $posts ) ) {

            // Apply theme class
            $theme_class = 'bluesky-theme-' . esc_attr( $theme );

            ob_start();
            /* echo '<pre>';
            var_dump( $posts );
            echo '</pre>'; */
            ?>
            <div class="bluesky-social-integration-last-post <?php echo esc_attr( $theme_class ); ?>">
                <ul class="bluesky-social-integration-last-post-list">
                    <?php foreach ($posts as $post): ?>
                        <li class="bluesky-social-integration-last-post-item">
                            <a title="<?php _e('Get to this post', 'bluesky-social'); ?>" href="<?php echo esc_url( $post['url'] ); ?>" class="bluesky-social-integration-last-post-link"><span class="screen-reader-text"><?php _e('Get to this post', 'bluesky-social'); ?></span></a>
                            <div class="bluesky-social-integration-last-post-header">
                                <img src="<?php echo esc_url( $post['account']['avatar'] ); ?>" width="42" height="42" alt="" class="avatar post-avatar">
                            </div>
                            <div class="bluesky-social-integration-last-post-content">                                
                                <p class="bluesky-social-integration-post-account-info-names">
                                    <?php //TODO: should I use aria-hidden on the name and handle to make it lighter for screenreaders? ?>
                                    <span class="bluesky-social-integration-post-account-info-name"><?php echo esc_html($post['account']['display_name']); ?></span>
                                    <span class="bluesky-social-integration-post-account-info-handle"><?php echo esc_html('@' . $post['account']['handle']); ?></span>
                                    <span class="bluesky-social-integration-post-account-info-date"><?php echo human_time_diff( strtotime( $post['created_at'] ), current_time( 'U' ) ) ; ?></span>
                                </p>

                                <div class="bluesky-social-integration-post-content-text">
                                
                                <?php // print post content 
                                echo nl2br( esc_html( $post['text'] ) ); ?>

                                </div>

                                <?php
                                // print potential embeds
                                if ( ! empty( $post['embedded_media'] ) && $display_embeds ):
                                        if ( $post['embedded_media']['type'] === 'video' ): ?>
                                    <div class="embedded-video">
                                        <img src="<?php echo esc_url($post['embedded_media']['video_details']['thumbnail_url']); ?>" 
                                            alt="<?php echo esc_attr($post['embedded_media']['alt']); ?>">
                                        <!-- You might want to add a video player or link here -->
                                        <p><a href="<?php echo esc_url($post['embedded_media']['video_details']['thumbnail_url']); ?>"><?php echo esc_url($post['embedded_media']['video_details']['thumbnail_url']); ?></a></p>
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
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            return ob_get_clean();
        } else {
            return '<p class="bluesky-posts-block no-posts">' . __( 'No posts available.', 'bluesky-social' ) . '</p>';
        }
    }

    // Shortcode for BlueSky profile card
    public function bluesky_profile_card_shortcode() {
        return $this -> render_bluesky_profile_card();
    }

    public function render_bluesky_profile_card( $attributes = [] ) {
        $profile = $this -> api_handler -> get_bluesky_profile();
        
        // TODO: write a fallback solution using cache
        if ( ! $profile ) {
            return '<p class="bluesky-social-integration-error">' . __('Unable to fetch BlueSky profile.', 'bluesky-social') . '</p>';
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
        
        $aria_label = sprintf(__('BlueSky Social Card of %s', 'bluesky-social'), $profile['displayName']);
        
        ob_start();
        ?>
        <aside class="<?php echo esc_attr( implode(' ', $classes ) ); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
            <div class="bluesky-social-integration-image" style="--bluesky-social-integration-banner: url('<?php echo esc_url( $profile['banner'] ); ?>')">
                <img class="avatar bluesky-social-integration-avatar" width="80" height="80" src="<?php echo esc_url( $profile['avatar'] ); ?>" alt="">
            </div>
            <div class="bluesky-social-integration-content">
                <p class="bluesky-social-integration-name"><?php echo esc_html( $profile['displayName'] ); ?></p>
                <p class="bluesky-social-integration-handle"><span>@</span><?php echo esc_html( $profile['handle'] ); ?></p>
                <p class="bluesky-social-integration-followers">
                    <span class="followers"><span class="nb"><?php echo intval( $profile['followersCount'] ) . '</span>&nbsp;' . __('Followers', 'bluesky-social'); ?></span>
                    <span class="follows"><span class="nb"><?php echo intval( $profile['followsCount'] ) . '</span>&nbsp;' . __('Following', 'bluesky-social'); ?></span>
                    <span class="posts"><span class="nb"><?php echo intval( $profile['postsCount'] ) . '</span>&nbsp;' . __('Posts', 'bluesky-social'); ?></span>
                </p>
                <p class="bluesky-social-integration-description"><?php echo nl2br( esc_html( $profile['description'] ) ); ?></p>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
}
