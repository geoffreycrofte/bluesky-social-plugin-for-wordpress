# Social Integration for BlueSky
- Contributors: Geoffrey Crofte (@creativejuiz)
- Donate link: https://paypal.me/crofte
- Tags: BlueSky, Syndicate, Profile, Feed
- Requires at least: 5.0
- Tested up to: 6.9.1
- Requires PHP: 7.4
- Stable tag: 2.0.0
- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html
- Official WordPress link: https://wordpress.org/plugins/social-integration-for-bluesky/


> Provides auto syndication (optional), a profile banner, and a list of your latest posts on BlueSky as Gutenberg blocks. It also adds the ability to link syndicated WordPress posts in the comment section, "importing" BlueSky discussions.

## Description

This plugin provides your website with Gutenberg blocks including a configurable profile banner (followers, posts and followings counts, banner, avatar, and name) and a list of your latest posts on BlueSky.
A Shortcodes (`[bluesky_profile]` and `[bluesky_last_posts]`) and Widgets are given as well for older sites.

An option is available for the syndication of your posts for BlueSky Social. This syndication allows you to display BlueSky discussions directly in the comment section of your WordPress posts. (it's an option)

Some other included features:

* **Configurable Bluesky Profile Card**
  * Choose to display a banner, or not
  * Choose to display an avatar, or not
  * Choose to display your bio, or not
  * Choose to display your counter, or not

* **Embedded posts in the feed**
  * Youtube URL detection
  * Embedded video
  * Quote embedded
  * Link reference (embedded card with image)
  * Starterpack display
  * Gallery of images (displaying an accessible lightbox)
  * Multiple available layouts
 
* **Auto-post new WordPress posts on BlueSky**
  * Preview the post for BlueSky on WordPress pre-post checks panel (if activated)
  * Display the syndicated post directly below the Gutenberg editor for reference.
  * It displays also the discussion on this BlueSky post if any.
 
* **Make BlueSky discussions for syndicated posts visible**
  * Activate the "Discussions" option to display Bluesky discussion even if the comment section of your posts is deactivated.
  * Choose to display only first level, or multi-level comments.
  * Choose between collasped or visible multi-level comments.
  * Choose to include photos, videos and attachments.
  * Important: people you mute or block on Bluesky won't be visible in the discussions.

* Encrypted App Password for a more secure connection
* Cache for a more performant display and avoid BlueSky request limitations
* Dark/Light mode (by default system/user choice)
* Custom font sizing for both blocks/shortcods

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
- `nocounters` - Whether to hide like, repost, reply, and quote counters (value: `true`, `false`)
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

No it is not. But I'm always happy when core team developers suggest new features, ideas, or code improvements. ;)

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
10. A gallery and a starterpack embedding.
11. The lightbox when gallery images are available.
12. The second layout of the Post Feed.
13. Some other plugin settings options.
14. The discussion on BlueSky added to the syndicated blog post.

## Changelog

### 2.0.0
* **NEW FEATURES**
  * **Multiple accounts**
    * Add multiple accounts (optional) to a single WordPress site.
    * Check the one used for auto-syndication to send new WordPress posts to multiple BlueSky accounts
    * Pick a primary account named "Active" that will served as default account for Last Posts & Profile blocks
    * Gutenberg Last Posts & Profile blocks now have an "accounts" option to let you pick one of the registered BlueSky profiles.
  * **BlueSky Discussions:**
    * Display the BlueSky Post in the WP Post Editor below the Gutenberg editor, including link to the post, comments (discussion), and counters.
    * Added the ability to display BlueSky discussion for syndicated posts below your blog posts, including options like the depth of the answers, their display, the image content, the counters, etc. (see `Settings > BlueSky Settings > Discussions`)
    * Check in the Post List if the syndication exists (BlueSky column)
* **Improvements**
  * **Core functions**
    * Rework of the entire codebase for a more maintainable code.
    * Asynchronous loading to improve performance in the admin and in the front-end.
    * Better cache management for the front-end display.
    * Better BlueSky limit rate management in the admin and front-end.
    * Better error message and UX in the admin area.
  * **Auto-published new posts:**
    * Added the "rich card" support for syndication: now posts in BlueSky look better with image, title, excerpt and link, with an embedded media/card.
    * Added a preview of the syndicated post into the pre-post checks panel of WordPress.
  * **Latest BlueSky Posts**
    * Counters now have the option to be displayed (likes, comments, repost, bookmarks)
* **Compatibility**
  * Tested with WordPress 6.9.
  * Fixed the issue with Gutenberg blocks not being clickable for edit. (finally T.T)

### 1.4.5
* **Bug Fix**
  * Improved compatibility with PHP7+ (parse error and warnings should disappear)

### 1.4.4
* **Bug Fix**
  * Fix the block admin preview being broken in some cases.
  * Fix a bug in layout 2 where the banner would displays in 3:1 ratio too.
* **Improvement**
  * Rename the Account Name font-size setting into Name/Pseudo and add the Handle option.
* **Compatibility**
  * Tries fixing a Spectra block compatibility

### 1.4.3
Typo fix in the Profile Widget that would block the loading of your custom styles. (sorry I was tired when published the 1.4.2)

### 1.4.2
* **Improvements**
  * Design decision: some styles are applied by default when the container of the widget/block is really small (image, padding size reduction mostly)
  *  Banner on the profile card is now always 3:1 ratio.
* **Bug fix**
  * Widget wouldn't display styles. They are now displaying them inline as a degraded solution.
  * On the Widget in layer 2 of the feed, SVG button wouldn't display properly. It's fixed.

### 1.4.1
* **Bug fix**
  * Custom font-size works on Layout 2 display name.
  * Admin better styles on input numbers and buttons.

### 1.4.0
* **Features** 
  * Pick among two feed layouts.
  * Customize font-size.
  * Decide to display Reposts or hide them.
* **Improvements**
  * Better admin plugin user interface.
  * Default CSS improvements.
  * Debug your options using `&godmode` in the setting page URL
* **Bug fix**
  * Log out from your account.

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
* **Bug fix**:
  * Connexion with bluesky should be more consistent now.
  * Light mode in non-system mode wasn't overriding the system preference.
* **Features**:
  * Deactivate syndication for a specific post before publishing your post
  * Remove the replies from the post feed
  * Display the 2 shortcodes in demo within the admin page

### 1.0.1
Adds the proper information about the shortcodes in the setting page, the plugin description and the mini-description.

### 1.0.0
* Initial release.

## Upgrade Notice

### 1.5.0
This new version comes with new features, a bug fix on gutenberg blocks not being clickable, and some UI improvements here and there.

### 1.4.0
Be careful, this version comes with multiple new options and behavior. Try in a safe environment before upgrading or wait for the 1.4.1.

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
