---
status: complete
phase: 06-advanced-syndication
source: 06-01-SUMMARY.md, 06-02-SUMMARY.md, 06-03-SUMMARY.md
started: 2026-02-22T18:00:00Z
updated: 2026-02-22T18:30:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Sidebar Panel Appears
expected: In the Gutenberg editor, the document settings sidebar shows a "Bluesky Syndication" panel with a "Don't syndicate this post" checkbox, editable text area, and character counter.
result: pass

### 2. Character Counter Accuracy
expected: Typing text in the syndication text area shows a live counter as "X / 300". Pasting an emoji like a flag or family emoji counts as 1 grapheme (not multiple characters). Counter turns red when over 300.
result: pass

### 3. Don't Syndicate Toggle
expected: Checking "Don't syndicate this post" hides the text area, account selection, and preview. Unchecking restores them.
result: pass

### 4. Reset to Default
expected: Typing custom text into the syndication field shows a "Reset to default" button. Clicking it clears the field. A preview box shows the auto-generated text (title + excerpt).
result: pass

### 5. Pre-Publish Panel
expected: Clicking "Publish" opens the pre-publish checklist. A "Bluesky Syndication" panel appears with the same don't-syndicate toggle, editable text, character counter, and post preview.
result: pass

### 6. Syndication Tab in Settings
expected: Settings page shows a "Syndication" tab. It contains a "Global Syndication Pause" toggle and per-account category rules with include/exclude checkboxes.
result: pass

### 7. Category Rules Save
expected: In Settings > Syndication, checking include/exclude categories for an account and clicking "Save Changes" persists the selections. Reloading the page shows the same checkboxes still checked.
result: pass

### 8. Global Pause Toggle
expected: Enabling "Global Syndication Pause" in Settings > Syndication shows a warning style. Publishing a post while pause is enabled does NOT syndicate to Bluesky.
result: issue
reported: "It's working, but it'll be great to get a warning in the gutenberg and post metabox saying the syndication is globally paused (in red?) with a link the the Syndication tab setting page."
severity: minor

### 9. Category-Aware Account Selection in Editor
expected: In the Gutenberg sidebar, with multi-account and category rules configured, selecting a post category automatically checks/unchecks accounts based on their include rules. Accounts that don't match show a warning.
result: pass

### 10. Category Include Rule Syndication
expected: With Account A set to include category "CA" and Account B set to include category "CB", publishing a post in category CA syndicates only to Account A (not B).
result: pass

### 11. Theme Option in Styles Tab
expected: Settings page "Styles" tab shows the Theme option (Light/Dark/Auto) before the "Profile Customization" section. It is NOT in the Feed Options tab.
result: pass

### 12. Only Auto-Syndicate Accounts in Category Rules
expected: Settings > Syndication category rules section only shows accounts with auto-syndicate enabled. Accounts with auto-syndicate OFF are not shown.
result: pass

## Summary

total: 12
passed: 11
issues: 1
pending: 0
skipped: 0

## Gaps

- truth: "Global pause shows warning in Gutenberg editor and post metabox with link to settings"
  status: failed
  reason: "User reported: It's working, but it'll be great to get a warning in the gutenberg and post metabox saying the syndication is globally paused (in red?) with a link the the Syndication tab setting page."
  severity: minor
  test: 8
  root_cause: ""
  artifacts: []
  missing: []
  debug_session: ""
