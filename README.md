# WP Základ

Baseline configuration plugin for WordPress. Optimizes, secures, and customizes WP out of the box with a modular, translatable settings panel.

**Available in Slovak and English.**

---

## Features

### Optimization
- Disable emoji scripts
- Disable oEmbed / embeds
- Remove RSS feeds
- Disable XML-RPC
- Remove jQuery Migrate
- Disable self-pingbacks
- Clean HTML head (RSD, WLW, shortlink, REST link)
- Block update emails
- Lazy load iframes & videos
- Disable Gravatars (GDPR)

### Security
- Hide WP and PHP version
- Disable file editor in admin
- Disable REST API for unauthenticated users
- Hide users via REST API and author archives
- Security HTTP headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- Hide updates for clients
- Hide login error messages (prevents username enumeration)
- Block `/?author=1` author scan

### SEO
- Discourage search engine indexing (dev mode)
- Noindex search results
- Noindex archives
- Remove feed links from `<head>`

### Appearance
- Custom login page logo
- Remove default dashboard widgets
- Hide admin menu for clients (CSS)
- Colored post statuses in list view
- Hide admin notices for non-admins

### Content & Media
- Disable comments sitewide
- Disable Gutenberg (block editor)
- Allow SVG uploads (DOMDocument allowlist sanitizer – safe)
- Lowercase filenames on upload
- Shortcode `[rok]` and token `$$rok$$` for current year
- Shortcode `[search_title]`
- Remove default block patterns
- Redirect attachment & date archives
- Redirect author archives

### Maintenance
- Maintenance mode (HTTP 503)
- Custom headline, message text, background color

### White Label
- Hide WP logo from admin bar
- Custom admin footer text (HTML supported)
- Hide "Howdy,"
- Hide admin bar on frontend for non-admins

### Scripts
- Google Tag Manager ID
- Custom `<head>` code
- Custom `</body>` code

### Performance
- Heartbeat API control (disable / 60s / 120s)
- Remove `?ver=` query strings from CSS/JS
- Disable large image auto-scaling
- Post revisions limit
- Disable Dashicons for visitors
- JPEG compression quality

### Admin Menu
- Drag & drop sidebar menu order
- Quick links toolbar (up to 8 links under ⚡ icon in admin bar)
- Quick links included in settings export/import

### System
- Dashboard info widget
- Track last login per user

---

## Requirements

- WordPress 6.0+
- PHP 8.0+

---

## Installation

**Option A – Git clone (recommended for auto-updates):**
```bash
cd wp-content/plugins/
git clone https://github.com/DatabenderSK/wp-zaklad.git
```

**Option B – ZIP download:**
Download the latest release ZIP from [Releases](https://github.com/DatabenderSK/wp-zaklad/releases), then upload via WP Admin → Plugins → Add New → Upload Plugin.

---

## Automatic Updates

The plugin uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) to deliver automatic update notifications directly in WP Admin → Plugins — just like official WordPress.org plugins, without being listed there.

**Update workflow:**
1. Make changes to the code
2. Update `Version:` in `wp-baseline.php` and `WPBL_VERSION` constant
3. `git commit && git push`
4. Create a new [GitHub Release](https://github.com/DatabenderSK/wp-zaklad/releases) with tag `v1.x.x`
5. All installed sites receive an update notification within 12 hours, or immediately via WP Admin → Updates → Check Again

---

## Export / Import

Settings can be exported and imported between sites via **Settings → WP Základ → Tools**.

The export includes all plugin settings **plus toolbar quick links**. Admin menu order is intentionally excluded (menu items differ between sites).

---

## License

GPL-2.0 or later. See [LICENSE](LICENSE).
