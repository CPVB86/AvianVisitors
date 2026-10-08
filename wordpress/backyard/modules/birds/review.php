<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/identity.php';
require_once __DIR__ . '/bulk-review.php';
require_once __DIR__ . '/confirmed.php';

function backyard_birds_review_count( $otje = false ) {
	$query = array( 'domain' => 'bird', 'review_only' => 'true' );
	if ( $otje ) { $query['identity_override'] = 'otje'; }
	$result = ( new Backyard_API_Client() )->get( '/api/observations/count', $query );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	// Older APIs silently ignore unknown query parameters; never trust their total.
	if ( $otje && 'otje' !== ( $result['identity_override'] ?? null ) ) {
		$rows = ( new Backyard_API_Client() )->get( '/api/observations/review', array( 'domain' => 'bird', 'limit' => 100, 'identity_override' => 'otje' ) );
		if ( is_wp_error( $rows ) || ! is_array( $rows ) || array_values( $rows ) !== $rows || count( $rows ) >= 100 ) {
			return new WP_Error( 'backyard_review', 'Werk de Backyard API bij voor een betrouwbare Otje-teller.' );
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { return new WP_Error( 'backyard_review', 'Ongeldig reviewantwoord.' ); }
		}
		return count( array_filter( $rows, 'backyard_birds_otje_candidate' ) );
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

function backyard_birds_pending_observations( $otje = false ) {
	$query = array( 'domain' => 'bird', 'limit' => 50 );
	if ( $otje ) { $query['identity_override'] = 'otje'; }
	$rows = ( new Backyard_API_Client() )->get( '/api/observations/review', $query );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}
	if ( array_values( $rows ) !== $rows ) {
		return new WP_Error( 'backyard_review', 'Waarnemingen tijdelijk niet beschikbaar.' );
	}
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! backyard_birds_observation_id( $row['id'] ?? null )
			|| 'bird' !== ( $row['domain'] ?? null ) || ( ( ! $otje && 'human_review' !== ( $row['classification'] ?? null ) ) || ! in_array( $row['status'] ?? null, array( 'pending_review', 'review_recommended' ), true ) )
			|| ! isset( $row['timestamp'], $row['scientific_name'], $row['confidence'], $row['supports'], $row['audio_available'] )
			|| ! is_string( $row['timestamp'] ) || false === strtotime( $row['timestamp'] )
			|| ! is_string( $row['scientific_name'] ) || ! is_numeric( $row['confidence'] )
			|| $row['confidence'] < 0 || $row['confidence'] > 1
			|| ! is_int( $row['supports'] ) || $row['supports'] < 0 || ! is_bool( $row['audio_available'] ) ) {
			return new WP_Error( 'backyard_review', 'Waarnemingen tijdelijk niet beschikbaar.' );
		}
	}
	return $otje ? array_values( array_filter( $rows, 'backyard_birds_otje_candidate' ) ) : $rows;
}

