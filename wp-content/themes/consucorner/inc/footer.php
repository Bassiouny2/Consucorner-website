<?php
/**
 * Footer helpers — nav columns + bottom social icon menu.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Official social profile URLs (Customizer defaults + one-time migration).
 *
 * @return array<string, string>
 */
function consucorner_footer_social_url_defaults() {
	return array(
		'footer_instagram_url' => 'https://www.instagram.com/consu.corner',
		'footer_facebook_url'  => 'https://www.facebook.com/Consucorner/',
		'footer_linkedin_url'  => 'https://www.linkedin.com/company/consucorner',
	);
}

/**
 * One-time migration: replace empty or "#" social URLs with official profiles.
 */
function consucorner_migrate_footer_social_customizer_urls() {
	if ( '20260625' === get_option( 'consucorner_footer_social_urls_migrated', '' ) ) {
		return;
	}

	foreach ( consucorner_footer_social_url_defaults() as $setting => $url ) {
		$current = get_theme_mod( $setting );
		if ( false === $current || '' === $current || '#' === $current ) {
			set_theme_mod( $setting, $url );
		}
	}

	update_option( 'consucorner_footer_social_urls_migrated', '20260625' );
}
add_action( 'after_setup_theme', 'consucorner_migrate_footer_social_customizer_urls', 25 );

/**
 * Social links controlled from Customizer (primary source for footer icons).
 *
 * @return array<int, array{url: string, classes: string[], label: string}>
 */
function consucorner_get_footer_customizer_social_links() {
	$defs = array(
		'footer_instagram_url' => array(
			'classes' => array( 'social-instagram' ),
			'label'   => 'Instagram',
		),
		'footer_facebook_url'  => array(
			'classes' => array( 'social-facebook' ),
			'label'   => 'Facebook',
		),
		'footer_linkedin_url'  => array(
			'classes' => array( 'social-linkedin' ),
			'label'   => 'LinkedIn',
		),
	);

	$links = array();

	foreach ( $defs as $setting => $meta ) {
		$url = function_exists( 'consucorner_get_footer_setting' )
			? trim( (string) consucorner_get_footer_setting( $setting ) )
			: '';

		if ( '' === $url || '#' === $url ) {
			continue;
		}

		$links[] = array(
			'url'     => $url,
			'classes' => $meta['classes'],
			'label'   => $meta['label'],
		);
	}

	return $links;
}

/**
 * Whether any footer social icons should display.
 *
 * @return bool
 */
function consucorner_footer_has_social_icons() {
	if ( count( consucorner_get_footer_customizer_social_links() ) > 0 ) {
		return true;
	}

	return consucorner_footer_menu_has_items( consucorner_footer_social_menu_location() );
}

/**
 * Footer text-link columns rendered in the main nav row.
 *
 * @return array<int, array{location: string, heading_key: string}>
 */
function consucorner_get_footer_nav_columns() {
	return array(
		array(
			'location'    => 'footer-explore',
			'heading_key' => 'footer_heading_explore',
		),
		array(
			'location'    => 'footer-legal',
			'heading_key' => 'footer_heading_legal',
		),
	);
}

/**
 * Theme location for bottom-bar social SVG icons.
 *
 * @return string
 */
function consucorner_footer_social_menu_location() {
	return 'footer-social';
}

/**
 * Whether a menu location has items.
 *
 * @param string $theme_location Menu location slug.
 * @return bool
 */
function consucorner_footer_menu_has_items( $theme_location ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $theme_location ] ) ) {
		return false;
	}

	$items = wp_get_nav_menu_items( (int) $locations[ $theme_location ] );

	return is_array( $items ) && count( $items ) > 0;
}

/**
 * Social slug → sprite symbol map.
 *
 * @return array<string, string>
 */
function consucorner_footer_social_icon_map() {
	return array(
		'instagram' => 'icon-instagram',
		'facebook'  => 'icon-facebook',
		'linkedin'  => 'icon-linkedin',
		'youtube'   => 'icon-youtube',
		'twitter'   => 'icon-twitter',
		'whatsapp'  => 'icon-whatsapp',
		'tiktok'    => 'icon-tiktok',
		'link'      => 'icon-external-link',
	);
}

/**
 * Detect social platform from URL / CSS classes.
 *
 * @param string   $url     Item URL.
 * @param string[] $classes Menu item classes.
 * @return string
 */
