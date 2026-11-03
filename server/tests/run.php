<?php
/**
 * Testele logicii pure din ah-app-api.php. Rulare: php server/tests/run.php
 * Ies cu cod 1 dacă un test pică.
 */

require __DIR__ . '/stubs.php';
require __DIR__ . '/../ah-app-api.php';

$GLOBALS['ah_ok']   = 0;
$GLOBALS['ah_rau']  = 0;
function verifica( $cond, $nume ) {
	if ( $cond ) {
		$GLOBALS['ah_ok']++;
	} else {
		$GLOBALS['ah_rau']++;
		echo "PICAT: $nume\n";
	}
}
function egal( $a, $b, $nume ) {
	verifica( $a === $b, $nume . ( $a === $b ? '' : ' (am ' . var_export( $a, true ) . ', așteptam ' . var_export( $b, true ) . ')' ) );
}
function query_din( $url ) {
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	return $q;
}

/* ---------- 1. Tokenurile ---------- */
$tok = ah_app_token_nou();
verifica( ah_app_token_valid_format( $tok ), 'tokenul nou are 64 hex' );
egal( strlen( hex2bin( $tok ) ), 32, 'tokenul are 32 de octeți' );
egal( ah_app_hash_token( $tok ), hash( 'sha256', $tok ), 'pe server se ține sha256' );
verifica( ah_app_hash_token( $tok ) !== $tok, 'hash-ul nu e tokenul' );
verifica( ah_app_token_nou() !== $tok, 'tokenurile sunt diferite' );
verifica( ! ah_app_token_valid_format( 'ABC' ), 'format greșit respins' );
verifica( ! ah_app_token_valid_format( strtoupper( $tok ) . 'x' ), 'format prea lung respins' );

$req = new WP_REST_Request( array( 'authorization' => 'Bearer ' . $tok ) );
egal( ah_app_token_din_cerere( $req ), $tok, 'tokenul se citește din Authorization: Bearer' );
$req = new WP_REST_Request( array( 'authorization' => 'Basic abc' ) );
egal( ah_app_token_din_cerere( $req ), '', 'alt tip de Authorization: nimic' );
$req = new WP_REST_Request( array( 'x_ah_token' => $tok ) );
egal( ah_app_token_din_cerere( $req ), $tok, 'rezerva X-Ah-Token' );

/* ---------- 2. Telefoanele (limita de 5) ---------- */
$email = 'ana@exemplu.ro';
$t1    = ah_app_inregistreaza( $email, 'disp-0001-aaaa', 'iPhone 15', 'ios' );
verifica( ah_app_token_valid_format( $t1 ), 'primul telefon primește token' );
$r1 = ah_app_token_rand( $t1 );
egal( $r1['email'] ?? null, $email, 'tokenul duce la adresă' );
verifica( ! isset( $r1['token'] ) && $r1['hash'] === hash( 'sha256', $t1 ), 'în tabel e doar hash-ul' );
verifica( null === ah_app_token_rand( ah_app_token_nou() ), 'un token necunoscut nu intră' );
$t1b = ah_app_inregistreaza( $email, 'disp-0001-aaaa', 'iPhone 15', 'ios' );
verifica( null === ah_app_token_rand( $t1 ), 'același telefon: tokenul vechi nu mai merge' );
verifica( null !== ah_app_token_rand( $t1b ), 'același telefon: tokenul nou merge' );
egal( count( ah_app_dispozitive( $email ) ), 1, 'același telefon nu ocupă loc nou' );
for ( $i = 2; $i <= 5; $i++ ) {
	verifica( '' !== ah_app_inregistreaza( $email, 'disp-000' . $i . '-aaaa', 'Telefon ' . $i, 'android' ), "telefonul $i intră" );
}
egal( ah_app_inregistreaza( $email, 'disp-0006-aaaa', 'Telefon 6', 'android' ), '', 'al 6-lea telefon e refuzat' );
egal( count( $GLOBALS['ah_test_mail'] ), 0, 'fără admin_email: niciun email' );
verifica( '' !== ah_app_inregistreaza( $email, 'disp-0003-aaaa', 'Telefon 3', 'android' ), 'la limită, un telefon deja cunoscut se reînnoiește' );
verifica( '' !== ah_app_inregistreaza( 'alta@exemplu.ro', 'disp-0006-aaaa', 'X', 'ios' ), 'altă adresă are locurile ei' );
$r1b = ah_app_token_rand( $t1b );
ah_app_revoca( $r1b['id'] );
verifica( null === ah_app_token_rand( $t1b ), 'ieșirea scoate tokenul' );
egal( count( ah_app_dispozitive( $email ) ), 4, 'după ieșire se eliberează un loc' );
egal( ah_app_decizie_disp( array(), 'x' ), 'nou', 'decizie: nou' );
egal( ah_app_decizie_disp( array( array( 'disp' => 'x' ) ), 'x' ), 'reinnoieste', 'decizie: reînnoiește' );
egal( ah_app_decizie_disp( array_fill( 0, 5, array( 'disp' => 'y' ) ), 'x' ), 'limita', 'decizie: limită' );

