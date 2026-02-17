# Phase 1 Context: Multi-Account Foundation

## User Vision & Corrections

These corrections override assumptions from the original research-based plans (01-01 through 01-05). The phase must be re-planned incorporating all of these.

### 1. Multi-Account as Opt-In Feature

**NOT auto-migration for everyone.** Multi-account is an optional feature toggle:

- **Default mode:** Single-account, identical to current UX. No migration, no new UI complexity.
- **Opt-in:** User enables "Multi-Account" in settings.
- **Only when enabled:** Migration runs, multi-account UI appears (add/remove accounts, per-post selection, display switching).
- **Rationale:** Keeps it simple for the majority of users who only need one account. No migration risk for them.

### 2. Syndication Must Be Multi-Account (Not Just Selection UI)

The original plans only built the account *selection* UI but didn't update the actual syndication handler. This phase must include:

- Updating `syndicate_post_to_bluesky()` in `BlueSky_Plugin_Setup.php` to loop over `_bluesky_syndication_accounts` post meta
- Creating per-account API handler instances to syndicate to each selected account
- Auto-syndication toggle per account (which accounts auto-syndicate on publish)
- Storing per-account syndication results (see Discussion Thread section below)

### 3. Discussion Thread Evolution

**Current state:** When a post is syndicated, `_bluesky_syndication_bs_post_info` stores the Bluesky post URI. The Discussion display (BlueSky_Discussion_Display class) fetches replies from that URI and shows them near the WP comment section.

**Multi-account evolution:**
- Post syndicated to N accounts = N Bluesky post URIs
- `_bluesky_syndication_bs_post_info` must evolve from a single JSON blob to an **account-keyed structure** (e.g., `{account_uuid: {uri, cid, ...}, account_uuid2: {...}}`)
- Settings page gets a dropdown: "Show discussion thread from: [Account dropdown]" — pick which account's Bluesky thread powers the Discussion display
- Only one discussion thread shown at a time (single account selectable, not multiple)
- This is a global default setting; per-post override can come later

### 4. Settings Page UX

**Keep the existing one-page tab approach.** The user wants a seamless experience:
- All settings on one WordPress admin page with tabs (not separate admin pages)
- Clean separation between account management and plugin settings within tabs
- When multi-account is disabled: simple single-account UI (current behavior)
- When multi-account is enabled: account management section appears

### 5. Existing Async System Must Be Preserved and Extended

The plugin already has an async loading system that must be accounted for:

**How it works:**
- PHP renders skeleton placeholders with `data-bluesky-async` attributes and `data-bluesky-params` (JSON-encoded render parameters)
- `bluesky-async-loader.js` finds these on DOMContentLoaded and fires AJAX calls
- AJAX handlers (`ajax_async_posts`, `ajax_async_profile`, `ajax_async_auth` in BlueSky_Plugin_Setup.php) do the actual API calls and return rendered HTML
- Skeleton is replaced with real content

**What multi-account changes need:**
- Thread `account_id` through the async pipeline — add it to `data-bluesky-params` JSON
- AJAX handlers must accept and use `account_id` parameter
- Auth check async handler needs to work per-account when multi-account is enabled

### 6. Auto-Syndication Per Account

When multi-account is enabled:
- Each account has an auto-syndication toggle (on/off)
- On post publish, only accounts with auto-syndication ON are pre-selected
- User can still override per-post in the editor (add/remove accounts from selection)

## Technical Context

### Key Files for Syndication
- `BlueSky_Plugin_Setup.php` line ~2365: `syndicate_post_to_bluesky()` — entry point, fires on `transition_post_status`
- `BlueSky_API_Handler.php` line ~552: `syndicate_post_to_bluesky()` — actually calls Bluesky API to create post
- `BlueSky_Post_Metabox.php`: "Don't syndicate" checkbox and (future) account selection

### Key Files for Async System
- `assets/js/bluesky-async-loader.js`: Client-side skeleton replacement
- `BlueSky_Plugin_Setup.php` lines 98-118: AJAX action registration
- `BlueSky_Plugin_Setup.php` lines 2262-2334: AJAX handlers (`ajax_async_posts`, `ajax_async_profile`, `ajax_async_auth`)
- `BlueSky_Render_Front.php` lines 1173-1226: Skeleton placeholder rendering

### Key Files for Discussion
- `BlueSky_Discussion_Display.php` (~1337 lines): Fetches and renders Bluesky thread replies
- Post meta `_bluesky_syndication_bs_post_info`: Currently single JSON blob with Bluesky post URI

### Existing Data Model
- `bluesky_settings` option: Single account credentials (handle, app_password, did) + all plugin settings mixed together
- `_bluesky_syndicated` post meta: Boolean flag
- `_bluesky_dont_syndicate` post meta: Per-post syndication opt-out
- `_bluesky_syndication_bs_post_info` post meta: Bluesky post response (URI, etc.)

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| Multi-account is opt-in, not default | Keeps it simple for single-account users, no migration risk |
| Migration only runs when multi-account enabled | Avoids data model changes for users who don't need it |
| Syndication execution included in Phase 1 | Users who connect multiple accounts expect to syndicate to all of them |
| Discussion thread: one account selectable | Simpler UX, showing multiple threads would be confusing |
| Discussion account is global setting | Per-post override deferred to later |
| Settings stay as one-page with tabs | Seamless experience, consistent with current UX |
| Auto-syndication toggle per account | Gives control over which accounts auto-post on publish |

---
*Context gathered: 2026-02-16*
