<?php
/**
 * Plugin Name:  WP Základ
 * Plugin URI:   https://github.com/DatabenderSK/wp-zaklad/
 * Description:  Baseline configuration for every WordPress site. Optimizes, secures and customizes WP out of the box with a modular, translatable settings panel.
 * Version:      1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:       Martin Pavlič
 * Author URI:   https://martinpavlic.sk/
 * License:      GPL-2.0+
 * Text Domain:  wp-baseline
 */

defined('ABSPATH') || exit;

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------
define('WPBL_VERSION',  '1.1.0');
define('WPBL_DIR',      plugin_dir_path(__FILE__));
define('WPBL_URL',      plugin_dir_url(__FILE__));
define('WPBL_BASENAME', plugin_basename(__FILE__));

// -------------------------------------------------------------------------
// Auto-update via GitHub (plugin-update-checker v5)
// Uses GitHub Releases – create a Release tagged "v1.0.x" for each version.
// -------------------------------------------------------------------------
$puc_file = WPBL_DIR . 'libs/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_file)) {
    require_once $puc_file;
    YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/DatabenderSK/wp-zaklad/',
        __FILE__,
        'wp-zaklad'
    );
    // No setBranch() – PUC defaults to GitHub Releases.
    // Version is read from the Release tag (e.g. "v1.0.8").
}