/* ---------- 3. Codul de 6 cifre ---------- */
$cod = ah_app_cod_nou( $email );
verifica( 1 === preg_match( '/^\d{6}$/', $cod ), 'codul are 6 cifre' );
$t = get_transient( ah_app_cheie_cod( $email ) );
verifica( false === strpos( wp_json_encode( $t ), $cod ), 'codul nu se păstrează în clar' );
$gresit = '000000' === $cod ? '111111' : '000000';
for ( $i = 1; $i <= 4; $i++ ) {
	egal( ah_app_cod_verifica( $email, $gresit ), 'cod_gresit', "încercarea greșită $i" );
}
egal( ah_app_cod_verifica( $email, $gresit ), 'cod_expirat', 'a 5-a greșeală anulează codul' );
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'după anulare nici codul bun nu mai merge' );
$cod = ah_app_cod_nou( $email );
egal( ah_app_cod_verifica( $email, ' ' . substr( $cod, 0, 3 ) . ' ' . substr( $cod, 3 ) ), 'ok', 'codul bun (cu spații) intră' );
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'codul se folosește o singură dată' );
$cod = ah_app_cod_nou( $email );
$GLOBALS['ah_test_timp'] = 16 * 60;
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'după 15 minute codul expiră' );
$GLOBALS['ah_test_timp'] = 0;
verifica( false !== strpos( ah_app_link_intra( $email, '123456' ), '/app/intra?e=' ), 'linkul din email duce la /app/intra' );

/* ---------- 4. Limitele ---------- */
for ( $i = 1; $i <= 5; $i++ ) {
	verifica( ! ah_app_limita( 'email_cod|' . $email, 5 ), "cererea $i trece" );
}
verifica( ah_app_limita( 'email_cod|' . $email, 5 ), 'a 6-a cerere în aceeași oră e oprită' );
verifica( ! ah_app_limita( 'email_cod|alta@exemplu.ro', 5 ), 'altă adresă are limita ei' );
$GLOBALS['ah_test_timp'] = 3601;
verifica( ! ah_app_limita( 'email_cod|' . $email, 5 ), 'după o oră limita se golește' );
$GLOBALS['ah_test_timp'] = 0;
for ( $i = 1; $i <= 20; $i++ ) {
	ah_app_limita( 'ip_cod|1.2.3.4', 20 );
}
verifica( ah_app_limita( 'ip_cod|1.2.3.4', 20 ), '20 / oră / IP' );

