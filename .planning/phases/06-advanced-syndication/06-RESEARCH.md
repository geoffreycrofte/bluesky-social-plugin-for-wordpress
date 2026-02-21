# Phase 6: Advanced Syndication - Research

**Researched:** 2026-02-21
**Domain:** WordPress post metadata, Gutenberg editor integration, settings UI, category taxonomy filtering, character counting
**Confidence:** HIGH

## Summary

Phase 6 extends the existing syndication system with editable post text, category-based routing rules, per-account auto-post toggles, and a global pause mechanism. The core challenge is integrating editable text controls into both the Gutenberg sidebar and pre-publish panel while respecting Bluesky's 300 grapheme character limit. Category-based rules require per-account include/exclude mappings using WordPress's native category taxonomy with OR logic. The existing architecture already has post meta registration, multi-account support, async syndication, and pre-publish preview infrastructure—this phase builds on top without replacing working functionality.

**Primary recommendation:** Use WordPress's native REST API-enabled post meta for storing custom syndication text, implement character counting with Intl.Segmenter API for accurate grapheme cluster counting (matching Bluesky's actual limits), extend the existing pre-publish panel component with TextareaControl, create a new PluginDocumentSettingPanel for the sidebar, add a dedicated "Category Rules" tab in settings using WordPress nav-tab CSS classes, and track auto-post preferences and global pause state in the multi-account data structure.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Post format & content:**
- Post format stays as-is (rich card) — no format type selector needed
- Post text becomes editable per-post, with auto-generated default from title + excerpt
- Textarea with live character count (Bluesky 300 char limit)
- Rich card image: featured image preferred, fallback to first image in post content
- Don't redo existing working functionality — build on top of it

**Per-post overrides:**
- Controls live in BOTH sidebar panel AND pre-publish checks panel
- Sidebar panel for editing while writing, pre-publish for final confirmation
- Editable post text field with character counter in both locations
- Existing per-post syndication toggle stays as-is (no changes)
- Existing per-post account selection stays as-is (no changes)

**Category-based rules:**
- Include/exclude per account — each account has its own category filter
- OR logic: if ANY matching category is included, the post gets syndicated
- Categories only (not tags or custom taxonomies)
- Dedicated rules tab in settings — shows all accounts and their category mappings in one view
- When no rules are set for an account, all categories are included (default = syndicate everything)

**Auto-post behavior:**
- Scheduled posts auto-syndicate when WordPress publishes them at the scheduled time
- Never re-syndicate on post edits — once syndicated, WP edits don't touch Bluesky
- New accounts: ask user to choose auto-post preference during account setup
- Global pause toggle in settings to pause all syndication across all accounts (maintenance mode)

### Claude's Discretion

None specified — all implementation details are Claude's choice as long as they honor the locked decisions above.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.

</user_constraints>

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | Core (6.0+) | Post meta storage and retrieval | Built into WordPress, powers Gutenberg editor, handles auth/permissions automatically |
| Gutenberg Components | Core (@wordpress/components) | UI controls (TextareaControl) | Official WordPress block editor components, pre-styled, accessibility built-in |
| Gutenberg Edit Post | Core (@wordpress/edit-post) | Editor panels (PluginDocumentSettingPanel, PluginPrePublishPanel) | Official extension points for editor UI |
| Gutenberg Data | Core (@wordpress/data) | State management (withSelect, withDispatch) | Official data layer for Gutenberg, reactive updates |
| WordPress Settings API | Core | Admin settings page structure | Standard WordPress pattern for plugin settings |
| WordPress Taxonomy API | Core (get_categories) | Category retrieval and filtering | Native WordPress taxonomy system |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Intl.Segmenter | Native Browser API | Unicode grapheme cluster counting | Character counting for Bluesky's 300 grapheme limit (modern browsers only) |
| grapheme-splitter | 1.0.4 (NPM) | Grapheme counting polyfill | Fallback for browsers without Intl.Segmenter support |
| Action Scheduler | Already integrated | Scheduled post syndication | Already in use from Phase 3, handles future->publish transitions |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Intl.Segmenter | String.length | String.length counts UTF-16 code units, not grapheme clusters—incorrectly counts emoji/diacritics. Would fail Bluesky validation. |
| REST API meta | AJAX save handler | Custom AJAX requires more code, loses automatic conflict resolution, no auth handling, breaks Gutenberg patterns |
| PluginDocumentSettingPanel | Meta box | Meta boxes look visually inconsistent with Gutenberg, require PHP rendering, don't work well with block editor's reactive state |
| get_categories() | get_terms() | get_categories() is a wrapper around get_terms() optimized for 'category' taxonomy—simpler API for this use case |

**Installation:**

No new dependencies required. All core WordPress/Gutenberg APIs. Optional polyfill for older browsers:

```bash
npm install grapheme-splitter --save
```

## Architecture Patterns

### Recommended Project Structure

```
classes/
├── BlueSky_Syndication_Service.php    # Extend: add category filtering, pause check
├── BlueSky_Settings_Service.php       # Extend: add category rules tab
├── BlueSky_Account_Manager.php        # Extend: add auto_syndicate field, global_pause option
└── BlueSky_Post_Metabox.php           # Extend: register new post meta for custom text

blocks/
├── bluesky-pre-publish-panel.js       # Extend: add editable text + character counter
└── bluesky-sidebar-panel.js           # NEW: sidebar panel with same controls

assets/js/
└── bluesky-character-counter.js       # NEW: shared grapheme counting utility
```

