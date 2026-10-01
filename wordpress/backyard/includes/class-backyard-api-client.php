<?php
defined( 'ABSPATH' ) || exit;

class Backyard_API_Client {
	/** Read a relative API path; return decoded JSON or WP_Error. */
	public function get( $path, $query = array() ) {
		if ( ! is_string( $path ) || ! preg_match( '#^/api/[a-zA-Z0-9/_-]+$#D', $path ) ) {
			return new WP_Error( 'backyard_path', 'Ongeldig API-pad.' );
		}
		$url = untrailingslashit( get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) ) . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		// The administrator deliberately selects a LAN host. Do not follow redirects.
		$response = wp_remote_get( $url, array(
			'timeout' => 5,
			'redirection' => 0,
			'limit_response_size' => 1048576,
			'headers' => array( 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'backyard_connection', 'API niet bereikbaar: ' . $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'backyard_http', sprintf( 'API antwoordt met HTTP %d.', $code ), array( 'status' => $code, 'body' => $data ) );
		}
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'backyard_json', 'API bereikbaar, maar het antwoord is geen geldig JSON-object of JSON-lijst.' );
		}
		return $data;
	}

	public function health() {
		$data = $this->get( '/api/health' );
		if ( is_wp_error( $data ) ) {
			$details = $data->get_error_data();
			if ( 'backyard_http' === $data->get_error_code() && 503 === $details['status']
				&& isset( $details['body']['database'] ) && 'unavailable' === $details['body']['database'] ) {
				return new WP_Error( 'backyard_database', 'API bereikbaar; database niet beschikbaar (HTTP 503).' );
			}
			return $data;
		}
		if ( ! isset( $data['status'], $data['service'], $data['database'] )
			|| 'ok' !== $data['status'] || 'backyard' !== $data['service'] || 'ok' !== $data['database'] ) {
			return new WP_Error( 'backyard_health', 'API bereikbaar, maar Backyard-status en database ok zijn niet bevestigd.' );
		}
		return $data;
	}
}
