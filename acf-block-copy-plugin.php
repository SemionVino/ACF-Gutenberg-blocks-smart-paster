<?php
/**
 * Plugin Name: ACF Block Copy with Image Resolution
 * Description: Copies ACF Gutenberg blocks and resolves attachment IDs to URLs across environments
 * Version: 1.0.0
 * Author: Semion Vino
 * Author URI: https://www.linkedin.com/in/semion-vinogradov-92439719b/
 * License: GPL2
 * Text Domain: acf-block-copy
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACF_BLOCK_COPY_VERSION', '1.0.0' );
define( 'ACF_BLOCK_COPY_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACF_BLOCK_COPY_URL', plugin_dir_url( __FILE__ ) );

// Include plugin files
require_once ACF_BLOCK_COPY_PATH . 'includes/class-block-copy-manager.php';
require_once ACF_BLOCK_COPY_PATH . 'includes/class-rest-api.php';
require_once ACF_BLOCK_COPY_PATH . 'includes/class-admin-assets.php';
require_once ACF_BLOCK_COPY_PATH . 'includes/class-image-handler.php';

// Initialize plugin
add_action( 'plugins_loaded', array( 'ACF_Block_Copy_Manager', 'get_instance' ) );
add_action( 'plugins_loaded', array( 'ACF_Block_Copy_REST_API', 'get_instance' ) );
add_action( 'plugins_loaded', array( 'ACF_Block_Copy_Image_Handler', 'get_instance' ) );
add_action( 'enqueue_block_editor_assets', array( 'ACF_Block_Copy_Admin_Assets', 'enqueue_assets' ) );
