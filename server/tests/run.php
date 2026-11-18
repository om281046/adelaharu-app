<?php
/**
 * Testele fragmentului ah-sp-aplicatie.php (fără WordPress). Rulare: php server/tests/run.php
 * Ies cu cod 1 dacă un test pică. Matematica ciclului din JS: node server/tests/ciclu.test.cjs.
 */

require __DIR__ . '/stubs.php';
require __DIR__ . '/../ah-sp-aplicatie.php';

$GLOBALS['ok']  = 0;
$GLOBALS['rau'] = 0;
function verifica( $c, $nume ) {
	if ( $c ) { $GLOBALS['ok']++; } else { $GLOBALS['rau']++; echo "PICAT: $nume\n"; }
}
function egal( $a, $b, $nume ) {
	verifica( $a === $b, $nume . ( $a === $b ? '' : ' (am ' . var_export( $a, true ) . ', așteptam ' . var_export( $b, true ) . ')' ) );
}
function q( $url ) { parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $x ); return $x; }
const UA_IOS     = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/1.2.0 (ios)';
const UA_ANDROID = 'Mozilla/5.0 (Linux; Android 15; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36 AdelaHaruApp/1.0.3 (android)';
const UA_SAFARI  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
function ca_browser() { $_SERVER['HTTP_USER_AGENT'] = UA_SAFARI; unset( $_COOKIE['ah_app'] ); }
function ca_aplicatie( $ua = UA_IOS ) { $_SERVER['HTTP_USER_AGENT'] = $ua; unset( $_COOKIE['ah_app'] ); }
function redirect_spre( $uri, $devreme = true ) {
	$_SERVER['REQUEST_URI'] = $uri;
	try { ah_spa_redirectioneaza( $devreme ); } catch ( Ah_Test_Redirect $r ) { return $r->loc; }
	return '';
}

/* ---------- 0. fragmentul se încarcă o singură dată, fără efecte ---------- */
egal( AH_SPA, '2.2.0', 'versiunea fragmentului' );
verifica( isset( $GLOBALS['ah_test_hooks']['template_redirect'] ), 'cârligul de redirecționare e legat' );
$n_hooks = count( $GLOBALS['ah_test_hooks'], COUNT_RECURSIVE );
require __DIR__ . '/../ah-sp-aplicatie.php'; // a doua oară: gărzile AH_SPA și function_exists
egal( count( $GLOBALS['ah_test_hooks'], COUNT_RECURSIVE ), $n_hooks, 'a doua încărcare nu leagă nimic din nou' );

/* ---------- 1. recunoașterea aplicației ---------- */
egal( ah_spa_ua_aplicatie( UA_IOS ), array( 'platforma' => 'ios', 'versiune' => '1.2.0' ), 'UA iPhone' );
egal( ah_spa_ua_aplicatie( UA_ANDROID ), array( 'platforma' => 'android', 'versiune' => '1.0.3' ), 'UA Android' );
egal( ah_spa_ua_aplicatie( 'Mozilla/5.0 (iPhone) AdelaHaruApp/2.0' ), array( 'platforma' => 'ios', 'versiune' => '2.0' ), 'UA fără paranteză: platforma din restul UA' );
egal( ah_spa_ua_aplicatie( UA_SAFARI ), null, 'Safari nu e aplicația' );
egal( ah_spa_ua_aplicatie( 'AdelaHaruAppX/1.0' ), null, 'alt nume nu e aplicația' );
egal( ah_spa_ua_aplicatie( '' ), null, 'fără UA' );
ca_browser();
verifica( ! ah_spa_e_aplicatie(), 'browserul nu e aplicația' );
egal( ah_spa_platforma(), '', 'browser: fără platformă' );
$_COOKIE['ah_app'] = 'android';
verifica( ah_spa_e_aplicatie(), 'cookie-ul ah_app=android ajunge' );
egal( ah_spa_platforma(), 'android', 'platforma din cookie' );
$_COOKIE['ah_app'] = 'altceva';
verifica( ! ah_spa_e_aplicatie(), 'cookie cu altă valoare: nu' );
ca_aplicatie();
verifica( ah_spa_e_aplicatie(), 'UA aplicației' );
egal( ah_spa_platforma(), 'ios', 'platforma din UA' );
egal( ah_spa_versiune(), '1.2.0', 'versiunea din UA' );
ah_spa_pune_cookie();
egal( $_COOKIE['ah_app'] ?? '', 'ios', 'cookie-ul ah_app se pune la prima cerere' );
ca_aplicatie( UA_ANDROID );
$_COOKIE['ah_app'] = 'ios';
ah_spa_pune_cookie();
egal( $_COOKIE['ah_app'], 'android', 'cookie-ul se reînnoiește când se schimbă platforma' );

/* ---------- 2. lista permisă și redirecționarea ---------- */
$sp = 'https://www.adelaharu.ro/spatiul-meu/';
$caz = array(
	'/spatiul-meu/'                                  => '',
	'/spatiul-meu'                                   => '',
	'/spatiul-meu/?cont=YW5h.abc123'                 => '',
	'/spatiul-meu/?mergi=sala&comanda=1&curs=x'      => '',
	'/Spatiul-Meu/#ciclu'                            => '',
	'/sala-de-curs/?comanda=1&cheie=wc_order_x'      => '',
	'/cartile-mele/?comanda=2&cheie=wc_order_y'      => '',
	'/politica-de-confidentialitate/'                => '',
	'/termeni-si-conditii/'                          => '',
	'/politica-de-cookie-uri/'                       => '',
	'/wp-content/uploads/2026/11/x.jpg'              => '',
	'/?ah_med_api=audio&id=5'                        => '',
	'/?ciclul-ei'                                    => '',
	'/?ah_sc_ics=14770&s=abc123'                     => '',  // sala (44): calendarul „Ritmul meu”
	'/?ah_sc_ics_r=14770&s=abc123'                   => '',  // sala (44): calendarul „Repetă o practică”
	'/?ahp=14770-0123456789-3'                       => '',  // linkul scurt din SMS
	'/?ah_sc_ritm_stop=14770&s=abc123'               => '',  // oprirea mementourilor din email
	'/?ah_sc_repeta_stop=14770&s=abc123'             => '',
	'/?ahpx=1'                                       => $sp,
	'/?s=abc123'                                     => $sp,
	'/product/x/?ah_sc_ics=14770&s=abc'              => $sp, // cheia sălii doar pe prima pagină
	'/'                                              => $sp,
	'/cursuri-online/'                               => $sp,
	'/product/magia-ciclului-feminin/'               => $sp,
	'/checkout/order-pay/15/?pay_for_order=true'     => $sp,
	'/cart/'                                         => $sp,
	'/card-cadou/'                                   => $sp,
	'/recomanda/'                                    => $sp,
	'/black-friday/'                                 => $sp,
	'/practici-de-respiratie-constienta-pentru-femei-ocupate/' => $sp,
	'/spatiul-meu-fals/'                             => $sp,
	'/spatiul-meu/?add-to-cart=328'                  => $sp,
	'/sala-de-curs/?ah_bf=pachet'                    => $sp,
	'/spatiul-meu/?mergi=abonament&tip=it'           => $sp . '#abonamente',
	'/spatiul-meu/?mergi=abonament&comanda=3&tip=hb' => $sp . '#abonamente',
	'/meditatiile-mele/'                             => '',  // devreme: îl lăsăm întâi lui 235 (intrarea automată)
);
ca_aplicatie();
foreach ( $caz as $uri => $dest ) {
	egal( redirect_spre( $uri, true ), $dest, 'aplicație: ' . $uri );
}
egal( redirect_spre( '/meditatiile-mele/?it=portal', false ), $sp . '#meditatii', 'meditatiile-mele cu parametri (portal Stripe) → Inner Temple din cont' );
egal( ah_spa_destinatie( '/app/intra?cont=YW5h.abc123' ), $sp . '?cont=YW5h.abc123', '/app/intra în aplicație păstrează linkul personal' );
egal( ah_spa_destinatie( '/app/intra?cont=<script>' ), $sp, '/app/intra cu cont stricat: doar contul' );
$_COOKIE['ah_app'] = 'ios';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Mobile'; // UA fără marcă, dar cookie-ul aplicației
egal( redirect_spre( '/product/x/' ), $sp, 'redirecționare și doar cu cookie-ul ah_app' );
ca_browser();
foreach ( $caz as $uri => $dest ) {
	egal( redirect_spre( $uri, true ) . redirect_spre( $uri, false ), '', 'browser: nicio redirecționare pentru ' . $uri );
}
// portalurile Stripe prin REST
$req = new WP_REST_Request( array(), null, array(), '/ah-hb/v1/portal' );
egal( ah_test_filtru( 'rest_pre_dispatch', null, null, $req ), null, 'browser: portalul HB merge ca înainte' );
ca_aplicatie();
$r = ah_test_filtru( 'rest_pre_dispatch', null, null, $req );
verifica( $r instanceof WP_REST_Response && 302 === $r->get_status() && $r->headers['Location'] === $sp . '#abonamente', 'aplicație: portalul HB se întoarce la abonamente' );
$r = ah_test_filtru( 'rest_pre_dispatch', null, null, new WP_REST_Request( array(), null, array(), '/ah-it/v1/portal' ) );
verifica( $r instanceof WP_REST_Response && 302 === $r->get_status(), 'aplicație: portalul Inner Temple blocat' );
egal( ah_test_filtru( 'rest_pre_dispatch', null, null, new WP_REST_Request( array(), null, array(), '/ah-sp/v1/ciclu-date' ) ), null, 'aplicație: celelalte rute REST merg' );

/* ---------- 3. datele contului, curățate la sursă ---------- */
$D = json_decode( file_get_contents( __DIR__ . '/date/spatiul-meu.json' ), true );
$C = ah_spa_curata_date( $D );
egal( $C['comenzi'], array(), 'fără comenzi' );
egal( $C['inchise'], array(), 'fără cursuri de cumpărat' );
egal( array_column( $C['cursuri'], 'stare' ), array( 'activ', 'activ' ), 'fără cursuri încheiate' );
egal( array_column( $C['med_toate'], 'id' ), array( 501, 502, 503, 504 ), 'doar meditațiile pe care le poate asculta' );
egal( implode( '', array_column( $C['med_toate'], 'pret' ) ) . implode( '', array_column( $C['med_toate'], 'cumpara' ) ), '', 'fără prețuri și linkuri de cumpărare' );
egal( array_column( $C['abonamente'], 'link' ), array( '', '' ), 'abonamente fără „Gestionează”' );
egal( $C['abonamente'][0]['detaliu'], 'Activ până pe 18 decembrie 2026', 'abonamentul: doar starea' );
egal( $C['abonamente'][1]['detaliu'], 'Încheiat pe 2 noiembrie 2026', 'abonamentul încheiat rămâne' );
egal( wp_json_encode( $C['ciclu']['produse'] ), '{}', 'produsele fazelor: obiect gol (JS citește P[faza])' );
verifica( 4 === count( $C['ciclu']['faze'] ), 'textele fazelor rămân' );
egal( array_column( $C['podcast']['episoade'], 'mai' ), array( null, null, null ), 'podcast fără „Mai departe: <curs>”' );
egal( $C['podcast']['spotify'], $D['podcast']['spotify'], 'podcastul (gratuit) rămâne' );
foreach ( array( 'recomanda', 'cadou', 'cursuri', 'yin' ) as $k ) {
	egal( $C['linkuri'][ $k ], '', 'linkul ' . $k . ' golit' );
}
egal( $C['linkuri']['site'], $D['linkuri']['site'], 'linkul site rămâne (din el se face linkul pentru partener)' );
$palink = $C['linkuri']['site'] . '?ciclul-ei#d=eyJ2IjoxfQ'; // CI.paLink din 235: D.linkuri.site + '?ciclul-ei#d=' + …
verifica( 0 === strpos( $palink, 'https://' ), 'linkul pentru partener e o adresă întreagă (https://…)' );
egal( ah_spa_destinatie( $palink ), '', 'linkul pentru partener se deschide și în aplicație' );
egal( $C['linkuri']['whatsapp'], $D['linkuri']['whatsapp'], 'Ajutor pe WhatsApp rămâne' );
egal( $C['carti'], $D['carti'], 'cărțile rămân' );
egal( $C['dar'], $D['dar'], 'darul Adelei rămâne' );
verifica( true === $C['aplicatie'], 'marcat aplicatie' );
egal( ah_spa_curata_date( array( 'stare' => 'intrare', 'api' => 'x' ) ), array( 'stare' => 'intrare', 'api' => 'x' ), 'ecranul de intrare: neatins' );
$json = wp_json_encode( $C );
foreach ( array( '/product/', '/card-cadou', '/recomanda', 'checkout', 'mergi=abonament', 'cursuri-online', 'lei"', '/yin-nourish-program' ) as $nu ) {
	verifica( false === strpos( str_replace( '\/', '/', $json ), $nu ), 'nimic din „' . $nu . '” în datele din aplicație' );
}
// în ieșirea shortcode-ului, exact cum o scrie 235
$iesire = '<style id="ahsp-css">x</style><div id="ahsp"></div><script>window.AH_SP=' . wp_json_encode( $D ) . ';</script><script>(function(){var a=1;})();</script>';
$cur    = ah_spa_curata_iesire( $iesire );
verifica( 0 === strpos( $cur, '<style id="ahsp-css">x</style><div id="ahsp"></div><script>window.AH_SP=' ), 'începutul ieșirii neatins' );
verifica( '</script><script>(function(){var a=1;})();</script>' === substr( $cur, strpos( $cur, ';</script>' ) + 1 ), 'scriptul paginii neatins' );
preg_match( '~window\.AH_SP=(.*?);</script>~s', $cur, $m );
egal( json_decode( $m[1], true ), json_decode( wp_json_encode( $C ), true ), 'ieșirea poartă datele curățate' );
egal( ah_spa_curata_iesire( $cur ), $cur, 'a doua curățare nu mai schimbă nimic' );
egal( ah_spa_curata_iesire( '<p>altceva</p>' ), '<p>altceva</p>', 'alt HTML: neatins' );
ca_browser();
egal( ah_test_filtru( 'do_shortcode_tag', $iesire, 'ah_spatiul_meu' ), $iesire, 'browser: Spațiul meu exact ca înainte' );
ca_aplicatie();
egal( ah_test_filtru( 'do_shortcode_tag', $iesire, 'ah_spatiul_meu' ), $cur, 'aplicație: filtrul curăță datele' );
egal( ah_test_filtru( 'do_shortcode_tag', $iesire, 'alt_shortcode' ), $iesire, 'aplicație: alte shortcode-uri neatinse' );

/* ---------- 4. pagina: stilul și puntea doar în aplicație ---------- */
$GLOBALS['ah_test_e_pagina'] = true;
$GLOBALS['ah_test_email']    = 'ana@exemplu.ro';
$_SERVER['REQUEST_URI']      = '/spatiul-meu/';
ca_browser();
egal( ah_test_actiune( 'wp_head' ), '', 'browser: nimic în <head>' );
egal( ah_test_filtru( 'body_class', array( 'ahsp-pagina' ) ), array( 'ahsp-pagina' ), 'browser: clasele corpului neatinse' );
ca_aplicatie();
$cap = ah_test_actiune( 'wp_head' );
verifica( false !== strpos( $cap, '<link rel="stylesheet" id="ahspa-css" href="https://www.adelaharu.ro/wp-json/ah-spa/v1/punte?f=css&amp;v=' . ah_spa_punte_ver() . '">' ), 'aplicație: stilul punții dintr-un fișier cu versiune' );
verifica( false !== strpos( $cap, '<script id="ahspa-js" src="https://www.adelaharu.ro/wp-json/ah-spa/v1/punte?f=js&amp;v=' . ah_spa_punte_ver() . '" defer></script>' ), 'aplicație: puntea dintr-un fișier cu versiune, defer' );
verifica( false !== strpos( $cap, '<script id="ahspa-cfg">window.AH_SPA=' ) && false === strpos( $cap, 'window.AHApp=' ), 'aplicație: în pagină doar configurația și partea devreme (fără puntea întreagă)' );
verifica( strlen( $cap ) < 6000, 'aplicație: <head>-ul punții e mic (' . strlen( $cap ) . ' octeți)' );
egal( substr_count( $cap, 'rel="preload"' ), 4, 'aplicație: fonturile primului ecran preîncărcate (Figtree, Gilda; latin și latin-ext)' );
verifica( false !== strpos( $cap, '/wp-content/uploads/elementor/google-fonts/fonts/figtree-xms-huzqdcfdgfmm4s9dq.woff2" as="font" type="font/woff2" crossorigin>' ), 'aplicație: preload Figtree latin, crossorigin' );
verifica( false === strpos( $cap, 'ana@exemplu.ro' ), 'puntea nu tipărește adresa de email' );
verifica( 0 === strpos( ah_spa_punte_ver(), '2.2.0-' ) && false === strpos( ah_spa_js() . ah_spa_css_tot(), 'ana@exemplu.ro' ), 'fișierele punții: aceleași pentru toate (fără date personale)' );
verifica( false !== strpos( ah_spa_css_tot(), '#ahsp a[href="https://www.adelaharu.ro/"]' ), 'aplicație: „Înapoi pe site” ascuns din stil' );
preg_match( '~window\.AH_SPA=(\{.*?\});\(function~s', $cap, $m );
$cfg = json_decode( $m[1] ?? 'null', true );
egal( $cfg['zona'] ?? '', 'sp', 'zona Spațiul meu' );
egal( $cfg['cine'] ?? '', ah_spa_cine( 'ana@exemplu.ro' ), 'amprenta adresei' );
egal( $cfg['platforma'] ?? '', 'ios', 'platforma în configurație' );
verifica( in_array( '/spatiul-meu/', $cfg['permise'] ?? array(), true ), 'lista permisă în configurație' );
verifica( in_array( 'ahspa-app', ah_test_filtru( 'body_class', array() ), true ), 'aplicație: clasa ahspa-app' );
$GLOBALS['ah_test_e_pagina'] = false;
$_SERVER['REQUEST_URI']      = '/sala-de-curs/?comanda=1&cheie=x';
verifica( false !== strpos( ah_test_actiune( 'wp_head' ), '"zona":"sala"' ), 'aplicație: puntea și în sala de curs' );

foreach ( array( '/politica-de-confidentialitate/', '/termeni-si-conditii/', '/politica-de-cookie-uri/?x=1' ) as $pol ) {
	$_SERVER['REQUEST_URI'] = $pol;
	egal( ah_spa_zona(), 'politici', 'zona politici: ' . $pol );
	$cap = ah_test_actiune( 'wp_head' );
	verifica( false !== strpos( $cap, '"zona":"politici"' ) && false !== strpos( $cap, 'id="ahspa-css"' ) && false !== strpos( ah_spa_css(), 'html.ahspa-z-politici .elementor-location-header' ) && false !== strpos( ah_spa_css(), '#ah-bf-banda' ), 'aplicație: pe ' . $pol . ' stilul ascunde antetul, subsolul și Black Friday' );
	verifica( in_array( 'ahspa-politici', ah_test_filtru( 'body_class', array() ), true ), 'aplicație: clasa ahspa-politici pe ' . $pol );
}
ca_browser();
$_SERVER['REQUEST_URI'] = '/termeni-si-conditii/';
egal( ah_test_actiune( 'wp_head' ), '', 'browser: politicile neatinse' );
ca_aplicatie();
$_SERVER['REQUEST_URI'] = '/despre-adela/';
egal( ah_spa_zona(), '', 'altă pagină: fără zonă' );
verifica( false !== strpos( ah_spa_css(), '#ahs-urmator,#ah-dar7-curs{display:none!important}' ), 'sala: „Mai departe” (44) și „Vreau să continui” (214) ascunse' );

/* ---------- 5. semnăturile linkurilor de descărcare ---------- */
$acum = 1795000000;
list( $url, $exp ) = ah_spa_link_fisier( 501, 'ana@exemplu.ro', $acum );
$x = q( $url );
verifica( 0 === strpos( $url, 'https://www.adelaharu.ro/wp-json/ah-spa/v1/fisier?' ), 'linkul e pe ruta fisier' );
egal( (int) $x['exp'], $acum + 15 * 60, 'expiră în 15 minute' );
egal( $exp, $acum + 15 * 60, 'expira întors' );
verifica( false === strpos( $url, 'ana' ) && false === strpos( $url, rawurlencode( 'ana@exemplu.ro' ) ), 'adresa nu apare în link' );
verifica( ah_spa_link_valid( 501, $x['exp'], $x['c'], $x['s'], $acum ), 'linkul bun trece' );
verifica( ah_spa_link_valid( 501, $x['exp'], $x['c'], $x['s'], $acum + 14 * 60 ), 'trece și după 14 minute' );
verifica( ! ah_spa_link_valid( 501, $x['exp'], $x['c'], $x['s'], $acum + 16 * 60 ), 'expirat după 15 minute' );
verifica( ! ah_spa_link_valid( 502, $x['exp'], $x['c'], $x['s'], $acum ), 'altă meditație: nu' );
verifica( ! ah_spa_link_valid( 501, $x['exp'] + 60, $x['c'], $x['s'], $acum ), 'expirarea schimbată: nu' );
verifica( ! ah_spa_link_valid( 501, $x['exp'], ah_spa_cine( 'alta@exemplu.ro' ), $x['s'], $acum ), 'altă adresă: nu' );
verifica( ! ah_spa_link_valid( 501, $x['exp'], $x['c'], str_repeat( '0', 32 ), $acum ), 'semnătură falsă: nu' );
$departe = $acum + 3600;
verifica( ! ah_spa_link_valid( 501, $departe, $x['c'], ah_spa_semn( 501, $departe, $x['c'] ), $acum ), 'expirare prea departe (chiar semnată): nu' );
verifica( ! ah_spa_link_valid( 0, $x['exp'], $x['c'], $x['s'], $acum ), 'id 0: nu' );
egal( ah_spa_cine( 'Ana@Exemplu.ro ' ), ah_spa_cine( 'ana@exemplu.ro' ), 'amprenta nu ține cont de litere mari/spații' );
// Range
egal( ah_spa_interval( '', 1000 ), false, 'fără Range: tot fișierul' );
egal( ah_spa_interval( 'bytes=0-', 1000 ), array( 0, 999 ), 'Range 0-' );
egal( ah_spa_interval( 'bytes=100-199', 1000 ), array( 100, 199 ), 'Range 100-199' );
egal( ah_spa_interval( 'bytes=900-5000', 1000 ), array( 900, 999 ), 'Range peste capăt: tăiat' );
egal( ah_spa_interval( 'bytes=-100', 1000 ), array( 900, 999 ), 'ultimii 100 de octeți' );
egal( ah_spa_interval( 'bytes=1000-', 1000 ), null, 'început după capăt: 416' );
egal( ah_spa_interval( 'bytes=500-100', 1000 ), null, 'interval întors: 416' );
egal( ah_spa_interval( 'bytes=0-1,5-9', 1000 ), false, 'mai multe intervale: tot fișierul' );
egal( ah_spa_ext( '/var/www/up/ah-meditatii/m501-abc-inima.M4A' ), 'm4a', 'extensia din cale' );
egal( ah_spa_ext( 'https://cdn.exemplu.ro/a.mp3?x=1' ), 'mp3', 'extensia din URL' );
egal( ah_spa_ext( '/tmp/fara' ), 'mp3', 'fără extensie: mp3' );

/* ---------- 6. descarca: accesul prin funcțiile fragmentului 51 ---------- */
$GLOBALS['ah_test_posts'] = array(
	501 => array( 'ID' => 501, 'post_type' => 'ah_meditatie', 'post_status' => 'publish', 'post_title' => 'Respirația inimii', 'poza' => 'https://www.adelaharu.ro/wp-content/uploads/m1.jpg' ),
	505 => array( 'ID' => 505, 'post_type' => 'ah_meditatie', 'post_status' => 'publish', 'post_title' => 'Manifestarea intențiilor', 'poza' => '' ),
	506 => array( 'ID' => 506, 'post_type' => 'ah_meditatie', 'post_status' => 'draft', 'post_title' => 'Ciornă', 'poza' => '' ),
	700 => array( 'ID' => 700, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Pagină', 'poza' => '' ),
);
function ah_med_acces( $e ) { return array( 'are' => true, 'pana' => time() + 86400, 'produse' => array() ); }
function ah_med_poate( $id, $acc ) { return is_array( $acc ) && in_array( (int) $id, array( 501 ), true ); }
function ah_med_m( $id, $k ) { return 'audio' === $k ? ( 501 === (int) $id ? '9001' : '' ) : ''; }
function ah_med_durata( $id ) { return 960; }
function ah_med_cale( $src, $id = 0 ) { return '/srv/up/ah-meditatii/m' . $id . '-abc-inima.m4a'; }
egal( ah_spa_poate_descarca( 501, 'ana@exemplu.ro' ), true, 'meditația ei: da' );
egal( ah_spa_poate_descarca( 505, 'ana@exemplu.ro' ), 'acces', 'meditație închisă: acces' );
egal( ah_spa_poate_descarca( 506, 'ana@exemplu.ro' ), 'lipsa', 'ciornă: lipsa' );
egal( ah_spa_poate_descarca( 700, 'ana@exemplu.ro' ), 'lipsa', 'altă pagină: lipsa' );
egal( ah_spa_poate_descarca( 999, 'ana@exemplu.ro' ), 'lipsa', 'id necunoscut: lipsa' );
$GLOBALS['ah_test_email'] = '';
egal( ah_spa_r_descarca( new WP_REST_Request( array(), null, array( 'id' => 501 ) ) )->get_status(), 403, 'descarca fără cont: 403' );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
$r = ah_spa_r_descarca( new WP_REST_Request( array(), null, array( 'id' => 501 ) ) );
$d = $r->get_data();
egal( $r->get_status(), 200, 'descarca: 200' );
egal( array( $d['titlu'], $d['durata'], $d['ext'], $d['imagine'] ), array( 'Respirația inimii', 960, 'm4a', 'https://www.adelaharu.ro/wp-content/uploads/m1.jpg' ), 'descarca: titlu, durată, extensie, imagine' );
$x = q( $d['url'] );
verifica( ah_spa_link_valid( 501, $x['exp'], $x['c'], $x['s'] ), 'descarca: linkul întors e valid' );
egal( $r->headers['Cache-Control'] ?? '', 'no-store, private, max-age=0', 'descarca: fără cache' );
verifica( in_array( 'litespeed_control_set_nocache', $GLOBALS['ah_test_jurnal_do'], true ), 'LiteSpeed: fără cache' );
egal( ah_spa_r_descarca( new WP_REST_Request( array(), null, array( 'id' => 505 ) ) )->get_status(), 403, 'descarca meditație închisă: 403' );
egal( ah_spa_r_descarca( new WP_REST_Request( array(), null, array( 'id' => 506 ) ) )->get_status(), 404, 'descarca ciornă: 404' );
egal( ah_spa_r_fisier( new WP_REST_Request( array(), null, array( 'id' => 501, 'exp' => $x['exp'], 'c' => $x['c'], 's' => 'gresit' ) ) )->get_status(), 403, 'fisier cu semnătură greșită: 403' );
egal( ah_spa_r_fisier( new WP_REST_Request( array(), null, array( 'id' => 505, 'exp' => $x['exp'], 'c' => $x['c'], 's' => $x['s'] ) ) )->get_status(), 403, 'fisier: semnătura altei meditații: 403' );

/* ---------- 7. push: validarea și tabelul ---------- */
$fcm = 'dGVzdC10b2tlbi1mY20:APA91bH' . str_repeat( 'x_Y-z9', 25 );
$v   = ah_spa_valideaza_push( array( 'token' => $fcm, 'platforma' => 'iOS', 'versiune' => '1.2.0', 'disp' => 'ABCD-1234/../x' ) );
verifica( $v['ok'], 'token FCM bun' );
egal( $v['date']['platforma'], 'ios', 'platforma cu litere mici' );
egal( $v['date']['disp'], 'ABCD-1234x', 'id-ul telefonului curățat' );
egal( ah_spa_valideaza_push( array( 'token' => str_repeat( 'ab', 32 ), 'platforma' => 'ios' ) )['motiv'], 'apns', 'tokenul APNs brut e refuzat clar' );
egal( ah_spa_valideaza_push( array( 'token' => 'scurt', 'platforma' => 'ios' ) )['motiv'], 'token', 'token prea scurt' );
egal( ah_spa_valideaza_push( array( 'token' => str_repeat( 'a', 40 ) . '<script>', 'platforma' => 'ios' ) )['motiv'], 'token', 'token cu caractere greșite' );
egal( ah_spa_valideaza_push( array( 'token' => str_repeat( 'a', 5000 ), 'platforma' => 'ios' ) )['motiv'], 'token', 'token prea lung' );
egal( ah_spa_valideaza_push( array( 'token' => array( 'x' ), 'platforma' => 'ios' ) )['motiv'], 'token', 'token care nu e text' );
egal( ah_spa_valideaza_push( array( 'token' => $fcm, 'platforma' => 'windows' ) )['motiv'], 'platforma', 'platformă necunoscută' );
egal( ah_spa_valideaza_push( array( 'token' => $fcm, 'platforma' => 'android', 'versiune' => '1.0; DROP' ) )['date']['versiune'], '', 'versiune stricată: golită' );
egal( ah_spa_valideaza_push( null )['motiv'], 'token', 'corp gol' );
global $wpdb;
$H = array( 'X-AH-App' => '1' );
$GLOBALS['ah_test_email'] = '';
egal( ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm, 'platforma' => 'ios' ) ) )->get_status(), 403, 'push fără cont: 403' );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
egal( ah_spa_r_push( new WP_REST_Request( array(), array( 'token' => $fcm, 'platforma' => 'ios' ) ) )->get_status(), 403, 'push fără antetul aplicației: 403' );
egal( ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => 'x', 'platforma' => 'ios' ) ) )->get_status(), 400, 'push cu token greșit: 400' );
egal( ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm, 'platforma' => 'ios', 'disp' => 'tel-1' ) ) )->get_status(), 200, 'push bun: 200' );
$rand = $wpdb->get_row( "SELECT * FROM wp_ah_spa_push WHERE token_h = '" . hash( 'sha256', $fcm ) . "'" );
egal( array( $rand['email'], $rand['token'], $rand['disp'] ), array( 'ana@exemplu.ro', $fcm, 'tel-1' ), 'tokenul e legat de adresă' );
ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm, 'platforma' => 'ios', 'disp' => 'tel-1' ) ) );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spa_push' ), 1, 'același token: un singur rând' );
$GLOBALS['ah_test_email'] = 'maria@exemplu.ro';
ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm, 'platforma' => 'ios', 'disp' => 'tel-1' ) ) );
egal( $wpdb->get_var( 'SELECT email FROM wp_ah_spa_push' ), 'maria@exemplu.ro', 'alt cont pe același telefon: tokenul trece la noul cont' );
ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm . 'NOU', 'platforma' => 'ios', 'disp' => 'tel-1' ) ) );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE disp = 'tel-1'" ), 1, 'același telefon, token nou: cel vechi pleacă' );
// „disp” vine de la telefon: o altă adresă care trimite același disp NU scoate tokenurile Mariei
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm . 'ANA', 'platforma' => 'ios', 'disp' => 'tel-1' ) ) );
egal( $wpdb->get_col_test( "SELECT email FROM wp_ah_spa_push WHERE disp = 'tel-1' ORDER BY email" ), array( 'ana@exemplu.ro', 'maria@exemplu.ro' ), 'același disp de la altă adresă: rândul Mariei rămâne (AND email)' );
$wpdb->query( "DELETE FROM wp_ah_spa_push WHERE disp = 'tel-1'" );
for ( $i = 0; $i < 12; $i++ ) {
	ah_spa_push_salveaza( 'ana@exemplu.ro', array( 'token' => $fcm . 'T' . $i, 'platforma' => 'android', 'versiune' => '', 'disp' => 'tel-x' . $i ) );
}
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE email = 'ana@exemplu.ro'" ), AH_SPA_TOKENURI_MAX, 'cel mult 10 telefoane pe adresă' );
egal( ah_spa_r_push_sterge( new WP_REST_Request( array(), array( 'token' => $fcm . 'T11' ) ) )->get_status(), 403, 'DELETE fără antet: 403' );
$GLOBALS['ah_test_email'] = ''; // după ieșirea din cont
egal( ah_spa_r_push_sterge( new WP_REST_Request( $H, array( 'token' => $fcm . 'T11' ) ) )->get_status(), 200, 'DELETE cu tokenul, și fără cont: 200' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE token = '" . $fcm . "T11'" ), 0, 'tokenul a plecat' );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';

/* ---------- 8. ștergerea contului ---------- */
function ah_sp_ciclu_tabel() { return 'wp_ah_sp_ciclu'; }
function ah_sp_ciclu_cheie( $e ) { return hash_hmac( 'sha256', strtolower( $e ), 'secret|cine' ); }
function ah_sp_ciclu_db() {}
function ah_ciclu_tabel() { return 'wp_ah_ciclu'; }
foreach ( array( 'ana@exemplu.ro', 'ioana@exemplu.ro' ) as $e ) {
	$wpdb->insert( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $e ), 'date' => 'criptat', 'acord' => '2026-11-10', 'actualizat' => '2026-11-10' ) );
	$wpdb->insert( 'wp_ah_ciclu', array( 'email' => $e, 'token' => 't' . md5( $e ), 'start' => '2026-11-01' ) );
	ah_spa_push_salveaza( $e, array( 'token' => $fcm . md5( $e ), 'platforma' => 'ios', 'versiune' => '', 'disp' => 'd' . md5( $e ) ) );
	update_option( 'ah_med_d_' . md5( $e ), array( 'x' => 1 ) );
	update_option( 'ah_med_min_' . md5( $e ), 40 );
	update_option( 'ah_med_asc_' . md5( $e ), array( 501 => 2 ) );
}
update_option( 'ah_sp_conturi', array( 'ana@exemplu.ro' => array( 'cand' => 'x' ), 'ioana@exemplu.ro' => array( 'cand' => 'y' ) ) );
update_option( 'ah_sp_vizite', array( md5( 'ana@exemplu.ro' ) => array( 'n' => 3 ), md5( 'ioana@exemplu.ro' ) => array( 'n' => 1 ) ) );
update_option( 'ah_test_comenzi', array( 15003 => 'ana@exemplu.ro' ) ); // comenzile (WooCommerce) nu se ating
// mementourile din sala de curs (44) pe comenzile ei: „Ritmul meu” cu SMS, „Repetă o practică”, una oprită deja; și ale Ioanei
function ah_sc_ritm_index( $c, $r ) { $l = (array) get_option( 'ah_sc_ritm_comenzi', array() ); if ( ! empty( $r['activ'] ) ) { $l[ $c->get_id() ] = 1; } else { unset( $l[ $c->get_id() ] ); } update_option( 'ah_sc_ritm_comenzi', $l ); }
function ah_sc_repeta_index( $c, $r ) { $l = (array) get_option( 'ah_sc_repeta_comenzi', array() ); if ( ! empty( $r['activ'] ) && ( ! empty( $r['email'] ) || ! empty( $r['sms'] ) ) ) { $l[ $c->get_id() ] = 1; } else { unset( $l[ $c->get_id() ] ); } update_option( 'ah_sc_repeta_comenzi', $l ); }
$ritm_ana = array( 'start' => '2026-11-02', 'ora' => '08:00', 'email' => true, 'sms' => true, 'telefon' => '0742 000 111', 'activ' => true, 'curs' => 'respira', 'pas' => 7, 'trimise' => array( 1 ) );
$GLOBALS['ah_test_wc'] = array(
	new Ah_Test_Comanda( 15001, 'Ana@Exemplu.ro', array( '_ah_sc_ritm' => $ritm_ana, '_ah_sc_repeta' => array( 'lectie' => 2, 'start' => '2026-11-10', 'ora' => '20:00', 'email' => true, 'sms' => false, 'telefon' => '', 'activ' => true ) ) ),
	new Ah_Test_Comanda( 15003, 'ana@exemplu.ro', array( '_ah_sc_ritm' => array( 'email' => true, 'sms' => false, 'telefon' => '', 'activ' => false ) ) ),
	new Ah_Test_Comanda( 15004, 'ioana@exemplu.ro', array( '_ah_sc_ritm' => $ritm_ana ) ),
);
update_option( 'ah_sc_ritm_comenzi', array( 15001 => 1, 15004 => 1 ) );
update_option( 'ah_sc_repeta_comenzi', array( 15001 => 1 ) );
update_option( 'ah_respira_inscrieri', array( 'ana@exemplu.ro' => array( 'prenume' => 'Ana', 'tara' => 'RO', 'whatsapp' => '0742000111', 'zile' => array( 1, 2, 3 ) ), 'ioana@exemplu.ro' => array( 'prenume' => 'Ioana', 'zile' => array( 2 ) ) ) );
$_COOKIE['ah_sp'] = 'x'; $_COOKIE['ah_med'] = 'y'; $_COOKIE['ah_ct_15003'] = 'z'; $_COOKIE['ah_sc15002'] = 'w'; $_COOKIE['alt_cookie'] = 'rămâne';
$GLOBALS['ah_test_mail'] = array();
egal( ah_spa_r_sterge_cont( new WP_REST_Request( $H, array() ) )->get_status(), 400, 'fără confirmare: 400' );
egal( ah_spa_r_sterge_cont( new WP_REST_Request( $H, array( 'confirmare' => 'true' ) ) )->get_status(), 400, 'confirmarea trebuie să fie true' );
egal( ah_spa_r_sterge_cont( new WP_REST_Request( array(), array( 'confirmare' => true ) ) )->get_status(), 400, 'fără antetul aplicației: 400' );
$GLOBALS['ah_test_email'] = '';
egal( ah_spa_r_sterge_cont( new WP_REST_Request( $H, array( 'confirmare' => true ) ) )->get_status(), 403, 'fără cont: 403' );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_sp_ciclu" ), 2, 'nimic șters înainte de confirmare' );
$r = ah_spa_r_sterge_cont( new WP_REST_Request( $H, array( 'confirmare' => true ) ) );
egal( $r->get_status(), 200, 'ștergerea: 200' );
egal( $r->headers['Cache-Control'] ?? '', 'no-store, private, max-age=0', 'ștergerea: fără cache' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_sp_ciclu WHERE cheie = '" . ah_sp_ciclu_cheie( 'ana@exemplu.ro' ) . "'" ), 0, 'ciclul din cont: șters' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_ciclu WHERE email = 'ana@exemplu.ro'" ), 0, 'emailurile fazelor: șterse' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE email = 'ana@exemplu.ro'" ), 0, 'tokenurile push: șterse' );
verifica( ! isset( get_option( 'ah_sp_conturi' )['ana@exemplu.ro'] ), 'intrarea din ah_sp_conturi: ștearsă' );
verifica( ! isset( get_option( 'ah_sp_vizite' )[ md5( 'ana@exemplu.ro' ) ] ), 'vizitele: șterse' );
verifica( false === get_option( 'ah_med_d_' . md5( 'ana@exemplu.ro' ) ) && false === get_option( 'ah_med_min_' . md5( 'ana@exemplu.ro' ) ) && false === get_option( 'ah_med_asc_' . md5( 'ana@exemplu.ro' ) ), 'telefoanele și ascultările din Inner Temple: șterse' );
verifica( ! isset( $_COOKIE['ah_sp'] ) && ! isset( $_COOKIE['ah_med'] ) && ! isset( $_COOKIE['ah_ct_15003'] ) && ! isset( $_COOKIE['ah_sc15002'] ), 'cookie-urile contului: șterse' );
egal( $_COOKIE['alt_cookie'] ?? '', 'rămâne', 'alte cookie-uri: neatinse' );
// ale altcuiva: neatinse
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_sp_ciclu" ), 1, 'ciclul altei cliente rămâne' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_ciclu WHERE email = 'ioana@exemplu.ro'" ), 1, 'emailurile fazelor altei cliente rămân' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE email = 'ioana@exemplu.ro'" ), 1, 'tokenul altei cliente rămâne' );
verifica( isset( get_option( 'ah_sp_conturi' )['ioana@exemplu.ro'] ) && false !== get_option( 'ah_med_d_' . md5( 'ioana@exemplu.ro' ) ), 'datele altei cliente rămân' );
egal( get_option( 'ah_test_comenzi' ), array( 15003 => 'ana@exemplu.ro' ), 'comenzile rămân' );
list( $c1, $c3, $c4 ) = $GLOBALS['ah_test_wc'];
egal( array( $c1->meta['_ah_sc_ritm']['activ'], $c1->meta['_ah_sc_ritm']['sms'], $c1->meta['_ah_sc_ritm']['telefon'] ), array( false, false, '' ), 'sala: „Ritmul meu” oprit, fără SMS și fără telefon' );
egal( $c1->meta['_ah_sc_ritm']['start'], '2026-11-02', 'sala: restul setării rămâne (comanda nu se schimbă altfel)' );
egal( $c1->meta['_ah_sc_repeta']['activ'], false, 'sala: „Repetă o practică” oprit' );
verifica( ! isset( get_option( 'ah_sc_ritm_comenzi' )[15001] ) && ! isset( get_option( 'ah_sc_repeta_comenzi' )[15001] ), 'sala: comanda scoasă din listele cronului (ah_sc_ritm_index / ah_sc_repeta_index)' );
verifica( 2 === count( $c1->note ) && 1 === $c1->salvari, 'sala: notă pe comandă și o singură salvare' );
verifica( 0 === count( $c3->note ) && 0 === $c3->salvari, 'sala: comanda cu mementourile oprite deja: neatinsă' );
verifica( true === $c4->meta['_ah_sc_ritm']['activ'] && isset( get_option( 'ah_sc_ritm_comenzi' )[15004] ) && 0 === $c4->salvari, 'sala: mementourile altei cliente rămân' );
verifica( ! isset( get_option( 'ah_respira_inscrieri' )['ana@exemplu.ro'] ), 'Respiră-te acasă: înscrierea ei ștearsă' );
verifica( isset( get_option( 'ah_respira_inscrieri' )['ioana@exemplu.ro'] ), 'Respiră-te acasă: înscrierea altei cliente rămâne' );
$corp = $GLOBALS['ah_test_mail'][0][2] ?? '';
verifica( false !== strpos( $corp, '- sala_ritm: 1' ) && false !== strpos( $corp, '- sala_repeta: 1' ) && false !== strpos( $corp, '- respira_inscriere: 1' ) && false !== strpos( $corp, 'mementourile din sala de curs' ), 'emailul către echipă spune de sală și de Respiră-te acasă' );
egal( count( $GLOBALS['ah_test_mail'] ), 1, 'un email către echipă' );
egal( $GLOBALS['ah_test_mail'][0][0] ?? '', 'contact@adelaharu.ro', 'emailul merge la echipă' );
verifica( false !== strpos( $GLOBALS['ah_test_mail'][0][2] ?? '', 'comenzile și facturile' ), 'emailul spune ce rămâne' );
$jur = wp_json_encode( get_option( 'ah_spa_jurnal' ) );
verifica( false === strpos( $jur, 'ana@exemplu.ro' ), 'jurnalul nu păstrează adresa' );

/* ---------- 9. linkurile universale ---------- */
egal( ah_spa_aasa(), array( 'applinks' => array( 'apps' => array(), 'details' => array() ) ), 'AASA fără Team ID: fără detalii' );
egal( ah_spa_assetlinks(), array(), 'assetlinks fără amprente: listă goală' );
$fp1 = implode( ':', str_split( strtoupper( hash( 'sha256', 'cheie1' ) ), 2 ) );
$fp2 = strtolower( implode( ':', str_split( hash( 'sha256', 'cheie2' ), 2 ) ) );
update_option( 'ah_spa_setari', ah_spa_curata_setari( array( 'ios_team' => 'abcde12345', 'android_sha256' => $fp1 . "\n" . $fp2 . ",nu-e-amprenta\n" . $fp1, 'link_ios' => 'https://apps.apple.com/ro/app/adela-haru/id1', 'link_android' => 'https://evil.example.com/x', 'min_ios' => '1.1.0', 'min_android' => 'unu', 'mesaj' => '<b>Actualizeaz-o</b>', 'email_echipa' => 'nu-e-email', 'push_meditatii' => '1' ), ah_spa_setari() ) );
$s = ah_spa_setari();
egal( $s['ios_team'], 'ABCDE12345', 'Team ID cu litere mari' );
egal( $s['link_ios'], 'https://apps.apple.com/ro/app/adela-haru/id1', 'linkul App Store păstrat' );
egal( $s['link_android'], '', 'link Google Play de pe alt domeniu: refuzat' );
egal( array( $s['min_ios'], $s['min_android'] ), array( '1.1.0', '' ), 'versiunile minime validate' );
egal( $s['mesaj'], 'Actualizeaz-o', 'mesajul fără HTML' );
egal( $s['email_echipa'], 'contact@adelaharu.ro', 'email greșit: cel implicit' );
egal( array( $s['push_meditatii'], $s['push_inreg'], $s['push_intalniri'] ), array( 1, 0, 0 ), 'bifele de notificări' );
$a = ah_spa_aasa();
$det = $a['applinks']['details'][0];
egal( $det['appID'], 'ABCDE12345.ro.adelaharu.app', 'AASA appID' );
egal( $det['appIDs'], array( 'ABCDE12345.ro.adelaharu.app' ), 'AASA appIDs' );
egal( $det['paths'], array( '/spatiul-meu', '/spatiul-meu/*', '/app/intra', '/app/intra/*', '/pentru-el', '/pentru-el/*' ), 'AASA căile: Spațiul meu (cu ?cont=), /app/intra și pagina partenerului, ca App Links pe Android' );
egal( $det['components'], array( array( '/' => '/spatiul-meu' ), array( '/' => '/spatiul-meu/*' ), array( '/' => '/app/intra' ), array( '/' => '/app/intra/*' ), array( '/' => '/pentru-el' ), array( '/' => '/pentru-el/*' ) ), 'AASA components' );
egal( wp_json_encode( $a, JSON_UNESCAPED_SLASHES ), '{"applinks":{"apps":[],"details":[{"appID":"ABCDE12345.ro.adelaharu.app","appIDs":["ABCDE12345.ro.adelaharu.app"],"paths":["/spatiul-meu","/spatiul-meu/*","/app/intra","/app/intra/*","/pentru-el","/pentru-el/*"],"components":[{"/":"/spatiul-meu"},{"/":"/spatiul-meu/*"},{"/":"/app/intra"},{"/":"/app/intra/*"},{"/":"/pentru-el"},{"/":"/pentru-el/*"}]}]}}', 'AASA: JSON-ul exact' );
$al = ah_spa_assetlinks();
egal( $al[0]['target']['package_name'], 'ro.adelaharu.app', 'assetlinks: pachetul' );
egal( $al[0]['target']['sha256_cert_fingerprints'], array( $fp1, strtoupper( $fp2 ) ), 'assetlinks: amprentele valide, fără dubluri' );
egal( $al[0]['relation'], array( 'delegate_permission/common.handle_all_urls' ), 'assetlinks: relația' );
$c = ah_spa_r_config()->get_data();
egal( array( $c['versiune_minima']['ios'], $c['magazine']['ios'] ), array( '1.1.0', 'https://apps.apple.com/ro/app/adela-haru/id1' ), 'config: versiunea minimă și magazinul' );

/* ---------- 10. declanșatorii notificărilor ---------- */
$acum = 1795000000;
$inreg = array(
	1 => array( 'url' => 'https://vimeo.com/1', 'publicat' => $acum - 600, 'retras' => 0 ),
	2 => array( 'url' => 'https://vimeo.com/2', 'publicat' => $acum - 23 * 3600 - 1800, 'retras' => 0 ), // dispare în 30 de minute
	3 => array( 'url' => 'https://vimeo.com/3', 'publicat' => $acum - 60, 'retras' => $acum - 30 ),
	4 => array( 'url' => '', 'publicat' => 0 ),
);
egal( ah_spa_inreg_de_anuntat( $inreg, array(), $acum, $acum - 86400 ), array( 1 => $acum - 600 ), 'înregistrări: doar ziua 1 (2 dispare curând, 3 retrasă, 4 goală)' );
egal( ah_spa_inreg_de_anuntat( $inreg, array( 1 => $acum - 600 ), $acum, 0 ), array(), 'înregistrare deja anunțată: nu din nou' );
egal( ah_spa_inreg_de_anuntat( $inreg, array( 1 => $acum - 9999 ), $acum, 0 ), array( 1 => $acum - 600 ), 'republicată: se anunță din nou' );
egal( ah_spa_inreg_de_anuntat( $inreg, array(), $acum, $acum ), array( 1 => $acum - 600 ), 'publicată cu puțin înaintea pornirii fragmentului: da' );
egal( ah_spa_inreg_de_anuntat( $inreg, array(), $acum, $acum + 3600 ), array(), 'publicată înainte de pornirea fragmentului: nu' );
$int = array(
	array( 'id' => 'a', 'start' => $acum + 14 * 60 ),
	array( 'id' => 'b', 'start' => $acum + 40 * 60 ),
	array( 'id' => 'c', 'start' => $acum - 60 ),
	array( 'id' => 'd', 'start' => $acum + 16 * 60 ),
);
egal( array_column( ah_spa_intalniri_de_anuntat( $int, array(), $acum ), 'id' ), array( 'a', 'd' ), 'întâlniri: în următoarele ~15 minute' );
egal( array_column( ah_spa_intalniri_de_anuntat( $int, array( 'a' => 1 ), $acum ), 'id' ), array( 'd' ), 'întâlnire anunțată: nu din nou' );
// lucrarea fără cheia Firebase: jurnal, nimic trimis, lucrarea se încheie
delete_option( 'ah_spa_lucrari' );
update_option( 'ah_spa_jurnal', array() );
$id = ah_spa_lucrare_noua( 'meditatie', array( 'id' => 501 ), 'O meditație nouă în Inner Temple', 'Respirația inimii', $sp . '#meditatii' );
verifica( (bool) wp_next_scheduled( 'ah_spa_lot' ), 'lotul e programat' );
ah_spa_lot();
egal( ah_spa_lucrari(), array(), 'lucrarea s-a încheiat' );
$jt = implode( "\n", array_column( get_option( 'ah_spa_jurnal' ), 1 ) );
verifica( false !== strpos( $jt, 'push OPRIT (fără cheia Firebase)' ), 'fără cheie: doar jurnal' );
verifica( false === strpos( $jt, '@' ), 'jurnalul push fără adrese' );
egal( ah_spa_destinatar( array( 'tip' => 'meditatie', 'param' => array( 'id' => 501 ) ), 'oricine@exemplu.ro' ), true, 'meditație: cine are acces o primește' );
egal( ah_spa_destinatar( array( 'tip' => 'meditatie', 'param' => array( 'id' => 505 ) ), 'oricine@exemplu.ro' ), false, 'meditație închisă: nu' );
function ah_respira_inreg_destinatare( $d ) { return 2 === (int) $d ? array( 'Ioana@Exemplu.ro' => array() ) : array(); }
egal( ah_spa_destinatar( array( 'tip' => 'inreg', 'param' => array( 'zi' => 2 ) ), 'ioana@exemplu.ro' ), true, 'înregistrare: înscrisa în ziua aceea' );
egal( ah_spa_destinatar( array( 'tip' => 'inreg', 'param' => array( 'zi' => 2 ) ), 'ana@exemplu.ro' ), false, 'înregistrare: neînscrisa nu' );
egal( ah_spa_destinatar( array( 'tip' => 'intalnire', 'param' => array( 'zi' => 1 ) ), 'ioana@exemplu.ro' ), false, 'întâlnire: altă zi, nu' );
$m = ah_spa_fcm_mesaj( 'tok', 'Titlu', 'Text', array( 'url' => $sp . '#meditatii', 'n' => 3 ) );
egal( $m['message']['data'], array( 'url' => $sp . '#meditatii', 'n' => '3' ), 'FCM: data doar texte' );
egal( ah_spa_fcm_cont( '{"project_id":"p"}' ), null, 'cont de serviciu incomplet: refuzat' );
$k = openssl_pkey_new( array( 'private_key_bits' => 2048 ) );
openssl_pkey_export( $k, $pem );
$sa  = ah_spa_fcm_cont( wp_json_encode( array( 'project_id' => 'adela', 'client_email' => 'push@adela.iam.gserviceaccount.com', 'private_key' => $pem ) ) );
$jwt = ah_spa_fcm_jwt( $sa, $acum );
$p   = explode( '.', $jwt );
$pub = openssl_pkey_get_details( $k )['key'];
egal( openssl_verify( $p[0] . '.' . $p[1], base64_decode( strtr( $p[2], '-_', '+/' ) ), $pub, OPENSSL_ALGO_SHA256 ), 1, 'JWT-ul FCM e semnat corect (RS256)' );
egal( json_decode( base64_decode( strtr( $p[1], '-_', '+/' ) ), true )['scope'], 'https://www.googleapis.com/auth/firebase.messaging', 'JWT: scope-ul FCM' );

// loturile cu cheie: cursorul după fiecare telefon, bugetul de timp verificat la fiecare telefon, fără dubluri
update_option( 'ah_spa_fcm', wp_json_encode( array( 'project_id' => 'adela', 'client_email' => 'push@adela.iam.gserviceaccount.com', 'private_key' => $pem ) ) );
set_transient( 'ah_spa_fcm_at_' . md5( 'push@adela.iam.gserviceaccount.com' ), 'at-proba', 3000 );
$wpdb->query( 'DELETE FROM wp_ah_spa_push' );
for ( $i = 1; $i <= 30; $i++ ) {
	$wpdb->insert( 'wp_ah_spa_push', array( 'email' => 'c' . $i . '@exemplu.ro', 'token' => 'tok' . $i, 'token_h' => hash( 'sha256', 'tok' . $i ), 'platforma' => 'android', 'creat' => 'x', 'vazut' => 'x' ) );
}
$ids = array_map( 'intval', array_column( $wpdb->get_results( 'SELECT id FROM wp_ah_spa_push ORDER BY id' ), 'id' ) );
delete_option( 'ah_spa_lucrari' );
delete_transient( 'ah_spa_lot_lacat' );
$lid = ah_spa_lucrare_noua( 'meditatie', array( 'id' => 501 ), 'O meditație nouă în Inner Temple', 'Respirația inimii', $sp . '#meditatii' );
$tok = function () { return array_map( function ( $x ) { return json_decode( $x[1]['body'], true )['message']['token']; }, array_values( array_filter( $GLOBALS['ah_test_http'], function ( $x ) { return false !== strpos( $x[0], 'fcm.googleapis.com' ); } ) ) ); };
// 1. cererea se oprește la al 8-lea telefon (timpul PHP): cursorul a rămas la al 7-lea
$GLOBALS['ah_test_http'] = array();
$GLOBALS['ah_test_http_raspuns'] = function () { if ( count( $GLOBALS['ah_test_http'] ) >= 8 ) { throw new RuntimeException( 'oprit' ); } return array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); };
try { ah_spa_lot(); } catch ( RuntimeException $x ) {}
$j = ah_spa_lucrari()[ $lid ] ?? array();
egal( array( (int) ( $j['cursor'] ?? -1 ), (int) ( $j['trimise'] ?? -1 ) ), array( $ids[6], 7 ), 'lot oprit la mijloc: cursorul e la ultimul telefon anunțat' );
egal( $GLOBALS['ah_test_http'][0][1]['timeout'] ?? 0, 3, 'FCM: 3 secunde pe telefon' );
delete_transient( 'ah_spa_lot_lacat' ); // lacătul expiră
// 2. bugetul de timp: FCM încet (60 ms), buget 0,15 s → câteva telefoane, cursorul scris, lotul reprogramat
$GLOBALS['ah_test_http_raspuns'] = function () { usleep( 60000 ); return array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); };
$GLOBALS['ah_test_cron'] = array();
ah_spa_lot( 0.15 );
$j = ah_spa_lucrari()[ $lid ] ?? array();
verifica( isset( $j['cursor'] ) && $j['cursor'] > $ids[6] && $j['cursor'] < $ids[29] && (int) $j['trimise'] === 7 + count( $tok() ) - 8, 'bugetul de timp: oprit între telefoane, cursorul la ultimul anunțat' );
verifica( (bool) wp_next_scheduled( 'ah_spa_lot' ) && false === get_transient( 'ah_spa_lot_lacat' ), 'lotul neterminat: reprogramat, lacătul ridicat' );
// 3. restul
$GLOBALS['ah_test_http_raspuns'] = null;
ah_spa_lot();
$t = $tok();
egal( ah_spa_lucrari(), array(), 'lucrarea cu cheie s-a încheiat' );
egal( count( array_unique( $t ) ), 30, 'fiecare telefon a primit notificarea' );
egal( count( $t ) - count( array_unique( $t ) ), 1, 'cel mult o dublură: doar telefonul la care s-a oprit cererea' );
egal( array_keys( array_filter( array_count_values( $t ), function ( $n ) { return $n > 1; } ) ), array( 'tok8' ), 'dublura e chiar telefonul din clipa opririi' );
verifica( false !== strpos( implode( "\n", array_column( get_option( 'ah_spa_jurnal' ), 1 ) ), 'lucrare gata: meditatie · O meditație nouă în Inner Temple — 30 telefoane' ), 'jurnalul: 30 de telefoane' );
delete_option( 'ah_spa_fcm' );
$GLOBALS['ah_test_http_raspuns'] = null;

/* ---------- 11. JS: puntea are tot ce cere arhitectura ---------- */
$js = ah_spa_js();
foreach ( array( "'appUrlOpen'", "'getLaunchUrl'", "'registration'", "'pushNotificationActionPerformed'", "'localNotificationActionPerformed'", "'requestPermissions'", "'ah_ciclu'", "'ah_descarcate'", "'downloadFile'", 'mediaSession', "'sterge-cont'", "'seekbackward'", "'seekforward'", 'LIBRARY_NO_CLOUD', "'#ahsp-iesi'" ) as $x ) {
	verifica( false !== strpos( $js, $x ), 'puntea conține ' . $x );
}
verifica( false !== strpos( $js, '/*CICLU-INCEPUT*/' ) && false !== strpos( $js, '/*CICLU-SFARSIT*/' ), 'calculul ciclului e delimitat (pentru testul node)' );

