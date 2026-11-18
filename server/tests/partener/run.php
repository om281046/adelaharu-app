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
function cerere( $json = null, $param = array(), $antet = array( 'x_ah_spp' => '1' ) ) { return new WP_REST_Request( $antet, $json, $param ); }
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
egal( AH_SPP, '1.4.0', 'versiunea fragmentului' );
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
egal( $GLOBALS['ah_test_cron'][0][3] ?? null, null, 'cronul: eveniment unic (lanț), nu „daily” (86400 s alunecă la schimbarea orei)' );
ah_test_actiune( 'init' );
egal( count( $GLOBALS['ah_test_cron'] ), 1, 'cronul: un al doilea init nu-l programează din nou' );

/* ---------- 1. criptarea, amprentele, tokenurile ---------- */
$c1 = ah_spp_cripteaza( 'el@exemplu.ro' );
$c2 = ah_spp_cripteaza( 'el@exemplu.ro' );
verifica( $c1 !== $c2, 'aceeași adresă se criptează diferit de fiecare dată' );
egal( ah_spp_decripteaza( $c1 ), 'el@exemplu.ro', 'decriptarea' );
$b = base64_decode( $c1 ); $b[ strlen( $b ) - 1 ] = chr( ord( $b[ strlen( $b ) - 1 ] ) ^ 1 );
egal( ah_spp_decripteaza( base64_encode( $b ) ), null, 'un text atins nu se decriptează (GCM)' );
egal( ah_sp_ciclu_decripteaza( $c1 ), null, 'cheia e separată de a ciclului din 235' );
egal( get_option( 'ah_spp_secret' ), false, 'cheia NU e în baza de date (nicio opțiune ah_spp_secret)' );
egal( ah_spp_secret(), hash_hmac( 'sha256', 'ah-spp|cheie', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) ), 'cheia: derivată din sărurile WordPress (fără AH_SPP_CHEIE)' );
egal( ah_spp_decripteaza( $c1, 'alta-cheie' ), null, 'cu altă cheie nu se decriptează' );
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
egal( array( $R1['acord_v'], AH_SPP_ACORD_V ), array( '2026-11-18', '2026-11-18' ), 'versiunea textului acordului e păstrată lângă acord (GDPR art. 7 alin. 1)' );
verifica( false === strpos( json_encode( $j ), 'acord_v' ), 'versiunea acordului nu apare în lista ei' );
$m = ultimul_mail();
egal( $m['catre'], 'mihai@exemplu.ro', 'invitația merge la el' );
egal( $m['subiect'], 'Ana te invită să-i cunoști ciclul', 'subiectul, cu prenumele ei' );
verifica( false !== strpos( $m['a']['continut'], 'Ana: „Ți-am pregătit ceva, cu textele Adelei Haru: aici vezi în ce anotimp al ciclului sunt și cum mă poți sprijini.”' ), 'invitația: rândul ei, cu textul de pe site (mesajul de WhatsApp din 235)' );
verifica( false !== strpos( $m['a']['motiv'], 'https://www.adelaharu.ro/politica-de-confidentialitate/' ) && false !== strpos( $m['a']['motiv'], 'Datele tale sunt prelucrate de HARU TRAINING SRL, operatorul site-ului adelaharu.ro.' ), 'invitația: operatorul și linkul spre politica de confidențialitate (GDPR art. 14)' );
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
egal( $A['faza'], 'primavara', 'ziua 10 din 28 → primăvara (CI.faze)' );
egal( $A['pana_la'], zi( -1 ), 'primăvara ține până în ziua 11 (oS-1 = 11): mâine' );
egal( $A['urmatoarea']['faza'] . '|' . $A['urmatoarea']['de_la'], 'vara|' . zi( -2 ), 'urmează vara, din ziua 12: poimâine' );
egal( $A['nume_ea'], 'Ana', 'prenumele ei, cum l-a ales ea' );
$CHEI = array( 'ok', 'nume_ea', 'nume', 'stare', 'faza', 'pana_la', 'urmatoarea', 'inceputuri', 'texte', 'setari' );
egal( array_keys( $A ), $CHEI, 'azi: doar cheile din contract (fără zi, lungime, harta: pagina nu mai are inelul)' );
egal( array_keys( $A['urmatoarea'] ), array( 'faza', 'de_la', 'pana_la', 'sprijin', 'arhetip' ), 'urmatoarea: doar ce arată „Ce urmează”' );
egal( $A['urmatoarea']['pana_la'], null, 'urmatoarea: fereastră doar pentru iarnă' );
egal( $A['urmatoarea']['sprijin'], ah_sp_partener_texte()['vara']['sprijin'][0], '„Ce urmează”: primul rând din „cum o poți sprijini” (235)' );
egal( array_keys( $A['texte'] ), array( 'ce', 'sprijin', 'citate', 'arhetip', 'luna' ), 'texte: doar ale Adelei' );
$JA = json_encode( $A, JSON_UNESCAPED_UNICODE );
foreach ( array( 'NOTITA-SECRETA', '"sit"', 'natural', 'note', '"tri"', '"cra"', 'starturi', zi( 37 ), zi( 65 ), 'cheie-veche', 'mihai@', 'ana@', '"men"', 'partener' ) as $nu ) {
	verifica( false === strpos( $JA, $nu ), 'azi nu conține: ' . $nu );
}
verifica( false === strpos( $JA, '"' . zi( 9 ) . '"' ), 'azi nu conține ziua notată de ea (prima zi a menstruației)' );
egal( $A['texte']['ce'], ah_sp_partener_texte()['primavara']['ce'], '„ce trăiește” din Harta ciclului (41)' );
egal( $A['texte']['citate'], ah_sp_partener_texte()['primavara']['citate'], 'citatele din Magia Ciclului Feminin (235)' );
egal( $A['inceputuri'], array( array( 'faza' => 'vara', 'data' => zi( -2 ), 'pana' => null ), array( 'faza' => 'toamna', 'data' => zi( -7 ), 'pana' => null ), array( 'faza' => 'iarna', 'data' => zi( -17 ), 'pana' => zi( -21 ) ) ), 'începuturile: fazele până la iarna următoare, iar ea ca fereastră (ziua 29 ± 2), nimic după ea' );
verifica( ! array_filter( $A['inceputuri'], function ( $x ) { return $x['data'] <= date( 'Y-m-d' ); } ), 'începuturile: doar în viitor' );

