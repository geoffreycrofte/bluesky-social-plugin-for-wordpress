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
     * Helpers instance
     * @var BlueSky_Helpers
     */
    private $helpers;

    /**
     * Constructor
     * @param BlueSky_API_Handler $api_handler API handler instance
     */
    public function __construct(BlueSky_API_Handler $api_handler) {
        $this->api_handler = $api_handler;
        $this->helpers = new BlueSky_Helpers();
        $this->options = get_option( BLUESKY_PLUGIN_OPTIONS );

        // On activation
        register_activation_hook(BLUESKY_PLUGIN_FILE, [$this, 'on_plugin_activation']);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Plugin activation hook
     */
    public function on_plugin_activation() {
        // Adds activation date if it doesn't exist.
        // Used later to block syndication for older posts.
        if ( ! get_option( BLUESKY_PLUGIN_OPTIONS . '_activation_date' ) ) {
            add_option( BLUESKY_PLUGIN_OPTIONS . '_activation_date', time() );
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Internationalization
        add_action('init', [$this, 'load_plugin_textdomain']);
        add_filter('plugin_action_links_' . BLUESKY_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

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
        add_action( 'transition_post_status', [$this, 'syndicate_post_to_bluesky'], 10, 3 );

        // Messaging & Noticing
        add_action('admin_notices', [$this, 'display_bluesky_logout_message']);
    }

    /**
     * Load plugin text domain for internationalization
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 'social-integration-for-bluesky', false, BLUESKY_PLUGIN_DIRECTORY_NAME . '/languages' );
    }

    /**
     * Add a link to the setting page
     */
    function add_plugin_action_links( array $links ) {
        $url = $this -> helpers -> get_the_admin_plugin_url();
        $settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__('Settings', 'social-integration-for-bluesky') . '</a>';
        $links[] = $settings_link;
        return $links;
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            esc_html__('Social Integration for BlueSky', 'social-integration-for-bluesky'),
            esc_html__('BlueSky Settings', 'social-integration-for-bluesky'),
            'manage_options',
            BLUESKY_PLUGIN_SETTING_PAGENAME ,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        $submit_button = '<p class="submit"><button type="submit" class="button button-large button-primary">' . __( 'Save Changes' ) . '</button></p>';

        register_setting( 
            'bluesky_settings_group', 
            BLUESKY_PLUGIN_OPTIONS,
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );

        add_settings_section(
            'bluesky_main_settings',
            esc_html__('BlueSky Account Settings', 'social-integration-for-bluesky'),
            [$this, 'settings_section_callback'],
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            array(
                'before_section' => '<div id="account" aria-hidden="false" class="bluesky-social-integration-admin-content">',
                //I tried get_submit_button() but it couldn't work for some reasons.
                'after_section'  => $submit_button . "\n\n" . '</div>',
                'section_class'  => 'bluesky-main-settings',
            ) 
        );

        add_settings_section(
            'bluesky_customization_settings',
            esc_html__('BlueSky Customization Settings', 'social-integration-for-bluesky'),
            [$this, 'customization_section_callback'],
            BLUESKY_PLUGIN_SETTING_PAGENAME,
            array(
                'before_section' => '<div id="customization" aria-hidden="false" class="bluesky-social-integration-admin-content">',
                'after_section'  => $submit_button . "\n\n" . '</div>',
                'section_class'  => 'bluesky-customization-settings',
            )
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
        $helpers = $this -> helpers;
        $current_options = $this -> options;
        
        // Handle encryption for secret key
        $secret_key = get_option( BLUESKY_PLUGIN_OPTIONS . '_secret' );
        if ( empty( $secret_key ) || $secret_key === false ) {
            add_option( BLUESKY_PLUGIN_OPTIONS . '_secret', bin2hex( random_bytes( 32 ) ) );
        }

        // Handle encryption for password
        if ( isset( $input['app_password'] ) && ! empty( $input['app_password'] ) ) {
            $sanitized['app_password'] = $helpers -> bluesky_encrypt( $input['app_password'] );
        } else {
            $sanitized['app_password'] = $this -> options['app_password'] ?? '';
        }

        // Sanitize de customization values (support int value only at the moment)
        $sanitized['customisation'] = $helpers -> sanitize_int_recursive($input['customisation']);

        // Sanitize other fields
        $sanitized['handle'] = isset( $input['handle'] ) ? sanitize_text_field( $input['handle'] ) : '';
        $sanitized['auto_syndicate'] = isset( $input['auto_syndicate'] ) ? 1 : 0;
        $sanitized['no_replies'] = isset( $input['no_replies'] ) ? 1 : 0;
        $sanitized['no_embeds'] = isset( $input['no_embeds'] ) ? 1 : 0;
        $sanitized['no_reposts'] = isset( $input['no_reposts'] ) ? 1 : 0;
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

        // Sanitize Layouts
        $sanitized['styles']['feed_layout'] = isset( $input['styles']['feed_layout'] ) && in_array( $input['styles']['feed_layout'], array('default', 'layout_2') ) ? esc_attr( $input['styles']['feed_layout'] ) : 'default';

        // If we go from any layout to 'layout_2' then make some custom setup.
        if ( isset( $current_options['styles']['feed_layout'] ) && $current_options['styles']['feed_layout'] !== 'layout_2' && $sanitized['styles']['feed_layout'] === 'layout_2' ) {
            $sanitized['no_replies'] = 1;
            $sanitized['no_embeds'] = 1;
            $sanitized['no_reposts'] = 1;
        }

        // Check if activation date exists (plugin activation before v1.3.0 wouldn't have it)
        if ( ! get_option( BLUESKY_PLUGIN_OPTIONS . '_activation_date' ) ) {
            add_option( BLUESKY_PLUGIN_OPTIONS . '_activation_date', time() );
        }

        return $sanitized;
    }

    /**
     * Add individual settings fields
     */
    private function add_settings_fields() {
        $fields = [
            'bluesky_handle' => [
                'label' => __('BlueSky Handle', 'social-integration-for-bluesky'),
                'callback' => 'render_handle_field',
                'section' => 'bluesky_main_settings'
            ],
            'bluesky_app_password' => [
                'label' => __('BlueSky Password', 'social-integration-for-bluesky'),
                'callback' => 'render_password_field',
                'section' => 'bluesky_main_settings'
            ],
            'bluesky_auto_syndicate' => [
                'label' => __('Auto-Syndicate Posts', 'social-integration-for-bluesky'),
                'callback' => 'render_syndicate_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_theme' => [
                'label' => __('Theme', 'social-integration-for-bluesky'),
                'callback' => 'render_theme_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_posts_limit' => [
                'label' => __('Number of Posts to Display', 'social-integration-for-bluesky'),
                'callback' => 'render_posts_limit_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_no_replies' => [
                'label' => __('Do not display replies', 'social-integration-for-bluesky'),
                'callback' => 'render_no_replies_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_no_reposts' => [
                'label' => __('Do not display reposts', 'social-integration-for-bluesky'),
                'callback' => 'render_no_reposts_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_no_embeds' => [
                'label' => __('Do not display embeds', 'social-integration-for-bluesky'),
                'callback' => 'render_no_embeds_field',
                'section' => 'bluesky_customization_settings'
            ],
            'bluesky_cache_duration' => [
                'label' => __('Cache Duration', 'social-integration-for-bluesky'),
                'callback' => 'render_cache_duration_field',
                'section' => 'bluesky_customization_settings'
            ]
        ];

        foreach ( $fields as $id => $field ) {
            add_settings_field(
                $id,
                '<label for="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_' . str_replace('bluesky_', '', $id) ) . '">' . esc_html( $field['label'] ) . '</label>',
                [ $this, $field['callback'] ],
                BLUESKY_PLUGIN_SETTING_PAGENAME ,
                $field['section']
            );
        }
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html( __('Enter your BlueSky account details to enable social integration.', 'social-integration-for-bluesky') ) . '</p>';
    }

    /**
     * Customization section callback
     */
    public function customization_section_callback() {
        echo '<p>' . esc_html( __('Start customizing how your feed and profile card are displayed.', 'social-integration-for-bluesky') ) . '</p>';
    }

    /**
     * Render handle field
     */
    public function render_handle_field() {
        $handle = $this -> options['handle'] ?? '';
        echo '<input type="text" id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_handle' ) . '" name="bluesky_settings[handle]" value="' . esc_attr( $handle ) . '" aria-describedby="bluesky-handle-description" />';
        echo '<p class="description" id="bluesky-handle-description">' . esc_html( __('This is your e-mail address used on BlueSky.', 'social-integration-for-bluesky') ) . '</p>';
    }

    /**
     * Render password field
     * If a password is already set, show a placeholder instead of the actual password
     */
    public function render_password_field() {

        $password = $this -> options['app_password'] ?? '';
        $login = $this -> options['handle'] ?? '';

        // Don't show the actual password, just a placeholder if it exists
        $placeholder = ! empty( $password ) && ! empty( $login ) ? '••••••••' : '';
        
        echo '<input type="password" id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_app_password' ) . '" name="bluesky_settings[app_password]" value="" placeholder="' . esc_attr( $placeholder ) . '" aria-describedby="bluesky-password-description" />';
        
        if ( ! empty( $password ) && ! empty( $login ) ) {
            echo '<p class="description" id="bluesky-password-description">' . esc_html( __('Leave empty to keep the current password.', 'social-integration-for-bluesky') ) . '</p>';
        } else {
            echo '<p class="description" id="bluesky-password-description">' . wp_kses_post(
                sprintf(
                    // translators: %1$s opening link tag, %2$s the closing link tag, %3$s new line insertion
                    __('Instead of using your password, you can use an %1$sApp Password%2$s available on BlueSky.%3$sNo need to authorize access to your direct messages, this plugin does not need it.', 'social-integration-for-bluesky')
                , '<a href="https://bsky.app/settings/app-passwords" target="_blank">', '</a>', '<br>' ) ) . '</p>';
        }

        // Adds a connection check using BlueSky API
        if ( ! empty( $password ) && ! empty( $login ) ) {
            $api = new BlueSky_API_Handler( $this -> options );
            $auth = $api -> authenticate();

            if ( $auth ) { ?>

                <div aria-live="polite" aria-atomic="true" id="bluesky-connection-test" class="description bluesky-connection-check notice-success">
                    <p>
                        <?php echo  esc_html__('Connection to BlueSky successful!', 'social-integration-for-bluesky'); ?>
                        <br>
                        <a class="bluesky-logout-link" href="<?php echo esc_url( admin_url( 'admin-post.php?action=bluesky_logout&nonce=' . wp_create_nonce('bluesky_logout_nonce') ) ); ?>">
                            <?php esc_html_e( 'Log out from this account', 'social-integration-for-bluesky' ); ?>
                        </a>

                    </p>
                </div>

            <?php } else { ?>

                <div aria-live="polite" aria-atomic="true" id="bluesky-connection-test" class="description bluesky-connection-check notice-error">
                    <p>
                        <?php echo esc_html__('Connection to BlueSky failed. Please check your credentials. It can also happend if you reached BlueSky request limit.', 'social-integration-for-bluesky'); ?>
                    </p>
                </div>

            <?php
            }
        }
    }

    /**
     * Render auto-syndicate field
     */
    public function render_syndicate_field() {
        $auto_syndicate = $this->options['auto_syndicate'] ?? 0;

        echo '<input id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_auto_syndicate' ) . '" type="checkbox" name="bluesky_settings[auto_syndicate]" value="1" ' . checked(1, $auto_syndicate, false) . ' aria-describedby="bluesky-auto-syndicate-desc" />';

        echo '<span class="description bluesky-description" id="bluesky-auto-syndicate-desc">' . esc_html( __('Automatically syndicate new posts to BlueSky. You can change this behaviour post by post while editing it.', 'social-integration-for-bluesky') ) . '</span>';
    }

    /**
     * Render theme field
     */
    public function render_theme_field() {
        $theme = $this->options['theme'] ?? 'system';
        echo '<select name="bluesky_settings[theme]" id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_theme' ) . '">';
        echo '<option value="system" ' . selected( 'system', $theme, false ) . '>' . esc_html( __('System Preference', 'social-integration-for-bluesky') ) . '</option>';
        echo '<option value="light" ' . selected( 'light', $theme, false ) . '>' . esc_html( __('Light', 'social-integration-for-bluesky') ) . '</option>';
        echo '<option value="dark" ' . selected( 'dark', $theme, false ) . '>' . esc_html( __('Dark', 'social-integration-for-bluesky') ) . '</option>';
        echo '</select>';
    }

    /**
     * Render posts limit field
     */
    public function render_posts_limit_field() {
        $limit = $this->options['posts_limit'] ?? 5;
        echo '<input type="number" min="1" max="10" id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_posts_limit' ) . '" name="bluesky_settings[posts_limit]" value="' . esc_attr( $limit ) . '" />';
        echo '<p class="description">' . esc_html( __('Enter the number of posts to display (1-10) - 5 is set by default', 'social-integration-for-bluesky') ) . '</p>';
    }

    /**
     * Render no replies field
     */
    public function render_no_replies_field() {
        $no_replies = $this->options['no_replies'] ?? 1;

        echo '<input id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_no_replies' ) . '" type="checkbox" name="bluesky_settings[no_replies]" value="1" ' . checked(1, $no_replies, false) . ' aria-describedby="bluesky-no_replies-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_replies-desc">' . esc_html( __('If checked, your replies will not be displayed in your feed.', 'social-integration-for-bluesky') ) . '</span>';
    }

    /**
     * Render no repost field
     */
    public function render_no_reposts_field() {
        $no_reposts = $this->options['no_reposts'] ?? 0;

        echo '<input id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_no_reposts' ) . '" type="checkbox" name="bluesky_settings[no_reposts]" value="1" ' . checked(1, $no_reposts, false) . ' aria-describedby="bluesky-no_reposts-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_reposts-desc">' . esc_html( __('If checked, the reposts won’t be displayed in your feed.', 'social-integration-for-bluesky') ) . '</span>';
    }

    /**
     * Render no embeds field
     */
    public function render_no_embeds_field() {
        $no_embeds = $this->options['no_embeds'] ?? 0;

        echo '<input id="' . esc_attr( BLUESKY_PLUGIN_OPTIONS . '_no_embeds' ) . '" type="checkbox" name="bluesky_settings[no_embeds]" value="1" ' . checked(1, $no_embeds, false) . ' aria-describedby="bluesky-no_embeds-desc" />';
        echo '<span class="description bluesky-description" id="bluesky-no_embeds-desc">' . esc_html( __('If checked, videos, images, and link cards won’t be displayed in your feed.', 'social-integration-for-bluesky') ) . '</span>';
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
                        id="bluesky_settings_cache_duration"
                        name="bluesky_settings[cache_duration][days]" 
                        value="<?php echo esc_attr( $cache_duration['days'] ); ?>" 
                        style="width: 60px;"> 
                <?php echo esc_html( __('Days', 'social-integration-for-bluesky') ); ?>
            </label>
            <label>
                <input type="number" 
                        min="0" 
                        name="bluesky_settings[cache_duration][hours]" 
                        value="<?php echo esc_attr($cache_duration['hours']); ?>" 
                        style="width: 60px;"> 
                <?php echo esc_html( __('Hours', 'social-integration-for-bluesky') ); ?>
            </label>
            <label>
                <input type="number" 
                        min="0" 
                        name="bluesky_settings[cache_duration][minutes]" 
                        value="<?php echo esc_attr( $cache_duration['minutes'] ); ?>" 
                        style="width: 60px;"> 
                <?php echo esc_html( __('Minutes', 'social-integration-for-bluesky') ); ?>
            </label>
        </div>
        <p class="description">
            <?php echo esc_html( __('Set to 0 in all fields to disable the cache.', 'social-integration-for-bluesky') ); ?>
        </p>
        <?php
    }

    /**
     * Display cache status
     */
    private function display_cache_status() {
        $helpers = $this -> helpers;
        $profile_transient = get_transient( $helpers -> get_profile_transient_key() );
        $posts_transient = get_transient( $helpers -> get_posts_transient_key() );
        $access_token_transient = get_transient( $helpers -> get_access_token_transient_key() );
        $refresh_token_transient = get_transient( $helpers -> get_refresh_token_transient_key() );

        $output = '<aside class="bluesky-cache-status">';

        $output .= '<h3 class="bluesky-cache-title">' . esc_html__( 'Current cache status:', 'social-integration-for-bluesky') . '</h3>';
        
        // Profile cache status
        $output .= '<p><strong>' . esc_html( __('Profile Card Cache:', 'social-integration-for-bluesky') ) . '</strong> ';
        if ( $profile_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_profile_transient_key() );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html( __('Active (expires in %s)', 'social-integration-for-bluesky') ),
                '<code>' . esc_html( $this -> format_time_remaining( $time_remaining ) ) . '</code>'
            );
        } else {
            $output .=  esc_html( __('Not cached', 'social-integration-for-bluesky') );
        }
        $output .= '</p>';

        // Posts cache status
        $output .=  '<p><strong>' . esc_html( __('Posts Feed Cache:', 'social-integration-for-bluesky') ) . '</strong> ';
        if ( $posts_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_posts_transient_key() );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html( __('Active (expires in %s)', 'social-integration-for-bluesky') ),
                '<code>' . esc_html( $this -> format_time_remaining( $time_remaining ) ) . '</code>'
            );
        } else {
            $output .= esc_html( __('Not cached', 'social-integration-for-bluesky') );
        }
        $output .= '</p>';

        $output .= '<hr>';

        // Access Token cache status
        $output .= '<p><strong>' . esc_html( __('Access Token Cache:', 'social-integration-for-bluesky') ) . '</strong> ';
        if ( $access_token_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_access_token_transient_key() );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html( __('Active (expires in %s)', 'social-integration-for-bluesky') ),
                '<code>' . esc_html( $this -> format_time_remaining( $time_remaining ) ) . '</code>'
            );
        } else {
            $output .= esc_html( __('Not cached', 'social-integration-for-bluesky') );
        }
        $output .= '</p>';

        // Refresh Token cache status
        $output .= '<p><strong>' . esc_html( __('Refresh Token Cache:', 'social-integration-for-bluesky') ) . '</strong> ';
        if ( $refresh_token_transient !== false ) {
            $time_remaining = $this -> get_transient_expiration_time( $helpers -> get_refresh_token_transient_key() );
            $output .= sprintf(
                // translators: %s is the time remaining
                esc_html( __('Active (expires in %s)', 'social-integration-for-bluesky') ),
                '<code>' . esc_html( $this -> format_time_remaining( $time_remaining ) ) . '</code>'
            );
        } else {
            $output .= esc_html( __('Not cached', 'social-integration-for-bluesky') );
        }
        $output .= '</p>';
        $output .= '</aside>';

        return $output;
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
            return esc_html( __('expired', 'social-integration-for-bluesky') );
        }

        $days = floor( $seconds / 86400 );
        $hours = floor( ( $seconds % 86400 ) / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $remaining_seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            // translators: %d is the number of days
            $parts[] = sprintf( esc_html( _n('%d day', '%d days', $days, 'social-integration-for-bluesky') ), $days );
        }
        if ( $hours > 0 ) {
            // translators: %d is the number of hours
            $parts[] = sprintf( esc_html( _n( '%d hour', '%d hours', $hours, 'social-integration-for-bluesky' ) ), $hours );
        }
        if ( $minutes > 0 ) {
            // translators: %d is the number of minutes
            $parts[] = sprintf( esc_html( _n( '%d minute', '%d minutes', $minutes, 'social-integration-for-bluesky' ) ), $minutes );
        }
        if ( empty( $parts ) || $remaining_seconds > 0 ) {
            // translators: %d is the number of seconds
            $parts[] = sprintf( esc_html( _n( '%d second', '%d seconds', $remaining_seconds, 'social-integration-for-bluesky' ) ), $remaining_seconds );
        }

        return implode(', ', $parts);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Adds a connection check using BlueSky API
        $api = new BlueSky_API_Handler( $this -> options );
        $auth = $api -> authenticate();

        ?>
        <main class="bluesky-social-integration-admin">
            <header role="banner" class="privacy-settings-header">
                <div class="privacy-settings-title-section">
                    <h1>
                        <svg width="64" height="56" viewBox="0 0 166 146" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z" fill="#1185FE"/>
                        </svg>
                        <?php echo esc_html__('Social Integration for BlueSky', 'social-integration-for-bluesky' ); ?>
                    </h1>
                </div>

                <nav id="bluesky-main-nav-tabs" role="navigation" class="privacy-settings-tabs-wrapper" aria-label="<?php esc_attr_e( 'Bluesky Settings Menu', 'social-integration-for-bluesky'); ?>">
                    <a href="#account" aria-controls="account" class="privacy-settings-tab active" aria-current="true">
                        <?php esc_html_e('Account Settings', 'social-integration-for-bluesky'); ?>
                    </a>

                    <?php if ( $auth ) { ?>
                    <a href="#customization" aria-controls="customization" class="privacy-settings-tab">
                        <?php esc_html_e('Customization', 'social-integration-for-bluesky'); ?>
                    </a>
                    <?php } ?>

                    <?php if ( $auth ) { ?>
                    <a href="#styles" aria-controls="styles" class="privacy-settings-tab">
                        <?php esc_html_e('Styles', 'social-integration-for-bluesky'); ?>
                    </a>
                    <?php } ?>
                    
                    <?php if ( $auth ) { ?>
                    <a href="#shortcodes" aria-controls="shortcodes" class="privacy-settings-tab">
                        <?php echo esc_html__('The shortcodes', 'social-integration-for-bluesky'); ?>
                    </a>
                    <?php } ?>

                    <a href="#about" aria-controls="about" class="privacy-settings-tab">
                        <?php echo esc_html__('About', 'social-integration-for-bluesky'); ?>
                    </a>
                </nav>
            </header>

            <div class="bluesky-social-integration-options">
                <form method="post" action="options.php">
                    
                    <?php
                        settings_fields('bluesky_settings_group');
                        do_settings_sections( BLUESKY_PLUGIN_SETTING_PAGENAME );

                        $style_layout = ! isset( $this -> options['styles']['feed_layout'] ) ? 'default' : $this -> options['styles']['feed_layout'];
                    ?>

                    <div id="styles" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__('Styles', 'social-integration-for-bluesky'); ?></h2>

                        <p><?php echo esc_html__('Decide how you want your Bluesky blocks to look like!', 'social-integration-for-bluesky'); ?></p>
                        
                        <h3><?php echo esc_html__('Customize Feed Layout', 'social-integration-for-bluesky'); ?></h3>

                        <p><?php echo esc_html__('Pick the layout that suits you best. Be careful, some of them could come later, and with specific pre-defined options. (e.g. "no-replies" by default)', 'social-integration-for-bluesky'); ?></p>

                        <div class="bluesky-social-integration-layout-options">
                            <label for="bluesky_settings_feed_layout_default">
                                <input id="bluesky_settings_feed_layout_default" type="radio" name="bluesky_settings[styles][feed_layout]" value="default"<?php echo $style_layout === 'default' ? ' checked="checked"' : '' ; ?>>
                                
                                <span class="screen-reader-text"><?php echo esc_html__('Default layout', 'social-integration-for-bluesky'); ?></span>

                                <img src="<?php echo BLUESKY_PLUGIN_FOLDER . '/assets/img/layout-default.svg'; ?>" alt="" width="150" height="163">
                            </label>

                            <label for="bluesky_settings_feed_layout_2">
                                <input id="bluesky_settings_feed_layout_2" type="radio" name="bluesky_settings[styles][feed_layout]" value="layout_2"<?php echo $style_layout === 'layout_2' ? ' checked="checked"' : '' ; ?>>

                                <span class="screen-reader-text"><?php echo esc_html__('Light Weight Layout', 'social-integration-for-bluesky'); ?></span>

                                <img src="<?php echo BLUESKY_PLUGIN_FOLDER . '/assets/img/layout-layout_2.svg'; ?>" alt="" width="150" height="163">
                            </label>
                        </div>

                        <?php submit_button(null, 'primary large', null, true); ?>

                        <h3><?php echo esc_html__('Customize Font Styling', 'social-integration-for-bluesky'); ?></h3>

                        <p><?php echo esc_html__('Tweak the display of each block by customizing the font sizes. Find the best balance!', 'social-integration-for-bluesky'); ?></p>

                        <div class="bluesky-custom-styles-output" hidden>
                            <?php 
                                $render_front = new BlueSky_Render_Front( $this -> api_handler );
                                $render_front -> render_inline_custom_styles();
                            ?>
                        </div>

                        <div class="bluesky-social-integration-large-content">
                            <section class="bluesky-social-integration-interactive" aria-label="[bluesky_profile]">
                                <div class="bluesky-social-integration-interactive-visual">
                                    <?php echo do_shortcode('[bluesky_profile]'); ?>
                                </div>
                                <div class="bluesky-social-integration-interactive-editor">
                                    <?php  
                                        $profile_data = $this->options['customisation']['profile'] ?? [];

                                        $profile_inputs = [
                                            'name' => [
                                                'fs' => [
                                                    'label' => __('Name/Pseudo', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 20,
                                                    'var' => '--bluesky-profile-custom-name-fs',
                                                ],
                                            ],
                                            'handle' => [
                                                'fs' => [
                                                    'label' => __('Nickhandle', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 14,
                                                    'var' => '--bluesky-profile-custom-handle-fs',
                                                ],
                                            ],
                                            'followers' => [
                                                'fs' => [
                                                    'label' => __('Counters', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 16,
                                                    'var' => '--bluesky-profile-custom-followers-fs',
                                                ],
                                            ],
                                            'description' => [
                                                'fs' => [
                                                    'label' => __('Biography', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 16,
                                                    'var' => '--bluesky-profile-custom-description-fs',
                                                ],
                                            ],
                                        ];
                                    ?>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php foreach ( $profile_inputs as $element => $properties ) { $index = 0; ?>
                                            <tr>
                                                <th scope="row">
                                                    <?php foreach ( $properties as $prop => $data ) { ?>

                                                    <label for="bluesky_custom_profile_<?php echo esc_attr( $element . '_' . $prop ); ?>"<?php echo $index > 0 ? 'class="screen-reader-text"' : ''; ?>>
                                                        <?php echo esc_html( $data['label'] ); ?>
                                                    </label>
                                                    
                                                    <?php $index++; } ?>
                                                </th>
                                                <td>
                                                    <?php foreach ( $properties as $prop => $data ) { ?>
                                                    <span class="bluesky-input-widget">
                                                        <input type="number" 
                                                            id="bluesky_custom_profile_<?php echo esc_attr( $element . '_' . $prop ); ?>"

                                                            name="bluesky_settings[customisation][profile][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][value]" 
                                                            
                                                            placeholder="<?php echo esc_attr($data['default']); ?>" 
                                                            
                                                            data-var="<?php echo esc_attr($data['var']); ?>" 
                                                            
                                                            aria-labelledby="bluesky_custom_profile_<?php echo esc_attr( $element . '_' . $prop ); ?> bluesky_custom_profile_<?php echo esc_attr( $element . '_' . $prop ); ?>_unit" 
                                                            
                                                            class="bluesky-custom-unit"
                                                            
                                                            min="<?php echo esc_attr($data['min']); ?>"
                                                            
                                                            value="<?php echo ( isset( $profile_data[$element][$prop]['value'] ) && intval( $profile_data[$element][$prop]['value'] ) >= $data['min'] ) ? intval( $profile_data[$element][$prop]['value'] ) : ''; ?>"

                                                            autocomplete="off"
                                                        >
                                                        <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][default]" value="<?php echo esc_attr($data['default']); ?>">

                                                        <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][min]" value="<?php echo esc_attr($data['min']); ?>">

                                                        <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_profile_<?php echo esc_attr( $element . '_' . $prop ); ?>_unit">px</abbr>
                                                    </span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                            <tr class="bluesky-submit-in-table">
                                                <td colspan="2">
                                                <?php submit_button(null, 'primary large', null, false); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="bluesky-social-integration-interactive" aria-label="[bluesky_last_posts]">
                                <div class="bluesky-social-integration-interactive-visual">
                                    <?php echo do_shortcode('[bluesky_last_posts]'); ?>
                                </div>
                                <div class="bluesky-social-integration-interactive-editor">
                                    <?php  
                                        $posts_data = $this->options['customisation']['posts'] ?? [];

                                        $posts_inputs = [
                                            'account-info-names' => [
                                                'fs' => [
                                                    'label' => __('Account Name', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 16,
                                                    'var' => '--bluesky-posts-custom-account-info-names-fs',
                                                ],
                                            ],
                                            'post-content' => [
                                                'fs' => [
                                                    'label' => __('Post Content', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 15,
                                                    'var' => '--bluesky-posts-custom-post-content-fs',
                                                ],
                                            ],
                                            'external-content-title' => [
                                                'fs' => [
                                                    'label' => __('External Title', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 18,
                                                    'var' => '--bluesky-posts-custom-external-content-title-fs',
                                                ],
                                            ],
                                            'external-content-description' => [
                                                'fs' => [
                                                    'label' => __('External Description', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 14,
                                                    'var' => '--bluesky-posts-custom-external-content-description-fs',
                                                ],
                                            ],
                                            'external-content-url' => [
                                                'fs' => [
                                                    'label' => __('External URL', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 16,
                                                    'var' => '--bluesky-posts-custom-external-content-url-fs',
                                                ],
                                            ],
                                            'starterpack-name' => [
                                                'fs' => [
                                                    'label' => __('StarterPack Name', 'social-integration-for-bluesky'),
                                                    'min' => 10,
                                                    'default' => 18,
                                                    'var' => '--bluesky-posts-custom-starterpack-name-fs',
                                                ],
                                            ],
                                        ];
                                    ?>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php foreach ( $posts_inputs as $element => $properties ) { $index = 0; ?>
                                            <tr>
                                                <th scope="row">
                                                    <?php foreach ( $properties as $prop => $data ) { ?>

                                                    <label for="bluesky_custom_posts_<?php echo esc_attr( $element . '_' . $prop ); ?>"<?php echo $index > 0 ? 'class="screen-reader-text"' : ''; ?>>
                                                        <?php echo esc_html( $data['label'] ); ?>
                                                    </label>
                                                    
                                                    <?php $index++; } ?>
                                                </th>
                                                <td>
                                                    <?php foreach ( $properties as $prop => $data ) { ?>
                                                    <span class="bluesky-input-widget">
                                                        <input type="number" 
                                                            id="bluesky_custom_posts_<?php echo esc_attr( $element . '_' . $prop ); ?>"

                                                            name="bluesky_settings[customisation][posts][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][value]" 
                                                            
                                                            placeholder="<?php echo esc_attr($data['default']); ?>" 
                                                            
                                                            data-var="<?php echo esc_attr($data['var']); ?>" 
                                                            
                                                            aria-labelledby="bluesky_custom_posts_<?php echo esc_attr( $element . '_' . $prop ); ?> bluesky_custom_posts_<?php echo esc_attr( $element . '_' . $prop ); ?>_unit" 
                                                            
                                                            class="bluesky-custom-unit"
                                                            
                                                            min="<?php echo esc_attr($data['min']); ?>"
                                                            
                                                            value="<?php echo ( isset( $posts_data[$element][$prop]['value'] ) && intval( $posts_data[$element][$prop]['value'] ) >= $data['min'] ) ? intval( $posts_data[$element][$prop]['value'] ) : ''; ?>"

                                                            autocomplete="off"
                                                        >

                                                        <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][default]" value="<?php echo esc_attr($data['default']); ?>">

                                                        <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr($element); ?>][<?php echo esc_attr($prop); ?>][min]" value="<?php echo esc_attr($data['min']); ?>">

                                                        <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_posts_<?php echo esc_attr( $element . '_' . $prop ); ?>_unit">px</abbr>
                                                    </span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                            <tr class="bluesky-submit-in-table">
                                                <td colspan="2">
                                                <?php submit_button(null, 'primary large', null, false); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                        </div>
                    </div>


                    <div id="shortcodes" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__('About the shortcodes', 'social-integration-for-bluesky'); ?></h2>
                        <?php // translators: %1$s is the the bluesky profile shortcode, %2$s is the bluesky last posts shortcode. ?>
                        <p><?php echo sprintf( esc_html__('You can use the following shortcodes to display your BlueSky profile and posts: %1$s and %2$s.', 'social-integration-for-bluesky'), '<code>[bluesky_profile]</code>', '<code>[bluesky_last_posts]</code>'); ?></p>

                        <p><?php echo esc_html__('By default, the shortcodes use the global settings, but you can decide to override them thanks to the attributes described on this page.', 'social-integration-for-bluesky'); ?></p>

                        <p><?php echo esc_html__('You can also use the Gutenberg blocks to display the profile card and posts feed.', 'social-integration-for-bluesky'); ?></p>

                        <?php if ( $auth ) { ?>
                        
                        <h2><?php echo esc_html__('Shortcodes Demo', 'social-integration-for-bluesky'); ?></h2>

                        <div class="bluesky-social-demo container">
                            <h3><?php echo esc_html__('Profile Card', 'social-integration-for-bluesky'); ?> <code>[bluesky_profile]</code></h3>
                            <p><?php echo esc_html__('The profile shortcode will display your BlueSky profile card. It uses the following attributes:', 'social-integration-for-bluesky'); ?></p>
                            <ul>
                                <li><code>displaybanner</code> - <?php echo esc_html__('Whether to display the profile banner. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>displayavatar</code> - <?php echo esc_html__('Whether to display the profile avatar. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>displaycounters</code> - <?php echo esc_html__('Whether to display follower/following counts. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>displaybio</code> - <?php echo esc_html__('Whether to display the profile bio. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>theme</code> - <?php echo esc_html__('The theme to use for displaying the profile. Options are "light", "dark", and "system". Default is "system".', 'social-integration-for-bluesky'); ?></li>
                                <li><code>classname</code> - <?php echo esc_html__('Additional CSS class to apply to the profile card.', 'social-integration-for-bluesky'); ?></li>
                            </ul>

                            <p><?php echo esc_html__('This is how your BlueSky profile card will look like:', 'social-integration-for-bluesky'); ?></p>
                            
                            <div class="demo">
                                <div class="demo-profile">
                                    <?php echo do_shortcode('[bluesky_profile]'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="bluesky-social-demo container">
                            <h3><?php echo esc_html__('Last Posts Feed', 'social-integration-for-bluesky'); ?> <code>[bluesky_last_posts]</code></h3>

                            <p><?php echo esc_html__('The last posts shortcode will display your last posts feed. It uses the following attributes:', 'social-integration-for-bluesky'); ?></p>
                            <ul>
                                <li><code>displayembeds</code> - <?php echo esc_html__('Whether to display embedded media in the posts. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>displayimages</code> - <?php echo esc_html__('Whether to display embedded images in the posts. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>noreplies</code> - <?php echo esc_html__('Whether to hide your replies, or include them in your feed. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>noreposts</code> - <?php echo esc_html__('Whether to hide the reposts, or include them in your feed. Default is true.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>numberofposts</code> - <?php echo esc_html__('The number of posts to display. Default is 5.', 'social-integration-for-bluesky'); ?></li>
                                <li><code>theme</code> - <?php echo esc_html__('The theme to use for displaying the posts. Options are "light", "dark", and "system". Default is "system".', 'social-integration-for-bluesky'); ?></li>
                            </ul>

                            <p><?php echo esc_html__('This is how your last posts feed will look like:', 'social-integration-for-bluesky'); ?></p>

                            <div class="demo">
                                <div class="demo-posts">
                                    <?php echo do_shortcode('[bluesky_last_posts numberofposts="3"]'); ?>
                                </div>
                            </div>
                        </div>
                        <?php
                        }
                        ?>
                    </div>
                    
                    <div id="about" aria-hidden="false" class="bluesky-social-integration-admin-content">
                        <h2><?php echo esc_html__('About this plugin', 'social-integration-for-bluesky'); ?></h2>
                        <?php // translators: %s is the name of the developer. ?>
                        <p><?php echo sprintf( esc_html__( 'This plugin is written by %s.', 'social-integration-for-bluesky'), '<a href="https://geoffreycrofte.com" target="_blank"><strong>Geoffrey Crofte</strong></a>' ); ?><br><?php echo esc_html__( 'This extension is not an official BlueSky plugin.', 'social-integration-for-bluesky')  ?></p>

                        <?php // translators: %1$s is the link opening tag, %2$s closing link tag. ?>
                        <p>
                            <?php echo sprintf( esc_html__( 'Need help with something? Have a suggestion? %1$sAsk away%2$s.', 'social-integration-for-bluesky'), '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/#new-topic-0" target="_blank">', '</a>' ); ?><br>
                            <?php echo sprintf( esc_html__( 'You want to contribute to this project? %1$sHere is the Github Repository%2$s.', 'social-integration-for-bluesky'), '<a href="https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress" target="_blank">', '</a>' ); ?>
                        </p>

                        <?php $title = __('Rate this plugin on WordPress.org', 'social-integration-for-bluesky') ?>

                        <?php // translators: %1$s is the link opening tag, %2$s closing link tag. ?>
                        <p><?php echo sprintf( esc_html__( 'Want to support the plugin? %1$sGive a review%2$s', 'social-integration-for-bluesky'), '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/reviews/" target="_blank" title="' . esc_attr( $title ) . '">', ' ⭐️⭐️⭐️⭐️⭐️</a>' ); ?></p>

                        <h2><?php echo esc_html__( 'Some Plugin Engine Info', 'social-integration-for-bluesky' ); ?></h2>
                        <?php echo $this -> display_cache_status(); ?>
                    </div>

                </form>
            </div>
            
            <?php if ( isset( $_GET['godmode'] ) || defined( 'WP_DEBUG' ) || defined( 'WP_DEBUG_DISPLAY' ) ) { ?>
            <aside class="bluesky-debug-sidebar is-collapsed">
                <button class="bluesky-open-button" type="button" aria-expanded="false" aria-controls="bluesky-debug-bar">
                    <span class="screen-reader-text"><?php esc_html_e('Debug Bar', 'social-integration-for-bluesky'); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><path fill="currentColor" d="M8.06561801,18.9432081 L14.565618,4.44320807 C14.7350545,4.06523433 15.1788182,3.8961815 15.5567919,4.06561801 C15.9032679,4.2209348 16.0741922,4.60676263 15.9697642,4.9611247 L15.934382,5.05679193 L9.43438199,19.5567919 C9.26494549,19.9347657 8.82118181,20.1038185 8.44320807,19.934382 C8.09673215,19.7790652 7.92580781,19.3932374 8.03023576,19.0388753 L8.06561801,18.9432081 L14.565618,4.44320807 L8.06561801,18.9432081 Z M2.21966991,11.4696699 L6.46966991,7.21966991 C6.76256313,6.9267767 7.23743687,6.9267767 7.53033009,7.21966991 C7.79659665,7.48593648 7.8208027,7.90260016 7.60294824,8.19621165 L7.53033009,8.28033009 L3.81066017,12 L7.53033009,15.7196699 C7.8232233,16.0125631 7.8232233,16.4874369 7.53033009,16.7803301 C7.26406352,17.0465966 6.84739984,17.0708027 6.55378835,16.8529482 L6.46966991,16.7803301 L2.21966991,12.5303301 C1.95340335,12.2640635 1.9291973,11.8473998 2.14705176,11.5537883 L2.21966991,11.4696699 L6.46966991,7.21966991 L2.21966991,11.4696699 Z M16.4696699,7.21966991 C16.7359365,6.95340335 17.1526002,6.9291973 17.4462117,7.14705176 L17.5303301,7.21966991 L21.7803301,11.4696699 C22.0465966,11.7359365 22.0708027,12.1526002 21.8529482,12.4462117 L21.7803301,12.5303301 L17.5303301,16.7803301 C17.2374369,17.0732233 16.7625631,17.0732233 16.4696699,16.7803301 C16.2034034,16.5140635 16.1791973,16.0973998 16.3970518,15.8037883 L16.4696699,15.7196699 L20.1893398,12 L16.4696699,8.28033009 C16.1767767,7.98743687 16.1767767,7.51256313 16.4696699,7.21966991 Z"></path></svg>
                </button>
                <div id="bluesky-debug-bar" class="bluesky-debug-sidebar-content" aria-hidden="true">
                    <h2><?php esc_html_e( 'Debug Bar', 'social-integration-for-bluesky' ); ?></h2>
                    <details>
                        <summary><?php esc_html_e( 'Plugin’s options', 'social-integration-for-bluesky' ); ?></summary>
                        <?php echo $this -> helpers -> war_dump( $this -> options ); ?>
                    </details>
                </div>
            </aside>
           <?php } ?>
        </main>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style( 'bluesky-social-admin', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-admin.css', array(), BLUESKY_PLUGIN_VERSION );
        wp_enqueue_style( 'bluesky-social-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css', array(), BLUESKY_PLUGIN_VERSION );
        wp_enqueue_style( 'bluesky-social-primsjs-css', BLUESKY_PLUGIN_FOLDER . 'assets/css/prism.min.css', array(), BLUESKY_PLUGIN_VERSION );
        wp_enqueue_style( 'bluesky-social-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css', array(), BLUESKY_PLUGIN_VERSION );
        wp_enqueue_script( 'bluesky-social-script', BLUESKY_PLUGIN_FOLDER . 'assets/js/bluesky-social-admin.js', ['jquery'], BLUESKY_PLUGIN_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
        wp_enqueue_script( 'bluesky-social-prismjs-js', BLUESKY_PLUGIN_FOLDER . 'assets/js/prism.min.js', array(), BLUESKY_PLUGIN_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        if ( ! is_admin() ) {
            wp_enqueue_style( 'bluesky-social-style-profile', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-profile.css', array(), BLUESKY_PLUGIN_VERSION );
            wp_enqueue_style( 'bluesky-social-style-posts', BLUESKY_PLUGIN_FOLDER . 'assets/css/bluesky-social-posts.css', array(), BLUESKY_PLUGIN_VERSION );
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
    public function syndicate_post_to_bluesky( $new_status, $old_status, $post ) {
        if ( ( 'publish' === $new_status && 'publish' !== $old_status ) && 'post' === $post->post_type ) {
            $post_id = $post -> ID;
            $permalink = get_permalink( $post_id );

            do_action( 'bluesky_before_syndicating_post', $post_id );

            // Check if the post should be syndicated (metabox option)
            // This metabox is set by the default global setting, or manually by the post editor.
            $dont_syndicate = get_post_meta( $post_id, '_bluesky_dont_syndicate', true );
            if ( $dont_syndicate || ( isset( $_POST['bluesky_dont_syndicate'] ) && $_POST['bluesky_dont_syndicate'] === '1' ) ) {
                return;
            }

            // Check if the post is already syndicated
            // because the action can be triggered multiple times by WordPress
            $is_syndicated = get_post_meta( $post_id, '_bluesky_syndicated', true );
            if ( $is_syndicated ) {
                return;
            }

            $this -> api_handler -> syndicate_post_to_bluesky( $post -> post_title, $permalink );

            // if it's supposed to be syndicated, add a meta to the post
            if ( ! $dont_syndicate ) {
                $post_meta = add_post_meta( $post_id, '_bluesky_syndicated', true, true );
            }

            do_action( 'bluesky_after_syndicating_post', $post_id, $post_meta );
        }
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
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'],
            BLUESKY_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        register_block_type('bluesky-social/posts', [
            'api_version' => 2,
            'editor_script' => 'bluesky-posts-block',
            'render_callback' => [$this, 'bluesky_posts_block_render'],
            'attributes' => [
                'displayembeds' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'noreplies' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'noreposts' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'theme' => [
                    'type' => 'string',
                    'default' => 'system'
                ],
                'numberofposts' => [
                    'type' => 'integer',
                    'default' => get_option(BLUESKY_PLUGIN_OPTIONS)['posts_limit'] ?? 5
                ]
            ]
        ]);

        // Register Profile Card
        wp_register_script(
            'bluesky-profile-block',
            BLUESKY_PLUGIN_FOLDER . 'blocks/bluesky-profile-card.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'],
            BLUESKY_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        register_block_type('bluesky-social/profile', [
            'api_version' => 2,
            'attributes' => [
                'displaybanner' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displayavatar' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displaycounters' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'displaybio' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'theme' => [
                    'type' => 'string',
                    'default' => 'system'
                ],
                'classname' => [
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
                    'label' => __('Rounded', 'social-integration-for-bluesky'),
                    'isDefault' => true
                ],
                [
                    'name' => 'outline',
                    'label' => __('Outline', 'social-integration-for-bluesky')
                ],
                [
                    'name' => 'squared',
                    'label' => __('Squared', 'social-integration-for-bluesky')
                ]
            ],
            'editor_script' => 'bluesky-profile-block',
            'render_callback' => [$this, 'bluesky_profile_block_render'],
        ]);
    }

    /**
     * Renders the BlueSky profile card block
     * 
     * @param array $attributes Block attributes including:
     *                         - displaybanner (bool) Whether to show the profile banner
     *                         - displayavatar (bool) Whether to show the profile avatar
     *                         - displaycounters (bool) Whether to show follower/following counts
     *                         - displaybio (bool) Whether to show the profile bio
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     *                         - classname (string) A custom string classname
     * @return string HTML markup for the profile card
     */
    public function bluesky_profile_block_render( $attributes = [] ) {
        // Get the style class
        $style_class = ! empty( $attributes['classname'] ) ? $attributes['classname'] : 'is-style-default';
        
        // Add the style class to the attributes for the render function
        $attributes['styleClass'] = $style_class;

        $render_front = new BlueSky_Render_Front( $this -> api_handler );
        return $render_front -> render_bluesky_profile_card( $attributes );
    }
    
    /**
     * Renders the BlueSky posts feed block
     * 
     * @param array $attributes Block attributes including:
     *                         - displayembeds (bool) Whether to show embedded media in posts
     *                         - noreplies (bool) Whether to show replies in posts
     *                         - noreposts (bool) Whether to show reposts in posts
     *                         - theme (string) Color theme - 'light', 'dark' or 'system'
     *                         - numberofposts (int) Number of posts to display (1-10)
     * @return string HTML markup for the posts feed
     */
    public function bluesky_posts_block_render( $attributes = [] ) {
        $render_front = new BlueSky_Render_Front( $this -> api_handler );
        return $render_front -> render_bluesky_posts_list( $attributes );
    }

    /**
     * Display logout messages based on the BLueSky_Admin_Actions method and redirection.
     *
     * @return void
     */
    public function display_bluesky_logout_message() {
        if ( $message_data = get_transient('bluesky_logout_message') ) {
            $class = ($message_data['type'] === 'success') ? 'updated' : 'error';
        ?>
        
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($message_data['message']); ?></p>
        </div>

        <?php
            delete_transient('bluesky_logout_message');
        }
    }
}