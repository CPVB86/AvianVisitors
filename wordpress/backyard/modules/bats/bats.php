<?php
defined( 'ABSPATH' ) || exit;

function backyard_bats_summary() {
	$data = backyard_data_snapshot( 'bats' );
	return is_wp_error( $data ) ? $data : array( 'text' => $data['stats']['total_observations'] ? sprintf( '%s geaccepteerde waarnemingen', number_format_i18n( $data['stats']['total_observations'] ) ) : 'Nog geen waarnemingen', 'url' => admin_url( 'admin.php?page=backyard-bats' ) );
}

function backyard_bats_admin_page() {
	backyard_require_admin();
	$tabs = array( 'overview' => 'Overzicht', 'observations' => 'Waarnemingen', 'species' => 'Soorten', 'stats' => 'Statistieken' );
	$view = $_GET['view'] ?? 'overview';
	if ( ! is_string( $view ) || ! isset( $tabs[ $view ] ) ) { $view = 'overview'; }
	echo '<div class="wrap"><h1>Bats</h1><nav class="nav-tab-wrapper" aria-label="Bats">';
	foreach ( $tabs as $key => $label ) {
		echo '<a class="nav-tab' . ( $view === $key ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=backyard-bats&view=' . $key ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav><section class="backyard-card"><h2>' . esc_html( $tabs[ $view ] ) . '</h2>';
	$data = backyard_data_snapshot( 'bats' );
	if ( is_wp_error( $data ) ) {
		echo '<p>Vleermuisgegevens zijn tijdelijk niet beschikbaar. Controleer de API-verbinding.</p>';
	} elseif ( 'observations' === $view ) {
		$rows = array(); $client = new Backyard_API_Client(); $failed = false;
		foreach ( array( 'auto_accepted', 'human_confirmed' ) as $status ) {
			$result = $client->get( '/api/observations', array( 'domain' => 'bat', 'status' => $status, 'limit' => 50 ) );
			if ( is_wp_error( $result ) || ! is_array( $result ) ) { $failed = true; break; }
			foreach ( $result as $row ) { if ( is_array( $row ) && ( $row['domain'] ?? '' ) === 'bat' && ( $row['status'] ?? '' ) === $status ) { $rows[] = $row; } }
		}
		if ( $failed ) { echo '<p>Waarnemingen zijn tijdelijk niet beschikbaar.</p>'; }
		elseif ( ! $rows ) { echo '<p>Er zijn nog geen geaccepteerde vleermuiswaarnemingen.</p>'; }
		else {
			usort( $rows, function ( $a, $b ) { return strcmp( $b['timestamp'], $a['timestamp'] ); } );
			echo '<table class="widefat striped"><thead><tr><th>Tijd</th><th>Soort</th><th>Confidence</th><th>Status</th></tr></thead><tbody>';
			foreach ( array_slice( $rows, 0, 50 ) as $row ) {
				$name = $row['effective_identity']['scientific_name'] ?? $row['scientific_name'];
				echo '<tr><td>' . esc_html( wp_date( 'd-m-Y H:i:s', strtotime( $row['timestamp'] ) ) ) . '</td><td>' . esc_html( $name ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['confidence'] * 100, 1 ) . '%' ) . '</td><td>' . esc_html( 'human_confirmed' === $row['status'] ? 'Handmatig bevestigd' : 'Automatisch geaccepteerd' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	} elseif ( 'species' === $view ) {
		if ( ! $data['species'] ) { echo '<p>Er zijn nog geen waargenomen vleermuissoorten.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Soort</th><th>Wetenschappelijke naam</th><th>Waarnemingen</th></tr></thead><tbody>';
			foreach ( $data['species'] as $row ) { echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['scientific_name'] ) . '</td><td>' . esc_html( $row['count'] ) . '</td></tr>'; }
			echo '</tbody></table>';
		}
	} else {
		if ( ! $data['stats']['total_observations'] ) { echo '<p>Er zijn nog geen geaccepteerde vleermuiswaarnemingen.</p>'; }
		echo '<table class="widefat striped"><tbody>';
		foreach ( array( 'total_observations' => 'Waarnemingen', 'unique_species' => 'Soorten', 'today_observations' => 'Waarnemingen vandaag', 'today_species' => 'Soorten vandaag', 'active_days' => 'Actieve dagen' ) as $field => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $data['stats'][ $field ] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</section></div>';
}
return array( 'title' => 'Bats', 'description' => 'Vleermuiswaarnemingen, soorten en statistieken.', 'admin_page' => 'backyard_bats_admin_page', 'summary' => 'backyard_bats_summary' );
