<?php
/**
 * Dynamic Offers landing page.
 *
 * Template Name: Offers Page
 *
 * Campaign pages load from /offers/{campaign-slug}/
 * Legacy ?vendor=&tag= links redirect to the campaign slug when possible.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$page_id       = get_the_ID();
	$hero          = function_exists( 'cc_offers_get_page_meta' ) ? cc_offers_get_page_meta( $page_id ) : array();
	$campaign_slug = function_exists( 'cc_offer_campaign_get_request_slug' ) ? cc_offer_campaign_get_request_slug() : '';
	$has_slug      = '' !== $campaign_slug;
	$campaign_id   = $has_slug && function_exists( 'cc_offer_campaign_resolve_by_slug' )
		? cc_offer_campaign_resolve_by_slug( $campaign_slug )
		: 0;
	$campaign      = $campaign_id && function_exists( 'cc_offer_campaign_get_data' )
		? cc_offer_campaign_get_data( $campaign_id )
		: null;
	$offer_ids     = ( is_array( $campaign ) && ! empty( $campaign['offer_ids'] ) ) ? (array) $campaign['offer_ids'] : array();

	if ( ! $has_slug && function_exists( 'cc_offer_campaign_get_all_offer_product_ids' ) ) {
		$offer_ids = cc_offer_campaign_get_all_offer_product_ids();
	}

	if ( is_array( $campaign ) ) {
		if ( ! empty( $campaign['hero_badge'] ) ) {
			$hero['badge'] = $campaign['hero_badge'];
		}
		if ( ! empty( $campaign['hero_title'] ) ) {
			$hero['title'] = $campaign['hero_title'];
		}
		if ( ! empty( $campaign['hero_desc'] ) ) {
			$hero['description'] = $campaign['hero_desc'];
		}
	}
	?>
	<main id="primary" class="page-offers cc-offers-page" role="main">
		<section class="cc-offers-hero" aria-labelledby="cc-offers-hero-title">
			<div class="cc-offers-hero__content">
				<?php if ( ! empty( $hero['badge'] ) ) : ?>
					<div class="cc-offers-hero__badge"><?php echo esc_html( $hero['badge'] ); ?></div>
				<?php endif; ?>
				<h1 id="cc-offers-hero-title" class="cc-offers-hero__title">
					<?php echo wp_kses_post( $hero['title'] ); ?>
				</h1>
				<?php if ( ! empty( $hero['description'] ) ) : ?>
					<p class="cc-offers-hero__description"><?php echo esc_html( $hero['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<?php
		if ( $has_slug && $campaign_id && function_exists( 'cc_offer_campaign_get_campaign_banner_slides' ) ) {
			$slides = cc_offer_campaign_get_campaign_banner_slides( $campaign_id );
			if ( count( $slides ) > 1 ) {
				get_template_part(
					'template-parts/offers/campaign-banner-slider',
					null,
					array(
						'campaigns' => $slides,
					)
				);
			} elseif ( 1 === count( $slides ) ) {
				get_template_part(
					'template-parts/offers/campaign-banner',
					null,
					array(
						'campaign' => $slides[0],
					)
				);
			}
		} elseif ( ! $has_slug && function_exists( 'cc_offer_campaign_get_public_banner_campaigns' ) ) {
			$banner_campaigns = cc_offer_campaign_get_public_banner_campaigns();
			if ( count( $banner_campaigns ) > 1 ) {
				get_template_part(
					'template-parts/offers/campaign-banner-slider',
					null,
					array(
						'campaigns' => $banner_campaigns,
					)
				);
			} elseif ( 1 === count( $banner_campaigns ) ) {
				get_template_part(
					'template-parts/offers/campaign-banner',
					null,
					array(
						'campaign' => $banner_campaigns[0],
					)
				);
			}
		}
		?>

		<section class="cc-offers-products" id="offers-products" aria-label="<?php esc_attr_e( 'Offer products', 'consucorner' ); ?>">
			<div class="cc-offers-products__container">
				<?php if ( $has_slug && ! is_array( $campaign ) ) : ?>
					<div class="cc-offers-empty">
						<h2><?php esc_html_e( 'This campaign is unavailable', 'consucorner' ); ?></h2>
						<p><?php esc_html_e( 'The campaign link may be inactive, expired, or not fully configured yet.', 'consucorner' ); ?></p>
					</div>
				<?php elseif ( empty( $offer_ids ) ) : ?>
					<div class="cc-offers-empty">
						<h2><?php esc_html_e( 'No offers available right now', 'consucorner' ); ?></h2>
						<p><?php esc_html_e( 'Check back soon for new deals and discounted medical supplies.', 'consucorner' ); ?></p>
					</div>
				<?php else : ?>
					<div class="fp-products-main cc-offers-products-main">
						<div class="fp-products-grid" id="ccOffersGrid">
						<?php
						foreach ( $offer_ids as $product_id ) {
							$product_id = absint( $product_id );
							if ( $product_id < 1 || ! function_exists( 'cc_render_product_card' ) ) {
								continue;
							}
							echo cc_render_product_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								$product_id,
								array(
									'show_offer_deal' => true,
								)
							);
						}
						?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
