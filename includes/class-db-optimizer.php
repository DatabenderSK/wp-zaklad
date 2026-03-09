<?php
defined('ABSPATH') || exit;

class WPBL_DB_Optimizer {

    const SCHEDULE_OPTION = 'wpzaklad_db_schedule';
    const CRON_HOOK       = 'wpbl_db_scheduled_clean';

    public static function init(): void {
        add_filter('cron_schedules', [self::class, 'add_monthly_schedule']);
        add_action(self::CRON_HOOK, [self::class, 'run_scheduled_clean']);
    }

    public static function add_monthly_schedule(array $schedules): array {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => 'Once Monthly',
        ];
        return $schedules;
    }

    // -------------------------------------------------------------------------
    // Counts
    // -------------------------------------------------------------------------

    public static function get_counts(): array {
        global $wpdb;

        return [
            'revisions'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"),
            'auto_drafts'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"),
            'trashed_posts'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"),
            'spam_comments'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"),
            'trashed_comments'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"),
            'expired_transients' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                $wpdb->esc_like('_transient_timeout_') . '%',
                time()
            )),
            'orphan_postmeta'    => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.ID IS NULL"
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // Clean individual type
    // -------------------------------------------------------------------------

    public static function clean(string $type): int {
        global $wpdb;

        switch ($type) {
            case 'revisions':
                $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_type = 'revision'"
                );
                return (int) $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");

            case 'auto_drafts':
                $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_status = 'auto-draft'"
                );
                return (int) $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");

            case 'trashed_posts':
                $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_status = 'trash'"
                );
                $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm
                     INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                     INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
                     WHERE p.post_status = 'trash'"
                );
                $wpdb->query(
                    "DELETE c FROM {$wpdb->comments} c
                     INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
                     WHERE p.post_status = 'trash'"
                );
                return (int) $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");

            case 'spam_comments':
                $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm
                     INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                     WHERE c.comment_approved = 'spam'"
                );
                return (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");

            case 'trashed_comments':
                $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm
                     INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                     WHERE c.comment_approved = 'trash'"
                );
                return (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");

            case 'expired_transients':
                $wpdb->query($wpdb->prepare(
                    "DELETE a, b FROM {$wpdb->options} a
                     LEFT JOIN {$wpdb->options} b
                         ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
                     WHERE a.option_name LIKE %s AND a.option_value < %d",
                    $wpdb->esc_like('_transient_timeout_') . '%',
                    time()
                ));
                wp_cache_flush();
                return $wpdb->rows_affected;

            case 'orphan_postmeta':
                $total = 0;
                do {
                    $deleted = (int) $wpdb->query(
                        "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (
                            SELECT meta_id FROM (
                                SELECT pm.meta_id FROM {$wpdb->postmeta} pm
                                LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                                WHERE p.ID IS NULL
                                LIMIT 5000
                            ) AS orphans
                        )"
                    );
                    $total += $deleted;
                } while ($deleted > 0);
                return $total;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Clean all + optimize
    // -------------------------------------------------------------------------

    public static function clean_all(): void {
        foreach (['revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'trashed_comments', 'expired_transients', 'orphan_postmeta'] as $type) {
            self::clean($type);
        }
    }

    public static function optimize_tables(): int {
        global $wpdb;
        $tables = $wpdb->get_col($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($wpdb->prefix) . '%'
        ));
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE `{$table}`"); // phpcs:ignore
        }
        return count($tables);
    }

    // -------------------------------------------------------------------------
    // Scheduled optimization
    // -------------------------------------------------------------------------

    public static function get_schedule(): string {
        return (string) get_option(self::SCHEDULE_OPTION, 'disabled');
    }

    public static function set_schedule(string $schedule): void {
        $allowed = ['disabled', 'daily', 'weekly', 'monthly'];
        if (!in_array($schedule, $allowed, true)) {
            $schedule = 'disabled';
        }
        update_option(self::SCHEDULE_OPTION, $schedule);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        if ($schedule !== 'disabled') {
            wp_schedule_event(time() + DAY_IN_SECONDS, $schedule, self::CRON_HOOK);
        }
    }

    public static function run_scheduled_clean(): void {
        self::clean_all();
        self::optimize_tables();
    }
}
