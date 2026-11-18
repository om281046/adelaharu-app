<?php
/**
 * Testele fragmentului ah-sp-partener.php („Spațiul meu – Pentru el”), fără WordPress.
 * Rulare (din rădăcina proiectului): php server/tests/partener/run.php   — iese cu cod 1 dacă un test pică.
 * Matematica fazelor față de 235: node server/tests/partener/faze.test.cjs. Pozele: node server/tests/partener/poze.cjs.
 */

require __DIR__ . '/stubs.php';
require __DIR__ . '/../../ah-sp-partener.php';

$GLOBALS['ok']  = 0;
$GLOBALS['rau'] = 0;
function verifica( $c, $nume ) {
	if ( $c ) { $GLOBALS['ok']++; } else { $GLOBALS['rau']++; echo "PICAT: $nume\n"; }
}
function egal( $a, $b, $nume ) {
	verifica( $a === $b, $nume . ( $a === $b ? '' : ' (am ' . var_export( $a, true ) . ', așteptam ' . var_export( $b, true ) . ')' ) );
}
function cerere( $json = null, $param = array() ) { return new WP_REST_Request( array(), $json, $param ); }
function date_r( $raspuns ) { return $raspuns->get_data(); }
function zi( $n ) { return date( 'Y-m-d', strtotime( date( 'Y-m-d' ) . ' 12:00:00' ) - $n * 86400 ); } // acum n zile
function ea( $email ) { $GLOBALS['ah_test_email'] = $email; }
function fara_limite() { $GLOBALS['ah_test_transients'] = array(); }
/** Ciclul ei în cont (ca 235: {ciclu, note, partener}, criptat). */
function ciclu_in_cont( $email, $ciclu, $note = array() ) {
	global $wpdb;
	$j = json_encode( array( 'ciclu' => $ciclu, 'note' => $note, 'partener' => array( 'k' => 'cheie-veche-0123456789', 's' => '2026-01-01' ) ) );
	$wpdb->delete( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $email ) ) );
	$wpdb->insert( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $email ), 'date' => ah_sp_ciclu_cripteaza( $j ), 'acord' => '2026-11-01 10:00:00', 'actualizat' => '2026-11-01 10:00:00' ) );
}
function ultimul_mail() { $m = end( $GLOBALS['ah_test_mail_cadru'] ); return $m ? $m : null; }
function token_din_mail( $m ) { parse_str( (string) parse_url( $m['a']['buton'][1], PHP_URL_QUERY ), $q ); return $q['t'] ?? ''; }
function ultim_rand() { $r = randuri(); return end( $r ); }
function randuri() { global $wpdb; return $wpdb->get_results( 'SELECT * FROM wp_ah_spp_parteneri ORDER BY id', ARRAY_A ); }
function la( $uri, $get = array() ) { $_SERVER['REQUEST_URI'] = $uri; $_GET = $get; }

/* ---------- 0. încărcarea ---------- */
egal( AH_SPP, '1.1.0', 'versiunea fragmentului' );
verifica( ah_spp_gata(), 'dependențele din 235 sunt găsite' );
$n_hooks = count( $GLOBALS['ah_test_hooks'], COUNT_RECURSIVE );
require __DIR__ . '/../../ah-sp-partener.php';
egal( count( $GLOBALS['ah_test_hooks'], COUNT_RECURSIVE ), $n_hooks, 'a doua încărcare nu leagă nimic din nou' );
foreach ( array( 'init', 'rest_api_init', 'template_redirect', 'wp_head', 'ah_spp_zilnic', 'ah_spp_verifica', 'rest_request_after_callbacks', 'ah_spa_cont_sters', 'ah_spa_permise' ) as $h ) {
	verifica( isset( $GLOBALS['ah_test_hooks'][ $h ] ), 'cârligul ' . $h );
}
ah_test_actiune( 'rest_api_init' );
foreach ( array( 'parteneri', 'invita', 'opreste', 'retrimite', 'azi', 'trimite-link', 'push', 'email', 'iesi', 'sterge' ) as $r ) {
	verifica( isset( $GLOBALS['ah_test_rute'][ 'ah-spp/v1/' . $r ] ), 'ruta ' . $r );
}
egal( ah_test_filtru( 'ah_spa_permise', array( '/spatiul-meu/' ) ), array( '/spatiul-meu/', '/pentru-el/' ), 'în aplicație /pentru-el/ e pe lista permisă' );
ah_test_actiune( 'init' );
egal( $GLOBALS['ah_test_cron'][0][1] ?? '', 'ah_spp_zilnic', 'cronul zilnic e programat' );
verifica( ( $GLOBALS['ah_test_cron'][0][0] ?? 0 ) > time() && ( $GLOBALS['ah_test_cron'][0][0] ?? 0 ) <= time() + 86400, 'cronul pornește în următoarele 24 de ore' );
egal( date( 'H:i', $GLOBALS['ah_test_cron'][0][0] ?? 0 ), '08:10', 'cronul: 8:10, ora site-ului' );

/* ---------- 1. criptarea, amprentele, tokenurile ---------- */
$c1 = ah_spp_cripteaza( 'el@exemplu.ro' );
$c2 = ah_spp_cripteaza( 'el@exemplu.ro' );
verifica( $c1 !== $c2, 'aceeași adresă se criptează diferit de fiecare dată' );
egal( ah_spp_decripteaza( $c1 ), 'el@exemplu.ro', 'decriptarea' );
$b = base64_decode( $c1 ); $b[ strlen( $b ) - 1 ] = chr( ord( $b[ strlen( $b ) - 1 ] ) ^ 1 );
egal( ah_spp_decripteaza( base64_encode( $b ) ), null, 'un text atins nu se decriptează (GCM)' );
egal( ah_sp_ciclu_decripteaza( $c1 ), null, 'cheia e separată de a ciclului din 235' );
egal( ah_spp_hash_email( ' El@Exemplu.RO ' ), ah_spp_hash_email( 'el@exemplu.ro' ), 'amprenta nu ține cont de majuscule și spații' );
verifica( 64 === strlen( ah_spp_hash_email( 'el@exemplu.ro' ) ) && false === strpos( ah_spp_hash_email( 'el@exemplu.ro' ), 'el' ), 'amprenta e HMAC (64 hex)' );
list( $t1, $h1 ) = ah_spp_token_nou();
list( $t2 ) = ah_spp_token_nou();
verifica( ah_spp_token_valid( $t1 ) && 43 === strlen( $t1 ) && $t1 !== $t2, 'tokenul: 32 de octeți aleatori, base64url' );
egal( $h1, hash( 'sha256', $t1 ), 'pe server: sha256 al tokenului' );
verifica( ! ah_spp_token_valid( $t1 . "\n" ) && ! ah_spp_token_valid( substr( $t1, 1 ) ) && ! ah_spp_token_valid( str_replace( substr( $t1, 0, 1 ), '+', $t1 ) . 'x' ), 'tokenuri stricate refuzate' );

