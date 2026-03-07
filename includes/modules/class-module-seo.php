<?php
defined('ABSPATH') || exit;

class WPBL_Module_Seo extends WPBL_Module_Base {

    public function get_id(): string { return 'seo'; }
    public function get_label(): string { return wpbl_t('tab_seo'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_noindex_search'    => 0,
            'wpzaklad_noindex_archives'  => 0,
            'wpzaklad_remove_feed_links' => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_noindex_search',    'type' => 'checkbox', 'label' => wpbl_t('noindex_search_label'),    'desc' => wpbl_t('noindex_search_desc'),   'recommended' => true],
            ['key' => 'wpzaklad_noindex_archives',  'type' => 'checkbox', 'label' => wpbl_t('noindex_archives_label'),  'desc' => wpbl_t('noindex_archives_desc')],
            ['key' => 'wpzaklad_remove_feed_links', 'type' => 'checkbox', 'label' => wpbl_t('remove_feed_links_label'), 'desc' => wpbl_t('remove_feed_links_desc')],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_noindex_search') || $this->get('wpzaklad_noindex_archives')) {
            add_action('wp_head', [$this, 'output_noindex'], 1);
        }
        if ($this->get('wpzaklad_remove_feed_links')) {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
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

        if ($noindex) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
}
