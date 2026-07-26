<?php
/**
 * Attribute Term Images
 *
 * Adds an image upload field to every WooCommerce attribute term and the
 * custom Country of Origin taxonomy.
 *
 * The image is stored as term meta: _cc_attribute_image (attachment ID).
 * It is used by the single-product template as the flag/icon for
 * "Country of Origin" (and any other attribute displayed as an image).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/* =====================================================================
   1.  Register hooks for supported product taxonomies
   ===================================================================== */

add_action( 'init', 'cc_register_attribute_image_hooks', 25 );

function cc_register_attribute_image_hooks() {
	$supported_taxonomies = array();

	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				$supported_taxonomies[] = wc_attribute_taxonomy_name( $tax->attribute_name );
			}
		}
	}

	$supported_taxonomies[] = 'country_of_origin';
	$supported_taxonomies = array_values( array_unique( array_filter( $supported_taxonomies ) ) );

	foreach ( $supported_taxonomies as $taxonomy ) {
		// Form fields — "Add new term" form.
		add_action( "{$taxonomy}_add_form_fields",  'cc_attr_add_image_field' );

		// Form fields — "Edit term" form.
		add_action( "{$taxonomy}_edit_form_fields", 'cc_attr_edit_image_field', 10, 2 );

		// Save on term create / update.
		add_action( "created_{$taxonomy}", 'cc_save_attr_image' );
		add_action( "edited_{$taxonomy}",  'cc_save_attr_image' );
	}
}

/* =====================================================================
   2.  "Add New Term" image field
   ===================================================================== */

function cc_attr_add_image_field( $taxonomy ) {
	?>
	<div class="form-field cc-attr-img-field">
		<label for="cc-attr-img-id"><?php esc_html_e( 'Image', 'consucorner' ); ?></label>

		<div class="cc-attr-img-wrap" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
			<img
				id="cc-attr-img-preview-new"
				src=""
				alt=""
				style="display:none;width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"
			/>
			<input type="hidden" id="cc-attr-img-id" name="cc_attribute_image_id" value="" />
			<button
				type="button"
				class="button cc-attr-upload-btn"
				data-preview="cc-attr-img-preview-new"
				data-input="cc-attr-img-id"
			><?php esc_html_e( 'Upload / Select Image', 'consucorner' ); ?></button>
			<button
				type="button"
				class="button cc-attr-remove-btn"
				data-preview="cc-attr-img-preview-new"
				data-input="cc-attr-img-id"
				style="display:none"
			><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
		</div>

		<p class="description"><?php esc_html_e( 'Optional image shown as a flag or icon for this term (e.g. country flag).', 'consucorner' ); ?></p>
	</div>
	<?php
}

/* =====================================================================
   3.  "Edit Term" image field
   ===================================================================== */

function cc_attr_edit_image_field( $term, $taxonomy ) {
	$img_id  = (int) get_term_meta( $term->term_id, '_cc_attribute_image', true );
	$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
	$field_id = 'cc-attr-img-id-' . (int) $term->term_id;
	$prev_id  = 'cc-attr-img-preview-' . (int) $term->term_id;
	$rm_id    = 'cc-attr-remove-' . (int) $term->term_id;
	?>
	<tr class="form-field cc-attr-img-row">
		<th scope="row">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Image', 'consucorner' ); ?></label>
		</th>
		<td>
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
				<img
					id="<?php echo esc_attr( $prev_id ); ?>"
					src="<?php echo esc_url( $img_url ); ?>"
					alt=""
					style="<?php echo esc_attr( $img_url ? '' : 'display:none;' ); ?>width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"
				/>
				<input
					type="hidden"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="cc_attribute_image_id"
					value="<?php echo esc_attr( $img_id ? $img_id : '' ); ?>"
				/>
				<button
					type="button"
					class="button cc-attr-upload-btn"
					data-preview="<?php echo esc_attr( $prev_id ); ?>"
					data-input="<?php echo esc_attr( $field_id ); ?>"
					data-remove="<?php echo esc_attr( $rm_id ); ?>"
				><?php esc_html_e( 'Upload / Select Image', 'consucorner' ); ?></button>
				<button
					type="button"
					id="<?php echo esc_attr( $rm_id ); ?>"
					class="button cc-attr-remove-btn"
					data-preview="<?php echo esc_attr( $prev_id ); ?>"
					data-input="<?php echo esc_attr( $field_id ); ?>"
					style="<?php echo esc_attr( $img_url ? '' : 'display:none;' ); ?>"
				><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Optional image shown as a flag or icon for this term (e.g. country flag).', 'consucorner' ); ?></p>
		</td>
	</tr>
	<?php
}

/* =====================================================================
   4.  Save handler
   ===================================================================== */

function cc_save_attr_image( $term_id ) {
	// Verify the request came from a legitimate WP admin term form.
	// WP core uses 'add-tag' on creation and 'update-tag_{$term_id}' on edits.
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

	if ( ! isset( $_POST['cc_attribute_image_id'] ) ) {
		return;
	}

	$img_id = absint( $_POST['cc_attribute_image_id'] );

	if ( $img_id ) {
		update_term_meta( $term_id, '_cc_attribute_image', $img_id );
	} else {
		delete_term_meta( $term_id, '_cc_attribute_image' );
	}
}

/* =====================================================================
   5.  Enqueue WP Media Library + our uploader script on term pages
   ===================================================================== */

add_action( 'admin_enqueue_scripts', 'cc_attr_enqueue_media_scripts' );

function cc_attr_enqueue_media_scripts( $hook ) {
	// Only fire on the term-list page (edit-tags.php) and the single term
	// edit page (term.php). Both can show our add/edit form fields.
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ( strpos( $screen->taxonomy, 'pa_' ) !== 0 && 'country_of_origin' !== $screen->taxonomy ) ) {
		return;
	}

	// wp_enqueue_media() bootstraps the entire WordPress media library frame
	// (React/Backbone UI + all dependencies). Must be called before the
	// page renders so wp.media is available in JS.
	wp_enqueue_media();

	// Enqueue our uploader handler script with jQuery as a dependency.
	// 'jquery' is always loaded in admin; wp_enqueue_media() ensures
	// wp.media is available by the time our DOMContentLoaded fires.
	wp_enqueue_script(
		'cc-attr-images',
		get_template_directory_uri() . '/assets/js/admin-attribute-images.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);
}
