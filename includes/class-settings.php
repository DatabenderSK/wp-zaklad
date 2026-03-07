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

    public function export(): string {
        return wp_json_encode($this->get_all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function import(string $json): bool {
        $data = json_decode(wp_unslash($json), true);
        if (!is_array($data)) return false;

        // Only import keys we know about
        $sanitized = $this->defaults;
        foreach ($this->defaults as $key => $default) {
            if (array_key_exists($key, $data)) {
                $sanitized[$key] = $data[$key];
            }
        }
        return $this->save($sanitized);
    }

    public function reset(): bool {
        $this->data   = $this->defaults;
        $this->loaded = true;
        return (bool) update_option(self::OPTION_KEY, $this->defaults, false);
    }
}
