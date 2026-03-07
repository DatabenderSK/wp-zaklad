/* global wpbl, wp */
(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Tabs
    // -------------------------------------------------------------------------
    function initTabs() {
        var $nav    = $('#wpbl-tabs');
        var $links  = $nav.find('a');
        var $footer = $('#wpbl-save-footer');

        function activateTab(id) {
            $links.removeClass('active');
            $('.wpbl-tab-panel').hide();

            var $link  = $links.filter('[href="#' + id + '"]');
            var $panel = $('#' + id);

            if (!$panel.length) {
                // fallback to first
                id     = $links.first().attr('href').replace('#', '');
                $link  = $links.first();
                $panel = $('#' + id);
            }

            $link.addClass('active');
            $panel.show();

            // Show save button only for module tabs (not tools)
            if (id === 'tab-tools') {
                $footer.hide();
            } else {
                $footer.show();
            }

            try { localStorage.setItem('wpbl_active_tab', id); } catch (e) {}
        }

        $links.on('click', function (e) {
            e.preventDefault();
            var id = $(this).attr('href').replace('#', '');
            activateTab(id);
        });

        // Restore active tab from localStorage or hash
        var initial = '';
        try { initial = localStorage.getItem('wpbl_active_tab') || ''; } catch (e) {}
        if (window.location.hash) {
            initial = window.location.hash.replace('#', '');
        }
        if (!initial || !$('#' + initial).length) {
            initial = $links.first().attr('href').replace('#', '');
        }
        activateTab(initial);
    }

    // -------------------------------------------------------------------------
    // Search / filter
    // -------------------------------------------------------------------------
    function initSearch() {
        var $allPanels   = $('.wpbl-tab-panel');
        var $allSettings = $allPanels.find('.wpbl-setting');

        $('#wpbl-search').on('input', function () {
            var query = $(this).val().toLowerCase().trim();

            if (!query) {
                // Restore normal tab state
                $allPanels.hide();
                $allSettings.removeClass('wpbl-hidden');
                var $activeLink = $('#wpbl-tabs a.active');
                if ($activeLink.length) {
                    $('#' + $activeLink.attr('href').replace('#', '')).show();
                }
                return;
            }

            // Show/hide individual settings across ALL panels
            $allSettings.each(function () {
                var text = ($(this).data('search') || '').toLowerCase();
                $(this).toggleClass('wpbl-hidden', text.indexOf(query) === -1);
            });

            // Show only panels that have at least one match
            $allPanels.each(function () {
                var hasMatch = $(this).find('.wpbl-setting:not(.wpbl-hidden)').length > 0;
                $(this).toggle(hasMatch);
            });
        });

        // Clear search on tab switch
        $('#wpbl-tabs a').on('click', function () {
            $('#wpbl-search').val('');
            $allSettings.removeClass('wpbl-hidden');
        });
    }

    // -------------------------------------------------------------------------
    // Color picker
    // -------------------------------------------------------------------------
    function initColorPicker() {
        if ($.fn.wpColorPicker) {
            $('.wpbl-color-field').wpColorPicker();
        }
    }

    // -------------------------------------------------------------------------
    // Media uploader
    // -------------------------------------------------------------------------
    function initMediaUploader() {
        var mediaFrame = null;
        var activeTarget = '';

        $(document).on('click', '.wpbl-media-btn', function () {
            activeTarget = $(this).data('target');

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title    : 'Select Image',
                button   : { text: 'Use this image' },
                multiple : false,
                library  : { type: 'image' },
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                var url        = attachment.url;
                var $field     = $('#' + activeTarget);

                $field.val(url);

                var $row = $field.closest('.wpbl-media-field');
                $row.find('.wpbl-media-preview').attr('src', url).show();
                $row.find('.wpbl-media-remove').show();
            });

            mediaFrame.open();
        });

        $(document).on('click', '.wpbl-media-remove', function () {
            var targetId = $(this).data('target');
            $('#' + targetId).val('');
            var $row = $(this).closest('.wpbl-media-field');
            $row.find('.wpbl-media-preview').attr('src', '').hide();
            $(this).hide();
        });
    }

    // -------------------------------------------------------------------------
    // Confirm dialogs (reset, flush)
    // -------------------------------------------------------------------------
    function initConfirmDialogs() {
        $(document).on('submit', '.wpbl-reset-form', function (e) {
            var msg = (typeof wpbl !== 'undefined' && wpbl.resetConfirm)
                ? wpbl.resetConfirm
                : 'Are you sure you want to reset all settings?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });

        $(document).on('submit', '.wpbl-flush-form', function (e) {
            var msg = (typeof wpbl !== 'undefined' && wpbl.flushConfirm)
                ? wpbl.flushConfirm
                : 'Are you sure you want to flush all transients?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Export download
    // -------------------------------------------------------------------------
    function initExportDownload() {
        $(document).on('click', '#wpbl-export-download', function () {
            var json = $('#wpbl-export-textarea').val();
            if (!json) return;
            var blob = new Blob([json], { type: 'application/json' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href     = url;
            a.download = 'wp-zaklad-export.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    // -------------------------------------------------------------------------
    // Language switcher – auto-submit on change
    // -------------------------------------------------------------------------
    function initLangSwitcher() {
        $('#wpbl-lang').on('change', function () {
            $(this).closest('form').submit();
        });
    }

    // -------------------------------------------------------------------------
    // Admin Menu sortable
    // -------------------------------------------------------------------------
    function initMenuSortable() {
        var $list = $('#wpbl-menu-sortable');
        if (!$list.length) return;

        $list.sortable({
            items       : '.wpbl-menu-card',
            handle      : '.wpbl-menu-handle',
            placeholder : 'wpbl-sortable-placeholder',
            tolerance   : 'pointer',
            update      : function () {
                syncMenuOrder($list);
            },
        });

        // Set initial hidden value
        syncMenuOrder($list);
    }

    function syncMenuOrder($list) {
        var order = $list.find('li').map(function () {
            return $(this).data('slug');
        }).get().join(',');
        $('#wpzaklad_menu_order_string').val(order);
    }

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------
    $(function () {
        initTabs();
        initSearch();
        initColorPicker();
        initMediaUploader();
        initConfirmDialogs();
        initExportDownload();
        initLangSwitcher();
        initMenuSortable();
    });

}(jQuery));
