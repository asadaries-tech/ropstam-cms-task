<?php
/**
 * Shared server-side markup for the block and the shortcode.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queries the latest services and renders cards with the add-on calculator.
 */
class SACALC_Renderer {

	/**
	 * Number of services to show (fixed by the brief).
	 */
	const LIMIT = 3;

	/**
	 * Fetch the 3 most recently updated, published services.
	 *
	 * WP_Query only, with no raw SQL. Meta is primed in one query by the default
	 * update_post_meta_cache, so reading price/days later costs no extra queries.
	 *
	 * @return WP_Post[]
	 */
	public static function get_services() {
		$query = new WP_Query(
			array(
				'post_type'              => SACALC_Post_Type::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::LIMIT,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true, // No pagination, so skip SQL_CALC_FOUND_ROWS.
				'update_post_term_cache' => false, // Terms are never displayed.
			)
		);

		return $query->posts;
	}

	/**
	 * Render the services list.
	 *
	 * @param string $wrapper_attributes Pre-escaped attribute string for the wrapper,
	 *                                   e.g. from get_block_wrapper_attributes().
	 * @return string
	 */
	public static function render( $wrapper_attributes ) {
		global $wp_locale;

		$services = self::get_services();
		$addons   = SACALC_Addons::get();

		// Number format data so live JS totals match PHP's number_format_i18n() exactly.
		$decimal   = isset( $wp_locale->number_format['decimal_point'] ) ? $wp_locale->number_format['decimal_point'] : '.';
		$thousands = isset( $wp_locale->number_format['thousands_sep'] ) ? $wp_locale->number_format['thousands_sep'] : ',';

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes() / built with esc_attr() in the shortcode. ?>
			data-sacalc-symbol="<?php echo esc_attr( self::currency_symbol() ); ?>"
			data-sacalc-decimal="<?php echo esc_attr( $decimal ); ?>"
			data-sacalc-thousands="<?php echo esc_attr( $thousands ); ?>">
			<?php if ( empty( $services ) ) : ?>
				<p class="sacalc-services__empty"><?php esc_html_e( 'No services are available yet.', 'service-addon-calculator' ); ?></p>
			<?php else : ?>
				<ul class="sacalc-services__list" role="list">
					<?php foreach ( $services as $service ) : ?>
						<li class="sacalc-services__item"><?php echo self::render_card( $service, $addons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped inside render_card(). ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		$html = (string) ob_get_clean();

		if ( ! empty( $services ) ) {
			SACALC_Assets::enqueue_frontend();
		}

		return $html;
	}

	/**
	 * Render one service card.
	 *
	 * @param WP_Post $service Service post.
	 * @param array[] $addons  Validated add-ons.
	 * @return string
	 */
	private static function render_card( WP_Post $service, array $addons ) {
		$uid            = wp_unique_id( 'sacalc-service-' );
		$price          = SACALC_Post_Type::get_price( $service->ID );
		$days           = SACALC_Post_Type::get_days( $service->ID );
		$excerpt        = get_the_excerpt( $service );
		$has_calculator = null !== $price && ! empty( $addons );

		ob_start();
		?>
		<article class="sacalc-service" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"
			<?php if ( $has_calculator ) : ?>
				data-sacalc-calculator data-sacalc-base="<?php echo esc_attr( self::to_cents( $price ) ); ?>"
			<?php endif; ?>>
			<h3 class="sacalc-service__title" id="<?php echo esc_attr( $uid ); ?>-title">
				<a href="<?php echo esc_url( get_permalink( $service ) ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></a>
			</h3>

			<?php if ( '' !== $excerpt ) : ?>
				<p class="sacalc-service__excerpt"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p>
			<?php endif; ?>

			<dl class="sacalc-service__meta">
				<div>
					<dt><?php esc_html_e( 'Base price', 'service-addon-calculator' ); ?></dt>
					<dd><?php echo esc_html( null === $price ? __( 'On request', 'service-addon-calculator' ) : self::format_money( $price ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Delivery', 'service-addon-calculator' ); ?></dt>
					<dd><?php echo esc_html( null === $days ? __( 'To be confirmed', 'service-addon-calculator' ) : self::format_days( $days ) ); ?></dd>
				</div>
			</dl>

			<?php if ( $has_calculator ) : ?>
				<?php // Hidden until JS runs: without JS the toggles would do nothing, so the base price is shown on its own. ?>
				<fieldset class="sacalc-service__addons" data-sacalc-addons hidden>
					<legend><?php esc_html_e( 'Optional add-ons', 'service-addon-calculator' ); ?></legend>
					<ul class="sacalc-addons" role="list">
						<?php foreach ( $addons as $addon ) : ?>
							<li>
								<button type="button" class="sacalc-addon" aria-pressed="false"
									data-sacalc-addon="<?php echo esc_attr( $addon['id'] ); ?>"
									data-sacalc-price="<?php echo esc_attr( self::to_cents( $addon['price'] ) ); ?>">
									<span class="sacalc-addon__label"><?php echo esc_html( $addon['label'] ); ?></span>
									<span class="sacalc-addon__price">+<?php echo esc_html( self::format_money( $addon['price'] ) ); ?></span>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				</fieldset>

				<p class="sacalc-service__total">
					<span id="<?php echo esc_attr( $uid ); ?>-total-label"><?php esc_html_e( 'Estimated price', 'service-addon-calculator' ); ?></span>
					<output class="sacalc-service__total-value" data-sacalc-total
						aria-labelledby="<?php echo esc_attr( $uid ); ?>-total-label" aria-live="polite" aria-atomic="true"><?php echo esc_html( self::format_money( $price ) ); ?></output>
				</p>
			<?php elseif ( null === $price ) : ?>
				<p class="sacalc-service__total sacalc-service__total--request"><?php esc_html_e( 'Contact us for a quote.', 'service-addon-calculator' ); ?></p>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Convert an amount to integer cents so the JS can add prices without float rounding errors.
	 *
	 * @param float $amount Amount.
	 * @return int
	 */
	public static function to_cents( $amount ) {
		return (int) round( (float) $amount * 100 );
	}

	/**
	 * Currency symbol (filterable).
	 *
	 * @return string
	 */
	public static function currency_symbol() {
		/**
		 * Filter the currency symbol shown before prices.
		 *
		 * @param string $symbol Default "$".
		 */
		return (string) apply_filters( 'sacalc_currency_symbol', '$' );
	}

	/**
	 * Format money using the site locale.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_money( $amount ) {
		return self::currency_symbol() . number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * Format a delivery time.
	 *
	 * @param int $days Days.
	 * @return string
	 */
	public static function format_days( $days ) {
		/* translators: %s: number of days. */
		return sprintf( _n( '%s day', '%s days', $days, 'service-addon-calculator' ), number_format_i18n( $days ) );
	}
}
