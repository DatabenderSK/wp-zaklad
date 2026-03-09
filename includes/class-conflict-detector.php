<?php
defined('ABSPATH') || exit;

class WPBL_Conflict_Detector {

    public static function detect(WPBL_Settings $settings): array {
        $conflicts = [];

        $rules = [
            [
                'setting'     => 'wpzaklad_disable_gutenberg',
                'check'       => defined('GENERATEBLOCKS_VERSION'),
                'plugin'      => 'GenerateBlocks',
                'severity'    => 'critical',
                'message_key' => 'conflict_gutenberg_generateblocks',
            ],
            [
                'setting'     => 'wpzaklad_disable_rest_unauth',
                'check'       => defined('FLUENTFORM'),
                'plugin'      => 'Fluent Forms',
                'severity'    => 'critical',
                'message_key' => 'conflict_rest_fluentforms',
            ],
            [
                'setting'     => 'wpzaklad_disable_rest_unauth',
                'check'       => defined('WPCF7_VERSION'),
                'plugin'      => 'Contact Form 7',
                'severity'    => 'warning',
                'message_key' => 'conflict_rest_cf7',
            ],
            [
                'setting'     => 'wpzaklad_custom_robots_txt',
                'check'       => defined('RANK_MATH_VERSION'),
                'plugin'      => 'RankMath',
                'severity'    => 'info',
                'message_key' => 'conflict_robots_rankmath',
            ],
        ];

        foreach ($rules as $rule) {
            if (!$rule['check']) continue;

            $value = $settings->get($rule['setting']);

            // For checkbox settings, check if enabled (truthy)
            // For text settings like custom_robots_txt, check if non-empty
            $is_active = is_string($value) ? ($value !== '') : !empty($value);

            if ($is_active) {
                $conflicts[] = [
                    'setting'     => $rule['setting'],
                    'plugin'      => $rule['plugin'],
                    'severity'    => $rule['severity'],
                    'message_key' => $rule['message_key'],
                ];
            }
        }

        return $conflicts;
    }
}
