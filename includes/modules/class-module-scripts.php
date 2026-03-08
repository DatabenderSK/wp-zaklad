<?php
defined('ABSPATH') || exit;

class WPBL_Module_Scripts extends WPBL_Module_Base {

    const CRON_HOOK     = 'wpbl_refresh_local_ga4';
    const LOCAL_GA4_DIR = 'wp-zaklad';

    public function get_id(): string { return 'scripts'; }
    public function get_label(): string { return wpbl_t('tab_scripts'); }

    public function get_defaults(): array {
        return [
            // Analytics
            'wpzaklad_analytics_type'         => 'gtm',   // backward compat default
            'wpzaklad_gtm_id'                 => '',
            'wpzaklad_ga4_id'                 => '',
            'wpzaklad_ga4_position'           => 'head',
            'wpzaklad_ga4_exclude_admins'     => 0,
            // Custom code
            'wpzaklad_head_code'              => '',
            'wpzaklad_footer_code'            => '',
        ];
    }

    public function get_fields(): array {
        // analytics_type, gtm_id, ga4_id, ga4_position, ga4_exclude_admins
        // are handled via render_custom_tab + save_custom_data
        return [
            [
                'key'      => 'wpzaklad_head_code',
                'type'     => 'textarea',
                'label'    => wpbl_t('head_code_label'),
                'desc'     => wpbl_t('head_code_desc'),
                'sanitize' => 'raw',
            ],
            [
                'key'      => 'wpzaklad_footer_code',
                'type'     => 'textarea',
                'label'    => wpbl_t('footer_code_label'),
                'desc'     => wpbl_t('footer_code_desc'),
                'sanitize' => 'raw',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    public function init(): void {
        $type = (string) $this->get('wpzaklad_analytics_type');

        switch ($type) {
            case 'gtm':
                $gtm_id = sanitize_text_field((string) $this->get('wpzaklad_gtm_id'));
                if ($gtm_id) {
                    add_action('wp_head',      fn() => $this->output_gtm_head($gtm_id), 1);
                    add_action('wp_body_open', fn() => $this->output_gtm_body($gtm_id), 1);
                }
                break;

            case 'ga4':
                $ga4_id = sanitize_text_field((string) $this->get('wpzaklad_ga4_id'));
                if ($ga4_id) {
                    $this->register_ga4_hooks($ga4_id, false);
                }
                break;

            case 'ga4_local':
                $ga4_id = sanitize_text_field((string) $this->get('wpzaklad_ga4_id'));
                if ($ga4_id) {
                    $this->register_ga4_hooks($ga4_id, true);
                    // Schedule daily refresh
                    add_action(self::CRON_HOOK, [$this, 'refresh_local_ga4']);
                    if (!wp_next_scheduled(self::CRON_HOOK)) {
                        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
                    }
                }
                break;
        }

        if ($this->get('wpzaklad_head_code')) {
            add_action('wp_head', [$this, 'output_head_code'], 99);
        }

        if ($this->get('wpzaklad_footer_code')) {
            add_action('wp_footer', [$this, 'output_footer_code'], 99);
        }
    }

    // -------------------------------------------------------------------------
    // GA4 helpers
    // -------------------------------------------------------------------------

    private function register_ga4_hooks(string $ga4_id, bool $local): void {
        $position = (string) $this->get('wpzaklad_ga4_position') ?: 'head';
        $exclude  = (bool)   $this->get('wpzaklad_ga4_exclude_admins');
        $hook     = $position === 'footer' ? 'wp_footer' : 'wp_head';

        add_action($hook, function () use ($ga4_id, $local, $exclude) {
            if ($exclude && current_user_can('manage_options')) return;

            if ($local) {
                $local_path = $this->get_local_path($ga4_id);
                // Download on first use if missing
                if (!file_exists($local_path)) {
                    $this->download_ga4_script($ga4_id);
                }
                $src = file_exists($local_path) ? $this->get_local_url($ga4_id) : '';
            } else {
                $src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga4_id);
            }

            if ($src) {
                echo '<script async src="' . esc_url($src) . '"></script>' . "\n"; // phpcs:ignore
            }
            echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . esc_js($ga4_id) . '",{"anonymize_ip":true});</script>' . "\n"; // phpcs:ignore
        }, 1);
    }

    // -------------------------------------------------------------------------
    // Local GA4 script management
    // -------------------------------------------------------------------------

    private function get_local_dir(): string {
        return wp_upload_dir()['basedir'] . '/' . self::LOCAL_GA4_DIR;
    }

    private function get_local_path(string $ga4_id): string {
        return $this->get_local_dir() . '/gtag-' . sanitize_file_name($ga4_id) . '.js';
    }

    private function get_local_url(string $ga4_id): string {
        return wp_upload_dir()['baseurl'] . '/' . self::LOCAL_GA4_DIR . '/gtag-' . sanitize_file_name($ga4_id) . '.js';
    }

    private function download_ga4_script(string $ga4_id): bool {
        $url      = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga4_id);
        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => true]);

        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) return false;

        wp_mkdir_p($this->get_local_dir());
        return (bool) file_put_contents($this->get_local_path($ga4_id), $body); // phpcs:ignore
    }

    public function refresh_local_ga4(): void {
        $ga4_id = sanitize_text_field((string) $this->get('wpzaklad_ga4_id'));
        if ($ga4_id) {
            $this->download_ga4_script($ga4_id);
        }
    }

    // -------------------------------------------------------------------------
    // GTM output
    // -------------------------------------------------------------------------

    private function output_gtm_head(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($id) . "');</script>\n<!-- End Google Tag Manager -->\n";
    }

    private function output_gtm_body(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }

    public function output_head_code(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get('wpzaklad_head_code') . "\n";
    }

    public function output_footer_code(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get('wpzaklad_footer_code') . "\n";
    }

    // -------------------------------------------------------------------------
    // Admin tab
    // -------------------------------------------------------------------------

    public function render_custom_tab(): void {
        $type         = (string) $this->get('wpzaklad_analytics_type');
        $gtm_id       = (string) $this->get('wpzaklad_gtm_id');
        $ga4_id       = (string) $this->get('wpzaklad_ga4_id');
        $ga4_position = (string) $this->get('wpzaklad_ga4_position') ?: 'head';
        $ga4_excl     = (bool)   $this->get('wpzaklad_ga4_exclude_admins');

        $local_path   = $ga4_id ? $this->get_local_path($ga4_id) : '';
        $local_exists = $local_path && file_exists($local_path);
        $local_time   = $local_exists ? wp_date(get_option('date_format') . ' H:i', filemtime($local_path)) : '';
        ?>
        <div class="wpbl-setting">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('analytics_section_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo esc_html(wpbl_t('analytics_section_desc')); ?></span>

                <div class="wpbl-analytics-options" style="margin-top:12px;">

                    <?php $this->analytics_radio('none',      wpbl_t('analytics_none'),      $type); ?>
                    <?php $this->analytics_radio('gtm',       wpbl_t('analytics_gtm'),       $type); ?>

                    <div class="wpbl-analytics-sub" data-for="gtm" <?php echo $type !== 'gtm' ? 'style="display:none;"' : ''; ?>>
                        <label style="font-size:12px;font-weight:600;"><?php echo esc_html(wpbl_t('gtm_id_label')); ?></label>
                        <input type="text" name="wpzaklad_gtm_id" value="<?php echo esc_attr($gtm_id); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                        <p class="description" style="margin-top:4px;"><?php echo wp_kses_post(wpbl_t('gtm_id_desc')); ?></p>
                    </div>

                    <?php $this->analytics_radio('ga4',       wpbl_t('analytics_ga4'),       $type); ?>
                    <?php $this->analytics_radio('ga4_local', wpbl_t('analytics_ga4_local'), $type); ?>

                    <div class="wpbl-analytics-sub" data-for="ga4 ga4_local" <?php echo !in_array($type, ['ga4', 'ga4_local'], true) ? 'style="display:none;"' : ''; ?>>
                        <label style="font-size:12px;font-weight:600;"><?php echo esc_html(wpbl_t('ga4_id_label')); ?></label>
                        <input type="text" name="wpzaklad_ga4_id" value="<?php echo esc_attr($ga4_id); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">

                        <div style="margin-top:10px;display:flex;gap:20px;flex-wrap:wrap;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('ga4_position_label')); ?></label>
                                <select name="wpzaklad_ga4_position">
                                    <option value="head"   <?php selected($ga4_position, 'head');   ?>><?php echo esc_html(wpbl_t('ga4_position_head')); ?></option>
                                    <option value="footer" <?php selected($ga4_position, 'footer'); ?>><?php echo esc_html(wpbl_t('ga4_position_footer')); ?></option>
                                </select>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:18px;">
                                <input type="checkbox" id="wpzaklad_ga4_exclude_admins" name="wpzaklad_ga4_exclude_admins" value="1" <?php checked($ga4_excl); ?>>
                                <label for="wpzaklad_ga4_exclude_admins" style="font-size:13px;"><?php echo esc_html(wpbl_t('ga4_exclude_admins_label')); ?></label>
                            </div>
                        </div>

                        <!-- Local script info (shown for ga4_local) -->
                        <div class="wpbl-ga4-local-info" <?php echo $type !== 'ga4_local' ? 'style="display:none;"' : ''; ?> style="margin-top:10px;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;font-size:12px;">
                            <?php if ($local_exists): ?>
                                <span style="color:#00a32a;">&#10003; <?php echo esc_html(wpbl_t('ga4_local_file_ok')); ?></span>
                                <span style="color:#646970;margin-left:8px;"><?php echo esc_html(wpbl_t('ga4_local_updated')); ?>: <?php echo esc_html($local_time); ?></span>
                            <?php else: ?>
                                <span style="color:#b26900;">&#9888; <?php echo esc_html(wpbl_t('ga4_local_file_missing')); ?></span>
                            <?php endif; ?>
                            <br style="margin:4px 0;">
                            <span style="color:#646970;"><?php echo esc_html(wpbl_t('ga4_local_auto_refresh')); ?></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <input type="hidden" name="wpzaklad_analytics_type" id="wpzaklad_analytics_type" value="<?php echo esc_attr($type); ?>">

        <style>
        .wpbl-analytics-options { display: flex; flex-direction: column; gap: 6px; }
        .wpbl-analytics-radio   { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; padding: 2px 0; }
        .wpbl-analytics-radio input[type=radio] { margin: 0; }
        .wpbl-analytics-sub { margin: 4px 0 8px 24px; padding: 12px 14px; background: #f6f7f7; border-left: 3px solid #2271b1; border-radius: 0 4px 4px 0; }
        </style>

        <script>
        (function() {
            var radios = document.querySelectorAll('[name="wpzaklad_analytics_type_radio"]');
            var hidden = document.getElementById('wpzaklad_analytics_type');

            function update(val) {
                hidden.value = val;
                document.querySelectorAll('.wpbl-analytics-sub').forEach(function(sub) {
                    var forVals = sub.dataset.for.split(' ');
                    sub.style.display = forVals.indexOf(val) !== -1 ? '' : 'none';
                });
                var localInfo = document.querySelector('.wpbl-ga4-local-info');
                if (localInfo) localInfo.style.display = val === 'ga4_local' ? '' : 'none';
            }

            radios.forEach(function(r) {
                r.addEventListener('change', function() { update(this.value); });
            });
        })();
        </script>
        <?php
    }

    private function analytics_radio(string $value, string $label, string $current): void {
        ?>
        <label class="wpbl-analytics-radio">
            <input type="radio" name="wpzaklad_analytics_type_radio" value="<?php echo esc_attr($value); ?>" <?php checked($current, $value); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save custom analytics settings
    // -------------------------------------------------------------------------

    public function save_custom_data(array $post): void {
        $allowed_types = ['none', 'gtm', 'ga4', 'ga4_local'];
        $type = sanitize_key($post['wpzaklad_analytics_type'] ?? 'none');
        if (!in_array($type, $allowed_types, true)) $type = 'none';

        $updates = [
            'wpzaklad_analytics_type'     => $type,
            'wpzaklad_gtm_id'             => sanitize_text_field(wp_unslash($post['wpzaklad_gtm_id'] ?? '')),
            'wpzaklad_ga4_id'             => sanitize_text_field(wp_unslash($post['wpzaklad_ga4_id'] ?? '')),
            'wpzaklad_ga4_position'       => in_array($post['wpzaklad_ga4_position'] ?? '', ['head', 'footer'], true) ? $post['wpzaklad_ga4_position'] : 'head',
            'wpzaklad_ga4_exclude_admins' => isset($post['wpzaklad_ga4_exclude_admins']) ? 1 : 0,
        ];

        $this->settings->patch($updates);

        // If switching to local GA4, trigger immediate download
        if ($type === 'ga4_local' && !empty($updates['wpzaklad_ga4_id'])) {
            $path = $this->get_local_path($updates['wpzaklad_ga4_id']);
            if (!file_exists($path)) {
                $this->download_ga4_script($updates['wpzaklad_ga4_id']);
            }
        }

        // Clear stale cron if type changed away from ga4_local
        if ($type !== 'ga4_local') {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}
