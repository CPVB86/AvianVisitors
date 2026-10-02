<?php
/** Run with php wordpress/tests/test-review.php. No live services or secrets used. */
require __DIR__ . '/test-backyard.php';

function wp_remote_post( $url, $args ) { return wp_remote_get( $url, $args ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_nonce_url( $url, $action ) { return add_query_arg( '_wpnonce', 'test-nonce', $url ); }
function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['nonce_ok'] && 'test-nonce' === $nonce && $action === 'backyard_birds_audio_' . $GLOBALS['review_id']; }
function wp_tempnam( $name, $directory ) { check( $directory === sys_get_temp_dir(), 'Audio uses system temporary storage' ); $file = tempnam( $directory, 'backyard-test-' ); $GLOBALS['audio_files'][] = $file; return $file; }
function wp_delete_file( $file ) { if ( file_exists( $file ) ) { unlink( $file ); } }
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][ $name ] ?? ''; }
class ReviewRedirect extends RuntimeException {}
function wp_safe_redirect( $url ) { throw new ReviewRedirect( $url ); }
function render_review() { ob_start(); try { backyard_birds_admin_page(); return ob_get_contents(); } finally { ob_end_clean(); } }
function render_index() { ob_start(); try { backyard_index_page(); return ob_get_contents(); } finally { ob_end_clean(); } }
function wp_add_dashboard_widget( $id, $title, $callback ) { $GLOBALS['dashboard_widgets'][ $id ] = array( $title, $callback ); }
function review_response( $code, $data ) { return array( 'response' => array( 'code' => $code ), 'body' => json_encode( $data ) ); }
function submit_review( $decision ) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array( 'observation_id' => $GLOBALS['review_id'], 'decision' => $decision );
	try { backyard_birds_handle_review(); } catch ( ReviewRedirect $redirect ) { return $redirect->getMessage(); }
	throw new RuntimeException( 'Expected post/redirect/get' );
}
function check_no_temp_files() {
	foreach ( $GLOBALS['audio_files'] ?? array() as $file ) { check( ! file_exists( $file ), 'Transport files must be removed' ); }
}

$review_id = '12345678-1234-4234-8234-123456789abc';
$expected_nonce_action = 'backyard_birds_review_' . $review_id;
$allowed = true;
$nonce_ok = true;
$_GET = array();
$pending = array(
	'id' => $review_id, 'domain' => 'bird', 'status' => 'pending_review',
	'timestamp' => '2026-10-02T08:30:00Z', 'common_name_nl' => 'Koolmees',
	'common_name' => 'Great Tit', 'scientific_name' => 'Parus major',
	'confidence' => .78, 'supports' => 3, 'audio_available' => true,
	'audio_url' => 'http://private-pi/secret-audio?token=' . $token,
);
$record = $pending;
$forced_post_code = 200;
$count_total = 152; // Larger than the list's limit; must use the real count endpoint.
$wav = 'RIFF' . pack( 'V', 36 ) . 'WAVE' . str_repeat( "\0", 32 );
$audio_code = 200;
$audio_body = $wav;
$audio_headers = array( 'content-type' => 'audio/wav', 'content-length' => strlen( $wav ) );
$http_handler = function ( $url, $args ) use ( &$record, &$forced_post_code, &$count_total, &$audio_code, &$audio_body, &$audio_headers ) {
	check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'All review requests must authenticate' );
	check( $args['redirection'] === 0, 'Never follow private API redirects' );
	$path = parse_url( $url, PHP_URL_PATH );
	parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $query );
	if ( '/api/observations/count' === $path || '/api/observations' === $path ) {
		check( $query['domain'] === 'bird' && $query['status'] === 'pending_review', 'Only pending birds requested' );
		if ( '/api/observations/count' === $path ) { return review_response( 200, array( 'count' => $count_total ) ); }
		check( $query['limit'] === '50', 'Bounded review list' );
		return review_response( 200, 'pending_review' === $record['status'] ? array( $record ) : array() );
	}
	if ( 'POST' === $args['method'] ) {
		check( in_array( $path, array( '/api/observations/' . $record['id'] . '/confirm', '/api/observations/' . $record['id'] . '/reject' ), true ), 'Only explicit review actions' );
		check( $args['headers']['Content-Type'] === 'application/json', 'JSON mutation' );
		check( json_decode( $args['body'], true ) === array( 'expected_status' => 'pending_review' ), 'Optimistic status precondition' );
		if ( 200 !== $forced_post_code ) { return review_response( $forced_post_code, array( 'detail' => 'private debug ' . $GLOBALS['token'] ) ); }
		$record['status'] = substr( $path, -8 ) === '/confirm' ? 'human_confirmed' : 'human_rejected';
		$count_total = 151;
		return review_response( 200, $record );
	}
	if ( $path === '/api/observations/' . $record['id'] . '/audio' ) {
		check( $args['stream'] === true && $args['limit_response_size'] === 67108865, 'Bounded streaming audio' );
		file_put_contents( $args['filename'], $audio_body );
		return array( 'response' => array( 'code' => $audio_code ), 'body' => '', 'headers' => $audio_headers );
	}
	check( $path === '/api/observations/' . $record['id'], 'Detail path cannot be supplied by user/API' );
	return review_response( 200, $record );
};

