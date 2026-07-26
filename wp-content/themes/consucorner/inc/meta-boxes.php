<?php

/**
 * Custom Fields (Meta Boxes) for Static Pages
 *
 * Each page (About, Contact, Privacy Policy, Vendor) gets its own
 * meta box in the WordPress editor so ALL text is editable from
 * wp-admin → Pages without touching any PHP files.
 *
 * Data is stored as individual post_meta keys prefixed with _cc_.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/* =============================================================
   SHARED HELPERS
   ============================================================= */

/** Print a section heading inside a meta box. */
function cc_meta_section($title)
{
	printf(
		'<h4 style="margin:14px 0 4px;padding:6px 10px;background:#f0f6f5;border-left:3px solid #00c8b3;font-size:12px;text-transform:uppercase;letter-spacing:.5px">%s</h4>',
		esc_html($title)
	);
}

/** Print a single-line text input. */
function cc_meta_text($post_id, $key, $label, $default = '')
{
	$value = get_post_meta($post_id, $key, true);
	if ($value === '' || $value === false) {
		$value = $default;
	}
?>
	<p style="margin:6px 0">
		<label for="<?php echo esc_attr($key); ?>" style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php echo esc_html($label); ?></label>
		<input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%;font-size:13px">
	</p>
<?php
}

/** Print a multi-line textarea (supports HTML). */
function cc_meta_textarea($post_id, $key, $label, $default = '', $rows = 3)
{
	$value = get_post_meta($post_id, $key, true);
	if ($value === '' || $value === false) {
		$value = $default;
	}
?>
	<p style="margin:6px 0">
		<label for="<?php echo esc_attr($key); ?>" style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">
			<?php echo esc_html($label); ?>
			<span style="font-weight:400;color:#888"> (HTML allowed)</span>
		</label>
		<textarea id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" rows="<?php echo absint($rows); ?>" style="width:100%;font-size:13px"><?php echo esc_textarea($value); ?></textarea>
	</p>
<?php
}

/** Print a checkbox stored as "1" or "". */
function cc_meta_checkbox($post_id, $key, $label, $description = '')
{
	$value = get_post_meta($post_id, $key, true);
	$checked = '1' === (string) $value;
?>
	<p style="margin:6px 0">
		<label for="<?php echo esc_attr($key); ?>" style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer">
			<input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($checked); ?> style="margin-top:2px">
			<span>
				<strong style="display:block;font-size:12px"><?php echo esc_html($label); ?></strong>
				<?php if ($description) : ?>
					<span style="display:block;color:#646970;font-size:11px;margin-top:2px"><?php echo esc_html($description); ?></span>
				<?php endif; ?>
			</span>
		</label>
	</p>
<?php
}

/** Default data for the homepage New Arrivals slider. */
function cc_home_new_arrivals_default_slides($img_base = '')
{
	$img_base = $img_base ? trailingslashit($img_base) : trailingslashit(get_template_directory_uri() . '/assets/images');

	return array(
		array(
			'bg1'          => $img_base . 'Rectangle blue.svg',
			'bg2'          => $img_base . 'Rectangle baby-blue.svg',
			'productImage' => $img_base . 'product-image-vendor.png',
			'vendorLogo'   => $img_base . 'vendor-log.png',
			'vendorName'   => 'LifeCare Surgical',
			'title'        => 'Illumination head for Disposable Proctoscopy',
			'btnText'      => 'Shop Now',
			'link'         => home_url('/shop/'),
		),
		array(
			'bg1'          => $img_base . 'Rectangle blue.svg',
			'bg2'          => $img_base . 'Rectangle baby-blue.svg',
			'productImage' => $img_base . 'product-image-vendor 2.png',
			'vendorLogo'   => $img_base . 'vendor-log 2.png',
			'vendorName'   => 'MedTech Solutions',
			'title'        => 'Advanced Surgical Forceps Kit',
			'btnText'      => 'Shop Now',
			'link'         => home_url('/shop/'),
		),
		array(
			'bg1'          => $img_base . 'Rectangle blue.svg',
			'bg2'          => $img_base . 'Rectangle baby-blue.svg',
			'productImage' => $img_base . 'product-image-vendor 3.png',
			'vendorLogo'   => $img_base . 'vendor-log 3.png',
			'vendorName'   => 'ProMed Instruments',
			'title'        => 'Digital Otoscope System',
			'btnText'      => 'Shop Now',
			'link'         => home_url('/shop/'),
		),
		array(
			'bg1'          => $img_base . 'Rectangle blue.svg',
			'bg2'          => $img_base . 'Rectangle baby-blue.svg',
			'productImage' => $img_base . 'product-image-vendor 4.png',
			'vendorLogo'   => $img_base . 'vendor-log 4.png',
			'vendorName'   => 'SurgiCare Plus',
			'title'        => 'Precision Dental Mirror Set',
			'btnText'      => 'Shop Now',
			'link'         => home_url('/shop/'),
		),
	);
}

/** Normalize one New Arrivals slide for safe frontend output. */
function cc_home_sanitize_new_arrivals_slide($slide)
{
	$slide = is_array($slide) ? $slide : array();

	return array(
		'bg1'          => isset($slide['bg1']) ? esc_url_raw($slide['bg1']) : '',
		'bg2'          => isset($slide['bg2']) ? esc_url_raw($slide['bg2']) : '',
		'productImage' => isset($slide['productImage']) ? esc_url_raw($slide['productImage']) : '',
		'vendorLogo'   => isset($slide['vendorLogo']) ? esc_url_raw($slide['vendorLogo']) : '',
		'vendorName'   => isset($slide['vendorName']) ? sanitize_text_field($slide['vendorName']) : '',
		'title'        => isset($slide['title']) ? sanitize_text_field($slide['title']) : '',
		'btnText'      => isset($slide['btnText']) ? sanitize_text_field($slide['btnText']) : '',
		'link'         => isset($slide['link']) ? esc_url_raw($slide['link']) : '',
	);
}

/** Get saved homepage New Arrivals slides, falling back to legacy fields/defaults. */
function cc_get_home_new_arrivals_slides($post_id, $img_base = '')
{
	$saved = get_post_meta($post_id, '_cc_home_new_arrivals_slides', true);
	if (is_array($saved)) {
		$slides = array();
		foreach ($saved as $slide) {
			$slide = cc_home_sanitize_new_arrivals_slide($slide);
			if ($slide['productImage'] || $slide['vendorLogo'] || $slide['vendorName'] || $slide['title']) {
				$slides[] = $slide;
			}
		}
		return $slides;
	}

	$defaults = cc_home_new_arrivals_default_slides($img_base);
	if ($post_id) {
		$defaults[0] = array(
			'bg1'          => get_post_meta($post_id, '_cc_home_new_arrivals_bg_1', true) ?: $defaults[0]['bg1'],
			'bg2'          => get_post_meta($post_id, '_cc_home_new_arrivals_bg_2', true) ?: $defaults[0]['bg2'],
			'productImage' => get_post_meta($post_id, '_cc_home_new_arrivals_product_image', true) ?: $defaults[0]['productImage'],
			'vendorLogo'   => get_post_meta($post_id, '_cc_home_new_arrivals_vendor_logo', true) ?: $defaults[0]['vendorLogo'],
			'vendorName'   => get_post_meta($post_id, '_cc_home_new_arrivals_vendor_name', true) ?: $defaults[0]['vendorName'],
			'title'        => get_post_meta($post_id, '_cc_home_new_arrivals_product_title', true) ?: $defaults[0]['title'],
			'btnText'      => get_post_meta($post_id, '_cc_home_new_arrivals_btn_text', true) ?: $defaults[0]['btnText'],
			'link'         => get_post_meta($post_id, '_cc_home_new_arrivals_btn_link', true) ?: $defaults[0]['link'],
		);
	}

	return array_map('cc_home_sanitize_new_arrivals_slide', $defaults);
}

/** Render repeatable New Arrivals slide controls in the homepage meta box. */
function cc_render_home_new_arrivals_slides_fields($post_id, $img_base)
{
	$slides = cc_get_home_new_arrivals_slides($post_id, $img_base);
?>
	<input type="hidden" name="_cc_home_new_arrivals_slides_present" value="1">
	<p style="margin:6px 0 10px;color:#646970;font-size:12px">
		<?php esc_html_e('Add and remove the slides shown inside the New Arrivals slider. Use full image URLs from the Media Library.', 'consucorner'); ?>
	</p>
	<div id="cc-new-arrivals-slides">
		<?php foreach ($slides as $index => $slide) : ?>
			<?php cc_render_home_new_arrivals_slide_row($index, $slide); ?>
		<?php endforeach; ?>
	</div>
	<p>
		<button type="button" class="button" id="cc-add-new-arrivals-slide"><?php esc_html_e('Add Slide', 'consucorner'); ?></button>
	</p>
	<script type="text/template" id="cc-new-arrivals-slide-template">
		<?php cc_render_home_new_arrivals_slide_row('__i__', array()); ?>
	</script>
	<script>
		(function() {
			var wrap = document.getElementById('cc-new-arrivals-slides');
			var add = document.getElementById('cc-add-new-arrivals-slide');
			var tpl = document.getElementById('cc-new-arrivals-slide-template');
			if (!wrap || !add || !tpl) return;

			add.addEventListener('click', function() {
				var index = 'new_' + Date.now();
				var holder = document.createElement('div');
				holder.innerHTML = tpl.innerHTML.replace(/__i__/g, index);
				wrap.appendChild(holder.firstElementChild);
			});

			wrap.addEventListener('click', function(event) {
				if (!event.target.classList.contains('cc-remove-new-arrivals-slide')) return;
				event.preventDefault();
				var row = event.target.closest('.cc-new-arrivals-slide');
				if (row) row.remove();
			});
		})();
	</script>
<?php
}

/** Render one New Arrivals slide row. */
function cc_render_home_new_arrivals_slide_row($index, $slide)
{
	$slide = cc_home_sanitize_new_arrivals_slide($slide);
	$name  = '_cc_home_new_arrivals_slides[' . $index . ']';
?>
	<div class="cc-new-arrivals-slide" style="border:1px solid #dcdcde;background:#fff;padding:10px 12px;margin:10px 0;border-radius:4px">
		<p style="display:flex;align-items:center;justify-content:space-between;margin:0 0 8px">
			<strong><?php echo esc_html(sprintf(__('Slide %s', 'consucorner'), is_numeric($index) ? ((int) $index + 1) : '#')); ?></strong>
			<button type="button" class="button-link-delete cc-remove-new-arrivals-slide"><?php esc_html_e('Remove slide', 'consucorner'); ?></button>
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Product title', 'consucorner'); ?></label>
			<input type="text" name="<?php echo esc_attr($name); ?>[title]" value="<?php echo esc_attr($slide['title']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Vendor name', 'consucorner'); ?></label>
			<input type="text" name="<?php echo esc_attr($name); ?>[vendorName]" value="<?php echo esc_attr($slide['vendorName']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Product image URL', 'consucorner'); ?></label>
			<input type="url" name="<?php echo esc_attr($name); ?>[productImage]" value="<?php echo esc_url($slide['productImage']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Vendor logo URL', 'consucorner'); ?></label>
			<input type="url" name="<?php echo esc_attr($name); ?>[vendorLogo]" value="<?php echo esc_url($slide['vendorLogo']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Button text', 'consucorner'); ?></label>
			<input type="text" name="<?php echo esc_attr($name); ?>[btnText]" value="<?php echo esc_attr($slide['btnText']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Button link', 'consucorner'); ?></label>
			<input type="url" name="<?php echo esc_attr($name); ?>[link]" value="<?php echo esc_url($slide['link']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Shape image 1 URL', 'consucorner'); ?></label>
			<input type="url" name="<?php echo esc_attr($name); ?>[bg1]" value="<?php echo esc_url($slide['bg1']); ?>" style="width:100%;font-size:13px">
		</p>
		<p style="margin:6px 0">
			<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px"><?php esc_html_e('Shape image 2 URL', 'consucorner'); ?></label>
			<input type="url" name="<?php echo esc_attr($name); ?>[bg2]" value="<?php echo esc_url($slide['bg2']); ?>" style="width:100%;font-size:13px">
		</p>
	</div>
<?php
}

