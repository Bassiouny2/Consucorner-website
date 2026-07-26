<?php
/**
 * Batch-assign `specialty` terms to products that have none.
 *
 * Run only via WP-CLI:
 *   wp eval-file wp-content/themes/consucorner/inc/assign-specialty-batch-cli.php
 *
 * Logic (same idea as the former admin bulk tool):
 * 1) Longest match of existing specialty term name in title + short + long description.
 * 2) Else mirror root product category (Yoast / Rank Math primary preferred), exclude Uncategorized.
 * 3) Else create/use « General specialty ».
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

/**
 * Walk product_cat term to root.
 *
 * @param WP_Term $term Term.
 * @return WP_Term
 */
function consucorner_cli_specialty_walk_to_root($term) {
	$t = $term;
	while ($t instanceof WP_Term && (int) $t->parent > 0) {
		$p = get_term((int) $t->parent, 'product_cat');
		if (! $p || is_wp_error($p)) {
			break;
		}
		$t = $p;
	}
	return $t instanceof WP_Term ? $t : $term;
}

function consucorner_cli_default_product_cat_id() {
	return absint(get_option('default_product_cat', 0));
}

function consucorner_cli_skip_product_cat($term) {
	if (! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy) {
		return true;
	}
	if ((int) $term->term_id === consucorner_cli_default_product_cat_id()) {
		return true;
	}

	return 'uncategorized' === $term->slug;
}

function consucorner_cli_specialty_from_categories($product_id) {
	$candidate_ids = array();

	$yoast = absint(get_post_meta($product_id, '_yoast_wpseo_primary_product_cat', true));
	if ($yoast) {
		$candidate_ids[] = $yoast;
	}
	$rank = absint(get_post_meta($product_id, 'rank_math_primary_product_cat', true));
	if ($rank) {
		$candidate_ids[] = $rank;
	}

	$terms = get_the_terms($product_id, 'product_cat');
	if (! is_wp_error($terms) && ! empty($terms)) {
		foreach ($terms as $t) {
			$candidate_ids[] = (int) $t->term_id;
		}
	}

	$candidate_ids = array_unique(array_filter(array_map('absint', $candidate_ids)));

	foreach ($candidate_ids as $tid) {
		$term = get_term($tid, 'product_cat');
		if (consucorner_cli_skip_product_cat($term)) {
			continue;
		}
		$root = consucorner_cli_specialty_walk_to_root($term);
		if (consucorner_cli_skip_product_cat($root)) {
			continue;
		}
		return $root->name;
	}

	return '';
}

function consucorner_cli_product_haystack($product_id) {
	$product = wc_get_product($product_id);
	if (! $product) {
		return '';
	}
	$parts = array(
		$product->get_name(),
		$product->get_short_description(),
		$product->get_description(),
	);

	return strtolower(wp_strip_all_tags(implode(' ', array_filter($parts))));
}

function consucorner_cli_match_specialty($haystack, array $terms) {
	if ('' === $haystack || empty($terms)) {
		return null;
	}
	usort(
		$terms,
		function ($a, $b) {
			$la = function_exists('mb_strlen') ? mb_strlen($a->name, 'UTF-8') : strlen($a->name);
			$lb = function_exists('mb_strlen') ? mb_strlen($b->name, 'UTF-8') : strlen($b->name);

			return $lb <=> $la;
		}
	);
	foreach ($terms as $term) {
		$name = trim($term->name);
		if ('' === $name) {
			continue;
		}
		$needle = strtolower($name);
		if (function_exists('mb_stripos')) {
			if (mb_stripos($haystack, $needle, 0, 'UTF-8') !== false) {
				return $term;
			}
		} elseif (false !== stripos($haystack, $needle)) {
			return $term;
		}
	}

	return null;
}

function consucorner_cli_get_or_create_specialty($name) {
	$name = trim(wp_strip_all_tags($name));
	if ('' === $name) {
		return 0;
	}
	$slug = sanitize_title($name);
	$existing = get_term_by('slug', $slug, 'specialty');
	if ($existing instanceof WP_Term) {
		return (int) $existing->term_id;
	}
	$by_name = term_exists($name, 'specialty');
	if (is_array($by_name) && ! empty($by_name['term_id'])) {
		return (int) $by_name['term_id'];
	}
	if (is_numeric($by_name)) {
		return (int) $by_name;
	}
	$args = array();
	if ('' !== $slug) {
		$args['slug'] = $slug;
	}
	$ins = wp_insert_term($name, 'specialty', $args);
	if (is_wp_error($ins)) {
		WP_CLI::warning($ins->get_error_message());

		return 0;
	}

	return (int) $ins['term_id'];
}

function consucorner_cli_sync_one($product_id) {
	$product_id = absint($product_id);
	$existing   = wp_get_post_terms($product_id, 'specialty', array('fields' => 'ids'));
	if (! is_wp_error($existing) && ! empty($existing)) {
		return array('status' => 'skip_has', 'term_id' => 0, 'source' => '');
	}

	$all = get_terms(
		array(
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
		)
	);
	$all = is_wp_error($all) ? array() : $all;

	$hay = consucorner_cli_product_haystack($product_id);
	$m   = consucorner_cli_match_specialty($hay, $all);
	if ($m instanceof WP_Term) {
		$tid = (int) $m->term_id;
	} else {
		$cat_name = consucorner_cli_specialty_from_categories($product_id);
		if ('' !== $cat_name) {
			$tid = consucorner_cli_get_or_create_specialty($cat_name);
		} else {
			$tid = consucorner_cli_get_or_create_specialty(__('General specialty', 'consucorner'));
		}
	}

	if ($tid < 1) {
		return array('status' => 'fail', 'term_id' => 0, 'source' => '');
	}

	$set = wp_set_object_terms($product_id, array($tid), 'specialty', false);
	if (is_wp_error($set)) {
		WP_CLI::warning(sprintf('Product %d: %s', $product_id, $set->get_error_message()));

		return array('status' => 'error', 'term_id' => 0, 'source' => '');
	}

	$label = get_term($tid, 'specialty');
	$label = $label instanceof WP_Term ? $label->name : (string) $tid;

	return array('status' => 'assigned', 'term_id' => $tid, 'source' => $label);
}

if (! taxonomy_exists('specialty')) {
	WP_CLI::error('Taxonomy `specialty` is not registered.');
}

$q = new WP_Query(
	array(
		'post_type'      => 'product',
		'post_status'    => array('publish', 'draft', 'pending', 'private'),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

$stats = array(
	'assigned' => 0,
	'skip'     => 0,
	'fail'     => 0,
);

foreach ($q->posts as $pid) {
	$r = consucorner_cli_sync_one((int) $pid);
	if ('assigned' === $r['status']) {
		++$stats['assigned'];
		WP_CLI::log(sprintf('#%d → %s', (int) $pid, $r['source']));
	} elseif ('skip_has' === $r['status']) {
		++$stats['skip'];
	} else {
		++$stats['fail'];
	}
}

wp_reset_postdata();

$ids = get_terms(
	array(
		'taxonomy'   => 'specialty',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if (! is_wp_error($ids) && ! empty($ids)) {
	wp_update_term_count($ids, 'specialty');
}

WP_CLI::success(
	sprintf(
		'Done. Assigned: %d, already had specialty: %d, failed: %d.',
		$stats['assigned'],
		$stats['skip'],
		$stats['fail']
	)
);
