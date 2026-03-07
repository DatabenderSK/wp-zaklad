<?php
defined('ABSPATH') || exit;

class WPBL_Admin_UI {

    private WPBL_Settings $settings;

    /** @var WPBL_Module_Base[] */
    private array $modules;

    public function __construct(WPBL_Settings $settings, array $modules) {
        $this->settings = $settings;
        $this->modules  = $modules;
    }

    public function init(): void {
        add_action('admin_menu',             [$this, 'add_menu']);
        add_action('admin_enqueue_scripts',  [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts',  [$this, 'enqueue_dashboard_assets']);
        add_action('admin_init',             [$this, 'handle_form_actions']);
        add_action('admin_bar_menu',         [$this, 'add_admin_bar_warnings'], 100);
        add_action('admin_head',             [$this, 'output_admin_bar_warning_css']);
        add_action('wp_head',                [$this, 'output_admin_bar_warning_css']);
        add_action('wp_dashboard_setup',     [$this, 'register_dashboard_widget']);
        add_filter('plugin_action_links_' . WPBL_BASENAME, [$this, 'plugin_action_links']);
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function add_menu(): void {
        add_options_page(
            wpbl_t('page_title'),
            wpbl_t('menu_title'),
            'manage_options',
            'wp-zaklad',
            [$this, 'render_page']
        );
    }

    // -------------------------------------------------------------------------
    // Warnings
    // -------------------------------------------------------------------------

    private function get_active_warnings(): array {
        $warnings = [];
        if (get_option('blog_public') == 0) {
            $warnings[] = 'noindex';
        }
        if ($this->settings->get('wpzaklad_maintenance_mode')) {
            $warnings[] = 'maintenance';
        }
        return $warnings;
    }

    public function add_admin_bar_warnings(\WP_Admin_Bar $wp_admin_bar): void {
        if (!current_user_can('manage_options')) return;

        $warnings = $this->get_active_warnings();

        if (in_array('noindex', $warnings, true)) {
            $wp_admin_bar->add_node([
                'id'    => 'wpbl-noindex-warning',
                'title' => '&#9888; ' . wpbl_t('warning_noindex'),
                'href'  => admin_url('options-reading.php'),
                'meta'  => ['class' => 'wpbl-bar-warning'],
            ]);
        }

        if (in_array('maintenance', $warnings, true)) {
            $wp_admin_bar->add_node([
                'id'    => 'wpbl-maintenance-warning',
                'title' => '&#9888; ' . wpbl_t('warning_maintenance'),
                'href'  => admin_url('options-general.php?page=wp-zaklad#tab-maintenance'),
                'meta'  => ['class' => 'wpbl-bar-warning'],
            ]);
        }
    }

    public function output_admin_bar_warning_css(): void {
        if (!$this->get_active_warnings()) return;
        if (!current_user_can('manage_options')) return;
        echo '<style>#wp-admin-bar-wpbl-noindex-warning .ab-item,#wp-admin-bar-wpbl-maintenance-warning .ab-item{background:#d63638!important;color:#fff!important;}</style>' . "\n";
    }

    // -------------------------------------------------------------------------
    // Dashboard widget
    // -------------------------------------------------------------------------

    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'wpbl_dashboard_widget',
            'WP Základ',
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget(): void {
        $warnings    = $this->get_active_warnings();
        $gtm_id      = $this->settings->get('wpzaklad_gtm_id');
        $head_code   = $this->settings->get('wpzaklad_head_code');
        $footer_code = $this->settings->get('wpzaklad_footer_code');
        $plugin_url  = admin_url('options-general.php?page=wp-zaklad');
        $scripts_url = admin_url('options-general.php?page=wp-zaklad#tab-scripts');
        ?>
        <div class="wpbl-widget">
            <?php foreach ($warnings as $warning): ?>
            <div class="wpbl-widget-alert wpbl-widget-alert-<?php echo esc_attr($warning); ?>">
                <?php echo esc_html(wpbl_t('widget_warning_' . $warning)); ?>
            </div>
            <?php endforeach; ?>

            <div class="wpbl-widget-section">
                <div class="wpbl-widget-section-header">
                    <strong><?php echo esc_html(wpbl_t('widget_scripts_title')); ?></strong>
                    <a href="<?php echo esc_url($scripts_url); ?>"><?php echo esc_html(wpbl_t('widget_configure')); ?> &rarr;</a>
                </div>
                <ul>
                    <li><?php echo esc_html(wpbl_t('widget_gtm')); ?>: <strong><?php echo $gtm_id ? esc_html($gtm_id) : esc_html(wpbl_t('widget_not_set')); ?></strong></li>
                    <li><?php echo esc_html(wpbl_t('widget_head_code')); ?>: <strong><?php echo $head_code ? esc_html(wpbl_t('widget_yes')) : esc_html(wpbl_t('widget_no')); ?></strong></li>
                    <li><?php echo esc_html(wpbl_t('widget_footer_code')); ?>: <strong><?php echo $footer_code ? esc_html(wpbl_t('widget_yes')) : esc_html(wpbl_t('widget_no')); ?></strong></li>
                </ul>
            </div>

            <div class="wpbl-widget-footer">
                <a href="<?php echo esc_url($plugin_url); ?>" class="button button-small"><?php echo esc_html(wpbl_t('widget_settings_link')); ?> &rarr;</a>
            </div>
        </div>
        <?php
    }

    public function enqueue_dashboard_assets(string $hook): void {
        if ($hook !== 'index.php') return;
        $css = '
            .wpbl-widget-alert{padding:7px 10px;margin-bottom:8px;border-radius:3px;font-size:12px;font-weight:600;line-height:1.4;}
            .wpbl-widget-alert-maintenance{background:#fce8e8;color:#b01c1e;border:1px solid #d63638;}
            .wpbl-widget-alert-noindex{background:#fff3cd;color:#7a5000;border:1px solid #f0c040;}
            .wpbl-widget-section{margin-bottom:10px;}
            .wpbl-widget-section-header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;}
            .wpbl-widget-section-header strong{font-size:12px;color:#1d2327;}
            .wpbl-widget-section-header a{font-size:11px;}
            .wpbl-widget-section ul{margin:0;padding-left:16px;}
            .wpbl-widget-section li{font-size:12px;color:#646970;margin-bottom:2px;}
            .wpbl-widget-section li strong{font-size:12px;color:#1d2327;font-weight:600;}
            .wpbl-widget-footer{margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f1;}
        ';
        wp_add_inline_style('wp-admin', $css);
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_wp-zaklad') return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style(
            'wpbl-admin',
            WPBL_URL . 'admin/css/admin.css',
            [],
            WPBL_VERSION
        );

        wp_enqueue_script(
            'wpbl-admin',
            WPBL_URL . 'admin/js/admin.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable'],
            WPBL_VERSION,
            true
        );

        wp_localize_script('wpbl-admin', 'wpbl', [
            'resetConfirm' => wpbl_t('reset_confirm'),
            'flushConfirm' => wpbl_t('flush_transients_confirm'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Form handlers
    // -------------------------------------------------------------------------

    public function handle_form_actions(): void {
        if (empty($_POST['wpbl_action'])) return;
        if (!current_user_can('manage_options')) return;

        $action = sanitize_key($_POST['wpbl_action']);

        switch ($action) {
            case 'save':
                check_admin_referer('wpbl_save_settings', 'wpbl_nonce');
                $this->process_save();
                break;

            case 'import':
                check_admin_referer('wpbl_import', 'wpbl_nonce');
                $this->process_import();
                break;

            case 'reset':
                check_admin_referer('wpbl_reset', 'wpbl_nonce');
                $this->settings->reset();
                $this->redirect(3);
                break;

            case 'flush_transients':
                check_admin_referer('wpbl_flush', 'wpbl_nonce');
                $this->process_flush();
                break;

            case 'set_lang':
                check_admin_referer('wpbl_lang', 'wpbl_nonce');
                $lang = sanitize_key($_POST['wpbl_lang'] ?? 'sk');
                if (!in_array($lang, ['sk', 'en'], true)) $lang = 'sk';
                update_option('wpzaklad_language', $lang);
                $this->redirect(0);
                break;
        }
    }

    private function process_save(): void {
        $data = $this->sanitize_posted_settings($_POST);
        $this->settings->save($data);
        foreach ($this->modules as $module) {
            $module->save_custom_data($_POST);
        }
        $this->redirect(1);
    }

    private function process_import(): void {
        $json = wp_unslash($_POST['wpbl_import_json'] ?? '');
        if ($this->settings->import($json)) {
            $this->redirect(2);
        } else {
            $this->redirect('import_error');
        }
    }

    private function process_flush(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $this->redirect(4);
    }

    private function redirect(int|string $code): void {
        wp_redirect(admin_url('options-general.php?page=wp-zaklad&wpbl_updated=' . $code));
        exit;
    }

    // -------------------------------------------------------------------------
    // Sanitize
    // -------------------------------------------------------------------------

    private function sanitize_posted_settings(array $post): array {
        $sanitized = [];

        foreach ($this->modules as $module) {
            foreach ($module->get_fields() as $field) {
                $key      = $field['key'];
                $type     = $field['type'] ?? 'checkbox';
                $value    = $post[$key] ?? '';
                $sanitize = $field['sanitize'] ?? '';

                switch ($type) {
                    case 'checkbox':
                        $sanitized[$key] = isset($post[$key]) ? 1 : 0;
                        break;

                    case 'color':
                        $sanitized[$key] = sanitize_hex_color((string) $value) ?: '';
                        break;

                    case 'media':
                        $sanitized[$key] = esc_url_raw((string) $value);
                        break;

                    case 'number':
                        $sanitized[$key] = intval($value);
                        break;

                    case 'select':
                        $allowed = array_keys($field['options'] ?? []);
                        $sanitized[$key] = in_array($value, $allowed, true) ? $value : ($allowed[0] ?? '');
                        break;

                    case 'textarea':
                        if ($sanitize === 'raw') {
                            $sanitized[$key] = wp_unslash((string) $value);
                        } elseif ($sanitize === 'kses') {
                            $sanitized[$key] = wp_kses_post(wp_unslash((string) $value));
                        } else {
                            $sanitized[$key] = sanitize_textarea_field((string) $value);
                        }
                        break;

                    default: // text
                        $sanitized[$key] = sanitize_text_field(wp_unslash((string) $value));
                        break;
                }
            }
        }

        return $sanitized;
    }

    // -------------------------------------------------------------------------
    // Plugin list link
    // -------------------------------------------------------------------------

    public function plugin_action_links(array $links): array {
        $url  = admin_url('options-general.php?page=wp-zaklad');
        $link = '<a href="' . esc_url($url) . '">' . esc_html(wpbl_t('plugin_settings_link')) . '</a>';
        array_unshift($links, $link);

        $count = 0;
        foreach ($this->modules as $module) {
            foreach ($module->get_fields() as $field) {
                if (($field['type'] ?? '') === 'checkbox' && $this->settings->get($field['key'])) {
                    $count++;
                }
            }
        }
        if ($count > 0) {
            $links[] = '<span style="color:#00a32a;font-weight:600;">' . sprintf(esc_html(wpbl_t('plugin_active_features')), $count) . '</span>';
        }

        return $links;
    }

    // -------------------------------------------------------------------------
    // Render page
    // -------------------------------------------------------------------------

    public function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $current_lang = get_option('wpzaklad_language', 'sk');
        $updated      = isset($_GET['wpbl_updated']) ? sanitize_key($_GET['wpbl_updated']) : '';
        ?>
        <div class="wrap wpbl-wrap">

            <!-- Header -->
            <div class="wpbl-page-header">
                <h1><?php esc_html_e('WP Základ'); ?> <span class="wpbl-version">v<?php echo esc_html(WPBL_VERSION); ?></span></h1>
                <div class="wpbl-header-actions">
                    <form method="post" class="wpbl-lang-form">
                        <?php wp_nonce_field('wpbl_lang', 'wpbl_nonce'); ?>
                        <input type="hidden" name="wpbl_action" value="set_lang">
                        <div class="wpbl-lang-switcher">
                            <label for="wpbl-lang"><?php esc_html_e(wpbl_t('lang_label')); ?>:</label>
                            <select id="wpbl-lang" name="wpbl_lang">
                                <option value="sk" <?php selected($current_lang, 'sk'); ?>>Slovensky</option>
                                <option value="en" <?php selected($current_lang, 'en'); ?>>English</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notices -->
            <?php $this->render_notice($updated); ?>

            <!-- Layout -->
            <div class="wpbl-layout">

                <!-- Sidebar -->
                <div class="wpbl-sidebar">
                    <ul class="wpbl-sidebar-nav" id="wpbl-tabs">
                        <?php foreach ($this->modules as $module): ?>
                        <li>
                            <a href="#tab-<?php echo esc_attr($module->get_id()); ?>">
                                <?php echo esc_html($module->get_label()); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><a href="#tab-tools"><?php echo esc_html(wpbl_t('tab_tools')); ?></a></li>
                    </ul>
                </div>

                <!-- Content -->
                <div class="wpbl-content">

                    <!-- Settings form -->
                    <form method="post" id="wpbl-settings-form">
                        <?php wp_nonce_field('wpbl_save_settings', 'wpbl_nonce'); ?>
                        <input type="hidden" name="wpbl_action" value="save">

                        <!-- Search -->
                        <div class="wpbl-search-bar">
                            <input type="search" id="wpbl-search" placeholder="<?php echo esc_attr(wpbl_t('search_placeholder')); ?>">
                        </div>

                        <?php foreach ($this->modules as $module): ?>
                        <div class="wpbl-tab-panel" id="tab-<?php echo esc_attr($module->get_id()); ?>" style="display:none;">
                            <?php $this->render_module_tab($module); ?>
                        </div>
                        <?php endforeach; ?>

                        <div class="wpbl-footer" id="wpbl-save-footer" style="display:none;">
                            <button type="submit" class="button button-primary"><?php echo esc_html(wpbl_t('save_button')); ?></button>
                        </div>
                    </form>

                    <!-- Tools tab (separate from main form) -->
                    <div class="wpbl-tab-panel" id="tab-tools" style="display:none;">
                        <?php $this->render_tools_tab(); ?>
                    </div>

                </div><!-- .wpbl-content -->
            </div><!-- .wpbl-layout -->
        </div><!-- .wpbl-wrap -->
        <?php
    }

    private function render_notice(string $code): void {
        $messages = [
            '1'            => ['success', wpbl_t('saved_notice')],
            '2'            => ['success', wpbl_t('import_success')],
            '3'            => ['success', wpbl_t('reset_success')],
            '4'            => ['success', wpbl_t('flush_transients_success')],
            'import_error' => ['error',   wpbl_t('import_error')],
        ];

        if (!isset($messages[$code])) return;

        [$type, $text] = $messages[$code];
        echo '<div class="wpbl-notice wpbl-notice-' . esc_attr($type) . '">' . esc_html($text) . '</div>';
    }

    // -------------------------------------------------------------------------
    // Module tab renderer
    // -------------------------------------------------------------------------

    private function render_module_tab(WPBL_Module_Base $module): void {
        foreach ($module->get_fields() as $field) {
            $this->render_field($field);
        }
        $module->render_custom_tab();
    }

    private function render_field(array $field): void {
        $key            = $field['key'];
        $type           = $field['type'] ?? 'checkbox';
        $label          = $field['label'] ?? '';
        $desc           = $field['desc'] ?? '';
        $is_recommended = $field['recommended'] ?? false;
        $value          = $this->settings->get($key);
        $search         = strtolower(wp_strip_all_tags($label . ' ' . $desc));
        ?>
        <div class="wpbl-setting wpbl-type-<?php echo esc_attr($type); ?>" data-search="<?php echo esc_attr($search); ?>">
            <?php if ($type === 'checkbox'): ?>
                <label class="wpbl-toggle" for="<?php echo esc_attr($key); ?>">
                    <input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1" <?php checked((int) $value, 1); ?>>
                    <span class="wpbl-toggle-slider"></span>
                </label>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                        <?php if ($is_recommended): ?><span class="wpbl-badge-recommended"><?php echo esc_html(wpbl_t('badge_recommended')); ?></span><?php endif; ?>
                    </label>
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'text'): ?>
                <div class="wpbl-setting-info">
                    <?php if ($label): ?>
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php endif; ?>
                    <input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" class="regular-text">
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'number'): ?>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <input type="number" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" class="small-text" min="<?php echo esc_attr((string) ($field['min'] ?? 0)); ?>">
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'textarea'): ?>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <textarea id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" class="large-text code" rows="6"><?php echo esc_textarea((string) $value); ?></textarea>
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'select'): ?>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <select id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>">
                        <?php foreach ($field['options'] as $opt_val => $opt_label): ?>
                            <option value="<?php echo esc_attr($opt_val); ?>" <?php selected((string) $value, $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'color'): ?>
                <div class="wpbl-setting-info">
                    <?php if ($label): ?>
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php endif; ?>
                    <input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" class="wpbl-color-field" data-default-color="<?php echo esc_attr($field['default'] ?? '#000000'); ?>">
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'media'): ?>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label">
                        <?php echo esc_html($label); ?>
                    </label>
                    <div class="wpbl-media-field">
                        <input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" class="regular-text wpbl-media-input">
                        <button type="button" class="button wpbl-media-btn" data-target="<?php echo esc_attr($key); ?>"><?php echo esc_html(wpbl_t('select_image')); ?></button>
                        <button type="button" class="button wpbl-media-remove" data-target="<?php echo esc_attr($key); ?>" <?php echo $value ? '' : 'style="display:none;"'; ?>><?php echo esc_html(wpbl_t('remove_image')); ?></button>
                        <?php if ($value): ?>
                            <img src="<?php echo esc_url((string) $value); ?>" alt="" class="wpbl-media-preview">
                        <?php else: ?>
                            <img src="" alt="" class="wpbl-media-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <?php if ($desc): ?><span class="wpbl-setting-desc"><?php echo wp_kses_post($desc); ?></span><?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tools tab
    // -------------------------------------------------------------------------

    private function render_tools_tab(): void {
        global $wpdb;
        ?>

        <!-- Export -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_export_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_export_desc')); ?></p>
            <textarea id="wpbl-export-textarea" class="large-text code" rows="8" readonly onclick="this.select()"><?php echo esc_textarea($this->settings->export()); ?></textarea>
            <p><button type="button" class="button" id="wpbl-export-download"><?php echo esc_html(wpbl_t('tools_export_download')); ?></button></p>
        </div>

        <!-- Import -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_import_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_import_desc')); ?></p>
            <form method="post">
                <?php wp_nonce_field('wpbl_import', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" value="import">
                <textarea name="wpbl_import_json" class="large-text code" rows="6" placeholder="<?php echo esc_attr(wpbl_t('tools_import_placeholder')); ?>"></textarea>
                <p><button type="submit" class="button"><?php echo esc_html(wpbl_t('import_button')); ?></button></p>
            </form>
        </div>

        <!-- Reset -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_reset_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_reset_desc')); ?></p>
            <form method="post" class="wpbl-reset-form">
                <?php wp_nonce_field('wpbl_reset', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" value="reset">
                <button type="submit" class="button button-link-delete"><?php echo esc_html(wpbl_t('reset_button')); ?></button>
            </form>
        </div>

        <!-- Flush transients -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_transients_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_transients_desc')); ?></p>
            <form method="post" class="wpbl-flush-form">
                <?php wp_nonce_field('wpbl_flush', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" value="flush_transients">
                <button type="submit" class="button"><?php echo esc_html(wpbl_t('flush_transients_btn')); ?></button>
            </form>
        </div>

        <!-- System info -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_sysinfo_title')); ?></h3>
            <?php
            $theme          = wp_get_theme();
            $active_plugins = count(get_option('active_plugins', []));
            $revisions      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
            $transients     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'");
            ?>
            <table class="wpbl-sysinfo">
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_wp_version')); ?></td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_php_version')); ?></td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_theme')); ?></td><td><?php echo esc_html($theme->get('Name')); ?> <?php echo esc_html($theme->get('Version')); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_active_plugins')); ?></td><td><?php echo esc_html($active_plugins); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_db_revisions')); ?></td><td><?php echo esc_html($revisions); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_db_transients')); ?></td><td><?php echo esc_html($transients); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_memory_limit')); ?></td><td><?php echo esc_html(ini_get('memory_limit')); ?></td></tr>
                <tr><td><?php echo esc_html(wpbl_t('sysinfo_max_upload')); ?></td><td><?php echo esc_html(size_format(wp_max_upload_size())); ?></td></tr>
            </table>
        </div>

        <?php
    }
}