/** Save repeatable New Arrivals slides. */
function cc_save_home_new_arrivals_slides($post_id)
{
	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	if (! isset($_POST['_cc_home_new_arrivals_slides_present'])) {
		return;
	}

	$slides = array();
	$raw    = isset($_POST['_cc_home_new_arrivals_slides']) ? wp_unslash($_POST['_cc_home_new_arrivals_slides']) : array();
	if (is_array($raw)) {
		foreach ($raw as $slide) {
			$slide = cc_home_sanitize_new_arrivals_slide($slide);
			if ($slide['productImage'] || $slide['vendorLogo'] || $slide['vendorName'] || $slide['title']) {
				$slides[] = $slide;
			}
		}
	}

	update_post_meta($post_id, '_cc_home_new_arrivals_slides', $slides);
}

/** Print a Forminator form reference for migrated public lead forms. */
function cc_meta_forminator_reference($option_name, $label)
{
	$form_id      = absint(get_option($option_name));
	$edit_url     = $form_id ? admin_url('admin.php?page=forminator-cform-wizard&id=' . $form_id) : '';
	$entries_url  = $form_id ? admin_url('admin.php?page=forminator-entries&form_type=forminator_forms&form_id=' . $form_id) : '';
	$shortcode    = $form_id ? sprintf('[forminator_form id="%d"]', $form_id) : __('Form not configured yet.', 'consucorner');
	$has_plugin   = shortcode_exists('forminator_form');
	$notice_color = $has_plugin && $form_id ? '#f0f6f5' : '#fff8e5';
	$border_color = $has_plugin && $form_id ? '#00c8b3' : '#dba617';
?>
	<div style="margin:8px 0 12px;padding:10px;border:1px solid <?php echo esc_attr($border_color); ?>;border-left-width:4px;background:<?php echo esc_attr($notice_color); ?>">
		<p style="margin:0 0 6px;font-weight:600"><?php echo esc_html($label); ?></p>
		<p style="margin:0 0 8px;color:#646970;font-size:12px">
			<?php esc_html_e('The actual form fields, emails, integrations, and submissions are managed in Forminator.', 'consucorner'); ?>
		</p>
		<input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" style="width:100%;font-size:13px;background:#fff">
		<?php if (! $has_plugin) : ?>
			<p style="margin:8px 0 0;color:#8a6d1d;font-size:12px"><?php esc_html_e('Forminator is not active, so this form cannot render on the frontend.', 'consucorner'); ?></p>
		<?php elseif ($form_id) : ?>
			<p style="margin:8px 0 0;font-size:12px">
				<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit form in Forminator', 'consucorner'); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url($entries_url); ?>"><?php esc_html_e('View submissions', 'consucorner'); ?></a>
			</p>
		<?php endif; ?>
	</div>
<?php
}

/** Print taxonomy term checkboxes for page filter configuration. */
function cc_meta_term_checkboxes($post_id, $key, $label, $taxonomy, $helper_text = '')
{
	$saved = get_post_meta($post_id, $key, true);
	$saved = is_array($saved) ? array_map('absint', $saved) : array();
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if (is_wp_error($terms) || empty($terms)) {
		return;
	}
?>
	<div style="margin:10px 0">
		<p style="margin:0 0 4px;font-weight:600;font-size:12px"><?php echo esc_html($label); ?></p>
		<?php if ($helper_text) : ?>
			<p style="margin:0 0 8px;color:#646970;font-size:12px"><?php echo esc_html($helper_text); ?></p>
		<?php endif; ?>
		<div style="max-height:190px;overflow:auto;border:1px solid #dcdcde;border-radius:4px;padding:8px;background:#fff">
			<?php foreach ($terms as $term) : ?>
				<label style="display:block;margin:4px 0;font-size:12px">
					<input type="checkbox" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array((int) $term->term_id, $saved, true)); ?>>
					<?php echo esc_html($term->name); ?>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
<?php
}

/**
 * Save a list of meta keys from $_POST.
 * Call this inside each page's save_post callback.
 */
function cc_save_meta_fields($post_id, array $keys)
{
	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	foreach ($keys as $key) {
		if (array_key_exists($key, $_POST)) {
			// wp_kses_post allows safe HTML tags (span, strong, a, ul, li …)
			update_post_meta($post_id, $key, wp_kses_post(wp_unslash($_POST[$key])));
		}
	}
}

/* =============================================================
   REGISTER ALL META BOXES  (one per page template)
   ============================================================= */
add_action('add_meta_boxes', 'cc_register_meta_boxes', 10, 2);
function cc_register_meta_boxes($post_type, $post)
{
	if ('page' !== $post_type) {
		return;
	}
	$tpl = get_post_meta($post->ID, '_wp_page_template', true);

	$boxes = array(
		'page-about.php'          => array('cc-about-fields',   'Page Content — About',          'cc_render_about_meta_box'),
		'page-contact.php'        => array('cc-contact-fields', 'Page Content — Contact',        'cc_render_contact_meta_box'),
		'page-privacy-policy.php' => array('cc-pp-fields',      'Page Content — Privacy Policy', 'cc_render_pp_meta_box'),
		'page-vendor.php'         => array('cc-vendor-fields',  'Page Content — Vendor',         'cc_render_vendor_meta_box'),
		'page-archive-posts.php'  => array('cc-blog-archive-fields', 'Page Content — Blog Archive', 'cc_render_blog_archive_meta_box'),
		'page-faq.php'            => array('cc-faq-fields',     'Page Content — FAQ',            'cc_render_faq_meta_box'),
		'page-shop-instruments.php' => array('cc-shop-instruments-fields', 'Page Content — Shop Instruments', 'cc_render_shop_instruments_meta_box'),
		'page-shop-specialty.php' => array('cc-shop-specialty-fields', 'Page Content — Shop Specialty', 'cc_render_shop_specialty_meta_box'),
		'page-offers.php'         => array('cc-offers-fields', 'Page Content — Offers', 'cc_render_offers_meta_box'),
	);

	if (isset($boxes[$tpl])) {
		list($id, $title, $cb) = $boxes[$tpl];
		add_meta_box($id, $title, $cb, 'page', 'normal', 'high');
	}

	if ($post && absint(get_option('page_on_front')) === (int) $post->ID) {
		add_meta_box(
			'cc-front-page-fields',
			'Page Content — Homepage',
			'cc_render_front_page_meta_box',
			'page',
			'normal',
			'high'
		);
	}

	/* The WooCommerce My Account page is a regular WP page selected in WC
	   settings (no custom template), so detect it by ID and attach the
	   Report & Support Forminator form picker here. */
	if ($post && cc_is_wc_myaccount_page((int) $post->ID)) {
		add_meta_box(
			'cc-myaccount-fields',
			'Page Content — My Account',
			'cc_render_myaccount_meta_box',
			'page',
			'normal',
			'high'
		);
	}
}

/** True when the given page ID is the WooCommerce My Account page. */
function cc_is_wc_myaccount_page($post_id)
{
	$post_id = absint($post_id);
	if (! $post_id) {
		return false;
	}
	$wc_id = absint(get_option('woocommerce_myaccount_page_id'));
	return $wc_id && $wc_id === $post_id;
}

/* =============================================================
   HOMEPAGE  (front-page.php)
   ============================================================= */

/**
 * Default homepage testimonial cards (used by meta box + frontend fallbacks).
 *
 * @return array<int, array{name:string,text:string,rating:string}>
 */
function cc_home_testimonial_defaults()
{
	return array(
		1 => array(
			'name'   => 'DR. Khalid Elbeltagui',
			'text'   => 'اول مره اري موقع مصري مشرف ومحترم متخصص في هذا المجال. الاسعار مقبوله. طريقة عرض الآلات سلسه وواضحة الخصائص. اتمني أن ينجح هذا الموقع لانه فعلا رائد في هذا المجال. شكرا لاصحاب هذا الموقع',
			'rating' => '★ Rated 4.8/5',
		),
		2 => array(
			'name'   => 'DR. Shady Abd Elsalam',
			'text'   => 'تجربة الشراء كانت مريحة وسريعة. قدرت أوصل للمنتج اللي محتاجه بسهولة. الأسعار معقولة والخدمة محترمة',
			'rating' => '★ Rated 4.8/5',
		),
		3 => array(
			'name'   => 'DR. Salah Helmy',
			'text'   => 'أكتر حاجة عجبتني البساطة. الموقع مرتب والمعلومات واضحة. وكمان الأسعار معقولة جدا',
			'rating' => '★ Rated 4.8/5',
		),
	);
}