function consucorner_footer_social_icon_slug( $url, $classes = array() ) {
	$map = consucorner_footer_social_icon_map();

	if ( is_array( $classes ) ) {
		foreach ( $classes as $class ) {
			if ( preg_match( '/^social-([a-z0-9-]+)$/i', sanitize_html_class( (string) $class ), $matches ) ) {
				$slug = 'x' === strtolower( $matches[1] ) ? 'twitter' : strtolower( $matches[1] );
				if ( isset( $map[ $slug ] ) ) {
					return $slug;
				}
			}
		}
	}

	$haystack = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) . ' ' . (string) $url );
	$rules    = array(
		'instagram' => array( 'instagram.com', 'instagr.am' ),
		'facebook'  => array( 'facebook.com', 'fb.com', 'fb.me' ),
		'linkedin'  => array( 'linkedin.com' ),
		'youtube'   => array( 'youtube.com', 'youtu.be' ),
		'twitter'   => array( 'twitter.com', 'x.com' ),
		'whatsapp'  => array( 'whatsapp.com', 'wa.me' ),
		'tiktok'    => array( 'tiktok.com' ),
	);

	foreach ( $rules as $slug => $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return $slug;
			}
		}
	}

	return 'link';
}

/**
 * Whether a social menu item URL should render (placeholders allowed for known networks).
 *
 * @param string   $url     Item URL.
 * @param string[] $classes Menu item CSS classes.
 * @return bool
 */
function consucorner_footer_social_url_is_renderable( $url, $classes = array() ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return false;
	}

	if ( '#' !== $url ) {
		return true;
	}

	$slug = consucorner_footer_social_icon_slug( $url, $classes );

	return in_array( $slug, array( 'instagram', 'facebook', 'linkedin', 'youtube', 'twitter', 'whatsapp', 'tiktok' ), true );
}

/**
 * Output one social icon list item.
 *
 * @param string   $url     Link URL.
 * @param string[] $classes CSS classes.
 * @param string   $label   Accessible label.
 */
function consucorner_render_footer_social_icon_item( $url, $classes, $label = '' ) {
	$icon_map = consucorner_footer_social_icon_map();
	$labels   = array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'twitter'   => 'X (Twitter)',
		'whatsapp'  => 'WhatsApp',
		'tiktok'    => 'TikTok',
		'link'      => 'External link',
	);

	$classes = is_array( $classes ) ? $classes : array();
	$slug    = consucorner_footer_social_icon_slug( $url, $classes );
	$icon_id = isset( $icon_map[ $slug ] ) ? $icon_map[ $slug ] : $icon_map['link'];
	$label   = trim( (string) $label );
	if ( '' === $label ) {
		$label = isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug );
	}

	$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$item_host = wp_parse_url( $url, PHP_URL_HOST );
	$external  = '#' !== $url && ! empty( $item_host ) && strtolower( (string) $item_host ) !== strtolower( (string) $home_host );
	?>
	<li class="footer-social-menu__item footer-social-menu__item--<?php echo esc_attr( sanitize_html_class( $slug ) ); ?>">
		<a
			href="<?php echo esc_url( $url ); ?>"
			class="footer-social-link"
			aria-label="<?php echo esc_attr( $label ); ?>"
			<?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
		>
			<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<use href="#<?php echo esc_attr( $icon_id ); ?>" />
			</svg>
		</a>
	</li>
	<?php
}

/**
 * Render bottom-bar social icons (Customizer + optional extra menu links).
 */
function consucorner_render_footer_social_icons() {
	$rendered_slugs = array();
	$custom_links   = consucorner_get_footer_customizer_social_links();
	$menu_location  = consucorner_footer_social_menu_location();
	$menu_items     = array();

	$locations = get_nav_menu_locations();
	if ( ! empty( $locations[ $menu_location ] ) ) {
		$items = wp_get_nav_menu_items( (int) $locations[ $menu_location ] );
		if ( is_array( $items ) ) {
			$menu_items = $items;
		}
	}

	if ( count( $custom_links ) < 1 && count( $menu_items ) < 1 ) {
		return;
	}

	echo '<ul class="footer-social-menu" role="list">';

	foreach ( $custom_links as $link ) {
		$slug = consucorner_footer_social_icon_slug( $link['url'], $link['classes'] );
		$rendered_slugs[ $slug ] = true;
		consucorner_render_footer_social_icon_item( $link['url'], $link['classes'], $link['label'] );
	}

	foreach ( $menu_items as $item ) {
		$url     = isset( $item->url ) ? (string) $item->url : '';
		$classes = is_array( $item->classes ) ? $item->classes : array();

		if ( ! consucorner_footer_social_url_is_renderable( $url, $classes ) ) {
			continue;
		}

		$slug = consucorner_footer_social_icon_slug( $url, $classes );
		if ( isset( $rendered_slugs[ $slug ] ) ) {
			continue;
		}

		$label = trim( (string) $item->attr_title );
		if ( '' === $label ) {
			$label = trim( (string) $item->title );
		}

		$rendered_slugs[ $slug ] = true;
		consucorner_render_footer_social_icon_item( $url, $classes, $label );
	}

	echo '</ul>';
}

/**
 * Render bottom-bar social icons from a WordPress menu (legacy alias).
 *
 * @param string $theme_location Menu location.
 */
function consucorner_render_footer_social_menu( $theme_location = '' ) {
	consucorner_render_footer_social_icons();
}

