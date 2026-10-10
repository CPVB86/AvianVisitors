<?php
defined( 'ABSPATH' ) || exit;

/** Request-scoped snapshots: shared selections, no stale counts after corrections. */
function backyard_data_snapshot( $module, $period = 'all', $identity = '' ) {
	static $cache = array();
	$timezone = wp_timezone_string();
	$key = hash( 'sha256', get_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL ) . '|' . get_option( 'backyard_api_token', '' ) ) . '|' . $module . '|' . $period . '|' . $timezone . '|' . $identity;
	if ( ! array_key_exists( $key, $cache ) ) {
		// add_query_arg expects new values to be encoded; a bare + becomes a space.
		$query = array( 'period' => $period, 'timezone' => rawurlencode( $timezone ) );
		if ( '' !== $identity ) { $query['identity'] = $identity; }
		$result = ( new Backyard_API_Client() )->get( '/api/presentation/' . $module, $query );
		$cache[ $key ] = ! is_wp_error( $result ) && is_array( $result ) && ( $result['module'] ?? null ) === $module
			&& ( '' === $identity || ( $result['identity'] ?? null ) === $identity )
			&& ( $result['period'] ?? null ) === $period && is_array( $result['species'] ?? null ) && is_array( $result['stats'] ?? null ) && is_array( $result['rankings'] ?? null )
			? $result : ( is_wp_error( $result ) ? $result : new WP_Error( 'backyard_data', 'Gegevens zijn tijdelijk niet beschikbaar.' ) );
	}
	return $cache[ $key ];
}

/** Admin-only diagnostic: never return response bodies, URLs or credentials. */
function backyard_data_connection_status() {
	backyard_require_admin();
	$data = backyard_data_snapshot( 'birds' );
	if ( ! is_wp_error( $data ) ) {
		return 'Shortcode-data bereikbaar: ' . (int) ( $data['stats']['total_observations'] ?? 0 ) . ' geaccepteerde waarnemingen.';
	}
	$code = $data->get_error_code();
	$detail = $data->get_error_data();
	if ( 'backyard_http' === $code && is_array( $detail ) && isset( $detail['status'] ) ) {
		$status = (int) $detail['status'];
		return 'Shortcode-data: HTTP ' . $status . '. ' . ( 404 === $status ? 'Endpoint ontbreekt. Controleer de Pi-update, API-herstart en eventuele reverse proxy.' : ( $status >= 500 ? 'De API meldt een serverfout. Controleer het backyard-api-log direct na deze test.' : 'Het endpoint weigert de aanvraag. Controleer de API-configuratie en WordPress-tijdzone.' ) );
	}
	$messages = array(
		'backyard_auth' => 'Authenticatie geweigerd. Controleer het API-token.',
		'backyard_token' => 'Geen geldig API-token ingesteld.',
		'backyard_connection' => 'Verbindingsfout of time-out bij het ophalen van de shortcode-data.',
		'backyard_json' => 'Geen geldig JSON-antwoord. Controleer de API en eventuele reverse proxy.',
		'backyard_data' => 'Het antwoord voldoet niet aan het presentatiecontract. Controleer de API-versie.',
	);
	return 'Shortcode-data: ' . ( $messages[ $code ] ?? 'Onbekende API-fout.' );
}

function backyard_data_fields() {
	return array( 'name', 'scientific_name', 'perched', 'flying', 'wikipedia_url', 'observations_url', 'count', 'first_seen', 'first_date', 'first_time', 'last_seen', 'last_date', 'last_time', 'species_id' );
}
function backyard_data_stat_fields() {
	return array( 'total_observations', 'unique_species', 'today_observations', 'today_species', 'last_activity', 'new_species', 'active_days' );
}

/** Bundled presentation assets, independent of species Generator availability. */
function backyard_data_otje_image( $field ) {
	$file = 'flying' === $field ? 'otje-2.png' : 'otje.png';
	return plugins_url( 'assets/' . $file, dirname( __DIR__ ) . '/backyard.php' );
}

