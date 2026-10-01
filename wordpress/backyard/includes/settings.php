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
	register_setting( 'backyard', 'backyard_api_base_url', array(
		'type' => 'string',
		'default' => BACKYARD_DEFAULT_API_URL,
		'sanitize_callback' => 'backyard_sanitize_api_url',
		'show_in_rest' => false,
	) );
	add_settings_section( 'backyard_api', 'Backyard API', '__return_false', 'backyard' );
	add_settings_field( 'backyard_api_base_url', 'API base URL', 'backyard_api_url_field', 'backyard', 'backyard_api', array( 'label_for' => 'backyard_api_base_url' ) );
}
add_action( 'admin_init', 'backyard_register_settings' );

function backyard_api_url_field() {
	printf( '<input type="url" class="regular-text" id="backyard_api_base_url" name="backyard_api_base_url" value="%s" required>', esc_attr( get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) ) );
	echo '<p class="description">Base URL zonder /api/health. De WordPress-server moet dit adres kunnen bereiken.</p>';
}