### Pattern 1: REST API-Enabled Post Meta

**What:** Register post meta with `show_in_rest: true` to make it available to Gutenberg editor via REST API

**When to use:** Storing per-post syndication text that needs to be editable in Gutenberg editor

**Example:**

```php
// Source: https://developer.wordpress.org/reference/functions/register_post_meta/
register_post_meta('post', '_bluesky_syndication_text', [
    'show_in_rest' => true,
    'single' => true,
    'type' => 'string',
    'default' => '',
    'auth_callback' => function() {
        return current_user_can('edit_posts');
    },
]);
```

**Critical detail:** The `show_in_rest` parameter exposes meta in the REST API response that Gutenberg consumes. Without it, Gutenberg cannot read/write the meta value.

### Pattern 2: Grapheme Cluster Counting with Intl.Segmenter

**What:** Use browser's native Intl.Segmenter API to count Unicode grapheme clusters (matches Bluesky's counting method)

**When to use:** Character counters for Bluesky text (300 grapheme limit, not 300 bytes/UTF-16 code units)

**Example:**

```javascript
// Source: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/Segmenter
// With feature detection and fallback
function countGraphemes(text) {
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
        const segmenter = new Intl.Segmenter('en', { granularity: 'grapheme' });
        return Array.from(segmenter.segment(text)).length;
    }

    // Fallback: use grapheme-splitter library or simple length
    // (Should include grapheme-splitter polyfill for production)
    if (window.GraphemeSplitter) {
        const splitter = new GraphemeSplitter();
        return splitter.countGraphemes(text);
    }

    // Last resort: character length (inaccurate but better than nothing)
    return text.length;
}
```

**Why this matters:** Bluesky API enforces 300 grapheme limit. Emoji like 👨‍👩‍👧‍👦 count as 1 grapheme but 11 UTF-16 code units. Using `string.length` would incorrectly report 11, causing validation failures.

### Pattern 3: Shared Controls in Multiple Panels

**What:** Same UI controls (textarea + character counter) appear in both sidebar panel and pre-publish panel, reading/writing same post meta

**When to use:** Per-post settings that need to be accessible during writing AND during final pre-publish review

**Example:**

```javascript
// Source: Combined pattern from existing pre-publish panel
const { withSelect, withDispatch } = wp.data;
const { compose } = wp.compose;

// Shared component for both locations
const EditableSyndicationText = ({ text, updateText, characterCount, maxLength }) => {
    return wp.element.createElement('div', { className: 'bluesky-editable-text' },
        wp.element.createElement(TextareaControl, {
            label: 'Post text',
            value: text,
            onChange: updateText,
            rows: 4,
            help: `${characterCount} / ${maxLength} characters`
        })
    );
};

// Wire to post meta
const ConnectedEditableText = compose([
    withSelect((select) => {
        const meta = select('core/editor').getEditedPostAttribute('meta') || {};
        const text = meta._bluesky_syndication_text || '';
        return {
            text,
            characterCount: countGraphemes(text),
            maxLength: 300
        };
    }),
    withDispatch((dispatch) => ({
        updateText: (newText) => {
            dispatch('core/editor').editPost({
                meta: { _bluesky_syndication_text: newText }
            });
        }
    }))
])(EditableSyndicationText);
```

**Why this pattern:** User may edit during writing (sidebar) or during final review (pre-publish). Both panels must stay in sync. Using same post meta field ensures single source of truth.

### Pattern 4: Settings Page Tabs with WordPress nav-tab Classes

**What:** Use WordPress's built-in CSS classes (`nav-tab-wrapper`, `nav-tab`, `nav-tab-active`) for tabbed navigation

**When to use:** Settings pages with multiple sections (Account, Customization, Category Rules)

**Example:**

```php
// Source: https://code.tutsplus.com/the-wordpress-settings-api-part-5-tabbed-navigation-for-settings--wp-24971t
// In settings page render function
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'account';
?>
<h2 class="nav-tab-wrapper">
    <a href="?page=bluesky-settings&tab=account"
       class="nav-tab <?php echo $active_tab == 'account' ? 'nav-tab-active' : ''; ?>">
        Account
    </a>
    <a href="?page=bluesky-settings&tab=customization"
       class="nav-tab <?php echo $active_tab == 'customization' ? 'nav-tab-active' : ''; ?>">
        Customization
    </a>
    <a href="?page=bluesky-settings&tab=category-rules"
       class="nav-tab <?php echo $active_tab == 'category-rules' ? 'nav-tab-active' : ''; ?>">
        Category Rules
    </a>
</h2>

<?php if ($active_tab == 'category-rules'): ?>
    <!-- Category rules UI -->
<?php endif; ?>
```

**Why this pattern:** WordPress admin already includes CSS for these classes. No custom JavaScript required. Users familiar with pattern from core WP settings pages.

### Pattern 5: Category-Based Filtering with OR Logic

**What:** Filter posts by checking if ANY of the post's categories match the account's included categories

**When to use:** Determining if a post should syndicate to a specific account based on category rules

**Example:**