$before = count( $requests );
$allowed = false;
foreach ( $GLOBALS['actions']['wp_dashboard_setup'] as $callback ) { $callback(); }
check( empty( $GLOBALS['dashboard_widgets'] ), 'Dashboard widget only available to administrators' );
$allowed = true;
foreach ( $GLOBALS['actions']['wp_dashboard_setup'] as $callback ) { $callback(); }
check( $GLOBALS['dashboard_widgets']['backyard_status'] === array( 'Backyard', 'backyard_index_page' ), 'Native dashboard widget registered for Screen Options' );
check( count( $requests ) === $before, 'Dashboard registration does not fetch data' );
$html = render_index();
check( strpos( $html, '152 te reviewen waarnemingen' ) !== false && strpos( $html, 'page=backyard-birds' ) !== false, 'Uncapped linked summary count' );
$html = render_review();
foreach ( array( 'Koolmees', 'Parus major', '78,0%', '>3</td>', '02-10-2026 08:30:00', 'Bevestigen', 'Afwijzen', 'preload="none"' ) as $text ) { check( strpos( $html, $text ) !== false, 'Review fields: ' . $text ); }
check( strpos( $html, 'admin-post.php?action=backyard_birds_audio' ) !== false, 'Player uses WordPress proxy' );
check( strpos( $html, $token ) === false && strpos( $html, 'private-pi' ) === false && strpos( $html, $option ) === false, 'No private URL or bearer token in HTML' );
check( ! isset( $GLOBALS['actions']['admin_post_nopriv_backyard_birds_audio'] ), 'Audio not public' );
check( ! isset( $GLOBALS['actions']['admin_post_nopriv_backyard_birds_review'] ), 'Review not public' );
$record['common_name_nl'] = '<script>bird</script>';
check( strpos( render_review(), '&lt;script&gt;bird&lt;/script&gt;' ) !== false, 'NL name escaped' );
$record['audio_available'] = false;
$html = render_review();
check( strpos( $html, '<audio' ) === false && strpos( $html, 'Geen audio beschikbaar' ) !== false, 'Missing audio is explicit' );
$record = $pending;

foreach ( array( 'confirm' => 'confirmed', 'reject' => 'rejected' ) as $decision => $notice ) {
	$record = $pending;
	check( strpos( submit_review( $decision ), 'review_result=' . $notice ) !== false, 'Review redirects with result' );
	$_GET = array( 'review_result' => $notice );
	$html = render_review();
	check( strpos( $html, '151 te reviewen waarnemingen' ) !== false && strpos( $html, 'geen vogelwaarnemingen' ) !== false, 'Fresh count and list after mutation' );
	check( strpos( $html, 'notice-success' ) !== false, 'Success notice' );
}
$record = $pending;
$forced_post_code = 409;
check( strpos( submit_review( 'confirm' ), 'review_result=conflict' ) !== false, 'Concurrent status change' );
$forced_post_code = 500;
check( strpos( submit_review( 'reject' ), 'review_result=failed' ) !== false, 'Backend error is generic' );
$forced_post_code = 200;
$record['audio_available'] = false;
$before = count( $requests );
check( strpos( submit_review( 'confirm' ), 'review_result=audio_missing' ) !== false && count( $requests ) === $before + 1, 'Confirmation requires audio without changing backend policy' );
$record = $pending;
$before = count( $requests );
$record['domain'] = 'bat';
check( strpos( submit_review( 'reject' ), 'review_result=failed' ) !== false && count( $requests ) === $before + 1, 'Cannot review bats even with a valid UUID' );
check( is_wp_error( backyard_birds_pending_observations() ), 'Wrong-domain list cannot be rendered' );
$record = $pending;
$record['status'] = 'human_confirmed';
$before = count( $requests );
check( strpos( submit_review( 'reject' ), 'review_result=conflict' ) !== false && count( $requests ) === $before + 1, 'No mutation of already-reviewed records' );
$record = $pending;
foreach ( array( 'capability', 'nonce' ) as $gate ) {
	$before = count( $requests );
	$allowed = 'capability' !== $gate;
	$nonce_ok = 'nonce' !== $gate;
	try { submit_review( 'confirm' ); throw new RuntimeException( 'Gate bypassed' ); } catch ( RuntimeException $error ) { check( in_array( $error->getMessage(), array( 'denied', 'nonce' ), true ), 'Review authorization enforced' ); }
	check( count( $requests ) === $before, 'Unauthorized review never contacts API' );
}
$allowed = true; $nonce_ok = true;
foreach ( array( array( 'GET', $review_id, 'confirm' ), array( 'POST', '../../secret', 'reject' ), array( 'POST', $review_id, 'delete' ) ) as $input ) {
	$_SERVER['REQUEST_METHOD'] = $input[0]; $_POST = array( 'observation_id' => $input[1], 'decision' => $input[2] );
	$before = count( $requests );
	try { backyard_birds_handle_review(); throw new RuntimeException( 'Invalid action accepted' ); } catch ( RuntimeException $error ) { check( $error->getMessage() === 'denied', 'Invalid action refused' ); }
	check( count( $requests ) === $before, 'Malformed actions never reach API' );
}

