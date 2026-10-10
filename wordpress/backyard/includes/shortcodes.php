<?php
defined( 'ABSPATH' ) || exit;

/** Documentation registry only; actual shortcode callbacks belong to modules.
 * Entries: module, shortcode, optional title, parameters (name => explanation), description.
 * Optional parameter_choices maps row names to label => exact copy text.
 * Optional parameter_examples maps parameter names to copyable shortcode examples.
 */
function backyard_shortcode_docs() {
	return apply_filters( 'backyard_shortcode_docs', array() );
}

/** Build reusable copyable attribute values for the documentation registry. */
function backyard_parameter_choices( $parameter, $values ) {
	$choices = array();
	foreach ( $values as $value ) { $choices[ (string) $value ] = $parameter . '="' . $value . '"'; }
	return $choices;
}