function backyard_data_image_output( $url, $name, $output ) {
	$url = esc_url( $url, array( 'http', 'https' ) );
	if ( 'image' === $output ) { return '<img src="' . $url . '" alt="' . esc_attr( $name ) . '" loading="lazy">'; }
	if ( 'link' === $output ) { return '<a href="' . $url . '">' . esc_html( $name ) . '</a>'; }
	return $url;
}

function backyard_data_shortcode( $attributes = array(), $content = null, $tag = 'backyard_data' ) {
	$a = shortcode_atts( array( 'module' => 'birds', 'type' => 'last', 'field' => 'name', 'period' => 'all', 'rank' => '1', 'species' => '', 'identity' => '', 'output' => 'text', 'fallback' => '', 'format' => '' ), $attributes, $tag );
	foreach ( $a as $value ) { if ( ! is_scalar( $value ) ) { return ''; } }
	if ( 'bird_data' === $tag ) { $a['module'] = 'birds'; }
	if ( 'bat_data' === $tag ) { $a['module'] = 'bats'; }
	$fallback = esc_html( $a['fallback'] );
	$rank = filter_var( $a['rank'], FILTER_VALIDATE_INT );
	if ( ! in_array( $a['module'], array( 'birds', 'bats' ), true )
		|| ! in_array( $a['type'], array( 'last', 'most', 'first', 'rarest', 'random', 'species', 'stats' ), true )
		|| ! in_array( $a['identity'], array( '', 'otje' ), true )
		|| ( '' !== $a['identity'] && ( 'birds' !== $a['module'] || '' !== trim( $a['species'] ) ) )
		|| ! in_array( $a['period'], array( 'today', '24h', '7d', '30d', 'all' ), true )
		|| ! in_array( $a['output'], array( 'text', 'url', 'image', 'link' ), true )
		|| false === $rank || $rank < 1 || $rank > 10 || strlen( $a['format'] ) > 100
		|| ! in_array( $a['field'], 'stats' === $a['type'] ? backyard_data_stat_fields() : backyard_data_fields(), true ) ) { return $fallback; }
	// An explicitly requested known portrait does not require an observation or API call.
	if ( 'otje' === $a['identity'] && 1 === $rank && in_array( $a['field'], array( 'perched', 'flying' ), true ) ) {
		return backyard_data_image_output( backyard_data_otje_image( $a['field'] ), 'Otje', $a['output'] );
	}
	$data = backyard_data_snapshot( $a['module'], $a['period'], $a['identity'] );
	if ( is_wp_error( $data ) ) { return $fallback; }
	$row = null;
	if ( 'stats' === $a['type'] ) {
		$value = $data['stats'][ $a['field'] ] ?? null;
	} else {
		$selection = 'species' === $a['type'] && '' !== $a['identity'] ? 'last' : $a['type'];
		$id = $data['rankings'][ $selection ][ $rank - 1 ] ?? null;
		foreach ( $data['species'] as $candidate ) {
			if ( 'species' === $selection ? ( $candidate['scientific_name'] ?? null ) === trim( $a['species'] ) : null !== $id && ( $candidate['species_id'] ?? null ) === $id ) {
				$row = $candidate; break;
			}
		}
		if ( ! $row ) { return $fallback; }
		$value = $row[ $a['field'] ] ?? null;
	}
	$is_image = in_array( $a['field'], array( 'perched', 'flying' ), true );
	$is_url = $is_image || in_array( $a['field'], array( 'wikipedia_url', 'observations_url' ), true );
	if ( $is_image && 'birds' === $a['module'] && 'otje' === ( $row['identity'] ?? null ) ) {
		return backyard_data_image_output( backyard_data_otje_image( $a['field'] ), 'Otje', $a['output'] );
	}
	if ( $is_image ) {
		static $generator = null;
		if ( null === $generator ) { $generator = new Backyard_Generator(); }
		$pose = 'flying' === $a['field'] ? 'flight' : 'perched';
		$asset = $row['assets'][ $pose ] ?? $pose;
		$value = is_string( $asset ) ? $generator->thumbnail( 'birds' === $a['module'] ? 'bird' : 'bat', $row['scientific_name'], array( $asset ) ) : '';
	}
	if ( preg_match( '/^(first|last)_(seen|date|time)$/D', $a['field'], $matches ) || 'last_activity' === $a['field'] ) {
		$value = 'last_activity' === $a['field'] ? $value : ( $row[ $matches[1] . '_seen' ] ?? null );
		$stamp = is_string( $value ) ? strtotime( $value ) : false;
		$kind = $matches[2] ?? 'seen';
		$value = false === $stamp ? null : wp_date( $a['format'] ?: array( 'seen' => 'd-m-Y H:i:s', 'date' => 'd-m-Y', 'time' => 'H:i:s' )[ $kind ], $stamp );
	}
	if ( null === $value || '' === $value || ! is_scalar( $value ) ) { return $fallback; }
	if ( $is_url ) {
		$url = esc_url( $value, array( 'http', 'https' ) );
		if ( ! $url ) { return $fallback; }
		if ( 'image' === $a['output'] ) { return $is_image ? '<img src="' . $url . '" alt="' . esc_attr( $row['name'] ?? $row['scientific_name'] ) . '" loading="lazy">' : $fallback; }
		if ( 'link' === $a['output'] ) { return '<a href="' . $url . '">' . esc_html( $row['name'] ?? $row['scientific_name'] ) . '</a>'; }
		return $url;
	}
	return 'text' === $a['output'] ? esc_html( $value ) : $fallback;
}
foreach ( array( 'backyard_data', 'bird_data', 'bat_data' ) as $tag ) { add_shortcode( $tag, 'backyard_data_shortcode' ); }

