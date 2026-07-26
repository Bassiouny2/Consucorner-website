<?php
/**
 * WordPress admin UI.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menus and pages.
 */
final class CC_GTM_Admin {

	const SLUG = 'cc-gtm-dashboard';

	/**
	 * Init admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_conflict_notice' ) );
		add_action( 'wp_ajax_cc_gtm_list_containers', array( __CLASS__, 'ajax_list_containers' ) );
	}

	/**
	 * Register menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'ConsuCorner GTM', 'consucorner-gtm' ),
			__( 'ConsuCorner GTM', 'consucorner-gtm' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-chart-line',
			58
		);
		$pages = array(
			'dashboard'   => array( __( 'Dashboard', 'consucorner-gtm' ), array( __CLASS__, 'render_dashboard' ) ),
			'settings'    => array( __( 'Settings', 'consucorner-gtm' ), array( __CLASS__, 'render_settings' ) ),
			'health'      => array( __( 'DataLayer Health', 'consucorner-gtm' ), array( __CLASS__, 'render_health' ) ),
			'auto-setup'  => array( __( 'GTM Auto Setup', 'consucorner-gtm' ), array( __CLASS__, 'render_auto_setup' ) ),
			'google-auth' => array( __( 'Google Auth', 'consucorner-gtm' ), array( __CLASS__, 'render_google_auth' ) ),
			'logs'        => array( __( 'Logs', 'consucorner-gtm' ), array( __CLASS__, 'render_logs' ) ),
			'import'      => array( __( 'Import / Export', 'consucorner-gtm' ), array( __CLASS__, 'render_import_export' ) ),
		);
		foreach ( $pages as $slug => $data ) {
			if ( 'dashboard' === $slug ) {
				continue;
			}
			add_submenu_page(
				self::SLUG,
				$data[0],
				$data[0],
				'manage_options',
				'cc-gtm-' . $slug,
				$data[1]
			);
		}
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'cc-gtm' ) ) {
			return;
		}
		$css_path = CC_GTM_PLUGIN_DIR . 'assets/admin.css';
		$js_path  = CC_GTM_PLUGIN_DIR . 'assets/admin.js';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : CC_GTM_VERSION;
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : CC_GTM_VERSION;

		wp_enqueue_style(
			'cc-gtm-admin',
			CC_GTM_PLUGIN_URL . 'assets/admin.css',
			array(),
			$css_ver
		);
		wp_enqueue_script(
			'cc-gtm-admin',
			CC_GTM_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		$status = self::get_setup_status();
		wp_localize_script(
			'cc-gtm-admin',
			'ccGtmAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'cc_gtm_admin_ajax' ),
				'containerId'  => CC_GTM_Settings::get_gtm_container_id(),
				'setupStatus'  => $status,
				'strings'      => array(
					'loadingContainers' => __( 'Loading containers…', 'consucorner-gtm' ),
					'selectContainer'   => __( '— Select container —', 'consucorner-gtm' ),
					'containerError'    => __( 'Could not load containers.', 'consucorner-gtm' ),
				),
			)
		);
	}

	/**
	 * Handle POST actions.
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// OAuth callback (Google returns code + state to redirect_uri; no action= in URI).
		if ( CC_GTM_Google_Auth::is_oauth_callback_request() ) {
			$result = CC_GTM_Google_Auth::handle_callback();
			$redirect = add_query_arg(
				array(
					'page'    => 'cc-gtm-google-auth',
					'cc_gtm'  => is_wp_error( $result ) ? 'oauth_error' : 'oauth_ok',
					'message' => is_wp_error( $result ) ? rawurlencode( $result->get_error_message() ) : '',
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( empty( $_POST['cc_gtm_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['cc_gtm_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, 'cc_gtm_admin' ) ) {
			return;
		}

		switch ( $action ) {
			case 'save_settings':
				$result = CC_GTM_Settings::save_from_input( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( $result['ok'] ) {
					CC_GTM_Logger::log( 'settings_saved', __( 'Settings saved.', 'consucorner-gtm' ) );
					self::redirect_notice( 'settings_ok' );
				}
				self::redirect_notice( 'settings_error', implode( ' ', $result['errors'] ) );
				break;

			case 'disconnect_google':
				CC_GTM_Google_Auth::disconnect();
				self::redirect_notice( 'google_disconnected', '', 'cc-gtm-google-auth' );
				break;

			case 'save_gtm_selection':
				$usage_ctx = sanitize_key( wp_unslash( $_POST['gtm_container_usage_context'] ?? 'web' ) );
				if ( ! in_array( $usage_ctx, array( 'web', 'server', 'amp', 'ios', 'android' ), true ) ) {
					$usage_ctx = 'web';
				}
				CC_GTM_Settings::update(
					array(
						'gtm_account_id'        => sanitize_text_field( wp_unslash( $_POST['gtm_account_id'] ?? '' ) ),
						'gtm_account_name'      => sanitize_text_field( wp_unslash( $_POST['gtm_account_name'] ?? '' ) ),
						'gtm_container_api_id'  => sanitize_text_field( wp_unslash( $_POST['gtm_container_api_id'] ?? '' ) ),
						'gtm_container_public_id' => strtoupper( sanitize_text_field( wp_unslash( $_POST['gtm_container_public_id'] ?? '' ) ) ),
						'gtm_container_usage_context' => $usage_ctx,
					)
				);
				CC_GTM_Logger::log( 'container_selected', __( 'GTM container selected.', 'consucorner-gtm' ) );
				self::redirect_notice( 'container_ok', '', 'cc-gtm-auto-setup' );
				break;

			case 'create_workspace':
				$res = CC_GTM_Auto_Setup::create_workspace();
				self::redirect_notice( $res['ok'] ? 'workspace_ok' : 'workspace_error', $res['message'] ?? '', 'cc-gtm-auto-setup' );
				break;

			case 'run_auto_setup':
				$publish = false;
				if ( ! empty( $_POST['allow_auto_publish'] ) && ! empty( $_POST['confirm_publish'] ) ) {
					$publish = (bool) CC_GTM_Settings::get( 'allow_auto_publish', false );
				}
				$res = CC_GTM_Auto_Setup::run_full_setup( $publish );
				set_transient( 'cc_gtm_setup_result', $res, HOUR_IN_SECONDS );
				self::redirect_notice( $res['ok'] ? 'setup_ok' : 'setup_error', $res['message'] ?? '', 'cc-gtm-auto-setup' );
				break;

			case 'run_one_click_setup':
				$res = CC_GTM_Auto_Setup::run_one_click_setup( false );
				set_transient( 'cc_gtm_setup_result', $res, HOUR_IN_SECONDS );
				self::redirect_notice( $res['ok'] ? 'setup_ok' : 'setup_error', $res['message'] ?? '', self::SLUG );
				break;

			case 'deactivate_conflicts':
				$files = isset( $_POST['conflict_files'] ) ? (array) wp_unslash( $_POST['conflict_files'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$files = array_map( 'sanitize_text_field', $files );
				$res   = CC_GTM_Conflicts::deactivate( $files );
				self::redirect_notice( $res['ok'] ? 'conflicts_ok' : 'conflicts_error', $res['message'] ?? '', self::SLUG );
				break;

			case 'clear_logs':
				CC_GTM_Logger::clear();
				self::redirect_notice( 'logs_cleared', '', 'cc-gtm-logs' );
				break;

			case 'import_settings':
				$json = isset( $_POST['import_json'] ) ? wp_unslash( $_POST['import_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$res  = CC_GTM_Settings::import_json( $json );
				self::redirect_notice( $res['ok'] ? 'import_ok' : 'import_error', $res['message'] ?? '', 'cc-gtm-import' );
				break;

			case 'download_checklist':
				// Handled via GET in render.
				break;
		}
	}

	/**
	 * @param string $code Notice code.
	 * @param string $message Message.
	 * @param string $page Admin page slug.
	 */
	private static function redirect_notice( $code, $message = '', $page = 'cc-gtm-dashboard' ) {
		$url = add_query_arg(
			array(
				'page'    => $page,
				'cc_gtm'  => $code,
				'message' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Print admin notices from query args.
	 */
	private static function print_notices() {
		if ( empty( $_GET['cc_gtm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['cc_gtm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg  = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class = ( false !== strpos( $code, 'error' ) || false !== strpos( $code, 'oauth_error' ) ) ? 'notice-error' : 'notice-success';
		if ( 'setup_ok' === $code ) {
			$msg = __( 'GTM setup created successfully. Please open GTM Preview Mode and test before publishing.', 'consucorner-gtm' );
		}
		if ( $msg ) {
			printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $msg ) );
		}
	}

	/**
	 * Setup progress for wizard UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_setup_status() {
		$s = CC_GTM_Settings::get_all();

		$gtm_id           = CC_GTM_Settings::get_gtm_container_id();
		$settings_ok      = CC_GTM_Settings::validate_gtm_id( $gtm_id ) && CC_GTM_Settings::get( 'enable_gtm', true );
		$oauth_configured = CC_GTM_Google_Auth::is_configured();
		$oauth_connected  = CC_GTM_Google_Auth::is_connected();
		$container_ok     = ! empty( $s['gtm_account_id'] ) && ! empty( $s['gtm_container_api_id'] );
		$workspace_ok     = ! empty( $s['gtm_workspace_id'] );
		$tags_ok          = CC_GTM_Auto_Setup::has_tags_from_last_setup();

		$steps = array(
			array(
				'id'    => 'settings',
				'label' => __( 'Settings', 'consucorner-gtm' ),
				'done'  => $settings_ok,
			),
			array(
				'id'    => 'oauth',
				'label' => __( 'Connect Google', 'consucorner-gtm' ),
				'done'  => $oauth_connected,
			),
			array(
				'id'    => 'container',
				'label' => __( 'Select Container', 'consucorner-gtm' ),
				'done'  => $container_ok,
			),
			array(
				'id'    => 'setup',
				'label' => __( 'Run Setup', 'consucorner-gtm' ),
				'done'  => $tags_ok,
			),
			array(
				'id'    => 'preview',
				'label' => __( 'Preview in GTM', 'consucorner-gtm' ),
				'done'  => $tags_ok,
			),
		);

		$current = 0;
		foreach ( $steps as $i => $step ) {
			if ( empty( $step['done'] ) ) {
				$current = $i;
				break;
			}
			$current = $i;
		}

		$can_one_click = $settings_ok && $oauth_configured && $oauth_connected;

		return array(
			'steps'           => $steps,
			'current_step'    => $current,
			'settings_ok'     => $settings_ok,
			'oauth_configured'=> $oauth_configured,
			'oauth_connected' => $oauth_connected,
			'container_ok'    => $container_ok,
			'workspace_ok'    => $workspace_ok,
			'tags_ok'         => $tags_ok,
			'gtm_installed'   => (bool) CC_GTM_Settings::get( 'enable_gtm', true ),
			'can_one_click'   => $can_one_click,
			'gtm_id'          => $gtm_id,
		);
	}

	/**
	 * AJAX: list containers for account.
	 */
	public static function ajax_list_containers() {
		check_ajax_referer( 'cc_gtm_admin_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'consucorner-gtm' ) ), 403 );
		}
		$account_id = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $account_id ) {
			wp_send_json_error( array( 'message' => __( 'Account ID required.', 'consucorner-gtm' ) ) );
		}
		if ( ! CC_GTM_Google_Auth::is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'Connect Google first.', 'consucorner-gtm' ) ) );
		}
		$result = CC_GTM_API::list_containers( $account_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$containers = CC_GTM_API::extract_list( $result, 'container', 'containers' );
		$items      = array();
		foreach ( $containers as $c ) {
			$items[] = array(
				'containerId'  => isset( $c['containerId'] ) ? (string) $c['containerId'] : '',
				'publicId'     => isset( $c['publicId'] ) ? (string) $c['publicId'] : '',
				'name'         => isset( $c['name'] ) ? (string) $c['name'] : '',
				'usageContext' => CC_GTM_API::usage_context_label( $c ),
			);
		}
		wp_send_json_success( array( 'containers' => $items ) );
	}

	/**
	 * @param array<string, mixed> $status Setup status.
	 */
	private static function render_wizard_steps( array $status ) {
		$steps   = $status['steps'];
		$current = (int) $status['current_step'];
		?>
		<ol class="cc-gtm-steps" aria-label="<?php esc_attr_e( 'Setup progress', 'consucorner-gtm' ); ?>">
			<?php foreach ( $steps as $i => $step ) : ?>
				<?php
				$classes = array( 'cc-gtm-step' );
				if ( ! empty( $step['done'] ) ) {
					$classes[] = 'is-done';
				}
				if ( $i === $current ) {
					$classes[] = 'is-current';
				}
				?>
				<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
					<span class="cc-gtm-step__num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
					<span class="cc-gtm-step__label"><?php echo esc_html( $step['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * @param array<string, mixed> $status Setup status.
	 */
	private static function render_status_cards( array $status ) {
		$s = CC_GTM_Settings::get_all();
		$cards = array(
			array(
				'key'   => 'gtm',
				'title' => __( 'GTM installed', 'consucorner-gtm' ),
				'done'  => ! empty( $status['gtm_installed'] ),
				'detail'=> '<code>' . esc_html( $status['gtm_id'] ) . '</code>',
			),
			array(
				'key'   => 'oauth',
				'title' => __( 'OAuth connected', 'consucorner-gtm' ),
				'done'  => ! empty( $status['oauth_connected'] ),
				'detail'=> ! empty( $status['oauth_connected'] )
					? esc_html( CC_GTM_Google_Auth::get_connected_email() ?: __( 'Connected', 'consucorner-gtm' ) )
					: ( ! empty( $status['oauth_configured'] ) ? esc_html__( 'Not connected', 'consucorner-gtm' ) : esc_html__( 'Add credentials in Settings', 'consucorner-gtm' ) ),
			),
			array(
				'key'   => 'container',
				'title' => __( 'Container selected', 'consucorner-gtm' ),
				'done'  => ! empty( $status['container_ok'] ),
				'detail'=> ! empty( $status['container_ok'] )
					? esc_html( ( $s['gtm_container_public_id'] ?: $status['gtm_id'] ) . ( $s['gtm_account_name'] ? ' · ' . $s['gtm_account_name'] : '' ) )
					: esc_html__( 'Auto-matched on setup', 'consucorner-gtm' ),
			),
			array(
				'key'   => 'workspace',
				'title' => __( 'Workspace ready', 'consucorner-gtm' ),
				'done'  => ! empty( $status['workspace_ok'] ),
				'detail'=> ! empty( $s['gtm_workspace_name'] ) ? esc_html( $s['gtm_workspace_name'] ) : esc_html__( 'Created during setup', 'consucorner-gtm' ),
			),
			array(
				'key'   => 'tags',
				'title' => __( 'Tags created', 'consucorner-gtm' ),
				'done'  => ! empty( $status['tags_ok'] ),
				'detail'=> ! empty( $status['tags_ok'] ) ? esc_html__( 'Variables, triggers & tags in workspace', 'consucorner-gtm' ) : esc_html__( 'Run one-click setup', 'consucorner-gtm' ),
			),
		);
		?>
		<div class="cc-gtm-status-grid">
			<?php foreach ( $cards as $card ) : ?>
				<div class="cc-gtm-status-card <?php echo ! empty( $card['done'] ) ? 'is-ok' : 'is-pending'; ?>">
					<div class="cc-gtm-status-card__head">
						<span class="cc-gtm-badge <?php echo ! empty( $card['done'] ) ? 'cc-gtm-badge--success' : 'cc-gtm-badge--pending'; ?>">
							<?php echo ! empty( $card['done'] ) ? esc_html__( 'Ready', 'consucorner-gtm' ) : esc_html__( 'Pending', 'consucorner-gtm' ); ?>
						</span>
						<h3><?php echo esc_html( $card['title'] ); ?></h3>
					</div>
					<p class="cc-gtm-status-card__detail"><?php echo $card['detail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Primary one-click CTA and prerequisite hints.
	 *
	 * @param array<string, mixed> $status Setup status.
	 */
	private static function render_one_click_panel( array $status ) {
		$auth_url = CC_GTM_Google_Auth::get_auth_url();
		?>
		<div class="cc-gtm-hero">
			<div class="cc-gtm-hero__copy">
				<h2><?php esc_html_e( 'One-Click GTM Setup', 'consucorner-gtm' ); ?></h2>
				<p><?php esc_html_e( 'Connect Google, match your container, create a workspace, and install dataLayer variables, triggers, and platform tags in one flow.', 'consucorner-gtm' ); ?></p>
				<?php if ( empty( $status['settings_ok'] ) ) : ?>
					<p class="cc-gtm-hint cc-gtm-hint--warn"><?php esc_html_e( 'Step 1: Save your GTM Container ID in Settings.', 'consucorner-gtm' ); ?></p>
				<?php elseif ( empty( $status['oauth_configured'] ) ) : ?>
					<p class="cc-gtm-hint cc-gtm-hint--warn"><?php esc_html_e( 'Step 2: Add Google OAuth Client ID and Secret in Settings.', 'consucorner-gtm' ); ?></p>
				<?php elseif ( empty( $status['oauth_connected'] ) ) : ?>
					<p class="cc-gtm-hint"><?php esc_html_e( 'Step 2: Connect your Google account with GTM edit access.', 'consucorner-gtm' ); ?></p>
				<?php else : ?>
					<p class="cc-gtm-hint"><?php esc_html_e( 'Ready — setup will auto-select the container matching your GTM ID, create a workspace, and install tracking assets.', 'consucorner-gtm' ); ?></p>
				<?php endif; ?>
			</div>
			<div class="cc-gtm-hero__actions">
				<?php if ( empty( $status['settings_ok'] ) ) : ?>
					<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'consucorner-gtm' ); ?></a>
				<?php elseif ( empty( $status['oauth_configured'] ) ) : ?>
					<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-settings#cc-gtm-oauth' ) ); ?>"><?php esc_html_e( 'Configure Google OAuth', 'consucorner-gtm' ); ?></a>
				<?php elseif ( empty( $status['oauth_connected'] ) && ! is_wp_error( $auth_url ) ) : ?>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $auth_url ); ?>"><?php esc_html_e( 'Connect Google Account', 'consucorner-gtm' ); ?></a>
				<?php elseif ( ! empty( $status['can_one_click'] ) ) : ?>
					<form method="post" class="cc-gtm-one-click-form" onsubmit="return confirm('<?php echo esc_js( __( 'Run full GTM setup in your container workspace? Existing items with the same names will be skipped.', 'consucorner-gtm' ) ); ?>');">
						<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
						<input type="hidden" name="cc_gtm_action" value="run_one_click_setup" />
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Start One-Click Setup', 'consucorner-gtm' ); ?></button>
					</form>
				<?php endif; ?>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-auto-setup' ) ); ?>"><?php esc_html_e( 'Advanced setup', 'consucorner-gtm' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Standing notice on the Plugins screen when a GTM plugin conflict exists.
	 */
	public static function maybe_conflict_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		if ( ! CC_GTM_Conflicts::has_conflicts() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'ConsuCorner GTM: another Google Tag Manager plugin is active and may load a duplicate container.', 'consucorner-gtm' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Review conflict', 'consucorner-gtm' )
		);
	}

	/**
	 * Warn about (and offer to deactivate) conflicting GTM plugins.
	 */
	private static function render_conflict_panel() {
		$conflicts = CC_GTM_Conflicts::get_active_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}
		$guard_on = (bool) CC_GTM_Settings::get( 'conflict_guard', true );
		?>
		<section class="cc-gtm-panel cc-gtm-conflict">
			<h2><?php esc_html_e( 'Conflicting GTM plugin detected', 'consucorner-gtm' ); ?></h2>
			<p>
				<?php esc_html_e( 'Another Tag Manager plugin is active. Running two GTM containers slows the storefront and double-counts every event.', 'consucorner-gtm' ); ?>
			</p>
			<ul class="cc-gtm-list">
				<?php foreach ( $conflicts as $c ) : ?>
					<li><strong><?php echo esc_html( $c['label'] ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $guard_on ) : ?>
				<p class="cc-gtm-ok"><?php esc_html_e( 'Auto-silence is ON — GTM4WP frontend output is suppressed so only this plugin\'s container runs. For a fully clean setup, deactivate the plugin(s) below.', 'consucorner-gtm' ); ?></p>
			<?php else : ?>
				<p class="cc-gtm-warn"><?php esc_html_e( 'Auto-silence is OFF. Enable it in Settings or deactivate the conflicting plugin(s) below.', 'consucorner-gtm' ); ?></p>
			<?php endif; ?>
			<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Deactivate the conflicting GTM plugin(s)?', 'consucorner-gtm' ) ); ?>');">
					<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
					<input type="hidden" name="cc_gtm_action" value="deactivate_conflicts" />
					<?php foreach ( $conflicts as $c ) : ?>
						<input type="hidden" name="conflict_files[]" value="<?php echo esc_attr( $c['file'] ); ?>" />
					<?php endforeach; ?>
					<?php submit_button( __( 'Deactivate conflicting plugin(s)', 'consucorner-gtm' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Dashboard page.
	 */
	public static function render_dashboard() {
		self::print_notices();
		$status  = self::get_setup_status();
		$s       = CC_GTM_Settings::get_all();
		$setup   = get_transient( 'cc_gtm_setup_result' );
		$rm_on   = CC_GTM_RankMath::is_frontend_analytics_enabled() && CC_GTM_Settings::get( 'rankmath_guard', true );
		$preview = 'https://tagmanager.google.com/';
		if ( ! empty( $s['gtm_container_public_id'] ) || ! empty( $status['gtm_id'] ) ) {
			$preview = 'https://tagmanager.google.com/#/container/' . rawurlencode( $s['gtm_container_public_id'] ?: $status['gtm_id'] ) . '/preview';
		}
		?>
		<div class="wrap cc-gtm-wrap cc-gtm-dashboard">
			<header class="cc-gtm-header">
				<div>
					<h1><?php esc_html_e( 'ConsuCorner GTM', 'consucorner-gtm' ); ?></h1>
					<p class="cc-gtm-subtitle"><?php esc_html_e( 'Tag Manager, dataLayer ecommerce, and API auto-setup', 'consucorner-gtm' ); ?></p>
				</div>
				<div class="cc-gtm-header__links">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-health' ) ); ?>"><?php esc_html_e( 'DataLayer Health', 'consucorner-gtm' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'consucorner-gtm' ); ?></a>
				</div>
			</header>

			<?php self::render_conflict_panel(); ?>
			<?php self::render_wizard_steps( $status ); ?>
			<?php self::render_one_click_panel( $status ); ?>
			<?php self::render_status_cards( $status ); ?>

			<?php if ( is_array( $setup ) && ! empty( $setup['summary'] ) ) : ?>
				<section class="cc-gtm-panel">
					<h2><?php esc_html_e( 'Last setup result', 'consucorner-gtm' ); ?></h2>
					<pre class="cc-gtm-code"><?php echo esc_html( wp_json_encode( $setup['summary'], JSON_PRETTY_PRINT ) ); ?></pre>
				</section>
			<?php endif; ?>

			<div class="cc-gtm-cards cc-gtm-cards--compact">
				<div class="cc-gtm-card">
					<h2><?php esc_html_e( 'Storefront', 'consucorner-gtm' ); ?></h2>
					<?php $server_url = CC_GTM_Settings::get_server_container_url(); ?>
					<ul class="cc-gtm-meta-list">
						<li><span><?php esc_html_e( 'dataLayer', 'consucorner-gtm' ); ?></span> <?php echo CC_GTM_Settings::get( 'enable_datalayer', true ) ? esc_html__( 'On', 'consucorner-gtm' ) : esc_html__( 'Off', 'consucorner-gtm' ); ?></li>
						<li><span><?php esc_html_e( 'Ecommerce', 'consucorner-gtm' ); ?></span> <?php echo CC_GTM_Settings::get( 'enable_ecommerce', true ) ? esc_html__( 'On', 'consucorner-gtm' ) : esc_html__( 'Off', 'consucorner-gtm' ); ?></li>
						<li><span><?php esc_html_e( 'Server-side (GA4)', 'consucorner-gtm' ); ?></span> <?php echo $server_url ? '<span class="cc-gtm-badge cc-gtm-badge--server">' . esc_html__( 'On', 'consucorner-gtm' ) . '</span>' : '<span class="cc-gtm-badge cc-gtm-badge--pending">' . esc_html__( 'Off', 'consucorner-gtm' ) . '</span>'; ?></li>
						<li><span><?php esc_html_e( 'First-party loader', 'consucorner-gtm' ); ?></span> <?php echo ( $server_url && CC_GTM_Settings::get( 'first_party_loader', false ) ) ? esc_html__( 'On', 'consucorner-gtm' ) : esc_html__( 'Off', 'consucorner-gtm' ); ?></li>
					</ul>
				</div>
				<div class="cc-gtm-card">
					<h2><?php esc_html_e( 'Platforms (for GTM tags)', 'consucorner-gtm' ); ?></h2>
					<ul class="cc-gtm-meta-list">
						<li>GA4 <?php echo ! empty( $s['ga4_enabled'] ) && ! empty( $s['ga4_measurement_id'] ) ? '<span class="cc-gtm-badge cc-gtm-badge--success">' . esc_html__( 'On', 'consucorner-gtm' ) . '</span>' : '<span class="cc-gtm-badge cc-gtm-badge--pending">' . esc_html__( 'Off', 'consucorner-gtm' ) . '</span>'; ?></li>
						<li>Meta <?php echo ! empty( $s['meta_enabled'] ) && ! empty( $s['meta_pixel_id'] ) ? '<span class="cc-gtm-badge cc-gtm-badge--success">' . esc_html__( 'On', 'consucorner-gtm' ) . '</span>' : '<span class="cc-gtm-badge cc-gtm-badge--pending">' . esc_html__( 'Off', 'consucorner-gtm' ) . '</span>'; ?></li>
						<li>Google Ads <?php echo ! empty( $s['google_ads_enabled'] ) && ! empty( $s['google_ads_conversion_id'] ) ? '<span class="cc-gtm-badge cc-gtm-badge--success">' . esc_html__( 'On', 'consucorner-gtm' ) . '</span>' : '<span class="cc-gtm-badge cc-gtm-badge--pending">' . esc_html__( 'Off', 'consucorner-gtm' ) . '</span>'; ?></li>
					</ul>
				</div>
				<div class="cc-gtm-card">
					<h2><?php esc_html_e( 'Rank Math guard', 'consucorner-gtm' ); ?></h2>
					<?php if ( $rm_on ) : ?>
						<p class="cc-gtm-warn"><?php esc_html_e( 'Rank Math may still output frontend analytics. Keep the guard enabled.', 'consucorner-gtm' ); ?></p>
					<?php else : ?>
						<p class="cc-gtm-ok"><?php esc_html_e( 'Frontend analytics blocked — tags run through GTM only.', 'consucorner-gtm' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $status['tags_ok'] ) ) : ?>
				<p class="cc-gtm-footer-cta">
					<a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url( $preview ); ?>"><?php esc_html_e( 'Open GTM Preview', 'consucorner-gtm' ); ?></a>
					<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Test storefront', 'consucorner-gtm' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Settings page.
	 */
	public static function render_settings() {
		self::print_notices();
		$s          = CC_GTM_Settings::get_all();
		$server_url = CC_GTM_Settings::get_server_container_url();
		?>
		<div class="wrap cc-gtm-wrap">
			<header class="cc-gtm-header">
				<div>
					<h1><?php esc_html_e( 'ConsuCorner GTM Settings', 'consucorner-gtm' ); ?></h1>
					<p class="cc-gtm-subtitle"><?php esc_html_e( 'Configure tag injection, server-side routing, marketing platforms, and the GTM API connection.', 'consucorner-gtm' ); ?></p>
				</div>
				<div class="cc-gtm-header__links">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>"><?php esc_html_e( 'Back to dashboard', 'consucorner-gtm' ); ?></a>
				</div>
			</header>

			<form method="post" class="cc-gtm-settings-form">
				<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
				<input type="hidden" name="cc_gtm_action" value="save_settings" />

				<section class="cc-gtm-panel">
					<h2><?php esc_html_e( 'General', 'consucorner-gtm' ); ?></h2>
					<p class="cc-gtm-section-desc"><?php esc_html_e( 'Core Tag Manager output for the storefront.', 'consucorner-gtm' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cc-gtm-id"><?php esc_html_e( 'GTM Container ID', 'consucorner-gtm' ); ?></label></th>
							<td>
								<input id="cc-gtm-id" name="gtm_container_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['gtm_container_id'] ); ?>" placeholder="GTM-XXXXXXX" />
								<p class="description"><?php esc_html_e( 'Your Web container ID. This is the container loaded on every page.', 'consucorner-gtm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-env"><?php esc_html_e( 'Environment', 'consucorner-gtm' ); ?></label></th>
							<td>
								<select id="cc-gtm-env" name="environment">
									<option value="production" <?php selected( $s['environment'], 'production' ); ?>><?php esc_html_e( 'Production', 'consucorner-gtm' ); ?></option>
									<option value="staging" <?php selected( $s['environment'], 'staging' ); ?>><?php esc_html_e( 'Staging', 'consucorner-gtm' ); ?></option>
								</select>
							</td>
						</tr>
						<?php
						$toggles = array(
							'enable_gtm'         => array( __( 'Enable GTM injection', 'consucorner-gtm' ), __( 'Output the GTM container snippet in the page head.', 'consucorner-gtm' ) ),
							'enable_noscript'    => array( __( 'Enable noscript iframe', 'consucorner-gtm' ), __( 'Fallback for visitors with JavaScript disabled.', 'consucorner-gtm' ) ),
							'enable_datalayer'   => array( __( 'Enable dataLayer', 'consucorner-gtm' ), __( 'Push page and ecommerce context into the dataLayer.', 'consucorner-gtm' ) ),
							'enable_ecommerce'   => array( __( 'Enable ecommerce events', 'consucorner-gtm' ), __( 'view_item, add_to_cart, purchase, and more.', 'consucorner-gtm' ) ),
							'debug_mode'         => array( __( 'Debug mode (admin overlay)', 'consucorner-gtm' ), __( 'Show a live event overlay for logged-in admins only.', 'consucorner-gtm' ) ),
							'rankmath_guard'     => array( __( 'Block Rank Math frontend analytics', 'consucorner-gtm' ), __( 'Prevents duplicate GA hits from Rank Math.', 'consucorner-gtm' ) ),
							'conflict_guard'     => array( __( 'Silence other GTM plugins', 'consucorner-gtm' ), __( 'Prevents a duplicate container from another plugin.', 'consucorner-gtm' ) ),
							'allow_auto_publish' => array( __( 'Allow auto-publish', 'consucorner-gtm' ), __( 'Lets setup publish the container (requires double confirmation).', 'consucorner-gtm' ) ),
						);
						foreach ( $toggles as $key => $pair ) :
							?>
						<tr>
							<th scope="row"><?php echo esc_html( $pair[0] ); ?></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<p class="description"><?php echo esc_html( $pair[1] ); ?></p>
							</td>
						</tr>
						<?php endforeach; ?>
					</table>
				</section>

				<section class="cc-gtm-panel cc-gtm-panel--server" id="cc-gtm-server">
					<h2>
						<?php esc_html_e( 'Server-Side GTM', 'consucorner-gtm' ); ?>
						<span class="cc-gtm-badge <?php echo $server_url ? 'cc-gtm-badge--success' : 'cc-gtm-badge--pending'; ?>"><?php echo $server_url ? esc_html__( 'Active', 'consucorner-gtm' ) : esc_html__( 'Off', 'consucorner-gtm' ); ?></span>
					</h2>
					<p class="cc-gtm-section-desc">
						<?php esc_html_e( 'Your site loads a Web container. To send GA4 data first-party through a Server container (sGTM), enter its URL below. Auto Setup will route the GA4 tag to this endpoint.', 'consucorner-gtm' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Route GA4 to server container', 'consucorner-gtm' ); ?></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="server_container_enabled" value="1" <?php checked( ! empty( $s['server_container_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<p class="description"><?php esc_html_e( 'Adds the server_container_url setting to the GA4 configuration tag during Auto Setup.', 'consucorner-gtm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-server-url"><?php esc_html_e( 'Server Container URL', 'consucorner-gtm' ); ?></label></th>
							<td>
								<input id="cc-gtm-server-url" name="server_container_url" type="url" inputmode="url" class="large-text" value="<?php echo esc_attr( $s['server_container_url'] ); ?>" placeholder="https://gtm.consucorner.com" />
								<p class="description"><?php esc_html_e( 'The HTTPS endpoint of your server-side tagging container (custom domain recommended for first-party cookies).', 'consucorner-gtm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'First-party loading', 'consucorner-gtm' ); ?></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="first_party_loader" value="1" <?php checked( ! empty( $s['first_party_loader'] ) ); ?> /> <span><?php esc_html_e( 'Load gtm.js from the server container domain', 'consucorner-gtm' ); ?></span></label>
								<p class="description"><?php esc_html_e( 'Serves the container script from your domain instead of googletagmanager.com. Only enable after confirming your server container serves gtm.js.', 'consucorner-gtm' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="cc-gtm-panel">
					<h2><?php esc_html_e( 'Marketing platforms', 'consucorner-gtm' ); ?></h2>
					<p class="cc-gtm-section-desc"><?php esc_html_e( 'Enable a platform and add its ID, then run Auto Setup to create the matching GTM tags.', 'consucorner-gtm' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cc-gtm-ga4"><?php esc_html_e( 'Google Analytics 4', 'consucorner-gtm' ); ?></label></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="ga4_enabled" value="1" <?php checked( ! empty( $s['ga4_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<input id="cc-gtm-ga4" name="ga4_measurement_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['ga4_measurement_id'] ); ?>" placeholder="G-XXXXXXXX" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-ads"><?php esc_html_e( 'Google Ads', 'consucorner-gtm' ); ?></label></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="google_ads_enabled" value="1" <?php checked( ! empty( $s['google_ads_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<input id="cc-gtm-ads" name="google_ads_conversion_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['google_ads_conversion_id'] ); ?>" placeholder="AW-XXXXXXXX" />
								<p><input name="google_ads_purchase_label" type="text" class="regular-text" value="<?php echo esc_attr( $s['google_ads_purchase_label'] ); ?>" placeholder="<?php esc_attr_e( 'Purchase conversion label', 'consucorner-gtm' ); ?>" /></p>
								<p><input name="google_ads_atc_label" type="text" class="regular-text" value="<?php echo esc_attr( $s['google_ads_atc_label'] ); ?>" placeholder="<?php esc_attr_e( 'Add to cart conversion label', 'consucorner-gtm' ); ?>" /></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-meta"><?php esc_html_e( 'Meta Pixel', 'consucorner-gtm' ); ?></label></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="meta_enabled" value="1" <?php checked( ! empty( $s['meta_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<input id="cc-gtm-meta" name="meta_pixel_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['meta_pixel_id'] ); ?>" placeholder="<?php esc_attr_e( 'Numeric pixel ID', 'consucorner-gtm' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-tiktok"><?php esc_html_e( 'TikTok Pixel', 'consucorner-gtm' ); ?></label></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="tiktok_enabled" value="1" <?php checked( ! empty( $s['tiktok_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<input id="cc-gtm-tiktok" name="tiktok_pixel_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['tiktok_pixel_id'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-clarity"><?php esc_html_e( 'Microsoft Clarity', 'consucorner-gtm' ); ?></label></th>
							<td>
								<label class="cc-gtm-check"><input type="checkbox" name="clarity_enabled" value="1" <?php checked( ! empty( $s['clarity_enabled'] ) ); ?> /> <span><?php esc_html_e( 'Enabled', 'consucorner-gtm' ); ?></span></label>
								<input id="cc-gtm-clarity" name="clarity_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['clarity_id'] ); ?>" />
							</td>
						</tr>
					</table>
				</section>

				<section class="cc-gtm-panel" id="cc-gtm-oauth">
					<h2><?php esc_html_e( 'Google OAuth (GTM API)', 'consucorner-gtm' ); ?></h2>
					<p class="cc-gtm-section-desc"><?php esc_html_e( 'Required for one-click setup. Add an OAuth client from Google Cloud Console with the Tag Manager API enabled.', 'consucorner-gtm' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cc-gtm-client-id"><?php esc_html_e( 'Client ID', 'consucorner-gtm' ); ?></label></th>
							<td><input id="cc-gtm-client-id" name="google_client_id" type="text" class="large-text" value="<?php echo esc_attr( $s['google_client_id'] ); ?>" autocomplete="off" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cc-gtm-client-secret"><?php esc_html_e( 'Client Secret', 'consucorner-gtm' ); ?></label></th>
							<td><input id="cc-gtm-client-secret" name="google_client_secret" type="password" class="large-text" value="" placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'consucorner-gtm' ); ?>" autocomplete="new-password" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Redirect URI', 'consucorner-gtm' ); ?></th>
							<td>
								<code><?php echo esc_html( CC_GTM_Google_Auth::redirect_uri() ); ?></code>
								<p class="description"><?php esc_html_e( 'Add this exact URI to your OAuth client\'s authorized redirect URIs.', 'consucorner-gtm' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<?php submit_button( __( 'Save Settings', 'consucorner-gtm' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * DataLayer health page.
	 */
	public static function render_health() {
		?>
		<div class="wrap cc-gtm-wrap">
			<h1><?php esc_html_e( 'DataLayer Health', 'consucorner-gtm' ); ?></h1>
			<p><?php esc_html_e( 'Expected events from this site:', 'consucorner-gtm' ); ?></p>
			<ul class="cc-gtm-list">
				<?php
				foreach ( array( 'page_context', 'view_item', 'view_item_list', 'select_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'purchase', 'search', 'filter_products' ) as $ev ) {
					echo '<li><code>' . esc_html( $ev ) . '</code></li>';
				}
				?>
			</ul>
			<h2><?php esc_html_e( 'Test flow', 'consucorner-gtm' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Open a product page', 'consucorner-gtm' ); ?></li>
				<li><?php esc_html_e( 'Add product to cart', 'consucorner-gtm' ); ?></li>
				<li><?php esc_html_e( 'Visit cart', 'consucorner-gtm' ); ?></li>
				<li><?php esc_html_e( 'Visit checkout', 'consucorner-gtm' ); ?></li>
				<li><?php esc_html_e( 'Place a test order (COD)', 'consucorner-gtm' ); ?></li>
				<li><?php esc_html_e( 'Reload thank-you — purchase must not repeat', 'consucorner-gtm' ); ?></li>
			</ol>
			<h2><?php esc_html_e( 'Browser console', 'consucorner-gtm' ); ?></h2>
			<pre class="cc-gtm-code">window.dataLayer
window.dataLayer.map(x => x.event)
window.dataLayer.filter(x => x.event === 'view_item')
window.dataLayer.filter(x => x.event === 'add_to_cart')
window.dataLayer.filter(x => x.event === 'purchase')</pre>
		</div>
		<?php
	}

	/**
	 * Google Auth page.
	 */
	public static function render_google_auth() {
		self::print_notices();
		$auth_url = CC_GTM_Google_Auth::get_auth_url();
		?>
		<div class="wrap cc-gtm-wrap">
			<h1><?php esc_html_e( 'Google Authentication', 'consucorner-gtm' ); ?></h1>
			<?php if ( ! CC_GTM_Google_Auth::is_configured() ) : ?>
				<p class="notice notice-warning"><?php esc_html_e( 'Google OAuth is not configured yet. Please enter Client ID and Client Secret in Settings, or use manual GTM template import.', 'consucorner-gtm' ); ?></p>
			<?php elseif ( CC_GTM_Google_Auth::is_connected() ) : ?>
				<p><strong><?php esc_html_e( 'Connected:', 'consucorner-gtm' ); ?></strong> <?php echo esc_html( CC_GTM_Google_Auth::get_connected_email() ?: __( '(email unknown)', 'consucorner-gtm' ) ); ?></p>
				<p><strong><?php esc_html_e( 'Token:', 'consucorner-gtm' ); ?></strong> <?php echo CC_GTM_Google_Auth::is_token_expired() ? esc_html__( 'Expired — will refresh on next API call', 'consucorner-gtm' ) : esc_html__( 'Valid', 'consucorner-gtm' ); ?></p>
				<form method="post" style="display:inline"><?php wp_nonce_field( 'cc_gtm_admin' ); ?><input type="hidden" name="cc_gtm_action" value="disconnect_google" /><?php submit_button( __( 'Disconnect', 'consucorner-gtm' ), 'delete' ); ?></form>
				<?php if ( ! is_wp_error( $auth_url ) ) : ?>
					<p><a class="button" href="<?php echo esc_url( $auth_url ); ?>"><?php esc_html_e( 'Reconnect Google Account', 'consucorner-gtm' ); ?></a></p>
				<?php endif; ?>
			<?php elseif ( ! is_wp_error( $auth_url ) ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( $auth_url ); ?>"><?php esc_html_e( 'Connect Google Account', 'consucorner-gtm' ); ?></a></p>
			<?php else : ?>
				<p class="notice notice-error"><?php echo esc_html( $auth_url->get_error_message() ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Auto setup wizard page.
	 */
	public static function render_auto_setup() {
		self::print_notices();
		$s       = CC_GTM_Settings::get_all();
		$setup   = get_transient( 'cc_gtm_setup_result' );
		$status  = self::get_setup_status();
		$accounts = array();
		if ( CC_GTM_Google_Auth::is_connected() ) {
			$accounts = CC_GTM_API::extract_list( CC_GTM_API::list_accounts(), 'account', 'accounts' );
		}
		$container_path = CC_GTM_API::get_container_parent_path();
		$mismatch = ! empty( $s['gtm_container_public_id'] ) && $s['gtm_container_public_id'] !== CC_GTM_Settings::get_gtm_container_id();

		if ( isset( $_GET['cc_gtm_download'] ) && 'checklist' === $_GET['cc_gtm_download'] && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename=consucorner-gtm-checklist.json' );
			echo CC_GTM_Auto_Setup::export_template_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$public_id = $s['gtm_container_public_id'] ?: CC_GTM_Settings::get_gtm_container_id();
		?>
		<div class="wrap cc-gtm-wrap cc-gtm-advanced">
			<header class="cc-gtm-header">
				<div>
					<h1><?php esc_html_e( 'GTM Auto Setup', 'consucorner-gtm' ); ?></h1>
					<p class="cc-gtm-subtitle"><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>">&larr; <?php esc_html_e( 'Back to dashboard', 'consucorner-gtm' ); ?></a></p>
				</div>
			</header>

			<?php self::render_wizard_steps( $status ); ?>

			<?php if ( ! CC_GTM_Google_Auth::is_connected() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Connect Google before running setup.', 'consucorner-gtm' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-google-auth' ) ); ?>"><?php esc_html_e( 'Google Auth', 'consucorner-gtm' ); ?></a></p></div>
			<?php endif; ?>

			<?php if ( $mismatch ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Warning: Installed GTM ID does not match selected container.', 'consucorner-gtm' ); ?></p></div>
			<?php endif; ?>

			<section class="cc-gtm-panel">
				<h2><?php esc_html_e( 'Select account & container', 'consucorner-gtm' ); ?></h2>
				<form method="post" class="cc-gtm-form-grid">
					<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
					<input type="hidden" name="cc_gtm_action" value="save_gtm_selection" />
					<p>
						<label for="cc-gtm-account"><?php esc_html_e( 'Account', 'consucorner-gtm' ); ?></label>
						<select name="gtm_account_id" id="cc-gtm-account" class="regular-text">
							<option value=""><?php esc_html_e( '— Select —', 'consucorner-gtm' ); ?></option>
							<?php foreach ( $accounts as $acc ) : ?>
								<?php $aid = str_replace( 'accounts/', '', $acc['path'] ?? '' ); ?>
								<option value="<?php echo esc_attr( $aid ); ?>" data-name="<?php echo esc_attr( $acc['name'] ?? '' ); ?>" <?php selected( $s['gtm_account_id'], $aid ); ?>><?php echo esc_html( $acc['name'] ?? $acc['path'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<input type="hidden" name="gtm_account_name" id="cc-gtm-account-name" value="<?php echo esc_attr( $s['gtm_account_name'] ); ?>" />
					<p>
						<label for="cc-gtm-container-select"><?php esc_html_e( 'Container', 'consucorner-gtm' ); ?></label>
						<select id="cc-gtm-container-select" class="regular-text" disabled>
							<option value=""><?php esc_html_e( 'Select an account first', 'consucorner-gtm' ); ?></option>
						</select>
					</p>
					<p>
						<label for="cc-gtm-container-api-id"><?php esc_html_e( 'Container API ID', 'consucorner-gtm' ); ?></label>
						<input name="gtm_container_api_id" id="cc-gtm-container-api-id" type="text" class="regular-text" value="<?php echo esc_attr( $s['gtm_container_api_id'] ); ?>" readonly />
					</p>
					<p>
						<label for="cc-gtm-container-public-id"><?php esc_html_e( 'Public Container ID', 'consucorner-gtm' ); ?></label>
						<input name="gtm_container_public_id" id="cc-gtm-container-public-id" type="text" class="regular-text" value="<?php echo esc_attr( $s['gtm_container_public_id'] ?: CC_GTM_Settings::get_gtm_container_id() ); ?>" placeholder="GTM-XXXXXXX" />
						<?php $usage_ctx = (string) ( $s['gtm_container_usage_context'] ?: 'web' ); ?>
						<input type="hidden" name="gtm_container_usage_context" id="cc-gtm-container-usage-context" value="<?php echo esc_attr( $usage_ctx ); ?>" />
						<span id="cc-gtm-container-usage-hint" class="cc-gtm-badge cc-gtm-badge--<?php echo 'server' === $usage_ctx ? 'server' : 'web'; ?>"><?php echo 'server' === $usage_ctx ? esc_html__( 'Server container', 'consucorner-gtm' ) : esc_html( ucfirst( $usage_ctx ) . ' container' ); ?></span>
					</p>
					<?php if ( 'server' === $usage_ctx ) : ?>
						<p class="cc-gtm-warn"><?php esc_html_e( 'This is a Server container. The injected storefront container must be a Web container — select your Web container here, and set the Server container URL under Settings → Server-Side GTM.', 'consucorner-gtm' ); ?></p>
					<?php endif; ?>
					<?php if ( $container_path ) : ?>
						<p class="description"><code><?php echo esc_html( $container_path ); ?></code></p>
					<?php endif; ?>
					<?php submit_button( __( 'Save Container Selection', 'consucorner-gtm' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<section class="cc-gtm-panel cc-gtm-panel--row">
				<div>
					<h2><?php esc_html_e( 'Workspace', 'consucorner-gtm' ); ?></h2>
					<?php if ( ! empty( $s['gtm_workspace_name'] ) ) : ?>
						<p><?php esc_html_e( 'Current:', 'consucorner-gtm' ); ?> <strong><?php echo esc_html( $s['gtm_workspace_name'] ); ?></strong> (<?php echo esc_html( $s['gtm_workspace_id'] ); ?>)</p>
					<?php endif; ?>
					<form method="post"><?php wp_nonce_field( 'cc_gtm_admin' ); ?><input type="hidden" name="cc_gtm_action" value="create_workspace" /><?php submit_button( __( 'Create workspace', 'consucorner-gtm' ), 'secondary', 'submit', false ); ?></form>
				</div>
				<div>
					<h2><?php esc_html_e( 'Run setup', 'consucorner-gtm' ); ?></h2>
					<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Create variables, triggers, and tags in the selected workspace?', 'consucorner-gtm' ) ); ?>');">
						<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
						<input type="hidden" name="cc_gtm_action" value="run_auto_setup" />
						<?php if ( CC_GTM_Settings::get( 'allow_auto_publish', false ) ) : ?>
							<p><label><input type="checkbox" name="allow_auto_publish" value="1" /> <?php esc_html_e( 'Attempt publish after setup', 'consucorner-gtm' ); ?></label></p>
							<p><label><input type="checkbox" name="confirm_publish" value="1" /> <?php esc_html_e( 'I understand this will publish live.', 'consucorner-gtm' ); ?></label></p>
						<?php endif; ?>
						<?php submit_button( __( 'Run GTM Auto Setup', 'consucorner-gtm' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			</section>

			<?php if ( is_array( $setup ) && ! empty( $setup['summary'] ) ) : ?>
				<section class="cc-gtm-panel">
					<h2><?php esc_html_e( 'Last setup result', 'consucorner-gtm' ); ?></h2>
					<pre class="cc-gtm-code"><?php echo esc_html( wp_json_encode( $setup['summary'], JSON_PRETTY_PRINT ) ); ?></pre>
				</section>
			<?php endif; ?>

			<section class="cc-gtm-panel">
				<h2><?php esc_html_e( 'Manual fallback', 'consucorner-gtm' ); ?></h2>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-auto-setup&cc_gtm_download=checklist' ) ); ?>"><?php esc_html_e( 'Download checklist JSON', 'consucorner-gtm' ); ?></a>
					<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://tagmanager.google.com/#/container/accounts/' . rawurlencode( (string) $s['gtm_account_id'] ) . '/containers/' . rawurlencode( (string) $s['gtm_container_api_id'] ) ); ?>"><?php esc_html_e( 'Open GTM Container', 'consucorner-gtm' ); ?></a>
					<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://tagmanager.google.com/#/container/' . rawurlencode( $public_id ) . '/preview' ); ?>"><?php esc_html_e( 'Open GTM Preview', 'consucorner-gtm' ); ?></a>
				</p>
			</section>
		</div>
		<?php
	}

	/**
	 * Logs page.
	 */
	public static function render_logs() {
		self::print_notices();
		$logs = CC_GTM_Logger::get_logs();
		?>
		<div class="wrap cc-gtm-wrap">
			<h1><?php esc_html_e( 'Logs', 'consucorner-gtm' ); ?></h1>
			<form method="post" style="margin-bottom:1em"><?php wp_nonce_field( 'cc_gtm_admin' ); ?><input type="hidden" name="cc_gtm_action" value="clear_logs" /><?php submit_button( __( 'Clear logs', 'consucorner-gtm' ), 'delete' ); ?></form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Time', 'consucorner-gtm' ); ?></th><th><?php esc_html_e( 'Action', 'consucorner-gtm' ); ?></th><th><?php esc_html_e( 'Message', 'consucorner-gtm' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['time'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $row['action'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $row['message'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Import / export page.
	 */
	public static function render_import_export() {
		self::print_notices();
		if ( isset( $_GET['cc_gtm_export'] ) && 'settings' === $_GET['cc_gtm_export'] && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename=consucorner-gtm-settings.json' );
			echo CC_GTM_Settings::export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
		?>
		<div class="wrap cc-gtm-wrap">
			<h1><?php esc_html_e( 'Import / Export', 'consucorner-gtm' ); ?></h1>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-gtm-import&cc_gtm_export=settings' ) ); ?>"><?php esc_html_e( 'Export settings JSON', 'consucorner-gtm' ); ?></a></p>
			<form method="post">
				<?php wp_nonce_field( 'cc_gtm_admin' ); ?>
				<input type="hidden" name="cc_gtm_action" value="import_settings" />
				<p><textarea name="import_json" rows="12" class="large-text code" placeholder="<?php esc_attr_e( 'Paste settings JSON', 'consucorner-gtm' ); ?>"></textarea></p>
				<?php submit_button( __( 'Import settings', 'consucorner-gtm' ) ); ?>
			</form>
		</div>
		<?php
	}
}