/* pauza și situațiile: hormonal/sarcină ajung la el ca pauză după 1–3 zile (sau la granița fazei), nu pe loc */
$RA = ah_spp_rand( $R1['id'] );
$NAT = date_r( ah_spp_r_azi( cerere() ) );
$sit = array( 'natural' => 'ok', 'alaptare' => 'ok', 'perimenopauza' => 'ok', 'hormonal' => 'pauza', 'sarcina' => 'pauza', 'altceva' => 'ok', null => 'ok' );
foreach ( $sit as $s => $st ) {
	$c = array( 'starturi' => array( zi( 9 ), zi( 37 ), zi( 65 ) ), 'lung' => 28, 'men' => 5 );
	if ( '' !== (string) $s ) { $c['sit'] = $s; }
	ciclu_in_cont( $ANA, $c );
	$A = date_r( ah_spp_r_azi( cerere() ) );
	if ( 'ok' === $st ) {
		egal( $A, $NAT, 'situația ' . var_export( $s, true ) . ' → la fel ca natural' );
		continue;
	}
	egal( $A, $NAT, 'situația ' . $s . ': în ziua schimbării el vede încă faza, ca înainte (nu află momentul)' );
	$rr = ah_spp_rand( $R1['id'] );
	verifica( (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $rr['pauza_de_la'] ), $s . ': ziua pauzei e păstrată pe server (pauza_de_la)' );
	$k = ah_spp_zi0( $rr['pauza_de_la'] ) - ah_spp_azi0();
	verifica( $k >= 1 && $k <= 2, $s . ': pauza vine în 1–3 zile sau la granița fazei (aici primăvara se termină mâine: ' . $k . ')' );
	egal( ah_spp_azi_pentru( $rr )['stare'], 'ok', $s . ': a doua cerere în aceeași zi, la fel' );
	egal( ah_spp_rand( $R1['id'] )['pauza_de_la'], $rr['pauza_de_la'], $s . ': ziua nu se trage din nou la fiecare cerere' );
	$A = ah_spp_azi_pentru( $rr, ah_spp_zi0( $rr['pauza_de_la'] ) );
	egal( array_keys( $A ), array( 'nume_ea', 'nume', 'stare', 'mesaj', 'sub' ), $s . ': din ziua aceea, pauza: fără faze, fără date' );
	egal( $A['mesaj'], 'Pentru moment, aici nu e nicio estimare.', 'pauza: textul neutru (nu spune de ce)' );
	verifica( false === strpos( json_encode( $A, JSON_UNESCAPED_UNICODE ), $s ), 'pauza: situația ei nu apare' );
	egal( ah_spp_azi_pentru( $rr, ah_spp_zi0( $rr['pauza_de_la'] ) - 1 )['stare'], 'ok', $s . ': cu o zi înainte, încă faza' );
	ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ), zi( 65 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
	egal( date_r( ah_spp_r_azi( cerere() ) ), $NAT, $s . ' → natural: faza, pe loc' );
	egal( ah_spp_rand( $R1['id'] )['pauza_de_la'], '', $s . ' → natural: ziua pauzei se golește' );
}
// amânarea: 1–3 zile, aceeași pentru același partener și aceeași zi, diferită între parteneri sau zile
$am = array();
for ( $i = 1; $i <= 60; $i++ ) { $am[ ah_spp_amanare( array( 'id' => $i, 'ea' => 'x' ), 20000 ) ] = 1; }
ksort( $am );
egal( array_keys( $am ), array( 1, 2, 3 ), 'amânarea pauzei: 1, 2 sau 3 zile' );
egal( ah_spp_amanare( array( 'id' => 7, 'ea' => 'x' ), 20000 ), ah_spp_amanare( array( 'id' => 7, 'ea' => 'x' ), 20000 ), 'amânarea: deterministă pentru aceeași schimbare' );
// în toamnă, granița următoare e iarna (ziua estimată a menstruației): nu se folosește, doar amânarea
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 26 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'sarcina' ) );
date_r( ah_spp_r_azi( cerere() ) );
$rr = ah_spp_rand( $R1['id'] );
egal( ah_spp_zi0( $rr['pauza_de_la'] ) - ah_spp_azi0(), ah_spp_amanare( $rr, ah_spp_azi0() ), 'toamna: pauza după amânare, nu în ziua estimată a menstruației' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 26 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
date_r( ah_spp_r_azi( cerere() ) );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( -3 ) ), 'lung' => 28, 'men' => 5 ) );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'pauza', 'prima zi în viitor → pauza' );
ciclu_in_cont( $ANA, array( 'starturi' => array( 'nu-e-data' ), 'lung' => 28 ) );
egal( date_r( ah_spp_r_azi( cerere() ) )['stare'], 'pauza', 'fără date valide → pauza' );
$GLOBALS['wpdb']->update( 'wp_ah_spp_parteneri', array( 'pauza_de_la' => '' ), array( 'id' => $R1['id'] ) );

/* ziua estimată a menstruației: nimic nu se schimbă la el (ziua dinainte, ziua estimată, ziua de după, capătul ferestrei) */
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 27 ), zi( 55 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) ); // azi = ziua 28
$P28 = date_r( ah_spp_r_azi( cerere() ) );
egal( array( $P28['stare'], $P28['faza'], $P28['pana_la'] ), array( 'ok', 'toamna', null ), 'ziua 28 din 28: toamna, „până la următoarea menstruație” (fără dată de sfârșit)' );
egal( array( $P28['urmatoarea']['faza'], $P28['urmatoarea']['de_la'], $P28['urmatoarea']['pana_la'] ), array( 'iarna', zi( 1 ), zi( -3 ) ), 'iarna următoare: fereastra ziua estimată ± 2 (nu o zi)' );
egal( $P28['inceputuri'], array( array( 'faza' => 'iarna', 'data' => zi( 1 ), 'pana' => zi( -3 ) ) ), 'în toamnă, începuturile: doar fereastra iernii' );
// cu ziua fixată (aceleași starturi, alt „azi”): răspunsul e identic, octet cu octet, în ziua dinainte, în ziua estimată și după
$rr = ah_spp_rand( $R1['id'] );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$a0 = ah_spp_azi0();
$V = array_map( function ( $d ) use ( $rr, $a0 ) { return json_encode( ah_spp_azi_pentru( $rr, $a0 + $d ) ); }, array( -1, 0, 1, 2 ) );
egal( count( array_unique( $V ) ), 1, 'ziua dinainte / ziua estimată / ziua de după / +2: răspunsuri identice' );
egal( json_decode( $V[0], true )['urmatoarea']['de_la'] . '|' . json_decode( $V[0], true )['urmatoarea']['pana_la'], zi( 2 ) . '|' . zi( -2 ), 'fereastra: ziua estimată (azi) ± 2' );
verifica( json_encode( ah_spp_azi_pentru( $rr, $a0 - 3 ) ) === $V[0], 'și cu 3 zile înainte (tot în toamnă), același răspuns' );
verifica( json_encode( ah_spp_azi_pentru( $rr, $a0 + 3 ) ) !== $V[0], 'după capătul ferestrei (+3) fereastra dispare' );
$A = ah_spp_azi_pentru( $rr, $a0 + 3 );
egal( array( $A['stare'], $A['faza'], $A['pana_la'], $A['urmatoarea']['de_la'], $A['urmatoarea']['pana_la'], $A['inceputuri'] ), array( 'ok', 'toamna', null, null, null, array() ), 'întârziere: toamna, fără nicio dată a iernii' );
$Q = array_map( function ( $d ) use ( $rr, $a0 ) { return json_encode( ah_spp_azi_pentru( $rr, $a0 + $d ) ); }, array( 3, 5, 8, 10 ) );
egal( count( array_unique( $Q ) ), 1, 'întârziere de 3, 5, 8, 10 zile: același răspuns (el nu poate număra zilele)' );
egal( ah_spp_azi_pentru( $rr, $a0 + 10 )['stare'], 'ok', 'a 10-a zi după ziua estimată: încă toamna' );
$A = ah_spp_azi_pentru( $rr, $a0 + 11 );
egal( array_keys( $A ), array( 'nume_ea', 'nume', 'stare', 'mesaj', 'sub' ), 'peste 10 zile de întârziere: pauza, fără zi, fază sau date' );
$P = $A;
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'hormonal' ) );
$GLOBALS['wpdb']->update( 'wp_ah_spp_parteneri', array( 'pauza_de_la' => zi( 1 ) ), array( 'id' => $R1['id'] ) );
egal( ah_spp_azi_pentru( ah_spp_rand( $R1['id'] ) ), $P, 'întârzierea lungă arată exact ca orice altă pauză (aici: situația hormonal)' );
$GLOBALS['wpdb']->update( 'wp_ah_spp_parteneri', array( 'pauza_de_la' => '' ), array( 'id' => $R1['id'] ) );
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
verifica( false !== strpos( ultimul_mail()['a']['motiv'], 'HARU TRAINING SRL' ) && false !== strpos( ultimul_mail()['a']['motiv'], '/politica-de-confidentialitate/' ), 'linkul nou: operatorul și politica' );
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
egal( array( $m['catre'], $m['subiect'] ), array( 'mihai@exemplu.ro', 'Ciclul ei: o fază nouă' ), 'emailul fazei: subiect neutru (fără numele fazei: se vede în lista de emailuri)' );
egal( $m['a']['titlu'], 'Începe Vara ei interioară', 'emailul fazei: faza, doar în corpul emailului' );
verifica( false !== strpos( $m['a']['motiv'], 'HARU TRAINING SRL' ) && false !== strpos( $m['a']['motiv'], '/politica-de-confidentialitate/' ), 'emailul fazei: operatorul și politica' );
verifica( false !== strpos( $m['a']['continut'], 'Fazele sunt o estimare: nu sunt o metodă de contracepție.' ), 'emailul fazei: nota, cu cuvintele de pe site' );
verifica( false !== strpos( $m['a']['dezabonare'], 'fara-emailuri=' ), 'emailul fazei are link de oprire' );
verifica( false === strpos( json_encode( $m, JSON_UNESCAPED_UNICODE ), 'NOTITA' ) && false === strpos( json_encode( $m ), zi( 11 ) ), 'emailul fazei: fără notițe, fără date notate' );
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], '', 'a doua rulare în aceeași zi: nimic' );
// modulul aplicație activ (funcțiile FCM ale lui ah-sp-aplicatie.php)
eval( 'function ah_spa_fcm_cont(){ return array( "project_id" => "adela-haru", "client_email" => "x@y", "private_key" => "k" ); } function ah_spa_fcm_acces( $sa ){ return empty( $GLOBALS["ah_test_fcm_fara"] ) ? "jeton" : ""; } function ah_spa_fcm_mesaj( $t, $ti, $tx, $d ){ return array( "message" => array( "token" => $t, "notification" => array( "title" => $ti, "body" => $tx ), "data" => $d ) ); }' );
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
egal( array( $b['message']['notification']['title'], $b['message']['data']['url'] ), array( 'Ciclul ei', 'https://www.adelaharu.ro/pentru-el/' ), 'push: titlul neutru „Ciclul ei” și pagina lui' );
egal( $b['message']['notification']['body'], 'Începe o fază nouă. Vezi cum o poți sprijini.', 'push: textul neutru (faza nu apare pe ecranul blocat)' );
verifica( ! preg_match( '/Toamna|Iarna|Vara|Primăvara|luteal|menstru/iu', json_encode( $b, JSON_UNESCAPED_UNICODE ) ), 'push: nimic despre fază în mesajul FCM' );
egal( $b['message']['android']['notification']['visibility'] ?? '', 'PRIVATE', 'push: Android, vizibilitate PRIVATE pe ecranul blocat' );
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
egal( json_decode( $GLOBALS['ah_test_http'][0][1]['body'], true )['message']['notification']['title'], 'Ciclul ei', 'push: iarna, tot cu titlul neutru' );
// situația devine „hormonal”: nimic, iar la revenire nu „anunțăm” faza din mijloc
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 0 ), zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'hormonal' ) );
$GLOBALS['ah_test_http'] = array();
$semn0 = ah_spp_rand( $R1['id'] )['faza_trimisa'];
egal( ah_spp_verifica_toti()[ (int) $R1['id'] ], '', 'hormonal, în zilele de amânare: nimic trimis' );
egal( ah_spp_rand( $R1['id'] )['faza_trimisa'], $semn0, 'hormonal, în zilele de amânare: semnul fazei rămâne' );
verifica( '' !== ah_spp_rand( $R1['id'] )['pauza_de_la'], 'cronul notează și el ziua pauzei' );
$GLOBALS['wpdb']->update( 'wp_ah_spp_parteneri', array( 'pauza_de_la' => zi( 0 ) ), array( 'id' => $R1['id'] ) );
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
egal( array( $A['mesaj'], $A['sub'] ), array( 'Linkul a fost oprit', 'Linkul acesta nu mai e activ. Dacă vrei să-l primești din nou, vorbește cu ea.' ), 'oprit: cuvintele paginii ?ciclul-ei (235)' );
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
egal( array_column( $cfg['invata'], 'id' ), array( 'faze', 'iarna', 'primavara', 'vara', 'toamna', 'podcast' ), 'Învață: 6 pagini' );
egal( array_column( $cfg['invata'], 'titlu' ), array( 'Cele 4 faze ale ciclului feminin', 'Iarna ei interioară', 'Primăvara ei interioară', 'Vara ei interioară', 'Toamna ei interioară', 'Din podcastul Intim Feminin' ), 'Învață: titlurile, numele de pe site' );
$ji = json_encode( $cfg['invata'], JSON_UNESCAPED_UNICODE );
verifica( false !== strpos( $cfg['nota'], 'Fazele sunt o estimare: nu sunt o metodă de contracepție.' ), 'nota despre contracepție, cu cuvintele lui 41' );
/* doar texte de pe site: fiecare paragraf, rând, citat și titlu de episod trebuie să existe în 41 sau 235 (sau să fie un nume/titlu
   compus din ele); nimic scris de noi */
