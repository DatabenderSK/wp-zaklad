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

        // Conflict warnings
        $conflicts = WPBL_Conflict_Detector::detect($this->settings);
        foreach ($conflicts as $i => $conflict) {
            if ($conflict['severity'] !== 'critical') continue;
            $wp_admin_bar->add_node([
                'id'    => 'wpbl-conflict-' . $i,
                'title' => '&#9888; ' . wpbl_t($conflict['message_key']),
                'href'  => admin_url('options-general.php?page=wp-zaklad'),
                'meta'  => ['class' => 'wpbl-bar-warning'],
            ]);
        }
    }

    public function output_admin_bar_warning_css(): void {
        if (!current_user_can('manage_options')) return;
        $has_warnings = $this->get_active_warnings() || WPBL_Conflict_Detector::detect($this->settings);
        if (!$has_warnings) return;
        echo '<style>#wp-admin-bar-wpbl-noindex-warning .ab-item,#wp-admin-bar-wpbl-maintenance-warning .ab-item,[id^="wp-admin-bar-wpbl-conflict-"] .ab-item{background:#d63638!important;color:#fff!important;}</style>' . "\n";
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
            .wpbl-manager{border-left:3px solid #2271b1;padding-left:12px;}
            .wpbl-manager-name{font-size:14px;font-weight:600;color:#1d2327;margin:0 0 10px;}
            .wpbl-manager-contacts{margin:0;padding:0;list-style:none;}
            .wpbl-manager-contacts li{font-size:13px;color:#646970;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
            .wpbl-manager-contacts li .dashicons{font-size:16px;width:16px;height:16px;color:#2271b1;}
            .wpbl-manager-contacts a{text-decoration:none;color:#2271b1;}
            .wpbl-manager-contacts a:hover{color:#135e96;}
            .wpbl-manager-booking{margin:12px 0 0;padding-top:12px;border-top:1px solid #f0f0f1;}
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

        $recommended_keys = [];
        foreach ($this->modules as $module) {
            foreach ($module->get_fields() as $field) {
                if (!empty($field['recommended'])) {
                    $recommended_keys[] = $field['key'];
                }
            }
        }

        $mine_keys = [];
        foreach ($this->modules as $module) {
            foreach ($module->get_fields() as $field) {
                if (!empty($field['mine'])) {
                    $mine_keys[] = $field['key'];
                }
            }
        }

        wp_localize_script('wpbl-admin', 'wpbl', [
            'resetConfirm'    => wpbl_t('reset_confirm'),
            'flushConfirm'    => wpbl_t('flush_transients_confirm'),
            'recommended'     => $recommended_keys,
            'mine'            => $mine_keys,
            'presetApplied'   => wpbl_t('preset_applied'),
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

            case 'db_clean':
                check_admin_referer('wpbl_db', 'wpbl_nonce');
                $type = sanitize_key($_POST['db_type'] ?? '');
                WPBL_DB_Optimizer::clean($type);
                $this->redirect(5);
                break;

            case 'db_clean_all':
                check_admin_referer('wpbl_db', 'wpbl_nonce');
                WPBL_DB_Optimizer::clean_all();
                $this->redirect(5);
                break;

            case 'db_optimize':
                check_admin_referer('wpbl_db', 'wpbl_nonce');
                WPBL_DB_Optimizer::optimize_tables();
                $this->redirect(6);
                break;

            case 'db_schedule':
                check_admin_referer('wpbl_db', 'wpbl_nonce');
                $schedule = sanitize_key($_POST['db_schedule_value'] ?? 'disabled');
                WPBL_DB_Optimizer::set_schedule($schedule);
                $this->redirect(7);
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
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_') . '%'
            )
        );
        wp_cache_flush();
        $this->redirect(4);
    }

    private function redirect(int|string $code): void {
        wp_safe_redirect(admin_url('options-general.php?page=wp-zaklad&wpbl_updated=' . $code));
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

                    case 'datetime':
                        $sanitized[$key] = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $value) ? (string) $value : '';
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
                <h1><?php echo esc_html( 'WP Základ' ); ?> <span class="wpbl-version">v<?php echo esc_html(WPBL_VERSION); ?></span></h1>
                <div class="wpbl-header-actions">
                    <form method="post" class="wpbl-lang-form">
                        <?php wp_nonce_field('wpbl_lang', 'wpbl_nonce'); ?>
                        <input type="hidden" name="wpbl_action" value="set_lang">
                        <div class="wpbl-lang-switcher">
                            <label for="wpbl-lang"><?php echo esc_html( wpbl_t('lang_label') ); ?>:</label>
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

            <!-- Conflict warnings -->
            <?php $this->render_conflict_warnings(); ?>

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
                            <li><a href="#tab-database"><?php echo esc_html(wpbl_t('tab_database')); ?></a></li>
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

                    <!-- Database tab (separate from main form – has its own sub-forms) -->
                    <div class="wpbl-tab-panel" id="tab-database" style="display:none;">
                        <?php $this->render_database_tab(); ?>
                    </div>

                    <!-- Tools tab (separate from main form) -->
                    <div class="wpbl-tab-panel" id="tab-tools" style="display:none;">
                        <?php $this->render_tools_tab(); ?>
                    </div>

                </div><!-- .wpbl-content -->
            </div><!-- .wpbl-layout -->

            <p class="wpbl-plugin-credit">
                WP Základ by <a href="https://martinpavlic.sk/" target="_blank" rel="noopener">Martin Pavlič</a>
            </p>
        </div><!-- .wpbl-wrap -->
        <?php
    }

    private function render_notice(string $code): void {
        $messages = [
            '1'            => ['success', wpbl_t('saved_notice')],
            '2'            => ['success', wpbl_t('import_success')],
            '3'            => ['success', wpbl_t('reset_success')],
            '4'            => ['success', wpbl_t('flush_transients_success')],
            '5'            => ['success', wpbl_t('db_cleaned_notice')],
            '6'            => ['success', wpbl_t('db_optimized_notice')],
            '7'            => ['success', wpbl_t('db_schedule_saved')],
            'import_error' => ['error',   wpbl_t('import_error')],
        ];

        if (!isset($messages[$code])) return;

        [$type, $text] = $messages[$code];
        echo '<div class="wpbl-notice wpbl-notice-' . esc_attr($type) . '">' . esc_html($text) . '</div>';
    }

    // -------------------------------------------------------------------------
    // Conflict warnings
    // -------------------------------------------------------------------------

    private function render_conflict_warnings(): void {
        $conflicts = WPBL_Conflict_Detector::detect($this->settings);
        if (empty($conflicts)) return;

        foreach ($conflicts as $conflict) {
            $class = 'wpbl-notice ';
            if ($conflict['severity'] === 'critical') {
                $class .= 'wpbl-notice-error';
            } elseif ($conflict['severity'] === 'warning') {
                $class .= 'wpbl-notice-warning';
            } else {
                $class .= 'wpbl-notice-info';
            }
            echo '<div class="' . esc_attr($class) . '">';
            echo esc_html(wpbl_t($conflict['message_key']));
            echo '</div>';
        }
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

            <?php elseif ($type === 'datetime'): ?>
                <div class="wpbl-setting-info">
                    <label class="wpbl-setting-label" for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <input type="datetime-local" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>">
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
        ?>
        <!-- Presets -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('preset_label')); ?></h3>
            <p><?php echo esc_html(wpbl_t('preset_desc')); ?></p>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <select id="wpbl-preset-select">
                    <option value="">— <?php echo esc_html(wpbl_t('preset_choose')); ?> —</option>
                    <option value="none"><?php echo esc_html(wpbl_t('preset_none')); ?></option>
                    <option value="recommended"><?php echo esc_html(wpbl_t('preset_recommended')); ?></option>
                    <option value="mine"><?php echo esc_html(wpbl_t('preset_mine')); ?></option>
                </select>
                <button type="button" id="wpbl-preset-apply" class="button"><?php echo esc_html(wpbl_t('preset_apply_btn')); ?></button>
            </div>
        </div>

        <script>
        (function($) {
            $('#wpbl-preset-apply').on('click', function() {
                var preset = $('#wpbl-preset-select').val();
                if (!preset) return;

                var toEnable = [];
                if (preset === 'recommended') toEnable = wpbl.recommended || [];
                else if (preset === 'mine')    toEnable = wpbl.mine || [];

                $('#wpbl-settings-form').find('input[type="checkbox"]').each(function() {
                    this.checked = toEnable.indexOf(this.name) !== -1;
                });

                alert(wpbl.presetApplied || 'Applied.');
            });
        })(jQuery);
        </script>

        <!-- Export -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_export_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_export_desc')); ?></p>
            <textarea id="wpbl-export-textarea" class="large-text code" rows="8" readonly onclick="this.select()"><?php echo esc_textarea($this->settings->export()); ?></textarea>
            <p style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="button" id="wpbl-export-copy"><?php echo esc_html(wpbl_t('portability_copy_btn')); ?></button>
                <button type="button" class="button" id="wpbl-export-download"><?php echo esc_html(wpbl_t('portability_download_btn')); ?></button>
            </p>
        </div>

        <!-- Import -->
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('tools_import_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('tools_import_desc')); ?></p>
            <form method="post" id="wpbl-import-form">
                <?php wp_nonce_field('wpbl_import', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" value="import">
                <textarea name="wpbl_import_json" id="wpbl-import-textarea" class="large-text code" rows="6" placeholder="<?php echo esc_attr(wpbl_t('tools_import_placeholder')); ?>"></textarea>
                <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="button"><?php echo esc_html(wpbl_t('import_button')); ?></button>
                    <label class="button" for="wpbl-import-file" style="cursor:pointer;"><?php echo esc_html(wpbl_t('portability_upload_btn')); ?></label>
                    <input type="file" id="wpbl-import-file" accept=".json" style="display:none;">
                </p>
            </form>
        </div>

        <script>
        (function() {
            var exportTA = document.getElementById('wpbl-export-textarea');
            document.getElementById('wpbl-export-copy').addEventListener('click', function() {
                exportTA.select(); document.execCommand('copy');
            });
            document.getElementById('wpbl-export-download').addEventListener('click', function() {
                var blob = new Blob([exportTA.value], {type: 'application/json'});
                var url  = URL.createObjectURL(blob);
                var a    = document.createElement('a');
                a.href = url; a.download = 'wpzaklad-settings.json';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });
            document.getElementById('wpbl-import-file').addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(evt) {
                    document.getElementById('wpbl-import-textarea').value = evt.target.result;
                    document.getElementById('wpbl-import-form').submit();
                };
                reader.readAsText(file);
                this.value = '';
            });
        })();
        </script>

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
        <?php
    }

    // -------------------------------------------------------------------------
    // Database tab
    // -------------------------------------------------------------------------

    private function render_database_tab(): void {
        ?>
        <!-- Database optimizer -->
        <?php $this->render_db_optimizer_section(); ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Database optimizer section
    // -------------------------------------------------------------------------

    private function render_db_optimizer_section(): void {
        $counts   = WPBL_DB_Optimizer::get_counts();
        $schedule = WPBL_DB_Optimizer::get_schedule();

        $rows = [
            'revisions'          => wpbl_t('db_revisions'),
            'auto_drafts'        => wpbl_t('db_auto_drafts'),
            'trashed_posts'      => wpbl_t('db_trashed_posts'),
            'spam_comments'      => wpbl_t('db_spam_comments'),
            'trashed_comments'   => wpbl_t('db_trashed_comments'),
            'expired_transients' => wpbl_t('db_expired_transients'),
            'orphan_postmeta'    => wpbl_t('db_orphan_postmeta'),
        ];

        $any_positive = !empty(array_filter($counts, static fn($c) => $c > 0));
        ?>
        <div class="wpbl-tools-section">
            <h3><?php echo esc_html(wpbl_t('db_optimizer_title')); ?></h3>
            <p><?php echo esc_html(wpbl_t('db_optimizer_desc')); ?></p>

            <form method="post" id="wpbl-db-form">
                <?php wp_nonce_field('wpbl_db', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" id="wpbl-db-action" value="db_clean">
                <input type="hidden" name="db_type"    id="wpbl-db-type"   value="">

                <table class="wpbl-sysinfo wpbl-db-table">
                    <?php foreach ($rows as $type => $label):
                        $count = $counts[$type] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td class="wpbl-db-count <?php echo $count > 0 ? 'wpbl-db-count-pos' : ''; ?>">
                            <?php echo number_format($count); ?>&nbsp;<?php echo esc_html(wpbl_t('db_found')); ?>
                        </td>
                        <td>
                            <button type="button"
                                class="button button-small wpbl-db-clean-btn"
                                data-type="<?php echo esc_attr($type); ?>"
                                data-confirm="<?php echo esc_attr(wpbl_t('db_confirm_single')); ?>"
                                <?php disabled($count, 0); ?>>
                                <?php echo esc_html(wpbl_t('db_delete_btn')); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <p style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="wpbl-db-clean-all" class="button"
                        data-confirm="<?php echo esc_attr(wpbl_t('db_confirm_all')); ?>"
                        <?php disabled($any_positive, false); ?>>
                        <?php echo esc_html(wpbl_t('db_clean_all_btn')); ?>
                    </button>
                    <button type="button" id="wpbl-db-optimize" class="button"
                        data-confirm="<?php echo esc_attr(wpbl_t('db_confirm_optimize')); ?>">
                        <?php echo esc_html(wpbl_t('db_optimize_btn')); ?>
                    </button>
                </p>
            </form>

            <form method="post" style="margin-top:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <?php wp_nonce_field('wpbl_db', 'wpbl_nonce'); ?>
                <input type="hidden" name="wpbl_action" value="db_schedule">
                <strong style="font-size:13px;"><?php echo esc_html(wpbl_t('db_schedule_title')); ?>:</strong>
                <select name="db_schedule_value">
                    <option value="disabled" <?php selected($schedule, 'disabled'); ?>><?php echo esc_html(wpbl_t('db_schedule_disabled')); ?></option>
                    <option value="daily"    <?php selected($schedule, 'daily');    ?>><?php echo esc_html(wpbl_t('db_schedule_daily'));    ?></option>
                    <option value="weekly"   <?php selected($schedule, 'weekly');   ?>><?php echo esc_html(wpbl_t('db_schedule_weekly'));   ?></option>
                    <option value="monthly"  <?php selected($schedule, 'monthly');  ?>><?php echo esc_html(wpbl_t('db_schedule_monthly'));  ?></option>
                </select>
                <button type="submit" class="button"><?php echo esc_html(wpbl_t('db_save_schedule')); ?></button>
                <?php if ($schedule !== 'disabled'): ?>
                    <span style="font-size:12px;color:#646970;">
                        (<?php echo esc_html(wpbl_t('db_next_run')); ?>:
                        <?php
                        $next = wp_next_scheduled(WPBL_DB_Optimizer::CRON_HOOK);
                        echo $next ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next)) : '–';
                        ?>)
                    </span>
                <?php endif; ?>
            </form>
        </div>

        <style>
        .wpbl-db-table td { vertical-align: middle; padding: 6px 12px 6px 0; }
        .wpbl-db-table td:first-child { font-size: 13px; color: #1d2327; min-width: 180px; }
        .wpbl-db-count { font-size: 12px; color: #646970; min-width: 120px; }
        .wpbl-db-count-pos { color: #b26900; font-weight: 600; }
        </style>

        <script>
        jQuery(function($) {
            $('#wpbl-db-form').find('.wpbl-db-clean-btn').on('click', function() {
                var msg = $(this).data('confirm');
                if (!confirm(msg)) return;
                $('#wpbl-db-action').val('db_clean');
                $('#wpbl-db-type').val($(this).data('type'));
                $('#wpbl-db-form').submit();
            });
            $('#wpbl-db-clean-all').on('click', function() {
                var msg = $(this).data('confirm');
                if (!confirm(msg)) return;
                $('#wpbl-db-action').val('db_clean_all');
                $('#wpbl-db-form').submit();
            });
            $('#wpbl-db-optimize').on('click', function() {
                var msg = $(this).data('confirm');
                if (!confirm(msg)) return;
                $('#wpbl-db-action').val('db_optimize');
                $('#wpbl-db-form').submit();
            });
        });
        </script>
        <?php
    }
}
