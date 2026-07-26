<?php
/**
 * One-off: build assets/admin/fa-icon-catalog.json from Dokan Font Awesome CSS.
 * Run: php scripts/build-fa-catalog.php
 */

$css_path = dirname( __DIR__, 4 ) . '/plugins/dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css';
if ( ! is_readable( $css_path ) ) {
	$css_path = dirname( __DIR__, 3 ) . '/plugins/dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css';
}

if ( ! is_readable( $css_path ) ) {
	fwrite( STDERR, "Font Awesome CSS not found.\n" );
	exit( 1 );
}

$css   = file_get_contents( $css_path );
$skip  = array_flip(
	array(
		'solid', 'regular', 'brands', '1x', '2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x',
		'2xs', 'xs', 'sm', 'lg', 'xl', '2xl', 'fw', 'ul', 'li', 'border', 'pull-left', 'pull-right',
		'beat', 'bounce', 'fade', 'flip', 'shake', 'spin', 'pulse', 'spin-pulse', 'stack', 'inverse',
		'sr-only', 'sr-only-focusable',
	)
);
$names = array();

if ( preg_match_all( '/\.fa-([a-z0-9-]+):before\{content:"\\\\/', $css, $matches ) ) {
	foreach ( $matches[1] as $name ) {
		if ( isset( $skip[ $name ] ) || ctype_digit( $name ) ) {
			continue;
		}
		$names[ $name ] = true;
	}
}

$icons = array();
foreach ( array_keys( $names ) as $name ) {
	sort( $names );
}
ksort( $names );

foreach ( array_keys( $names ) as $name ) {
	$label = ucwords( str_replace( '-', ' ', $name ) );
	$icons[] = array(
		'class' => 'fa-solid fa-' . $name,
		'label' => $label,
		'terms' => $name . ' ' . strtolower( $label ),
	);
}

$out = dirname( __DIR__ ) . '/assets/admin/fa-icon-catalog.json';
file_put_contents( $out, wp_json_encode( $icons, JSON_UNESCAPED_UNICODE ) );
echo 'Wrote ' . count( $icons ) . ' icons to ' . $out . PHP_EOL;