$surse = file_get_contents( __DIR__ . '/../../../reference/v2/sn41.php' ) . file_get_contents( __DIR__ . '/../../../reference/v2/sn235.php' );
$compuse = array( 'Cele 4 faze ale ciclului feminin', 'Fragment din cartea Spre feminitate, de Adela Haru', 'Din podcastul Intim Feminin', 'Cum o poți sprijini', 'Ce le spune Adela femeilor, în Spre feminitate', 'Ce le spune Adela femeilor, în Magia Ciclului Feminin' );
$straine = array();
foreach ( $cfg['invata'] as $pg ) {
	foreach ( $pg['b'] as $bl ) {
		$txt = array();
		if ( in_array( $bl['t'], array( 'p', 'h' ), true ) ) { $txt[] = $bl['text']; }
		if ( in_array( $bl['t'], array( 'ul', 'citat' ), true ) ) { $txt = array_merge( $txt, $bl['l'] ); }
		if ( 'citat' === $bl['t'] ) { $txt[] = $bl['sursa']; }
		if ( 'podcast' === $bl['t'] ) { $txt[] = $bl['titlu']; $txt[] = $bl['text']; }
		foreach ( $txt as $t ) {
			if ( ! in_array( $t, $compuse, true ) && false === strpos( $surse, json_encode( $t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) && false === strpos( $surse, $t ) && false === strpos( $surse, str_replace( '„', '„', $t ) ) ) {
				$straine[] = $t;
			}
		}
	}
}
egal( $straine, array(), 'Învață: doar texte de pe site (41, 235)' );
foreach ( array( 'Adela vorbește despre ciclul feminin', 'Fazele sunt repere, nu reguli', 'Zilele dinaintea menstruației', 'Cum vorbești cu ea', 'Dorința ei poate veni', 'Despre pagina aceasta' ) as $nu ) {
	verifica( false === strpos( $ji, $nu ), 'Învață: fără textul nostru „' . $nu . '…”' );
}
egal( array_column( end( $cfg['invata'] )['b'], 'titlu' ), array( 'Menstruația conștientă', 'Fertilitatea conștientă', 'Despre nevoia de a fi iubit(ă) într-o relație', 'Despre limitele personale în relațiile apropiate', 'Energia sexuală în fazele ciclului feminin' ), 'Învață: episoadele 2–6 din podcast' );
verifica( false !== strpos( $ji, ah_ciclu_faze()['menstruala']['practica'] ) && false !== strpos( $ji, ah_ciclu_faze()['ovulatorie']['ajuta'][2] ), 'Învață: „ajuta” și „practica” din Harta ciclului (41)' );
egal( ah_spp_mesaj_faza( 'vara' ), array( 'Începe Vara ei interioară', ah_sp_partener_texte()['vara']['sprijin'][0] ), 'mesajul fazei: numele fazei + primul rând din „sprijin”' );
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

/* ---------- 13. antetul X-AH-SPP (apărare CSRF în plus față de SameSite=Lax) ---------- */
global $wpdb;
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
fara_limite();
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
ea( $ANA );
$fara = array();
foreach ( array( 'ah_spp_r_invita' => array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'acord' => true ), 'ah_spp_r_opreste' => array( 'id' => 1 ), 'ah_spp_r_retrimite' => array( 'id' => 1 ) ) as $f => $corp ) {
	$r = $f( cerere( $corp, array(), array() ) );
	$fara[] = $r->get_status() . ':' . ( date_r( $r )['motiv'] ?? '' );
}
egal( $fara, array( '403:antet', '403:antet', '403:antet' ), 'ea: invita/opreste/retrimite fără antetul X-AH-SPP → 403' );
egal( count( randuri() ), 0, 'fără antet: nimic salvat' );
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ), array(), array( 'X-AH-SPP' => '1' ) ) );
egal( date_r( $r )['ok'] ?? false, true, 'ea: cu antetul X-AH-SPP: 1, merge' );
$RM = ultim_rand();
ea( '' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RM, time() + 1000 );
$fara = array();
foreach ( array( 'ah_spp_r_email' => array( 'on' => true ), 'ah_spp_r_push' => array( 'token' => 'fcm-' . str_repeat( 'q', 40 ), 'platforma' => 'ios' ), 'ah_spp_r_iesi' => array(), 'ah_spp_r_sterge' => array( 'confirmare' => true ) ) as $f => $corp ) {
	$fara[] = $f( cerere( $corp, array(), array( 'x_ah_spp' => '0' ) ) )->get_status();
}
egal( $fara, array( 403, 403, 403, 403 ), 'el: email/push/iesi/sterge fără antet (sau cu altă valoare) → 403' );
verifica( null !== ah_spp_din_cookie() && null !== ah_spp_rand( $RM['id'] ) && 0 === (int) ah_spp_rand( $RM['id'] )['email_faze'], 'el, fără antet: nimic schimbat, rămâne conectat' );
egal( ah_spp_r_azi( cerere( null, array(), array() ) )->get_status(), 200, 'GET /azi nu cere antetul (nu schimbă nimic)' );
$src = file_get_contents( __DIR__ . '/../../ah-sp-partener.php' );
verifica( false !== strpos( $src, "h['X-AH-SPP']='1'" ) && false !== strpos( $src, "'X-AH-SPP':'1'" ), 'paginile (a lui și cardul ei) trimit antetul X-AH-SPP' );

