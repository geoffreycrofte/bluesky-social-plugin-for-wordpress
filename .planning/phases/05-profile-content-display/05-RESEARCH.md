# Phase 5: Profile & Content Display - Research

**Researched:** 2026-02-19
**Domain:** WordPress frontend rendering, Bluesky API integration, UI component design
**Confidence:** HIGH

## Summary

Phase 5 adds a Bluesky-style profile banner component with two layout variants and enhances the existing posts feed with bookmark counters, GIF detection, and polished loading/empty states. The work builds on existing patterns: profile data already fetched via `app.bsky.actor.getProfile`, posts via `app.bsky.feed.getAuthorFeed`, both with Phase 3's 3-layer cache and stale-while-revalidate serving. The plugin already has template-based rendering with PHP includes, Gutenberg blocks using ServerSideRender, and classic widgets extending WP_Widget.

The technical challenges are well-defined: extract dominant color from avatar URLs for gradient fallbacks, detect GIF media types in embed structures, implement CSS-based skeleton shimmer effects, and extend existing renderer architecture without duplicating code. WordPress conventions are clear (kebab-case CSS classes with `bluesky-` prefix, snake_case PHP methods, PascalCase classes), and the Phase 2 refactoring established constructor DI patterns that this phase will follow.

**Primary recommendation:** Leverage existing template infrastructure (`templates/frontend/`), extend `BlueSky_Render_Front` with new methods for banner variants, use JavaScript color extraction libraries (Color Thief or Vibrant.js) for gradient fallbacks, detect GIFs via MIME type in embed blob data, and implement pure CSS skeleton loaders with `@keyframes` shimmer animations matching layout shapes.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Profile Banner Design:**
- Two layout variants, selectable in Gutenberg block inspector and plugin settings:
  1. **Full banner** — Bluesky-native style: wide header image, overlapping circular avatar, name/handle/bio below
  2. **Compact card** — Header image as background with overlaid avatar, name, and stats
- Display full stats on both variants: followers, following, and post count
- When no header image exists on the Bluesky profile, use a gradient fallback generated from the avatar's dominant color
- Link behavior: keep existing behavior from current profile card (name/handle links to Bluesky profile)

**Feed Enhancements:**
- Keep current list layout as-is (no grid layout)
- Show full post content — no truncation
- Engagement counters: keep existing like/repost/reply counters as optional (user toggle), add bookmark counter alongside them — same toggle controls all four
- GIF detection: detect when GIFs are used in posts and render them as inline animated images, not as embedded link cards
- Keep current image display behavior unchanged for non-GIF media
- Existing reply/repost filter toggle (DISP-04) must continue working seamlessly

**Empty & Loading States:**
- Loading: skeleton placeholders with shimmer effect matching the layout shape
- Empty feed: friendly message with subtle illustration/icon ("No posts yet")
- API down / circuit breaker open: show stale cached content with small "Content may be outdated" banner (leverages Phase 3 stale-while-revalidate)
- Load More button: simple "Load More" text, no count

### Claude's Discretion

- Skeleton placeholder exact design and animation timing
- Gradient algorithm for header image fallback
- Empty state illustration choice
- Exact spacing, typography, and responsive breakpoints
- How to detect GIF content from Bluesky API response structure

### Deferred Ideas (OUT OF SCOPE)

- Grid layout for posts feed (DISP-01) — dropped from v1, potential future phase
- Date range filtering (DISP-02) — deferred to future phase
- Hashtag filtering (DISP-03) — deferred to future phase

</user_constraints>

## Standard Stack

### Core WordPress APIs

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WP_Widget | WordPress 6.7+ | Classic widget rendering | WordPress native widget system, backward compatible |
| ServerSideRender | Gutenberg Package | Block editor preview | Official Gutenberg pattern for PHP-rendered blocks |
| register_block_type | WordPress 6.7+ | Gutenberg block registration | Standard WordPress block API |
| wp_localize_script | WordPress 6.7+ | Pass PHP data to JavaScript | WordPress convention for server→client data |
| wp_remote_get | WordPress HTTP API | Bluesky API calls | Already used in plugin, no external HTTP library needed |

### Color Extraction (JavaScript)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Color Thief | 2.4.0+ | Extract dominant color from images | Small (4KB), simple API, good for single dominant color |
| Vibrant.js | 1.0.0+ | Extract prominent color palette | More sophisticated, provides multiple swatches |

**Recommendation:** Use **Color Thief** — simpler, smaller footprint, sufficient for single dominant color extraction for gradient fallback.

**Installation:**
```bash
# Via CDN (no npm in this WordPress plugin)
# Enqueue in PHP: wp_enqueue_script('color-thief', 'https://cdn.jsdelivr.net/npm/colorthief@2.4.0/dist/color-thief.min.js')
```

### CSS Skeleton Loaders

| Approach | Purpose | When to Use |
|----------|---------|-------------|
| Pure CSS with @keyframes | Shimmer animation | Lightweight, no JS dependencies, GPU-accelerated |
| Inline background-image gradient | Shimmer sweep effect | CSS-only, fast, works in all browsers |