/* ---------- 2. invitația: acordul, ciclul în cont, limitele ---------- */
$ANA = 'ana@exemplu.ro';
ea( '' );
egal( date_r( ah_spp_r_parteneri( cerere() ) )['ok'], false, 'fără contul ei: 403' );
egal( ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'acord' => true ) ) )->get_status(), 403, 'invitație fără cont: 403' );
ea( $ANA );
$j = date_r( ah_spp_r_parteneri( cerere() ) );
egal( array( $j['ok'], $j['ciclu'], $j['max'], $j['parteneri'] ), array( true, false, 2, array() ), 'lista goală, ciclul nu e încă în cont' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana' ) ) );
egal( array( $r->get_status(), date_r( $r )['motiv'] ), array( 400, 'acord' ), 'fără bifa acordului: refuzat' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => 'da' ) ) );
egal( date_r( $r )['motiv'] ?? '', 'acord', 'acordul trebuie să fie explicit (true/1)' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
egal( array( $r->get_status(), date_r( $r )['motiv'] ), array( 409, 'ciclu' ), 'ciclul nu e în cont: întâi acordul pentru asta' );
egal( count( randuri() ), 0, 'nimic salvat fără ciclul în cont' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ), zi( 65 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ), array( zi( 2 ) => array( 'st' => array( 'tri' ), 'co' => array( 'cra' ), 'fl' => 'm', 'text' => 'NOTITA-SECRETA' ) ) );
egal( date_r( ah_spp_r_parteneri( cerere() ) )['ciclu'], true, 'ciclul e acum în cont' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai <a href="http://x.ro">', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
egal( date_r( $r )['ok'] ?? null, true, 'etichetele HTML se scot din prenume' );
fara_limite();
egal( randuri()[0]['nume'], 'Mihai', 'prenumele curățat' );
$GLOBALS['wpdb']->query( 'DELETE FROM wp_ah_spp_parteneri' );
$GLOBALS['ah_test_mail_cadru'] = array();
foreach ( array( 'www.reclama.ro', 'Ion http://x', 'a@b.ro', '' ) as $n ) {
	egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => $n, 'email' => 'mihai@exemplu.ro', 'acord' => true ) ) ) )['motiv'] ?? '', 'nume', 'prenume refuzat: ' . $n );
}
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'nu-e-email', 'acord' => true ) ) ) )['motiv'] ?? '', 'email', 'email greșit' );
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => ' ANA@exemplu.ro', 'acord' => true ) ) ) )['motiv'] ?? '', 'email', 'nu se poate invita pe ea însăși' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'Mihai@Exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$j = date_r( $r );
egal( array( $j['ok'], $j['email'], count( $j['parteneri'] ) ), array( true, true, 1 ), 'invitația 1: salvată, emailul a plecat' );
egal( $j['parteneri'][0], array( 'id' => $j['parteneri'][0]['id'], 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'stare' => 'invitat', 'vizita' => '', 'creat' => date( 'Y-m-d' ) ), 'ce vede ea în listă' );
$R1 = randuri()[0];
egal( $R1['ea'], ah_sp_ciclu_cheie( $ANA ), 'rândul e legat de ea prin ah_sp_ciclu_cheie (nu prin email)' );
verifica( false === strpos( json_encode( $R1 ), 'mihai@' ) && false === strpos( json_encode( $R1 ), 'ana@' ), 'nicio adresă în clar în tabel' );
egal( ah_spp_decripteaza( $R1['email_c'] ), 'mihai@exemplu.ro', 'emailul lui, criptat' );
egal( $R1['email_h'], ah_spp_hash_email( 'mihai@exemplu.ro' ), 'amprenta pentru căutare' );
verifica( '' !== $R1['acord'] && $R1['acord'] === $R1['creat'], 'momentul acordului e păstrat' );
$m = ultimul_mail();
egal( $m['catre'], 'mihai@exemplu.ro', 'invitația merge la el' );
egal( $m['subiect'], 'Ana te invită să-i cunoști ciclul', 'subiectul, cu prenumele ei' );
verifica( false !== strpos( $m['a']['continut'], 'poate opri accesul oricând' ) && false !== strpos( $m['a']['continut'], 'faza ei de azi și datele estimate' ), 'invitația spune ce vede și că ea poate opri' );
$TOK1 = token_din_mail( $m );
verifica( ah_spp_token_valid( $TOK1 ), 'linkul personal /pentru-el/?t=' );
verifica( 0 === strpos( $m['a']['buton'][1], 'https://www.adelaharu.ro/pentru-el/?t=' ), 'linkul duce la /pentru-el/' );
egal( hash( 'sha256', $TOK1 ), $R1['token_h'], 'în tabel doar sha256 al tokenului din email' );
verifica( false === strpos( json_encode( randuri() ), $TOK1 ), 'tokenul în clar nu e nicăieri în tabel' );
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'acord' => true ) ) ) )['motiv'] ?? '', 'exista', 'același partener de două ori: refuzat' );
$j = date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Radu', 'email' => 'radu@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => 1 ) ) ) );
egal( count( $j['parteneri'] ?? array() ), 2, 'invitația 2' );
$TOK2 = token_din_mail( ultimul_mail() );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Dan', 'email' => 'dan@exemplu.ro', 'acord' => true ) ) );
egal( array( $r->get_status(), date_r( $r )['motiv'] ), array( 409, 'max' ), 'cel mult 2 parteneri activi' );

