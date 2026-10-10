<?php
defined( 'ABSPATH' ) || exit;

function backyard_lucide_icons() {
	static $icons = null;
	if ( null === $icons ) {
		$icons = json_decode( file_get_contents( dirname( __DIR__ ) . '/assets/lucide/icons.json' ), true );
		if ( ! is_array( $icons ) ) { $icons = array(); }
	}
	return $icons;
}

function backyard_icon_shortcode( $attributes = array() ) {
	$a = shortcode_atts( array( 'name' => 'bird', 'size' => '24', 'color' => 'currentColor', 'stroke' => '2', 'label' => '' ), $attributes, 'backyard_icon' );
	foreach ( $a as $value ) { if ( ! is_scalar( $value ) ) { return ''; } }
	$name = strtolower( trim( $a['name'] ) );
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name ) ) { return ''; }
	$icons = backyard_lucide_icons();
	if ( ! isset( $icons[ $name ] ) ) { return ''; }
	$size = filter_var( $a['size'], FILTER_VALIDATE_INT );
	$size = false === $size ? 24 : max( 8, min( 256, $size ) );
	$stroke = is_numeric( $a['stroke'] ) ? max( 0.5, min( 4, (float) $a['stroke'] ) ) : 2;
	$color = preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/iD', $a['color'] ) ? $a['color'] : 'currentColor';
	$accessibility = '' === trim( $a['label'] ) ? ' aria-hidden="true"' : ' role="img" aria-label="' . esc_attr( $a['label'] ) . '"';
	// Only trusted, locally bundled SVG geometry; shortcode values never become SVG markup.
	return '<svg class="backyard-icon" xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="' . esc_attr( (string) $stroke ) . '" stroke-linecap="round" stroke-linejoin="round" focusable="false"' . $accessibility . '>' . $icons[ $name ] . '</svg>';
}
add_shortcode( 'backyard_icon', 'backyard_icon_shortcode' );

add_filter( 'elementor/icons_manager/additional_tabs', function ( $tabs ) {
	$base = plugins_url( 'assets/lucide/', dirname( __DIR__ ) . '/backyard.php' );
	$tabs['backyard-lucide'] = array(
		'name' => 'backyard-lucide', 'label' => 'Lucide', 'url' => $base . 'lucide.css',
		'enqueue' => array(), 'prefix' => 'by-lucide-', 'displayPrefix' => 'backyard-lucide',
		'labelIcon' => 'eicon-star', 'ver' => '1.54.0', 'fetchJson' => $base . 'elementor.json',
		'native' => false,
	);
	return $tabs;
} );

add_filter( 'backyard_shortcode_docs', function ( $entries ) {
	$entries[] = array( 'module' => 'Lucide-iconen', 'title' => 'Lucide-icoon', 'shortcode' => '[backyard_icon name="bird"]',
		'description' => 'Lokale Lucide-iconen. In Elementor ook beschikbaar via de iconenkiezer: Lucide. Namen vind je op lucide.dev/icons. De shortcode geeft een SVG zonder JavaScript; standaard volgt de kleur de tekstkleur.',
		'parameters' => array( 'name' => 'Lucide-naam, bijvoorbeeld bird, feather, sun, moon, leaf, carrot of volume-2.', 'size' => 'Grootte in pixels; standaard 24, minimaal 8, maximaal 256.', 'color' => 'currentColor (standaard, erft tekstkleur) of hexkleur, bijvoorbeeld #2271b1.', 'stroke' => 'Lijndikte; standaard 2, minimaal 0.5, maximaal 4. Alleen voor de SVG-shortcode.', 'label' => 'Optionele toegankelijke omschrijving. Leeg betekent decoratief. Bijvoorbeeld Vogel.' ),
		'parameter_choices' => array( 'name' => backyard_parameter_choices( 'name', array( 'bird', 'feather', 'sun', 'moon', 'leaf', 'carrot', 'volume-2' ) ), 'size' => backyard_parameter_choices( 'size', array( 16, 24, 32, 48 ) ), 'color' => backyard_parameter_choices( 'color', array( 'currentColor', '#2271b1' ) ), 'stroke' => backyard_parameter_choices( 'stroke', array( 1, 1.5, 2, 3 ) ), 'label' => backyard_parameter_choices( 'label', array( 'Vogel' ) ) ),
		'parameter_examples' => array( 'name' => '[backyard_icon name="feather" size="32" label="Vogel"]' ),
	);
	return $entries;
}, 30 );
