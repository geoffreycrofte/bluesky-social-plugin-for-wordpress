<?php
// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Variables available from including method:
// $this (the BlueSky_Settings_Service instance)
// $this->options, $this->account_manager, $this->api_handler, $this->helpers

$auth = true; // Render all tabs immediately — auth check happens via AJAX
?>
<main class="bluesky-social-integration-admin">
    <header role="banner" class="privacy-settings-header">
        <div class="privacy-settings-title-section">
            <h1>
                <svg width="64" height="56" viewBox="0 0 166 146" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z" fill="#1185FE"/>
                </svg>
                <?php echo esc_html__(
                    "Social Integration for BlueSky",
                    "social-integration-for-bluesky",
                ); ?>
            </h1>
        </div>

        <nav id="bluesky-main-nav-tabs" role="navigation" class="privacy-settings-tabs-wrapper" aria-label="<?php esc_attr_e(
            "Bluesky Settings Menu",
            "social-integration-for-bluesky",
        ); ?>">
            <a href="#account" aria-controls="account" class="privacy-settings-tab active" aria-current="true">
                <?php esc_html_e(
                    "Account Settings",
                    "social-integration-for-bluesky",
                ); ?>
            </a>

            <?php if ($auth) { ?>
            <a href="#customization" aria-controls="customization" class="privacy-settings-tab">
                <?php esc_html_e(
                    "Customization",
                    "social-integration-for-bluesky",
                ); ?>
            </a>
            <?php } ?>

            <?php if ($auth) { ?>
            <a href="#styles" aria-controls="styles" class="privacy-settings-tab">
                <?php esc_html_e(
                    "Styles",
                    "social-integration-for-bluesky",
                ); ?>
            </a>
            <?php } ?>

            <?php if ($auth) { ?>
            <a href="#discussions" aria-controls="discussions" class="privacy-settings-tab">
                <?php echo esc_html__(
                    "Discussions",
                    "social-integration-for-bluesky",
                ); ?>
            </a>
            <?php } ?>

            <?php if ($auth) { ?>
            <a href="#shortcodes" aria-controls="shortcodes" class="privacy-settings-tab">
                <?php echo esc_html__(
                    "The shortcodes",
                    "social-integration-for-bluesky",
                ); ?>
            </a>
            <?php } ?>

            <a href="#about" aria-controls="about" class="privacy-settings-tab">
                <?php echo esc_html__(
                    "About",
                    "social-integration-for-bluesky",
                ); ?>
            </a>
        </nav>
    </header>

    <div class="bluesky-social-integration-options">
        <form method="post" action="options.php">

            <?php
            settings_fields("bluesky_settings_group");
            do_settings_sections(BLUESKY_PLUGIN_SETTING_PAGENAME);

            $style_feed_layout = $this->options["styles"]["feed_layout"] ?? "default";
            $style_profile_layout = $this->options["styles"]["profile_layout"] ?? "default";
            ?>

            <div id="styles" aria-hidden="false" class="bluesky-social-integration-admin-content">
                <h2><?php echo esc_html__(
                    "Styles",
                    "social-integration-for-bluesky",
                ); ?></h2>

                <p><?php echo esc_html__(
                    "Decide how you want your Bluesky blocks to look like!",
                    "social-integration-for-bluesky",
                ); ?></p>

                <div class="bluesky-custom-styles-output" hidden>
                    <?php
                    $render_front = new BlueSky_Render_Front(
                        $this->api_handler,
                    );
                    $render_front->render_inline_custom_styles();
                    ?>
                </div>

                <!-- ============================
                     Profile Customization
                     ============================ -->

                <h3><?php echo esc_html__(
                    "Profile Customization",
                    "social-integration-for-bluesky",
                ); ?></h3>

                <h4><?php echo esc_html__(
                    "Profile Layout",
                    "social-integration-for-bluesky",
                ); ?></h4>

                <p><?php echo esc_html__(
                    "Choose the default layout for your profile card.",
                    "social-integration-for-bluesky",
                ); ?></p>

                <div class="bluesky-social-integration-layout-options">
                    <label for="bluesky_settings_profile_layout_default">
                        <input id="bluesky_settings_profile_layout_default" type="radio" name="bluesky_settings[styles][profile_layout]" value="default"<?php echo $style_profile_layout === "default" ? ' checked="checked"' : ""; ?>>
                        
                        <span class="screen-reader-text"><?php echo esc_html__(
                            "Default",
                            "social-integration-for-bluesky",
                        ); ?></span>

                        <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                            "/assets/img/profile-layout-default.svg"; ?>" alt="" width="150" height="163">
                    </label>

                    <label for="bluesky_settings_profile_layout_compact">
                        <input id="bluesky_settings_profile_layout_compact" type="radio" name="bluesky_settings[styles][profile_layout]" value="compact"<?php echo $style_profile_layout === "compact" ? ' checked="checked"' : ""; ?>>
                        
                        <span class="screen-reader-text"><?php echo esc_html__(
                            "Compact",
                            "social-integration-for-bluesky",
                        ); ?></span>

                        <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                            "/assets/img/profile-layout-compact.svg"; ?>" alt="" width="150" height="163">
                    </label>
                </div>

                <h4><?php echo esc_html__(
                    "Profile Font Styling",
                    "social-integration-for-bluesky",
                ); ?></h4>

                <p><?php echo esc_html__(
                    "Tweak the font sizes for the profile card.",
                    "social-integration-for-bluesky",
                ); ?></p>

                <div class="bluesky-social-integration-large-content">
                    <section class="bluesky-social-integration-interactive" aria-label="[bluesky_profile]">
                        <div class="bluesky-social-integration-interactive-visual">
                            <?php echo do_shortcode(
                                "[bluesky_profile]",
                            ); ?>
                        </div>
                        <div class="bluesky-social-integration-interactive-editor">
                            <?php
                            $profile_data =
                                $this->options["customisation"][
                                    "profile"
                                ] ?? [];

                            $profile_inputs = [
                                "name" => [
                                    "fs" => [
                                        "label" => __(
                                            "Name/Pseudo",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 20,
                                        "var" =>
                                            "--bluesky-profile-custom-name-fs",
                                    ],
                                ],
                                "handle" => [
                                    "fs" => [
                                        "label" => __(
                                            "Nickhandle",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 14,
                                        "var" =>
                                            "--bluesky-profile-custom-handle-fs",
                                    ],
                                ],
                                "followers" => [
                                    "fs" => [
                                        "label" => __(
                                            "Counters",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 16,
                                        "var" =>
                                            "--bluesky-profile-custom-followers-fs",
                                    ],
                                ],
                                "description" => [
                                    "fs" => [
                                        "label" => __(
                                            "Biography",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 16,
                                        "var" =>
                                            "--bluesky-profile-custom-description-fs",
                                    ],
                                ],
                            ];
                            ?>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <?php foreach (
                                        $profile_inputs
                                        as $element => $properties
                                    ) {
                                        $index = 0; ?>
                                    <tr>
                                        <th scope="row">
                                            <?php foreach (
                                                $properties
                                                as $prop => $data
                                            ) { ?>

                                            <label for="bluesky_custom_profile_<?php echo esc_attr(
                                                $element . "_" . $prop,
                                            ); ?>"<?php echo $index > 0
   ? 'class="screen-reader-text"'
   : ""; ?>>
                                                <?php echo esc_html(
                                                    $data["label"],
                                                ); ?>
                                            </label>

                                            <?php $index++;} ?>
                                        </th>
                                        <td>
                                            <?php foreach (
                                                $properties
                                                as $prop => $data
                                            ) { ?>
                                            <span class="bluesky-input-widget">
                                                <input type="number"
                                                    id="bluesky_custom_profile_<?php echo esc_attr(
                                                        $element .
                                                            "_" .
                                                            $prop,
                                                    ); ?>"

                                                    name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                        $element,
                                                    ); ?>][<?php echo esc_attr(
   $prop,
); ?>][value]"

                                                    placeholder="<?php echo esc_attr(
                                                        $data[
                                                            "default"
                                                        ],
                                                    ); ?>"

                                                    data-var="<?php echo esc_attr(
                                                        $data["var"],
                                                    ); ?>"

                                                    aria-labelledby="bluesky_custom_profile_<?php echo esc_attr(
                                                        $element .
                                                            "_" .
                                                            $prop,
                                                    ); ?> bluesky_custom_profile_<?php echo esc_attr(
    $element . "_" . $prop,
); ?>_unit"

                                                    class="bluesky-custom-unit"

                                                    min="<?php echo esc_attr(
                                                        $data["min"],
                                                    ); ?>"

                                                    value="<?php echo isset(
                                                        $profile_data[
                                                            $element
                                                        ][$prop][
                                                            "value"
                                                        ],
                                                    ) &&
                                                    intval(
                                                        $profile_data[
                                                            $element
                                                        ][$prop][
                                                            "value"
                                                        ],
                                                    ) >= $data["min"]
                                                        ? intval(
                                                            $profile_data[
                                                                $element
                                                            ][$prop][
                                                                "value"
                                                            ],
                                                        )
                                                        : ""; ?>"

                                                    autocomplete="off"
                                                >
                                                <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                    $element,
                                                ); ?>][<?php echo esc_attr(
   $prop,
); ?>][default]" value="<?php echo esc_attr($data["default"]); ?>">

                                                <input type="hidden" name="bluesky_settings[customisation][profile][<?php echo esc_attr(
                                                    $element,
                                                ); ?>][<?php echo esc_attr(
   $prop,
); ?>][min]" value="<?php echo esc_attr($data["min"]); ?>">

                                                <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_profile_<?php echo esc_attr(
                                                    $element .
                                                        "_" .
                                                        $prop,
                                                ); ?>_unit">px</abbr>
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                    } ?>
                                    <tr class="bluesky-submit-in-table">
                                        <td colspan="2">
                                        <?php submit_button(
                                            null,
                                            "secondary large",
                                            null,
                                            false,
                                        ); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <?php submit_button(
                    null,
                    "primary large",
                    null,
                    true,
                ); ?>

                <hr />
                <!-- ============================
                     Feed Customization
                     ============================ -->

                <h3><?php echo esc_html__(
                    "Feed Customization",
                    "social-integration-for-bluesky",
                ); ?></h3>

                <h4><?php echo esc_html__(
                    "Feed Layout",
                    "social-integration-for-bluesky",
                ); ?></h4>

                <p><?php echo esc_html__(
                    'Pick the layout that suits you best. Be careful, some of them could come later, and with specific pre-defined options. (e.g. "no-replies" by default)',
                    "social-integration-for-bluesky",
                ); ?></p>

                <div class="bluesky-social-integration-layout-options">
                    <label for="bluesky_settings_feed_layout_default">
                        <input id="bluesky_settings_feed_layout_default" type="radio" name="bluesky_settings[styles][feed_layout]" value="default"<?php echo $style_feed_layout === "default" ? ' checked="checked"' : ""; ?>>

                        <span class="screen-reader-text"><?php echo esc_html__(
                            "Default layout",
                            "social-integration-for-bluesky",
                        ); ?></span>

                        <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                            "/assets/img/layout-default.svg"; ?>" alt="" width="150" height="163">
                    </label>

                    <label for="bluesky_settings_feed_layout_2">
                        <input id="bluesky_settings_feed_layout_2" type="radio" name="bluesky_settings[styles][feed_layout]" value="layout_2"<?php echo $style_feed_layout === "layout_2" ? ' checked="checked"' : ""; ?>>

                        <span class="screen-reader-text"><?php echo esc_html__(
                            "Light Weight Layout",
                            "social-integration-for-bluesky",
                        ); ?></span>

                        <img src="<?php echo BLUESKY_PLUGIN_FOLDER .
                            "/assets/img/layout-layout_2.svg"; ?>" alt="" width="150" height="163">
                    </label>
                </div>

                <h4><?php echo esc_html__(
                    "Feed Font Styling",
                    "social-integration-for-bluesky",
                ); ?></h4>

                <p><?php echo esc_html__(
                    "Tweak the font sizes for the posts feed.",
                    "social-integration-for-bluesky",
                ); ?></p>

                <div class="bluesky-social-integration-large-content">
                    <section class="bluesky-social-integration-interactive" aria-label="[bluesky_last_posts]">
                        <div class="bluesky-social-integration-interactive-visual">
                            <?php echo do_shortcode(
                                "[bluesky_last_posts]",
                            ); ?>
                        </div>
                        <div class="bluesky-social-integration-interactive-editor">
                            <?php
                            $posts_data =
                                $this->options["customisation"][
                                    "posts"
                                ] ?? [];

                            $posts_inputs = [
                                "account-info-names" => [
                                    "fs" => [
                                        "label" => __(
                                            "Name/Pseudo",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 16,
                                        "var" =>
                                            "--bluesky-posts-custom-account-info-names-fs",
                                    ],
                                ],
                                "handle" => [
                                    "fs" => [
                                        "label" => __(
                                            "Nickhandle",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 14,
                                        "var" =>
                                            "--bluesky-posts-custom-handle-fs",
                                    ],
                                ],
                                "post-content" => [
                                    "fs" => [
                                        "label" => __(
                                            "Post Content",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 15,
                                        "var" =>
                                            "--bluesky-posts-custom-post-content-fs",
                                    ],
                                ],
                                "external-content-title" => [
                                    "fs" => [
                                        "label" => __(
                                            "External Title",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 18,
                                        "var" =>
                                            "--bluesky-posts-custom-external-content-title-fs",
                                    ],
                                ],
                                "external-content-description" => [
                                    "fs" => [
                                        "label" => __(
                                            "External Description",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 14,
                                        "var" =>
                                            "--bluesky-posts-custom-external-content-description-fs",
                                    ],
                                ],
                                "external-content-url" => [
                                    "fs" => [
                                        "label" => __(
                                            "External URL",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 16,
                                        "var" =>
                                            "--bluesky-posts-custom-external-content-url-fs",
                                    ],
                                ],
                                "starterpack-name" => [
                                    "fs" => [
                                        "label" => __(
                                            "StarterPack Name",
                                            "social-integration-for-bluesky",
                                        ),
                                        "min" => 10,
                                        "default" => 18,
                                        "var" =>
                                            "--bluesky-posts-custom-starterpack-name-fs",
                                    ],
                                ],
                            ];
                            ?>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <?php foreach (
                                        $posts_inputs
                                        as $element => $properties
                                    ) {
                                        $index = 0; ?>
                                    <tr>
                                        <th scope="row">
                                            <?php foreach (
                                                $properties
                                                as $prop => $data
                                            ) { ?>

                                            <label for="bluesky_custom_posts_<?php echo esc_attr(
                                                $element . "_" . $prop,
                                            ); ?>"<?php echo $index > 0
   ? 'class="screen-reader-text"'
   : ""; ?>>
                                                <?php echo esc_html(
                                                    $data["label"],
                                                ); ?>
                                            </label>

                                            <?php $index++;} ?>
                                        </th>
                                        <td>
                                            <?php foreach (
                                                $properties
                                                as $prop => $data
                                            ) { ?>
                                            <span class="bluesky-input-widget">
                                                <input type="number"
                                                    id="bluesky_custom_posts_<?php echo esc_attr(
                                                        $element .
                                                            "_" .
                                                            $prop,
                                                    ); ?>"

                                                    name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                        $element,
                                                    ); ?>][<?php echo esc_attr(
   $prop,
); ?>][value]"

                                                    placeholder="<?php echo esc_attr(
                                                        $data[
                                                            "default"
                                                        ],
                                                    ); ?>"

                                                    data-var="<?php echo esc_attr(
                                                        $data["var"],
                                                    ); ?>"

                                                    aria-labelledby="bluesky_custom_posts_<?php echo esc_attr(
                                                        $element .
                                                            "_" .
                                                            $prop,
                                                    ); ?> bluesky_custom_posts_<?php echo esc_attr(
    $element . "_" . $prop,
); ?>_unit"

                                                    class="bluesky-custom-unit"

                                                    min="<?php echo esc_attr(
                                                        $data["min"],
                                                    ); ?>"

                                                    value="<?php echo isset(
                                                        $posts_data[
                                                            $element
                                                        ][$prop][
                                                            "value"
                                                        ],
                                                    ) &&
                                                    intval(
                                                        $posts_data[
                                                            $element
                                                        ][$prop][
                                                            "value"
                                                        ],
                                                    ) >= $data["min"]
                                                        ? intval(
                                                            $posts_data[
                                                                $element
                                                            ][$prop][
                                                                "value"
                                                            ],
                                                        )
                                                        : ""; ?>"

                                                    autocomplete="off"
                                                >

                                                <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                    $element,
                                                ); ?>][<?php echo esc_attr(
   $prop,
); ?>][default]" value="<?php echo esc_attr($data["default"]); ?>">

                                                <input type="hidden" name="bluesky_settings[customisation][posts][<?php echo esc_attr(
                                                    $element,
                                                ); ?>][<?php echo esc_attr(
   $prop,
); ?>][min]" value="<?php echo esc_attr($data["min"]); ?>">

                                                <abbr title="pixels" class="bluesky-input-widget-unit" id="bluesky_custom_posts_<?php echo esc_attr(
                                                    $element .
                                                        "_" .
                                                        $prop,
                                                ); ?>_unit">px</abbr>
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                    } ?>
                                    <tr class="bluesky-submit-in-table">
                                        <td colspan="2">
                                        <?php submit_button(
                                            null,
                                            "secondary large",
                                            null,
                                            false,
                                        ); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>

                <?php submit_button(
                    null,
                    "primary large",
                    null,
                    true,
                ); ?>
            </div>


            <div id="shortcodes" aria-hidden="false" class="bluesky-social-integration-admin-content">
                <h2><?php echo esc_html__(
                    "About the shortcodes",
                    "social-integration-for-bluesky",
                ); ?></h2>
                <?php // translators: %1$s is the the bluesky profile shortcode, %2$s is the bluesky last posts shortcode.
