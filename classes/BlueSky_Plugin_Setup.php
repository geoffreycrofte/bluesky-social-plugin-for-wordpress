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
        add_action('enqueue_block_editor_assets', [$this, 'register_gutenberg_blocks']);

        // Post syndication
        if (!empty($this->options['auto_syndicate'])) {
            add_action('publish_post', [$this, 'syndicate_post_to_bluesky'], 10, 1);
        }

        // Shortcodes
        add_shortcode('bluesky_profile', [$this, 'bluesky_profile_shortcode']);
        add_shortcode('bluesky_last_posts', [$this, 'bluesky_last_posts_shortcode']);
    }

    /**
     * Load plugin text domain for internationalization
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('bluesky-social', false, BLUESKY_PLUGIN_DIRECTORY_NAME . '/languages');
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
        register_setting( 'bluesky_settings_group', BLUESKY_PLUGIN_OPTIONS );

        add_settings_section(
            'bluesky_main_settings',
            __('BlueSky Account Settings', 'bluesky-social'),
            [$this, 'settings_section_callback'],
            'bluesky-social-settings'
        );

        $this->add_settings_fields();
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
            ]
        ];

        foreach ($fields as $id => $field) {
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
        $handle = $this->options['handle'] ?? '';
        echo "<input type='text' id='" . BLUESKY_PLUGIN_OPTIONS . "_handle' name='bluesky_settings[handle]' value='" . esc_attr($handle) . "' />";
    }

    /**
     * Render password field
     */
    public function render_password_field() {
        $password = $this->options['app_password'] ?? '';
        echo "<input type='password' id='" . BLUESKY_PLUGIN_OPTIONS . "_password' name='bluesky_settings[app_password]' value='" . esc_attr($password) . "' />";
    }

    /**
     * Render auto-syndicate field
     */
    public function render_syndicate_field() {
        $auto_syndicate = $this->options['auto_syndicate'] ?? 0;
        echo '<input id="' . BLUESKY_PLUGIN_OPTIONS . '_syndicate" type="checkbox" name="bluesky_settings[auto_syndicate]" value="1" ' . checked(1, $auto_syndicate, false) . ' />';
    }

    /**
     * Render theme field
     */
    public function render_theme_field() {
        $theme = $this->options['theme'] ?? 'light';
        echo '<select name="bluesky_settings[theme]" id="' . BLUESKY_PLUGIN_OPTIONS . '_theme">';
        echo '<option value="light" ' . selected('light', $theme, false) . '>Light</option>';
        echo '<option value="dark" ' . selected('dark', $theme, false) . '>Dark</option>';
        echo '</select>';
    }

    /**
     * Render posts limit field
     */
    public function render_posts_limit_field() {
        $limit = $this->options['posts_limit'] ?? 10;
        echo "<input type='number' min='1' max='10' id='" . BLUESKY_PLUGIN_OPTIONS . "_posts_limit' name='bluesky_settings[posts_limit]' value='" . esc_attr($limit) . "' />";
        echo "<p class='description'>" . __('Enter the number of posts to display (1-10) - 10 is set by default', 'bluesky-social') . "</p>";
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
        $limit = $this->options['posts_limit'] ?? 10;
        $posts = $this->api_handler->fetch_bluesky_posts($limit);
        
        if ($posts !== false) {
            wp_send_json_success($posts);
        } else {
            wp_send_json_error('Could not fetch posts');
        }
        wp_die();
    }

    /**
     * AJAX handler for fetching BlueSky profile
     */
    public function ajax_get_bluesky_profile() {
        $profile = $this->api_handler->get_bluesky_profile();
        
        if ($profile) {
            wp_send_json_success($profile);
        } else {
            wp_send_json_error('Could not fetch profile');
        }
        wp_die();
    }

    /**
     * Syndicate post to BlueSky
     * @param int $post_id WordPress post ID
     */
    public function syndicate_post_to_bluesky($post_id) {
        $post = get_post($post_id);
        $permalink = get_permalink($post_id);
        
        $this->api_handler->syndicate_post_to_bluesky($post->post_title, $permalink);
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
            'editor_script' => 'bluesky-posts-block'
        ]);

        register_block_type('bluesky-social/profile', [
            'render_callback' => [ $this, 'bluesky_profile_block_render' ]
        ]);
    }

    public function bluesky_profile_block_render() {
        $render_front = new BlueSky_Render_Front( $this -> api_handler );
        return $render_front -> render_bluesky_profile_card();
    }
}