<?php
defined( 'ABSPATH' ) || exit;

function backyard_birds_confirmed_options() {
	return array( '1h' => 'Laatste uur', '12h' => '12 uur', '24h' => '24 uur', '7d' => '7 dagen', 'all' => 'Alle' );
}

function backyard_birds_confirmed_statuses( $rejected = false ) {
	if ( $rejected ) { return array( 'human_rejected' => 'Handmatig afgewezen' ); }
	return array( 'auto_accepted' => 'Automatisch geaccepteerd', 'human_confirmed' => 'Handmatig bevestigd', 'human_rejected' => 'Handmatig afgewezen' );
}

function backyard_birds_confirmed_filters( $rejected = false ) {
	$period = $_GET['period'] ?? '24h';
	$status = $_GET['status'] ?? 'all';
	return array(
		is_string( $period ) && isset( backyard_birds_confirmed_options()[ $period ] ) ? $period : '24h',
		is_string( $status ) && isset( backyard_birds_confirmed_statuses( $rejected )[ $status ] ) ? $status : 'all',
	);
}

function backyard_birds_confirmed_controls( $rejected = false ) {
	list( $period, $status ) = backyard_birds_confirmed_filters( $rejected );
	echo '<form method="get"><input type="hidden" name="page" value="backyard-birds"><input type="hidden" name="view" value="' . ( $rejected ? 'rejected' : 'confirmed' ) . '">';
	foreach ( array( 'period' => array( 'Periode', backyard_birds_confirmed_options(), $period ), 'status' => array( 'Status', array( 'all' => 'Alle statussen' ) + backyard_birds_confirmed_statuses( $rejected ), $status ) ) as $key => $field ) {
		echo '<label>' . esc_html( $field[0] ) . ' <select name="' . esc_attr( $key ) . '">';
		foreach ( $field[1] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . ( $value === $field[2] ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label> ';
	}
	echo '<button class="button" type="submit">Filteren</button></form>';
}

function backyard_birds_confirmed_observations( $rejected = false ) {
	list( $period, $status ) = backyard_birds_confirmed_filters( $rejected );
	$durations = array( '1h' => 3600, '12h' => 43200, '24h' => 86400, '7d' => 604800 );
	$cutoff = isset( $durations[ $period ] ) ? time() - $durations[ $period ] : 0;
	$rows = array();
	$client = new Backyard_API_Client();
	foreach ( 'all' === $status ? ( $rejected ? array( 'human_rejected' ) : array( 'auto_accepted', 'human_confirmed' ) ) : array( $status ) as $wanted ) {
		$result = $client->get( '/api/observations', array( 'domain' => 'bird', 'status' => $wanted, 'limit' => 50 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( ! is_array( $result ) || array_values( $result ) !== $result ) { return new WP_Error( 'backyard_review', 'Ongeldig antwoord.' ); }
		foreach ( $result as $row ) {
			if ( ! is_array( $row ) || ( $row['domain'] ?? null ) !== 'bird' || ( $row['status'] ?? null ) !== $wanted ) { continue; }
			if ( ! backyard_birds_observation_id( $row['id'] ?? null ) || ! is_string( $row['timestamp'] ?? null ) || false === strtotime( $row['timestamp'] )
				|| ! is_string( $row['scientific_name'] ?? null ) || ! is_numeric( $row['confidence'] ?? null ) || $row['confidence'] < 0 || $row['confidence'] > 1
				|| ! is_int( $row['supports'] ?? null ) || $row['supports'] < 0 || ! is_bool( $row['audio_available'] ?? null ) ) {
				return new WP_Error( 'backyard_review', 'Ongeldig antwoord.' );
			}
			if ( strtotime( $row['timestamp'] ) >= $cutoff ) { $rows[ $row['id'] ] = $row; }
		}
	}
	$rows = array_values( $rows );
	usort( $rows, function ( $a, $b ) { return ( new DateTimeImmutable( $b['timestamp'] ) <=> new DateTimeImmutable( $a['timestamp'] ) ) ?: strcmp( $b['id'], $a['id'] ); } );
	return array_slice( $rows, 0, 50 );
}
