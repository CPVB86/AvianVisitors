<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$expected_nonce_action = 'backyard_birds_bulk_review';
$ids = array( '12345678-1234-4234-8234-123456789ab1', '12345678-1234-4234-8234-123456789ab2', '12345678-1234-4234-8234-123456789ab3' );
$base = array( 'domain' => 'bird', 'status' => 'pending_review', 'classification' => 'human_review', 'scientific_name' => 'Parus major', 'common_name' => 'Great Tit', 'confidence' => .8, 'timestamp' => gmdate( 'c' ), 'supports' => 2, 'audio_available' => true );
$records = array();
foreach ( $ids as $id ) { $records[ $id ] = array_merge( $base, array( 'id' => $id ) ); }
$records[ $ids[2] ]['status'] = 'human_rejected';
$http_handler = function ( $url, $args ) use ( &$records ) {
	$path = parse_url( $url, PHP_URL_PATH );
	parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $q );
	if ( strpos( $path, '/api/generator/' ) === 0 ) { return review_response( 200, array( 'assets' => array() ) ); }
	if ( '/api/observations/count' === $path ) { return review_response( 200, array( 'count' => 2 ) ); }
	if ( '/api/observations/review' === $path ) { return review_response( 200, array_values( array_filter( $records, function ( $row ) { return $row['status'] === 'pending_review'; } ) ) ); }
	if ( '/api/observations' === $path ) { return review_response( 200, array_values( array_filter( $records, function ( $row ) use ( $q ) { return $row['status'] === $q['status']; } ) ) ); }
	$parts = explode( '/', $path ); $id = $parts[3];
	if ( 'POST' === $args['method'] ) {
		check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Bulk uses existing authenticated client' );
		$records[ $id ]['status'] = end( $parts ) === 'confirm' ? 'human_confirmed' : 'human_rejected';
	}
	return review_response( 200, $records[ $id ] );
};
$_GET = array();
$html = render_review();
check( substr_count( $html, 'name="decision" value="confirm"' ) === 1 && substr_count( $html, 'name="observation_ids[]"' ) === 2 && strpos( $html, 'backyard-select-all' ) !== false && strpos( $html, 'assets/nest.webp' ) !== false, 'One shared action set, selection and nest fallback' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'observation_ids' => array_merge( $ids, array( $ids[0] ) ), 'decision' => 'confirm' );
try { backyard_birds_handle_bulk_review(); } catch ( ReviewRedirect $e ) { $redirect = $e->getMessage(); }
check( strpos( $redirect, 'done=2' ) !== false && strpos( $redirect, 'failed=1' ) !== false, 'Deduplicate selection and report stale observation separately' );
foreach ( array( 'allowed', 'nonce_ok' ) as $gate ) {
	$GLOBALS[ $gate ] = false; $before = count( $requests );
	try { backyard_birds_handle_bulk_review(); } catch ( RuntimeException $e ) {}
	check( count( $requests ) === $before, 'Bulk capability/nonce rejects before API access' ); $GLOBALS[ $gate ] = true;
}
$before = count( $requests ); $_POST['observation_ids'] = array( $ids[0], '../bad' );
try { backyard_birds_handle_bulk_review(); } catch ( RuntimeException $e ) {}
check( count( $requests ) === $before, 'Validate entire selection before mutations' );
$records[ $ids[0] ]['status'] = 'auto_accepted';
$records[ $ids[1] ]['timestamp'] = gmdate( 'c', time() - 7200 );
$_GET = array( 'view' => 'confirmed', 'period' => '1h' );
check( count( backyard_birds_confirmed_observations() ) === 1, 'One-hour filter uses observation time' );
$_GET['period'] = '12h';
check( count( backyard_birds_confirmed_observations() ) === 2, 'Twelve-hour filter includes both accepted statuses' );
foreach ( array( '24h', '7d', 'all' ) as $period ) { $_GET['period'] = $period; check( count( backyard_birds_confirmed_observations() ) === 2, 'Time filter ' . $period ); }
$_GET['status'] = 'human_confirmed';
$html = render_review();
check( strpos( $html, 'Handmatig bevestigd' ) !== false && strpos( $html, 'name="observation_ids[]"' ) === false && strpos( $html, 'name="decision"' ) === false, 'Confirmed tab is filtered and read-only' );
echo "Focused bulk review, image fallback and confirmed-tab checks passed.\n";
