<?php
/**
 * Script and style registration.
 *
 * Every asset is registered with wp_register_script() / wp_register_style() with
 * its dependencies listed, and loaded only through wp_enqueue_script() /
 * wp_enqueue_style() (or the block.json handles, which core enqueues the same way).
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and conditionally enqueues assets.
 */
class SACALC_Assets {

	const EDITOR_SCRIPT = 'sacalc-editor';
	const VIEW_SCRIPT   = 'sacalc-view';
	const STYLE         = 'sacalc-style';

	/**
	 * Attach hooks. Registration runs on init (priority 5) so the handles exist
	 * before the block is registered at the default priority.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_for_shortcode' ) );
	}

	/**
	 * Register all handles. Nothing is enqueued here.
	 */
	public static function register() {
		wp_register_style(
			self::STYLE,
			SACALC_PLUGIN_URL . 'assets/css/style.css',
			array(),
			SACALC_VERSION
		);

		// Front end: vanilla JS with no dependencies, deferred and in the footer.
		wp_register_script(
			self::VIEW_SCRIPT,
			SACALC_PLUGIN_URL . 'assets/js/view.js',
			array(),
			SACALC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Editor: uses only packages core already ships, so there is no build step.
		wp_register_script(
			self::EDITOR_SCRIPT,
			SACALC_PLUGIN_URL . 'assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ),
			SACALC_VERSION,
			true
		);
	}

	/**
	 * Enqueue front-end assets. Called from the renderer, so the files load only
	 * on pages that actually show the services.
	 */
	public static function enqueue_frontend() {
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::VIEW_SCRIPT );
	}

	/**
	 * When the shortcode is in a singular post's content, enqueue the stylesheet
	 * early so it prints in <head> and the cards never render unstyled.
	 */
	public static function maybe_enqueue_for_shortcode() {
		$post = get_post();
		if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, SACALC_Block::SHORTCODE ) ) {
			wp_enqueue_style( self::STYLE );
		}
	}
}
