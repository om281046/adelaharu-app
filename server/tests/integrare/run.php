<?php
/**
 * Testele de integrare: modulul aplicație (ah-sp-aplicatie.php) + contul pentru el (ah-sp-partener.php), încărcate
 * împreună, ca pe site. Rulare (din rădăcina proiectului): php server/tests/integrare/run.php — cod 1 dacă pică ceva.
 *
 * Stubs: cele ale partenerului (tests/partener/stubs.php: WordPress imitat, $wpdb SQLite, funcțiile lui 235 citite din
 * reference/v2), cu o singură schimbare: do_action / apply_filters chiar rulează cârligele (acolo doar se țin minte),
 * pentru că aici exact legăturile dintre cele două fragmente se verifică (ah_spa_permise, ah_spa_cont_sters).
 *
 * Verifică: lista permisă și redirecționările din aplicație pentru /pentru-el/, AASA = App Links (AndroidManifest.xml),
 * configurația punții (el, api_el), tokenul push al unui telefon = un singur cont (ea sau el), telefonul ei are prioritate,
 * ștergerea contului ei îi șterge și partenerii (cu tokenurile lor), pagina lui în aplicație (fără cărți, fără Google,
 * fără urmărire), ieșirea și ștergerea lui nu ating tokenurile ei.
 */

$src = file_get_contents( __DIR__ . '/../partener/stubs.php' );
$src = str_replace(
	array(
		'function do_action( $h, ...$a ) { $GLOBALS[\'ah_test_jurnal_do\'][] = $h; }',
		'function apply_filters( $h, $v, ...$a ) { return $v; }',
	),
	array(
		'function do_action( $h, ...$a ) { $GLOBALS[\'ah_test_jurnal_do\'][] = $h; ah_test_actiune( $h, ...$a ); }',
		'function apply_filters( $h, $v, ...$a ) { return ah_test_filtru( $h, $v, ...$a ); }',
	),
	$src,
	$n
);
if ( 2 !== $n ) {
	fwrite( STDERR, "stubs.php al partenerului s-a schimbat: do_action / apply_filters nu mai sunt cum le știam\n" );
	exit( 1 );
}
eval( '?>' . $src ); // __DIR__ rămâne server/tests/integrare (aceeași adâncime ca partener/: căile spre reference/v2 merg)
// ce cere modulul aplicație în plus față de stubs-ul partenerului
if ( ! function_exists( 'is_robots' ) ) { function is_robots() { return false; } }
if ( ! function_exists( 'is_favicon' ) ) { function is_favicon() { return false; } }

// cheia contului pentru el din wp-config.php (AH_SPP_CHEIE): aici definită, ca pe un site care o are
define( 'AH_SPP_CHEIE', 'cheie-de-test-din-wp-config-0123456789abcdef' );
require __DIR__ . '/../../ah-sp-aplicatie.php';
require __DIR__ . '/../../ah-sp-partener.php';

$GLOBALS['ok']  = 0;
$GLOBALS['rau'] = 0;
function verifica( $c, $nume ) {
	if ( $c ) { $GLOBALS['ok']++; } else { $GLOBALS['rau']++; echo "PICAT: $nume\n"; }
}
function egal( $a, $b, $nume ) {
	verifica( $a === $b, $nume . ( $a === $b ? '' : ' (am ' . var_export( $a, true ) . ', așteptam ' . var_export( $b, true ) . ')' ) );
}
function cerere( $json = null, $param = array(), $antet = array( 'x_ah_app' => '1', 'x_ah_spp' => '1' ) ) { return new WP_REST_Request( $antet, $json, $param ); }
function ea( $e ) { $GLOBALS['ah_test_email'] = $e; }
function aplicatie( $da ) {
	$_SERVER['HTTP_USER_AGENT'] = $da ? 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Mobile/15E148 AdelaHaruApp/2.0.0 (ios)' : 'Mozilla/5.0 (iPhone) Safari/604.1';
	unset( $_COOKIE['ah_app'] );
}
function zi( $n ) { return date( 'Y-m-d', strtotime( date( 'Y-m-d' ) . ' 12:00:00' ) - $n * 86400 ); }
function ciclu_in_cont( $email ) {
	global $wpdb;
	$j = json_encode( array( 'ciclu' => array( 'starturi' => array( zi( 8 ), zi( 36 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ), 'note' => array( zi( 1 ) => array( 'text' => 'NOTITA' ) ) ) );
	$wpdb->delete( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $email ) ) );
	$wpdb->insert( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $email ), 'date' => ah_sp_ciclu_cripteaza( $j ), 'acord' => '2026-11-01 10:00:00', 'actualizat' => '2026-11-01 10:00:00' ) );
}
function invita( $ea, $nume, $email ) {
	global $wpdb;
	ea( $ea );
	$r = ah_spp_r_invita( cerere( array( 'nume' => $nume, 'email' => $email, 'nume_ea' => 'Ana', 'acord' => true ) ) );
	verifica( ! empty( $r->get_data()['ok'] ), 'invitația ' . $nume . ' pleacă' );
	ea( '' );
	return $wpdb->get_row( 'SELECT * FROM wp_ah_spp_parteneri ORDER BY id DESC LIMIT 1', ARRAY_A );
}
function el( $r ) {
	if ( $r ) {
		$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $r, time() + 3600 );
	} else {
		unset( $_COOKIE['ah_spp'] );
	}
}
function tokenuri( $tabel ) { global $wpdb; return array_map( function ( $r ) { return $r['token']; }, $wpdb->get_results( 'SELECT token FROM ' . $tabel . ' ORDER BY id', ARRAY_A ) ); }