function backyard_birds_review_notice( $key ) {
	$messages = array(
		'otje' => array( 'success', 'Waarneming bevestigd met menselijke identiteit Otje; BirdNET-evidence behouden.' ),
		'identity_unavailable' => array( 'error', 'Otje niet opgeslagen: deze waarneming of API ondersteunt de identity override niet.' ),
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
	backyard_birds_bulk_notice();
	$otje_view = backyard_birds_otje_view();
	$rejected = 'rejected' === ( $_GET['view'] ?? '' );
	$confirmed = $rejected || 'confirmed' === ( $_GET['view'] ?? '' );
	$active = $rejected ? 'rejected' : ( $confirmed ? 'confirmed' : ( $otje_view ? 'otje' : 'review' ) );
	echo '<nav class="nav-tab-wrapper" aria-label="Birds">';
	foreach ( array( 'review' => 'Review', 'otje' => 'Otje', 'confirmed' => 'Bevestigd', 'rejected' => 'Afgewezen' ) as $view => $label ) {
		echo '<a class="nav-tab' . ( $active === $view ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'backyard-birds', 'view' => $view ), admin_url( 'admin.php' ) ) ) . '"' . ( $active === $view ? ' aria-current="page"' : '' ) . '>' . ( 'otje' === $view ? backyard_otje_icon() : '' ) . esc_html( $label ) . '</a>';
	}
	echo '</nav><section class="backyard-card"><h2>' . ( $confirmed ? ( $rejected ? 'Afgewezen' : 'Bevestigd' ) : ( $otje_view ? backyard_otje_icon() . 'Potentiële Otjes' : 'Review' ) ) . '</h2>';
	if ( $confirmed ) {
		backyard_birds_confirmed_controls( $rejected );
		if ( $rejected ) { echo '<p>Automatisch verworpen detecties worden niet door de API opgeslagen en zijn hier niet beschikbaar.</p>'; }
		$rows = backyard_birds_confirmed_observations( $rejected );
	} else {
		$count = backyard_birds_review_count( $otje_view );
		echo '<p>' . esc_html( is_wp_error( $count ) ? 'Reviewteller tijdelijk niet beschikbaar.' : sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ) ) . '</p>';
		$rows = backyard_birds_pending_observations( $otje_view );
	}
	if ( is_wp_error( $rows ) ) {
		echo '<p>Waarnemingen tijdelijk niet beschikbaar. Probeer het later opnieuw.</p></section></div>';
		return;
	}
	if ( ! $rows ) {
		echo '<p>' . ( $confirmed ? ( $rejected ? 'Geen afgewezen waarnemingen binnen deze filters.' : 'Geen bevestigde waarnemingen binnen deze filters.' ) : 'Er zijn geen vogelwaarnemingen om te reviewen.' ) . '</p></section></div>';
		return;
	}
	echo '<p>Maximaal 50 meest recente waarnemingen. Na een actie worden lijst en teller opnieuw opgehaald.</p>';
	if ( ! $confirmed ) {
	echo '<form class="backyard-bulk-review" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="backyard_birds_bulk_review">';
	wp_nonce_field( 'backyard_birds_bulk_review' );
	if ( $otje_view ) { echo '<input type="hidden" name="review_view" value="otje">'; }
	backyard_birds_bulk_buttons( $rows );
	}
	$generator = new Backyard_Generator();
	echo '<div class="backyard-review-scroll"><table class="widefat striped backyard-review-table"><thead><tr>' . ( $confirmed ? '<th scope="col">Status</th>' : '<th scope="col"><input type="checkbox" class="backyard-select-all" aria-label="Selecteer alle waarnemingen op deze pagina"></th>' ) . '<th scope="col">Vogel</th><th scope="col">Datum/tijd</th><th scope="col">Soort</th><th scope="col">Latijnse naam</th><th scope="col">Confidence</th><th scope="col">Supports</th><th scope="col">Audio</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$name = $row['common_name_nl'] ?? '';
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			$name = isset( $row['common_name'] ) && is_string( $row['common_name'] ) ? $row['common_name'] : '—';
		}
		$time = strtotime( $row['timestamp'] );
		if ( $confirmed ) {
			echo '<tr><td>' . esc_html( backyard_birds_confirmed_statuses( $rejected )[ $row['status'] ] ) . '</td>';
		} else {
		echo '<tr><td><input type="checkbox" name="observation_ids[]" value="' . esc_attr( $row['id'] ) . '" data-otje="' . ( backyard_birds_otje_candidate( $row ) ? '1' : '0' ) . '" aria-label="' . esc_attr( 'Selecteer ' . $name . ' ' . wp_date( 'd-m-Y H:i:s', $time ) ) . '"></td>';
		}
		echo '<td>' . backyard_birds_review_image( $generator, $row ) . '</td><td><time datetime="' . esc_attr( gmdate( 'c', $time ) ) . '">' . esc_html( wp_date( 'd-m-Y H:i:s', $time ) ) . '</time></td>';
		echo '<td>' . esc_html( $name ) . '</td><td>' . esc_html( $row['scientific_name'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['confidence'] * 100, 1 ) . '%' ) . '</td><td>' . esc_html( $row['supports'] ) . '</td><td>';
		if ( $row['audio_available'] ) {
			// Never use audio_url supplied by the API: only an authenticated WP route.
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'backyard_birds_audio', 'observation_id' => $row['id'] ), admin_url( 'admin-post.php' ) ), 'backyard_birds_audio_' . $row['id'] );
			echo '<audio controls preload="none" src="' . esc_url( $url ) . '" aria-label="' . esc_attr( 'Audio van ' . $name ) . '">Je browser ondersteunt geen audio.</audio>';
		} else {
			echo 'Geen audio beschikbaar';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>' . ( $confirmed ? '' : '</form>' ) . '</section></div>';
}

