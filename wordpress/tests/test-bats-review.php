<?php
/** Focused shared review contract checks; no live WP/Pi required. */
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
define( 'BACKYARD_TEST_RAW_QUERY', true );
require __DIR__ . '/test-review.php';
function wp_create_nonce( $action ) { return 'test-nonce'; }
function get_current_user_id() { return 42; }
function wp_unslash( $value ) { return $value; }
function wp_generate_uuid4() { return 'abcddcba-1234-4234-8234-123456789abc'; }
function check_ajax_referer( $action, $key ) { check( $GLOBALS['nonce_ok'] && $action === 'backyard_corrections', 'AJAX nonce' ); }
class BatAjaxResult extends RuntimeException { public $data; function __construct( $data ) { $this->data = $data; } }
function wp_send_json_success( $data ) { throw new BatAjaxResult( $data ); }
function wp_send_json_error( $data, $status ) { throw new RuntimeException( 'ajax-error-' . $status ); }
function bat_ajax( $operation, $extra = array() ) {
    $_POST = $extra + array( 'domain' => 'bat', 'operation' => $operation, 'id' => $GLOBALS['review_id'] );
    try { backyard_birds_correction_ajax(); } catch ( BatAjaxResult $result ) { return $result->data; }
}
function render_bats() { ob_start(); try { backyard_bats_admin_page(); return ob_get_contents(); } finally { ob_end_clean(); } }
$review_id = '12345678-1234-4234-8234-123456789abc';
$audio_nonce_prefix = 'backyard_bats_audio_';
$record = array( 'id' => $review_id, 'domain' => 'bat', 'status' => 'pending_review', 'classification' => 'unknown',
    'timestamp' => '2026-10-15T20:00:01Z', 'scientific_name' => 'Pipistrellus pipistrellus', 'common_name_nl' => 'Gewone dwergvleermuis',
    'confidence' => .96, 'supports' => 1, 'audio_available' => true, 'audio_url' => 'http://private/secret', 'review_version' => 0,
    'correction_capabilities' => array( 'actions' => array( 'confirm', 'reject' ) ) );
