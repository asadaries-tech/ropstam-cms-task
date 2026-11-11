<?php
/**
 * "Services" custom post type and its meta fields.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sacalc_service post type and the base_price / delivery_time_days meta.
 */
class SACALC_Post_Type {

	/**
	 * Post type key (prefixed to avoid collisions; max 20 chars).
	 */
	const POST_TYPE = 'sacalc_service';

	/**
	 * Meta key: base price in the store currency (float, 2 decimals).
	 */
	const META_PRICE = 'base_price';

	/**
	 * Meta key: delivery time in whole days (int >= 1).
	 */
	const META_DAYS = 'delivery_time_days';

	/**
	 * Attach hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the post type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'Services', 'post type general name', 'service-addon-calculator' ),
			'singular_name'      => _x( 'Service', 'post type singular name', 'service-addon-calculator' ),
			'menu_name'          => _x( 'Services', 'admin menu', 'service-addon-calculator' ),
			'add_new_item'       => __( 'Add New Service', 'service-addon-calculator' ),
			'edit_item'          => __( 'Edit Service', 'service-addon-calculator' ),
			'new_item'           => __( 'New Service', 'service-addon-calculator' ),
			'view_item'          => __( 'View Service', 'service-addon-calculator' ),
			'all_items'          => __( 'All Services', 'service-addon-calculator' ),
			'search_items'       => __( 'Search Services', 'service-addon-calculator' ),
			'not_found'          => __( 'No services found.', 'service-addon-calculator' ),
			'not_found_in_trash' => __( 'No services found in Trash.', 'service-addon-calculator' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'services' ),
				'menu_icon'    => 'dashicons-hammer',
				'show_in_rest' => true,
				// "custom-fields" is required for the meta to be exposed in the REST API.
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
			)
		);
	}

	/**
	 * Register both meta fields with type, sanitisation and auth callbacks so
	 * every write path (meta box, REST API, update_post_meta) is sanitised.
	 */
	public static function register_meta() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		register_post_meta(
			self::POST_TYPE,
			self::META_PRICE,
			array(
				'type'              => 'number',
				'description'       => __( 'Base price of the service.', 'service-addon-calculator' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_price' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_DAYS,
			array(
				'type'              => 'integer',
				'description'       => __( 'Delivery time in days.', 'service-addon-calculator' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_days' ),
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Price: non-negative float rounded to 2 decimals.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_price( $value ) {
		return round( max( 0, (float) $value ), 2 );
	}

	/**
	 * Delivery days: positive integer, at least 1.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_days( $value ) {
		return max( 1, absint( $value ) );
	}

	/**
	 * Read a service's price. Returns null when no price has been set, so
	 * "not set" is never confused with "free".
	 *
	 * @param int $post_id Post ID.
	 * @return float|null
	 */
	public static function get_price( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_PRICE, true );
		return ( '' === $raw || null === $raw ) ? null : self::sanitize_price( $raw );
	}

	/**
	 * Read a service's delivery time. Returns null when unset.
	 *
	 * @param int $post_id Post ID.
	 * @return int|null
	 */
	public static function get_days( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_DAYS, true );
		return ( '' === $raw || null === $raw ) ? null : self::sanitize_days( $raw );
	}
}
