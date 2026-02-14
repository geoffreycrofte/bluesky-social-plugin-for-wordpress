# Domain Pitfalls

**Domain:** WordPress Plugin — Multi-Account Bluesky Integration & Refactoring
**Researched:** 2026-02-14
**Confidence:** MEDIUM

## Critical Pitfalls

Mistakes that cause data loss, rewrites, or major production issues.

### Pitfall 1: Breaking Existing User Settings During Multi-Account Migration

**What goes wrong:** Migrating from single-account (`bluesky_settings` option) to multi-account structure corrupts existing credentials, post metadata, and syndication history.

**Why it happens:**
- Migration runs without version detection
- New structure doesn't preserve existing credential format
- Post meta keys change without migration
- Transient cache keys change, causing auth failures
- No rollback if migration fails partway through

**Consequences:** Existing installations lose auth state, discussion threads break, support floods.

**Prevention:**
1. Version-gated migration: check schema version, only migrate once
2. Preserve original option: copy to new structure, delete old only after verification
3. Post meta migration: scan posts with `_bluesky_syndicated`, associate with primary account
4. Admin notice: show "Migration Complete" with verification link
5. Emergency bypass: `BLUESKY_SKIP_MIGRATION` constant

**Detection:** Auth form shows "not authenticated" after update; discussion threads disappear; "undefined index: handle" errors.

**Phase:** Must-have in multi-account foundation phase.

---

### Pitfall 2: Race Conditions in Async Syndication with WP-Cron

**What goes wrong:** Moving syndication to WP-Cron causes duplicate Bluesky posts, lost syndication attempts, or orphaned metadata.

**Why it happens:**
- WP-Cron depends on site traffic to trigger (not real cron)
- Multiple WP-Cron processes run simultaneously on high-traffic sites
- No locking mechanism prevents duplicate job execution
- Token expiration mid-queue processing
- Hook ordering: `transition_post_status` fires before meta is saved in some contexts

**Consequences:** Duplicate posts on Bluesky, scheduled posts never syndicate (low-traffic sites), bulk imports overwhelm queue.

**Prevention:**
1. Atomic meta check: `add_post_meta($post_id, '_bluesky_syndicated', 'pending', true)` with unique=true
2. Use Action Scheduler over WP-Cron for reliability, built-in locking, retry logic
3. Token refresh mid-queue: check expiration before each job
4. Queue draining: batch import detection, process 1/min to respect rate limits
5. Idempotency key: `hash(post_id + post_modified)` to prevent retry duplicates

**Detection:** Post meta `_bluesky_syndicated = pending` older than 1 hour; same post appears multiple times on Bluesky.

**Phase:** Must solve before async syndication ships.

---

### Pitfall 3: Bluesky API Rate Limit Cascade Failure

**What goes wrong:** Undocumented rate limits hit during batch operations, 429 errors propagate to all features. No backoff strategy leads to exponential retry, worsening the rate limit.

**Why it happens:**
- AT Protocol PDS rate limits are per-DID, not documented publicly
- Current code has no 429 response handling
- Multiple shortcodes per page each call API independently
- Cache misses cause thundering herd
- Multi-account: N accounts × M requests

**Consequences:** Entire site shows "connection failed" on all pages.

**Prevention:**
1. 429 detection + exponential backoff (1min, 5min, 15min, 1hr)
2. Global rate limit state in transient: check before any API call
3. Request deduplication: share API results across simultaneous shortcode renders
4. Circuit breaker: after 3 consecutive 429s, stop all requests for 15min
5. Priority queue: syndication > discussion > profile > feed

**Detection:** HTTP 429 in logs; all API calls return false simultaneously; admin sees "auth failed" with correct credentials.

**Phase:** Address in performance/resilience phase or earlier if multi-account increases volume.

---

### Pitfall 4: Decomposing Monolithic Class Without Interface Contracts

**What goes wrong:** Splitting 2537-line `BlueSky_Plugin_Setup.php` without clear interfaces causes silent behavioral changes, breaks hook order, introduces subtle bugs.

**Why it happens:**
- No interface definition before refactor — implicit contracts undocumented
- Method extraction changes `$this->` references
- Hook registration order changes when moved to different classes
- WordPress action/filter priorities lost during migration

**Consequences:** Settings sanitization runs after AJAX registration; AJAX handlers can't access API handler; shortcode renders without styles.

**Prevention:**
1. Extract interfaces first: define contracts before implementation
2. Hook priority audit: document all `add_action`/`add_filter` with priorities before extraction
3. Incremental extraction: one concern per commit, test between each
4. Facade pattern: keep Plugin_Setup as coordinator initially, delegate to new classes
5. Smoke test checklist: settings save, syndication, AJAX, frontend render after each extraction

**Detection:** "Call to undefined method" errors; settings save but don't persist; JS console "ajaxurl is not defined".

