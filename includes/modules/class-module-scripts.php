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
            // Conversion pixels
            'wpzaklad_fb_pixel_id'            => '',
            'wpzaklad_clarity_id'             => '',
            'wpzaklad_tiktok_pixel_id'        => '',
            'wpzaklad_bing_uet_id'            => '',
            'wpzaklad_linkedin_partner_id'    => '',
            'wpzaklad_x_pixel_id'             => '',
            'wpzaklad_pinterest_tag_id'       => '',
            'wpzaklad_pixels_exclude_admins'  => 1,
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

        // Conversion pixels
        $pixels_exclude = (bool) $this->get('wpzaklad_pixels_exclude_admins');

        $fb_pixel = sanitize_text_field((string) $this->get('wpzaklad_fb_pixel_id'));
        if ($fb_pixel) {
            add_action('wp_head', function () use ($fb_pixel, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_fb_pixel($fb_pixel);
            }, 2);
        }

        $clarity = sanitize_text_field((string) $this->get('wpzaklad_clarity_id'));
        if ($clarity) {
            add_action('wp_head', function () use ($clarity, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_clarity($clarity);
            }, 2);
        }

        $tiktok = sanitize_text_field((string) $this->get('wpzaklad_tiktok_pixel_id'));
        if ($tiktok) {
            add_action('wp_head', function () use ($tiktok, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_tiktok_pixel($tiktok);
            }, 2);
        }

        $bing = sanitize_text_field((string) $this->get('wpzaklad_bing_uet_id'));
        if ($bing) {
            add_action('wp_head', function () use ($bing, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_bing_uet($bing);
            }, 2);
        }

        $linkedin = sanitize_text_field((string) $this->get('wpzaklad_linkedin_partner_id'));
        if ($linkedin) {
            add_action('wp_head', function () use ($linkedin, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_linkedin($linkedin);
            }, 2);
        }

        $x_pixel = sanitize_text_field((string) $this->get('wpzaklad_x_pixel_id'));
        if ($x_pixel) {
            add_action('wp_head', function () use ($x_pixel, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_x_pixel($x_pixel);
            }, 2);
        }

        $pinterest = sanitize_text_field((string) $this->get('wpzaklad_pinterest_tag_id'));
        if ($pinterest) {
            add_action('wp_head', function () use ($pinterest, $pixels_exclude) {
                if ($pixels_exclude && current_user_can('manage_options')) return;
                $this->output_pinterest($pinterest);
            }, 2);
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
    // Conversion pixels
    // -------------------------------------------------------------------------

    private function output_fb_pixel(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- Meta Pixel -->\n<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc_js($id) . "');fbq('track','PageView');</script>\n";
        echo '<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . esc_attr($id) . '&ev=PageView&noscript=1" alt=""></noscript>' . "\n";
        echo "<!-- End Meta Pixel -->\n";
    }

    private function output_clarity(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- Microsoft Clarity -->\n<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','" . esc_js($id) . "');</script>\n<!-- End Clarity -->\n";
    }

    private function output_tiktok_pixel(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- TikTok Pixel -->\n<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie','holdConsent','revokeConsent','grantConsent'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var r='https://analytics.tiktok.com/i18n/pixel/events.js',o=n&&n.partner;ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e+\"_\"+o]=1;var i=d.createElement('script');i.type='text/javascript';i.async=!0;i.src=r+'?sdkid='+e+'&lib='+t;var a=d.getElementsByTagName('script')[0];a.parentNode.insertBefore(i,a)};ttq.load('" . esc_js($id) . "');ttq.page();}(window,document,'ttq');</script>\n<!-- End TikTok Pixel -->\n";
    }

    private function output_bing_uet(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- Microsoft UET -->\n<script>(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:\"" . esc_js($id) . "\"};o.q=w[u],w[u]=new UET(o),w[u].push(\"pageLoad\")},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!==\"loaded\"&&s!==\"complete\"||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,\"script\",\"//bat.bing.com/bat.js\",\"uetq\");</script>\n";
        echo '<noscript><img src="//bat.bing.com/action/0?ti=' . esc_attr($id) . '&amp;Ver=2" height="0" width="0" style="display:none;visibility:hidden;" alt=""></noscript>' . "\n";
        echo "<!-- End Microsoft UET -->\n";
    }

    private function output_linkedin(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- LinkedIn Insight -->\n<script>_linkedin_partner_id=\"" . esc_js($id) . "\";window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);</script>\n";
        echo "<script>(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName(\"script\")[0];var b=document.createElement(\"script\");b.type=\"text/javascript\";b.async=true;b.src=\"https://snap.licdn.com/li.lms-analytics/insight.min.js\";s.parentNode.insertBefore(b,s);})(window.lintrk);</script>\n";
        echo '<noscript><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=' . esc_attr($id) . '&amp;fmt=gif"></noscript>' . "\n";
        echo "<!-- End LinkedIn Insight -->\n";
    }

    private function output_x_pixel(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- X Pixel -->\n<script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments)},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config','" . esc_js($id) . "');</script>\n<!-- End X Pixel -->\n";
    }

    private function output_pinterest(string $id): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<!-- Pinterest Tag -->\n<script>!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");pintrk('load','" . esc_js($id) . "');pintrk('page');</script>\n";
        echo '<noscript><img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?tid=' . esc_attr($id) . '&amp;event=init&amp;noscript=1"></noscript>' . "\n";
        echo "<!-- End Pinterest Tag -->\n";
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

        <?php
        // Conversion pixels section
        $fb_pixel   = (string) $this->get('wpzaklad_fb_pixel_id');
        $clarity    = (string) $this->get('wpzaklad_clarity_id');
        $tiktok     = (string) $this->get('wpzaklad_tiktok_pixel_id');
        $bing_uet   = (string) $this->get('wpzaklad_bing_uet_id');
        $linkedin   = (string) $this->get('wpzaklad_linkedin_partner_id');
        $x_pixel    = (string) $this->get('wpzaklad_x_pixel_id');
        $pinterest  = (string) $this->get('wpzaklad_pinterest_tag_id');
        $px_excl    = (bool)   $this->get('wpzaklad_pixels_exclude_admins');
        ?>
        <div class="wpbl-setting" style="margin-top:20px;">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('pixels_section_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo esc_html(wpbl_t('pixels_section_desc')); ?></span>

                <div style="margin-top:12px;display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('fb_pixel_id_label')); ?></label>
                        <input type="text" name="wpzaklad_fb_pixel_id" value="<?php echo esc_attr($fb_pixel); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('clarity_id_label')); ?></label>
                        <input type="text" name="wpzaklad_clarity_id" value="<?php echo esc_attr($clarity); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('tiktok_pixel_id_label')); ?></label>
                        <input type="text" name="wpzaklad_tiktok_pixel_id" value="<?php echo esc_attr($tiktok); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('bing_uet_id_label')); ?></label>
                        <input type="text" name="wpzaklad_bing_uet_id" value="<?php echo esc_attr($bing_uet); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('linkedin_partner_id_label')); ?></label>
                        <input type="text" name="wpzaklad_linkedin_partner_id" value="<?php echo esc_attr($linkedin); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('x_pixel_id_label')); ?></label>
                        <input type="text" name="wpzaklad_x_pixel_id" value="<?php echo esc_attr($x_pixel); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo esc_html(wpbl_t('pinterest_tag_id_label')); ?></label>
                        <input type="text" name="wpzaklad_pinterest_tag_id" value="<?php echo esc_attr($pinterest); ?>" class="regular-text">
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="wpzaklad_pixels_exclude_admins" name="wpzaklad_pixels_exclude_admins" value="1" <?php checked($px_excl); ?>>
                        <label for="wpzaklad_pixels_exclude_admins" style="font-size:13px;"><?php echo esc_html(wpbl_t('pixels_exclude_admins_label')); ?></label>
                    </div>
                </div>
            </div>
        </div>

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

        // Conversion pixels
        $updates['wpzaklad_fb_pixel_id']           = sanitize_text_field(wp_unslash($post['wpzaklad_fb_pixel_id'] ?? ''));
        $updates['wpzaklad_clarity_id']            = sanitize_text_field(wp_unslash($post['wpzaklad_clarity_id'] ?? ''));
        $updates['wpzaklad_tiktok_pixel_id']       = sanitize_text_field(wp_unslash($post['wpzaklad_tiktok_pixel_id'] ?? ''));
        $updates['wpzaklad_bing_uet_id']           = sanitize_text_field(wp_unslash($post['wpzaklad_bing_uet_id'] ?? ''));
        $updates['wpzaklad_linkedin_partner_id']   = sanitize_text_field(wp_unslash($post['wpzaklad_linkedin_partner_id'] ?? ''));
        $updates['wpzaklad_x_pixel_id']            = sanitize_text_field(wp_unslash($post['wpzaklad_x_pixel_id'] ?? ''));
        $updates['wpzaklad_pinterest_tag_id']      = sanitize_text_field(wp_unslash($post['wpzaklad_pinterest_tag_id'] ?? ''));
        $updates['wpzaklad_pixels_exclude_admins'] = isset($post['wpzaklad_pixels_exclude_admins']) ? 1 : 0;

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