function cc_render_front_page_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id       = $post->ID;
	$img_base = get_template_directory_uri() . '/assets/images/';

	cc_meta_section('Hero Text + Payment');
	cc_meta_text($id, '_cc_home_hero_title_blue', 'Hero title line 1', "Egypt's Medical");
	cc_meta_text($id, '_cc_home_hero_title_gradient', 'Hero title line 2', 'Marketplace');
	cc_meta_text($id, '_cc_home_hero_subtitle', 'Hero subtitle', 'Tools For Every Specialty');
	cc_meta_text($id, '_cc_home_payment_title', 'Payment block title', 'Flexible & Secure Payment Options');
	cc_meta_textarea($id, '_cc_home_payment_text', 'Payment block description', 'Choose the payment method that works best for you - including cash on delivery and online payment options - for a seamless checkout experience', 3);
	cc_meta_text($id, '_cc_home_hero_bg_image', 'Hero background image URL', '');
	cc_meta_text($id, '_cc_home_payment_logo_1', 'Payment logo 1 URL', $img_base . 'mastercard.png');
	cc_meta_text($id, '_cc_home_payment_logo_2', 'Payment logo 2 URL', $img_base . 'visa.png');

	cc_meta_section('Hero Banner Slides');
	$banner_defaults = array(
		1 => array('Future is here', 'Shop Now With<br />Premium<br />Quality', 'Shop Now', home_url('/shop/'), $img_base . 'product banner.png'),
		2 => array('Fast Delivery', 'Explore Global<br />Brands at<br />Your Doorstep', 'Shop Brands', home_url('/shop/'), $img_base . 'product banner.png'),
		3 => array('Secure Payments', 'Reliable Checkouts<br />For Every<br />Order', 'Order Now', home_url('/shop/'), $img_base . 'product banner.png'),
	);
	$banner_tag_icons = function_exists('consucorner_hero_banner_default_tag_icons') ? consucorner_hero_banner_default_tag_icons() : array();
	foreach ($banner_defaults as $n => $b) {
		echo '<p style="margin:10px 0 2px;font-size:12px;color:#555"><em>— Banner Slide ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_home_banner_{$n}_tag", 'Tag text', $b[0]);
		cc_meta_fa_icon_picker($id, "_cc_home_banner_{$n}_tag_icon", 'Tag icon (white on banner)', $banner_tag_icons[ $n ] ?? 'fa-solid fa-circle-info');
		cc_meta_text($id, "_cc_home_banner_{$n}_title", 'Title (HTML ok)', $b[1]);
		cc_meta_text($id, "_cc_home_banner_{$n}_btn_text", 'Button text', $b[2]);
		cc_meta_text($id, "_cc_home_banner_{$n}_btn_link", 'Button link', $b[3]);
		cc_meta_text($id, "_cc_home_banner_{$n}_image", 'Product image URL', $b[4]);
	}

	cc_meta_section('Popular Categories + Browse Specialty');
	cc_meta_text($id, '_cc_home_categories_title', 'Popular categories title', 'Popular Categories');
	cc_meta_text($id, '_cc_home_category_btn_text', 'Category card button text', 'Shop Now');
	cc_meta_text($id, '_cc_home_category_fallback_image', 'Category fallback image URL', $img_base . 'product%20demo.png');
	cc_meta_text($id, '_cc_home_browse_title', 'Browse specialty title (HTML ok)', 'Browse Medical Tools by<br />Your Specialty');
	cc_meta_text($id, '_cc_home_browse_loading_text', 'Browse grid loading text', 'Loading products...');
	cc_meta_text($id, '_cc_home_browse_btn_text', 'Browse button text', 'Shop All');
	cc_meta_section('Medical Products Banners');
	cc_meta_text($id, '_cc_home_mid_banner_bg', 'Top medical products banner image URL', $img_base . 'Banner Section.webp');

	cc_meta_section('New Arrivals Promo');
	cc_meta_text($id, '_cc_home_new_arrivals_title', 'Section title (HTML ok)', 'New <span>Arrivals</span>');
	cc_render_home_new_arrivals_slides_fields($id, $img_base);

	cc_meta_section('Collection + Vector Banner');
	cc_meta_text($id, '_cc_home_collection_title', 'Collection title (HTML ok)', "DON'T MISS OUR <span>COLLECTION</span>");
	for ($n = 1; $n <= 3; $n++) {
		echo '<p style="margin:10px 0 2px;font-size:12px;color:#555"><em>— Collection Card ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_home_collection_{$n}_badge", 'Badge text', 3 === $n ? 'Category Name' : 'Category');
		cc_meta_text($id, "_cc_home_collection_{$n}_title", 'Card title (HTML ok)', 3 === $n ? 'Product Name' : 'Product<br />Name');
		cc_meta_text($id, "_cc_home_collection_{$n}_image", 'Image URL', $img_base . (1 === $n ? 'product 3.png' : (2 === $n ? 'product 2.png' : 'product 1.png')));
		if ($n < 3) {
			cc_meta_text($id, "_cc_home_collection_{$n}_link", 'Card link URL', home_url('/shop/'));
		}
	}
	cc_meta_text($id, '_cc_home_collection_btn_text', 'Card 3 button text', 'SHOP NOW →');
	cc_meta_text($id, '_cc_home_collection_btn_link', 'Card 3 button link', home_url('/shop/'));
	cc_meta_text($id, '_cc_home_vector_banner_image', 'Vector banner image URL', $img_base . 'Vector (1).png');
	cc_meta_text($id, '_cc_home_vector_banner_link', 'Banner link URL (optional — makes the image clickable)', '');

	cc_meta_section('Dynamic Product Sections');
	cc_meta_text($id, '_cc_home_bestsellers_title', 'Bestsellers title', 'Bestsellers');
	cc_meta_text($id, '_cc_home_bestsellers_loading_text', 'Bestsellers loading text', 'Loading bestsellers...');
	if (function_exists('cc_render_home_bestsellers_product_picker')) {
		cc_render_home_bestsellers_product_picker($id);
	}
	cc_meta_text($id, '_cc_home_recommended_title', 'Recommended title', 'Recommended For You');
	cc_meta_text($id, '_cc_home_recommended_loading_text', 'Recommended loading text', 'Loading recommendations...');
	cc_meta_text($id, '_cc_home_recommended_btn_text', 'Recommended button text', 'All Specialties');
	cc_meta_text($id, '_cc_home_recommended_btn_link', 'Recommended button link', home_url('/shop/'));
	cc_meta_text($id, '_cc_home_bottom_banner_bg', 'Bottom medical products banner image URL', $img_base . 'Banner Section.webp');

	cc_meta_section('Fast Delivery');
	cc_meta_text($id, '_cc_home_fast_title_teal', 'Title teal line', 'Fast Delivery &');
	cc_meta_text($id, '_cc_home_fast_title_black', 'Title black line', 'Safe Packaging');
	cc_meta_textarea($id, '_cc_home_fast_desc', 'Description', 'At the heart of our brand lies a passion for redefining how we experience time. As the sculptors of time and innovation, we are dedicated to crafting smart watches that seamlessly blend artistry', 3);
	cc_meta_text($id, '_cc_home_fast_btn_text', 'Button text', 'Read More');
	cc_meta_text($id, '_cc_home_fast_btn_link', 'Button link', home_url('/shop/'));
	cc_meta_text($id, '_cc_home_fast_shape_1', 'Shape image left URL', $img_base . 'Rectangle baby blue left.svg');
	cc_meta_text($id, '_cc_home_fast_shape_2', 'Shape image middle URL', $img_base . 'Rectangle blue location.svg');
	cc_meta_text($id, '_cc_home_fast_shape_3', 'Shape image right URL', $img_base . 'Rectangle baby blue right.svg');
	cc_meta_text($id, '_cc_home_fast_image', 'Main image URL', $img_base . 'location.png');

	cc_meta_section('Testimonials');
	echo '<p style="margin:4px 0 8px;font-size:12px;color:#666">Edit the three homepage testimonial cards below. Changes appear on the homepage slider after you update the page.</p>';
	cc_meta_text($id, '_cc_home_testimonials_label', 'Label', 'Testimonials');
	cc_meta_text($id, '_cc_home_testimonials_title', 'Title (HTML ok)', 'What Our Customers<br />Say About Us');
	cc_meta_text($id, '_cc_home_testimonials_stars', 'Stars text', '★★★★★');
	cc_meta_text($id, '_cc_home_testimonials_rating_text', 'Rating text', 'Trusted by Healthcare professional');
	$cc_home_review_defaults = cc_home_testimonial_defaults();
	for ($n = 1; $n <= 3; $n++) {
		$cc_review_default = isset($cc_home_review_defaults[$n]) ? $cc_home_review_defaults[$n] : array(
			'name'   => 'ConsuCorner Customer',
			'text'   => '',
			'rating' => '★ Rated 4.8/5',
		);
		echo '<p style="margin:10px 0 2px;font-size:12px;color:#555"><em>— Review ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_home_review_{$n}_name", 'Reviewer name', $cc_review_default['name']);
		cc_meta_textarea($id, "_cc_home_review_{$n}_text", 'Review text', $cc_review_default['text'], 3);
		cc_meta_text($id, "_cc_home_review_{$n}_rating", 'Rating text', $cc_review_default['rating']);
	}
}

add_action('save_post', 'cc_save_front_page_meta');
function cc_save_front_page_meta($post_id)
{
	if (absint(get_option('page_on_front')) !== (int) $post_id) {
		return;
	}

	$keys = array(
		'_cc_home_hero_title_blue',
		'_cc_home_hero_title_gradient',
		'_cc_home_hero_subtitle',
		'_cc_home_payment_title',
		'_cc_home_payment_text',
		'_cc_home_hero_bg_image',
		'_cc_home_payment_logo_1',
		'_cc_home_payment_logo_2',
		'_cc_home_categories_title',
		'_cc_home_category_btn_text',
		'_cc_home_category_fallback_image',
		'_cc_home_browse_title',
		'_cc_home_browse_loading_text',
		'_cc_home_browse_btn_text',
		'_cc_home_mid_banner_bg',
		'_cc_home_new_arrivals_title',
		'_cc_home_new_arrivals_bg_1',
		'_cc_home_new_arrivals_bg_2',
		'_cc_home_new_arrivals_product_image',
		'_cc_home_new_arrivals_vendor_logo',
		'_cc_home_new_arrivals_vendor_name',
		'_cc_home_new_arrivals_product_title',
		'_cc_home_new_arrivals_btn_text',
		'_cc_home_new_arrivals_btn_link',
		'_cc_home_collection_title',
		'_cc_home_collection_1_link',
		'_cc_home_collection_2_link',
		'_cc_home_collection_btn_text',
		'_cc_home_collection_btn_link',
		'_cc_home_vector_banner_image',
		'_cc_home_vector_banner_link',
		'_cc_home_bestsellers_title',
		'_cc_home_bestsellers_loading_text',
		'_cc_home_recommended_title',
		'_cc_home_recommended_loading_text',
		'_cc_home_recommended_btn_text',
		'_cc_home_recommended_btn_link',
		'_cc_home_bottom_banner_bg',
		'_cc_home_fast_title_teal',
		'_cc_home_fast_title_black',
		'_cc_home_fast_desc',
		'_cc_home_fast_btn_text',
		'_cc_home_fast_btn_link',
		'_cc_home_fast_shape_1',
		'_cc_home_fast_shape_2',
		'_cc_home_fast_shape_3',
		'_cc_home_fast_image',
		'_cc_home_testimonials_label',
		'_cc_home_testimonials_title',
		'_cc_home_testimonials_stars',
		'_cc_home_testimonials_rating_text',
	);
	for ($n = 1; $n <= 3; $n++) {
		$keys[] = "_cc_home_banner_{$n}_tag";
		$keys[] = "_cc_home_banner_{$n}_tag_icon";
		$keys[] = "_cc_home_banner_{$n}_title";
		$keys[] = "_cc_home_banner_{$n}_btn_text";
		$keys[] = "_cc_home_banner_{$n}_btn_link";
		$keys[] = "_cc_home_banner_{$n}_image";
		$keys[] = "_cc_home_collection_{$n}_badge";
		$keys[] = "_cc_home_collection_{$n}_title";
		$keys[] = "_cc_home_collection_{$n}_image";
	}
	for ($n = 1; $n <= 3; $n++) {
		$keys[] = "_cc_home_review_{$n}_name";
		$keys[] = "_cc_home_review_{$n}_text";
		$keys[] = "_cc_home_review_{$n}_rating";
	}

	cc_save_meta_fields($post_id, $keys);
	cc_save_home_new_arrivals_slides($post_id);
	if (function_exists('cc_save_home_bestsellers_product_ids')) {
		cc_save_home_bestsellers_product_ids($post_id);
	}
}

/* =============================================================
   MY ACCOUNT  (WooCommerce My Account page)
   ============================================================= */

/**
 * Render the My Account meta box: lets the admin pick which Forminator
 * form powers the Report & Support modal inside the profile dashboard.
 *
 * The form ID lives in the `consucorner_forminator_report_form_id` option
 * (not in post meta) so it stays consistent with the existing Contact and
 * Vendor Forminator settings.
 */
