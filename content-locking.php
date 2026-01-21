<?php
/*
 * Plugin Name:       Content Lock
 * Description:       Prevents simultaneous editing of the same content by multiple users. Applies a session-based lock when a user manages/edits content. Other users attempting to access the same content will see a clear message indicating it is being managed by someone else.
 * Version:           1.0.1
 * Author:            sundew team
 * Author URI:        https://sundewsolutions.com/
 * 
 * @package Content_Lock
 * 
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Constants.
define('CONTENT_LOCK_VERSION', '1.0.1');
define('CONTENT_LOCK_PATH', plugin_dir_path(__FILE__));
define('CONTENT_LOCK_URL', plugin_dir_url(__FILE__));
define('CONTENT_LOCK_FILE', __FILE__);

// Core Includes.
require_once CONTENT_LOCK_PATH . 'includes/class-content-lock.php';

function initialize_content_lock()
{
    $plugin = new Content_Lock();
    $plugin->lock_initialize();
}

// Initialize
initialize_content_lock();
