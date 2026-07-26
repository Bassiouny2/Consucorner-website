<?php
/**
 * Offers campaign banner — collage card layout, data from the campaign bundle pool.
 *
 * @package ConsuCorner
 *
 * @var array<string,mixed> $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$campaign = isset( $args['campaign'] ) && is_array( $args['campaign'] ) ? $args['campaign'] : array();
$title    = isset( $campaign['title'] ) ? (string) $campaign['title'] : '';
$subtitle = isset( $campaign['subtitle'] ) ? (string) $campaign['subtitle'] : '';
$cta_url  = isset( $campaign['cta_url'] ) ? (string) $campaign['cta_url'] : '';
$cta      = isset( $campaign['cta_label'] ) ? (string) $campaign['cta_label'] : '';
$images   = isset( $campaign['pool_images'] ) && is_array( $campaign['pool_images'] ) ? $campaign['pool_images'] : array();
$currency = isset( $campaign['currency'] ) ? (string) $campaign['currency'] : 'EGP';
$amount   = isset( $campaign['price_amount'] ) ? (string) $campaign['price_amount'] : '';
$savings  = isset( $campaign['savings_percent'] ) ? (int) $campaign['savings_percent'] : 0;
$grad_id  = 'ccBannerRibbonGrad_' . ( isset( $campaign['id'] ) ? absint( $campaign['id'] ) : 0 );

if ( '' === $cta_url ) {
	return;
}

$images = array_values(
	array_filter(
		$images,
		static function ( $image ) {
			return is_array( $image ) && ! empty( $image['src'] );
		}
	)
);
while ( count( $images ) < 3 ) {
	$images[] = array(
		'src'  => '',
		'name' => '',
	);
}
$images = array_slice( $images, 0, 3 );

$circle_classes = array(
	'cc-offer-campaign-banner__circle--top',
	'cc-offer-campaign-banner__circle--main',
	'cc-offer-campaign-banner__circle--bottom',
);
?>
<section class="cc-offer-campaign-banner" aria-label="<?php esc_attr_e( 'Featured campaign bundle', 'consucorner' ); ?>">
	<article class="cc-offer-campaign-banner__card">
		<div class="cc-offer-campaign-banner__inner" aria-hidden="true">
			<div class="cc-offer-campaign-banner__blob"></div>
		</div>

		<div class="cc-offer-campaign-banner__circles" aria-hidden="true">
			<?php foreach ( $images as $index => $image ) : ?>
				<div class="cc-offer-campaign-banner__circle <?php echo esc_attr( $circle_classes[ $index ] ); ?><?php echo empty( $image['src'] ) ? ' is-empty' : ''; ?>">
					<?php if ( ! empty( $image['src'] ) ) : ?>
						<img src="<?php echo esc_url( $image['src'] ); ?>" alt="" loading="lazy" decoding="async" />
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cc-offer-campaign-banner__content">
			<?php if ( '' !== $title ) : ?>
				<h2 class="cc-offer-campaign-banner__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="cc-offer-campaign-banner__subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>

			<div class="cc-offer-campaign-banner__footer">
				<a class="cc-offer-campaign-banner__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M6 6h15l-1.5 9h-12z"></path>
						<circle cx="9" cy="20" r="1.5"></circle>
						<circle cx="18" cy="20" r="1.5"></circle>
						<path d="M6 6L5 3H2"></path>
					</svg>
					<?php echo esc_html( $cta ?: __( 'Shop the bundle', 'consucorner' ) ); ?>
				</a>
			</div>
		</div>

		<?php if ( '' !== $amount ) : ?>
			<div class="cc-offer-campaign-banner__ribbon">
				<svg width="139" height="340" viewBox="0 0 139 340" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
					<path
						d="M0.00551161 17.487L2.3055 143.887C2.4055 147.387 3.50551 150.787 5.50551 153.587L138.506 339.187V55.387C138.506 48.187 134.006 41.787 127.206 39.287L23.0055 1.08703C11.7055 -3.11297 -0.294488 5.38703 0.00551161 17.487Z"
						fill="url(#<?php echo esc_attr( $grad_id ); ?>)"
					/>
					<defs>
						<linearGradient
							id="<?php echo esc_attr( $grad_id ); ?>"
							x1="77.8433"
							y1="297.768"
							x2="66.2609"
							y2="-10.6138"
							gradientUnits="userSpaceOnUse"
						>
							<stop stop-color="#40DBD3" />
							<stop offset="0.5679" stop-color="#35E6C0" />
							<stop offset="0.9767" stop-color="#30EAB9" />
						</linearGradient>
					</defs>
				</svg>
				<div class="cc-offer-campaign-banner__price">
					<span class="cc-offer-campaign-banner__currency"><?php echo esc_html( $currency ); ?></span>
					<span class="cc-offer-campaign-banner__amount"><?php echo esc_html( $amount ); ?></span>
					<?php if ( $savings > 0 ) : ?>
						<span class="cc-offer-campaign-banner__saved">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: savings percent */
									__( 'Saved %d%%', 'consucorner' ),
									$savings
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</article>
</section>