$TOK  = 'fcmTelefon:APA91b' . str_repeat( 'a', 120 );
$TOK2 = 'fcmAltTelefon:APA91b' . str_repeat( 'b', 120 );

/* ---------- 0. amândouă încărcate ---------- */
verifica( defined( 'AH_SPA' ) && defined( 'AH_SPP' ), 'cele două fragmente se încarcă împreună' );
verifica( ah_spp_gata(), 'contul pentru el găsește funcțiile lui 235' );
update_option( 'ah_spa_tabel', AH_SPA_TABEL_VER ); // tabelul ei e deja în $wpdb-ul de test (țin locul lui dbDelta)
ah_test_actiune( 'init' );
ah_test_actiune( 'rest_api_init' );
egal( ah_spp_secret(), hash_hmac( 'sha256', 'ah-spp|cheie', AH_SPP_CHEIE ), 'contul pentru el: cheia din AH_SPP_CHEIE (wp-config), nu din baza de date' );
verifica( false === get_option( 'ah_spp_secret' ), 'contul pentru el: nicio cheie în opțiuni' );

/* ---------- 1. lista permisă, redirecționări, AASA = App Links ---------- */
verifica( in_array( '/pentru-el/', ah_spa_permise(), true ), 'ah_spa_permise() conține /pentru-el/ (prin filtrul partenerului)' );
aplicatie( true );
egal( ah_spa_destinatie( '/pentru-el/' ), '', 'în aplicație /pentru-el/ rămâne în aplicație' );
egal( ah_spa_destinatie( '/pentru-el/?t=abc' ), '', 'în aplicație /pentru-el/?t= rămâne' );
egal( ah_spa_destinatie( '/pentru-el' ), '', 'în aplicație /pentru-el (fără bară) rămâne' );
egal( ah_spa_destinatie( '/pentru-el/?fara-emailuri=1.x' ), '', 'oprirea emailurilor lui merge și în aplicație' );
egal( ah_spa_destinatie( '/product/magia-ciclului-feminin/' ), 'https://www.adelaharu.ro/spatiul-meu/', 'cărțile rămân blocate în aplicație' );
verifica( in_array( '/pentru-el/', ah_spa_cfg( 'sp', '' )['permise'], true ), 'puntea: S.permise conține /pentru-el/ (permis() pentru appUrlOpen, getLaunchUrl, notificări)' );
$_SERVER['REQUEST_URI'] = '/pentru-el/';
$GLOBALS['ah_test_e_pagina'] = false;
egal( ah_spa_zona(), '', 'pagina lui nu e o zonă a punții (puntea ei nu se tipărește acolo)' );

