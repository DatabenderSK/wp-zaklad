<?php
defined('ABSPATH') || exit;

class WPBL_Module_Seo extends WPBL_Module_Base {

    public function get_id(): string { return 'seo'; }
    public function get_label(): string { return wpbl_t('tab_seo'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_noindex_search'         => 0,
            'wpzaklad_noindex_archives'        => 0,
            'wpzaklad_noindex_paginated'       => 0,
            'wpzaklad_redirect_attachments'    => 0,
            'wpzaklad_open_graph'              => 0,
            'wpzaklad_remove_feed_links'       => 0,
            'wpzaklad_custom_robots_txt'       => '',
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_noindex_search',         'type' => 'checkbox', 'label' => wpbl_t('noindex_search_label'),       'desc' => wpbl_t('noindex_search_desc'),       'recommended' => true],
            ['key' => 'wpzaklad_noindex_archives',        'type' => 'checkbox', 'label' => wpbl_t('noindex_archives_label'),     'desc' => wpbl_t('noindex_archives_desc')],
            ['key' => 'wpzaklad_noindex_paginated',       'type' => 'checkbox', 'label' => wpbl_t('noindex_paginated_label'),    'desc' => wpbl_t('noindex_paginated_desc'),    'recommended' => true],
            ['key' => 'wpzaklad_redirect_attachments',    'type' => 'checkbox', 'label' => wpbl_t('redirect_attachments_label'), 'desc' => wpbl_t('redirect_attachments_desc'), 'recommended' => true],
            ['key' => 'wpzaklad_open_graph',              'type' => 'checkbox', 'label' => wpbl_t('open_graph_label'),           'desc' => wpbl_t('open_graph_desc')],
            ['key' => 'wpzaklad_remove_feed_links',       'type' => 'checkbox', 'label' => wpbl_t('remove_feed_links_label'),   'desc' => wpbl_t('remove_feed_links_desc')],
            ['key' => 'wpzaklad_custom_robots_txt',       'type' => 'textarea', 'label' => wpbl_t('custom_robots_txt_label'),   'desc' => wpbl_t('custom_robots_txt_desc'),   'sanitize' => 'raw'],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_noindex_search') || $this->get('wpzaklad_noindex_archives') || $this->get('wpzaklad_noindex_paginated')) {
            add_action('wp_head', [$this, 'output_noindex'], 1);
        }

        if ($this->get('wpzaklad_remove_feed_links')) {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        }

        if ($this->get('wpzaklad_redirect_attachments')) {
            add_action('template_redirect', [$this, 'redirect_attachment_pages']);
        }

        if ($this->get('wpzaklad_open_graph')) {
            add_action('wp_head', [$this, 'output_open_graph'], 5);
        }

        $robots_txt = (string) $this->get('wpzaklad_custom_robots_txt');
        if ($robots_txt !== '') {
            add_filter('robots_txt', fn() => $robots_txt);
        }
    }

    public function output_noindex(): void {
        $noindex = false;

        if ($this->get('wpzaklad_noindex_search') && is_search()) {
            $noindex = true;
        }
        if ($this->get('wpzaklad_noindex_archives') && (is_category() || is_tag() || is_date() || is_author())) {
            $noindex = true;
        }
        if ($this->get('wpzaklad_noindex_paginated') && is_paged()) {
            $noindex = true;
        }

        if ($noindex) {
            echo '<meta name="robots" content="noindex, follow">' . "\n"; // phpcs:ignore
        }
    }

    public function redirect_attachment_pages(): void {
        if (!is_attachment()) return;
        $post = get_post();
        $redirect = ($post && $post->post_parent)
            ? get_permalink($post->post_parent)
            : home_url('/');
        wp_safe_redirect($redirect, 301);
        exit;
    }

    public function output_open_graph(): void {
        // Skip if a dedicated SEO plugin already handles OG tags
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || class_exists('SeoPress')) {
            return;
        }

        $title = is_singular() ? get_the_title() : (get_bloginfo('name') . (is_front_page() ? '' : ' – ' . get_the_title()));
        $url   = is_singular() ? (string) get_permalink() : (string) home_url('/');
        $desc  = is_singular() ? strip_tags((string) get_the_excerpt()) : get_bloginfo('description');
        $image = (is_singular() && has_post_thumbnail()) ? (string) get_the_post_thumbnail_url(null, 'large') : '';
        $type  = is_singular() && !is_front_page() ? 'article' : 'website';

        echo '<meta property="og:type"  content="' . esc_attr($type) . '">' . "\n";  // phpcs:ignore
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n"; // phpcs:ignore
        echo '<meta property="og:url"   content="' . esc_url($url) . '">' . "\n";    // phpcs:ignore
        if ($desc)  echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";  // phpcs:ignore
        if ($image) echo '<meta property="og:image"       content="' . esc_url($image) . '">' . "\n"; // phpcs:ignore
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n"; // phpcs:ignore
    }
}
