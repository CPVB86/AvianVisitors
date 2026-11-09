<?php
defined( 'ABSPATH' ) || exit;

function backyard_status_components() {
	return array( 'backyard' => 'Backyard', 'backyard_remote' => 'Backyard Remote', 'bird_mic' => 'BirdMic', 'bat_mic' => 'VleerMic' );
}

function backyard_status_reasons() {
	return array(
		'not_configured' => 'Remote-healthadres ontbreekt in de Backyard-configuratie.',
		'invalid_configuration' => 'Controleer het remote-healthadres in de Backyard-configuratie.',
		'status_endpoint_unavailable' => 'Werk de BYRemote-healthservice bij; het statusendpoint is niet beschikbaar.',
		'invalid_status_response' => 'De remote-healthservice geeft een ongeldig statusantwoord.',
		'insufficient_status' => 'Onvoldoende informatie over de remote-services; controleer hun configuratie.',
		'no_active_receiver' => 'Geen actieve audio-ontvanger; dit betekent niet dat de microfoon defect is.',
		'starting' => 'Audio-overdracht wordt gestart.',
		'remote_unreachable' => 'Backyard kan de remote-audioservice niet bereiken.',
		'database_unavailable' => 'De Backyard-database is niet beschikbaar.',
		'capture_failed' => 'De opnameservice meldt een opnamefout.',
		'audio_stalled' => 'De actieve audio-overdracht is stilgevallen.',
	);
}

/** Strict UTC timestamps, never interpret null as epoch/offline. */
function backyard_status_timestamp( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|\+00:00)$/D', $value ) ) { return false; }
	try {
		$date = new DateTimeImmutable( $value );
		$errors = DateTimeImmutable::getLastErrors();
		return $errors && ( $errors['warning_count'] || $errors['error_count'] ) ? false : $date->getTimestamp();
	} catch ( Exception $error ) { return false; }
}

function backyard_status_failure( $issue ) {
	$rows = array();
	foreach ( backyard_status_components() as $key => $label ) {
		$offline = 'transport' === $issue && 'backyard' === $key;
		$rows[ $key ] = array( 'status' => $offline ? 'offline' : 'unknown', 'online' => $offline ? false : null, 'last_audio_at' => null );
	}
	return array( 'checked_at' => null, 'components' => $rows, 'issue' => $issue );
}

function backyard_status_validate( $data, $now ) {
	if ( ! is_array( $data ) || 1 !== ( $data['schema_version'] ?? null ) || ! is_array( $data['components'] ?? null ) ) { return backyard_status_failure( 'contract' ); }
	$stamp = backyard_status_timestamp( $data['checked_at'] ?? null );
	if ( false === $stamp || $stamp > $now + 5 ) { return backyard_status_failure( 'contract' ); }
	$rows = array();
	$expected = array( 'online' => true, 'offline' => false, 'unknown' => null );
	foreach ( backyard_status_components() as $key => $label ) {
		$row = $data['components'][ $key ] ?? null;
		if ( ! is_array( $row ) || ! is_string( $row['status'] ?? null ) || ! array_key_exists( $row['status'], $expected )
			|| ! array_key_exists( 'online', $row ) || $row['online'] !== $expected[ $row['status'] ]
			|| ! is_string( $row['reason'] ?? null ) || ! array_key_exists( 'last_audio_at', $row )
			|| ( null !== $row['last_audio_at'] && false === backyard_status_timestamp( $row['last_audio_at'] ) ) ) { return backyard_status_failure( 'contract' ); }
		// Never retain arbitrary remote text/URLs for public rendering.
		$rows[ $key ] = array( 'status' => $row['status'], 'online' => $row['online'], 'last_audio_at' => $row['last_audio_at'],
			'admin_info' => backyard_status_reasons()[ $row['reason'] ] ?? '' );
	}
	return array( 'checked_at' => $data['checked_at'], 'components' => $rows, 'issue' => '' );
}

/** Shared transient, including failures, namespaced by connection configuration. */
function backyard_system_status() {
	$now = time();
	$url = get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL );
	$token = get_option( 'backyard_api_token', '' );
	$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
	if ( ! $parts || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || empty( $parts['host'] )
		|| isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) { return backyard_status_failure( 'configuration' ); }
	$key = 'backyard_status_v1_' . hash( 'sha256', $url . '|' . ( is_string( $token ) ? $token : '' ) );
	$cache = get_transient( $key );
	if ( ! is_array( $cache ) || ! is_int( $cache['fetched_at'] ?? null ) || $cache['fetched_at'] > $now
		|| $now - $cache['fetched_at'] >= 30 || ! is_array( $cache['result'] ?? null ) ) {
		$response = ( new Backyard_API_Client() )->system_status();
		if ( is_wp_error( $response ) ) {
			$issues = array( 'backyard_connection' => 'transport', 'backyard_token' => 'configuration', 'backyard_auth' => 'authentication', 'backyard_json' => 'contract' );
			$result = backyard_status_failure( $issues[ $response->get_error_code() ] ?? 'endpoint' );
		} else {
			$result = backyard_status_validate( $response, time() );
		}
		$cache = array( 'fetched_at' => time(), 'result' => $result );
		set_transient( $key, $cache, 30 );
	}
	$result = $cache['result'];
	$stamp = backyard_status_timestamp( $result['checked_at'] ?? null );
	if ( false !== $stamp && time() - $stamp >= 60 ) {
		$stale = backyard_status_failure( 'stale' );
		$stale['checked_at'] = $result['checked_at'];
		return $stale;
	}
	return $result;
}

