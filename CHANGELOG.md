# Changelog

All notable changes to Code Unloader will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.4] - 2025-03-04

### Changed

- Source header row (light) — gradient from subtle purple to deep red tint
- Source header row (dark) — gradient from #f0f0f0 to deep red tint; chevron now white
- Warning banner — stronger red gradient applied to both themes

## [1.2.3] - 2025-03-03

### Changed

- Toggle track for unloaded (off) assets changed to red (#cc1818) in both light and dark themes

### Improved

- Group-disabled assets now show both the blue "Group: X" badge and a red "Disabled (match_type)" badge, consistent with non-grouped disabled assets

## [1.2.2] - 2025-03-02

### Changed

- Source group header rows now have a distinct background to visually separate plugins
- Light theme — header row background: #5500cc52 (semi-transparent purple); count/size/action badges: white with dark text
- Dark theme — header row background: #f0f0f0; source label color: #234897; all badges remain white-on-dark for contrast
- All new colour pairs verified against WCAG AA contrast ratios

## [1.2.1] - 2025-03-01

### Fixed

- Disabled-group rules now correctly excluded from panel rule_map at PHP render time (FrontendPanel)
- Same (int) cast bug found and fixed in InlineBlocker group_enabled check
- REST get_rules endpoint page_url branch now skips disabled-group rules
- Disabling a group now suspends its rules — they disappear from the Rules tab, assets load normally on frontend and in panel
- "N total rules" count and Rules table now exclude suspended (disabled-group) rules
- COUNT query in get_rules_filtered now JOINs groups table so the group-enabled filter applies correctly to pagination totals
- Panel (GET /assets) now skips disabled-group rules so assets correctly show as Active when their group is disabled

## [1.2.0] - 2025-02-28

### Fixed

- Disabling a group now correctly stops its rules from being applied — wpdb returns column values as strings, so "0" was truthy and the group-disabled check never fired; fixed with explicit (int) cast

## [1.1.9] - 2025-02-27

### Fixed

- "N total rules" counter now updates instantly after any delete (single, bulk, stale) — no page reload needed
- Disabling a group now immediately stops its rules from being applied on the frontend — root cause was static in-memory rule cache not being cleared when group enabled state changed

## [1.1.8] - 2025-02-26

### Fixed

- "Assign" / "Save" button stays greyed out after first group assignment — button now always re-enables after any outcome
- Group column shows raw ID instead of name when assigning to a newly created group — integer type mismatch corrected
- GET /assets endpoint now keys rules by handle|type (not handle alone) so same-handle JS+CSS rules both survive

### Improved

- Panel now live-syncs rule_map and groups from the REST API on every open — admin changes are reflected without a page reload

## [1.1.7] - 2025-02-25

### Changed

- Admin layout widened from 1100px to 1200px
- Rules table now defaults to 10 per page (was 20)

### Added

- "Rules per page" screen option (10 / 20 / 50) accessible via Screen Options tab at the top of the admin page

## [1.1.6] - 2025-02-24

### Fixed

- Group column in Rules table now updates instantly after bulk-assign — no page reload needed

## [1.1.5] - 2025-02-23

### Added

- "Group" column in admin Rules table — shows group name as a teal pill, or "—" if ungrouped

### Changed

- Settings icon in panel header replaced with a clean solid SVG gear (matches browser native style)

## [1.1.4] - 2025-02-22

### Fixed

- Plugin zip now correctly extracts to `code-unloader/` folder — resolves all PCP text domain mismatch false positives caused by wrong folder name

### Added

- Settings (gear) icon button in panel header — links directly to the Code Unloader admin screen

## [1.1.3] - 2025-02-21

### Changed

- Disable Asset dialog widened from 560px to 660px for better readability
- Version number now displayed next to plugin title in admin screen header

### Fixed

- Inline Blocks info message no longer references an unbuilt feature

## [1.1.2] - 2025-02-20

### Added

- Version displayed in panel header (plugin version + panel.js/panel.css file versions)
- "Everywhere except here" scope option in the Disable Asset dialog
- Inline Blocks tab now shows informational notice — blocks cannot be unloaded from the panel
- Inline Blocks can be grouped by type (JS / CSS) via a toggle button

### Changed

- Inline Blocks CSS items now styled in blue (matching Assets tab), JS items in amber/yellow

### Fixed

- Removed noisy "[Code Unloader] inline_blocks: N detected" console log
- "Save Rule" button text now has full contrast in light mode
- "Cancel" button text now visible on hover in dark mode

## [1.1.0] - 2025-02-15

### Fixed

- Panel now persists across page refresh (?wpcu stays in URL)
- Inline Blocks tab now detects and displays inline scripts and styles
- Close button properly strips ?wpcu so refresh after close won't reopen

### Changed

- Version-stamped asset files for easier cache debugging
- Updated readme with PHP 8.0 requirement explanation and inline blocks FAQ

## [1.0.0] - 2025-01-01

### Added

- Per-page JavaScript and CSS asset management
- Exact URL, wildcard pattern, and regex URL matching
- Frontend panel accessible from Admin Toolbar and via `?wpcu` parameter
- Assets grouped by plugin, theme, or WordPress Core
- Global admin screen with Rules, Groups, Audit Log, and Settings tabs
- One-click kill switch for emergency asset restoration
- Conditional rules (logged-in users, WooCommerce pages, shortcodes, post types)
- Device-type targeting (desktop-only, mobile-only)
- Inline script/style blocking for unregistered handles
- Rule groups for batch management
- Full audit log
- JSON import/export
- Cache purge integration (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache)
