<?php
defined( 'ABSPATH' ) || exit;

function backyard_birds_otje_view() {
	return 'otje' === ( $_GET['view'] ?? '' );
}

function backyard_birds_otje_dashboard() {
	backyard_require_admin();
	$count = backyard_birds_review_count( true );
	$text = is_wp_error( $count ) ? 'Teller tijdelijk niet beschikbaar.' : ( $count ? sprintf( '%s wachten op beoordeling', number_format_i18n( $count ) ) : 'Geen kandidaten' );
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=backyard-birds&view=otje' ) ) . '">' . esc_html( $text ) . '</a></p>';
}

/** The backend capability is the sole authority for the human identity choice. */
function backyard_birds_otje_candidate( $row ) {
	return 'bird' === ( $row['domain'] ?? null )
		&& in_array( $row['status'] ?? null, array( 'pending_review', 'review_recommended' ), true )
		&& backyard_birds_otje_supported( $row );
}

function backyard_birds_otje_supported( $row ) {
	return is_array( $row['review_capabilities']['identity_overrides'] ?? null )
		&& in_array( 'otje', $row['review_capabilities']['identity_overrides'], true );
}

function backyard_birds_otje_button( $row ) {
	if ( ! backyard_birds_otje_candidate( $row ) ) {
		return;
	}
	echo ' <button type="submit" class="button" name="decision" value="otje">🐔 Otje</button>';
}