function cc_render_myaccount_meta_box($post)
{
	wp_nonce_field('_cc_save_myaccount_meta', '_cc_myaccount_meta_nonce');

	$current_id = absint(get_option('consucorner_forminator_report_form_id'));
	cc_meta_section('Report & Support — Forminator Form');
?>
	<p style="margin:6px 0">
		<label for="consucorner_forminator_report_form_id" style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">
			<?php esc_html_e('Forminator form ID', 'consucorner'); ?>
		</label>
		<input
			type="number"
			min="0"
			step="1"
			id="consucorner_forminator_report_form_id"
			name="consucorner_forminator_report_form_id"
			value="<?php echo esc_attr($current_id); ?>"
			style="width:160px;font-size:13px" />
		<span style="color:#646970;font-size:12px;margin-left:8px">
			<?php esc_html_e('Set to 0 to disable the form.', 'consucorner'); ?>
		</span>
	</p>
<?php
	cc_meta_forminator_reference('consucorner_forminator_report_form_id', __('Report & Support form', 'consucorner'));
}

/** Save the Forminator form ID picker on the WC My Account page. */
add_action('save_post_page', 'cc_save_myaccount_meta');
function cc_save_myaccount_meta($post_id)
{
	if (
		! isset($_POST['_cc_myaccount_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_myaccount_meta_nonce']), '_cc_save_myaccount_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	if (! cc_is_wc_myaccount_page((int) $post_id)) {
		return;
	}

	$form_id = isset($_POST['consucorner_forminator_report_form_id'])
		? absint(wp_unslash($_POST['consucorner_forminator_report_form_id']))
		: 0;
	update_option('consucorner_forminator_report_form_id', $form_id);
}

/* =============================================================
   ABOUT  (/about)
   ============================================================= */

function cc_render_about_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_about_head_title',       'Title',       'About Consucorner');
	cc_meta_text($id, '_cc_about_head_breadcrumbs', 'Breadcrumbs', 'Home/About');

	cc_meta_section('About Us Section');
	cc_meta_text($id, '_cc_about_us_tag',   'Tag',   'About Us');
	cc_meta_text($id, '_cc_about_us_title', 'Title (HTML ok)', 'CONSU<span>CORNER</span>');
	cc_meta_textarea(
		$id,
		'_cc_about_us_text_1',
		'Paragraph 1',
		"Our Goal is to make ConsuCorner the medical hub for professionals across every specialty — offering clarity, speed, and trusted tools in one organized platform."
	);
	cc_meta_textarea(
		$id,
		'_cc_about_us_text_2',
		'Paragraph 2',
		"We're building ConsuCorner to become a multi-specialty platform, covering a full range of medical fields — from ophthalmology to dental, ENT, and beyond."
	);
	cc_meta_textarea(
		$id,
		'_cc_about_us_text_3',
		'Paragraph 3',
		"Each specialty will be launched with the same care, organization, and commitment to compliance that define our current ophthalmology offerings."
	);
	cc_meta_textarea(
		$id,
		'_cc_about_us_text_4',
		'Paragraph 4',
		"Today, ConsuCorner is your go-to destination for trusted eye care tools — from surgical instruments and diagnostic devices to sterile consumables that are all carefully selected and clearly presented for professionals who don't have time to waste."
	);

	cc_meta_section('What Makes Us Different');
	cc_meta_text($id, '_cc_about_diff_tag',   'Tag',   'Why choose us');
	cc_meta_text($id, '_cc_about_diff_title', 'Title (HTML ok)', 'What Makes Us <span>Different</span>');

	$diff_defaults = array(
		1 => array('Professionalism',    'We serve healthcare professionals with care, respect, and a clear understanding of their daily needs.',            'professionalism-icon.png'),
		2 => array('Trust',              "We only offer products we'd trust ourselves — reliable, compliant, and carefully chosen to support real-world medical work.", 'trust-icon.png'),
		3 => array('Clarity',            'We keep things simple and clear. No clutter, no confusion — just well-organized tools and supplies that speak for themselves.', 'clarity-icon.png'),
		4 => array('Speed',              "We respect your time. Our platform is built to help you find what you need quickly, so you can focus on what really matters — your patients.", 'speed-icon.png'),
		5 => array('Integrity & Compliance', 'We do things the right way, every time. Each product and specialty is added with full attention to medical standards and ethical practices.', 'integrity-icon.png'),
		6 => array('Specialty-Focused',  'We grow with purpose — building spaces that truly meet the needs of each medical field, one specialty at a time.', 'specialty-icon.png'),
	);
	foreach ($diff_defaults as $n => $d) {
		echo '<p style="margin:8px 0 2px;font-size:12px;color:#555"><em>— Card ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_about_diff_{$n}_title", "Title",    $d[0]);
		cc_meta_textarea($id, "_cc_about_diff_{$n}_desc",  "Description", $d[1]);
		cc_meta_text($id, "_cc_about_diff_{$n}_icon",  "Icon filename (e.g. professionalism-icon.png)", $d[2]);
	}

	cc_meta_section('Mission');
	cc_meta_text($id, '_cc_about_mission_title', 'Title (HTML ok)', '<span>Our</span> Mission');
	cc_meta_textarea(
		$id,
		'_cc_about_mission_text',
		'Text',
		"To simplify the medical supply process for healthcare providers across Egypt — offering a trusted platform with reliable brands, organized ordering system, and all the products they need in one marketplace."
	);

	cc_meta_section('Vision');
	cc_meta_text($id, '_cc_about_vision_title', 'Title (HTML ok)', '<span>Our</span> Vision');
	cc_meta_textarea(
		$id,
		'_cc_about_vision_text',
		'Text',
		'To build a trusted medical hub where finding, ordering, and receiving supplies is always simple, smooth, and within reach.'
	);

	cc_meta_section('Core Values');
	cc_meta_text($id, '_cc_about_cv_title',    'Title (HTML ok)', '<span>Our Core</span> Values');
	cc_meta_text($id, '_cc_about_cv_subtitle', 'Subtitle', 'ConsuCorner is built on four values that guide everything we do:');

	$cv_defaults = array(
		1 => array('Organized Access',   'All your medical tools in one reliable platform — sorted, searchable, and updated.',             'organized-icon.png'),
		2 => array('Verified Quality',   'We only work with licensed, verified suppliers. Every product is traceable and certified.',      'verified-icon.png'),
		3 => array('Smooth Operations',  'All your medical tools in one reliable platform — sorted, searchable, and updated.',             'smooth-icon.png'),
		4 => array('Compliance-Ready',   'We only work with licensed, verified suppliers. Every product is traceable and certified.',      'compliance-icon.png'),
	);
	foreach ($cv_defaults as $n => $d) {
		echo '<p style="margin:8px 0 2px;font-size:12px;color:#555"><em>— Value Card ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_about_cv_{$n}_title", 'Title',       $d[0]);
		cc_meta_textarea($id, "_cc_about_cv_{$n}_desc",  'Description', $d[1]);
		cc_meta_text($id, "_cc_about_cv_{$n}_icon",  'Icon filename', $d[2]);
	}
}

add_action('save_post', 'cc_save_about_meta');
function cc_save_about_meta($post_id)
{
	$keys = array(
		'_cc_about_head_title',
		'_cc_about_head_breadcrumbs',
		'_cc_about_us_tag',
		'_cc_about_us_title',
		'_cc_about_us_text_1',
		'_cc_about_us_text_2',
		'_cc_about_us_text_3',
		'_cc_about_us_text_4',
		'_cc_about_diff_tag',
		'_cc_about_diff_title',
		'_cc_about_mission_title',
		'_cc_about_mission_text',
		'_cc_about_vision_title',
		'_cc_about_vision_text',
		'_cc_about_cv_title',
		'_cc_about_cv_subtitle',
	);
	for ($n = 1; $n <= 6; $n++) {
		$keys[] = "_cc_about_diff_{$n}_title";
		$keys[] = "_cc_about_diff_{$n}_desc";
		$keys[] = "_cc_about_diff_{$n}_icon";
	}
	for ($n = 1; $n <= 4; $n++) {
		$keys[] = "_cc_about_cv_{$n}_title";
		$keys[] = "_cc_about_cv_{$n}_desc";
		$keys[] = "_cc_about_cv_{$n}_icon";
	}
	cc_save_meta_fields($post_id, $keys);
}

/* =============================================================
   CONTACT  (/contact)
   ============================================================= */

function cc_render_contact_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_contact_head_title',       'Title',       'Contact Us');
	cc_meta_text($id, '_cc_contact_head_breadcrumbs', 'Breadcrumbs', 'Home/Contact Us');

	cc_meta_section('Forminator Form');
	cc_meta_forminator_reference('consucorner_forminator_contact_form_id', 'Contact form');
	cc_meta_text($id, '_cc_contact_form_title', 'Form heading (HTML ok)', 'Get in <span>Touch</span>');
	cc_meta_textarea(
		$id,
		'_cc_contact_form_desc',
		'Form description',
		"Whether you have a question, need support with an order, or want to request a product you don't see on the site."
	);

	cc_meta_section('Map');
	cc_meta_text(
		$id,
		'_cc_contact_map_src',
		'Map iframe src URL',
		'https://www.openstreetmap.org/export/embed.html?bbox=31.3180%2C30.0820%2C31.3380%2C30.0960&layer=mapnik&marker=30.0890%2C31.3280'
	);

	cc_meta_section('Contact Info — Phone');
	cc_meta_text($id, '_cc_contact_phone_label', 'Label', 'PHONE');
	cc_meta_text($id, '_cc_contact_phone_value', 'Display value', '01555458555');
	cc_meta_text($id, '_cc_contact_phone_href',  'href (tel:…)', 'tel:01555458555');

	cc_meta_section('Contact Info — Email');
	cc_meta_text($id, '_cc_contact_email_label', 'Label', 'EMAIL');
	cc_meta_text($id, '_cc_contact_email_value', 'Display value', 'info@consucorner.com');
	cc_meta_text($id, '_cc_contact_email_href',  'href (mailto:…)', 'mailto:info@consucorner.com');

	cc_meta_section('Contact Info — Location');
	cc_meta_text($id, '_cc_contact_loc_label', 'Label', 'Location');
	cc_meta_textarea(
		$id,
		'_cc_contact_loc_value',
		'Address',
		'7 Obour Buildings, Salah Salem St., Heliopolis Cairo, 4460020, Egypt'
	);
}

add_action('save_post', 'cc_save_contact_meta');
function cc_save_contact_meta($post_id)
{
	cc_save_meta_fields($post_id, array(
		'_cc_contact_head_title',
		'_cc_contact_head_breadcrumbs',
		'_cc_contact_form_title',
		'_cc_contact_form_desc',
		'_cc_contact_map_src',
		'_cc_contact_phone_label',
		'_cc_contact_phone_value',
		'_cc_contact_phone_href',
		'_cc_contact_email_label',
		'_cc_contact_email_value',
		'_cc_contact_email_href',
		'_cc_contact_loc_label',
		'_cc_contact_loc_value',
	));
}

/* =============================================================
   FAQ  (/faq)
   ============================================================= */

