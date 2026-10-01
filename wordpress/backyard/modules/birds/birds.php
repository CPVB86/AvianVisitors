<?php
defined( 'ABSPATH' ) || exit;

/** Future Birds consumers share this read-only adapter. No automatic requests. */
function backyard_birds_detections( $limit = 50 ) {
	$client = new Backyard_API_Client();
	return $client->get( '/api/birds/detections', array( 'limit' => max( 1, min( 100, (int) $limit ) ) ) );
}

return array( 'title' => 'Birds', 'description' => 'Voorbereid voor het uitlezen van detecties uit de Backyard API. Het Birds-dashboard volgt in een latere fase.' );
