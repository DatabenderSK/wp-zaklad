<?php
defined('ABSPATH') || exit;

class WPBL_Module_Whitelabel extends WPBL_Module_Base {

    public function get_id(): string { return 'whitelabel'; }
    public function get_label(): string { return wpbl_t('tab_whitelabel'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_hide_wp_logo'       => 0,
            'wpzaklad_admin_footer_text'  => '',
            'wpzaklad_hide_howdy'         => 0,
            'wpzaklad_hide_frontend_bar'  => 0,
        ];
    }

    public function get_fields(): array {
        return [
            [
                'key'         => 'wpzaklad_hide_wp_logo',
                'type'        => 'checkbox',
                'label'       => wpbl_t('hide_wp_logo_label'),
                'desc'        => wpbl_t('hide_wp_logo_desc'),
                'recommended' => true,
                'mine'        => true,
            ],
            [
                'key'      => 'wpzaklad_admin_footer_text',
                'type'     => 'textarea',
                'sanitize' => 'kses',
                'label'    => wpbl_t('admin_footer_text_label'),
                'desc'     => wpbl_t('admin_footer_text_desc'),
            ],
            [
                'key'         => 'wpzaklad_hide_howdy',
                'type'        => 'checkbox',
                'label'       => wpbl_t('hide_howdy_label'),
                'desc'        => wpbl_t('hide_howdy_desc'),
                'recommended' => true,
                'mine'        => true,
            ],
            [
                'key'   => 'wpzaklad_hide_frontend_bar',
                'type'  => 'checkbox',
                'label' => wpbl_t('hide_frontend_bar_label'),
                'desc'  => wpbl_t('hide_frontend_bar_desc'),
            ],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_hide_wp_logo')) {
            add_action('admin_bar_menu', [$this, 'remove_wp_logo'], 999);
        }

        $footer_text = $this->get('wpzaklad_admin_footer_text');
        if ($footer_text) {
            add_filter('admin_footer_text', fn() => wp_kses_post($footer_text));
        }

        if ($this->get('wpzaklad_hide_howdy')) {
            add_filter('admin_bar_menu', [$this, 'remove_howdy'], 25);
        }

        if ($this->get('wpzaklad_hide_frontend_bar')) {
            add_action('after_setup_theme', [$this, 'hide_frontend_admin_bar']);
        }
    }

    public function remove_wp_logo(\WP_Admin_Bar $wp_admin_bar): void {
        $wp_admin_bar->remove_node('wp-logo');
    }

    public function remove_howdy(\WP_Admin_Bar $wp_admin_bar): void {
        $account = $wp_admin_bar->get_node('my-account');
        if ($account) {
            $title = str_replace('Howdy, ', '', $account->title);
            $wp_admin_bar->add_node([
                'id'    => 'my-account',
                'title' => $title,
            ]);
        }
    }

    public function hide_frontend_admin_bar(): void {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }
}
