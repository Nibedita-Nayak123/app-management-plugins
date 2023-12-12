<?php
/** 
* Plugin Name: App Management
* Plugin URI: http://magnigenie.com
* Description: A small light weight plugin for manage apps.
* Version: 1.0
* Author: Magnigenie
* Author URI: http://magnigenie.com
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: woopcs
* Text Domain: appm
* Domain path: languages
* WC tested upto: 8.1.9
*/

// No direct file access
! defined('ABSPATH') AND exit;

define('APPM_FILE', __FILE__);
define('APPM_PATH', plugin_dir_path(__FILE__));
define('APPM_BASE', plugin_basename(__FILE__));
//PLugin Localization
add_action('plugins_loaded', 'appm_load_textdomain');

function appm_load_textdomain() {
	load_plugin_textdomain( 'appm', false, dirname( plugin_basename( __FILE__ ) ). '/languages/' );
}

if ( ! function_exists( 'wp_handle_upload' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

register_activation_hook( __FILE__, 'appm_install' );

function appm_install() {
    // update_option('appm_default_option', 'Default Value');
}


require APPM_PATH . '/includes/functions.php';
require_once APPM_PATH . '/includes/class-app-management-api-auth.php';
           
new appm();