**No external library needed** — Pure CSS with `@keyframes` and `linear-gradient` is the 2026 standard.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Color Thief (JS) | PHP GD Library color sampling | Server-side processing avoids client-side JS but adds server load, no caching benefit |
| Pure CSS skeleton | WordPress Placeholder plugin | External dependency, overkill for simple shimmer effect |
| Inline SVG empty state | WordPress Dashicons | Dashicons are admin-only, not enqueued on frontend by default |

## Architecture Patterns

### Existing Renderer Pattern (from Phase 2 refactor)

Current `BlueSky_Render_Front` structure:
```
classes/BlueSky_Render_Front.php
├── __construct(BlueSky_API_Handler $api_handler)
├── render_bluesky_profile_card($atts = [])
├── render_bluesky_posts_list($atts = [])
├── render_bluesky_post_content($post)
├── get_inline_custom_styles($context)
└── Templates: templates/frontend/profile-card.php, posts-list.php
```

**Pattern:** Renderer class methods prepare data, include PHP templates with scoped variables (`$profile`, `$posts`, `$classes`, `$this`), templates handle HTML output.

### Extended Pattern for Profile Banner Variants

Add new methods to `BlueSky_Render_Front`:

```php
class BlueSky_Render_Front {
    /**
     * Render profile banner (two variants)
     * @param array $atts Attributes: layout ('full'|'compact'), account_id
     * @return string HTML output
     */
    public function render_profile_banner($atts = []) {
        $layout = $atts['layout'] ?? 'full'; // 'full' or 'compact'
        $account_id = $atts['account_id'] ?? '';

        // Fetch profile via existing API handler
        $profile = $this->api_handler->get_bluesky_profile();

        if (!$profile) {
            return $this->render_error_state('profile');
        }

        // Check for missing banner, generate gradient fallback
        if (empty($profile['banner'])) {
            $profile['banner'] = $this->generate_gradient_fallback($profile['avatar']);
        }

        // Determine template based on layout
        $template = $layout === 'compact'
            ? 'profile-banner-compact.php'
            : 'profile-banner-full.php';

        $classes = ['bluesky-profile-banner', "bluesky-profile-banner-{$layout}"];
        $aria_label = __('Bluesky Profile Banner', 'social-integration-for-bluesky');

        // Template receives: $profile, $classes, $aria_label, $this
        ob_start();
        include plugin_dir_path(BLUESKY_PLUGIN_FILE) . "templates/frontend/{$template}";
        return ob_get_clean();
    }

    /**
     * Generate gradient fallback from avatar dominant color
     * @param string $avatar_url Avatar image URL
     * @return string Data URI or CSS gradient
     */
    private function generate_gradient_fallback($avatar_url) {
        // Return data attribute for client-side JS processing
        // JS will use Color Thief to extract color and set CSS var
        return 'data:gradient-placeholder,' . esc_url($avatar_url);
    }
}
```

### Template Structure for Profile Banner

**Full Banner Template:** `templates/frontend/profile-banner-full.php`

```php
<?php
// Variables: $profile, $classes, $aria_label, $this
?>
<div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
     aria-label="<?php echo esc_attr($aria_label); ?>"
     data-banner-url="<?php echo esc_attr($profile['banner']); ?>">

    <div class="bluesky-profile-banner-header"
         style="background-image: url('<?php echo esc_url($profile['banner']); ?>');">
        <!-- Header image as CSS background -->
    </div>

    <div class="bluesky-profile-banner-content">
        <img class="bluesky-profile-banner-avatar"
             src="<?php echo esc_url($profile['avatar']); ?>"
             alt="<?php echo esc_attr($profile['displayName']); ?>"
             width="120" height="120">

        <h2 class="bluesky-profile-banner-name">
            <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>">
                <?php echo esc_html($profile['displayName']); ?>
            </a>
        </h2>

        <p class="bluesky-profile-banner-handle">
            @<?php echo esc_html($profile['handle']); ?>
        </p>

        <?php if (!empty($profile['description'])): ?>
        <p class="bluesky-profile-banner-bio">
            <?php echo nl2br(esc_html($profile['description'])); ?>
        </p>
        <?php endif; ?>

        <div class="bluesky-profile-banner-stats">
            <span class="stat">
                <strong><?php echo number_format_i18n($profile['followersCount']); ?></strong>
                <?php esc_html_e('Followers', 'social-integration-for-bluesky'); ?>
            </span>
            <span class="stat">
                <strong><?php echo number_format_i18n($profile['followsCount']); ?></strong>
                <?php esc_html_e('Following', 'social-integration-for-bluesky'); ?>
            </span>
            <span class="stat">
                <strong><?php echo number_format_i18n($profile['postsCount']); ?></strong>
                <?php esc_html_e('Posts', 'social-integration-for-bluesky'); ?>
            </span>
        </div>
    </div>
</div>
```

**Compact Card Template:** `templates/frontend/profile-banner-compact.php`

