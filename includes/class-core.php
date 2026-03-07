<?php
defined('ABSPATH') || exit;

class WPBL_Core {

    private static ?self $instance = null;

    private WPBL_Settings $settings;

    /** @var WPBL_Module_Base[] */
    private array $modules = [];

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        $this->settings = new WPBL_Settings();
        $this->load_modules();
        $this->settings->load();
        $this->init_modules();
        $this->init_admin();
    }

    private function load_modules(): void {
        $module_classes = [
            WPBL_Module_Optimization::class,
            WPBL_Module_Seo::class,
            WPBL_Module_Security::class,
            WPBL_Module_Appearance::class,
            WPBL_Module_Content::class,
            WPBL_Module_Maintenance::class,
            WPBL_Module_Whitelabel::class,
            WPBL_Module_Scripts::class,
            WPBL_Module_Performance::class,
            WPBL_Module_Admin_Menu::class,
            WPBL_Module_System::class,
        ];

        foreach ($module_classes as $class) {
            $module = new $class($this->settings);
            $this->settings->register_defaults($module->get_defaults());
            $this->modules[] = $module;
        }
    }

    private function init_modules(): void {
        // Re-load after all defaults registered, then init
        $this->settings->load();
        foreach ($this->modules as $module) {
            $module->init();
        }
    }

    private function init_admin(): void {
        if (!is_admin()) return;
        $admin = new WPBL_Admin_UI($this->settings, $this->modules);
        $admin->init();
    }
}