```php
// Source: https://developer.wordpress.org/reference/functions/get_the_category/
function should_syndicate_to_account($post_id, $account_id) {
    $account_manager = new BlueSky_Account_Manager();
    $account = $account_manager->get_account($account_id);

    // If no rules set, syndicate everything
    if (empty($account['category_rules']['include']) && empty($account['category_rules']['exclude'])) {
        return true;
    }

    // Get post categories
    $post_categories = get_the_category($post_id);
    $post_category_ids = array_map(function($cat) { return $cat->term_id; }, $post_categories);

    // Check exclude rules first (takes priority)
    if (!empty($account['category_rules']['exclude'])) {
        $excluded_ids = $account['category_rules']['exclude'];
        if (array_intersect($post_category_ids, $excluded_ids)) {
            return false; // Post has an excluded category
        }
    }

    // Check include rules (OR logic: if ANY category matches, include)
    if (!empty($account['category_rules']['include'])) {
        $included_ids = $account['category_rules']['include'];
        if (array_intersect($post_category_ids, $included_ids)) {
            return true; // Post has at least one included category
        }
        return false; // Post doesn't match any included categories
    }

    return true; // No rules blocking it
}
```

**Why OR logic:** User wants "syndicate if post is in Blog OR News OR Announcements". AND logic would require post to be in ALL categories simultaneously (impossible in most cases).

### Pattern 6: Scheduled Post Syndication with transition_post_status

**What:** Hook into `transition_post_status` to catch future->publish transitions when WordPress publishes scheduled posts

**When to use:** Auto-syndicating scheduled posts when their publish time arrives

**Example:**

```php
// Source: https://developer.wordpress.org/reference/hooks/transition_post_status/
// Already implemented in BlueSky_Syndication_Service::syndicate_post_to_bluesky()
public function syndicate_post_to_bluesky($new_status, $old_status, $post) {
    // Existing check catches draft->publish AND future->publish
    if ('publish' === $new_status && 'publish' !== $old_status && 'post' === $post->post_type) {
        // Check if already syndicated (prevents re-syndication on edits)
        $is_syndicated = get_post_meta($post_id, '_bluesky_syndicated', true);
        if ($is_syndicated) {
            return; // Already syndicated, don't do it again
        }

        // Proceed with syndication...
    }
}
```

**Critical detail:** The `'publish' !== $old_status` check ensures we only syndicate on NEW publishes (draft->publish, future->publish), not on post updates (publish->publish). This prevents re-syndication.

### Anti-Patterns to Avoid

- **Custom character counting with string.length:** JavaScript's `string.length` counts UTF-16 code units, not grapheme clusters. Emoji and special characters will be miscounted. Use Intl.Segmenter or grapheme-splitter library.
- **Separate settings for each panel:** Don't create different post meta fields for sidebar vs pre-publish panel. Use one meta field, accessed from both panels.
- **Global default syndication text:** Don't store default text in settings. Generate it dynamically from title + excerpt when post meta is empty.
- **Re-syndicating on post edits:** Don't check post status alone. Must check `_bluesky_syndicated` meta to prevent duplicate posts on Bluesky when user edits WP post.
- **Hard-coding category IDs:** Categories may differ between sites. Always use category term_id from get_categories() or get_the_category(), never hard-code IDs.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Unicode character counting | Custom regex/loops | Intl.Segmenter API + grapheme-splitter polyfill | Unicode grapheme clusters are complex: emoji sequences, zero-width joiners, combining diacritics, regional indicators. UAX #29 spec is 100+ pages. Libraries handle edge cases. |
| Post meta storage/retrieval | Custom AJAX endpoints | WordPress REST API with register_post_meta | REST API handles permissions, conflict resolution, validation, caching. Custom endpoints miss all of this. |
| Settings page tabs | Custom JavaScript router | WordPress nav-tab CSS classes + $_GET['tab'] | WordPress admin CSS already includes tab styles. No JS needed. Accessible by default. |
| Category filtering | Manual SQL queries | get_categories() / get_the_category() | WordPress taxonomy API handles term hierarchy, caching, permissions, filters. SQL misses all of this. |
| Editor state management | jQuery + DOM manipulation | Gutenberg withSelect/withDispatch | Gutenberg is React-based. Manually manipulating DOM will break on re-renders. Use data layer. |

**Key insight:** WordPress and Gutenberg provide robust, tested APIs for all the requirements in this phase. Custom implementations would require handling edge cases (Unicode, accessibility, caching, permissions, conflict resolution) that took WordPress years to get right. Use the platform.

## Common Pitfalls

### Pitfall 1: Incorrect Character Counting Breaks Bluesky API Validation

**What goes wrong:** Using JavaScript `string.length` reports 7 characters for "👨‍👩‍👧‍👦" (family emoji), but Bluesky API counts it as 1 grapheme. Plugin shows "7 / 300" characters used. User posts 50 emojis thinking they're under limit (350 code units, 50 graphemes). Bluesky API returns 400 error: "text must not be longer than 300 graphemes".

**Why it happens:** JavaScript strings use UTF-16 encoding. Complex emoji are composed of multiple code points joined with zero-width joiners. `string.length` counts code units, not user-perceived characters (grapheme clusters).

**How to avoid:**
1. Use Intl.Segmenter API for grapheme counting (available in modern browsers)
2. Include grapheme-splitter polyfill for older browsers
3. Match Bluesky's counting method exactly (grapheme clusters per UAX #29)

