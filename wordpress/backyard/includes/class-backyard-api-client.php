<?php
defined( 'ABSPATH' ) || exit;

class Backyard_API_Client {
	/** Read a relative API path; return decoded JSON or WP_Error. */
	public function get( $path, $query = array() ) {
		return $this->decode( $this->request( $path, $query ) );
	}

	public function post( $path, $body ) {
		return $this->decode( $this->request( $path, array(), array(
			'method' => 'POST',
			'headers' => array( 'Accept' => 'application/json', 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( $body ),
		) ) );
	}

	/** Shared authenticated transport. Only internal methods can supply HTTP options. */
	private function request( $path, $query = array(), $options = array() ) {
		if ( ! is_string( $path ) || ! preg_match( '#^/api/[a-zA-Z0-9/_-]+$#D', $path ) ) {
			return new WP_Error( 'backyard_path', 'Ongeldig API-pad.' );
		}
		$url = untrailingslashit( get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) ) . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		$token = get_option( 'backyard_api_token', '' );
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/D', $token ) ) {
			return new WP_Error( 'backyard_token', 'Stel eerst een geldig API-token in bij Backyard.' );
		}
		// The administrator deliberately selects a LAN host. Do not follow redirects.
		$args = array_replace( array(
			'method' => 'GET',
			'timeout' => 5,
			'redirection' => 0,
			'limit_response_size' => 1048576,
			'headers' => array( 'Accept' => 'application/json' ),
		), $options );
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$response = 'POST' === $args['method'] ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'backyard_connection', 'API niet bereikbaar. Controleer de URL en netwerkverbinding.' );
		}
		return $response;
	}

	private function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'backyard_auth', 'API-authenticatie geweigerd. Controleer het ingestelde token.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'backyard_http', sprintf( 'API antwoordt met HTTP %d.', $code ), array( 'status' => $code, 'body' => $data ) );
		}
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'backyard_json', 'API bereikbaar, maar het antwoord is geen geldig JSON-object of JSON-lijst.' );
		}
		return $data;
	}

	/** Download WAV to a temporary transport file. Caller must delete after streaming. */
	public function audio( $path, $range = '' ) {
		if ( ! is_string( $range ) || ( '' !== $range && ! preg_match( '/^bytes=(?:[0-9]{1,12}-[0-9]{0,12}|-[0-9]{1,12})$/D', $range ) ) ) {
			return new WP_Error( 'backyard_audio_range', 'Ongeldig audiobereik.', array( 'status' => 416 ) );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Use the system temp directory, not WordPress's possible wp-content fallback.
		$file = wp_tempnam( 'backyard-audio', sys_get_temp_dir() );
		if ( ! $file ) {
			return new WP_Error( 'backyard_audio', 'Audio tijdelijk niet beschikbaar.' );
		}
		$keep = false;
		try {
			$headers = array( 'Accept' => 'audio/wav' );
			if ( '' !== $range ) {
				$headers['Range'] = $range;
			}
			// Backend allows at most 64 MiB; one extra byte detects truncated/oversize responses.
			$response = $this->request( $path, array(), array(
				'headers' => $headers, 'timeout' => 30, 'stream' => true,
				'filename' => $file, 'limit_response_size' => 67108865,
			) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( ! in_array( $code, array( 200, 206 ), true ) ) {
				return new WP_Error( 'backyard_audio', 'Audio niet beschikbaar.', array( 'status' => in_array( $code, array( 404, 416 ), true ) ? $code : 502 ) );
			}
			clearstatcache( true, $file );
			$size = filesize( $file );
			$type_header = wp_remote_retrieve_header( $response, 'content-type' );
			$type = is_string( $type_header ) ? strtolower( trim( explode( ';', $type_header )[0] ) ) : '';
			if ( ! in_array( $type, array( 'audio/wav', 'audio/x-wav', 'audio/wave' ), true ) || false === $size || $size < 1 || $size > 67108864 ) {
				return new WP_Error( 'backyard_audio', 'Ongeldig audioantwoord.' );
			}
			$length = wp_remote_retrieve_header( $response, 'content-length' );
			if ( '' !== $length && ( ! is_scalar( $length ) || ! ctype_digit( (string) $length ) || (int) $length !== $size ) ) {
				return new WP_Error( 'backyard_audio', 'Ongeldig audioantwoord.' );
			}
			$content_range = wp_remote_retrieve_header( $response, 'content-range' );
			if ( 206 === $code ) {
				if ( '' === $range || ! is_string( $content_range ) || ! preg_match( '/^bytes ([0-9]{1,12})-([0-9]{1,12})\/([0-9]{1,12})$/D', $content_range, $parts )
					|| (int) $parts[1] > (int) $parts[2] || (int) $parts[2] >= (int) $parts[3]
					|| (int) $parts[3] > 67108864 || (int) $parts[2] - (int) $parts[1] + 1 !== $size ) {
					return new WP_Error( 'backyard_audio', 'Ongeldig audioantwoord.' );
				}
			} else {
				$prefix = file_get_contents( $file, false, null, 0, 12 );
				if ( ! is_string( $prefix ) || 12 !== strlen( $prefix ) || 'RIFF' !== substr( $prefix, 0, 4 ) || 'WAVE' !== substr( $prefix, 8, 4 ) ) {
					return new WP_Error( 'backyard_audio', 'Ongeldig audioantwoord.' );
				}
			}
			$keep = true;
			return array( 'file' => $file, 'status' => $code, 'size' => $size, 'content_range' => 206 === $code ? $content_range : '' );
		} finally {
			if ( ! $keep ) {
				wp_delete_file( $file );
			}
		}
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
