<?php
defined('ABSPATH') || exit;

class WPBL_Module_Help_Videos extends WPBL_Module_Base {

    public function get_id(): string { return 'help-videos'; }

    public function get_label(): string { return wpbl_t('tab_help_videos'); }

    public function get_defaults(): array { return []; }

    public function get_fields(): array { return []; }

    public function init(): void {
        add_action('wp_dashboard_setup', [$this, 'maybe_register_widget']);
    }

    public function maybe_register_widget(): void {
        $videos = get_option('wpzaklad_help_videos', []);
        if (empty($videos)) return;
        wp_add_dashboard_widget(
            'wpbl_help_videos_widget',
            wpbl_t('help_videos_widget_title'),
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget(): void {
        $videos = (array) get_option('wpzaklad_help_videos', []);
        if (empty($videos)) {
            echo '<p style="color:#646970;font-size:13px;">' . esc_html(wpbl_t('help_videos_empty')) . '</p>';
            return;
        }

        // Filter out empty entries
        $videos = array_values(array_filter($videos, static fn($v) => ($v['title'] ?? '') !== '' || ($v['url'] ?? '') !== ''));
        if (empty($videos)) return;

        $play_label = wpbl_t('help_videos_play_btn');
        $show_more  = wpbl_t('help_videos_show_more');
        $show_less  = wpbl_t('help_videos_show_less');
        $desc_limit = 110;
        $last_i     = count($videos) - 1;

        // YouTube icon SVG (inline, no external dependency)
        $yt_icon = '<svg width="22" height="15" viewBox="0 0 22 15" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;flex-shrink:0;">'
                 . '<rect width="22" height="15" rx="3" fill="#FF0000"/>'
                 . '<polygon points="9,3.5 9,11.5 16.5,7.5" fill="white"/>'
                 . '</svg>';

        echo '<ul style="margin:0;padding:0;list-style:none;">';

        foreach ($videos as $i => $video) {
            $title = $video['title'] ?? '';
            $desc  = $video['desc'] ?? '';
            $url   = $video['url'] ?? '';
            $sep   = ($i < $last_i) ? 'margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f1;' : 'margin-bottom:0;';

            // Collapsible description
            $has_more   = mb_strlen($desc) > $desc_limit;
            $short_desc = $has_more ? mb_substr($desc, 0, $desc_limit) . '…' : $desc;

            echo '<li style="' . $sep . '">';

            // Row: YouTube icon  |  title + play button
            echo '<div style="display:flex;align-items:center;gap:10px;">';

            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" title="' . esc_attr($title ?: $url) . '">'
               . $yt_icon // phpcs:ignore
               . '</a>';

            echo '<div style="flex:1;min-width:0;display:flex;align-items:baseline;justify-content:space-between;gap:6px;flex-wrap:wrap;">';

            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" '
               . 'style="color:#2271b1;text-decoration:underline;font-weight:600;font-size:13px;line-height:1.4;word-break:break-word;">'
               . esc_html($title ?: $url)
               . '</a>';

            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" '
               . 'style="white-space:nowrap;font-size:11px;padding:3px 9px;background:#2271b1;color:#fff;border-radius:3px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;line-height:1.4;">'
               . '&#9654; ' . esc_html($play_label)
               . '</a>';

            echo '</div></div>'; // end title row

            // Description
            if ($desc !== '') {
                echo '<div style="margin-top:5px;margin-left:32px;font-size:12px;color:#646970;line-height:1.5;">';
                if ($has_more) {
                    echo '<span class="wpbl-vd-short" data-i="' . $i . '">' . nl2br(esc_html($short_desc)) . '</span>';
                    echo '<span class="wpbl-vd-full" data-i="' . $i . '" style="display:none;">' . nl2br(esc_html($desc)) . '</span>';
                    echo '&nbsp;<a href="#" class="wpbl-vd-toggle" data-i="' . $i . '" '
                       . 'data-more="' . esc_attr($show_more) . '" data-less="' . esc_attr($show_less) . '" '
                       . 'style="font-size:11px;color:#2271b1;text-decoration:underline;">'
                       . esc_html($show_more) . '</a>';
                } else {
                    echo nl2br(esc_html($desc)); // phpcs:ignore
                }
                echo '</div>';
            }

            echo '</li>';
        }

        echo '</ul>';
        ?>
        <script>
        (function() {
            document.querySelectorAll('.wpbl-vd-toggle').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var i    = this.dataset.i;
                    var sh   = document.querySelector('.wpbl-vd-short[data-i="' + i + '"]');
                    var fl   = document.querySelector('.wpbl-vd-full[data-i="' + i + '"]');
                    var open = fl.style.display !== 'none';
                    sh.style.display  = open ? '' : 'none';
                    fl.style.display  = open ? 'none' : '';
                    this.textContent  = open ? this.dataset.more : this.dataset.less;
                });
            });
        })();
        </script>
        <?php
    }

    public function render_custom_tab(): void {
        $videos = (array) get_option('wpzaklad_help_videos', []);
        $remove_label = esc_js(wpbl_t('help_videos_remove_row'));
        $url_ph = 'https://youtube.com/watch?v=...';
        ?>
        <div class="wpbl-setting">
            <div class="wpbl-setting-info">
                <strong class="wpbl-setting-label"><?php echo esc_html(wpbl_t('help_videos_section_title')); ?></strong>
                <span class="wpbl-setting-desc"><?php echo esc_html(wpbl_t('help_videos_section_desc')); ?></span>
            </div>
        </div>

        <style>
        #wpbl-videos-list { margin-top: 4px; }
        .wpbl-video-card {
            background: #f9f9f9;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 0;
        }
        .wpbl-video-handle {
            padding: 14px 10px;
            cursor: grab;
            color: #c3c4c7;
            flex-shrink: 0;
            transition: color .15s;
        }
        .wpbl-video-handle:hover { color: #787c82; }
        .wpbl-video-card.ui-sortable-helper { box-shadow: 0 4px 16px rgba(0,0,0,.14); border-color: #2271b1; }
        .wpbl-video-card.ui-sortable-placeholder { background: #f6f7f7; border: 2px dashed #c3c4c7; visibility: visible !important; }
        .wpbl-video-fields {
            flex: 1;
            padding: 14px 14px 14px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .wpbl-video-field-url { }
        .wpbl-video-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 4px;
        }
        .wpbl-video-field input[type="text"],
        .wpbl-video-field textarea {
            width: 100%;
            box-sizing: border-box;
            font-size: 13px;
        }
        .wpbl-video-field textarea { resize: vertical; min-height: 58px; }
        .wpbl-video-remove-wrap {
            padding: 12px 12px 12px 0;
            flex-shrink: 0;
            display: flex;
            align-items: flex-start;
        }
        .wpbl-video-remove-wrap .button {
            white-space: nowrap;
            font-size: 12px;
        }
        #wpbl-add-video { margin-top: 4px; }
        </style>

        <div id="wpbl-videos-list">
            <?php foreach ($videos as $i => $video): ?>
            <div class="wpbl-video-card">
                <span class="wpbl-video-handle dashicons dashicons-move" title="<?php echo esc_attr(wpbl_t('help_videos_drag')); ?>"></span>
                <div class="wpbl-video-fields">
                    <div class="wpbl-video-field">
                        <label><?php echo esc_html(wpbl_t('help_videos_col_title')); ?></label>
                        <input type="text" name="wpzaklad_videos[<?php echo $i; ?>][title]" value="<?php echo esc_attr($video['title'] ?? ''); ?>">
                    </div>
                    <div class="wpbl-video-field">
                        <label><?php echo esc_html(wpbl_t('help_videos_col_desc')); ?></label>
                        <textarea name="wpzaklad_videos[<?php echo $i; ?>][desc]" rows="2"><?php echo esc_textarea($video['desc'] ?? ''); ?></textarea>
                    </div>
                    <div class="wpbl-video-field wpbl-video-field-url">
                        <label><?php echo esc_html(wpbl_t('help_videos_col_url')); ?></label>
                        <input type="text" name="wpzaklad_videos[<?php echo $i; ?>][url]" value="<?php echo esc_attr($video['url'] ?? ''); ?>" placeholder="<?php echo esc_attr($url_ph); ?>">
                    </div>
                </div>
                <div class="wpbl-video-remove-wrap">
                    <button type="button" class="button wpbl-remove-video"><?php echo esc_html(wpbl_t('help_videos_remove_row')); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="wpbl-add-video" class="button">
            <?php echo esc_html(wpbl_t('help_videos_add_row')); ?>
        </button>

        <script>
        (function($) {
            var list     = $('#wpbl-videos-list');
            var addBtn   = $('#wpbl-add-video');
            var urlPh    = <?php echo json_encode($url_ph); ?>;
            var lblTitle = <?php echo json_encode(wpbl_t('help_videos_col_title')); ?>;
            var lblDesc  = <?php echo json_encode(wpbl_t('help_videos_col_desc')); ?>;
            var lblUrl   = <?php echo json_encode(wpbl_t('help_videos_col_url')); ?>;
            var lblRm    = <?php echo json_encode(wpbl_t('help_videos_remove_row')); ?>;
            var lblDrag  = <?php echo json_encode(wpbl_t('help_videos_drag')); ?>;

            function reindex() {
                list.find('.wpbl-video-card').each(function(i) {
                    $(this).find('input, textarea').each(function() {
                        this.name = this.name.replace(/\[\d+\]/, '[' + i + ']');
                    });
                });
            }

            function bindRemove(card) {
                card.find('.wpbl-remove-video').on('click', function() {
                    card.remove();
                    reindex();
                });
            }

            function makeCard(idx) {
                var card = $('<div class="wpbl-video-card">');
                card.append(
                    $('<span>').addClass('wpbl-video-handle dashicons dashicons-move').attr('title', lblDrag)
                );
                var fields = $('<div class="wpbl-video-fields">');
                fields.append(
                    $('<div class="wpbl-video-field">').append(
                        $('<label>').text(lblTitle),
                        $('<input type="text">').attr('name', 'wpzaklad_videos[' + idx + '][title]')
                    ),
                    $('<div class="wpbl-video-field">').append(
                        $('<label>').text(lblDesc),
                        $('<textarea rows="2" style="width:100%;box-sizing:border-box;resize:vertical;min-height:58px;">').attr('name', 'wpzaklad_videos[' + idx + '][desc]')
                    ),
                    $('<div class="wpbl-video-field">').append(
                        $('<label>').text(lblUrl),
                        $('<input type="text">').attr({ name: 'wpzaklad_videos[' + idx + '][url]', placeholder: urlPh })
                    )
                );
                card.append(fields);
                card.append(
                    $('<div class="wpbl-video-remove-wrap">').append(
                        $('<button type="button" class="button wpbl-remove-video">').text(lblRm)
                    )
                );
                return card;
            }

            addBtn.on('click', function() {
                var idx  = list.find('.wpbl-video-card').length;
                var card = makeCard(idx);
                bindRemove(card);
                list.append(card);
                card.find('input').first().focus();
            });

            list.find('.wpbl-video-card').each(function() {
                bindRemove($(this));
            });

            list.sortable({
                handle: '.wpbl-video-handle',
                placeholder: 'wpbl-video-card ui-sortable-placeholder',
                forcePlaceholderSize: true,
                stop: function() { reindex(); }
            });
        })(jQuery);
        </script>

        <!-- Videos export / import -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f1;">
            <strong style="font-size:13px;"><?php echo esc_html(wpbl_t('videos_export_title')); ?></strong>
            <div style="margin-top:8px;display:flex;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <p style="margin:0 0 4px;font-size:12px;color:#646970;"><?php echo esc_html(wpbl_t('portability_export_label')); ?></p>
                    <textarea id="wpbl-videos-export" class="large-text code" rows="4" readonly onclick="this.select()"><?php echo esc_textarea(wp_json_encode((array) get_option('wpzaklad_help_videos', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]'); ?></textarea>
                    <p style="margin:6px 0 0;display:flex;gap:6px;">
                        <button type="button" class="button button-small" id="wpbl-videos-copy"><?php echo esc_html(wpbl_t('portability_copy_btn')); ?></button>
                        <button type="button" class="button button-small" id="wpbl-videos-download"><?php echo esc_html(wpbl_t('portability_download_btn')); ?></button>
                    </p>
                </div>
                <div style="flex:1;min-width:200px;">
                    <p style="margin:0 0 4px;font-size:12px;color:#646970;"><?php echo esc_html(wpbl_t('portability_import_label')); ?></p>
                    <textarea id="wpbl-videos-import-json" class="large-text code" rows="4" placeholder="<?php echo esc_attr(wpbl_t('portability_import_placeholder')); ?>"></textarea>
                    <p style="margin:6px 0 0;display:flex;gap:6px;align-items:center;">
                        <button type="button" class="button button-small" id="wpbl-videos-import-btn"><?php echo esc_html(wpbl_t('portability_import_btn')); ?></button>
                        <label class="button button-small" for="wpbl-videos-file" style="cursor:pointer;"><?php echo esc_html(wpbl_t('portability_upload_btn')); ?></label>
                        <input type="file" id="wpbl-videos-file" accept=".json" style="display:none;">
                    </p>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            var list2    = $('#wpbl-videos-list');
            var urlPh2   = <?php echo json_encode($url_ph); ?>;
            var lblTitle2 = <?php echo json_encode(wpbl_t('help_videos_col_title')); ?>;
            var lblDesc2  = <?php echo json_encode(wpbl_t('help_videos_col_desc')); ?>;
            var lblUrl2   = <?php echo json_encode(wpbl_t('help_videos_col_url')); ?>;
            var lblRm2    = <?php echo json_encode(wpbl_t('help_videos_remove_row')); ?>;
            var lblDrag2  = <?php echo json_encode(wpbl_t('help_videos_drag')); ?>;
            var errMsg    = <?php echo json_encode(wpbl_t('portability_invalid_json')); ?>;

            function applyVideosJson(json) {
                try {
                    var items = JSON.parse(json);
                    if (!Array.isArray(items)) throw new Error();
                    list2.empty();
                    items.forEach(function(v, i) {
                        var card = $('<div class="wpbl-video-card">');
                        card.append($('<span>').addClass('wpbl-video-handle dashicons dashicons-move').attr('title', lblDrag2));
                        var fields = $('<div class="wpbl-video-fields">');
                        fields.append(
                            $('<div class="wpbl-video-field">').append(
                                $('<label>').text(lblTitle2),
                                $('<input type="text">').attr('name', 'wpzaklad_videos[' + i + '][title]').val(v.title || '')
                            ),
                            $('<div class="wpbl-video-field">').append(
                                $('<label>').text(lblDesc2),
                                $('<textarea rows="2" style="width:100%;box-sizing:border-box;resize:vertical;min-height:58px;">').attr('name', 'wpzaklad_videos[' + i + '][desc]').val(v.desc || '')
                            ),
                            $('<div class="wpbl-video-field">').append(
                                $('<label>').text(lblUrl2),
                                $('<input type="text">').attr({ name: 'wpzaklad_videos[' + i + '][url]', placeholder: urlPh2 }).val(v.url || '')
                            )
                        );
                        card.append(fields);
                        card.append(
                            $('<div class="wpbl-video-remove-wrap">').append(
                                $('<button type="button" class="button wpbl-remove-video">').text(lblRm2)
                            )
                        );
                        card.find('.wpbl-remove-video').on('click', function() {
                            card.remove();
                            list2.find('.wpbl-video-card').each(function(j) {
                                $(this).find('input, textarea').each(function() {
                                    this.name = this.name.replace(/\[\d+\]/, '[' + j + ']');
                                });
                            });
                        });
                        list2.append(card);
                    });
                    $('#wpbl-videos-import-json').val('');
                } catch(e) {
                    alert(errMsg);
                }
            }

            $('#wpbl-videos-copy').on('click', function() {
                var ta = document.getElementById('wpbl-videos-export');
                ta.select();
                document.execCommand('copy');
            });

            $('#wpbl-videos-download').on('click', function() {
                var data = $('#wpbl-videos-export').val();
                var blob = new Blob([data], {type: 'application/json'});
                var url  = URL.createObjectURL(blob);
                var a    = document.createElement('a');
                a.href = url; a.download = 'wpzaklad-videos.json';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });

            $('#wpbl-videos-import-btn').on('click', function() {
                applyVideosJson($('#wpbl-videos-import-json').val().trim());
            });

            $('#wpbl-videos-file').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(evt) { applyVideosJson(evt.target.result); };
                reader.readAsText(file);
                this.value = '';
            });
        })(jQuery);
        </script>
        <?php
    }

    public function save_custom_data(array $post): void {
        if (!isset($post['wpzaklad_videos']) || !is_array($post['wpzaklad_videos'])) {
            update_option('wpzaklad_help_videos', []);
            return;
        }
        $clean = [];
        foreach ($post['wpzaklad_videos'] as $item) {
            $title = sanitize_text_field(wp_unslash($item['title'] ?? ''));
            $desc  = sanitize_textarea_field(wp_unslash($item['desc'] ?? ''));
            $url   = esc_url_raw(wp_unslash($item['url'] ?? ''));
            if ($title !== '' || $url !== '') {
                $clean[] = ['title' => $title, 'desc' => $desc, 'url' => $url];
            }
        }
        update_option('wpzaklad_help_videos', $clean);
    }
}