$http_handler = function ( $url, $args ) use ( &$record ) {
    check( $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Bearer on all requests' );
    $path = parse_url( $url, PHP_URL_PATH ); parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $q );
    if ( '/api/observations/count' === $path || '/api/observations' === $path ) {
        check( $q['domain'] === 'bat' && ! isset( $q['review_only'] ), 'Bat open-status selection, including unknown plausibility' );
        $matches = $q['status'] === $record['status'];
        return review_response( 200, '/api/observations/count' === $path ? array( 'count' => $matches ? 1 : 0 ) : ( $matches ? array( $record ) : array() ) );
    }
    if ( '/api/generator/bat/species' === $path ) { return review_response( 200, array( 'domain' => 'bat', 'species_key' => hash( 'sha256', $record['scientific_name'] ), 'assets' => array( 'flight' => array( 'content_type' => 'image/png' ) ) ) ); }
    if ( '/api/species/search' === $path ) {
        check( $q['domain'] === 'bat' && $q['q'] === 'Myotis daubentonii', 'Domain-scoped encoded species search' );
        return review_response( 200, array( 'results' => array( array( 'scientific_name' => 'Myotis daubentonii', 'common_name_nl' => 'Watervleermuis' ) ) ) );
    }
    if ( substr( $path, -6 ) === '/audio' ) {
        check( $args['headers']['Range'] === 'bytes=0-11' && $args['stream'], 'Range forwarded to streamed transport' );
        file_put_contents( $args['filename'], 'RIFF1234WAVE' );
        return array( 'response' => array( 'code' => 206 ), 'body' => '', 'headers' => array( 'content-type' => 'audio/wav', 'content-length' => 12, 'content-range' => 'bytes 0-11/100' ) );
    }
    if ( 'POST' === $args['method'] ) {
        check( in_array( basename( $path ), array( 'confirm', 'reject', 'correct' ), true ), 'No generation requests' );
        $p = json_decode( $args['body'], true );
        check( $p['expected_status'] === $record['status'], 'Preserve optimistic status' );
        if ( 'correct' === basename( $path ) ) {
            check( $p['expected_version'] === $record['review_version'] && $p['actor'] === 'wordpress:42', 'Versioned correction with trusted actor' );
            $record['review_version']++;
            $record['effective_identity'] = array( 'scientific_name' => $p['scientific_name_override'] );
        }
        $decision = $p['action'] ?? basename( $path );
        $record['status'] = 'reject' === $decision ? 'human_rejected' : 'human_confirmed';
    }
    check( strpos( $path, '/api/observations/' . $record['id'] ) === 0, 'Only known observation paths' );
    return review_response( 200, $record );
};
$_GET = array();
$html = render_bats();
check( strpos( $html, '1 te reviewen' ) !== false && strpos( $html, 'Gewone dwergvleermuis' ) !== false && strpos( $html, '15-10-2026 20:00:01' ) !== false, 'Unknown bat appears with date and count' );
check( strpos( $html, 'backyard-select-all' ) !== false && strpos( $html, 'backyard_bats_bulk_review' ) !== false && strpos( $html, 'backyard_bats_audio' ) !== false, 'Shared bulk selection and local audio' );
check( strpos( $html, 'Otje' ) === false && strpos( $html, 'backyard_generator_asset' ) !== false && strpos( $html, 'private' ) === false && strpos( $html, $token ) === false, 'Existing bat image, no Otje or secrets' );
check( backyard_bats_summary()['text'] === '1 te reviewen waarnemingen', 'Dashboard uses open bat count' );
$_GET['action'] = 'backyard_bats_audio';
$r = backyard_birds_audio_response( $review_id, 'test-nonce', 'bytes=0-11' );
check( ! is_wp_error( $r ) && $r['status'] === 206 && $r['size'] === 12, 'Bat Range audio through existing WP tempfile flow' );
wp_delete_file( $r['file'] ); check_no_temp_files();
$before = count( $requests ); $allowed = false;
check( is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ) && count( $requests ) === $before, 'Audio requires admin before API' ); $allowed = true;
check( is_wp_error( backyard_birds_audio_response( $review_id, 'bad-nonce' ) ) && count( $requests ) === $before, 'Audio requires valid nonce' );
$record['domain'] = 'bird';
check( backyard_birds_apply_review( $review_id, 'confirm', '', 'bat' ) === 'failed' && is_wp_error( backyard_birds_audio_response( $review_id, 'test-nonce' ) ), 'Bat actions and audio reject bird IDs' ); $record['domain'] = 'bat';
$expected_nonce_action = 'backyard_bats_bulk_review'; $_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'observation_ids' => array( $review_id ), 'decision' => 'confirm' );
try { backyard_birds_handle_bulk_review( 'bat' ); } catch ( ReviewRedirect $r ) { check( strpos( $r->getMessage(), 'backyard-bats' ) !== false && strpos( $r->getMessage(), 'done=1' ) !== false, 'Batch success redirects to Bats' ); }
check( backyard_birds_review_count( false, 'bat' ) === 0 && ! backyard_birds_pending_observations( false, 'bat' ), 'Confirmed bat leaves review and counter' );
$_GET = array( 'view' => 'confirmed', 'period' => 'all' );
check( strpos( render_bats(), 'backyard-edit' ) !== false, 'Confirmed bat can be edited' );
$data = bat_ajax( 'load' );
check( strpos( $data['observation']['audio'], 'backyard_bats_audio' ) !== false, 'Editor audio is bat proxy' );
check( bat_ajax( 'search', array( 'q' => 'Myotis daubentonii' ) )[0]['name'] === 'Watervleermuis', 'Search uses bat catalog' );
$p = array( 'action' => 'reject', 'expected_status' => 'human_confirmed', 'expected_version' => 0, 'request_id' => $review_id, 'identity_override' => null, 'scientific_name_override' => 'Myotis daubentonii' );
$data = bat_ajax( 'save', array( 'payload' => json_encode( $p ) ) );
check( $data['observation']['status'] === 'human_rejected' && $data['observation']['scientific_name'] === 'Myotis daubentonii' && $data['observation']['original_scientific_name'] === 'Pipistrellus pipistrellus', 'Correction preserves evidence' );
$_GET = array( 'view' => 'rejected', 'period' => 'all' ); check( strpos( render_bats(), 'backyard-edit' ) !== false, 'Rejected bat can be corrected' );
ob_start(); backyard_birds_correction_editor( 'bat' ); $editor = ob_get_clean(); check( strpos( $editor, 'Otje' ) === false, 'No Otje in bat editor' );
echo "Bats list, counts, images, bulk review, audio authorization/Range and correction checks passed.\n";
