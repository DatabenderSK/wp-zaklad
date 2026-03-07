<?php
defined('ABSPATH') || exit;

class WPBL_Module_Appearance extends WPBL_Module_Base {

    public function get_id(): string { return 'appearance'; }
    public function get_label(): string { return wpbl_t('tab_appearance'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_custom_login_logo'        => 0,
            'wpzaklad_custom_login_logo_url'    => '',
            'wpzaklad_clean_dashboard'          => 0,
            'wpzaklad_hide_admin_for_clients'   => 0,
            'wpzaklad_colored_post_statuses'    => 0,
            'wpzaklad_color_draft'              => '#fce8e8',
            'wpzaklad_color_pending'            => '#fff8e5',
            'wpzaklad_color_private'            => '#f0f0f0',
            'wpzaklad_color_future'             => '#e8f5e9',
        ];
    }

    public function get_fields(): array {
        return [
            [
                'key'   => 'wpzaklad_custom_login_logo',
                'type'  => 'checkbox',
                'label' => wpbl_t('custom_login_logo_label'),
                'desc'  => wpbl_t('custom_login_logo_desc'),
            ],
            [
                'key'   => 'wpzaklad_custom_login_logo_url',
                'type'  => 'media',
                'label' => wpbl_t('custom_login_logo_url_label'),
                'desc'  => wpbl_t('custom_login_logo_url_desc'),
            ],
            ['key' => 'wpzaklad_clean_dashboard',        'type' => 'checkbox', 'label' => wpbl_t('clean_dashboard_label'),    'desc' => wpbl_t('clean_dashboard_desc')],
            ['key' => 'wpzaklad_hide_admin_for_clients', 'type' => 'checkbox', 'label' => wpbl_t('hide_admin_clients_label'), 'desc' => wpbl_t('hide_admin_clients_desc')],
            ['key' => 'wpzaklad_colored_post_statuses',  'type' => 'checkbox', 'label' => wpbl_t('colored_statuses_label'),   'desc' => wpbl_t('colored_statuses_desc')],
            ['key' => 'wpzaklad_color_draft',            'type' => 'color',    'label' => wpbl_t('color_draft_label'),        'default' => '#fce8e8'],
            ['key' => 'wpzaklad_color_pending',          'type' => 'color',    'label' => wpbl_t('color_pending_label'),      'default' => '#fff8e5'],
            ['key' => 'wpzaklad_color_private',          'type' => 'color',    'label' => wpbl_t('color_private_label'),      'default' => '#f0f0f0'],
            ['key' => 'wpzaklad_color_future',           'type' => 'color',    'label' => wpbl_t('color_future_label'),       'default' => '#e8f5e9'],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_custom_login_logo') && $this->get('wpzaklad_custom_login_logo_url')) {
            add_action('login_enqueue_scripts', [$this, 'custom_login_logo']);
            add_filter('login_headerurl', fn() => home_url());
        }
        if ($this->get('wpzaklad_clean_dashboard')) {
            add_action('wp_dashboard_setup', [$this, 'clean_dashboard']);
        }
        if ($this->get('wpzaklad_hide_admin_for_clients')) {
            add_action('admin_head', [$this, 'hide_admin_for_clients_css']);
        }
        if ($this->get('wpzaklad_colored_post_statuses')) {
            add_action('admin_head', [$this, 'colored_post_statuses_css']);
        }
    }

    public function custom_login_logo(): void {
        $url = esc_url($this->get('wpzaklad_custom_login_logo_url'));
        ?>
        <style>
            #login h1 a,
            .login h1 a {
                background-image: url('<?php echo $url; ?>');
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
                width: 320px;
                height: 80px;
            }
        </style>
        <?php
    }

    public function clean_dashboard(): void {
        remove_meta_box('dashboard_right_now',   'dashboard', 'normal');
        remove_meta_box('dashboard_activity',    'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_primary',     'dashboard', 'side');
        remove_action('welcome_panel', 'wp_welcome_panel');
    }

    public function hide_admin_for_clients_css(): void {
        if (current_user_can('manage_options')) return;
        echo '<style>#adminmenu,#adminmenuback,#adminmenuwrap,#wpadminbar{display:none!important;}#wpcontent,#wpfooter{margin-left:0!important;}</style>' . "\n";
    }

    public function colored_post_statuses_css(): void {
        $draft   = sanitize_hex_color($this->get('wpzaklad_color_draft'))   ?: '#fce8e8';
        $pending = sanitize_hex_color($this->get('wpzaklad_color_pending')) ?: '#fff8e5';
        $private = sanitize_hex_color($this->get('wpzaklad_color_private')) ?: '#f0f0f0';
        $future  = sanitize_hex_color($this->get('wpzaklad_color_future'))  ?: '#e8f5e9';
        ?>
        <style>
            .wp-list-table tr.status-draft   td { background-color: <?php echo esc_attr($draft); ?>; }
            .wp-list-table tr.status-pending td { background-color: <?php echo esc_attr($pending); ?>; }
            .wp-list-table tr.status-private td { background-color: <?php echo esc_attr($private); ?>; }
            .wp-list-table tr.status-future  td { background-color: <?php echo esc_attr($future); ?>; }
        </style>
        <?php
    }
}
