<?php
/**
 * Optional add-ons offered in the calculator.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the add-on list. Developers can change it with the
 * `sacalc_service_addons` filter; whatever comes back is validated.
 */
class SACALC_Addons {

	/**
	 * Get the validated add-on list.
	 *
	 * Each item: array( 'id' => string, 'label' => string, 'price' => float ).
	 *
	 * @return array[]
	 */
	public static function get() {
		$defaults = array(
			array(
				'id'    => 'priority',
				'label' => __( 'Priority Turnaround', 'service-addon-calculator' ),
				'price' => 50,
			),
			array(
				'id'    => 'extra-revision',
				'label' => __( 'Extra Revision Round', 'service-addon-calculator' ),
				'price' => 25,
			),
			array(
				'id'    => 'source-files',
				'label' => __( 'Source Files', 'service-addon-calculator' ),
				'price' => 30,
			),
		);

		/**
		 * Filter the add-ons shown in the service calculator.
		 *
		 * @param array[] $defaults List of add-ons: id, label, price.
		 */
		$addons = apply_filters( 'sacalc_service_addons', $defaults );

		return self::validate( is_array( $addons ) ? $addons : array() );
	}

	/**
	 * Drop malformed entries and duplicate ids so filtered data can't break the markup.
	 *
	 * @param array $addons Raw add-ons.
	 * @return array[]
	 */
	private static function validate( array $addons ) {
		$clean = array();

		foreach ( $addons as $addon ) {
			if ( ! is_array( $addon ) || empty( $addon['id'] ) || empty( $addon['label'] ) || ! isset( $addon['price'] ) || ! is_numeric( $addon['price'] ) ) {
				continue;
			}

			$id = sanitize_key( $addon['id'] );
			if ( '' === $id || isset( $clean[ $id ] ) ) {
				continue;
			}

			$clean[ $id ] = array(
				'id'    => $id,
				'label' => sanitize_text_field( $addon['label'] ),
				'price' => round( max( 0, (float) $addon['price'] ), 2 ),
			);
		}

		return array_values( $clean );
	}
}
