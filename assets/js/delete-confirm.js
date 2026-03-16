/**
 * Code Unloader — Plugin delete confirmation modal
 * Enqueued on the Plugins admin screen only.
 */
(function () {
	'use strict';

	const pluginFile = ( window.CU_DELETE_DATA && window.CU_DELETE_DATA.plugin_file ) ? window.CU_DELETE_DATA.plugin_file : '';
	const modal      = document.getElementById( 'cu-delete-modal' );
	const confirmBtn = document.getElementById( 'cu-delete-confirm' );
	const cancelBtn  = document.getElementById( 'cu-delete-cancel' );

	if ( ! modal || ! pluginFile ) {
		return;
	}

	const links = document.querySelectorAll( 'tr[data-plugin="' + pluginFile + '"] .delete a, tr[data-slug="code-unloader"] .delete a' );
	links.forEach( function ( link ) {
		const realHref = link.href;
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			modal.style.display = 'flex';
			confirmBtn.href     = realHref;
		} );
	} );

	cancelBtn.addEventListener( 'click', function () {
		modal.style.display = 'none';
	} );
	modal.addEventListener( 'click', function ( e ) {
		if ( e.target === modal ) {
			modal.style.display = 'none';
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			modal.style.display = 'none';
		}
	} );
})();
