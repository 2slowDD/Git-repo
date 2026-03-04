# CLAUDE.md — Code Unloader WordPress Plugin

This file provides guidance for AI assistants working on the Code Unloader codebase.

---

## Project Overview

**Code Unloader** is a WordPress plugin (v1.0.0) that provides per-page JavaScript and CSS asset management. It allows administrators to surgically dequeue specific scripts and styles on any page using exact URL matching, wildcard patterns, or regex.

- **Requirements:** WordPress 6.2+, PHP 8.0+
- **No external dependencies:** No Composer, no npm, no build tools
- **Text domain:** `code-unloader`

---

## Repository Structure

```
code-unloader.php          # Plugin entry point — defines constants, autoloader, activation hooks
uninstall.php              # Full cleanup on plugin deletion
readme.txt                 # WordPress.org plugin documentation
assets/
  css/
    admin.css              # Admin page styles
    panel.css              # Frontend panel styles (890 lines, custom properties)
  js/
    admin.js               # Admin UI logic (vanilla JS, 284 lines)
    panel.js               # Frontend panel logic (vanilla JS, 749 lines)
  img/
    CU_icon_200x200.png
src/                       # PSR-4 autoloaded, namespace: CodeUnloader\
  Plugin.php               # Bootstrap — conditionally wires all components
  Admin/
    AdminScreen.php        # Admin settings page (rules, groups, log, settings tabs)
    RulesListTable.php     # WP_List_Table for rules
    LogListTable.php       # WP_List_Table for audit log
  Api/
    RestController.php     # All REST API routes and handlers
  Core/
    Installer.php          # DB table creation/upgrades via dbDelta()
    DequeueEngine.php      # Core: loads rules, matches pages, dequeues assets
    RuleRepository.php     # All DB CRUD (rules, groups, log)
    PatternMatcher.php     # Exact / wildcard / regex URL matching
    AssetDetector.php      # Inspects wp_scripts/wp_styles queues
    ConditionEvaluator.php # Evaluates conditional rules (is_page_template, etc.)
    DeviceDetector.php     # Mobile vs desktop detection (static cache)
    InlineBlocker.php      # Output buffering to strip inline <script>/<style>
    CachePurger.php        # Purges 10+ caching plugins on rule change
  Frontend/
    FrontendPanel.php      # Admin toolbar button + panel loaded via ?wpcu param
```

---

## Architecture

### Bootstrap Flow

1. `code-unloader.php` defines constants (`CU_VERSION`, `CU_PLUGIN_DIR`, etc.) and registers the PSR-4 autoloader for `CodeUnloader\` → `src/`
2. `Plugin::boot()` fires on `plugins_loaded`
3. `Plugin` conditionally instantiates components:
   - **Frontend (non-admin):** `DequeueEngine`, `InlineBlocker`, `FrontendPanel`
   - **Admin:** `AdminScreen`
   - **Always:** `RestController`

### Key Design Patterns

| Pattern | Where Used |
|---|---|
| Repository | `RuleRepository` — all DB access goes through here |
| Strategy | `PatternMatcher` — switchable matching logic (exact/wildcard/regex) |
| Output buffering | `InlineBlocker` — wraps `wp_head`/`wp_footer` to filter inline blocks |
| Static cache | `DeviceDetector`, `RuleRepository::$rules_cache` — per-request memoization |
| Transient cache | `AssetDetector` — source map cached for `DAY_IN_SECONDS` |

### Database Tables

All tables are prefixed `wp_cu_*`:

- `wp_cu_rules` — dequeue rules (url_pattern, match_type, asset_handle, asset_type, device_type, condition, group_id, label)
- `wp_cu_groups` — rule groups for bulk management
- `wp_cu_log` — audit trail (action, rule_id, JSON snapshot, user_id, timestamp)

WordPress options used: `cu_kill_switch`, `cu_db_version`

### REST API

Base namespace: `code-unloader/v1` — all routes require `manage_options`.

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/rules` | List / create rules |
| DELETE/PATCH | `/rules/:id` | Delete / update rule |
| POST | `/rules/bulk-delete` | Bulk delete |
| POST | `/rules/bulk-assign-group` | Bulk assign group |
| POST | `/rules/validate-pattern` | Validate pattern syntax |
| GET/POST/PATCH/DELETE | `/groups`, `/groups/:id` | Group CRUD |
| GET/DELETE | `/log` | Audit log |
| GET/POST | `/killswitch` | Emergency kill switch |
| GET | `/assets` | Enqueued assets for a URL (used by panel) |

---

## Coding Conventions

### PHP

- **All files** use `declare(strict_types=1)` — never omit this.
- **Namespace:** `CodeUnloader\`, subnamespaces `Core`, `Admin`, `Api`, `Frontend`.
- **Class names:** PascalCase.
- **Methods/functions:** snake_case (WordPress standard).
- **Constants:** `SCREAMING_SNAKE_CASE` (plugin-level: `CU_*`).
- **Private properties/methods:** no leading underscore needed; use PHP visibility keywords.
- **No external libraries** — use WordPress APIs for everything (HTTP, DB, caching, escaping).

### Security (required on every change)

- Sanitize all input: `sanitize_text_field()`, `absint()`, `wp_parse_url()`, `wp_normalize_path()`
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Use `$wpdb->prepare()` for all queries — never raw string interpolation
- Verify nonces on form submissions; check `current_user_can('manage_options')` on all privileged operations
- REST endpoints: always include `permission_callback` returning `current_user_can('manage_options')`

### JavaScript (vanilla, no frameworks)

- IDs and class names: `cu-*` prefix
- `localStorage` keys: `cu_*` prefix
- Use `wp_rest` nonce for all REST calls (localized as `cuPanel.nonce` / `cuAdmin.nonce`)
- No jQuery dependency for new code (WordPress jQuery may be available but avoid new coupling)

### CSS

- All selectors scoped with `#cu-*` or `.cu-*` to avoid conflicts
- Custom properties (`--cu-*`) for theming — dark theme is default; light mode via `.cu-light` class
- Panel CSS in `assets/css/panel.css`; admin CSS in `assets/css/admin.css`