update_option( 'ah_spa_setari', array( 'ios_team' => 'ABCDE12345' ) );
$cai = ah_spa_aasa()['applinks']['details'][0]['paths'];
verifica( in_array( '/pentru-el', $cai, true ) && in_array( '/pentru-el/*', $cai, true ), 'AASA: /pentru-el și /pentru-el/*' );
$man = file_get_contents( __DIR__ . '/../../../app/android/app/src/main/AndroidManifest.xml' );
preg_match_all( '/android:(path|pathPrefix)="([^"]+)"/', $man, $m, PREG_SET_ORDER );
$android = array_map( function ( $x ) { return 'pathPrefix' === $x[1] ? $x[2] . '*' : $x[2]; }, $m );
egal( $android, $cai, 'App Links din AndroidManifest.xml = căile din AASA (aceeași ordine)' );
verifica( false !== strpos( $man, 'android:autoVerify="true"' ), 'App Links: autoVerify' );
$ent = file_get_contents( __DIR__ . '/../../../app/ios/App/App/App.entitlements' ) . file_get_contents( __DIR__ . '/../../../app/ios/App/App/AppDebug.entitlements' );
egal( substr_count( $ent, '<string>applinks:www.adelaharu.ro</string>' ), 2, 'iOS: Associated Domains (Release și Debug) acoperă /pentru-el fără altceva' );
$cap = file_get_contents( __DIR__ . '/../../../app/capacitor.config.ts' );
verifica( (bool) preg_match( "/allowNavigation:\\s*\\[[^\\]]*'www\\.adelaharu\\.ro'/", $cap ), 'shell: www.adelaharu.ro în allowNavigation (deci /pentru-el/ se deschide în aplicație, nu în Safari)' );

/* ---------- 2. redirecționarea din aplicație: telefonul lui pornește în pagina lui ---------- */
ciclu_in_cont( 'ana@exemplu.ro' );
$m1 = invita( 'ana@exemplu.ro', 'Mihai', 'mihai@exemplu.ro' );
$_SERVER['REQUEST_URI'] = '/spatiul-meu/';
$_GET = array();
el( $m1 );
aplicatie( true );
egal( ah_spp_redirect_aplicatie(), 'https://www.adelaharu.ro/pentru-el/', 'aplicație, /spatiul-meu/ cu cookie-ul lui și fără al ei → /pentru-el/' );
ea( 'ana@exemplu.ro' );
egal( ah_spp_redirect_aplicatie(), '', 'cu ea intrată pe telefon: contul ei (nicio redirecționare)' );
ea( '' );
aplicatie( false );
egal( ah_spp_redirect_aplicatie(), '', 'în browser: nicio redirecționare' );

/* ---------- 3. configurația punții: e intrat el? ---------- */
aplicatie( true );
$c = ah_spa_cfg( 'sp', '' );
egal( array( $c['el'], $c['api_el'], $c['pagina_el'] ), array( true, 'https://www.adelaharu.ro/wp-json/ah-spp/v1/', 'https://www.adelaharu.ro/pentru-el/' ), 'AH_SPA: el = true, api_el, pagina_el' );
verifica( false === strpos( wp_json_encode( $c ), 'mihai' ), 'AH_SPA: nimic despre el în afară de da/nu' );
el( null );
egal( ah_spa_cfg( 'sp', '' )['el'], false, 'AH_SPA: fără cookie-ul lui, el = false' );

/* ---------- 4. un telefon = un cont: tokenul push trece dintr-o parte în alta ---------- */
global $wpdb;
el( $m1 );
$r = ah_spp_r_push( cerere( array( 'token' => $TOK, 'platforma' => 'android' ) ) );
egal( $r->get_data()['ok'] ?? null, true, 'el: POST /ah-spp/v1/push' );
egal( tokenuri( 'wp_ah_spp_push' ), array( $TOK ), 'el: tokenul e în tabelul lui' );
ea( 'ana@exemplu.ro' );
el( null );
$r = ah_spa_r_push( cerere( array( 'token' => $TOK, 'platforma' => 'android', 'versiune' => '2.0.0', 'disp' => 'tel1' ) ) );
egal( $r->get_data()['ok'] ?? null, true, 'ea: POST /ah-spa/v1/push cu același token (același telefon)' );
egal( array( tokenuri( 'wp_ah_spa_push' ), tokenuri( 'wp_ah_spp_push' ) ), array( array( $TOK ), array() ), 'ea: tokenul trece la ea și pleacă de la el' );
// el pe același telefon, cu ea intrată: refuzat (telefonul e al ei)
el( $m1 );
$r = ah_spp_r_push( cerere( array( 'token' => $TOK, 'platforma' => 'android' ) ) );
egal( array( $r->get_status(), $r->get_data()['motiv'] ?? '' ), array( 409, 'cont_ea' ), 'el, cu ea intrată pe telefon: 409 cont_ea' );
egal( array( tokenuri( 'wp_ah_spa_push' ), tokenuri( 'wp_ah_spp_push' ) ), array( array( $TOK ), array() ), '… și tokenul rămâne al ei' );
// ea iese din cont pe telefon; el îl folosește: trece la el
ea( '' );
$r = ah_spp_r_push( cerere( array( 'token' => $TOK, 'platforma' => 'android' ) ) );
egal( $r->get_data()['ok'] ?? null, true, 'el, după ce ea a ieșit: POST /ah-spp/v1/push' );
egal( array( tokenuri( 'wp_ah_spa_push' ), tokenuri( 'wp_ah_spp_push' ) ), array( array(), array( $TOK ) ), 'tokenul trece la el și pleacă de la ea' );
$r = ah_spp_r_push( cerere( array( 'token' => str_repeat( 'ab', 32 ), 'platforma' => 'ios' ) ) );
egal( $r->get_data()['motiv'] ?? '', 'apns', 'el: tokenul APNs brut e refuzat (aceeași validare ca la ea)' );
// „Ieși” pe partea lui: DELETE /ah-spp/v1/push scoate doar tokenul lui; tabelul ei neatins
ea( 'ana@exemplu.ro' );
el( null );
ah_spa_r_push( cerere( array( 'token' => $TOK2, 'platforma' => 'ios', 'versiune' => '2.0.0', 'disp' => 'tel2' ) ) ); // telefonul ei
ea( '' );
el( $m1 );
ah_spp_r_push_sterge( cerere( array( 'token' => $TOK ) ) );
ah_spp_r_iesi( cerere( array() ) );
egal( array( tokenuri( 'wp_ah_spa_push' ), tokenuri( 'wp_ah_spp_push' ) ), array( array( $TOK2 ), array() ), '„Ieși” la el: doar tokenul lui pleacă' );
// „Ieși” pe partea ei: DELETE /ah-spa/v1/push scoate doar tokenul ei
el( $m1 );
ah_spp_r_push( cerere( array( 'token' => $TOK, 'platforma' => 'android' ) ) );
ah_spa_r_push_sterge( cerere( array( 'token' => $TOK2 ) ) );
egal( array( tokenuri( 'wp_ah_spa_push' ), tokenuri( 'wp_ah_spp_push' ) ), array( array(), array( $TOK ) ), '„Ieși” la ea: doar tokenul ei pleacă' );

