<?php
defined( 'ABSPATH' ) || exit;

/** Read-only Generator adapter. Never calls the generation endpoint. */
class Backyard_Generator {
	private $lookups = array();

	private static function context() {
		return hash( 'sha256', get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) . '|' . get_option( 'backyard_api_token', '' ) );
	}

	public function thumbnail( $domain, $scientific_name, $preferences ) {
		if ( ! in_array( $domain, array( 'bird', 'bat' ), true ) || ! is_string( $scientific_name ) || '' === trim( $scientific_name ) || strlen( $scientific_name ) > 255 ) {
			return '';
		}
		$scientific_name = trim( $scientific_name );
		$key = hash( 'sha256', $scientific_name );
		$cache_key = 'backyard_gen_' . hash( 'sha256', self::context() . $domain . $key );
		if ( ! array_key_exists( $cache_key, $this->lookups ) ) {
			$data = get_transient( $cache_key );
			if ( false === $data ) {
				$result = ( new Backyard_API_Client() )->get( '/api/generator/' . $domain . '/species', array( 'scientific_name' => $scientific_name ) );
				$data = array();
				if ( ! is_wp_error( $result ) && ( $result['domain'] ?? null ) === $domain && ( $result['species_key'] ?? null ) === $key && is_array( $result['assets'] ?? null ) ) {
					foreach ( $result['assets'] as $id => $asset ) {
						if ( is_string( $id ) && preg_match( '/^[a-z0-9_-]{1,64}$/D', $id ) && is_array( $asset ) && in_array( $asset['content_type'] ?? '', array( 'image/png', 'image/webp', 'image/jpeg' ), true ) ) {
							$data[] = $id;
						}
					}
				}
				// Cache misses too; connection changes invalidate the namespace.
				set_transient( $cache_key, $data, $data ? 300 : 60 );
			}
			$this->lookups[ $cache_key ] = $data;
		}
		foreach ( $preferences as $id ) {
			if ( in_array( $id, $this->lookups[ $cache_key ], true ) ) {
				return add_query_arg( array( 'action' => 'backyard_generator_asset', 'domain' => $domain, 'key' => $key, 'asset' => $id, 'signature' => self::signature( $domain, $key, $id ) ), admin_url( 'admin-post.php' ) );
			}
		}
		return '';
	}

	private static function signature( $domain, $key, $asset ) {
		return hash_hmac( 'sha256', self::context() . '|' . $domain . '|' . $key . '|' . $asset, wp_salt( 'auth' ) );
	}

	/** Public images require a server-issued signature, not administrator cookies. */
	public static function image( $domain, $key, $asset, $signature ) {
		$error = new WP_Error( 'backyard_image', 'Afbeelding niet beschikbaar.' );
		if ( ! is_string( $domain ) || ! in_array( $domain, array( 'bird', 'bat' ), true )
			|| ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key )
			|| ! is_string( $asset ) || ! preg_match( '/^[a-z0-9_-]{1,64}$/D', $asset )
			|| ! is_string( $signature ) || ! hash_equals( self::signature( $domain, $key, $asset ), $signature ) ) {
			return $error;
		}
		$token = get_option( 'backyard_api_token', '' );
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/D', $token ) ) {
			return $error;
		}
		// Separate bounded image transport leaves the production audio client untouched.
		$response = wp_remote_get( untrailingslashit( get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) ) . '/api/generator/' . $domain . '/assets/' . $key . '/' . $asset, array(
			'timeout' => 10, 'redirection' => 0, 'limit_response_size' => 8388609,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'image/png,image/webp,image/jpeg' ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $error;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || strlen( $body ) > 8388608 || '' === $body ) {
			return $error;
		}
		$info = @getimagesizefromstring( $body );
		$type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! $info || ! is_string( $type ) || ! in_array( $info['mime'], array( 'image/png', 'image/webp', 'image/jpeg' ), true ) || strtolower( trim( explode( ';', $type )[0] ) ) !== $info['mime'] ) {
			return $error;
		}
		return array( 'body' => $body, 'type' => $info['mime'] );
	}
}

function backyard_generator_serve_asset() {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		status_header( 405 );
		exit;
	}
	$result = Backyard_Generator::image( $_GET['domain'] ?? null, $_GET['key'] ?? null, $_GET['asset'] ?? null, $_GET['signature'] ?? null );
	if ( is_wp_error( $result ) ) {
		nocache_headers();
		status_header( 404 );
		exit;
	}
	header( 'Content-Type: ' . $result['type'] );
	header( 'Content-Length: ' . strlen( $result['body'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: public, max-age=300' );
	echo $result['body']; // Validated raster bytes; never API JSON or upstream headers.
	exit;
}
add_action( 'admin_post_backyard_generator_asset', 'backyard_generator_serve_asset' );
add_action( 'admin_post_nopriv_backyard_generator_asset', 'backyard_generator_serve_asset' );
