<?php
defined( 'ABSPATH' ) || exit;

/** Documentation registry only; actual shortcode callbacks belong to modules.
 * Entries: module, shortcode, optional title, parameters (name => explanation), description.
 */
function backyard_shortcode_docs() {
	return apply_filters( 'backyard_shortcode_docs', array() );
}
