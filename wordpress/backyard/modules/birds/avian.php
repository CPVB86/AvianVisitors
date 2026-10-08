<?php
defined( 'ABSPATH' ) || exit;

/** Embed the public AvianVisitors presentation, without API credentials. */
function backyard_avian() {
	return '<div class="backyard-avian" style="width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);height:100vh;height:100dvh;overflow:hidden;">'
		. '<iframe src="' . esc_url( 'https://backyard.tail99c3bd.ts.net:8443/avian-visitors/' ) . '" title="Backyard AvianVisitors" scrolling="no" allowfullscreen style="display:block;width:100%;max-width:100%;height:100%;border:0;"></iframe></div>';
}
add_shortcode( 'backyard_avian', 'backyard_avian' );

add_filter( 'backyard_shortcode_docs', function ( $entries ) {
	$entries[] = array(
		'module' => 'Birds', 'title' => 'AvianVisitors', 'shortcode' => '[backyard_avian]',
		'parameters' => array(),
		'description' => 'AvianVisitors als iframe op schermbreedte en schermhoogte, zonder interne scrollbalken. Gebruik een lege paginatemplate zonder header en footer voor een schermvullende pagina. De bezoeker moet de AvianVisitors-URL kunnen bereiken.',
	);
	return $entries;
} );
