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

/* ---------- 3. Codul de 6 cifre (tabelul codurilor e în SQLite: interogarea atomică rulează ca SQL) ---------- */
$cod_rand = function ( $e ) { return $GLOBALS['wpdb']->sql->query( "SELECT * FROM wp_ah_app_coduri WHERE email = " . $GLOBALS['wpdb']->sql->quote( $e ) )->fetch( PDO::FETCH_ASSOC ); };
$cod_set  = function ( $e, $col, $v ) { $GLOBALS['wpdb']->sql->exec( "UPDATE wp_ah_app_coduri SET {$col} = " . (int) $v . ' WHERE email = ' . $GLOBALS['wpdb']->sql->quote( $e ) ); };
$cod = ah_app_cod_nou( $email );
verifica( 1 === preg_match( '/^\d{6}$/', $cod ), 'codul are 6 cifre' );
verifica( false === strpos( wp_json_encode( $cod_rand( $email ) ), $cod ), 'codul nu se păstrează în clar' );
$gresit = '000000' === $cod ? '111111' : '000000';
for ( $i = 1; $i <= 4; $i++ ) {
	egal( ah_app_cod_verifica( $email, $gresit ), 'cod_gresit', "încercarea greșită $i" );
}
egal( (int) $cod_rand( $email )['n'], 4, 'fiecare încercare se numără în baza de date (UPDATE atomic)' );
egal( ah_app_cod_verifica( $email, $gresit ), 'cod_expirat', 'a 5-a greșeală anulează codul' );
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'după anulare nici codul bun nu mai merge' );
egal( (int) $cod_rand( $email )['n'], 5, 'după anulare nu se mai rezervă încercări' );
$cod = ah_app_cod_nou( $email );
egal( (int) $cod_rand( $email )['n'], 0, 'codul nou pornește de la 0 încercări' );
egal( (int) $cod_rand( $email )['zi_n'], 5, '… dar încercările din ultimele 24 h rămân' );
egal( ah_app_cod_verifica( $email, ' ' . substr( $cod, 0, 3 ) . ' ' . substr( $cod, 3 ) ), 'ok', 'codul bun (cu spații) intră' );
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'codul se folosește o singură dată' );
egal( $cod_rand( $email ), false, 'intrarea reușită șterge rândul (și numărătoarea pe zi)' );
$cod = ah_app_cod_nou( $email );
$cod_set( $email, 'exp', time() - 1 );
egal( ah_app_cod_verifica( $email, $cod ), 'cod_expirat', 'după 15 minute codul expiră' );
egal( ah_app_cod_verifica( 'nimeni@exemplu.ro', '123456' ), 'cod_expirat', 'fără cod cerut: expirat' );
// Limita pe adresă, peste coduri noi: 10 încercări greșite în 24 de ore.
$e3 = 'tinta@exemplu.ro';
for ( $k = 0; $k < 2; $k++ ) {
	$c3 = ah_app_cod_nou( $e3 );
	$g3 = '000000' === $c3 ? '111111' : '000000';
	for ( $i = 0; $i < 5; $i++ ) {
		ah_app_cod_verifica( $e3, $g3 );
	}
}
egal( (int) $cod_rand( $e3 )['zi_n'], 10, '10 încercări greșite în 24 h, pe două coduri' );
verifica( ah_app_cod_blocat( $e3 ), 'adresa e blocată după 10 greșeli' );
$c3 = ah_app_cod_nou( $e3 );
egal( ah_app_cod_verifica( $e3, $c3 ), 'prea_multe', 'blocată: nici codul bun nu intră 24 h' );
egal( (int) $cod_rand( $e3 )['n'], 0, 'blocată: încercarea nu se consumă' );
$GLOBALS['ah_test_mail'] = array();
$_SERVER['REMOTE_ADDR']  = '7.7.7.7';
$rb = ah_app_r_cod( new WP_REST_Request( array(), array( 'email' => $e3 ) ) );
egal( array( $rb->get_status(), $rb->get_data()['eroare']['cod'] ), array( 429, 'prea_multe' ), '/auth/cod: adresă blocată ⇒ 429, fără email' );
egal( count( $GLOBALS['ah_test_mail'] ), 0, '/auth/cod: adresă blocată nu primește cod' );
$rb = ah_app_r_verifica( new WP_REST_Request( array(), array( 'email' => $e3, 'cod' => $c3, 'dispozitiv' => array( 'id' => 'aaaabbbb-cccc', 'platforma' => 'ios' ) ) ) );
verifica( false !== strpos( $rb->get_data()['eroare']['mesaj'], 'mâine' ), '/auth/verifica: blocată ⇒ mesajul spune „mâine”' );
$cod_set( $e3, 'zi_start', time() - DAY_IN_SECONDS - 1 );
egal( ah_app_cod_verifica( $e3, $c3 ), 'ok', 'după 24 h, codul bun intră din nou' );
// A 10-a încercare din zi poate fi cea bună.
$e4 = 'a10@exemplu.ro';
$c4 = ah_app_cod_nou( $e4 );
$cod_set( $e4, 'zi_n', 9 );
$cod_set( $e4, 'zi_start', time() - 60 );
egal( ah_app_cod_verifica( $e4, $c4 ), 'ok', 'a 10-a încercare din zi, cea bună, intră' );
// Curățenia zilnică a rândurilor vechi.
$e5 = 'veche@exemplu.ro';
ah_app_cod_nou( $e5 );
$cod_set( $e5, 'exp', time() - 2 * DAY_IN_SECONDS );
$c6 = ah_app_cod_nou( 'noua@exemplu.ro' );
ah_app_coduri_curata();
egal( $cod_rand( $e5 ), false, 'curățenia: codul vechi de o zi se șterge' );
verifica( false !== $cod_rand( 'noua@exemplu.ro' ), 'curățenia: codul de acum rămâne' );
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
} else {
	verifica( false, 'reference/sn41.php lipsește: nu pot compara limitele' );
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
egal( ah_app_durata_secunde( '29 de minute' ), 1740, 'durata: 29 de minute' );
egal( ah_app_durata_secunde( 'cam 36 de minute' ), 2160, 'durata: cam 36 de minute' );
egal( ah_app_durata_secunde( '18 minute' ), 1080, 'durata: 18 minute' );
egal( ah_app_durata_secunde( 'cam 2 ore și 40 de minute' ), 9600, 'durata: cam 2 ore și 40 de minute' );
egal( ah_app_durata_secunde( 'cam o oră și 45 de minute' ), 6300, 'durata: cam o oră și 45 de minute' );
egal( ah_app_durata_secunde( 'cam o oră și jumătate' ), 5400, 'durata: cam o oră și jumătate' );
egal( ah_app_durata_secunde( 'cam 2 ore' ), 7200, 'durata: cam 2 ore' );
egal( ah_app_durata_secunde( '4 min + meditația de 33 min' ), 2220, 'durata: două bucăți se adună' );
$ref44d = __DIR__ . '/../../reference/sn44.php';
if ( is_file( $ref44d ) ) {
	preg_match_all( "/'durata'\s*=>\s*'([^']+)'/u", file_get_contents( $ref44d ), $dm );
	$fara = array_values( array_filter( array_unique( $dm[1] ), function ( $x ) { return ! ah_app_durata_secunde( $x ); } ) );
	verifica( count( $dm[1] ) > 50, 'sn44: am găsit duratele lecțiilor' );
	egal( $fara, array(), 'sn44: fiecare durată scrisă în sală are secunde' );
}
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
egal( $a['applinks']['details'][0]['paths'], array( 'NOT /app/player*', '/app/*', '/meditatiile-mele/*', '/sala-de-curs/*', '/cursurile-mele/*' ), 'AASA: căile, fără /app/player' );
egal( $a['applinks']['details'][0]['components'][0], array( '/' => '/app/player*', 'exclude' => true ), 'AASA: /app/player exclus, înaintea lui /app/*' );
egal( $a['applinks']['details'][0]['components'][1], array( '/' => '/app/*' ), 'AASA: apoi /app/*' );
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

/* ---------- 10. Fluxul de intrare, cap-coadă (rutele, cu $wpdb în memorie) ---------- */
$_SERVER['REMOTE_ADDR'] = '9.9.9.9';
$GLOBALS['ah_test_mail'] = array();
$e2  = 'Maria@Exemplu.ro';
$rez = ah_app_r_cod( new WP_REST_Request( array(), array( 'email' => $e2 ) ) );
egal( $rez, array( 'ok' => true ), '/auth/cod: {"ok":true}' );
egal( count( $GLOBALS['ah_test_mail'] ), 1, '/auth/cod: pleacă un email (și fără nimic cumpărat)' );
egal( $GLOBALS['ah_test_mail'][0][0], 'maria@exemplu.ro', '/auth/cod: adresa cu litere mici' );
preg_match( '/(\d{6})$/', $GLOBALS['ah_test_mail'][0][1], $mc );
verifica( ! empty( $mc[1] ) && false !== strpos( $GLOBALS['ah_test_mail'][0][2], 'app/intra?e=' ), '/auth/cod: codul în subiect și linkul /app/intra în email' );
egal( ah_app_r_cod( new WP_REST_Request( array(), array( 'email' => 'nu-e-email' ) ) )->get_status(), 400, '/auth/cod: adresă greșită ⇒ 400' );
$disp = array( 'id' => 'f3a1c2d4-1111-2222-3333-444455556666', 'nume' => 'iPhone 15', 'platforma' => 'ios' );
$rv   = ah_app_r_verifica( new WP_REST_Request( array(), array( 'email' => $e2, 'cod' => '000000' === $mc[1] ? '111111' : '000000', 'dispozitiv' => $disp ) ) );
egal( $rv->get_data()['eroare']['cod'] ?? '', 'cod_gresit', '/auth/verifica: cod greșit' );
egal( ah_app_r_verifica( new WP_REST_Request( array(), array( 'email' => $e2, 'cod' => $mc[1], 'dispozitiv' => array( 'id' => 'x' ) + $disp ) ) )->get_data()['eroare']['cod'] ?? '', 'invalid', '/auth/verifica: dispozitiv invalid' );
$rv = ah_app_r_verifica( new WP_REST_Request( array(), array( 'email' => $e2, 'cod' => $mc[1], 'dispozitiv' => $disp ) ) );
verifica( is_array( $rv ) && ah_app_token_valid_format( $rv['token'] ?? '' ), '/auth/verifica: token de 64 hex' );
egal( $rv['utilizatoare']['email'] ?? '', 'maria@exemplu.ro', '/auth/verifica: utilizatoarea' );
$cerere = new WP_REST_Request( array( 'authorization' => 'Bearer ' . $rv['token'] ) );
$ua     = ah_app_autentifica( $cerere );
egal( $ua['email'] ?? '', 'maria@exemplu.ro', 'Bearer: autentificat' );
$ruta = ah_app_ruta( 'ah_app_r_iesire' );
egal( $ruta( $cerere ), array( 'ok' => true ), '/auth/iesire: ok' );
egal( $ruta( $cerere )->get_status(), 401, 'după ieșire: 401 neautentificat' );
for ( $i = 0; $i < 5; $i++ ) {
	ah_app_r_cod( new WP_REST_Request( array(), array( 'email' => 'ioana@exemplu.ro' ) ) );
}
egal( ah_app_r_cod( new WP_REST_Request( array(), array( 'email' => 'ioana@exemplu.ro' ) ) )->get_status(), 429, '/auth/cod: al 6-lea cod pe oră ⇒ 429' );
$cfg = ah_app_r_config();
egal( array_keys( $cfg ), array( 'versiune_minima', 'contact', 'linkuri', 'mesaj' ), '/config: cheile din contract' );
egal( $cfg['mesaj'], null, '/config: fără anunț ⇒ null' );
update_option( 'ah_app_mesaj', array( 'titlu' => 'Bună', 'text' => 'Avem meditații noi.' ) );
egal( ah_app_r_config()['mesaj'], array( 'titlu' => 'Bună', 'text' => 'Avem meditații noi.' ), '/config: anunțul din admin' );

/* ---------- 11. Sala de curs: cheile și progresul cu funcțiile adevărate din sn44 ---------- */
$ref44 = __DIR__ . '/../../reference/sn44.php';
if ( is_file( $ref44 ) ) {
	$src44 = file_get_contents( $ref44 );
	foreach ( array( 'ah_sc_vimeo', 'ah_sc_chei_lectie', 'ah_sc_cheie_lectie', 'ah_sc_harta_lectii', 'ah_sc_din_cheie', 'ah_sc_media_id' ) as $fn ) {
		if ( preg_match( '/^function ' . $fn . '\(.*?^\}/sm', $src44, $mf ) ) {
			eval( $mf[0] ); // phpcs:ignore
		}
	}
	verifica( function_exists( 'ah_sc_cheie_lectie' ) && function_exists( 'ah_sc_din_cheie' ), 'funcțiile de chei din sn44 s-au încărcat' );
	function get_the_post_thumbnail_url( $id, $s ) { return ''; }
	function wp_upload_dir( $a = null, $b = true ) { return array( 'baseurl' => 'https://www.adelaharu.ro/wp-content/uploads', 'basedir' => '/tmp/nu-exista' ); }
	class Ah_Test_Comanda {
		public $meta = array();
		public function get_id() { return 555; }
		public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	}
	$c    = new Ah_Test_Comanda();
	$curs = array(
		'slug' => 'wild-love', 'nume' => 'Wild Love', 'pe_rand' => true, 'bun_venit' => 'Bine ai venit.',
		'texte' => array( 'faze' => array( array( 'p', 'Despre faze.' ) ) ),
		'module' => array(
			array( 'grup' => 0, 'titlu' => 'Introducere', 'nr' => 'Intro', 'vimeo' => 'https://player.vimeo.com/video/111?h=aa11', 'pdf' => 'https://www.adelaharu.ro/wp-content/uploads/caiet.pdf', 'durata' => '20 min', 'jurnal' => array( 'Ce aștepți?' ) ),
			array( 'grup' => 1, 'titlu' => 'Întâlnirea 1', 'nr' => 'Modulul 1', 'vimeo' => 'https://vimeo.com/222/bb22', 'pdf' => '', 'durata' => 'cam 2 ore', 'mp3' => 'https://www.adelaharu.ro/wp-content/uploads/m1.m4a', 'audio_titlu' => 'Practică' ),
			array( 'grup' => 1, 'titlu' => 'Fazele', 'nr' => 'Modulul 1 · Text', 'vimeo' => '', 'pdf' => '', 'durata' => '', 'citeste' => array( 'faze', 'Cele 4 faze' ) ),
			array( 'grup' => 2, 'titlu' => 'Meditația', 'nr' => 'Modulul 2 · Audio', 'vimeo' => '', 'mp3' => 'https://extern.ro/a.mp3', 'pdf' => '', 'durata' => '10 min' ),
		),
	);
	$c->meta['_ah_sc_progres'] = array( 'wild-love' => array( 0 ) );
	$d = ah_app_curs_detaliu( array( 'comanda' => $c, 'curs' => $curs, 'produs' => 0 ), $u );
	egal( array_column( $d['module'], 'id' ), array( 'l0', 'm1', 'm2' ), 'cursul: modulele' );
	egal( array_column( $d['module'], 'deschis' ), array( true, true, false ), 'cursul: pe rând, modulul 2 încă închis' );
	$l0 = $d['module'][0]['lectii'][0];
	egal( $l0['cheie'], ah_sc_cheie_lectie( array_values( $curs['module'] ), 0 ), 'lecția: cheia stabilă din sală' );
	egal( $l0['tip'], 'video', 'lecția Vimeo: video' );
	verifica( 0 === strpos( (string) $l0['player'], 'https://www.adelaharu.ro/app/player?' ), 'lecția Vimeo: playerul de pe adelaharu.ro' );
	verifica( false === strpos( wp_json_encode( $d ), 'player.vimeo.com' ), 'linkul Vimeo direct nu apare în răspuns' );
	verifica( 0 === strpos( (string) $l0['fisier'], 'https://www.adelaharu.ro/?ah_app_media=l' ), 'caietul PDF: link semnat' );
	egal( $l0['terminata'], true, 'lecția bifată în sală apare terminată' );
	egal( $l0['durata'], 1200, 'durata în secunde' );
	$l1 = $d['module'][1]['lectii'][0];
	egal( $l1['deschisa'], true, 'pe rând: prima neterminată e deschisă' );
	verifica( false !== strpos( (string) $l1['fisier'], 'k=mp3' ), 'lecția cu video + audio: fișierul audio semnat' );
	egal( $d['module'][1]['lectii'][1]['deschisa'], false, 'pe rând: următoarea e închisă' );
	egal( $d['module'][1]['lectii'][1]['fisier'], null, 'lecția închisă: fără fișier' );
	verifica( false !== strpos( (string) $d['module'][1]['lectii'][1]['text'], 'Despre faze.' ), 'textul de citit din curs' );
	egal( $d['module'][1]['lectii'][1]['tip'], 'text', 'lecția doar cu text' );
	egal( $d['progres'], 0.25, 'progresul: 1 din 4' );
	$h = ah_sc_harta_lectii( array_values( $curs['module'] ) );
	egal( ah_sc_din_cheie( $h, $l1['cheie'] ), 1, 'cheia din aplicație duce la lecția corectă în sală' );
	$curs['pe_rand'] = false;
	$d = ah_app_curs_detaliu( array( 'comanda' => $c, 'curs' => $curs, 'produs' => 0 ), $u );
	egal( $d['module'][2]['lectii'][0]['fisier'], 'https://extern.ro/a.mp3', 'fișier extern: linkul direct, ca în sală' );
	egal( ah_app_curs_scurt( array( 'comanda' => $c, 'curs' => $curs, 'produs' => 0 ) )['module_terminate'], 1, 'module terminate: introducerea' );
}

/* ---------- 12. Ciclul fără emailuri: aceeași păstrare ca sn41 (12 luni fără actualizare) ---------- */
ah_app_ciclu_scrie( 'ciclu@exemplu.ro', array( 'prima_zi' => '2026-10-20', 'zile_sangerare' => 5, 'zile_ciclu' => 28, 'emailuri_faze' => false ) );
$kc = ah_app_cheie_ciclu( 'ciclu@exemplu.ro' );
verifica( abs( (int) ( get_option( $kc )['actualizat'] ?? 0 ) - time() ) < 5, 'ciclul: se ține data actualizării' );
update_option( ah_app_cheie_ciclu( 'vechi@exemplu.ro' ), array( 'prima_zi' => '2025-01-01', 'zile_sangerare' => 5, 'zile_ciclu' => 28, 'acord' => gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS ) ) );
update_option( 'ah_app_ciclu_setare', 'altceva' );
egal( ah_app_ciclu_curata( time() + 364 * DAY_IN_SECONDS ), 1, 'curățenia: doar cel vechi (fără „actualizat”, după acord) se șterge' );
verifica( is_array( get_option( $kc ) ), 'curățenia: 364 de zile ⇒ rămâne' );
egal( ah_app_ciclu_curata( time() + 366 * DAY_IN_SECONDS ), 1, 'curățenia: 366 de zile ⇒ se șterge' );
egal( get_option( $kc ), false, 'curățenia: opțiunea nu mai există' );
egal( get_option( 'ah_app_ciclu_setare' ), 'altceva', 'curățenia: nu atinge alte opțiuni' );

