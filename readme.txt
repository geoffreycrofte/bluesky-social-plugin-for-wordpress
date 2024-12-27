=== Social Integration for BlueSky ===
Contributors: CreativeJuiz
Donate link: https://paypal.me/crofte
Tags: BlueSky, Syndicate, Profile, Feed
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides auto syndication (optional), a profile banner, and a last posts Gutenberg blocks for BlueSky Social.

== Description ==

This plugin provides your website with Gutenberg blocks including a configurable profile banner (followers, posts and followings counts, banner, avatar, and name) and a list of your latest posts on BlueSky.
A Shortcodes and Widgets are given as well for older sites.

An option is available for syndication of posts for BlueSky Social.

Some other included features:

* Embedded posts in the feed
 * Youtube URL detection
 * Quote embedded
 * Link reference (embedded card)
* App Password for a more secure connection
* Cache for a more performant display and avoid BlueSky request limitations
* Dark/Light mode (by default is system/user choice)
* Lots of options in the display of your profile banner

Not yet supported:
* Galleries of images

== Installation ==

1. Install the plugin through the WordPress plugins screen directly.
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

= How do I ask for a feature support? =

The plugin is new, so feel free to ask for new features or better support at: https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress/issues
The more info you give, the better the support will be. Be patient, I work alone.

= Is this an official BlueSky Plugin =

No it is not, but it is under evaluation of BlueSky's Team to take part of the developpement of this plugin.

== Screenshots ==

1. The profile card in light theme.
2. The list of posts in light theme.
3. The profile card in dark theme.
4. The list of posts in dark theme.
5. The Gutenberg block lists. (the last one isn't ours)
6. The BlueSky Posts Feed Gutenberg options.
7. The BlueSky Profile Gutenberg options.
8. The plugin settings screen.
9. A post automatically shared after a WordPress publication.

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
