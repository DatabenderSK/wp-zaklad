=== WP Základ ===
Contributors: martinpavlic
Tags: optimization, security, performance, whitelabel, developer, maintenance, seo
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.6
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Baseline configuration for every WordPress site. Optimizes, secures and customizes WP out of the box with a modular, translatable settings panel.

== Description ==

WP Základ is a developer utility plugin for managing WordPress websites. It provides a modular settings panel with toggles for common optimizations, security settings, SEO, appearance tweaks, and more — all in one place.

**Key features:**

* **Optimization** – Disable emoji, oEmbed, RSS, XML-RPC, jQuery Migrate, self-pingbacks, clean head, block update emails, lazy load iframes, disable Gravatars (GDPR)
* **Security** – Hide WP/PHP version, disable file editor, restrict REST API, security HTTP headers, hide login errors, block author scan, hide updates for clients
* **SEO** – Discourage indexing, noindex search/archives, remove feed links
* **Appearance** – Custom login logo, clean dashboard, hide admin for clients, colored post statuses, hide admin notices
* **Content** – Disable comments, disable Gutenberg, allow SVG uploads (with DOMDocument sanitizer), lowercase filenames, shortcodes `[rok]` and `[search_title]`
* **Maintenance** – Maintenance mode (503) with custom content and background color
* **White Label** – Hide WP logo, custom admin footer, hide "Howdy,", hide frontend admin bar
* **Scripts** – Google Tag Manager ID, custom head/footer code
* **Performance** – Heartbeat control, remove query strings, disable large image scaling, revisions limit, disable Dashicons, JPEG quality
* **Admin Menu** – Drag & drop sidebar order, quick links toolbar (up to 8 links), quick links exportable
* **System** – Dashboard info widget, track last login

Available in **Slovak** and **English**.

== Installation ==

1. Upload the `wp-zaklad` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → WP Základ** to configure

== Frequently Asked Questions ==

= Does this plugin work with page builders like Elementor? =

Yes. All features are individually toggleable. The "Disable REST API for unauthenticated users" option may conflict with some page builders — use the safer "Hide users via REST API" option instead.

= Is the SVG upload safe? =

Yes. SVG files are sanitized using a DOMDocument-based allowlist that removes `<script>`, `<foreignObject>`, event handlers, and dangerous href values. SVG uploads are restricted to administrators only.

= Can I transfer settings between sites? =

Yes. Use the Export / Import feature in the Tools tab. The export includes all settings plus your toolbar quick links. Admin menu order is intentionally excluded as it differs between sites.

= Why is DISALLOW_FILE_EDIT not working? =

The plugin both defines the `DISALLOW_FILE_EDIT` constant and removes the editor menu items directly. For maximum security, also add `define('DISALLOW_FILE_EDIT', true);` to your `wp-config.php`.

= Does this plugin support multisite? =

Not officially tested on multisite. It is designed for single-site installations.

== Screenshots ==

1. Main settings panel with sidebar navigation
2. Security settings tab
3. Tools tab (export, import, reset, flush transients, system info)
4. Admin Menu tab with drag & drop order and quick links

== Changelog ==

= 1.0.6 =
* Security: Added Login Honeypot – blocks bots, never locks out real users
* Security: Added Disable Application Passwords toggle
* Security: Added Block PHP Execution in uploads folder (.htaccess, Apache/LiteSpeed only)
* Security: Custom login URL now supports dots (e.g. vstup.php); updated description clarifies valid formats
* Export/Import: All portability sections now support file download and file upload (not just copy/paste)
* Export/Import applies to: main Settings, Help Videos, Toolbar Links

= 1.0.5 =
* Added: Preset quick-setup selector (Žiadne / Odporúčané / Moje) in sidebar – applies checkboxes with one click
* Added: Videos Export/Import in Help Videos tab (JS-based, no page reload)
* Added: Toolbar links Export/Import in Admin Menu tab
* Added: Admin toolbar quick links label now visible (was black-on-black icon only)
* Added: Expanded System Info – MySQL version, WP Debug status, WP Memory Limit, PHP extensions (curl, gd, imagick…), max execution time, DB prefix
* Removed: JPEG quality setting (use dedicated image optimization plugins instead)
* Removed: Duplicate "Flush Transients" section from Database tab (covered by DB Optimizer expired transients row)

= 1.0.4 =
* Fixed: Critical save bug – settings (checkboxes etc.) were wiped on save due to Scripts module overwriting the full option with partial data
* Added: Database tab – DB optimizer and flush transients moved to dedicated tab
* Added: System info table added to the System tab settings panel
* Improved: Admin Menu toolbar quick links are now dynamic (add/remove/reorder, no 8-link limit)
* Improved: Help Videos admin UI – fields stacked vertically, subtle card background
* Improved: Tools tab now contains only Export, Import, and Reset

= 1.0.3 =
* Added: Help Videos dashboard widget – admin configures tutorial video list, clients see it on dashboard
* Admin Menu: increased toolbar quick links from 5 to 8
* Export/Import: toolbar quick links now included in settings export/import
* Added: README.md and readme.txt documentation
* Added: LICENSE file (GPL-2.0)

= 1.0.2 =
* Security: SVG sanitizer replaced with DOMDocument allowlist (was bypassable regex blocklist)
* Security: SVG upload restricted to administrators only
* Security: DISALLOW_FILE_EDIT now also removes menu items directly
* Security: Login error message respects language setting
* Security: Import JSON values sanitized per field type
* Security: Transient flush uses $wpdb->prepare() and wp_cache_flush()
* All wp_redirect() replaced with wp_safe_redirect()
* Fixed esc_html_e() misuse

= 1.0.1 =
* Added: Hide login error messages (security – prevents username enumeration)
* Added: Block /?author=1 author scan (security)
* Added: Disable Gravatars (privacy/GDPR)
* Added: GitHub auto-update via plugin-update-checker v5

= 1.0.0-alpha =
* Initial release

== Upgrade Notice ==

= 1.0.2 =
Security hardening release. Recommended for all users. Fixes SVG sanitizer, adds wp_safe_redirect, and improves import sanitization.