```php
<?php
// Compact variant: header as background, content overlaid
?>
<div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
     style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('<?php echo esc_url($profile['banner']); ?>');"
     data-banner-url="<?php echo esc_attr($profile['banner']); ?>">

    <img class="bluesky-profile-banner-avatar"
         src="<?php echo esc_url($profile['avatar']); ?>"
         alt="" width="60" height="60">

    <div class="bluesky-profile-banner-compact-content">
        <h3 class="bluesky-profile-banner-name">
            <a href="https://bsky.app/profile/<?php echo esc_attr($profile['handle']); ?>">
                <?php echo esc_html($profile['displayName']); ?>
            </a>
        </h3>
        <p class="bluesky-profile-banner-handle">@<?php echo esc_html($profile['handle']); ?></p>

        <div class="bluesky-profile-banner-stats">
            <span><?php echo number_format_i18n($profile['followersCount']); ?> Followers</span>
            <span><?php echo number_format_i18n($profile['followsCount']); ?> Following</span>
            <span><?php echo number_format_i18n($profile['postsCount']); ?> Posts</span>
        </div>
    </div>
</div>
```

### Gutenberg Block Registration Pattern

Extend existing block registration in `BlueSky_Plugin_Setup` or new `BlueSky_Blocks_Service`:

```php
public function register_profile_banner_block() {
    register_block_type('bluesky-social/profile-banner', [
        'render_callback' => [$this->render_front, 'render_profile_banner_block'],
        'attributes' => [
            'layout' => [
                'type' => 'string',
                'default' => 'full'
            ],
            'accountId' => [
                'type' => 'string',
                'default' => ''
            ]
        ]
    ]);
}

// In BlueSky_Render_Front
public function render_profile_banner_block($attributes) {
    return $this->render_profile_banner([
        'layout' => $attributes['layout'],
        'account_id' => $attributes['accountId']
    ]);
}
```

**JavaScript Block (ES5 for compatibility):**

```javascript
// blocks/bluesky-profile-banner.js
(function(blocks, element, components, i18n, blockEditor, serverSideRender) {
  const el = element.createElement;
  const { __ } = i18n;
  const { InspectorControls, useBlockProps } = blockEditor;
  const { PanelBody, SelectControl } = components;
  const ServerSideRender = serverSideRender;

  const edit = function(props) {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps();

    return el('div', blockProps,
      el(InspectorControls, { key: 'inspector' },
        el(PanelBody, {
          key: 'layout-options',
          title: __('Profile Banner Layout', 'social-integration-for-bluesky')
        },
          el(SelectControl, {
            label: __('Layout Variant', 'social-integration-for-bluesky'),
            value: attributes.layout,
            options: [
              { label: __('Full Banner', 'social-integration-for-bluesky'), value: 'full' },
              { label: __('Compact Card', 'social-integration-for-bluesky'), value: 'compact' }
            ],
            onChange: function(value) {
              setAttributes({ layout: value });
            }
          })
        )
      ),
      el(ServerSideRender, {
        block: 'bluesky-social/profile-banner',
        attributes: attributes
      })
    );
  };

  blocks.registerBlockType('bluesky-social/profile-banner', {
    title: __('Bluesky Profile Banner', 'social-integration-for-bluesky'),
    icon: 'admin-users',
    category: 'widgets',
    attributes: {
      layout: { type: 'string', default: 'full' },
      accountId: { type: 'string', default: '' }
    },
    edit: edit,
    save: function() { return null; } // Server-side render
  });

})(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.i18n,
  window.wp.blockEditor,
  window.wp.serverSideRender
);
```

### Skeleton Loader Pattern

**Pure CSS Shimmer Effect:**

```css
/* assets/css/frontend.css */
@keyframes shimmer {
  0% {
    background-position: -1000px 0;
  }
  100% {
    background-position: 1000px 0;
  }
}

.bluesky-skeleton {
  background: linear-gradient(
    90deg,
    #f0f0f0 0%,
    #f8f8f8 50%,
    #f0f0f0 100%
  );
  background-size: 1000px 100%;
  animation: shimmer 2s infinite;
  border-radius: 4px;
}

.bluesky-skeleton-profile-banner {
  width: 100%;
  height: 200px;
  margin-bottom: 16px;
}

.bluesky-skeleton-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  margin: -40px auto 16px;
}

.bluesky-skeleton-text {
  height: 16px;
  margin-bottom: 8px;
}

.bluesky-skeleton-text-short {
  width: 60%;
}

.bluesky-skeleton-text-long {
  width: 100%;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .bluesky-skeleton {
    background: linear-gradient(
      90deg,
      #2a2a2a 0%,
      #3a3a3a 50%,
      #2a2a2a 100%
    );
  }
}
```

**Loading State Template:** `templates/frontend/profile-banner-loading.php`

```php
<div class="bluesky-profile-banner bluesky-profile-banner-loading">
    <div class="bluesky-skeleton bluesky-skeleton-profile-banner"></div>
    <div class="bluesky-skeleton bluesky-skeleton-avatar"></div>
    <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-short"></div>
    <div class="bluesky-skeleton bluesky-skeleton-text bluesky-skeleton-text-long"></div>
</div>
```

### Anti-Patterns to Avoid

