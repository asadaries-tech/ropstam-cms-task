<?php
/**
 * Server-side render for sacalc/latest-services.
 *
 * Available variables: $attributes, $content, $block.
 *
 * @package ServiceAddonCalculator
 */

defined( 'ABSPATH' ) || exit;

echo SACALC_Renderer::render( get_block_wrapper_attributes( array( 'class' => 'sacalc-services' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped inside SACALC_Renderer.