/* ---------- 3. linkul lui: cookie semnat, adresă curată ---------- */
la( '/pentru-el/', array( 't' => $TOK1 ) );
$x = ah_spp_proceseaza_el();
egal( $x, array( 'redirect' => 'https://www.adelaharu.ro/pentru-el/' ), '?t= → redirect spre adresa curată' );
$COOKIE_M = $_COOKIE['ah_spp'] ?? '';
verifica( (bool) preg_match( '/^\d+\.\d+\.[a-f0-9]{40}$/', $COOKIE_M ), 'cookie-ul ah_spp: id.expirare.semnătură' );
$p = explode( '.', $COOKIE_M );
verifica( (int) $p[1] > time() + 364 * 86400, 'cookie-ul ține un an' );
verifica( false === strpos( $COOKIE_M, 'mihai' ) && false === strpos( $COOKIE_M, $TOK1 ), 'cookie-ul nu conține emailul sau tokenul' );
egal( (int) ah_spp_din_cookie()['id'], (int) $R1['id'], 'cookie-ul îl recunoaște' );
$RR = ah_spp_rand( $R1['id'] );
verifica( ! empty( $RR['intrat'] ) && ! empty( $RR['vizita'] ), 'prima intrare și vizita notate' );
verifica( '' !== $RR['faza_trimisa'], 'semnul fazei de acum e notat la prima intrare (fără notificare)' );
$_COOKIE['ah_spp'] = $p[0] . '.' . $p[1] . '.' . str_repeat( 'a', 40 );
egal( ah_spp_din_cookie(), null, 'semnătură greșită: nu' );
$_COOKIE['ah_spp'] = ( (int) $p[0] + 1 ) . '.' . $p[1] . '.' . $p[2];
egal( ah_spp_din_cookie(), null, 'alt id cu aceeași semnătură: nu' );
$_COOKIE['ah_spp'] = $p[0] . '.' . ( (int) $p[1] + 1 ) . '.' . $p[2];
egal( ah_spp_din_cookie(), null, 'altă expirare cu aceeași semnătură: nu' );
$e = time() - 10; $_COOKIE['ah_spp'] = $p[0] . '.' . $e . '.' . ah_spp_semn_cookie( $p[0], $e, $RR['ses'] );
egal( ah_spp_din_cookie(), null, 'cookie expirat: nu' );
$_COOKIE['ah_spp'] = $COOKIE_M . "\n";
egal( ah_spp_din_cookie(), null, 'cookie cu rând nou la capăt: nu' );
la( '/pentru-el/', array( 't' => str_repeat( 'x', 43 ) ) );
unset( $_COOKIE['ah_spp'] );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => 'link' ), 'token necunoscut: pagina cu „linkul nu mai e valabil”' );
egal( $_COOKIE['ah_spp'] ?? '', '', 'token necunoscut: fără cookie' );
$_COOKIE['ah_spp'] = $COOKIE_M;
egal( (int) ah_spp_el( cerere( null, array( 't' => $TOK2 ) ) )['id'], (int) $R1['id'], 'cookie-ul are întâietate față de ?t=' );
unset( $_COOKIE['ah_spp'] );
egal( ah_spp_el( cerere( null, array( 't' => $TOK2 ) ) )['nume'], 'Radu', '„azi” merge și cu ?t= (aplicația)' );

/* ---------- 4. „azi”: datele minime ---------- */
$_COOKIE['ah_spp'] = $COOKIE_M;
$A = date_r( ah_spp_r_azi( cerere() ) );
egal( $A['stare'], 'ok', 'azi: stare ok' );
egal( array( $A['faza'], $A['zi'], $A['lungime'] ), array( 'primavara', 10, 28 ), 'ziua 10 din 28 → primăvara (CI.faze)' );
egal( $A['pana_la'], zi( -1 ), 'primăvara ține până în ziua 11 (oS-1 = 11): mâine' );
egal( $A['urmatoarea']['faza'] . '|' . $A['urmatoarea']['de_la'], 'vara|' . zi( -2 ), 'urmează vara, din ziua 12: poimâine' );
egal( $A['nume_ea'], 'Ana', 'prenumele ei, cum l-a ales ea' );
$CHEI = array( 'ok', 'nume_ea', 'nume', 'stare', 'faza', 'zi', 'lungime', 'pana_la', 'urmatoarea', 'inceputuri', 'harta', 'texte', 'azi', 'setari' );
egal( array_keys( $A ), $CHEI, 'azi: doar cheile din contract' );
egal( array_keys( $A['texte'] ), array( 'ce', 'sprijin', 'citate', 'arhetip', 'luna' ), 'texte: doar ale Adelei' );
$JA = json_encode( $A, JSON_UNESCAPED_UNICODE );
foreach ( array( 'NOTITA-SECRETA', '"sit"', 'natural', 'note', '"tri"', '"cra"', 'starturi', zi( 37 ), zi( 65 ), 'cheie-veche', 'mihai@', 'ana@', '"men"', 'partener' ) as $nu ) {
	verifica( false === strpos( $JA, $nu ), 'azi nu conține: ' . $nu );
}
verifica( false === strpos( $JA, '"' . zi( 9 ) . '"' ), 'azi nu conține ziua notată de ea (prima zi a menstruației)' );
egal( $A['texte']['ce'], ah_sp_partener_texte()['primavara']['ce'], '„ce trăiește” din Harta ciclului (41)' );
egal( $A['texte']['citate'], ah_sp_partener_texte()['primavara']['citate'], 'citatele din Magia Ciclului Feminin (235)' );
$prima = $A['inceputuri'][0] ?? array();
egal( $prima, array( 'faza' => 'vara', 'data' => zi( -2 ) ), 'începuturile: prima fază viitoare' );
verifica( count( $A['inceputuri'] ) >= 24 && count( $A['inceputuri'] ) <= 28, 'începuturile: 6 luni (~4 pe ciclu)' );
$ultima = end( $A['inceputuri'] );
verifica( $ultima['data'] <= date( 'Y-m-d', strtotime( '+183 days' ) ), 'începuturile: cel mult 6 luni înainte' );
verifica( ! array_filter( $A['inceputuri'], function ( $x ) { return $x['data'] <= date( 'Y-m-d' ); } ), 'începuturile: doar în viitor' );

