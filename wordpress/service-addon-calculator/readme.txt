=== Service Add-on Calculator ===
Contributors: (your wp.org username)
Tags: services, pricing, calculator, block
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A "Services" post type plus a block that lists the 3 most recently updated services with a live add-on price calculator.

== Description ==

* Services custom post type with Base price and Delivery time (days) fields.
* "Latest Services" dynamic block and [sacalc_latest_services] shortcode.
* Vanilla JS calculator: toggle optional add-ons and see the estimated price update instantly.
* Add-ons and currency symbol are filterable: `sacalc_service_addons`, `sacalc_currency_symbol`.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install the zip via Plugins > Add New.
2. Activate the plugin.
3. Add services under Services > Add New, then insert the "Latest Services" block on any page.

== Changelog ==

= 1.0.2 =
* Accessibility: the ✓ on pressed add-ons is now aria-hidden, so the button name stays constant and only aria-pressed announces the state.

= 1.0.1 =
* Fix: the /services/ archive took over pages with the slug "services" and broke Elementor previews. The archive is now off and single services use /service/{slug}/.

= 1.0.0 =
* Initial release.
