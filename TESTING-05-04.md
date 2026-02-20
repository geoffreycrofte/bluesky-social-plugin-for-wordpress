# Phase 5 Plan 4 Testing Guide

## Integration Verification (Task 1)

All Phase 5 components are properly wired into the plugin:

### Widget Registration ✓
- **File require:** `classes/widgets/BlueSky_Profile_Banner_Widget.php` at line 59 of main plugin file
- **Widget registration:** `register_widget("BlueSky_Profile_Banner_Widget")` at line 69 of BlueSky_Blocks_Service

### Asset Enqueuing ✓
- **Profile banner CSS:** Enqueued at line 119 of BlueSky_Assets_Service (frontend context)
- **Gradient JS:** Enqueued at line 162 of BlueSky_Assets_Service with color-thief dependency
- **Color Thief CDN:** Enqueued at line 152 (version 2.4.0)
- **Block editor script:** Registered at line 245 of BlueSky_Blocks_Service

### Shortcode Registration ✓
- **Profile banner shortcode:** `[bluesky_profile_banner]` registered at line 46 of BlueSky_Render_Front
- **Existing profile shortcode:** `[bluesky_profile]` registered at line 38 (unchanged)
- **Existing posts shortcode:** `[bluesky_last_posts]` registered at line 42 (unchanged)

### Block Registration ✓
- **Profile banner block:** `bluesky-social/profile-banner` registered at line 260 of BlueSky_Blocks_Service
- **Render callback:** `bluesky_profile_banner_block_render()` at line 346

### PHP Syntax ✓
All modified files pass PHP lint check with no syntax errors.

## Test Page Shortcodes

Create a test page with the following shortcodes for manual verification:

```
[bluesky_profile_banner layout="full"]

[bluesky_profile_banner layout="compact"]

[bluesky_profile]

[bluesky_last_posts]
```

## Expected Behavior

- **No PHP errors** on page load
- **Profile banner (full)** renders with header image, overlapping avatar, name, handle, bio, stats
- **Profile banner (compact)** renders with header as background, overlaid content
- **Profile card** renders correctly (no regression)
- **Posts feed** renders correctly (no regression)

## Files Verified

- social-integration-for-bluesky.php (widget require at line 59)
- classes/BlueSky_Blocks_Service.php (block + widget registration)
- classes/BlueSky_Render_Front.php (shortcode registration)
- classes/BlueSky_Assets_Service.php (asset enqueuing)
- classes/widgets/BlueSky_Profile_Banner_Widget.php (widget class exists)
- blocks/bluesky-profile-banner.js (block script exists)
- assets/js/bluesky-profile-banner-gradient.js (gradient script exists)
- assets/css/bluesky-profile-banner.css (stylesheet exists)

All components properly integrated with no wiring issues found.
