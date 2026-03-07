<?php
defined('ABSPATH') || exit;

class WPBL_Module_Security extends WPBL_Module_Base {

    public function get_id(): string { return 'security'; }
    public function get_label(): string { return wpbl_t('tab_security'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_hide_wp_version'           => 0,
            'wpzaklad_disable_file_edit'         => 0,
            'wpzaklad_disable_rest_unauth'       => 0,
            'wpzaklad_disable_user_rest'         => 0,
            'wpzaklad_security_headers'          => 0,
            'wpzaklad_hide_updates_for_clients'  => 0,
            'wpzaklad_hide_login_errors'         => 0,
            'wpzaklad_block_author_scan'         => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_hide_wp_version',          'type' => 'checkbox', 'label' => wpbl_t('hide_wp_version_label'),     'desc' => wpbl_t('hide_wp_version_desc'),     'recommended' => true],
            ['key' => 'wpzaklad_disable_file_edit',        'type' => 'checkbox', 'label' => wpbl_t('disable_file_edit_label'),   'desc' => wpbl_t('disable_file_edit_desc'),   'recommended' => true],
            ['key' => 'wpzaklad_disable_rest_unauth',      'type' => 'checkbox', 'label' => wpbl_t('disable_rest_unauth_label'), 'desc' => wpbl_t('disable_rest_unauth_desc')],
            ['key' => 'wpzaklad_disable_user_rest',        'type' => 'checkbox', 'label' => wpbl_t('disable_user_rest_label'),   'desc' => wpbl_t('disable_user_rest_desc'),   'recommended' => true],
            ['key' => 'wpzaklad_security_headers',         'type' => 'checkbox', 'label' => wpbl_t('security_headers_label'),    'desc' => wpbl_t('security_headers_desc'),    'recommended' => true],
            ['key' => 'wpzaklad_hide_updates_for_clients', 'type' => 'checkbox', 'label' => wpbl_t('hide_updates_label'),        'desc' => wpbl_t('hide_updates_desc')],
            ['key' => 'wpzaklad_hide_login_errors',        'type' => 'checkbox', 'label' => wpbl_t('hide_login_errors_label'),   'desc' => wpbl_t('hide_login_errors_desc'),   'recommended' => true],
            ['key' => 'wpzaklad_block_author_scan',        'type' => 'checkbox', 'label' => wpbl_t('block_author_scan_label'),   'desc' => wpbl_t('block_author_scan_desc'),   'recommended' => true],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_hide_wp_version')) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
            add_action('send_headers', function () { header_remove('X-Powered-By'); });
        }

        if ($this->get('wpzaklad_disable_file_edit')) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
            // Belt-and-suspenders: also remove editor menu items directly.
            // The constant is the authoritative guard; menu removal covers edge cases
            // where the constant may be evaluated before plugins load.
            add_action('admin_menu', static function () {
                remove_submenu_page('themes.php', 'theme-editor.php');
                remove_submenu_page('plugins.php', 'plugin-editor.php');
            }, 999);
        }

        if ($this->get('wpzaklad_disable_rest_unauth')) {
            add_filter('rest_authentication_errors', [$this, 'restrict_rest_api']);
        }

        if ($this->get('wpzaklad_disable_user_rest')) {
            add_filter('rest_endpoints', [$this, 'remove_user_endpoints']);
            add_action('template_redirect', [$this, 'redirect_author_archive']);
        }

        if ($this->get('wpzaklad_hide_updates_for_clients')) {
            add_action('init', [$this, 'apply_hide_updates_for_clients']);
        }

        if ($this->get('wpzaklad_security_headers')) {
            add_action('send_headers', [$this, 'send_security_headers']);
        }

        if ($this->get('wpzaklad_hide_login_errors')) {
            add_filter('login_errors', fn() => wpbl_t('login_error_generic'));
            add_filter('login_shake_error_codes', '__return_empty_array');
        }

        if ($this->get('wpzaklad_block_author_scan')) {
            add_action('template_redirect', function () {
                if (!is_admin() && isset($_GET['author'])) {
                    wp_safe_redirect(home_url('/'), 301);
                    exit;
                }
            });
        }
    }

    public function restrict_rest_api($result) {
        if (!empty($result)) {
            return $result;
        }
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                'REST API requires authentication.',
                ['status' => 401]
            );
        }
        return $result;
    }

    public function remove_user_endpoints(array $endpoints): array {
        $user_endpoints = ['/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)'];
        foreach ($user_endpoints as $endpoint) {
            if (isset($endpoints[$endpoint])) {
                unset($endpoints[$endpoint]);
            }
        }
        return $endpoints;
    }

    public function send_security_headers(): void {
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    public function redirect_author_archive(): void {
        if (is_author()) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    public function apply_hide_updates_for_clients(): void {
        if (current_user_can('manage_options')) return;
        add_filter('pre_site_transient_update_core',    '__return_null');
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes',  '__return_null');
        add_action('admin_menu', function () {
            remove_submenu_page('index.php', 'update-core.php');
        });
    }
}
