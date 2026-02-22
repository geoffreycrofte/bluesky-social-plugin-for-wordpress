---
phase: 06-advanced-syndication
plan: 01
subsystem: syndication
tags: [gutenberg, post-meta, character-counting, ui]
dependency_graph:
  requires: [05-04]
  provides: [editable-syndication-text, grapheme-counter]
  affects: [syndication-service]
tech_stack:
  added: [Intl.Segmenter API, PluginDocumentSettingPanel]
  patterns: [grapheme-clustering, progressive-enhancement]
key_files:
  created:
    - assets/js/bluesky-character-counter.js
    - blocks/bluesky-sidebar-panel.js
  modified:
    - classes/BlueSky_Post_Metabox.php
    - classes/BlueSky_Assets_Service.php
decisions:
  - Empty syndication text triggers auto-generation from title + excerpt at syndication time
  - Character counter uses Intl.Segmenter with length fallback for browser compatibility
  - Sidebar panel hidden when syndication disabled (respects _bluesky_dont_syndicate)
  - Preview shown for auto-generated text when meta field is empty
  - Reset button only appears when custom text is set
metrics:
  duration: 2m 18s
  tasks_completed: 2
  files_created: 2
  files_modified: 2
  commits: 2
  completed_at: 2026-02-22T10:17:20Z
---

# Phase 6 Plan 1: Editable Syndication Text Summary

**One-liner:** Gutenberg sidebar panel for editing Bluesky post text with accurate grapheme cluster counting using Intl.Segmenter API.

## What Was Built

Added capability for users to customize the text that accompanies their syndicated Bluesky posts directly from the Gutenberg editor, with accurate character counting that matches Bluesky's 300 grapheme limit.

### Components Delivered

1. **Post Meta Registration** (`BlueSky_Post_Metabox.php`)
   - Registered `_bluesky_syndication_text` with REST API visibility
   - Sanitization via `sanitize_textarea_field`
   - Empty default (triggers auto-generation at syndication time)

2. **Character Counter Utility** (`bluesky-character-counter.js`)
   - Primary: `Intl.Segmenter` API for accurate grapheme cluster counting
   - Fallback: `text.length` for older browsers
   - `countGraphemes(text)` function
   - `getCountStatus(text, maxLength)` returning count metadata
   - Global `window.BlueSkyCharacterCounter` exposure

3. **Gutenberg Sidebar Panel** (`bluesky-sidebar-panel.js`)
   - `PluginDocumentSettingPanel` in document settings
   - Editable textarea for custom syndication text
   - Live character count display (red when over 300)
   - Auto-generated text preview when meta is empty
   - "Reset to default" button for custom text
   - Hidden when `_bluesky_dont_syndicate` is enabled

4. **Script Enqueuing** (`BlueSky_Assets_Service.php`)
   - Character counter loaded before sidebar panel
   - Dependencies: wp-plugins, wp-edit-post, wp-element, wp-data, wp-compose, wp-i18n
   - Only loads in Gutenberg editor on post screens

## Technical Decisions

### Grapheme Cluster Counting

**Decision:** Use `Intl.Segmenter` API as primary method with `text.length` fallback.

**Rationale:** Bluesky counts graphemes, not characters. Emojis like 👨‍👩‍👧‍👦 (family emoji) are 1 grapheme but 11 characters. `Intl.Segmenter` provides accurate counting matching Bluesky's behavior.

**Browser Support:** Modern browsers (Chrome 87+, Safari 14.1+, Firefox 125+). Fallback degrades gracefully for older browsers.

### Empty Text = Auto-Generated

**Decision:** Empty `_bluesky_syndication_text` triggers auto-generation from title + excerpt at syndication time.

**Rationale:** Most users want default behavior. Empty meta is simpler than storing a "use default" flag. Preview shown in sidebar for clarity.

### Progressive Disclosure

**Decision:** Panel hidden when syndication disabled, preview shown when using default.

**Rationale:** Reduces UI clutter. Only show controls relevant to current state.

## Deviations from Plan

None - plan executed exactly as written.

## Integration Points

### Upstream Dependencies
- **Phase 5 Complete:** Post editor environment ready
- **Existing Meta Registration:** Pattern established in `BlueSky_Post_Metabox.php`
- **Assets Service:** Enqueuing pattern from pre-publish panel

### Downstream Impact
- **Syndication Service:** Will read `_bluesky_syndication_text` meta (next plan)
- **Pre-Publish Panel:** Could show character count preview (future enhancement)
- **Multi-Account:** Custom text applies to all selected accounts

## Testing Notes

### Manual Verification Performed
1. ✅ Post meta registered with `show_in_rest: true`
2. ✅ Character counter file exists with required functions
3. ✅ Sidebar panel file exists with PluginDocumentSettingPanel
4. ✅ Scripts enqueued in Assets_Service with correct dependencies

### Key Test Scenarios (For Future E2E)
- [ ] Sidebar panel appears in document settings when syndication enabled
- [ ] Character count updates live as user types
- [ ] Count turns red when over 300 characters
- [ ] Emoji counting accurate (e.g., 👨‍👩‍👧‍👦 counts as 1)
- [ ] Preview shows auto-generated text when meta empty
- [ ] Reset button clears custom text back to empty
- [ ] Panel hidden when "Don't syndicate" checked
- [ ] Custom text persists across editor sessions

## Next Steps

**Immediate (06-02):** Update `BlueSky_Syndication_Service` to read `_bluesky_syndication_text` meta and use custom text when available, fallback to auto-generation when empty.

**Future Enhancements:**
- Add character count to pre-publish panel
- Show warning when custom text is over limit
- Template placeholders (e.g., `{title}`, `{excerpt}`, `{url}`)
- Per-account custom text (advanced use case)

## Files Changed

### Created
- `assets/js/bluesky-character-counter.js` (64 lines) - Grapheme counting utility
- `blocks/bluesky-sidebar-panel.js` (221 lines) - Gutenberg sidebar panel

### Modified
- `classes/BlueSky_Post_Metabox.php` (+11 lines) - Meta registration
- `classes/BlueSky_Assets_Service.php` (+42 lines) - Script enqueuing

## Commits

1. **4791e24** - `feat(06-01): register syndication text post meta and character counter`
   - Post meta registration
   - Character counter utility

2. **5692f47** - `feat(06-01): add Gutenberg sidebar panel for syndication text editing`
   - Sidebar panel component
   - Script enqueuing

## Self-Check: PASSED

✅ All created files exist:
- `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/assets/js/bluesky-character-counter.js`
- `/Users/CRG/Local Sites/wordpress/app/public/wp-content/plugins/bluesky-social-plugin-for-wordpress/blocks/bluesky-sidebar-panel.js`

✅ All commits exist:
- `4791e24` - Task 1 commit
- `5692f47` - Task 2 commit

✅ All verification criteria met:
- `_bluesky_syndication_text` registered with `show_in_rest: true`
- Character counter contains `countGraphemes`, `getCountStatus`, `BlueSkyCharacterCounter`
- Sidebar panel contains `PluginDocumentSettingPanel`, `_bluesky_syndication_text`
- Assets service enqueues both scripts with correct dependencies