- **Fetching profile data in template:** Templates are view-only, all data fetching happens in renderer methods before template include
- **Inline JavaScript in templates:** Enqueue scripts via `wp_enqueue_script()`, pass data via `wp_localize_script()`
- **Duplicating API calls:** Use request cache (Phase 3) — multiple blocks/widgets on same page share single API call
- **Hard-coded gradient colors:** Always derive from avatar dominant color, don't use static fallback colors
- **Blocking JavaScript color extraction:** Color extraction for gradient fallback should be async, show placeholder gradient while processing

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dominant color extraction | Custom color sampling with PHP GD | Color Thief (JavaScript library) | Edge cases: color space conversion, perceptual weighting, alpha channels — tested library handles all |
| Shimmer animation timing | JavaScript setInterval animation loop | Pure CSS @keyframes | GPU-accelerated, no JavaScript overhead, automatic fallback on low-power devices |
| Image lazy loading | Custom IntersectionObserver wrapper | WordPress native `loading="lazy"` | Already implemented in existing templates, browser-native, no JS needed |
| MIME type detection | Regex parsing of file extensions | Bluesky API embed blob mimeType field | Bluesky API returns `mimeType` directly in embed data, authoritative source |
| Empty state SVG icons | Custom SVG drawing | Existing WordPress SVG from templates/frontend/posts-list.php | Already has accessible empty state SVG, reuse pattern |

**Key insight:** This phase extends existing patterns more than it creates new ones. The plugin already handles profile fetching, posts rendering, caching, and template inclusion. New work is primarily additive (banner variants, GIF detection, bookmark counter display) rather than architectural.

## Common Pitfalls

### Pitfall 1: Gradient Fallback Blocking Render

**What goes wrong:** Client-side color extraction with Color Thief requires image to load before processing, blocking banner display while avatar downloads.

**Why it happens:** Color Thief needs a loaded `<img>` element to read pixel data — can't work with URLs directly.

**How to avoid:**
1. Render banner immediately with neutral gradient placeholder (`linear-gradient(135deg, #ddd, #eee)`)
2. Load avatar image offscreen via JavaScript
3. Extract dominant color when image loads
4. Update CSS custom property (`--bluesky-banner-gradient`) to real gradient
5. CSS transition smooths visual change

**Warning signs:**
- Empty banner space on initial load
- Flash of unstyled content when gradient updates
- Console errors about Color Thief accessing unloaded images

**Code pattern:**
```javascript
// assets/js/profile-banner-gradient.js
(function() {
  'use strict';

  const banners = document.querySelectorAll('[data-banner-url^="data:gradient-placeholder"]');

  banners.forEach(function(banner) {
    const avatarUrl = banner.getAttribute('data-banner-url').replace('data:gradient-placeholder,', '');
    const img = new Image();
    img.crossOrigin = 'Anonymous'; // Needed for Color Thief

    img.onload = function() {
      const colorThief = new ColorThief();
      const color = colorThief.getColor(img);
      const rgb = 'rgb(' + color.join(',') + ')';

      // Set CSS variable for gradient
      banner.style.setProperty('--bluesky-banner-gradient',
        'linear-gradient(135deg, ' + rgb + ', ' + adjustBrightness(rgb, 20) + ')'
      );
    };

    img.src = avatarUrl;
  });

  function adjustBrightness(rgb, percent) {
    // Simple brightness adjustment for gradient variation
    // Parse rgb(r,g,b) and increase each channel by percent
    const values = rgb.match(/\d+/g).map(Number);
    const adjusted = values.map(function(v) {
      return Math.min(255, v + Math.round(v * percent / 100));
    });
    return 'rgb(' + adjusted.join(',') + ')';
  }
})();
```

### Pitfall 2: GIF Detection False Positives

**What goes wrong:** Detecting GIFs by file extension (`.gif`) or external media URI pattern fails when GIFs are embedded as external link cards instead of inline images.

**Why it happens:** Bluesky embed structure is complex — GIFs can appear as:
1. `embed.images[]` with `mimeType: "image/gif"` (inline image)
2. `embed.external` with `.gif` URL (link card preview)
3. `embed.media.external` with GIF thumbnail

**How to avoid:**
1. Check `embed.images[].image.mimeType === "image/gif"` first (authoritative)
2. For external embeds, only treat as GIF if it's in `embed.images` array, not `embed.external`
3. Never rely on URL extension alone — CDN URLs may not include `.gif`

**Warning signs:**
- Non-GIF images rendering as animated
- External link cards disappearing when they shouldn't
- Static GIF images not animating

**Code pattern:**
```php
// In BlueSky_API_Handler->transform_posts()
private function is_gif_embed($embed) {
    // Only check images array, not external media
    if (isset($embed['images'])) {
        foreach ($embed['images'] as $image) {
            if (isset($image['image']['mimeType']) &&
                $image['image']['mimeType'] === 'image/gif') {
                return true;
            }
        }
    }
    return false;
}

// In transform loop
if ($this->is_gif_embed($post['embed'])) {
    $images[] = [
        'url' => $image['fullsize'] ?? $image['thumb'],
        'alt' => $image['alt'] ?? '',
        'is_gif' => true, // Flag for template rendering
        'width' => $image['aspectRatio']['width'] ?? 0,
        'height' => $image['aspectRatio']['height'] ?? 0
    ];
}
```

