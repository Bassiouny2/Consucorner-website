<?php
/**
 * Search results page.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

$search_query   = trim(get_search_query());
$search_page    = max(1, (int) get_query_var('paged'));
$per_page       = 24;
$categories     = array();
$specialties    = array();
$search_result  = array(
	'products' => array(),
	'total'    => 0,
	'pages'    => 0,
	'page'     => $search_page,
	'per_page' => $per_page,
);

if ($search_query && mb_strlen($search_query) >= 3) {
	if (function_exists('consucorner_get_product_search_categories')) {
		$categories = consucorner_get_product_search_categories($search_query, 0);
	}
	if (function_exists('consucorner_get_product_search_specialties')) {
		$specialties = consucorner_get_product_search_specialties($search_query, 8);
	}
	if (function_exists('consucorner_run_product_search')) {
		$search_result = consucorner_run_product_search(
			$search_query,
			array(
				'per_page' => $per_page,
				'page'     => $search_page,
			)
		);
	}
}

$products        = $search_result['products'];
$product_total   = (int) $search_result['total'];
$product_pages   = (int) $search_result['pages'];
$category_count  = count($categories);
$specialty_count = count($specialties);
$term_count      = $category_count + $specialty_count;

get_header();
?>

<main id="primary" class="site-main cc-search-page">
	<section class="cc-search-hero">
		<div class="cc-search-hero__inner">
			<p class="cc-search-eyebrow"><?php esc_html_e('Search the catalog', 'consucorner'); ?></p>
			<h1 class="cc-search-title">
				<?php if ($search_query) : ?>
					<?php
					printf(
						/* translators: %s: search query. */
						esc_html__('Results for "%s"', 'consucorner'),
						esc_html($search_query)
					);
					?>
				<?php else : ?>
					<?php esc_html_e('Find medical supplies faster', 'consucorner'); ?>
				<?php endif; ?>
			</h1>
			<p class="cc-search-subtitle">
				<?php esc_html_e('Browse matching products, specialties, and categories in one organized view.', 'consucorner'); ?>
			</p>
			<form class="cc-search-page-form" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
				<label class="screen-reader-text" for="ccSearchPageInput"><?php esc_html_e('Search products and categories', 'consucorner'); ?></label>
				<input id="ccSearchPageInput" type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search products, categories, specialties...', 'consucorner'); ?>" />
				<button type="submit">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#icon-search"></use></svg>
					<?php esc_html_e('Search', 'consucorner'); ?>
				</button>
			</form>
		</div>
	</section>

	<section class="cc-search-results">
		<div class="cc-search-results__head">
			<div>
				<h2><?php esc_html_e('Matched results', 'consucorner'); ?></h2>
				<p>
					<?php
					if ($search_query && mb_strlen($search_query) >= 3) {
						printf(
							/* translators: 1: products count, 2: categories/specialties count. */
							esc_html(_n(
								'%1$d product and %2$d specialty/category match found.',
								'%1$d products and %2$d specialty/category matches found.',
								$product_total,
								'consucorner'
							)),
							(int) $product_total,
							(int) $term_count
						);
					} else {
						esc_html_e('Enter at least 3 characters to search the catalog.', 'consucorner');
					}
					?>
				</p>
			</div>
			<?php if (function_exists('wc_get_page_permalink')) : ?>
				<a class="cc-search-shop-link" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Browse all products', 'consucorner'); ?></a>
			<?php endif; ?>
		</div>

		<?php if (! $search_query || mb_strlen($search_query) < 3) : ?>
			<div class="cc-search-empty">
				<h2><?php esc_html_e('Start with at least 3 letters', 'consucorner'); ?></h2>
				<p><?php esc_html_e('Try a product name, category, specialty, brand, or SKU.', 'consucorner'); ?></p>
			</div>
		<?php elseif (! $product_total && ! $term_count) : ?>
			<div class="cc-search-empty">
				<h2><?php esc_html_e('No results found', 'consucorner'); ?></h2>
				<p><?php esc_html_e('Try a broader medical term, a specialty name, or browse the full shop catalog.', 'consucorner'); ?></p>
			</div>
		<?php else : ?>
			<?php if ($specialty_count) : ?>
				<section class="cc-search-block" aria-labelledby="ccSearchSpecialtiesTitle">
					<div class="cc-search-block__title">
						<h2 id="ccSearchSpecialtiesTitle"><?php esc_html_e('Specialties', 'consucorner'); ?></h2>
						<span><?php echo esc_html(number_format_i18n($specialty_count)); ?></span>
					</div>
					<div class="cc-search-scrollbox cc-search-scrollbox--categories">
						<div class="cc-search-category-grid">
							<?php foreach ($specialties as $specialty) : ?>
								<?php $specialty_link = get_term_link($specialty); ?>
								<?php if (is_wp_error($specialty_link)) : continue; endif; ?>
								<a class="cc-search-category-card cc-search-category-card--specialty" href="<?php echo esc_url($specialty_link); ?>">
									<span class="cc-search-category-card__icon" aria-hidden="true">+</span>
									<span>
										<strong><?php echo esc_html($specialty->name); ?></strong>
										<small>
											<?php
											printf(
												/* translators: %d: product count. */
												esc_html__('%d products', 'consucorner'),
												(int) $specialty->count
											);
											?>
										</small>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ($category_count) : ?>
				<section class="cc-search-block" aria-labelledby="ccSearchCategoriesTitle">
					<div class="cc-search-block__title">
						<h2 id="ccSearchCategoriesTitle"><?php esc_html_e('Categories', 'consucorner'); ?></h2>
						<span><?php echo esc_html(number_format_i18n($category_count)); ?></span>
					</div>
					<div class="cc-search-scrollbox cc-search-scrollbox--categories">
						<div class="cc-search-category-grid">
							<?php foreach ($categories as $category) : ?>
								<?php $category_link = get_term_link($category); ?>
								<?php if (is_wp_error($category_link)) : continue; endif; ?>
								<a class="cc-search-category-card" href="<?php echo esc_url($category_link); ?>">
									<span class="cc-search-category-card__icon" aria-hidden="true">#</span>
									<span>
										<strong><?php echo esc_html($category->name); ?></strong>
										<small>
											<?php
											$category_context = function_exists('consucorner_get_term_parent_context') ? consucorner_get_term_parent_context($category) : '';
											if ($category_context) {
												echo esc_html($category_context . ' • ');
											}
											printf(
												/* translators: %d: product count. */
												esc_html__('%d products', 'consucorner'),
												(int) $category->count
											);
											?>
										</small>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ($product_total) : ?>
				<section class="cc-search-block" aria-labelledby="ccSearchProductsTitle">
					<div class="cc-search-block__title">
						<h2 id="ccSearchProductsTitle"><?php esc_html_e('Products', 'consucorner'); ?></h2>
						<span><?php echo esc_html(number_format_i18n($product_total)); ?></span>
					</div>
					<div class="cc-search-scrollbox cc-search-scrollbox--products">
						<div class="fp-products-grid cc-search-product-grid">
							<?php foreach ($products as $product) : ?>
								<?php echo cc_render_product_card($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes card fields. ?>
							<?php endforeach; ?>
						</div>
					</div>
					<?php if ($product_pages > 1) : ?>
						<nav class="cc-search-pagination" aria-label="<?php esc_attr_e('Search results pages', 'consucorner'); ?>">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg(
											array(
												's'     => $search_query,
												'paged' => '%#%',
											),
											home_url('/')
										),
										'format'    => '',
										'current'   => $search_page,
										'total'     => $product_pages,
										'prev_text' => '&larr; ' . __('Previous', 'consucorner'),
										'next_text' => __('Next', 'consucorner') . ' &rarr;',
										'type'      => 'list',
									)
								)
							);
							?>
						</nav>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</main>

<?php
get_footer();
