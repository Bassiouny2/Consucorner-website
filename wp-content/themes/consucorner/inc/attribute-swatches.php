<?php
/**
 * Attribute swatch display settings (dropdown, buttons, images).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Option key for an attribute taxonomy swatch display mode.
 *
 * @param string $taxonomy Attribute taxonomy e.g. pa_qty.
 * @return string
 */
function cc_attribute_swatch_option_key( $taxonomy ) {
	return 'ccs_swatch_display_' . sanitize_key( $taxonomy );
}

/**
 * Get swatch display mode for an attribute taxonomy.
 *
 * @param string $attribute Taxonomy (pa_*) or raw attribute name.
 * @return string dropdown|button|image
 */
function cc_get_attribute_swatch_display( $attribute ) {
	$taxonomy = taxonomy_exists( $attribute ) ? $attribute : wc_attribute_taxonomy_name( $attribute );

	if ( ! $taxonomy ) {
		return 'dropdown';
	}

	$display = get_option( cc_attribute_swatch_option_key( $taxonomy ), 'button' );
	$allowed = array( 'dropdown', 'button', 'image' );

	if ( ! in_array( $display, $allowed, true ) ) {
		$display = 'button';
	}

	return $display;
}

/**
 * Save swatch display from attribute admin form POST.
 *
 * @param string $attribute_slug Attribute slug without pa_ prefix.
 * @return void
 */
