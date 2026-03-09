<?php
defined('ABSPATH') || exit;

class WPBL_Module_System extends WPBL_Module_Base {

    public function get_id(): string { return 'system'; }
    public function get_label(): string { return wpbl_t('tab_system'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_sysinfo_widget'   => 1,
            'wpzaklad_track_last_login' => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_sysinfo_widget',   'type' => 'checkbox', 'label' => wpbl_t('sysinfo_widget_label'),   'desc' => wpbl_t('sysinfo_widget_desc'), 'mine' => true],
            ['key' => 'wpzaklad_track_last_login',  'type' => 'checkbox', 'label' => wpbl_t('track_last_login_label'), 'desc' => wpbl_t('track_last_login_desc'), 'mine' => true],
        ];
    }

    public function render_custom_tab(): void {
        global $wpdb;
        $theme          = wp_get_theme();
        $active_plugins = count(get_option('active_plugins', []));
        $revisions      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $transients     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'");
        $is_https       = is_ssl();
        $server         = $_SERVER['SERVER_SOFTWARE'] ?? '—';
        $mysql_version  = $wpdb->db_version();
        $max_exec       = ini_get('max_execution_time');
        $wp_debug       = defined('WP_DEBUG') && WP_DEBUG;
        $wp_memory      = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : ini_get('memory_limit');

        $ext_list = [];
        foreach (['curl', 'gd', 'imagick', 'mbstring', 'zip', 'exif', 'intl'] as $ext) {
            $ext_list[] = extension_loaded($ext)
                ? '<span style="color:#00a32a;">&#10003; ' . $ext . '</span>'
                : '<span style="color:#c3c4c7;">&times; ' . $ext . '</span>';
        }
        ?>
        <div class="wpbl-setting">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('tools_sysinfo_title')); ?></strong>
                <table class="wpbl-sysinfo" style="margin-top:8px;">
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_site_name')); ?></td><td><?php echo esc_html(get_bloginfo('name')); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_site_url')); ?></td><td><?php echo esc_html(home_url()); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_protocol')); ?></td><td><?php echo $is_https ? 'HTTPS' : 'HTTP'; ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_wp_version')); ?></td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_wp_debug')); ?></td><td><?php echo $wp_debug ? '<span style="color:#d63638;font-weight:600;">ON</span>' : '<span style="color:#00a32a;">OFF</span>'; ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_wp_memory')); ?></td><td><?php echo esc_html($wp_memory); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_php_version')); ?></td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_php_max_exec')); ?></td><td><?php echo esc_html($max_exec); ?>s</td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_memory_limit')); ?></td><td><?php echo esc_html(ini_get('memory_limit')); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_php_extensions')); ?></td><td><?php echo implode(' &nbsp; ', $ext_list); // phpcs:ignore ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_mysql_version')); ?></td><td><?php echo esc_html($mysql_version ?: '—'); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_db_prefix')); ?></td><td><?php echo esc_html($wpdb->prefix); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_server')); ?></td><td><?php echo esc_html($server); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_theme')); ?></td><td><?php echo esc_html($theme->get('Name')); ?> <?php echo esc_html($theme->get('Version')); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_active_plugins')); ?></td><td><?php echo esc_html($active_plugins); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_db_revisions')); ?></td><td><?php echo esc_html($revisions); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_db_transients')); ?></td><td><?php echo esc_html($transients); ?></td></tr>
                    <tr><td><?php echo esc_html(wpbl_t('sysinfo_max_upload')); ?></td><td><?php echo esc_html(size_format(wp_max_upload_size())); ?></td></tr>
                </table>
            </div>
        </div>
        <?php
    }

    public function init(): void {
        if ($this->get('wpzaklad_sysinfo_widget')) {
            add_action('wp_dashboard_setup',     [$this, 'register_system_info_widget']);
            add_action('admin_enqueue_scripts',  [$this, 'enqueue_widget_styles']);
        }
        if ($this->get('wpzaklad_track_last_login')) {
            add_action('wp_login',               [$this, 'track_login'], 10, 2);
            add_filter('manage_users_columns',   [$this, 'add_last_login_column']);
            add_filter('manage_users_custom_column', [$this, 'show_last_login_column'], 10, 3);
        }
    }

    public function enqueue_widget_styles(string $hook): void {
        if ($hook !== 'index.php') return;
        $css = '
            .wpbl-sysinfo-widget table{width:100%;border-collapse:collapse;}
            .wpbl-sysinfo-widget tr{border-bottom:1px solid #f0f0f1;}
            .wpbl-sysinfo-widget tr:last-child{border-bottom:none;}
            .wpbl-sysinfo-widget td{padding:7px 4px;font-size:12px;vertical-align:middle;}
            .wpbl-sysinfo-widget td:first-child{color:#646970;font-weight:600;width:42%;white-space:nowrap;}
            .wpbl-sysinfo-widget td:last-child{color:#1d2327;}
            .wpbl-sysinfo-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;}
            .wpbl-sysinfo-badge.badge-http{background:#fee2e2;color:#991b1b;}
        ';
        wp_add_inline_style('wp-admin', $css);
    }

    public function register_system_info_widget(): void {
        wp_add_dashboard_widget(
            'wpbl_system_info_widget',
            wpbl_t('tools_sysinfo_title'),
            [$this, 'render_system_info_widget']
        );
    }

    public function render_system_info_widget(): void {
        $is_https = is_ssl();
        $server   = $_SERVER['SERVER_SOFTWARE'] ?? '—';
        ?>
        <div class="wpbl-sysinfo-widget">
            <table>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_site_name')); ?></td>
                    <td><?php echo esc_html(get_bloginfo('name')); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_site_url')); ?></td>
                    <td><?php echo esc_html(home_url()); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_protocol')); ?></td>
                    <td>
                        <span class="wpbl-sysinfo-badge<?php echo $is_https ? '' : ' badge-http'; ?>">
                            <?php echo $is_https ? 'HTTPS' : 'HTTP'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_wp_version')); ?></td>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_php_version')); ?></td>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_server')); ?></td>
                    <td><?php echo esc_html($server); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_memory_limit')); ?></td>
                    <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html(wpbl_t('sysinfo_max_upload')); ?></td>
                    <td><?php echo esc_html(size_format(wp_max_upload_size())); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function track_login(string $user_login, \WP_User $user): void {
        update_user_meta($user->ID, 'wpzaklad_last_login', current_time('mysql'));
    }

    public function add_last_login_column(array $columns): array {
        $columns['wpzaklad_last_login'] = wpbl_t('last_login_column');
        return $columns;
    }

    public function show_last_login_column(string $output, string $column, int $user_id): string {
        if ($column !== 'wpzaklad_last_login') {
            return $output;
        }
        $last_login = get_user_meta($user_id, 'wpzaklad_last_login', true);
        if (!$last_login) {
            return esc_html(wpbl_t('never_logged_in'));
        }
        return esc_html(date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($last_login)
        ));
    }
}