**Warning signs:**
- Character counter shows different count than Bluesky's web interface
- API validation errors with "text too long" when counter shows under limit
- Emoji-heavy posts consistently fail syndication

**Implementation check:**
```javascript
// Test with complex emoji
const text = "👨‍👩‍👧‍👦";
console.log(text.length); // 11 (WRONG for Bluesky)
const segmenter = new Intl.Segmenter('en', { granularity: 'grapheme' });
console.log(Array.from(segmenter.segment(text)).length); // 1 (CORRECT for Bluesky)
```

### Pitfall 2: Post Meta Not Available in Gutenberg Editor

**What goes wrong:** Developer registers post meta with `register_post_meta()` but forgets `show_in_rest: true`. Gutenberg editor cannot read or write the meta value. Sidebar panel shows empty field even when meta is saved in database. User edits text, clicks save, nothing changes.

**Why it happens:** Gutenberg is JavaScript-based and uses REST API to read/write post data. Without `show_in_rest: true`, meta field is not exposed in REST API responses. Gutenberg never receives the data.

**How to avoid:**
1. Always include `show_in_rest: true` in register_post_meta() for Gutenberg-accessible fields
2. Test by checking REST API response: `GET /wp-json/wp/v2/posts/{id}`—meta should appear in response
3. Use withSelect to verify field is readable before building UI

**Warning signs:**
- Field appears empty in editor but has value in database (check with WP-CLI or phpMyAdmin)
- Meta saves when using classic editor but not Gutenberg
- Browser console shows "undefined" when accessing meta via select('core/editor').getEditedPostAttribute('meta')

**Fix verification:**
```php
// Correct registration
register_post_meta('post', '_bluesky_syndication_text', [
    'show_in_rest' => true, // CRITICAL for Gutenberg
    'single' => true,
    'type' => 'string',
    'auth_callback' => function() { return current_user_can('edit_posts'); }
]);
```

### Pitfall 3: Re-Syndication on Post Edits Creates Duplicate Bluesky Posts

**What goes wrong:** User publishes post, syndicates to Bluesky successfully. Later, user edits WordPress post to fix typo. Upon saving, plugin syndicates again, creating duplicate post on Bluesky. User's Bluesky feed now has same article twice.

**Why it happens:** The `transition_post_status` hook fires on publish->publish transitions (post updates), not just draft->publish. If code only checks `$new_status === 'publish'`, it triggers on every post save.

**How to avoid:**
1. Check `_bluesky_syndicated` post meta before syndicating—if set, skip syndication
2. Ensure meta is set BEFORE API call completes (not after) to prevent race conditions
3. Document this behavior clearly: "Syndication happens once, on first publish"

**Warning signs:**
- Duplicate posts appear on Bluesky when editing WordPress posts
- Users report "accidentally syndicating twice"
- Syndication happens every time "Update" button is clicked

**Implementation check:**
```php
// BEFORE syndication
$is_syndicated = get_post_meta($post_id, '_bluesky_syndicated', true);
if ($is_syndicated) {
    return; // Already syndicated, bail out
}

// Proceed with syndication...
// IMMEDIATELY AFTER scheduling/sending (not after success callback)
add_post_meta($post_id, '_bluesky_syndicated', true, true);
```

### Pitfall 4: Category Rules Not Checked Before Auto-Syndication

**What goes wrong:** User sets up category rules: Account A syndicates only "News" category. User publishes post in "Blog" category. Post auto-syndicates to Account A anyway, violating category rules.

**Why it happens:** Category filtering logic is written but not called during auto-syndication flow. Async handler or syndication service bypasses category check.

**How to avoid:**
1. Extract category check into dedicated method (e.g., `should_syndicate_to_account($post_id, $account_id)`)
2. Call this method BEFORE attempting syndication (both sync and async paths)
3. Write tests covering: no rules set (all posts), include rules (whitelist), exclude rules (blacklist)

**Warning signs:**
- Category rules settings save successfully but don't affect syndication behavior
- All posts syndicate regardless of category configuration
- Account-specific rules only work for some accounts, not others

**Implementation checklist:**
- [ ] Category check function exists and is testable
- [ ] Syndication service calls category check before `schedule_syndication()`
- [ ] Async handler calls category check before `syndicate_post_to_bluesky()`
- [ ] Tests verify: no rules → all posts, include rules → only matching, exclude rules → blocks matching

### Pitfall 5: Global Pause Setting Not Checked Across All Syndication Entry Points

**What goes wrong:** User enables global syndication pause for maintenance. Manual "Syndicate Now" button in posts list still syndicates. Scheduled posts still auto-syndicate. Only editor-triggered syndication is paused.

**Why it happens:** Global pause check is added to one code path (e.g., `transition_post_status` hook) but other entry points (manual retry, scheduled posts, bulk actions) don't check the flag.

**How to avoid:**
1. Check global pause flag in ONE central location that all paths use (e.g., top of `syndicate_post_to_bluesky()`)
2. Document all syndication entry points: transition_post_status, async handler, manual retry, bulk action
3. Test each entry point with pause enabled to verify it's blocked

**Warning signs:**
- Global pause works sometimes but not always
- Manual actions bypass pause
- Async/scheduled jobs ignore pause setting

