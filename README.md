# Code Unloader

Per-page JavaScript & CSS asset management for WordPress. Surgically dequeue scripts and styles on any page using exact, wildcard, or regex URL rules.

## Features

- **Disable any registered JS or CSS** file on any page or post
- **Flexible URL matching** — exact URL, wildcard patterns (`/shop/*`), and full regex
- **Persistent rules** — stored in a custom database table, survive cache flushes and plugin reactivations
- **Organized panel** — assets grouped by plugin, theme, or WordPress Core
- **Frontend panel** — accessible from the Admin Toolbar or via `?wpcu` URL parameter
- **Global admin screen** — view and manage all rules across the site
- **Kill switch** — one-click emergency recovery to restore all assets sitewide
- **Conditional rules** — target logged-in users, WooCommerce pages, shortcodes, or post types
- **Device-type rules** — desktop-only or mobile-only
- **Inline blocking** — block inline scripts/styles without registered handles
- **Rule groups** — manage sets of rules as a unit
- **Audit log** — full history of all changes
- **Import/Export** — JSON-based backup and migration
- **Zero overhead** — no performance impact on pages with no matching rules

## Requirements

- WordPress 6.2 or higher
- PHP 8.0 or higher

## Installation

1. Download or clone this repository into your `/wp-content/plugins/` directory:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/2slowDD/Code-Unloader.git code-unloader
   ```
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Visit any page while logged in as an admin and click **Assets** in the Admin Toolbar.
4. Toggle off any asset — a rule creation dialog will appear.

## Usage

### Frontend Panel

Navigate to any page on your site and click **Assets** in the Admin Toolbar (or append `?wpcu` to the URL). The panel displays all enqueued scripts and styles grouped by their source (plugin, theme, or core).

### Creating Rules

1. Toggle off an asset in the frontend panel.
2. Choose a **match type**: Exact URL, Wildcard, or Regular Expression.
3. Optionally set **conditions** (logged-in users, post type, etc.) and **device targeting**.
4. Save the rule.

### Admin Screen

Go to **Settings → Code Unloader** to:

- View and manage all rules in the **Rules** tab
- Organize rules into **Groups**
- Review the **Audit Log**
- Toggle the **Kill Switch** in Settings
- **Import/Export** rules as JSON

## Compatibility

Tested with: WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, WooCommerce, Elementor, and Divi.

## License

This plugin is licensed under the [GPL-2.0-or-later](LICENSE).
