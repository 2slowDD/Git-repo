<?php
declare( strict_types=1 );

namespace CodeUnloader\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CodeUnloader\Core\{RuleRepository, Installer, PatternMatcher};

class AdminScreen {

	/** Stored page hook suffix so load-{hook} and screen options can reference it. */
	private string $page_hook = '';

	public function init(): void {
		add_action( 'admin_menu',    [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',    [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
		add_action( 'current_screen',  [ $this, 'maybe_hook_delete_confirmation' ] );

		// Allow WordPress to save our per_page screen option value.
		add_filter( 'set_screen_option_cu_rules_per_page', [ $this, 'save_screen_option' ], 10, 3 );

		// Upgrade check
		Installer::maybe_upgrade();
	}

	/**
	 * Callback for set_screen_option_{option} — return the sanitized value to save it.
	 *
	 * @param mixed  $status  False by default; return a value to save it.
	 * @param string $option  Option name.
	 * @param mixed  $value   Submitted value.
	 * @return int
	 */
	public function save_screen_option( $status, string $option, $value ): int {
		$value = (int) $value;
		// Clamp to allowed values: 10, 20, 50.
		return in_array( $value, [ 10, 20, 50 ], true ) ? $value : 10;
	}

	public function register_menu(): void {
		$this->page_hook = (string) add_options_page(
			__( 'Code Unloader', 'code-unloader' ),
			__( 'Code Unloader', 'code-unloader' ),
			'manage_options',
			'code-unloader',
			[ $this, 'render_page' ]
		);

		// Register the per-page screen option on the plugin's own admin page.
		add_action( 'load-' . $this->page_hook, [ $this, 'register_screen_options' ] );
	}

	/** Register per-page screen option for the Rules tab. */
	public function register_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Rules per page', 'code-unloader' ),
				'default' => 10,
				'option'  => 'cu_rules_per_page',
			]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_code-unloader' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cu-admin', CU_URL . 'assets/css/admin.css', [], CU_VERSION );
		wp_enqueue_script( 'cu-admin', CU_URL . 'assets/js/admin.js', [ 'jquery' ], CU_VERSION, true );
		wp_localize_script( 'cu-admin', 'CU_ADMIN', [
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'api_base'   => esc_url( rest_url( 'code-unloader/v1' ) ),
			'kill_switch'=> (bool) get_option( CU_OPTION_KILL ),
			'groups'     => RuleRepository::get_all_groups(),
		] );
	}

	public function handle_actions(): void {
		// Handle GET actions (export)
		if ( isset( $_GET['cu_action'] ) && current_user_can( 'manage_options' ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['cu_action'] ) );
			check_admin_referer( 'cu_admin_action' );

			if ( 'export' === $action ) {
				$this->handle_export();
				exit;
			}
		}

		// Handle POST actions (import)
		if ( ! isset( $_POST['cu_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = sanitize_text_field( wp_unslash( $_POST['cu_action'] ) );
		check_admin_referer( 'cu_admin_action' );

		switch ( $action ) {
			case 'import':
				$this->handle_import();
				break;
		}
	}

	private function handle_export(): void {
		$rules  = RuleRepository::get_rules_filtered( [], 9999, 1 )['rows'];
		$groups = RuleRepository::get_all_groups();

		$payload = [
			'version'    => CU_VERSION,
			'exported_at'=> gmdate( 'c' ),
			'rules'      => $rules,
			'groups'     => $groups,
		];

		$filename = 'code-unloader-backup-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	private function handle_import(): void {
		if ( ! check_admin_referer( 'cu_admin_action' ) ) {
			return;
		}
		if ( empty( $_FILES['cu_import_file']['tmp_name'] ) ) {
			return;
		}
		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['cu_import_file']['tmp_name'] ) );

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		$json = $wp_filesystem->get_contents( $tmp_name );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['rules'] ) ) {
			add_settings_error( 'cu_import', 'invalid', __( 'Invalid import file.', 'code-unloader' ) );
			return;
		}

		$imported = 0;
		foreach ( $data['rules'] as $rule ) {
			$result = RuleRepository::create_rule( $rule );
			if ( ! is_wp_error( $result ) ) {
				$imported++;
			}
		}

		add_settings_error( 'cu_import', 'success',
			/* translators: %d: number of imported rules */
			sprintf( __( 'Imported %d rules.', 'code-unloader' ), $imported ),
			'updated'
		);
	}

	public function show_notices(): void {
		$screen = get_current_screen();
		if ( $screen && 'settings_page_code-unloader' === $screen->id ) {
			settings_errors( 'cu_import' );
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, no data modification.
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'rules';
		$valid_tabs = [ 'rules', 'groups', 'log', 'settings' ];
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'rules';
		}

		echo '<div class="wrap cu-admin-wrap">';

		// Plugin header with icon, title, and subtitle
		echo '<div class="cu-header">';
		echo '<img src="' . esc_url( CU_URL . 'assets/img/CU_icon_200x200.png' ) . '" alt="Code Unloader" class="cu-header-icon">';
		echo '<div class="cu-header-text">';
		echo '<h1 class="cu-header-title">' . esc_html__( 'Code Unloader', 'code-unloader' ) . ' <span class="cu-header-version">v' . esc_html( CU_VERSION ) . '</span></h1>';
		echo '<p class="cu-header-subtitle">' . wp_kses(
			sprintf(
				/* translators: %s: link to WPservice.pro */
				__( 'by Dalibor Druzinec / %s', 'code-unloader' ),
				'<a href="https://wpservice.pro/" target="_blank" rel="noopener noreferrer">WPservice.pro</a>'
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		) . '</p>';
		echo '</div>';
		echo '</div>';

		// Kill switch alert
		if ( get_option( CU_OPTION_KILL ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( '⚠ Kill switch is ACTIVE — all assets are loading normally. No rules are being applied.', 'code-unloader' ) . '</strong></p></div>';
		}

		// Tabs
		$base = admin_url( 'options-general.php?page=code-unloader' );
		echo '<nav class="nav-tab-wrapper">';
		foreach ( [
			'rules'    => __( 'Rules', 'code-unloader' ),
			'groups'   => __( 'Groups', 'code-unloader' ),
			'log'      => __( 'Audit Log', 'code-unloader' ),
			'settings' => __( 'Settings', 'code-unloader' ),
		] as $key => $label ) {
			$class = ( $tab === $key ) ? 'nav-tab-active' : '';
			echo '<a href="' . esc_url( add_query_arg( 'tab', $key, $base ) ) . '" class="nav-tab ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		echo '<div class="cu-tab-content">';

		switch ( $tab ) {
			case 'rules':    $this->render_rules_tab();    break;
			case 'groups':   $this->render_groups_tab();   break;
			case 'log':      $this->render_log_tab();      break;
			case 'settings': $this->render_settings_tab(); break;
		}

		echo '</div></div>';
	}

	// -------------------------------------------------------------------------
	// Rules Tab
	// -------------------------------------------------------------------------
	private function render_rules_tab(): void {
		// Summary bar — count only active rules (disabled-group rules are suspended, not shown).
		global $wpdb;
		$count = wp_cache_get( 'cu_rules_count' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT COUNT(*) FROM {$wpdb->prefix}cu_rules r
				 LEFT JOIN {$wpdb->prefix}cu_groups g ON g.id = r.group_id
				 WHERE (g.enabled = 1 OR r.group_id IS NULL)"
			);
			wp_cache_set( 'cu_rules_count', $count );
		}

		// Stale rule detection — check after wp_enqueue_scripts has fired
		// We do this here because the admin screen loads after enqueue.
		$stale_ids = \CodeUnloader\Core\RuleRepository::get_stale_rule_ids();
		if ( ! empty( $stale_ids ) ) {
			$count_stale = count( $stale_ids );
			echo '<div class="notice notice-warning cu-stale-notice" data-stale-ids="' . esc_attr( (string) wp_json_encode( $stale_ids ) ) . '">';
			echo '<p><strong>' . sprintf(
				/* translators: %d: number of stale rules */
				esc_html( _n(
					'%d stale rule detected — the asset handle it references is no longer registered in WordPress.',
					'%d stale rules detected — these asset handles are no longer registered in WordPress.',
					$count_stale,
					'code-unloader'
				) ),
				(int) $count_stale
			) . '</strong> ';
			echo '<button type="button" class="button button-small" id="cu-delete-stale-btn">'
				. esc_html__( 'Delete stale rules', 'code-unloader' ) . '</button></p>';
			echo '</div>';
		}

		echo '<div class="cu-summary-bar">';
		/* translators: %d: number of rules */
		echo '<span id="cu-total-rules-count">' . sprintf( esc_html__( '%d total rules', 'code-unloader' ), (int) $count ) . '</span>';
		echo '<span> &bull; </span>';
		$kill = get_option( CU_OPTION_KILL ) ? '<span class="cu-kill-pill">' . esc_html__( 'Kill switch ON', 'code-unloader' ) . '</span>' : '<span class="cu-active-pill">' . esc_html__( 'Rules active', 'code-unloader' ) . '</span>';
		echo wp_kses_post( $kill );
		echo '</div>';

		// List table
		$table = new RulesListTable();
		$table->prepare_items();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="code-unloader">';
		echo '<input type="hidden" name="tab"  value="rules">';
		$table->search_box( __( 'Search rules by handle', 'code-unloader' ), 'cu_search' );
		$table->display();
		echo '</form>';
	}

	// -------------------------------------------------------------------------
	// Groups Tab
	// -------------------------------------------------------------------------
	private function render_groups_tab(): void {
		$groups = RuleRepository::get_all_groups();

		echo '<div class="cu-groups-grid" id="cu-groups-grid">';

		foreach ( $groups as $group ) {
			$disabled_class = $group->enabled ? '' : ' cu-group-card-disabled';
			echo '<div class="cu-group-card' . esc_attr( $disabled_class ) . '" data-group-id="' . esc_attr( $group->id ) . '">';
			echo '<div class="cu-group-card-header">';
			echo '<strong>' . esc_html( $group->name ) . '</strong>';
			/* translators: %d: number of rules in a group */
			echo '<span class="cu-group-rule-count">' . sprintf( esc_html__( '%d rules', 'code-unloader' ), (int) $group->rule_count ) . '</span>';
			echo '</div>';
			if ( $group->description ) {
				echo '<p class="cu-group-desc">' . esc_html( $group->description ) . '</p>';
			}
			echo '<div class="cu-group-card-actions">';
			echo '<button class="button cu-group-toggle-btn" data-id="' . esc_attr( $group->id ) . '" data-enabled="' . esc_attr( $group->enabled ) . '">'
				. ( $group->enabled ? esc_html__( 'Disable Group', 'code-unloader' ) : esc_html__( 'Enable Group', 'code-unloader' ) )
				. '</button> ';
			echo '<button class="button button-link-delete cu-group-delete-btn" data-id="' . esc_attr( $group->id ) . '">' . esc_html__( 'Delete', 'code-unloader' ) . '</button>';
			echo '</div></div>';
		}

		echo '</div>';

		// Create group form
		echo '<hr>';
		echo '<h3>' . esc_html__( 'Create New Group', 'code-unloader' ) . '</h3>';
		echo '<div id="cu-create-group-form">';
		echo '<input type="text" id="cu-new-group-name" placeholder="' . esc_attr__( 'Group name', 'code-unloader' ) . '" class="regular-text"> ';
		echo '<input type="text" id="cu-new-group-desc" placeholder="' . esc_attr__( 'Description (optional)', 'code-unloader' ) . '" class="regular-text"> ';
		echo '<button class="button button-primary" id="cu-create-group-btn">' . esc_html__( 'Create Group', 'code-unloader' ) . '</button>';
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Log Tab
	// -------------------------------------------------------------------------
	private function render_log_tab(): void {
		$table = new LogListTable();
		$table->prepare_items();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="code-unloader">';
		echo '<input type="hidden" name="tab"  value="log">';
		$table->display();
		echo '</form>';

		echo '<hr>';
		echo '<button class="button button-link-delete" id="cu-clear-log-btn">' . esc_html__( 'Clear Log', 'code-unloader' ) . '</button>';
	}

	// -------------------------------------------------------------------------
	// Settings Tab
	// -------------------------------------------------------------------------
	private function render_settings_tab(): void {
		$kill = (bool) get_option( CU_OPTION_KILL );

		echo '<table class="form-table"><tbody>';

		// Kill switch
		echo '<tr><th scope="row">' . esc_html__( 'Global Kill Switch', 'code-unloader' ) . '</th><td>';
		if ( $kill ) {
			echo '<p><span class="cu-kill-pill">' . esc_html__( '⚠ Kill switch is ACTIVE', 'code-unloader' ) . '</span></p>';
			echo '<button class="button button-secondary" id="cu-killswitch-btn" data-active="1">' . esc_html__( 'Deactivate Kill Switch', 'code-unloader' ) . '</button>';
		} else {
			echo '<button class="button button-secondary cu-btn-danger" id="cu-killswitch-btn" data-active="0">' . esc_html__( 'Activate Kill Switch', 'code-unloader' ) . '</button>';
		}
		echo '<p class="description">' . esc_html__( 'When active, all dequeue rules are bypassed sitewide. Rules are not deleted.', 'code-unloader' ) . '</p>';
		echo '</td></tr>';

		// Export
		echo '<tr><th scope="row">' . esc_html__( 'Export Rules', 'code-unloader' ) . '</th><td>';
		$export_url = wp_nonce_url(
			add_query_arg( [ 'cu_action' => 'export' ], admin_url( 'options-general.php?page=code-unloader&tab=settings' ) ),
			'cu_admin_action'
		);
		echo '<a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Download JSON Export', 'code-unloader' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'Exports all rules and groups as a JSON file. Log entries are not exported.', 'code-unloader' ) . '</p>';
		echo '</td></tr>';

		// Import
		echo '<tr><th scope="row">' . esc_html__( 'Import Rules', 'code-unloader' ) . '</th><td>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'cu_admin_action' );
		echo '<input type="hidden" name="cu_action" value="import">';
		echo '<input type="file" name="cu_import_file" accept=".json"> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Import JSON', 'code-unloader' ) . '</button>';
		echo '</form>';
		echo '</td></tr>';

		echo '</tbody></table>';
	}


	// -------------------------------------------------------------------------
	// Plugins-page delete confirmation
	// -------------------------------------------------------------------------
	public function inject_delete_confirmation(): void {
		$plugin_file = plugin_basename( CU_FILE );
		?>
<div id="cu-delete-modal" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.7);align-items:center;justify-content:center;">
	<div style="background:#fff;border-radius:10px;padding:32px;max-width:480px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.4);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
		<h2 style="margin:0 0 12px;font-size:18px;color:#b00020;">⚠️ Delete Code Unloader?</h2>
		<p style="margin:0 0 12px;color:#333;line-height:1.6;">Deleting this plugin will <strong>permanently remove all dequeue rules, groups, and audit logs</strong>.</p>
		<p style="margin:0 0 24px;color:#333;line-height:1.6;">All assets that were being unloaded will <strong>immediately start loading again</strong> on every page — your site will be returned to its state before Code Unloader was installed.</p>
		<div style="display:flex;gap:12px;justify-content:flex-end;">
			<button id="cu-delete-cancel" style="background:#f0f0f0;color:#333;border:1px solid #ccc;padding:9px 18px;border-radius:6px;cursor:pointer;font-size:14px;">Cancel</button>
			<a id="cu-delete-confirm" href="#" style="background:#b00020;color:#fff;border:none;padding:9px 18px;border-radius:6px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block;">Delete Plugin &amp; All Data</a>
		</div>
	</div>
</div>
<script>
(function(){
	var pluginFile = <?php echo wp_json_encode( $plugin_file ); ?>;
	var modal = document.getElementById('cu-delete-modal');
	var confirmBtn = document.getElementById('cu-delete-confirm');
	var cancelBtn  = document.getElementById('cu-delete-cancel');
	if (!modal) return;
	var links = document.querySelectorAll('tr[data-plugin="' + pluginFile + '"] .delete a, tr[data-slug="code-unloader"] .delete a');
	links.forEach(function(link) {
		var realHref = link.href;
		link.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			modal.style.display = 'flex';
			confirmBtn.href = realHref;
		});
	});
	cancelBtn.addEventListener('click', function() { modal.style.display = 'none'; });
	modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
	document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.style.display = 'none'; });
})();
</script>
		<?php
	}

	public function maybe_hook_delete_confirmation(): void {
		$screen = get_current_screen();
		if ( $screen && 'plugins' === $screen->id ) {
			add_action( 'admin_footer', [ $this, 'inject_delete_confirmation' ] );
		}
	}
}