/* pauza și situațiile */
$sit = array( 'natural' => 'ok', 'alaptare' => 'ok', 'perimenopauza' => 'ok', 'hormonal' => 'pauza', 'sarcina' => 'pauza', 'altceva' => 'ok', null => 'ok' );
foreach ( $sit as $s => $st ) {
	$c = array( 'starturi' => array( zi( 9 ) ), 'lung' => 28, 'men' => 5 );
	if ( '' !== (string) $s ) { $c['sit'] = $s; }
	ciclu_in_cont( $ANA, $c );
	$A = date_r( ah_spp_r_azi( cerere() ) );
	egal( $A['stare'], $st, 'situația ' . var_export( $s, true ) . ' → ' . $st );
	if ( 'pauza' === $st ) {
		egal( array_keys( $A ), array( 'ok', 'nume_ea', 'nume', 'stare', 'mesaj', 'sub', 'setari' ), 'pauza: fără faze, fără date' );
		egal( $A['mesaj'], 'Pentru moment, aici nu e nicio estimare.', 'pauza: textul neutru (nu spune de ce)' );
		verifica( false === strpos( json_encode( $A, JSON_UNESCAPED_UNICODE ), $s ), 'pauza: situația ei nu apare' );
	}
}
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( -3 ) ), 'lung' => 28, 'men' => 5 ) );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'pauza', 'prima zi în viitor → pauza' );
ciclu_in_cont( $ANA, array( 'starturi' => array( 'nu-e-data' ), 'lung' => 28 ) );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'pauza', 'fără date valide → pauza' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 30 ) ), 'lung' => 28, 'men' => 5 ) );
$A = date_r( ah_spp_r_azi( cerere() ) );
egal( array_keys( $A ), array( 'ok', 'nume_ea', 'nume', 'stare', 'mesaj', 'sub', 'setari' ), 'menstruația întârzie o zi (ziua 31 din 28): pauza, fără zi, fază sau date' );
$P = $A;
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 27 ) ), 'lung' => 28, 'men' => 5 ) );
$A = date_r( ah_spp_r_azi( cerere() ) );
egal( array( $A['stare'], $A['faza'], $A['zi'] ), array( 'ok', 'toamna', 28 ), 'ultima zi estimată (ziua 28 din 28): încă toamna' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 35 ) ), 'lung' => 28, 'men' => 5 ) );
$A = date_r( ah_spp_r_azi( cerere() ) );
egal( $A, $P, 'întârziere de 1 zi și de 8 zile: același răspuns, deci el nu poate număra zilele' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'hormonal' ) );
egal( date_r( ah_spp_r_azi( cerere() ) ), $P, 'întârzierea arată exact ca orice altă pauză (aici: situația hormonal)' );
$GLOBALS['wpdb']->delete( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $ANA ) ) );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'pauza', 'ea a șters ciclul → pauza' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ), zi( 65 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
unset( $_COOKIE['ah_spp'] );
$r = ah_spp_r_azi( cerere() );
egal( array( $r->get_status(), date_r( $r )['stare'] ), array( 401, 'iesit' ), 'fără cookie: 401' );
egal( $r->get_headers()['Cache-Control'] ?? '', 'no-store, private', 'răspunsurile nu se țin în cache' );

/* ---------- 5. ea vede cine a intrat; „Trimite din nou” schimbă tokenul ---------- */
ea( $ANA );
$L = date_r( ah_spp_r_parteneri( cerere() ) )['parteneri'];
egal( array( $L[0]['stare'], $L[0]['vizita'], $L[1]['stare'] ), array( 'a_intrat', date( 'Y-m-d' ), 'invitat' ), 'ea vede: Mihai a intrat azi, Radu încă nu' );
fara_limite();
$COOKIE_R = ah_spp_valoare_cookie( ah_spp_rand( $L[1]['id'] ), time() + 3600 ); // un telefon pe care a deschis linkul
$_COOKIE['ah_spp'] = $COOKIE_R;
egal( ah_spp_din_cookie()['nume'] ?? '', 'Radu', 'înainte de „Trimite din nou”: telefonul lui Radu e conectat' );
$j = date_r( ah_spp_r_retrimite( cerere( array( 'id' => $L[1]['id'] ) ) ) );
egal( ah_spp_din_cookie(), null, '„Trimite din nou invitația”: sesiune nouă, orice cookie vechi al lui nu mai merge' );
unset( $_COOKIE['ah_spp'] );
egal( array( $j['ok'], $j['email'] ), array( true, true ), 'Trimite din nou invitația' );
$TOK2b = token_din_mail( ultimul_mail() );
verifica( $TOK2b !== $TOK2, 'invitația nouă are alt token' );
egal( ah_spp_rand_token( $TOK2 ), null, 'tokenul vechi nu mai merge' );
egal( ah_spp_rand_token( $TOK2b )['nume'], 'Radu', 'tokenul nou merge' );
ah_spp_r_retrimite( cerere( array( 'id' => $L[1]['id'] ) ) );
ah_spp_r_retrimite( cerere( array( 'id' => $L[1]['id'] ) ) );
egal( ah_spp_r_retrimite( cerere( array( 'id' => $L[1]['id'] ) ) )->get_status(), 429, 'Trimite din nou: cel mult 3 pe zi' );
$TOK2 = token_din_mail( ultimul_mail() );
ea( 'alta@exemplu.ro' );
egal( ah_spp_r_retrimite( cerere( array( 'id' => $L[1]['id'] ) ) )->get_status(), 404, 'alta nu poate retrimite invitația altcuiva' );
egal( ah_spp_r_opreste( cerere( array( 'id' => $L[0]['id'] ) ) )->get_status(), 404, 'alta nu poate opri accesul altcuiva' );
egal( date_r( ah_spp_r_parteneri( cerere() ) )['parteneri'], array(), 'alta nu vede partenerii Anei' );

/* ---------- 6. „Trimite-mi din nou linkul” (el) ---------- */
ea( '' );
fara_limite();
$GLOBALS['ah_test_mail_cadru'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$r1 = date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'necunoscut@exemplu.ro' ) ) ) );
$r2 = date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'Mihai@exemplu.ro' ) ) ) );
egal( $r1, $r2, 'același răspuns pentru o adresă necunoscută și una invitată' );
egal( count( $GLOBALS['ah_test_mail_cadru'] ), 1, 'emailul pleacă doar la adresa invitată' );
$TOK1b = token_din_mail( ultimul_mail() );
egal( ultimul_mail()['subiect'], 'Linkul tău nou: ciclul ei', 'emailul cu linkul nou' );
egal( (int) ah_spp_rand_token( $TOK1 )['id'], (int) $R1['id'], 'cererea (poate a altcuiva) nu-i strică linkul pe care îl are' );
egal( (int) ah_spp_rand_token( $TOK1b )['id'], (int) $R1['id'], 'linkul nou merge' );
egal( ah_spp_rand_token( $TOK1 ), null, 'după prima folosire a linkului nou, cel vechi nu mai merge' );
egal( (int) ah_spp_rand_token( $TOK1b )['id'], (int) $R1['id'], 'linkul nou rămâne valabil' );
egal( ah_spp_rand( $R1['id'] )['token_nou_h'], '', 'linkul nou a devenit cel curent' );
$_COOKIE['ah_spp'] = $COOKIE_M;
verifica( null !== ah_spp_din_cookie(), 'telefonul pe care era deja conectat rămâne conectat' );
date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'mihai@exemplu.ro' ) ) ) );
date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'mihai@exemplu.ro' ) ) ) );
egal( count( $GLOBALS['ah_test_mail_cadru'] ), 3, 'aceeași adresă: încă 2 emailuri' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
$r3 = date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'mihai@exemplu.ro' ) ) ) );
egal( array( $r3, count( $GLOBALS['ah_test_mail_cadru'] ) ), array( $r1, 3 ), 'a 4-a cerere pe zi pentru aceeași adresă: tăcut, același răspuns, fără email' );
$TOK1 = token_din_mail( ultimul_mail() );
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
for ( $i = 0; $i < 5; $i++ ) { ah_spp_r_trimite_link( cerere( array( 'email' => 'x' . $i . '@exemplu.ro' ) ) ); }
egal( ah_spp_r_trimite_link( cerere( array( 'email' => 'y@exemplu.ro' ) ) )->get_status(), 429, 'de pe un IP: cel mult 5 pe oră' );
$n = count( $GLOBALS['ah_test_mail_cadru'] );
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
egal( date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'radu@exemplu.ro', 'site' => 'robot' ) ) ) ), $r1, 'capcana pentru roboți: același răspuns' );
egal( count( $GLOBALS['ah_test_mail_cadru'] ), $n, 'capcana: nimic trimis' );
egal( ah_spp_r_trimite_link( cerere( array( 'email' => 'nu-e' ) ) )->get_status(), 400, 'adresă greșită: 400' );
/* timpul: fără închiderea timpurie a cererii, orice răspuns durează cel puțin AH_SPP_LINK_MS */
fara_limite();
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
$t0 = microtime( true );
ah_spp_r_trimite_link( cerere( array( 'email' => 'nimeni@exemplu.ro' ) ) );
verifica( ( microtime( true ) - $t0 ) * 1000 >= AH_SPP_LINK_MS, 'adresă necunoscută: răspunsul durează cel puțin AH_SPP_LINK_MS (cât una invitată)' );
/* cu LiteSpeed / PHP-FPM: emailul pleacă după răspuns (la shutdown), nu în timpul lui */
if ( ! function_exists( 'fastcgi_finish_request' ) ) { // în if: altfel PHP o declară de la începutul fișierului
	function fastcgi_finish_request() { $GLOBALS['ah_test_inchis'] = ( $GLOBALS['ah_test_inchis'] ?? 0 ) + 1; return true; }
}
$n  = count( $GLOBALS['ah_test_mail_cadru'] );
$t0 = microtime( true );
egal( date_r( ah_spp_r_trimite_link( cerere( array( 'email' => 'mihai@exemplu.ro' ) ) ) ), $r1, 'cu închidere timpurie: același răspuns' );
verifica( ( microtime( true ) - $t0 ) * 1000 < AH_SPP_LINK_MS, 'cu închidere timpurie: fără așteptare' );
egal( count( $GLOBALS['ah_test_mail_cadru'] ), $n, 'emailul nu pleacă înainte de răspuns' );
ah_test_actiune( 'shutdown' );
egal( array( count( $GLOBALS['ah_test_mail_cadru'] ), $GLOBALS['ah_test_inchis'] ?? 0 ), array( $n + 1, 1 ), 'emailul pleacă după răspuns, după ce cererea s-a închis' );
$TOK1 = token_din_mail( ultimul_mail() );
$GLOBALS['ah_test_hooks']['shutdown'] = array();
fara_limite();

