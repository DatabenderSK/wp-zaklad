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
            'wpzaklad_duplicate_posts'        => 0,
            'wpzaklad_featured_image_column'  => 0,
            'wpzaklad_media_filesize_column'  => 0,
            'wpzaklad_external_links_blank'   => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_disable_comments',       'type' => 'checkbox', 'label' => wpbl_t('disable_comments_label'),          'desc' => wpbl_t('disable_comments_desc'),          'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_gutenberg',      'type' => 'checkbox', 'label' => wpbl_t('disable_gutenberg_label'),         'desc' => wpbl_t('disable_gutenberg_desc')],
            ['key' => 'wpzaklad_allow_svg',              'type' => 'checkbox', 'label' => wpbl_t('allow_svg_label'),                 'desc' => wpbl_t('allow_svg_desc'), 'mine' => true],
            ['key' => 'wpzaklad_lowercase_filenames',    'type' => 'checkbox', 'label' => wpbl_t('lowercase_filenames_label'),       'desc' => wpbl_t('lowercase_filenames_desc'), 'mine' => true],
            ['key' => 'wpzaklad_year_shortcode',         'type' => 'checkbox', 'label' => wpbl_t('year_shortcode_label'),            'desc' => wpbl_t('year_shortcode_desc'), 'mine' => true],
            ['key' => 'wpzaklad_search_title_shortcode', 'type' => 'checkbox', 'label' => wpbl_t('search_title_shortcode_label'),    'desc' => wpbl_t('search_title_shortcode_desc'), 'mine' => true],
            ['key' => 'wpzaklad_clean_block_editor',     'type' => 'checkbox', 'label' => wpbl_t('clean_block_editor_label'),        'desc' => wpbl_t('clean_block_editor_desc')],
            ['key' => 'wpzaklad_disable_archives',       'type' => 'checkbox', 'label' => wpbl_t('disable_archives_label'),          'desc' => wpbl_t('disable_archives_desc')],
            ['key' => 'wpzaklad_clean_archive_titles',   'type' => 'checkbox', 'label' => wpbl_t('clean_archive_titles_label'),      'desc' => wpbl_t('clean_archive_titles_desc')],
            ['key' => 'wpzaklad_disable_author_archive', 'type' => 'checkbox', 'label' => wpbl_t('disable_author_archive_label'),    'desc' => wpbl_t('disable_author_archive_desc')],
            ['key' => 'wpzaklad_duplicate_posts',        'type' => 'checkbox', 'label' => wpbl_t('duplicate_posts_label'),           'desc' => wpbl_t('duplicate_posts_desc'),           'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_featured_image_column',  'type' => 'checkbox', 'label' => wpbl_t('featured_image_column_label'),    'desc' => wpbl_t('featured_image_column_desc'),    'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_media_filesize_column',  'type' => 'checkbox', 'label' => wpbl_t('media_filesize_column_label'),    'desc' => wpbl_t('media_filesize_column_desc'),    'mine' => true, 'new' => true],
            ['key' => 'wpzaklad_external_links_blank',   'type' => 'checkbox', 'label' => wpbl_t('external_links_blank_label'),     'desc' => wpbl_t('external_links_blank_desc'),     'mine' => true, 'new' => true],
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
        if ($this->get('wpzaklad_duplicate_posts')) {
            add_filter('post_row_actions',  [$this, 'add_duplicate_link'], 10, 2);
            add_filter('page_row_actions',  [$this, 'add_duplicate_link'], 10, 2);
            add_action('admin_action_wpbl_duplicate_post', [$this, 'handle_duplicate']);
        }
        if ($this->get('wpzaklad_featured_image_column')) {
            add_filter('manage_posts_columns',        [$this, 'add_thumbnail_column']);
            add_filter('manage_pages_columns',        [$this, 'add_thumbnail_column']);
            add_action('manage_posts_custom_column',  [$this, 'render_thumbnail_column'], 10, 2);
            add_action('manage_pages_custom_column',  [$this, 'render_thumbnail_column'], 10, 2);
            add_action('admin_head', [$this, 'thumbnail_column_css']);
        }
        if ($this->get('wpzaklad_media_filesize_column')) {
            add_filter('manage_upload_columns',       [$this, 'add_filesize_column']);
            add_action('manage_media_custom_column',  [$this, 'render_filesize_column'], 10, 2);
        }
        if ($this->get('wpzaklad_external_links_blank')) {
            add_filter('the_content', [$this, 'external_links_new_tab'], 20);
        }
    }

    /**
     * Allow SVG uploads for administrators only.
     * Restricted to manage_options to prevent editor-level users from uploading
     * SVGs that could execute JavaScript when opened directly in the browser.
     */
    public function allow_svg_mime(array $mimes): array {
        if (current_user_can('manage_options')) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * Sanitize uploaded SVG files using a DOMDocument-based allowlist approach.
     * Blocklist-based regex sanitizers (e.g. stripping <script>) are bypassable
     * via <foreignObject>, <use xlink:href>, CDATA blocks, and encoding tricks.
     * An allowlist of safe elements and attributes is the only reliable defence.
     */
    public function sanitize_svg_upload(array $file): array {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'svg') {
            return $file;
        }

        if (empty($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            $file['error'] = 'Could not read uploaded SVG file.';
            return $file;
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            $file['error'] = 'Could not read uploaded SVG file.';
            return $file;
        }

        $sanitized = $this->sanitize_svg_content($content);
        if ($sanitized === false) {
            $file['error'] = 'Invalid or unsafe SVG file rejected during sanitization.';
            return $file;
        }

        file_put_contents($file['tmp_name'], $sanitized);
        return $file;
    }

    /**
     * Parse SVG with DOMDocument and strip everything not on the allowlist.
     * Prevents XXE via LIBXML_NONET | LIBXML_NOENT.
     *
     * @return string|false Sanitized SVG markup, or false if the document is invalid.
     */
    private function sanitize_svg_content(string $content): string|false {
        $allowed_elements = [
            'svg', 'g', 'path', 'circle', 'rect', 'polygon', 'polyline', 'line',
            'ellipse', 'use', 'defs', 'title', 'desc', 'symbol', 'clipPath',
            'linearGradient', 'radialGradient', 'stop', 'filter', 'mask', 'pattern',
            'text', 'tspan', 'image',
            'feBlend', 'feColorMatrix', 'feFlood', 'feGaussianBlur',
            'feMerge', 'feMergeNode', 'feOffset', 'feComposite', 'feTurbulence',
            'feDisplacementMap',
        ];

        $allowed_attributes = [
            'id', 'class', 'style', 'viewBox', 'width', 'height', 'x', 'y',
            'cx', 'cy', 'r', 'rx', 'ry', 'd', 'fill', 'stroke', 'stroke-width',
            'transform', 'opacity', 'points', 'x1', 'y1', 'x2', 'y2',
            'clip-path', 'clip-rule', 'fill-rule', 'fill-opacity', 'stroke-opacity',
            'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stop-color',
            'stop-opacity', 'gradientUnits', 'gradientTransform', 'patternUnits',
            'patternTransform', 'preserveAspectRatio', 'xmlns', 'xmlns:xlink',
            'xlink:href', 'href', 'type', 'in', 'in2', 'result', 'values',
            'stdDeviation', 'offset', 'dx', 'dy', 'font-size', 'font-family',
            'font-weight', 'text-anchor', 'dominant-baseline', 'color',
            'color-interpolation-filters', 'flood-color', 'flood-opacity',
            'lighting-color', 'marker-end', 'marker-start', 'marker-mid',
            'mask', 'visibility', 'display', 'overflow',
        ];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // LIBXML_NONET  – prevent external network requests (XXE via DTD)
        // LIBXML_NOENT  – prevent entity expansion
        $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();

        if (!$loaded) {
            return false;
        }

        $root = $dom->documentElement;
        if (!$root || strtolower($root->localName) !== 'svg') {
            return false;
        }

        $this->sanitize_svg_node($root, $allowed_elements, $allowed_attributes);

        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Recursively walk the SVG DOM tree.
     * – Removes elements not on the allowlist (incl. <script>, <foreignObject>, <animate>).
     * – Removes attributes not on the allowlist and all on* event handlers.
     * – Restricts href / xlink:href to internal fragment references (#id) only.
     * – Strips dangerous CSS patterns from style attributes.
     * – Removes processing instructions and comments.
     */
    private function sanitize_svg_node(DOMElement $node, array $allowed_elements, array $allowed_attributes): void {
        $to_remove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                /** @var DOMElement $child */
                if (!in_array(strtolower($child->localName), $allowed_elements, true)) {
                    $to_remove[] = $child;
                    continue;
                }

                // Collect attributes to remove (cannot modify during iteration)
                $attrs_to_remove = [];
                foreach (iterator_to_array($child->attributes) as $attr) {
                    /** @var DOMAttr $attr */
                    $attr_local = strtolower($attr->localName);

                    // Remove all event handlers (onclick, onload, onmouseover …)
                    if (str_starts_with($attr_local, 'on')) {
                        $attrs_to_remove[] = $attr->nodeName;
                        continue;
                    }

                    // Enforce attribute allowlist
                    if (!in_array($attr->nodeName, $allowed_attributes, true) &&
                        !in_array($attr_local, $allowed_attributes, true)) {
                        $attrs_to_remove[] = $attr->nodeName;
                        continue;
                    }

                    // href / xlink:href: only allow internal fragment references (#id)
                    if (in_array($attr_local, ['href', 'xlink:href'], true)) {
                        if (!str_starts_with(ltrim($attr->value), '#')) {
                            $attrs_to_remove[] = $attr->nodeName;
                        }
                        continue;
                    }

                    // style: strip expression(), behavior:, javascript:, vbscript:
                    if ($attr_local === 'style') {
                        $safe_style = preg_replace(
                            '/expression\s*\(|behavior\s*:|javascript\s*:|vbscript\s*:/i',
                            '',
                            $attr->value
                        );
                        $child->setAttribute($attr->nodeName, $safe_style ?? '');
                    }
                }

                foreach ($attrs_to_remove as $attr_name) {
                    $child->removeAttribute($attr_name);
                }

                // Recurse into allowed child elements
                $this->sanitize_svg_node($child, $allowed_elements, $allowed_attributes);

            } elseif (in_array($child->nodeType, [XML_PI_NODE, XML_COMMENT_NODE], true)) {
                // Remove processing instructions (e.g. xml-stylesheet) and comments
                $to_remove[] = $child;
            }
        }

        foreach ($to_remove as $el) {
            $node->removeChild($el);
        }
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
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    public function redirect_author_archive(): void {
        if (is_author()) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    public function clean_archive_title(string $title): string {
        return preg_replace('/^[^:]+:\s*/', '', strip_tags($title));
    }

    // -------------------------------------------------------------------------
    // Post duplication
    // -------------------------------------------------------------------------

    public function add_duplicate_link(array $actions, \WP_Post $post): array {
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        $url = wp_nonce_url(
            admin_url('admin.php?action=wpbl_duplicate_post&post_id=' . $post->ID),
            'wpbl_duplicate_' . $post->ID
        );
        $actions['wpbl_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html(wpbl_t('duplicate_link')) . '</a>';
        return $actions;
    }

    public function handle_duplicate(): void {
        $post_id = (int) ($_GET['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html(wpbl_t('duplicate_error')));
        }
        check_admin_referer('wpbl_duplicate_' . $post_id);

        $original = get_post($post_id);
        if (!$original) {
            wp_die(esc_html(wpbl_t('duplicate_error')));
        }

        // 1. Insert new post (as draft)
        $new_id = wp_insert_post([
            'post_type'      => $original->post_type,
            'post_title'     => $original->post_title . ' ' . wpbl_t('duplicate_suffix'),
            'post_content'   => $original->post_content,
            'post_excerpt'   => $original->post_excerpt,
            'post_author'    => get_current_user_id(),
            'post_status'    => 'draft',
            'post_parent'    => $original->post_parent,
            'menu_order'     => $original->menu_order,
            'post_password'  => $original->post_password,
            'comment_status' => $original->comment_status,
            'ping_status'    => $original->ping_status,
        ]);

        if (is_wp_error($new_id) || !$new_id) {
            wp_die(esc_html(wpbl_t('duplicate_error')));
        }

        // 2. Copy all post meta (ACF, RankMath, GenerateBlocks, custom fields...)
        $skip_meta = ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date'];
        $all_meta  = get_post_meta($post_id);

        foreach ($all_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, $skip_meta, true)) {
                continue;
            }
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_id, $meta_key, maybe_unserialize($meta_value));
            }
        }

        // 3. Copy all taxonomies (categories, tags, custom taxonomies)
        $taxonomies = get_object_taxonomies($original->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($new_id, $terms, $taxonomy);
            }
        }

        do_action('wpzaklad_post_duplicated', $new_id, $post_id);

        wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_id));
        exit;
    }

    // -------------------------------------------------------------------------
    // Featured image column
    // -------------------------------------------------------------------------

    public function add_thumbnail_column(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['wpbl_thumbnail'] = wpbl_t('featured_image_column_header');
            }
        }
        return $new;
    }

    public function render_thumbnail_column(string $column, int $post_id): void {
        if ($column !== 'wpbl_thumbnail') return;
        $thumb = get_the_post_thumbnail($post_id, [60, 60]);
        echo $thumb ?: '—';
    }

    public function thumbnail_column_css(): void {
        echo '<style>.column-wpbl_thumbnail{width:70px;}</style>' . "\n";
    }

    // -------------------------------------------------------------------------
    // Media filesize column
    // -------------------------------------------------------------------------

    public function add_filesize_column(array $columns): array {
        $columns['wpbl_filesize'] = wpbl_t('media_filesize_column_header');
        return $columns;
    }

    public function render_filesize_column(string $column, int $post_id): void {
        if ($column !== 'wpbl_filesize') return;
        $file = get_attached_file($post_id);
        if ($file && file_exists($file)) {
            echo esc_html(size_format(filesize($file), 2));
        } else {
            echo '—';
        }
    }

    // -------------------------------------------------------------------------
    // External links in new tab
    // -------------------------------------------------------------------------

    public function external_links_new_tab(string $content): string {
        if (empty($content)) return $content;

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);

        return preg_replace_callback(
            '/<a\s([^>]*href=["\']https?:\/\/[^"\']+["\'][^>]*)>/i',
            function (array $m) use ($home_host) {
                $tag = $m[0];
                // Skip if already has target
                if (preg_match('/\btarget\s*=/i', $tag)) return $tag;
                // Extract href host
                if (preg_match('/href=["\']https?:\/\/([^"\'\/]+)/i', $tag, $href)) {
                    $link_host = strtolower($href[1]);
                    if ($link_host === $home_host || str_ends_with($link_host, '.' . $home_host)) {
                        return $tag; // internal link
                    }
                }
                // Add target and rel
                return str_replace('<a ', '<a target="_blank" rel="noopener noreferrer" ', $tag);
            },
            $content
        );
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