**Phase:** Code quality refactor phase — must be methodical, not rushed.

---

### Pitfall 5: Multi-Account Post Ownership Ambiguity

**What goes wrong:** Existing syndicated posts have no account association. Discussion threads fetch from wrong account, reply links break.

**Why it happens:**
- Original design stored only Bluesky URI, no account identifier
- `_bluesky_uri` contains DID but not plugin account ID
- Discussion rendering assumes current account owns all synced posts

**Consequences:** Deleting account breaks discussions; re-syndicating goes to wrong account; mixed discussions.

**Prevention:**
1. Add `_bluesky_account_id` meta alongside `_bluesky_uri` for new syndications
2. Backfill: extract DID from existing URIs, match to migrated account
3. Orphan detection: on account deletion, find posts, mark orphaned, show admin notice
4. Discussion fallback: if account ID missing, parse DID from URI

**Detection:** "Account not found" in discussions; account dropdown shows "(none)"; re-syndicate creates duplicate on different account.

**Phase:** Must address during multi-account foundation.

---

## Moderate Pitfalls

### Pitfall 6: Encryption Key Rotation Breaks Existing Credentials
**What goes wrong:** Changing encryption during refactor makes existing `app_password` values undecryptable.
**Prevention:** Decrypt with old key before changing encryption. Store encryption version, support old version decryption.

### Pitfall 7: Transient Cache Collision Between Accounts
**What goes wrong:** Cache keys lack account ID, switching accounts shows stale data from previous account.
**Prevention:** Include account ID in all transient keys. Invalidate on account switch.

### Pitfall 8: WordPress Multisite Network Activation Conflict
**What goes wrong:** Network activation creates global settings instead of per-site. All subsites share one account.
**Prevention:** Detect multisite, force per-site activation. Document compatibility status.

### Pitfall 9: Discussion Thread Recursion Depth Explosion
**What goes wrong:** Deeply nested replies (>50 levels) cause PHP memory exhaustion.
**Prevention:** Limit thread depth to 10, show "Load more". Use iterative rendering instead of recursive.

### Pitfall 10: Shortcode Attribute Changes Break Existing Posts
**What goes wrong:** Renaming attributes (e.g., `limit` → `posts_limit`) breaks thousands of existing posts.
**Prevention:** Support both old and new names with deprecation notice. Map old to new internally.

## Minor Pitfalls

### Pitfall 11: Over-Engineering Multi-Account UI
**What goes wrong:** Complex account switcher confuses single-account users.
**Prevention:** Auto-select if only one account. Progressive disclosure.

### Pitfall 12: Incomplete Asset Cleanup on Uninstall
**What goes wrong:** Uninstall leaves multi-account settings, post meta, transients.
**Prevention:** Update `uninstall.php` for new option keys and meta.

### Pitfall 13: Missing Translation Strings in New Classes
**What goes wrong:** Extracted classes add English strings without `__()` wrappers.
**Prevention:** Audit all new classes. Regenerate `.pot` file.

### Pitfall 14: Debug Code Leaks to Production
**What goes wrong:** `?godmode` parameter and `var_dump()` remain in production.
**Prevention:** Remove all debug GET parameters. Replace with proper logging.

### Pitfall 15: Inconsistent Error Messages Across Features
**What goes wrong:** Different features show different messages for same failure.
**Prevention:** Centralize in `BlueSky_Helpers::get_error_message($error_code)`.

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Multi-Account Migration | Settings data loss (#1) | Version-gated migration with rollback |
| Multi-Account Migration | Post ownership (#5) | Add `_bluesky_account_id`, backfill from URIs |
| Async Syndication | WP-Cron races (#2) | Action Scheduler, atomic locking, idempotency |
| Async Syndication | Rate limit cascade (#3) | 429 detection, exponential backoff, circuit breaker |
| Code Refactor | Interface violations (#4) | Extract interfaces first, incremental extraction |
| Code Refactor | Hook priority changes (#4) | Audit all priorities before extraction |
| Advanced Customization | Shortcode breaking (#10) | Backward compatibility layer |
| Advanced Customization | Over-complex UI (#11) | Progressive disclosure |
| Security/Testing | Debug leaks (#14) | Remove `?godmode`, proper logging |
| Security/Testing | Encryption changes (#6) | Support old encryption version |

## WordPress.org Distribution Risks

1. **SVN update propagation:** Updates reach all users immediately. No gradual rollout. Beta test major migrations.
2. **PHP version matrix:** Users on PHP 7.4 shared hosting. Avoid PHP 8+ only features.
3. **WordPress compatibility:** Test on 5.9+. Graceful degradation for older Gutenberg.

## Sources

- Codebase analysis (CONCERNS.md, current source files)
- WordPress plugin development patterns and Codex
- AT Protocol/Bluesky API behavior inferred from codebase
- Standard software refactoring practices

---
*Pitfalls research: 2026-02-14*
