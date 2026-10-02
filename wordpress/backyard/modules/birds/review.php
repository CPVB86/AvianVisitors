<?php
defined( 'ABSPATH' ) || exit;

function backyard_birds_review_count() {
	$result = ( new Backyard_API_Client() )->get( '/api/observations/count', array( 'domain' => 'bird', 'status' => 'pending_review' ) );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( ! isset( $result['count'] ) || ! is_int( $result['count'] ) || $result['count'] < 0 ) {
		return new WP_Error( 'backyard_review', 'Reviewteller tijdelijk niet beschikbaar.' );
	}
	return $result['count'];
}

function backyard_birds_summary() {
	$count = backyard_birds_review_count();
	return is_wp_error( $count ) ? $count : array(
		'text' => sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ),
		'url' => admin_url( 'admin.php?page=backyard-birds' ),
	);
}

function backyard_birds_observation_id( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $value );
}

function backyard_birds_pending_observations() {
	$rows = ( new Backyard_API_Client() )->get( '/api/observations', array( 'domain' => 'bird', 'status' => 'pending_review', 'limit' => 50 ) );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}
	if ( array_values( $rows ) !== $rows ) {
		return new WP_Error( 'backyard_review', 'Waarnemingen tijdelijk niet beschikbaar.' );
	}
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! backyard_birds_observation_id( $row['id'] ?? null )
			|| 'bird' !== ( $row['domain'] ?? null ) || 'pending_review' !== ( $row['status'] ?? null )
			|| ! isset( $row['timestamp'], $row['scientific_name'], $row['confidence'], $row['supports'], $row['audio_available'] )
			|| ! is_string( $row['timestamp'] ) || false === strtotime( $row['timestamp'] )
			|| ! is_string( $row['scientific_name'] ) || ! is_numeric( $row['confidence'] )
			|| $row['confidence'] < 0 || $row['confidence'] > 1
			|| ! is_int( $row['supports'] ) || $row['supports'] < 0 || ! is_bool( $row['audio_available'] ) ) {
			return new WP_Error( 'backyard_review', 'Waarnemingen tijdelijk niet beschikbaar.' );
		}
	}
	return $rows;
}

function backyard_birds_review_notice( $key ) {
	$messages = array(
		'confirmed' => array( 'success', 'Waarneming bevestigd.' ),
		'rejected' => array( 'success', 'Waarneming afgewezen.' ),
		'conflict' => array( 'warning', 'Deze waarneming is inmiddels gewijzigd. De lijst is opnieuw geladen.' ),
		'audio_missing' => array( 'error', 'Bevestigen is niet mogelijk zonder beschikbare audio. Afwijzen blijft mogelijk.' ),
		'failed' => array( 'error', 'Actie niet bevestigd. Controleer de actuele lijst voordat je opnieuw probeert.' ),
	);
	if ( is_string( $key ) && isset( $messages[ $key ] ) ) {
		$message = $messages[ $key ];
		echo '<div class="notice notice-' . esc_attr( $message[0] ) . '"><p>' . esc_html( $message[1] ) . '</p></div>';
	}
}

function backyard_birds_admin_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Birds</h1>';
	backyard_birds_review_notice( $_GET['review_result'] ?? '' );
	echo '<section class="backyard-card"><h2>Review</h2>';
	$count = backyard_birds_review_count();
	echo '<p>' . esc_html( is_wp_error( $count ) ? 'Reviewteller tijdelijk niet beschikbaar.' : sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ) ) . '</p>';
	$rows = backyard_birds_pending_observations();
	if ( is_wp_error( $rows ) ) {
		echo '<p>Waarnemingen tijdelijk niet beschikbaar. Probeer het later opnieuw.</p></section></div>';
		return;
	}
	if ( ! $rows ) {
		echo '<p>Er zijn geen vogelwaarnemingen om te reviewen.</p></section></div>';
		return;
	}
	echo '<p>Maximaal 50 meest recente waarnemingen. Na een actie worden lijst en teller opnieuw opgehaald.</p>';
	echo '<div class="backyard-review-scroll"><table class="widefat striped backyard-review-table"><thead><tr><th scope="col">Datum/tijd</th><th scope="col">Soort</th><th scope="col">Latijnse naam</th><th scope="col">Confidence</th><th scope="col">Supports</th><th scope="col">Audio</th><th scope="col">Actie</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$name = $row['common_name_nl'] ?? '';
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			$name = isset( $row['common_name'] ) && is_string( $row['common_name'] ) ? $row['common_name'] : '—';
		}
		$time = strtotime( $row['timestamp'] );
		echo '<tr><td><time datetime="' . esc_attr( gmdate( 'c', $time ) ) . '">' . esc_html( wp_date( 'd-m-Y H:i:s', $time ) ) . '</time></td>';
		echo '<td>' . esc_html( $name ) . '</td><td>' . esc_html( $row['scientific_name'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['confidence'] * 100, 1 ) . '%' ) . '</td><td>' . esc_html( $row['supports'] ) . '</td><td>';
		if ( $row['audio_available'] ) {
			// Never use audio_url supplied by the API: only an authenticated WP route.
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'backyard_birds_audio', 'observation_id' => $row['id'] ), admin_url( 'admin-post.php' ) ), 'backyard_birds_audio_' . $row['id'] );
			echo '<audio controls preload="none" src="' . esc_url( $url ) . '" aria-label="' . esc_attr( 'Audio van ' . $name ) . '">Je browser ondersteunt geen audio.</audio>';
		} else {
			echo 'Geen audio beschikbaar';
		}
		echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="backyard_birds_review"><input type="hidden" name="observation_id" value="' . esc_attr( $row['id'] ) . '">';
		wp_nonce_field( 'backyard_birds_review_' . $row['id'] );
		echo '<button type="submit" class="button button-primary" name="decision" value="confirm">Bevestigen</button> <button type="submit" class="button" name="decision" value="reject">Afwijzen</button></form></td></tr>';
	}
	echo '</tbody></table></div></section></div>';
}

