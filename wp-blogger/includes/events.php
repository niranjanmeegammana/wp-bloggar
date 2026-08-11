<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WP_Blogger_Events {
    public static function init() {
        add_action('wp_login', array(__CLASS__, 'login_success'), 10, 2);
        add_action('wp_login_failed', array(__CLASS__, 'login_failed'), 10, 2);
        add_action('wp_logout', array(__CLASS__, 'logout'));

        add_action('user_register', array(__CLASS__, 'user_created'), 10, 2);
        add_action('delete_user', array(__CLASS__, 'user_deleted'), 10, 3);
        add_action('set_user_role', array(__CLASS__, 'role_changed'), 10, 3);
        add_action('profile_update', array(__CLASS__, 'profile_updated'), 10, 3);

        add_action('activated_plugin', array(__CLASS__, 'plugin_activated'), 10, 2);
        add_action('deactivated_plugin', array(__CLASS__, 'plugin_deactivated'), 10, 2);
        add_action('deleted_plugin', array(__CLASS__, 'plugin_deleted'), 10, 2);
        add_action('switch_theme', array(__CLASS__, 'theme_switched'), 10, 3);
        add_action('upgrader_process_complete', array(__CLASS__, 'upgrade_complete'), 10, 2);

        add_action('save_post', array(__CLASS__, 'post_saved'), 10, 3);
        add_action('before_delete_post', array(__CLASS__, 'post_deleted'), 10, 2);
        add_action('add_attachment', array(__CLASS__, 'attachment_added'));
        add_action('delete_attachment', array(__CLASS__, 'attachment_deleted'));

        add_action('updated_option', array(__CLASS__, 'option_updated'), 10, 3);
    }

    public static function login_success($user_login, $user) {
        WP_Blogger_Logger::log('login_success', 'info', array('login' => $user_login, 'user_id' => $user->ID));
    }

    public static function login_failed($username, $error = null) {
        WP_Blogger_Logger::log('login_failed', 'medium', array('login' => $username));
    }

    public static function logout() {
        WP_Blogger_Logger::log('logout', 'info');
    }

    public static function user_created($user_id, $userdata = array()) {
        $user = get_userdata($user_id);
        $roles = $user ? $user->roles : array();
        $severity = in_array('administrator', $roles, true) ? 'critical' : 'high';
        WP_Blogger_Logger::log('user_created', $severity, array('created_user_id' => $user_id, 'login' => $user ? $user->user_login : '', 'roles' => $roles));
    }

    public static function user_deleted($user_id, $reassign = null, $user = null) {
        WP_Blogger_Logger::log('user_deleted', 'high', array('deleted_user_id' => $user_id, 'login' => $user ? $user->user_login : '', 'reassign_to' => $reassign));
    }

    public static function role_changed($user_id, $role, $old_roles) {
        $severity = ($role === 'administrator' || in_array('administrator', (array) $old_roles, true)) ? 'critical' : 'high';
        WP_Blogger_Logger::log('user_role_changed', $severity, array('target_user_id' => $user_id, 'new_role' => $role, 'old_roles' => (array) $old_roles));
    }

    public static function profile_updated($user_id, $old_user_data, $userdata) {
        WP_Blogger_Logger::log('profile_updated', 'medium', array('target_user_id' => $user_id));
    }

    public static function plugin_activated($plugin, $network_wide) {
        WP_Blogger_Logger::log('plugin_activated', 'high', array('plugin' => $plugin, 'network_wide' => $network_wide));
    }

    public static function plugin_deactivated($plugin, $network_wide) {
        WP_Blogger_Logger::log('plugin_deactivated', 'high', array('plugin' => $plugin, 'network_wide' => $network_wide));
    }

    public static function plugin_deleted($plugin_file, $deleted) {
        WP_Blogger_Logger::log('plugin_deleted', 'high', array('plugin' => $plugin_file, 'deleted' => $deleted));
    }

    public static function theme_switched($new_name, $new_theme, $old_theme) {
        WP_Blogger_Logger::log('theme_switched', 'high', array('new_theme' => $new_name, 'old_theme' => $old_theme ? $old_theme->get('Name') : ''));
    }

    public static function upgrade_complete($upgrader, $hook_extra) {
        $type = isset($hook_extra['type']) ? sanitize_key($hook_extra['type']) : 'unknown';
        $action = isset($hook_extra['action']) ? sanitize_key($hook_extra['action']) : 'unknown';
        WP_Blogger_Logger::log('upgrade_complete', 'high', array('type' => $type, 'action' => $action));
    }

    public static function post_saved($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        WP_Blogger_Logger::log($update ? 'content_updated' : 'content_created', 'info', array('post_id' => $post_id, 'post_type' => $post->post_type, 'status' => $post->post_status));
    }

    public static function post_deleted($post_id, $post = null) {
        WP_Blogger_Logger::log('content_deleted', 'medium', array('post_id' => $post_id, 'post_type' => $post ? $post->post_type : ''));
    }

    public static function attachment_added($post_id) {
        WP_Blogger_Logger::log('media_uploaded', 'medium', array('attachment_id' => $post_id));
    }

    public static function attachment_deleted($post_id) {
        WP_Blogger_Logger::log('media_deleted', 'medium', array('attachment_id' => $post_id));
    }

    public static function option_updated($option, $old_value, $value) {
        $ignored = array('_transient_', '_site_transient_', 'cron');
        foreach ($ignored as $prefix) {
            if (strpos($option, $prefix) === 0) {
                return;
            }
        }

        $sensitive = array('active_plugins', 'users_can_register', 'default_role', 'siteurl', 'home', 'permalink_structure');
        $severity = in_array($option, $sensitive, true) ? 'high' : 'info';
        WP_Blogger_Logger::log('option_updated', $severity, array('option' => $option));
    }
}
