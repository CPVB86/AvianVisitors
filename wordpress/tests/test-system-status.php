<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		protected function start_controls_section( $id, $args ) {}
		protected function end_controls_section() {}
		protected function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		protected function add_group_control( $type, $args ) {}
		protected function add_responsive_control( $id, $args ) { $this->controls[ $id ] = $args; }
	}
	class Controls_Manager { const SELECT='select', TEXT='text', SWITCHER='switch', RAW_HTML='html', TAB_STYLE='style', COLOR='color', SLIDER='slider'; }
	class Group_Control_Typography { static function get_type() { return 'typography'; } }
}
namespace {
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
require __DIR__ . '/../backyard/includes/class-backyard-status-widget.php';
class StatusTestWidget extends Backyard_Status_Widget {
	public function output() { ob_start(); $this->render(); return ob_get_clean(); }
	public function configure() { $this->register_controls(); }
	public function dynamic() { return $this->is_dynamic_content(); }
}
function fresh_status() {
	$rows = array();
	foreach ( backyard_status_components() as $key => $label ) { $rows[ $key ] = array( 'status' => 'online', 'online' => true, 'reason' => 'safe', 'last_audio_at' => null ); }
	$rows['bird_mic'] = array( 'status' => 'offline', 'online' => false, 'reason' => 'audio_stalled', 'last_audio_at' => gmdate( 'c', time()-20 ) );
	$rows['bat_mic'] = array( 'status' => 'unknown', 'online' => null, 'reason' => 'no_active_receiver', 'last_audio_at' => null );
	return array( 'schema_version' => 1, 'checked_at' => gmdate( 'c' ), 'components' => $rows );
}
$body = fresh_status(); $code = 200; $transport = false;
$http_handler = function ( $url, $args ) use ( &$body, &$code, &$transport ) {
	check( $url === $GLOBALS['option'] . '/api/system/status' && $args['method'] === 'GET', 'Existing server-side endpoint only' );
	check( $args['timeout'] >= 6 && $args['redirection'] === 0 && $args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['token'], 'Timeout, Bearer and no redirects' );
	return $transport ? new WP_Error( 'http_request_failed', 'SECRET http://private 192.168.1.105' ) : review_response( $code, $body );
};
$manager = new class { public $widgets = array(); function register( $widget ) { $this->widgets[] = $widget; } };
foreach ( $actions['elementor/widgets/register'] as $callback ) { $callback( $manager ); }
check( count( $manager->widgets ) === 1 && $manager->widgets[0]->get_name() === 'backyard-status', 'One registered Elementor widget type' );
$widget = new StatusTestWidget(); $widget->configure();
check( $widget->controls['component']['options'] === backyard_status_components() && $widget->dynamic(), 'Four component choices, Elementor output caching disabled' );
$before = count( $requests );
foreach ( backyard_status_components() as $key => $name ) {
	$instance = new StatusTestWidget(); $instance->settings = array( 'component' => $key, 'label' => '<b>Eigen label</b>', 'show_checked' => 'yes' );
	$html = $instance->output();
	check( substr_count( $html, 'class="backyard-status-dot"' ) === 1 && strpos( $html, '&lt;b&gt;Eigen label&lt;/b&gt;' ) !== false && strpos( $html, 'Laatst gecontroleerd' ) !== false, 'Each instance renders one component with escaped label and time' );
	check( strpos( $html, 'data-status="' . $body['components'][$key]['status'] . '"' ) !== false, 'Independent three-state output' );
	check( strpos( $html, $token ) === false && strpos( $html, '192.168.' ) === false && strpos( $html, 'no_active_receiver' ) === false, 'No secrets, remote addresses or raw reasons in HTML' );
}
ob_start(); backyard_status_dashboard(); $html = ob_get_clean();
check( substr_count( $html, 'class="backyard-status-dot"' ) === 4 && strpos( $html, 'Geen actieve audio-ontvanger' ) !== false, 'Home card shows all four with safe admin guidance' );
check( count( $requests ) === $before + 1 && array_values( $transient_ttls )[0] === 30, 'Four widgets and Home share one request and 30-second transient' );
check( backyard_system_status()['components']['bat_mic']['online'] === null, 'Null stays unknown, not false' );
$key = array_key_first( $transients );
$transients[$key]['result']['checked_at'] = gmdate( 'c', time()-61 );
$old = backyard_system_status();
check( $old['issue'] === 'stale' && ! in_array( 'online', array_column( $old['components'], 'status' ), true ), 'Stale positive timestamp never stays online even in fresh transient' );
$transients[$key]['fetched_at'] = time()-31; $before = count( $requests );
backyard_system_status(); check( count( $requests ) === $before+1, 'Old transient triggers fresh request' );
$body['checked_at'] = gmdate( 'c', time()-61 ); $transients = array();
check( backyard_system_status()['issue'] === 'stale', 'Stale server response also becomes unknown' );
$body = fresh_status();
$before = count( $requests ); $token = str_repeat( 'b', 43 );
backyard_system_status();
check( count( $requests ) === $before + 1, 'Changing credentials invalidates the shared status cache' );
foreach ( array( 401, 403, 404, 500 ) as $status ) {
	$code = $status; $transients = array(); $body = array( 'detail' => 'SECRET token in remote error' );
	$data = backyard_system_status();
	check( array_unique( array_column( $data['components'], 'status' ) ) === array( 'unknown' ), 'HTTP/auth failures are unknown' );
	ob_start(); backyard_status_dashboard(); $html = ob_get_clean();
	check( strpos( $html, 'SECRET' ) === false && strpos( $html, 'Controleer' ) !== false, 'Safe actionable admin error without response body' );
}
$code=200; $transport=true; $transients=array(); $data=backyard_system_status();
check( $data['components']['backyard']['status'] === 'offline' && $data['components']['backyard_remote']['online'] === null, 'Transport failure means only Backyard offline' );
$transport=false; $body=fresh_status();
$invalid=$body; $invalid['schema_version']=2;
check( backyard_status_validate($invalid,time())['issue'] === 'contract', 'Unsupported schema rejected' );
$body='not a JSON object'; $transients=array();
check( backyard_system_status()['issue']==='contract', 'Invalid JSON structure is unknown, not offline' );
$body=fresh_status();
$invalid = $body; $invalid['components']['bat_mic']['online'] = false;
check( backyard_status_validate( $invalid, time() )['issue'] === 'contract', 'Inconsistent null/status contract rejected' );
foreach ( array( null, 'yesterday', '2026-02-31T12:00:00Z', gmdate( 'c', time()+300 ) ) as $stamp ) {
	$invalid=$body; $invalid['checked_at']=$stamp;
	check( backyard_status_validate( $invalid, time() )['issue'] === 'contract', 'Invalid timestamps rejected' );
}
$invalid=$body; unset($invalid['components']['bat_mic']);
check( backyard_status_validate($invalid,time())['issue'] === 'contract', 'Missing component rejected' );
$before=count($requests); $token=''; $transients=array();
check( backyard_system_status()['issue']==='configuration' && count($requests)===$before, 'Missing token fails before HTTP' );
$option='invalid-url';
check( backyard_system_status()['issue']==='configuration', 'Invalid base URL is configuration error' );
$allowed=false;
try { backyard_status_dashboard(); throw new \LogicException('Expected capability check'); } catch ( \RuntimeException $e ) { check($e->getMessage()==='denied','Home requires manage_options'); }
echo "System status: schema, partial outage, freshness, auth/config, four Elementor instances and Home card passed.\n";
}
