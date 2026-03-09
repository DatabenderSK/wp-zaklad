<?php
defined('ABSPATH') || exit;

class WPBL_Module_Optimization extends WPBL_Module_Base {

    public function get_id(): string { return 'optimization'; }
    public function get_label(): string { return wpbl_t('tab_optimization'); }

    public function get_defaults(): array {
        return [
            'wpzaklad_disable_emoji'          => 0,
            'wpzaklad_disable_embeds'         => 0,
            'wpzaklad_remove_rss'             => 0,
            'wpzaklad_disable_xmlrpc'         => 0,
            'wpzaklad_remove_jquery_migrate'  => 0,
            'wpzaklad_disable_self_pingbacks' => 0,
            'wpzaklad_clean_head'             => 0,
            'wpzaklad_block_update_emails'    => 0,
            'wpzaklad_lazy_load_iframes'      => 0,
            'wpzaklad_disable_gravatars'      => 0,
            'wpzaklad_local_avatars'          => 0,
            'wpzaklad_disable_admin_emails'   => 0,
        ];
    }

    public function get_fields(): array {
        return [
            ['key' => 'wpzaklad_disable_emoji',          'type' => 'checkbox', 'label' => wpbl_t('disable_emoji_label'),          'desc' => wpbl_t('disable_emoji_desc'),          'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_embeds',         'type' => 'checkbox', 'label' => wpbl_t('disable_embeds_label'),         'desc' => wpbl_t('disable_embeds_desc')],
            ['key' => 'wpzaklad_remove_rss',             'type' => 'checkbox', 'label' => wpbl_t('remove_rss_label'),             'desc' => wpbl_t('remove_rss_desc')],
            ['key' => 'wpzaklad_disable_xmlrpc',         'type' => 'checkbox', 'label' => wpbl_t('disable_xmlrpc_label'),         'desc' => wpbl_t('disable_xmlrpc_desc'),         'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_remove_jquery_migrate',  'type' => 'checkbox', 'label' => wpbl_t('remove_jquery_migrate_label'),  'desc' => wpbl_t('remove_jquery_migrate_desc'),  'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_disable_self_pingbacks', 'type' => 'checkbox', 'label' => wpbl_t('disable_self_pingbacks_label'), 'desc' => wpbl_t('disable_self_pingbacks_desc'), 'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_clean_head',             'type' => 'checkbox', 'label' => wpbl_t('clean_head_label'),             'desc' => wpbl_t('clean_head_desc'),             'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_block_update_emails',    'type' => 'checkbox', 'label' => wpbl_t('block_update_emails_label'),    'desc' => wpbl_t('block_update_emails_desc'),    'recommended' => true, 'mine' => true],
            ['key' => 'wpzaklad_lazy_load_iframes',      'type' => 'checkbox', 'label' => wpbl_t('lazy_load_iframes_label'),      'desc' => wpbl_t('lazy_load_iframes_desc')],
            ['key' => 'wpzaklad_disable_gravatars',      'type' => 'checkbox', 'label' => wpbl_t('disable_gravatars_label'),      'desc' => wpbl_t('disable_gravatars_desc'), 'mine' => true],
            ['key' => 'wpzaklad_local_avatars',          'type' => 'checkbox', 'label' => wpbl_t('local_avatars_label'),          'desc' => wpbl_t('local_avatars_desc')],
            ['key' => 'wpzaklad_disable_admin_emails', 'type' => 'checkbox', 'label' => wpbl_t('disable_admin_emails_label'), 'desc' => wpbl_t('disable_admin_emails_desc'), 'mine' => true],
        ];
    }

