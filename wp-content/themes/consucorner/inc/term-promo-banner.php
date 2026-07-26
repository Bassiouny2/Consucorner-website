<?php
/**
 * Per-term archive promo banner (product_cat + specialty).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_TERM_PROMO_BG_META_KEY         = '_cc_term_promo_bg_id';
const CC_TERM_PROMO_TITLE_META_KEY      = '_cc_term_promo_title';
const CC_TERM_PROMO_TEXT_META_KEY       = '_cc_term_promo_text';
const CC_TERM_PROMO_BUTTON_META_KEY     = '_cc_term_promo_button_text';
const CC_TERM_PROMO_BUTTON_URL_META_KEY = '_cc_term_promo_button_url';
const CC_TERM_PROMO_DESIGN_META_KEY    = '_cc_term_promo_design';
const CC_TERM_PROMO_COUNTRIES_META_KEY = '_cc_term_promo_countries';

/**
 * Taxonomies that support per-term promo banners.
 *
 * @return string[]
 */
function consucorner_term_promo_taxonomies() {
	return array( 'product_cat', 'specialty' );
}

/**
 * Register term form hooks for supported taxonomies.
 */
function consucorner_term_promo_register_hooks() {
	foreach ( consucorner_term_promo_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_add_form_fields", 'consucorner_term_promo_add_form_fields' );
		add_action( "{$taxonomy}_edit_form_fields", 'consucorner_term_promo_edit_form_fields', 10, 2 );
		add_action( "created_{$taxonomy}", 'consucorner_term_promo_save_fields' );
		add_action( "edited_{$taxonomy}", 'consucorner_term_promo_save_fields' );
	}
}
add_action( 'init', 'consucorner_term_promo_register_hooks', 30 );

/**
 * Allowed banner design values.
 *
 * @return string[]
 */
function consucorner_term_promo_allowed_designs() {
	return array( 'normal', 'offer' );
}

/**
 * Normalize a stored design value.
 *
 * @param mixed $design Raw design value.
 * @return string 'normal'|'offer'
 */
function consucorner_term_promo_normalize_design( $design ) {
	$design = sanitize_key( (string) $design );
	return in_array( $design, consucorner_term_promo_allowed_designs(), true ) ? $design : 'normal';
}

/**
 * Read stored promo field values for a term.
 *
 * @param int $term_id Term ID.
 * @return array<string, mixed>
 */
function consucorner_term_promo_get_stored_fields( $term_id ) {
	$countries_raw = get_term_meta( $term_id, CC_TERM_PROMO_COUNTRIES_META_KEY, true );
	$countries     = array();
	if ( is_array( $countries_raw ) ) {
		$countries = array_values( array_unique( array_filter( array_map( 'absint', $countries_raw ) ) ) );
	} elseif ( is_string( $countries_raw ) && '' !== $countries_raw ) {
		$decoded = json_decode( $countries_raw, true );
		if ( is_array( $decoded ) ) {
			$countries = array_values( array_unique( array_filter( array_map( 'absint', $decoded ) ) ) );
		}
	}

	return array(
		'bg_id'       => absint( get_term_meta( $term_id, CC_TERM_PROMO_BG_META_KEY, true ) ),
		'title'       => (string) get_term_meta( $term_id, CC_TERM_PROMO_TITLE_META_KEY, true ),
		'text'        => (string) get_term_meta( $term_id, CC_TERM_PROMO_TEXT_META_KEY, true ),
		'button_text' => (string) get_term_meta( $term_id, CC_TERM_PROMO_BUTTON_META_KEY, true ),
		'button_url'  => (string) get_term_meta( $term_id, CC_TERM_PROMO_BUTTON_URL_META_KEY, true ),
		'design'      => consucorner_term_promo_normalize_design( get_term_meta( $term_id, CC_TERM_PROMO_DESIGN_META_KEY, true ) ),
		'countries'   => $countries,
	);
}

