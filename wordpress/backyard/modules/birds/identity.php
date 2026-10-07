<?php
defined( 'ABSPATH' ) || exit;

/** Only offers a human choice; this list never changes an observation's identity. */
function backyard_birds_otje_candidate( $row ) {
	return 'bird' === ( $row['domain'] ?? null ) && in_array( $row['scientific_name'] ?? null, array(
		'Gallus gallus', 'Gallus gallus domesticus', 'Gallus domesticus',
		'Gallus sonneratii', 'Gallus lafayettii', 'Gallus varius',
	), true );
}

function backyard_birds_otje_supported( $row ) {
	return is_array( $row['review_capabilities']['identity_overrides'] ?? null )
		&& in_array( 'otje', $row['review_capabilities']['identity_overrides'], true );
}

function backyard_birds_otje_button( $row ) {
	if ( ! backyard_birds_otje_candidate( $row ) ) {
		return;
	}
	$supported = backyard_birds_otje_supported( $row );
	echo ' <button type="submit" class="button" name="decision" value="otje"' . ( $supported ? '' : ' disabled' ) . '>🐔 Otje</button>';
	if ( ! $supported ) {
		echo '<small> Otje vereist ondersteuning voor identity overrides in de Backyard API.</small>';
	}
}
