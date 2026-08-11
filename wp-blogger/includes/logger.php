<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WP_Blogger_Logger {
    const PRIMARY_PATH = '/var/log/wp-blogger/';

    private static $active_path = null;

    public static function init() {
        add_action('init', array(__CLASS__, 'ensure_log_path'), 1);
    }

    public static function ensure_log_path() {
        if (self::$active_path !== null) {
            return self::$active_path;
        }

        $primary = defined('WP_BLOGGER_LOG_PATH') ? WP_BLOGGER_LOG_PATH : self::PRIMARY_PATH;
        $fallback = defined('WP_BLOGGER_FALLBACK_LOG_PATH')
            ? WP_BLOGGER_FALLBACK_LOG_PATH
            : WP_CONTENT_DIR . '/uploads/wp-blogger/';

        foreach (array($primary, $fallback) as $path) {
            $path = trailingslashit($path);
            if (!is_dir($path)) {
                @wp_mkdir_p($path);
            }
            if (is_dir($path) && is_writable($path)) {
                self::$active_path = $path;
                self::protect_web_path($path);
                return self::$active_path;
            }
        }

        self::$active_path = false;
        return false;
    }

    private static function protect_web_path($path) {
        $uploads = trailingslashit(WP_CONTENT_DIR . '/uploads/');
        if (strpos(wp_normalize_path($path), wp_normalize_path($uploads)) !== 0) {
            return;
        }

        $htaccess = $path . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "# WP Blogger private logs\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }

        $index = $path . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php http_response_code(403); exit;\n");
        }
    }

    public static function active_path() {
        return self::ensure_log_path();
    }

    public static function log($event_id, $severity = 'info', $details = array()) {
        $path = self::ensure_log_path();
        if (!$path) {
            return false;
        }

        $user = wp_get_current_user();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $ip = self::client_ip();
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $record = array(
            'timestamp'   => gmdate('c'),
            'event_id'    => sanitize_key($event_id),
            'severity'    => sanitize_key($severity),
            'user_id'     => $user && $user->exists() ? (int) $user->ID : 0,
            'username'    => $user && $user->exists() ? sanitize_user($user->user_login) : '',
            'source_ip'   => $ip,
            'request_uri' => $request_uri,
            'user_agent'  => $agent,
            'details'     => self::sanitize_details($details),
        );

        $file = $path . 'wp-blogger-' . gmdate('Y-m') . '.jsonl';
        $line = wp_json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $fp = @fopen($file, 'ab');
        if (!$fp) {
            return false;
        }

        @flock($fp, LOCK_EX);
        $ok = fwrite($fp, $line) !== false;
        @flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($file, 0640);

        return $ok;
    }

    public static function client_ip_public() {
        return self::client_ip();
    }

    private static function client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return preg_match('/^[0-9a-fA-F:.]+$/', $ip) ? $ip : '';
    }

    private static function sanitize_details($details) {
        if (!is_array($details)) {
            return array('message' => sanitize_text_field((string) $details));
        }

        $deny = array('password', 'pass', 'pwd', 'cookie', 'authorization', 'token', 'nonce', 'secret', 'api_key');
        $clean = array();

        foreach ($details as $key => $value) {
            $key_string = sanitize_key((string) $key);
            foreach ($deny as $needle) {
                if (strpos($key_string, $needle) !== false) {
                    $clean[$key_string] = '[REDACTED]';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $clean[$key_string] = self::sanitize_details($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key_string] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }
}
