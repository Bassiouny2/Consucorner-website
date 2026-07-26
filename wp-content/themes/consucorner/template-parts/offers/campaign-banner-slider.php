<?php
/**
 * Offers campaign banner slider — reuses campaign-banner.php markup per slide.
 *
 * @package ConsuCorner
 *
 * @var array<string,mixed> $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$campaigns = isset( $args['campaigns'] ) && is_array( $args['campaigns'] ) ? $args['campaigns'] : array();
$campaigns = array_values(
	array_filter(
		$campaigns,
		static function ( $campaign ) {
			return is_array( $campaign ) && ! empty( $campaign['cta_url'] );
		}
	)
);

if ( count( $campaigns ) < 2 ) {
	return;
}
?>
<section class="cc-offers-banner-slider" aria-label="<?php esc_attr_e( 'Featured offer campaigns', 'consucorner' ); ?>" data-slide-count="<?php echo esc_attr( (string) count( $campaigns ) ); ?>">
	<div class="cc-offers-banner-slider__viewport">
		<div class="cc-offers-banner-slider__track" id="ccOffersBannerTrack">
			<?php foreach ( $campaigns as $campaign ) : ?>
				<div class="cc-offers-banner-slider__slide" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'consucorner' ); ?>">
					<?php
					get_template_part(
						'template-parts/offers/campaign-banner',
						null,
						array(
							'campaign' => $campaign,
						)
					);
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="cc-offers-banner-slider__controls">
		<button type="button" class="cc-offers-banner-slider__arrow cc-offers-banner-slider__arrow--prev" id="ccOffersBannerPrev" aria-label="<?php esc_attr_e( 'Previous campaign', 'consucorner' ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>
		<div class="cc-offers-banner-slider__dots" id="ccOffersBannerDots" role="tablist" aria-label="<?php esc_attr_e( 'Campaign slides', 'consucorner' ); ?>"></div>
		<button type="button" class="cc-offers-banner-slider__arrow cc-offers-banner-slider__arrow--next" id="ccOffersBannerNext" aria-label="<?php esc_attr_e( 'Next campaign', 'consucorner' ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>
	</div>
</section>