/**
 * Country of origin terms available for the manual banner picker.
 *
 * @return WP_Term[]
 */
function consucorner_term_promo_get_country_terms() {
	$taxonomy = function_exists( 'consucorner_country_origin_taxonomy' )
		? consucorner_country_origin_taxonomy()
		: 'country_of_origin';

	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	return ( ! is_wp_error( $terms ) && is_array( $terms ) ) ? $terms : array();
}

/**
 * Build an origins payload from manually selected country term IDs.
 *
 * Shape matches cc_get_archive_country_origins() so the existing promo
 * origins renderer can be reused without changes.
 *
 * @param int[]        $country_ids  Selected country_of_origin term IDs.
 * @param WP_Term|null $archive_term Current archive term for filter links.
 * @return array{total:int,visible:array<int,array<string,mixed>>,all:array<int,array<string,mixed>>,mode:string,hidden_count:int}
 */
function consucorner_term_promo_build_manual_origins( array $country_ids, $archive_term = null ) {
	$out = array(
		'total'        => 0,
		'visible'      => array(),
		'all'          => array(),
		'mode'         => 'none',
		'hidden_count' => 0,
	);

	$taxonomy = function_exists( 'consucorner_country_origin_taxonomy' )
		? consucorner_country_origin_taxonomy()
		: 'country_of_origin';

	$country_ids = array_values( array_unique( array_filter( array_map( 'absint', $country_ids ) ) ) );
	if ( empty( $country_ids ) || ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return $out;
	}

	$items = array();
	foreach ( $country_ids as $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			continue;
		}

		$image_url = function_exists( 'cc_get_country_origin_term_image_url' )
			? cc_get_country_origin_term_image_url( $term, 'thumbnail' )
			: '';

		$filter_url = function_exists( 'cc_build_archive_filter_url' )
			? cc_build_archive_filter_url( $archive_term, $taxonomy, (string) $term->slug )
			: '';

		$items[] = array(
			'term_id'       => (int) $term->term_id,
			'name'          => (string) $term->name,
			'slug'          => (string) $term->slug,
			'product_count' => 0,
			'image_url'     => $image_url,
			'filter_url'    => $filter_url,
		);
	}

	if ( empty( $items ) ) {
		return $out;
	}

	$total = count( $items );
	$mode  = 'single';
	if ( $total > 4 ) {
		$mode = 'overflow';
	} elseif ( $total > 1 ) {
		$mode = 'cluster';
	}

	$visible = 'overflow' === $mode ? array_slice( $items, 0, 3 ) : $items;

	$out['total']        = $total;
	$out['visible']      = $visible;
	$out['all']          = $items;
	$out['mode']         = $mode;
	$out['hidden_count'] = max( 0, $total - count( $visible ) );

	return $out;
}

/**
 * Render the design select control.
 *
 * @param string $field_id   Input element ID.
 * @param string $input_name Form input name.
 * @param string $selected   Current design value.
 */
function consucorner_term_promo_render_design_field( $field_id, $input_name, $selected = 'normal' ) {
	$selected = consucorner_term_promo_normalize_design( $selected );
	?>
	<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>">
		<option value="normal" <?php selected( $selected, 'normal' ); ?>><?php esc_html_e( 'Normal', 'consucorner' ); ?></option>
		<option value="offer" <?php selected( $selected, 'offer' ); ?>><?php esc_html_e( 'Offer', 'consucorner' ); ?></option>
	</select>
	<p class="description"><?php esc_html_e( 'Offer design adds a sale badge and accent styling on the archive banner.', 'consucorner' ); ?></p>
	<?php
}

/**
 * Render the countries multi-select control.
 *
 * @param string $field_id   Select element ID.
 * @param string $input_name Form input name.
 * @param int[]  $selected   Selected country term IDs.
 */
