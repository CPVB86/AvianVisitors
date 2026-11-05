<?php
defined( 'ABSPATH' ) || exit;

function backyard_module_information( $view, $module ) {
	$data = backyard_data_snapshot( $module );
	if ( is_wp_error( $data ) ) {
		echo '<p>Gegevens zijn tijdelijk niet beschikbaar. Controleer de API-verbinding.</p>';
	} elseif ( 'species' === $view ) {
		if ( ! $data['species'] ) { echo '<p>Er zijn nog geen waargenomen soorten.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Soort</th><th>Wetenschappelijke naam</th><th>Waarnemingen</th></tr></thead><tbody>';
			foreach ( $data['species'] as $row ) { echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['scientific_name'] ) . '</td><td>' . esc_html( $row['count'] ) . '</td></tr>'; }
			echo '</tbody></table>';
		}
	} else {
		if ( ! $data['stats']['total_observations'] ) { echo '<p>Er zijn nog geen geaccepteerde waarnemingen.</p>'; }
		echo '<table class="widefat striped"><tbody>';
		foreach ( array( 'total_observations' => 'Waarnemingen', 'unique_species' => 'Soorten', 'today_observations' => 'Waarnemingen vandaag', 'today_species' => 'Soorten vandaag', 'active_days' => 'Actieve dagen' ) as $field => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $data['stats'][ $field ] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