$result = backyard_birds_audio_response( $review_id, 'test-nonce' );
check( ! is_wp_error( $result ) && file_get_contents( $result['file'] ) === $wav && $result['status'] === 200, 'Authenticated WAV retrieval' );
wp_delete_file( $result['file'] );
$audio_code = 206; $audio_body = substr( $wav, 0, 12 );
$audio_headers = array( 'content-type' => 'audio/wav', 'content-range' => 'bytes 0-11/44', 'content-length' => 12 );
$result = backyard_birds_audio_response( $review_id, 'test-nonce', 'bytes=0-11' );
check( ! is_wp_error( $result ) && $result['status'] === 206 && $result['content_range'] === 'bytes 0-11/44', 'Range response for seeking' );
check( end( $requests )[1]['headers']['Range'] === 'bytes=0-11', 'Range forwarded via authenticated client' );
wp_delete_file( $result['file'] );
foreach ( array( 'bytes=0-3,8-12', "bytes=0-12\r\nInjected: yes", array() ) as $range ) {
	$before = count( $requests );
	check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce', $range ) ), 'Malformed ranges refused' );
	check( count( $requests ) === $before + 1, 'No binary request for invalid range' );
}
foreach ( array( array( false, $review_id, 'test-nonce' ), array( true, $review_id, 'wrong' ), array( true, '../../etc/passwd', 'test-nonce' ) ) as $input ) {
	$allowed = $input[0]; $before = count( $requests );
	check( is_wp_error( backyard_birds_audio_response( $input[1], $input[2] ) ) && count( $requests ) === $before, 'Audio gated before API' );
}
$allowed = true;
$record['domain'] = 'bat';
$before = count( $requests );
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ) && count( $requests ) === $before + 1, 'Bat audio refused' );
$record = $pending;
$record['audio_available'] = false;
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Missing audio refused' );
$record = $pending;
foreach ( array( 401, 404, 416, 500, 302 ) as $code ) {
	$audio_code = $code;
	check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Upstream audio errors refused' );
	check_no_temp_files();
}
$audio_code = 200; $audio_body = 'private debug ' . $token; $audio_headers = array( 'content-type' => 'text/html' );
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Non-audio response never relayed' );
$audio_headers['content-type'] = 'audio/wav';
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Non-WAV bytes refused' );
$audio_body = $wav; $audio_headers['content-length'] = 999;
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Truncated audio refused' );
$audio_code = 206; $audio_headers = array( 'content-type' => 'audio/wav', 'content-range' => "bytes 0-11/44\r\nInjected: yes" );
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce', 'bytes=0-11' ) ), 'Upstream header injection refused' );
check_no_temp_files();

$http_handler = function () { return new WP_Error( 'private', 'secret ' . $GLOBALS['token'] ); };
$_GET = array( 'review_result' => 'failed' );
$html = render_index() . render_review();
check( strpos( $html, $token ) === false && strpos( $html, 'tijdelijk niet beschikbaar' ) !== false, 'API failures do not leak secrets or display a false zero count' );
check( is_wp_error( ( new Backyard_API_Client() )->audio( '/api/observations/' . $review_id . '/audio' ) ), 'Audio transport failure' );
check_no_temp_files();
echo "Birds review, count, actions and audio proxy tests passed (WordPress doubles).\n";
