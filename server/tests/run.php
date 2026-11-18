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
egal( AH_SPA, '2.0.0', 'versiunea fragmentului' );
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
foreach ( array( 'recomanda', 'cadou', 'site', 'cursuri', 'yin' ) as $k ) {
	egal( $C['linkuri'][ $k ], '', 'linkul ' . $k . ' golit' );
}
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
verifica( false !== strpos( $cap, '<style id="ahspa-css">' ) && false !== strpos( $cap, 'window.AH_SPA=' ) && false !== strpos( $cap, 'window.AHApp=' ), 'aplicație: stilul și puntea în <head>' );
verifica( false === strpos( $cap, 'ana@exemplu.ro' ), 'puntea nu tipărește adresa de email' );
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
$_SERVER['REQUEST_URI'] = '/politica-de-confidentialitate/';
egal( ah_test_actiune( 'wp_head' ), '', 'aplicație: nimic pe paginile de politici' );

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
$GLOBALS['ah_test_email'] = 'ana@exemplu.ro';
ah_spa_r_push( new WP_REST_Request( $H, array( 'token' => $fcm . 'NOU', 'platforma' => 'ios', 'disp' => 'tel-1' ) ) );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spa_push WHERE disp = 'tel-1'" ), 1, 'același telefon, token nou: cel vechi pleacă' );
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
egal( $det['paths'], array( '/spatiul-meu', '/spatiul-meu/*', '/app/*' ), 'AASA căile: Spațiul meu (cu ?cont=) și /app/' );
egal( $det['components'], array( array( '/' => '/spatiul-meu' ), array( '/' => '/spatiul-meu/*' ), array( '/' => '/app/*' ) ), 'AASA components' );
egal( wp_json_encode( $a, JSON_UNESCAPED_SLASHES ), '{"applinks":{"apps":[],"details":[{"appID":"ABCDE12345.ro.adelaharu.app","appIDs":["ABCDE12345.ro.adelaharu.app"],"paths":["/spatiul-meu","/spatiul-meu/*","/app/*"],"components":[{"/":"/spatiul-meu"},{"/":"/spatiul-meu/*"},{"/":"/app/*"}]}]}}', 'AASA: JSON-ul exact' );
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

/* ---------- 11. JS: puntea are tot ce cere arhitectura ---------- */
$js = ah_spa_js();
foreach ( array( "'appUrlOpen'", "'getLaunchUrl'", "'registration'", "'pushNotificationActionPerformed'", "'localNotificationActionPerformed'", "'requestPermissions'", "'ah_ciclu'", "'ah_descarcate'", "'downloadFile'", 'mediaSession', "'sterge-cont'", "'seekbackward'", "'seekforward'", 'LIBRARY_NO_CLOUD', "'#ahsp-iesi'" ) as $x ) {
	verifica( false !== strpos( $js, $x ), 'puntea conține ' . $x );
}
verifica( false !== strpos( $js, '/*CICLU-INCEPUT*/' ) && false !== strpos( $js, '/*CICLU-SFARSIT*/' ), 'calculul ciclului e delimitat (pentru testul node)' );

echo "\n" . $GLOBALS['ok'] . ' teste trecute, ' . $GLOBALS['rau'] . " picate.\n";
exit( $GLOBALS['rau'] ? 1 : 0 );
