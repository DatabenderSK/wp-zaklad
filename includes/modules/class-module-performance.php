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
            'wpzaklad_defer_js'                 => 0,
            'wpzaklad_defer_js_exclude'         => '',
            'wpzaklad_delay_js'                 => 0,
            'wpzaklad_delay_js_keywords'        => "sbi-scripts\nbit-assist\ninstagram-feed\nfacebook-feed\ngtag\nanalytics\nclarity",
            'wpzaklad_delay_js_timeout'         => 5,
            'wpzaklad_async_css'                => 0,
            'wpzaklad_async_css_exclude'        => "generate-style\ngeneratepress-dynamic",
            'wpzaklad_hero_preload_url'         => '',
            'wpzaklad_force_font_swap'          => 0,
            'wpzaklad_preload_fonts'            => '',
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
            ['key' => 'wpzaklad_hero_eager_load',    'type' => 'checkbox', 'label' => wpbl_t('hero_eager_load_label'),    'desc' => wpbl_t('hero_eager_load_desc'),    'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_hero_eager_class',   'type' => 'text',     'label' => wpbl_t('hero_eager_class_label'),   'desc' => wpbl_t('hero_eager_class_desc')],

            // --- PageSpeed optimizations ---
            ['type' => 'heading', 'label' => wpbl_t('pagespeed_section_title')],

            ['key' => 'wpzaklad_defer_js',           'type' => 'checkbox',  'label' => wpbl_t('defer_js_label'),           'desc' => wpbl_t('defer_js_desc'),           'recommended' => true, 'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_defer_js_exclude',   'type' => 'textarea',  'label' => wpbl_t('defer_js_exclude_label'),   'desc' => wpbl_t('defer_js_exclude_desc')],
            ['key' => 'wpzaklad_delay_js',           'type' => 'checkbox',  'label' => wpbl_t('delay_js_label'),           'desc' => wpbl_t('delay_js_desc'),           'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_delay_js_keywords',  'type' => 'textarea',  'label' => wpbl_t('delay_js_keywords_label'),  'desc' => wpbl_t('delay_js_keywords_desc')],
            ['key' => 'wpzaklad_delay_js_timeout',   'type' => 'number',    'label' => wpbl_t('delay_js_timeout_label'),   'desc' => wpbl_t('delay_js_timeout_desc'),   'min' => 1, 'max' => 30],
            ['key' => 'wpzaklad_async_css',          'type' => 'checkbox',  'label' => wpbl_t('async_css_label'),          'desc' => wpbl_t('async_css_desc'),          'recommended' => true, 'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_async_css_exclude',  'type' => 'textarea',  'label' => wpbl_t('async_css_exclude_label'),  'desc' => wpbl_t('async_css_exclude_desc')],
            ['key' => 'wpzaklad_hero_preload_url',   'type' => 'text',      'label' => wpbl_t('hero_preload_url_label'),   'desc' => wpbl_t('hero_preload_url_desc'),   'new' => true],
            ['key' => 'wpzaklad_force_font_swap',    'type' => 'checkbox',  'label' => wpbl_t('force_font_swap_label'),    'desc' => wpbl_t('force_font_swap_desc'),    'recommended' => true, 'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_preload_fonts',      'type' => 'textarea',  'label' => wpbl_t('preload_fonts_label'),      'desc' => wpbl_t('preload_fonts_desc'),      'new' => true],
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

        // --- PageSpeed optimizations ---

        // Font-display: swap (output buffer on wp_head)
        if ($this->get('wpzaklad_force_font_swap') && !is_admin()) {
            add_action('wp_head', [$this, 'ob_start_font_swap'], 1);
            add_action('wp_head', [$this, 'ob_end_font_swap'], 9999);
        }

        // Hero image preload URL
        $hero_url = trim((string) $this->get('wpzaklad_hero_preload_url'));
        if ($hero_url !== '') {
            add_action('wp_head', function () use ($hero_url): void {
                if (!is_front_page()) return;
                $url = esc_url($hero_url);
                echo '<link rel="preload" as="image" href="' . $url . '">' . "\n";
            }, 1);
        }

        // Preload fonts
        $preload_fonts = trim((string) $this->get('wpzaklad_preload_fonts'));
        if ($preload_fonts !== '') {
            add_action('wp_head', function () use ($preload_fonts): void {
                $urls = array_filter(array_map('trim', preg_split('/[\r\n]+/', $preload_fonts)));
                foreach ($urls as $url) {
                    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                    $type = match ($ext) {
                        'woff2' => 'font/woff2',
                        'woff'  => 'font/woff',
                        'ttf'   => 'font/ttf',
                        'otf'   => 'font/otf',
                        default => 'font/woff2',
                    };
                    echo '<link rel="preload" as="font" type="' . esc_attr($type) . '" href="' . esc_url($url) . '" crossorigin>' . "\n";
                }
            }, 2);
        }

        // Defer JavaScript
        if ($this->get('wpzaklad_defer_js') && !is_admin()) {
            $this->defer_js_exclude = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) $this->get('wpzaklad_defer_js_exclude'))));
            add_filter('script_loader_tag', [$this, 'defer_scripts'], 10, 3);
        }

        // Delay JavaScript (after defer, priority 11)
        if ($this->get('wpzaklad_delay_js') && !is_admin()) {
            $this->delay_js_keywords = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) $this->get('wpzaklad_delay_js_keywords'))));
            $this->delay_js_timeout  = max(1, (int) $this->get('wpzaklad_delay_js_timeout'));
            if (!empty($this->delay_js_keywords)) {
                add_filter('script_loader_tag', [$this, 'delay_scripts'], 11, 3);
                add_action('wp_footer', [$this, 'render_delay_loader'], 999);
            }
        }

        // Async CSS
        if ($this->get('wpzaklad_async_css') && !is_admin()) {
            $this->async_css_exclude = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) $this->get('wpzaklad_async_css_exclude'))));
            add_filter('style_loader_tag', [$this, 'async_styles'], 10, 4);
        }

        // --- Existing hero eager load ---

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

    // --- Font-display: swap ---

    /** @var string[] */
    private array $defer_js_exclude = [];
    /** @var string[] */
    private array $delay_js_keywords = [];
    private int $delay_js_timeout = 5;
    /** @var string[] */
    private array $async_css_exclude = [];
    private bool $has_delayed_scripts = false;

    public function ob_start_font_swap(): void {
        ob_start();
    }

    public function ob_end_font_swap(): void {
        $html = ob_get_clean();
        if ($html === false) return;

        // Add or replace font-display in all @font-face blocks
        $html = preg_replace_callback('/@font-face\s*\{([^}]+)\}/i', function (array $m): string {
            $block = $m[1];
            if (preg_match('/font-display\s*:/i', $block)) {
                $block = preg_replace('/font-display\s*:\s*[^;]+;?/i', 'font-display:swap;', $block);
            } else {
                $block = rtrim($block, " \t\n\r") . "font-display:swap;";
            }
            return '@font-face{' . $block . '}';
        }, $html);

        echo $html;
    }

    // --- Defer JavaScript ---

    public function defer_scripts(string $tag, string $handle, string $src): string {
        // Skip if already has defer/async or is a module
        if (preg_match('/\b(defer|async|type\s*=\s*["\']module)\b/i', $tag)) {
            return $tag;
        }

        // Skip excluded handles
        foreach ($this->defer_js_exclude as $exclude) {
            if ($exclude !== '' && str_contains($handle, $exclude)) {
                return $tag;
            }
        }

        return str_replace(' src=', ' defer src=', $tag);
    }

    // --- Delay JavaScript ---

    public function delay_scripts(string $tag, string $handle, string $src): string {
        // Check if this script matches any delay keyword
        $matched = false;
        foreach ($this->delay_js_keywords as $keyword) {
            if ($keyword !== '' && (str_contains($handle, $keyword) || str_contains($src, $keyword) || str_contains($tag, $keyword))) {
                $matched = true;
                break;
            }
        }
        if (!$matched) return $tag;

        $this->has_delayed_scripts = true;

        // Change type to prevent execution, store original type
        $tag = preg_replace('/type\s*=\s*["\'][^"\']*["\']/i', 'type="wpzaklad/delayed"', $tag);
        if (!str_contains($tag, 'type=')) {
            $tag = str_replace('<script ', '<script type="wpzaklad/delayed" ', $tag);
        }

        // Remove defer/async as they're irrelevant for delayed scripts
        $tag = preg_replace('/\s+(defer|async)\b/i', '', $tag);

        return $tag;
    }

    public function render_delay_loader(): void {
        if (!$this->has_delayed_scripts) return;

        $timeout = $this->delay_js_timeout * 1000;
        ?>
<script>
(function(){var d=false;function r(){if(d)return;d=true;
document.querySelectorAll('script[type="wpzaklad/delayed"]').forEach(function(s){
var n=document.createElement('script');
if(s.src)n.src=s.src;else n.textContent=s.textContent;
Array.from(s.attributes).forEach(function(a){if(a.name!=='type')n.setAttribute(a.name,a.value)});
s.parentNode.replaceChild(n,s);
});}
var e=['scroll','click','touchstart','mouseover','keydown'];
e.forEach(function(ev){window.addEventListener(ev,r,{once:true,passive:true})});
setTimeout(r,<?php echo $timeout; ?>);
})();
</script>
        <?php
    }

    // --- Async CSS ---

    public function async_styles(string $tag, string $handle, string $href, string $media): string {
        // Skip excluded handles
        foreach ($this->async_css_exclude as $exclude) {
            if ($exclude !== '' && str_contains($handle, $exclude)) {
                return $tag;
            }
        }

        // Convert stylesheet to preload with onload fallback
        $noscript = '<noscript>' . $tag . '</noscript>';
        $tag = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
        $tag = str_replace('rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag);

        return $tag . "\n" . $noscript;
    }
}
