<?php
defined( 'ABSPATH' ) || exit;

function backyard_birds_correction_error( $error ) {
	$data = $error->get_error_data();
	$code = is_array( $data ) ? ( $data['status'] ?? 502 ) : 502;
	$messages = array(
		409 => 'De waarneming is gewijzigd of de audio is niet beschikbaar. Haal de actuele waarneming op voordat je opnieuw opslaat.',
		422 => 'Deze soort, identiteit of combinatie is niet toegestaan. Controleer de selectie.',
		404 => 'Waarneming of benodigde audio niet beschikbaar.',
	);
	wp_send_json_error( array( 'message' => $messages[ $code ] ?? 'De API kon de actie niet bevestigen. Probeer hetzelfde verzoek opnieuw of haal de actuele waarneming op.' ), isset( $messages[ $code ] ) ? $code : 502 );
}

/** Browser receives only display fields and a WordPress audio URL. */
function backyard_birds_correction_projection( $row ) {
	if ( ! is_array( $row ) || ! backyard_birds_observation_id( $row['id'] ?? null ) || 'bird' !== ( $row['domain'] ?? null )
		|| ! is_int( $row['review_version'] ?? null ) || ! is_string( $row['scientific_name'] ?? null ) ) {
		return new WP_Error( 'correction_contract', 'Correctiecontract niet beschikbaar.' );
	}
	$effective = $row['effective_identity'] ?? array();
	return array(
		'id' => $row['id'], 'status' => $row['status'], 'review_version' => $row['review_version'],
		'original_name' => $row['common_name_nl'] ?? $row['common_name'] ?? '',
		'original_scientific_name' => $row['scientific_name'],
		'scientific_name' => $effective['scientific_name'] ?? $row['scientific_name'],
		'identity' => $effective['identity_override'] ?? null,
		'confidence' => $row['confidence'] ?? null,
		'date' => wp_date( 'd-m-Y H:i:s', strtotime( $row['timestamp'] ) ),
		'actions' => $row['correction_capabilities']['actions'] ?? array(),
		// JSON/DOM URLs need raw query separators; wp_nonce_url() escapes them for HTML.
		'audio' => ! empty( $row['audio_available'] ) ? add_query_arg( array( 'action' => 'backyard_birds_audio', 'observation_id' => $row['id'], '_wpnonce' => wp_create_nonce( 'backyard_birds_audio_' . $row['id'] ) ), admin_url( 'admin-post.php' ) ) : '',
	);
}

function backyard_birds_correction_payload( $input ) {
	if ( ! is_array( $input ) || ! in_array( $input['action'] ?? null, array( 'confirm', 'reject' ), true )
		|| ! in_array( $input['expected_status'] ?? null, array( 'auto_accepted', 'human_confirmed', 'human_rejected' ), true )
		|| ! is_int( $input['expected_version'] ?? null ) || $input['expected_version'] < 0
		|| ! backyard_birds_observation_id( $input['request_id'] ?? null )
		|| ! in_array( $input['identity_override'] ?? null, array( null, 'otje' ), true )
		|| ! is_string( $input['note'] ?? '' ) || strlen( $input['note'] ?? '' ) > 4000 ) {
		return new WP_Error( 'correction_input', 'Ongeldige correctie.' );
	}
	$payload = array_intersect_key( $input, array_flip( array( 'action', 'expected_status', 'expected_version', 'request_id', 'identity_override', 'note' ) ) );
	if ( array_key_exists( 'scientific_name_override', $input ) ) {
		$name = $input['scientific_name_override'];
		if ( null !== $name && ( ! is_string( $name ) || '' === trim( $name ) || strlen( $name ) > 1020 ) ) { return new WP_Error( 'correction_input', 'Ongeldige soort.' ); }
		$payload['scientific_name_override'] = $name;
	}
	$payload['actor'] = 'wordpress:' . get_current_user_id();
	return $payload;
}

