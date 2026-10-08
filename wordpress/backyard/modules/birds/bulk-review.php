<?php
defined( 'ABSPATH' ) || exit;

function backyard_birds_review_image( $generator, $row ) {
	$fallback = plugins_url( 'assets/nest.webp', dirname( __DIR__, 2 ) . '/backyard.php' );
	$url = $generator->thumbnail( 'bird', ( $row['effective_identity']['scientific_name'] ?? $row['scientific_name'] ), array( 'perched', 'flight', 'photo_cutout' ) );
	return '<img class="backyard-review-image" src="' . esc_url( $url ?: $fallback ) . '" data-fallback="' . esc_url( $fallback ) . '" width="64" height="64" alt="" loading="lazy" style="width:64px;height:64px;object-fit:contain;background:transparent">';
}

function backyard_birds_bulk_buttons( $rows ) {
	echo '<p class="backyard-review-actions"><button type="submit" class="button button-primary" name="decision" value="confirm">Bevestigen</button> <button type="submit" class="button" name="decision" value="reject">Afwijzen</button>';
	if ( array_filter( $rows, 'backyard_birds_otje_candidate' ) ) {
		echo ' <button type="submit" class="button" name="decision" value="otje">' . backyard_otje_icon() . 'Otje</button>';
	}
	echo ' <span class="backyard-selection-count" role="status" aria-live="polite">Selecteer waarnemingen.</span></p>';
}

function backyard_birds_bulk_notice() {
	if ( 'bulk' !== ( $_GET['review_result'] ?? '' ) ) { return; }
	$counts = array();
	foreach ( array( 'done', 'failed', 'skipped' ) as $key ) {
		$value = $_GET[ $key ] ?? 0;
		$counts[ $key ] = is_scalar( $value ) ? max( 0, min( 50, (int) $value ) ) : 0;
	}
	$class = $counts['failed'] || $counts['skipped'] ? 'warning' : 'success';
	echo '<div class="notice notice-' . esc_attr( $class ) . '"><p>' . esc_html( sprintf(
		'%d verwerkt; %d niet bevestigd (gewijzigd, niet toegestaan of API-fout); %d niet uitgevoerd. De lijst is vernieuwd. Controleer resterende waarnemingen voordat je opnieuw probeert.',
		$counts['done'], $counts['failed'], $counts['skipped']
	) ) . '</p></div>';
}

function backyard_birds_handle_bulk_review() {
	backyard_require_admin();
	check_admin_referer( 'backyard_birds_bulk_review' );
	$ids = $_POST['observation_ids'] ?? array();
	$decision = $_POST['decision'] ?? null;
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_array( $ids ) || ! $ids || count( $ids ) > 50
		|| ! in_array( $decision, array( 'confirm', 'reject', 'otje' ), true ) ) {
		wp_die( 'Ongeldige reviewselectie.', '', array( 'response' => 400 ) );
	}
	foreach ( $ids as $id ) {
		if ( ! backyard_birds_observation_id( $id ) ) { wp_die( 'Ongeldige waarneming.', '', array( 'response' => 400 ) ); }
	}
	$ids = array_values( array_unique( $ids ) );
	$view = 'otje' === ( $_POST['review_view'] ?? '' ) ? 'otje' : '';
	$query = array( 'review_result' => 'bulk', 'done' => 0, 'failed' => 0, 'skipped' => 0 );
	// Bound slow batches below typical PHP request limits. Remaining rows stay open.
	$deadline = microtime( true ) + 15;
	foreach ( $ids as $index => $id ) {
		if ( microtime( true ) >= $deadline ) { $query['skipped'] = count( $ids ) - $index; break; }
		$result = backyard_birds_apply_review( $id, $decision, $view );
		$query[ in_array( $result, array( 'confirmed', 'rejected', 'otje' ), true ) ? 'done' : 'failed' ]++;
	}
	if ( $view ) { $query['view'] = $view; }
	wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php?page=backyard-birds' ) ) );
	exit;
}
add_action( 'admin_post_backyard_birds_bulk_review', 'backyard_birds_handle_bulk_review' );
