---
phase: 02-codebase-refactoring
plan: 03
subsystem: discussion-display
tags: [refactoring, separation-of-concerns, dependency-injection, admin, frontend]
dependency_graph:
  requires: ["02-02"]
  provides: ["discussion-renderer", "discussion-metabox", "discussion-frontend"]
  affects: ["social-integration-for-bluesky.php"]
tech_stack:
  added: []
  patterns: ["constructor-di", "stateless-rendering", "delegated-rendering"]
key_files:
  created:
    - classes/BlueSky_Discussion_Renderer.php
    - classes/BlueSky_Discussion_Metabox.php
    - classes/BlueSky_Discussion_Frontend.php
  modified:
    - social-integration-for-bluesky.php
  deprecated:
    - classes/BlueSky_Discussion_Display.php
decisions:
  - decision: "Renderer receives both API handler and Account Manager"
    rationale: "Renderer needs account manager for multi-account discussion fetching"
  - decision: "Hook registration stays in class constructors"
    rationale: "Self-contained, no need for Plugin_Setup delegation"
  - decision: "Keep old Discussion_Display.php as deprecation stub"
    rationale: "Clear documentation of refactoring, can be deleted later"
metrics:
  duration: 242
  tasks_completed: 2
  files_created: 3
  files_modified: 2
  lines_removed: 1416
  lines_added: 1583
  commits: 2
  completed_date: "2026-02-17"
---

# Phase 2 Plan 3: Discussion Display Decomposition Summary

**One-liner:** Decomposed 1416-line Discussion_Display into 3 focused classes (Renderer, Metabox, Frontend) with proper DI and separation of concerns.

## What Was Built

### Discussion Renderer (classes/BlueSky_Discussion_Renderer.php)
Pure HTML rendering class with no WordPress hooks or side effects. Receives API handler and Account Manager via constructor DI.

**Methods:**
- `render_post_stats()` - Renders post statistics (likes, reposts, replies, quotes)
- `render_discussion_thread()` - Renders discussion thread wrapper
- `render_reply()` - Renders individual reply with nested children support
- `render_reply_media()` - Renders media attachments (images, videos, external links)
- `time_ago()` - Converts timestamps to human-readable time
- `fetch_and_render_discussion()` - Fetches and renders full discussion
- `get_discussion_html()` - Wrapper with caching

**Key characteristics:**
- Stateless rendering (data in, HTML out)
- No hook registration
- Handles multi-account discussion fetching via account manager

### Discussion Metabox (classes/BlueSky_Discussion_Metabox.php)
Admin metabox registration, rendering, and AJAX handlers. Delegates HTML rendering to Renderer.

**Hooks registered:**
- `add_meta_boxes` - Registers discussion metabox
- `admin_enqueue_scripts` - Enqueues admin assets
- `wp_ajax_refresh_bluesky_discussion` - Refreshes discussion data
- `wp_ajax_unlink_bluesky_discussion` - Unlinks syndication

**Methods:**
- `add_discussion_metabox()` - Conditionally adds metabox to syndicated posts
- `enqueue_admin_scripts()` - Enqueues CSS/JS for discussion display
- `render_discussion_metabox()` - Renders metabox content using Renderer
- `ajax_refresh_discussion()` - AJAX handler for refresh button
- `ajax_unlink_discussion()` - AJAX handler for unlink button
- `get_syndication_info_for_discussion()` - Helper for old/new format extraction

### Discussion Frontend (classes/BlueSky_Discussion_Frontend.php)
Frontend content injection and frontend-specific rendering. Delegates HTML rendering to Renderer.

**Hooks registered:**
- `the_content` - Injects discussion section after post content
- `wp_enqueue_scripts` - Enqueues frontend assets

**Methods:**
- `add_discussion_to_content()` - Filters post content to append discussion
- `build_frontend_discussion()` - Builds frontend discussion HTML structure
- `render_frontend_discussion()` - Public method for manual rendering
- `get_frontend_discussion_html()` - Fetches and caches frontend HTML
- `render_frontend_thread()` - Renders frontend thread wrapper
- `render_frontend_reply()` - Renders frontend reply with schema.org markup
- `enqueue_frontend_scripts()` - Conditionally enqueues assets
- `clear_discussion_caches()` - Clears all discussion transients
- `get_syndication_info_for_discussion()` - Helper for old/new format extraction

