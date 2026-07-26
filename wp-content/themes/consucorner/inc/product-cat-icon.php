<?php
/**
 * Product Category Icon
 *
 * Adds an icon upload field to WooCommerce product categories (product_cat).
 * Stored as term meta: _cc_product_cat_icon (attachment ID).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_PRODUCT_CAT_ICON_META_KEY = '_cc_product_cat_icon';

add_action( 'product_cat_add_form_fields', 'cc_product_cat_add_icon_field' );
add_action( 'product_cat_edit_form_fields', 'cc_product_cat_edit_icon_field', 10, 2 );
add_action( 'created_product_cat', 'cc_save_product_cat_icon' );
add_action( 'edited_product_cat', 'cc_save_product_cat_icon' );
add_action( 'admin_enqueue_scripts', 'cc_product_cat_icon_enqueue_media' );

/**
 * Render icon field on "Add New Category" form.
 *
 * @param string $taxonomy Taxonomy slug.
 */
function cc_product_cat_add_icon_field( $taxonomy ) {
	?>
	<div class="form-field cc-product-cat-icon-field">
		<label for="cc-product-cat-icon-id"><?php esc_html_e( 'Category Icon', 'consucorner' ); ?></label>

		<div class="cc-attr-img-wrap" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
			<img
				id="cc-product-cat-icon-preview-new"
				src=""
				alt=""
				style="display:none;width:64px;height:64px;object-fit:contain;border-radius:6px;border:1px solid #ddd;background:#fff;padding:4px;"
			/>
			<input type="hidden" id="cc-product-cat-icon-id" name="cc_product_cat_icon_id" value="" />
			<button
				type="button"
				class="button cc-attr-upload-btn"
				data-preview="cc-product-cat-icon-preview-new"
				data-input="cc-product-cat-icon-id"
			><?php esc_html_e( 'Upload / Select Icon', 'consucorner' ); ?></button>
			<button
				type="button"
				class="button cc-attr-remove-btn"
				data-preview="cc-product-cat-icon-preview-new"
				data-input="cc-product-cat-icon-id"
				style="display:none"
			><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
		</div>

		<p class="description"><?php esc_html_e( 'Optional icon for this category. Can be reused across menus, filters, and other theme sections.', 'consucorner' ); ?></p>
	</div>
	<?php
}

/**
 * Render icon field on "Edit Category" form.
 *
 * @param WP_Term $term     Term object.
 * @param string  $taxonomy Taxonomy slug.
 */
function cc_product_cat_edit_icon_field( $term, $taxonomy ) {
	$icon_id  = cc_get_product_cat_icon_id( $term );
	$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
	$field_id = 'cc-product-cat-icon-id-' . (int) $term->term_id;
	$prev_id  = 'cc-product-cat-icon-preview-' . (int) $term->term_id;
	$rm_id    = 'cc-product-cat-icon-remove-' . (int) $term->term_id;
	?>
	<tr class="form-field cc-product-cat-icon-row">
		<th scope="row">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Category Icon', 'consucorner' ); ?></label>
		</th>
		<td>
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
				<img
					id="<?php echo esc_attr( $prev_id ); ?>"
					src="<?php echo esc_url( $icon_url ); ?>"
					alt=""
					style="<?php echo esc_attr( $icon_url ? '' : 'display:none;' ); ?>width:64px;height:64px;object-fit:contain;border-radius:6px;border:1px solid #ddd;background:#fff;padding:4px;"
				/>
				<input
					type="hidden"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="cc_product_cat_icon_id"
					value="<?php echo esc_attr( $icon_id ? $icon_id : '' ); ?>"
				/>
				<button
					type="button"
					class="button cc-attr-upload-btn"
					data-preview="<?php echo esc_attr( $prev_id ); ?>"
					data-input="<?php echo esc_attr( $field_id ); ?>"
					data-remove="<?php echo esc_attr( $rm_id ); ?>"
				><?php esc_html_e( 'Upload / Select Icon', 'consucorner' ); ?></button>
				<button
					type="button"
					id="<?php echo esc_attr( $rm_id ); ?>"
					class="button cc-attr-remove-btn"
					data-preview="<?php echo esc_attr( $prev_id ); ?>"
					data-input="<?php echo esc_attr( $field_id ); ?>"
					style="<?php echo esc_attr( $icon_url ? '' : 'display:none;' ); ?>"
				><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Optional icon for this category. Can be reused across menus, filters, and other theme sections.', 'consucorner' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save product category icon term meta.
 *
 * @param int $term_id Term ID.
 */
function cc_save_product_cat_icon( $term_id ) {
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

	if ( ! isset( $_POST['cc_product_cat_icon_id'] ) ) {
		return;
	}

	$icon_id = absint( $_POST['cc_product_cat_icon_id'] );

	if ( $icon_id ) {
		update_term_meta( $term_id, CC_PRODUCT_CAT_ICON_META_KEY, $icon_id );
	} else {
		delete_term_meta( $term_id, CC_PRODUCT_CAT_ICON_META_KEY );
	}
}

/**
 * Enqueue media uploader assets on product category admin screens.
 *
 * @param string $hook Current admin page hook.
 */
function cc_product_cat_icon_enqueue_media( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'cc-product-cat-icon',
		get_template_directory_uri() . '/assets/js/admin-attribute-images.js',
		array( 'jquery' ),
		defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0',
		true
	);
}

/**
 * Return the icon attachment ID for a product category term.
 *
 * @param int|WP_Term $term Term ID or object.
 * @return int
 */
function cc_get_product_cat_icon_id( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term, 'product_cat' );
	}

	if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
		return 0;
	}

	return absint( get_term_meta( $term->term_id, CC_PRODUCT_CAT_ICON_META_KEY, true ) );
}

/**
 * Return the icon URL for a product category term.
 *
 * @param int|WP_Term $term Term ID or object.
 * @param string      $size Image size.
 * @return string
 */
function cc_get_product_cat_icon_url( $term, $size = 'thumbnail' ) {
	$icon_id = cc_get_product_cat_icon_id( $term );
	if ( ! $icon_id ) {
		return '';
	}

	$icon_url = wp_get_attachment_image_url( $icon_id, $size );
	return $icon_url ? $icon_url : '';
}

/**
 * Return icon data for a product category term.
 *
 * @param int|WP_Term $term Term ID or object.
 * @param string      $size Image size.
 * @return array{id:int,url:string}
 */
function cc_get_product_cat_icon_info( $term, $size = 'thumbnail' ) {
	$icon_id = cc_get_product_cat_icon_id( $term );

	return array(
		'id'  => $icon_id,
		'url' => $icon_id ? cc_get_product_cat_icon_url( $term, $size ) : '',
	);
}
