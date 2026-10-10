<?php
defined( 'ABSPATH' ) || exit;

/** Explicit documentation choices; never infer executable attributes from prose. */
function backyard_manual_parameter_text( $description, $choices = array() ) {
	if ( ! $choices ) { return esc_html( $description ); }
	$labels = array_map( 'strval', array_keys( $choices ) );
	usort( $labels, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
	$pattern = '/(?<![\pL\pN_])(' . implode( '|', array_map( function ( $label ) { return preg_quote( $label, '/' ); }, $labels ) ) . ')(?![\pL\pN_])/u';
	$parts = preg_split( $pattern, $description, -1, PREG_SPLIT_DELIM_CAPTURE );
	$html = ''; $seen = array();
	foreach ( $parts as $index => $part ) {
		if ( $index % 2 ) {
			$html .= backyard_manual_parameter_button( $part, $choices[ $part ] );
			$seen[ $part ] = true;
		} else { $html .= esc_html( $part ); }
	}
	$extra = '';
	foreach ( $choices as $label => $value ) {
		if ( ! isset( $seen[ $label ] ) ) { $extra .= backyard_manual_parameter_button( (string) $label, $value ) . ' '; }
	}
	return $html . ( $extra ? '<div class="backyard-parameter-choices">' . $extra . '</div>' : '' );
}

function backyard_manual_parameter_button( $label, $copy ) {
	return '<span class="backyard-copy-item backyard-parameter-choice"><button type="button" class="backyard-copy-shortcode backyard-copy-value" data-copy="' . esc_attr( $copy ) . '" title="' . esc_attr( 'Kopieer ' . $copy ) . '" aria-label="' . esc_attr( 'Kopieer ' . $copy ) . '"><code>' . esc_html( $label ) . '</code><sup class="dashicons dashicons-admin-page" aria-hidden="true"></sup></button><span class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></span></span>';
}
