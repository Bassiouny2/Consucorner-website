<?php
/**
 * Admin Font Awesome icon picker for meta boxes.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the page ID currently being edited in admin.
 *
 * @return int
 */
function cc_admin_icon_picker_get_edited_post_id() {
	global $post;

	if ( $post && ! empty( $post->ID ) ) {
		return (int) $post->ID;
	}

	if ( isset( $_GET['post'] ) ) {
		return absint( wp_unslash( $_GET['post'] ) );
	}

	return 0;
}

/**
 * Whether the current admin screen should load the icon picker.
 *
 * @return bool
 */
function cc_admin_icon_picker_screen_active() {
	if ( ! is_admin() ) {
		return false;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'page' !== $screen->post_type ) {
		return false;
	}

	$post_id = cc_admin_icon_picker_get_edited_post_id();
	if ( ! $post_id ) {
		return false;
	}

	return absint( get_option( 'page_on_front' ) ) === $post_id;
}

/**
 * Load Font Awesome icon catalog for the picker.
 *
 * @return array<int, array{class:string,label:string,terms:string}>
 */
function cc_get_fa_icon_catalog() {
	$path = get_template_directory() . '/assets/admin/fa-icon-catalog.json';
	if ( ! is_readable( $path ) ) {
		return array();
	}

	$raw = file_get_contents( $path );
	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}

	$icons = json_decode( $raw, true );
	return is_array( $icons ) ? $icons : array();
}

/**
 * Enqueue icon picker assets on the homepage editor.
 *
 * @param string $hook Admin page hook.
 */
function cc_admin_icon_picker_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	if ( ! cc_admin_icon_picker_screen_active() ) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$theme_dir = get_template_directory();

	if ( function_exists( 'consucorner_enqueue_theme_icons' ) ) {
		consucorner_enqueue_theme_icons();
	} else {
		$dokan_css = WP_PLUGIN_DIR . '/dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css';
		if ( file_exists( $dokan_css ) ) {
			wp_enqueue_style(
				'consucorner-fontawesome',
				plugins_url( 'dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css' ),
				array(),
				'6.5.1'
			);
		}
	}

	wp_enqueue_style(
		'cc-icon-picker',
		$theme_uri . '/assets/admin/css/cc-icon-picker.css',
		array(),
		file_exists( $theme_dir . '/assets/admin/css/cc-icon-picker.css' )
			? (string) filemtime( $theme_dir . '/assets/admin/css/cc-icon-picker.css' )
			: _S_VERSION
	);

	wp_enqueue_script(
		'cc-icon-picker',
		$theme_uri . '/assets/admin/js/cc-icon-picker.js',
		array(),
		file_exists( $theme_dir . '/assets/admin/js/cc-icon-picker.js' )
			? (string) filemtime( $theme_dir . '/assets/admin/js/cc-icon-picker.js' )
			: _S_VERSION,
		true
	);

	wp_localize_script(
		'cc-icon-picker',
		'ccIconPicker',
		array(
			'icons'   => cc_get_fa_icon_catalog(),
			'strings' => array(
				'title'       => __( 'Choose an icon', 'consucorner' ),
				'search'      => __( 'Search icons…', 'consucorner' ),
				'noResults'   => __( 'No icons match your search.', 'consucorner' ),
				'choose'      => __( 'Choose icon', 'consucorner' ),
				'clear'       => __( 'Clear', 'consucorner' ),
				'close'       => __( 'Close', 'consucorner' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cc_admin_icon_picker_enqueue' );

/**
 * Render icon picker modal shell once per screen.
 */
function cc_admin_icon_picker_render_modal() {
	if ( ! cc_admin_icon_picker_screen_active() ) {
		return;
	}
	?>
	<div id="cc-fa-icon-picker" class="cc-fa-icon-picker" hidden aria-hidden="true">
		<div class="cc-fa-icon-picker__backdrop" data-cc-icon-close></div>
		<div class="cc-fa-icon-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="cc-fa-icon-picker-title">
			<div class="cc-fa-icon-picker__header">
				<h2 id="cc-fa-icon-picker-title" class="cc-fa-icon-picker__title"><?php esc_html_e( 'Choose an icon', 'consucorner' ); ?></h2>
				<button type="button" class="cc-fa-icon-picker__close" data-cc-icon-close aria-label="<?php esc_attr_e( 'Close', 'consucorner' ); ?>">&times;</button>
			</div>
			<div class="cc-fa-icon-picker__search-wrap">
				<input type="search" class="cc-fa-icon-picker__search" placeholder="<?php esc_attr_e( 'Search icons…', 'consucorner' ); ?>" autocomplete="off" />
			</div>
			<div class="cc-fa-icon-picker__grid" role="listbox" aria-label="<?php esc_attr_e( 'Icon list', 'consucorner' ); ?>"></div>
			<p class="cc-fa-icon-picker__empty" hidden><?php esc_html_e( 'No icons match your search.', 'consucorner' ); ?></p>
		</div>
	</div>
	<?php
}
add_action( 'admin_footer', 'cc_admin_icon_picker_render_modal' );

/**
 * Print a Font Awesome icon field with preview + picker button.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $label   Field label.
 * @param string $default Default icon class.
 */
function cc_meta_fa_icon_picker( $post_id, $key, $label, $default = '' ) {
	$value = get_post_meta( $post_id, $key, true );
	if ( '' === $value || false === $value ) {
		$value = $default;
	}

	$normalized = function_exists( 'consucorner_normalize_fa_icon_class' )
		? consucorner_normalize_fa_icon_class( $value )
		: $value;
	$has_icon   = '' !== trim( (string) $normalized );
	?>
	<p class="cc-fa-icon-field" style="margin:6px 0" data-cc-icon-field>
		<label for="<?php echo esc_attr( $key ); ?>" style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">
			<?php echo esc_html( $label ); ?>
		</label>
		<span class="cc-fa-icon-field__row">
			<span class="cc-fa-icon-preview<?php echo $has_icon ? '' : ' is-empty'; ?>" data-cc-icon-preview aria-hidden="true">
				<?php if ( $has_icon ) : ?>
					<i class="<?php echo esc_attr( $normalized ); ?>" aria-hidden="true"></i>
				<?php endif; ?>
			</span>
			<input
				type="text"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				class="cc-fa-icon-input"
				value="<?php echo esc_attr( $value ); ?>"
				data-cc-icon-input
				placeholder="fa-solid fa-cart-shopping"
				style="flex:1;min-width:180px;font-size:13px"
			/>
			<button type="button" class="button cc-fa-icon-choose" data-cc-icon-choose>
				<?php esc_html_e( 'Choose icon', 'consucorner' ); ?>
			</button>
			<button type="button" class="button-link cc-fa-icon-clear" data-cc-icon-clear<?php echo $has_icon ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'Clear', 'consucorner' ); ?>
			</button>
		</span>
	</p>
	<?php
}
