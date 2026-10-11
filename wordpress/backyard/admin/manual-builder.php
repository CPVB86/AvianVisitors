<?php
defined( 'ABSPATH' ) || exit;

function backyard_manual_builder() {
	$config = array( 'speciesFields' => backyard_data_fields(), 'statsFields' => backyard_data_stat_fields() );
	echo '<section class="backyard-card" id="backyard-builder" data-config="' . esc_attr( json_encode( $config ) ) . '"><h2>Shortcode-builder</h2><p>Kies achtereenvolgens module, type, veld en periode. Alleen passende opties worden aangeboden.</p><noscript>Schakel JavaScript in om de builder te gebruiken. De voorbeelden hieronder blijven beschikbaar.</noscript><div class="backyard-builder-fields">';
	$selects = array(
		'module' => array( '1. Module', array( 'birds' => 'Birds', 'bats' => 'Bats' ) ),
		'type' => array( '2. Type', array( 'last' => 'Laatst waargenomen soort', 'most' => 'Meest waargenomen soort', 'first' => 'Eerst waargenomen soort', 'rarest' => 'Minst waargenomen soort', 'random' => 'Willekeurige soort', 'species' => 'Specifieke soort', 'stats' => 'Statistieken' ) ),
		'field' => array( '3. Veld', array() ),
		'period' => array( '4. Periode', array( 'all' => 'Alle waarnemingen', 'today' => 'Vandaag', '24h' => 'Laatste 24 uur', '7d' => 'Laatste 7 dagen', '30d' => 'Laatste 30 dagen' ) ),
		'output' => array( 'Uitvoer', array() ),
		'identity' => array( 'Identiteit', array( '' => 'Alle identiteiten', 'otje' => 'Alleen Otje' ) ),
		'rank' => array( 'Positie in ranglijst', array_combine( range( 1, 10 ), range( 1, 10 ) ) ),
	);
	foreach ( $selects as $key => $select ) {
		echo '<label data-builder-row="' . esc_attr( $key ) . '">' . esc_html( $select[0] ) . '<select name="' . esc_attr( $key ) . '">';
		foreach ( $select[1] as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; }
		echo '</select></label>';
	}
	foreach ( array( 'species' => 'Wetenschappelijke soortnaam (verplicht)', 'format' => 'Datumformaat (optioneel, bijvoorbeeld d-m-Y)', 'fallback' => 'Tekst bij ontbrekende gegevens (optioneel)' ) as $key => $label ) {
		echo '<label data-builder-row="' . esc_attr( $key ) . '">' . esc_html( $label ) . '<input name="' . esc_attr( $key ) . '" type="text" maxlength="' . ( 'format' === $key ? '100' : '255' ) . '"></label>';
	}
	echo '</div><p class="backyard-builder-hint"></p><p class="backyard-builder-error" role="status"></p><div class="backyard-copy-item"><button type="button" class="backyard-copy-shortcode backyard-builder-copy" disabled aria-label="Kopieer samengestelde shortcode"><code>Kies de opties hierboven.</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><span class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></span></div><p class="description">Klik op de code om te kopiëren. Geldige instellingen garanderen niet dat er al waarnemingen of afbeeldingen beschikbaar zijn.</p></section>';
}
