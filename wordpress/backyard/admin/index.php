<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'backyard_birds', 'Birds', 'backyard_birds_dashboard' );
		wp_add_dashboard_widget( 'backyard_bats', 'Bats', 'backyard_bats_dashboard' );
		wp_add_dashboard_widget( 'backyard_otje', 'Otje', 'backyard_otje_dashboard_card' );
	}
} );

function backyard_birds_dashboard() { backyard_dashboard_card( 'birds' ); }
function backyard_bats_dashboard() { backyard_dashboard_card( 'bats' ); }
function backyard_otje_dashboard_card() { backyard_dashboard_card( 'birds', true ); }

/** Native widgets remain movable and individually selectable in Screen Options. */
function backyard_dashboard_card( $module, $otje = false ) {
	backyard_require_admin();
	$count = backyard_birds_review_count( $otje, 'bats' === $module ? 'bat' : 'bird' );
	$url = admin_url( 'admin.php?page=backyard-' . $module . ( $otje ? '&view=otje' : '' ) );
	echo '<div class="backyard-dashboard-summary"><a class="backyard-dashboard-review" href="' . esc_url( $url ) . '">' . esc_html( is_wp_error( $count ) ? 'Reviewteller tijdelijk niet beschikbaar.' : sprintf( '%s te reviewen waarnemingen', number_format_i18n( $count ) ) ) . '</a>';
	if ( $otje ) {
		echo '<img class="backyard-dashboard-portrait" src="' . esc_url( backyard_data_otje_image( 'perched' ) ) . '" alt="Otje" width="112" height="112"></div>';
		return;
	}
	$data = backyard_data_snapshot( $module );
	if ( is_wp_error( $data ) ) { echo '<p>Statistieken tijdelijk niet beschikbaar.</p></div>'; return; }
	echo '<dl class="backyard-dashboard-numbers">';
	foreach ( array( 'today_observations' => 'Waarnemingen vandaag', 'unique_species' => 'Waargenomen soorten · totaal' ) as $key => $label ) {
		$value = $data['stats'][ $key ] ?? null;
		echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( is_numeric( $value ) ? number_format_i18n( $value ) : '—' ) . '</dd></div>';
	}
	echo '</dl>';
	$id = $data['rankings']['last'][0] ?? null;
	$last = null;
	foreach ( $data['species'] as $row ) { if ( null !== $id && ( $row['species_id'] ?? null ) === $id ) { $last = $row; break; } }
	if ( ! $last ) { echo '<p>Nog geen waarnemingen.</p></div>'; return; }
	$image = backyard_data_shortcode( array( 'module' => $module, 'type' => 'last', 'field' => 'perched', 'output' => 'image' ) );
	if ( '' === $image ) { $image = backyard_data_shortcode( array( 'module' => $module, 'type' => 'last', 'field' => 'flying', 'output' => 'image' ) ); }
	echo '<div class="backyard-dashboard-last">' . $image . '<div><span>Laatste waarneming</span><strong>' . esc_html( $last['name'] ?? $last['scientific_name'] ?? 'Onbekend' ) . '</strong>';
	$stamp = isset( $last['last_seen'] ) && is_string( $last['last_seen'] ) ? strtotime( $last['last_seen'] ) : false;
	if ( false !== $stamp ) { echo '<time datetime="' . esc_attr( gmdate( 'c', $stamp ) ) . '">' . esc_html( wp_date( 'd-m-Y H:i:s', $stamp ) ) . '</time>'; }
	echo '</div></div></div>';
}

// Retain the old callable for integrations; the dashboard uses separate widgets.
function backyard_index_page() { backyard_birds_dashboard(); backyard_bats_dashboard(); }
