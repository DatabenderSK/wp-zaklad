<?php
defined('ABSPATH') || exit;

/**
 * Returns a translated string for the given key.
 * Language loaded once per request and cached in a static variable.
 */
function wpbl_t(string $key): string {
    static $strings = null;

    if ($strings === null) {
        $lang = get_option('wpzaklad_language', 'sk');
        $file = WPBL_DIR . 'languages/' . $lang . '.php';

        if (!file_exists($file)) {
            $file = WPBL_DIR . 'languages/sk.php';
        }

        $strings = require $file;
    }

    return isset($strings[$key]) ? (string) $strings[$key] : $key;
}
