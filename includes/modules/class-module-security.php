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
            'wpzaklad_login_honeypot'            => 0,
            'wpzaklad_disable_app_passwords'     => 0,
            'wpzaklad_disable_php_uploads'       => 0,
            'wpzaklad_custom_login_slug'         => '',
            'wpzaklad_extend_login_expiry'       => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_hide_wp_version',          'type' => 'checkbox', 'label' => wpbl_t('hide_wp_version_label'),          'desc' => wpbl_t('hide_wp_version_desc'),          'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_file_edit',        'type' => 'checkbox', 'label' => wpbl_t('disable_file_edit_label'),         'desc' => wpbl_t('disable_file_edit_desc'),        'recommended' => true],
            ['key' => 'wpzaklad_disable_rest_unauth',      'type' => 'checkbox', 'label' => wpbl_t('disable_rest_unauth_label'),       'desc' => wpbl_t('disable_rest_unauth_desc')],
            ['key' => 'wpzaklad_disable_user_rest',        'type' => 'checkbox', 'label' => wpbl_t('disable_user_rest_label'),         'desc' => wpbl_t('disable_user_rest_desc'),        'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_security_headers',         'type' => 'checkbox', 'label' => wpbl_t('security_headers_label'),          'desc' => wpbl_t('security_headers_desc'),         'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_hide_updates_for_clients', 'type' => 'checkbox', 'label' => wpbl_t('hide_updates_label'),              'desc' => wpbl_t('hide_updates_desc')],
            ['key' => 'wpzaklad_hide_login_errors',        'type' => 'checkbox', 'label' => wpbl_t('hide_login_errors_label'),         'desc' => wpbl_t('hide_login_errors_desc'),        'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_block_author_scan',        'type' => 'checkbox', 'label' => wpbl_t('block_author_scan_label'),         'desc' => wpbl_t('block_author_scan_desc'),        'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_login_honeypot',           'type' => 'checkbox', 'label' => wpbl_t('login_honeypot_label'),            'desc' => wpbl_t('login_honeypot_desc'),           'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_app_passwords',    'type' => 'checkbox', 'label' => wpbl_t('disable_app_passwords_label'),     'desc' => wpbl_t('disable_app_passwords_desc'),    'recommended' => true],
            ['key' => 'wpzaklad_disable_php_uploads',      'type' => 'checkbox', 'label' => wpbl_t('disable_php_uploads_label'),       'desc' => wpbl_t('disable_php_uploads_desc'),      'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_custom_login_slug',        'type' => 'text',     'label' => wpbl_t('custom_login_slug_label'),         'desc' => wpbl_t('custom_login_slug_desc')],
            ['key' => 'wpzaklad_extend_login_expiry',      'type' => 'checkbox', 'label' => wpbl_t('extend_login_expiry_label'),      'desc' => wpbl_t('extend_login_expiry_desc'),      'mine' => true],
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

        if ($this->get('wpzaklad_login_honeypot')) {
            add_action('login_form',          [$this, 'add_honeypot_field']);
            add_filter('wp_authenticate_user', [$this, 'check_honeypot'], 1);
        }

        if ($this->get('wpzaklad_disable_app_passwords')) {
            add_filter('wp_is_application_passwords_available', '__return_false');
        }

        if ($this->get('wpzaklad_disable_php_uploads')) {
            add_action('init', [$this, 'ensure_uploads_htaccess']);
        } else {
            add_action('init', [$this, 'remove_uploads_htaccess']);
        }

        if ($this->get('wpzaklad_extend_login_expiry')) {
            add_filter('auth_cookie_expiration', fn() => 30 * DAY_IN_SECONDS);
        }

        // Allow letters, numbers, hyphens, underscores, dots (so vphovno.php works)
        $slug = preg_replace('/[^a-z0-9\-_.]/i', '', (string) $this->get('wpzaklad_custom_login_slug'));
        $slug = strtolower(trim($slug, '-_.'));
        if ($slug !== '') {
            $this->setup_custom_login_url($slug);
        }
    }

    public function add_honeypot_field(): void {
        // Hidden via inline style – bots fill it, humans don't see it
        echo '<div style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;" aria-hidden="true"><input type="text" name="wpbl_hp" value="" tabindex="-1" autocomplete="off"></div>' . "\n"; // phpcs:ignore
    }

    public function check_honeypot(\WP_User|\WP_Error $user): \WP_User|\WP_Error {
        if (!empty($_POST['wpbl_hp'])) {
            return new \WP_Error('honeypot', esc_html(wpbl_t('login_error_generic')));
        }
        return $user;
    }

    public function ensure_uploads_htaccess(): void {
        $upload_dir = wp_upload_dir();
        $htaccess   = $upload_dir['basedir'] . '/.htaccess';
        if (file_exists($htaccess)) return;
        $content = "# WP Zaklad – block PHP execution\n"
                 . "<FilesMatch \"\.(php|php3|php4|php5|phtml|phar)$\">\n"
                 . "  Order Deny,Allow\n  Deny from all\n"
                 . "</FilesMatch>\n";
        file_put_contents($htaccess, $content); // phpcs:ignore
    }

    public function remove_uploads_htaccess(): void {
        $upload_dir = wp_upload_dir();
        $htaccess   = $upload_dir['basedir'] . '/.htaccess';
        if (!file_exists($htaccess)) return;
        $content = file_get_contents($htaccess); // phpcs:ignore
        if ($content !== false && strpos($content, '# WP Zaklad') !== false) {
            unlink($htaccess); // phpcs:ignore
        }
    }

    private function setup_custom_login_url(string $slug): void {
        // Serve login form at custom slug
        add_action('init', function () use ($slug) {
            $request = trim(parse_url(
                wp_unslash($_SERVER['REQUEST_URI'] ?? ''),
                PHP_URL_PATH
            ) ?? '', '/');

            // Custom slug hit → serve the login form
            if ($request === $slug) {
                // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomFunction
                require_once ABSPATH . 'wp-login.php';
                exit;
            }

            // Block direct wp-login.php access (allow password-reset key links)
            global $pagenow;
            if ($pagenow === 'wp-login.php' && !isset($_GET['key'])) {
                wp_safe_redirect(home_url('/'), 302);
                exit;
            }
        }, 1);

        // Rewrite login URL throughout WP
        $custom_url = home_url('/' . $slug);
        add_filter('login_url',          fn(string $u) => add_query_arg(array_diff_key(parse_url($u, PHP_URL_QUERY) ? wp_parse_args(parse_url($u, PHP_URL_QUERY)) : [], []), $custom_url), 10, 3);
        add_filter('site_url',           fn(string $u, string $p) => str_contains($p, 'wp-login.php') ? str_replace('wp-login.php', $slug, $u) : $u, 10, 2);
        add_filter('network_site_url',   fn(string $u, string $p) => str_contains($p, 'wp-login.php') ? str_replace('wp-login.php', $slug, $u) : $u, 10, 2);
        add_filter('wp_redirect',        fn(string $u) => str_contains($u, 'wp-login.php') ? str_replace('wp-login.php', $slug, $u) : $u);
    }

    public function restrict_rest_api($result) {
        if (!empty($result)) {
            return $result;
        }
        if (is_user_logged_in()) {
            return $result;
        }

        // Whitelist known plugin REST namespaces
        $whitelist = [
            'fluentform',
            'frm',
            'contact-form-7',
            'wpforms',
            'generateblocks',
        ];
        $whitelist = apply_filters('wpzaklad_rest_whitelist', $whitelist);

        $rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        if ($rest_route === '') {
            $rest_route = $_SERVER['REQUEST_URI'] ?? '';
        }

        foreach ($whitelist as $namespace) {
            if (strpos($rest_route, '/' . $namespace . '/') !== false || strpos($rest_route, '/' . $namespace) === strlen($rest_route) - strlen('/' . $namespace)) {
                return $result;
            }
        }

        return new WP_Error(
            'rest_not_logged_in',
            'REST API requires authentication.',
            ['status' => 401]
        );
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
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            header('X-XSS-Protection: 1; mode=block');
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
