# Social Integration for BlueSky
- Contributors: Geoffrey Crofte (@creativejuiz)
- Donate link: https://paypal.me/crofte
- Tags: BlueSky, Syndicate, Profile, Feed
- Requires at least: 5.0
- Tested up to: 6.7
- Requires PHP: 7.4
- Stable tag: 1.4.0
- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html
- Official WordPress link: https://wordpress.org/plugins/social-integration-for-bluesky/


> Provides auto syndication (optional), a profile banner, and a last posts Gutenberg blocks for BlueSky Social.

## Description

This plugin provides your website with Gutenberg blocks including a configurable profile banner (followers, posts and followings counts, banner, avatar, and name) and a list of your latest posts on BlueSky.
A Shortcodes (`[bluesky_profile]` and `[bluesky_last_posts]`) and Widgets are given as well for older sites.

An option is available for syndication of posts for BlueSky Social.

Some other included features:

* Embedded posts in the feed
 * Youtube URL detection
 * Embedded video
 * Quote embedded
 * Link reference (embedded card with image)
 * Starterpack display
 * Gallery of images (displaying an accessible lightbox)
 * Multiple available layouts
* App Password for a more secure connection
* Cache for a more performant display and avoid BlueSky request limitations
* Dark/Light mode (by default is system/user choice)
* Lots of options in the display of your profile banner

## Shortcode usage

In the shortcodes below, the complete list of attributes is displayed. You can omit them if you want, as the default values or the global values will be used if you omit them.

### Display your profile banner

`[bluesky_profile theme="system" styleclass="" displaybanner="true" displayavatar="true" displaycounters="true" displaybio="true"]`

- `theme`: displays a different set of colors supporting dark and light modes (values: `system`, `light`, `dark`)
- `styleclass`: accept any string class-valid to customise the class attribute
- `displaybanner`: either you want to display your profile banner image or not (values: `true`, `false`)
- `displayavatar`: either you want to display your profile avatar or not (values: `true`, `false`)
- `displaycounters`: either you want to display your followers, following and posts, or not (values: `true`, `false`)
- `displaybio`: either you want to display your profile description or not (values: `true`, `false`)

### Display the last posts

`[bluesky_last_posts displaysembeds="true" noreplies="true" noreposts="true" theme="system" numberofposts="5"]`

- `displaysembeds`: either you want to display only your posts, or include the embeds too (values: `true`, `false`)
- `noreplies`: either you want to hide your replies, or include them in your feed (values: `true`, `false`)
- `noreposts`: either you want to hide your reposts, or include them in your feed (values: `true`, `false`)
- `theme`: displays a different set of colors supporting dark and light modes (values: `system`, `light`, `dark`)
- `numberofposts`: any number of posts to display. (advice, don't set a too high value)

## Installation

1. Install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the settings screen in your WordPress admin area to configure the plugin with at least your BlueSky IDs.

## Frequently Asked Questions

### Is this plugin secure?

Yes, this plugin uses a mix of secret keys, salts and OpenSSL methods to secure your BlueSky IDs.

### Do you care for performance?

No I don't. Just kidding, I do care for performance. The plugin uses caching to reduce the number of calls to the BlueSky API.

### What are the current options?

You have some options available like multiple ways to display your profile card and posts, the number of posts to display, whether to display embedded records or not, and the theme of the profile card and posts.

### How do I report issues?

The plugin is new.
Be patient, and report issues via the support forum or the GitHub repository: https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress/issues

### How do I ask for a feature support?

The plugin is new, so feel free to ask for new features or better support at: https://github.com/geoffreycrofte/bluesky-social-plugin-for-wordpress/issues
The more info you give, the better the support will be. Be patient, I work alone.

### Is this an official BlueSky Plugin

No it is not, but it is under evaluation of BlueSky's Team to take part of the developpement of this plugin.

## Screenshots

1. The profile card in light theme.
2. The list of posts in light theme.
3. The profile card in dark theme.
4. The list of posts in dark theme.
5. The Gutenberg block lists. (the last one isn't ours)
6. The BlueSky Posts Feed Gutenberg options.
7. The BlueSky Profile Gutenberg options.
8. The plugin settings screen.
9. A post automatically shared after a WordPress publication.

## Changelog

### 1.4.0
* **Features** 
 * Pick among different feed layouts.
 * Customize font-size.
 * Decide to display Reposts of hide them.
* **Improvements**
 * Better admin plugin user interface.

### 1.3.0
* **Features**
 * Displays links for URL and hashtags.
 * Displays Open Graph Image for embedded link cards.
 * Better empty states for new accounts.
 * Default avatar and banner for empty accounts.
* **Improvements**
 * Auto-syndication only auto-post posts created after plugin activation (🚨 existing users: you need to save your settings again)

### 1.2.0
* **Bug fix**
 * Text wrap renders better now.
 * numberofposts shortcode attribute works now.
 * displayembeds shortcode attribute works now.
* **Features**
 * Decide if you want to include images, video, links and other embeds, or not, in your feed in the global params
 * Display an accessible lightbox to display embedded images. (mostly useful for galleries)
 * Support Starterpack embed in posts

### 1.1.1
WordPress still distributing 1.0.1 instead of 1.1.0. Forcing a version number update.

### 1.1.0
- **Bug fix**:
 - Connexion with bluesky should be more consistent now.
 - Light mode in non-system mode wasn't overriding the system preference.
- **Features**:
 - Deactivate syndication for a specific post before publishing your post
 - Remove the replies from the post feed
 - Display the 2 shortcodes in demo within the admin page

### 1.0.1
Adds the proper information about the shortcodes in the setting page, the plugin description and the mini-description.

### 1.0.0
* Initial release.

## Upgrade Notice

### 1.3.0
Existing users: you need to save your settings again to ensure proper auto-syndication function.

### 1.2.0
Smalm breaking change on the shortcode attributes. Use lower cases on all the name from now on.

### 1.1.0
Important bug fix on maintaining the connexion with Bluesky Services

### 1.0.0
Initial release. Please backup your site before installing.

## Notes

This plugin is open source and licensed under GPLv2 or later. Contributions are welcome via GitHub.

## Known Bugs & Improvements

### Known Bugs
* On the Gutenberg editor, the blocks are not clickable. You need to open the block layers panel to select them. I'm working on it.

### Planned Improvements

Follow the roadmap on [Github Project](https://github.com/users/geoffreycrofte/projects/1/views/2).

* Enhance customization options for profile cards and posts.
* Add color scheme options for the profile card and posts.
* ~~Add support for Open Graph Image on Post Cards~~
* ~~Add support links and hashtag being real links~~
* ~~Add support for the embedded records options in the posts feed.~~
* ~~Adds an option within the post review to disable the syndication, post by post.~~