add_filter( 'backyard_shortcode_docs', function ( $entries ) {
	$parameters = array(
		'module' => 'birds (standaard) of bats. bird_data en bat_data vullen de module automatisch in.',
		'type' => 'last (standaard), most, first, rarest, random, species of stats. Alleen geaccepteerde waarnemingen; correcties tellen mee.',
		'field' => 'Soort: ' . implode( ', ', backyard_data_fields() ) . '. Statistieken: ' . implode( ', ', backyard_data_stat_fields() ) . '.',
		'Naamvelden' => 'name: Nederlandse catalogusnaam (anders bestaande naam), scientific_name: actuele wetenschappelijke naam, species_id: stabiele identificatie van soort en eventuele lokale identiteit.',
		'Afbeeldingsvelden' => 'perched: zittend; flying: vliegend. Bestaande Generator-afbeeldingen; Otje gebruikt de meegeleverde otje.png en otje-2.png. Met identity="otje" is haar foto ook zonder waarnemingen beschikbaar. Ontbrekend betekent fallback, nooit generatie.',
		'Tijdvelden' => 'first_seen/last_seen: volledige datum en tijd; first_date/last_date: alleen datum; first_time/last_time: alleen tijd. count: aantal geaccepteerde waarnemingen.',
		'period' => 'today, 24h, 7d, 30d of all (standaard). Selecties, count en eerste/laatste waarneming gelden binnen deze periode.',
		'rank' => 'Positie 1 t/m 10 (standaard 1). Bij gelijke waarden sorteert de Pi op wetenschappelijke naam en identiteit.',
		'species' => 'Wetenschappelijke naam bij type="species". Bij meerdere identiteiten krijgt de gewone soort voorrang.',
		'identity' => 'otje: uitsluitend expliciet als Otje bevestigde waarnemingen, over alle oorspronkelijke herkenningen heen. Alleen birds; niet combineren met species. Werkt met count, datums, afbeeldingen en stats. Zonder Otje-waarnemingen geldt fallback; statistieken geven nul.',
		'output' => 'text (standaard), url, image of link. Afbeeldingen: perched/flying met image of url. Links: wikipedia_url/observations_url met link of url. Geen styling.',
		'format' => 'Optioneel PHP-datumformaat, bijvoorbeeld d-m-Y of j F Y. Standaard d-m-Y H:i:s, d-m-Y of H:i:s; WordPress-tijdzone en sitetaal gelden.',
		'fallback' => 'Tekst wanneer gegevens ontbreken of de API onbereikbaar is; standaard leeg. Nul is een geldige statistiek.',
		'Statistieken' => 'total_observations, unique_species, last_activity en active_days binnen de periode. today_* altijd vandaag. new_species: soorten die voor het eerst ooit binnen de periode verschenen. Otje is geen extra biologische soort.',
		'Links en consistentie' => 'observations_url gebruikt de bestaande cataloguslink (waarneming.nl); wikipedia_url de bestaande Wikipedia-link. Eén API-snapshot per module/periode/tijdzone per paginarender, ook voor random. Geen blijvende tellingencache. Externe paginacaches kunnen wel oudere HTML tonen.',
	);
	$choices = array();
	foreach ( array(
		'module' => array( 'birds', 'bats' ),
		'type' => array( 'last', 'most', 'first', 'rarest', 'random', 'species', 'stats' ),
		'field' => array_merge( backyard_data_fields(), backyard_data_stat_fields() ),
		'period' => array( 'today', '24h', '7d', '30d', 'all' ),
		'rank' => range( 1, 10 ),
		'species' => array( 'Erithacus rubecula' ),
		'identity' => array( 'otje' ),
		'output' => array( 'text', 'url', 'image', 'link' ),
		'format' => array( 'd-m-Y H:i:s', 'd-m-Y', 'H:i:s', 'j F Y' ),
		'fallback' => array( 'Nog geen waarnemingen' ),
	) as $parameter => $values ) { $choices[ $parameter ] = backyard_parameter_choices( $parameter, $values ); }
	$choices['fallback']['leeg'] = 'fallback=""';
	foreach ( array(
		'Naamvelden' => array( 'name', 'scientific_name', 'species_id' ),
		'Afbeeldingsvelden' => array( 'perched', 'flying' ),
		'Tijdvelden' => array( 'first_seen', 'last_seen', 'first_date', 'last_date', 'first_time', 'last_time', 'count' ),
		'Statistieken' => backyard_data_stat_fields(),
		'Links en consistentie' => array( 'observations_url', 'wikipedia_url' ),
	) as $row => $fields ) { $choices[ $row ] = backyard_parameter_choices( 'field', $fields ); }
	$choices['output'] += backyard_parameter_choices( 'field', array( 'perched', 'flying', 'wikipedia_url', 'observations_url' ) );
	foreach ( array( '[backyard_data module="birds" type="last" field="name"]', '[bird_data type="last" field="perched" output="image"]', '[bat_data type="most" field="scientific_name"]' ) as $index => $code ) {
		$entries[] = array( 'module' => 'Elementor / gedeelde gegevens', 'title' => array( 'Backyard data', 'Birds data', 'Bats data' )[ $index ], 'shortcode' => $code,
			'description' => 'Eén veld voor een Elementor Shortcode-widget of ondersteunde dynamische Shortcode-tag. Afbeeldingen lopen via WordPress; er worden geen API-geheimen meegestuurd.',
			'parameters' => 0 === $index ? $parameters : array(),
			'parameter_choices' => 0 === $index ? $choices : array(),
			'parameter_examples' => array( 'identity' => '[bird_data identity="otje" field="perched" output="image"]', 'rank' => '[bird_data type="most" rank="2" field="count" period="7d"]', 'species' => '[backyard_data module="birds" type="species" species="Erithacus rubecula" field="count" period="30d"]', 'field' => '[bird_data type="stats" field="unique_species"]', 'format' => '[bird_data type="last" field="first_date" format="j F Y"]', 'fallback' => '[bat_data field="name" fallback="Nog geen waarnemingen"]' ) );
	}
	foreach ( array( 'perched' => 'Otje: afbeelding', 'last_seen' => 'Otje: laatste waarneming', 'count' => 'Otje: aantal waarnemingen' ) as $field => $title ) {
		$entries[] = array( 'module' => 'Elementor / gedeelde gegevens', 'title' => $title,
			'shortcode' => '[bird_data identity="otje" field="' . $field . '"' . ( 'perched' === $field ? ' output="image"' : '' ) . ']',
			'description' => 'Alleen Otje, standaard alle perioden. Voeg period="7d" toe voor de laatste zeven dagen.', 'parameters' => array() );
	}
	return $entries;
}, 20 );