// -------------------------------------------------------------------------
// Autoloader
// Maps WPBL_Foo_Bar → includes/class-foo-bar.php
//      WPBL_Module_Foo → includes/modules/class-module-foo.php
// -------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'WPBL_') !== 0) return;

    // e.g. WPBL_Admin_UI → admin-ui
    $name = strtolower(str_replace('_', '-', substr($class, 5)));

    $candidates = [
        WPBL_DIR . 'includes/class-' . $name . '.php',
        WPBL_DIR . 'includes/modules/class-' . $name . '.php',
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// -------------------------------------------------------------------------
// Language helper (must load before Core so wpbl_t() is available)
// -------------------------------------------------------------------------
require_once WPBL_DIR . 'includes/class-lang.php';

// -------------------------------------------------------------------------
// One-time migration: wpbl_settings → wpzaklad_settings
// Runs automatically on first load after rename (no reactivation needed)
// -------------------------------------------------------------------------
add_action('plugins_loaded', static function (): void {
    if (get_option(WPBL_Settings::OPTION_KEY) === false) {
        $old = get_option('wpbl_settings', false);
        if (is_array($old)) {
            update_option(WPBL_Settings::OPTION_KEY, $old, false);
        } else {
            update_option(WPBL_Settings::OPTION_KEY, [
                'wpzaklad_disable_emoji'           => 1,
                'wpzaklad_disable_embeds'          => 0,
                'wpzaklad_remove_rss'              => 0,
                'wpzaklad_disable_xmlrpc'          => 1,
                'wpzaklad_remove_jquery_migrate'   => 1,
                'wpzaklad_disable_self_pingbacks'  => 1,
                'wpzaklad_noindex_search'          => 1,
                'wpzaklad_noindex_archives'        => 0,

                'wpzaklad_hide_wp_version'         => 1,
                'wpzaklad_disable_file_edit'       => 0,
                'wpzaklad_disable_rest_unauth'     => 0,
                'wpzaklad_disable_user_rest'       => 1,
                'wpzaklad_security_headers'        => 1,
                'wpzaklad_custom_login_logo'       => 1,
                'wpzaklad_custom_login_logo_url'   => '',
                'wpzaklad_disable_comments'        => 1,
                'wpzaklad_disable_gutenberg'       => 0,
                'wpzaklad_maintenance_mode'        => 0,
                'wpzaklad_maintenance_headline'    => 'Stránka je dočasne nedostupná',
                'wpzaklad_maintenance_text'        => 'Pracujeme na aktualizácii. Čoskoro budeme späť.',
                'wpzaklad_maintenance_bg'          => '#1a1a2e',
                'wpzaklad_hide_wp_logo'            => 1,
                'wpzaklad_admin_footer_text'       => '',
                'wpzaklad_hide_howdy'              => 1,
                'wpzaklad_hide_frontend_bar'       => 0,
                'wpzaklad_gtm_id'                  => '',
                'wpzaklad_head_code'               => '',
                'wpzaklad_footer_code'             => '',
                'wpzaklad_heartbeat'               => '',
                'wpzaklad_remove_query_strings'    => 1,
                'wpzaklad_disable_big_image'       => 0,
                'wpzaklad_revisions_limit'         => 10,
            ], false);
        }
    }
    if (get_option('wpzaklad_language') === false) {
        update_option('wpzaklad_language', get_option('wpbl_language', 'sk'), false);
    }

    // Migrate remove_feed_links (SEO duplicate) → remove_rss (Optimization)
    $settings = get_option(WPBL_Settings::OPTION_KEY, []);
    if (is_array($settings) && !empty($settings['wpzaklad_remove_feed_links'])) {
        $settings['wpzaklad_remove_rss'] = 1;
        unset($settings['wpzaklad_remove_feed_links']);
        update_option(WPBL_Settings::OPTION_KEY, $settings, false);
    }
}, 1);

// -------------------------------------------------------------------------
// Boot
// -------------------------------------------------------------------------
add_action('plugins_loaded', static function (): void {
    WPBL_Core::get_instance()->init();
}, 5);

// -------------------------------------------------------------------------
// Activation – write default settings to DB on first activation
// -------------------------------------------------------------------------
register_activation_hook(__FILE__, static function (): void {
    if (get_option(WPBL_Settings::OPTION_KEY) === false) {
        // Migrate from old option key (wpbl_settings) if present
        $old_settings = get_option('wpbl_settings', false);
        if (is_array($old_settings)) {
            update_option(WPBL_Settings::OPTION_KEY, $old_settings, false);
        } else {
            // Fresh install – write opinionated defaults
            update_option(WPBL_Settings::OPTION_KEY, [
                'wpzaklad_disable_emoji'           => 1,
                'wpzaklad_disable_embeds'          => 0,
                'wpzaklad_remove_rss'              => 0,
                'wpzaklad_disable_xmlrpc'          => 1,
                'wpzaklad_remove_jquery_migrate'   => 1,
                'wpzaklad_disable_self_pingbacks'  => 1,
                'wpzaklad_noindex_search'          => 1,
                'wpzaklad_noindex_archives'        => 0,

                'wpzaklad_hide_wp_version'         => 1,
                'wpzaklad_disable_file_edit'       => 0,
                'wpzaklad_disable_rest_unauth'     => 0,
                'wpzaklad_disable_user_rest'       => 1,
                'wpzaklad_security_headers'        => 1,
                'wpzaklad_custom_login_logo'       => 1,
                'wpzaklad_custom_login_logo_url'   => '',
                'wpzaklad_disable_comments'        => 1,
                'wpzaklad_disable_gutenberg'       => 0,
                'wpzaklad_maintenance_mode'        => 0,
                'wpzaklad_maintenance_headline'    => 'Stránka je dočasne nedostupná',
                'wpzaklad_maintenance_text'        => 'Pracujeme na aktualizácii. Čoskoro budeme späť.',
                'wpzaklad_maintenance_bg'          => '#1a1a2e',
                'wpzaklad_hide_wp_logo'            => 1,
                'wpzaklad_admin_footer_text'       => '',
                'wpzaklad_hide_howdy'              => 1,
                'wpzaklad_hide_frontend_bar'       => 0,
                'wpzaklad_gtm_id'                  => '',
                'wpzaklad_head_code'               => '',
                'wpzaklad_footer_code'             => '',
                'wpzaklad_heartbeat'               => '',
                'wpzaklad_remove_query_strings'    => 1,
                'wpzaklad_disable_big_image'       => 0,
                'wpzaklad_revisions_limit'         => 10,
            ], false);
        }
    }

    // Migrate language option from old key
    if (get_option('wpzaklad_language') === false) {
        $old_lang = get_option('wpbl_language', 'sk');
        update_option('wpzaklad_language', $old_lang, false);
    }
});
