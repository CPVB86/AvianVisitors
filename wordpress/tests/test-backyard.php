<?php
/** Dependency-free contract tests with WordPress test doubles; not a WP integration test. */
define( 'ABSPATH', __DIR__ );
$option = 'http://192.168.1.31:8010';
$response = null;
$requests = array();
$allowed = true;
$nonce_ok = true;
$filters = array();
class WP_Error {
	private $code, $message, $data;
	function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	function get_error_code() { return $this->code; }
	function get_error_message() { return $this->message; }
	function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $name, $default = false ) { return $GLOBALS['option']; }
function add_option( $name, $value ) { $GLOBALS['added'] = array( $name, $value ); }
function add_action( ...$args ) {}
function register_activation_hook( ...$args ) {}
function register_deactivation_hook( ...$args ) {}
function is_admin() { return true; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function esc_url_raw( $value, $protocols ) { return preg_match( '#^https?://#', $value ) ? $value : ''; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function add_settings_error( ...$args ) { $GLOBALS['setting_error'] = true; }
function add_query_arg( $query, $url ) { return $url . '?' . http_build_query( $query ); }
function wp_remote_get( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $value ) { return $value['response']['code']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function current_user_can( $cap ) { return $GLOBALS['allowed'] && 'manage_options' === $cap; }
function wp_die( $message ) { throw new RuntimeException( 'denied' ); }
function check_admin_referer( $action ) { if ( ! $GLOBALS['nonce_ok'] || 'backyard_test_connection' !== $action ) { throw new RuntimeException( 'nonce' ); } }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function admin_url( $value ) { return '/wp-admin/' . $value; }
function settings_errors() {}
function settings_fields( $group ) {}
function do_settings_sections( $page ) {}
function submit_button( ...$args ) {}
function wp_nonce_field( $action ) {}
function apply_filters( $name, $value ) { return $GLOBALS['filters'][ $name ] ?? $value; }
require dirname( __DIR__ ) . '/backyard/backyard.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function reply( $code, $body ) { $GLOBALS['response'] = array( 'response' => array( 'code' => $code ), 'body' => $body ); }
function page() { ob_start(); try { backyard_settings_page(); return ob_get_contents(); } finally { ob_end_clean(); } }

check( count( backyard_modules() ) === 4 && count( $requests ) === 0, 'Modules must not fetch data on load' );
backyard_activate();
check( $GLOBALS['added'] === array( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ), 'Activation default' );
backyard_deactivate();
check( $option === BACKYARD_DEFAULT_API_URL, 'Deactivation preserves settings' );
check( backyard_sanitize_api_url( ' https://example.org/backyard/ ' ) === 'https://example.org/backyard', 'Normalize base path' );
foreach ( array( '', 'file:///tmp/data', 'https://user:secret@example.org', 'https://example.org?q=1', 'https://example.org#part', array() ) as $invalid ) {
	$GLOBALS['setting_error'] = false;
	check( backyard_sanitize_api_url( $invalid ) === $option && $GLOBALS['setting_error'], 'Reject invalid URL and preserve setting' );
}
$client = new Backyard_API_Client();
reply( 200, '{"status":"ok","service":"backyard","database":"ok"}' );
check( ! is_wp_error( $client->health() ), 'Healthy API' );
check( $requests[0][0] === $option . '/api/health' && $requests[0][1]['redirection'] === 0 && $requests[0][1]['timeout'] === 5, 'Bounded request without redirects' );
reply( 503, '{"status":"error","database":"unavailable"}' );
check( $client->health()->get_error_code() === 'backyard_database', 'Database unavailable' );
foreach ( array( 301, 401, 404, 500 ) as $code ) {
	reply( $code, '{}' );
	check( $client->health()->get_error_code() === 'backyard_http', 'HTTP error' );
}
foreach ( array( '<html>error</html>', 'null', 'true' ) as $body ) {
	reply( 200, $body );
	check( $client->health()->get_error_code() === 'backyard_json', 'Invalid JSON body' );
}
reply( 200, '{}' );
check( $client->health()->get_error_code() === 'backyard_health', 'Unknown health is not success' );
$response = new WP_Error( 'timeout', '<script>timeout</script>' );
check( $client->health()->get_error_code() === 'backyard_connection', 'Transport error' );
$before = count( $requests );
check( is_wp_error( $client->get( '//external.test' ) ) && count( $requests ) === $before, 'Absolute target rejected' );
reply( 200, '[]' );
check( backyard_birds_detections( 999 ) === array(), 'Empty detections valid' );
check( substr( end( $requests )[0], -9 ) === 'limit=100', 'Limit bounded' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$before = count( $requests );
page();
check( count( $requests ) === $before, 'No health request on page load' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['backyard_test_connection'] = '1';
$allowed = false;
try { page(); throw new RuntimeException( 'Missing capability check' ); } catch ( RuntimeException $e ) { check( $e->getMessage() === 'denied', 'Capability enforced' ); }
$allowed = true;
$nonce_ok = false;
try { page(); throw new RuntimeException( 'Missing nonce check' ); } catch ( RuntimeException $e ) { check( $e->getMessage() === 'nonce', 'Nonce enforced' ); }
check( count( $requests ) === $before, 'Unauthorized requests never fetch' );
$nonce_ok = true;
$response = new WP_Error( 'timeout', '<script>timeout</script>' );
check( strpos( page(), '&lt;script&gt;' ) !== false, 'Escape API error output' );
reply( 200, '{"status":"ok","service":"backyard","database":"ok"}' );
check( strpos( page(), 'database ok' ) !== false, 'Render healthy result' );
ob_start(); backyard_manual_page(); $manual = ob_get_clean();
check( strpos( $manual, 'nog geen publieke shortcodes' ) !== false, 'Empty registry message' );
$filters['backyard_shortcode_docs'] = array( array( 'module' => 'Birds', 'shortcode' => '[example]', 'parameters' => array( 'limit' => '<count>' ), 'description' => 'Example' ) );
ob_start(); backyard_manual_page(); $manual = ob_get_clean();
check( strpos( $manual, '[example]' ) !== false && strpos( $manual, '&lt;count&gt;' ) !== false, 'Registry rendering' );
echo "Backyard contract tests passed (WordPress doubles).\n";
