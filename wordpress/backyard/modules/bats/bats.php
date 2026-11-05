<?php
defined( 'ABSPATH' ) || exit;

function backyard_bats_summary() {
	$count = backyard_birds_review_count( false, 'bat' );
	return is_wp_error( $count ) ? $count : array( 'text' => sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ), 'url' => admin_url( 'admin.php?page=backyard-bats' ) );
}

function backyard_bats_admin_page() {
	// Keep existing Waarnemingen links working.
	if ( 'observations' === ( $_GET['view'] ?? '' ) ) { $_GET['view'] = 'confirmed'; }
	backyard_birds_admin_page( 'bat' );
}

return array( 'title' => 'Bats', 'description' => 'Vleermuiswaarnemingen, soorten en statistieken.', 'admin_page' => 'backyard_bats_admin_page', 'summary' => 'backyard_bats_summary' );
