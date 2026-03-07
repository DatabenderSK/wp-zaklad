<?php
defined('ABSPATH') || exit;

class WPBL_Module_Content extends WPBL_Module_Base {

    public function get_id(): string { return 'content'; }
    public function get_label(): string { return wpbl_t('tab_content'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_disable_comments'       => 0,
            'wpzaklad_disable_gutenberg'      => 0,
            'wpzaklad_allow_svg'              => 0,
            'wpzaklad_lowercase_filenames'    => 0,
            'wpzaklad_year_shortcode'         => 0,
            'wpzaklad_search_title_shortcode' => 0,
            'wpzaklad_clean_block_editor'     => 0,
            'wpzaklad_disable_archives'       => 0,
            'wpzaklad_clean_archive_titles'   => 0,
            'wpzaklad_disable_author_archive' => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_disable_comments',       'type' => 'checkbox', 'label' => wpbl_t('disable_comments_label'),          'desc' => wpbl_t('disable_comments_desc'),          'recommended' => true],
            ['key' => 'wpzaklad_disable_gutenberg',      'type' => 'checkbox', 'label' => wpbl_t('disable_gutenberg_label'),         'desc' => wpbl_t('disable_gutenberg_desc')],
            ['key' => 'wpzaklad_allow_svg',              'type' => 'checkbox', 'label' => wpbl_t('allow_svg_label'),                 'desc' => wpbl_t('allow_svg_desc')],
            ['key' => 'wpzaklad_lowercase_filenames',    'type' => 'checkbox', 'label' => wpbl_t('lowercase_filenames_label'),       'desc' => wpbl_t('lowercase_filenames_desc')],
            ['key' => 'wpzaklad_year_shortcode',         'type' => 'checkbox', 'label' => wpbl_t('year_shortcode_label'),            'desc' => wpbl_t('year_shortcode_desc')],
            ['key' => 'wpzaklad_search_title_shortcode', 'type' => 'checkbox', 'label' => wpbl_t('search_title_shortcode_label'),    'desc' => wpbl_t('search_title_shortcode_desc')],
            ['key' => 'wpzaklad_clean_block_editor',     'type' => 'checkbox', 'label' => wpbl_t('clean_block_editor_label'),        'desc' => wpbl_t('clean_block_editor_desc')],
            ['key' => 'wpzaklad_disable_archives',       'type' => 'checkbox', 'label' => wpbl_t('disable_archives_label'),          'desc' => wpbl_t('disable_archives_desc')],
            ['key' => 'wpzaklad_clean_archive_titles',   'type' => 'checkbox', 'label' => wpbl_t('clean_archive_titles_label'),      'desc' => wpbl_t('clean_archive_titles_desc')],
            ['key' => 'wpzaklad_disable_author_archive', 'type' => 'checkbox', 'label' => wpbl_t('disable_author_archive_label'),    'desc' => wpbl_t('disable_author_archive_desc')],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_disable_comments')) {
            $this->disable_comments();
        }
        if ($this->get('wpzaklad_disable_gutenberg')) {
            add_filter('use_block_editor_for_post', '__return_false');
            add_filter('use_block_editor_for_post_type', '__return_false');
        }
        if ($this->get('wpzaklad_allow_svg')) {
            add_filter('upload_mimes', [$this, 'allow_svg_mime']);
            add_filter('wp_handle_upload_prefilter', [$this, 'sanitize_svg_upload']);
        }
        if ($this->get('wpzaklad_lowercase_filenames')) {
            add_filter('sanitize_file_name', 'mb_strtolower');
        }
        if ($this->get('wpzaklad_year_shortcode')) {
            add_shortcode('rok', [$this, 'year_shortcode']);
            add_filter('the_content', [$this, 'replace_year_token']);
            add_filter('the_title',   [$this, 'replace_year_token']);
            add_filter('widget_text', [$this, 'replace_year_token']);
        }
        if ($this->get('wpzaklad_search_title_shortcode')) {
            add_shortcode('search_title', [$this, 'search_title_shortcode']);
        }
        if ($this->get('wpzaklad_clean_block_editor')) {
            add_action('after_setup_theme', function () {
                remove_theme_support('core-block-patterns');
            });
            add_filter('should_load_remote_block_patterns', '__return_false');
        }
        if ($this->get('wpzaklad_disable_archives')) {
            add_action('template_redirect', [$this, 'redirect_archives']);
        }
        if ($this->get('wpzaklad_clean_archive_titles')) {
            add_filter('get_the_archive_title', [$this, 'clean_archive_title']);
        }
        if ($this->get('wpzaklad_disable_author_archive')) {
            add_action('template_redirect', [$this, 'redirect_author_archive']);
        }
    }

    public function allow_svg_mime(array $mimes): array {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    public function sanitize_svg_upload(array $file): array {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['svg', 'svgz'], true)) {
            return $file;
        }
        $content = file_get_contents($file['tmp_name'] ?? '');
        if ($content === false) {
            $file['error'] = 'Could not read SVG file.';
            return $file;
        }
        $content = preg_replace('/<script[\s\S]*?<\/script>/i', '', $content);
        $content = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $content);
        $content = preg_replace("/\\bon\\w+\\s*=\\s*'[^']*'/i", '', $content);
        $content = preg_replace('/javascript\s*:/i', '', $content);
        file_put_contents($file['tmp_name'], $content);
        return $file;
    }

    public function year_shortcode(): string {
        return date('Y');
    }

    public function replace_year_token(string $content): string {
        return str_replace('$$rok$$', date('Y'), $content);
    }

    public function search_title_shortcode(): string {
        return esc_html(get_search_query());
    }

    public function redirect_archives(): void {
        if (is_attachment() || is_date()) {
            wp_redirect(home_url('/'), 301);
            exit;
        }
    }

    public function redirect_author_archive(): void {
        if (is_author()) {
            wp_redirect(home_url('/'), 301);
            exit;
        }
    }

    public function clean_archive_title(string $title): string {
        return preg_replace('/^[^:]+:\s*/', '', strip_tags($title));
    }

    private function disable_comments(): void {
        // Close comments on frontend
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);

        // Hide existing comments
        add_filter('comments_array', '__return_empty_array', 10, 2);

        // Remove comment support from post types
        add_action('init', function () {
            foreach (get_post_types() as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        });

        // Remove admin menu items
        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
        });

        // Remove admin bar item
        add_action('wp_before_admin_bar_render', function () {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
        });

        // Remove dashboard widget
        add_action('wp_dashboard_setup', function () {
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        });
    }
}
