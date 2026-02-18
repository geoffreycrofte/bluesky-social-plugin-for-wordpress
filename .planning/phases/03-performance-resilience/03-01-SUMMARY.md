---
phase: 03-performance-resilience
plan: 01
subsystem: resilience
tags: [circuit-breaker, rate-limiter, tdd, transients, per-account]
dependency_graph:
  requires: [WordPress Transient API, Brain Monkey test framework]
  provides: [BlueSky_Circuit_Breaker, BlueSky_Rate_Limiter]
  affects: [API reliability layer, failure recovery system]
tech_stack:
  added:
    - Circuit breaker pattern (closed/open/half-open states)
    - Rate limiter with Retry-After header parsing
    - Exponential backoff with jitter (60s/120s/300s)
  patterns:
    - State machine for circuit breaker transitions
    - Per-account isolation via WordPress transients
    - TDD with Brain Monkey mocking
key_files:
  created:
    - classes/BlueSky_Circuit_Breaker.php
    - classes/BlueSky_Rate_Limiter.php
    - tests/unit/BlueSky_Circuit_Breaker_Test.php
    - tests/unit/BlueSky_Rate_Limiter_Test.php
  modified:
    - tests/bootstrap.php
decisions:
  - "Circuit breaker uses 3-failure threshold with 15-minute cooldown"
  - "Rate limiter supports both numeric seconds and HTTP date Retry-After formats"
  - "Exponential backoff applies ±20% jitter to prevent thundering herd"
  - "Per-account state isolation prevents one failing account from blocking others"
  - "State persisted in WordPress transients (survives across requests)"
metrics:
  duration_seconds: 263
  duration_minutes: 4
  tasks_completed: 2
  files_created: 4
  files_modified: 1
  tests_added: 21
  test_assertions: 26
  commits: 4
  completed_at: "2026-02-18T21:50:08Z"
---

# Phase 03 Plan 01: Circuit Breaker and Rate Limiter Summary

**One-liner:** Per-account circuit breaker with 3-failure threshold and rate limiter with Retry-After parsing plus exponential backoff (60s/120s/300s with jitter)

## What Was Built

Created two foundational resilience primitives using TDD:

1. **BlueSky_Circuit_Breaker**: Prevents cascading failures by blocking requests after repeated failures
   - Three states: closed (normal), open (failing), half-open (testing recovery)
   - Opens circuit after 3 consecutive failures per account
   - 15-minute cooldown before allowing test request
   - Successful test request closes circuit; failed test request reopens it
   - State stored in transients: `bluesky_circuit_{account_id}` and `bluesky_failures_{account_id}`

2. **BlueSky_Rate_Limiter**: Detects and manages HTTP 429 responses
   - Parses Retry-After header (numeric seconds and HTTP date format)
   - Exponential backoff when no header provided: 60s (attempt 1) → 120s (attempt 2) → 300s (attempt 3+)
   - ±20% jitter on backoff delays to prevent thundering herd
   - State stored in transients: `bluesky_rate_limit_{account_id}` and `bluesky_rate_attempts_{account_id}`
   - `is_rate_limited()` checks active state, cleans up expired limits
   - `get_retry_after()` returns remaining seconds until retry allowed

Both classes provide per-account isolation—one account's failures or rate limits don't affect others.

## TDD Workflow

**RED → GREEN cycle executed for both classes:**

### Task 1: Circuit Breaker
- **RED** (c7004d9): 9 tests written, all failing (class not found)
- **GREEN** (e6a04e3): Implementation added, all 9 tests passing

### Task 2: Rate Limiter
- **RED** (d29c805): 12 tests written, all failing (class not found)
- **GREEN** (7dfd1da): Implementation added, all 12 tests passing

**No REFACTOR phase needed**—implementations were clean on first pass.

## Test Coverage

**21 new tests, 26 assertions:**

**Circuit Breaker Tests (9):**
- Initial state (closed, available)
- Failure counting and threshold detection
- Circuit opening after 3 failures
- Cooldown period enforcement (15 minutes)
- Half-open state transition after cooldown
- Success/failure handling in half-open state
- Success in closed state resets failure count
- Per-account independence