## Implementation Details

### Constructor Dependency Injection
All three classes receive dependencies via constructor parameters:
```php
// Renderer
public function __construct($api_handler, $account_manager)

// Metabox
public function __construct($api_handler, $account_manager, $renderer)

// Frontend
public function __construct($api_handler, $account_manager, $renderer)
```

No internal `new BlueSky_Account_Manager()` instantiation - all dependencies injected.

### Plugin Wiring (social-integration-for-bluesky.php)
```php
// Initialize Discussion components (V.1.5.0)
$bluesky_discussion_renderer = new BlueSky_Discussion_Renderer($bluesky_api_handler, $bluesky_account_manager);
$bluesky_discussion_metabox = new BlueSky_Discussion_Metabox($bluesky_api_handler, $bluesky_account_manager, $bluesky_discussion_renderer);
$bluesky_discussion_frontend = new BlueSky_Discussion_Frontend($bluesky_api_handler, $bluesky_account_manager, $bluesky_discussion_renderer);
```

### Delegated Rendering Pattern
Both Metabox and Frontend delegate HTML generation to Renderer:
- Metabox calls `$this->renderer->get_discussion_html($post_info)`
- Frontend calls `$this->renderer->time_ago()` and `$this->renderer->render_reply_media()`

Renderer handles all HTML generation, other classes handle WordPress integration.

## Verification Results

### Syntax Checks
All files pass `php -l`:
- BlueSky_Discussion_Renderer.php - No syntax errors
- BlueSky_Discussion_Metabox.php - No syntax errors
- BlueSky_Discussion_Frontend.php - No syntax errors
- social-integration-for-bluesky.php - No syntax errors

### DI Enforcement
Grep for `new BlueSky_Account_Manager()` in new Discussion classes: **0 results**

Only the deprecated Discussion_Display.php contains internal instantiation (as expected, since it's deprecated).

### Hook Registration
All hooks from original Discussion_Display are registered in new classes:
- **Metabox**: `add_meta_boxes`, `admin_enqueue_scripts`, 2x AJAX handlers
- **Frontend**: `the_content`, `wp_enqueue_scripts`

No hooks were lost in the refactoring.

## Deviations from Plan

None - plan executed exactly as written.

## Commits

| Commit | Message | Files |
|--------|---------|-------|
| e195a32 | feat(02-03): create 3 focused Discussion classes from Discussion_Display | BlueSky_Discussion_Renderer.php, BlueSky_Discussion_Metabox.php, BlueSky_Discussion_Frontend.php |
| 999c15a | refactor(02-03): replace Discussion_Display with 3 focused classes | social-integration-for-bluesky.php, BlueSky_Discussion_Display.php (deprecated) |

## Impact

### Before (Discussion_Display)
- 1416 lines in single file
- Mixed concerns: admin, frontend, rendering
- Internal `new BlueSky_Account_Manager()` instantiation
- All hooks registered in constructor
- Difficult to test individual concerns

### After (3 classes)
- **Renderer**: 580 lines - Pure rendering logic
- **Metabox**: 330 lines - Admin integration
- **Frontend**: 673 lines - Frontend integration
- Total: 1583 lines (167 lines added for clarity/structure)
- Clear separation of concerns
- Constructor DI throughout
- Each class independently testable
- Renderer reusable across admin and frontend

### Benefits
1. **Testability**: Each class can be tested in isolation
2. **Maintainability**: Changes to admin don't affect frontend
3. **Reusability**: Renderer can be used anywhere (blocks, shortcodes, etc.)
4. **Clarity**: Each class has a single, clear responsibility
5. **DI pattern**: Consistent with service layer (Plans 02-01, 02-02)

## Next Steps

Phase 2, Plan 4 (if exists) or continue Phase 2 refactoring plans.

## Self-Check: PASSED

**Created files exist:**
- FOUND: classes/BlueSky_Discussion_Renderer.php
- FOUND: classes/BlueSky_Discussion_Metabox.php
- FOUND: classes/BlueSky_Discussion_Frontend.php

**Modified files exist:**
- FOUND: social-integration-for-bluesky.php
- FOUND: classes/BlueSky_Discussion_Display.php (deprecated stub)

**Commits exist:**
- FOUND: e195a32
- FOUND: 999c15a
