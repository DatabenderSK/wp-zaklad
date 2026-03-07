<?php
defined('ABSPATH') || exit;

class WPBL_Module_Scripts extends WPBL_Module_Base {

    public function get_id(): string { return 'scripts'; }
    public function get_label(): string { return wpbl_t('tab_scripts'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_gtm_id'      => '',
            'wpzaklad_head_code'   => '',
            'wpzaklad_footer_code' => '',
        ];
    }

    public function get_fields(): array {
        return [
            [
                'key'      => 'wpzaklad_gtm_id',
                'type'     => 'text',
                'label'    => wpbl_t('gtm_id_label'),
                'desc'     => wpbl_t('gtm_id_desc'),
                'sanitize' => 'raw',
            ],
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

    public function init(): void {
        $gtm_id = sanitize_text_field($this->get('wpzaklad_gtm_id'));

        if ($gtm_id) {
            add_action('wp_head', fn() => $this->output_gtm_head($gtm_id), 1);
            add_action('wp_body_open', fn() => $this->output_gtm_body($gtm_id), 1);
        }

        if ($this->get('wpzaklad_head_code')) {
            add_action('wp_head', [$this, 'output_head_code'], 99);
        }

        if ($this->get('wpzaklad_footer_code')) {
            add_action('wp_footer', [$this, 'output_footer_code'], 99);
        }
    }

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
}