**Implementation pattern:**
```php
// In BlueSky_Syndication_Service or BlueSky_Async_Handler
public function syndicate_post_to_bluesky($new_status, $old_status, $post) {
    // FIRST CHECK: Global pause
    $options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    if (!empty($options['global_pause'])) {
        return; // All syndication paused
    }

    // SECOND CHECK: Per-post disable
    // THIRD CHECK: Category rules
    // Then proceed...
}
```

**Test checklist:**
- [ ] Editor publish with pause enabled → no syndication
- [ ] Scheduled post with pause enabled → no syndication
- [ ] Manual retry with pause enabled → no syndication
- [ ] Bulk action with pause enabled → no syndication
- [ ] Disable pause → syndication resumes for new publishes (old posts still skip)

## Code Examples

Verified patterns from official sources and existing codebase:

### Registering Editable Syndication Text Meta

```php
// Source: Existing pattern from BlueSky_Post_Metabox::register_post_meta()
// Location: classes/BlueSky_Post_Metabox.php
public function register_post_meta() {
    // Existing meta (keep as-is)
    register_post_meta('post', '_bluesky_dont_syndicate', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'default' => '',
        'auth_callback' => function() { return current_user_can('edit_posts'); }
    ]);

    // NEW: Editable syndication text
    register_post_meta('post', '_bluesky_syndication_text', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'default' => '', // Empty = auto-generate from title + excerpt
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback' => function() { return current_user_can('edit_posts'); }
    ]);
}
```

### Generating Default Syndication Text

```php
// Source: Existing pattern from BlueSky_API_Handler::syndicate_post_to_bluesky()
// Location: classes/BlueSky_API_Handler.php lines 799-843
function generate_default_syndication_text($post_id) {
    $post = get_post($post_id);
    $char_limit = 300;

    // Start with title
    $title_trimmed = wp_trim_words($post->post_title, 20, '...');
    $text = $title_trimmed;

    // Add excerpt if available and space allows
    $excerpt = !empty($post->post_excerpt)
        ? $post->post_excerpt
        : wp_trim_words($post->post_content, 30, '...');

    if (!empty($excerpt)) {
        $excerpt_clean = wp_strip_all_tags($excerpt);
        $excerpt_clean = preg_replace('/\s+/', ' ', $excerpt_clean);

        $permalink = get_permalink($post_id);
        $space_for_excerpt = $char_limit - mb_strlen($text) - mb_strlen($permalink) - 10;

        if ($space_for_excerpt > 50) {
            $excerpt_trimmed = mb_substr($excerpt_clean, 0, $space_for_excerpt);
            $last_space = mb_strrpos($excerpt_trimmed, ' ');
            if ($last_space !== false && $last_space > 30) {
                $excerpt_trimmed = mb_substr($excerpt_trimmed, 0, $last_space);
            }
            $excerpt_trimmed = trim($excerpt_trimmed) . '...';
            $text .= "\n\n" . $excerpt_trimmed;
        }
    }

    // Ensure limit respected
    if (mb_strlen($text) > $char_limit - 10) {
        $text = mb_substr($text, 0, $char_limit - 13) . '...';
    }

    return $text;
}
```

### Character Counter Component (Shared)

```javascript
// NEW FILE: assets/js/bluesky-character-counter.js
// Uses Intl.Segmenter with fallback
(function(window) {
    'use strict';

    /**
     * Count grapheme clusters (user-perceived characters)
     * Matches Bluesky's counting method
     */
    function countGraphemes(text) {
        if (!text) return 0;

        // Modern browser with Intl.Segmenter
        if (typeof Intl !== 'undefined' && Intl.Segmenter) {
            const segmenter = new Intl.Segmenter('en', { granularity: 'grapheme' });
            return Array.from(segmenter.segment(text)).length;
        }

        // Fallback: grapheme-splitter library (if included)
        if (window.GraphemeSplitter) {
            const splitter = new window.GraphemeSplitter();
            return splitter.countGraphemes(text);
        }

        // Last resort: string length (inaccurate for emoji but better than nothing)
        return text.length;
    }

    /**
     * Get character count status
     * @returns {object} { count, max, remaining, isOverLimit, percentage }
     */
    function getCountStatus(text, maxLength) {
        maxLength = maxLength || 300; // Bluesky limit
        const count = countGraphemes(text);

        return {
            count: count,
            max: maxLength,
            remaining: maxLength - count,
            isOverLimit: count > maxLength,
            percentage: Math.round((count / maxLength) * 100)
        };
    }

    // Expose globally
    window.BlueSkyCharacterCounter = {
        countGraphemes: countGraphemes,
        getCountStatus: getCountStatus
    };

})(window);
```

### Editable Text in Pre-Publish Panel

