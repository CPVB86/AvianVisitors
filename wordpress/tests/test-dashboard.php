<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$http_handler = function ( $url, $args ) {
	check( 'GET' === $args['method'], 'Dashboard never mutates or generates' );
	$path = parse_url( $url, PHP_URL_PATH ); parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $q );
	if ( '/api/observations/count' === $path ) { return review_response( 200, array( 'count' => isset( $q['identity_override'] ) ? 2 : 4, 'identity_override' => $q['identity_override'] ?? null ) ); }
	if ( strpos( $path, '/api/generator/' ) === 0 ) { return review_response( 200, array( 'domain' => explode( '/', $path )[3], 'species_key' => hash( 'sha256', $q['scientific_name'] ), 'assets' => array( 'flight' => array( 'content_type' => 'image/png' ), 'perched' => array( 'content_type' => 'image/png' ) ) ) ); }
	check( strpos( $path, '/api/presentation/' ) === 0, 'Existing presentation endpoint only' );
	if ( 'otje' === ( $q['identity'] ?? '' ) ) {
		return review_response( 200, array( 'module' => 'birds', 'identity' => 'otje', 'period' => 'all', 'species' => array(), 'rankings' => array(), 'stats' => array( 'last_activity' => $GLOBALS['otje_last_seen'] ?? null ) ) );
	}
	$module = basename( $path ); $name = 'bats' === $module ? 'Dwergvleermuis' : 'Koolmees';
	return review_response( 200, array( 'module' => $module, 'period' => 'all', 'species' => array( array( 'species_id' => 'one', 'name' => $name, 'scientific_name' => $name, 'count' => 10, 'last_seen' => '2026-11-05T10:00:00Z' ) ), 'stats' => array( 'total_observations' => 10, 'unique_species' => 9, 'today_observations' => 3, 'today_species' => 1, 'active_days' => 2 ), 'rankings' => array( 'last' => array( 'one' ) ) ) );
};
foreach ( $actions['wp_dashboard_setup'] as $callback ) { $callback(); }
check( array_keys( $dashboard_widgets ) === array( 'backyard_birds', 'backyard_bats', 'backyard_otje' ), 'Three independent native dashboard cards' );
foreach ( array( 'birds', 'bats' ) as $module ) {
	ob_start(); backyard_dashboard_card( $module ); $html = ob_get_clean();
	check( strpos( $html, 'Waarnemingen vandaag' ) !== false && strpos( $html, '>3</dd>' ) !== false && strpos( $html, 'Waargenomen soorten vandaag' ) !== false && strpos( $html, '>1</dd>' ) !== false && strpos( $html, '>9</dd>' ) === false, 'API daily statistics rendered without local recount' );
	check( strpos( $html, 'asset=flight' ) !== false && strpos( $html, 'asset=perched' ) === false, 'Dashboard uses flying illustration' );
	check( strpos( $html, 'bats' === $module ? 'Dwergvleermuis' : 'Koolmees' ) !== false && strpos( $html, $token ) === false, 'Latest species stays within its module without secrets' );
	foreach ( array( 'species', 'stats', 'overview' ) as $view ) {
		$_GET = array( 'view' => $view ); ob_start(); backyard_birds_admin_page( 'bats' === $module ? 'bat' : 'bird' ); $html = ob_get_clean();
		check( strpos( $html, '>Overzicht</a>' ) === false && strpos( $html, '>Soorten</a>' ) !== false && strpos( $html, '>Statistieken</a>' ) !== false, 'Shared tabs, old overview links resolve to stats' );
		if ( 'birds' === $module ) { check( strpos( $html, '>Otje</a>' ) > strpos( $html, '>Statistieken</a>' ), 'Otje last' ); }
		else { check( strpos( $html, 'Otje' ) === false, 'No Otje tab for bats' ); }
	}
}
$otje_last_seen = '2026-11-05T09:12:34Z';
ob_start(); backyard_otje_dashboard_card(); $html = ob_get_clean();
check( strpos( $html, '2 te reviewen' ) !== false && strpos( $html, 'otje-2.png' ) !== false && strpos( $html, 'view=otje' ) !== false && strpos( $html, 'backyard-dashboard-last' ) !== false && strpos( $html, '05-11-2026 09:12:34' ) !== false, 'Otje flying image and identity-specific timestamp use shared styling' );
$otje_last_seen = null; $option = 'http://changed-test-api';
ob_start(); backyard_otje_dashboard_card(); $html = ob_get_clean();
check( strpos( $html, 'Dat is al een tijdje geleden' ) !== false && strpos( $html, 'Laatste waarneming' ) !== false && strpos( $html, 'otje-2.png' ) !== false, 'Missing Otje timestamp retains label, image and fallback' );
echo "Dashboard cards, API stats and shared module tabs passed.\n";
