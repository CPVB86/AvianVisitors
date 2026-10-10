<?php
defined( 'ABSPATH' ) || exit;

function backyard_manual_icons() {
	$names = array_keys( backyard_lucide_icons() );
	sort( $names, SORT_STRING );
	echo '<section class="backyard-card backyard-icon-browser"><h2>Alle Lucide-iconen</h2><label for="backyard-icon-search">Zoek op icoonnaam</label> <input id="backyard-icon-search" type="search" placeholder="Bijvoorbeeld bird, arrow of cross" autocomplete="off" aria-controls="backyard-icon-grid">';
	echo '<p class="backyard-icon-count" role="status" aria-live="polite">' . count( $names ) . ' iconen</p><div id="backyard-icon-grid" class="backyard-icon-grid" role="list" aria-label="Lucide-iconen">';
	foreach ( $names as $name ) {
		$copy = '[backyard_icon name="' . $name . '"]';
		echo '<div class="backyard-copy-item backyard-icon-tile" role="listitem" data-icon-name="' . esc_attr( $name ) . '"><button type="button" class="backyard-copy-shortcode backyard-copy-icon" title="' . esc_attr( $name ) . '" aria-label="' . esc_attr( 'Kopieer ' . $copy ) . '" data-copy="' . esc_attr( $copy ) . '">' . backyard_icon_shortcode( array( 'name' => $name, 'size' => 28 ) ) . '<code>' . esc_html( $name ) . '</code><sup class="dashicons dashicons-admin-page" aria-hidden="true"></sup></button><span class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></span></div>';
	}
	echo '</div><p class="backyard-icon-empty" hidden>Geen iconen gevonden. Probeer een andere naam.</p></section>';
}
