<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$species = array(
	array( 'species_id' => 'a', 'name' => 'Roodborst', 'scientific_name' => 'Erithacus rubecula', 'count' => 3, 'first_seen' => '2026-10-01T10:11:12Z', 'last_seen' => '2026-10-09T12:13:14Z', 'wikipedia_url' => 'https://nl.wikipedia.org/wiki/Roodborst', 'observations_url' => 'https://waarneming.nl/species/1/', 'assets' => array( 'perched' => 'perched', 'flight' => 'flight' ) ),
	array( 'species_id' => 'b', 'name' => '<Merel>', 'scientific_name' => 'Turdus merula', 'count' => 2 ),
);
$GLOBALS['http_handler'] = function ( $url, $args ) use ( $species ) {
	if ( strpos( $url, '/api/generator/' ) !== false ) {
		$value = array( 'domain' => 'bird', 'species_key' => hash( 'sha256', 'Erithacus rubecula' ), 'assets' => array( 'perched' => array( 'content_type' => 'image/png' ) ) );
	} else {
		parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
		$bats = strpos( $url, '/bats?' ) !== false;
		$value = array( 'module' => $bats ? 'bats' : 'birds', 'period' => $query['period'], 'species' => $bats ? array() : $species,
			'stats' => array( 'total_observations' => $bats ? 0 : 5, 'unique_species' => $bats ? 0 : 2 ), 'rankings' => array_fill_keys( array( 'last', 'first', 'most', 'rarest', 'random' ), $bats ? array() : array( 'a', 'b' ) ) );
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $value ) );
};
function data_code( $a = array(), $tag = 'bird_data' ) { return backyard_data_shortcode( $a, null, $tag ); }
check( data_code() === 'Roodborst', 'Default selection' );
check( data_code( array( 'module' => 'bats' ) ) === 'Roodborst', 'Bird alias fixes module' );
foreach ( array( 'first', 'most', 'rarest', 'random', 'last' ) as $type ) {
	check( data_code( array( 'type' => $type, 'field' => 'scientific_name' ) ) === 'Erithacus rubecula', 'Shared ranking ' . $type );
}
check( data_code( array( 'type' => 'most', 'rank' => '2' ) ) === '&lt;Merel&gt;', 'Rank and escaping' );
check( data_code( array( 'type' => 'species', 'species' => 'Turdus merula', 'field' => 'count' ) ) === '2', 'Specific species' );
check( data_code( array( 'field' => 'first_date' ) ) === '01-10-2026', 'First date' );
check( data_code( array( 'field' => 'last_time' ) ) === '12:13:14', 'Last time' );
check( data_code( array( 'field' => 'first_seen', 'format' => 'Y' ) ) === '2026', 'Custom format' );
check( data_code( array( 'type' => 'stats', 'field' => 'unique_species' ) ) === '2', 'Statistics field' );
check( count( $requests ) === 1, 'Share a single snapshot across fields and types' );
$image = data_code( array( 'field' => 'perched', 'output' => 'image' ) );
check( strpos( $image, '<img ' ) === 0 && strpos( $image, 'alt="Roodborst"' ) !== false && strpos( $image, 'backyard_generator_asset' ) !== false && strpos( $image, $token ) === false && strpos( $image, '192.168.' ) === false, 'Accessible image uses WP proxy without secrets' );
check( strpos( data_code( array( 'field' => 'perched', 'output' => 'url' ) ), '<img' ) === false && count( $requests ) === 2, 'Image URL reuses lookup' );
check( data_code( array( 'field' => 'flying', 'output' => 'image', 'fallback' => 'Geen afbeelding' ) ) === 'Geen afbeelding', 'Missing pose does not generate or substitute assets' );
check( strpos( data_code( array( 'field' => 'wikipedia_url', 'output' => 'link' ) ), '<a href="https://nl.wikipedia.org/' ) === 0, 'Link output' );
check( data_code( array( 'fallback' => 'Nog leeg' ), 'bat_data' ) === 'Nog leeg', 'Empty bats fallback' );
check( data_code( array( 'type' => 'stats', 'field' => 'total_observations', 'fallback' => 'Geen' ), 'bat_data' ) === '0', 'Zero is data, not fallback' );
$count = count( $requests );
check( data_code( array( 'rank' => '11', 'fallback' => '<invalid>' ) ) === '&lt;invalid&gt;' && count( $requests ) === $count, 'Validate before requests' );
$GLOBALS['http_handler'] = function () { return new WP_Error( 'private', 'Secret URL/token' ); };
check( data_code( array( 'period' => '7d', 'fallback' => 'Onbeschikbaar' ) ) === 'Onbeschikbaar', 'API failure never leaks details' );
foreach ( array( 'overview', 'observations', 'species', 'stats' ) as $view ) {
	$_GET = array( 'view' => $view );
	ob_start(); backyard_bats_admin_page(); $html = ob_get_clean();
	check( strpos( $html, 'Otje' ) === false && strpos( $html, '<h1>Bats</h1>' ) !== false, 'Bats tabs reuse admin without Otje' );
}
echo "Shared shortcode, image, cache and Bats checks passed.\n";