```javascript
// EXTEND: blocks/bluesky-pre-publish-panel.js
// Add after existing account selection rendering
renderEditableSyndicationText() {
    const { meta, updateMeta, postId, title, excerpt } = this.props;
    const customText = meta._bluesky_syndication_text || '';
    const maxLength = 300;

    // Generate default if empty
    const displayText = customText || this.generateDefaultText(title, excerpt);

    // Count characters
    const status = window.BlueSkyCharacterCounter.getCountStatus(displayText, maxLength);

    return wp.element.createElement('div', {
        className: 'bluesky-editable-text',
        style: { marginTop: '12px', paddingTop: '12px', borderTop: '1px solid #ddd' }
    },
        wp.element.createElement('label', {
            style: { display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '13px' }
        }, __('Post text:', 'social-integration-for-bluesky')),

        wp.element.createElement('textarea', {
            value: displayText,
            onChange: (e) => {
                updateMeta({ _bluesky_syndication_text: e.target.value });
            },
            rows: 4,
            style: {
                width: '100%',
                padding: '8px',
                border: status.isOverLimit ? '1px solid #d63638' : '1px solid #ddd',
                borderRadius: '2px',
                fontFamily: 'inherit'
            }
        }),

        wp.element.createElement('div', {
            style: {
                fontSize: '12px',
                color: status.isOverLimit ? '#d63638' : '#757575',
                marginTop: '4px',
                display: 'flex',
                justifyContent: 'space-between'
            }
        },
            wp.element.createElement('span', null,
                status.isOverLimit
                    ? __('Character limit exceeded', 'social-integration-for-bluesky')
                    : __('Character count', 'social-integration-for-bluesky')
            ),
            wp.element.createElement('span', {
                style: { fontWeight: 600 }
            }, `${status.count} / ${maxLength}`)
        )
    );
}

// Helper method
generateDefaultText(title, excerpt) {
    // Match PHP generation logic
    let text = title || '';
    if (excerpt && text.length < 250) {
        text += "\n\n" + excerpt.substring(0, 200);
    }
    return text;
}
```

### Sidebar Panel Component (New)

```javascript
// NEW FILE: blocks/bluesky-sidebar-panel.js
// Registers PluginDocumentSettingPanel for sidebar
(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { Component } = wp.element;
    const { withSelect, withDispatch } = wp.data;
    const { compose } = wp.compose;
    const { __ } = wp.i18n;

    class BlueSkyDocumentPanel extends Component {
        render() {
            const { meta, updateMeta, title, excerpt } = this.props;
            const customText = meta._bluesky_syndication_text || '';
            const maxLength = 300;

            const displayText = customText || this.generateDefaultText(title, excerpt);
            const status = window.BlueSkyCharacterCounter.getCountStatus(displayText, maxLength);

            return wp.element.createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'bluesky-syndication-panel',
                    title: __('Bluesky Syndication', 'social-integration-for-bluesky'),
                    icon: null
                },
                // Same editable text UI as pre-publish panel
                // (can extract to shared component)
                this.renderEditableSyndicationText(displayText, status, updateMeta)
            );
        }

        generateDefaultText(title, excerpt) {
            let text = title || '';
            if (excerpt && text.length < 250) {
                text += "\n\n" + excerpt.substring(0, 200);
            }
            return text;
        }

        renderEditableSyndicationText(displayText, status, updateMeta) {
            // Same implementation as pre-publish panel
            // (see above example)
        }
    }

    const ConnectedPanel = compose([
        withSelect((select) => {
            const editor = select('core/editor');
            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                title: editor.getEditedPostAttribute('title'),
                excerpt: editor.getEditedPostAttribute('excerpt')
            };
        }),
        withDispatch((dispatch) => ({
            updateMeta: (newMeta) => {
                dispatch('core/editor').editPost({ meta: newMeta });
            }
        }))
    ])(BlueSkyDocumentPanel);

    registerPlugin('bluesky-document-panel', {
        render: ConnectedPanel
    });

})(window.wp);
```

### Category Rules UI in Settings

```php
// EXTEND: classes/BlueSky_Settings_Service.php
// Add new settings section for category rules tab
public function render_category_rules_tab() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'account';

    if ($active_tab !== 'category-rules') {
        return; // Only render on category-rules tab
    }

    $account_manager = new BlueSky_Account_Manager();
    $accounts = $account_manager->get_accounts();
    $categories = get_categories(['hide_empty' => false]);

    echo '<div class="bluesky-category-rules">';
    echo '<p>' . __('Configure which categories syndicate to each account. If no rules are set, all categories syndicate.', 'social-integration-for-bluesky') . '</p>';

    foreach ($accounts as $account_id => $account) {
        $include_cats = isset($account['category_rules']['include']) ? $account['category_rules']['include'] : [];
        $exclude_cats = isset($account['category_rules']['exclude']) ? $account['category_rules']['exclude'] : [];

        echo '<div class="account-category-rules" style="margin-bottom: 30px; padding: 15px; border: 1px solid #ccc;">';
        echo '<h3>' . esc_html($account['name']) . ' (@' . esc_html($account['handle']) . ')</h3>';

        // Include categories
        echo '<div style="margin-bottom: 15px;">';
        echo '<label style="display: block; margin-bottom: 8px; font-weight: 600;">';
        echo __('Include categories (OR logic):', 'social-integration-for-bluesky');
        echo '</label>';

        foreach ($categories as $category) {
            $checked = in_array($category->term_id, $include_cats);
            echo '<label style="display: block; margin-bottom: 4px;">';
            echo '<input type="checkbox" name="bluesky_category_rules[' . esc_attr($account_id) . '][include][]" ';
            echo 'value="' . esc_attr($category->term_id) . '" ' . checked($checked, true, false) . '> ';
            echo esc_html($category->name);
            echo '</label>';
        }
        echo '</div>';

        // Exclude categories
        echo '<div>';
        echo '<label style="display: block; margin-bottom: 8px; font-weight: 600;">';
        echo __('Exclude categories:', 'social-integration-for-bluesky');
        echo '</label>';

        foreach ($categories as $category) {
            $checked = in_array($category->term_id, $exclude_cats);
            echo '<label style="display: block; margin-bottom: 4px;">';
            echo '<input type="checkbox" name="bluesky_category_rules[' . esc_attr($account_id) . '][exclude][]" ';
            echo 'value="' . esc_attr($category->term_id) . '" ' . checked($checked, true, false) . '> ';
            echo esc_html($category->name);
            echo '</label>';
        }
        echo '</div>';

        echo '</div>'; // .account-category-rules
    }

    echo '</div>'; // .bluesky-category-rules
}
```

