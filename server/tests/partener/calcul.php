<?php
/**
 * Pentru faze.test.cjs: citește cazurile (JSON: [{ciclu, azi:"YYYY-MM-DD"}…]) și scrie ce calculează serverul
 * (ah_spp_d + ah_spp_azi, adică portul lui CI.d / CI.lung / CI.faze / CI.azi). Rulare: php calcul.php <fișier>
 */
require __DIR__ . '/stubs.php';
require __DIR__ . '/../../ah-sp-partener.php';
$cazuri = json_decode( file_get_contents( $argv[1] ), true );
$out    = array();
foreach ( $cazuri as $c ) {
	$x = ah_spp_d( $c['ciclu'] );
	$z = $x ? ah_spp_azi( $x, ah_spp_zi0( $c['azi'] ) ) : null;
	$out[] = array(
		'd'   => $x ? array( 'starturi' => $x['starturi'], 'lung' => $x['lung'], 'men' => $x['men'] ) : null,
		'L'   => $x ? ah_spp_lung( $x ) : null,
		'azi' => $z ? array( 'zi' => $z['zi'], 'L' => $z['L'], 'm' => $z['m'], 'f' => $z['f'], 's0' => $z['s0'], 'fz' => $z['fz'], 'urm' => $z['urm'], 'peste' => $z['peste'] ) : null,
		'iso' => $z ? array( ah_spp_iso( $z['s0'] ), ah_spp_iso( $z['urm'] ) ) : null,
	);
}
echo json_encode( $out );
