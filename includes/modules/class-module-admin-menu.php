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

        // Hide menu items for all users
        add_action('admin_menu', [$this, 'apply_hidden_items'], 9999);
    }

    // -------------------------------------------------------------------------
    // Render tab content
    // -------------------------------------------------------------------------

    public function render_custom_tab(): void {
        global $menu;

        $saved_order  = get_option('wpzaklad_admin_menu_order', []);
        $hidden_items = get_option('wpzaklad_admin_menu_hidden', []);
        $toolbar      = get_option('wpzaklad_admin_menu_toolbar', []);

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

        $order_value  = implode(',', array_keys($menu_items));
        $hidden_value = implode(',', $hidden_items);
        ?>

        <!-- Menu order & visibility -->
        <div class="wpbl-setting wpbl-admin-menu-section">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('admin_menu_order_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo wp_kses_post(wpbl_t('admin_menu_order_desc')); ?></span>
                <input type="hidden" id="wpzaklad_menu_order_string" name="wpzaklad_menu_order_string" value="<?php echo esc_attr($order_value); ?>">
                <input type="hidden" id="wpzaklad_menu_hidden_string" name="wpzaklad_menu_hidden_string" value="<?php echo esc_attr($hidden_value); ?>">
                <ul id="wpbl-menu-sortable">
                    <?php foreach ($menu_items as $slug => $label): ?>
                        <?php
                        $is_sep   = ($label === '' || str_starts_with($slug, 'separator'));
                        $is_hidden = in_array($slug, $hidden_items, true);
                        ?>
                        <li class="<?php echo $is_sep ? 'wpbl-menu-separator' : 'wpbl-menu-card'; ?><?php echo $is_hidden ? ' wpbl-menu-hidden' : ''; ?>"
                            data-slug="<?php echo esc_attr($slug); ?>">
                            <?php if (!$is_sep): ?>
                                <span class="wpbl-menu-handle dashicons dashicons-menu-alt2"></span>
                                <span class="wpbl-menu-label"><?php echo esc_html($label ?: $slug); ?></span>
                                <button type="button" class="wpbl-menu-visibility" title="<?php echo esc_attr(wpbl_t('admin_menu_toggle_visibility')); ?>">
                                    <span class="dashicons <?php echo $is_hidden ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
                                </button>
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
            </div>
        </div>

        <style>
        #wpbl-toolbar-list { margin-top: 4px; }
        .wpbl-toolbar-card {
            background: #f9f9f9;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 0;
        }
        .wpbl-toolbar-handle {
            padding: 12px 10px;
            cursor: grab;
            color: #c3c4c7;
            flex-shrink: 0;
            transition: color .15s;
        }
        .wpbl-toolbar-handle:hover { color: #787c82; }
        .wpbl-toolbar-card.ui-sortable-helper { box-shadow: 0 4px 16px rgba(0,0,0,.14); border-color: #2271b1; }
        .wpbl-toolbar-card.ui-sortable-placeholder { background: #f6f7f7; border: 2px dashed #c3c4c7; visibility: visible !important; }
        .wpbl-toolbar-fields {
            flex: 1;
            padding: 10px 10px 10px 0;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .wpbl-toolbar-fields input[type="text"] {
            flex: 1;
            font-size: 13px;
        }
        .wpbl-toolbar-remove-wrap {
            padding: 8px 10px 8px 0;
            flex-shrink: 0;
        }
        #wpbl-add-toolbar-link { margin-top: 4px; }
        </style>

        <div id="wpbl-toolbar-list">
            <?php foreach ($toolbar as $i => $item): ?>
            <div class="wpbl-toolbar-card">
                <span class="wpbl-toolbar-handle dashicons dashicons-move" title="<?php echo esc_attr(wpbl_t('help_videos_drag')); ?>"></span>
                <div class="wpbl-toolbar-fields">
                    <input type="text" name="wpzaklad_toolbar[<?php echo $i; ?>][title]" value="<?php echo esc_attr($item['title'] ?? ''); ?>" placeholder="<?php echo esc_attr(wpbl_t('admin_menu_toolbar_name')); ?>">
                    <input type="text" name="wpzaklad_toolbar[<?php echo $i; ?>][url]" value="<?php echo esc_attr($item['url'] ?? ''); ?>" placeholder="admin.php?page=…">
                </div>
                <div class="wpbl-toolbar-remove-wrap">
                    <button type="button" class="button button-small wpbl-toolbar-remove"><?php echo esc_html(wpbl_t('help_videos_remove_row')); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="wpbl-add-toolbar-link" class="button">
            <?php echo esc_html(wpbl_t('admin_menu_toolbar_add')); ?>
        </button>

        <script>
        (function($) {
            var list   = $('#wpbl-toolbar-list');
            var addBtn = $('#wpbl-add-toolbar-link');
            var lblName = <?php echo json_encode(wpbl_t('admin_menu_toolbar_name')); ?>;
            var lblDrag = <?php echo json_encode(wpbl_t('help_videos_drag')); ?>;
            var lblRm   = <?php echo json_encode(wpbl_t('help_videos_remove_row')); ?>;

            function reindex() {
                list.find('.wpbl-toolbar-card').each(function(i) {
                    $(this).find('input').each(function() {
                        this.name = this.name.replace(/\[\d+\]/, '[' + i + ']');
                    });
                });
            }

            function bindRemove(card) {
                card.find('.wpbl-toolbar-remove').on('click', function() {
                    card.remove();
                    reindex();
                });
            }

            function makeCard(idx) {
                var card = $('<div class="wpbl-toolbar-card">');
                card.append(
                    $('<span>').addClass('wpbl-toolbar-handle dashicons dashicons-move').attr('title', lblDrag)
                );
                var fields = $('<div class="wpbl-toolbar-fields">');
                fields.append(
                    $('<input type="text">').attr({ name: 'wpzaklad_toolbar[' + idx + '][title]', placeholder: lblName }),
                    $('<input type="text">').attr({ name: 'wpzaklad_toolbar[' + idx + '][url]', placeholder: 'admin.php?page=…' })
                );
                card.append(fields);
                card.append(
                    $('<div class="wpbl-toolbar-remove-wrap">').append(
                        $('<button type="button" class="button button-small wpbl-toolbar-remove">').text(lblRm)
                    )
                );
                return card;
            }

            addBtn.on('click', function() {
                var idx  = list.find('.wpbl-toolbar-card').length;
                var card = makeCard(idx);
                bindRemove(card);
                list.append(card);
                card.find('input').first().focus();
            });

            list.find('.wpbl-toolbar-card').each(function() {
                bindRemove($(this));
            });

            list.sortable({
                handle: '.wpbl-toolbar-handle',
                placeholder: 'wpbl-toolbar-card ui-sortable-placeholder',
                forcePlaceholderSize: true,
                stop: function() { reindex(); }
            });
        })(jQuery);
        </script>

        <!-- Toolbar export / import -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f1;">
            <strong style="font-size:13px;"><?php echo esc_html(wpbl_t('toolbar_export_title')); ?></strong>
            <div style="margin-top:8px;display:flex;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <p style="margin:0 0 4px;font-size:12px;color:#646970;"><?php echo esc_html(wpbl_t('portability_export_label')); ?></p>
                    <textarea id="wpbl-toolbar-export" class="large-text code" rows="4" readonly onclick="this.select()"><?php echo esc_textarea(wp_json_encode(get_option('wpzaklad_admin_menu_toolbar', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]'); ?></textarea>
                    <p style="margin:6px 0 0;display:flex;gap:6px;">
                        <button type="button" class="button button-small" id="wpbl-toolbar-copy"><?php echo esc_html(wpbl_t('portability_copy_btn')); ?></button>
                        <button type="button" class="button button-small" id="wpbl-toolbar-download"><?php echo esc_html(wpbl_t('portability_download_btn')); ?></button>
                    </p>
                </div>
                <div style="flex:1;min-width:200px;">
                    <p style="margin:0 0 4px;font-size:12px;color:#646970;"><?php echo esc_html(wpbl_t('portability_import_label')); ?></p>
                    <textarea id="wpbl-toolbar-import-json" class="large-text code" rows="4" placeholder="<?php echo esc_attr(wpbl_t('portability_import_placeholder')); ?>"></textarea>
                    <p style="margin:6px 0 0;display:flex;gap:6px;align-items:center;">
                        <button type="button" class="button button-small" id="wpbl-toolbar-import-btn"><?php echo esc_html(wpbl_t('portability_import_btn')); ?></button>
                        <label class="button button-small" for="wpbl-toolbar-file" style="cursor:pointer;"><?php echo esc_html(wpbl_t('portability_upload_btn')); ?></label>
                        <input type="file" id="wpbl-toolbar-file" accept=".json" style="display:none;">
                    </p>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            var lblDrag = <?php echo json_encode(wpbl_t('help_videos_drag')); ?>;
            var lblRm   = <?php echo json_encode(wpbl_t('help_videos_remove_row')); ?>;
            var lblName = <?php echo json_encode(wpbl_t('admin_menu_toolbar_name')); ?>;
            var errMsg  = <?php echo json_encode(wpbl_t('portability_invalid_json')); ?>;

            function applyToolbarJson(json) {
                try {
                    var items = JSON.parse(json);
                    if (!Array.isArray(items)) throw new Error();
                    var list = $('#wpbl-toolbar-list');
                    list.empty();
                    items.forEach(function(item, i) {
                        var card = $('<div class="wpbl-toolbar-card">');
                        card.append($('<span>').addClass('wpbl-toolbar-handle dashicons dashicons-move').attr('title', lblDrag));
                        var fields = $('<div class="wpbl-toolbar-fields">');
                        fields.append(
                            $('<input type="text">').attr({ name: 'wpzaklad_toolbar[' + i + '][title]', placeholder: lblName }).val(item.title || ''),
                            $('<input type="text">').attr({ name: 'wpzaklad_toolbar[' + i + '][url]', placeholder: 'admin.php?page=…' }).val(item.url || '')
                        );
                        card.append(fields);
                        card.append(
                            $('<div class="wpbl-toolbar-remove-wrap">').append(
                                $('<button type="button" class="button button-small wpbl-toolbar-remove">').text(lblRm)
                            )
                        );
                        card.find('.wpbl-toolbar-remove').on('click', function() { card.remove(); });
                        list.append(card);
                    });
                    $('#wpbl-toolbar-import-json').val('');
                } catch(e) {
                    alert(errMsg);
                }
            }

            $('#wpbl-toolbar-copy').on('click', function() {
                var ta = document.getElementById('wpbl-toolbar-export');
                ta.select(); document.execCommand('copy');
            });

            $('#wpbl-toolbar-download').on('click', function() {
                var data = $('#wpbl-toolbar-export').val();
                var blob = new Blob([data], {type: 'application/json'});
                var url  = URL.createObjectURL(blob);
                var a    = document.createElement('a');
                a.href = url; a.download = 'wpzaklad-toolbar-links.json';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });

            $('#wpbl-toolbar-import-btn').on('click', function() {
                applyToolbarJson($('#wpbl-toolbar-import-json').val().trim());
            });

            $('#wpbl-toolbar-file').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(evt) { applyToolbarJson(evt.target.result); };
                reader.readAsText(file);
                this.value = '';
            });
        })(jQuery);
        </script>

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

        // Hidden menu items
        if (isset($post['wpzaklad_menu_hidden_string'])) {
            $raw    = sanitize_text_field(wp_unslash($post['wpzaklad_menu_hidden_string']));
            $hidden = $raw !== '' ? array_values(array_filter(array_map('trim', explode(',', $raw)))) : [];
            update_option('wpzaklad_admin_menu_hidden', $hidden);
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
    // Apply hidden menu items (non-admins only)
    // -------------------------------------------------------------------------

    public function apply_hidden_items(): void {
        $hidden = get_option('wpzaklad_admin_menu_hidden', []);
        if (empty($hidden)) return;

        // Never hide Settings (WP Základ lives there)
        $protected = ['options-general.php'];

        foreach ($hidden as $slug) {
            if (in_array($slug, $protected, true)) continue;
            remove_menu_page($slug);
        }
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
            'title' => '<span class="ab-icon dashicons dashicons-bolt"></span><span class="ab-label">' . esc_html(wpbl_t('toolbar_label')) . '</span>',
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
