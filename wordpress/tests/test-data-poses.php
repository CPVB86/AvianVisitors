<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';

$http_handler = function ( $url, $args ) {
	check( 'GET' === $args['method'], 'Pose selection never starts generation' );
	$path = parse_url( $url, PHP_URL_PATH );
	$bat = false !== strpos( $path, '/bat' );
	$name = $bat ? 'Pipistrellus pipistrellus' : 'Erithacus rubecula';
	if ( strpos( $path, '/api/presentation/' ) === 0 ) {
		$data = array( 'module' => $bat ? 'bats' : 'birds', 'period' => 'all',
			'species' => array( array( 'species_id' => 'one', 'name' => $name, 'scientific_name' => $name,
				'assets' => array( 'hanging' => 'hanging', 'perched' => 'perched', 'flight' => 'flight' ) ) ),
			'stats' => array(), 'rankings' => array( 'last' => array( 'one' ) ) );
	} else {
		check( $path === '/api/generator/' . ( $bat ? 'bat' : 'bird' ) . '/species', 'Only existing read-only Generator lookup' );
		$data = array( 'domain' => $bat ? 'bat' : 'bird', 'species_key' => hash( 'sha256', $name ),
			'assets' => array_fill_keys( array( 'hanging', 'perched', 'flight' ), array( 'content_type' => 'image/png' ) ) );
	}
	return review_response( 200, $data );
};
foreach ( array( 'bird_data' => 'birds', 'bat_data' => 'bats', 'backyard_data' => 'bats' ) as $tag => $module ) {
	foreach ( array( 'perched', 'flying' ) as $field ) {
		$url = backyard_data_shortcode( array( 'module' => $module, 'field' => $field, 'output' => 'url' ), null, $tag );
		parse_str( parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_QUERY ) ?? '', $query );
		$expected = 'flying' === $field ? 'flight' : ( 'bats' === $module ? 'hanging' : 'perched' );
		check( ( $query['asset'] ?? null ) === $expected && $query['domain'] === ( 'bats' === $module ? 'bat' : 'bird' ), 'Correct domain and pose for ' . $tag . '/' . $field );
	}
}
check( count( $requests ) === 4, 'Reuse species snapshots and Generator lookups across poses and aliases' );
echo "Birds/Bats pose mapping and read-only lookup checks passed.\n";
