# Phase 4: Error Handling & UX - Context

**Gathered:** 2026-02-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Transform raw Bluesky API errors into user-friendly messages with actionable guidance, and provide visible plugin health status across three locations (dashboard widget, settings page, WP Site Health). Covers error display, re-authentication prompts, rate limit communication, and health monitoring. Does not add new API capabilities or change syndication behavior.

</domain>

<decisions>
## Implementation Decisions

### Error message style
- Errors appear in two places: inline contextual (next to the action that failed) AND persistent admin notices for critical issues needing attention across pages
- Friendly & plain tone throughout: "We couldn't post to Bluesky right now. Your credentials may have expired." — approachable, no jargon
- Action links (e.g., "Go to Settings" button) included only for critical errors like auth failures and config issues; transient errors (rate limits, timeouts) just describe the situation
- No visible severity levels — all errors look the same visually; message content itself conveys urgency; no color-coded red/yellow/blue distinction

### Health dashboard design
- Three locations: WP admin dashboard widget, plugin settings page (rework existing cache status area in last tab), and WP Site Health integration
- Dashboard widget shows full summary: account statuses, last syndication time/result, API health (circuit breaker state), cache status, and pending retries
- Dashboard widget uses manual refresh button (no auto-refresh via Heartbeat)
- WP Site Health integration includes pass/fail status checks ("Bluesky connection active", "API responding", "Credentials valid") plus a debug info section with API version, rate limit state, cache stats, account count

### Re-auth & recovery flows
- Expired credentials trigger a persistent admin notice banner with link to settings page — no inline re-auth forms
- Multiple broken accounts grouped into a single notice: "Accounts X and Y need re-authentication" — not one notice per account
- Notices are dismissible but return after 24 hours if the issue persists
- Partial syndication failures show per-account status list on the post edit screen: "Account A: Posted successfully. Account B: Failed — expired credentials."

### Retry & timing display
- Rate-limited/retrying posts show simple status text: "Rate limited — will retry automatically." No countdown timers or estimated times
- Circuit breaker open state visible to users with friendly explanation: "Bluesky requests paused due to repeated errors. Will resume automatically."
- Recent activity log (last 5-10 syndication events across posts) shown in the health widget for diagnosing recurring issues

### Claude's Discretion
- Retry button timing: whether to show immediately or only after auto-retries exhaust (based on Phase 3 async handler behavior)
- Exact wording of error messages for each error type
- Layout and styling of the dashboard widget and per-account status list
- How to structure the Site Health debug info section
- Activity log data structure and storage approach

</decisions>

<specifics>
## Specific Ideas

- Rework the existing cache status area in the last settings tab into the health section (don't add a new tab, enhance what's there)
- Use WP Site Health best practices for plugin integration (proper test registration, debug info sections)
- Grouped notice pattern for multi-account issues reduces visual noise in admin

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 04-error-handling-ux*
*Context gathered: 2026-02-19*
