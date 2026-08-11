<?php
/**
 * Plugin Name: WP Blogger
 * Description: WordPress content service.
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) { exit; }

define('WP_BLOGGER_VERSION', '1.1.0');
define('WP_BLOGGER_DIR', WP_CONTENT_DIR . '/wp-blogger');

$bootstrap = WP_BLOGGER_DIR . '/includes/bootstrap.php';
if (is_readable($bootstrap)) { require_once $bootstrap; }
