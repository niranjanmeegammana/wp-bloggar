<?php
if (!defined('ABSPATH')) { exit; }

final class WP_Blogger_Security {
    const CRON_HOOK = 'wp_blogger_security_scan';
    const STATE_FILE = 'integrity-state.json';
    const SCAN_INTERVAL = 900; // 15 minutes.
    const MAX_FILES = 20000;

    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action('init', array(__CLASS__, 'ensure_schedule'), 20);
        add_action(self::CRON_HOOK, array(__CLASS__, 'scan'));
        add_action('xmlrpc_call', array(__CLASS__, 'xmlrpc_call'));
        add_action('wp_login_failed', array(__CLASS__, 'track_failed_login'), 20, 2);
        add_action('rest_api_init', array(__CLASS__, 'rest_api_seen'));
    }

    public static function cron_schedules($schedules) {
        if (!isset($schedules['wp_blogger_15min'])) {
            $schedules['wp_blogger_15min'] = array('interval' => self::SCAN_INTERVAL, 'display' => 'Every 15 minutes');
        }
        return $schedules;
    }

    public static function ensure_schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'wp_blogger_15min', self::CRON_HOOK);
        }
    }

    public static function xmlrpc_call($method) {
        WP_Blogger_Logger::log('xmlrpc_activity', 'medium', array('method' => $method));
    }

    public static function rest_api_seen() {
        if (!defined('REST_REQUEST') || !REST_REQUEST) { return; }
        $route = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (preg_match('#/wp-json/wp/v2/users(?:/|\?|$)#i', $route)) {
            WP_Blogger_Logger::log('rest_user_api_activity', 'high', array('route' => $route));
        }
    }

    public static function track_failed_login($username, $error = null) {
        $ip = WP_Blogger_Logger::client_ip_public();
        if (!$ip) { return; }
        $key = 'wpb_fail_' . md5($ip);
        $state = get_transient($key);
        if (!is_array($state)) { $state = array('count' => 0, 'first' => time()); }
        $state['count']++;
        set_transient($key, $state, 15 * MINUTE_IN_SECONDS);
        if (in_array($state['count'], array(5, 10, 20), true)) {
            WP_Blogger_Logger::log('login_failure_threshold', $state['count'] >= 10 ? 'critical' : 'high', array(
                'login' => $username,
                'failures' => $state['count'],
                'window_minutes' => 15,
                'source_ip' => $ip,
            ));
        }
    }

    public static function scan() {
        $path = WP_Blogger_Logger::active_path();
        if (!$path) { return array('ok' => false, 'message' => 'No writable log path.'); }

        $current = self::build_snapshot();
        $state_path = trailingslashit($path) . self::STATE_FILE;
        $previous = self::load_state($state_path);

        if (!$previous) {
            self::save_state($state_path, $current);
            WP_Blogger_Logger::log('integrity_baseline_created', 'medium', array(
                'files' => count($current['files']),
                'truncated' => $current['truncated'] ? 'yes' : 'no',
            ));
            return array('ok' => true, 'baseline' => true, 'changes' => 0, 'files' => count($current['files']));
        }

        $changes = 0;
        $old_files = isset($previous['files']) && is_array($previous['files']) ? $previous['files'] : array();
        $new_files = $current['files'];

        foreach ($new_files as $file => $meta) {
            if (!isset($old_files[$file])) {
                $changes++;
                self::log_file_change('file_added', $file, null, $meta);
            } elseif (!hash_equals((string) $old_files[$file]['sha256'], (string) $meta['sha256'])) {
                $changes++;
                self::log_file_change('file_modified', $file, $old_files[$file], $meta);
            }
        }
        foreach ($old_files as $file => $meta) {
            if (!isset($new_files[$file])) {
                $changes++;
                self::log_file_change('file_deleted', $file, $meta, null);
            }
        }

        self::save_state($state_path, $current);
        WP_Blogger_Logger::log('integrity_scan_complete', $changes ? 'high' : 'info', array(
            'files' => count($new_files), 'changes' => $changes, 'truncated' => $current['truncated'] ? 'yes' : 'no'
        ));
        return array('ok' => true, 'baseline' => false, 'changes' => $changes, 'files' => count($new_files));
    }

    private static function log_file_change($event, $relative, $old, $new) {
        $severity = self::severity_for_file($relative, $event);
        $details = array('file' => $relative);
        if ($old) { $details['old_sha256'] = $old['sha256']; }
        if ($new) { $details['new_sha256'] = $new['sha256']; $details['size'] = $new['size']; }
        if (self::is_suspicious_name($relative)) { $details['suspicious_name'] = 'yes'; $severity = 'critical'; }
        if (self::is_upload_executable($relative)) { $details['executable_in_uploads'] = 'yes'; $severity = 'critical'; }
        WP_Blogger_Logger::log($event, $severity, $details);
    }

    private static function severity_for_file($relative, $event) {
        $r = strtolower($relative);
        if ($r === 'wp-config.php' || $r === '.htaccess' || strpos($r, 'wp-content/mu-plugins/') === 0) { return 'critical'; }
        if (self::is_upload_executable($relative)) { return 'critical'; }
        if (strpos($r, 'wp-admin/') === 0 || strpos($r, 'wp-includes/') === 0) { return 'critical'; }
        if (strpos($r, 'wp-content/plugins/') === 0 || strpos($r, 'wp-content/themes/') === 0) { return 'high'; }
        return $event === 'file_added' ? 'high' : 'medium';
    }

    private static function build_snapshot() {
        $root = wp_normalize_path(ABSPATH);
        $files = array();
        $truncated = false;
        $targets = array(
            $root . 'wp-config.php', $root . '.htaccess', $root . 'wp-admin', $root . 'wp-includes',
            wp_normalize_path(WP_CONTENT_DIR . '/plugins'), wp_normalize_path(WP_CONTENT_DIR . '/mu-plugins'),
            wp_normalize_path(WP_CONTENT_DIR . '/themes'), wp_normalize_path(WP_CONTENT_DIR . '/uploads'),
        );

        foreach ($targets as $target) {
            if (count($files) >= self::MAX_FILES) { $truncated = true; break; }
            if (is_file($target)) {
                self::snapshot_file($target, $root, $files);
                continue;
            }
            if (!is_dir($target)) { continue; }
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $fi) {
                    if (count($files) >= self::MAX_FILES) { $truncated = true; break 2; }
                    if (!$fi->isFile() || $fi->isLink()) { continue; }
                    $file = wp_normalize_path($fi->getPathname());
                    if (!self::should_track($file, $root)) { continue; }
                    self::snapshot_file($file, $root, $files);
                }
            } catch (UnexpectedValueException $e) {
                WP_Blogger_Logger::log('integrity_scan_path_error', 'medium', array('path' => $target));
            }
        }
        ksort($files);
        return array('generated' => gmdate('c'), 'files' => $files, 'truncated' => $truncated);
    }

    private static function should_track($file, $root) {
        $lower = strtolower($file);
        if (strpos($lower, '/wp-content/uploads/') !== false) {
            return (bool) preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh)$/i', $lower);
        }
        if (strpos($lower, '/wp-content/cache/') !== false || strpos($lower, '/wp-content/upgrade/') !== false) { return false; }
        return (bool) preg_match('/\.(php\d*|phtml|phar|js|css|htaccess)$/i', $lower) || basename($lower) === '.htaccess';
    }

    private static function snapshot_file($file, $root, &$files) {
        if (!is_readable($file)) { return; }
        $relative = ltrim(str_replace($root, '', wp_normalize_path($file)), '/');
        $hash = @hash_file('sha256', $file);
        if (!$hash) { return; }
        $files[$relative] = array('sha256' => $hash, 'size' => (int) @filesize($file), 'mtime' => (int) @filemtime($file));
    }

    private static function is_upload_executable($relative) {
        return strpos(strtolower($relative), 'wp-content/uploads/') === 0 && (bool) preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh)$/i', $relative);
    }

    private static function is_suspicious_name($relative) {
        $name = strtolower(basename($relative));
        $exact = array('shell.php','ws.php','wso.php','cmd.php','r57.php','c99.php','mailer.php','about.php');
        if (in_array($name, $exact, true)) { return true; }
        return (bool) preg_match('/(?:shell|backdoor|webshell|bypass|uploader|filemanager|wso|c99|r57)/i', $name);
    }

    private static function load_state($path) {
        if (!is_readable($path)) { return false; }
        $raw = @file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) ? $data : false;
    }

    private static function save_state($path, $state) {
        $tmp = $path . '.tmp';
        $json = wp_json_encode($state, JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            @rename($tmp, $path);
            @chmod($path, 0640);
        }
    }
}
