# Phase 3: Performance & Resilience - Context

**Gathered:** 2026-02-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the plugin handle API failures gracefully with async syndication, request deduplication, rate limit handling, and circuit breaker protection. This phase covers backend resilience infrastructure — no new user-facing features, but users benefit from non-blocking publish, stale-cache fallbacks, and automatic recovery from API outages.

</domain>

<decisions>
## Implementation Decisions

### Async Syndication Behavior
- Post publishes instantly, then a non-blocking admin notice says "Syndicating to Bluesky..." that updates when done
- Auto-retry up to 3 times with increasing delay on failure, then mark as failed and surface a manual "Retry" button in post list
- Multi-account syndication order (parallel vs sequential): Claude's discretion based on rate limit and complexity tradeoffs
- Async mechanism (WP-Cron single event vs Action Scheduler): Claude's discretion based on codebase research — PROJECT.md mentions "WP-Cron" and "Action Scheduler for async" as prior decisions, researcher should evaluate

### Rate Limit & Backoff Strategy
- Respect Bluesky's Retry-After header when available, fall back to own exponential backoff schedule if header absent
- Rate limit tracking scope (per-account vs global): Claude's discretion based on Bluesky API behavior research
- Rate limit state persistence (transients vs in-memory): Claude's discretion based on WordPress patterns
- When rate-limited on frontend (blocks/widgets): serve stale cached data with a subtle "last updated X ago" indicator — never show empty/error state if cache exists

### Circuit Breaker Policy
- Fixed thresholds: 3 consecutive failures triggers 15-minute cooldown (not admin-configurable)
- Circuit breaker scope: per-account — one account's failures don't block others
- When breaker is open: queue syndication requests and retry after cooldown (no data loss)
- Recovery mechanism (auto-recover vs half-open probe): Claude's discretion — pick the standard pattern

### Request Deduplication & Caching
- Same-page dedup: in-memory per-request cache (static variable during page render) prevents duplicate API calls for multiple blocks
- Also optimize existing transient cache strategy (review and improve, not just same-page dedup)
- Stale-while-revalidate pattern: serve cached data immediately, refresh in background
- Cache TTL: reuse the existing admin settings page cache duration option; default to 10 minutes if not set

### Claude's Discretion
- Async mechanism choice (WP-Cron vs Action Scheduler)
- Multi-account syndication order (parallel vs sequential)
- Rate limit tracking scope (per-account vs global)
- Rate limit state persistence method
- Circuit breaker recovery mechanism (auto vs half-open probe)
- Exact backoff schedule timing
- Stale-while-revalidate implementation approach
- Admin notice update mechanism (AJAX polling, heartbeat, or page reload)

</decisions>

<specifics>
## Specific Ideas

- Existing admin settings page already has a cache duration option — reuse that value for transient TTL rather than adding a new setting
- Admin notice pattern: "Syndicating to Bluesky..." that updates to success/failure — non-blocking, informational
- Per-account circuit breakers allow isolated failure handling — if one account's credentials expire, others keep working
- Queue-and-retry when circuit breaker is open ensures no syndication requests are silently dropped

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 03-performance-resilience*
*Context gathered: 2026-02-18*
