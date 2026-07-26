<?php
/**
 * Dynamic Bundles landing page — mix-and-match builder cards.
 *
 * Template Name: Bundles Page
 *
 * Exact campaign: /bundles/{campaign-slug}/ or /bundles/{campaign-slug}/{pack-key}/
 * Optional filters: ?vendor=vendor-username & ?tag=bundle-tag-slug
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$campaign_slug = function_exists( 'cc_offer_campaign_get_request_slug' ) ? cc_offer_campaign_get_request_slug() : '';
	$pack_key      = function_exists( 'cc_offer_campaign_get_request_pack_key' ) ? cc_offer_campaign_get_request_pack_key() : '';
	$campaign_id   = ( '' !== $campaign_slug && function_exists( 'cc_offer_campaign_resolve_bundle_by_slug' ) )
		? cc_offer_campaign_resolve_bundle_by_slug( $campaign_slug )
		: 0;
	$exact_mode    = '' !== $campaign_slug;
	$pack_mode     = $exact_mode && '' !== $pack_key;

	$filters   = function_exists( 'cc_bundles_get_filters_from_request' )
		? cc_bundles_get_filters_from_request()
		: array(
			'vendor_id'       => 0,
			'tag_slug'        => '',
			'vendor_username' => '',
		);
	$vendor_id = isset( $filters['vendor_id'] ) ? (int) $filters['vendor_id'] : 0;
	$tag_slug  = isset( $filters['tag_slug'] ) ? (string) $filters['tag_slug'] : '';

	$vendor_label = ( $vendor_id && function_exists( 'cc_offers_get_vendor_label' ) ) ? cc_offers_get_vendor_label( $vendor_id ) : '';
	$tag_term     = ( '' !== $tag_slug && taxonomy_exists( CC_BUNDLE_TAG_TAXONOMY ) )
		? get_term_by( 'slug', $tag_slug, CC_BUNDLE_TAG_TAXONOMY )
		: false;
	$tag_label    = ( $tag_term instanceof WP_Term ) ? $tag_term->name : $tag_slug;
	$has_filters  = ( $vendor_id > 0 ) || ( '' !== $tag_slug );

	$bundles_html = '';
	$hero_pack    = null;

	if ( $exact_mode ) {
		if ( $campaign_id && function_exists( 'cc_campaign_get_active_bundles' ) && function_exists( 'cc_bundles_render_card' ) ) {
			if ( $pack_mode ) {
				$card = cc_bundles_render_card( $campaign_id, $pack_key );
				if ( '' !== $card ) {
					$bundles_html = '<div class="cc-bundles-grid">' . $card . '</div>';
				}
				$hero_pack = function_exists( 'cc_bundles_get_builder_data' )
					? cc_bundles_get_builder_data( $campaign_id, $pack_key )
					: null;
			} else {
				$cards = array();
				foreach ( cc_campaign_get_active_bundles( $campaign_id ) as $pack ) {
					$card = cc_bundles_render_card( $campaign_id, $pack['key'] );
					if ( '' !== $card ) {
						$cards[] = $card;
					}
				}
				if ( ! empty( $cards ) ) {
					$bundles_html = '<div class="cc-bundles-grid">' . implode( '', $cards ) . '</div>';
				}
				$hero_pack = function_exists( 'cc_bundles_get_builder_data' )
					? cc_bundles_get_builder_data( $campaign_id )
					: null;
			}
		}
	} else {
		$bundles_query = function_exists( 'cc_bundles_query' )
			? cc_bundles_query( $vendor_id, $tag_slug )
			: null;
		$bundles_html  = ( $bundles_query instanceof WP_Query && function_exists( 'cc_bundles_render_grid' ) )
			? cc_bundles_render_grid( $bundles_query )
			: '';

		if ( $bundles_query instanceof WP_Query && $bundles_query->have_posts() ) {
			$first_id  = (int) $bundles_query->posts[0]->ID;
			$hero_pack = function_exists( 'cc_bundles_get_builder_data' )
				? cc_bundles_get_builder_data( $first_id )
				: null;
		}
	}

	$hero_title = get_the_title();
	if ( $exact_mode && is_array( $hero_pack ) && ! empty( $hero_pack['title'] ) ) {
		$hero_title = (string) $hero_pack['title'];
	} elseif ( $vendor_label && $tag_label ) {
		$hero_title = sprintf(
			/* translators: 1: vendor store name, 2: bundle tag name */
			__( '%1$s · %2$s', 'consucorner' ),
			$vendor_label,
			$tag_label
		);
	} elseif ( $tag_label ) {
		$hero_title = sprintf(
			/* translators: %s: bundle tag name */
			__( '%s Bundles', 'consucorner' ),
			$tag_label
		);
	} elseif ( $vendor_label ) {
		$hero_title = sprintf(
			/* translators: %s: vendor store name */
			__( 'Bundles from %s', 'consucorner' ),
			$vendor_label
		);
	}

	$hero_desc = has_excerpt()
		? get_the_excerpt()
		: __( 'Mix and match from each pack — pick any combination of items for one flat price.', 'consucorner' );
	if ( $exact_mode && is_array( $hero_pack ) && ! empty( $hero_pack['excerpt'] ) ) {
		$hero_desc = wp_strip_all_tags( (string) $hero_pack['excerpt'] );
	}
	?>
	<main id="primary" class="page-bundles cc-bundles-page" role="main">
		<section class="cc-bundles-hero" aria-labelledby="cc-bundles-hero-title">
			<div class="cc-bundles-hero__glow" aria-hidden="true"></div>
			<div class="cc-bundles-hero__inner">
				<div class="cc-bundles-hero__copy">
					<p class="cc-bundles-hero__eyebrow"><?php esc_html_e( 'Mix & Match Packs', 'consucorner' ); ?></p>
					<h1 id="cc-bundles-hero-title" class="cc-bundles-hero__title"><?php echo esc_html( $hero_title ); ?></h1>
					<p class="cc-bundles-hero__description"><?php echo esc_html( $hero_desc ); ?></p>
				</div>
			</div>
		</section>

		<section class="cc-bundles-products" id="bundles-products" aria-label="<?php esc_attr_e( 'Bundle packs', 'consucorner' ); ?>">
			<div class="cc-bundles-products__container">
				<?php if ( '' === $bundles_html ) : ?>
					<div class="cc-bundles-empty">
						<div class="cc-bundles-empty__icon" aria-hidden="true">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
						</div>
						<h2>
							<?php
							echo esc_html(
								$exact_mode
									? __( 'This campaign bundle is unavailable', 'consucorner' )
									: __( 'No packs found', 'consucorner' )
							);
							?>
						</h2>
						<p>
							<?php
							if ( $exact_mode ) {
								esc_html_e( 'The campaign link may be inactive, expired, or not fully configured yet.', 'consucorner' );
							} elseif ( $has_filters ) {
								esc_html_e( 'Nothing matches this campaign link right now.', 'consucorner' );
							} else {
								esc_html_e( 'No active packs are available yet. Check back soon.', 'consucorner' );
							}
							?>
						</p>
					</div>
				<?php else : ?>
					<?php echo $bundles_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
