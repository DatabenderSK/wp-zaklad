<?php
defined('ABSPATH') || exit;

abstract class WPBL_Module_Base {

    protected WPBL_Settings $settings;

    public function __construct(WPBL_Settings $settings) {
        $this->settings = $settings;
    }

    /** Unique module slug, e.g. 'optimization' */
    abstract public function get_id(): string;

    /** Human-readable tab label (translated) */
    abstract public function get_label(): string;

    /** Default values for this module's settings keys */
    abstract public function get_defaults(): array;

    /**
     * Field definitions for the admin UI.
     * Each entry: [key, label, desc, type, is_new, options (for select), ...]
     */
    abstract public function get_fields(): array;

    /** Register WP hooks. Called after all defaults are loaded from the DB. */
    abstract public function init(): void;

    /** Optional: render additional HTML below standard fields in the module tab. */
    public function render_custom_tab(): void {}

    /** Optional: save additional (non-standard) data when the settings form is submitted. */
    public function save_custom_data(array $post): void {}

    /** Convenience accessor */
    protected function get(string $key) {
        return $this->settings->get($key);
    }
}
