<?php
/**
 * Plugin Name: Aspen Membership Add-on Rules
 * Description: Restricts tagged add-ons to active members or carts containing a product that grants membership access.
 * Version: 1.0.0
 * Author: Aspen
 * Text Domain: aspen-membership-addon-rules
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'AMAR_VERSION', '1.0.0' );
define( 'AMAR_FILE', __FILE__ );
define( 'AMAR_PATH', plugin_dir_path( __FILE__ ) );

require_once AMAR_PATH . 'includes/class-rule-repository.php';
require_once AMAR_PATH . 'includes/class-eligibility.php';
require_once AMAR_PATH . 'includes/class-purchase-restrictions.php';
require_once AMAR_PATH . 'includes/class-admin.php';
require_once AMAR_PATH . 'includes/class-notices.php';
require_once AMAR_PATH . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Aspen_Membership_Addon_Rules_Plugin', 'instance' ) );