function backyard_status_admin_message( $issue ) {
	$messages = array(
		'transport' => 'Backyard is niet bereikbaar. Controleer de API-URL en de netwerkverbinding van de WordPress-server.',
		'configuration' => 'Controleer de API base URL en het API-token onder Backyard → Instellingen.',
		'authentication' => 'De API weigert de authenticatie. Controleer het opgeslagen token onder Backyard → Instellingen.',
		'contract' => 'Het API-antwoord voldoet niet aan statuscontract versie 1. Controleer de Backyard-versie en de serverklokken.',
		'endpoint' => 'Het statusendpoint is niet beschikbaar. Controleer de Backyard-update en API-configuratie.',
		'stale' => 'De laatste statuscontrole is ouder dan 60 seconden. Vernieuw de pagina; controleer bij herhaling de API en serverklokken.',
	);
	return $messages[ $issue ] ?? '';
}

function backyard_status_html( $component, $label = '', $show_checked = false, $snapshot = null, $admin = false ) {
	$names = backyard_status_components();
	if ( ! is_string( $component ) || ! isset( $names[ $component ] ) ) { return ''; }
	$data = null === $snapshot ? backyard_system_status() : $snapshot;
	$status = $data['components'][ $component ]['status'];
	$labels = array( 'online' => 'Online', 'offline' => 'Offline', 'unknown' => 'Onbekend' );
	$stamp = backyard_status_timestamp( $data['checked_at'] );
	// Start green candidates grey until the local age guard verifies freshness.
	// This also fails closed with disabled JS or stale full-page HTML caches.
	$initial = 'online' === $status ? 'unknown' : $status;
	$html = '<div class="backyard-system-status is-' . $initial . '" data-status="' . esc_attr( $status ) . '" data-expires="' . ( false === $stamp ? 0 : $stamp + 60 ) . '">';
	$html .= '<div class="backyard-status-line"><span class="backyard-status-dot" aria-hidden="true"></span><span class="backyard-status-name">' . esc_html( is_string( $label ) && '' !== trim( $label ) ? $label : $names[ $component ] ) . '</span><span class="backyard-status-value" role="status" aria-live="polite">' . $labels[ $initial ] . '</span></div>';
	if ( $show_checked ) {
		$html .= '<small class="backyard-status-checked">Laatst gecontroleerd: ' . ( false === $stamp ? 'niet beschikbaar' : '<time datetime="' . esc_attr( $data['checked_at'] ) . '">' . esc_html( wp_date( 'd-m-Y H:i:s', $stamp ) ) . '</time>' ) . '</small>';
	}
	if ( $admin && current_user_can( 'manage_options' ) && ! empty( $data['components'][ $component ]['admin_info'] ) ) { $html .= '<p class="backyard-status-info">' . esc_html( $data['components'][ $component ]['admin_info'] ) . '</p>'; }
	return $html . '</div>';
}

function backyard_status_register_assets() {
	$base = plugins_url( 'assets/', dirname( __DIR__ ) . '/backyard.php' );
	wp_register_style( 'backyard-system-status', $base . 'system-status.css', array(), '1.0.0' );
	wp_register_script( 'backyard-system-status', $base . 'system-status.js', array(), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'backyard_status_register_assets' );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'index.php' !== $hook ) { return; }
	backyard_status_register_assets();
	wp_enqueue_style( 'backyard-system-status' );
	wp_enqueue_script( 'backyard-system-status' );
} );
add_action( 'elementor/widgets/register', function ( $manager ) {
	require_once __DIR__ . '/class-backyard-status-widget.php';
	$manager->register( new Backyard_Status_Widget() );
} );
add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'manage_options' ) ) { wp_add_dashboard_widget( 'backyard_system_status', 'Backyard · Systeemstatus', 'backyard_status_dashboard' ); }
} );

function backyard_status_dashboard() {
	backyard_require_admin();
	$data = backyard_system_status();
	echo '<div class="backyard-system-overview">';
	foreach ( backyard_status_components() as $component => $label ) { echo backyard_status_html( $component, '', true, $data, true ); }
	echo '</div><p class="description">Backyard: API/database. Backyard Remote: bereikbaarheid van de audioservices. Microfoon online: recente actieve audio-overdracht, geen bevestiging van BirdNET-verwerking.</p>';
	if ( ! empty( $data['issue'] ) ) { echo '<p role="status">' . esc_html( backyard_status_admin_message( $data['issue'] ) ) . '</p>'; }
}
