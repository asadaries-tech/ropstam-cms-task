<?php
/**
 * Plugin Name:       Service Add-on Calculator
 * Description:       Registers a "Services" post type and a block that lists the 3 most recently updated services with a live add-on price calculator.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Senior CMS Developer Candidate
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       service-addon-calculator
 * Domain Path:       /languages
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

define( 'SACALC_VERSION', '1.0.0' );
define( 'SACALC_PLUGIN_FILE', __FILE__ );
define( 'SACALC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SACALC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-post-type.php';
require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-meta-box.php';
require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-addons.php';
require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-renderer.php';
require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-assets.php';
require_once SACALC_PLUGIN_DIR . 'includes/class-sacalc-block.php';

/**
 * Boot the plugin. Each class only attaches its own hooks.
 */
function sacalc_bootstrap() {
	SACALC_Post_Type::init();
	SACALC_Meta_Box::init();
	SACALC_Assets::init();
	SACALC_Block::init();
}
add_action( 'plugins_loaded', 'sacalc_bootstrap' );

/**
 * Load translations.
 */
function sacalc_load_textdomain() {
	load_plugin_textdomain( 'service-addon-calculator', false, dirname( plugin_basename( SACALC_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'sacalc_load_textdomain', 0 );

/**
 * On activation, register the post type first so its rewrite rules exist, then flush once.
 */
function sacalc_activate() {
	SACALC_Post_Type::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sacalc_activate' );

/**
 * On deactivation, drop this plugin's rewrite rules.
 */
function sacalc_deactivate() {
	unregister_post_type( SACALC_Post_Type::POST_TYPE );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sacalc_deactivate' );
