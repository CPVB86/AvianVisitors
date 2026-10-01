<?php
defined( 'ABSPATH' ) || exit;

/** Documentation registry only; actual shortcode callbacks belong to modules.
 * Entries: module, shortcode, optional title, parameters (name => explanation), description.
 * Optional parameter_examples maps parameter names to copyable shortcode examples.
 */
function backyard_shortcode_docs() {
	return apply_filters( 'backyard_shortcode_docs', array() );
}