### Pitfall 3: Bookmark Counter Missing from API Response

**What goes wrong:** Adding bookmark counter to template but Bluesky API doesn't return bookmark count in `app.bsky.feed.getAuthorFeed` response.

**Why it happens:** Bookmarks are private data in Bluesky — only the user can see their own bookmarks. The API doesn't expose bookmark counts on posts.

**How to avoid:**
1. Research current Bluesky API response structure first
2. If bookmark count not available, document as "future enhancement pending API support"
3. Don't add UI for non-existent data
4. Check if `viewer.bookmark` field exists (indicates user has bookmarked this post) as boolean, not count

**Warning signs:**
- Bookmark counter always shows 0
- API response inspection shows no `bookmarkCount` field
- Official Bluesky app doesn't show bookmark counts either

**Resolution:**
Per research, Bluesky API has `app.bsky.bookmark.getBookmarks` endpoint but doesn't expose public bookmark counts on posts. If user decision requires bookmark counter, must clarify: "bookmark indicator" (boolean: user has bookmarked) vs "bookmark count" (number: how many users bookmarked). Likely only boolean available.

### Pitfall 4: Skeleton Loader Layout Shift

**What goes wrong:** Skeleton placeholder dimensions don't match real content, causing layout shift (CLS) when content loads.

**Why it happens:** Skeleton uses fixed heights, but real content varies based on bio length, stats presence, responsive breakpoints.

**How to avoid:**
1. Skeleton dimensions should match most common content size
2. Use `aspect-ratio` CSS for banner (e.g., `aspect-ratio: 3/1` for 1500×500 Bluesky banner)
3. Reserve space for avatar with negative margin (`margin-top: -40px`) matching real layout
4. Test on mobile and desktop — skeleton should match both breakpoints

**Warning signs:**
- Visible jump when skeleton replaced by content
- Google PageSpeed Insights shows high CLS score
- Content appears "bouncy" on load

**Code pattern:**
```css
.bluesky-skeleton-profile-banner {
  width: 100%;
  aspect-ratio: 3 / 1; /* Bluesky native banner ratio */
  max-height: 500px;
}

.bluesky-skeleton-avatar {
  width: 80px;
  height: 80px;
  margin-top: -40px; /* Match real avatar overlap */
  margin-left: auto;
  margin-right: auto;
}
```

### Pitfall 5: Widget Shortcode Duplication

**What goes wrong:** Creating separate `[bluesky_profile_banner]` shortcode when widget and block already exist, violating DRY.

**Why it happens:** Copy-pasting existing shortcode registration without realizing shortcode can call same renderer method as widget/block.

**How to avoid:**
1. All three interfaces (shortcode, block, widget) call same `render_profile_banner()` method
2. Only difference is attribute parsing: widgets get `$instance`, blocks get `$attributes`, shortcodes get `$atts`
3. Renderer method accepts normalized array, each interface translates to that format

**Code pattern:**
```php
// Shortcode (BlueSky_Plugin_Setup or dedicated service)
add_shortcode('bluesky_profile_banner', function($atts) {
    $atts = shortcode_atts([
        'layout' => 'full',
        'account_id' => ''
    ], $atts);

    return $this->render_front->render_profile_banner($atts);
});

// Block render callback
public function render_profile_banner_block($attributes) {
    // Translate block attributes to renderer format
    return $this->render_front->render_profile_banner([
        'layout' => $attributes['layout'],
        'account_id' => $attributes['accountId']
    ]);
}

// Widget (in BlueSky_Profile_Banner_Widget::widget)
public function widget($args, $instance) {
    // Translate widget instance to renderer format
    $output = $this->render_front->render_profile_banner([
        'layout' => $instance['layout'],
        'account_id' => $instance['account_id']
    ]);

    echo $args['before_widget'] . $output . $args['after_widget'];
}
```

## Code Examples

### Profile Data Structure (from API)

Based on `BlueSky_API_Handler->get_bluesky_profile()` which calls `app.bsky.actor.getProfile`:

```php
// API response structure (JSON decoded to array)
$profile = [
    'did' => 'did:plc:...',
    'handle' => 'username.bsky.social',
    'displayName' => 'Display Name',
    'description' => 'Bio text...',
    'avatar' => 'https://cdn.bsky.app/img/avatar/plain/...',
    'banner' => 'https://cdn.bsky.app/img/banner/plain/...', // May be missing
    'followersCount' => 1234,
    'followsCount' => 567,
    'postsCount' => 890,
    'indexedAt' => '2026-02-19T10:30:00.000Z',
    'viewer' => [
        'muted' => false,
        'blockedBy' => false
    ]
];

// Check for missing banner
if (empty($profile['banner'])) {
    // Generate gradient fallback
    $profile['banner'] = $this->generate_gradient_fallback($profile['avatar']);
}
```

### Post Data Structure with GIF Detection

Based on `BlueSky_API_Handler->fetch_bluesky_posts()` transformation:

