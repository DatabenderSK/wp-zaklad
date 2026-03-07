<?php
defined('ABSPATH') || exit;

class WPBL_Module_Optimization extends WPBL_Module_Base {

    public function get_id(): string { return 'optimization'; }
    public function get_label(): string { return wpbl_t('tab_optimization'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_disable_emoji'          => 0,
            'wpzaklad_disable_embeds'         => 0,
            'wpzaklad_remove_rss'             => 0,
            'wpzaklad_disable_xmlrpc'         => 0,
            'wpzaklad_remove_jquery_migrate'  => 0,
            'wpzaklad_disable_self_pingbacks' => 0,
            'wpzaklad_clean_head'             => 0,
            'wpzaklad_block_update_emails'    => 0,
            'wpzaklad_lazy_load_iframes'      => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_disable_emoji',          'type' => 'checkbox', 'label' => wpbl_t('disable_emoji_label'),          'desc' => wpbl_t('disable_emoji_desc'),          'recommended' => true],
            ['key' => 'wpzaklad_disable_embeds',         'type' => 'checkbox', 'label' => wpbl_t('disable_embeds_label'),         'desc' => wpbl_t('disable_embeds_desc')],
            ['key' => 'wpzaklad_remove_rss',             'type' => 'checkbox', 'label' => wpbl_t('remove_rss_label'),             'desc' => wpbl_t('remove_rss_desc')],
            ['key' => 'wpzaklad_disable_xmlrpc',         'type' => 'checkbox', 'label' => wpbl_t('disable_xmlrpc_label'),         'desc' => wpbl_t('disable_xmlrpc_desc'),         'recommended' => true],
            ['key' => 'wpzaklad_remove_jquery_migrate',  'type' => 'checkbox', 'label' => wpbl_t('remove_jquery_migrate_label'),  'desc' => wpbl_t('remove_jquery_migrate_desc'),  'recommended' => true],
            ['key' => 'wpzaklad_disable_self_pingbacks', 'type' => 'checkbox', 'label' => wpbl_t('disable_self_pingbacks_label'), 'desc' => wpbl_t('disable_self_pingbacks_desc'), 'recommended' => true],
            ['key' => 'wpzaklad_clean_head',             'type' => 'checkbox', 'label' => wpbl_t('clean_head_label'),             'desc' => wpbl_t('clean_head_desc'),             'recommended' => true],
            ['key' => 'wpzaklad_block_update_emails',    'type' => 'checkbox', 'label' => wpbl_t('block_update_emails_label'),    'desc' => wpbl_t('block_update_emails_desc'),    'recommended' => true],
            ['key' => 'wpzaklad_lazy_load_iframes',      'type' => 'checkbox', 'label' => wpbl_t('lazy_load_iframes_label'),      'desc' => wpbl_t('lazy_load_iframes_desc')],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_disable_emoji')) {
            $this->disable_emoji();
        }
        if ($this->get('wpzaklad_disable_embeds')) {
            $this->disable_embeds();
        }
        if ($this->get('wpzaklad_remove_rss')) {
            add_action('wp_head', [$this, 'remove_rss'], 1);
        }
        if ($this->get('wpzaklad_disable_xmlrpc')) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'remove_xmlrpc_header']);
        }
        if ($this->get('wpzaklad_remove_jquery_migrate')) {
            add_action('wp_default_scripts', [$this, 'remove_jquery_migrate']);
        }
        if ($this->get('wpzaklad_disable_self_pingbacks')) {
            add_action('pre_ping', [$this, 'disable_self_pingbacks']);
        }
        if ($this->get('wpzaklad_clean_head')) {
            $this->clean_head();
        }
        if ($this->get('wpzaklad_block_update_emails')) {
            $this->block_update_emails();
        }
        if ($this->get('wpzaklad_lazy_load_iframes')) {
            add_filter('the_content', [$this, 'lazy_load_iframes']);
            add_filter('oembed_result', [$this, 'lazy_load_iframes']);
        }
    }

    private function disable_emoji(): void {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
        add_filter('wp_resource_hints', [$this, 'disable_emoji_dns_prefetch'], 10, 2);
    }

    public function disable_emojis_tinymce(array $plugins): array {
        return array_diff($plugins, ['wpemoji']);
    }

    public function disable_emoji_dns_prefetch(array $urls, string $relation_type): array {
        if ($relation_type === 'dns-prefetch') {
            $urls = array_filter($urls, fn($url) => strpos($url, 'https://s.w.org/images/core/emoji') === false);
        }
        return $urls;
    }

    private function disable_embeds(): void {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        add_filter('embed_oembed_discover', '__return_false');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('rewrite_rules_array', [$this, 'disable_embeds_rewrite_rules']);
    }

    public function disable_embeds_rewrite_rules(array $rules): array {
        foreach ($rules as $rule => $rewrite) {
            if (strpos($rewrite, 'embed=true') !== false) {
                unset($rules[$rule]);
            }
        }
        return $rules;
    }

    public function remove_rss(): void {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }

    public function remove_xmlrpc_header(array $headers): array {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function remove_jquery_migrate(\WP_Scripts $scripts): void {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    public function disable_self_pingbacks(array &$links): void {
        $home = get_option('home');
        foreach ($links as $key => $link) {
            if (str_starts_with($link, $home)) {
                unset($links[$key]);
            }
        }
    }

    private function clean_head(): void {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
    }

    private function block_update_emails(): void {
        add_filter('auto_core_update_send_email',   '__return_false');
        add_filter('auto_plugin_update_send_email', '__return_false');
        add_filter('auto_theme_update_send_email',  '__return_false');
    }

    public function lazy_load_iframes(string $content): string {
        return str_replace('<iframe ', '<iframe loading="lazy" ', $content);
    }
}
