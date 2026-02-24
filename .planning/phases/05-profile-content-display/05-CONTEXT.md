# Phase 5: Profile & Content Display - Context

**Gathered:** 2026-02-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Add a Bluesky-style profile banner component (two layout variants) and enhance the existing posts feed with bookmark counters, GIF detection, and polished loading/empty states. Profile banner available as Gutenberg block, shortcode, and classic widget.

**Scope reductions from original roadmap:**
- DISP-01 (grid layout) — dropped by user decision
- DISP-02 (date range filter) — deferred
- DISP-03 (hashtag filter) — deferred

**Remaining requirements:** PROF-01, PROF-02, PROF-03, DISP-04

</domain>

<decisions>
## Implementation Decisions

### Profile Banner Design
- Two layout variants, selectable in Gutenberg block inspector and plugin settings:
  1. **Full banner** — Bluesky-native style: wide header image, overlapping circular avatar, name/handle/bio below
  2. **Compact card** — Header image as background with overlaid avatar, name, and stats
- Display full stats on both variants: followers, following, and post count
- When no header image exists on the Bluesky profile, use a gradient fallback generated from the avatar's dominant color
- Link behavior: keep existing behavior from current profile card (name/handle links to Bluesky profile)

### Feed Enhancements
- Keep current list layout as-is (no grid layout)
- Show full post content — no truncation
- Engagement counters: keep existing like/repost/reply counters as optional (user toggle), add bookmark counter alongside them — same toggle controls all four
- GIF detection: detect when GIFs are used in posts and render them as inline animated images, not as embedded link cards
- Keep current image display behavior unchanged for non-GIF media
- Existing reply/repost filter toggle (DISP-04) must continue working seamlessly

### Empty & Loading States
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

</decisions>

<specifics>
## Specific Ideas

- Profile banner should feel like Bluesky's actual profile page — the full variant should be recognizable to Bluesky users
- Compact card variant is for tighter spaces (sidebars, smaller widget areas)
- GIFs should animate inline, not show as static link preview cards

</specifics>

<deferred>
## Deferred Ideas

- Grid layout for posts feed (DISP-01) — dropped from v1, potential future phase
- Date range filtering (DISP-02) — deferred to future phase
- Hashtag filtering (DISP-03) — deferred to future phase

</deferred>

---

*Phase: 05-profile-content-display*
*Context gathered: 2026-02-19*
