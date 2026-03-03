<?php
declare( strict_types=1 );

namespace CodeUnloader;

class Plugin {

	public function boot(): void {
		load_plugin_textdomain( 'code-unloader', false, dirname( plugin_basename( CU_FILE ) ) . '/languages' );

		// Core engine — always runs on frontend
		if ( ! is_admin() ) {
			( new Core\DequeueEngine() )->init();
			( new Core\InlineBlocker() )->init();
			( new Frontend\FrontendPanel() )->init();
		}

		// Admin screen
		if ( is_admin() ) {
			( new Admin\AdminScreen() )->init();
		}

		// REST API — available on all requests
		add_action( 'rest_api_init', [ new Api\RestController(), 'register_routes' ] );
	}
}
