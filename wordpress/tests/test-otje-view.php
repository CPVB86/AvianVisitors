<?php
/** Only three focused checks; reuse review doubles without running other suites. */
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$review_id = '12345678-1234-4234-8234-123456789abc';
$expected_nonce_action = 'backyard_birds_review_' . $review_id;
$record = array( 'id' => $review_id, 'domain' => 'bird', 'status' => 'pending_review',
	'classification' => 'unknown', 'scientific_name' => 'Backend selected species',
	'common_name_nl' => 'Kandidaat', 'timestamp' => '2026-10-07T12:00:00Z',
	'confidence' => .7, 'supports' => 1, 'audio_available' => true,
	'review_capabilities' => array( 'identity_overrides' => array( 'otje' ) ) );
check( backyard_birds_otje_candidate( $record ) && ! backyard_birds_otje_candidate( array_merge( $record, array( 'review_capabilities' => array() ) ) )
	&& ! backyard_birds_otje_candidate( array_merge( $record, array( 'status' => 'human_confirmed' ) ) ), 'Capability, not species, selects open candidates' );
$http_handler = function ( $url, $args ) use ( &$record ) {
	check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Existing Bearer client' );
	$path = parse_url( $url, PHP_URL_PATH );
	parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $query );
	if ( 'POST' === $args['method'] ) {
		check( substr( $path, -8 ) === '/confirm' && json_decode( $args['body'], true ) === array( 'expected_status' => 'pending_review', 'identity_override' => 'otje' ), 'Existing confirm contract' );
		$record['status'] = 'human_confirmed';
		$record['review'] = array( 'action' => 'confirm', 'identity_override' => 'otje' );
		return review_response( 200, $record );
	}
	if ( '/api/observations/count' === $path || '/api/observations/review' === $path ) {
		check( ( $query['identity_override'] ?? '' ) === 'otje', 'API filters before limiting/counting' );
		$open = backyard_birds_otje_candidate( $record );
		return review_response( 200, '/api/observations/count' === $path ? array( 'count' => $open ? 151 : 0 ) : ( $open ? array( $record ) : array() ) );
	}
	return review_response( 200, $record );
};
$_GET = array( 'view' => 'otje' );
foreach ( $actions['wp_dashboard_setup'] as $callback ) { $callback(); }
ob_start(); backyard_birds_otje_dashboard(); $card = ob_get_clean();
$view = render_review();
check( isset( $dashboard_widgets['backyard_otje'] ) && strpos( $card, '151 wachten op beoordeling' ) !== false && strpos( $card, 'view=otje' ) !== false
	&& strpos( $view, 'Kandidaat' ) !== false && strpos( $view, 'value="otje"' ) !== false, 'Dashboard uncapped count links to shared review view' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'observation_id' => $review_id, 'decision' => 'otje', 'review_view' => 'otje' );
try { backyard_birds_handle_review(); } catch ( ReviewRedirect $redirect ) { $location = $redirect->getMessage(); }
ob_start(); backyard_birds_otje_dashboard(); $card = ob_get_clean();
check( strpos( $location ?? '', 'review_result=otje' ) !== false && strpos( $location, 'view=otje' ) !== false
	&& backyard_birds_pending_observations( true ) === array() && strpos( $card, 'Geen kandidaten' ) !== false, 'Successful confirm removes candidate and refreshes count in same view' );
echo "Three focused Otje view/dashboard checks passed.\n";