function backyard_birds_correction_ajax() {
	backyard_require_admin();
	check_ajax_referer( 'backyard_corrections', 'nonce' );
	$client = new Backyard_API_Client();
	$op = $_POST['operation'] ?? '';
	if ( 'refresh' === $op ) {
		$stats = $client->get( '/api/avian-visitors/stats', array( 'hours' => 24, 'locale' => 'nl' ) );
		$atlas = $client->get( '/api/avian-visitors/lifelist', array( 'locale' => 'nl' ) );
		$counts = array( 'review' => backyard_birds_review_count(), 'otje' => backyard_birds_review_count( true ) );
		if ( is_wp_error( $stats ) || is_wp_error( $atlas ) || is_wp_error( $counts['review'] ) || is_wp_error( $counts['otje'] ) ) {
			wp_send_json_error( array( 'message' => 'Correctie opgeslagen, maar overzichten konden niet volledig worden vernieuwd.' ), 502 );
		}
		$species = array();
		foreach ( $atlas['species'] ?? array() as $row ) {
			$species[] = array_intersect_key( $row, array_flip( array( 'scientific_name', 'common_name', 'identity_id', 'count' ) ) );
		}
		wp_send_json_success( array( 'counts' => $counts, 'stats' => array_intersect_key( $stats, array_flip( array( 'observation_count', 'species_count', 'all_time_observation_count', 'all_time_species_count', 'window_start', 'window_end' ) ) ), 'atlas' => $species ) );
	}
	if ( 'search' === $op ) {
		$q = isset( $_POST['q'] ) && is_string( $_POST['q'] ) ? trim( wp_unslash( $_POST['q'] ) ) : '';
		if ( '' === $q || strlen( $q ) > 400 ) { wp_send_json_error( array( 'message' => 'Vul een soortnaam in.' ), 400 ); }
		$result = $client->get( '/api/avian-visitors/search', array( 'q' => $q, 'limit' => 8 ) );
		if ( is_wp_error( $result ) ) { backyard_birds_correction_error( $result ); }
		$rows = array();
		foreach ( $result['results'] ?? array() as $row ) {
			if ( is_string( $row['scientific_name'] ?? null ) ) { $rows[] = array( 'scientific_name' => $row['scientific_name'], 'name' => $row['common_name_nl'] ?? $row['scientific_name'] ); }
		}
		wp_send_json_success( $rows );
	}
	$id = $_POST['id'] ?? '';
	if ( ! backyard_birds_observation_id( $id ) || ! in_array( $op, array( 'load', 'save' ), true ) ) { wp_send_json_error( array( 'message' => 'Ongeldige aanvraag.' ), 400 ); }
	$row = $client->get( '/api/observations/' . $id );
	if ( is_wp_error( $row ) ) { backyard_birds_correction_error( $row ); }
	$display = backyard_birds_correction_projection( $row );
	if ( is_wp_error( $display ) || $display['id'] !== $id ) { wp_send_json_error( array( 'message' => 'Correctiecontract niet beschikbaar voor deze waarneming.' ), 502 ); }
	if ( 'save' === $op ) {
		$input = is_string( $_POST['payload'] ?? null ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null;
		$payload = backyard_birds_correction_payload( $input );
		if ( is_wp_error( $payload ) ) { wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 ); }
		// Preserve the user's version and UUID: do not silently retry against newer data.
		$result = $client->post( '/api/observations/' . $id . '/correct', $payload );
		if ( is_wp_error( $result ) ) { backyard_birds_correction_error( $result ); }
		$display = backyard_birds_correction_projection( $result );
		if ( is_wp_error( $display ) || $display['id'] !== $id ) { wp_send_json_error( array( 'message' => 'Opslagantwoord niet bevestigd; haal de actuele waarneming op.' ), 502 ); }
		if ( $display['status'] !== ( 'confirm' === $payload['action'] ? 'human_confirmed' : 'human_rejected' ) || $display['review_version'] <= $payload['expected_version'] ) {
			wp_send_json_error( array( 'message' => 'Correctie niet bevestigd door de API; haal de actuele waarneming op.' ), 502 );
		}
	}
	wp_send_json_success( array( 'observation' => $display, 'request_id' => wp_generate_uuid4() ) );
}
add_action( 'wp_ajax_backyard_correction', 'backyard_birds_correction_ajax' );

function backyard_birds_correction_editor() {
	echo '<dialog id="backyard-correction" aria-labelledby="backyard-correction-title"><h2 id="backyard-correction-title">Waarneming bewerken</h2><p class="correction-message" role="status"></p><div class="correction-detail"></div><audio class="correction-audio" controls preload="none" hidden></audio>';
	echo '<form class="correction-form"><fieldset><p><label>Status <select name="decision"><option value="confirm">Bevestigd</option><option value="reject">Afgewezen</option></select></label></p>';
	echo '<p><label>Soort <select name="species_mode"><option value="keep">Huidige soort behouden</option><option value="original">Oorspronkelijke BirdNET-soort herstellen</option><option value="choose">Andere soort kiezen</option></select></label></p>';
	echo '<div class="correction-search" hidden><label>Zoek in Backyard <input type="search" name="species_query" maxlength="100" autocomplete="off"></label> <button type="button" class="button correction-find">Zoeken</button><p><label>Catalogusresultaat <select name="species"><option value="">Kies een zoekresultaat</option></select></label></p></div>';
	echo '<p><label>Identiteit <select name="identity"><option value="">Geen lokale identiteit (gewone soort)</option><option value="otje">Otje</option></select></label></p><p>Otje is alleen toegestaan voor een geschikte doelsoort; Backyard controleert dit.</p><p><label>Toelichting<br><textarea name="note" maxlength="1000" rows="3"></textarea></label></p></fieldset><p><button type="submit" class="button button-primary">Opslaan</button> <button type="button" class="button correction-reload">Actuele waarneming ophalen</button> <button type="button" class="button correction-close">Sluiten</button></p></form></dialog>';
}
add_action( 'admin_footer-backyard_page_backyard-birds', 'backyard_birds_correction_editor' );
add_action( 'admin_footer-toplevel_page_backyard', 'backyard_birds_correction_editor' );