/* ---------- 7. setările lui: push, emailuri, ieși ---------- */
$_COOKIE['ah_spp'] = $COOKIE_M;
egal( date_r( ah_spp_r_push( cerere( array( 'token' => str_repeat( 'ab', 32 ), 'platforma' => 'ios' ) ) ) )['motiv'] ?? '', 'apns', 'tokenul APNs brut e refuzat' );
egal( date_r( ah_spp_r_push( cerere( array( 'token' => 'fcm-tok-mihai-telefon-1-' . str_repeat( 'x', 20 ), 'platforma' => 'altceva' ) ) ) )['motiv'] ?? '', 'platforma', 'platformă necunoscută' );
$PT1 = 'fcm-tok-mihai-telefon-1-' . str_repeat( 'x', 20 );
$PT2 = 'fcm-tok-mihai-telefon-2-' . str_repeat( 'y', 20 );
egal( date_r( ah_spp_r_push( cerere( array( 'token' => $PT1, 'platforma' => 'ios' ) ) ) ), array( 'ok' => true ), 'push: tokenul lui' );
ah_spp_r_push( cerere( array( 'token' => $PT1, 'platforma' => 'ios' ) ) );
ah_spp_r_push( cerere( array( 'token' => $PT2, 'platforma' => 'android' ) ) );
egal( count( ah_spp_push_ale_lui( $R1['id'] ) ), 2, 'push: același token o singură dată' );
verifica( $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_ah_spa_push' ) === 0 || '0' === (string) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_ah_spa_push' ), 'tokenurile lui stau separat de ale ei (ah_spa_push gol)' );
egal( date_r( ah_spp_r_azi( cerere() ) )['setari'], array( 'email_faze' => false, 'push' => true ), 'setările lui în „azi”' );
ah_spp_r_push_sterge( cerere( array( 'token' => $PT2 ) ) );
egal( count( ah_spp_push_ale_lui( $R1['id'] ) ), 1, 'DELETE /push' );
ah_spp_r_push( cerere( array( 'token' => $PT2, 'platforma' => 'android' ) ) );
egal( date_r( ah_spp_r_email( cerere( array( 'on' => true ) ) ) ), array( 'ok' => true, 'email_faze' => true ), 'emailurile fazelor: pornite' );
egal( (int) ah_spp_rand( $R1['id'] )['email_faze'], 1, 'emailurile fazelor: în tabel' );
unset( $_COOKIE['ah_spp'] );
egal( ah_spp_r_email( cerere( array( 'on' => true ) ) )->get_status(), 403, 'emailurile: fără cookie, 403' );
egal( ah_spp_r_push( cerere( array( 'token' => $PT1, 'platforma' => 'ios' ) ) )->get_status(), 403, 'push: fără cookie, 403' );
$_COOKIE['ah_spp'] = $COOKIE_M;
ah_spp_r_iesi( cerere() );
egal( $_COOKIE['ah_spp'] ?? '', '', 'Ieși: cookie-ul pleacă' );
verifica( null !== ah_spp_rand( $R1['id'] ), 'Ieși: accesul rămâne (intră din nou cu linkul)' );
$_COOKIE['ah_spp'] = $COOKIE_M;

