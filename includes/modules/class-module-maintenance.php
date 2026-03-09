<?php
defined('ABSPATH') || exit;

class WPBL_Module_Maintenance extends WPBL_Module_Base {

    public function get_id(): string { return 'maintenance'; }
    public function get_label(): string { return wpbl_t('tab_maintenance'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_maintenance_mode'     => 0,
            'wpzaklad_maintenance_headline' => 'Stránka je dočasne nedostupná',
            'wpzaklad_maintenance_text'     => 'Pracujeme na aktualizácii. Čoskoro budeme späť.',
            'wpzaklad_maintenance_bg'       => '#1a1a2e',
            'wpzaklad_maintenance_start'    => '',
            'wpzaklad_maintenance_end'      => '',
        ];
    }

    public function get_fields(): array {
        return [
            [
                'key'   => 'wpzaklad_maintenance_mode',
                'type'  => 'checkbox',
                'label' => wpbl_t('maintenance_mode_label'),
                'desc'  => wpbl_t('maintenance_mode_desc'),
            ],
            [
                'key'   => 'wpzaklad_maintenance_headline',
                'type'  => 'text',
                'label' => wpbl_t('maintenance_headline_label'),
                'desc'  => '',
            ],
            [
                'key'   => 'wpzaklad_maintenance_text',
                'type'  => 'textarea',
                'label' => wpbl_t('maintenance_text_label'),
                'desc'  => '',
            ],
            [
                'key'     => 'wpzaklad_maintenance_bg',
                'type'    => 'color',
                'label'   => wpbl_t('maintenance_bg_label'),
                'desc'    => wpbl_t('maintenance_bg_desc'),
                'default' => '#1a1a2e',
            ],
            [
                'key'   => 'wpzaklad_maintenance_start',
                'type'  => 'datetime',
                'label' => wpbl_t('maintenance_start_label'),
                'desc'  => wpbl_t('maintenance_start_desc'),
            ],
            [
                'key'   => 'wpzaklad_maintenance_end',
                'type'  => 'datetime',
                'label' => wpbl_t('maintenance_end_label'),
                'desc'  => wpbl_t('maintenance_end_desc'),
            ],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_maintenance_mode')) {
            // Check scheduled time window
            $start = $this->get('wpzaklad_maintenance_start');
            $end   = $this->get('wpzaklad_maintenance_end');
            $now   = current_time('Y-m-d\TH:i');

            // If start is set and we haven't reached it yet, skip
            if ($start !== '' && $now < $start) {
                return;
            }

            // If end is set and has passed, auto-disable maintenance mode
            if ($end !== '' && $now > $end) {
                $settings = get_option('wpzaklad_settings', []);
                if (is_array($settings)) {
                    $settings['wpzaklad_maintenance_mode'] = 0;
                    update_option('wpzaklad_settings', $settings, false);
                }
                return;
            }

            add_action('template_redirect', [$this, 'maintenance_page']);
        }
    }

    public function maintenance_page(): void {
        if (current_user_can('manage_options')) {
            return;
        }

        $headline = $this->get('wpzaklad_maintenance_headline') ?: 'Stránka je dočasne nedostupná';
        $text     = $this->get('wpzaklad_maintenance_text') ?: 'Pracujeme na aktualizácii. Čoskoro budeme späť.';
        $bg       = $this->get('wpzaklad_maintenance_bg') ?: '#1a1a2e';

        // Validate hex color
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bg)) {
            $bg = '#1a1a2e';
        }

        status_header(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($headline); ?></title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    background: <?php echo esc_attr($bg); ?>;
                    color: #ffffff;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 24px;
                }
                .box { text-align: center; max-width: 520px; }
                h1 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 700; }
                p  { font-size: 1rem; opacity: .75; line-height: 1.7; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1><?php echo esc_html($headline); ?></h1>
                <p><?php echo esc_html($text); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