### Category Filtering Check

```php
// EXTEND: classes/BlueSky_Syndication_Service.php
// Add method to check category rules
private function should_syndicate_to_account($post_id, $account_id) {
    $account_manager = new BlueSky_Account_Manager();
    $account = $account_manager->get_account($account_id);

    // No rules = syndicate everything
    if (empty($account['category_rules']['include']) && empty($account['category_rules']['exclude'])) {
        return true;
    }

    // Get post categories
    $post_categories = get_the_category($post_id);
    if (empty($post_categories)) {
        // Post has no categories
        // If include rules exist, don't syndicate (no matching categories)
        // If only exclude rules exist, syndicate (nothing to exclude)
        return empty($account['category_rules']['include']);
    }

    $post_category_ids = array_map(function($cat) { return $cat->term_id; }, $post_categories);

    // Check exclude rules first (higher priority)
    if (!empty($account['category_rules']['exclude'])) {
        if (array_intersect($post_category_ids, $account['category_rules']['exclude'])) {
            return false; // Post has excluded category
        }
    }

    // Check include rules (OR logic)
    if (!empty($account['category_rules']['include'])) {
        if (array_intersect($post_category_ids, $account['category_rules']['include'])) {
            return true; // Post has at least one included category
        }
        return false; // Post doesn't match any included categories
    }

    return true; // No rules blocking it
}

// Use in syndicate_post_multi_account()
foreach ($selected_account_ids as $account_id) {
    // Category check BEFORE syndication attempt
    if (!$this->should_syndicate_to_account($post_id, $account_id)) {
        continue; // Skip this account
    }

    // Proceed with syndication...
}
```

### Global Pause Check

```php
// EXTEND: classes/BlueSky_Syndication_Service.php
// Add at start of syndicate_post_to_bluesky()
public function syndicate_post_to_bluesky($new_status, $old_status, $post) {
    // FIRST CHECK: Global pause
    $options = get_option(BLUESKY_PLUGIN_OPTIONS, []);
    if (!empty($options['global_pause'])) {
        return; // All syndication paused
    }

    // Existing checks follow...
    if ('publish' === $new_status && 'publish' !== $old_status && 'post' === $post->post_type) {
        // Continue with syndication...
    }
}
```

### Auto-Post Preference in Account Setup

