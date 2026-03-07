<?php
defined('ABSPATH') || exit;

class WPBL_Module_Admin_Menu extends WPBL_Module_Base {

    public function get_id(): string { return 'admin-menu'; }

    public function get_label(): string { return wpbl_t('tab_admin_menu'); }

    public function get_defaults(): array { return []; }

    public function get_fields(): array { return []; }

    public function init(): void {
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', [$this, 'apply_menu_order']);

        if (current_user_can('manage_options')) {
            add_action('admin_bar_menu', [$this, 'apply_toolbar'], 999);
        }
    }

    // -------------------------------------------------------------------------
    // Render tab content
    // -------------------------------------------------------------------------

    public function render_custom_tab(): void {
        global $menu;

        $saved_order = get_option('wpzaklad_admin_menu_order', []);
        $toolbar     = get_option('wpzaklad_admin_menu_toolbar', []);

        // Build ordered menu items list
        $menu_items = [];
        foreach ($menu as $item) {
            $slug  = $item[2] ?? '';
            $label = wp_strip_all_tags($item[0] ?? '');
            if ($slug === '') continue;
            $menu_items[$slug] = $label;
        }

        // Re-sort by saved order, append new items at end
        if (!empty($saved_order)) {
            $sorted = [];
            foreach ($saved_order as $slug) {
                if (isset($menu_items[$slug])) {
                    $sorted[$slug] = $menu_items[$slug];
                }
            }
            foreach ($menu_items as $slug => $label) {
                if (!isset($sorted[$slug])) {
                    $sorted[$slug] = $label;
                }
            }
            $menu_items = $sorted;
        }

        $order_value = implode(',', array_keys($menu_items));
        ?>

        <!-- Menu order -->
        <div class="wpbl-setting wpbl-admin-menu-section">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('admin_menu_order_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo esc_html(wpbl_t('admin_menu_order_desc')); ?></span>
                <input type="hidden" id="wpzaklad_menu_order_string" name="wpzaklad_menu_order_string" value="<?php echo esc_attr($order_value); ?>">
                <ul id="wpbl-menu-sortable">
                    <?php foreach ($menu_items as $slug => $label): ?>
                        <?php $is_sep = ($label === '' || str_starts_with($slug, 'separator')); ?>
                        <li class="<?php echo $is_sep ? 'wpbl-menu-separator' : 'wpbl-menu-card'; ?>"
                            data-slug="<?php echo esc_attr($slug); ?>">
                            <?php if (!$is_sep): ?>
                                <span class="wpbl-menu-handle dashicons dashicons-menu-alt2"></span>
                                <?php echo esc_html($label ?: $slug); ?>
                            <?php else: ?>
                                <span class="wpbl-sep-line"></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Toolbar quick links -->
        <div class="wpbl-setting wpbl-admin-menu-section">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('admin_menu_toolbar_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo esc_html(wpbl_t('admin_menu_toolbar_desc')); ?></span>
                <table class="wpbl-toolbar-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html(wpbl_t('admin_menu_toolbar_name')); ?></th>
                            <th><?php echo esc_html(wpbl_t('admin_menu_toolbar_url')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $toolbar = array_pad((array) $toolbar, 5, ['title' => '', 'url' => '']);
                        for ($i = 0; $i < 5; $i++):
                            $item = $toolbar[$i];
                        ?>
                        <tr>
                            <td><input type="text" name="wpzaklad_toolbar[<?php echo $i; ?>][title]" value="<?php echo esc_attr($item['title'] ?? ''); ?>" class="regular-text"></td>
                            <td><input type="text" name="wpzaklad_toolbar[<?php echo $i; ?>][url]" value="<?php echo esc_attr($item['url'] ?? ''); ?>" class="regular-text" placeholder="admin.php?page=…"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
    }

    // -------------------------------------------------------------------------
    // Save custom data
    // -------------------------------------------------------------------------

    public function save_custom_data(array $post): void {
        // Menu order
        if (isset($post['wpzaklad_menu_order_string'])) {
            $raw   = sanitize_text_field(wp_unslash($post['wpzaklad_menu_order_string']));
            $order = array_values(array_filter(array_map('trim', explode(',', $raw))));
            update_option('wpzaklad_admin_menu_order', $order);
        }

        // Toolbar links
        if (isset($post['wpzaklad_toolbar']) && is_array($post['wpzaklad_toolbar'])) {
            $clean = [];
            foreach ($post['wpzaklad_toolbar'] as $item) {
                $title = sanitize_text_field(wp_unslash($item['title'] ?? ''));
                $url   = sanitize_text_field(wp_unslash($item['url'] ?? ''));
                // Strip full domain + optional /wp-admin/ prefix
                $url   = preg_replace('#^https?://[^/]+(/wp-admin/)?#', '', $url);
                $url   = ltrim($url, '/');
                if ($title !== '' || $url !== '') {
                    $clean[] = ['title' => $title, 'url' => $url];
                }
            }
            update_option('wpzaklad_admin_menu_toolbar', $clean);
        }
    }

    // -------------------------------------------------------------------------
    // Apply menu order
    // -------------------------------------------------------------------------

    public function apply_menu_order(array $menu_order): array {
        $saved = get_option('wpzaklad_admin_menu_order', []);
        if (empty($saved)) return $menu_order;

        $remaining = array_values(array_diff($menu_order, $saved));
        return array_merge($saved, $remaining);
    }

    // -------------------------------------------------------------------------
    // Apply toolbar quick links
    // -------------------------------------------------------------------------

    public function apply_toolbar(\WP_Admin_Bar $bar): void {
        $links = array_filter(
            (array) get_option('wpzaklad_admin_menu_toolbar', []),
            static fn($l) => !empty($l['title'])
        );

        if (empty($links)) return;

        $bar->add_node([
            'id'    => 'wpzaklad-toolbar',
            'title' => '<span class="ab-icon dashicons dashicons-bolt" style="top:2px;"></span>',
            'href'  => '#',
        ]);

        foreach (array_values($links) as $i => $link) {
            $href = $link['url'] ?? '';
            if ($href !== '' && !str_contains($href, '://')) {
                $href = admin_url($href);
            }
            $bar->add_node([
                'parent' => 'wpzaklad-toolbar',
                'id'     => 'wpzaklad-toolbar-' . $i,
                'title'  => esc_html($link['title']),
                'href'   => esc_url($href),
            ]);
        }
    }
}