    public function init(): void {
        if ($this->get('wpzaklad_disable_emoji')) {
            $this->disable_emoji();
        }
        if ($this->get('wpzaklad_disable_embeds')) {
            $this->disable_embeds();
        }
        if ($this->get('wpzaklad_remove_rss')) {
            add_action('wp_head', [$this, 'remove_rss'], 1);
        }
        if ($this->get('wpzaklad_disable_xmlrpc')) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'remove_xmlrpc_header']);
        }
        if ($this->get('wpzaklad_remove_jquery_migrate')) {
            add_action('wp_default_scripts', [$this, 'remove_jquery_migrate']);
        }
        if ($this->get('wpzaklad_disable_self_pingbacks')) {
            add_action('pre_ping', [$this, 'disable_self_pingbacks']);
        }
        if ($this->get('wpzaklad_clean_head')) {
            $this->clean_head();
        }
        if ($this->get('wpzaklad_block_update_emails')) {
            $this->block_update_emails();
        }
        if ($this->get('wpzaklad_lazy_load_iframes')) {
            add_filter('the_content', [$this, 'lazy_load_iframes']);
            add_filter('oembed_result', [$this, 'lazy_load_iframes']);
        }
        if ($this->get('wpzaklad_disable_gravatars')) {
            add_filter('option_show_avatars', '__return_false');
        }
        if ($this->get('wpzaklad_local_avatars')) {
            add_filter('get_avatar_url',             [$this, 'get_local_avatar_url'], 10, 3);
            add_action('show_user_profile',          [$this, 'user_avatar_field']);
            add_action('edit_user_profile',          [$this, 'user_avatar_field']);
            add_action('personal_options_update',    [$this, 'save_user_avatar_field']);
            add_action('edit_user_profile_update',   [$this, 'save_user_avatar_field']);
            add_action('admin_enqueue_scripts',      [$this, 'enqueue_avatar_scripts']);
        }
        if ($this->get('wpzaklad_disable_admin_emails')) {
            add_filter('send_password_change_email', '__return_false');
            add_filter('send_email_change_email', '__return_false');
            add_filter('wp_new_user_notification_email_admin', '__return_false');
            add_filter('admin_email_check_interval', '__return_false');
        }
    }

    private function disable_emoji(): void {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
        add_filter('wp_resource_hints', [$this, 'disable_emoji_dns_prefetch'], 10, 2);
    }

    public function disable_emojis_tinymce(array $plugins): array {
        return array_diff($plugins, ['wpemoji']);
    }

    public function disable_emoji_dns_prefetch(array $urls, string $relation_type): array {
        if ($relation_type === 'dns-prefetch') {
            $urls = array_filter($urls, fn($url) => strpos($url, 'https://s.w.org/images/core/emoji') === false);
        }
        return $urls;
    }

    private function disable_embeds(): void {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        add_filter('embed_oembed_discover', '__return_false');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('rewrite_rules_array', [$this, 'disable_embeds_rewrite_rules']);
    }

    public function disable_embeds_rewrite_rules(array $rules): array {
        foreach ($rules as $rule => $rewrite) {
            if (strpos($rewrite, 'embed=true') !== false) {
                unset($rules[$rule]);
            }
        }
        return $rules;
    }

    public function remove_rss(): void {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }

    public function remove_xmlrpc_header(array $headers): array {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function remove_jquery_migrate(\WP_Scripts $scripts): void {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    public function disable_self_pingbacks(array &$links): void {
        $home = get_option('home');
        foreach ($links as $key => $link) {
            if (str_starts_with($link, $home)) {
                unset($links[$key]);
            }
        }
    }

    private function clean_head(): void {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
    }

    private function block_update_emails(): void {
        add_filter('auto_core_update_send_email',   '__return_false');
        add_filter('auto_plugin_update_send_email', '__return_false');
        add_filter('auto_theme_update_send_email',  '__return_false');
    }

    public function lazy_load_iframes(string $content): string {
        return preg_replace('/<iframe(?![^>]*loading\s*=)([^>]*)>/i', '<iframe loading="lazy"$1>', $content);
    }

    // -------------------------------------------------------------------------
    // Local Avatars
    // -------------------------------------------------------------------------

    public function get_local_avatar_url(string $url, $id_or_email, array $args): string {
        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif ($id_or_email instanceof \WP_User) {
            $user_id = $id_or_email->ID;
        } elseif ($id_or_email instanceof \WP_Post) {
            $user_id = (int) $id_or_email->post_author;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        if (!$user_id) {
            return $url;
        }
        $attachment_id = (int) get_user_meta($user_id, 'wpzaklad_local_avatar', true);
        if (!$attachment_id) {
            return $url;
        }
        $size        = isset($args['size']) ? (int) $args['size'] : 96;
        $custom_url  = wp_get_attachment_image_url($attachment_id, [$size, $size]);
        return $custom_url ?: $url;
    }

    public function user_avatar_field(\WP_User $user): void {
        $attachment_id = (int) get_user_meta($user->ID, 'wpzaklad_local_avatar', true);
        $preview_url   = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
        ?>
        <h3><?php echo esc_html(wpbl_t('local_avatar_section')); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html(wpbl_t('local_avatar_label')); ?></label></th>
                <td>
                    <div id="wpzaklad-avatar-preview" style="margin-bottom:10px;">
                        <?php if ($preview_url): ?>
                            <img src="<?php echo esc_url($preview_url); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:50%;display:block;">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="wpzaklad_local_avatar" id="wpzaklad_local_avatar" value="<?php echo esc_attr($attachment_id ?: ''); ?>">
                    <button type="button" class="button" id="wpzaklad-avatar-upload"><?php echo esc_html(wpbl_t('local_avatar_choose')); ?></button>
                    <button type="button" class="button" id="wpzaklad-avatar-remove"<?php echo $attachment_id ? '' : ' style="display:none;"'; ?>><?php echo esc_html(wpbl_t('local_avatar_remove')); ?></button>
                    <p class="description" style="margin-top:6px;"><?php echo esc_html(wpbl_t('local_avatar_field_desc')); ?></p>
                </td>
            </tr>
        </table>
        <script>
        jQuery(function($){
            var frame;
            $('#wpzaklad-avatar-upload').on('click', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title:    '<?php echo esc_js(wpbl_t('local_avatar_choose')); ?>',
                    button:   { text: '<?php echo esc_js(wpbl_t('local_avatar_select')); ?>' },
                    multiple: false,
                    library:  { type: 'image' }
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#wpzaklad_local_avatar').val(attachment.id);
                    var thumb = (attachment.sizes && attachment.sizes.thumbnail)
                        ? attachment.sizes.thumbnail.url
                        : attachment.url;
                    $('#wpzaklad-avatar-preview').html('<img src="' + thumb + '" style="width:80px;height:80px;object-fit:cover;border-radius:50%;display:block;">');
                    $('#wpzaklad-avatar-remove').show();
                });
                frame.open();
            });
            $('#wpzaklad-avatar-remove').on('click', function(e){
                e.preventDefault();
                $('#wpzaklad_local_avatar').val('');
                $('#wpzaklad-avatar-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    public function save_user_avatar_field(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['wpzaklad_local_avatar'])) {
            return;
        }
        $attachment_id = absint($_POST['wpzaklad_local_avatar']);
        if ($attachment_id) {
            update_user_meta($user_id, 'wpzaklad_local_avatar', $attachment_id);
        } else {
            delete_user_meta($user_id, 'wpzaklad_local_avatar');
        }
    }

    public function enqueue_avatar_scripts(string $hook): void {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) {
            return;
        }
        wp_enqueue_media();
    }
}