```php
// Current structure
$post = [
    'text' => 'Post content...',
    'url' => 'https://bsky.app/profile/handle/post/abc123',
    'created_at' => '2026-02-19T10:00:00.000Z',
    'account' => [
        'handle' => 'username.bsky.social',
        'display_name' => 'Display Name',
        'avatar' => 'https://...'
    ],
    'images' => [
        [
            'url' => 'https://cdn.bsky.app/img/...',
            'alt' => 'Alt text',
            'width' => 1200,
            'height' => 800
        ]
    ],
    'external_media' => [ /* link card data */ ],
    'embedded_media' => [ /* video/record data */ ],
    'counts' => [
        'reply' => 5,
        'repost' => 10,
        'like' => 42,
        'quote' => 3
    ],
    'facets' => [ /* link/mention data */ ]
];

// Add GIF detection in transform
private function transform_posts($raw_posts) {
    return array_map(function($post) {
        $post = $post['post'];

        $images = [];
        if (isset($post['embed']['images'])) {
            foreach ($post['embed']['images'] as $image) {
                $is_gif = isset($image['image']['mimeType']) &&
                          $image['image']['mimeType'] === 'image/gif';

                $images[] = [
                    'url' => $image['fullsize'] ?? $image['thumb'] ?? '',
                    'alt' => $image['alt'] ?? '',
                    'width' => $image['aspectRatio']['width'] ?? 0,
                    'height' => $image['aspectRatio']['height'] ?? 0,
                    'is_gif' => $is_gif // NEW FLAG
                ];
            }
        }

        return [
            // ... existing fields
            'images' => $images,
            // ...
        ];
    }, $raw_posts);
}
```

### Template GIF Rendering

In `templates/frontend/posts-list.php`, modify image gallery rendering:

```php
<?php foreach ($post['images'] as $image): ?>
    <a href="<?php echo esc_url($image['url']); ?>"
       class="bluesky-gallery-image <?php echo !empty($image['is_gif']) ? 'is-gif' : ''; ?>">
        <img src="<?php echo esc_url($image['url']); ?>"
             alt="<?php echo isset($image['alt']) ? esc_attr($image['alt']) : ''; ?>"
             <?php echo !empty($image['width']) ? ' width="' . esc_attr($image['width']) . '"' : ''; ?>
             <?php echo !empty($image['height']) ? ' height="' . esc_attr($image['height']) . '"' : ''; ?>
             loading="lazy">
    </a>
<?php endforeach; ?>
```

CSS to prevent GIF lightbox (GIFs should play inline, not open in lightbox):

```css
.bluesky-gallery-image.is-gif {
  cursor: default;
  pointer-events: auto; /* Keep link for accessibility but style differently */
}

.bluesky-gallery-image.is-gif img {
  /* Ensure GIFs animate inline */
  image-rendering: auto;
}
```

JavaScript to prevent lightbox click on GIFs:

```javascript
// In assets/js/bluesky-social-lightbox.js
document.querySelectorAll('.bluesky-gallery-image.is-gif').forEach(function(link) {
  link.addEventListener('click', function(e) {
    e.preventDefault(); // Don't open lightbox for GIFs
  });
});
```

### Empty State Template

Reuse existing pattern from `templates/frontend/posts-list.php` (lines 497-510):

```php
<?php if (empty($posts)): ?>
<div class="bluesky-social-integration-empty-state">
    <svg fill="none" width="64" viewBox="0 0 24 24" height="64" aria-hidden="true">
        <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"
              d="M3 4a1 1 0 0 1 1-1h1a8.003 8.003 0 0 1 7.75 6.006A7.985 7.985 0 0 1 19 6h1a1 1 0 0 1 1 1v1a8 8 0 0 1-8 8v4a1 1 0 1 1-2 0v-7a8 8 0 0 1-8-8V4Zm2 1a6 6 0 0 1 6 6 6 6 0 0 1-6-6Zm8 9a6 6 0 0 1 6-6 6 6 0 0 1-6 6Z">
        </path>
    </svg>
    <p class="bluesky-empty-state-message">
        <?php esc_html_e('No posts available.', 'social-integration-for-bluesky'); ?>
    </p>
</div>
<?php endif; ?>
```

**Styling:**
```css
.bluesky-social-integration-empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #666;
}

.bluesky-social-integration-empty-state svg {
  opacity: 0.5;
  margin-bottom: 16px;
}

.bluesky-empty-state-message {
  font-size: 16px;
  color: inherit;
}
```

### Stale Indicator Banner

Existing template: `templates/frontend/stale-indicator.php` (Phase 3):

```php
<?php
/**
 * Stale cache indicator template
 * @var string $time_ago Human-readable time since last update
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bluesky-stale-indicator"
     style="font-size: 0.8em; color: #666; margin-top: 5px;">
    <?php
    printf(
        esc_html__('Last updated %s ago', 'social-integration-for-bluesky'),
        esc_html($time_ago)
    );
    ?>
</div>
```

**Usage in renderer when circuit breaker open:**

