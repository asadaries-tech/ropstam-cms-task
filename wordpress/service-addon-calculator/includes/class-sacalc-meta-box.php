<?php
/**
 * Edit-screen meta box for base_price and delivery_time_days.
 *
 * A classic meta box works in both the block editor and the classic editor
 * without a JS build step.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the "Service details" meta box.
 */
class SACALC_Meta_Box {

	const NONCE_ACTION = 'sacalc_save_service_meta';
	const NONCE_FIELD  = 'sacalc_service_meta_nonce';

	/**
	 * Attach hooks. Post-type-specific hooks keep this off every other screen.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_' . SACALC_Post_Type::POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . SACALC_Post_Type::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Register the meta box.
	 */
	public static function add() {
		add_meta_box(
			'sacalc-service-details',
			__( 'Service details', 'service-addon-calculator' ),
			array( __CLASS__, 'render' ),
			SACALC_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Output the fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render( $post ) {
		$price = SACALC_Post_Type::get_price( $post->ID );
		$days  = SACALC_Post_Type::get_days( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label for="sacalc-base-price"><?php esc_html_e( 'Base price', 'service-addon-calculator' ); ?></label><br />
			<input type="number" id="sacalc-base-price" name="sacalc_base_price" class="widefat"
				min="0" step="0.01" inputmode="decimal"
				value="<?php echo esc_attr( null === $price ? '' : $price ); ?>" />
		</p>
		<p>
			<label for="sacalc-delivery-days"><?php esc_html_e( 'Delivery time (days)', 'service-addon-calculator' ); ?></label><br />
			<input type="number" id="sacalc-delivery-days" name="sacalc_delivery_time_days" class="widefat"
				min="1" step="1" inputmode="numeric"
				value="<?php echo esc_attr( null === $days ? '' : $days ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'Leave the price empty to show "Price on request".', 'service-addon-calculator' ); ?></p>
		<?php
	}

	/**
	 * Save the fields after nonce, autosave, revision and capability checks.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			SACALC_Post_Type::META_PRICE => 'sacalc_base_price',
			SACALC_Post_Type::META_DAYS  => 'sacalc_delivery_time_days',
		);

		foreach ( $fields as $meta_key => $input_name ) {
			$raw = isset( $_POST[ $input_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) ) : '';

			if ( '' === $raw || ! is_numeric( $raw ) ) {
				// Empty or non-numeric input clears the value instead of storing 0.
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			// The sanitize_callback registered in SACALC_Post_Type::register_meta() runs here too.
			update_post_meta( $post_id, $meta_key, $raw );
		}
	}
}
