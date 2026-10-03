<?php
require __DIR__ . '/test-backyard.php';
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][ $name ] ?? ''; }
$transients = array();
$key = hash( 'sha256', 'Parus major' );
$assets = array_fill_keys( array( 'perched', 'flight', 'photo_cutout' ), array( 'content_type' => 'image/png', 'url' => 'https://private/never-use' ) );
$lookup_count = 0;
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
$image_body = $png;
$image_type = 'image/png';
$image_code = 200;
$http_handler = function ( $url, $args ) use ( &$lookup_count, &$assets, $key, &$image_body, &$image_type, &$image_code, $bird ) {
	check( ( $args['method'] ?? 'GET' ) === 'GET', 'Never generate or mutate' );
	check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'] && $args['redirection'] === 0, 'Authenticated, no redirects' );
	$path = parse_url( $url, PHP_URL_PATH );
	if ( '/api/observations' === $path ) {
		parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
		$rows = array();
		if ( 'auto_accepted' === $query['status'] ) { for ( $i = 0; $i < 15; ++$i ) { $rows[] = array_merge( $bird, array( 'id' => 'bird-' . $i ) ); } }
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $rows ) );
	}
	if ( '/api/generator/bird/species' === $path ) {
		++$lookup_count;
		parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
		check( $query['scientific_name'] === 'Parus major', 'Scientific identity lookup' );
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'domain' => 'bird', 'species_key' => $key, 'assets' => $assets ) ) );
	}
	check( $path === '/api/generator/bird/assets/' . $key . '/perched', 'Only constructed asset path is fetched' );
	check( $args['limit_response_size'] === 8388609, 'Bounded binary response' );
	return array( 'response' => array( 'code' => $image_code ), 'body' => $image_body, 'headers' => array( 'content-type' => $image_type ) );
};
$html = backyard_birds_log();
check( $lookup_count === 1 && substr_count( $html, '<img ' ) === 15, 'Fifteen observations share one lookup' );
check( strpos( $html, '>Vogel</th><th scope="col">Soort' ) !== false, 'Image column precedes species' );
check( strpos( $html, $token ) === false && strpos( $html, 'https://private' ) === false && strpos( $html, $option ) === false, 'No upstream URL or token in HTML' );
backyard_birds_log();
check( $lookup_count === 1 && in_array( 300, $transient_ttls, true ), 'Lookup reused across renders for five minutes' );
$url = ( new Backyard_Generator() )->thumbnail( 'bird', 'Parus major', array( 'perched', 'flight', 'photo_cutout' ) );
parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
check( $params['asset'] === 'perched', 'Perched preference' );
$result = Backyard_Generator::image( $params['domain'], $params['key'], $params['asset'], $params['signature'] );
check( $result['body'] === $png && $result['type'] === 'image/png', 'Transparent PNG bytes preserved' );
$before = count( $requests );
foreach ( array( array( 'bird', $key, 'flight', $params['signature'] ), array( 'bird', '../escape', 'perched', $params['signature'] ), array( 'bird', $key, 'perched', 'invalid' ), array( array(), $key, 'perched', '' ) ) as $input ) {
	check( is_wp_error( Backyard_Generator::image( ...$input ) ), 'Invalid signatures and paths refused' );
}
check( count( $requests ) === $before, 'Invalid requests never reach API' );
foreach ( array( 302, 401, 404, 500 ) as $image_code ) {
	check( is_wp_error( Backyard_Generator::image( 'bird', $key, 'perched', $params['signature'] ) ), 'Upstream errors are not relayed' );
}
$image_code = 200;
foreach ( array( '<svg onload="evil"/>', '<html>private debug</html>', str_repeat( 'x', 8388609 ) ) as $image_body ) {
	check( is_wp_error( Backyard_Generator::image( 'bird', $key, 'perched', $params['signature'] ) ), 'Reject non-raster and oversize data' );
}
foreach ( array( 'flight', 'photo_cutout', '' ) as $expected ) {
	array_shift( $assets );
	$transients = array();
	$url = ( new Backyard_Generator() )->thumbnail( 'bird', 'Parus major', array( 'perched', 'flight', 'photo_cutout' ) );
	check( '' === $expected ? '' === $url : strpos( $url, 'asset=' . $expected ) !== false, 'Asset fallback order or empty result' );
}
check( strpos( backyard_birds_log(), '<table' ) !== false && strpos( backyard_birds_log(), '<img ' ) === false, 'Missing assets preserve table' );
check( in_array( 60, $transient_ttls, true ), 'Missing assets cached briefly' );
check( isset( $actions['admin_post_nopriv_backyard_generator_asset'], $actions['admin_post_backyard_generator_asset'] ), 'Public and authenticated image routes registered' );
echo "Generator contract tests passed (WordPress doubles).\n";