function backyard_birds_handle_review() {
	backyard_require_admin();
	$id = $_POST['observation_id'] ?? null;
	$decision = $_POST['decision'] ?? null;
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! backyard_birds_observation_id( $id ) || ! in_array( $decision, array( 'confirm', 'reject', 'otje' ), true ) ) {
		wp_die( 'Ongeldige reviewaanvraag.', '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'backyard_birds_review_' . $id );
	$result = backyard_birds_apply_review( $id, $decision, $_POST['review_view'] ?? '' );
	// Post/redirect/get prevents a browser refresh from repeating the mutation.
	$query = array( 'review_result' => $result );
	if ( 'otje' === ( $_POST['review_view'] ?? '' ) ) { $query['view'] = 'otje'; }
	wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php?page=backyard-birds' ) ) );
	exit;
}

/** Shared mutation for single and bulk handlers; callers enforce capability and nonce. */
function backyard_birds_apply_review( $id, $decision, $view = '' ) {
	$client = new Backyard_API_Client();
	$record = $client->get( '/api/observations/' . $id );
	$otje = 'otje' === $decision;
	$decision = $otje ? 'confirm' : $decision;
	$result = 'failed';
	if ( ! is_wp_error( $record ) && ( $record['id'] ?? null ) === $id && 'bird' === ( $record['domain'] ?? null ) ) {
		$identity_review = $otje || ( 'otje' === ( $view ) && backyard_birds_otje_candidate( $record ) );
		if ( ( ( ! $identity_review && 'human_review' !== ( $record['classification'] ?? null ) ) || ! in_array( $record['status'] ?? null, array( 'pending_review', 'review_recommended' ), true ) ) ) {
			$result = 'conflict';
		} elseif ( $otje && ( ! backyard_birds_otje_candidate( $record ) || ! backyard_birds_otje_supported( $record ) ) ) {
			$result = 'identity_unavailable';
		} elseif ( 'confirm' === $decision && true !== ( $record['audio_available'] ?? false ) ) {
			$result = 'audio_missing';
		} else {
			$payload = array( 'expected_status' => $record['status'] );
			if ( $otje ) {
				$payload['identity_override'] = 'otje';
			}
			$response = $client->post( '/api/observations/' . $id . '/' . $decision, $payload );
			if ( is_wp_error( $response ) ) {
				$details = $response->get_error_data();
				$result = is_array( $details ) && 409 === ( $details['status'] ?? null ) ? 'conflict' : 'failed';
			} elseif ( ( $response['id'] ?? null ) === $id && 'bird' === ( $response['domain'] ?? null )
				&& ( 'confirm' === $decision ? 'human_confirmed' : 'human_rejected' ) === ( $response['status'] ?? null )
				&& ( ! $otje || ( 'otje' === ( $response['review']['identity_override'] ?? null ) && 'confirm' === ( $response['review']['action'] ?? null ) ) ) ) {
				$result = $otje ? 'otje' : ( 'confirm' === $decision ? 'confirmed' : 'rejected' );
			}
		}
	}
	return $result;
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
