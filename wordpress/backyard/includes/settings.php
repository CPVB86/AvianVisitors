<?php
defined( 'ABSPATH' ) || exit;

define( 'BACKYARD_DEFAULT_API_URL', 'http://192.168.1.31:8010' );

function backyard_sanitize_api_url( $value ) {
	$url   = is_string( $value ) ? esc_url_raw( trim( $value ), array( 'http', 'https' ) ) : '';
	$parts = wp_parse_url( $url );
	if ( ! $parts || empty( $parts['host'] ) || empty( $parts['scheme'] )
		|| ! in_array( $parts['scheme'], array( 'http', 'https' ), true )
		|| isset( $parts['user'] ) || isset( $parts['pass'] )
		|| isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
		add_settings_error( 'backyard_api_base_url', 'backyard_invalid_url', 'Gebruik een HTTP(S) base URL zonder inloggegevens, query of fragment.' );
		return get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL );
	}
	return untrailingslashit( $url );
}

function backyard_register_settings() {
	register_setting( 'backyard', 'backyard_api_token', array(
		'type' => 'string',
		'default' => '',
		'sanitize_callback' => 'backyard_sanitize_api_token',
		'show_in_rest' => false,
	) );
	register_setting( 'backyard', 'backyard_api_base_url', array(
		'type' => 'string',
		'default' => BACKYARD_DEFAULT_API_URL,
		'sanitize_callback' => 'backyard_sanitize_api_url',
		'show_in_rest' => false,
	) );
	add_settings_section( 'backyard_api', '', '__return_false', 'backyard' );
	// Registered last so connection settings are saved before the Pi request.
	register_setting( 'backyard', 'backyard_frame_period', array(
		'type' => 'string', 'default' => '24h', 'show_in_rest' => false,
		'sanitize_callback' => 'backyard_save_frame_period',
	) );
	add_settings_field( 'backyard_api_base_url', 'API base URL', 'backyard_api_url_field', 'backyard', 'backyard_api', array( 'label_for' => 'backyard_api_base_url' ) );
	add_settings_field( 'backyard_api_token', 'API-token', 'backyard_api_token_field', 'backyard', 'backyard_api', array( 'label_for' => 'backyard_api_token' ) );
}
add_action( 'admin_init', 'backyard_register_settings' );

function backyard_frame_periods() {
	return array( '1h' => '1 uur', '12h' => '12 uur', '24h' => '24 uur (standaard)', '7d' => '7 dagen', 'all' => 'Alle waarnemingen' );
}

function backyard_save_frame_period( $value ) {
	$previous = get_option( 'backyard_frame_period', '24h' );
	static $saved = null;
	if ( null !== $saved ) { return $saved; }
	if ( null === $value ) { $saved = $previous; return $saved; } // Disabled field when Pi could not be read.
	if ( ! current_user_can( 'manage_options' ) || ! is_string( $value ) || ! isset( backyard_frame_periods()[ $value ] ) ) {
		add_settings_error( 'backyard_frame_period', 'invalid_period', 'Kies een geldige collageperiode.' );
		$saved = $previous;
		return $saved;
	}
	// Settings API can sanitize twice when adding an option for the first time.
	$result = ( new Backyard_API_Client() )->post( '/api/avian-collage/settings', array( 'period' => $value ) );
	if ( is_wp_error( $result ) || ( $result['period'] ?? null ) !== $value ) {
		add_settings_error( 'backyard_frame_period', 'frame_save_failed', 'Opslaan van de collageperiode op de Pi is niet bevestigd. Controleer de verbinding en probeer opnieuw.' );
		$saved = $previous;
	} else {
		$saved = $value;
	}
	return $saved;
}

function backyard_frame_period_field() {
	$result = ( new Backyard_API_Client() )->get( '/api/avian-collage/settings' );
	$period = is_wp_error( $result ) ? null : ( $result['period'] ?? null );
	$valid = is_string( $period ) && isset( backyard_frame_periods()[ $period ] );
	echo '<label for="backyard_frame_period">Collageperiode</label> <select id="backyard_frame_period" name="backyard_frame_period"' . ( $valid ? '' : ' disabled' ) . '>';
	foreach ( backyard_frame_periods() as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . ( $value === ( $period ?? '24h' ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select><p class="description">' . esc_html( $valid ? 'De Pi bewaart deze keuze en gebruikt deze vanaf de volgende collage-export.' : 'Collageperiode niet beschikbaar. Sla de API-verbinding op en controleer of de Pi is bijgewerkt.' ) . '</p>';
}

function backyard_api_url_field() {
	printf( '<input type="url" class="regular-text" id="backyard_api_base_url" name="backyard_api_base_url" value="%s" required>', esc_attr( get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) ) );
}

function backyard_sanitize_api_token( $value ) {
	$previous = get_option( 'backyard_api_token', '' );
	if ( '' === $value ) {
		return $previous; // Empty password field means keep the stored token.
	}
	if ( ! is_string( $value ) || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/D', $value ) ) {
		add_settings_error( 'backyard_api_token', 'backyard_invalid_token', 'Gebruik het gegenereerde URL-veilige API-token van 43–128 tekens.' );
		return $previous;
	}
	return $value;
}

function backyard_api_token_field() {
	// Never put the saved secret back into HTML, even in a password input.
	echo '<input type="password" class="regular-text" id="backyard_api_token" name="backyard_api_token" value="" autocomplete="new-password" spellcheck="false">';
	echo '<p class="description">' . esc_html( get_option( 'backyard_api_token', '' ) ? 'Token ingesteld. Laat leeg om te behouden; vul een nieuw token in om te vervangen.' : 'Nog geen token ingesteld. Vul hetzelfde token in als op de API-server.' ) . '</p>';
}