function consucorner_term_promo_render_countries_field( $field_id, $input_name, array $selected = array() ) {
	$terms    = consucorner_term_promo_get_country_terms();
	$selected = array_values( array_unique( array_filter( array_map( 'absint', $selected ) ) ) );
	?>
	<select
		id="<?php echo esc_attr( $field_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>[]"
		multiple="multiple"
		style="width:100%;max-width:480px;min-height:120px"
	>
		<?php foreach ( $terms as $term ) : ?>
			<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $selected, true ) ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php
		if ( empty( $terms ) ) {
			esc_html_e( 'No countries of origin found yet. Create terms under Products → Countries of Origin first.', 'consucorner' );
		} else {
			esc_html_e( 'Hold Ctrl/Cmd to select multiple. When set, these replace the auto-detected countries from products in this archive. Leave empty to keep automatic detection.', 'consucorner' );
		}
		?>
	</p>
	<?php
}

/**
 * Render image upload control markup.
 *
 * @param string $input_id   Hidden input element ID.
 * @param string $preview_id Preview image element ID.
 * @param string $remove_id  Remove button element ID.
 * @param int    $image_id   Current attachment ID.
 * @param string $input_name Form input name.
 * @param string $preview_style Optional extra preview styles.
 */
function consucorner_term_promo_render_image_field( $input_id, $preview_id, $remove_id, $image_id, $input_name, $preview_style = '' ) {
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	$style     = $preview_style ? $preview_style : 'width:120px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #ddd;';
	?>
	<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
		<img
			id="<?php echo esc_attr( $preview_id ); ?>"
			src="<?php echo esc_url( $image_url ); ?>"
			alt=""
			style="<?php echo esc_attr( $image_url ? '' : 'display:none;' ); ?><?php echo esc_attr( $style ); ?>"
		/>
		<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $image_id ? (string) $image_id : '' ); ?>" />
		<button
			type="button"
			class="button cc-attr-upload-btn"
			data-preview="<?php echo esc_attr( $preview_id ); ?>"
			data-input="<?php echo esc_attr( $input_id ); ?>"
			<?php if ( $remove_id ) : ?>
				data-remove="<?php echo esc_attr( $remove_id ); ?>"
			<?php endif; ?>
		><?php esc_html_e( 'Upload / Select Image', 'consucorner' ); ?></button>
		<button
			type="button"
			id="<?php echo esc_attr( $remove_id ); ?>"
			class="button cc-attr-remove-btn"
			data-preview="<?php echo esc_attr( $preview_id ); ?>"
			data-input="<?php echo esc_attr( $input_id ); ?>"
			style="<?php echo esc_attr( $image_url ? '' : 'display:none;' ); ?>"
		><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
	</div>
	<?php
}

/**
 * Render promo fields on "Add New Term" form.
 *
 * @param string $taxonomy Taxonomy slug.
 */
