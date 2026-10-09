<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
// Like WordPress add_query_arg, do not URL-encode newly supplied values.
define( 'BACKYARD_TEST_RAW_QUERY', true );
require __DIR__ . '/test-review.php';
$GLOBALS['http_handler'] = function ( $url, $args ) {
	parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
	check( $query['timezone'] === $GLOBALS['test_timezone'], 'Timezone survives WordPress query building and API decoding' );
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'module' => 'birds', 'period' => 'all', 'species' => array(), 'stats' => array(), 'rankings' => array() ) ) );
};
foreach ( array( '+02:00', '+00:00', '-03:30', 'Europe/Amsterdam' ) as $zone ) {
	$GLOBALS['test_timezone'] = $zone;
	check( ! is_wp_error( backyard_data_snapshot( 'birds' ) ), 'Valid timezone accepted' );
}
echo "Timezone query encoding checks passed.\n";
