<?php
/** Public observation filtering and merge contract; no live API required. */
require __DIR__ . '/test-backyard.php';

$auto = array_merge( $bird, array( 'id' => 'auto', 'common_name' => 'Automatic', 'timestamp' => '2026-10-01T10:00:00.100Z' ) );
$confirmed = array_merge( $bird, array( 'id' => 'confirmed', 'status' => 'human_confirmed', 'common_name' => 'Confirmed', 'timestamp' => '2026-10-01T10:00:00.200Z' ) );
$older = array_merge( $auto, array( 'id' => 'older', 'common_name' => 'Older', 'timestamp' => '2026-09-30T10:00:00Z' ) );
$hidden = array();
foreach ( array( 'pending_review', 'human_rejected', 'review_recommended' ) as $status ) {
	$hidden[] = array_merge( $auto, array( 'id' => $status, 'status' => $status, 'common_name' => 'PRIVATE-' . $status ) );
}
$hidden[] = array_merge( $auto, array( 'domain' => 'bat', 'common_name' => 'PRIVATE-bat' ) );
$legacy = $auto;
unset( $legacy['status'], $legacy['domain'] );
$legacy['common_name'] = 'PRIVATE-legacy';
$hidden[] = $legacy;
$fail_status = null;
$http_handler = function ( $url, $args ) use ( &$auto, &$confirmed, &$older, $hidden, &$fail_status ) {
	check( parse_url( $url, PHP_URL_PATH ) === '/api/observations', 'Never fetch legacy detections' );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
	check( $query['domain'] === 'bird' && in_array( $query['status'], array( 'auto_accepted', 'human_confirmed' ), true ), 'Request only public bird statuses' );
	check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Public shortcode fetch authenticates on server' );
	$data = 'auto_accepted' === $query['status'] ? array( $older, $auto ) : array( $confirmed );
	return array( 'response' => array( 'code' => $query['status'] === $fail_status ? 500 : 200 ), 'body' => json_encode( array_merge( $data, $hidden ) ) );
};
$before = count( $requests );
$html = backyard_birds_log( array( 'limit' => 2 ) );
check( count( $requests ) === $before + 2, 'Two bounded requests for the single-status API' );
check( strpos( $html, 'Confirmed' ) < strpos( $html, 'Automatic' ), 'Merge sorts newest first, including subsecond timestamps' );
check( substr_count( $html, '<time ' ) === 2 && strpos( $html, 'Older' ) === false, 'Limit applies across both statuses' );
check( strpos( $html, 'PRIVATE-' ) === false, 'Private, unsupported and legacy rows never appear' );
check( strpos( $html, $token ) === false && strpos( $html, $option ) === false, 'No credentials or private URLs' );
$auto['timestamp'] = $confirmed['timestamp'];
check( backyard_birds_public_observations( 1 )[0]['id'] === 'confirmed', 'Stable descending ID tie-breaker' );
foreach ( array( 'auto_accepted', 'human_confirmed' ) as $status ) {
	$fail_status = $status;
	check( backyard_birds_log() === '<p>Vogelregistraties zijn tijdelijk niet beschikbaar.</p>', 'Either failed status request produces generic error' );
}
unset( $http_handler );
reply( 200, json_encode( $hidden ) );
check( backyard_birds_log() === '<p>Er zijn nog geen vogelregistraties.</p>', 'Only private/legacy records yields empty public state' );
echo "Public observation contract tests passed (WordPress doubles).\n";