/**
 * Default bottom social menu items.
 *
 * @return array{name: string, items: array<int, array{title: string, url: string, classes?: string}>}
 */
function consucorner_footer_social_menu_defaults() {
	$url_defaults = consucorner_footer_social_url_defaults();

	return array(
		'name'  => 'Footer - Social Icons',
		'items' => array(
			array(
				'title'   => 'Instagram',
				'url'     => function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_instagram_url' )
					: $url_defaults['footer_instagram_url'],
				'classes' => 'social-instagram',
			),
			array(
				'title'   => 'Facebook',
				'url'     => function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_facebook_url' )
					: $url_defaults['footer_facebook_url'],
				'classes' => 'social-facebook',
			),
			array(
				'title'   => 'LinkedIn',
				'url'     => function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_linkedin_url' )
					: $url_defaults['footer_linkedin_url'],
				'classes' => 'social-linkedin',
			),
		),
	);
}

/**
 * Replace all items in a nav menu.
 *
 * @param int   $menu_id Menu ID.
 * @param array $items   Menu rows.
 */
function consucorner_replace_footer_nav_menu_items( $menu_id, array $items ) {
	$menu_id = absint( $menu_id );
	if ( $menu_id < 1 ) {
		return;
	}

	$existing = wp_get_nav_menu_items( $menu_id );
	if ( is_array( $existing ) ) {
		foreach ( $existing as $item ) {
			wp_delete_post( (int) $item->ID, true );
		}
	}

	foreach ( $items as $item ) {
		$args = array(
			'menu-item-title'  => $item['title'],
			'menu-item-url'    => $item['url'],
			'menu-item-status' => 'publish',
			'menu-item-type'   => 'custom',
		);
		if ( ! empty( $item['classes'] ) ) {
			$args['menu-item-classes'] = $item['classes'];
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
}

/**
 * Ensure default social icons exist (Instagram, Facebook, LinkedIn).
 *
 * @param int $menu_id Menu ID.
 */
function consucorner_seed_footer_social_menu( $menu_id ) {
	$menu_id  = absint( $menu_id );
	$defaults = consucorner_footer_social_menu_defaults()['items'];
	$existing = wp_get_nav_menu_items( $menu_id );
	$present  = array(
		'instagram' => false,
		'facebook'  => false,
		'linkedin'  => false,
	);

	if ( is_array( $existing ) ) {
		foreach ( $existing as $item ) {
			$url     = isset( $item->url ) ? (string) $item->url : '';
			$classes = is_array( $item->classes ) ? $item->classes : array();
			$slug    = consucorner_footer_social_icon_slug( $url, $classes );

			if ( isset( $present[ $slug ] ) ) {
				$present[ $slug ] = true;
			}

			if ( 'instagram' === $slug ) {
				$instagram_url = function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_instagram_url' )
					: consucorner_footer_social_url_defaults()['footer_instagram_url'];
				wp_update_nav_menu_item(
					$menu_id,
					(int) $item->ID,
					array(
						'menu-item-title'   => 'Instagram',
						'menu-item-url'     => $instagram_url,
						'menu-item-status'  => 'publish',
						'menu-item-type'    => 'custom',
						'menu-item-classes' => 'social-instagram',
					)
				);
			} elseif ( 'facebook' === $slug ) {
				$facebook_url = function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_facebook_url' )
					: consucorner_footer_social_url_defaults()['footer_facebook_url'];
				wp_update_nav_menu_item(
					$menu_id,
					(int) $item->ID,
					array(
						'menu-item-title'   => 'Facebook',
						'menu-item-url'     => $facebook_url,
						'menu-item-status'  => 'publish',
						'menu-item-type'    => 'custom',
						'menu-item-classes' => 'social-facebook',
					)
				);
			} elseif ( 'linkedin' === $slug ) {
				$linkedin_url = function_exists( 'consucorner_get_footer_setting' )
					? consucorner_get_footer_setting( 'footer_linkedin_url' )
					: consucorner_footer_social_url_defaults()['footer_linkedin_url'];
				wp_update_nav_menu_item(
					$menu_id,
					(int) $item->ID,
					array(
						'menu-item-title'   => 'LinkedIn',
						'menu-item-url'     => $linkedin_url,
						'menu-item-status'  => 'publish',
						'menu-item-type'    => 'custom',
						'menu-item-classes' => 'social-linkedin',
					)
				);
			}
		}
	}

	foreach ( $defaults as $item ) {
		$slug = consucorner_footer_social_icon_slug( $item['url'], array( $item['classes'] ) );
		if ( ! isset( $present[ $slug ] ) || ! $present[ $slug ] ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'   => $item['title'],
					'menu-item-url'     => $item['url'],
					'menu-item-status'  => 'publish',
					'menu-item-type'    => 'custom',
					'menu-item-classes' => $item['classes'],
				)
			);
		}
	}
}
