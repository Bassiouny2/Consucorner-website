<?php
/**
 * FAQ Page Template
 *
 * Template Name: FAQ Page
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

require get_template_directory() . '/inc/page-content/faq-data.php';

$faq_head  = isset( $faq_head ) && is_array( $faq_head ) ? $faq_head : array();
$faq_intro = isset( $faq_intro ) && is_array( $faq_intro ) ? $faq_intro : array();
$faq_cta   = isset( $faq_cta ) && is_array( $faq_cta ) ? $faq_cta : array();
$faq_items = isset( $faq_items ) && is_array( $faq_items ) ? $faq_items : array();

$faq_head = wp_parse_args(
	$faq_head,
	array(
		'title'       => __( 'Frequently Asked Questions', 'consucorner' ),
		'breadcrumbs' => __( 'Home/FAQ', 'consucorner' ),
	)
);
$faq_intro = wp_parse_args(
	$faq_intro,
	array(
		'eyebrow' => __( 'Help Center', 'consucorner' ),
		'title'   => __( 'Answers For A Smoother Medical Supply Experience', 'consucorner' ),
		'text'    => __( 'Find quick answers about ordering, vendors, delivery, payments, returns, and using ConsuCorner for your medical purchasing workflow.', 'consucorner' ),
	)
);
$faq_cta = wp_parse_args(
	$faq_cta,
	array(
		'title'       => __( 'Still Need Help?', 'consucorner' ),
		'text'        => __( 'Our support team can help with product requests, order questions, vendor onboarding, and marketplace guidance.', 'consucorner' ),
		'button_text' => __( 'Contact Support', 'consucorner' ),
		'button_url'  => home_url( '/contact/' ),
	)
);

get_header();
?>

<main class="faq-page-main">
	<section class="faq-page-head" aria-label="<?php esc_attr_e( 'FAQ page heading', 'consucorner' ); ?>">
		<div class="faq-page-head-inner">
			<h1 class="faq-page-title"><?php echo wp_kses_post( $faq_head['title'] ); ?></h1>
			<p class="faq-page-breadcrumbs"><?php consucorner_render_breadcrumbs( $faq_head['breadcrumbs'], get_permalink() ); ?></p>
		</div>
	</section>

	<section class="faq-section" aria-labelledby="faq-main-title">
		<div class="faq-inner">
			<header class="faq-intro">
				<span class="faq-eyebrow"><?php echo esc_html( $faq_intro['eyebrow'] ); ?></span>
				<h2 id="faq-main-title" class="faq-intro-title"><?php echo wp_kses_post( $faq_intro['title'] ); ?></h2>
				<p class="faq-intro-text"><?php echo esc_html( $faq_intro['text'] ); ?></p>
			</header>

			<div class="faq-layout">
				<div class="faq-list">
					<?php foreach ( $faq_items as $index => $item ) : ?>
						<?php
						$question = isset( $item['question'] ) ? $item['question'] : '';
						$answer   = isset( $item['answer'] ) ? $item['answer'] : '';

						if ( '' === trim( $question ) || '' === trim( wp_strip_all_tags( $answer ) ) ) {
							continue;
						}

						$button_id = 'faq-question-' . $index;
						$panel_id  = 'faq-answer-' . $index;
						?>
						<article class="faq-item">
							<h3 class="faq-question-heading">
								<button
									class="faq-question"
									id="<?php echo esc_attr( $button_id ); ?>"
									type="button"
									aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>"
									aria-controls="<?php echo esc_attr( $panel_id ); ?>"
								>
									<span><?php echo esc_html( $question ); ?></span>
									<svg class="faq-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
										<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</button>
							</h3>
							<div
								class="faq-answer"
								id="<?php echo esc_attr( $panel_id ); ?>"
								role="region"
								aria-labelledby="<?php echo esc_attr( $button_id ); ?>"
								<?php echo 0 === $index ? '' : 'hidden'; ?>
							>
								<div class="faq-answer-inner">
									<?php echo wp_kses_post( wpautop( $answer ) ); ?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<aside class="faq-support-card" aria-label="<?php esc_attr_e( 'FAQ support information', 'consucorner' ); ?>">
					<span class="faq-support-kicker"><?php esc_html_e( 'Support', 'consucorner' ); ?></span>
					<h2><?php echo esc_html( $faq_cta['title'] ); ?></h2>
					<p><?php echo esc_html( $faq_cta['text'] ); ?></p>
					<a class="faq-support-btn" href="<?php echo esc_url( $faq_cta['button_url'] ); ?>">
						<?php echo esc_html( $faq_cta['button_text'] ); ?>
					</a>
				</aside>
			</div>
		</div>
	</section>

	<section class="faq-bottom-banner" aria-hidden="true">
		<div class="medical-products-banner sp-banner"></div>
	</section>
</main>

<?php
$schema_items = array();
foreach ( $faq_items as $item ) {
	$question = isset( $item['question'] ) ? trim( wp_strip_all_tags( $item['question'] ) ) : '';
	$answer   = isset( $item['answer'] ) ? trim( wp_strip_all_tags( $item['answer'] ) ) : '';

	if ( '' === $question || '' === $answer ) {
		continue;
	}

	$schema_items[] = array(
		'@type'          => 'Question',
		'name'           => $question,
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $answer,
		),
	);
}

if ( $schema_items ) :
	?>
	<script type="application/ld+json">
	<?php
	echo wp_json_encode(
		array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $schema_items,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
	);
	?>
	</script>
<?php endif; ?>

<?php get_footer(); ?>
