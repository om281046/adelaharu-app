<?php
/**
 * Harnașul vizual „Pentru el”: construiește paginile pentru poze.cjs.
 *   sp-ea.html       Spațiul meu (CSS/JS din 235 și 266, citite din reference/v2) + partea ei din ah-sp-partener.php, cu contul ei
 *   sp-intrare.html  același, fără cont (ecranul de intrare, cu „Sunt partenerul ei”)
 *   el-*.html        pagina lui (/pentru-el/), tipărită de fragment cu date reale (ciclul ei criptat în $wpdb-ul de test):
 *                    ok (browser), ok (aplicație), ok (aplicație, cu ea intrată pe telefon), toamna (fereastra iernii), pauza,
 *                    oprit, fără cont, link expirat
 * Rulare: php construieste.php <dosar>   (o face poze.cjs)
 */
$dir = rtrim( $argv[1] ?? '', '/' );
if ( '' === $dir || ! is_dir( $dir ) ) {
	fwrite( STDERR, "Folosire: php construieste.php <dosar>\n" );
	exit( 1 );
}
require __DIR__ . '/stubs.php';
require __DIR__ . '/../../ah-sp-partener.php';

$ref  = __DIR__ . '/../../../reference/v2/';
$s235 = file_get_contents( $ref . 'sn235.php' );
$s266 = file_get_contents( $ref . 'sn266.php' );
function intre( $s, $a, $b ) {
	$i = strpos( $s, $a );
	if ( false === $i ) { throw new Exception( 'nu găsesc: ' . $a ); }
	$i += strlen( $a );
	$j = strpos( $s, $b, $i );
	if ( false === $j ) { throw new Exception( 'nu găsesc: ' . $b ); }
	return substr( $s, $i, $j - $i );
}
$css235  = intre( $s235, "\$css = <<<'AHSPCSS'\n", "\nAHSPCSS;" );
$js235   = intre( $s235, "\$js = <<<'AHSPJS'\n", "\nAHSPJS;" );
$ascunde = intre( $s235, "\t\$ascunde = '", "';\n" );
$css266  = intre( $s266, "\$css = <<<'AHSPXCSS'\n", "\nAHSPXCSS;" );
$js266   = intre( $s266, "\$js = <<<'AHSPXJS'\n", "\nAHSPXJS;" );

function pagina( $cap, $corp ) {
	return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#FBF6F3"><title>Spațiul meu</title>'
		. '<link rel="stylesheet" href="/fonts/fonts.css">' . "\n" . $cap . '</head><body class="page ahsp-pagina"><div class="site-content"><article><div class="entry-content">' . $corp . '</div></article></div></body></html>';
}
$x266 = function ( $cont ) {
	return array( 'cont' => $cont, 'nume' => $cont ? 'Ana' : '', 'ganduri' => array( 'Ia-ți un moment doar pentru tine.' ), 'evenimente' => array(), 'acum' => time(), 'api' => 'https://www.adelaharu.ro/wp-json/ah-spx/v1/', 'termeni' => 'https://www.adelaharu.ro/termeni-si-conditii/', 'gdpr' => 'https://www.adelaharu.ro/politica-de-confidentialitate/' );
};
$GLOBALS['ah_test_e_pagina'] = true;
$_SERVER['REQUEST_URI']      = '/spatiul-meu/';
$_SERVER['HTTP_USER_AGENT']  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

/* ---------- Spațiul meu, cu contul ei ---------- */
$D = json_decode( file_get_contents( __DIR__ . '/../date/spatiul-meu.json' ), true );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
$cap = '<style id="ahspx-css">' . $css266 . '</style><script id="ahspx-js">window.AH_SPX=' . wp_json_encode( $x266( true ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';' . $js266 . '</script>' . "\n" . ah_test_actiune( 'wp_head' );
$sc  = '<style id="ahsp-css">' . $ascunde . $css235 . '</style><div id="ahsp"></div><script>window.AH_SP=' . wp_json_encode( $D ) . ';</script><script>' . $js235 . '</script>';
file_put_contents( $dir . '/sp-ea.html', pagina( $cap, $sc ) );

/* ---------- ecranul de intrare ---------- */
$GLOBALS['ah_test_email'] = '';
$cap = '<style id="ahspx-css">' . $css266 . '</style><script id="ahspx-js">window.AH_SPX=' . wp_json_encode( $x266( false ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';' . $js266 . '</script>' . "\n" . ah_test_actiune( 'wp_head' );
$sc  = '<style id="ahsp-css">' . $ascunde . $css235 . '</style><div id="ahsp"></div><script>window.AH_SP=' . wp_json_encode( array( 'stare' => 'intrare', 'api' => 'https://www.adelaharu.ro/wp-json/ah-sp/v1/' ) ) . ';</script><script>' . $js235 . '</script>';
file_put_contents( $dir . '/sp-intrare.html', pagina( $cap, $sc ) );

/* ---------- pagina lui ---------- */
global $wpdb;
$zi = function ( $n ) { return date( 'Y-m-d', strtotime( date( 'Y-m-d' ) . ' 12:00:00' ) - $n * 86400 ); };
$ciclu = function ( $c ) use ( $wpdb ) {
	$wpdb->delete( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( 'ana@exemplu.ro' ) ) );
	$wpdb->insert( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( 'ana@exemplu.ro' ), 'date' => ah_sp_ciclu_cripteaza( json_encode( array( 'ciclu' => $c, 'note' => array( date( 'Y-m-d' ) => array( 'text' => 'NOTITA-SECRETA' ) ) ) ) ), 'acord' => '2026-11-01 10:00:00', 'actualizat' => '2026-11-01 10:00:00' ) );
};
$ciclu( array( 'starturi' => array( $zi( 8 ), $zi( 36 ), $zi( 64 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
ah_spp_r_invita( new WP_REST_Request( array( 'x_ah_spp' => '1' ), array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$GLOBALS['ah_test_email'] = '';
$r = $wpdb->get_row( 'SELECT * FROM wp_ah_spp_parteneri ORDER BY id DESC LIMIT 1', ARRAY_A );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $r, time() + 3600 );
$scrie = function ( $nume, $cfg ) use ( $dir ) {
	file_put_contents( $dir . '/el-' . $nume . '.html', ah_spp_pagina_el( $cfg ) );
	file_put_contents( $dir . '/el-' . $nume . '.json', wp_json_encode( $cfg['azi'] ? $cfg['azi'] : array( 'ok' => false, 'stare' => 'iesit' ) ) );
};
$scrie( 'ok', ah_spp_cfg_el() );
eval( 'function ah_spa_e_aplicatie(){ return ! empty( $GLOBALS["ah_test_app"] ); }' );
$GLOBALS['ah_test_app'] = true;
$scrie( 'aplicatie', ah_spp_cfg_el() );
// același telefon, cu ea intrată (cookie-ul ah_sp): telefonul e al contului ei, pagina lui nu oferă notificări
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
$scrie( 'aplicatie-ea', ah_spp_cfg_el() );
$GLOBALS['ah_test_email'] = '';
$GLOBALS['ah_test_app'] = false;
// ziua 28 din 28: toamna, iarna următoare ca fereastră (aceeași în ziua estimată și după ea)
$ciclu( array( 'starturi' => array( $zi( 27 ), $zi( 55 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$scrie( 'toamna', ah_spp_cfg_el() );
// întârziere peste 10 zile: pauza (la fel arată și hormonal/sarcină, după amânare)
$ciclu( array( 'starturi' => array( $zi( 45 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$scrie( 'pauza', ah_spp_cfg_el() );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s' ) ), array( 'id' => $r['id'] ) );
$scrie( 'oprit', ah_spp_cfg_el() );
unset( $_COOKIE['ah_spp'] );
$scrie( 'iesit', ah_spp_cfg_el() );
$scrie( 'link', ah_spp_cfg_el( 'link' ) );
echo "gata\n";