function backyard_birds_handle_review() {
	backyard_require_admin();
	$id = $_POST['observation_id'] ?? null;
	$decision = $_POST['decision'] ?? null;
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! backyard_birds_observation_id( $id ) || ! in_array( $decision, array( 'confirm', 'reject' ), true ) ) {
		wp_die( 'Ongeldige reviewaanvraag.', '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'backyard_birds_review_' . $id );
	$client = new Backyard_API_Client();
	$record = $client->get( '/api/observations/' . $id );
	$result = 'failed';
	if ( ! is_wp_error( $record ) && ( $record['id'] ?? null ) === $id && 'bird' === ( $record['domain'] ?? null ) ) {
		if ( 'pending_review' !== ( $record['status'] ?? null ) ) {
			$result = 'conflict';
		} elseif ( 'confirm' === $decision && true !== ( $record['audio_available'] ?? false ) ) {
			$result = 'audio_missing';
		} else {
			$response = $client->post( '/api/observations/' . $id . '/' . $decision, array( 'expected_status' => 'pending_review' ) );
			if ( is_wp_error( $response ) ) {
				$details = $response->get_error_data();
				$result = is_array( $details ) && 409 === ( $details['status'] ?? null ) ? 'conflict' : 'failed';
			} elseif ( ( $response['id'] ?? null ) === $id && 'bird' === ( $response['domain'] ?? null )
				&& ( 'confirm' === $decision ? 'human_confirmed' : 'human_rejected' ) === ( $response['status'] ?? null ) ) {
				$result = 'confirm' === $decision ? 'confirmed' : 'rejected';
			}
		}
	}
	// Post/redirect/get prevents a browser refresh from repeating the mutation.
	wp_safe_redirect( add_query_arg( 'review_result', $result, admin_url( 'admin.php?page=backyard-birds' ) ) );
	exit;
}

/** Gate both detail and binary requests before contacting the private API. */
function backyard_birds_audio_response( $id, $nonce, $range = '' ) {
	if ( ! current_user_can( 'manage_options' ) || ! backyard_birds_observation_id( $id )
		|| ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'backyard_birds_audio_' . $id ) ) {
		return new WP_Error( 'backyard_audio_access', 'Geen toegang tot deze audio.', array( 'status' => 403 ) );
	}
	$client = new Backyard_API_Client();
	$record = $client->get( '/api/observations/' . $id );
	if ( is_wp_error( $record ) ) {
		return new WP_Error( 'backyard_audio', 'Audio tijdelijk niet beschikbaar.', array( 'status' => 502 ) );
	}
	if ( ( $record['id'] ?? null ) !== $id || 'bird' !== ( $record['domain'] ?? null ) || true !== ( $record['audio_available'] ?? false ) ) {
		return new WP_Error( 'backyard_audio', 'Audio niet beschikbaar.', array( 'status' => 404 ) );
	}
	return $client->audio( '/api/observations/' . $id . '/audio', $range );
}

function backyard_birds_handle_audio() {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'Ongeldige audioaanvraag.', '', array( 'response' => 405 ) );
	}
	$result = backyard_birds_audio_response( $_GET['observation_id'] ?? null, $_GET['_wpnonce'] ?? null, $_SERVER['HTTP_RANGE'] ?? '' );
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	if ( is_wp_error( $result ) ) {
		$details = $result->get_error_data();
		wp_die( 'Audio niet beschikbaar.', '', array( 'response' => is_array( $details ) ? ( $details['status'] ?? 502 ) : 502 ) );
	}
	try {
		// Finish cleanup even when the browser stops playback/disconnects.
		ignore_user_abort( true );
		status_header( $result['status'] );
		header( 'Content-Type: audio/wav' );
		header( 'Content-Disposition: inline; filename="backyard-observation.wav"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . $result['size'] );
		if ( $result['content_range'] ) {
			header( 'Content-Range: ' . $result['content_range'] );
		}
		readfile( $result['file'] );
	} finally {
		wp_delete_file( $result['file'] );
	}
	exit;
}

add_action( 'admin_post_backyard_birds_review', 'backyard_birds_handle_review' );
add_action( 'admin_post_backyard_birds_audio', 'backyard_birds_handle_audio' );
