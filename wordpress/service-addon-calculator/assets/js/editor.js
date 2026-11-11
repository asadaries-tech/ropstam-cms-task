/**
 * Editor registration for sacalc/latest-services.
 *
 * Written as plain ES5 using core's wp.* globals, so it needs no build step and
 * no node_modules. Name, title, icon and supports come from block.json via
 * server-side registration. The block is dynamic, so save() returns null and
 * the editor shows the real PHP output through ServerSideRender.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'sacalc/latest-services', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( ServerSideRender, { block: 'sacalc/latest-services' } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender );
