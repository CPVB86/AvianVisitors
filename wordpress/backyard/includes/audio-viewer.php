<?php
defined( 'ABSPATH' ) || exit;

function backyard_audio_viewer( $id, $domain, $private = false, $spectrogram = true ) {
    if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f-]{36}$/D', $id )
        || ! in_array( $domain, array( 'bird', 'bat' ), true ) ) { return ''; }
    wp_enqueue_script( 'backyard-audio-viewer', plugins_url( 'assets/audio-viewer.js', dirname( __DIR__ ) . '/backyard.php' ), array(), '3', true );
    wp_enqueue_style( 'backyard-audio-viewer', plugins_url( 'assets/audio-viewer.css', dirname( __DIR__ ) . '/backyard.php' ), array(), '1' );
    $args = array( 'action' => 'backyard_audio_view', 'observation_id' => $id, 'domain' => $domain,
        'scope' => $private ? 'admin' : 'public',
        'key' => $private ? wp_create_nonce( 'backyard_audio_view_' . $id ) : wp_hash( 'audio-view|' . $domain . '|' . $id ) );
    $url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
    if ( ! $spectrogram ) {
        return '<audio controls preload="none" src="' . esc_url( add_query_arg( array( 'part' => 'listen' ), $url ) ) . '"></audio><p>' . ( 'bat' === $domain ? 'Luisterversie: volume versterkt en 10× vertraagd.' : 'Luisterversie met volumeversterking.' ) . '</p>';
    }
    return '<div class="backyard-audio-viewer" data-url="' . esc_url( $url ) . '"><button type="button" class="backyard-audio-load">Audio en spectrogram openen</button><p class="backyard-audio-message" role="status"></p><div class="backyard-audio-display" hidden><canvas width="800" height="330" aria-label="Spectrogram: tijd en oorspronkelijke frequentie"></canvas><audio controls preload="none"></audio><p class="backyard-audio-caption"></p></div></div>';
}

function backyard_audio_shortcode( $attributes ) {
    $a = shortcode_atts( array( 'module' => 'birds', 'observation_id' => '', 'spectrogram' => 'yes' ), $attributes, 'backyard_audio' );
    if ( ! is_string( $a['module'] ) || ! in_array( $a['module'], array( 'birds','bats' ), true ) ) { return ''; }
    return backyard_audio_viewer( $a['observation_id'], 'bats' === $a['module'] ? 'bat' : 'bird', false, 'no' !== $a['spectrogram'] );
}
add_shortcode( 'backyard_audio', 'backyard_audio_shortcode' );

/** All public media requests recheck accepted status; signed IDs are not authorization alone. */
function backyard_audio_view_record( $args ) {
    $id = $args['observation_id'] ?? '';
    $domain = $args['domain'] ?? '';
    $key = $args['key'] ?? '';
    $scope = $args['scope'] ?? '';
    if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f-]{36}$/D', $id )
        || ! in_array( $domain, array( 'bird', 'bat' ), true ) || ! is_string( $key )
        || ! in_array( $scope, array( 'admin', 'public' ), true ) ) {
        return new WP_Error( 'audio_forbidden', 'Geen toegang.' );
    }
    if ( 'admin' === $scope ) {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $key, 'backyard_audio_view_' . $id ) ) {
            return new WP_Error( 'audio_forbidden', 'Geen toegang.' );
        }
    } elseif ( ! hash_equals( wp_hash( 'audio-view|' . $domain . '|' . $id ), $key ) ) {
        return new WP_Error( 'audio_forbidden', 'Geen toegang.' );
    }
    $record = ( new Backyard_API_Client() )->get( '/api/observations/' . $id );
    if ( is_wp_error( $record ) || ( $record['id'] ?? null ) !== $id || ( $record['domain'] ?? null ) !== $domain
        || true !== ( $record['audio_available'] ?? false )
        || ( 'public' === $scope && ! in_array( $record['status'] ?? '', array( 'auto_accepted','human_confirmed' ), true ) ) ) {
        return new WP_Error( 'audio_unavailable', 'Audio niet beschikbaar.' );
    }
    return $record;
}

function backyard_audio_view_request() {
    nocache_headers();
    header( 'Cache-Control: private, no-store' );
    if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { status_header( 405 ); exit; }
    $record = backyard_audio_view_record( $_GET );
    if ( is_wp_error( $record ) ) { status_header( 404 ); exit; }
    $client = new Backyard_API_Client();
    $path = '/api/observations/' . $record['id'];
    $part = $_GET['part'] ?? 'metadata';
    if ( 'metadata' === $part ) {
        $result = $client->get( $path . '/audio-view' );
        if ( is_wp_error( $result ) ) { status_header( 502 ); exit; }
        wp_send_json( $result );
    }
    if ( 'listen' !== $part ) { status_header( 400 ); exit; }
    // No Range forwarded: the bounded preview is sent as a full WAV; browser may seek in its buffer.
    $result = $client->audio( $path . '/audio-listen' );
    if ( is_wp_error( $result ) ) { status_header( 502 ); exit; }
    try {
        ignore_user_abort( true );
        header( 'Content-Type: audio/wav' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Length: ' . $result['size'] );
        readfile( $result['file'] );
    } finally { wp_delete_file( $result['file'] ); }
    exit;
}
add_action( 'admin_post_backyard_audio_view', 'backyard_audio_view_request' );
add_action( 'admin_post_nopriv_backyard_audio_view', 'backyard_audio_view_request' );

function backyard_audio_builder() {
    echo '<section class="backyard-card backyard-audio-builder"><h2>Audio + spectrogram</h2><p>Kies één opname via het observation-ID. Op de website zijn alleen geaccepteerde waarnemingen met aanwezige audio beschikbaar.</p><label>Module <select class="audio-module"><option value="birds">Birds</option><option value="bats">Bats</option></select></label> <label>Weergave <select class="audio-display"><option value="yes">Audio + spectrogram</option><option value="no">Alleen audio</option></select></label> <label>Observation-ID <input class="audio-id" type="text" maxlength="36"></label><p class="backyard-copy-item"><button type="button" class="backyard-copy-shortcode" disabled><code class="audio-shortcode">Vul een observation-ID in.</code></button><span class="backyard-copy-status" role="status"></span></p></section>';
    wp_enqueue_script( 'backyard-audio-builder', plugins_url( 'admin/audio-builder.js', dirname( __DIR__ ) . '/backyard.php' ), array(), '2', true );
}
add_filter( 'backyard_shortcode_docs', function( $docs ) {
    $docs[] = array( 'module'=>'bats', 'shortcode'=>'[backyard_audio module="bats" observation_id="UUID"]',
        'title'=>'Audio en spectrogram', 'parameters'=>array( 'module'=>'birds of bats', 'observation_id'=>'ID van een geaccepteerde waarneming met audio' ),
        'description'=>'Gedeelde viewer. Bats: 10x tijdexpansie; overlay toont analysevenster, niet de exacte roep.' );
    return $docs;
} );
