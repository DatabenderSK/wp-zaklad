<?php
defined('ABSPATH') || exit;

class WPBL_Module_Performance extends WPBL_Module_Base {

    public function get_id(): string { return 'performance'; }
    public function get_label(): string { return wpbl_t('tab_performance'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_heartbeat'                => '',
            'wpzaklad_remove_query_strings'     => 0,
            'wpzaklad_disable_big_image'        => 0,
            'wpzaklad_revisions_limit'          => 10,
            'wpzaklad_disable_dashicons'        => 0,
            'wpzaklad_disable_font_library'     => 0,
            'wpzaklad_hero_eager_load'          => 0,
            'wpzaklad_hero_eager_class'         => '',
        ];
    }

    public function get_fields(): array {
        return [
            [
                'key'     => 'wpzaklad_heartbeat',
                'type'    => 'select',
                'label'   => wpbl_t('heartbeat_label'),
                'desc'    => wpbl_t('heartbeat_desc'),
                'options' => [
                    ''      => wpbl_t('heartbeat_default'),
                    'off'   => wpbl_t('heartbeat_off'),
                    '60'    => wpbl_t('heartbeat_slow60'),
                    '120'   => wpbl_t('heartbeat_slow120'),
                ],
            ],
            [
                'key'         => 'wpzaklad_remove_query_strings',
                'type'        => 'checkbox',
                'label'       => wpbl_t('remove_query_strings_label'),
                'desc'        => wpbl_t('remove_query_strings_desc'),
                'recommended' => true,
            ],
            [
                'key'         => 'wpzaklad_disable_big_image',
                'type'        => 'checkbox',
                'label'       => wpbl_t('disable_big_image_label'),
                'desc'        => wpbl_t('disable_big_image_desc'),
                'recommended' => true,
            ],
            [
                'key'  => 'wpzaklad_revisions_limit',
                'type' => 'number',
                'label' => wpbl_t('revisions_limit_label'),
                'desc'  => wpbl_t('revisions_limit_desc'),
                'min'  => -1,
            ],
            ['key' => 'wpzaklad_disable_dashicons',    'type' => 'checkbox', 'label' => wpbl_t('disable_dashicons_label'),    'desc' => wpbl_t('disable_dashicons_desc'),    'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_font_library', 'type' => 'checkbox', 'label' => wpbl_t('disable_font_library_label'), 'desc' => wpbl_t('disable_font_library_desc'), 'recommended' => true],
            ['key' => 'wpzaklad_hero_eager_load',    'type' => 'checkbox', 'label' => wpbl_t('hero_eager_load_label'),    'desc' => wpbl_t('hero_eager_load_desc'),    'recommended' => true, 'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_hero_eager_class',   'type' => 'text',     'label' => wpbl_t('hero_eager_class_label'),   'desc' => wpbl_t('hero_eager_class_desc')],
        ];
    }

    public function init(): void {
        $heartbeat = $this->get('wpzaklad_heartbeat');
        if ($heartbeat === 'off') {
            add_action('init', function () {
                wp_deregister_script('heartbeat');
            }, 1);
        } elseif (in_array($heartbeat, ['60', '120'], true)) {
            add_filter('heartbeat_settings', function (array $settings) use ($heartbeat): array {
                $settings['interval'] = (int) $heartbeat;
                return $settings;
            });
        }

        if ($this->get('wpzaklad_remove_query_strings')) {
            add_filter('style_loader_src',  [$this, 'remove_query_string'], 15);
            add_filter('script_loader_src', [$this, 'remove_query_string'], 15);
        }

        if ($this->get('wpzaklad_disable_big_image')) {
            add_filter('big_image_size_threshold', '__return_false');
        }

        $revisions = (int) $this->get('wpzaklad_revisions_limit');
        if ($revisions !== 0) {
            add_filter('wp_revisions_to_keep', function (int $num) use ($revisions): int {
                return $revisions < 0 ? 0 : $revisions;
            }, 10, 1);
        }

        if ($this->get('wpzaklad_disable_dashicons')) {
            add_action('wp_enqueue_scripts', [$this, 'disable_dashicons']);
        }

        if ($this->get('wpzaklad_disable_font_library')) {
            add_filter('block_editor_settings_all', [$this, 'disable_font_library_editor']);
            add_filter('rest_endpoints', [$this, 'remove_font_library_endpoints']);
        }

        $hero_class = trim((string) $this->get('wpzaklad_hero_eager_class'));
        if ($this->get('wpzaklad_hero_eager_load') && $hero_class !== '') {
            add_filter('wp_content_img_tag', function (string $image) use ($hero_class): string {
                if (!is_front_page()) return $image;

                // Check if any of the configured classes matches
                $classes = array_filter(array_map('trim', preg_split('/[\s,]+/', $hero_class)));
                $matched = false;
                foreach ($classes as $cls) {
                    if ($cls !== '' && str_contains($image, $cls)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) return $image;

                // Set eager loading + high fetch priority
                $image = str_replace('loading="lazy"', 'loading="eager"', $image);
                if (!str_contains($image, 'fetchpriority')) {
                    $image = str_replace('<img ', '<img fetchpriority="high" ', $image);
                }

                return $image;
            });
        }
    }

    public function disable_dashicons(): void {
        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
            wp_deregister_style('dashicons');
        }
    }


    public function disable_font_library_editor(array $settings): array {
        $settings['fontLibraryEnabled'] = false;
        return $settings;
    }

    public function remove_font_library_endpoints(array $endpoints): array {
        foreach ($endpoints as $route => $data) {
            if (str_contains($route, '/font-families')) {
                unset($endpoints[$route]);
            }
        }
        return $endpoints;
    }

    public function remove_query_string(string $src): string {
        $parts = explode('?ver=', $src);
        return $parts[0];
    }
}