function cc_render_faq_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_faq_head_title', 'Title', 'Frequently Asked Questions');
	cc_meta_text($id, '_cc_faq_head_breadcrumbs', 'Breadcrumbs', 'Home/FAQ');

	cc_meta_section('Intro');
	cc_meta_text($id, '_cc_faq_intro_eyebrow', 'Eyebrow', 'Help Center');
	cc_meta_text($id, '_cc_faq_intro_title', 'Title (HTML ok)', 'Answers For A Smoother Medical Supply Experience');
	cc_meta_textarea(
		$id,
		'_cc_faq_intro_text',
		'Intro text',
		'Find quick answers about ordering, vendors, delivery, payments, returns, and using ConsuCorner for your medical purchasing workflow.',
		3
	);

	cc_meta_section('Support CTA');
	cc_meta_text($id, '_cc_faq_cta_title', 'Title', 'Still Need Help?');
	cc_meta_textarea(
		$id,
		'_cc_faq_cta_text',
		'Text',
		'Our support team can help with product requests, order questions, vendor onboarding, and marketplace guidance.',
		3
	);
	cc_meta_text($id, '_cc_faq_cta_button_text', 'Button text', 'Contact Support');
	cc_meta_text($id, '_cc_faq_cta_button_url', 'Button URL', home_url('/contact/'));

	$faqs = get_post_meta($id, '_cc_faq_items', true);
	if (! is_array($faqs)) {
		$faqs = array();
	}

	if (empty($faqs)) {
		$faqs = array(
			array(
				'question' => 'How can I find the right medical product on ConsuCorner?',
				'answer'   => 'Use the search bar, browse by specialty, or open the Shop mega menu to filter products by category, specialty, and procedure.',
			),
		);
	}
?>
	<style>
		.cc-faq-row {
			position: relative;
			margin: 12px 0;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			background: #fff;
		}

		.cc-faq-row label {
			display: block;
			margin: 0 0 4px;
			font-weight: 600;
			font-size: 12px;
		}

		.cc-faq-row input,
		.cc-faq-row textarea {
			width: 100%;
		}

		.cc-faq-actions {
			margin-top: 10px;
			display: flex;
			gap: 8px;
		}
	</style>

	<?php cc_meta_section('FAQ Questions'); ?>
	<p style="color:#646970;font-size:12px;margin:6px 0 10px">
		<?php esc_html_e('Add, remove, or reorder questions by editing these rows. Empty rows are ignored on save.', 'consucorner'); ?>
	</p>

	<div id="cc-faq-items">
		<?php foreach ($faqs as $index => $faq) : ?>
			<?php
			$question = isset($faq['question']) ? $faq['question'] : '';
			$answer   = isset($faq['answer']) ? $faq['answer'] : '';
			?>
			<div class="cc-faq-row">
				<p>
					<label><?php esc_html_e('Question', 'consucorner'); ?></label>
					<input type="text" name="_cc_faq_items[<?php echo esc_attr($index); ?>][question]" value="<?php echo esc_attr($question); ?>">
				</p>
				<p>
					<label><?php esc_html_e('Answer', 'consucorner'); ?> <span style="font-weight:400;color:#888">(<?php esc_html_e('HTML allowed', 'consucorner'); ?>)</span></label>
					<textarea name="_cc_faq_items[<?php echo esc_attr($index); ?>][answer]" rows="4"><?php echo esc_textarea($answer); ?></textarea>
				</p>
				<div class="cc-faq-actions">
					<button type="button" class="button cc-faq-remove"><?php esc_html_e('Remove Question', 'consucorner'); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" class="button button-secondary" id="cc-faq-add"><?php esc_html_e('Add Question', 'consucorner'); ?></button>
	</p>

	<script>
		(function() {
			var list = document.getElementById('cc-faq-items');
			var addButton = document.getElementById('cc-faq-add');
			if (!list || !addButton) {
				return;
			}

			function nextIndex() {
				return list.querySelectorAll('.cc-faq-row').length;
			}

			function bindRemove(button) {
				button.addEventListener('click', function() {
					var row = button.closest('.cc-faq-row');
					if (row) {
						row.remove();
					}
				});
			}

			list.querySelectorAll('.cc-faq-remove').forEach(bindRemove);

			addButton.addEventListener('click', function() {
				var index = nextIndex();
				var row = document.createElement('div');
				row.className = 'cc-faq-row';
				row.innerHTML = '<p><label><?php echo esc_js(__('Question', 'consucorner')); ?></label><input type="text" name="_cc_faq_items[' + index + '][question]" value=""></p>' +
					'<p><label><?php echo esc_js(__('Answer', 'consucorner')); ?> <span style="font-weight:400;color:#888">(<?php echo esc_js(__('HTML allowed', 'consucorner')); ?>)</span></label><textarea name="_cc_faq_items[' + index + '][answer]" rows="4"></textarea></p>' +
					'<div class="cc-faq-actions"><button type="button" class="button cc-faq-remove"><?php echo esc_js(__('Remove Question', 'consucorner')); ?></button></div>';
				list.appendChild(row);
				bindRemove(row.querySelector('.cc-faq-remove'));
			});
		})();
	</script>
<?php
}

add_action('save_post', 'cc_save_faq_meta');
function cc_save_faq_meta($post_id)
{
	cc_save_meta_fields($post_id, array(
		'_cc_faq_head_title',
		'_cc_faq_head_breadcrumbs',
		'_cc_faq_intro_eyebrow',
		'_cc_faq_intro_title',
		'_cc_faq_intro_text',
		'_cc_faq_cta_title',
		'_cc_faq_cta_text',
		'_cc_faq_cta_button_text',
		'_cc_faq_cta_button_url',
	));

	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$faqs = array();
	if (isset($_POST['_cc_faq_items']) && is_array($_POST['_cc_faq_items'])) {
		$raw_faqs = wp_unslash($_POST['_cc_faq_items']);
		foreach ($raw_faqs as $faq) {
			$question = isset($faq['question']) ? sanitize_text_field($faq['question']) : '';
			$answer   = isset($faq['answer']) ? wp_kses_post($faq['answer']) : '';

			if ('' === $question && '' === trim(wp_strip_all_tags($answer))) {
				continue;
			}

			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}
	}

	if ($faqs) {
		update_post_meta($post_id, '_cc_faq_items', $faqs);
	} else {
		delete_post_meta($post_id, '_cc_faq_items');
	}
}

/* =============================================================
   SHOP INSTRUMENTS  (/shop-instruments)
   ============================================================= */

function cc_render_shop_instruments_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_shop_instruments_head_title', 'Title', 'Shop Instruments');
	cc_meta_text($id, '_cc_shop_instruments_head_breadcrumbs', 'Breadcrumbs', 'Home/Shop Instruments');

	cc_meta_section('Instrument Filter Section');
	cc_meta_text($id, '_cc_shop_instruments_filter_title', 'Section title', 'Shop By Instrument');
	cc_meta_textarea(
		$id,
		'_cc_shop_instruments_filter_copy',
		'Section description',
		'Filter medical products by procedure or instrument type and quickly find the right tools for your specialty.',
		3
	);
	cc_meta_text($id, '_cc_shop_instruments_all_label', 'All instruments label', 'All Instruments');

	cc_meta_section('Product Results');
	cc_meta_text($id, '_cc_shop_instruments_products_title', 'Default results title', 'All Instrument Products');
	cc_meta_text($id, '_cc_shop_instruments_sidebar_title', 'Sidebar filters title', 'Filters');
	cc_meta_text($id, '_cc_shop_instruments_per_page', 'Products per carousel section (4-16)', '8');
	cc_meta_text($id, '_cc_shop_instruments_no_results', 'No results message', 'No products found for this instrument filter yet.');

	cc_meta_section('Static Design Section Labels');
	cc_meta_text($id, '_cc_shop_instruments_new_arrivals_title', 'New arrivals title (HTML ok)', 'New <span>Arrivals</span>');
	cc_meta_text($id, '_cc_shop_instruments_shop_now_text', 'Promo button text', 'Shop Now');
	cc_meta_text($id, '_cc_shop_instruments_bestsellers_title', 'Bestsellers title', 'Bestsellers');
	cc_meta_text($id, '_cc_shop_instruments_recommended_title', 'Recommended title', 'Recommended for you');

	cc_meta_section('New Arrivals Products');
	cc_meta_textarea(
		$id,
		'_cc_shop_instruments_new_arrival_product_ids',
		'Product IDs to show in New Arrivals',
		'',
		3
	);
?>
	<p style="margin:4px 0 10px;color:#646970;font-size:12px">
		<?php esc_html_e('Enter product IDs separated by commas or spaces. The order here controls the slider order. Leave empty to show latest instrument products automatically.', 'consucorner'); ?>
	</p>
	<?php

	$recent_products = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	if ($recent_products) :
	?>
		<div style="margin:8px 0 12px;padding:8px 10px;border:1px solid #dcdcde;border-radius:4px;background:#fff">
			<p style="margin:0 0 6px;font-weight:600;font-size:12px"><?php esc_html_e('Recent product IDs', 'consucorner'); ?></p>
			<?php foreach ($recent_products as $product_id) : ?>
				<p style="margin:3px 0;font-size:12px">
					<code><?php echo esc_html($product_id); ?></code>
					<?php echo esc_html(get_the_title($product_id)); ?>
				</p>
			<?php endforeach; ?>
		</div>
	<?php
	endif;

	cc_meta_section('Filter Controls');
	cc_meta_term_checkboxes(
		$id,
		'_cc_shop_instruments_procedure_ids',
		'Instrument filters to show',
		'procedure',
		'Leave all unchecked to automatically show every Procedure term, including new terms you create later.'
	);
	cc_meta_term_checkboxes(
		$id,
		'_cc_shop_instruments_category_ids',
		'Category filters to show in sidebar',
		'product_cat',
		'Leave all unchecked to automatically show every product category.'
	);
}

add_action('save_post', 'cc_save_shop_instruments_meta');
function cc_save_shop_instruments_meta($post_id)
{
	cc_save_meta_fields($post_id, array(
		'_cc_shop_instruments_head_title',
		'_cc_shop_instruments_head_breadcrumbs',
		'_cc_shop_instruments_filter_title',
		'_cc_shop_instruments_filter_copy',
		'_cc_shop_instruments_all_label',
		'_cc_shop_instruments_products_title',
		'_cc_shop_instruments_sidebar_title',
		'_cc_shop_instruments_per_page',
		'_cc_shop_instruments_no_results',
		'_cc_shop_instruments_new_arrivals_title',
		'_cc_shop_instruments_shop_now_text',
		'_cc_shop_instruments_bestsellers_title',
		'_cc_shop_instruments_recommended_title',
		'_cc_shop_instruments_new_arrival_product_ids',
	));

	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	foreach (array('_cc_shop_instruments_procedure_ids', '_cc_shop_instruments_category_ids') as $key) {
		if (isset($_POST[$key]) && is_array($_POST[$key])) {
			update_post_meta($post_id, $key, array_values(array_filter(array_map('absint', wp_unslash($_POST[$key])))));
		} else {
			delete_post_meta($post_id, $key);
		}
	}
}

/* =============================================================
   SHOP SPECIALTY  (/shop-specialty)
   ============================================================= */

function cc_shop_specialty_country_taxonomy()
{
	$taxonomy = function_exists('consucorner_country_origin_taxonomy') ? consucorner_country_origin_taxonomy() : 'country_of_origin';
	return taxonomy_exists($taxonomy) ? $taxonomy : '';
}

