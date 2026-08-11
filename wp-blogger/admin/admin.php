<?php
if (!defined('ABSPATH')) { exit; }

final class WP_Blogger_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_wp_blogger_scan', array(__CLASS__, 'manual_scan'));
    }

    public static function menu() {
        add_menu_page('Blogger Activity', 'Blogger', 'manage_options', 'wp-blogger', array(__CLASS__, 'render_activity'), 'dashicons-media-text', 81);
        add_submenu_page('wp-blogger', 'Activity', 'Activity', 'manage_options', 'wp-blogger', array(__CLASS__, 'render_activity'));
        add_submenu_page('wp-blogger', 'Security Events', 'Security Events', 'manage_options', 'wp-blogger-security', array(__CLASS__, 'render_security'));
    }

    public static function manual_scan() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wp_blogger_scan');
        $result = WP_Blogger_Security::scan();
        $args = array('page' => 'wp-blogger-security', 'scan' => !empty($result['ok']) ? 'ok' : 'error');
        if (isset($result['changes'])) { $args['changes'] = (int) $result['changes']; }
        if (!empty($result['baseline'])) { $args['baseline'] = 1; }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function render_activity() {
        self::render_page(false);
    }

    public static function render_security() {
        self::render_page(true);
    }

    private static function render_page($security_only) {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.')); }
        $path = WP_Blogger_Logger::active_path();
        echo '<div class="wrap"><h1>' . ($security_only ? 'Blogger Security Events' : 'Blogger Activity') . '</h1>';
        echo '<p><strong>Active log path:</strong> <code>' . esc_html($path ? $path : 'Unavailable') . '</code> &nbsp; <strong>Version:</strong> ' . esc_html(WP_BLOGGER_VERSION) . '</p>';

        if ($security_only) {
            echo '<p>Filesystem integrity is checked automatically about every 15 minutes when WP-Cron runs. The first scan creates a baseline; it does not prove the existing files are clean.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0 18px">';
            echo '<input type="hidden" name="action" value="wp_blogger_scan">';
            wp_nonce_field('wp_blogger_scan');
            submit_button('Run Security Scan Now', 'secondary', 'submit', false);
            echo '</form>';
            if (isset($_GET['scan'])) {
                $baseline = !empty($_GET['baseline']);
                $changes = isset($_GET['changes']) ? absint($_GET['changes']) : 0;
                $msg = $baseline ? 'Security baseline created.' : 'Security scan completed. Changes detected: ' . $changes . '.';
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            }
        }

        if (!$path) { echo '<div class="notice notice-error"><p>No writable log directory is available.</p></div></div>'; return; }
        $files = glob(trailingslashit($path) . 'wp-blogger-*.jsonl');
        if (!$files) { echo '<p>No activity has been recorded yet.</p></div>'; return; }
        rsort($files, SORT_STRING);
        $selected = isset($_GET['logfile']) ? basename(sanitize_text_field(wp_unslash($_GET['logfile']))) : basename($files[0]);
        $selected_path = trailingslashit($path) . $selected;
        $page = $security_only ? 'wp-blogger-security' : 'wp-blogger';
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr($page) . '"><label for="logfile"><strong>Log file:</strong></label> ';
        echo '<select id="logfile" name="logfile" onchange="this.form.submit()">';
        foreach ($files as $file) { $name = basename($file); echo '<option value="' . esc_attr($name) . '" ' . selected($selected, $name, false) . '>' . esc_html($name) . '</option>'; }
        echo '</select></form><br>';

        $real_path = realpath($path); $real_selected = realpath($selected_path);
        if (!$real_selected || !$real_path || !is_readable($selected_path) || strpos($real_selected, $real_path) !== 0) {
            echo '<div class="notice notice-error"><p>Invalid log file.</p></div></div>'; return;
        }
        $lines = self::tail($selected_path, 1000);
        echo '<table class="widefat striped"><thead><tr><th>UTC Time</th><th>Severity</th><th>Event</th><th>User</th><th>IP</th><th>Details</th></tr></thead><tbody>';
        $shown = 0;
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true); if (!is_array($row)) { continue; }
            if ($security_only && !self::is_security_event($row)) { continue; }
            $shown++;
            echo '<tr><td>' . esc_html($row['timestamp'] ?? '') . '</td>';
            echo '<td><strong>' . esc_html(strtoupper($row['severity'] ?? '')) . '</strong></td>';
            echo '<td>' . esc_html($row['event_id'] ?? '') . '</td>';
            echo '<td>' . esc_html($row['username'] ?? '') . '</td>';
            echo '<td><code>' . esc_html($row['source_ip'] ?? '') . '</code></td>';
            echo '<td><code style="white-space:pre-wrap">' . esc_html(wp_json_encode($row['details'] ?? array(), JSON_UNESCAPED_SLASHES)) . '</code></td></tr>';
            if ($shown >= 250) { break; }
        }
        if (!$shown) { echo '<tr><td colspan="6">No matching events in this log file.</td></tr>'; }
        echo '</tbody></table><p>Showing up to 250 ' . ($security_only ? 'security ' : '') . 'records from the selected monthly log.</p></div>';
    }

    private static function is_security_event($row) {
        $event = $row['event_id'] ?? '';
        $security = array('login_failed','login_failure_threshold','user_created','user_deleted','user_role_changed','plugin_activated','plugin_deactivated','plugin_deleted','theme_switched','upgrade_complete','xmlrpc_activity','rest_user_api_activity','file_added','file_modified','file_deleted','integrity_baseline_created','integrity_scan_complete','integrity_scan_path_error');
        return in_array($event, $security, true) || in_array($row['severity'] ?? '', array('high','critical'), true);
    }

    private static function tail($filename, $max_lines = 1000) {
        $lines = @file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) { return array(); }
        return array_slice($lines, -1 * absint($max_lines));
    }
}