?>
                <p><?php echo sprintf(
                    esc_html__(
                        'You can use the following shortcodes to display your BlueSky profile and posts: %1$s and %2$s.',
                        "social-integration-for-bluesky",
                    ),
                    "<code>[bluesky_profile]</code>",
                    "<code>[bluesky_last_posts]</code>",
                ); ?></p>

                <p><?php echo esc_html__(
                    "By default, the shortcodes use the global settings, but you can decide to override them thanks to the attributes described on this page.",
                    "social-integration-for-bluesky",
                ); ?></p>

                <p><?php echo esc_html__(
                    "You can also use the Gutenberg blocks to display the profile card and posts feed.",
                    "social-integration-for-bluesky",
                ); ?></p>

                <?php if ($auth) { ?>

                <h2><?php echo esc_html__(
                    "Shortcodes Demo",
                    "social-integration-for-bluesky",
                ); ?></h2>

                <div class="bluesky-social-demo container">
                    <h3><?php echo esc_html__(
                        "Profile Card",
                        "social-integration-for-bluesky",
                    ); ?> <code>[bluesky_profile]</code></h3>
                    <p><?php echo esc_html__(
                        "The profile shortcode will display your BlueSky profile card. It uses the following attributes:",
                        "social-integration-for-bluesky",
                    ); ?></p>
                    <ul>
                        <li><code>layout</code> - <?php echo esc_html__(
                            'The layout to use. Options are "default" and "compact". Default uses the global setting.',
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displaybanner</code> - <?php echo esc_html__(
                            "Whether to display the profile banner. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displayavatar</code> - <?php echo esc_html__(
                            "Whether to display the profile avatar. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displaycounters</code> - <?php echo esc_html__(
                            "Whether to display follower/following counts. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displaybio</code> - <?php echo esc_html__(
                            "Whether to display the profile bio. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>theme</code> - <?php echo esc_html__(
                            'The theme to use for displaying the profile. Options are "light", "dark", and "system". Default is "system".',
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>classname</code> - <?php echo esc_html__(
                            "Additional CSS class to apply to the profile card.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>account_id</code> - <?php echo sprintf(
                            esc_html__(
                                'The UUID of a specific account to display. Leave empty to use the active account. %1$sFind your Bluesky DID%2$s.',
                                "social-integration-for-bluesky",
                            ),
                            '<a href="https://ilo.so/bluesky-did" target="_blank" rel="noopener noreferrer">',
                            '</a>',
                        ); ?></li>
                    </ul>

                    <p><?php echo esc_html__(
                        "This is how your BlueSky profile card will look like:",
                        "social-integration-for-bluesky",
                    ); ?></p>

                    <div class="demo">
                        <div class="demo-profile">
                            <?php echo do_shortcode(
                                "[bluesky_profile]",
                            ); ?>
                        </div>
                    </div>
                </div>

                <div class="bluesky-social-demo container">
                    <h3><?php echo esc_html__(
                        "Last Posts Feed",
                        "social-integration-for-bluesky",
                    ); ?> <code>[bluesky_last_posts]</code></h3>

                    <p><?php echo esc_html__(
                        "The last posts shortcode will display your last posts feed. It uses the following attributes:",
                        "social-integration-for-bluesky",
                    ); ?></p>
                    <ul>
                        <li><code>layout</code> - <?php echo esc_html__(
                            'Override the feed layout. Options are "default", "layout_2" or "compact" (compact with header). Leave empty to use the global setting.',
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displayembeds</code> - <?php echo esc_html__(
                            "Whether to display embedded media in the posts. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>displayimages</code> - <?php echo esc_html__(
                            "Whether to display embedded images in the posts. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>noreplies</code> - <?php echo esc_html__(
                            "Whether to hide your replies, or include them in your feed. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>noreposts</code> - <?php echo esc_html__(
                            "Whether to hide the reposts, or include them in your feed. Default is true.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>numberofposts</code> - <?php echo esc_html__(
                            "The number of posts to display. Default is 5.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>nocounters</code> - <?php echo esc_html__(
                            "Whether to hide like, repost, reply, and quote counters. Default is false.",
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>theme</code> - <?php echo esc_html__(
                            'The theme to use for displaying the posts. Options are "light", "dark", and "system". Default is "system".',
                            "social-integration-for-bluesky",
                        ); ?></li>
                        <li><code>account_id</code> - <?php echo sprintf(
                            esc_html__(
                                'The UUID of a specific account to display. Leave empty to use the active account. %1$sFind your Bluesky DID%2$s.',
                                "social-integration-for-bluesky",
                            ),
                            '<a href="https://ilo.so/bluesky-did" target="_blank" rel="noopener noreferrer">',
                            '</a>',
                        ); ?></li>
                    </ul>

                    <p><?php echo esc_html__(
                        "This is how your last posts feed will look like:",
                        "social-integration-for-bluesky",
                    ); ?></p>

                    <div class="demo">
                        <div class="demo-posts">
                            <?php echo do_shortcode(
                                '[bluesky_last_posts numberofposts="3"]',
                            ); ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div id="about" aria-hidden="false" class="bluesky-social-integration-admin-content">
                <h2><?php echo esc_html__(
                    "About this plugin",
                    "social-integration-for-bluesky",
                ); ?></h2>
                <?php // translators: %s is the name of the developer.
?>
                <p><?php echo sprintf(
                    esc_html__(
                        "This plugin is written by %s.",
                        "social-integration-for-bluesky",
                    ),
                    '<a href="https://geoffreycrofte.com" target="_blank"><strong>Geoffrey Crofte</strong></a>',
                ); ?><br><?php echo esc_html__(
   "This extension is not an official BlueSky plugin.",
   "social-integration-for-bluesky",
); ?></p>

                <?php // translators: %1$s is the link opening tag, %2$s closing link tag.
?>
                <p>
                    <?php echo sprintf(
                        esc_html__(
                            'Need help with something? Have a suggestion? %1$sAsk away%2$s.',
                            "social-integration-for-bluesky",
                        ),
                        '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/#new-topic-0" target="_blank">',
                        "</a>",
                    ); ?><br>
                    <?php echo sprintf(
                        esc_html__(
                            'You want to contribute to this project? %1$sHere is the Github Repository%2$s.',
                            "social-integration-for-bluesky",
                        ),
                        '<a href="https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress" target="_blank">',
                        "</a>",
                    ); ?>
                </p>

                <?php $title = __(
                    "Rate this plugin on WordPress.org",
                    "social-integration-for-bluesky",
                ); ?>

                <?php // translators: %1$s is the link opening tag, %2$s closing link tag.
?>
                <p><?php echo sprintf(
                    esc_html__(
                        'Want to support the plugin? %1$sGive a review%2$s',
                        "social-integration-for-bluesky",
                    ),
                    '<a href="https://wordpress.org/support/plugin/social-integration-for-bluesky/reviews/" target="_blank" title="' .
                        esc_attr($title) .
                        '">',
                    " ⭐️⭐️⭐️⭐️⭐️</a>",
                ); ?></p>

                <h2><?php echo esc_html__(
                    "Some Plugin Engine Info",
                    "social-integration-for-bluesky",
                ); ?></h2>
                <?php echo $this->display_health_section(); ?>
            </div>

        </form>
    </div>

    <?php if (
        (isset($_GET["godmode"]) && current_user_can("manage_options")) ||
        defined("WP_DEBUG") ||
        defined("WP_DEBUG_DISPLAY")
    ) { ?>
    <aside class="bluesky-debug-sidebar is-collapsed">
        <button class="bluesky-open-button" type="button" aria-expanded="false" aria-controls="bluesky-debug-bar">
            <span class="screen-reader-text"><?php esc_html_e(
                "Debug Bar",
                "social-integration-for-bluesky",
            ); ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><path fill="currentColor" d="M8.06561801,18.9432081 L14.565618,4.44320807 C14.7350545,4.06523433 15.1788182,3.8961815 15.5567919,4.06561801 C15.9032679,4.2209348 16.0741922,4.60676263 15.9697642,4.9611247 L15.934382,5.05679193 L9.43438199,19.5567919 C9.26494549,19.9347657 8.82118181,20.1038185 8.44320807,19.934382 C8.09673215,19.7790652 7.92580781,19.3932374 8.03023576,19.0388753 L8.06561801,18.9432081 L14.565618,4.44320807 L8.06561801,18.9432081 Z M2.21966991,11.4696699 L6.46966991,7.21966991 C6.76256313,6.9267767 7.23743687,6.9267767 7.53033009,7.21966991 C7.79659665,7.48593648 7.8208027,7.90260016 7.60294824,8.19621165 L7.53033009,8.28033009 L3.81066017,12 L7.53033009,15.7196699 C7.8232233,16.0125631 7.8232233,16.4874369 7.53033009,16.7803301 C7.26406352,17.0465966 6.84739984,17.0708027 6.55378835,16.8529482 L6.46966991,16.7803301 L2.21966991,12.5303301 C1.95340335,12.2640635 1.9291973,11.8473998 2.14705176,11.5537883 L2.21966991,11.4696699 L6.46966991,7.21966991 L2.21966991,11.4696699 Z M16.4696699,7.21966991 C16.7359365,6.95340335 17.1526002,6.9291973 17.4462117,7.14705176 L17.5303301,7.21966991 L21.7803301,11.4696699 C22.0465966,11.7359365 22.0708027,12.1526002 21.8529482,12.4462117 L21.7803301,12.5303301 L17.5303301,16.7803301 C17.2374369,17.0732233 16.7625631,17.0732233 16.4696699,16.7803301 C16.2034034,16.5140635 16.1791973,16.0973998 16.3970518,15.8037883 L16.4696699,15.7196699 L20.1893398,12 L16.4696699,8.28033009 C16.1767767,7.98743687 16.1767767,7.51256313 16.4696699,7.21966991 Z"></path></svg>
        </button>
        <div id="bluesky-debug-bar" class="bluesky-debug-sidebar-content" aria-hidden="true">
            <h2><?php esc_html_e(
                "Debug Bar",
                "social-integration-for-bluesky",
            ); ?></h2>
            <details>
                <summary><?php esc_html_e(
                    "Former plugin's options (kept for retro-compatibility)",
                    "social-integration-for-bluesky",
                ); ?></summary>
                <?php echo $this->helpers->war_dump($this->options); ?>
            </details>
            <details>
                <summary><?php esc_html_e(
                    "Multi-Account: Accounts",
                    "social-integration-for-bluesky",
                ); ?></summary>
                <?php echo $this->helpers->war_dump(get_option('bluesky_accounts', [])); ?>
            </details>
            <details>
                <summary><?php esc_html_e(
                    "Multi-Account: Active Account",
                    "social-integration-for-bluesky",
                ); ?></summary>
                <?php echo $this->helpers->war_dump(get_option('bluesky_active_account', '')); ?>
            </details>
            <details>
                <summary><?php esc_html_e(
                    "Multi-Account: Global Settings",
                    "social-integration-for-bluesky",
                ); ?></summary>
                <?php echo $this->helpers->war_dump(get_option('bluesky_global_settings', [])); ?>
            </details>
            <details>
                <summary><?php esc_html_e(
                    "Multi-Account: Schema Version",
                    "social-integration-for-bluesky",
                ); ?></summary>
                <?php echo $this->helpers->war_dump(get_option('bluesky_schema_version', 1)); ?>
            </details>
        </div>
    </aside>
   <?php } ?>
</main>