```php
// EXTEND: classes/BlueSky_Account_Manager.php
// Modify account data structure
private $default_account_structure = [
    'id' => '',           // UUID
    'name' => '',         // Display name
    'handle' => '',       // @username.bsky.social
    'app_password' => '', // Encrypted
    'created_at' => 0,    // Timestamp
    'status' => 'active', // active|expired|error
    'auto_syndicate' => true, // NEW: Auto-syndicate new posts
    'category_rules' => [     // NEW: Category filtering
        'include' => [],      // Array of category term_ids
        'exclude' => []       // Array of category term_ids
    ]
];

// Update add_account() to ask for preference
public function add_account($handle, $app_password, $name = '', $auto_syndicate = true) {
    // Existing validation...

    $account_data = [
        'id' => $this->generate_uuid(),
        'name' => $name ?: $handle,
        'handle' => $handle,
        'app_password' => $this->encrypt_password($app_password),
        'created_at' => time(),
        'status' => 'active',
        'auto_syndicate' => (bool) $auto_syndicate, // User choice
        'category_rules' => ['include' => [], 'exclude' => []]
    ];

    // Save account...
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Meta boxes for post settings | PluginDocumentSettingPanel (Gutenberg sidebar) | WordPress 5.0 (2018) | Better UX integration, reactive updates, consistent styling |
| Custom AJAX for meta save | REST API with show_in_rest | WordPress 4.7+ (2016) | Automatic permissions, conflict resolution, caching |
| String.length for counting | Intl.Segmenter API for grapheme clusters | ECMAScript 2022 | Accurate emoji/Unicode counting, matches Bluesky API |
| PHP-rendered settings tabs | WordPress nav-tab CSS classes | WordPress 3.0+ (2010) | Built-in styles, no custom JS needed, accessible |

**Deprecated/outdated:**
- **Meta boxes for Gutenberg settings:** Still work but look inconsistent. WordPress recommends PluginDocumentSettingPanel for block editor integration. Source: [Block Editor Handbook - Meta Boxes](https://developer.wordpress.org/block-editor/how-to-guides/metabox/)
- **Custom character counters using regex:** Unicode has evolved significantly. Hand-rolled solutions miss edge cases. Use Intl.Segmenter or battle-tested libraries. Source: [UAX #29 Unicode Text Segmentation](https://unicode.org/reports/tr29/)
- **Manual DOM manipulation in Gutenberg:** React-based editor re-renders frequently. jQuery/vanilla JS breaks on re-render. Use withSelect/withDispatch for state management. Source: [Gutenberg Data Module](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/)

## Open Questions

### 1. Intl.Segmenter Browser Support

**What we know:** Intl.Segmenter is supported in Chrome 87+, Edge 87+, Safari 14.1+, Firefox 125+ (as of 2025). Covers ~95% of users on modern browsers.

**What's unclear:** Should we bundle grapheme-splitter polyfill for older browsers, or accept fallback to string.length with warning?

**Recommendation:** Bundle grapheme-splitter polyfill (1.8KB gzipped) for universal support. Character counting is critical for Bluesky API validation. Fallback to string.length creates bad UX (users hit API errors when counter says they're under limit). Cost is minimal, benefit is high reliability.

### 2. Default Auto-Syndicate State for New Accounts

**What we know:** User decision from CONTEXT.md: "New accounts: ask user to choose auto-post preference during account setup"

**What's unclear:** What should the default checkbox state be when adding account? Checked (opt-out) or unchecked (opt-in)?

**Recommendation:** Default to checked (auto-syndicate enabled). Rationale: Most users adding a Bluesky account want to syndicate posts. Opt-out is safer than opt-in (users are more likely to notice unwanted syndication than missing syndication). Provide clear label: "Auto-syndicate new posts to this account" with checkbox.

### 3. Category Rules Save Mechanism

**What we know:** Category rules stored per-account in Account Manager data structure. Settings page has dedicated tab.

**What's unclear:** Should category rules save with main settings form, or separate AJAX save per account?

**Recommendation:** Separate save mechanism per account. Rationale: Each account's rules are independent. User may want to update one account without touching others. Provide "Save Rules" button per account section. Use AJAX to avoid full page reload. Show confirmation message: "Category rules saved for [Account Name]."

### 4. Handling Scheduled Posts with Category Rules

**What we know:** Scheduled posts auto-syndicate when WordPress publishes them (future->publish transition). Category rules apply.

**What's unclear:** If user schedules post in "Blog" category, then changes category to "News" before publish time, which category rules apply?

**Recommendation:** Apply category rules at syndication time (when post publishes), not at schedule time. Rationale: Categories may change during draft period. User expects current state to determine syndication. Implementation: Check categories in `transition_post_status` hook (when $new_status === 'publish'), not when scheduling.

## Sources

### Primary (HIGH confidence)

- **WordPress Developer Reference - register_post_meta():** https://developer.wordpress.org/reference/functions/register_post_meta/
- **WordPress Developer Reference - get_categories():** https://developer.wordpress.org/reference/functions/get_categories/
- **WordPress Developer Reference - transition_post_status hook:** https://developer.wordpress.org/reference/hooks/transition_post_status/
- **WordPress Block Editor Handbook - PluginDocumentSettingPanel:** https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-document-setting-panel/
- **WordPress Block Editor Handbook - Meta Boxes:** https://developer.wordpress.org/block-editor/how-to-guides/metabox/
- **MDN Web Docs - Intl.Segmenter:** https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/Segmenter
- **Bluesky API - Character Limit Issue (#3244):** https://github.com/bluesky-social/social-app/issues/3244
- **Bluesky API - Grapheme Validation Issue (#427):** https://github.com/MarshalX/atproto/issues/427

### Secondary (MEDIUM confidence)

- **Envato Tuts+ - WordPress Settings API Tabbed Navigation:** https://code.tutsplus.com/the-wordpress-settings-api-part-5-tabbed-navigation-for-settings--wp-24971t (verified with WordPress Codex)
- **Rudrastyh - Creating Tabs in Settings Pages:** https://rudrastyh.com/gutenberg/plugin-sidebars.html (verified with official docs)
- **Jeffrey Carandang - Post Meta in Gutenberg:** https://jeffreycarandang.com/2023/06/02/how-to-enable-read-and-update-post-meta-in-the-wordpress-block-editor-gutenberg/ (verified with official docs)
- **rtCamp Handbook - Custom Sidebar Panels:** https://rtcamp.com/handbook/developing-for-block-editor-and-site-editor/custom-sidebar-panels/ (verified with official docs)

### Tertiary (LOW confidence)

- **GitHub - grapheme-splitter library:** https://github.com/orling/grapheme-splitter (popular library, 1M+ weekly downloads, but not official spec)
- **Smashing Magazine - WordPress Settings Tabs:** https://www.smashingmagazine.com/2011/10/create-tabs-wordpress-settings-pages/ (dated 2011, pattern still valid but verify with current docs)

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH - All WordPress core APIs with official documentation, existing patterns in codebase verified
- **Architecture:** HIGH - Patterns extracted from official WordPress/Gutenberg docs and existing codebase implementation
- **Pitfalls:** MEDIUM-HIGH - Based on common WordPress development issues, Bluesky API constraints documented in GitHub issues, and existing code analysis. Some pitfalls inferred from API behavior rather than explicit documentation.

**Research date:** 2026-02-21
**Valid until:** 60 days (2026-04-22) - WordPress/Gutenberg APIs are stable, Bluesky API character limit is spec-defined and unlikely to change
