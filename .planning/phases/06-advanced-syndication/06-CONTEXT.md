# Phase 6: Advanced Syndication - Context

**Gathered:** 2026-02-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Configure advanced syndication controls: editable post text with character counting, per-post sidebar + pre-publish controls, category-based routing rules per account, per-account auto-post toggles, and a global syndication pause. The existing post format (rich card) stays unchanged — no format switching.

</domain>

<decisions>
## Implementation Decisions

### Post format & content
- Post format stays as-is (rich card) — no format type selector needed
- Post text becomes editable per-post, with auto-generated default from title + excerpt
- Textarea with live character count (Bluesky 300 char limit)
- Rich card image: featured image preferred, fallback to first image in post content
- Don't redo existing working functionality — build on top of it

### Per-post overrides
- Controls live in BOTH sidebar panel AND pre-publish checks panel
- Sidebar panel for editing while writing, pre-publish for final confirmation
- Editable post text field with character counter in both locations
- Existing per-post syndication toggle stays as-is (no changes)
- Existing per-post account selection stays as-is (no changes)

### Category-based rules
- Include/exclude per account — each account has its own category filter
- OR logic: if ANY matching category is included, the post gets syndicated
- Categories only (not tags or custom taxonomies)
- Dedicated rules tab in settings — shows all accounts and their category mappings in one view
- When no rules are set for an account, all categories are included (default = syndicate everything)

### Auto-post behavior
- Scheduled posts auto-syndicate when WordPress publishes them at the scheduled time
- Never re-syndicate on post edits — once syndicated, WP edits don't touch Bluesky
- New accounts: ask user to choose auto-post preference during account setup
- Global pause toggle in settings to pause all syndication across all accounts (maintenance mode)

</decisions>

<specifics>
## Specific Ideas

- Many syndication controls already exist from earlier phases (per-post toggle, account selection, pre-publish preview, async syndication with retry). This phase adds the editable text, category rules, and global controls on top.
- The 300-character limit textarea should feel natural — similar to Twitter/Bluesky's own compose experience with a live counter.
- Category rules tab should show a clear visual mapping: account name + included/excluded categories.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 06-advanced-syndication*
*Context gathered: 2026-02-21*
