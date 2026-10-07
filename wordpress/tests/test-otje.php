<?php
require __DIR__ . '/test-review.php';

$record = array_merge( $pending, array( 'scientific_name' => 'Gallus gallus', 'common_name' => 'Red Junglefowl', 'common_name_nl' => 'Bankivahoen' ) );
$original = $record;
$writes = array();
$omit_identity = false;
$post_code = 200;
$http_handler = function ( $url, $args ) use ( &$record, &$writes, &$omit_identity, &$post_code ) {
	check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Otje uses existing Bearer client' );
	if ( 'POST' === $args['method'] ) {
		check( substr( $url, -8 ) === '/confirm', 'Otje uses the existing confirm endpoint' );
		$payload = json_decode( $args['body'], true );
		$writes[] = $payload;
		check( $payload === array( 'expected_status' => $record['status'], 'identity_override' => 'otje' ), 'Only explicit override and status; no evidence replacement' );
		$result = array_merge( $record, array( 'status' => 'human_confirmed', 'review' => array( 'action' => 'confirm' ) ) );
		if ( ! $omit_identity ) { $result['review']['identity_override'] = 'otje'; }
		return review_response( $post_code, $result );
	}
	return review_response( 200, $record );
};
ob_start(); backyard_birds_otje_button( $record ); $html = ob_get_clean();
check( strpos( $html, '🐔 Otje' ) !== false && strpos( $html, ' disabled' ) !== false, 'Old API shows disabled Otje with explanation' );
check( strpos( submit_review( 'otje' ), 'identity_unavailable' ) !== false && ! $writes, 'Old API cannot silently confirm without identity' );
$record['review_capabilities'] = array( 'identity_overrides' => array( 'otje' ) );
ob_start(); backyard_birds_otje_button( $record ); $html = ob_get_clean();
check( strpos( $html, ' disabled' ) === false, 'Supported API enables explicit choice' );
check( strpos( submit_review( 'otje' ), 'review_result=otje' ) !== false && count( $writes ) === 1, 'Success requires confirmed override response' );
foreach ( array( 'scientific_name', 'common_name', 'confidence', 'timestamp' ) as $field ) {
	check( $record[ $field ] === $original[ $field ], 'Original evidence unchanged in request: ' . $field );
}
$omit_identity = true;
check( strpos( submit_review( 'otje' ), 'failed' ) !== false, 'Confirm without echoed identity is never Otje success' );
$omit_identity = false;
foreach ( array( 409 => 'conflict', 422 => 'failed', 500 => 'failed' ) as $post_code => $expected ) {
	check( strpos( submit_review( 'otje' ), $expected ) !== false, 'Safe handling of API failure' );
}
$before = count( $writes );
$record['audio_available'] = false;
check( strpos( submit_review( 'otje' ), 'audio_missing' ) !== false && count( $writes ) === $before, 'Existing audio requirement preserved' );
$record['audio_available'] = true;
$record['scientific_name'] = 'Parus major';
ob_start(); backyard_birds_otje_button( $record ); $html = ob_get_clean();
check( '' === $html, 'No Otje action for unrelated species' );
check( strpos( submit_review( 'otje' ), 'identity_unavailable' ) !== false && count( $writes ) === $before, 'Forged unrelated species action refused' );
$record['scientific_name'] = 'Gallus gallus';
$record['domain'] = 'bat';
check( strpos( submit_review( 'otje' ), 'failed' ) !== false && count( $writes ) === $before, 'Wrong domain refused' );
$record['domain'] = 'bird';
foreach ( array( 'allowed', 'nonce_ok' ) as $gate ) {
	$GLOBALS[ $gate ] = false;
	$requests_before = count( $requests );
	try { submit_review( 'otje' ); throw new RuntimeException( 'unexpected' ); }
	catch ( RuntimeException $e ) { check( in_array( $e->getMessage(), array( 'denied', 'nonce' ), true ), 'Existing access/nonce gate retained' ); }
	check( count( $requests ) === $requests_before, 'Unauthorized action never reaches API' );
	$GLOBALS[ $gate ] = true;
}
echo "Otje review contract tests passed (WordPress doubles; backend implementation still required).\n";