/* ---------- 8. faza nouă: push / email, o singură dată ---------- */
$GLOBALS['ah_test_mail_cadru'] = array();
$GLOBALS['ah_test_http'] = array();
egal( ah_spp_verifica_toti(), array( (int) $R1['id'] => '', (int) $L[1]['id'] => 'notat' ), 'cron: fără schimbare la el; Radu (neintrat) doar notat' );
// ziua 12: începe vara (ea a notat o menstruație cu 11 zile în urmă)
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 11 ), zi( 39 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$v = ah_spp_verifica_toti();
egal( $v[ (int) $R1['id'] ], 'email', 'vara începe azi: fără modulul aplicație, doar emailul (a cerut emailuri)' );
verifica( false !== strpos( json_encode( get_option( 'ah_spp_jurnal' ), JSON_UNESCAPED_UNICODE ), 'push OPRIT (modulul aplicație nu e activ)' ), 'push fără modulul aplicație: doar jurnal' );
egal( $v[ (int) $L[1]['id'] ], 'notat', 'Radu n-a intrat încă: nimic trimis' );
$m = ultimul_mail();
egal( array( $m['catre'], $m['subiect'] ), array( 'mihai@exemplu.ro', 'Începe vara ei interioară' ), 'emailul fazei' );
verifica( false !== strpos( $m['a']['dezabonare'], 'fara-emailuri=' ), 'emailul fazei are link de oprire' );
verifica( false === strpos( json_encode( $m, JSON_UNESCAPED_UNICODE ), 'NOTITA' ) && false === strpos( json_encode( $m ), zi( 11 ) ), 'emailul fazei: fără notițe, fără date notate' );
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], '', 'a doua rulare în aceeași zi: nimic' );
// modulul aplicație activ (funcțiile FCM ale lui ah-sp-aplicatie.php)
eval( 'function ah_spa_fcm_cont(){ return array( "project_id" => "adela-haru", "client_email" => "x@y", "private_key" => "k" ); } function ah_spa_fcm_acces( $sa ){ return "jeton"; } function ah_spa_fcm_mesaj( $t, $ti, $tx, $d ){ return array( "message" => array( "token" => $t, "notification" => array( "title" => $ti, "body" => $tx ), "data" => $d ) ); }' );
$GLOBALS['ah_test_http_raspuns'] = function ( $url, $a ) use ( $PT2 ) {
	$b = json_decode( $a['body'], true );
	return $b['message']['token'] === $PT2 ? array( 'response' => array( 'code' => 404 ), 'body' => '{"error":{"status":"NOT_FOUND","details":[{"errorCode":"UNREGISTERED"}]}}' ) : array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
};
// ziua 17: începe toamna
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 16 ), zi( 44 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$GLOBALS['ah_test_http'] = array();
$v = ah_spp_verifica_toti();
egal( $v[ (int) $R1['id'] ], 'push+email', 'toamna: push și email' );
egal( count( $GLOBALS['ah_test_http'] ), 2, 'push: câte o cerere pe telefon' );
$b = json_decode( $GLOBALS['ah_test_http'][0][1]['body'], true );
egal( array( $b['message']['notification']['title'], $b['message']['data']['url'] ), array( 'Începe toamna ei interioară', 'https://www.adelaharu.ro/pentru-el/' ), 'push: textul și pagina lui' );
egal( count( ah_spp_push_ale_lui( $R1['id'] ) ), 1, 'tokenul necunoscut lui Firebase s-a șters' );
// corectare din urmă: ea mută data înapoi, faza „nouă” a început acum 5 zile → doar notat
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 4 ), zi( 32 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$GLOBALS['ah_test_http'] = array();
$n = count( $GLOBALS['ah_test_mail_cadru'] );
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], 'notat', 'faza schimbată din urmă (corectare): fără mesaj' );
egal( array( count( $GLOBALS['ah_test_http'] ), count( $GLOBALS['ah_test_mail_cadru'] ) ), array( 0, $n ), 'corectarea: nimic trimis' );
// menstruația nouă notată azi → iarna începe azi → mesaj (și declanșatorul după ciclu-date)
ea( $ANA );
$GLOBALS['ah_test_cron'] = array();
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 0 ), zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$rasp = new WP_REST_Response( array( 'ok' => true, 'actualizat' => '2026-11-18 10:00:00' ) );
ah_test_filtru( 'rest_request_after_callbacks', $rasp, null, new WP_REST_Request( array(), null, array(), '/ah-sp/v1/ciclu-date', 'POST' ) );
egal( array( $GLOBALS['ah_test_cron'][0][1] ?? '', $GLOBALS['ah_test_cron'][0][2] ?? '' ), array( 'ah_spp_verifica', array( ah_sp_ciclu_cheie( $ANA ) ) ), 'după ce ea își salvează ciclul: verificare programată' );
verifica( ( $GLOBALS['ah_test_cron'][0][0] ?? 0 ) - time() <= 120, 'verificarea: în 2 minute' );
ah_test_filtru( 'rest_request_after_callbacks', $rasp, null, new WP_REST_Request( array(), null, array(), '/ah-sp/v1/ciclu-date', 'POST' ) );
egal( count( $GLOBALS['ah_test_cron'] ), 1, 'nu se programează de două ori' );
$GLOBALS['ah_test_http'] = array();
ah_test_actiune( 'ah_spp_verifica', ah_sp_ciclu_cheie( $ANA ) );
egal( count( $GLOBALS['ah_test_http'] ), 1, 'iarna începe azi: push trimis' );
egal( json_decode( $GLOBALS['ah_test_http'][0][1]['body'], true )['message']['notification']['title'], 'Începe iarna ei interioară', 'push: iarna' );
// situația devine „hormonal”: nimic, iar la revenire nu „anunțăm” faza din mijloc
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 0 ), zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'hormonal' ) );
$GLOBALS['ah_test_http'] = array();
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], '', 'pauza: nimic trimis' );
egal( ah_spp_rand( $R1['id'] )['faza_trimisa'], '', 'pauza: semnul fazei se golește' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 0 ), zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], 'notat', 'după pauză: doar notat' );
egal( count( $GLOBALS['ah_test_http'] ), 0, 'după pauză: fără push' );
// oprirea emailurilor din linkul din email
la( '/pentru-el/', array( 'fara-emailuri' => explode( 'fara-emailuri=', $m['a']['dezabonare'] )[1] ? explode( '"', explode( 'fara-emailuri=', $m['a']['dezabonare'] )[1] )[0] : '' ) );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => 'fara_emailuri' ), 'linkul de oprire din email' );
egal( (int) ah_spp_rand( $R1['id'] )['email_faze'], 0, 'emailurile fazelor s-au oprit' );
la( '/pentru-el/', array( 'fara-emailuri' => $R1['id'] . '.' . str_repeat( 'a', 24 ) ) );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => 'link' ), 'link de oprire falsificat: refuzat' );
la( '/pentru-el/', array() );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => '' ), 'pagina simplă' );