/* ---------- 5. ștergerea contului ei: partenerii ei pleacă, cu tokenurile lor; ale altei femei rămân ---------- */
ciclu_in_cont( 'ioana@exemplu.ro' );
$alt = invita( 'ioana@exemplu.ro', 'Radu', 'radu@exemplu.ro' );
el( $alt );
ah_spp_r_push( cerere( array( 'token' => $TOK2, 'platforma' => 'ios' ) ) );
el( null );
ea( 'ana@exemplu.ro' );
$s = ah_spa_sterge_cont( 'ana@exemplu.ro' );
egal( $s['pentru_el'] ?? 0, 1, 'sterge-cont: „pentru_el” = 1 (în emailul echipei)' );
egal( (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_ah_spp_parteneri WHERE ea = " . $wpdb->pdo->quote( ah_sp_ciclu_cheie( 'ana@exemplu.ro' ) ) ), 0, 'sterge-cont: niciun partener al ei rămas' );
egal( tokenuri( 'wp_ah_spp_push' ), array( $TOK2 ), 'sterge-cont: tokenurile partenerilor ei șterse, ale altei femei rămân' );
egal( count( $wpdb->get_results( 'SELECT id FROM wp_ah_spp_parteneri', ARRAY_A ) ), 1, 'sterge-cont: partenerul altei femei rămâne' );
verifica( in_array( 'ah_spa_cont_sters', $GLOBALS['ah_test_jurnal_do'], true ), 'sterge-cont: cârligul ah_spa_cont_sters rulează (și partenerul îl ascultă)' );
el( $m1 );
egal( ah_spp_din_cookie(), null, 'el (al ei, șters): cookie-ul lui nu mai deschide nimic' );
$r = ah_spp_r_azi( cerere( null ) );
egal( $r->get_status(), 401, 'el (al ei, șters): /azi → 401' );
ea( '' );

/* ---------- 6. pagina lui în aplicație: fără cărți, fără Google, fără urmărire, fără puntea ei ---------- */
el( $alt );
aplicatie( true );
$cfg = ah_spp_cfg_el();
egal( array( $cfg['app'], $cfg['cont_ea'] ), array( true, false ), 'pagina lui în aplicație: app = true, cont_ea = false' );
$html = ah_spp_pagina_el( $cfg );
verifica( ! preg_match( '~/product\\\\?/~', $html ) && false === stripos( $html, 'fonts.googleapis' ), 'pagina lui în aplicație: fără linkuri spre cărți și fără Google Fonts' );
verifica( ! preg_match( '/googletagmanager|gtag\(|fbq\(|clarity|complianz|cmplz/i', $html ), 'pagina lui: fără analiză / reclame / bara de cookie-uri' );
verifica( false === strpos( $html, 'ahspa-js' ) && false === strpos( $html, 'window.AH_SPA' ), 'pagina lui: fără puntea ei (AHApp nu poate înregistra telefonul lui în contul ei)' );
verifica( false !== strpos( $html, "chem('AhApp','info')" ) && false !== strpos( $html, "cont:'el'" ), 'pagina lui: push doar cu AhApp.info() și Preferences.ah_push.cont = el' );
ea( 'ioana@exemplu.ro' );
egal( ah_spp_cfg_el()['cont_ea'], true, 'pagina lui, cu ea intrată pe același telefon: cont_ea = true (fără notificări pentru el)' );
ea( '' );
aplicatie( false );
egal( ah_spp_cfg_el()['cont_ea'], false, 'în browser: cont_ea = false (nu contează)' );
verifica( (bool) preg_match( '~/product\\\\?/~', ah_spp_pagina_el( ah_spp_cfg_el() ) ), 'în browser: cărțile rămân în „Învață”' );

/* ---------- 7. puntea ei: tokenul are stăpân ---------- */
$js = ah_spa_js();
verifica( false !== strpos( $js, "cont:'ea'" ) && false !== strpos( $js, "al:function(v){return v&&v.cont==='el'?'el':'ea';}" ), 'puntea: Preferences.ah_push.cont = ea; rândurile vechi (fără cont) sunt ale ei' );
verifica( false !== strpos( $js, "if(S.el||!S.api_el)return;" ), 'puntea: tokenul lui nu se atinge cât e intrat el' );

/* ---------- 8. interfețele comune (1.4.0): ah_spa_cont_mutat, ah_spa_are_acces_el, POST /push/sterge, ah_spp_ultima_rulare ---------- */
ea( '' );
el( null );
ciclu_in_cont( 'maria@exemplu.ro' );
$mm = invita( 'maria@exemplu.ro', 'Ion', 'ion@exemplu.ro' );
ciclu_in_cont( 'maria.noua@exemplu.ro' );
do_action( 'ah_spa_cont_mutat', 'maria@exemplu.ro', 'maria.noua@exemplu.ro' );
egal( $wpdb->get_var( 'SELECT ea FROM wp_ah_spp_parteneri WHERE id = ' . (int) $mm['id'] ), ah_sp_ciclu_cheie( 'maria.noua@exemplu.ro' ), 'ah_spa_cont_mutat (do_action): rândurile ei trec pe adresa nouă' );
el( $mm );
egal( (bool) apply_filters( 'ah_spa_are_acces_el', true, 'x@exemplu.ro' ), true, 'ah_spa_are_acces_el: cookie-ul lui, acces activ → true' );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s' ) ), array( 'id' => $mm['id'] ) );
egal( (bool) apply_filters( 'ah_spa_are_acces_el', true, 'x@exemplu.ro' ), false, 'ah_spa_are_acces_el: acces oprit → false (rândul „Ciclul ei” nu apare)' );
el( null );
egal( (bool) apply_filters( 'ah_spa_are_acces_el', false, 'x@exemplu.ro' ), false, 'ah_spa_are_acces_el: fără cookie → false' );
ah_test_actiune( 'rest_api_init' );
verifica( isset( $GLOBALS['ah_test_rute']['ah-spp/v1/push/sterge'] ), 'POST /ah-spp/v1/push/sterge există (puntea ei îl folosește)' );
ah_test_actiune( 'ah_spp_zilnic' );
verifica( abs( (int) get_option( 'ah_spp_ultima_rulare' ) - time() ) <= 2, 'ah_spp_ultima_rulare: notată de cronul zilnic (avizul din admin)' );

echo $GLOBALS['ok'] . ' verificări trecute, ' . $GLOBALS['rau'] . " picate.\n";
exit( $GLOBALS['rau'] ? 1 : 0 );