/* ---------- 14. cronul: 8:10 ora site-ului și după schimbarea orei (lanț de evenimente unice) ---------- */
$GLOBALS['ah_test_cron'] = array();
$t = strtotime( '2026-10-23 12:00:00' ); // ora de vară se termină duminică, 25 oct. 2026, la Bucureşti
$ore = array();
for ( $i = 0; $i < 5; $i++ ) { $t = ah_spp_urmatoarea_rulare( $t ); $ore[] = date( 'Y-m-d H:i T', $t ); }
egal( $ore, array( '2026-10-24 08:10 EEST', '2026-10-25 08:10 EET', '2026-10-26 08:10 EET', '2026-10-27 08:10 EET', '2026-10-28 08:10 EET' ), 'cronul: 8:10 în fiecare zi, și după trecerea la ora de iarnă' );
$t = strtotime( '2027-03-27 09:00:00' );
egal( date( 'Y-m-d H:i T', ah_spp_urmatoarea_rulare( $t ) ), '2027-03-28 08:10 EEST', 'cronul: 8:10 și în ziua trecerii la ora de vară' );
egal( date( 'Y-m-d H:i', ah_spp_urmatoarea_rulare( strtotime( '2026-11-18 08:10:00' ) ) ), '2026-11-19 08:10', 'cronul: exact la 8:10 → mâine' );
egal( date( 'Y-m-d H:i', ah_spp_urmatoarea_rulare( strtotime( '2026-11-18 08:09:59' ) ) ), '2026-11-18 08:10', 'cronul: înainte de 8:10 → azi' );
// o rulare se reprogramează pe ea însăși; WordPress scoate evenimentul unic înainte să-l ruleze
ah_test_actiune( 'ah_spp_zilnic' );
egal( count( $GLOBALS['ah_test_cron'] ), 1, 'rularea zilnică programează următoarea' );
egal( date( 'H:i', $GLOBALS['ah_test_cron'][0][0] ), '08:10', 'următoarea: la 8:10' );
ah_test_actiune( 'init' );
ah_test_actiune( 'ah_spp_zilnic' );
egal( count( $GLOBALS['ah_test_cron'] ), 1, 'fără programare dublă (init + rularea, cu una deja programată)' );
set_transient( 'ah_spp_lacat', 1, 120 );
$GLOBALS['ah_test_cron'] = array();
ah_test_actiune( 'ah_spp_zilnic' );
egal( count( $GLOBALS['ah_test_cron'] ), 1, 'și cu lacătul pus (altă rulare în curs), lanțul nu se rupe' );
delete_transient( 'ah_spp_lacat' );
// evenimentul recurent din 1.2.0 („daily”) e înlocuit
$GLOBALS['ah_test_cron'] = array( array( time() + 3600, 'ah_spp_zilnic', array(), 'daily' ) );
ah_test_actiune( 'init' );
egal( array( count( $GLOBALS['ah_test_cron'] ), $GLOBALS['ah_test_cron'][0][3] ?? null, date( 'H:i', $GLOBALS['ah_test_cron'][0][0] ) ), array( 1, null, '08:10' ), 'evenimentul „daily” din 1.2.0 → lanțul la 8:10' );

/* ---------- 15. notificarea fazei: semnul se notează doar după o trimitere reușită ---------- */
$wpdb->update( 'wp_ah_spp_parteneri', array( 'intrat' => date( 'Y-m-d H:i:s' ), 'email_faze' => 0 ), array( 'id' => $RM['id'] ) );
$wpdb->query( 'DELETE FROM wp_ah_spp_push' );
$wpdb->insert( 'wp_ah_spp_push', array( 'partener' => $RM['id'], 'token' => 'fcm-bun-' . str_repeat( 'b', 40 ), 'token_h' => hash( 'sha256', 'fcm-bun-' . str_repeat( 'b', 40 ) ), 'platforma' => 'ios', 'creat' => date( 'Y-m-d H:i:s' ), 'vazut' => date( 'Y-m-d H:i:s' ) ) );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 1 ), zi( 29 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) );
$semn_vechi = ah_spp_rand( $RM['id'] )['faza_trimisa'];
verifica( 0 === strpos( $semn_vechi, 'iarna:' ), 'notificări: semnul iernii, notat' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 5 ), zi( 33 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) ); // primăvara începe azi
$GLOBALS['ah_test_fcm_fara'] = true; // Google nu dă jetonul de acces
$GLOBALS['ah_test_http'] = array();
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $RM['id'] ], 'reincearca', 'FCM fără jeton de acces: nimic n-a plecat → se reîncearcă' );
egal( ah_spp_rand( $RM['id'] )['faza_trimisa'], $semn_vechi, 'FCM fără jeton: semnul vechi rămâne (notificarea nu se pierde)' );
$GLOBALS['ah_test_fcm_fara'] = false;
$GLOBALS['ah_test_http_raspuns'] = function () { return new WP_Error( 'http_request_failed', 'timeout' ); };
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $RM['id'] ], 'reincearca', 'eroare de rețea la FCM: se reîncearcă' );
$GLOBALS['ah_test_http_raspuns'] = null;
$GLOBALS['ah_test_http'] = array();
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $RM['id'] ], 'push', 'rularea următoare: push trimis' );
egal( count( $GLOBALS['ah_test_http'] ), 1, 'o singură notificare' );
verifica( 0 === strpos( ah_spp_rand( $RM['id'] )['faza_trimisa'], 'primavara:' ), 'semnul primăverii, notat după trimitere' );
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $RM['id'] ], '', 'încă o rulare: nimic (fără dubluri)' );
egal( count( $GLOBALS['ah_test_http'] ), 1, 'tot o singură notificare' );
// două rulări care citiseră același rând: doar prima trimite
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 11 ), zi( 39 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) ); // vara începe azi
$rv = ah_spp_rand( $RM['id'] );
$cv = ah_spp_ciclul_ei( $rv['ea'], ah_spp_azi0() );
$GLOBALS['ah_test_http'] = array();
egal( array( ah_spp_verifica_partener( $rv, $cv, ah_spp_azi0() ), ah_spp_verifica_partener( $rv, $cv, ah_spp_azi0() ) ), array( 'push', '' ), 'două rulări simultane: o singură notificare' );
egal( count( $GLOBALS['ah_test_http'] ), 1, 'două rulări simultane: o singură cerere FCM' );

