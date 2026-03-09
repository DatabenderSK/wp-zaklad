<?php
defined('ABSPATH') || exit;

class WPBL_Settings {

    const OPTION_KEY = 'wpzaklad_settings';

    private array $defaults = [];
    private array $data     = [];
    private bool  $loaded   = false;

    /** Called by each module to register its default values */
    public function register_defaults(array $defaults): void {
        $this->defaults = array_merge($this->defaults, $defaults);
    }

    public function get_defaults(): array {
        return $this->defaults;
    }

    /** Load from DB once, merge with defaults */
    public function load(): void {
        if ($this->loaded) return;
        $stored     = get_option(self::OPTION_KEY, []);
        $this->data = is_array($stored) ? wp_parse_args($stored, $this->defaults) : $this->defaults;
        $this->loaded = true;
    }

    public function get(string $key) {
        $this->load();
        return $this->data[$key] ?? $this->defaults[$key] ?? null;
    }

    public function get_all(): array {
        $this->load();
        return $this->data;
    }

    public function save(array $data): bool {
        $this->data   = $data;
        $this->loaded = true;
        return (bool) update_option(self::OPTION_KEY, $data, false);
    }

    /** Merge partial data into existing settings without replacing unrelated keys. */
    public function patch(array $data): bool {
        $this->load();
        $this->data = array_merge($this->data, $data);
        return (bool) update_option(self::OPTION_KEY, $this->data, false);
    }

    public function export(): string {
        $data = $this->get_all();

        // Include toolbar quick links in export (stored separately, excluded: menu order)
        $toolbar = get_option('wpzaklad_admin_menu_toolbar', []);
        if (!empty($toolbar)) {
            $data['_toolbar_links'] = $toolbar;
        }

        return wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function import(string $json): bool {
        $data = json_decode(wp_unslash($json), true);
        if (!is_array($data)) return false;

        // Restore toolbar quick links if present in the export
        if (isset($data['_toolbar_links']) && is_array($data['_toolbar_links'])) {
            $clean_toolbar = [];
            foreach ($data['_toolbar_links'] as $item) {
                if (!is_array($item)) continue;
                $title = sanitize_text_field(wp_unslash((string) ($item['title'] ?? '')));
                $url   = sanitize_text_field(wp_unslash((string) ($item['url'] ?? '')));
                $url   = preg_replace('#^https?://[^/]+(/wp-admin/)?#', '', $url);
                $url   = ltrim($url, '/');
                if ($title !== '' || $url !== '') {
                    $clean_toolbar[] = ['title' => $title, 'url' => $url];
                }
            }
            update_option('wpzaklad_admin_menu_toolbar', $clean_toolbar);
        }

        // Only import keys we know about, and sanitize each value by type.
        $sanitized = $this->defaults;
        foreach ($this->defaults as $key => $default) {
            if (array_key_exists($key, $data)) {
                $sanitized[$key] = $this->sanitize_import_value($key, $data[$key], $default);
            }
        }
        return $this->save($sanitized);
    }

    /**
     * Sanitize a single imported value.
     *
     * Mirrors the field-level sanitization that the settings form applies so that
     * the import path does not bypass it.  Known raw-code and kses fields are
     * handled explicitly; everything else is sanitized by the type of its default.
     */
    private function sanitize_import_value(string $key, mixed $value, mixed $default): mixed {
        // Fields that store arbitrary HTML / JavaScript (admin-only by design)
        $raw_fields = ['wpzaklad_head_code', 'wpzaklad_footer_code', 'wpzaklad_critical_css_code'];
        if (in_array($key, $raw_fields, true)) {
            return wp_unslash((string) $value);
        }

        // Fields that allow limited HTML (no scripts)
        $kses_fields = ['wpzaklad_maintenance_text', 'wpzaklad_admin_footer_text'];
        if (in_array($key, $kses_fields, true)) {
            return wp_kses_post(wp_unslash((string) $value));
        }

        // Hex color fields
        $color_fields = [
            'wpzaklad_maintenance_bg', 'wpzaklad_color_draft', 'wpzaklad_color_pending',
            'wpzaklad_color_private', 'wpzaklad_color_future', 'wpzaklad_login_bg_color',
        ];
        if (in_array($key, $color_fields, true)) {
            return sanitize_hex_color((string) $value) ?: (is_string($default) ? $default : '');
        }

        // URL / media fields
        $url_fields = ['wpzaklad_custom_login_logo_url'];
        if (in_array($key, $url_fields, true)) {
            return esc_url_raw((string) $value);
        }

        // Integer defaults → integer values (covers all checkbox 0/1 fields and numeric fields)
        if (is_int($default)) {
            return (int) $value;
        }

        // Select / text fields
        return sanitize_text_field(wp_unslash((string) $value));
    }

    public function reset(): bool {
        $this->data   = $this->defaults;
        $this->loaded = true;
        return (bool) update_option(self::OPTION_KEY, $this->defaults, false);
    }
}