```php
public function render_profile_banner($atts = []) {
    $profile = $this->api_handler->get_bluesky_profile();

    if (!$profile) {
        // Check if we served stale data
        if ($this->api_handler->served_stale_data()) {
            $cache_age = $this->api_handler->get_cache_age();
            $time_ago = human_time_diff($cache_age, current_time('timestamp'));

            ob_start();
            include plugin_dir_path(BLUESKY_PLUGIN_FILE) . 'templates/frontend/stale-indicator.php';
            $stale_banner = ob_get_clean();

            // Prepend to output
            return $stale_banner . $this->render_profile_banner_html($profile);
        }
        return $this->render_error_state('profile');
    }

    return $this->render_profile_banner_html($profile);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| JavaScript-driven skeleton loaders (React Skeleton packages) | Pure CSS @keyframes shimmer | 2023-2024 | 10-20x faster, GPU accelerated, no JS overhead |
| Server-side color extraction with GD library | Client-side JavaScript (Color Thief, Vibrant.js) | 2022+ | Offloads processing to client, enables caching, no server CPU spike |
| Inline SVG data URIs for images | WordPress native `loading="lazy"` attribute | WordPress 5.5 (2020) | Browser-native lazy load, no JS required, better performance |
| WordPress Dashicons for empty states | Custom SVG in templates | 2021+ | Dashicons are admin-only, custom SVG ensures frontend display |
| PHP image dimensions with getimagesize() | Aspect ratio from API response | ATProto API design | No server-side image download, faster, less bandwidth |
| IIFE function wrapping in Gutenberg blocks | WordPress @wordpress/scripts build tools | Gutenberg evolution | Modern WordPress standard: JSX+build vs ES5+IIFE, but plugin uses ES5 currently |

**Deprecated/outdated:**
- **wp.editor.ServerSideRender:** Deprecated in WordPress 5.3+ — use `@wordpress/server-side-render` package instead (same component, different import)
- **Manual RGB-to-HSL conversion for gradients:** Modern browsers (2026) support Oklab color space in CSS, better perceptual brightness consistency — use `linear-gradient(in oklab, ...)` when generating gradients
- **CSS vendor prefixes for animations:** `-webkit-animation`, `-moz-animation` unnecessary in 2026, all modern browsers support unprefixed `animation` and `@keyframes`

## Open Questions

### 1. Bookmark Counter API Availability

**What we know:**
- Bluesky API has `app.bsky.bookmark.getBookmarks` endpoint (returns bookmarks by authenticated user)
- No public bookmark count field found in `app.bsky.feed.getAuthorFeed` response
- Official Bluesky app doesn't display bookmark counts on posts

**What's unclear:**
- User decision specifies "bookmark counter" — does this mean:
  - A) Boolean indicator (user has bookmarked this post) — likely available as `viewer.bookmark` field
  - B) Numeric count (how many users bookmarked this post) — likely NOT available in API

**Recommendation:**
- Implement as boolean bookmark indicator if `viewer.bookmark` field exists in API response
- If numeric count required, document as "pending Bluesky API support" and implement when available
- During planning phase, test actual API response structure to confirm field availability

### 2. Color Thief CORS Restrictions

**What we know:**
- Color Thief requires loaded image to extract color
- Canvas API (used by Color Thief) requires CORS headers for cross-origin images
- Bluesky CDN serves images from `cdn.bsky.app` domain (cross-origin from user's WordPress site)

**What's unclear:**
- Does Bluesky CDN send `Access-Control-Allow-Origin: *` header?
- Will `img.crossOrigin = 'Anonymous'` work, or will CORS block color extraction?

**Recommendation:**
- Test Color Thief with actual Bluesky avatar URLs early in implementation
- If CORS blocks extraction, fallback options:
  - Server-side proxy: WordPress fetches image, serves with CORS headers
  - Static gradient palette: Pre-defined gradients selected by hash of avatar URL
  - PHP GD library: Server-side color extraction (less ideal but CORS-proof)

### 3. Skeleton Loader During AJAX Load More

**What we know:**
- Existing "Load More" button fetches additional posts via AJAX
- Phase 3 added request-level caching and stale-while-revalidate
- No loading indicator currently shown during AJAX fetch

**What's unclear:**
- Should skeleton loader appear when clicking "Load More"?
- Should new posts append with skeleton placeholder first, then morph to real content?
- Or should "Load More" button show spinner/loading state while fetching?

**Recommendation:**
- Simplest: Show loading state on "Load More" button itself (button text changes to "Loading..." with spinner)
- More sophisticated: Append skeleton placeholder posts while fetching, replace when data arrives
- Choice depends on UX preferences — "Load More" button state is simpler, skeleton append is more polished but complex

### 4. Profile Banner Widget Title Control

**What we know:**
- Classic widgets have `before_title`/`after_title` wrapper args
- Current `BlueSky_Profile_Widget` hard-codes title: `__( 'BlueSky Profile', 'social-integration-for-bluesky' )`

**What's unclear:**
- Should new profile banner widget allow custom title input in widget form?
- Or maintain hard-coded title like existing profile widget?
- Should title be hidden if banner includes profile name?

**Recommendation:**
- Add title field to widget form (WordPress standard pattern) with default "Bluesky Profile"
- Allow blank title to hide title output (check `!empty($title)` before echoing)
- Matches WordPress widget UX expectations

## Sources

### Primary (HIGH confidence)

- **WordPress Developer Documentation** - https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/ - ServerSideRender component usage (verified February 13, 2026)
- **WordPress Developer Documentation** - https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/ - Dynamic block patterns with render callbacks
- **Bluesky API Documentation** - https://docs.bsky.app/docs/api/app-bsky-actor-get-profile - getProfile endpoint specification
- **Bluesky API Documentation** - https://docs.bsky.app/docs/api/app-bsky-bookmark-get-bookmarks - Bookmark functionality (private data)
- **Plugin Codebase** - `classes/BlueSky_API_Handler.php` lines 567-687 - Existing `get_bluesky_profile()` implementation
- **Plugin Codebase** - `templates/frontend/profile-card.php` - Current profile card template pattern
- **Plugin Codebase** - `templates/frontend/posts-list.php` lines 497-510 - Existing empty state SVG
- **Plugin Codebase** - `.planning/codebase/CONVENTIONS.md` - Coding standards and naming patterns

### Secondary (MEDIUM confidence)

- **CSS-Tricks** - https://css-tricks.com/simple-image-placeholders-with-svg/ - SVG placeholder patterns
- **Medium** - https://codewithbilal.medium.com/how-to-create-a-skeleton-loading-shimmer-effect-with-pure-css-7f9041ec9134 - Pure CSS shimmer implementation
- **GitHub** - https://github.com/bluesky-social/atproto/blob/main/lexicons/app/bsky/embed/images.json - ATProto embed image schema
- **GitHub** - https://github.com/bluesky-social/atproto/discussions/1740 - Blob size limits and MIME type discussions
- **Color Thief** - https://lokeshdhakar.com/projects/color-thief/ - Dominant color extraction library
- **Vibrant.js** - http://jariz.github.io/vibrant.js/ - Prominent color palette extraction
- **Mobbin** - https://mobbin.com/glossary/skeleton - Skeleton UI design patterns and examples
- **Elementor Blog** - https://elementor.com/blog/css-gradients/ - CSS Gradients guide for 2026 (Oklab support)
- **Ayrshare** - https://www.ayrshare.com/complete-guide-to-bluesky-api-integration-authorization-posting-analytics-comments/ - Bluesky API integration guide

### Tertiary (LOW confidence - verify during implementation)

- **GitHub Issue** - https://github.com/bluesky-social/atproto/issues/763 - Discussion on image types (2+ years old, may be outdated)
- **SocialPilot** - https://www.socialpilot.co/blog/bluesky-image-sizes-guide - Bluesky image size specifications (banner 1500×500, not verified with official source)
- **GIGAZINE** - https://gigazine.net/gsc_news/en/20240426-bluesky-gif-search-2fa-link/ - GIF posting features (news article, not technical docs)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress APIs and patterns well documented, Color Thief is established library
- Architecture: HIGH - Existing plugin patterns clear from codebase analysis, extend rather than rebuild
- Pitfalls: MEDIUM-HIGH - GIF detection based on API structure analysis (HIGH), gradient fallback CORS issue untested (MEDIUM), bookmark counter API field unconfirmed (MEDIUM)
- Code examples: HIGH - All examples based on existing codebase patterns and WordPress conventions

**Research date:** 2026-02-19
**Valid until:** 30 days (WordPress/Bluesky APIs are stable, unlikely to change significantly)

---

**Sources:**
- [app.bsky.actor.getProfile - Bluesky API](https://docs.bsky.app/docs/api/app-bsky-actor-get-profile)
- [app.bsky.bookmark.getBookmarks | Bluesky](https://docs.bsky.app/docs/api/app-bsky-bookmark-get-bookmarks)
- [Creating dynamic blocks – Block Editor Handbook](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/)
- [@wordpress/server-side-render – Block Editor Handbook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/)
- [Color Thief - Extract dominant color from images](https://lokeshdhakar.com/projects/color-thief/)
- [Vibrant.js - Extract prominent colors](http://jariz.github.io/vibrant.js/)
- [How to Create a Skeleton Loading Shimmer Effect with Pure CSS](https://codewithbilal.medium.com/how-to-create-a-skeleton-loading-shimmer-effect-with-pure-css-7f9041ec9134)
- [CSS Gradients: The Complete Guide for 2026](https://elementor.com/blog/css-gradients/)
- [Skeleton UI Design: Best practices, Design variants & Examples | Mobbin](https://mobbin.com/glossary/skeleton)
- [atproto/lexicons/app/bsky/embed/images.json](https://github.com/bluesky-social/atproto/blob/main/lexicons/app/bsky/embed/images.json)
- [Bluesky Image Sizes: Banners, Profile, and More!](https://www.socialpilot.co/blog/bluesky-image-sizes-guide)
- [Complete Guide to Bluesky API Integration](https://www.ayrshare.com/complete-guide-to-bluesky-api-integration-authorization-posting-analytics-comments/)