/* ---------- 16. cheia veche din baza de date (opțiunea ah_spp_secret, până la 1.2.0): tranziția de 30 de zile, apoi mutarea ---------- */
$VECHI = str_repeat( 'ab12', 16 );
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
$wpdb->query( 'DELETE FROM wp_ah_spp_push' );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
$zv = ah_spp_ciclul_ei( ah_sp_ciclu_cheie( $ANA ), ah_spp_azi0() )['z'];
$rand_vechi = function ( $email, $tok ) use ( $wpdb, $VECHI, $zv, $ANA ) {
	$wpdb->insert( 'wp_ah_spp_parteneri', array( 'ea' => ah_sp_ciclu_cheie( $ANA ), 'nume' => 'Vechi', 'nume_ea' => 'Ana', 'email_c' => ah_spp_cripteaza( $email, $VECHI ), 'email_h' => ah_spp_hash_email( $email, $VECHI ), 'token_h' => hash( 'sha256', $tok ), 'ses' => str_repeat( 'c', 32 ), 'acord' => '2026-11-18 10:00:00', 'acord_v' => AH_SPP_ACORD_V, 'creat' => '2026-11-18 10:00:00', 'invitat' => '2026-11-18 10:00:00', 'intrat' => '2026-11-18 10:00:00', 'faza_trimisa' => ah_spp_semn_faza( $zv, $VECHI ) ) );
	return ah_spp_rand( $wpdb->insert_id );
};
$V1 = $rand_vechi( 'vlad@exemplu.ro', str_repeat( 'v', 43 ) );
$V2 = $rand_vechi( 'ion@exemplu.ro', str_repeat( 'i', 43 ) );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'email_c' => base64_encode( random_bytes( 40 ) ) ), array( 'id' => $V2['id'] ) ); // un rând stricat
$V1c = $V1['email_c'];
update_option( 'ah_spp_secret', $VECHI );
$exp = time() + 5000;
$COOKIE_VECHI = $V1['id'] . '.' . $exp . '.' . ah_spp_semn_cookie( $V1['id'], $exp, $V1['ses'], ah_spp_cheie( 'cookie', $VECHI ) );
$STOP_VECHI = $V1['id'] . '.' . substr( hash_hmac( 'sha256', 'stop|' . (int) $V1['id'], ah_spp_cheie( 'stop', $VECHI ) ), 0, 24 );
$_COOKIE['ah_spp'] = $COOKIE_VECHI;
egal( ah_spp_din_cookie(), null, 'înainte de tranziție: cookie-ul semnat cu cheia veche nu e cunoscut (cheia nouă e alta)' );
ah_test_actiune( 'init' );
// pasul 1: tranziția începe, nimic nu se rescrie, cheia veche rămâne (o întoarcere la 1.2 găsește aceeași cheie și aceleași rânduri)
egal( get_option( 'ah_spp_secret' ), $VECHI, 'tranziția: cheia veche rămâne în baza de date 30 de zile (întoarcerea la 1.2 e sigură)' );
egal( ah_spp_rand( $V1['id'] )['email_c'], $V1c, 'tranziția: rândul vechi nu se rescrie' );
egal( array( ah_spp_decripteaza( $V1c, $VECHI ), ah_spp_rand( $V1['id'] )['email_h'] ), array( 'vlad@exemplu.ro', ah_spp_hash_email( 'vlad@exemplu.ro', $VECHI ) ), 'tranziția: 1.2 (cheia veche) citește încă emailul și amprenta' );
egal( ah_spp_rand( $V1['id'] )['faza_trimisa'], ah_spp_semn_faza( $zv, $VECHI ), 'tranziția: semnul fazei rămâne cel vechi' );
egal( ah_spp_decripteaza( $V1c ), 'vlad@exemplu.ro', 'tranziția: 1.4 citește rândul vechi (cu cheia veche, cât ține tranziția)' );
ea( $ANA );
$L16 = date_r( ah_spp_r_parteneri( cerere() ) )['parteneri'];
egal( array_column( $L16, 'email' ), array( 'vlad@exemplu.ro', '' ), 'tranziția: lista ei arată adresa rândului vechi (rândul stricat: gol)' );
fara_limite();
egal( date_r( ah_spp_r_invita( cerere( array( 'nume' => 'Vlad', 'email' => 'vlad@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) ) )['motiv'] ?? '', 'exista', 'tranziția: „L-ai invitat deja” recunoaște și amprenta veche' );
ea( '' );
$GLOBALS['ah_test_mail_cadru'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.60';
ah_spp_r_trimite_link( cerere( array( 'email' => 'vlad@exemplu.ro' ) ) );
ah_test_actiune( 'shutdown' ); // emailul pleacă după răspuns (fastcgi_finish_request e definită mai sus)
$GLOBALS['ah_test_hooks']['shutdown'] = array();
egal( array_column( $GLOBALS['ah_test_mail_cadru'], 'catre' ), array( 'vlad@exemplu.ro' ), 'tranziția: „Trimite-mi din nou linkul” găsește rândul vechi (amprenta veche)' );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'token_nou_h' => '' ), array( 'id' => $V1['id'] ) );
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $V1['id'] ], '', 'tranziția: semnul vechi al aceleiași faze → nicio notificare falsă' );
egal( ah_spp_rand( $V1['id'] )['faza_trimisa'], ah_spp_semn_faza( $zv, $VECHI ), 'tranziția: semnul vechi nu se rescrie (1.2 l-ar citi)' );
$tr = get_option( 'ah_spp_tranzitie' );
verifica( is_array( $tr ) && array_keys( $tr ) === array( 'cookie', 'stop', 'pana' ) && false === strpos( json_encode( $tr ), $VECHI ), 'tranziția: subcheile cookie/stop, nu cheia veche' );
verifica( $tr['pana'] > time() + 29 * 86400 && $tr['pana'] <= time() + 30 * 86400, 'tranziția: 30 de zile' );
$_COOKIE['ah_spp'] = $COOKIE_VECHI;
egal( (int) ( ah_spp_din_cookie()['id'] ?? 0 ), (int) $V1['id'], 'tranziția: cookie-ul semnat cu cheia veche e primit' );
egal( $_COOKIE['ah_spp'], ah_spp_valoare_cookie( ah_spp_rand( $V1['id'] ), $exp ), '…și semnat din nou cu cheia nouă, cu aceeași expirare' );
la( '/pentru-el/', array( 'fara-emailuri' => $STOP_VECHI ) );
egal( array( ah_spp_proceseaza_el(), (int) ah_spp_rand( $V1['id'] )['email_faze'] ), array( array( 'mesaj' => 'fara_emailuri' ), 0 ), 'tranziția: linkul „Oprește emailurile” dintr-un email vechi merge' );
egal( ah_spp_migreaza_cheia(), 0, 'tranziția: în cele 30 de zile nu se mută nimic' );
// o invitație nouă, în tranziție: cu cheia nouă
ea( $ANA );
fara_limite();
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s' ) ), array( 'id' => $V2['id'] ) );
ah_spp_r_invita( cerere( array( 'nume' => 'Nou', 'email' => 'nou@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$VN = ultim_rand();
egal( array( ah_spp_decripteaza( $VN['email_c'], ah_spp_secret() ), $VN['email_h'] ), array( 'nou@exemplu.ro', ah_spp_hash_email( 'nou@exemplu.ro' ) ), 'tranziția: ce e nou se scrie cu cheia nouă' );
ea( '' );
// pasul 2: la capătul tranziției, rândurile vechi se recriptează și cheia veche pleacă
$tr['pana'] = time() - 1;
update_option( 'ah_spp_tranzitie', $tr );
ah_test_actiune( 'init' );
egal( array( get_option( 'ah_spp_secret' ), get_option( 'ah_spp_tranzitie' ) ), array( false, false ), 'capătul tranziției: cheia veche și subcheile au plecat din baza de date' );
$V1n = ah_spp_rand( $V1['id'] );
egal( ah_spp_decripteaza( $V1n['email_c'], ah_spp_secret() ), 'vlad@exemplu.ro', 'capătul tranziției: emailul lui, criptat acum cu cheia nouă' );
egal( ah_spp_decripteaza( $V1n['email_c'], $VECHI ), null, '…și nu mai merge cu cheia veche' );
egal( $V1n['email_h'], ah_spp_hash_email( 'vlad@exemplu.ro' ), 'capătul tranziției: amprenta emailului refăcută (căutarea merge)' );
egal( $V1n['faza_trimisa'], ah_spp_semn_faza( $zv ), 'capătul tranziției: semnul fazei refăcut (cronul nu crede că s-a schimbat faza)' );
egal( ah_spp_rand( $VN['id'] )['email_c'], $VN['email_c'], 'capătul tranziției: rândul scris deja cu cheia nouă nu se atinge' );
egal( ah_spp_verifica_toti( ah_sp_ciclu_cheie( $ANA ) )[ (int) $V1['id'] ], '', 'după mutare: nicio notificare falsă' );
egal( ah_spp_rand( $V2['id'] )['email_h'], $V2['email_h'], 'rândul care nu se decripta: lăsat cum era' );
verifica( false !== strpos( json_encode( get_option( 'ah_spp_jurnal' ), JSON_UNESCAPED_UNICODE ), '1 rânduri mutate, 1 care nu se puteau decripta' ), 'mutarea: în jurnal, fără adrese' );
$_COOKIE['ah_spp'] = $COOKIE_VECHI;
egal( ah_spp_din_cookie(), null, 'după 30 de zile: semnătura veche nu mai e primită' );
la( '/pentru-el/', array( 'fara-emailuri' => $STOP_VECHI ) );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => 'link' ), 'după 30 de zile: linkul de oprire vechi nu mai merge' );
egal( ah_spp_migreaza_cheia(), 0, 'mutarea: o singură dată' );
// o întoarcere la 1.2 după capăt își face altă cheie: la revenirea pe 1.4, rândurile deja mutate nu se ating
update_option( 'ah_spp_secret', str_repeat( 'cd34', 16 ) );
ah_test_actiune( 'init' );
$tr = get_option( 'ah_spp_tranzitie' );
$tr['pana'] = time() - 1;
update_option( 'ah_spp_tranzitie', $tr );
egal( ah_spp_migreaza_cheia(), 0, 'altă cheie veche: rândurile deja mutate nu se ating' );
egal( ah_spp_decripteaza( ah_spp_rand( $V1['id'] )['email_c'] ), 'vlad@exemplu.ro', '…și se decriptează în continuare' );
la( '/pentru-el/', array() );
// un site trecut prin 1.3 (cheia veche ștearsă atunci, subcheile încă acolo): subcheile pleacă la capăt
update_option( 'ah_spp_tranzitie', array( 'cookie' => str_repeat( 'a', 64 ), 'stop' => str_repeat( 'b', 64 ), 'pana' => time() - 1 ) );
egal( array( ah_spp_cheie_veche( 'cookie' ), get_option( 'ah_spp_tranzitie' ) ), array( null, false ), 'după 1.3: subcheile vechi pleacă la capătul tranziției' );

/* ---------- 17. a doua rundă de review (1.4.0) ---------- */
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
$wpdb->query( 'DELETE FROM wp_ah_spp_push' );
unset( $_COOKIE['ah_spp'] );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 9 ), zi( 37 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
ea( $ANA );
fara_limite();
$GLOBALS['ah_test_mail_cadru'] = array();

// H1: adresa lui, strict — diacriticele și spațiile nu se mai „curăță” spre adresa altcuiva
foreach ( array( 'andrei.ștefan@gmail.com', 'andrei stefan@gmail.com', 'ana-maria.ionescu@yahoo.ro ', "mihai\u{00A0}@exemplu.ro", 'ioana@exemplu.rо', 'radu@exemplu.ro' . "\u{200B}" ) as $i => $adr ) {
	$r = ah_spp_r_invita( cerere( array( 'nume' => 'Andrei', 'email' => $adr, 'nume_ea' => 'Ana', 'acord' => true ) ) );
	if ( 2 === $i ) { // doar spațiul de la capăt: se taie, adresa e bună
		egal( date_r( $r )['ok'] ?? false, true, 'H1: spațiile de la capete se taie (' . trim( $adr ) . ')' );
		continue;
	}
	egal( array( $r->get_status(), date_r( $r )['motiv'] ?? '', date_r( $r )['mesaj'] ?? '' ), array( 400, 'email', 'Adresa conține caractere nepermise (diacritice sau spații).' ), 'H1: refuzat, cu mesaj clar: ' . json_encode( $adr, JSON_UNESCAPED_UNICODE ) );
}
egal( array_column( $GLOBALS['ah_test_mail_cadru'], 'catre' ), array( 'ana-maria.ionescu@yahoo.ro' ), 'H1: nicio invitație la andrei.tefan@gmail.com sau altă adresă „curățată”' );
egal( ah_spp_email_strict( 'Andrei.Stefan@Gmail.COM' ), 'andrei.stefan@gmail.com', 'H1: majusculele devin mici (aceeași adresă)' );
foreach ( array( 'andrei.stefan@gmail', 'andrei"x"@gmail.com', 'a@b@c.ro', 'andrei<x>@gmail.com' ) as $adr ) {
	egal( ah_spp_email_strict( $adr ), '', 'H1: adresă invalidă sau schimbată de sanitize_email → refuz: ' . $adr );
}
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
fara_limite();
ea( '' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.70';
$r = ah_spp_r_trimite_link( cerere( array( 'email' => 'andrei.ștefan@gmail.com' ) ) );
egal( array( $r->get_status(), date_r( $r )['mesaj'] ?? '' ), array( 400, 'Adresa conține caractere nepermise (diacritice sau spații).' ), 'H1: „Trimite-mi din nou linkul” la fel de strict' );
egal( ah_spp_r_trimite_link( cerere( array( 'email' => 'nu-e' ) ) )->get_status(), 400, 'H1: /trimite-link, adresă incompletă: 400' );
$src = file_get_contents( __DIR__ . '/../../ah-sp-partener.php' );
egal( substr_count( $src, '/[^\x21-\x7E]/.test(' ), 3, 'H1: și în pagini (cardul ei, „Trimite-mi linkul” la el și pe ecranul de intrare) adresa cu diacritice sau spații e oprită' );

// L2: „Trimite din nou invitația”: dacă emailul nu pleacă, linkul și sesiunea lui rămân
ea( $ANA );
fara_limite();
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RL = ultim_rand();
$TOKL = token_din_mail( ultimul_mail() );
$CL = ah_spp_valoare_cookie( $RL, time() + 3600 );
$GLOBALS['ah_test_mail_esec'] = true;
$j = date_r( ah_spp_r_retrimite( cerere( array( 'id' => $RL['id'] ) ) ) );
unset( $GLOBALS['ah_test_mail_esec'] );
egal( array( $j['ok'], $j['email'] ), array( true, false ), 'L2: emailul n-a plecat → răspunsul o spune' );
$RL2 = ah_spp_rand( $RL['id'] );
egal( array( $RL2['token_h'], $RL2['ses'], $RL2['invitat'] ), array( $RL['token_h'], $RL['ses'], $RL['invitat'] ), 'L2: tokenul, sesiunea și data invitației nu s-au schimbat' );
egal( (int) ( ah_spp_rand_token( $TOKL )['id'] ?? 0 ), (int) $RL['id'], 'L2: linkul vechi merge în continuare' );
$_COOKIE['ah_spp'] = $CL;
verifica( null !== ah_spp_din_cookie(), 'L2: telefonul lui rămâne conectat' );
unset( $_COOKIE['ah_spp'] );
$j = date_r( ah_spp_r_retrimite( cerere( array( 'id' => $RL['id'] ) ) ) );
egal( array( $j['email'], ah_spp_rand_token( $TOKL ), (int) ( ah_spp_rand_token( token_din_mail( ultimul_mail() ) )['id'] ?? 0 ) ), array( true, null, (int) $RL['id'] ), 'L2: cu emailul plecat, linkul se schimbă (cel vechi nu mai merge)' );

// L3: linkul unei invitații oprite nu ia locul unei sesiuni active
ea( 'bia@exemplu.ro' );
ciclu_in_cont( 'bia@exemplu.ro', array( 'starturi' => array( zi( 3 ) ), 'lung' => 30, 'men' => 4, 'sit' => 'natural' ) );
fara_limite();
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Bia', 'acord' => true ) ) );
$RB2 = ultim_rand();
$TOKB = token_din_mail( ultimul_mail() );
ah_spp_r_opreste( cerere( array( 'id' => $RB2['id'] ) ) );
$RL = ah_spp_rand( $RL['id'] );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RL, time() + 3600 );
$CL = $_COOKIE['ah_spp'];
la( '/pentru-el/', array( 't' => $TOKB ) );
egal( ah_spp_proceseaza_el(), array( 'mesaj' => 'oprit' ), 'L3: linkul unei invitații oprite, cu o sesiune activă: pagina rămâne, cu un rând scurt' );
egal( $_COOKIE['ah_spp'], $CL, 'L3: cookie-ul activ nu se schimbă' );
unset( $_COOKIE['ah_spp'] );
la( '/pentru-el/', array( 't' => $TOKB ) );
egal( array( ah_spp_proceseaza_el(), (int) ( ah_spp_din_cookie()['id'] ?? 0 ) ), array( array( 'redirect' => 'https://www.adelaharu.ro/pentru-el/' ), (int) $RB2['id'] ), 'L3: fără sesiune activă, linkul oprit duce ca înainte la „Linkul a fost oprit”' );
$h = ah_spp_pagina_el( array( 'mesaj' => 'oprit' ) + ah_spp_cfg_el() );
verifica( false !== strpos( $h, "D.mesaj==='oprit'" ) && false !== strpos( $h, 'Linkul acesta nu mai e activ.' ), 'L3: pagina arată rândul „Linkul acesta nu mai e activ.”' );
unset( $_COOKIE['ah_spp'] );
la( '/pentru-el/', array() );

// L4: invitat de două femei: tokenul lui stă lângă amândouă invitațiile
fara_limite();
ea( 'bia@exemplu.ro' );
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'Mihai@exemplu.ro', 'nume_ea' => 'Bia', 'acord' => true ) ) );
$RB3 = ultim_rand();
ea( '' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RL, time() + 3600 );
$PT = 'fcm-tok-mihai-l4-' . str_repeat( 'k', 30 );
egal( date_r( ah_spp_r_push( cerere( array( 'token' => $PT, 'platforma' => 'android' ) ) ) ), array( 'ok' => true ), 'L4: POST /push (pe pagina Anei)' );
egal( array( count( ah_spp_push_ale_lui( $RL['id'] ) ), count( ah_spp_push_ale_lui( $RB3['id'] ) ), count( ah_spp_push_ale_lui( $RB2['id'] ) ) ), array( 1, 1, 0 ), 'L4: tokenul stă lângă ambele invitații active (Ana și Bia), nu lângă cea oprită' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RB3, time() + 3600 );
ah_spp_r_push( cerere( array( 'token' => $PT, 'platforma' => 'android' ) ) );
egal( array( count( ah_spp_push_ale_lui( $RL['id'] ) ), count( ah_spp_push_ale_lui( $RB3['id'] ) ) ), array( 1, 1 ), 'L4: deschizând pagina Biei, tokenul NU se mută de la Ana (amândouă îl notifică)' );
egal( ah_spp_azi_pentru( ah_spp_rand( $RB3['id'] ) )['nume_ea'], 'Bia', 'L4: pagina lui spune a cui e pagina deschisă (nume_ea)' );
// faza nouă la amândouă: câte o notificare de la fiecare
$GLOBALS['ah_test_http'] = array();
$GLOBALS['ah_test_http_raspuns'] = null;
$wpdb->update( 'wp_ah_spp_parteneri', array( 'intrat' => date( 'Y-m-d H:i:s' ), 'faza_trimisa' => 'x:0' ), array( 'id' => $RL['id'] ) );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'intrat' => date( 'Y-m-d H:i:s' ), 'faza_trimisa' => 'x:0' ), array( 'id' => $RB3['id'] ) );
ciclu_in_cont( $ANA, array( 'starturi' => array( zi( 0 ), zi( 28 ) ), 'lung' => 28, 'men' => 5, 'sit' => 'natural' ) );
ciclu_in_cont( 'bia@exemplu.ro', array( 'starturi' => array( zi( 0 ), zi( 30 ) ), 'lung' => 30, 'men' => 4, 'sit' => 'natural' ) );
$v = ah_spp_verifica_toti();
egal( array( $v[ (int) $RL['id'] ], $v[ (int) $RB3['id'] ], count( $GLOBALS['ah_test_http'] ) ), array( 'push', 'push', 2 ), 'L4: faza nouă la Ana și la Bia → telefonul lui primește de la amândouă' );
// ea (Ana) oprește: tokenul pleacă doar de lângă ea
ea( $ANA );
ah_spp_r_opreste( cerere( array( 'id' => $RL['id'] ) ) );
egal( array( count( ah_spp_push_ale_lui( $RL['id'] ) ), count( ah_spp_push_ale_lui( $RB3['id'] ) ) ), array( 0, 1 ), 'L4: Ana oprește accesul → tokenul rămâne doar lângă Bia' );
ea( '' );
// iesi / ștergere: POST /push/sterge scoate tokenul de pe toate
fara_limite();
ea( $ANA );
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RL5 = ultim_rand();
ea( '' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RL5, time() + 3600 );
la( '/pentru-el/' );
ah_spp_cfg_el(); // prima intrare pe invitația nouă a Anei: telefonul lui (de lângă Bia) ajunge și aici
egal( array( count( ah_spp_push_ale_lui( $RL5['id'] ) ), count( ah_spp_push_ale_lui( $RB3['id'] ) ) ), array( 1, 1 ), 'L4: la prima intrare pe o invitație nouă, telefonul lui de pe cealaltă invitație ajunge și acolo' );
ah_test_actiune( 'rest_api_init' );
verifica( isset( $GLOBALS['ah_test_rute']['ah-spp/v1/push/sterge'] ) && 'POST' === $GLOBALS['ah_test_rute']['ah-spp/v1/push/sterge']['methods'] && 'ah_spp_r_push_sterge' === $GLOBALS['ah_test_rute']['ah-spp/v1/push/sterge']['callback'], 'POST /push/sterge (WAF: fără DELETE)' );
egal( array_column( $GLOBALS['ah_test_rute']['ah-spp/v1/push'], 'methods' ), array( 'POST', 'DELETE' ), 'DELETE /push rămâne, pentru compatibilitate' );
ah_spp_r_push_sterge( cerere( array( 'token' => $PT ) ) );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spp_push' ), 0, 'L4: „Ieși” (POST /push/sterge) scoate tokenul de pe toate invitațiile lui' );
ah_spp_r_push( cerere( array( 'token' => $PT, 'platforma' => 'android' ) ) );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spp_push' ), 2, 'L4: pus din nou, pe ambele' );
ah_spp_r_sterge( cerere( array( 'confirmare' => true ) ) );
egal( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ah_spp_push' ), 0, 'L4: „Șterge accesul meu” scoate telefonul lui și de lângă cealaltă invitație' );
verifica( null !== ah_spp_rand( $RB3['id'] ), 'L4: invitația Biei rămâne (doar telefonul a plecat)' );
// alt bărbat pe același telefon: tokenul pleacă de la primul
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( ah_spp_rand( $RB3['id'] ), time() + 3600 );
ah_spp_r_push( cerere( array( 'token' => $PT, 'platforma' => 'android' ) ) );
fara_limite();
ea( $ANA );
ah_spp_r_invita( cerere( array( 'nume' => 'Dan', 'email' => 'dan@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RD = ultim_rand();
ea( '' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( $RD, time() + 3600 );
ah_spp_r_push( cerere( array( 'token' => $PT, 'platforma' => 'android' ) ) );
egal( array( count( ah_spp_push_ale_lui( $RB3['id'] ) ), count( ah_spp_push_ale_lui( $RD['id'] ) ) ), array( 0, 1 ), 'L4: alt bărbat pe același telefon: tokenul trece la el (un telefon = un cont)' );
unset( $_COOKIE['ah_spp'] );

// L5: /invita sub lacăt — cu lacătul ținut de altă cerere, nimic nu trece
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
$wpdb->query( 'DELETE FROM wp_ah_spp_push' );
fara_limite();
ea( $ANA );
$GLOBALS['ah_test_lacate'][ 'ah_spp_' . substr( ah_sp_ciclu_cheie( $ANA ), 0, 40 ) ] = 'alta-cerere';
$r = ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
egal( array( $r->get_status(), date_r( $r )['motiv'] ?? '', count( randuri() ) ), array( 503, 'ocupat', 0 ), 'L5: lacătul ținut de altă cerere (peste 3 s) → 503, nimic salvat' );
$GLOBALS['ah_test_lacate'] = array();
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
egal( $GLOBALS['ah_test_lacate'], array(), 'L5: lacătul se eliberează după invitație' );
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
egal( $GLOBALS['ah_test_lacate'], array(), 'L5: și după un refuz (exista)' );
verifica( (bool) preg_match( "/'ah_spp_' \. substr\( \(string\) \\\$k, 0, 40 \)/", $src ), 'L5: numele lacătului încape în 64 de caractere (MySQL)' );
// rafală în paralel: 6 procese, fiecare cu altă adresă, deodată (SQLite pe disc, lacătul = flock, citirea limitei încetinită)
function ah_test_rafala( $fara_lacat ) {
	global $wpdb, $ANA;
	$dir = sys_get_temp_dir() . '/ahspp-rafala-' . getmypid() . '-' . (int) $fara_lacat;
	@mkdir( $dir );
	$db = $dir . '/db.sqlite';
	@unlink( $db );
	$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
	$wpdb->pdo->exec( 'VACUUM INTO ' . $wpdb->pdo->quote( $db ) );
	$start = microtime( true ) + 0.4;
	$copii = array();
	for ( $i = 0; $i < 6; $i++ ) {
		$pid = pcntl_fork();
		if ( 0 === $pid ) {
			$wpdb->pdo = new PDO( 'sqlite:' . $db );
			$wpdb->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$wpdb->pdo->exec( 'PRAGMA busy_timeout = 10000' );
			$GLOBALS['ah_test_lacat_dosar'] = $dir;
			$GLOBALS['ah_test_fara_lacat']  = $fara_lacat;
			$GLOBALS['ah_test_incet']       = 150000; // 150 ms între citirea partenerilor ei și inserare
			$GLOBALS['ah_test_transients']  = array();
			ea( $ANA );
			while ( microtime( true ) < $start ) { usleep( 1000 ); }
			$r = ah_spp_r_invita( cerere( array( 'nume' => 'P' . $i, 'email' => 'p' . $i . '@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
			file_put_contents( $dir . '/r' . $i, $r->get_status() . ':' . ( date_r( $r )['motiv'] ?? 'ok' ) );
			exit( 0 );
		}
		$copii[] = $pid;
	}
	foreach ( $copii as $pid ) { pcntl_waitpid( $pid, $st ); }
	$p = new PDO( 'sqlite:' . $db );
	$n = (int) $p->query( 'SELECT COUNT(*) FROM wp_ah_spp_parteneri WHERE oprit IS NULL' )->fetchColumn();
	$rasp = array();
	for ( $i = 0; $i < 6; $i++ ) { $rasp[] = (string) @file_get_contents( $dir . '/r' . $i ); }
	sort( $rasp );
	array_map( 'unlink', glob( $dir . '/*' ) );
	rmdir( $dir );
	return array( $n, $rasp );
}
if ( function_exists( 'pcntl_fork' ) ) {
	list( $n, $rasp ) = ah_test_rafala( true );
	verifica( $n > AH_SPP_MAX, 'L5: controlul — fără lacăt, rafala chiar trece de limită (' . $n . ' rânduri: testul vede cursa)' );
	list( $n, $rasp ) = ah_test_rafala( false );
	egal( $n, AH_SPP_MAX, 'L5: cu lacătul, 6 invitații trimise deodată → exact ' . AH_SPP_MAX . ' parteneri activi' );
	egal( array_count_values( $rasp ), array( '200:ok' => 2, '409:max' => 4 ), 'L5: rafala: 2 reușite, 4 refuzuri „max”' );
} else {
	echo "(L5: fără pcntl, rafala în paralel e sărită)\n";
}
$wpdb->query( 'DELETE FROM wp_ah_spp_parteneri' );
fara_limite();

// L7: versiunea tabelelor se notează doar dacă dbDelta chiar a mers
$GLOBALS['ah_test_options']['ah_spp_tabel'] = '4';
$GLOBALS['ah_test_dbdelta'] = array();
$wpdb->pdo->exec( 'DROP INDEX partener_token' );
$wpdb->pdo->exec( 'DROP INDEX token_h' );
$wpdb->pdo->exec( 'CREATE UNIQUE INDEX token_h ON wp_ah_spp_push ( token_h )' ); // tabelul 4: tokenul unic
ah_spp_db();
egal( get_option( 'ah_spp_tabel' ), '4', 'L7: dbDelta n-a făcut indexul partener_token → versiunea NU se notează' );
verifica( count( $GLOBALS['ah_test_dbdelta'] ) === 2 && false !== strpos( $GLOBALS['ah_test_dbdelta'][1], 'UNIQUE KEY partener_token (partener,token_h)' ) && false !== strpos( $GLOBALS['ah_test_dbdelta'][1], ' KEY token_h (token_h)' ), 'L7: dbDelta cere tabelul 5 (unic pe partener + token)' );
egal( $wpdb->get_results( "SHOW INDEX FROM wp_ah_spp_push WHERE Key_name = 'token_h'" ), array(), 'tabelul 5: indexul unic vechi token_h a plecat întâi (dbDelta nu șterge indexuri)' );
verifica( false !== strpos( json_encode( get_option( 'ah_spp_jurnal' ), JSON_UNESCAPED_UNICODE ), 'tabelele NU s-au actualizat' ), 'L7: în jurnal' );
$GLOBALS['ah_test_dbdelta'] = array();
ah_spp_db();
egal( count( $GLOBALS['ah_test_dbdelta'] ), 0, 'L7: nu se reîncearcă la fiecare cerere (o oră)' );
delete_transient( 'ah_spp_db_incercat' );
$wpdb->pdo->exec( 'CREATE UNIQUE INDEX partener_token ON wp_ah_spp_push ( partener, token_h )' );
$wpdb->pdo->exec( 'CREATE INDEX token_h ON wp_ah_spp_push ( token_h )' );
$wpdb->pdo->exec( 'ALTER TABLE wp_ah_spp_parteneri RENAME COLUMN pauza_de_la TO pauza_x' ); // o coloană lipsă
ah_spp_db();
egal( get_option( 'ah_spp_tabel' ), '4', 'L7: lipsește pauza_de_la → versiunea NU se notează' );
delete_transient( 'ah_spp_db_incercat' );
$wpdb->pdo->exec( 'ALTER TABLE wp_ah_spp_parteneri RENAME COLUMN pauza_x TO pauza_de_la' );
ah_spp_db();
egal( get_option( 'ah_spp_tabel' ), '5', 'L7: coloanele și indexul sunt acolo → versiunea 5' );

// cârligele cu modulul aplicație: ah_spa_cont_mutat, ah_spa_are_acces_el; ultima rulare a cronului
ea( $ANA );
ah_spp_r_invita( cerere( array( 'nume' => 'Mihai', 'email' => 'mihai@exemplu.ro', 'nume_ea' => 'Ana', 'acord' => true ) ) );
$RC = ultim_rand();
ciclu_in_cont( 'ana.noua@exemplu.ro', array( 'starturi' => array( zi( 9 ) ), 'lung' => 28, 'men' => 5 ) );
ah_test_actiune( 'ah_spa_cont_mutat', $ANA, 'ana.noua@exemplu.ro' );
egal( ah_spp_rand( $RC['id'] )['ea'], ah_sp_ciclu_cheie( 'ana.noua@exemplu.ro' ), 'ah_spa_cont_mutat: rândurile ei trec pe cheia adresei noi' );
ea( 'ana.noua@exemplu.ro' );
egal( count( date_r( ah_spp_r_parteneri( cerere() ) )['parteneri'] ), 1, 'ah_spa_cont_mutat: cu adresa nouă își vede partenerii' );
ea( $ANA );
egal( count( date_r( ah_spp_r_parteneri( cerere() ) )['parteneri'] ), 0, 'ah_spa_cont_mutat: adresa veche nu mai are nimic' );
egal( ah_spp_curata(), 0, 'ah_spa_cont_mutat: curățenia nu șterge rândurile mutate (ciclul ei e pe cheia nouă)' );
ea( '' );
unset( $_COOKIE['ah_spp'] );
egal( ah_test_filtru( 'ah_spa_are_acces_el', false ), false, 'ah_spa_are_acces_el: fără cookie-ul lui → false' );
$_COOKIE['ah_spp'] = ah_spp_valoare_cookie( ah_spp_rand( $RC['id'] ), time() + 3600 );
egal( ah_test_filtru( 'ah_spa_are_acces_el', false ), true, 'ah_spa_are_acces_el: cookie valid, acces activ → true' );
$wpdb->update( 'wp_ah_spp_parteneri', array( 'oprit' => date( 'Y-m-d H:i:s' ) ), array( 'id' => $RC['id'] ) );
egal( ah_test_filtru( 'ah_spa_are_acces_el', false ), false, 'ah_spa_are_acces_el: acces oprit → false' );
egal( ah_test_filtru( 'ah_spa_are_acces_el', true ), false, 'ah_spa_are_acces_el: acces oprit → false, chiar dacă valoarea primită era true (doar „are cookie”)' );
$_COOKIE['ah_spp'] = '1.' . ( time() + 99 ) . '.' . str_repeat( 'a', 40 );
egal( ah_test_filtru( 'ah_spa_are_acces_el', false ), false, 'ah_spa_are_acces_el: cookie fals → false' );
unset( $_COOKIE['ah_spp'] );
delete_option( 'ah_spp_ultima_rulare' );
$GLOBALS['ah_test_cron'] = array();
ah_test_actiune( 'ah_spp_zilnic' );
verifica( abs( (int) get_option( 'ah_spp_ultima_rulare' ) - time() ) <= 2, 'ah_spp_ultima_rulare: notată la fiecare rulare a cronului zilnic' );
set_transient( 'ah_spp_lacat', 1, 120 );
update_option( 'ah_spp_ultima_rulare', 5 );
ah_test_actiune( 'ah_spp_zilnic' );
verifica( (int) get_option( 'ah_spp_ultima_rulare' ) > 5, 'ah_spp_ultima_rulare: și când altă rulare ține lacătul (cronul e viu)' );
delete_transient( 'ah_spp_lacat' );

echo "\n" . $GLOBALS['ok'] . ' verificări trecute, ' . $GLOBALS['rau'] . " picate.\n";
exit( $GLOBALS['rau'] ? 1 : 0 );