function consucorner_term_promo_add_form_fields( $taxonomy ) {
	if ( ! in_array( $taxonomy, consucorner_term_promo_taxonomies(), true ) ) {
		return;
	}
	?>
	<div class="form-field cc-term-promo-wrap">
		<h2><?php esc_html_e( 'Archive promo banner', 'consucorner' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Optional static banner shown on this term’s shop archive page. Prefer a manual country list below when you want specific flags; otherwise countries are detected automatically from products. Recommended background image: 1208 × 390 px.', 'consucorner' ); ?></p>
	</div>

	<div class="form-field">
		<label for="cc-term-promo-design-new"><?php esc_html_e( 'Banner design', 'consucorner' ); ?></label>
		<?php consucorner_term_promo_render_design_field( 'cc-term-promo-design-new', 'cc_term_promo_design', 'normal' ); ?>
	</div>

	<div class="form-field">
		<label><?php esc_html_e( 'Background image', 'consucorner' ); ?></label>
		<?php consucorner_term_promo_render_image_field( 'cc-term-promo-bg-new', 'cc-term-promo-bg-preview-new', 'cc-term-promo-bg-remove-new', 0, 'cc_term_promo_bg_id' ); ?>
		<p class="description"><?php esc_html_e( 'Recommended upload: 1208 × 390 px JPG/WebP. Keep important artwork away from the copy area.', 'consucorner' ); ?></p>
	</div>

	<div class="form-field">
		<label for="cc-term-promo-title-new"><?php esc_html_e( 'Heading (H2)', 'consucorner' ); ?></label>
		<input type="text" id="cc-term-promo-title-new" name="cc_term_promo_title" value="" />
	</div>

	<div class="form-field">
		<label for="cc-term-promo-text-new"><?php esc_html_e( 'Paragraph text', 'consucorner' ); ?></label>
		<textarea id="cc-term-promo-text-new" name="cc_term_promo_text" rows="3"></textarea>
	</div>

	<div class="form-field">
		<label for="cc-term-promo-button-new"><?php esc_html_e( 'Button label', 'consucorner' ); ?></label>
		<input type="text" id="cc-term-promo-button-new" name="cc_term_promo_button_text" value="" />
	</div>

	<div class="form-field">
		<label for="cc-term-promo-button-url-new"><?php esc_html_e( 'Button link', 'consucorner' ); ?></label>
		<input type="url" id="cc-term-promo-button-url-new" name="cc_term_promo_button_url" value="" class="regular-text" placeholder="https://" />
		<p class="description"><?php esc_html_e( 'Leave empty to use this term’s archive URL.', 'consucorner' ); ?></p>
	</div>

	<div class="form-field">
		<label for="cc-term-promo-countries-new"><?php esc_html_e( 'Countries (manual)', 'consucorner' ); ?></label>
		<?php consucorner_term_promo_render_countries_field( 'cc-term-promo-countries-new', 'cc_term_promo_countries', array() ); ?>
	</div>

	<?php
}

/**
 * Render promo fields on "Edit Term" form.
 *
 * @param WP_Term $term     Term object.
 * @param string  $taxonomy Taxonomy slug.
 */
function consucorner_term_promo_edit_form_fields( $term, $taxonomy ) {
	if ( ! in_array( $taxonomy, consucorner_term_promo_taxonomies(), true ) ) {
		return;
	}

	$fields  = consucorner_term_promo_get_stored_fields( $term->term_id );
	$term_id = (int) $term->term_id;
	?>
	<tr class="form-field cc-term-promo-wrap">
		<th scope="row" colspan="2">
			<h2><?php esc_html_e( 'Archive promo banner', 'consucorner' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Optional static banner shown on this term’s shop archive page. Prefer a manual country list below when you want specific flags; otherwise countries are detected automatically from products.', 'consucorner' ); ?></p>
		</th>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-design-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Banner design', 'consucorner' ); ?></label></th>
		<td>
			<?php
			consucorner_term_promo_render_design_field(
				'cc-term-promo-design-' . $term_id,
				'cc_term_promo_design',
				$fields['design']
			);
			?>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label><?php esc_html_e( 'Background image', 'consucorner' ); ?></label></th>
		<td>
			<?php
			consucorner_term_promo_render_image_field(
				'cc-term-promo-bg-' . $term_id,
				'cc-term-promo-bg-preview-' . $term_id,
				'cc-term-promo-bg-remove-' . $term_id,
				$fields['bg_id'],
				'cc_term_promo_bg_id'
			);
			?>
			<p class="description"><?php esc_html_e( 'Recommended upload: 1208 × 390 px JPG/WebP. Keep important artwork away from the copy area.', 'consucorner' ); ?></p>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-title-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Heading (H2)', 'consucorner' ); ?></label></th>
		<td>
			<input type="text" id="cc-term-promo-title-<?php echo esc_attr( (string) $term_id ); ?>" name="cc_term_promo_title" value="<?php echo esc_attr( $fields['title'] ); ?>" class="regular-text" />
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-text-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Paragraph text', 'consucorner' ); ?></label></th>
		<td>
			<textarea id="cc-term-promo-text-<?php echo esc_attr( (string) $term_id ); ?>" name="cc_term_promo_text" rows="3" class="large-text"><?php echo esc_textarea( $fields['text'] ); ?></textarea>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-button-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Button label', 'consucorner' ); ?></label></th>
		<td>
			<input type="text" id="cc-term-promo-button-<?php echo esc_attr( (string) $term_id ); ?>" name="cc_term_promo_button_text" value="<?php echo esc_attr( $fields['button_text'] ); ?>" class="regular-text" />
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-button-url-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Button link', 'consucorner' ); ?></label></th>
		<td>
			<input type="url" id="cc-term-promo-button-url-<?php echo esc_attr( (string) $term_id ); ?>" name="cc_term_promo_button_url" value="<?php echo esc_attr( $fields['button_url'] ); ?>" class="regular-text" placeholder="https://" />
			<p class="description"><?php esc_html_e( 'Leave empty to use this term’s archive URL.', 'consucorner' ); ?></p>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row"><label for="cc-term-promo-countries-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Countries (manual)', 'consucorner' ); ?></label></th>
		<td>
			<?php
			consucorner_term_promo_render_countries_field(
				'cc-term-promo-countries-' . $term_id,
				'cc_term_promo_countries',
				isset( $fields['countries'] ) && is_array( $fields['countries'] ) ? $fields['countries'] : array()
			);
			?>
		</td>
	</tr>

	<?php
}

/**
 * Save term promo fields.
 *
 * @param int $term_id Term ID.
 */
function consucorner_term_promo_save_fields( $term_id ) {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! $nonce
		|| ( ! wp_verify_nonce( $nonce, 'add-tag' )
			&& ! wp_verify_nonce( $nonce, 'update-tag_' . $term_id ) )
	) {
		return;
	}

	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}

	$bg_id = isset( $_POST['cc_term_promo_bg_id'] ) ? absint( $_POST['cc_term_promo_bg_id'] ) : 0;
	if ( $bg_id ) {
		update_term_meta( $term_id, CC_TERM_PROMO_BG_META_KEY, $bg_id );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_BG_META_KEY );
	}

	$title = isset( $_POST['cc_term_promo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_term_promo_title'] ) ) : '';
	if ( '' !== $title ) {
		update_term_meta( $term_id, CC_TERM_PROMO_TITLE_META_KEY, $title );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_TITLE_META_KEY );
	}

	$text = isset( $_POST['cc_term_promo_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cc_term_promo_text'] ) ) : '';
	if ( '' !== $text ) {
		update_term_meta( $term_id, CC_TERM_PROMO_TEXT_META_KEY, $text );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_TEXT_META_KEY );
	}

	$button_text = isset( $_POST['cc_term_promo_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_term_promo_button_text'] ) ) : '';
	if ( '' !== $button_text ) {
		update_term_meta( $term_id, CC_TERM_PROMO_BUTTON_META_KEY, $button_text );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_BUTTON_META_KEY );
	}

	$button_url = isset( $_POST['cc_term_promo_button_url'] ) ? esc_url_raw( wp_unslash( $_POST['cc_term_promo_button_url'] ) ) : '';
	if ( '' !== $button_url ) {
		update_term_meta( $term_id, CC_TERM_PROMO_BUTTON_URL_META_KEY, $button_url );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_BUTTON_URL_META_KEY );
	}

	$design = isset( $_POST['cc_term_promo_design'] )
		? consucorner_term_promo_normalize_design( wp_unslash( $_POST['cc_term_promo_design'] ) )
		: 'normal';
	if ( 'normal' !== $design ) {
		update_term_meta( $term_id, CC_TERM_PROMO_DESIGN_META_KEY, $design );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_DESIGN_META_KEY );
	}

	$countries = array();
	if ( isset( $_POST['cc_term_promo_countries'] ) && is_array( $_POST['cc_term_promo_countries'] ) ) {
		$taxonomy = function_exists( 'consucorner_country_origin_taxonomy' )
			? consucorner_country_origin_taxonomy()
			: 'country_of_origin';
		foreach ( wp_unslash( $_POST['cc_term_promo_countries'] ) as $raw_id ) {
			$cid = absint( $raw_id );
			if ( ! $cid ) {
				continue;
			}
			$term = get_term( $cid, $taxonomy );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$countries[] = $cid;
			}
		}
		$countries = array_values( array_unique( $countries ) );
	}
	if ( ! empty( $countries ) ) {
		update_term_meta( $term_id, CC_TERM_PROMO_COUNTRIES_META_KEY, $countries );
	} else {
		delete_term_meta( $term_id, CC_TERM_PROMO_COUNTRIES_META_KEY );
	}

}

