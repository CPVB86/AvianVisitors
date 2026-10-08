<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/avian.php';
if ( is_admin() ) {
	require_once __DIR__ . '/review.php';
}

/** Birds consumers share this read-only adapter. No requests on module load. */
function backyard_birds_public_observations( $limit = 25 ) {
	$client = new Backyard_API_Client();
	$limit = max( 1, min( 100, (int) $limit ) );
	$rows = array();
	// The API accepts one status per request. Merge both bounded result sets.
	foreach ( array( 'auto_accepted', 'human_confirmed' ) as $status ) {
		$result = $client->get( '/api/observations', array( 'domain' => 'bird', 'status' => $status, 'limit' => $limit ) );
		if ( is_wp_error( $result ) || ! is_array( $result ) || array_values( $result ) !== $result ) {
			return new WP_Error( 'backyard_observations', 'Vogelregistraties zijn tijdelijk niet beschikbaar.' );
		}
		foreach ( $result as $row ) {
			// Fail closed for private statuses, other domains and legacy records.
			if ( ! is_array( $row ) || ( $row['domain'] ?? null ) !== 'bird' || ( $row['status'] ?? null ) !== $status ) {
				continue;
			}
			if ( ! isset( $row['id'], $row['timestamp'] ) || ! is_string( $row['id'] ) || '' === $row['id']
				|| ! is_string( $row['timestamp'] ) || false === strtotime( $row['timestamp'] ) ) {
				return new WP_Error( 'backyard_observations', 'Vogelregistraties zijn tijdelijk niet beschikbaar.' );
			}
			$rows[ $row['id'] ] = $row;
		}
	}
	$rows = array_values( $rows );
	usort( $rows, function ( $a, $b ) {
		return ( new DateTimeImmutable( $b['timestamp'] ) <=> new DateTimeImmutable( $a['timestamp'] ) ) ?: strcmp( $b['id'], $a['id'] );
	} );
	return array_slice( $rows, 0, $limit );
}

function backyard_birds_log( $attributes = array() ) {
	$attributes = shortcode_atts( array( 'limit' => 25, 'language' => 'nl' ), $attributes, 'backyard_birds_log' );
	$language = is_string( $attributes['language'] ) ? strtolower( trim( $attributes['language'] ) ) : '';
	// Add language-to-field mappings here when the API supplies more translations.
	$name_fields = array( 'nl' => 'common_name_nl', 'en' => 'common_name', 'de' => 'common_name_de' );
	$name_field = $name_fields[ $language ] ?? 'common_name';
	$limit = filter_var( $attributes['limit'], FILTER_VALIDATE_INT );
	$limit = false === $limit ? 25 : max( 1, min( 100, $limit ) );
	$rows = backyard_birds_public_observations( $limit );
	$error = '<p>Vogelregistraties zijn tijdelijk niet beschikbaar.</p>';
	if ( is_wp_error( $rows ) || ! is_array( $rows ) || array_values( $rows ) !== $rows ) {
		return $error;
	}
	if ( ! $rows ) {
		return '<p>Er zijn nog geen vogelregistraties.</p>';
	}
	$html = '<table class="backyard-birds-log"><caption>Recente vogelregistraties</caption><thead><tr><th scope="col">Tijd</th><th scope="col">Vogel</th><th scope="col">Soort</th><th scope="col">Latijnse naam</th><th scope="col">Confidence</th></tr></thead><tbody>';
	$generator = new Backyard_Generator();
	// Public observations are ordered by timestamp descending, then ID.
	foreach ( array_slice( $rows, 0, $limit ) as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['timestamp'], $row['confidence'] )
			|| ! is_string( $row['timestamp'] ) || ! is_numeric( $row['confidence'] )
			|| $row['confidence'] < 0 || $row['confidence'] > 1
			|| ( isset( $row['common_name'] ) && ! is_string( $row['common_name'] ) )
			|| ( isset( $row['scientific_name'] ) && ! is_string( $row['scientific_name'] ) ) ) {
			return $error;
		}
		$timestamp = strtotime( $row['timestamp'] );
		if ( false === $timestamp ) {
			return $error;
		}
		$html .= '<tr><td><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">' . esc_html( wp_date( 'd-m-Y H:i:s', $timestamp ) ) . '</time></td>';
		$image = $generator->thumbnail( 'bird', $row['scientific_name'] ?? '', array( 'perched', 'flight', 'photo_cutout' ) );
		$html .= '<td>' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="" width="64" height="64" loading="lazy" style="width:64px;height:64px;object-fit:contain;background:transparent">' : '' ) . '</td>';
		$name = $row[ $name_field ] ?? null;
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			$name = $row['common_name'] ?? '—';
		}
		$html .= '<td>' . esc_html( $name ) . '</td><td>' . esc_html( $row['scientific_name'] ?? '—' ) . '</td>';
		$html .= '<td>' . esc_html( number_format_i18n( (float) $row['confidence'] * 100, 1 ) . '%' ) . '</td></tr>';
	}
	return $html . '</tbody></table>';
}

add_shortcode( 'backyard_birds_log', 'backyard_birds_log' );
add_filter( 'backyard_shortcode_docs', function ( $entries ) {
	$entries[] = array(
		'module' => 'Birds', 'title' => 'Recente vogelregistraties', 'shortcode' => '[backyard_birds_log]',
		'parameters' => array(
			'limit' => 'Aantal recente registraties; standaard 25, minimaal 1, maximaal 100.',
			'language' => 'Taal van de soortnaam: NL (standaard), EN of DE, ongeacht hoofdletters. Ontbrekende vertalingen of onbekende talen vallen terug op common_name.',
		),
		'parameter_examples' => array( 'limit' => '[backyard_birds_log limit="50"]', 'language' => '[backyard_birds_log language="nl" limit="50"]' ),
		'description' => 'De laatste vogelregistraties met tijd, vogelafbeelding indien beschikbaar, soort en confidence. Nieuwste bovenaan, in de WordPress-tijdzone.',
	);
	return $entries;
} );

return array(
	'title' => 'Birds',
	'description' => 'Review van vogelwaarnemingen.',
	'admin_page' => 'backyard_birds_admin_page',
	'summary' => 'backyard_birds_summary',
);
