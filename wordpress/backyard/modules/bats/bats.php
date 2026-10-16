<?php
defined( 'ABSPATH' ) || exit;

function backyard_bats_summary() {
	$count = backyard_birds_review_count( false, 'bat' );
	return is_wp_error( $count ) ? $count : array( 'text' => sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ), 'url' => admin_url( 'admin.php?page=backyard-bats' ) );
}

function backyard_bats_admin_page() {
	// Keep existing Waarnemingen links working.
	if ( 'observations' === ( $_GET['view'] ?? '' ) ) { $_GET['view'] = 'confirmed'; }
	backyard_birds_admin_page( 'bat' );
}

function backyard_bats_information( $view ) {
	$data = backyard_data_snapshot( 'bats' );
	if ( is_wp_error( $data ) ) {
		echo '<p>Vleermuisgegevens zijn tijdelijk niet beschikbaar. Controleer de API-verbinding.</p>';
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
}
return array( 'title' => 'Bats', 'description' => 'Vleermuiswaarnemingen, soorten en statistieken.', 'admin_page' => 'backyard_bats_admin_page', 'summary' => 'backyard_bats_summary' );