---

## WordPress Hook Reference

| Hook | Priority | Handler | Notes |
|---|---|---|---|
| `plugins_loaded` | default | `Plugin::boot()` | Entry point |
| `wp_enqueue_scripts` | 100 | `DequeueEngine::process_rules()` | Late priority to catch all enqueued assets |
| `wp_head` | -1 / 999 | `InlineBlocker` | Start/end output buffering |
| `wp_footer` | -1 / 999 | `InlineBlocker` | Start/end output buffering |
| `admin_bar_menu` | 100 | `FrontendPanel::add_toolbar_button()` | |
| `admin_menu` | default | `AdminScreen::register_page()` | |
| `rest_api_init` | default | `RestController::register_routes()` | |

---

## Pattern Matching Logic

`PatternMatcher` handles three match types:

1. **Exact** — case-insensitive, strips query string/fragment, normalizes trailing slashes
2. **Wildcard** — uses `fnmatch()` with `FNM_CASEFOLD`; `*` matches any path segment
3. **Regex** — auto-wrapped in `~pattern~` delimiter; `pcre.backtrack_limit` set to 100,000 for safety; validated before storage via `preg_match()` error checking

URL normalization is applied before all comparisons (scheme/host lowercased, query/fragment stripped).

---

## Conditional Rules

`ConditionEvaluator` supports built-in conditions:

- `is_user_logged_in`
- `is_woocommerce_page`
- `has_shortcode:{name}`
- `is_post_type:{type}`
- `is_page_template:{file}`

All conditions support inversion (`condition_invert = 1` → UNLESS logic).

Third-party conditions can be added via the `code_unloader_conditions` filter.

---

## Performance Notes

- **Kill switch** (`cu_kill_switch` option): single option check short-circuits all processing — check this first when debugging performance.
- **Request-scoped rule cache** in `RuleRepository::$rules_cache` — rules are fetched once per request.
- **Source map transient** in `AssetDetector` — cached for 1 day; invalidated on rule create/delete.
- **Output buffering** (`InlineBlocker`) is only activated when a page has matching inline rules — not globally.
- **Frontend panel assets** are only enqueued on `?wpcu` parameter pages.

---

## Development Workflow

### Making Changes

1. All PHP source lives in `src/` under the `CodeUnloader\` namespace.
2. No build step required — edit PHP/CSS/JS files directly.
3. Database schema changes go in `Installer::create_tables()` using `dbDelta()`.
4. When adding new DB columns, increment `CU_DB_VERSION` and add an upgrade path in `Installer::maybe_upgrade()`.

### Testing Manually

- Activate plugin on a WordPress 6.2+ install with PHP 8.0+.
- Navigate to **Settings > Code Unloader** for the admin panel.
- Append `?wpcu` to any frontend URL while logged in as admin to open the asset panel.
- Use the kill switch (Settings tab) to disable all rules without deleting data.

### Adding New REST Endpoints

1. Add route registration in `RestController::register_routes()`.
2. Add handler method with `permission_callback => fn() => current_user_can('manage_options')`.
3. Sanitize all `WP_REST_Request` parameters before use.
4. Return `WP_REST_Response` or `WP_Error`.

### Adding New Conditions

1. Add condition key to the match array in `ConditionEvaluator::evaluate()`.
2. Document the key in `readme.txt` FAQ section.
3. No DB schema change needed — condition_type is a `VARCHAR(64)`.

---

## Cache Integration

`CachePurger::purge()` is called after every rule change. It supports:

WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, Autoptimize, Breeze, SG Optimizer, Nginx Helper, Cloudflare

When adding a new cache plugin integration, add a method to `CachePurger` and call it from `purge()`.

---

## Data Lifecycle

| Event | Effect |
|---|---|
| Plugin activation | Creates `wp_cu_rules`, `wp_cu_groups`, `wp_cu_log` tables |
| Plugin deactivation | No data deleted (intentional — rules preserved) |
| Plugin uninstall (`uninstall.php`) | Drops all tables, deletes all `cu_*` options and transients |
| Kill switch ON | All dequeue processing skipped; data untouched |

---

## What NOT to Do

- Do not add Composer or npm dependencies — the plugin is intentionally dependency-free.
- Do not use `$wpdb->query()` with unescaped input — always use `$wpdb->prepare()`.
- Do not enqueue frontend panel assets globally — only on `?wpcu` pages.
- Do not use jQuery for new JavaScript — keep assets vanilla.
- Do not skip `declare(strict_types=1)` in any new PHP file.
- Do not perform dequeue before `wp_enqueue_scripts` priority 100 — assets must be fully registered first.
- Do not directly access `$_GET`/`$_POST` in REST handlers — use `$request->get_param()`.
