<?php
/**
 * Block and shortcode registration.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic "Latest Services" block and its shortcode fallback.
 */
class SACALC_Block {

	const SHORTCODE = 'sacalc_latest_services';

	/**
	 * Attach hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the block from block.json and the shortcode.
	 */
	public static function register() {
		register_block_type( SACALC_PLUGIN_DIR . 'blocks/latest-services' );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Shortcode for classic themes / page builders: [sacalc_latest_services].
	 * It takes no attributes, so it can't be fed unsanitised input.
	 *
	 * @return string
	 */
	public static function shortcode() {
		return SACALC_Renderer::render( 'class="' . esc_attr( 'sacalc-services' ) . '"' );
	}
}