/* ---------- 13. Notificările în loturi ---------- */
if ( ! function_exists( 'ah_med_poate' ) ) {
	$GLOBALS['ah_test_acces'] = array();
	function ah_med_acces( $e ) { $GLOBALS['ah_test_acces'][] = $e; return array( 'are' => 0 !== strpos( $e, 'fara' ) ); }
	function ah_med_poate( $id, $a ) { return ! empty( $a['are'] ); }
}
$tp = ah_app_tabel_push();
$GLOBALS['wpdb']->t[ $tp ] = array();
for ( $i = 0; $i < 230; $i++ ) {
	// 3 telefoane pe adresă; fiecare a zecea adresă nu are acces; câteva fără notificări de meditații.
	$em = ( 0 === intdiv( $i, 3 ) % 10 ? 'fara' : 'are' ) . intdiv( $i, 3 ) . '@exemplu.ro';
	$GLOBALS['wpdb']->insert( $tp, array( 'email' => $em, 'fcm' => 'f' . $i, 'meditatii' => 225 <= $i ? 0 : 1, 'cursuri' => 1 ) );
}
// Ce așteptăm, calculat direct din tabel: loturi de câte 100 de abonate, în ordinea id-urilor.
$abon   = array_values( array_filter( $GLOBALS['wpdb']->t[ $tp ], function ( $r ) { return 1 === $r['meditatii']; } ) );
$asteapta = array();
$perechi  = 0;
foreach ( array_chunk( $abon, 100 ) as $lot ) {
	$asteapta[] = count( array_filter( $lot, function ( $r ) { return 0 !== strpos( $r['email'], 'fara' ); } ) );
	$perechi   += count( array_unique( array_column( $lot, 'email' ) ) );
}
$GLOBALS['ah_test_posts'][77] = array( 'status' => 'publish', 'titlu' => 'Lumina de dimineață' );
$GLOBALS['ah_test_cron']      = array();
update_option( 'ah_app_jurnal', array() );
$job = ah_app_push_meditatie( 77 );
verifica( '' !== $job && is_array( get_option( $job ) ), 'meditație nouă: trimiterea e pusă la coadă' );
verifica( (bool) get_post_meta( 77, '_ah_app_push', true ), 'meditație nouă: marcată după ce e la coadă' );
egal( ah_app_push_meditatie( 77 ), '', 'meditație nouă: o singură dată' );
$loturi = 0;
$trimise = 0;
while ( ( $ev = ah_test_cron_scoate( 'ah_app_push_lot' ) ) && $loturi < 10 ) {
	ah_app_push_lot( $ev[2][0] );
	$loturi++;
}
$jr = implode( "\n", get_option( 'ah_app_jurnal', array() ) );
preg_match_all( '/ar fi plecat la (\d+) telefoane/', $jr, $jm );
egal( $loturi, 3, 'loturi de câte 100: 225 abonate ⇒ 3 loturi' );
egal( array_map( 'intval', $jm[1] ), $asteapta, 'fiecare lot trimite doar celor cu acces, fără tăiere la 1000' );
egal( array_sum( $asteapta ), 201, 'toate cele cu acces primesc (225 abonate − 8 adrese × 3 telefoane fără acces)' );
egal( get_option( $job ), false, 'după ultimul lot, lucrarea se șterge' );
egal( wp_next_scheduled( 'ah_app_push_lot', array( $job ) ), false, 'nicio reîncercare rămasă' );
egal( count( $GLOBALS['ah_test_acces'] ), $perechi, 'accesul: o dată pe adresă și lot, nu pe telefon' );
// Un lot care cade la jumătate: reîncercarea e deja programată și pornește de la cursor.
$GLOBALS['ah_test_cron'] = array();
$job2 = ah_app_push_porneste( 'meditatii', array( 'tip' => 'meditatie', 'id' => 77 ), 'T', 'X', array() );
ah_test_cron_scoate( 'ah_app_push_lot' );
$j2 = get_option( $job2 );
$j2['incercari'] = 1;
$j2['cursor']    = (int) $abon[149]['id'];
update_option( $job2, $j2 );
wp_schedule_single_event( time() + 900, 'ah_app_push_lot', array( $job2 ) ); // ce a lăsat lotul căzut
update_option( 'ah_app_jurnal', array() );
ah_app_push_lot( ah_test_cron_scoate( 'ah_app_push_lot' )[2][0] );
preg_match_all( '/ar fi plecat la (\d+) telefoane/', implode( "\n", get_option( 'ah_app_jurnal', array() ) ), $jm );
egal( array_map( 'intval', $jm[1] ), array( count( array_filter( array_slice( $abon, 150 ), function ( $r ) { return 0 !== strpos( $r['email'], 'fara' ); } ) ) ), 'reîncercarea pornește de la cursor (după a 150-a)' );
egal( get_option( $job2 ), false, 'reîncercarea termină lucrarea' );
$j3 = ah_app_push_porneste( 'meditatii', array( 'tip' => 'meditatie', 'id' => 77 ), 'T', 'X', array() );
update_option( $j3, array( 'incercari' => 3 ) + get_option( $j3 ) );
ah_app_push_lot( $j3 );
egal( get_option( $j3 ), false, 'după 3 căderi la rând, lucrarea se oprește (cu jurnal)' );
// Ora: trimiterile se pun la coadă înainte să se noteze grupurile văzute.
$GLOBALS['ah_test_cron'] = array();
update_option( 'ah_app_grupuri_vazute', array( 'wild-love' => array( 'g0' ) ) );
$jo = ah_app_orar( array( 'wild-love' => array( 'nume' => 'Wild Love', 'grupuri' => array( 'g0', 'g1' ) ), 'nou' => array( 'nume' => 'Nou', 'grupuri' => array( 'g0' ) ) ) );
egal( count( $jo ), 1, 'ora: o trimitere pentru cursul cu o parte nouă (cursul nou nu anunță)' );
egal( get_option( $jo[0] )['cui'], array( 'tip' => 'curs', 'slug' => 'wild-love' ), 'ora: trimiterea e pentru Wild Love' );
egal( get_option( 'ah_app_grupuri_vazute' ), array( 'wild-love' => array( 'g0', 'g1' ), 'nou' => array( 'g0' ) ), 'ora: grupurile văzute, notate după' );

echo "\nTeste trecute: {$GLOBALS['ah_ok']}, picate: {$GLOBALS['ah_rau']}\n";
exit( $GLOBALS['ah_rau'] ? 1 : 0 );