/* ---------- 12. contul de recenzie (docs/review-account.md, punctul 9) ---------- */
$GLOBALS['ah_test_cookie_sp'] = array();
$GLOBALS['ah_test_mail_235']  = array();
if ( ! function_exists( 'ah_mail_trimite' ) ) { function ah_mail_trimite( ...$a ) { $GLOBALS['ah_test_mail_235'][] = $a; return true; } }
if ( ! function_exists( 'ah_med_cheie_disp' ) ) { function ah_med_cheie_disp( $e ) { return 'ah_med_d_' . md5( strtolower( trim( $e ) ) ); } }
if ( ! function_exists( 'ah_sc_dispozitive' ) ) { function ah_sc_dispozitive( $c ) { return (array) $c->get_meta( '_ah_sc_disp' ); } }
$REC  = 'recenzie@adelaharu.ro';
$COD  = '482916';
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
$cere = function ( $ruta, $corp, $metoda = 'POST' ) {
	return ah_test_filtru( 'rest_pre_dispatch', null, null, new WP_REST_Request( array( 'Content-Type' => 'application/json' ), $corp, array(), $ruta, $metoda ) );
};
$tr235 = function () { return array_values( array_filter( array_keys( $GLOBALS['ah_test_transients'] ), function ( $k ) { return 0 === strpos( $k, 'ah_sp_ip_' ) || 0 === strpos( $k, 'ah_sp_em_' ) || 0 === strpos( $k, 'ah_sp_cod_' ); } ) ); };
ca_aplicatie();
delete_option( 'ah_spa_recenzie' );
egal( ah_spa_recenzie(), array( 'email' => '', 'hash' => '', 'doar_app' => 1, 'oprit' => 0 ), 'recenzie: implicit oprit, „doar în aplicație” bifat' );
foreach ( array( '/ah-sp/v1/cod' => array( 'email' => $REC ), '/ah-sp/v1/intra' => array( 'email' => $REC, 'cod' => $COD ) ) as $ruta => $corp ) {
	egal( $cere( $ruta, $corp ), null, 'recenzie: opțiunea goală ⇒ ' . $ruta . ' merge la 235' );
}
// validarea codului la salvare
foreach ( array( '123456', '654321', '000000', '999999', '012345', '987654', '12345', '1234567', 'abcdef', '12 34 56', '' ) as $slab ) {
	verifica( '' !== ah_spa_recenzie_cod_slab( $slab ), 'recenzie: codul „' . $slab . '” e refuzat' );
}
egal( ah_spa_recenzie_cod_slab( $COD ), '', 'recenzie: un cod greu de ghicit trece' );
list( $o, $err ) = ah_spa_curata_recenzie( array( 'recenzie_email' => '  Recenzie@AdelaHaru.RO ', 'recenzie_cod' => $COD, 'recenzie_doar_app' => '1' ), ah_spa_recenzie() );
egal( array( $o['email'], $o['doar_app'], $err ), array( $REC, 1, '' ), 'recenzie: adresa salvată normalizată' );
verifica( '' !== $o['hash'] && $o['hash'] !== $COD && password_verify( $COD, $o['hash'] ), 'recenzie: se păstrează doar amprenta codului' );
verifica( false === strpos( serialize( $o ), $COD ), 'recenzie: codul nu apare nicăieri în opțiune' );
list( $o2, $err2 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_cod' => '123456', 'recenzie_doar_app' => '1' ), $o );
egal( array( $o2['hash'], $err2 ), array( $o['hash'], 'sir' ), 'recenzie: un cod refuzat nu schimbă codul de acum' );
list( $o3, $err3 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_cod' => '', 'recenzie_doar_app' => '1' ), $o );
egal( array( $o3['hash'], $err3 ), array( $o['hash'], '' ), 'recenzie: câmpul gol păstrează codul' );
list( $o4, $err4 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => 'nu-e-email', 'recenzie_cod' => '' ), $o );
egal( array( $o4['email'], $o4['doar_app'], $err4 ), array( '', 0, 'email' ), 'recenzie: adresă greșită ⇒ oprit, cu mesaj' );
list( $o5 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_sterge_cod' => '1', 'recenzie_doar_app' => '1' ), $o );
egal( $o5['hash'], '', 'recenzie: „Șterge codul”' );
update_option( 'ah_spa_recenzie', $o );
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) ), null, 'recenzie: fără 235 (ah_sp_seteaza_cookie) filtrul nu face nimic' );
if ( ! function_exists( 'ah_sp_seteaza_cookie' ) ) { function ah_sp_seteaza_cookie( $e ) { $GLOBALS['ah_test_cookie_sp'][] = $e; } }
// altă adresă, altă metodă, altă rută: neatinse
foreach ( array( 'recenzie+1@adelaharu.ro', 'recenzie@adelaharu.com', 'ana@exemplu.ro', '' ) as $alta ) {
	egal( $cere( '/ah-sp/v1/cod', array( 'email' => $alta ) ), null, 'recenzie: altă adresă (' . $alta . ') ⇒ ruta 235' );
	egal( $cere( '/ah-sp/v1/intra', array( 'email' => $alta, 'cod' => $COD ) ), null, 'recenzie: /intra cu altă adresă (' . $alta . ') ⇒ ruta 235' );
}
egal( $cere( '/ah-sp/v1/cod', array( 'email' => array( $REC ) ) ), null, 'recenzie: emailul care nu e text ⇒ ruta 235' );
egal( $cere( '/ah-sp/v1/cod', array( 'email' => $REC ), 'GET' ), null, 'recenzie: GET ⇒ neatins' );
foreach ( array( '/ah-sp/v1/iesi', '/ah-sp/v1/ciclu-date', '/ah-sp/v1/ciclu-sterge', '/ah-spa/v1/push' ) as $ruta ) {
	egal( $cere( $ruta, array( 'email' => $REC, 'cod' => $COD ) ), null, 'recenzie: altă rută (' . $ruta . ') ⇒ neatinsă' );
}
egal( ah_spa_recenzie_filtru( 'deja', null, new WP_REST_Request( array(), array( 'email' => $REC ), array(), '/ah-sp/v1/cod', 'POST' ) ), 'deja', 'recenzie: un răspuns dat deja de alt filtru rămâne' );
// „doar în aplicație”: în browser merge pe ruta normală
ca_browser();
egal( $cere( '/ah-sp/v1/cod', array( 'email' => $REC ) ), null, 'recenzie: doar_app + browser ⇒ /cod din 235' );
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) ), null, 'recenzie: doar_app + browser ⇒ /intra din 235' );
ca_aplicatie();
// /cod: 200 ok, fără email, fără contoarele 235
$GLOBALS['ah_test_mail'] = array();
$r = $cere( '/ah-sp/v1/cod', array( 'email' => '  RECENZIE@adelaharu.ro ' ) );
verifica( $r instanceof WP_REST_Response && 200 === $r->get_status() && array( 'ok' => true ) === $r->get_data(), 'recenzie: /cod ⇒ 200 {"ok":true} (adresa cu majuscule și spații recunoscută)' );
egal( $r->headers['Cache-Control'] ?? '', 'no-store, private, max-age=0', 'recenzie: /cod fără cache' );
egal( array( $GLOBALS['ah_test_mail_235'], $GLOBALS['ah_test_mail'] ), array( array(), array() ), 'recenzie: /cod nu trimite niciun email' );
egal( $tr235(), array(), 'recenzie: contoarele 235 (ah_sp_ip_*, ah_sp_em_*) neatinse' );
for ( $i = 0; $i < 29; $i++ ) { $cere( '/ah-sp/v1/cod', array( 'email' => $REC ) ); }
$r = $cere( '/ah-sp/v1/cod', array( 'email' => $REC ) );
verifica( 429 === $r->get_status() && 'Ai cerut deja câteva coduri. Încearcă din nou peste 15 minute.' === $r->get_data()['mesaj'], 'recenzie: a 31-a cerere de cod pe oră de pe același IP ⇒ 429 (mesajul din 235)' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.4';
egal( $cere( '/ah-sp/v1/cod', array( 'email' => $REC ) )->get_status(), 200, 'recenzie: alt IP ⇒ din nou 200' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
// dispozitivele: ale recenziei și ale altei cliente
update_option( 'ah_med_d_' . md5( $REC ), array( 'tel1' => 1, 'tel2' => 2, 'tel3' => 3 ) );
update_option( 'ah_med_d_' . md5( 'ana@exemplu.ro' ), array( 'tel' => 1 ) );
$cr = new Ah_Test_Comanda( 16001, 'Recenzie@AdelaHaru.ro', array( '_ah_sc_disp' => array( 'a' => 1, 'b' => 2 ) ) );
$cr2 = new Ah_Test_Comanda( 16002, $REC, array() );
$ca = new Ah_Test_Comanda( 16003, 'ana@exemplu.ro', array( '_ah_sc_disp' => array( 'x' => 1 ) ) );
$GLOBALS['ah_test_wc'] = array( $cr, $cr2, $ca );
egal( ah_spa_recenzie_goleste_disp( 'ana@exemplu.ro' ), array( 'meditatii' => 0, 'sala' => 0 ), 'recenzie: golirea dispozitivelor nu face nimic pentru altă adresă' );
// /intra
$GLOBALS['ah_test_cookie_sp'] = array();
$r = $cere( '/ah-sp/v1/intra', array( 'email' => ' Recenzie@adelaharu.ro', 'cod' => $COD ) );
verifica( 200 === $r->get_status() && array( 'ok' => true ) === $r->get_data(), 'recenzie: /intra cu codul bun ⇒ 200 {"ok":true}' );
egal( $GLOBALS['ah_test_cookie_sp'], array( $REC ), 'recenzie: ah_sp_seteaza_cookie chemat o dată, cu adresa normalizată' );
egal( $r->headers['Cache-Control'] ?? '', 'no-store, private, max-age=0', 'recenzie: /intra fără cache' );
egal( get_option( 'ah_med_d_' . md5( $REC ) ), false, 'recenzie: dispozitivele meditațiilor (51) golite' );
verifica( ! isset( $cr->meta['_ah_sc_disp'] ) && 1 === $cr->salvari && 0 === $cr2->salvari, 'recenzie: dispozitivele sălii (44) golite pe comanda ei' );
verifica( array( 'tel' => 1 ) === get_option( 'ah_med_d_' . md5( 'ana@exemplu.ro' ) ) && isset( $ca->meta['_ah_sc_disp'] ) && 0 === $ca->salvari, 'recenzie: dispozitivele altei cliente rămân' );
egal( $tr235(), array(), 'recenzie: /intra nu atinge transientul de cod din 235' );
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => '482 916' ) )->get_status(), 200, 'recenzie: codul cu spații (cum îl scrie telefonul) trece; nu expiră, nu e de unică folosință' );
// greșit, apoi limita de 5, apoi după 15 minute
$GLOBALS['ah_test_cookie_sp'] = array();
$r = $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => '000001' ) );
verifica( 403 === $r->get_status() && array( 'ok' => false, 'mesaj' => 'Codul nu se potrivește. Verifică cifrele.' ) === $r->get_data(), 'recenzie: cod greșit ⇒ 403 (mesajul din 235)' );
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => array( 'x' ) ) )->get_status(), 403, 'recenzie: cod care nu e text ⇒ 403' );
foreach ( array( '135790', '24680', '975310' ) as $g ) { $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $g ) ); }
$r = $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) );
verifica( 429 === $r->get_status() && 'Prea multe încercări. Cere un cod nou.' === $r->get_data()['mesaj'], 'recenzie: a 6-a încercare după 5 greșite ⇒ 429, chiar cu codul bun' );
egal( $GLOBALS['ah_test_cookie_sp'], array(), 'recenzie: niciun cookie cât e blocat' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => '000002' ) )->get_status(), 403, 'recenzie: limita e pe IP (alt IP mai are încercări)' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
$wpdb->query( 'UPDATE wp_ah_spa_incercari SET expira = ' . ( time() - 1 ) ); // au trecut 15 minute
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) )->get_status(), 200, 'recenzie: după 15 minute acceptă din nou' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_incercari WHERE cheie IN ('" . ah_spa_contor_cheie( 'rec_tot' ) . "','" . ah_spa_contor_cheie( 'rec_ip|203.0.113.77' ) . "')" ), 0, 'recenzie: contoarele (IP și total) la zero după o intrare reușită' );
egal( get_transient( 'ah_spa_rec_n' ), false, 'recenzie: fără vechiul contor neatomic (transient)' );
$n0 = $GLOBALS['ah_test_check_pw'] ?? 0;
$cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) );
egal( ( $GLOBALS['ah_test_check_pw'] ?? 0 ) - $n0, 1, 'recenzie: codul se compară cu wp_check_password (timp constant)' );
// „doar în aplicație” debifat: merge și în browser
update_option( 'ah_spa_recenzie', array_merge( $o, array( 'doar_app' => 0 ) ) );
ca_browser();
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) )->get_status(), 200, 'recenzie: doar_app debifat ⇒ merge și pe web' );
update_option( 'ah_spa_recenzie', $o );
ca_aplicatie();
// rafală în paralel: toate cererile își rezervă încercarea înainte de comparare ⇒ cel mult 5 comparații pe IP
$n0 = $GLOBALS['ah_test_check_pw'] ?? 0;
$GLOBALS['ah_test_cookie_sp'] = array();
$coduri = array();
for ( $i = 0; $i < 30; $i++ ) { $coduri[] = $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => sprintf( '%06d', 100000 + $i * 7 ) ) )->get_status(); }
egal( array( ( $GLOBALS['ah_test_check_pw'] ?? 0 ) - $n0, count( array_keys( $coduri, 429 ) ) ), array( 5, 25 ), 'recenzie, rafală de 30 de pe un IP: 5 comparații, 25 × 429' );
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
// plafonul total: 20 de coduri greșite (de pe IP-uri diferite) ⇒ contul de recenzie se oprește, echipa primește email
$GLOBALS['ah_test_mail'] = array();
for ( $i = 0; $i < 20; $i++ ) { $_SERVER['REMOTE_ADDR'] = '192.0.2.' . ( $i + 1 ); $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => '000003' ) ); }
verifica( ah_spa_recenzie()['oprit'] > 0, 'recenzie: după 20 de coduri greșite contul de recenzie e oprit' );
egal( array( count( $GLOBALS['ah_test_mail'] ), $GLOBALS['ah_test_mail'][0][0] ?? '', $GLOBALS['ah_test_mail'][0][1] ?? '' ), array( 1, 'contact@adelaharu.ro', 'Aplicația: contul de recenzie a fost oprit' ), 'recenzie: un singur email către echipă' );
verifica( false === strpos( wp_json_encode( $GLOBALS['ah_test_mail'] ), $COD ), 'recenzie: emailul nu are codul' );
$_SERVER['REMOTE_ADDR'] = '192.0.2.200';
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) ), null, 'recenzie oprită: chiar și codul bun merge la 235 (drumul obișnuit, cu email)' );
egal( $cere( '/ah-sp/v1/cod', array( 'email' => $REC ) ), null, 'recenzie oprită: /cod merge la 235 (codul vine pe email)' );
list( $o6 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_doar_app' => '1', 'recenzie_porneste' => '1' ), ah_spa_recenzie() + array( 'hash' => $o['hash'] ) );
egal( array( $o6['oprit'], $o6['hash'] ), array( 0, $o['hash'] ), 'recenzie: „Pornește din nou” (Setări › Aplicația mobilă) păstrează codul' );
list( $o7 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_doar_app' => '1', 'recenzie_cod' => '590247' ), ah_spa_recenzie() );
egal( $o7['oprit'], 0, 'recenzie: un cod nou o pornește din nou' );
list( $o8 ) = ah_spa_curata_recenzie( array( 'recenzie_email' => $REC, 'recenzie_doar_app' => '1' ), ah_spa_recenzie() );
verifica( $o8['oprit'] > 0, 'recenzie: o salvare fără bifă o lasă oprită' );
update_option( 'ah_spa_recenzie', $o6 );
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
egal( $cere( '/ah-sp/v1/intra', array( 'email' => $REC, 'cod' => $COD ) )->get_status(), 200, 'recenzie pornită din nou: codul fix merge' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
// jurnalul: fără cod, fără adresă, IP trunchiat
$jur = implode( "\n", array_column( (array) get_option( 'ah_spa_jurnal' ), 1 ) );
foreach ( array( 'recenzie: cod cerut', 'recenzie: intrare', 'recenzie: cod greșit', 'recenzie: prea multe încercări' ) as $x ) {
	verifica( false !== strpos( $jur, $x ), 'recenzie: jurnalul are „' . $x . '”' );
}
foreach ( array( $COD, '482 916', '000001', '135790', '24680', '975310', $REC, '203.0.113.77' ) as $x ) {
	verifica( false === strpos( $jur, $x ), 'recenzie: jurnalul nu conține „' . $x . '”' );
}
verifica( false !== strpos( $jur, '203.0.113.0/24' ), 'recenzie: IP-ul trunchiat (/24) în jurnal' );
egal( ah_spa_ip_scurt( '2001:db8:abcd:12:34::1' ), '2001:db8:abcd::/48', 'IPv6 trunchiat la /48' );
egal( ah_spa_ip_scurt( 'nu-e-ip' ), '?', 'IP stricat: ?' );
// ștergerea contului pe adresa de recenzie: emailul echipei are mențiunea
$GLOBALS['ah_test_mail']  = array();
$GLOBALS['ah_test_email'] = $REC;
egal( ah_spa_r_sterge_cont( new WP_REST_Request( $H, array( 'confirmare' => true ) ) )->get_status(), 200, 'recenzie: sterge-cont merge normal' );
egal( $GLOBALS['ah_test_mail'][0][1] ?? '', 'Aplicația: cerere de ștergere a contului (contul de recenzie)', 'recenzie: subiectul emailului către echipă' );
$GLOBALS['ah_test_mail']  = array();
$GLOBALS['ah_test_email'] = 'maria@exemplu.ro';
ah_spa_r_sterge_cont( new WP_REST_Request( $H, array( 'confirmare' => true ) ) );
egal( $GLOBALS['ah_test_mail'][0][1] ?? '', 'Aplicația: cerere de ștergere a contului', 'recenzie: altă adresă, subiectul obișnuit' );
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
$GLOBALS['ah_test_wc']    = array();
delete_option( 'ah_spa_recenzie' );

/* ---------- 13. fără analiză și urmărire în aplicație ---------- */
foreach ( array( array( 'sourcebuster-js', '' ), array( 'wc-order-attribution', '' ), array( 'wc-facebook-signals', '' ), array( 'cmplz-cookiebanner', '' ), array( 'cmplz-general', '' ), array( 'google_gtagjs', '' ), array( 'x-ga', 'https://www.googletagmanager.com/gtag/js?id=G-52BCW9FS1V' ), array( 'ms-clarity', 'https://www.clarity.ms/tag/yvi25enyux' ), array( 'banner', 'https://www.adelaharu.ro/wp-content/uploads/complianz/css/banner-1-optin.css' ), array( 'fb', 'https://connect.facebook.net/en_US/fbevents.js' ) ) as $x ) {
	verifica( ah_spa_e_urmarire( $x[0], $x[1] ), 'urmărire: ' . $x[0] . ' e scos' );
}
foreach ( array( array( 'ahspx-css', '' ), array( 'cmplz-document', 'https://www.adelaharu.ro/wp-content/plugins/complianz-gdpr/assets/css/document.min.css' ), array( 'jquery-core', 'https://www.adelaharu.ro/wp-includes/js/jquery/jquery.min.js' ), array( 'elementor-frontend', 'https://www.adelaharu.ro/wp-content/plugins/elementor/assets/js/frontend.min.js' ), array( 'wc-cart-fragments', '' ) ) as $x ) {
	verifica( ! ah_spa_e_urmarire( $x[0], $x[1] ), 'urmărire: ' . $x[0] . ' rămâne' );
}
$coada = function () {
	$GLOBALS['wp_scripts'] = new Ah_Test_Deps();
	$GLOBALS['wp_styles']  = new Ah_Test_Deps();
	foreach ( array( 'jquery-core' => '/wp-includes/js/jquery/jquery.min.js', 'sourcebuster-js' => '/wp-content/plugins/woocommerce/assets/js/sourcebuster/sourcebuster.min.js', 'wc-order-attribution' => '/wp-content/plugins/woocommerce/assets/js/frontend/order-attribution.min.js', 'wc-facebook-signals' => '/wp-content/plugins/facebook-for-woocommerce/assets/js/facebook-for-woocommerce-signals.js', 'cmplz-cookiebanner' => '/wp-content/plugins/complianz-gdpr/cookiebanner/js/complianz.min.js', 'elementor-frontend' => '/wp-content/plugins/elementor/assets/js/frontend.min.js', 'clarity-x' => 'https://www.clarity.ms/tag/abc' ) as $h => $src ) { $GLOBALS['wp_scripts']->adauga( $h, $src ); }
	foreach ( array( 'astra-theme-css' => '/wp-content/themes/astra/style.css', 'cmplz-general' => '/wp-content/plugins/complianz-gdpr/assets/css/cookieblocker.min.css', 'cmplz-banner-1-optin' => '/wp-content/uploads/complianz/css/banner-1-optin.css', 'cmplz-document' => '/wp-content/plugins/complianz-gdpr/assets/css/document.min.css' ) as $h => $src ) { $GLOBALS['wp_styles']->adauga( $h, $src ); }
};
$coada();
ca_browser();
ah_test_actiune( 'wp_print_scripts' ); ah_test_actiune( 'wp_print_styles' ); ah_test_actiune( 'wp_enqueue_scripts' );
egal( array( $GLOBALS['wp_scripts']->scoase, $GLOBALS['wp_styles']->scoase ), array( array(), array() ), 'urmărire: în browser coada rămâne neatinsă' );
ca_aplicatie();
ah_test_actiune( 'wp_enqueue_scripts' );
egal( $GLOBALS['wp_scripts']->queue, array( 'jquery-core', 'elementor-frontend' ), 'urmărire: în aplicație rămân doar scripturile site-ului' );
egal( $GLOBALS['wp_styles']->queue, array( 'astra-theme-css', 'cmplz-document' ), 'urmărire: în aplicație pleacă stilurile barei Complianz' );
unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
foreach ( array( 'facebook_for_woocommerce_integration_pixel_enabled', 'wc_order_attribution_allow_tracking', 'cmplz_site_needs_cookiewarning' ) as $f ) {
	ca_browser();
	egal( ah_test_filtru( $f, true ), true, 'urmărire: browser, ' . $f . ' neatins' );
	ca_aplicatie();
	egal( ah_test_filtru( $f, true ), false, 'urmărire: aplicație, ' . $f . ' = false' );
}
foreach ( array( 'googlesitekit_analytics-4_tag_blocked', 'googlesitekit_tagmanager_tag_blocked' ) as $f ) {
	ca_browser();
	egal( ah_test_filtru( $f, false ), false, 'urmărire: browser, ' . $f . ' neatins' );
	ca_aplicatie();
	egal( ah_test_filtru( $f, false ), true, 'urmărire: aplicație, ' . $f . ' = true' );
}
$capi = 'https://graph.facebook.com/v21.0/1234567890/events';
verifica( ah_test_filtru( 'pre_http_request', false, array(), $capi ) instanceof WP_Error, 'urmărire: Conversions API dintr-o cerere din aplicație nu pleacă' );
egal( ah_test_filtru( 'pre_http_request', false, array(), 'https://fcm.googleapis.com/v1/projects/x/messages:send' ), false, 'urmărire: alte cereri (FCM) pleacă' );
ca_browser();
egal( ah_test_filtru( 'pre_http_request', false, array(), $capi ), false, 'urmărire: în browser Conversions API neatins' );
ca_aplicatie();
// pagina publică salvată de pe site (cu GA, Clarity, Complianz, atribuire, Meta) + un pixel Meta clasic
$pixel = "<!-- Meta Pixel Code -->\n<script type='text/javascript'>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');</script>\n<script type='text/javascript'>fbq('init', '000000000000000', {}, {\"agent\": \"woocommerce-9.0-3.7.6\"});fbq('track', 'PageView', []);</script>\n<noscript><img height=\"1\" width=\"1\" style=\"display:none\" alt=\"fbpx\" src=\"https://www.facebook.com/tr?id=000000000000000&ev=PageView&noscript=1\" /></noscript>\n<!-- End Meta Pixel Code -->\n";
$gtm   = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
$ga_ev = '<script>(function(){if(typeof gtag!==\'function\')return;gtag(\'event\',\'x\');})();</script>';
$pub   = str_replace( '</head>', $pixel . '</head>', file_get_contents( is_file( __DIR__ . '/../../reference/v2/spatiul-meu-public.html' ) ? __DIR__ . '/../../reference/v2/spatiul-meu-public.html' : __DIR__ . '/date/v2/spatiul-meu-public.html' ) /* CI: copia din extrage-referinte.php */ );
$pub   = preg_replace( '~<body([^>]*)>~', '<body$1>' . $gtm . $ga_ev, $pub, 1 );
$cur   = ah_spa_fara_urmarire( $pub );
foreach ( array( 'googletagmanager.com', 'G-52BCW9FS1V', 'function gtag(', 'clarity.ms', 'cmplzCallClarity', 'connect.facebook.net', 'fbq(', 'facebook.com/tr', 'facebook-for-woocommerce', 'sourcebuster', 'order-attribution', 'wc_order_attribution', 'complianz.min.js', 'id="cmplz-cookiebanner-container"', 'cmplz-cookiebanner-js', 'Consent Management powered by Complianz', 'cmplz-general-css', 'cmplz-banner-1-optin-css', 'id="cmplz-manage-consent"', 'data-category="statistics"' ) as $nu ) {
	verifica( false === strpos( $cur, $nu ), 'urmărire: pagina din aplicație nu mai conține „' . $nu . '”' );
	verifica( false !== strpos( $pub, $nu ), 'urmărire (martor): pagina din browser conține „' . $nu . '”' );
}
foreach ( array( 'id="ahspx-js"', 'window.AH_SPX=', 'var D=window.AH_SP', 'function ev(n,p){try{if(typeof window.gtag', 'id="ah-telefon-1022-js"', 'yoast-schema-graph', 'speculationrules', 'ahbq-fila', $ga_ev, 'id="wp-hooks-js"', 'id="ahsp"' ) as $da ) {
	verifica( false !== strpos( $cur, $da ), 'urmărire: rămâne „' . substr( $da, 0, 40 ) . '”' );
}
egal( substr_count( $cur, '<script' ), substr_count( $pub, '<script' ) - 14, 'urmărire: pleacă exact cele 14 scripturi de urmărire și ale barei' );
egal( ah_spa_fara_urmarire( $cur ), $cur, 'urmărire: a doua trecere nu mai schimbă nimic' );
egal( ah_spa_fara_urmarire( '<p>altceva</p><script>var a=1;</script>' ), '<p>altceva</p><script>var a=1;</script>', 'urmărire: alt HTML neatins' );
verifica( false !== strpos( ah_spa_css(), '#cmplz-cookiebanner-container' ), 'urmărire: și stilul aplicației ascunde bara (dacă ar rămâne)' );
// Vimeo: dnt=1
$v = array(
	'https://player.vimeo.com/video/1?h=a'                         => 'https://player.vimeo.com/video/1?h=a&dnt=1',
	'https://player.vimeo.com/video/1'                             => 'https://player.vimeo.com/video/1?dnt=1',
	'https://player.vimeo.com/video/1?h=a&amp;title=0'             => 'https://player.vimeo.com/video/1?h=a&amp;title=0&amp;dnt=1',
	'https://player.vimeo.com/video/1?dnt=0&h=a'                   => 'https://player.vimeo.com/video/1?h=a&dnt=1',
	'https://player.vimeo.com/video/1?h=a#t=30s'                   => 'https://player.vimeo.com/video/1?h=a&dnt=1#t=30s',
	'https://player.vimeo.com/video/1?h=a&title=0&dnt=1&byline=0'  => 'https://player.vimeo.com/video/1?h=a&title=0&dnt=1&byline=0',
	'//player.vimeo.com/video/2'                                   => '//player.vimeo.com/video/2?dnt=1',
	'https://vimeo.com/1'                                          => 'https://vimeo.com/1',
	'https://www.youtube.com/embed/x'                              => 'https://www.youtube.com/embed/x',
);
foreach ( $v as $a => $b ) {
	egal( ah_spa_vimeo_dnt( $a ), $b, 'Vimeo dnt: ' . $a );
}
egal( ah_spa_vimeo_dnt_html( '<div><iframe src="https://player.vimeo.com/video/9?h=b" allow="autoplay"></iframe><iframe src="https://www.youtube.com/embed/x"></iframe></div>' ), '<div><iframe src="https://player.vimeo.com/video/9?h=b&dnt=1" allow="autoplay"></iframe><iframe src="https://www.youtube.com/embed/x"></iframe></div>', 'Vimeo dnt în HTML; YouTube neatins' );
egal( ah_spa_vimeo_dnt_html( '<iframe class="cmplz-iframe" data-src-cmplz="https://player.vimeo.com/video/9?h=b" src="about:blank"></iframe>' ), '<iframe class="cmplz-iframe" src="https://player.vimeo.com/video/9?h=b&dnt=1"></iframe>', 'Vimeo ținut de Complianz: pornește cu dnt=1' );
// ob_start: doar în aplicație, doar pe paginile ei
$GLOBALS['ah_test_e_pagina'] = true;
$_SERVER['REQUEST_URI']      = '/spatiul-meu/';
ca_browser();
$n0 = ob_get_level();
egal( array( ah_spa_porneste_filtru_html(), ob_get_level() ), array( false, $n0 ), 'urmărire: în browser nicio prelucrare a paginii' );
ca_aplicatie();
ob_start(); // „browserul” care primește pagina
egal( array( ah_spa_porneste_filtru_html(), ob_get_level() ), array( true, $n0 + 2 ), 'urmărire: în aplicație pagina trece prin filtru' );
echo $pub;
ob_end_flush(); // WordPress, la final (wp_ob_end_flush_all)
$iesit = ob_get_clean();
egal( $iesit, ah_spa_fara_elementor( ah_spa_viewport_cover( ah_spa_vimeo_dnt_html( ah_spa_fara_urmarire( $pub ) ) ) ), 'urmărire: ieșirea paginii e cea curățată' );
verifica( false !== strpos( $iesit, 'viewport-fit=cover' ) && 1 === preg_match_all( '~<meta[^>]*name=["\']viewport~i', $iesit ), 'aplicație: o singură meta viewport, cu viewport-fit=cover' );
verifica( false === strpos( $iesit, 'data-elementor-type="header"' ) && false === strpos( $iesit, 'data-elementor-type="footer"' ) && false === strpos( $iesit, 'data-elementor-type="popup"' ), 'Spațiul meu în aplicație: fără marcajul antetului, subsolului și popup-urilor Elementor' );
egal( substr_count( $iesit, '<div' ) - substr_count( $iesit, '</div' ), substr_count( $pub, '<div' ) - substr_count( $pub, '</div' ), 'Elementor scos: <div>-urile rămân echilibrate' );
$GLOBALS['ah_test_e_pagina'] = false;
$_SERVER['REQUEST_URI']      = '/?ah_med_api=audio&id=5';
egal( ah_spa_porneste_filtru_html(), false, 'urmărire: sunetul meditațiilor (/?ah_med_api) nu trece prin filtru' );
verifica( isset( $GLOBALS['ah_test_hooks']['template_redirect'] ) && in_array( 9999, array_column( $GLOBALS['ah_test_hooks']['template_redirect'], 1 ), true ), 'urmărire: filtrul pornește după redirecționări (template_redirect, 9999)' );
// Apple Podcasts: playerul nu se încarcă în aplicație, linkul episodului rămâne
$C2 = ah_spa_curata_date( $D );
egal( array_unique( array_column( $C2['podcast']['episoade'], 'embed' ) ), array( 'about:blank' ), 'podcast: playerul Apple (embed) nu se încarcă în aplicație' );
egal( array_column( $C2['podcast']['episoade'], 'apple' ), array_column( $D['podcast']['episoade'], 'apple' ), 'podcast: linkul episodului spre Apple Podcasts rămâne' );
verifica( false === strpos( wp_json_encode( $C2 ), 'embed.podcasts.apple.com' ), 'podcast: nicio adresă embed.podcasts.apple.com în datele din aplicație' );

/* ---------- 14. JS: refacerea ciclului, tipul tokenului, iframe-urile ---------- */
$js = ah_spa_js();
foreach ( array( 'function refacere()', "PREF.ia('ah_ciclu')", 'v.cine!==S.cine', "new HashChangeEvent('hashchange')", 'ahspa-refac', 'ciSters', 'cine:S.cine', "fel:function(tok)", "r.token==='fcm'", 'PUSH.primit(t.value)', 'dntVimeo', 'embed\\.podcasts\\.apple\\.com', 'Ascultă în Apple Podcasts' ) as $x ) {
	verifica( false !== strpos( $js, $x ), 'puntea conține ' . $x );
}
verifica( false !== strpos( ah_spa_css(), 'html.ahspa-refac #ahsp{visibility:hidden}' ), 'stilul: Spațiul meu ascuns cât se reface ciclul' );
foreach ( array( 'googletagmanager', 'connect.facebook', 'clarity.ms', 'gtag(' ) as $nu ) {
	verifica( false === strpos( $js, $nu ), 'puntea nu conține „' . $nu . '” (nu ar trece de propriul filtru)' );
}

/* ---------- 15. codul de intrare din 235: contor atomic pe adresă (secțiunea 11) ---------- */
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
delete_option( 'ah_spa_recenzie' );
$GLOBALS['ah_test_transients'] = array();
$EM = 'ioana@exemplu.ro';
$intra = function ( $e, $cod = '111111' ) { return ah_test_filtru( 'rest_pre_dispatch', null, null, new WP_REST_Request( array(), array( 'email' => $e, 'cod' => $cod ), array(), '/ah-sp/v1/intra', 'POST' ) ); };
$dupa = function ( $ruta, $e, $st, $d ) { return ah_test_filtru( 'rest_post_dispatch', new WP_REST_Response( $d, $st ), null, new WP_REST_Request( array(), array( 'email' => $e ), array(), $ruta, 'POST' ) ); };
verifica( in_array( 'ah_spa_cod_paza', array_column( array_filter( $GLOBALS['ah_test_hooks']['rest_pre_dispatch'], function ( $x ) { return 6 === $x[1]; } ), 0 ), true ), 'paza codului: rest_pre_dispatch, prioritatea 6 (după contul de recenzie, 4)' );
ca_browser(); // apără intrarea și în browser
// rafala: 40 de cereri pornite deodată ajung toate la paza înainte ca 235 să compare vreun cod (235 citește n=0 pentru toate)
$trec = 0;
for ( $i = 0; $i < 40; $i++ ) { if ( null === $intra( $EM, sprintf( '%06d', $i ) ) ) { $trec++; } }
egal( $trec, AH_SPA_COD_MAX, 'rafală de 40 de cereri /intra în paralel: doar 5 ajung la 235' );
$r = $intra( $EM );
verifica( $r instanceof WP_REST_Response && 429 === $r->get_status() && array( 'ok' => false, 'mesaj' => 'Prea multe încercări. Cere un cod nou.' ) === $r->get_data() && 'no-store, private, max-age=0' === ( $r->headers['Cache-Control'] ?? '' ), 'peste limită: 429 cu răspunsul lui 235, fără cache' );
set_transient( 'ah_sp_cod_' . md5( $EM ), array( 'h' => 'x', 'n' => 0 ), 900 );
$intra( $EM );
egal( get_transient( 'ah_sp_cod_' . md5( $EM ) ), false, 'peste limită: codul de acum din 235 nu mai e bun (cere altul)' );
egal( $intra( '  IOANA@Exemplu.ro ' )->get_status(), 429, 'adresa normalizată ca în 235 (majuscule, spații): același contor' );
egal( $intra( 'alta@exemplu.ro' ), null, 'altă adresă: contorul ei' );
// un cod nou (/cod 200 din 235) golește fereastra
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
for ( $i = 0; $i < 5; $i++ ) { $intra( $EM ); }
egal( $intra( $EM )->get_status(), 429, 'a 6-a încercare pe același cod: 429' );
$dupa( '/ah-sp/v1/cod', $EM, 200, array( 'ok' => true ) );
egal( $intra( $EM ), null, 'după un cod nou: din nou încercări' );
$dupa( '/ah-sp/v1/cod', $EM, 429, array( 'ok' => false ) );
$dupa( '/ah-sp/v1/intra', $EM, 403, array( 'ok' => false ) );
for ( $i = 0; $i < 4; $i++ ) { $intra( $EM ); }
egal( $intra( $EM )->get_status(), 429, '/cod refuzat sau /intra greșit nu golesc fereastra' );
// intrare reușită: fereastra la zero
$dupa( '/ah-sp/v1/intra', $EM, 200, array( 'ok' => true ) );
egal( $intra( $EM ), null, 'după o intrare reușită: fereastra la zero' );
// plafonul pe zi: 15 greșeli, chiar cu coduri noi
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
$trec = 0;
for ( $c = 0; $c < 5; $c++ ) { $dupa( '/ah-sp/v1/cod', $EM, 200, array( 'ok' => true ) ); for ( $i = 0; $i < 5; $i++ ) { if ( null === $intra( $EM ) ) { $trec++; } } }
egal( $trec, AH_SPA_COD_ZI, 'cu coduri noi mereu: cel mult 15 încercări pe zi' );
$wpdb->query( 'UPDATE wp_ah_spa_incercari SET expira = ' . ( time() - 1 ) );
egal( $intra( $EM ), null, 'a doua zi: din nou încercări' );
// contorul e atomic: un singur UPDATE (rândul blocat), apoi citirea
$GLOBALS['ah_test_sql'] = array();
ah_spa_contor( 'proba', 60 );
verifica( 2 === count( $GLOBALS['ah_test_sql'] ) && 0 === strpos( $GLOBALS['ah_test_sql'][0], 'INSERT IGNORE INTO wp_ah_spa_incercari' ) && false !== strpos( $GLOBALS['ah_test_sql'][1], 'SET n = CASE WHEN expira <' ), 'contorul: INSERT IGNORE + un UPDATE atomic (n = n + 1 în baza de date, nu în PHP)' );
verifica( false === strpos( implode( ' ', $GLOBALS['ah_test_sql'] ), $EM ), 'contorul: în baza de date doar amprenta, fără adresa de email' );
// fără tabel (fragmentul abia pornit): rezerva cu transient, fără eroare
$wpdb->pdo->exec( 'ALTER TABLE wp_ah_spa_incercari RENAME TO x_incercari' );
egal( array( ah_spa_contor( 'fara-tabel', 60 ), ah_spa_contor( 'fara-tabel', 60 ) ), array( 1, 2 ), 'fără tabel: contorul merge prin transient' );
$wpdb->pdo->exec( 'ALTER TABLE x_incercari RENAME TO wp_ah_spa_incercari' );
// contul de recenzie nu trece prin paza de aici (are contoarele lui)
ca_aplicatie();
$wpdb->query( 'DELETE FROM wp_ah_spa_incercari' );
update_option( 'ah_spa_recenzie', array( 'email' => $REC, 'hash' => wp_hash_password( $COD ), 'doar_app' => 1 ) );
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
for ( $i = 0; $i < 3; $i++ ) { $intra( $REC, $COD ); }
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_incercari WHERE cheie = '" . ah_spa_contor_cheie( 'cod|' . $REC ) . "'" ), 0, 'recenzie: codul fix nu atinge contorul pe adresă' );
egal( $intra( $REC, $COD )->get_status(), 200, 'recenzie: intrarea cu codul fix merge în continuare' );
delete_option( 'ah_spa_recenzie' );
egal( $intra( 'x@y.ro' ) === null && ah_spa_cod_paza( 'deja', null, new WP_REST_Request( array(), array( 'email' => $EM ), array(), '/ah-sp/v1/intra', 'POST' ) ) === 'deja', true, 'un răspuns dat deja rămâne' );
egal( ah_spa_cod_paza( null, null, new WP_REST_Request( array(), array( 'email' => $EM ), array(), '/ah-sp/v1/intra', 'GET' ) ), null, 'GET: neatins' );
egal( array( ah_spa_ip_cheie( '2001:db8:1:2:3:4:5:6' ), ah_spa_ip_cheie( '2001:db8:1:2:ffff::1' ), ah_spa_ip_cheie( '203.0.113.9' ) ), array( '20010db800010002/64', '20010db800010002/64', '203.0.113.9' ), 'IP: IPv6 pe /64, IPv4 întreg' );

/* ---------- 16. pagina din aplicație: zone sigure, stilurile temei, notificările ---------- */
egal( ah_spa_viewport_cover( '<head><meta name="viewport" content="width=device-width, initial-scale=1"></head>' ), '<head><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"></head>', 'viewport: + viewport-fit=cover' );
egal( ah_spa_viewport_cover( "<meta name='viewport' content='width=device-width,viewport-fit=cover'>" ), "<meta name='viewport' content='width=device-width,viewport-fit=cover'>", 'viewport: deja cover, neschimbat' );
egal( ah_spa_viewport_cover( '<html><head><title>x</title></head>' ), '<html><head><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>x</title></head>', 'viewport: lipsă, adăugat' );
$GLOBALS['wp_styles'] = new Ah_Test_Deps();
foreach ( array( 'astra-theme-css' => array(), 'chld_thm_cfg_child' => array( 'astra-theme-css' ), 'woocommerce-general' => array(), 'wp-block-library' => array(), 'global-styles' => array(), 'elementor-frontend' => array(), 'elementor-post-18' => array( 'elementor-frontend' ), 'elementor-gf-local-figtree' => array(), 'ahspx-css' => array() ) as $h => $dp ) { $GLOBALS['wp_styles']->adauga( $h, '/x/' . $h . '.css', $dp ); }
$GLOBALS['ah_test_e_pagina'] = true;
$_SERVER['REQUEST_URI'] = '/spatiul-meu/';
ca_browser();
ah_spa_scoate_tema();
egal( $GLOBALS['wp_styles']->scoase, array(), 'stilurile temei: în browser neatinse' );
ca_aplicatie();
ah_spa_scoate_tema();
$sc = $GLOBALS['wp_styles']->scoase;
sort( $sc );
egal( $sc, array( 'elementor-frontend', 'elementor-post-18', 'global-styles', 'woocommerce-general', 'wp-block-library' ), 'Spațiul meu în aplicație: scoase WooCommerce, blocurile, stilurile globale, Elementor; Astra rămâne fiindcă tema-copil depinde de ea' );
egal( count( $GLOBALS['wp_styles']->sterse ), 5, 'scoase și din înregistrare (cu stilurile lor inline)' );
verifica( in_array( 'elementor-gf-local-figtree', $GLOBALS['wp_styles']->queue, true ) && in_array( 'ahspx-css', $GLOBALS['wp_styles']->queue, true ), 'fonturile și stilul lui 266 rămân' );
egal( ah_spa_stiluri_de_scos( array( 'astra-theme-css', 'x' ), array(), ah_spa_stiluri_tema( 'sp' ) ), array( 'astra-theme-css' ), 'fără dependențe: Astra pleacă pe Spațiul meu' );
verifica( ! in_array( 'astra-theme-css', ah_spa_stiluri_tema( 'sala' ), true ) && ! in_array( 'elementor-frontend', ah_spa_stiluri_tema( 'carti' ), true ) && array() === ah_spa_stiluri_tema( 'politici' ), 'sala, cărțile: tema rămâne (le folosesc 44, 231); politicile: neatinse' );
$GLOBALS['ah_test_e_pagina'] = false;
egal( ah_spa_fcm_mesaj( 't', 'T', 'x', array() )['message']['android']['notification']['icon'], 'ic_stat_lotus', 'push Android: pictograma albă a aplicației' );
$js = ah_spa_js();
foreach ( array( "'backButton'", "'minimizeApp'", 'function inCoada(', 'AHSPA_ALT_CONT', "'createChannel'", 'visibility:0', "title:FT.t", "'ah_ciclu_notif',1", 'mementouriPornite', "cheama('Filesystem','stat'", "cheama('SplashScreen','hide'", 'function intra(', 'Ascultă pe Spotify', 'function confirma(', 'aria-labelledby', 'Tot de la tine:', 'Cursurile, cărțile și meditațiile de pe această adresă apar aici.' ) as $x ) {
	verifica( false !== strpos( $js, $x ), 'puntea conține ' . $x );
}
verifica( false === strpos( $js, 'window.confirm' ) && false === strpos( $js, 'Menstruația ta ar putea' ), 'puntea: fără window.confirm și fără „menstruație” pe ecranul blocat' );
$dev = ah_spa_js_devreme();
verifica( false !== strpos( $dev, "'ahspa_cine'" ) && false !== strpos( $dev, '/^(ahsp_|ahspx_|ahct_|ah_ciclu_v1$)/' ) && strlen( $dev ) < 1200, 'partea devreme: amprenta contului și curățarea cheilor, mică' );

echo "\n" . $GLOBALS['ok'] . ' teste trecute, ' . $GLOBALS['rau'] . " picate.\n";
exit( $GLOBALS['rau'] ? 1 : 0 );
