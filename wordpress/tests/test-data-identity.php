<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$GLOBALS['http_handler'] = function ( $url, $args ) {
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	if ( strpos( $url, '/api/generator/' ) !== false ) {
		$value = array( 'domain' => 'bird', 'species_key' => hash( 'sha256', 'Gallus domesticus' ), 'assets' => array( 'otje_perched' => array( 'content_type' => 'image/png' ) ) );
	} else {
		check( ( $q['identity'] ?? null ) === 'otje', 'Send explicit identity to Pi' );
		$row = array( 'species_id' => 'otje-id', 'name' => 'Otje', 'scientific_name' => 'Gallus domesticus', 'identity' => 'otje', 'count' => 9, 'last_seen' => '2026-10-09T12:13:14Z', 'assets' => array( 'perched' => 'otje_perched' ) );
		$value = array( 'module' => 'birds', 'period' => $q['period'], 'identity' => 'otje', 'species' => array( $row ), 'rankings' => array( 'last' => array( 'otje-id' ) ), 'stats' => array( 'total_observations' => 9 ) );
		if ( '24h' === $q['period'] ) { $value['species'] = array(); $value['rankings']['last'] = array(); $value['stats']['total_observations'] = 0; }
		if ( '7d' === $q['period'] ) { unset( $value['identity'] ); } // Old API ignores parameter.
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $value ) );
};
function otje_code( $args = array(), $tag = 'bird_data' ) { return backyard_data_shortcode( $args + array( 'identity' => 'otje' ), null, $tag ); }
check( otje_code() === 'Otje', 'Explicit identity with default type' );
check( otje_code( array( 'field' => 'count' ) ) === '9', 'Pi-combined count' );
check( otje_code( array( 'type' => 'species', 'field' => 'count' ) ) === '9', 'Identity selects without scientific name' );
check( otje_code( array( 'field' => 'last_seen' ) ) === '09-10-2026 12:13:14', 'Latest marked observation' );
check( otje_code( array( 'type' => 'stats', 'field' => 'total_observations' ) ) === '9' && count( $requests ) === 1, 'Shared identity snapshot and filtered stats' );
$image = otje_code( array( 'field' => 'perched', 'output' => 'image' ) );
check( strpos( $image, 'asset=otje_perched' ) !== false && strpos( $image, 'alt="Otje"' ) !== false, 'Otje image uses existing proxy and profile asset' );
check( otje_code( array( 'period' => '24h', 'fallback' => 'Geen Otje' ) ) === 'Geen Otje', 'No marked observations fallback' );
check( otje_code( array( 'period' => '24h', 'type' => 'stats', 'field' => 'total_observations' ) ) === '0', 'Empty identity stats remain zero' );
check( otje_code( array( 'period' => '7d', 'fallback' => 'Werk API bij' ) ) === 'Werk API bij', 'Fail closed if old API ignores identity' );
$count = count( $requests );
check( otje_code( array( 'fallback' => 'Niet beschikbaar' ), 'bat_data' ) === 'Niet beschikbaar', 'Otje is bird-only' );
check( otje_code( array( 'species' => 'Gallus gallus', 'fallback' => 'Ongeldig' ) ) === 'Ongeldig' && count( $requests ) === $count, 'No conflicting species/identity query' );
echo "Explicit Otje shortcode checks passed.\n";