function cc_save_attribute_swatch_display_for_slug( $attribute_slug ) {
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}

	if ( ! isset( $_POST['cc_attribute_swatch_display'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$display = sanitize_key( wp_unslash( $_POST['cc_attribute_swatch_display'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$allowed = array( 'dropdown', 'button', 'image' );

	if ( ! in_array( $display, $allowed, true ) ) {
		return;
	}

	$taxonomy = wc_attribute_taxonomy_name( $attribute_slug );
	if ( $taxonomy ) {
		update_option( cc_attribute_swatch_option_key( $taxonomy ), $display );
	}
}

add_action(
	'woocommerce_attribute_added',
	function ( $id, $data ) {
		if ( ! empty( $data['attribute_name'] ) ) {
			cc_save_attribute_swatch_display_for_slug( $data['attribute_name'] );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_attribute_updated',
	function ( $id, $data ) {
		if ( ! empty( $data['attribute_name'] ) ) {
			cc_save_attribute_swatch_display_for_slug( $data['attribute_name'] );
		}
	},
	10,
	2
);

/**
 * Render swatch display field on WooCommerce attribute add form.
 *
 * @return void
 */
function cc_attribute_swatch_admin_add_field() {
	?>
	<div class="form-field">
		<label for="cc_attribute_swatch_display"><?php esc_html_e( 'Variation display', 'consucorner' ); ?></label>
		<select name="cc_attribute_swatch_display" id="cc_attribute_swatch_display">
			<option value="dropdown"><?php esc_html_e( 'Dropdown', 'consucorner' ); ?></option>
			<option value="button" selected><?php esc_html_e( 'Buttons', 'consucorner' ); ?></option>
			<option value="image"><?php esc_html_e( 'Images', 'consucorner' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'How this attribute appears on variable product pages.', 'consucorner' ); ?></p>
	</div>
	<?php
}
add_action( 'woocommerce_after_add_attribute_fields', 'cc_attribute_swatch_admin_add_field' );

/**
 * Render swatch display field on WooCommerce attribute edit form.
 *
 * @return void
 */
function cc_attribute_swatch_admin_edit_field() {
	$edit    = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = 'button';

	if ( $edit && function_exists( 'wc_get_attribute' ) ) {
		$attribute = wc_get_attribute( $edit );
		if ( $attribute && ! empty( $attribute->slug ) ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->slug );
			$current  = cc_get_attribute_swatch_display( $taxonomy );
		}
	}
	?>
	<tr class="form-field">
		<th scope="row">
			<label for="cc_attribute_swatch_display"><?php esc_html_e( 'Variation display', 'consucorner' ); ?></label>
		</th>
		<td>
			<select name="cc_attribute_swatch_display" id="cc_attribute_swatch_display">
				<option value="dropdown" <?php selected( $current, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'consucorner' ); ?></option>
				<option value="button" <?php selected( $current, 'button' ); ?>><?php esc_html_e( 'Buttons', 'consucorner' ); ?></option>
				<option value="image" <?php selected( $current, 'image' ); ?>><?php esc_html_e( 'Images', 'consucorner' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'How this attribute appears on variable product pages. Image mode uses term images from Products → Attributes → Terms.', 'consucorner' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'woocommerce_after_edit_attribute_fields', 'cc_attribute_swatch_admin_edit_field' );

/**
 * Get attachment image URL for an attribute term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy.
 * @return string
 */
function cc_get_attribute_term_image_url( $term_id, $taxonomy = '' ) {
	$img_id = (int) get_term_meta( $term_id, '_cc_attribute_image', true );
	if ( ! $img_id ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
	return $url ? $url : '';
}

/**
 * Render one variation attribute field (dropdown and/or swatches).
 *
 * @param string     $attribute_name Attribute key.
 * @param array      $options        Option slugs.
 * @param WC_Product $product        Product.
 * @param bool       $is_last        Whether this is the last attribute row.
 * @return void
 */
function cc_render_variation_attribute_field( $attribute_name, $options, $product, $is_last = false ) {
	$label     = wc_attribute_label( $attribute_name );
	$display   = cc_get_attribute_swatch_display( $attribute_name );
	$select_id = sanitize_title( $attribute_name );

	if ( 'button' === $display && count( (array) $options ) > 8 ) {
		$display = 'dropdown';
	}

	$selected_key = 'attribute_' . sanitize_title( $attribute_name );
	$selected     = isset( $_REQUEST[ $selected_key ] ) ? wc_clean( wp_unslash( $_REQUEST[ $selected_key ] ) ) : $product->get_variation_default_attribute( $attribute_name ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	?>
	<div class="sp-variation-row sp-variation-row--<?php echo esc_attr( $display ); ?>" data-attribute="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
		<span class="sp-variation-label"><?php echo esc_html( $label ); ?></span>
		<div class="sp-variation-value value">
			<?php
			wc_dropdown_variation_attribute_options(
				array(
					'options'   => $options,
					'attribute' => $attribute_name,
					'product'   => $product,
					'selected'  => $selected,
					'class'     => 'cc-variation-select' . ( 'dropdown' !== $display ? ' cc-variation-select--hidden' : '' ),
					'id'        => $select_id,
				)
			);

			if ( 'dropdown' !== $display ) :
				$swatch_class = 'image' === $display ? 'sp-swatches--image' : 'sp-swatches--button';
				?>
				<div class="sp-swatches <?php echo esc_attr( $swatch_class ); ?>" role="listbox" aria-label="<?php echo esc_attr( $label ); ?>">
					<?php
					if ( taxonomy_exists( $attribute_name ) ) {
						$terms = wc_get_product_terms(
							$product->get_id(),
							$attribute_name,
							array( 'fields' => 'all' )
						);

						foreach ( $terms as $term ) {
							if ( ! in_array( $term->slug, (array) $options, true ) ) {
								continue;
							}

							$is_selected = (string) $selected === (string) $term->slug;
							$img_url     = cc_get_attribute_term_image_url( $term->term_id, $attribute_name );
							$use_image   = 'image' === $display && $img_url;

							if ( 'image' === $display && ! $img_url ) {
								$use_image = false;
							}

							$btn_class = 'sp-swatch' . ( $use_image ? ' sp-swatch--image' : ' sp-swatch--button' );
							if ( $is_selected ) {
								$btn_class .= ' is-selected';
							}
							?>
							<button
								type="button"
								class="<?php echo esc_attr( $btn_class ); ?>"
								data-value="<?php echo esc_attr( $term->slug ); ?>"
								data-select-id="<?php echo esc_attr( $select_id ); ?>"
								role="option"
								aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
								title="<?php echo esc_attr( $term->name ); ?>"
							>
								<?php if ( $use_image ) : ?>
									<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" width="48" height="48" loading="lazy" decoding="async" />
								<?php else : ?>
									<span class="sp-swatch__text"><?php echo esc_html( $term->name ); ?></span>
								<?php endif; ?>
							</button>
							<?php
						}
					} else {
						foreach ( (array) $options as $option ) {
							$is_selected = (string) $selected === (string) $option || sanitize_title( (string) $selected ) === sanitize_title( (string) $option );
							$btn_class   = 'sp-swatch sp-swatch--button' . ( $is_selected ? ' is-selected' : '' );
							?>
							<button
								type="button"
								class="<?php echo esc_attr( $btn_class ); ?>"
								data-value="<?php echo esc_attr( $option ); ?>"
								data-select-id="<?php echo esc_attr( $select_id ); ?>"
								role="option"
								aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
							>
								<span class="sp-swatch__text"><?php echo esc_html( apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute_name, $product ) ); ?></span>
							</button>
							<?php
						}
					}
					?>
				</div>
			<?php endif; ?>

			<?php
			if ( $is_last ) {
				echo wp_kses_post(
					apply_filters(
						'woocommerce_reset_variations_link',
						'<a class="reset_variations" href="#" aria-label="' . esc_attr__( 'Clear options', 'woocommerce' ) . '">' . esc_html__( 'Clear', 'consucorner' ) . '</a>'
					)
				);
			}
			?>
		</div>
	</div>
	<?php
}