/**
 * Enqueue media uploader on term admin screens.
 *
 * @param string $hook Admin page hook.
 */
function consucorner_term_promo_enqueue_media( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->taxonomy, consucorner_term_promo_taxonomies(), true ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'cc-term-promo-admin',
		get_template_directory_uri() . '/assets/js/admin-attribute-images.js',
		array( 'jquery' ),
		defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'consucorner_term_promo_enqueue_media' );

/**
 * Whether stored term promo fields are entirely empty.
 *
 * @param array<string, mixed> $fields Stored field values.
 * @return bool
 */
function consucorner_term_promo_fields_are_empty( array $fields ) {
	$text_blob = trim(
		(string) ( $fields['title'] ?? '' )
		. (string) ( $fields['text'] ?? '' )
		. (string) ( $fields['button_text'] ?? '' )
	);

	return '' === $text_blob
		&& empty( $fields['bg_id'] )
		&& '' === (string) ( $fields['button_url'] ?? '' );
}

/**
 * Build a single promo slide array for a term archive banner.
 *
 * @param WP_Term $term       Term object.
 * @param string  $shop_url   Shop page URL (unused fallback; term link preferred).
 * @param string  $banner_url   Default background image URL.
 * @param string  $theme_uri    Theme URI.
 * @return array{title:string,subtitle:string,button:string,button_url:string,image:string,design:string,countries:int[]}|null
 */
