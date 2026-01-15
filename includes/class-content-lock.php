<?php
/**
 * Content lock plugin main class file
 * 
 * @since 1.0.0
 * @package Content_Lock
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Content_Lock{
    
    /**
     * Constructor
     * 
     * load all the plugin dependencies
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {    
        $this->load_dependencies();
    }

    /**
     * including required class files
     * 
     * @since 1.0.0
     * @return void
     */
    public function load_dependencies(){
        require_once CONTENT_LOCK_PATH . 'includes/class-content-lock-admin.php';
    }

    /**
     * initializing classes
     * 
     * @since 1.0.0
     * @return void
     */
    public function lock_initialize(){
        new Content_Lock_Admin();
    }

}