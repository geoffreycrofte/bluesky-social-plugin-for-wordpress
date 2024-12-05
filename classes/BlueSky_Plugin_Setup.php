<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_Plugin_Setup {
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

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Internationalization
        add_action('init', [$this, 'load_plugin_textdomain']);

        // Admin menu and settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Script and style enqueuing
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_enqueue_scripts']);

        // AJAX actions
        add_action('wp_ajax_fetch_bluesky_posts', [$this, 'ajax_fetch_bluesky_posts']);
        add_action('wp_ajax_nopriv_fetch_bluesky_posts', [$this, 'ajax_fetch_bluesky_posts']);

        add_action('wp_ajax_get_bluesky_profile', [$this, 'ajax_get_bluesky_profile']);
        add_action('wp_ajax_nopriv_get_bluesky_profile', [$this, 'ajax_get_bluesky_profile']);

        // Widgets and Gutenberg blocks
        add_action('widgets_init', [$this, 'register_widgets']);
        add_action('init', [$this, 'register_gutenberg_blocks']);

        // Post syndication
        if ( ! empty( $this -> options['auto_syndicate'] ) ) {
            add_action( 'publish_post', [$this, 'syndicate_post_to_bluesky'], 10, 1 );
        }
    }

    /**
     * Load plugin text domain for internationalization
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 'bluesky-social', false, BLUESKY_PLUGIN_DIRECTORY_NAME . '/languages' );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('BlueSky Social Integration', 'bluesky-social'),
            __('BlueSky Settings', 'bluesky-social'),
            'manage_options',
            'bluesky-social-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 
            'bluesky_settings_group', 
            BLUESKY_PLUGIN_OPTIONS,
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );

        add_settings_section(
            'bluesky_main_settings',
            __('BlueSky Account Settings', 'bluesky-social'),
            [$this, 'settings_section_callback'],
            'bluesky-social-settings'
        );

        $this->add_settings_fields();
    }

    /**
     * Sanitize settings before saving
     * @param array $input The settings array to sanitize
     * @return array Sanitized settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];
        
        // Handle encryption for secret key
        $secret_key = get_option( BLUESKY_PLUGIN_OPTIONS . '_secret' );
        if ( empty( $secret_key ) || $secret_key === false ) {
            add_option( BLUESKY_PLUGIN_OPTIONS . '_secret', bin2hex( random_bytes( 32 ) ) );
        }

        // Handle encryption for password
        if ( isset( $input['app_password'] ) && ! empty( $input['app_password'] ) ) {
            $helpers = new BlueSky_Helpers();
            $sanitized['app_password'] = $helpers -> bluesky_encrypt( $input['app_password'] );
        } else {
            $sanitized['app_password'] = $this -> options['app_password'] ?? '';
        }   

        // Sanitize other fields
        $sanitized['handle'] = isset( $input['handle'] ) ? sanitize_text_field( $input['handle'] ) : '';
        $sanitized['auto_syndicate'] = isset( $input['auto_syndicate'] ) ? 1 : 0;
        $sanitized['theme'] = isset( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : 'system';
        $sanitized['posts_limit'] = isset( $input['posts_limit'] ) ? min( 10, max( 1, intval( $input['posts_limit'] ) ) ) : 5;

        $minutes = isset( $input['cache_duration']['minutes'] ) ? absint( $input['cache_duration']['minutes'] ) : 0;
        $hours = isset( $input['cache_duration']['hours'] ) ? absint( $input['cache_duration']['hours'] ) : 0;
        $days = isset( $input['cache_duration']['days'] ) ? absint( $input['cache_duration']['days'] ) : 0;
        
        $sanitized['cache_duration'] = [
            'minutes' => $minutes,
            'hours' => $hours,
            'days' => $days,
            'total_seconds' => ( $minutes * 60 ) + ( $hours * 3600 ) + ( $days * 86400 )
        ];
        
        return $sanitized;
    }

    /**
     * Add individual settings fields
     */
    private function add_settings_fields() {
        $fields = [
            'bluesky_handle' => [
                'label' => __('BlueSky Handle', 'bluesky-social'),
                'callback' => 'render_handle_field'
            ],
            'bluesky_app_password' => [
                'label' => __('BlueSky Password', 'bluesky-social'),
                'callback' => 'render_password_field'
            ],
            'bluesky_auto_syndicate' => [
                'label' => __('Auto-Syndicate Posts', 'bluesky-social'),
                'callback' => 'render_syndicate_field'
            ],
            'bluesky_theme' => [
                'label' => __('Theme', 'bluesky-social'),
                'callback' => 'render_theme_field'
            ],
            'bluesky_posts_limit' => [
                'label' => __('Number of Posts to Display', 'bluesky-social'),
                'callback' => 'render_posts_limit_field'
            ],
            'bluesky_cache_duration' => [
                'label' => __('Cache Duration', 'bluesky-social'),
                'callback' => 'render_cache_duration_field'
            ]
        ];

        foreach ( $fields as $id => $field ) {
            add_settings_field(
                $id,
                '<label for="' . BLUESKY_PLUGIN_OPTIONS . '_' . str_replace('bluesky_', '', $id) . '">' . $field['label'] . '</label>',
                [$this, $field['callback']],
                'bluesky-social-settings',
                'bluesky_main_settings'
            );
        }
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Enter your BlueSky account details to enable social integration.', 'bluesky-social') . '</p>';
    }

    /**
     * Render handle field
     */
    public function render_handle_field() {
        $handle = $this -> options['handle'] ?? '';
        echo "<input type='text' id='" . BLUESKY_PLUGIN_OPTIONS . "_handle' name='bluesky_settings[handle]' value='" . esc_attr( $handle ) . "' />";
    }

    /**
     * Render password field
     */
    public function render_password_field() {
        $password = $this -> options['app_password'] ?? '';
        // Don't show the actual password, just a placeholder if it exists
        $placeholder = ! empty( $password ) ? '••••••••' : '';
        
        echo "<input type='password' id='" . BLUESKY_PLUGIN_OPTIONS . "_app_password' name='bluesky_settings[app_password]' value='' placeholder='" . esc_attr( $placeholder ) . "' />";
        
        if ( ! empty( $password ) ) {
            echo "<p class='description'>" . __('Leave empty to keep the current password.', 'bluesky-social') . "</p>";
        }
    }

    /**
     * Render auto-syndicate field
     */
    public function render_syndicate_field() {
        $auto_syndicate = $this->options['auto_syndicate'] ?? 0;
        echo '<input id="' . BLUESKY_PLUGIN_OPTIONS . '_auto_syndicate" type="checkbox" name="bluesky_settings[auto_syndicate]" value="1" ' . checked(1, $auto_syndicate, false) . ' />';
    }

    /**
     * Render theme field
     */
    public function render_theme_field() {
        $theme = $this->options['theme'] ?? 'light';
        echo '<select name="bluesky_settings[theme]" id="' . BLUESKY_PLUGIN_OPTIONS . '_theme">';
        echo '<option value="system" ' . selected('system', $theme, false) . '>' . __('System Preference', 'bluesky-social') . '</option>';
        echo '<option value="light" ' . selected('light', $theme, false) . '>' . __('Light', 'bluesky-social') . '</option>';
        echo '<option value="dark" ' . selected('dark', $theme, false) . '>' . __('Dark', 'bluesky-social') . '</option>';
        echo '</select>';
    }

    /**
     * Render posts limit field
     */
    public function render_posts_limit_field() {
        $limit = $this->options['posts_limit'] ?? 5;
        echo "<input type='number' min='1' max='10' id='" . BLUESKY_PLUGIN_OPTIONS . "_posts_limit' name='bluesky_settings[posts_limit]' value='" . esc_attr( $limit ) . "' />";
        echo "<p class='description'>" . __('Enter the number of posts to display (1-10) - 5 is set by default', 'bluesky-social') . "</p>";
    }

    /**
     * Render cache duration field
     */
    public function render_cache_duration_field() {
        $cache_duration = $this->options['cache_duration'] ?? [
            'minutes' => 0,
            'hours' => 1,
            'days' => 0
        ];

        ?>
        <div class="cache-duration-fields">
            <label>
                <input type="number" 
                        min="0" 
                        name="bluesky_settings[cache_duration][days]" 
                        value="<?php echo esc_attr( $cache_duration['days'] ); ?>" 
                        style="width: 60px;"> 
                <?php _e('Days', 'bluesky-social'); ?>
            </label>
            <label>
                <input type="number" 
                        min="0" 
                        name="bluesky_settings[cache_duration][hours]" 
                        value="<?php echo esc_attr($cache_duration['hours']); ?>" 
                        style="width: 60px;"> 
                <?php _e('Hours', 'bluesky-social'); ?>
            </label>
            <label>
                <input type="number" 
                        min="0" 
                        name="bluesky_settings[cache_duration][minutes]" 
                        value="<?php echo esc_attr( $cache_duration['minutes'] ); ?>" 
                        style="width: 60px;"> 
                <?php _e('Minutes', 'bluesky-social'); ?>
            </label>
        </div>
        <p class="description">
            <?php _e('Set to 0 in all fields to disable caching. Current cache status:', 'bluesky-social'); ?>
        </p>
        <?php
        $this->display_cache_status();
    }

    /**
     * Display cache status
     */
    private function display_cache_status() {
        $helpers = new BlueSky_Helpers();
        $profile_transient = get_transient( $helpers -> get_profile_transient_key() );
        $posts_transient = get_transient( $helpers -> get_posts_transient_key() );

        echo '<div class="cache-status">';
        echo '<style>.cache-duration-fields label {
                    margin-right: 15px;
                }

                .cache-status {
                    margin-top: 10px;
                    padding: 10px;
                    background: #f8f8f8;
                    border-left: 4px solid #646970;
                }

                .cache-status p {
                    margin: 5px 0;
                }
            </style>';
        
        // Profile cache status
        echo '<p><strong>' . __('Profile Card Cache:', 'bluesky-social') . '</strong> ';
        if ( $profile_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_profile_transient_key() );
            echo sprintf(
                __('Active (expires in %s)', 'bluesky-social'),
                '<code>' . $this -> format_time_remaining( $time_remaining ) . '</code>'
            );
        } else {
            echo __('Not cached', 'bluesky-social');
        }
        echo '</p>';

        // Posts cache status
        echo '<p><strong>' . __('Posts Feed Cache:', 'bluesky-social') . '</strong> ';
        if ( $posts_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_posts_transient_key() );
            echo sprintf(
                __('Active (expires in %s)', 'bluesky-social'),
                '<code>' . $this -> format_time_remaining( $time_remaining ) . '</code>'
            );
        } else {
            echo __('Not cached', 'bluesky-social');
        }
        echo '</p>';

        echo '</div>';
    }

    /**
     * Get transient expiration time in seconds
     */
    private function get_transient_expiration_time( $transient_name ) {
        $timeout_key = '_transient_timeout_' . $transient_name;

        $timeout = get_option( $timeout_key );
        
        if ( $timeout ) {
            return $timeout - time();
        }
        
        return 0;
    }

    /**
     * Format time remaining in human-readable format
     */
    private function format_time_remaining( $seconds ) {
        if ( $seconds <= 0 ) {
            return __('expired', 'bluesky-social');
        }

        $days = floor( $seconds / 86400 );
        $hours = floor( ( $seconds % 86400 ) / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $remaining_seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = sprintf(_n('%d day', '%d days', $days, 'bluesky-social'), $days);
        }
        if ( $hours > 0 ) {
            $parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'bluesky-social' ), $hours );
        }
        if ( $minutes > 0 ) {
            $parts[] = sprintf(_n('%d minute', '%d minutes', $minutes, 'bluesky-social'), $minutes);
        }
        if ( empty( $parts ) || $remaining_seconds > 0 ) {
            $parts[] = sprintf( _n( '%d second', '%d seconds', $remaining_seconds, 'bluesky-social' ), $remaining_seconds );
        }

        return implode(', ', $parts);
    }

    /**
     * Render settings page
     */
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

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style('bluesky-social-style', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social.css');
        wp_enqueue_style('bluesky-social-style-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css');
        wp_enqueue_style('bluesky-social-style-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css');
        wp_enqueue_script('bluesky-social-script', BLUESKY_PLUGIN_FOLDER . 'assets/js/bluesky-social.js', ['jquery'], '1.0', true);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_style('bluesky-social-style-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css');
            wp_enqueue_style('bluesky-social-style-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css');
        }
    }

    /**
     * AJAX handler for fetching BlueSky posts
     */
    public function ajax_fetch_bluesky_posts() {
        $limit = $this -> options['posts_limit'] ?? 5;
        $posts = $this -> api_handler -> fetch_bluesky_posts( $limit );
        
        if ($posts !== false) {
            wp_send_json_success( $posts );
        } else {
            wp_send_json_error('Could not fetch posts');
        }
        wp_die();
    }

    /**
     * AJAX handler for fetching BlueSky profile
     */
    public function ajax_get_bluesky_profile() {
        $profile = $this -> api_handler -> get_bluesky_profile();
        
        if ($profile) {
            wp_send_json_success( $profile );
        } else {
            wp_send_json_error( 'Could not fetch profile' );
        }
        wp_die();
    }

    /**
     * Syndicate post to BlueSky
     * @param int $post_id WordPress post ID
     */
    public function syndicate_post_to_bluesky( $post_id ) {
        $post = get_post( $post_id );
        $permalink = get_permalink( $post_id );

        // Check if the post is already syndicated
        // because the action can be triggered multiple times by WordPress
        $is_syndicated = get_post_meta( $post_id, '_bluesky_syndicated', true );
        if ( $is_syndicated ) {
            return;
        }

        $this -> api_handler -> syndicate_post_to_bluesky( $post -> post_title, $permalink );
        add_post_meta( $post_id, '_bluesky_syndicated', true, true );
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget('BlueSky_Posts_Widget');
        register_widget('BlueSky_Profile_Widget');
    }

    /**
     * Register Gutenberg blocks
     */
    public function register_gutenberg_blocks() {
        // Register Posts Feed
        wp_register_script(
            'bluesky-posts-block',
            BLUESKY_PLUGIN_FOLDER . 'blocks/bluesky-posts-feed.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render']
        );

        register_block_type('bluesky-social/posts', [
            'api_version' => 2,
            'editor_script' => 'bluesky-posts-block',
            'render_callback' => [$this, 'bluesky_posts_block_render'],
            'attributes' => [
                'displayEmbeds' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'theme' => [
                    'type' => 'string',
                    'default' => 'system'
                ],
                'numberOfPosts' => [
                    'type' => 'integer',
                    'default' => get_option(BLUESKY_PLUGIN_OPTIONS)['posts_limit'] ?? 5
                ]
            ]
        ]);

        // Register Profile Card
        wp_register_script(
            'bluesky-profile-block',
            BLUESKY_PLUGIN_FOLDER . 'blocks/bluesky-profile-card.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render']
        );

        register_block_type('bluesky-social/profile', [
            'api_version' => 2,
            'attributes' => [
                'displayBanner' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displayAvatar' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displayCounters' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displayBio' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'theme' => [
                    'type' => 'string',
                    'default' => 'system'
                ],
                'className' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'style' => [
                    'type' => 'string',
                    'default' => 'default'
                ]
            ],
            'styles' => [
                [
                    'name' => 'default',
                    'label' => __('Rounded', 'bluesky-social'),
                    'isDefault' => true
                ],
                [
                    'name' => 'outline',
                    'label' => __('Outline', 'bluesky-social')
                ],
                [
                    'name' => 'squared',
                    'label' => __('Squared', 'bluesky-social')
                ]
            ],
            'editor_script' => 'bluesky-profile-block',
            'render_callback' => [$this, 'bluesky_profile_block_render'],
        ]);

        // Register REST API routes
        if ( function_exists( 'register_rest_field' ) ) {
            register_rest_route( 'bluesky-social/v1', '/profile-data', [
                'methods' => 'GET',
                'callback' => [ $this, 'get_profile_data' ],
                'permission_callback' => '__return_true'
            ]);
        }
    }

    /**
     * Renders the BlueSky profile card block
     * 
     * @param array $attributes Block attributes including:
     *                         - displayBanner (bool) Whether to show the profile banner
     *                         - displayAvatar (bool) Whether to show the profile avatar
     *                         - displayCounters (bool) Whether to show follower/following counts
     *                         - displayBio (bool) Whether to show the profile bio
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     * @return string HTML markup for the profile card
     */
    public function bluesky_profile_block_render( $attributes = [] ) {
        // Get the style class
        $style_class = !empty($attributes['className']) ? $attributes['className'] : 'is-style-default';
        
        // Add the style class to the attributes for the render function
        $attributes['styleClass'] = $style_class;

        $render_front = new BlueSky_Render_Front( $this -> api_handler );
        return $render_front -> render_bluesky_profile_card( $attributes );
    }
    
    /**
     * Renders the BlueSky posts feed block
     * 
     * @param array $attributes Block attributes including:
     *                         - displayEmbeds (bool) Whether to show embedded media in posts
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     *                         - numberOfPosts (int) Number of posts to display (1-10)
     * @return string HTML markup for the posts feed
     */

    public function bluesky_posts_block_render( $attributes = [] ) {
        $render_front = new BlueSky_Render_Front( $this -> api_handler );
        return $render_front -> render_bluesky_posts_list( $attributes );
    }
}