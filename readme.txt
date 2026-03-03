=== Code Unloader ===
Contributors: pluginowner
Tags: performance, assets, scripts, styles, dequeue
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per-page JavaScript & CSS asset management. Surgically dequeue scripts and styles on any page using exact, wildcard, or regex URL rules.

== Description ==

Code Unloader gives site administrators surgical control over which JavaScript and CSS files are loaded on each individual page or post.

**Key Features:**

* Disable any registered JS or CSS file on any page or post
* Exact URL, wildcard pattern (/shop/*), and full regex matching
* Rules survive cache flushes and plugin reactivations
* Assets grouped by plugin, theme, or WordPress Core in the panel
* Per-page frontend panel accessible from the Admin Toolbar
* Access panel on any page via `?wpcu` URL parameter
* Global admin screen listing all rules across the site
* One-click kill switch to instantly restore all assets sitewide
* Conditional rules (logged-in users, WooCommerce pages, shortcodes, post types)
* Device-type rules (desktop-only or mobile-only)
* Inline script/style blocking for assets without registered handles
* Rule groups for managing sets of rules as a unit
* Full audit log of all changes
* JSON import/export
* Zero performance overhead on pages with no matching rules

**Compatible with:** WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, WooCommerce, Elementor, Divi.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/code-unloader`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Visit any page while logged in as an admin and click **Assets** in the Admin Toolbar
4. Toggle off any asset — a rule creation dialog will appear

== Frequently Asked Questions ==

= Will my rules survive a cache flush? =
Yes. Rules are stored in a custom database table and are not affected by caching plugin cache clears.

= What is the kill switch? =
The kill switch is a one-click emergency recovery button in **Settings > Code Unloader > Settings**. When active, all rules are bypassed and every asset loads normally. Your rules are not deleted — they resume when you deactivate the kill switch.

= What does the ?wpcu parameter do? =
Appending `?wpcu` to any frontend URL will automatically open the asset panel for logged-in administrators, even on pages where the Admin Toolbar is hidden.

= Does it support regex? =
Yes. When creating a rule, choose **Regular Expression** as the match type. Patterns are validated before saving, and a regex warning is shown to help you keep patterns specific.

== Screenshots ==

1. Frontend panel showing assets grouped by source
2. Rule creation dialog with match type, device, and condition options
3. Global admin screen — Rules tab
4. Admin screen — Groups tab
5. Admin screen — Audit Log tab
6. Admin screen — Settings tab with kill switch

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
