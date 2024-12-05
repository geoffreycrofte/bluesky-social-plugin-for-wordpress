=== BlueSky Social Integration ===
Contributors: Geoffrey Crofte
Donate link: https://paypal.me/crofte
Tags: BlueSky, Syndicate, Profile, Feed
Requires at least: 5.0
Tested up to: 6.4.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Provides Gutenberg blocks, Shortcode and Widget as well as optional syndication of posts for BlueSky Social.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bluesky-social-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the settings screen in your WordPress admin area to configure the plugin with at least your BlueSky IDs.

== Frequently Asked Questions ==

= Is this plugin secure? =

Yes, this plugin uses a mix of secret keys, salts and OpenSSL methods to secure your BlueSky IDs.

= Do you care for performance? =

No I don't. Just kidding, I do care for performance. The plugin uses caching to reduce the number of calls to the BlueSky API.

= What are the current options? =

You have some options available like multiple ways to display your profile card and posts, the number of posts to display, whether to display embedded records or not, and the theme of the profile card and posts.

= How do I report issues? =

The plugin is new.
Be patient, and report issues via the support forum or the GitHub repository: https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress/issues

= Is this an official BlueSky Plugin =

No it is not, but it is under evaluation of BlueSky's Team to take part of the developpement of this plugin.

== Screenshots ==

1. The profile card.
2. The list of posts.
3. A post automatically shared after a WordPress publication.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Please backup your site before installing.

== Notes ==

This plugin is open source and licensed under GPLv2 or later. Contributions are welcome via GitHub.

== Known Bugs & Improvements ==

= Known Bugs =
* On the Gutenberg editor, the blocks are not clickable. You need to open the block layers panel to select them. I'm working on it.

= Planned Improvements =
* Add support for the embedded records options in the posts feed.
* Enhance customization options for profile cards and posts.
* Add color scheme options for the profile card and posts.
* Adds an option within the post review to disable the syndication.