**Rate Limiter Tests (12):**
- Non-429 responses return false
- 429 responses return true
- Retry-After numeric seconds parsing
- Retry-After HTTP date parsing
- Exponential backoff without header (attempt 1/2/10)
- Active rate limit detection
- Expired rate limit cleanup
- `get_retry_after()` seconds calculation
- Per-account independence
- 300s cap on exponential backoff

**Full suite: 48 tests, 68 assertions (no regressions)**

## Technical Implementation

### Circuit Breaker State Machine

```
CLOSED (normal) ─[3 failures]→ OPEN (cooldown)
    ↑                              ↓
    │                      [15 min expires]
    │                              ↓
    └───[success]─── HALF_OPEN (testing)
                         ↓
                    [failure]
                         ↓
                      OPEN (reopen)
```

### Rate Limiter Backoff Schedule

| Attempt | Base Delay | With Jitter (±20%) |
|---------|------------|---------------------|
| 1       | 60s        | 48-72s              |
| 2       | 120s       | 96-144s             |
| 3+      | 300s       | 240-360s            |

**Server Retry-After header overrides backoff schedule.**

### Transient Keys

**Circuit Breaker:**
- State: `bluesky_circuit_{account_id}` → `['status' => 'closed|open|half_open', 'open_until' => timestamp]`
- Failures: `bluesky_failures_{account_id}` → integer count

**Rate Limiter:**
- Expiry: `bluesky_rate_limit_{account_id}` → Unix timestamp
- Attempts: `bluesky_rate_attempts_{account_id}` → integer count

## Deviations from Plan

None—plan executed exactly as written. All tests pass, all requirements met.

## Integration Readiness

These classes are standalone primitives ready for integration into BlueSky_API_Handler:

**Next integration steps (Phase 03, Plan 02):**
1. Update BlueSky_API_Handler to check circuit breaker before making requests
2. Record circuit breaker success/failure based on response codes
3. Check rate limiter after receiving 429 responses
4. Block requests when `is_rate_limited()` returns true
5. Surface retry timing via `get_retry_after()` for user feedback

**Current state:** Classes fully tested in isolation. No API handler changes yet.

## Verification

✅ Circuit breaker state machine (closed → open → half-open → closed)
✅ 3-failure threshold triggers circuit opening
✅ 15-minute cooldown before half-open transition
✅ HTTP 429 detection and Retry-After header parsing
✅ Exponential backoff with jitter when no header
✅ Per-account isolation (independent circuits and rate limits)
✅ State persistence via WordPress transients
✅ Full test suite passes (48 tests, 68 assertions)
✅ No regressions in existing tests

## Files Changed

**Created:**
- `classes/BlueSky_Circuit_Breaker.php` (217 lines)
- `classes/BlueSky_Rate_Limiter.php` (184 lines)
- `tests/unit/BlueSky_Circuit_Breaker_Test.php` (312 lines)
- `tests/unit/BlueSky_Rate_Limiter_Test.php` (432 lines)

**Modified:**
- `tests/bootstrap.php` (added conditional requires for new classes)

## Commits

| Commit  | Type | Description                                    |
|---------|------|------------------------------------------------|
| c7004d9 | test | Add failing test for circuit breaker (RED)    |
| e6a04e3 | feat | Implement circuit breaker with state machine   |
| d29c805 | test | Add failing test for rate limiter (RED)        |
| 7dfd1da | feat | Implement rate limiter with exponential backoff|

## Self-Check: PASSED

**Files created verification:**
```
✅ FOUND: classes/BlueSky_Circuit_Breaker.php
✅ FOUND: classes/BlueSky_Rate_Limiter.php
✅ FOUND: tests/unit/BlueSky_Circuit_Breaker_Test.php
✅ FOUND: tests/unit/BlueSky_Rate_Limiter_Test.php
```

**Commits verification:**
```
✅ FOUND: c7004d9
✅ FOUND: e6a04e3
✅ FOUND: d29c805
✅ FOUND: 7dfd1da
```

**Test execution:**
```
✅ Circuit breaker tests: 9/9 passing
✅ Rate limiter tests: 12/12 passing
✅ Full suite: 48/48 tests passing
```

All claims verified. Plan complete.
