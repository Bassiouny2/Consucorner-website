<?php
/**
 * Template Name: New Order Thank You
 * Template Post Type: page
 *
 * WordPress version of front-end/new-order-thank-you.html.
 * Uses the theme header/footer; only the body content for the thank-you page
 * is rendered here so the page can later be wired to Forminator fields.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

$_cc_quote_key  = isset($_COOKIE['cc_quote_key']) ? sanitize_key(wp_unslash($_COOKIE['cc_quote_key'])) : '';
$_cc_quote_data = $_cc_quote_key ? get_transient($_cc_quote_key) : array();
$_cc_quote_data = is_array($_cc_quote_data) ? $_cc_quote_data : array();

$ty_name    = isset($_cc_quote_data['name']) ? sanitize_text_field($_cc_quote_data['name']) : '';
$ty_email   = isset($_cc_quote_data['email']) ? sanitize_email($_cc_quote_data['email']) : '';
$ty_product = isset($_cc_quote_data['product']) ? sanitize_text_field($_cc_quote_data['product']) : '';
$ty_time    = isset($_cc_quote_data['time']) ? sanitize_text_field($_cc_quote_data['time']) : wp_date('M j \a\t g:i A');

get_header();
?>

<main class="new-ty-main" role="main">
	<div class="new-ty-container">
		<div class="new-ty-card">
			<div class="new-ty-icon-wrapper" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false">
					<polyline points="20 6 9 17 4 12"></polyline>
				</svg>
			</div>

			<h1 class="new-ty-title"><?php esc_html_e('Request Sent Successfully!', 'consucorner'); ?></h1>

			<p class="new-ty-desc">
				<?php esc_html_e('Hi', 'consucorner'); ?>
				<strong class="new-ty-customer-name"><?php echo esc_html($ty_name ? $ty_name : __('there', 'consucorner')); ?></strong>,
				<?php esc_html_e("we've sent your request for the", 'consucorner'); ?>
				<strong class="product-highlight new-ty-product-name"><?php echo esc_html($ty_product ? $ty_product : __('your product', 'consucorner')); ?></strong>
				<?php esc_html_e("to the vendor. You'll hear back from us soon.", 'consucorner'); ?>
			</p>

			<div class="new-ty-status-box">
				<div class="status-header">
					<span class="status-label"><?php esc_html_e('QUOTE STATUS', 'consucorner'); ?></span>
					<span class="status-badge"><span class="dot"></span> <?php esc_html_e('IN REVIEW', 'consucorner'); ?></span>
				</div>

				<div class="status-timeline">
					<div class="timeline-step completed">
						<div class="step-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false">
								<polyline points="20 6 9 17 4 12"></polyline>
							</svg>
						</div>
						<div class="step-text">
							<strong><?php esc_html_e('Request Received', 'consucorner'); ?></strong>
							<span class="new-ty-request-time"><?php echo esc_html($ty_time); ?></span>
						</div>
					</div>

					<div class="timeline-step pending">
						<div class="step-icon" aria-hidden="true">2</div>
						<div class="step-text">
							<strong><?php esc_html_e('Vendor Verification', 'consucorner'); ?></strong>
							<span><?php esc_html_e('Pending', 'consucorner'); ?></span>
						</div>
					</div>

					<div class="timeline-step pending">
						<div class="step-icon" aria-hidden="true">3</div>
						<div class="step-text">
							<strong><?php esc_html_e('Final Quote Ready', 'consucorner'); ?></strong>
							<span><?php esc_html_e('Estimate: Within 24 hours', 'consucorner'); ?></span>
						</div>
					</div>
				</div>
			</div>

			<a class="new-ty-btn" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>">
				<?php esc_html_e('Continue Shopping', 'consucorner'); ?>
			</a>

			<p class="new-ty-footer-note">
				<?php esc_html_e('A copy of this request has been sent to', 'consucorner'); ?>
				<strong class="new-ty-customer-email"><?php echo esc_html($ty_email ? $ty_email : __('your email address', 'consucorner')); ?></strong>
			</p>
		</div>

		<div class="new-ty-content">
			<h3><?php esc_html_e("What's next?", 'consucorner'); ?></h3>
			<p><?php esc_html_e("One of our team members will get back to you shortly with the details you need. We're committed to providing a smooth, reliable, and professional experience tailored to healthcare professionals.", 'consucorner'); ?></p>

			<h3><?php esc_html_e('Need immediate assistance?', 'consucorner'); ?></h3>
			<p><?php esc_html_e("If you have any questions or require further support, feel free to contact us - we're always happy to help. ConsuCorner is built to simplify access to trusted medical supplies and solutions, saving you time and effort so you can focus on what matters most.", 'consucorner'); ?></p>

			<p class="new-ty-appreciation"><strong><?php esc_html_e('At ConsuCorner, we truly appreciate your trust. Your submission has been received, and our team is already reviewing it to ensure you get the best possible support and service.', 'consucorner'); ?></strong></p>

			<p class="new-ty-thank-you"><?php esc_html_e('Thank you for choosing ConsuCorner. We look forward to serving you.', 'consucorner'); ?></p>
		</div>
	</div>
</main>

<?php
get_footer();