function cc_render_shop_specialty_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_shop_specialty_head_subtitle', 'Subtitle', 'Every Thing you Need in');
	cc_meta_text($id, '_cc_shop_specialty_head_title', 'Title', 'Ophthalmology');
	cc_meta_text($id, '_cc_shop_specialty_head_breadcrumbs', 'Breadcrumbs', 'Home/Ophthalmology');

	cc_meta_section('Instrument Filter Section');
	cc_meta_text($id, '_cc_shop_specialty_filter_title', 'Section title', 'Shop By Instrument');
	cc_meta_textarea(
		$id,
		'_cc_shop_specialty_filter_copy',
		'Section description',
		'Filter this specialty by instrument type and quickly find the right tools for your workflow.',
		3
	);
	cc_meta_text($id, '_cc_shop_specialty_all_label', 'All instruments label', 'All Instruments');

	cc_meta_section('Product Sections');
	cc_meta_text($id, '_cc_shop_specialty_per_page', 'Products per carousel section (4-16)', '8');
	cc_meta_text($id, '_cc_shop_specialty_no_results', 'No results message', 'No products found for this specialty filter yet.');
	cc_meta_text($id, '_cc_shop_specialty_bestsellers_title', 'Bestsellers title', 'Bestsellers');
	cc_meta_text($id, '_cc_shop_specialty_recommended_title', 'Recommended title', 'Recommended for you');
	cc_meta_text($id, '_cc_shop_specialty_new_arrivals_title', 'New arrivals title (HTML ok)', 'New <span>Arrivals</span>');
	cc_meta_text($id, '_cc_shop_specialty_shop_now_text', 'Promo button text', 'Shop Now');
	cc_meta_text($id, '_cc_shop_specialty_brands_title', 'Brands title (HTML ok)', '<span class="green-text">Popular</span> Brands');
	cc_meta_text($id, '_cc_shop_specialty_country_title', 'Country title (HTML ok)', '<span class="teal-text">Country</span> of Origin');

	cc_meta_section('New Arrivals Products');
	cc_meta_textarea(
		$id,
		'_cc_shop_specialty_new_arrival_product_ids',
		'Product IDs to show in New Arrivals',
		'',
		3
	);
	?>
	<p style="margin:4px 0 10px;color:#646970;font-size:12px">
		<?php esc_html_e('Enter product IDs separated by commas or spaces. The order here controls the slider order. Leave empty to show latest products for this specialty automatically.', 'consucorner'); ?>
	</p>
	<?php

	$recent_products = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	if ($recent_products) :
	?>
		<div style="margin:8px 0 12px;padding:8px 10px;border:1px solid #dcdcde;border-radius:4px;background:#fff">
			<p style="margin:0 0 6px;font-weight:600;font-size:12px"><?php esc_html_e('Recent product IDs', 'consucorner'); ?></p>
			<?php foreach ($recent_products as $product_id) : ?>
				<p style="margin:3px 0;font-size:12px">
					<code><?php echo esc_html($product_id); ?></code>
					<?php echo esc_html(get_the_title($product_id)); ?>
				</p>
			<?php endforeach; ?>
		</div>
	<?php
	endif;

	cc_meta_section('Filter Controls');
	cc_meta_term_checkboxes(
		$id,
		'_cc_shop_specialty_category_ids',
		'Specialty/product categories this page should show',
		'product_cat',
		'Select the specialty categories for this page, such as Ophthalmology. Leave all unchecked to allow all product categories.'
	);
	cc_meta_term_checkboxes(
		$id,
		'_cc_shop_specialty_procedure_ids',
		'Instrument filters to show',
		'procedure',
		'Leave all unchecked to automatically show every Procedure term, including new terms you create later.'
	);

	if (taxonomy_exists('product_brand')) {
		cc_meta_term_checkboxes(
			$id,
			'_cc_shop_specialty_brand_ids',
			'Popular brands to show',
			'product_brand',
			'Leave all unchecked to automatically show brands attached to products in the active specialty filter.'
		);
	}

	$country_taxonomy = cc_shop_specialty_country_taxonomy();
	if ($country_taxonomy) {
		cc_meta_term_checkboxes(
			$id,
			'_cc_shop_specialty_country_ids',
			'Country of origin terms to show',
			$country_taxonomy,
			'Leave all unchecked to automatically show countries attached to products in the active specialty filter.'
		);
	}
}

/* =============================================================
   OFFERS PAGE  (/offers)
   ============================================================= */

function cc_render_offers_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	$defaults = function_exists( 'cc_offers_get_default_meta' ) ? cc_offers_get_default_meta() : array(
		'badge'       => 'Certified Excellence',
		'title'       => '<span class="cc-offers-hero__brand">ConsuCorner</span> <span class="cc-offers-hero__deal">Flash Deals – Premium</span><br />Medical Supplies at Unbeatable Prices.',
		'description' => 'Exclusive access to professional surgical instruments, high-grade consumables, and specialty-specific equipment. Certified global quality standards for clinical precision.',
	);

	cc_meta_section('Hero');
	cc_meta_text($id, '_cc_offers_badge', 'Badge', $defaults['badge']);
	cc_meta_textarea(
		$id,
		'_cc_offers_title',
		'Title (HTML ok)',
		$defaults['title'],
		3
	);
	cc_meta_textarea(
		$id,
		'_cc_offers_description',
		'Description',
		$defaults['description'],
		4
	);

	if (current_user_can('edit_products')) {
		$builder_url = admin_url('admin.php?page=cc-offers-link-builder');
		?>
		<p style="margin:16px 0 0">
			<a class="button button-secondary" href="<?php echo esc_url($builder_url); ?>">
				<?php esc_html_e('Open Link Builder', 'consucorner'); ?>
			</a>
		</p>
		<p class="description" style="margin:6px 0 0">
			<?php esc_html_e('Build shareable shop and specialty filter campaign links under Products → Link Builder.', 'consucorner'); ?>
		</p>
		<?php
	}
}

add_action('save_post', 'cc_save_offers_meta');
function cc_save_offers_meta($post_id)
{
	if (get_page_template_slug($post_id) !== 'page-offers.php') {
		return;
	}

	cc_save_meta_fields($post_id, array(
		'_cc_offers_badge',
		'_cc_offers_title',
		'_cc_offers_description',
	));
}

add_action('save_post', 'cc_save_shop_specialty_meta');
function cc_save_shop_specialty_meta($post_id)
{
	cc_save_meta_fields($post_id, array(
		'_cc_shop_specialty_head_subtitle',
		'_cc_shop_specialty_head_title',
		'_cc_shop_specialty_head_breadcrumbs',
		'_cc_shop_specialty_filter_title',
		'_cc_shop_specialty_filter_copy',
		'_cc_shop_specialty_all_label',
		'_cc_shop_specialty_per_page',
		'_cc_shop_specialty_no_results',
		'_cc_shop_specialty_bestsellers_title',
		'_cc_shop_specialty_recommended_title',
		'_cc_shop_specialty_new_arrivals_title',
		'_cc_shop_specialty_shop_now_text',
		'_cc_shop_specialty_brands_title',
		'_cc_shop_specialty_country_title',
		'_cc_shop_specialty_new_arrival_product_ids',
	));

	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	foreach (array('_cc_shop_specialty_category_ids', '_cc_shop_specialty_procedure_ids', '_cc_shop_specialty_brand_ids', '_cc_shop_specialty_country_ids') as $key) {
		if (isset($_POST[$key]) && is_array($_POST[$key])) {
			update_post_meta($post_id, $key, array_values(array_filter(array_map('absint', wp_unslash($_POST[$key])))));
		} else {
			delete_post_meta($post_id, $key);
		}
	}
}

/* =============================================================
   PRIVACY POLICY  (/privacy-policy)
   ============================================================= */

function cc_render_pp_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Page Head');
	cc_meta_text($id, '_cc_pp_head_title',       'Title',       'Privacy &amp; Policy');
	cc_meta_text($id, '_cc_pp_head_breadcrumbs', 'Breadcrumbs', 'Home / Privacy &amp; Policy');
	echo '<p style="margin:10px 0 14px;padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc;color:#1d2327;font-size:13px">';
	esc_html_e('Privacy Policy body content is now edited in the main WordPress editor above. The section fields below are legacy fallback content and only appear on the frontend if the main editor is empty.', 'consucorner');
	echo '</p>';

	$pp_defaults = array(
		1  => array('1. Introduction',                'ConsuCorner respects your privacy and is committed to protecting your personal data. This Privacy Policy explains what information we collect, how we use it, and the measures we take to keep it safe. By using our website, you agree to this policy.'),
		2  => array('2. What Information We Collect', '<p>We may collect the following types of information:</p><ul><li><strong>Personal information</strong> (such as your name, email address, phone number, and shipping address) when you place an order or create an account.</li><li><strong>Order details, payment method</strong> (e.g., cash or online) and transaction reference. We do not store or have access to your full credit/debit card information, as all online payments are processed securely via Paymob.</li><li><strong>Device and browser data</strong> (such as your IP address and device type), collected for analytics and security purposes.</li><li><strong>Marketing interaction indicators</strong> (e.g., open rates, click rates) based on your activity on our website or in our emails.</li></ul>'),
		3  => array('3. How We Use Your Information', '<p>We use your information to:</p><ul><li>Process and fulfill your orders</li><li>Communicate with you about your order or inquiries</li><li>Improve our website and customer service</li><li>Comply with legal obligations</li></ul>'),
		4  => array('4. Sharing Your Information',    '<p>We do not sell or rent your personal data. We may share your information with:</p><ul><li>Trusted third-party service providers (e.g., couriers, payment processors)</li><li>Legal or regulatory bodies if required by law</li></ul>'),
		5  => array('5. Data Security',               'We implement a variety of technical and organizational measures to protect your personal information, including encryption, secure servers, and limited access to sensitive data.'),
		6  => array('6. Your Rights',                 '<p>You have the right to:</p><ul><li>Access the personal data we hold about you</li><li>Correct inaccuracies in your data</li><li>Request deletion of your data</li><li>Opt out of marketing communications</li></ul>'),
		7  => array('7. Cookies and Tracking',        'We use cookies to enhance your browsing experience, personalize content, and analyze website traffic. You can control cookie settings in your browser preferences.'),
		8  => array('8. Retention of Data',           'We retain your personal data only as long as necessary for the purposes outlined in this policy or as required by law.'),
		9  => array('9. Changes to This Policy',      'We may update this Privacy Policy from time to time. Changes will be posted on this page and the revised date will be updated accordingly.'),
		10 => array('10. Contact Us',                 'If you have any questions or concerns about this Privacy Policy, please contact us at:<br><strong>Email:</strong> <a href="mailto:support@consucorner.com">support@consucorner.com</a>'),
	);

	cc_meta_section('Policy Sections (title + content — HTML allowed)');
	foreach ($pp_defaults as $n => $d) {
		echo '<p style="margin:10px 0 2px;font-size:12px;color:#555"><em>— Section ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_pp_s{$n}_title",   'Section title',   $d[0]);
		cc_meta_textarea($id, "_cc_pp_s{$n}_content", 'Section content', $d[1], 4);
	}

	cc_meta_section('Sidebar Promo Banner Slides (3 slides)');
	$banner_defaults = array(
		1 => array('Future is here',   'Shop Now With<br>Premium<br>Quality',      'Shop Now',   get_home_url(null, '/shop')),
		2 => array('Fast Delivery',    'Explore Global<br>Brands at<br>Your Doorstep', 'Shop Brands', get_home_url(null, '/shop')),
		3 => array('Secure Payments',  'Reliable Checkouts<br>For Every<br>Order', 'Order Now',  get_home_url(null, '/shop')),
	);
	foreach ($banner_defaults as $n => $b) {
		echo '<p style="margin:10px 0 2px;font-size:12px;color:#555"><em>— Banner Slide ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_pp_b{$n}_tag",      'Tag text',    $b[0]);
		cc_meta_text($id, "_cc_pp_b{$n}_title",    'Title (HTML ok)', $b[1]);
		cc_meta_text($id, "_cc_pp_b{$n}_btn_text", 'Button text', $b[2]);
		cc_meta_text($id, "_cc_pp_b{$n}_btn_link", 'Button link', $b[3]);
	}
}