/* ---------- 5. Linkurile semnate ---------- */
$u   = array( 'id' => 7, 'email' => $email );
$url = ah_app_link_meditatie( 123, $u );
$q   = query_din( $url );
egal( $q['ah_app_media'], 'm123', 'linkul meditației are id-ul' );
verifica( 0 === strpos( $url, 'https://www.adelaharu.ro/?ah_app_media=' ), 'linkul e pe adelaharu.ro' );
verifica( (int) $q['exp'] > time() + 5 * 3600 && (int) $q['exp'] <= time() + 6 * 3600, 'valabil 6 ore' );
egal( strlen( $q['sig'] ), 64, 'semnătura are 64 hex (HMAC-SHA256)' );
verifica( ah_app_semn_bun( array( 'm', 123, 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'semnătura bună trece' );
verifica( ! ah_app_semn_bun( array( 'm', 124, 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'altă meditație: respins' );
verifica( ! ah_app_semn_bun( array( 'm', 123, 7, 'alta@exemplu.ro', $q['exp'] ), $q['exp'], $q['sig'] ), 'altă adresă: respins' );
verifica( ! ah_app_semn_bun( array( 'm', 123, 8, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'alt token: respins' );
verifica( ! ah_app_semn_bun( array( 'm', 123, 7, $email, $q['exp'] + 1 ), $q['exp'] + 1, $q['sig'] ), 'expirare schimbată: respins' );
verifica( ! ah_app_semn_bun( array( 'm', 123, 7, $email, $q['exp'] ), $q['exp'], strtoupper( $q['sig'] ) ), 'semnătură modificată: respins' );
$vechi = time() - 10;
verifica( ! ah_app_semn_bun( array( 'm', 123, 7, $email, $vechi ), $vechi, ah_app_semn( array( 'm', 123, 7, $email, $vechi ) ) ), 'link expirat: respins' );
$departe = time() + 30 * 3600;
verifica( ! ah_app_semn_bun( array( 'm', 123, 7, $email, $departe ), $departe, ah_app_semn( array( 'm', 123, 7, $email, $departe ) ) ), 'expirare prea departe: respins' );
$url = ah_app_link_lectie( 555, 'wild-love', 'mp3', 'https://www.adelaharu.ro/wp-content/uploads/a.mp3', $u );
$q   = query_din( $url );
egal( $q['ah_app_media'], 'l' . substr( md5( 'https://www.adelaharu.ro/wp-content/uploads/a.mp3' ), 0, 16 ), 'lecția: id-ul fișierului ca în sală' );
verifica( ah_app_semn_bun( array( 'l', 555, 'wild-love', 'mp3', substr( $q['ah_app_media'], 1 ), 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'lecția: semnătura bună trece' );
verifica( ! ah_app_semn_bun( array( 'l', 555, 'wild-love', 'mp4', substr( $q['ah_app_media'], 1 ), 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'lecția: alt tip de fișier respins' );
$url = ah_app_player_lectie( 555, 'wild-love', 'v123|tabc', $u );
$q   = query_din( $url );
verifica( 0 === strpos( $url, 'https://www.adelaharu.ro/app/player?' ), 'playerul e pe adelaharu.ro/app/player' );
verifica( ah_app_semn_bun( array( 'pl', 555, 'wild-love', rawurldecode( $q['l'] ), 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'playerul: semnătura bună trece' );
$q = query_din( ah_app_player_meditatie( 9, $u ) );
verifica( ! ah_app_semn_bun( array( 'm', 9, 7, $email, $q['exp'] ), $q['exp'], $q['sig'] ), 'semnătura playerului nu merge ca semnătură de fișier' );

/* ---------- 6. Ciclul ---------- */
$azi = '2026-11-03';
egal( ah_app_ciclu_valideaza( array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'acord', 'fără acord: invalid' );
egal( ah_app_ciclu_valideaza( array( 'prima_zi' => '2026-10-20', 'acord_date_sanatate' => false ), $azi )['motiv'] ?? '', 'acord', 'acord false: invalid' );
$b = array( 'acord_date_sanatate' => true, 'zile_sangerare' => 5, 'zile_ciclu' => 28, 'emailuri_faze' => true );
egal( ah_app_ciclu_valideaza( $b + array( 'prima_zi' => '2026-02-30' ), $azi )['motiv'] ?? '', 'data', 'dată inexistentă' );
egal( ah_app_ciclu_valideaza( $b + array( 'prima_zi' => '20-10-2026' ), $azi )['motiv'] ?? '', 'data', 'format greșit' );
egal( ah_app_ciclu_valideaza( $b + array( 'prima_zi' => '2026-11-04' ), $azi )['motiv'] ?? '', 'viitor', 'data din viitor' );
egal( ah_app_ciclu_valideaza( array( 'zile_ciclu' => 20 ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'ciclu sub 21' );
egal( ah_app_ciclu_valideaza( array( 'zile_ciclu' => 46 ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'ciclu peste 45' );
egal( ah_app_ciclu_valideaza( array( 'zile_sangerare' => 1 ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'sângerare sub 2' );
egal( ah_app_ciclu_valideaza( array( 'zile_sangerare' => 11 ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'sângerare peste 10' );
egal( ah_app_ciclu_valideaza( array( 'zile_ciclu' => '28.5' ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'zecimale respinse' );
egal( ah_app_ciclu_valideaza( array( 'zile_ciclu' => true ) + $b + array( 'prima_zi' => '2026-10-20' ), $azi )['motiv'] ?? '', 'numere', 'tip greșit respins' );
$v = ah_app_ciclu_valideaza( $b + array( 'prima_zi' => '2026-10-20' ), $azi );
egal( $v['date'] ?? null, array( 'prima_zi' => '2026-10-20', 'zile_sangerare' => 5, 'zile_ciclu' => 28, 'emailuri_faze' => true ), 'date bune' );
egal( ah_app_ciclu_valideaza( $b + array( 'prima_zi' => $azi ), $azi )['ok'], true, 'azi e permis' );
// O dată veche: se mută înainte cu cicluri întregi, faza de azi rămâne aceeași.
$v   = ah_app_ciclu_valideaza( array( 'zile_ciclu' => 31 ) + $b + array( 'prima_zi' => '2026-01-10' ), $azi );
$nou = $v['date']['prima_zi'];
$zi  = function ( $s, $c ) use ( $azi ) { return ( (int) round( ( strtotime( $azi . ' 12:00 UTC' ) - strtotime( $s . ' 12:00 UTC' ) ) / 86400 ) ) % $c + 1; };
verifica( $nou >= '2026-07-06' && $nou <= $azi, 'data veche adusă în ultimele 120 de zile (' . $nou . ')' );
egal( $zi( $nou, 31 ), $zi( '2026-01-10', 31 ), 'ziua din ciclu rămâne aceeași după mutare' );
egal( ah_app_ciclu_starturi( array( '2026-09-20' ), '2026-10-20' ), array( '2026-09-20', '2026-10-20' ), 'start nou se adaugă' );
egal( ah_app_ciclu_starturi( array( '2026-09-20', '2026-10-20' ), '2026-10-22' ), array( '2026-09-20', '2026-10-22' ), 'sub 15 zile: corectură' );
egal( count( ah_app_ciclu_starturi( array( '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01' ), '2026-07-01' ) ), 6, 'se țin ultimele 6 starturi' );
// Limitele fazelor: aceeași formulă ca ah_ciclu_limite (snippetul 41).
egal( ah_app_ciclu_limite( 28, 5 ), array( 'menstruala' => array( 1, 5 ), 'foliculara' => array( 6, 11 ), 'ovulatorie' => array( 12, 16 ), 'premenstruala' => array( 17, 28 ) ), 'fazele la 28/5' );
egal( ah_app_ciclu_limite( 21, 7 ), array( 'menstruala' => array( 1, 7 ), 'foliculara' => array( 8, 8 ), 'ovulatorie' => array( 9, 13 ), 'premenstruala' => array( 14, 21 ) ), 'fazele la 21/7' );
egal( ah_app_ciclu_limite( 99, 0 ), ah_app_ciclu_limite( 45, 2 ), 'valorile se strâng în 21–45 / 2–10' );
// Comparație cu funcția din sn41 (copiată din reference/sn41.php, dacă e acolo).
$ref = __DIR__ . '/../../reference/sn41.php';
if ( is_file( $ref ) && preg_match( '/function ah_ciclu_limite\( \$c, \$m \) \{.*?\n\}/s', file_get_contents( $ref ), $mm ) ) {
	eval( str_replace( 'function ah_ciclu_limite(', 'function ah_test_limite_41(', $mm[0] ) ); // phpcs:ignore
	$toate = true;
	for ( $c = 21; $c <= 45; $c++ ) {
		for ( $m = 2; $m <= 10; $m++ ) {
			$toate = $toate && ah_test_limite_41( $c, $m ) === ah_app_ciclu_limite( $c, $m );
		}
	}
	verifica( $toate, 'limitele sunt identice cu sn41 pentru toate ciclurile 21–45 × 2–10' );
}
$f = ah_app_faze_date();
egal( array_column( $f['faze'], 'slug' ), array( 'iarna', 'primavara', 'vara', 'toamna' ), 'fazele: slugurile din contract' );
egal( $f['faze'][0]['arhetip'], 'Alchimista', 'iarna = Alchimista' );
egal( $f['faze'][1]['anotimp'], 'Primăvara', 'anotimpul cu majusculă și diacritice' );
egal( $f['avertisment'], 'Fazele sunt o estimare, nu o metodă de contracepție.', 'avertismentul' );

/* ---------- 7. Cursurile (logica sălii) ---------- */
egal( ah_app_durata_secunde( '30 min' ), 1800, 'durata: 30 min' );
egal( ah_app_durata_secunde( 'cam 2 ore și jumătate' ), 9000, 'durata: 2 ore și jumătate' );
egal( ah_app_durata_secunde( '1 oră' ), 3600, 'durata: 1 oră' );
egal( ah_app_durata_secunde( '1h 20 min' ), 4800, 'durata: 1h 20 min' );
egal( ah_app_durata_secunde( '' ), null, 'durata goală' );
egal( ah_app_durata_secunde( 'câteva zile' ), null, 'durata necunoscută' );
$lec = array(
	array( 'titlu' => 'Intro', 'grup' => 0 ),
	array( 'titlu' => 'M1 video', 'grup' => 1 ),
	array( 'titlu' => 'M1 audio', 'grup' => 1 ),
	array( 'titlu' => 'Bonus' ),
	array( 'titlu' => 'M2', 'grup' => 2 ),
);
$g = ah_app_grupuri( $lec );
egal( array_column( $g, 'id' ), array( 'l0', 'm1', 'l3', 'm2' ), 'modulele ca în sală (grup 0 = lecție singură)' );
egal( $g[1]['lectii'], array( 1, 2 ), 'modulul 1 are două lecții' );
egal( $g[1]['titlu'], 'M1 video', 'titlul modulului = prima lecție' );
egal( ah_app_lectii_libere( 5, array(), false ), array( true, true, true, true, true ), 'fără „pe rând”: toate deschise' );
egal( ah_app_lectii_libere( 5, array(), true ), array( true, false, false, false, false ), 'pe rând: doar prima' );
egal( ah_app_lectii_libere( 5, array( 0, 1 ), true ), array( true, true, true, false, false ), 'pe rând: până la prima neterminată' );
egal( ah_app_lectii_libere( 5, array( 0, 3 ), true ), array( true, true, false, true, false ), 'pe rând: cele bifate rămân deschise' );
egal( ah_app_grupuri_noi( array( 'a' => array( 'm1' ) ), array( 'a' => array( 'grupuri' => array( 'm1', 'm2' ) ), 'b' => array( 'grupuri' => array( 'l0' ) ) ) ), array( 'a' => array( 'm2' ) ), 'modul nou deschis; cursul nou nu anunță' );
$h = ah_app_text_html( array( array( 'h', 'Titlu' ), array( 'p', 'Un <b>text</b>' ), array( 'li', 'unu' ), array( 'li', 'doi' ), array( 'p', 'final' ) ), 'Tema', array( 'Ce simți?' ) );
egal( $h, '<h4>Titlu</h4><p>Un &lt;b&gt;text&lt;/b&gt;</p><ul><li>unu</li><li>doi</li></ul><p>final</p><p>Tema</p><h4>Întrebări pentru jurnal</h4><ul><li>Ce simți?</li></ul>', 'textul lecției: HTML simplu, escapat' );

/* ---------- 8. Range, căi, linkuri universale, erori ---------- */
egal( ah_app_interval( '', 1000 ), array( 200, 0, 999 ), 'fără Range: tot' );
egal( ah_app_interval( 'bytes=0-', 1000 ), array( 206, 0, 999 ), 'Range 0-' );
egal( ah_app_interval( 'bytes=100-199', 1000 ), array( 206, 100, 199 ), 'Range 100-199' );
egal( ah_app_interval( 'bytes=-100', 1000 ), array( 206, 900, 999 ), 'Range ultimii 100' );
egal( ah_app_interval( 'bytes=900-5000', 1000 ), array( 206, 900, 999 ), 'Range peste capăt' );
egal( ah_app_interval( 'bytes=1000-', 1000 ), array( 416, 0, 0 ), 'Range în afară: 416' );
egal( ah_app_cale_relativa( '/app/intra?e=x', '/' ), 'app/intra', 'calea /app/intra' );
egal( ah_app_cale_relativa( '/.well-known/apple-app-site-association', '/' ), '.well-known/apple-app-site-association', 'calea AASA' );
egal( ah_app_cale_relativa( '/site/app/player?x=1', '/site/' ), 'app/player', 'site într-un subfolder' );
egal( ah_app_aasa()['applinks']['details'], array(), 'AASA gol fără Team ID' );
update_option( 'ah_app_ios_team', 'ABCDE12345' );
$a = ah_app_aasa();
egal( $a['applinks']['details'][0]['appIDs'], array( 'ABCDE12345.ro.adelaharu.app' ), 'AASA: appID' );
egal( $a['applinks']['details'][0]['paths'], array( '/app/*', '/meditatiile-mele/*', '/sala-de-curs/*', '/cursurile-mele/*' ), 'AASA: căile' );
$fp = implode( ':', array_fill( 0, 32, 'AB' ) );
update_option( 'ah_app_android_sha256', strtolower( $fp ) . "\nnu-e-amprenta" );
$l = ah_app_assetlinks();
egal( $l[0]['target']['package_name'], 'ro.adelaharu.app', 'assetlinks: pachetul' );
egal( $l[0]['target']['sha256_cert_fingerprints'], array( $fp ), 'assetlinks: doar amprentele valide, cu majuscule' );
$e = ah_app_eroare( 'prea_multe' );
egal( $e->get_status(), 429, 'eroarea prea_multe are 429' );
egal( array_keys( $e->get_data()['eroare'] ), array( 'cod', 'mesaj' ), 'forma erorii din contract' );
egal( ah_app_eroare( 'ceva-necunoscut' )->get_status(), 500, 'cod necunoscut ⇒ server' );
foreach ( ah_app_mesaje() as $k => $m ) {
	verifica( ! preg_match( '/\b(error|please|token|login)\b/i', $m ), "mesajul $k e în română" );
}
verifica( ah_app_origine_permisa( 'capacitor://localhost' ) && ah_app_origine_permisa( 'https://localhost' ) && ah_app_origine_permisa( 'http://localhost' ), 'CORS: originile aplicației' );
verifica( ! ah_app_origine_permisa( 'https://rau.ro' ), 'CORS: alte origini nu' );

/* ---------- 9. FCM: JWT semnat cu cheia contului de serviciu ---------- */
$k = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
openssl_pkey_export( $k, $pem );
$pub = openssl_pkey_get_details( $k )['key'];
$sa  = array( 'type' => 'service_account', 'project_id' => 'adela-haru', 'client_email' => 'push@adela-haru.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' );
verifica( null === ah_app_fcm_cont(), 'fără opțiunea ah_app_fcm: oprit' );
verifica( null === ah_app_fcm_cont( '{"project_id":"x"}' ), 'JSON incomplet: oprit' );
egal( ah_app_fcm_cont( json_encode( $sa ) )['project_id'], 'adela-haru', 'contul de serviciu se citește' );
$jwt = ah_app_fcm_jwt( $sa, 1700000000 );
$p   = explode( '.', $jwt );
egal( count( $p ), 3, 'JWT în 3 părți' );
$dec = function ( $s ) { return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) ); };
egal( json_decode( $dec( $p[0] ), true ), array( 'alg' => 'RS256', 'typ' => 'JWT' ), 'JWT: antetul' );
$cl = json_decode( $dec( $p[1] ), true );
egal( $cl['scope'], 'https://www.googleapis.com/auth/firebase.messaging', 'JWT: scope FCM' );
egal( $cl['exp'] - $cl['iat'], 3600, 'JWT: o oră' );
egal( $cl['iss'], $sa['client_email'], 'JWT: emitentul' );
egal( openssl_verify( $p[0] . '.' . $p[1], $dec( $p[2] ), $pub, OPENSSL_ALGO_SHA256 ), 1, 'JWT: semnătura RS256 se verifică cu cheia publică' );
$msg = ah_app_fcm_mesaj( 'tok', 'Titlu', 'Text', array( 'id' => 5, 'tip' => 'meditatie' ) );
egal( $msg['message']['data'], array( 'id' => '5', 'tip' => 'meditatie' ), 'FCM: valorile din data sunt texte' );
egal( ah_app_trimite_push( array( array( 'id' => 1, 'email' => $email, 'fcm' => 'x' ) ), 'Test', 'Text' ), 0, 'fără cheie: nu se trimite' );
verifica( false !== strpos( implode( "\n", get_option( 'ah_app_jurnal', array() ) ), 'push OPRIT' ), 'fără cheie: doar jurnal' );

echo "\nTeste trecute: {$GLOBALS['ah_ok']}, picate: {$GLOBALS['ah_rau']}\n";
exit( $GLOBALS['ah_rau'] ? 1 : 0 );
