<?php
/**
 * Shop / archive promo: trust row + AB-style slider or static banner.
 *
 * @package ConsuCorner
 *
 * Context: set_query_var( 'cc_shop_promo_context', array( ... ) ) from archive-product.php
 *
 * @var array $cc {
 *   @type string $theme_uri   Theme URI for static assets (flag image).
 *   @type string $shop_url    Shop page URL (CTA targets).
 *   @type array  $benefits    [ 'label' => string, 'icon' => 'check'|'clock'|'lock' ].
 *   @type array  $slides      [ 'title', 'subtitle', 'button', 'button_url', 'image', 'origins' ] (Customizer slides may include flag_image/flag_url).
 *   @type string $banner_url  Fallback background URL.
 *   @type string $mode        'slider' (main shop) or 'banner' (term archives).
 * }
 */

defined( 'ABSPATH' ) || exit;

$cc = get_query_var( 'cc_shop_promo_context', array() );
if ( ! is_array( $cc ) ) {
	$cc = array();
}
$theme_uri  = isset( $cc['theme_uri'] ) ? (string) $cc['theme_uri'] : get_template_directory_uri();
$shop_url   = isset( $cc['shop_url'] ) ? (string) $cc['shop_url'] : '';
$benefits   = isset( $cc['benefits'] ) && is_array( $cc['benefits'] ) ? $cc['benefits'] : array();
$slides     = isset( $cc['slides'] ) && is_array( $cc['slides'] ) ? $cc['slides'] : array();
$banner_url = isset( $cc['banner_url'] ) ? (string) $cc['banner_url'] : '';
$mode       = isset( $cc['mode'] ) ? (string) $cc['mode'] : 'slider';
$is_banner  = 'banner' === $mode;

if ( $is_banner && ! empty( $slides ) ) {
	$slides = array( $slides[0] );
}

?>
<section class="cc-shop-promo<?php echo $is_banner ? ' cc-shop-promo--static' : ''; ?>" aria-label="<?php esc_attr_e( 'Shop promotions', 'consucorner' ); ?>">
	<div class="cc-shop-promo-benefits" aria-label="<?php esc_attr_e( 'Shop benefits', 'consucorner' ); ?>">
		<?php foreach ( $benefits as $promo_benefit ) : ?>
			<?php
			$icon = isset( $promo_benefit['icon'] ) ? (string) $promo_benefit['icon'] : 'check';
			?>
			<div class="cc-shop-promo-benefit">
				<span class="cc-shop-promo-benefit-ic" aria-hidden="true">
					<?php if ( 'clock' === $icon ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
							<path d="M3 3v5h5"></path>
							<path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path>
							<path d="M16 21h5v-5"></path>
						</svg>
					<?php elseif ( 'lock' === $icon ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="6" y="10" width="12" height="10" rx="2"></rect>
							<path d="M9 10V8a3 3 0 0 1 6 0v2"></path>
						</svg>
					<?php else : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="9"></circle>
							<path d="M8.5 12.5l2.4 2.4 4.8-5.4"></path>
						</svg>
					<?php endif; ?>
				</span>
				<span><?php echo esc_html( $promo_benefit['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $slides ) ) : ?>
		<?php if ( $is_banner ) : ?>
			<div
				class="ab-promo-banner-static"
				role="region"
				aria-label="<?php esc_attr_e( 'Featured offer', 'consucorner' ); ?>"
			>
				<div class="ab-promo-viewport">
					<?php
					consucorner_shop_promo_render_slide( $slides[0], 0, $theme_uri, $shop_url, $banner_url, true, false );
					?>
				</div>
			</div>
		<?php else : ?>
			<div
				class="ab-promo-slider-wrapper"
				role="region"
				aria-label="<?php esc_attr_e( 'Featured offers', 'consucorner' ); ?>"
				data-ab-promo-slider
				tabindex="0"
			>
				<div class="ab-promo-arrow-container">
					<button class="ab-promo-arrow" type="button" data-promo-prev aria-label="<?php esc_attr_e( 'Previous promotion', 'consucorner' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M15 18l-6-6 6-6"></path>
						</svg>
					</button>
				</div>

				<div class="ab-promo-viewport" data-promo-viewport>
					<div class="ab-promo-track">
						<?php foreach ( $slides as $index => $promo_slide ) : ?>
							<?php
							consucorner_shop_promo_render_slide(
								$promo_slide,
								(int) $index,
								$theme_uri,
								$shop_url,
								$banner_url,
								0 === (int) $index,
								true
							);
							?>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="ab-promo-arrow-container">
					<button class="ab-promo-arrow" type="button" data-promo-next aria-label="<?php esc_attr_e( 'Next promotion', 'consucorner' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M9 18l6-6-6-6"></path>
						</svg>
					</button>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