add_action('save_post', 'cc_save_pp_meta');
function cc_save_pp_meta($post_id)
{
	$keys = array('_cc_pp_head_title', '_cc_pp_head_breadcrumbs');
	for ($n = 1; $n <= 10; $n++) {
		$keys[] = "_cc_pp_s{$n}_title";
		$keys[] = "_cc_pp_s{$n}_content";
	}
	for ($n = 1; $n <= 3; $n++) {
		$keys[] = "_cc_pp_b{$n}_tag";
		$keys[] = "_cc_pp_b{$n}_title";
		$keys[] = "_cc_pp_b{$n}_btn_text";
		$keys[] = "_cc_pp_b{$n}_btn_link";
	}
	cc_save_meta_fields($post_id, $keys);
}

/* =============================================================
   VENDOR  (/vendor)
   ============================================================= */

function cc_render_vendor_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Hero Section');
	cc_meta_text(
		$id,
		'_cc_vendor_hero_title',
		'Hero title',
		'All on ConsuCorner Marketing, Delivery, and Collections'
	);
	cc_meta_text(
		$id,
		'_cc_vendor_hero_subtitle',
		'Hero subtitle',
		'Your gateway to doctors, clinics, and medical centers online.'
	);
	cc_meta_textarea(
		$id,
		'_cc_vendor_hero_desc',
		'Hero description',
		"List your products, we manage operations. From listing to secure payment – we simplify every step. Join as a vendor and expand your reach across Egypt's leading medical marketplace."
	);

	cc_meta_section('Forminator Form');
	cc_meta_forminator_reference('consucorner_forminator_vendor_form_id', 'Vendor application form');
	cc_meta_text($id, '_cc_vendor_form_title', 'Form card heading (HTML ok)', 'Become a vendor <span>→</span>');

	cc_meta_section('Why Join Section');
	cc_meta_text($id, '_cc_vendor_why_tag',   'Tag',   'Your gateway to doctors, clinics, and medical centers online.');
	cc_meta_text($id, '_cc_vendor_why_title', 'Title (HTML ok)', 'Why Join ConsuCorner as a <span>Vendor?</span>');
	cc_meta_textarea(
		$id,
		'_cc_vendor_why_desc',
		'Description',
		"With ConsuCorner, you can expand your business reach and connect with a growing network of buyers in the medical supplies and equipment field. We help you boost sales, manage orders with ease, and ensure fast and secure payouts. Your success starts here—because our growth is tied to yours."
	);

	$why_defaults = array(
		1 => array('Reach more customers', 'We serve healthcare professionals with care, respect, and a clear understanding of their daily needs.',                               'reach-icon.png'),
		2 => array('Earn more money',       "We'll help you serve more medical professionals and clinics without expanding your physical store — and we'll make sure you get paid promptly and securely.", 'earn-icon.png'),
		3 => array('Grow your business',    'Increase your sales, connect with more healthcare buyers, and showcase your products more effectively. At ConsuCorner, we provide the tools to grow your business — because your success drives ours.', 'grow-icon.png'),
	);
	foreach ($why_defaults as $n => $d) {
		echo '<p style="margin:8px 0 2px;font-size:12px;color:#555"><em>— Why Item ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_vendor_why_{$n}_title", 'Title',       $d[0]);
		cc_meta_textarea($id, "_cc_vendor_why_{$n}_desc",  'Description', $d[1]);
		cc_meta_text($id, "_cc_vendor_why_{$n}_icon",  'Icon filename', $d[2]);
	}

	cc_meta_section('Partners Brochure');
	cc_meta_text($id, '_cc_vendor_brochure_title',    'Title',       'Partners brochure');
	cc_meta_text($id, '_cc_vendor_brochure_btn_text', 'Button text', 'DOWNLOAD');
	cc_meta_text($id, '_cc_vendor_brochure_btn_link', 'Button link', '#');

	cc_meta_section('How We Collaborate Section');
	cc_meta_text($id, '_cc_vendor_collab_tag',   'Tag',   'A simple, seamless process that connects your products to the right buyers.');
	cc_meta_text($id, '_cc_vendor_collab_title', 'Title (HTML ok)', 'How We Collaborate with <span>Our Vendors</span>');
	cc_meta_textarea(
		$id,
		'_cc_vendor_collab_desc',
		'Description',
		"From listing your medical products on ConsuCorner to receiving secure payments, we streamline every step. Customers place their orders, you prepare them, and our logistics partners handle the delivery — while you monitor sales and growth through your vendor dashboard."
	);

	$step_defaults = array(
		1 => array('1', 'List Your Products',   'Add your items with clear prices and details through your vendor dashboard.'),
		2 => array('2', 'We Do the Marketing',  'Your products are promoted on ConsuCorner to the right audience — free of charge.'),
		3 => array('3', 'We Handle Delivery',   'Orders are shipped to customers and payments are collected securely.'),
		4 => array('4', 'Receive Your Earnings', 'Your balance is transferred directly to you after commission deduction.'),
	);
	foreach ($step_defaults as $n => $d) {
		echo '<p style="margin:8px 0 2px;font-size:12px;color:#555"><em>— Step ' . absint($n) . ' —</em></p>';
		cc_meta_text($id, "_cc_vendor_step_{$n}_num",   'Step number', $d[0]);
		cc_meta_text($id, "_cc_vendor_step_{$n}_title", 'Title',       $d[1]);
		cc_meta_textarea($id, "_cc_vendor_step_{$n}_desc",  'Description', $d[2]);
	}

	cc_meta_section('FAQ Section');
	cc_meta_text($id, '_cc_vendor_faq_title', 'Section heading (HTML ok)', 'Questions? <span>We\'ve got answers</span>');

	$faq_defaults = array(
		1 => array('What is intraocular lens (IOL) implantation?',     'IOL implantation is a surgical procedure performed after cataract removal or extraction of a damaged natural lens, where a transparent artificial lens is implanted to restore clear vision.'),
		2 => array('Is IOL implantation only used for cataract surgery?', 'No, IOL implantation is not limited to cataract surgery. It is also used in refractive lens exchange procedures to correct vision problems such as severe myopia, hyperopia, or presbyopia.'),
		3 => array('What instruments are required for IOL implantation?', 'The procedure requires a phacoemulsification system, IOL injector and cartridge, micro-incision knives, viscoelastic agents, irrigation/aspiration handpieces, and a microscope for precision surgical visualization.'),
		4 => array('Why is an injector and cartridge system important?', 'The injector and cartridge system allows the surgeon to fold and insert the IOL through a very small incision, minimizing trauma to the eye, reducing recovery time, and lowering the risk of complications.'),
	);

	if (metadata_exists('post', $id, '_cc_vendor_faq_items')) {
		$vendor_faqs = get_post_meta($id, '_cc_vendor_faq_items', true);
		$vendor_faqs = is_array($vendor_faqs) ? $vendor_faqs : array();
	} else {
		$vendor_faqs = array();
		foreach ($faq_defaults as $n => $d) {
			$vendor_faqs[] = array(
				'question' => get_post_meta($id, "_cc_vendor_faq_{$n}_q", true) ?: $d[0],
				'answer'   => get_post_meta($id, "_cc_vendor_faq_{$n}_a", true) ?: $d[1],
			);
		}
	}
	?>
	<style>
		.cc-vendor-faq-row {
			position: relative;
			margin: 12px 0;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			background: #fff;
		}

		.cc-vendor-faq-row label {
			display: block;
			margin: 0 0 4px;
			font-weight: 600;
			font-size: 12px;
		}

		.cc-vendor-faq-row input,
		.cc-vendor-faq-row textarea {
			width: 100%;
		}

		.cc-vendor-faq-actions {
			margin-top: 10px;
			display: flex;
			gap: 8px;
		}
	</style>
	<p style="color:#646970;font-size:12px;margin:6px 0 10px">
		<?php esc_html_e('Add or remove vendor FAQ questions here. Empty rows are ignored on save.', 'consucorner'); ?>
	</p>
	<div id="cc-vendor-faq-items">
		<?php foreach ($vendor_faqs as $index => $faq) : ?>
			<?php
			$question = isset($faq['question']) ? $faq['question'] : '';
			$answer   = isset($faq['answer']) ? $faq['answer'] : '';
			?>
			<div class="cc-vendor-faq-row">
				<p>
					<label><?php esc_html_e('Question', 'consucorner'); ?></label>
					<input type="text" name="_cc_vendor_faq_items[<?php echo esc_attr($index); ?>][question]" value="<?php echo esc_attr($question); ?>">
				</p>
				<p>
					<label><?php esc_html_e('Answer', 'consucorner'); ?> <span style="font-weight:400;color:#888">(<?php esc_html_e('HTML allowed', 'consucorner'); ?>)</span></label>
					<textarea name="_cc_vendor_faq_items[<?php echo esc_attr($index); ?>][answer]" rows="4"><?php echo esc_textarea($answer); ?></textarea>
				</p>
				<div class="cc-vendor-faq-actions">
					<button type="button" class="button cc-vendor-faq-remove"><?php esc_html_e('Remove Question', 'consucorner'); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<p>
		<button type="button" class="button button-secondary" id="cc-vendor-faq-add"><?php esc_html_e('Add Question', 'consucorner'); ?></button>
	</p>
	<script>
		(function() {
			var list = document.getElementById('cc-vendor-faq-items');
			var addButton = document.getElementById('cc-vendor-faq-add');
			if (!list || !addButton) {
				return;
			}

			function nextIndex() {
				var max = -1;
				list.querySelectorAll('[name^="_cc_vendor_faq_items["]').forEach(function(field) {
					var match = field.name.match(/_cc_vendor_faq_items\[(\d+)\]/);
					if (match) {
						max = Math.max(max, parseInt(match[1], 10));
					}
				});
				return max + 1;
			}

			function bindRemove(button) {
				button.addEventListener('click', function() {
					var row = button.closest('.cc-vendor-faq-row');
					if (row) {
						row.remove();
					}
				});
			}

			list.querySelectorAll('.cc-vendor-faq-remove').forEach(bindRemove);

			addButton.addEventListener('click', function() {
				var index = nextIndex();
				var row = document.createElement('div');
				row.className = 'cc-vendor-faq-row';
				row.innerHTML = '<p><label><?php echo esc_js(__('Question', 'consucorner')); ?></label><input type="text" name="_cc_vendor_faq_items[' + index + '][question]" value=""></p>' +
					'<p><label><?php echo esc_js(__('Answer', 'consucorner')); ?> <span style="font-weight:400;color:#888">(<?php echo esc_js(__('HTML allowed', 'consucorner')); ?>)</span></label><textarea name="_cc_vendor_faq_items[' + index + '][answer]" rows="4"></textarea></p>' +
					'<div class="cc-vendor-faq-actions"><button type="button" class="button cc-vendor-faq-remove"><?php echo esc_js(__('Remove Question', 'consucorner')); ?></button></div>';
				list.appendChild(row);
				bindRemove(row.querySelector('.cc-vendor-faq-remove'));
			});
		})();
	</script>
