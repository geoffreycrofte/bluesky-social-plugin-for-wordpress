<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Social_Integration {

    private $bluesky_api_url = 'https://bsky.social/xrpc/';

    private $options;

    private $did = null;

    private $access_token = null;

    public function __construct() {
        // Initialize plugin
        add_action('init', [$this, 'init']);
        
        // Admin menu and settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Add actions to do the actual work on bluesky
        add_action('wp_ajax_fetch_bluesky_posts', [$this, 'fetch_bluesky_posts']);
        add_action('wp_ajax_nopriv_fetch_bluesky_posts', [$this, 'fetch_bluesky_posts']);

        add_action('wp_ajax_get_bluesky_profile', [$this, 'get_bluesky_profile_for_ajax']);
        add_action('wp_ajax_nopriv_get_bluesky_profile', [$this, 'get_bluesky_profile_for_ajax']);

        add_action('publish_post', [$this, 'syndicate_post_to_bluesky'], 10, 1);

        // Set options
        $this -> options = get_option( BLUESKY_PLUGIN_OPTIONS );

        // block and widgets
        add_action('widgets_init', [$this, 'register_widgets']);
        add_action('enqueue_block_editor_assets', [$this, 'register_gutenberg_blocks']);
    }

    public function init() {
        // Load plugin textdomain for internationalization
        load_plugin_textdomain('bluesky-social', false, BLUESKY_PLUGIN_DIRECTORY_NAME . '/languages');
        
        // Enqueue necessary scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        if ( ! is_admin() ) {
            wp_enqueue_style('bluesky-social-style-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css');
            wp_enqueue_style('bluesky-social-style-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css');
        }

        // Adds shortcode
        add_shortcode('bluesky_profile', [$this, 'bluesky_profile_card_shortcode']);
        add_shortcode('bluesky_last_posts', [$this, 'bluesky_last_posts_shortcode']);
    }

    public function add_admin_menu() {
        add_options_page(
            __('BlueSky Social Integration', 'bluesky-social'),
            __('BlueSky Settings', 'bluesky-social'),
            'manage_options',
            'bluesky-social-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('bluesky_settings_group', BLUESKY_PLUGIN_OPTIONS );

        add_settings_section(
            'bluesky_main_settings',
            __('BlueSky Account Settings', 'bluesky-social'),
            [$this, 'settings_section_callback'],
            'bluesky-social-settings'
        );

        add_settings_field(
            'bluesky_handle',
            '<label for="bluesky_settings_handle">' . __( 'BlueSky Handle', 'bluesky-social' ) . '</label>',
            [$this, 'bluesky_handle_callback'],
            'bluesky-social-settings',
            'bluesky_main_settings'
        );

        add_settings_field(
            'bluesky_app_password',
            '<label for="bluesky_settings_password">' . __( 'BlueSky Password', 'bluesky-social' ) . '</label>',
            [$this, 'bluesky_app_password_callback'],
            'bluesky-social-settings',
            'bluesky_main_settings'
        );

        add_settings_field(
            'bluesky_auto_syndicate',
            '<label for="bluesky_settings_syndicate">' . __( 'Auto-Syndicate Posts', 'bluesky-social' ) . '</label>',
            [$this, 'bluesky_auto_syndicate_callback'],
            'bluesky-social-settings',
            'bluesky_main_settings'
        );

        add_settings_field(
            'bluesky_theme',
            '<label for="bluesky_settings_theme">' . __( 'Theme', 'bluesky-social' ) . '</label>',
            [$this, 'bluesky_theme_callback'],
            'bluesky-social-settings',
            'bluesky_main_settings'
        );

        add_settings_field(
            'bluesky_posts_limit',
            '<label for="bluesky_settings_posts_limit">' . __( 'Number of Posts to Display', 'bluesky-social' ) . '</label>',
            [$this, 'bluesky_posts_limit_callback'],
            'bluesky-social-settings',
            'bluesky_main_settings'
        );
    }

    public function settings_section_callback() {
        echo '<p>' . __( 'Enter your BlueSky account details to enable social integration.', 'bluesky-social' ) . '</p>';
        echo do_shortcode('[bluesky_profile]');
    }

    public function bluesky_handle_callback() {
        $options = $this -> options;
        $handle = isset($options['handle']) ? $options['handle'] : '';
        echo "<input type='text' id='bluesky_settings_handle' name='" . BLUESKY_PLUGIN_OPTIONS . "[handle]' value='" . esc_attr( $handle ) . "' />";
    }

    public function bluesky_app_password_callback() {
        $options = $this -> options;
        $password = isset($options['app_password']) ? $options['app_password'] : '';
        echo "<input type='password' id='bluesky_settings_password' name='" . BLUESKY_PLUGIN_OPTIONS . "[app_password]' value='" . esc_attr( $password ) . "' />";
    }

    public function bluesky_auto_syndicate_callback() {
        $auto_syndicate = isset($this->options['auto_syndicate']) ? $this->options['auto_syndicate'] : 0;
        echo '<input id="bluesky_settings_syndicate" type="checkbox" name="" . BLUESKY_PLUGIN_OPTIONS . "[auto_syndicate]" value="1" ' . checked(1, $auto_syndicate, false) . ' />';
    }

    public function bluesky_theme_callback() {
        $theme = isset($this->options['theme']) ? $this->options['theme'] : 'light';
        echo '<select name="" . BLUESKY_PLUGIN_OPTIONS . "[theme]" id="bluesky_settings_theme">';
        echo '<option value="light" ' . selected('light', $theme, false) . '>Light</option>';
        echo '<option value="dark" ' . selected('dark', $theme, false) . '>Dark</option>';
        echo '</select>';
    }

    public function bluesky_posts_limit_callback() {
        $limit = isset($this->options['posts_limit']) ? $this->options['posts_limit'] : 10;
        echo "<input type='number' min='1' max='10' id='bluesky_settings_posts_limit' name='" . BLUESKY_PLUGIN_OPTIONS . "[posts_limit]' value='" . esc_attr( $limit ) . "' />";
        echo "<p class='description'>" . __('Enter the number of posts to display (1-10) - 10 is set by default', 'bluesky-social') . "</p>";
    }

    public function render_settings_page() {
        ?>
        <div class="wrap bluesky-social-integration-admin">
            <h1>BlueSky Social Integration</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bluesky_settings_group');
                do_settings_sections('bluesky-social-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style('bluesky-social-style', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social.css');
        wp_enqueue_style('bluesky-social-style-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css');
        wp_enqueue_style('bluesky-social-style-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css');
        wp_enqueue_script('bluesky-social-script', BLUESKY_PLUGIN_FOLDER . 'assets/js/bluesky-social.js', ['jquery'], '1.0', true);
    }

    /**
     * Methods to get the work done on Bluesky
     */
    // Authentication method
    private function authenticate() {
        $options = $this -> options;
        
        if ( ! isset( $options['handle'] ) || ! isset( $options['app_password'] ) ) {
            return false;
        }

        $response = wp_remote_post( $this -> bluesky_api_url . 'com.atproto.server.createSession', [
            'body' => json_encode([
                'identifier' => $options['handle'],
                'password' => $options['app_password']
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true);
        
        if ( isset( $body['did'] ) && isset( $body['accessJwt'] ) ) {
            $this -> did = $body['did'];
            $this -> access_token = $body['accessJwt'];
            return true;
        }

        return false;
    }

    // Fetch BlueSky posts
    public function fetch_bluesky_posts( $render = false ) {
        $cache_key = BLUESKY_PLUGIN_TRANSIENT . 'posts';
        $cached_posts = get_transient( $cache_key );
        

        if ( $cached_posts !== false ) {
            if ( $render ) return $cached_posts;
            wp_send_json_success( $cached_posts );
        }

        if ( ! $this -> authenticate() ) {
            wp_send_json_error('Authentication failed');
        }

        // Use the posts_limit setting, default to 10 if not set
        $limit = isset( $this -> options['posts_limit'] ) ? intval( $this -> options['posts_limit'] ) : 10;
        $limit = max(1, min(10, $limit)); // Ensure limit is between 1 and 10

        $response = wp_remote_get($this->bluesky_api_url . 'app.bsky.feed.getAuthorFeed', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ],
            'body' => [
                'actor' => $this->did,
                'limit' => $limit
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error('Failed to fetch posts');
        }

        $raw_posts = json_decode( wp_remote_retrieve_body( $response ), true );

        /* echo '<pre>';
        var_dump( $raw_posts );
        echo '</pre>'; */


        // Normalize posts to ensure a consistent structure
        $processed_posts = array_map( function( $post ) {
            
            // Extract embedded images
            $images = [];
            if ( isset( $post['post']['embed']['images'] ) ) {
                foreach ( $post['post']['embed']['images'] as $image ) {
                    $images[] = $image['fullsize'] ?? $image['thumb'] ?? '';
                    // missing alt?
                }
            }

            // Extract GIFs or external media
            $external_media = null;
            if ( isset( $post['post']['embed']['external'] ) ) {
                $external_media = [
                    'uri' => $post['post']['embed']['external']['uri'],
                    'title' => $post['post']['embed']['external']['title'] ?? '',
                    'alt' => $post['post']['embed']['external']['alt'] ?? '',
                    'description' => $post['post']['embed']['external']['description'] ?? ''
                ];
            }

            // Check for video embed
            $embedded_media = null;
            if ( isset( $post['post']['embed']['video'] ) || 
                    (isset( $post['post']['embed']['$type'] ) && $post['post']['embed']['$type'] === 'app.bsky.embed.video') ) {
                $video_embed = $post['post']['embed'];
                $embedded_media = [
                    'type' => 'video',
                    'alt' => $video_embed['alt'] ?? '',
                    'aspect_ratio' => [
                        'width' => $video_embed['aspectRatio']['width'] ?? null,
                        'height' => $video_embed['aspectRatio']['height'] ?? null
                    ],
                    'video_details' => [
                        'mime_type' => $video_embed['video']['mimeType'] ?? '',
                        'size' => $video_embed['video']['size'] ?? 0,
                        'playlist_url' => $video_embed['embeds'][0]['playlist'] ?? '',
                        'thumbnail_url' => $video_embed['embeds'][0]['thumbnail'] ?? ''
                    ]
                ];
            }
            
            // Check for record (embedded post) embed
            elseif ( isset( $post['post']['embed']['record'] ) || 
                        (isset( $post['post']['embed']['$type'] ) && $post['post']['embed']['$type'] === 'app.bsky.embed.record') ) {
                $record_embed = $post['post']['embed']['record'];
                $end0fURI = explode( '/', $record_embed['uri'] );
                $embedded_media = [
                    'type' => 'record',
                    'author' => [
                        'did' => $record_embed['author']['did'] ?? '',
                        'handle' => $record_embed['author']['handle'] ?? '',
                        'display_name' => $record_embed['author']['displayName'] ?? ''
                    ],
                    'text' => $record_embed['value']['text'] ?? '',
                    'created_at' => $record_embed['value']['createdAt'] ?? '',
                    'like_count' => $record_embed['likeCount'] ?? 0,
                    'reply_count' => $record_embed['replyCount'] ?? 0,
                    'url' => 'https://bsky.app/profile/' . ( $record_embed['author']['handle'] ?? '' ) . '/post/' . ( $record_embed['uri'] ? end( $end0fURI ) : '' )
                ];

                // Check if the embedded record has its own media (like a video)
                if ( isset( $record_embed['value']['embed']['video'] ) ) {
                    $embedded_media['embedded_video'] = [
                        'alt' => $record_embed['value']['embed']['alt'] ?? '',
                        'aspect_ratio' => [
                            'width' => $record_embed['value']['embed']['aspectRatio']['width'] ?? null,
                            'height' => $record_embed['value']['embed']['aspectRatio']['height'] ?? null
                        ],
                        'video_details' => [
                            'mime_type' => $record_embed['value']['embed']['video']['mimeType'] ?? '',
                            'size' => $record_embed['value']['embed']['video']['size'] ?? 0
                        ]
                    ];
                }
            }

            $end0fPostURI = isset( $post['post']['uri'] ) ? explode( '/', $post['post']['uri'] ) : array();
            return [
                'text' => $post['post']['record']['text'] ?? 'No text',
                'created_at' => $post['post']['record']['createdAt'] ?? '',
                'account' => [
                    'did' => $post['post']['author']['did'] ?? '',
                    'handle' => $post['post']['author']['handle'] ?? '',
                    'display_name' => $post['post']['author']['displayName'] ?? '',
                    'avatar' => $post['post']['author']['avatar'] ?? '',
                ],
                'images' => $images,
                'external_media' => $external_media,
                'embedded_media' => $embedded_media,
                'url' => 'https://bsky.app/profile/' . ( $post['post']['author']['handle'] ?? '') . '/post/' . ( isset( $post['post']['uri'] ) ? end( $end0fPostURI ) : '' )
            ];

        }, $raw_posts['feed'] ?? []);

        // Sort by most recent first
        usort( $processed_posts, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        } );

        // Cache the posts for 1 hour
        set_transient( $cache_key, $processed_posts, HOUR_IN_SECONDS );

        if ( $render ) return $processed_posts;
        
        wp_send_json_success( $processed_posts );
        wp_die(); // Always end AJAX handlers with wp_die()
    }

    public function render_bluesky_posts_list() {
        $posts = $this -> fetch_bluesky_posts( true );

        if ( isset ( $posts ) && is_array( $posts ) ) {
            // Start output buffering
            ob_start();
            echo '<pre>';
            var_dump( $posts );
            echo '</pre>';
            ?>
            <div class="bluesky-social-integration-last-post">
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
                                if ( ! empty($post['embedded_media'] ) ):
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
    public function bluesky_last_posts_shortcode() {
        return $this -> render_bluesky_posts_list();
    }

    // Get BlueSky profile
    public function get_bluesky_profile() {
        if ( ! $this -> authenticate() ) {
            return false;
        }

        $response = wp_remote_get( $this -> bluesky_api_url . 'app.bsky.actor.getProfile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this -> access_token
            ],
            'body' => [
                'actor' => $this -> did
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true);
    }

    public function get_bluesky_profile_for_ajax() {
        $profile = $this->get_bluesky_profile(); // Use existing method
        
        if ( $profile ) {
            wp_send_json_success( $profile );
        } else {
            wp_send_json_error('Could not fetch profile');
        }
        wp_die(); // Always call wp_die() at the end of AJAX handlers
    }

    // Shortcode for BlueSky profile card
    public function bluesky_profile_card_shortcode() {
        $profile = $this->get_bluesky_profile();
        
        // TODO: write a fallback solution using cache
        if ( ! $profile ) {
            return __('Unable to fetch BlueSky profile', 'bluesky-social');
        }

        ob_start();
        ?>
        <aside class="bluesky-social-integration-profile-card" aria-label="<?php echo esc_attr( sprintf( __('BlueSky Social Card of %s', 'bluesky-social'), $profile['displayName'] ) ); ?>">
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

    // Syndicate WordPress post to BlueSky
    public function syndicate_post_to_bluesky( $post_id ) {
        if ( ! isset( $this -> options['auto_syndicate']) || ! $this -> options['auto_syndicate'] ) {
            return false;
        }

        if ( ! $this -> authenticate() ) {
            return false;
        }

        $post = get_post( $post_id );
        $permalink = get_permalink( $post_id );

        $post_data = [
            '$type' => 'app.bsky.feed.post',
            'text' => wp_trim_words( $post -> post_title, 50 ) . "\n\nRead more: " . $permalink,
            'createdAt' => date('c')
        ];

        $response = wp_remote_post( $this -> bluesky_api_url . 'com.atproto.repo.createRecord', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this -> access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'repo' => $this -> did,
                'collection' => 'app.bsky.feed.post',
                'record' => $post_data
            ])
        ]);

        return ! is_wp_error( $response );
    }

    public function register_widgets() {
        register_widget('BlueSky_Posts_Widget');
        register_widget('BlueSky_Profile_Widget');
    }

    public function register_gutenberg_blocks() {
        wp_register_script(
            'bluesky-posts-block',
            BLUESKY_PLUGIN_FOLDER . 'blocks/bluesky-posts-feed.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'jquery']
        );

        wp_register_script(
            'bluesky-profile-block',
            BLUESKY_PLUGIN_FOLDER . 'blocks/bluesky-profile-card.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'jquery']
        );

        register_block_type('bluesky-social/posts', [
            'editor_script' => 'bluesky-posts-block',
            'render_callback' => [$this, 'render_bluesky_posts_block']
        ]);

        register_block_type('bluesky-social/profile', [
            'editor_script' => 'bluesky-profile-block'
        ]);
    }
}