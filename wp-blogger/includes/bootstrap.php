<?php
if (!defined('ABSPATH')) { exit; }

require_once WP_BLOGGER_DIR . '/includes/logger.php';
require_once WP_BLOGGER_DIR . '/includes/events.php';
require_once WP_BLOGGER_DIR . '/includes/security.php';
require_once WP_BLOGGER_DIR . '/admin/admin.php';

WP_Blogger_Logger::init();
WP_Blogger_Events::init();
WP_Blogger_Security::init();
WP_Blogger_Admin::init();