<?php
}

add_action('save_post', 'cc_save_vendor_meta');
function cc_save_vendor_meta($post_id)
{
	$keys = array(
		'_cc_vendor_hero_title',
		'_cc_vendor_hero_subtitle',
		'_cc_vendor_hero_desc',
		'_cc_vendor_form_title',
		'_cc_vendor_why_tag',
		'_cc_vendor_why_title',
		'_cc_vendor_why_desc',
		'_cc_vendor_brochure_title',
		'_cc_vendor_brochure_btn_text',
		'_cc_vendor_brochure_btn_link',
		'_cc_vendor_collab_tag',
		'_cc_vendor_collab_title',
		'_cc_vendor_collab_desc',
		'_cc_vendor_faq_title',
	);
	for ($n = 1; $n <= 3; $n++) {
		$keys[] = "_cc_vendor_why_{$n}_title";
		$keys[] = "_cc_vendor_why_{$n}_desc";
		$keys[] = "_cc_vendor_why_{$n}_icon";
	}
	for ($n = 1; $n <= 4; $n++) {
		$keys[] = "_cc_vendor_step_{$n}_num";
		$keys[] = "_cc_vendor_step_{$n}_title";
		$keys[] = "_cc_vendor_step_{$n}_desc";
	}
	cc_save_meta_fields($post_id, $keys);

	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_meta_nonce']), '_cc_save_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$vendor_faqs = array();
	if (isset($_POST['_cc_vendor_faq_items']) && is_array($_POST['_cc_vendor_faq_items'])) {
		$raw_vendor_faqs = wp_unslash($_POST['_cc_vendor_faq_items']);
		foreach ($raw_vendor_faqs as $faq) {
			$question = isset($faq['question']) ? sanitize_text_field($faq['question']) : '';
			$answer   = isset($faq['answer']) ? wp_kses_post($faq['answer']) : '';

			if ('' === $question && '' === trim(wp_strip_all_tags($answer))) {
				continue;
			}

			$vendor_faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}
	}

	update_post_meta($post_id, '_cc_vendor_faq_items', $vendor_faqs);
}

/* =============================================================
   BLOG ARCHIVE PAGE  (/blogs)
   ============================================================= */

function cc_render_blog_archive_meta_box($post)
{
	wp_nonce_field('_cc_save_meta', '_cc_meta_nonce');
	$id = $post->ID;

	cc_meta_section('Featured Header');
	cc_meta_text($id, '_cc_blog_archive_featured_label', 'Tag label', 'Featured');
	cc_meta_text($id, '_cc_blog_archive_featured_title', 'Heading text', 'This Month');

	cc_meta_section('Recently Posted Header');
	cc_meta_text($id, '_cc_blog_archive_recent_label', 'Tag label', 'Recently');
	cc_meta_text($id, '_cc_blog_archive_recent_title', 'Heading text', 'Posted');

	cc_meta_section('Data Source Controls');
	cc_meta_text(
		$id,
		'_cc_blog_archive_featured_post_id',
		'Featured post ID (optional — leave empty for latest post)',
		''
	);
	cc_meta_text(
		$id,
		'_cc_blog_archive_recent_count',
		'Recently posted cards count (3 to 12)',
		'8'
	);
	cc_meta_text(
		$id,
		'_cc_blog_archive_grid_count',
		'Blog grid cards per page (3 to 24)',
		'6'
	);
	cc_meta_text($id, '_cc_blog_archive_read_link_text', 'Featured card button text', 'Read Article');

	cc_meta_section('Middle Promo Banner');
	cc_meta_text(
		$id,
		'_cc_blog_archive_banner_image',
		'Banner image URL',
		get_template_directory_uri() . '/assets/images/blog-banner.webp'
	);
	cc_meta_text(
		$id,
		'_cc_blog_archive_banner_position',
		'Background position',
		'0 -500px'
	);

	cc_meta_section('Editing Notes');
	echo '<p style="margin:6px 0;color:#646970;font-size:12px">';
	echo esc_html__('Main cards pull from real blog posts. Edit each post body/content in Gutenberg from Posts. The page editor (Gutenberg) is also enabled for adding fully custom block content between sections.', 'consucorner');
	echo '</p>';
}

add_action('save_post', 'cc_save_blog_archive_meta');
function cc_save_blog_archive_meta($post_id)
{
	cc_save_meta_fields(
		$post_id,
		array(
			'_cc_blog_archive_featured_label',
			'_cc_blog_archive_featured_title',
			'_cc_blog_archive_recent_label',
			'_cc_blog_archive_recent_title',
			'_cc_blog_archive_featured_post_id',
			'_cc_blog_archive_recent_count',
			'_cc_blog_archive_grid_count',
			'_cc_blog_archive_read_link_text',
			'_cc_blog_archive_banner_image',
			'_cc_blog_archive_banner_position',
		)
	);
}

/* =============================================================
   PRODUCT  (single-product extras)

   Note: the "Offer Deal" and "Bulk pricing" fields that used to
   live in a meta box here now live in the native WooCommerce
   "Pricing & Deals" Product Data tab.
   @see inc/product-pricing-tabs.php
   ============================================================= */

/* =============================================================
   BLOG POSTS  (repeatable FAQs)

   The article body remains Gutenberg-managed. These fields control
   single-post extras that sit outside the editor content.
   ============================================================= */

add_action('add_meta_boxes_post', 'cc_register_blog_post_meta_box');
function cc_register_blog_post_meta_box()
{
	add_meta_box(
		'cc-blog-post-extras',
		__('Blog Single Page Extras', 'consucorner'),
		'cc_render_blog_post_meta_box',
		'post',
		'normal',
		'high'
	);
}

function cc_render_blog_post_meta_box($post)
{
	wp_nonce_field('_cc_save_blog_meta', '_cc_blog_meta_nonce');

	$faqs = get_post_meta($post->ID, '_cc_blog_faqs', true);

	if (! is_array($faqs)) {
		$faqs = array();
	}
?>
	<style>
		.cc-blog-faq-row {
			position: relative;
			margin: 12px 0;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			background: #fff;
		}

		.cc-blog-faq-row label {
			display: block;
			margin: 0 0 4px;
			font-weight: 600;
			font-size: 12px;
		}

		.cc-blog-faq-row input,
		.cc-blog-faq-row textarea {
			width: 100%;
		}

		.cc-blog-faq-actions {
			margin-top: 10px;
			display: flex;
			gap: 8px;
		}
	</style>

	<?php cc_meta_section('Blog FAQ'); ?>
	<p style="color:#646970;font-size:12px;margin:6px 0 10px">
		<?php esc_html_e('Add as many FAQ items as this blog needs. Empty rows are ignored on save.', 'consucorner'); ?>
	</p>

	<div id="cc-blog-faqs">
		<?php
		if (empty($faqs)) {
			$faqs = array(
				array(
					'question' => '',
					'answer'   => '',
				),
			);
		}

		foreach ($faqs as $index => $faq) :
			$question = isset($faq['question']) ? $faq['question'] : '';
			$answer   = isset($faq['answer']) ? $faq['answer'] : '';
		?>
			<div class="cc-blog-faq-row">
				<p>
					<label><?php esc_html_e('Question', 'consucorner'); ?></label>
					<input type="text" name="_cc_blog_faqs[<?php echo esc_attr($index); ?>][question]" value="<?php echo esc_attr($question); ?>">
				</p>
				<p>
					<label><?php esc_html_e('Answer', 'consucorner'); ?> <span style="font-weight:400;color:#888">(<?php esc_html_e('HTML allowed', 'consucorner'); ?>)</span></label>
					<textarea name="_cc_blog_faqs[<?php echo esc_attr($index); ?>][answer]" rows="3"><?php echo esc_textarea($answer); ?></textarea>
				</p>
				<div class="cc-blog-faq-actions">
					<button type="button" class="button cc-blog-remove-faq"><?php esc_html_e('Remove FAQ', 'consucorner'); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" class="button button-secondary" id="cc-blog-add-faq"><?php esc_html_e('Add FAQ', 'consucorner'); ?></button>
	</p>

	<script>
		(function() {
			var list = document.getElementById('cc-blog-faqs');
			var addButton = document.getElementById('cc-blog-add-faq');
			if (!list || !addButton) {
				return;
			}

			function nextIndex() {
				return list.querySelectorAll('.cc-blog-faq-row').length;
			}

			function bindRemove(button) {
				button.addEventListener('click', function() {
					var row = button.closest('.cc-blog-faq-row');
					if (row) {
						row.remove();
					}
				});
			}

			list.querySelectorAll('.cc-blog-remove-faq').forEach(bindRemove);

			addButton.addEventListener('click', function() {
				var index = nextIndex();
				var row = document.createElement('div');
				row.className = 'cc-blog-faq-row';
				row.innerHTML = '<p><label><?php echo esc_js(__('Question', 'consucorner')); ?></label><input type="text" name="_cc_blog_faqs[' + index + '][question]" value=""></p>' +
					'<p><label><?php echo esc_js(__('Answer', 'consucorner')); ?> <span style="font-weight:400;color:#888">(<?php echo esc_js(__('HTML allowed', 'consucorner')); ?>)</span></label><textarea name="_cc_blog_faqs[' + index + '][answer]" rows="3"></textarea></p>' +
					'<div class="cc-blog-faq-actions"><button type="button" class="button cc-blog-remove-faq"><?php echo esc_js(__('Remove FAQ', 'consucorner')); ?></button></div>';
				list.appendChild(row);
				bindRemove(row.querySelector('.cc-blog-remove-faq'));
			});
		})();
	</script>
<?php
}

add_action('save_post_post', 'cc_save_blog_post_meta');
function cc_save_blog_post_meta($post_id)
{
	if (
		! isset($_POST['_cc_blog_meta_nonce'])
		|| ! wp_verify_nonce(wp_unslash($_POST['_cc_blog_meta_nonce']), '_cc_save_blog_meta')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$faqs = array();
	if (isset($_POST['_cc_blog_faqs']) && is_array($_POST['_cc_blog_faqs'])) {
		$raw_faqs = wp_unslash($_POST['_cc_blog_faqs']);
		foreach ($raw_faqs as $faq) {
			$question = isset($faq['question']) ? sanitize_text_field($faq['question']) : '';
			$answer   = isset($faq['answer']) ? wp_kses_post($faq['answer']) : '';

			if ('' === $question && '' === trim(wp_strip_all_tags($answer))) {
				continue;
			}

			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}
	}

	if ($faqs) {
		update_post_meta($post_id, '_cc_blog_faqs', $faqs);
	} else {
		delete_post_meta($post_id, '_cc_blog_faqs');
	}
}