function consucorner_get_term_promo_banner( $term, $shop_url, $banner_url, $theme_uri ) {
	if ( ! $term instanceof WP_Term ) {
		return null;
	}

	$fields = consucorner_term_promo_get_stored_fields( $term->term_id );
	if ( consucorner_term_promo_fields_are_empty( $fields ) ) {
		return null;
	}

	$term_link = get_term_link( $term );
	if ( is_wp_error( $term_link ) ) {
		$term_link = (string) $shop_url;
	}

	$bg = '';
	if ( ! empty( $fields['bg_id'] ) ) {
		$bg_url = wp_get_attachment_image_url( (int) $fields['bg_id'], 'cc_shop_promo_banner' );
		if ( ! $bg_url ) {
			$bg_url = wp_get_attachment_image_url( (int) $fields['bg_id'], 'full' );
		}
		$bg     = $bg_url ? $bg_url : '';
	}
	if ( '' === $bg ) {
		$bg = (string) $banner_url;
	}

	$button_url = '' !== $fields['button_url'] ? $fields['button_url'] : (string) $term_link;

	return array(
		'title'      => $fields['title'],
		'subtitle'   => $fields['text'],
		'button'     => $fields['button_text'],
		'button_url' => $button_url,
		'image'      => $bg,
		'design'     => consucorner_term_promo_normalize_design( $fields['design'] ?? 'normal' ),
		'countries'  => isset( $fields['countries'] ) && is_array( $fields['countries'] ) ? $fields['countries'] : array(),
	);
}
