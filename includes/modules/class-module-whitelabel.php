<?php
defined('ABSPATH') || exit;

class WPBL_Module_Whitelabel extends WPBL_Module_Base {

    public function get_id(): string { return 'whitelabel'; }
    public function get_label(): string { return wpbl_t('tab_whitelabel'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_hide_wp_logo'           => 0,
            'wpzaklad_admin_footer_text'      => '',
            'wpzaklad_hide_howdy'             => 0,
            'wpzaklad_hide_frontend_bar'      => 0,
            'wpzaklad_manager_name'           => '',
            'wpzaklad_manager_email'          => '',
            'wpzaklad_manager_phone'          => '',
            'wpzaklad_manager_url'            => '',
            'wpzaklad_manager_booking_url'    => '',
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
            // Manager contact widget
            [
                'key'   => 'wpzaklad_manager_name',
                'type'  => 'text',
                'label' => wpbl_t('manager_name_label'),
            ],
            [
                'key'   => 'wpzaklad_manager_email',
                'type'  => 'text',
                'label' => wpbl_t('manager_email_label'),
            ],
            [
                'key'   => 'wpzaklad_manager_phone',
                'type'  => 'text',
                'label' => wpbl_t('manager_phone_label'),
            ],
            [
                'key'   => 'wpzaklad_manager_url',
                'type'  => 'text',
                'label' => wpbl_t('manager_url_label'),
                'desc'  => wpbl_t('manager_url_desc'),
            ],
            [
                'key'   => 'wpzaklad_manager_booking_url',
                'type'  => 'text',
                'label' => wpbl_t('manager_booking_label'),
                'desc'  => wpbl_t('manager_booking_desc'),
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

        add_action('wp_dashboard_setup', [$this, 'register_manager_widget']);
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

    // -------------------------------------------------------------------------
    // Manager contact widget
    // -------------------------------------------------------------------------

    public function register_manager_widget(): void {
        $name  = $this->get('wpzaklad_manager_name');
        $email = $this->get('wpzaklad_manager_email');
        $phone = $this->get('wpzaklad_manager_phone');
        if (!$name && !$email && !$phone) return;

        wp_add_dashboard_widget(
            'wpbl_manager_widget',
            wpbl_t('manager_widget_title'),
            [$this, 'render_manager_widget']
        );
    }

    public function render_manager_widget(): void {
        $name    = $this->get('wpzaklad_manager_name');
        $email   = $this->get('wpzaklad_manager_email');
        $phone   = $this->get('wpzaklad_manager_phone');
        $url     = $this->get('wpzaklad_manager_url');
        $booking = $this->get('wpzaklad_manager_booking_url');
        ?>
        <div class="wpbl-manager">
            <?php if ($name): ?>
                <p class="wpbl-manager-name"><?php echo esc_html($name); ?></p>
            <?php endif; ?>

            <ul class="wpbl-manager-contacts">
                <?php if ($email): ?>
                    <li><span class="dashicons dashicons-email-alt"></span> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li>
                <?php endif; ?>
                <?php if ($phone): ?>
                    <li><span class="dashicons dashicons-phone"></span> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></li>
                <?php endif; ?>
                <?php if ($url): ?>
                    <li><span class="dashicons dashicons-admin-site-alt3"></span> <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('#^https?://#', '', rtrim($url, '/'))); ?></a></li>
                <?php endif; ?>
            </ul>

            <?php if ($booking): ?>
                <p class="wpbl-manager-booking">
                    <a href="<?php echo esc_url($booking); ?>" target="_blank" rel="noopener" class="button button-small">
                        <?php echo esc_html(wpbl_t('manager_booking_btn')); ?> &rarr;
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