/* ---------- 9. „Oprește accesul” (ea) ---------- */
ea( $ANA );
$j = date_r( ah_spp_r_opreste( cerere( array( 'id' => $R1['id'] ) ) ) );
egal( array( $j['ok'], count( $j['parteneri'] ) ), array( true, 1 ), 'Oprește accesul: pe loc, dispare din lista ei' );
egal( count( ah_spp_push_ale_lui( $R1['id'] ) ), 0, 'oprit: tokenurile push s-au șters' );
$_COOKIE['ah_spp'] = $COOKIE_M;
$A = date_r( ah_spp_r_azi( cerere() ) );
egal( array( $A['stare'], array_keys( $A ) ), array( 'oprit', array( 'ok', 'nume_ea', 'nume', 'stare', 'mesaj', 'sub', 'setari' ) ), 'el vede „oprit”, fără nimic din ciclu' );
egal( ah_spp_r_push( cerere( array( 'token' => $PT1, 'platforma' => 'ios' ) ) )->get_status(), 403, 'oprit: nu-și mai poate pune notificări' );
egal( ah_spp_r_email( cerere( array( 'on' => true ) ) )->get_status(), 403, 'oprit: nu-și mai poate porni emailurile' );
$GLOBALS['ah_test_http'] = array();
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 5 ), zi( 33 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
verifica( ! isset( ah_spp_verifica_toti()[ (int) $R1['id'] ] ), 'oprit: cronul nu-l mai verifică' );
la( '/pentru-el/', array( 't' => $TOK1 ) );
unset( $_COOKIE['ah_spp'] );
egal( ah_spp_proceseaza_el(), array( 'redirect' => 'https://www.adelaharu.ro/pentru-el/' ), 'oprit: linkul îl duce la pagina lui…' );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'oprit', '…care spune „oprit”' );
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) ) )['ok'] ?? false, true, 'după oprire, îl poate invita din nou (rând nou)' );
$R3 = ultim_rand();
verifica( $R3['token_h'] !== $R1['token_h'] && (int) $R3['id'] !== (int) $R1['id'], 'invitația nouă: alt rând, alt token' );
fara_limite();
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Dan', 'email' => 'dan@exemplu.ro', 'acord' => true ) ) ) )['motiv'] ?? '', 'max', 'tot cel mult 2 activi' );
fara_limite();
for ( $i = 0; $i < 6; $i++ ) { ah_spp_limita( 'invita|' . ah_sp_ciclu_cheie( $ANA ), 99, DAY_IN_SECONDS ); }
ah_spp_r_opreste( cerere( array( 'id' => $R3['id'] ) ) );
egal( ah_spp_r_invita( cerere( array( 'nume' => 'Dan', 'email' => 'dan@exemplu.ro', 'acord' => true ) ) )->get_status(), 429, 'cel mult 6 invitații pe zi' );
fara_limite();

/* ---------- 10. el își șterge accesul ---------- */
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( ah_spp_rand( $L[1]['id'] ), time() + 1000 );
egal( ah_spp_r_sterge( cerere( array() ) )->get_status(), 400, 'Șterge accesul meu: cere confirmare' );
ah_spp_r_push( cerere( array( 'token' => 'fcm-tok-radu-' . str_repeat( 'z', 30 ), 'platforma' => 'android' ) ) );
egal( date_r( ah_spp_r_sterge( cerere( array( 'confirmare' => true ) ) ) ), array( 'ok' => true ), 'Șterge accesul meu' );
egal( ah_spp_rand( $L[1]['id'] ), null, 'rândul lui a plecat' );
egal( count( ah_spp_push_ale_lui( $L[1]['id'] ) ), 0, 'și tokenurile push' );
egal( $_COOKIE['ah_spp'] ?? '', '', 'și cookie-ul' );

/* ---------- 11. curățenia ---------- */
global $wpdb;
$BIA = 'bia@exemplu.ro';
ciclu_in_cont( $BIA, array( 'starturi' => array( zi( 3 ) ), 'lung' => 30, 'men' => 4, 'sit' => 'natural' ) );
ea( $BIA );
ah_spp_r_invita( cerere( array( 'nume' => 'Vlad', 'email' => 'vlad@exemplu.ro', 'nume_ea' => 'Bia', 'acord' => true ) ) );
$RB = ultim_rand();
ea( $ANA );
ah_spp_r_invita( cerere( array( 'nume' => 'Sorin', 'email' => 'sorin@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RS = ultim_rand();
$wpdb->insert( 'wp_ah_spp_push', array( 'partener' => $RS['id'], 'token' => 't-sorin', 'token_h' => hash( 'sha256', 't-sorin' ), 'platforma' => 'ios', 'creat' => date( 'Y-m-d H:i:s' ), 'vazut' => date( 'Y-m-d H:i:s' ) ) );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s', time() - 31 * 86400 ) ), array( 'id' => $R1['id'] ) );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s', time() - 29 * 86400 ) ), array( 'id' => $R3['id'] ) );
egal( ah_spp_curata(), 1, 'curățenia: oprit de peste 30 de zile → șters' );
egal( ah_spp_rand( $R1['id'] ), null, 'oprit de 31 de zile: șters' );
verifica( null !== ah_spp_rand( $R3['id'] ), 'oprit de 29 de zile: încă acolo (el vede „oprit”)' );
// ea își șterge ciclul din cont (235: ciclu-sterge) → toate accesele ei pleacă pe loc
ah_test_filtru( 'rest_request_after_callbacks', new WP_REST_Response( array( 'ok' => true ) ), null, new WP_REST_Request( array(), null, array(), '/ah-sp/v1/ciclu-sterge', 'POST' ) );
egal( count( ah_spp_ale_ei( ah_sp_ciclu_cheie( $ANA ), true ) ), 0, 'ciclu-sterge: accesele ei s-au șters' );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spp_push WHERE partener = ' . (int) $RS['id'] ), 0, 'ciclu-sterge: și tokenurile push' );
verifica( null !== ah_spp_rand( $RB['id'] ), 'partenerul altei femei rămâne' );
// ciclul dispare pe alt drum (ex. ștergerea directă din tabel): curățenia zilnică îl prinde
ea( $ANA );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 3 ) ), 'lung' => 28, 'men' => 5 ) );
fara_limite();
ah_spp_r_invita( cerere( array( 'nume' => 'Sorin', 'email' => 'sorin@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$wpdb->delete( 'wp_ah_sp_ciclu', array( 'cheie' => ah_sp_ciclu_cheie( $ANA ) ) );
egal( ah_spp_curata(), 1, 'curățenia zilnică: rândul ei din ah_sp_ciclu lipsește → accesul lui pleacă' );
verifica( null !== ah_spp_rand( $RB['id'] ), 'Bia neatinsă' );
// contul ei șters din aplicație (ah_spa_cont_sters)
ah_test_actiune( 'ah_spa_cont_sters', $BIA );
egal( ah_spp_rand( $RB['id'] ), null, 'ah_spa_cont_sters: accesele ei s-au șters' );
egal( count( randuri() ), 0, 'nimic rămas' );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spp_push' ), 0, 'niciun token push rămas' );

/* ---------- 12. pagina lui și partea ei în pagină ---------- */
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ), array( zi( 1 ) => array( 'text' => 'NOTITA-SECRETA' ) ) );
ea( $ANA );
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RM = ultim_rand();
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RM, time() + 1000 );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Safari/604.1';
$h = ah_spp_pagina_el( ah_spp_cfg_el() );
verifica( false !== strpos( $h, '<title>Ciclul ei · Adela Haru</title>' ) && false !== strpos( $h, 'noindex' ), 'pagina lui: titlu, noindex' );
foreach ( array( 'mihai@', 'ana@', 'NOTITA', '"sit"', 'starturi', zi( 37 ) ) as $nu ) {
	verifica( false === strpos( $h, $nu ), 'pagina lui nu conține: ' . $nu );
}
verifica( false !== strpos( $h, 'magia-ciclului-feminin' ) && false !== strpos( $h, 'fonts.googleapis.com' ), 'browser: cărțile (spp-carte) și Cormorant de la Google' );
$cfg = ah_spp_cfg_el();
egal( count( $cfg['invata'] ), 8, 'Învață: 8 pagini' );
egal( array_column( $cfg['invata'], 'id' ), array( 'anotimpuri', 'iarna', 'primavara', 'vara', 'toamna', 'vorbeste', 'dorinta', 'despre' ), 'Învață: paginile' );
$ji = json_encode( $cfg['invata'], JSON_UNESCAPED_UNICODE );
verifica( false !== strpos( $ji, 'nu este o metodă de contracepție' ), 'Învață: nota despre contracepție' );
verifica( false !== strpos( $ji, 'Menstruația conștientă' ) && false !== strpos( $ji, 'Energia sexuală în fazele ciclului feminin' ), 'Învață: episoadele de podcast' );
verifica( false !== strpos( $ji, ah_ciclu_faze()['premenstruala']['simti'] ), 'Învață: textele din Harta ciclului (41)' );
verifica( false === strpos( $ji, '"mai"' ) && false === strpos( $ji, 'wild-love' ), 'Învață: fără „Mai departe” (produsele) de la podcast' );
verifica( ! preg_match( '/\bnu\b[^.]{0,80}\bci\b/u', $ji ), 'Învață: fără construcții „nu… ci”' );
// în aplicație: fără cărți, fără Google Fonts
eval( 'function ah_spa_e_aplicatie(){ return ! empty( $GLOBALS["ah_test_app"] ); }' );
$GLOBALS['ah_test_app'] = true;
$h = ah_spp_pagina_el( ah_spp_cfg_el() );
verifica( false === strpos( $h, 'product\\/' ) && false === strpos( $h, 'magia-ciclului-feminin' ) && false === strpos( $h, 'fonts.googleapis.com' ) && false !== strpos( $h, 'class="spp-app"' ), 'aplicație: fără cărți, fără Google Fonts' );
// în aplicație, pe /spatiul-meu/ fără contul ei, cu al lui → la pagina lui
ea( '' );
la( '/spatiul-meu/' );
egal( ah_spp_redirect_aplicatie(), 'https://www.adelaharu.ro/pentru-el/', 'aplicație: Spațiul meu fără contul ei → pagina lui' );
la( '/spatiul-meu/', array( 'cont' => 'abc' ) );
egal( ah_spp_redirect_aplicatie(), '', 'aplicație: cu ?cont= (linkul ei) nu' );
la( '/spatiul-meu/' );
ea( $ANA );
egal( ah_spp_redirect_aplicatie(), '', 'aplicație: cu contul ei, rămâne în Spațiul meu' );
ea( '' );
$GLOBALS['ah_test_app'] = false;
egal( ah_spp_redirect_aplicatie(), '', 'browser: nicio redirecționare' );
la( '/pentru-el/' );
verifica( ah_spp_e_pagina_el(), '/pentru-el/ e pagina lui' );
la( '/pentru-el' );
verifica( ah_spp_e_pagina_el(), '/pentru-el (fără /) e pagina lui' );
la( '/pentru-ele/' );
verifica( ! ah_spp_e_pagina_el(), '/pentru-ele/ nu' );
// partea ei: doar pe Spațiul meu
$GLOBALS['ah_test_e_pagina'] = false;
egal( ah_test_actiune( 'wp_head' ), '', 'în afara Spațiului meu: nimic în <head>' );
$GLOBALS['ah_test_e_pagina'] = true;
ea( $ANA );
$h = ah_test_actiune( 'wp_head' );
verifica( false !== strpos( $h, 'id="ahspp-js"' ) && false !== strpos( $h, '"cont":true' ), 'Spațiul meu: cardul ei' );
verifica( false === strpos( $h, 'ana@' ), 'fără emailul ei în pagină' );
verifica( false !== strpos( $h, 'Sunt de acord ca' ) && false !== strpos( $h, 'să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.' ), 'textul exact al acordului' );
ea( '' );
verifica( false !== strpos( ah_test_actiune( 'wp_head' ), '"cont":false' ), 'ecranul de intrare: „Sunt partenerul ei”' );
// fără 235 nu face nimic: verificat prin ah_spp_gata() (toate cârligele îl întreabă întâi)
$src = file_get_contents( __DIR__ . '/../../ah-sp-partener.php' );
foreach ( array( 'ah_spp_template_redirect', 'ah_spp_head_ea', 'ah_spp_verifica_toti', 'ah_spp_curata' ) as $f ) {
	verifica( (bool) preg_match( '/function ' . $f . '\([^)]*\) \{\n(\t[^\n]*\n){0,3}?\tif \( ! ah_spp_gata\(\)/', $src ), $f . ' întreabă întâi ah_spp_gata()' );
}

echo "\n" . $GLOBALS['ok'] . ' verificări trecute, ' . $GLOBALS['rau'] . " picate.\n";
exit( $GLOBALS['rau'] ? 1 : 0 );
