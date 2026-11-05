<?php
/**
 * Adela Haru – API-ul aplicației mobile „Adela Haru” (Android și iPhone). 3 nov. 2026.
 *
 * Cum se adaugă: Code Snippets › Snippets › Add New › PHP.
 * Lipește tot ce este SUB linia „<?php”, alege „Run everywhere”, „Save Changes and Activate”.
 * Apoi: Meditațiile mele › Aplicația mobilă (sau WooCommerce › Aplicația mobilă), completează setările.
 *
 * Ce face (doar lucruri NOI, nu schimbă nimic din snippeturile existente):
 *  - API-ul aplicației la /wp-json/ah-app/v1 (contractul: docs/api-contract.md);
 *  - intrarea cu cod de 6 cifre pe email (ca la „Meditațiile mele”), cu un token pe telefon,
 *    cel mult 5 telefoane pe adresă (separat de cele 5 ale aplicației web);
 *  - meditațiile (snippetul 51), cursurile din sala de curs (snippetul 44), ciclul (snippetul 41):
 *    citește și scrie EXACT în aceleași locuri ca ele, ca să nu fie două surse;
 *  - fișierele audio/PDF prin linkuri semnate (?ah_app_media=…), cu „Range” (derulare, redare în fundal);
 *  - /app/player: o pagină mică pe adelaharu.ro cu videoul Vimeo (Vimeo are restricție de domeniu);
 *  - /app/intra: linkul din email (deschide aplicația, sau arată codul și magazinele);
 *  - /.well-known/apple-app-site-association și /.well-known/assetlinks.json (linkurile universale);
 *  - notificări push prin Firebase (FCM HTTP v1): meditație nouă, parte nouă deschisă într-un curs.
 *    Până pui cheia contului de serviciu în setări, NU se trimite nimic, doar se scrie în jurnal.
 *
 * Aplicația NU vinde nimic: fără prețuri, fără butoane de cumpărare, fără linkuri spre cumpărare
 * (regulile Apple 3.1.1 / 3.1.3(a)). De aceea API-ul nu dă niciodată prețuri sau linkuri spre magazin.
 *
 * Depinde (dar merge și fără ele, cu mai puțin): 51 (ah_med_*), 154 (ah_it_*), 44 (ah_sc_*), 146, 41 (ah_ciclu_*).
 * Textele pentru cliente sunt CIORNĂ, de citit de Adela.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AH_APP' ) ) :
define( 'AH_APP', 1 );

define( 'AH_APP_NS', 'ah-app/v1' );
define( 'AH_APP_VERSIUNE_TABEL', '2' );
define( 'AH_APP_PACHET', 'ro.adelaharu.app' );
define( 'AH_APP_MAX_DISP', 5 );          // telefoane pe adresă (aplicația mobilă)
define( 'AH_APP_LINK_ORE', 6 );          // cât ține un link semnat (media, player)
define( 'AH_APP_COD_MIN', 15 );          // cât ține codul din email
define( 'AH_APP_PUSH_LOT', 100 );         // telefoane pe lot de notificări (un eveniment cron)
define( 'AH_APP_COD_INCERCARI', 5 );     // încercări pe un cod
define( 'AH_APP_COD_ZI', 10 );           // încercări greșite pe adresă în 24 de ore, peste coduri
define( 'AH_APP_MESAJ_BLOCAT_ZI', 'Ai încercat de mai multe ori un cod greșit pentru adresa aceasta. Ca să o păstrăm în siguranță, te rog să mai încerci mâine.' ); // CIORNĂ
define( 'AH_APP_CONTACT', 'contact@adelaharu.ro' );

/* ==================================================================
   1. UNELTE MICI
   ================================================================== */

/** Adresa de email curată (litere mici), sau ''. */
function ah_app_email( $e ) {
	$e = strtolower( trim( sanitize_email( (string) $e ) ) );
	return is_email( $e ) ? $e : '';
}

/** Mesajele de eroare, în română, pentru utilizatoare. */
function ah_app_mesaje() {
	return array(
		'neautentificat' => 'Te rog să intri din nou în cont, cu adresa ta de email.',
		'fara_acces'     => 'Nu ai acces la aceasta.',
		'negasit'        => 'Nu am găsit ce cauți.',
		'prea_multe'     => 'Ai încercat de mai multe ori. Te rog să mai încerci peste o oră.',
		'cod_gresit'     => 'Codul nu pare să fie corect. Verifică-l, te rog, în email.',
		'cod_expirat'    => 'Codul acesta nu mai este valabil. Cere, te rog, unul nou.',
		'dispozitive'    => 'Contul tău e deschis deja pe ' . AH_APP_MAX_DISP . ' telefoane. Scrie-ne la ' . AH_APP_CONTACT . ' și îți facem loc.',
		'invalid'        => 'Ceva nu e completat cum trebuie. Verifică, te rog, și mai încearcă.',
		'server'         => 'A apărut o problemă la noi. Te rog să mai încerci puțin mai târziu.',
	);
}

/** Codurile de eroare și statusul HTTP. */
function ah_app_statusuri() {
	return array(
		'neautentificat' => 401,
		'fara_acces'     => 403,
		'negasit'        => 404,
		'prea_multe'     => 429,
		'cod_gresit'     => 400,
		'cod_expirat'    => 400,
		'dispozitive'    => 403,
		'invalid'        => 400,
		'server'         => 500,
	);
}

/** Răspunsul de eroare: {"eroare":{"cod","mesaj"}}. */
function ah_app_eroare( $cod, $mesaj = '' ) {
	$m = ah_app_mesaje();
	$s = ah_app_statusuri();
	$cod = isset( $s[ $cod ] ) ? $cod : 'server';
	return new WP_REST_Response( array( 'eroare' => array( 'cod' => $cod, 'mesaj' => '' !== $mesaj ? $mesaj : $m[ $cod ] ) ), $s[ $cod ] );
}

function ah_app_fara_cache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'ah app' );
}

/** Data ISO 8601 cu ora, în fusul site-ului (2026-11-03T10:00:00+02:00). */
function ah_app_iso( $t ) {
	return $t ? wp_date( 'c', (int) $t ) : null;
}
/** Doar ziua (2026-11-03). */
function ah_app_zi( $t ) {
	return $t ? wp_date( 'Y-m-d', (int) $t ) : null;
}

function ah_app_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $_SERVER['REMOTE_ADDR'] ) : '0';
}

/**
 * Limită simplă cu transient: true dacă s-a atins $max în fereastra de $secunde (și nu mai numără).
 * Fereastra pornește la prima cerere (nu se prelungește la fiecare cerere).
 */
function ah_app_limita( $cheie, $max, $secunde = HOUR_IN_SECONDS ) {
	$k = 'ah_app_l_' . md5( $cheie );
	$v = get_transient( $k );
	$v = is_array( $v ) && isset( $v['n'], $v['t'] ) && $v['t'] > time() ? $v : array( 'n' => 0, 't' => time() + $secunde );
	if ( $v['n'] >= $max ) {
		return true;
	}
	$v['n']++;
	set_transient( $k, $v, max( 1, $v['t'] - time() ) );
	return false;
}

/** Jurnalul aplicației (ultimele 200 de rânduri), ca ah_it_jurnal. Fără date despre ciclu. */
function ah_app_jurnal( $text ) {
	$j   = get_option( 'ah_app_jurnal', array() );
	$j   = is_array( $j ) ? $j : array();
	$j[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . $text;
	update_option( 'ah_app_jurnal', array_slice( $j, -200 ), false );
}

function ah_app_b64( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
}

/** Corpul cererii (JSON sau formular). */
function ah_app_corp( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	return is_array( $p ) ? $p : (array) $r->get_body_params();
}

/** Calea cererii, fără baza site-ului: „app/intra”, „.well-known/assetlinks.json”. */
function ah_app_cale_relativa( $uri, $baza ) {
	$p    = (string) parse_url( (string) $uri, PHP_URL_PATH );
	$baza = '/' . trim( (string) $baza, '/' );
	$baza = '/' === $baza ? '/' : $baza . '/';
	if ( 0 === strpos( $p, $baza ) ) {
		$p = substr( $p, strlen( $baza ) );
	}
	return trim( $p, '/' );
}

/* ==================================================================
   2. TABELELE (tokenurile aplicației și tokenurile push)
   ================================================================== */

function ah_app_tabel_tokenuri() {
	global $wpdb;
	return $wpdb->prefix . 'ah_app_tokenuri';
}
function ah_app_tabel_push() {
	global $wpdb;
	return $wpdb->prefix . 'ah_app_push';
}
function ah_app_tabel_coduri() {
	global $wpdb;
	return $wpdb->prefix . 'ah_app_coduri';
}

add_action( 'init', function () {
	if ( get_option( 'ah_app_tabel' ) === AH_APP_VERSIUNE_TABEL ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	$t  = ah_app_tabel_tokenuri();
	$p  = ah_app_tabel_push();
	// Tokenul nu stă niciodată în clar: doar sha256 (64 hex).
	dbDelta(
		"CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		hash char(64) NOT NULL,
		disp varchar(64) NOT NULL,
		disp_nume varchar(80) NOT NULL DEFAULT '',
		platforma varchar(10) NOT NULL DEFAULT '',
		creat datetime NOT NULL,
		folosit datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY hash (hash),
		KEY email (email)
		) {$cs};"
	);
	dbDelta(
		"CREATE TABLE {$p} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_id bigint(20) unsigned NOT NULL,
		email varchar(190) NOT NULL,
		fcm varchar(255) NOT NULL,
		fcm_h char(64) NOT NULL,
		platforma varchar(10) NOT NULL DEFAULT '',
		meditatii tinyint(1) unsigned NOT NULL DEFAULT 1,
		cursuri tinyint(1) unsigned NOT NULL DEFAULT 1,
		actualizat datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY fcm_h (fcm_h),
		KEY token_id (token_id),
		KEY email (email)
		) {$cs};"
	);
	// Codurile din email: un rând pe adresă. Încercările se numără atomic (UPDATE … WHERE n < 5),
	// ca cererile trimise în paralel să nu primească fiecare încercarea ei.
	$c = ah_app_tabel_coduri();
	dbDelta(
		"CREATE TABLE {$c} (
		email varchar(190) NOT NULL,
		hash char(64) NOT NULL DEFAULT '',
		n tinyint(3) unsigned NOT NULL DEFAULT 0,
		exp int(10) unsigned NOT NULL DEFAULT 0,
		zi_n smallint(5) unsigned NOT NULL DEFAULT 0,
		zi_start int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (email)
		) {$cs};"
	);
	update_option( 'ah_app_tabel', AH_APP_VERSIUNE_TABEL, true );
} );

/* ==================================================================
   3. TOKENURILE APLICAȚIEI
   ================================================================== */

/** Un token nou: 32 de octeți aleatori, 64 hex. */
function ah_app_token_nou() {
	return bin2hex( random_bytes( 32 ) );
}

/** Ce păstrăm pe server: doar sha256. */
function ah_app_hash_token( $token ) {
	return hash( 'sha256', (string) $token );
}

/** Tokenul arată ca unul de-al nostru? */
function ah_app_token_valid_format( $token ) {
	return is_string( $token ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $token );
}

/** Tokenul din cerere: „Authorization: Bearer …” (sau X-Ah-Token, dacă serverul taie Authorization). */
function ah_app_token_din_cerere( WP_REST_Request $r ) {
	$h = (string) $r->get_header( 'authorization' );
	if ( '' === $h ) {
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$h = (string) $_SERVER[ $k ];
				break;
			}
		}
	}
	if ( preg_match( '/^\s*Bearer\s+([0-9a-f]{64})\s*$/i', $h, $m ) ) {
		return strtolower( $m[1] );
	}
	$x = strtolower( trim( (string) $r->get_header( 'x_ah_token' ) ) );
	return ah_app_token_valid_format( $x ) ? $x : '';
}

/** Rândul tokenului (array) sau null. */
function ah_app_token_rand( $token ) {
	if ( ! ah_app_token_valid_format( $token ) ) {
		return null;
	}
	global $wpdb;
	$h = ah_app_hash_token( $token );
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_app_tabel_tokenuri() . ' WHERE hash = %s', $h ), ARRAY_A );
	return ( $r && hash_equals( (string) $r['hash'], $h ) ) ? $r : null;
}

function ah_app_token_dupa_id( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_app_tabel_tokenuri() . ' WHERE id = %d', (int) $id ), ARRAY_A );
}

/** Cine e: array( id, email, disp ) sau null. Ultima folosire se scrie cel mult o dată pe oră. */
function ah_app_autentifica( WP_REST_Request $r ) {
	$rand = ah_app_token_rand( ah_app_token_din_cerere( $r ) );
	if ( ! $rand ) {
		return null;
	}
	if ( strtotime( (string) $rand['folosit'] ) < time() - HOUR_IN_SECONDS ) {
		global $wpdb;
		$wpdb->update( ah_app_tabel_tokenuri(), array( 'folosit' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $rand['id'] ) );
	}
	return array( 'id' => (int) $rand['id'], 'email' => (string) $rand['email'], 'disp' => (string) $rand['disp'] );
}

/** Telefoanele aplicației pentru o adresă. */
function ah_app_dispozitive( $email ) {
	global $wpdb;
	$r = $wpdb->get_results( $wpdb->prepare( 'SELECT id, disp, disp_nume, platforma, creat, folosit FROM ' . ah_app_tabel_tokenuri() . ' WHERE email = %s ORDER BY id ASC', $email ), ARRAY_A );
	return is_array( $r ) ? $r : array();
}

/**
 * Decide ce facem cu un telefon nou: 'reinnoieste' (același id), 'nou' sau 'limita'.
 * Logica pură (fără bază de date), ca să poată fi testată.
 */
function ah_app_decizie_disp( $existente, $disp, $max = AH_APP_MAX_DISP ) {
	foreach ( (array) $existente as $d ) {
		if ( isset( $d['disp'] ) && hash_equals( (string) $d['disp'], (string) $disp ) ) {
			return 'reinnoieste';
		}
	}
	return count( (array) $existente ) >= $max ? 'limita' : 'nou';
}

/** Înregistrează telefonul și întoarce tokenul (în clar, o singură dată), sau '' la limită. */
function ah_app_inregistreaza( $email, $disp, $nume, $platforma ) {
	global $wpdb;
	$t     = ah_app_tabel_tokenuri();
	$token = ah_app_token_nou();
	$acum  = gmdate( 'Y-m-d H:i:s' );
	$ce    = ah_app_decizie_disp( ah_app_dispozitive( $email ), $disp );
	if ( 'limita' === $ce ) {
		$k = 'ah_app_lim_' . md5( $email );
		if ( ! get_transient( $k ) ) {
			set_transient( $k, 1, DAY_IN_SECONDS );
			$admin = get_option( 'admin_email' );
			if ( $admin ) {
				wp_mail( $admin, 'Aplicația Adela Haru: o adresă a atins limita de telefoane',
					'<p>Contul ' . esc_html( $email ) . ' e deschis deja în aplicație pe ' . AH_APP_MAX_DISP . ' telefoane și cineva a încercat încă unul.</p>'
					. '<p>Dacă îți scrie ea: Meditațiile mele › Aplicația mobilă › caută adresa › „Golește telefoanele”.</p>',
					array( 'Content-Type: text/html; charset=UTF-8' ) );
			}
		}
		return '';
	}
	$rand = array(
		'email'     => $email,
		'hash'      => ah_app_hash_token( $token ),
		'disp'      => $disp,
		'disp_nume' => $nume,
		'platforma' => $platforma,
		'folosit'   => $acum,
	);
	if ( 'reinnoieste' === $ce ) {
		// Același telefon: tokenul vechi nu mai merge, rândul (și tokenurile push legate de el) rămâne.
		$wpdb->update( $t, $rand, array( 'email' => $email, 'disp' => $disp ) );
	} else {
		$rand['creat'] = $acum;
		$wpdb->insert( $t, $rand );
	}
	return $token;
}

/** Scoate un token (ieșirea), cu tokenurile push legate de el. */
function ah_app_revoca( $id ) {
	global $wpdb;
	$wpdb->delete( ah_app_tabel_push(), array( 'token_id' => (int) $id ) );
	$wpdb->delete( ah_app_tabel_tokenuri(), array( 'id' => (int) $id ) );
}

/* ==================================================================
   4. CODUL DE 6 CIFRE
   ================================================================== */

function ah_app_hash_cod( $cod, $email ) {
	return hash_hmac( 'sha256', (string) $cod . '|' . $email, wp_salt( 'auth' ) . 'ah_app_cod' );
}

/**
 * Un cod nou pentru adresă (îl înlocuiește pe cel vechi). Întoarce codul în clar.
 * Încercările pe cod pornesc de la 0, dar cele din ultimele 24 de ore (zi_n) rămân.
 */
function ah_app_cod_nou( $email ) {
	global $wpdb;
	$t   = ah_app_tabel_coduri();
	$cod = (string) random_int( 100000, 999999 );
	$d   = array( 'hash' => ah_app_hash_cod( $cod, $email ), 'n' => 0, 'exp' => time() + AH_APP_COD_MIN * MINUTE_IN_SECONDS );
	$are = $wpdb->get_row( $wpdb->prepare( "SELECT email FROM {$t} WHERE email = %s", $email ), ARRAY_A );
	if ( $are || ! $wpdb->insert( $t, $d + array( 'email' => $email, 'zi_n' => 0, 'zi_start' => 0 ) ) ) {
		$wpdb->update( $t, $d, array( 'email' => $email ) ); // și dacă o cerere paralelă a apucat să-l insereze
	}
	return $cod;
}

/** True dacă adresa a consumat cele AH_APP_COD_ZI încercări din ultimele 24 de ore. */
function ah_app_cod_blocat( $email ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT zi_n, zi_start FROM ' . ah_app_tabel_coduri() . ' WHERE email = %s', $email ), ARRAY_A );
	return $r && (int) $r['zi_start'] >= time() - DAY_IN_SECONDS && (int) $r['zi_n'] >= AH_APP_COD_ZI;
}

/**
 * Verifică codul: 'ok', 'cod_gresit', 'cod_expirat' sau 'prea_multe'.
 * Întâi rezervă o încercare cu un singur UPDATE atomic (n < 5 pe cod, zi_n < 10 pe adresă în 24 h),
 * abia apoi compară. Așa, cererile paralele nu pot primi mai multe încercări decât e voie.
 * Încercarea bună șterge rândul (cu tot cu numărătoarea pe zi); a 5-a greșită anulează codul.
 * Comparația e în timp constant (hash_equals).
 */
function ah_app_cod_verifica( $email, $cod ) {
	global $wpdb;
	$t    = ah_app_tabel_coduri();
	$acum = time();
	$prag = $acum - DAY_IN_SECONDS;
	// Expresiile nu depind de ordinea atribuirilor (MySQL le face de la stânga la dreapta, SQL standard nu).
	$rez = $wpdb->query( $wpdb->prepare(
		"UPDATE {$t} SET n = n + 1,
			zi_n = CASE WHEN zi_start < %d THEN 1 ELSE zi_n + 1 END,
			zi_start = CASE WHEN zi_start < %d THEN %d ELSE zi_start END
		WHERE email = %s AND hash <> '' AND n < %d AND exp >= %d AND ( zi_n < %d OR zi_start < %d )",
		$prag, $prag, $acum, $email, AH_APP_COD_INCERCARI, $acum, AH_APP_COD_ZI, $prag
	) );
	if ( ! $rez ) {
		return ah_app_cod_blocat( $email ) ? 'prea_multe' : 'cod_expirat';
	}
	$r   = $wpdb->get_row( $wpdb->prepare( "SELECT hash, n FROM {$t} WHERE email = %s", $email ), ARRAY_A );
	$cod = preg_replace( '/\D/', '', (string) $cod );
	if ( $r && 6 === strlen( $cod ) && hash_equals( (string) $r['hash'], ah_app_hash_cod( $cod, $email ) ) ) {
		// O singură cerere câștigă, chiar și cu același cod trimis de două ori deodată.
		return $wpdb->delete( $t, array( 'email' => $email, 'hash' => $r['hash'] ) ) ? 'ok' : 'cod_expirat';
	}
	if ( ! $r || (int) $r['n'] >= AH_APP_COD_INCERCARI ) {
		$wpdb->update( $t, array( 'hash' => '' ), array( 'email' => $email ) ); // codul nu mai merge; zi_n rămâne
		return 'cod_expirat';
	}
	return 'cod_gresit';
}

/** Emailul, în rama emailurilor din „Meditațiile mele” (snippetul 51), dacă e activ. */
function ah_app_trimite_email( $catre, $subiect, $continut ) {
	if ( function_exists( 'ah_med_trimite_email' ) ) {
		return ah_med_trimite_email( $catre, $subiect, $continut );
	}
	$html = '<html><body style="margin:0;padding:28px 16px;background:#FBF6F0"><div style="max-width:520px;margin:0 auto;font-family:Georgia,serif;font-size:16.5px;line-height:1.7;color:#3E2F28">'
		. $continut . '<p style="margin:24px 0 0;color:#6F5B4F">Cu drag,<br>Adela</p></div></body></html>';
	return wp_mail( $catre, $subiect, $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: Adela Haru <' . AH_APP_CONTACT . '>', 'Reply-To: Adela Haru <' . AH_APP_CONTACT . '>' ) );
}

function ah_app_buton( $url, $text ) {
	if ( function_exists( 'ah_med_buton' ) ) {
		return ah_med_buton( $url, $text );
	}
	return '<p style="margin:26px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 30px;border-radius:50px;background:#96593F;color:#fff;font-family:Helvetica,Arial,sans-serif;font-size:13px;font-weight:bold;letter-spacing:1.4px;text-transform:uppercase;text-decoration:none">' . esc_html( $text ) . '</a></p>';
}

function ah_app_link_intra( $email, $cod ) {
	return add_query_arg( array( 'e' => rawurlencode( $email ), 'c' => $cod ), home_url( '/app/intra' ) );
}

/* CIORNĂ */
function ah_app_email_cod( $email, $cod, $nume ) {
	return ah_app_trimite_email( $email, 'Codul tău pentru aplicația Adela Haru: ' . $cod,
		'<p style="font-size:24px;margin:0 0 18px">' . ( $nume ? esc_html( $nume ) . ',' : 'Bună,' ) . '</p>'
		. '<p style="margin:0 0 10px;color:#6F5B4F">Codul tău pentru a intra în aplicația Adela Haru este:</p>'
		. '<p style="font-family:Helvetica,Arial,sans-serif;font-size:34px;letter-spacing:8px;font-weight:bold;margin:8px 0 18px;color:#3E2F28">' . esc_html( $cod ) . '</p>'
		. '<p style="margin:0 0 10px;color:#6F5B4F">Dacă deschizi emailul pe telefonul pe care ai aplicația, poți apăsa butonul de mai jos și codul se completează singur.</p>'
		. ah_app_buton( ah_app_link_intra( $email, $cod ), 'Intră în aplicație' )
		. '<p style="margin:0;color:#6F5B4F">Codul e valabil ' . AH_APP_COD_MIN . ' minute. Dacă nu l-ai cerut tu, poți ignora liniștită emailul acesta.</p>' );
}

/* ==================================================================
   5. CINE ARE ACCES (din snippeturile 51, 154, 44)
   ================================================================== */

/** Accesul la meditații (ah_med_acces), cu valori goale dacă snippetul 51 lipsește. */
function ah_app_acces( $email ) {
	$gol = array( 'pana' => 0, 'produse' => array(), 'nume' => '', 'are' => false );
	if ( ! function_exists( 'ah_med_acces' ) ) {
		return $gol;
	}
	$a = ah_med_acces( $email );
	return is_array( $a ) ? array_merge( $gol, $a ) : $gol;
}

function ah_app_prenume( $email, $acc = null ) {
	$acc = $acc ? $acc : ah_app_acces( $email );
	if ( ! empty( $acc['nume'] ) ) {
		return (string) $acc['nume'];
	}
	$u = get_user_by( 'email', $email );
	return $u && $u->first_name ? (string) $u->first_name : '';
}

/** Ultima comandă Inner Temple (snippetul 154): planul și abonamentul Stripe. */
function ah_app_comanda_it( $email ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return null;
	}
	$o = wc_get_orders( array(
		'billing_email' => $email,
		'status'        => array( 'processing', 'completed' ),
		'meta_key'      => '_ah_it_plan', // phpcs:ignore
		'orderby'       => 'date',
		'order'         => 'DESC',
		'limit'         => 1,
	) );
	return $o ? $o[0] : null;
}

/**
 * Se reînnoiește? Abonament Stripe activ și neanulat. Doar o citire (GET) la Stripe, prin ah_it_api,
 * ținută minte 6 ore. Fără snippetul 154: false.
 */
function ah_app_se_reinnoieste( $sub ) {
	if ( ! $sub || ! function_exists( 'ah_it_api' ) ) {
		return false;
	}
	$k = 'ah_app_sub_' . md5( $sub );
	$v = get_transient( $k );
	if ( false !== $v ) {
		return '1' === $v;
	}
	$s  = ah_it_api( 'GET', 'subscriptions/' . rawurlencode( $sub ) );
	if ( ! empty( $s['error'] ) ) {
		set_transient( $k, '0', 30 * MINUTE_IN_SECONDS );
		return false;
	}
	$da = in_array( (string) ( $s['status'] ?? '' ), array( 'active', 'trialing', 'past_due' ), true ) && empty( $s['cancel_at_period_end'] ) && empty( $s['cancel_at'] );
	set_transient( $k, $da ? '1' : '0', 6 * HOUR_IN_SECONDS );
	return $da;
}

/* Când Stripe anunță o schimbare (anulare, încheiere), uităm ce știam. Nu schimbă nimic în snippetul 154. */
add_action( 'rest_api_init', function () {
	add_filter( 'rest_pre_dispatch', function ( $rez, $server, $req ) {
		if ( $req instanceof WP_REST_Request && '/ah-it/v1/stripe' === $req->get_route() ) {
			$ev = json_decode( (string) $req->get_body(), true );
			$id = (string) ( $ev['data']['object']['id'] ?? '' );
			if ( 0 === strpos( $id, 'sub_' ) ) {
				delete_transient( 'ah_app_sub_' . md5( $id ) );
			}
		}
		return $rez;
	}, 10, 3 );
} );

/** Blocul inner_temple din /eu. */
function ah_app_inner_temple( $email, $acc ) {
	$activ = (int) $acc['pana'] > time();
	$tip   = null;
	$sub   = '';
	$c     = ah_app_comanda_it( $email );
	if ( $c ) {
		$p   = (string) $c->get_meta( '_ah_it_plan' );
		$tip = in_array( $p, array( 'lunar', 'anual' ), true ) ? $p : null;
		$sub = (string) $c->get_meta( '_ah_it_abonament' );
	}
	if ( ! $tip && $activ && function_exists( 'ah_med_abonamente' ) && function_exists( 'wc_get_orders' ) ) {
		// Abonament vechi (fără Stripe): după zilele produsului.
		$ab = ah_med_abonamente();
		foreach ( wc_get_orders( array( 'billing_email' => $email, 'status' => array( 'processing', 'completed' ), 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			foreach ( $o->get_items() as $l ) {
				$z = $ab[ (int) $l->get_product_id() ] ?? 0;
				if ( $z ) {
					$tip = $z >= 300 ? 'anual' : 'lunar';
					break 2;
				}
			}
		}
	}
	return array(
		'activ'          => $activ,
		'pana_la'        => $acc['pana'] ? ah_app_zi( $acc['pana'] ) : null,
		'tip'            => $tip,
		'se_reinnoieste' => $activ && ah_app_se_reinnoieste( $sub ),
	);
}

/* ==================================================================
   6. LINKURILE SEMNATE (media și player)
   ================================================================== */

function ah_app_cheie_semn() {
	return wp_salt( 'auth' ) . 'ah_app_media';
}

/** Semnătura: HMAC-SHA256 peste părți (tip, id-uri, token, email, expirare). */
function ah_app_semn( array $parti ) {
	return hash_hmac( 'sha256', implode( '|', array_map( 'strval', $parti ) ), ah_app_cheie_semn() );
}

/** Semnătura e bună și linkul nu a expirat (și nu e din viitorul îndepărtat)? */
function ah_app_semn_bun( array $parti, $exp, $sig ) {
	$exp = (int) $exp;
	if ( $exp < time() || $exp > time() + ( AH_APP_LINK_ORE + 1 ) * HOUR_IN_SECONDS ) {
		return false;
	}
	return is_string( $sig ) && 64 === strlen( $sig ) && hash_equals( ah_app_semn( $parti ), $sig );
}

function ah_app_exp() {
	return time() + AH_APP_LINK_ORE * HOUR_IN_SECONDS;
}

/** Linkul audio al unei meditații, legat de token (deci de email). */
function ah_app_link_meditatie( $id, $u, $exp = 0 ) {
	$exp = $exp ? $exp : ah_app_exp();
	return add_query_arg( array(
		'ah_app_media' => 'm' . (int) $id,
		't'            => (int) $u['id'],
		'exp'          => $exp,
		'sig'          => ah_app_semn( array( 'm', (int) $id, (int) $u['id'], $u['email'], $exp ) ),
	), home_url( '/' ) );
}

function ah_app_media_id( $url ) {
	return function_exists( 'ah_sc_media_id' ) ? ah_sc_media_id( $url ) : substr( md5( (string) $url ), 0, 16 );
}

/** Linkul unui fișier dintr-o lecție (mp3, mp4 sau pdf). */
function ah_app_link_lectie( $comanda_id, $slug, $k, $url, $u, $exp = 0 ) {
	$exp = $exp ? $exp : ah_app_exp();
	$f   = ah_app_media_id( $url );
	return add_query_arg( array(
		'ah_app_media' => 'l' . $f,
		'c'            => (int) $comanda_id,
		's'            => $slug,
		'k'            => $k,
		't'            => (int) $u['id'],
		'exp'          => $exp,
		'sig'          => ah_app_semn( array( 'l', (int) $comanda_id, $slug, $k, $f, (int) $u['id'], $u['email'], $exp ) ),
	), home_url( '/' ) );
}

/** /app/player pentru explicația video a unei meditații. */
function ah_app_player_meditatie( $id, $u, $exp = 0 ) {
	$exp = $exp ? $exp : ah_app_exp();
	return add_query_arg( array(
		'm'   => (int) $id,
		't'   => (int) $u['id'],
		'exp' => $exp,
		'sig' => ah_app_semn( array( 'pm', (int) $id, (int) $u['id'], $u['email'], $exp ) ),
	), home_url( '/app/player' ) );
}

/** /app/player pentru o lecție Vimeo din sala de curs. */
function ah_app_player_lectie( $comanda_id, $slug, $cheie, $u, $exp = 0 ) {
	$exp = $exp ? $exp : ah_app_exp();
	return add_query_arg( array(
		'c'   => (int) $comanda_id,
		's'   => $slug,
		'l'   => rawurlencode( $cheie ),
		't'   => (int) $u['id'],
		'exp' => $exp,
		'sig' => ah_app_semn( array( 'pl', (int) $comanda_id, $slug, $cheie, (int) $u['id'], $u['email'], $exp ) ),
	), home_url( '/app/player' ) );
}

/* ==================================================================
   7. MEDITAȚIILE (snippetul 51)
   ================================================================== */

/** Slugul categoriei („Respirație” ⇒ „respiratie”). */
function ah_app_slug( $t ) {
	$t = remove_accents( (string) $t );
	return sanitize_title( $t );
}

/** Pozițiile de ascultare (sn51 le ține doar în browser, deci aici: o opțiune pe adresă). */
function ah_app_cheie_poz( $email ) {
	return 'ah_app_poz_' . md5( $email );
}
function ah_app_pozitii( $email ) {
	$p = get_option( ah_app_cheie_poz( $email ), array() );
	return is_array( $p ) ? $p : array();
}

/** Datele unei meditații pentru aplicație. Fără preț și fără link de cumpărare. */
function ah_app_meditatie( $id, $acc, $u, $poz ) {
	$poate = function_exists( 'ah_med_poate' ) && ah_med_poate( $id, $acc );
	$prod  = (int) ah_med_m( $id, 'produs' );
	if ( '1' === ah_med_m( $id, 'gratuita' ) ) {
		$acces = 'gratuita';
	} elseif ( $prod && ! empty( $acc['produse'][ $prod ] ) ) {
		$acces = 'cumparata';
	} elseif ( '0' !== ah_med_m( $id, 'abonament' ) ) {
		$acces = 'abonament';
	} else {
		$acces = 'cumparata';
	}
	$video = null;
	$v     = ah_med_m( $id, 'video' );
	if ( $poate && '' !== $v && ( ! function_exists( 'ah_med_vimeo' ) || ah_med_vimeo( $v ) ) ) {
		$video = array( 'tip' => 'vimeo', 'embed' => ah_app_player_meditatie( $id, $u ) );
	}
	$poza = get_the_post_thumbnail_url( $id, 'medium_large' );
	$text = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
	$cat  = ah_med_m( $id, 'categorie' );
	$pub  = (int) get_post_time( 'U', true, $id );
	return array(
		'id'            => (int) $id,
		'titlu'         => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'text'          => html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ),
		'poza'          => $poza ? $poza : null,
		'durata'        => function_exists( 'ah_med_durata' ) ? (int) ah_med_durata( $id ) : 0,
		'categorie'     => '' !== $cat ? ah_app_slug( $cat ) : null,
		'acces'         => $acces,
		'poate_asculta' => $poate,
		'are_audio'     => '' !== ah_med_m( $id, 'audio' ),
		'video'         => $video,
		'pozitie'       => isset( $poz[ $id ]['s'] ) ? (int) $poz[ $id ]['s'] : 0,
		'noua'          => $pub > time() - 14 * DAY_IN_SECONDS,
	);
}

/* ==================================================================
   8. CURSURILE (snippetul 44: ah_sc_*)
   Fiecare element din $curs['module'] e o LECȚIE; lecțiile se adună în module după 'grup',
   exact ca în sala de curs (grup 0 sau lipsă = lecție singură).
   ================================================================== */

/** Grupurile (modulele) din lista de lecții, ca în JavaScript-ul sălii (var GR). */
function ah_app_grupuri( $lectii ) {
	$gr = array();
	foreach ( array_values( (array) $lectii ) as $i => $m ) {
		$g  = isset( $m['grup'] ) ? (int) $m['grup'] : 0;
		$id = $g ? 'm' . $g : 'l' . $i;
		if ( ! isset( $gr[ $id ] ) ) {
			$gr[ $id ] = array( 'id' => $id, 'grup' => $g, 'titlu' => (string) ( $m['titlu'] ?? '' ), 'lectii' => array() );
		}
		$gr[ $id ]['lectii'][] = $i;
	}
	return array_values( $gr );
}

/**
 * Ce lecții sunt deschise (ca liber() din sală): fără „pe rând”, toate; altfel până la prima neterminată,
 * plus cele deja terminate.
 */
function ah_app_lectii_libere( $n, $gata, $pe_rand ) {
	$gata = array_map( 'intval', (array) $gata );
	$prim = $n;
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! in_array( $i, $gata, true ) ) {
			$prim = $i;
			break;
		}
	}
	$o = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$o[ $i ] = ! $pe_rand || $i <= $prim || in_array( $i, $gata, true );
	}
	return $o;
}

/** Durata în secunde din textul sălii („30 min”, „29 de minute”, „cam 2 ore și jumătate”, „o oră și 45 de minute”); null dacă nu se poate. */
function ah_app_durata_secunde( $t ) {
	$t = mb_strtolower( trim( (string) $t ) );
	if ( '' === $t ) {
		return null;
	}
	$s = 0;
	$gasit = false;
	if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:ore|oră|ora|h)\b/u', $t, $m ) ) {
		$s += (int) round( (float) str_replace( ',', '.', $m[1] ) * 3600 );
		$gasit = true;
	} elseif ( preg_match( '/\b(?:o|1)\s+(?:oră|ora)\b/u', $t ) ) {
		$s += 3600;
		$gasit = true;
	}
	if ( $gasit && false !== strpos( $t, 'jumătate' ) ) {
		$s += 1800;
	}
	// „29 de minute”, „cam 2 ore și 40 de minute”, „4 min + meditația de 33 min” (se adună).
	if ( preg_match_all( '/(\d+)\s*(?:de\s+)?(?:min|minute)\b/u', $t, $m ) ) {
		foreach ( $m[1] as $x ) {
			$s += (int) $x * 60;
		}
		$gasit = true;
	}
	return $gasit ? $s : null;
}

/** Textul de citit din lecție ('citeste' + 'texte' din curs), plus nota și întrebările de jurnal: HTML simplu. */
function ah_app_text_html( $blocuri, $nota = '', $jurnal = array() ) {
	$h     = '';
	$lista = false;
	foreach ( (array) $blocuri as $b ) {
		if ( ! is_array( $b ) || ! isset( $b[0], $b[1] ) ) {
			continue;
		}
		if ( 'li' === $b[0] ) {
			if ( ! $lista ) {
				$h    .= '<ul>';
				$lista = true;
			}
			$h .= '<li>' . esc_html( $b[1] ) . '</li>';
			continue;
		}
		if ( $lista ) {
			$h    .= '</ul>';
			$lista = false;
		}
		$h .= 'h' === $b[0] ? '<h4>' . esc_html( $b[1] ) . '</h4>' : '<p>' . esc_html( $b[1] ) . '</p>';
	}
	if ( $lista ) {
		$h .= '</ul>';
	}
	if ( '' !== trim( (string) $nota ) ) {
		$h .= '<p>' . esc_html( $nota ) . '</p>';
	}
	$jurnal = array_filter( array_map( 'strval', (array) $jurnal ) );
	if ( $jurnal ) {
		$h .= '<h4>Întrebări pentru jurnal</h4><ul>';
		foreach ( $jurnal as $q ) {
			$h .= '<li>' . esc_html( $q ) . '</li>';
		}
		$h .= '</ul>';
	}
	return $h;
}

/**
 * Cursurile adresei: slug => array( comanda, curs, produs ). Aceeași regulă ca ah_cm_cursuri_pentru
 * (snippetul 163): comenzi plătite, cea mai nouă câștigă, fără cele expirate.
 */
function ah_app_cursuri_email( $email ) {
	static $cache = array();
	if ( isset( $cache[ $email ] ) ) {
		return $cache[ $email ];
	}
	$out = array();
	if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'ah_sc_din_comanda' ) ) {
		return $cache[ $email ] = $out;
	}
	$comenzi = wc_get_orders( array(
		'billing_email' => $email,
		'status'        => array( 'wc-processing', 'wc-completed', 'wc-ah-acces', 'wc-col-shipping-init' ),
		'limit'         => 100,
		'orderby'       => 'date',
		'order'         => 'DESC',
	) );
	foreach ( $comenzi as $c ) {
		if ( strtolower( trim( $c->get_billing_email() ) ) !== $email ) {
			continue;
		}
		if ( function_exists( 'ah_sc_platita' ) && ! ah_sc_platita( $c ) ) {
			continue;
		}
		foreach ( ah_sc_din_comanda( $c ) as $pid => $curs ) {
			$slug = (string) ( $curs['slug'] ?? '' );
			if ( '' === $slug || isset( $out[ $slug ] ) ) {
				continue;
			}
			if ( function_exists( 'ah_sc_expirat' ) && ah_sc_expirat( $c, $curs ) ) {
				continue;
			}
			$out[ $slug ] = array( 'comanda' => $c, 'curs' => $curs, 'produs' => (int) $pid );
		}
	}
	return $cache[ $email ] = $out;
}

/** Pe rând? (echipa și cursantele care revin văd tot deschis, ca în sală). */
function ah_app_pe_rand( $c, $curs ) {
	return ! empty( $curs['pe_rand'] )
		&& ! ( function_exists( 'ah_sc_echipa' ) && ah_sc_echipa( $c ) )
		&& ! ( function_exists( 'ah_sc_revine' ) && ah_sc_revine( $c, $curs ) );
}

/** Lecțiile bifate (numerele de acum), din același loc ca sala. */
function ah_app_bife( $c, $curs ) {
	if ( function_exists( 'ah_sc_bife_citeste' ) ) {
		return array_map( 'intval', (array) ah_sc_bife_citeste( $c, $curs ) );
	}
	$pr = (array) $c->get_meta( '_ah_sc_progres' );
	return array_map( 'intval', (array) ( $pr[ $curs['slug'] ] ?? array() ) );
}

function ah_app_imagine_curs( $pid ) {
	$u = $pid ? get_the_post_thumbnail_url( $pid, 'medium_large' ) : '';
	return $u ? $u : null;
}

/** Rezumatul unui curs (pentru /eu și /cursuri). */
function ah_app_curs_scurt( $x ) {
	$c     = $x['comanda'];
	$curs  = $x['curs'];
	$lec   = array_values( (array) $curs['module'] );
	$gata  = ah_app_bife( $c, $curs );
	$gr    = ah_app_grupuri( $lec );
	$term  = 0;
	foreach ( $gr as $g ) {
		if ( ! array_diff( $g['lectii'], $gata ) ) {
			$term++;
		}
	}
	$pana = null;
	if ( ( ! empty( $curs['zile'] ) || ! empty( $curs['pana_fix'] ) ) && function_exists( 'ah_sc_sfarsit' ) ) {
		$pana = ah_app_zi( ah_sc_sfarsit( $c, $curs ) );
	}
	return array(
		'slug'             => (string) $curs['slug'],
		'nume'             => (string) ( $curs['nume'] ?? $curs['slug'] ),
		'imagine'          => ah_app_imagine_curs( $x['produs'] ),
		'pana_la'          => $pana,
		'progres'          => $lec ? round( count( array_intersect( array_keys( $lec ), $gata ) ) / count( $lec ), 2 ) : 0,
		'module_total'     => count( $gr ),
		'module_terminate' => $term,
	);
}

/**
 * Programul modulelor Wild Love (citit din aceeași opțiune ca sala: ah_live_setari[456]['sesiuni']).
 * grup => momentul în care se deschide. Pentru celelalte cursuri: nimic (sunt deschise de la început).
 */
function ah_app_program_curs( $slug ) {
	if ( 'wild-love' !== $slug ) {
		return array();
	}
	$set   = get_option( 'ah_live_setari', array() );
	$ses   = is_array( $set ) && ! empty( $set[456]['sesiuni'] ) ? (string) $set[456]['sesiuni'] : '';
	$inreg = is_array( $set ) && ! empty( $set[456]['inregistrate'] ) && is_array( $set[456]['inregistrate'] ) ? $set[456]['inregistrate'] : array();
	$o     = array();
	$nr    = 0;
	foreach ( preg_split( '/\r\n|\r|\n/', $ses ) as $r ) {
		$r = trim( $r );
		if ( '' === $r ) {
			continue;
		}
		$nr++;
		$d = substr( $r, 0, 16 );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $d ) ) {
			$t = DateTime::createFromFormat( 'Y-m-d H:i', $d, new DateTimeZone( 'Europe/Bucharest' ) );
			if ( $t ) {
				$o[ $nr ] = $t->getTimestamp() + ( isset( $inreg[ $d ] ) ? 0 : 2 * HOUR_IN_SECONDS );
			}
		}
	}
	return $o;
}

/** Toate lecțiile cursului, și cele încă închise (ca pentru contul echipei), doar pentru titluri. */
function ah_app_toate_lectiile( $pid ) {
	if ( ! function_exists( 'ah_sc_cursuri' ) ) {
		return array();
	}
	$inainte = $GLOBALS['ah_sc_toate_lectiile'] ?? null;
	$GLOBALS['ah_sc_toate_lectiile'] = 1;
	$toate = ah_sc_cursuri();
	if ( null === $inainte ) {
		unset( $GLOBALS['ah_sc_toate_lectiile'] );
	} else {
		$GLOBALS['ah_sc_toate_lectiile'] = $inainte;
	}
	return isset( $toate[ $pid ]['module'] ) ? array_values( (array) $toate[ $pid ]['module'] ) : array();
}

function ah_app_e_local( $url ) {
	if ( function_exists( 'ah_sc_e_local' ) ) {
		return ah_sc_e_local( $url );
	}
	$up = wp_upload_dir( null, false );
	return 0 === strpos( preg_replace( '#^https?:#', '', (string) $url ), preg_replace( '#^https?:#', '', $up['baseurl'] ) );
}

/** Cheia stabilă a lecției $i (aceeași ca în sală: _ah_sc_bife, _ah_sc_poz_k). */
function ah_app_cheie_lectie( $lec, $i ) {
	return function_exists( 'ah_sc_cheie_lectie' ) ? ah_sc_cheie_lectie( $lec, $i ) : 't' . substr( md5( (string) ( $lec[ $i ]['titlu'] ?? '' ) . '|' . (string) ( $lec[ $i ]['nr'] ?? '' ) ), 0, 16 );
}

/** O lecție pentru aplicație. */
function ah_app_lectie( $c, $curs, $lec, $i, $deschisa, $gata, $u ) {
	$m     = $lec[ $i ];
	$slug  = (string) $curs['slug'];
	$cheie = ah_app_cheie_lectie( $lec, $i );
	$vimeo = ! empty( $m['vimeo'] ) && function_exists( 'ah_sc_vimeo' ) && ah_sc_vimeo( (string) $m['vimeo'] );
	$mp3   = (string) ( $m['mp3'] ?? '' );
	$mp4   = (string) ( $m['mp4'] ?? '' );
	$pdf   = (string) ( $m['pdf'] ?? '' );
	$link  = function ( $k, $url ) use ( $c, $slug, $u ) {
		if ( '' === $url ) {
			return null;
		}
		return ah_app_e_local( $url ) ? ah_app_link_lectie( $c->get_id(), $slug, $k, $url, $u ) : esc_url_raw( $url );
	};
	if ( $vimeo || '' !== $mp4 ) {
		$tip = 'video';
	} elseif ( '' !== $mp3 ) {
		$tip = 'audio';
	} elseif ( '' !== $pdf || ! empty( $m['pdfuri'] ) ) {
		$tip = 'pdf';
	} else {
		$tip = 'text';
	}
	$blocuri = array();
	if ( ! empty( $m['citeste'][0] ) && ! empty( $curs['texte'][ $m['citeste'][0] ] ) ) {
		$blocuri = array_merge( array( array( 'h', (string) ( $m['citeste'][1] ?? '' ) ) ), (array) $curs['texte'][ $m['citeste'][0] ] );
	}
	$text = ah_app_text_html( $blocuri, (string) ( $m['nota'] ?? '' ), (array) ( $m['jurnal'] ?? array() ) );
	$fisier = null;
	if ( $deschisa ) {
		if ( '' !== $mp3 ) {
			$fisier = $link( 'mp3', $mp3 );
		} elseif ( '' !== $mp4 ) {
			$fisier = $link( 'mp4', $mp4 );
		} elseif ( '' !== $pdf ) {
			$fisier = $link( 'pdf', $pdf );
		}
	}
	$atas = array();
	foreach ( (array) ( $m['pdfuri'] ?? array() ) as $x ) {
		if ( is_array( $x ) && ! empty( $x[1] ) && '{recenzie}' !== $x[1] && $deschisa ) {
			$atas[] = array( 'titlu' => (string) $x[0], 'url' => $link( 'pdf', (string) $x[1] ) );
		}
	}
	return array(
		'cheie'       => $cheie,
		'titlu'       => (string) ( $m['titlu'] ?? '' ),
		'nr'          => (string) ( $m['nr'] ?? '' ),
		'tip'         => $tip,
		'durata'      => ah_app_durata_secunde( $m['durata'] ?? '' ),
		'durata_text' => (string) ( $m['durata'] ?? '' ),
		'deschisa'    => (bool) $deschisa,
		'player'      => ( $deschisa && $vimeo ) ? ah_app_player_lectie( $c->get_id(), $slug, $cheie, $u ) : null,
		'fisier'      => $fisier,
		'audio_titlu' => (string) ( $m['audio_titlu'] ?? '' ),
		'pdf'         => ( $deschisa && '' !== $pdf && '' !== $mp3 . $mp4 ) ? $link( 'pdf', $pdf ) : null,
		'atasamente'  => $atas,
		'text'        => '' !== $text ? $text : null,
		'terminata'   => in_array( $i, $gata, true ),
	);
}

/** Cursul întreg, cu modulele și lecțiile. */
function ah_app_curs_detaliu( $x, $u ) {
	$c     = $x['comanda'];
	$curs  = $x['curs'];
	$lec   = array_values( (array) $curs['module'] );
	$gata  = ah_app_bife( $c, $curs );
	$liber = ah_app_lectii_libere( count( $lec ), $gata, ah_app_pe_rand( $c, $curs ) );
	$module = array();
	$vazute = array();
	foreach ( ah_app_grupuri( $lec ) as $g ) {
		$l = array();
		$d = false;
		foreach ( $g['lectii'] as $i ) {
			$l[] = ah_app_lectie( $c, $curs, $lec, $i, $liber[ $i ], $gata, $u );
			$d   = $d || $liber[ $i ];
		}
		$vazute[ $g['id'] ] = 1;
		$module[] = array( 'id' => $g['id'], 'titlu' => $g['titlu'], 'deschis' => $d, 'se_deschide_la' => null, 'lectii' => $l );
	}
	// Modulele programate (încă ascunse în sală): apar închise, cu data, fără fișiere.
	$prog = ah_app_program_curs( (string) $curs['slug'] );
	if ( $prog ) {
		$toate = ah_app_toate_lectiile( $x['produs'] );
		foreach ( ah_app_grupuri( $toate ) as $g ) {
			if ( isset( $vazute[ $g['id'] ] ) || ! $g['grup'] || empty( $prog[ $g['grup'] ] ) || $prog[ $g['grup'] ] <= time() ) {
				continue;
			}
			$l = array();
			foreach ( $g['lectii'] as $i ) {
				$l[] = array(
					'cheie' => ah_app_cheie_lectie( $toate, $i ), 'titlu' => (string) ( $toate[ $i ]['titlu'] ?? '' ), 'nr' => (string) ( $toate[ $i ]['nr'] ?? '' ),
					'tip' => 'text', 'durata' => ah_app_durata_secunde( $toate[ $i ]['durata'] ?? '' ), 'durata_text' => (string) ( $toate[ $i ]['durata'] ?? '' ),
					'deschisa' => false, 'player' => null, 'fisier' => null, 'audio_titlu' => '', 'pdf' => null, 'atasamente' => array(), 'text' => null, 'terminata' => false,
				);
			}
			$module[] = array( 'id' => $g['id'], 'titlu' => $g['titlu'], 'deschis' => false, 'se_deschide_la' => ah_app_iso( $prog[ $g['grup'] ] ), 'lectii' => $l );
		}
	}
	$scurt = ah_app_curs_scurt( $x );
	return array(
		'slug'      => $scurt['slug'],
		'nume'      => $scurt['nume'],
		'imagine'   => $scurt['imagine'],
		'descriere' => (string) ( $curs['bun_venit'] ?? '' ),
		'cum'       => array_values( array_map( 'strval', (array) ( $curs['cum'] ?? array() ) ) ),
		'pana_la'   => $scurt['pana_la'],
		'progres'   => $scurt['progres'],
		'pe_rand'   => ah_app_pe_rand( $c, $curs ),
		'mai_vin'   => ! empty( $curs['mai_vin'] ) ? (string) $curs['mai_vin'] : null,
		'module'    => $module,
	);
}

/** Jurnalul ascultărilor (snippetul 146): aceeași meta și aceeași regulă (o pornire la 30 de minute). */
function ah_app_ascultare( $c, $slug, $m ) {
	if ( ! defined( 'AH_ASC_META' ) || ! $c ) {
		return;
	}
	$titlu = mb_substr( sanitize_text_field( (string) ( $m['titlu'] ?? '' ) ), 0, 160 );
	$nr    = mb_substr( sanitize_text_field( (string) ( $m['nr'] ?? '' ) ), 0, 160 );
	if ( '' === $titlu ) {
		return;
	}
	$k   = substr( md5( $titlu . '|' . $nr ), 0, 10 );
	$j   = $c->get_meta( AH_ASC_META );
	$j   = is_array( $j ) ? $j : array();
	$x   = $j[ $slug ][ $k ] ?? array( 't' => $titlu, 'nr' => $nr, 'n' => 0, 'prima' => 0, 'ultim' => 0 );
	$now = time();
	if ( $x['ultim'] && $now - (int) $x['ultim'] < 30 * MINUTE_IN_SECONDS ) {
		return;
	}
	$x['n']     = (int) $x['n'] + 1;
	$x['prima'] = $x['prima'] ? $x['prima'] : $now;
	$x['ultim'] = $now;
	$j[ $slug ][ $k ] = $x;
	$c->update_meta_data( AH_ASC_META, $j );
	$c->update_meta_data( '_ah_sc_ascultat_ultim', $now );
	$c->save_meta_data();
}

/* ==================================================================
   9. CICLUL (snippetul 41: același tabel wp_ah_ciclu, aceeași abonare la emailuri)
   ================================================================== */

/** Textele fazelor: din ah_ciclu_faze() (snippetul 41); o copie doar dacă 41 nu e activ. */
function ah_app_faze_sursa() {
	if ( function_exists( 'ah_ciclu_faze' ) ) {
		return ah_ciclu_faze();
	}
	return array(
		'menstruala'    => array( 'nume' => 'Faza menstruală', 'arhetip' => 'Alchimista', 'anotimp' => 'iarna', 'luna' => 'Luna nouă', 'culoare' => '#8C4A3A',
			'pe_scurt' => 'Faza de răgaz, de introspecție și profunzime spirituală. Felul în care trăiești această fază îți stabilește cum vei fi în următoarea lună.',
			'simti' => 'În timpul acestei faze, femeia simte o nevoie mare de a fi singură, de a se retrage, pentru a se regenera energetic, în special în primele două zile.',
			'ajuta' => array( 'Introspecție, meditație, odihnă.', 'Curățare energetică și intenții noi pentru luna care vine.', 'Spre finele acestei perioade, e ideal să-ți setezi intenții sau chiar să-ți planifici luna care urmează.' ),
			'practica' => 'Permite-i să-și îndeplinească funcția și dă-ți voie să renaști în fiecare lună ca femeie.' ),
		'foliculara'    => array( 'nume' => 'Faza foliculară', 'arhetip' => 'Fecioara', 'anotimp' => 'primăvara', 'luna' => 'Luna în creștere', 'culoare' => '#8F9A6E',
			'pe_scurt' => 'Faza cea mai dinamică a lunii, corespunzătoare Lunii în creștere. Ideală pentru noi începuturi, pentru a porni planuri și proiecte.',
			'simti' => 'După o perioadă de retragere și de regenerare cum este menstruația, femeia primește darul reînnoirii din punct de vedere energetic.',
			'ajuta' => array( 'Dinamism, claritate mentală și extravertire.', 'Socializare, dezvoltare profesională și personală, activități sportive.', 'Energia se orientează mai mult spre exterior, fiind încărcată cu elanul de a acționa, de a schimba lucruri, de a construi ceva nou.' ),
			'practica' => 'După fiecare menstruație, femeia se întoarce din nou la starea de virginitate și puritate.' ),
		'ovulatorie'    => array( 'nume' => 'Faza ovulatorie', 'arhetip' => 'Mama', 'anotimp' => 'vara', 'luna' => 'Luna plină', 'culoare' => '#C29A4A',
			'pe_scurt' => 'Faza expresivității și a comunicării. Perioada ideală pentru a crea, pentru a asculta și a oferi sprijin, pentru profunzime în sexualitate.',
			'simti' => 'În această etapă femeia simte imboldul de a se dărui mai mult celorlalți. Dacă și-a trăit corespunzător perioada de regenerare, aceste activități nu o vor seca de energie, din contră, o vor încărca.',
			'ajuta' => array( 'Activități sociale, caritabile: a te dărui mai mult celorlalți.', 'Este o perioadă ideală pentru a ieși în lume.', 'Bucură-te din plin de ea: acum ai cel mai mult potențial creator.' ),
			'practica' => 'Rămâne să-ți observi cu atenție stările și să ții cont de ele.' ),
		'premenstruala' => array( 'nume' => 'Faza premenstruală', 'arhetip' => 'Fermecătoreasa', 'anotimp' => 'toamna', 'luna' => 'Luna în descreștere', 'culoare' => '#A9714F',
			'pe_scurt' => 'Faza interiorizării și a inspirației, care corespunde Lunii în descreștere și toamnei. Este o etapă în care energia își schimbă direcția.',
			'simti' => 'Scade nivelul de concentrare și nevoia de a socializa, dar simultan crește nivelul de intuiție și de inspirație.',
			'ajuta' => array( 'O perioadă ideală introspecției și comunicării cu sinele superior.', 'Idei noi îți pot da târcoale, ține doar de tine să le „seduci” și să le aduci în viața ta.', 'Nu este un moment potrivit pentru a iniția proiecte, cât mai degrabă pentru a termina ceea ce ai început sau amânat.' ),
			'practica' => 'Dacă ne schimbăm puțin perspectiva, putem să ne bucurăm de darurile pe care aceasta le aduce.' ),
	);
}

/** Meditația (id ah_meditatie) legată de un produs, dacă există. */
function ah_app_meditatie_din_produs( $pid ) {
	if ( ! $pid ) {
		return null;
	}
	$p = get_posts( array( 'post_type' => 'ah_meditatie', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_ah_med_produs', 'meta_value' => (string) (int) $pid ) ); // phpcs:ignore
	return $p ? (int) $p[0] : null;
}

/** Cursul (slugul din sala de curs) pentru un produs, dacă există. */
function ah_app_curs_din_produs( $pid ) {
	if ( ! $pid || ! function_exists( 'ah_sc_cursuri' ) ) {
		return null;
	}
	$t = ah_sc_cursuri();
	return isset( $t[ $pid ]['slug'] ) ? (string) $t[ $pid ]['slug'] : null;
}

/** Răspunsul /ciclu/faze. Fără produse, fără prețuri: doar id-ul meditației sau slugul cursului. */
function ah_app_faze_date() {
	$slug_anotimp = array( 'iarna' => 'iarna', 'primăvara' => 'primavara', 'vara' => 'vara', 'toamna' => 'toamna' );
	$prod  = function_exists( 'ah_ciclu_produse' ) ? ah_ciclu_produse() : array();
	$lungi = function_exists( 'ah_ciclu_texte_lungi' ) ? ah_ciclu_texte_lungi() : array();
	$lim   = ah_app_ciclu_limite( 28, 5 );
	$o     = array();
	foreach ( ah_app_faze_sursa() as $k => $f ) {
		$an  = (string) ( $f['anotimp'] ?? '' );
		$pid = isset( $prod[ $k ][0] ) ? (int) $prod[ $k ][0] : 0;
		$med = ah_app_meditatie_din_produs( $pid );
		$o[] = array(
			'slug'           => $slug_anotimp[ $an ] ?? ah_app_slug( $an ),
			'cheie'          => $k,
			'nume'           => (string) ( $f['nume'] ?? '' ),
			'arhetip'        => (string) ( $f['arhetip'] ?? '' ),
			'anotimp'        => function_exists( 'mb_convert_case' ) ? mb_convert_case( $an, MB_CASE_TITLE, 'UTF-8' ) : ucfirst( $an ),
			'luna'           => (string) ( $f['luna'] ?? '' ),
			'culoare'        => (string) ( $f['culoare'] ?? '' ),
			'ce_se_intampla' => trim( (string) ( $f['pe_scurt'] ?? '' ) . ' ' . (string) ( $f['simti'] ?? '' ) ),
			'ce_e_potrivit'  => implode( ' ', array_map( 'strval', (array) ( $f['ajuta'] ?? array() ) ) ),
			'potrivit_lista' => array_values( array_map( 'strval', (array) ( $f['ajuta'] ?? array() ) ) ),
			'practica'       => (string) ( $f['practica'] ?? '' ),
			'text_lung'      => array_values( array_map( 'strval', (array) ( $lungi[ $k ] ?? array() ) ) ),
			'zile_la_28_5'   => $lim[ $k ],
			'meditatie_id'   => $med,
			'curs_slug'      => $med ? null : ah_app_curs_din_produs( $pid ),
		);
	}
	return array(
		'faze'        => $o,
		'regula'      => 'Ziua 1 este prima zi de sângerare. Lungimea ciclului (c) e între 21 și 45 de zile, zilele de sângerare (m) între 2 și 10. '
			. 'Ovulația începe în ziua max(c − 16, m + 2) și ține până în ziua min(început + 4, c − 2). '
			. 'Faza menstruală: zilele 1…m; foliculară: m+1 … începutul ovulației − 1; ovulatorie: începutul … sfârșitul ovulației; premenstruală: de a doua zi după ovulație până în ziua c. '
			. 'Ziua din ciclu = (zile de la prima zi a ultimei menstruații) mod c + 1. Identic cu ah_ciclu_limite() și ah_ciclu_unde() din Harta ciclului.',
		'avertisment' => 'Fazele sunt o estimare, nu o metodă de contracepție.',
	);
}

/** Limitele fazelor (ah_ciclu_limite din 41, sau aceeași formulă dacă 41 lipsește). */
function ah_app_ciclu_limite( $c, $m ) {
	if ( function_exists( 'ah_ciclu_limite' ) ) {
		return ah_ciclu_limite( $c, $m );
	}
	$c   = max( 21, min( 45, (int) $c ) );
	$m   = max( 2, min( 10, (int) $m ) );
	$ovS = max( $c - 16, $m + 2 );
	$ovE = min( $ovS + 4, $c - 2 );
	return array(
		'menstruala'    => array( 1, $m ),
		'foliculara'    => array( $m + 1, $ovS - 1 ),
		'ovulatorie'    => array( $ovS, $ovE ),
		'premenstruala' => array( $ovE + 1, $c ),
	);
}

/**
 * Verifică datele trimise la PUT /ciclu. Întoarce array( 'ok' => true, 'date' => … ) sau array( 'ok' => false ).
 * Ca în 41: data în format AAAA-LL-ZZ, nu în viitor; ciclul 21–45 de zile, sângerarea 2–10.
 * Dacă prima zi e mai veche de 120 de zile (limita din 41), o mutăm înainte cu cicluri întregi:
 * faza de azi iese aceeași (calculul e modulo lungimea ciclului).
 */
function ah_app_ciclu_valideaza( $p, $azi ) {
	$p = is_array( $p ) ? $p : array();
	if ( true !== ( $p['acord_date_sanatate'] ?? null ) && 1 !== ( $p['acord_date_sanatate'] ?? null ) && '1' !== ( $p['acord_date_sanatate'] ?? null ) ) {
		return array( 'ok' => false, 'motiv' => 'acord' );
	}
	$s = (string) ( $p['prima_zi'] ?? '' );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $x ) || ! checkdate( (int) $x[2], (int) $x[3], (int) $x[1] ) ) {
		return array( 'ok' => false, 'motiv' => 'data' );
	}
	$c = $p['zile_ciclu'] ?? 28;
	$m = $p['zile_sangerare'] ?? 5;
	if ( ! is_numeric( $c ) || ! is_numeric( $m ) || (int) $c != $c || (int) $m != $m ) { // phpcs:ignore
		return array( 'ok' => false, 'motiv' => 'numere' );
	}
	$c = (int) $c;
	$m = (int) $m;
	if ( $c < 21 || $c > 45 || $m < 2 || $m > 10 ) {
		return array( 'ok' => false, 'motiv' => 'numere' );
	}
	$tz = new DateTimeZone( 'UTC' );
	$z  = DateTimeImmutable::createFromFormat( '!Y-m-d', $azi, $tz );
	$d  = DateTimeImmutable::createFromFormat( '!Y-m-d', $s, $tz );
	if ( ! $z || ! $d || $d > $z ) {
		return array( 'ok' => false, 'motiv' => 'viitor' );
	}
	$min = $z->modify( '-120 days' );
	if ( $d < $min ) {
		$zile = (int) round( ( $min->getTimestamp() - $d->getTimestamp() ) / 86400 );
		$d    = $d->modify( '+' . ( (int) ceil( $zile / $c ) * $c ) . ' days' );
	}
	$e = $p['emailuri_faze'] ?? false;
	return array(
		'ok'   => true,
		'date' => array(
			'prima_zi'       => $d->format( 'Y-m-d' ),
			'zile_sangerare' => $m,
			'zile_ciclu'     => $c,
			'emailuri_faze'  => true === $e || 1 === $e || '1' === $e,
		),
	);
}

/** Starturile (ultimele 6), cu aceeași regulă ca ah_ciclu_rest_actualizare: sub 15 zile = corectură. */
function ah_app_ciclu_starturi( $vechi, $start ) {
	$st    = is_array( $vechi ) ? array_values( $vechi ) : array();
	$ultim = end( $st );
	if ( $ultim && abs( strtotime( $start ) - strtotime( $ultim ) ) < 15 * DAY_IN_SECONDS ) {
		array_pop( $st );
	}
	$st[] = $start;
	$st   = array_values( array_unique( $st ) );
	sort( $st );
	return array_slice( $st, -6 );
}

function ah_app_ciclu_41() {
	return function_exists( 'ah_ciclu_tabel' );
}

function ah_app_ciclu_rand( $email ) {
	if ( ! ah_app_ciclu_41() ) {
		return null;
	}
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_ciclu_tabel() . ' WHERE email = %s', $email ), ARRAY_A );
}

/** Fără emailuri, datele se țin doar pentru sincronizare (41 nu are rând fără emailuri). */
function ah_app_cheie_ciclu( $email ) {
	return 'ah_app_ciclu_' . md5( $email );
}

function ah_app_ciclu_citeste( $email ) {
	$r = ah_app_ciclu_rand( $email );
	if ( $r ) {
		return array( 'prima_zi' => (string) $r['start'], 'zile_sangerare' => (int) $r['durata'], 'zile_ciclu' => (int) $r['ciclu'], 'emailuri_faze' => true );
	}
	$o = get_option( ah_app_cheie_ciclu( $email ) );
	if ( is_array( $o ) && ! empty( $o['prima_zi'] ) ) {
		return array( 'prima_zi' => (string) $o['prima_zi'], 'zile_sangerare' => (int) $o['zile_sangerare'], 'zile_ciclu' => (int) $o['zile_ciclu'], 'emailuri_faze' => false );
	}
	return null;
}

/** Scrie ciclul: cu emailuri ⇒ rândul din tabelul 41 (abonarea); fără ⇒ doar pentru sincronizare, iar rândul 41 se șterge. */
function ah_app_ciclu_scrie( $email, $d ) {
	global $wpdb;
	if ( $d['emailuri_faze'] && ah_app_ciclu_41() ) {
		$t      = ah_ciclu_tabel();
		$acum   = current_time( 'mysql' );
		$exista = ah_app_ciclu_rand( $email );
		$rand   = array(
			'email'      => $email,
			'start'      => $d['prima_zi'],
			'ciclu'      => $d['zile_ciclu'],
			'durata'     => $d['zile_sangerare'],
			'actualizat' => $acum,
		);
		$rand['ultima'] = function_exists( 'ah_ciclu_cheie_acum' ) ? ah_ciclu_cheie_acum( $rand ) : '';
		if ( $exista ) {
			$vechi            = json_decode( (string) $exista['starturi'], true );
			$rand['starturi'] = wp_json_encode( ah_app_ciclu_starturi( $vechi, $d['prima_zi'] ) );
			if ( (string) $exista['start'] === $d['prima_zi'] && (int) $exista['ciclu'] === $d['zile_ciclu'] && (int) $exista['durata'] === $d['zile_sangerare'] ) {
				unset( $rand['ultima'] ); // nimic nou: nu atingem emailul fazei de acum
			}
			$wpdb->update( $t, $rand, array( 'id' => (int) $exista['id'] ) );
		} else {
			$rand['prenume']  = mb_substr( ah_app_prenume( $email ), 0, 60 );
			$rand['starturi'] = wp_json_encode( array( $d['prima_zi'] ) );
			$rand['acord']    = $acum;
			$rand['token']    = wp_generate_password( 32, false, false );
			$wpdb->insert( $t, $rand );
			if ( function_exists( 'ah_ciclu_trimite' ) ) {
				ah_ciclu_trimite( $rand, 'bun_venit' ); // la fel ca abonarea din pagină
			}
		}
		delete_option( ah_app_cheie_ciclu( $email ) );
		return true;
	}
	if ( ah_app_ciclu_41() ) {
		$wpdb->delete( ah_ciclu_tabel(), array( 'email' => $email ) ); // fără emailuri: oprirea, ca linkul din email
	}
	update_option( ah_app_cheie_ciclu( $email ), array(
		'prima_zi'       => $d['prima_zi'],
		'zile_sangerare' => $d['zile_sangerare'],
		'zile_ciclu'     => $d['zile_ciclu'],
		'acord'          => gmdate( 'Y-m-d H:i:s' ),
		'actualizat'     => time(), // ca în snippetul 41: după 12 luni fără actualizare, se șterge singur
	), false );
	return false;
}

function ah_app_ciclu_sterge( $email ) {
	global $wpdb;
	if ( ah_app_ciclu_41() ) {
		$wpdb->delete( ah_ciclu_tabel(), array( 'email' => $email ) );
	}
	delete_option( ah_app_cheie_ciclu( $email ) );
}

/**
 * Aceeași păstrare ca rândurile din snippetul 41 (ah_ciclu_trimite_zilnic): datele de ciclu
 * ținute doar pentru sincronizare se șterg după 365 de zile fără nicio actualizare.
 * Întoarce câte s-au șters.
 */
function ah_app_ciclu_curata( $acum = null ) {
	global $wpdb;
	$prag = ( null === $acum ? time() : (int) $acum ) - 365 * DAY_IN_SECONDS;
	$nume = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ah_app_ciclu_' ) . '%' ) );
	$n    = 0;
	foreach ( (array) $nume as $k ) {
		if ( ! preg_match( '/^ah_app_ciclu_[0-9a-f]{32}$/', (string) $k ) ) {
			continue;
		}
		$o = get_option( $k );
		// Rândurile scrise înainte de câmpul „actualizat”: data acordului, care se rescria la fiecare salvare.
		$t = is_array( $o ) ? (int) ( $o['actualizat'] ?? strtotime( (string) ( $o['acord'] ?? '' ) . ' UTC' ) ) : 0;
		if ( $t < $prag ) {
			delete_option( $k );
			$n++;
		}
	}
	return $n;
}

/** Codurile vechi (expirate de o zi și fără încercări în ultimele 24 h) nu mai trebuie ținute. */
function ah_app_coduri_curata() {
	global $wpdb;
	$prag = time() - DAY_IN_SECONDS;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ah_app_tabel_coduri() . ' WHERE exp < %d AND zi_start < %d', $prag, $prag ) );
}

function ah_app_zilnic() {
	$n = ah_app_ciclu_curata();
	if ( $n ) {
		ah_app_jurnal( 'ciclu: ' . $n . ' înregistrări neactualizate de 12 luni, șterse' );
	}
	ah_app_coduri_curata();
}
add_action( 'ah_app_zilnic', 'ah_app_zilnic' );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ah_app_zilnic' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ah_app_zilnic' );
	}
} );

/* ==================================================================
   10. NOTIFICĂRILE PUSH (Firebase Cloud Messaging, HTTP v1)
   ================================================================== */

/** Contul de serviciu din setări (JSON), sau null. Fără el, nu se trimite nimic. */
function ah_app_fcm_cont( $json = null ) {
	$json = null === $json ? (string) get_option( 'ah_app_fcm', '' ) : (string) $json;
	if ( '' === trim( $json ) ) {
		return null;
	}
	$j = json_decode( $json, true );
	if ( ! is_array( $j ) || empty( $j['project_id'] ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
		return null;
	}
	return $j;
}

/** JWT-ul semnat RS256 (cheia contului de serviciu), pentru tokenul OAuth Google. */
function ah_app_fcm_jwt( $sa, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$cap  = ah_app_b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$corp = ah_app_b64( wp_json_encode( array(
		'iss'   => $sa['client_email'],
		'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
		'aud'   => ! empty( $sa['token_uri'] ) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token',
		'iat'   => $acum,
		'exp'   => $acum + 3600,
	) ) );
	$cheie = openssl_pkey_get_private( (string) $sa['private_key'] );
	if ( ! $cheie ) {
		return '';
	}
	$semn = '';
	if ( ! openssl_sign( $cap . '.' . $corp, $semn, $cheie, OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	return $cap . '.' . $corp . '.' . ah_app_b64( $semn );
}

/** Tokenul OAuth (ținut minte 50 de minute). */
function ah_app_fcm_acces( $sa ) {
	$k = 'ah_app_fcm_at_' . md5( $sa['client_email'] );
	$v = get_transient( $k );
	if ( is_string( $v ) && '' !== $v ) {
		return $v;
	}
	$jwt = ah_app_fcm_jwt( $sa );
	if ( '' === $jwt ) {
		ah_app_jurnal( 'push: cheia contului de serviciu nu se poate citi' );
		return '';
	}
	$r = wp_remote_post( ! empty( $sa['token_uri'] ) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token', array(
		'timeout' => 15,
		'body'    => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ),
	) );
	$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['access_token'] ) ) {
		ah_app_jurnal( 'push: Google nu a dat tokenul (' . ( is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r ) ) . ')' );
		return '';
	}
	set_transient( $k, (string) $j['access_token'], 50 * MINUTE_IN_SECONDS );
	return (string) $j['access_token'];
}

/** Mesajul FCM pentru un telefon. Valorile din „data” trebuie să fie texte. */
function ah_app_fcm_mesaj( $fcm, $titlu, $text, $date ) {
	return array(
		'message' => array(
			'token'        => (string) $fcm,
			'notification' => array( 'title' => (string) $titlu, 'body' => (string) $text ),
			'data'         => array_map( 'strval', (array) $date ),
			'android'      => array( 'priority' => 'high' ),
			'apns'         => array( 'payload' => array( 'aps' => array( 'sound' => 'default' ) ) ),
		),
	);
}

/**
 * Trimite o notificare la rândurile din tabelul push (un lot; loturile le face ah_app_push_lot).
 * Fără cheie în setări: doar jurnal. Tokenurile pe care Firebase nu le mai recunoaște se șterg.
 * $dupa( $r ) se cheamă după fiecare telefon (cursorul lotului).
 * Întoarce câte au plecat, sau null dacă Google nu a dat tokenul de acces (lotul se reia mai târziu).
 */
function ah_app_trimite_push( $randuri, $titlu, $text, $date = array(), $dupa = null ) {
	$randuri = array_values( (array) $randuri );
	if ( ! $randuri ) {
		return 0;
	}
	$sa = ah_app_fcm_cont();
	if ( ! $sa ) {
		ah_app_jurnal( 'push OPRIT (fără cheie), ar fi plecat la ' . count( $randuri ) . ' telefoane: ' . $titlu );
		if ( $dupa ) {
			call_user_func( $dupa, end( $randuri ) );
		}
		return 0;
	}
	$at = ah_app_fcm_acces( $sa );
	if ( '' === $at ) {
		return null;
	}
	global $wpdb;
	$url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send';
	$ok  = 0;
	foreach ( $randuri as $r ) {
		$res = wp_remote_post( $url, array(
			'timeout' => 5,
			'headers' => array( 'Authorization' => 'Bearer ' . $at, 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( ah_app_fcm_mesaj( $r['fcm'], $titlu, $text, $date ) ),
		) );
		$cod = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $cod ) {
			$ok++;
		} else {
			$corp = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
			if ( 404 === $cod || false !== strpos( $corp, 'UNREGISTERED' ) || ( 400 === $cod && false !== strpos( $corp, 'registration token' ) ) ) {
				$wpdb->delete( ah_app_tabel_push(), array( 'id' => (int) $r['id'] ) );
			}
		}
		if ( $dupa ) {
			call_user_func( $dupa, $r );
		}
	}
	ah_app_jurnal( 'push: ' . $titlu . ' — ' . $ok . ' din ' . count( $randuri ) );
	return $ok;
}

/** Următoarele rânduri push abonate la un subiect ('meditatii' sau 'cursuri'), după id-ul $dupa_id. */
function ah_app_push_abonati( $subiect, $dupa_id = 0, $cate = AH_APP_PUSH_LOT ) {
	global $wpdb;
	$col = 'cursuri' === $subiect ? 'cursuri' : 'meditatii';
	$r   = $wpdb->get_results( $wpdb->prepare( 'SELECT id, email, fcm FROM ' . ah_app_tabel_push() . " WHERE {$col} = %d AND id > %d ORDER BY id ASC LIMIT %d", 1, (int) $dupa_id, (int) $cate ), ARRAY_A );
	return is_array( $r ) ? $r : array();
}

/*
 * Trimiterea în loturi. O trimitere („lucrare”) stă într-o opțiune: titlul, textul, cui (meditația
 * sau cursul) și cursorul (ultimul id push trimis). Fiecare lot e un eveniment cron separat, cu
 * AH_APP_PUSH_LOT telefoane; cursorul se scrie după fiecare telefon. Dacă un lot cade la jumătate
 * (timeout, memorie), o reîncercare programată la începutul lotului îl reia de unde a rămas.
 */

/** Pornește o trimitere în loturi. Întoarce id-ul lucrării. */
function ah_app_push_porneste( $subiect, $cui, $titlu, $text, $date ) {
	$id = 'ah_app_pj_' . strtolower( wp_generate_password( 12, false, false ) );
	update_option( $id, array(
		'subiect'   => $subiect,
		'cui'       => $cui,
		'titlu'     => $titlu,
		'text'      => $text,
		'date'      => $date,
		'cursor'    => 0,
		'incercari' => 0,
		'creat'     => time(),
	), false );
	wp_schedule_single_event( time(), 'ah_app_push_lot', array( $id ) );
	return $id;
}

/** Are adresa acces la ce anunță lucrarea? (o dată pe adresă și lot) */
function ah_app_push_are_acces( $cui, $email ) {
	if ( 'meditatie' === ( $cui['tip'] ?? '' ) ) {
		return function_exists( 'ah_med_poate' ) && ah_med_poate( (int) $cui['id'], ah_app_acces( $email ) );
	}
	if ( 'curs' === ( $cui['tip'] ?? '' ) ) {
		$cu = ah_app_cursuri_email( $email );
		return isset( $cu[ (string) $cui['slug'] ] );
	}
	return false;
}

/** Un lot. Întoarce câte notificări au plecat (0 fără cheie). */
function ah_app_push_lot( $id ) {
	$id = (string) $id;
	$j  = get_option( $id );
	if ( 0 !== strpos( $id, 'ah_app_pj_' ) || ! is_array( $j ) ) {
		return 0;
	}
	// Reîncercarea, dacă lotul acesta nu ajunge la capăt. După 3 căderi la rând, lucrarea se oprește.
	if ( (int) $j['incercari'] >= 3 ) {
		ah_app_jurnal( 'push: oprit după 3 încercări, la id ' . (int) $j['cursor'] . ': ' . $j['titlu'] );
		delete_option( $id );
		return 0;
	}
	$j['incercari'] = (int) $j['incercari'] + 1;
	update_option( $id, $j, false );
	wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'ah_app_push_lot', array( $id ) );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore — cel mult 100 × 5 s; reîncercarea vine abia după 15 minute
	}

	$rand = ah_app_push_abonati( $j['subiect'], (int) $j['cursor'] );
	$cine = array();
	$acc  = array(); // aceeași adresă pe mai multe telefoane: accesul se calculează o dată
	foreach ( $rand as $r ) {
		$e = (string) $r['email'];
		if ( ! isset( $acc[ $e ] ) ) {
			$acc[ $e ] = ah_app_push_are_acces( $j['cui'], $e );
		}
		if ( $acc[ $e ] ) {
			$cine[] = $r;
		}
	}
	$ultim = $rand ? (int) end( $rand )['id'] : (int) $j['cursor'];
	$ok    = ah_app_trimite_push( $cine, $j['titlu'], $j['text'], $j['date'], function ( $r ) use ( $id, &$j ) {
		$j['cursor'] = (int) $r['id'];
		update_option( $id, $j, false );
	} );
	if ( null === $ok ) {
		return 0; // fără token de acces: rămâne reîncercarea programată mai sus, de la același cursor
	}

	wp_clear_scheduled_hook( 'ah_app_push_lot', array( $id ) ); // lotul a ajuns la capăt: fără reîncercare
	if ( count( $rand ) < AH_APP_PUSH_LOT ) {
		delete_option( $id ); // gata
	} else {
		$j['cursor']    = $ultim; // și telefoanele fără acces de la capătul lotului
		$j['incercari'] = 0;
		update_option( $id, $j, false );
		wp_schedule_single_event( time() + 30, 'ah_app_push_lot', array( $id ) );
	}
	if ( function_exists( 'wp_cache_flush_runtime' ) ) {
		wp_cache_flush_runtime(); // comenzile citite pentru acces nu mai trebuie ținute în memorie
	}
	return $ok;
}
add_action( 'ah_app_push_lot', 'ah_app_push_lot' );

/* Declanșatorul 1: o meditație nouă publicată (după 2 minute, ca să fie salvate și câmpurile ei). */
add_action( 'transition_post_status', function ( $nou, $vechi, $post ) {
	if ( 'publish' !== $nou || 'publish' === $vechi || ! $post || 'ah_meditatie' !== $post->post_type ) {
		return;
	}
	if ( ! wp_next_scheduled( 'ah_app_push_meditatie', array( (int) $post->ID ) ) ) {
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'ah_app_push_meditatie', array( (int) $post->ID ) );
	}
}, 10, 3 );

function ah_app_push_meditatie( $id ) {
	$id = (int) $id;
	if ( 'publish' !== get_post_status( $id ) || get_post_meta( $id, '_ah_app_push', true ) || ! function_exists( 'ah_med_poate' ) ) {
		return '';
	}
	$titlu = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	$job   = ah_app_push_porneste( 'meditatii', array( 'tip' => 'meditatie', 'id' => $id ), 'O meditație nouă te așteaptă', '„' . $titlu . '” e acum în aplicație.', array( 'tip' => 'meditatie', 'id' => $id ) );
	update_post_meta( $id, '_ah_app_push', time() ); // o singură dată pe meditație, după ce trimiterea e pusă la coadă
	return $job;
}
add_action( 'ah_app_push_meditatie', 'ah_app_push_meditatie' );

/* Declanșatorul 2: o parte nouă deschisă într-un curs (verificat din oră în oră). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ah_app_orar' ) ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', 'ah_app_orar' );
	}
} );

/** Grupurile vizibile acum, pe slug (fără lecțiile programate). */
function ah_app_grupuri_vizibile() {
	if ( ! function_exists( 'ah_sc_cursuri' ) ) {
		return array();
	}
	$o = array();
	foreach ( ah_sc_cursuri() as $curs ) {
		$s = (string) ( $curs['slug'] ?? '' );
		if ( '' === $s || isset( $o[ $s ] ) ) {
			continue;
		}
		$o[ $s ] = array( 'nume' => (string) ( $curs['nume'] ?? $s ), 'grupuri' => array_column( ah_app_grupuri( (array) ( $curs['module'] ?? array() ) ), 'id' ) );
	}
	return $o;
}

/** Ce grupuri sunt noi față de ce știam (pur, pentru teste). Un curs văzut prima dată nu anunță nimic. */
function ah_app_grupuri_noi( $vechi, $acum ) {
	$noi = array();
	foreach ( (array) $acum as $slug => $x ) {
		if ( ! isset( $vechi[ $slug ] ) ) {
			continue;
		}
		$d = array_values( array_diff( $x['grupuri'], (array) $vechi[ $slug ] ) );
		if ( $d ) {
			$noi[ $slug ] = $d;
		}
	}
	return $noi;
}

/** Ora: cursurile cu părți noi primesc câte o trimitere în loturi; apoi se notează ce s-a văzut. */
function ah_app_orar( $acum = null ) {
	$acum = null === $acum ? ah_app_grupuri_vizibile() : $acum;
	if ( ! $acum ) {
		return array();
	}
	$vechi = get_option( 'ah_app_grupuri_vazute' );
	$prima = ! is_array( $vechi );
	$noi   = $prima ? array() : ah_app_grupuri_noi( $vechi, $acum );
	$joburi = array();
	foreach ( $noi as $slug => $g ) {
		$joburi[] = ah_app_push_porneste( 'cursuri', array( 'tip' => 'curs', 'slug' => (string) $slug ), 'S-a deschis o parte nouă', 'În „' . $acum[ $slug ]['nume'] . '” te așteaptă lecții noi.', array( 'tip' => 'curs', 'slug' => $slug ) );
	}
	$st = array();
	foreach ( $acum as $s => $x ) {
		$st[ $s ] = array_values( array_unique( array_merge( (array) ( $vechi[ $s ] ?? array() ), $x['grupuri'] ) ) );
	}
	update_option( 'ah_app_grupuri_vazute', $st, false ); // după ce trimiterile sunt la coadă
	return $joburi;
}
add_action( 'ah_app_orar', function () {
	ah_app_orar();
} );

/* ==================================================================
   11. RUTELE API: /wp-json/ah-app/v1
   ================================================================== */

/** Învelișul fiecărei rute: fără cache, autentificare (dacă e cazul), erori prinse. */
function ah_app_ruta( $cb, $auth = true ) {
	return function ( WP_REST_Request $r ) use ( $cb, $auth ) {
		ah_app_fara_cache();
		try {
			$u = null;
			if ( $auth ) {
				$u = ah_app_autentifica( $r );
				if ( ! $u ) {
					return ah_app_eroare( 'neautentificat' );
				}
			}
			return call_user_func( $cb, $r, $u );
		} catch ( Throwable $e ) {
			error_log( 'ah_app: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			return ah_app_eroare( 'server' );
		}
	};
}

add_action( 'rest_api_init', function () {
	$rute = array(
		array( '/auth/cod', 'POST', 'ah_app_r_cod', false ),
		array( '/auth/verifica', 'POST', 'ah_app_r_verifica', false ),
		array( '/auth/iesire', 'POST', 'ah_app_r_iesire', true ),
		array( '/cont', 'DELETE', 'ah_app_r_sterge_cont', true ),
		array( '/config', 'GET', 'ah_app_r_config', false ),
		array( '/eu', 'GET', 'ah_app_r_eu', true ),
		array( '/meditatii', 'GET', 'ah_app_r_meditatii', true ),
		array( '/meditatii/(?P<id>\d+)/redare', 'GET', 'ah_app_r_redare', true ),
		array( '/meditatii/(?P<id>\d+)/pozitie', 'POST', 'ah_app_r_pozitie', true ),
		array( '/cursuri', 'GET', 'ah_app_r_cursuri', true ),
		array( '/cursuri/(?P<slug>[a-z0-9_-]+)', 'GET', 'ah_app_r_curs', true ),
		array( '/cursuri/(?P<slug>[a-z0-9_-]+)/progres', 'POST', 'ah_app_r_progres', true ),
		array( '/ciclu/faze', 'GET', 'ah_app_r_faze', false ),
		array( '/ciclu', 'GET', 'ah_app_r_ciclu', true ),
		array( '/ciclu', 'PUT', 'ah_app_r_ciclu_pune', true ),
		array( '/ciclu', 'DELETE', 'ah_app_r_ciclu_sterge', true ),
		array( '/push', 'POST', 'ah_app_r_push', true ),
		array( '/push', 'DELETE', 'ah_app_r_push_sterge', true ),
	);
	$grup = array();
	foreach ( $rute as $x ) {
		$grup[ $x[0] ][] = array(
			'methods'             => $x[1],
			'callback'            => ah_app_ruta( $x[2], $x[3] ),
			'permission_callback' => '__return_true', // autentificarea e în ah_app_ruta, ca erorile să aibă forma din contract
		);
	}
	foreach ( $grup as $cale => $def ) {
		register_rest_route( AH_APP_NS, $cale, $def );
	}
} );

/* ---- autentificare ---- */

function ah_app_r_cod( WP_REST_Request $r ) {
	$p     = ah_app_corp( $r );
	$email = ah_app_email( $p['email'] ?? '' );
	if ( ! $email ) {
		return ah_app_eroare( 'invalid', 'Adresa de email nu pare completă. Verifică-o, te rog.' );
	}
	if ( ah_app_cod_blocat( $email ) ) {
		return ah_app_eroare( 'prea_multe', AH_APP_MESAJ_BLOCAT_ZI ); // codul nu ar mai putea fi folosit azi
	}
	if ( ah_app_limita( 'ip_cod|' . ah_app_ip(), 20 ) || ah_app_limita( 'email_cod|' . $email, 5 ) ) {
		return ah_app_eroare( 'prea_multe' );
	}
	$cod = ah_app_cod_nou( $email );
	ah_app_email_cod( $email, $cod, ah_app_prenume( $email ) ); // pleacă și dacă nu are nimic cumpărat
	return array( 'ok' => true );
}

function ah_app_r_verifica( WP_REST_Request $r ) {
	$p     = ah_app_corp( $r );
	$email = ah_app_email( $p['email'] ?? '' );
	$d     = is_array( $p['dispozitiv'] ?? null ) ? $p['dispozitiv'] : array();
	$disp  = (string) ( $d['id'] ?? '' );
	$plat  = (string) ( $d['platforma'] ?? '' );
	if ( ! $email || ! preg_match( '/^[A-Za-z0-9-]{8,64}$/', $disp ) || ! in_array( $plat, array( 'ios', 'android' ), true ) ) {
		return ah_app_eroare( 'invalid' );
	}
	if ( ah_app_limita( 'ip_ver|' . ah_app_ip(), 40 ) ) {
		return ah_app_eroare( 'prea_multe' );
	}
	$rez = ah_app_cod_verifica( $email, (string) ( $p['cod'] ?? '' ) );
	if ( 'ok' !== $rez ) {
		return ah_app_eroare( $rez, 'prea_multe' === $rez ? AH_APP_MESAJ_BLOCAT_ZI : '' );
	}
	$token = ah_app_inregistreaza( $email, strtolower( $disp ), mb_substr( sanitize_text_field( (string) ( $d['nume'] ?? '' ) ), 0, 80 ), $plat );
	if ( '' === $token ) {
		return ah_app_eroare( 'dispozitive' );
	}
	return array( 'token' => $token, 'utilizatoare' => array( 'email' => $email, 'prenume' => ah_app_prenume( $email ) ) );
}

function ah_app_r_iesire( WP_REST_Request $r, $u ) {
	ah_app_revoca( $u['id'] );
	return array( 'ok' => true );
}

/** Șterge tot ce ține de aplicație pentru adresă. Comenzile și facturile rămân. */
function ah_app_r_sterge_cont( WP_REST_Request $r, $u ) {
	global $wpdb;
	$e = $u['email'];
	$wpdb->delete( ah_app_tabel_push(), array( 'email' => $e ) );
	$wpdb->delete( ah_app_tabel_tokenuri(), array( 'email' => $e ) );
	ah_app_ciclu_sterge( $e );
	delete_option( ah_app_cheie_poz( $e ) );
	$wpdb->delete( ah_app_tabel_coduri(), array( 'email' => $e ) );
	ah_app_jurnal( 'cont șters din aplicație: ' . substr( md5( $e ), 0, 8 ) );
	return array( 'ok' => true );
}

/* ---- configurație ---- */

function ah_app_r_config() {
	$m = get_option( 'ah_app_mesaj' );
	$m = ( is_array( $m ) && ( '' !== trim( (string) ( $m['titlu'] ?? '' ) ) || '' !== trim( (string) ( $m['text'] ?? '' ) ) ) )
		? array( 'titlu' => (string) $m['titlu'], 'text' => (string) $m['text'] ) : null;
	$v = (string) get_option( 'ah_app_versiune_minima', '1.0.0' );
	return array(
		'versiune_minima' => preg_match( '/^\d+\.\d+\.\d+$/', $v ) ? $v : '1.0.0',
		'contact'         => array( 'email' => AH_APP_CONTACT, 'whatsapp' => '+40742709306' ),
		'linkuri'         => array(
			'site'              => home_url( '/' ),
			'confidentialitate' => home_url( '/politica-de-confidentialitate/' ),
			'termeni'           => home_url( '/termeni-si-conditii/' ),
		),
		'mesaj'           => $m,
	);
}

/* ---- profil ---- */

function ah_app_r_eu( WP_REST_Request $r, $u ) {
	$e   = $u['email'];
	$acc = ah_app_acces( $e );
	$cu  = array();
	foreach ( ah_app_cursuri_email( $e ) as $x ) {
		$s    = ah_app_curs_scurt( $x );
		$cu[] = array( 'slug' => $s['slug'], 'nume' => $s['nume'], 'imagine' => $s['imagine'], 'pana_la' => $s['pana_la'], 'progres' => $s['progres'] );
	}
	$ci = ah_app_ciclu_citeste( $e );
	return array(
		'email'               => $e,
		'prenume'             => ah_app_prenume( $e, $acc ),
		'inner_temple'        => ah_app_inner_temple( $e, $acc ),
		'meditatii_cumparate' => count( (array) $acc['produse'] ),
		'cursuri'             => $cu,
		'ciclu'               => array( 'salvat_pe_site' => null !== $ci, 'emailuri_faze' => $ci ? $ci['emailuri_faze'] : false ),
	);
}

/* ---- meditații ---- */

function ah_app_r_meditatii( WP_REST_Request $r, $u ) {
	if ( ! function_exists( 'ah_med_lista' ) || ! function_exists( 'ah_med_m' ) ) {
		return array( 'categorii' => array(), 'meditatii' => array() );
	}
	$acc  = ah_app_acces( $u['email'] );
	$poz  = ah_app_pozitii( $u['email'] );
	$are  = ! empty( $acc['are'] );
	$cats = array();
	$lst  = array();
	foreach ( ah_med_lista() as $id ) {
		$m = ah_app_meditatie( $id, $acc, $u, $poz );
		if ( ! $m['poate_asculta'] && 'gratuita' !== $m['acces'] && ! $are ) {
			continue; // cine nu are nimic vede doar meditațiile gratuite
		}
		$lst[] = $m;
		$c     = ah_med_m( $id, 'categorie' );
		if ( '' !== $c && ! isset( $cats[ ah_app_slug( $c ) ] ) ) {
			$cats[ ah_app_slug( $c ) ] = array( 'slug' => ah_app_slug( $c ), 'nume' => $c );
		}
	}
	return array( 'categorii' => array_values( $cats ), 'meditatii' => $lst );
}

/** Meditația publicată, sau null. */
function ah_app_meditatie_post( $id ) {
	$p = get_post( (int) $id );
	return ( $p && 'ah_meditatie' === $p->post_type && 'publish' === $p->post_status ) ? $p : null;
}

function ah_app_r_redare( WP_REST_Request $r, $u ) {
	$id = (int) $r['id'];
	if ( ! function_exists( 'ah_med_poate' ) || ! ah_app_meditatie_post( $id ) ) {
		return ah_app_eroare( 'negasit' );
	}
	if ( ! ah_med_poate( $id, ah_app_acces( $u['email'] ) ) ) {
		return ah_app_eroare( 'fara_acces', 'Meditația aceasta e disponibilă în Inner Temple. Detalii pe adelaharu.ro.' );
	}
	if ( '' === ah_med_m( $id, 'audio' ) ) {
		return ah_app_eroare( 'negasit', 'Meditația aceasta nu are încă fișier audio.' );
	}
	$exp = ah_app_exp();
	return array( 'url' => ah_app_link_meditatie( $id, $u, $exp ), 'expira' => ah_app_iso( $exp ) );
}

function ah_app_r_pozitie( WP_REST_Request $r, $u ) {
	$id = (int) $r['id'];
	if ( ! ah_app_meditatie_post( $id ) ) {
		return ah_app_eroare( 'negasit' );
	}
	$p = ah_app_corp( $r );
	if ( ! isset( $p['secunde'] ) || ! is_numeric( $p['secunde'] ) ) {
		return ah_app_eroare( 'invalid' );
	}
	$s   = max( 0, min( 6 * 3600, (int) $p['secunde'] ) );
	$fin = ! empty( $p['terminat'] );
	$poz = ah_app_pozitii( $u['email'] );
	$x   = $poz[ $id ] ?? array( 's' => 0, 'f' => 0, 'u' => 0 );
	$minute = null;
	// Minutele de practică, ca în aplicația web: o meditație ascultată până la capăt adaugă durata ei.
	if ( $fin && ( empty( $x['f'] ) || (int) $x['u'] < time() - 20 * MINUTE_IN_SECONDS ) && function_exists( 'ah_med_adauga_minute' ) ) {
		$d = function_exists( 'ah_med_durata' ) ? (int) ah_med_durata( $id ) : 0;
		if ( $d >= 60 ) {
			$minute = ah_med_adauga_minute( $u['email'], (int) round( $d / 60 ) );
		}
	}
	$poz[ $id ] = array( 's' => $fin ? 0 : $s, 'f' => $fin ? 1 : 0, 'u' => time() );
	if ( count( $poz ) > 400 ) {
		uasort( $poz, function ( $a, $b ) { return (int) $b['u'] - (int) $a['u']; } );
		$poz = array_slice( $poz, 0, 400, true );
	}
	update_option( ah_app_cheie_poz( $u['email'] ), $poz, false );
	$o = array( 'ok' => true );
	if ( $minute ) {
		$o['minute'] = $minute;
	}
	return $o;
}

/* ---- cursuri ---- */

function ah_app_r_cursuri( WP_REST_Request $r, $u ) {
	$o = array();
	foreach ( ah_app_cursuri_email( $u['email'] ) as $x ) {
		$o[] = ah_app_curs_scurt( $x );
	}
	return $o;
}

function ah_app_r_curs( WP_REST_Request $r, $u ) {
	$cu = ah_app_cursuri_email( $u['email'] );
	$s  = sanitize_key( (string) $r['slug'] );
	if ( ! isset( $cu[ $s ] ) ) {
		return ah_app_eroare( 'negasit', 'Nu am găsit cursul acesta pe adresa ta.' );
	}
	return ah_app_curs_detaliu( $cu[ $s ], $u );
}

/** „Am terminat lecția”: aceeași scriere ca ah_sc_rest_progres din sală (merge pe orice dispozitiv). */
function ah_app_r_progres( WP_REST_Request $r, $u ) {
	$cu = ah_app_cursuri_email( $u['email'] );
	$s  = sanitize_key( (string) $r['slug'] );
	if ( ! isset( $cu[ $s ] ) ) {
		return ah_app_eroare( 'negasit', 'Nu am găsit cursul acesta pe adresa ta.' );
	}
	if ( ! function_exists( 'ah_sc_harta_lectii' ) || ! function_exists( 'ah_sc_din_cheie' ) || ! function_exists( 'ah_sc_bife_scrie' ) ) {
		return ah_app_eroare( 'server' );
	}
	$p     = ah_app_corp( $r );
	$cheie = (string) ( $p['cheie'] ?? '' );
	if ( '' === $cheie || ! array_key_exists( 'terminata', $p ) ) {
		return ah_app_eroare( 'invalid' );
	}
	$c    = $cu[ $s ]['comanda'];
	$curs = $cu[ $s ]['curs'];
	$lec  = array_values( (array) $curs['module'] );
	$i    = ah_sc_din_cheie( ah_sc_harta_lectii( $lec ), $cheie );
	if ( null === $i ) {
		return ah_app_eroare( 'negasit', 'Lecția aceasta nu mai e în curs.' );
	}
	$gata = ah_app_bife( $c, $curs );
	$da   = ! empty( $p['terminata'] );
	if ( $da ) {
		$liber = ah_app_lectii_libere( count( $lec ), $gata, ah_app_pe_rand( $c, $curs ) );
		if ( empty( $liber[ $i ] ) ) {
			return ah_app_eroare( 'fara_acces', 'Lecția aceasta se deschide după cea dinainte.' );
		}
		$gata[] = $i;
	} else {
		$gata = array_diff( $gata, array( $i ) );
	}
	$gata = ah_sc_bife_scrie( $c, $curs, $gata );
	// Cursul terminat: aceeași notă în comandă ca în sală (și cuponul-cadou al sălii, pe care aplicația NU îl arată).
	if ( empty( $curs['mai_vin'] ) && count( $gata ) >= count( $lec ) && ! $c->get_meta( '_ah_sc_terminat' ) ) {
		$c->update_meta_data( '_ah_sc_terminat', current_time( 'mysql' ) );
		$c->add_order_note( 'Sala de curs: a terminat cursul ' . $curs['nume'] . ' (din aplicație).' );
		$c->save();
		if ( function_exists( 'ah_sc_cadou' ) ) {
			ah_sc_cadou( $c, $curs );
		}
	}
	$x = $cu[ $s ];
	$k = ah_app_curs_scurt( $x );
	return array( 'ok' => true, 'progres' => $k['progres'], 'module_terminate' => $k['module_terminate'], 'terminate' => array_values( array_map( function ( $j ) use ( $lec ) { return ah_app_cheie_lectie( $lec, $j ); }, $gata ) ) );
}

/* ---- ciclu ---- */

function ah_app_r_faze() {
	$res = new WP_REST_Response( ah_app_faze_date(), 200 );
	$res->header( 'Cache-Control', 'public, max-age=86400' );
	return $res;
}

function ah_app_r_ciclu( WP_REST_Request $r, $u ) {
	return new WP_REST_Response( ah_app_ciclu_citeste( $u['email'] ), 200 );
}

function ah_app_r_ciclu_pune( WP_REST_Request $r, $u ) {
	$v = ah_app_ciclu_valideaza( ah_app_corp( $r ), ah_app_azi_ymd() );
	if ( ! $v['ok'] ) {
		$m = array(
			'acord'  => 'Ca să păstrăm datele ciclului pe site, avem nevoie de acordul tău pentru date de sănătate.',
			'data'   => 'Data primei zile nu pare corectă. Verific-o, te rog.',
			'viitor' => 'Prima zi a menstruației nu poate fi în viitor.',
			'numere' => 'Ciclul poate avea între 21 și 45 de zile, iar sângerarea între 2 și 10 zile.',
		);
		return ah_app_eroare( 'invalid', $m[ $v['motiv'] ] ?? '' );
	}
	if ( ah_app_limita( 'ciclu|' . $u['email'], 30 ) ) {
		return ah_app_eroare( 'prea_multe' );
	}
	ah_app_ciclu_scrie( $u['email'], $v['date'] );
	return new WP_REST_Response( ah_app_ciclu_citeste( $u['email'] ), 200 );
}

/** Ziua de azi (AAAA-LL-ZZ), în fusul site-ului, ca ah_ciclu_azi(). */
function ah_app_azi_ymd() {
	if ( function_exists( 'ah_ciclu_azi' ) ) {
		return ah_ciclu_azi()->format( 'Y-m-d' );
	}
	return ( new DateTimeImmutable( 'today', function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Bucharest' ) ) )->format( 'Y-m-d' );
}

function ah_app_r_ciclu_sterge( WP_REST_Request $r, $u ) {
	ah_app_ciclu_sterge( $u['email'] );
	return array( 'ok' => true );
}

/* ---- push ---- */

function ah_app_r_push( WP_REST_Request $r, $u ) {
	$p    = ah_app_corp( $r );
	$fcm  = (string) ( $p['token'] ?? '' );
	$plat = (string) ( $p['platforma'] ?? '' );
	if ( ! preg_match( '/^[A-Za-z0-9_:.\-]{20,255}$/', $fcm ) || ! in_array( $plat, array( 'ios', 'android' ), true ) ) {
		return ah_app_eroare( 'invalid' );
	}
	$sub = is_array( $p['subiecte'] ?? null ) ? $p['subiecte'] : array();
	global $wpdb;
	$t    = ah_app_tabel_push();
	$rand = array(
		'token_id'   => (int) $u['id'],
		'email'      => $u['email'],
		'fcm'        => $fcm,
		'fcm_h'      => hash( 'sha256', $fcm ),
		'platforma'  => $plat,
		'meditatii'  => array_key_exists( 'meditatii_noi', $sub ) ? ( $sub['meditatii_noi'] ? 1 : 0 ) : 1,
		'cursuri'    => array_key_exists( 'cursuri', $sub ) ? ( $sub['cursuri'] ? 1 : 0 ) : 1,
		'actualizat' => gmdate( 'Y-m-d H:i:s' ),
	);
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE fcm_h = %s", hash( 'sha256', $fcm ) ) );
	if ( $id ) {
		$wpdb->update( $t, $rand, array( 'id' => (int) $id ) ); // același telefon, poate altă adresă acum
	} else {
		$wpdb->insert( $t, $rand );
	}
	return array( 'ok' => true );
}

function ah_app_r_push_sterge( WP_REST_Request $r, $u ) {
	$p = ah_app_corp( $r );
	global $wpdb;
	$wpdb->delete( ah_app_tabel_push(), array( 'fcm_h' => hash( 'sha256', (string) ( $p['token'] ?? '' ) ), 'email' => $u['email'] ) );
	return array( 'ok' => true );
}

/* ---- CORS și fără cache, doar pentru rutele noastre ---- */

function ah_app_e_ruta_noastra( $req ) {
	return $req instanceof WP_REST_Request && 0 === strpos( (string) $req->get_route(), '/' . AH_APP_NS );
}

/** Originile aplicației (Capacitor). */
function ah_app_origine_permisa( $o ) {
	return in_array( (string) $o, array( 'capacitor://localhost', 'https://localhost', 'http://localhost' ), true );
}

add_filter( 'rest_post_dispatch', function ( $res, $server, $req ) {
	if ( ! ah_app_e_ruta_noastra( $req ) || ! ( $res instanceof WP_HTTP_Response ) ) {
		return $res;
	}
	$h = $res->get_headers();
	if ( empty( $h['Cache-Control'] ) ) {
		$res->header( 'Cache-Control', 'no-store, private' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	}
	$res->header( 'X-Robots-Tag', 'noindex, nofollow' );
	return $res;
}, 20, 3 );

add_filter( 'rest_pre_serve_request', function ( $servit, $rez, $req ) {
	if ( ! ah_app_e_ruta_noastra( $req ) || headers_sent() ) {
		return $servit;
	}
	$o = (string) $req->get_header( 'origin' );
	if ( ah_app_origine_permisa( $o ) ) {
		header( 'Access-Control-Allow-Origin: ' . $o );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
		header( 'Access-Control-Max-Age: 600' );
		header_remove( 'Access-Control-Allow-Credentials' ); // tokenul e în header, nu în cookie
	} elseif ( '' !== $o ) {
		header_remove( 'Access-Control-Allow-Origin' );
		header_remove( 'Access-Control-Allow-Credentials' );
	}
	header( 'Vary: Origin', false );
	return $servit;
}, 20, 3 );

/* ==================================================================
   12. FIȘIERELE (?ah_app_media=…), PLAYERUL, /app/intra, .well-known
   ================================================================== */

/** Redă fișierul cu „Range”: streamingul din 51 sau din 44; copia de mai jos doar dacă lipsesc amândouă. */
function ah_app_streameaza( $cale, $mime ) {
	if ( function_exists( 'ah_med_streameaza' ) ) {
		ah_med_streameaza( $cale, $mime );
	}
	if ( function_exists( 'ah_sc_streameaza' ) ) {
		ah_sc_streameaza( $cale, $mime );
	}
	$size = filesize( $cale );
	list( $cod, $start, $end ) = ah_app_interval( $_SERVER['HTTP_RANGE'] ?? '', $size );
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $mime );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Disposition: inline' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, no-store' );
	status_header( $cod );
	if ( 416 === $cod ) {
		header( 'Content-Range: bytes */' . $size );
		exit;
	}
	if ( 206 === $cod ) {
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
	}
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		exit;
	}
	$f = fopen( $cale, 'rb' );
	fseek( $f, $start );
	$rest = $end - $start + 1;
	while ( $rest > 0 && ! feof( $f ) && ! connection_aborted() ) {
		$buf = fread( $f, (int) min( 65536, $rest ) );
		if ( false === $buf || '' === $buf ) {
			break;
		}
		echo $buf; // phpcs:ignore
		flush();
		$rest -= strlen( $buf );
	}
	fclose( $f );
	exit;
}

/** „Range: bytes=…” ⇒ array( status, start, end ), aceeași regulă ca ah_med_streameaza. */
function ah_app_interval( $range, $size ) {
	$start = 0;
	$end   = $size - 1;
	if ( '' !== (string) $range && preg_match( '/bytes=(\d*)-(\d*)/', (string) $range, $m ) ) {
		if ( '' === $m[1] && '' !== $m[2] ) {
			$start = max( 0, $size - (int) $m[2] );
		} elseif ( '' !== $m[1] ) {
			$start = (int) $m[1];
			if ( '' !== $m[2] ) {
				$end = min( (int) $m[2], $size - 1 );
			}
		}
		if ( $start > $end || $start >= $size ) {
			return array( 416, 0, 0 );
		}
		return array( 206, $start, $end );
	}
	return array( 200, $start, $end );
}

/** Calea unui fișier din Media (uploads), dacă e acolo; altfel ''. */
function ah_app_cale_uploads( $url ) {
	$up = wp_upload_dir( null, false );
	$u  = preg_replace( '#^https?:#', '', (string) $url );
	$b  = preg_replace( '#^https?:#', '', $up['baseurl'] );
	if ( 0 !== strpos( $u, $b ) ) {
		return '';
	}
	$c = realpath( $up['basedir'] . '/' . ltrim( rawurldecode( (string) parse_url( substr( $u, strlen( $b ) ), PHP_URL_PATH ) ), '/' ) );
	$r = realpath( $up['basedir'] );
	return ( $c && $r && 0 === strpos( $c, $r ) && is_file( $c ) ) ? $c : '';
}

/** Tokenul din link (după id) și emailul lui; null dacă a fost scos (ieșire, ștergere). */
function ah_app_u_din_link( $tid ) {
	$t = ah_app_token_dupa_id( (int) $tid );
	return $t ? array( 'id' => (int) $t['id'], 'email' => (string) $t['email'] ) : null;
}

function ah_app_mime( $cale, $k ) {
	if ( preg_match( '~\.m4a$~i', $cale ) ) {
		return 'audio/mp4';
	}
	$m = wp_check_filetype( $cale );
	if ( ! empty( $m['type'] ) ) {
		return $m['type'];
	}
	return array( 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'pdf' => 'application/pdf' )[ $k ] ?? 'application/octet-stream';
}

add_action( 'wp_loaded', function () {
	if ( empty( $_GET['ah_app_media'] ) ) {
		return;
	}
	ah_app_fara_cache();
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	$nu = function ( $cod ) {
		status_header( $cod );
		exit;
	};
	$ce  = (string) wp_unslash( $_GET['ah_app_media'] );
	$tid = absint( $_GET['t'] ?? 0 );
	$exp = (int) ( $_GET['exp'] ?? 0 );
	$sig = strtolower( (string) wp_unslash( $_GET['sig'] ?? '' ) );
	$u   = ah_app_u_din_link( $tid );
	if ( ! $u ) {
		$nu( 403 );
	}

	if ( preg_match( '/^m(\d+)$/', $ce, $x ) ) { // meditația
		$id = (int) $x[1];
		if ( ! ah_app_semn_bun( array( 'm', $id, $u['id'], $u['email'], $exp ), $exp, $sig ) ) {
			$nu( 403 );
		}
		if ( ! ah_app_meditatie_post( $id ) || ! function_exists( 'ah_med_poate' ) ) {
			$nu( 404 );
		}
		if ( ! ah_med_poate( $id, ah_app_acces( $u['email'] ) ) ) {
			$nu( 403 );
		}
		$src  = ah_med_m( $id, 'audio' );
		$cale = function_exists( 'ah_med_cale' ) ? ah_med_cale( $src, $id ) : '';
		if ( $cale ) {
			ah_app_streameaza( $cale, ah_app_mime( $cale, 'mp3' ) );
		}
		if ( $src && ! ctype_digit( $src ) ) {
			wp_redirect( $src ); // link extern (nu poate fi protejat), ca în 51
			exit;
		}
		$nu( 404 );
	}

	if ( preg_match( '/^l([a-f0-9]{16})$/', $ce, $x ) ) { // fișier dintr-o lecție
		$fid  = $x[1];
		$cid  = absint( $_GET['c'] ?? 0 );
		$slug = sanitize_key( wp_unslash( $_GET['s'] ?? '' ) );
		$k    = in_array( $_GET['k'] ?? '', array( 'mp3', 'mp4', 'pdf' ), true ) ? (string) $_GET['k'] : '';
		if ( ! $k || ! ah_app_semn_bun( array( 'l', $cid, $slug, $k, $fid, $u['id'], $u['email'], $exp ), $exp, $sig ) ) {
			$nu( 403 );
		}
		$cu = ah_app_cursuri_email( $u['email'] );
		if ( ! isset( $cu[ $slug ] ) || (int) $cu[ $slug ]['comanda']->get_id() !== $cid ) {
			$nu( 403 );
		}
		$url = '';
		$lec = null;
		foreach ( (array) $cu[ $slug ]['curs']['module'] as $mm ) { // doar lecțiile vizibile acum
			foreach ( 'pdf' === $k ? array_merge( array( (string) ( $mm['pdf'] ?? '' ) ), array_map( function ( $z ) { return is_array( $z ) ? (string) ( $z[1] ?? '' ) : ''; }, (array) ( $mm['pdfuri'] ?? array() ) ) ) : array( (string) ( $mm[ $k ] ?? '' ) ) as $cand ) {
				if ( '' !== $cand && ah_app_media_id( $cand ) === $fid ) {
					$url = $cand;
					$lec = $mm;
					break 2;
				}
			}
		}
		if ( '' === $url ) {
			$nu( 404 );
		}
		$cale = ( 'pdf' !== $k && function_exists( 'ah_sc_cale_media' ) ) ? ah_sc_cale_media( $url ) : ah_app_cale_uploads( $url );
		if ( ! $cale ) {
			$nu( 404 );
		}
		if ( 'pdf' !== $k && ( empty( $_SERVER['HTTP_RANGE'] ) || preg_match( '/bytes=0-/', (string) $_SERVER['HTTP_RANGE'] ) ) ) {
			ah_app_ascultare( $cu[ $slug ]['comanda'], $slug, $lec );
		}
		ah_app_streameaza( $cale, ah_app_mime( $cale, $k ) );
	}
	$nu( 404 );
}, 6 );

/** Pagina simplă (player, intra), în culorile site-ului. */
function ah_app_pagina( $titlu, $corp, $stil = '', $status = 200 ) {
	ah_app_fara_cache();
	if ( ! headers_sent() ) {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
	echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
		. '<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="strict-origin-when-cross-origin"><title>' . esc_html( $titlu ) . '</title>'
		. '<style>html,body{margin:0;padding:0}body{background:#FBF6F0;color:#3E2F28;font-family:Georgia,serif;font-size:17px;line-height:1.6}'
		. '.ahapp{max-width:520px;margin:0 auto;padding:48px 20px;text-align:center}.ahapp h1{font-weight:400;font-size:30px;margin:0 0 14px}'
		. '.ahapp p{color:#6F5B4F;margin:0 0 14px}.ahapp .cod{font-family:Helvetica,Arial,sans-serif;font-size:38px;letter-spacing:10px;font-weight:bold;color:#3E2F28;margin:18px 0 24px}'
		. '.ahapp a.b{display:inline-block;margin:8px 6px;padding:14px 26px;border-radius:50px;background:#96593F;color:#fff;font-family:Helvetica,Arial,sans-serif;font-size:13px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;text-decoration:none}'
		. $stil . '</style></head><body>' . $corp . '</body></html>';
	exit;
}

/** /app/player: videoul Vimeo într-o pagină de pe adelaharu.ro (referrerul e site-ul, deci restricția de domeniu trece). */
function ah_app_player() {
	$tid = absint( $_GET['t'] ?? 0 );
	$exp = (int) ( $_GET['exp'] ?? 0 );
	$sig = strtolower( (string) wp_unslash( $_GET['sig'] ?? '' ) );
	$u   = ah_app_u_din_link( $tid );
	$src = '';
	$titlu = 'Video';
	if ( $u && isset( $_GET['m'] ) ) {
		$id = absint( $_GET['m'] );
		if ( ah_app_semn_bun( array( 'pm', $id, $u['id'], $u['email'], $exp ), $exp, $sig ) && ah_app_meditatie_post( $id )
			&& function_exists( 'ah_med_poate' ) && ah_med_poate( $id, ah_app_acces( $u['email'] ) ) && function_exists( 'ah_med_vimeo' ) ) {
			$src   = ah_med_vimeo( ah_med_m( $id, 'video' ) );
			$titlu = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
		}
	} elseif ( $u && isset( $_GET['c'], $_GET['s'], $_GET['l'] ) ) {
		$cid   = absint( $_GET['c'] );
		$slug  = sanitize_key( wp_unslash( $_GET['s'] ) );
		$cheie = rawurldecode( (string) wp_unslash( $_GET['l'] ) );
		if ( ah_app_semn_bun( array( 'pl', $cid, $slug, $cheie, $u['id'], $u['email'], $exp ), $exp, $sig ) && function_exists( 'ah_sc_harta_lectii' ) && function_exists( 'ah_sc_vimeo' ) ) {
			$cu = ah_app_cursuri_email( $u['email'] );
			if ( isset( $cu[ $slug ] ) && (int) $cu[ $slug ]['comanda']->get_id() === $cid ) {
				$lec = array_values( (array) $cu[ $slug ]['curs']['module'] );
				$i   = ah_sc_din_cheie( ah_sc_harta_lectii( $lec ), $cheie );
				$v   = null !== $i && ! empty( $lec[ $i ]['vimeo'] ) ? ah_sc_vimeo( (string) $lec[ $i ]['vimeo'] ) : null;
				if ( $v ) {
					$src   = 'https://player.vimeo.com/video/' . $v[0] . '?' . ( $v[1] ? 'h=' . $v[1] . '&' : '' ) . 'title=0&byline=0&portrait=0&dnt=1&playsinline=1'
						. ( preg_match( '~#t=(\d+)s?~', (string) $lec[ $i ]['vimeo'], $t ) ? '#t=' . $t[1] . 's' : '' );
					$titlu = (string) $lec[ $i ]['titlu'];
					ah_app_ascultare( $cu[ $slug ]['comanda'], $slug, $lec[ $i ] );
				}
			}
		}
	}
	if ( '' === $src ) {
		ah_app_pagina( 'Linkul a expirat', '<div class="ahapp"><h1>Linkul a expirat</h1><p>Întoarce-te în aplicație și deschide din nou videoul.</p></div>', '', 403 );
	}
	ah_app_pagina( $titlu,
		'<iframe id="v" src="' . esc_url( $src ) . '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen title="' . esc_attr( $titlu ) . '"></iframe>'
		. '<script src="https://player.vimeo.com/api/player.js"></script>'
		. '<script>(function(){try{var p=new Vimeo.Player(document.getElementById("v"));["play","pause","ended"].forEach(function(e){p.on(e,function(d){try{var m={ahApp:"vimeo",ev:e,sec:d&&d.seconds||0};if(window.parent!==window){window.parent.postMessage(m,"*");}if(window.webkit&&window.webkit.messageHandlers&&window.webkit.messageHandlers.ahApp){window.webkit.messageHandlers.ahApp.postMessage(m);}}catch(x){}});});}catch(x){}})();</script>',
		'html,body{height:100%;background:#000;overflow:hidden}iframe{position:fixed;inset:0;width:100%;height:100%;border:0}'
	);
}

/** /app/intra: din email. Cu aplicația instalată, telefonul deschide aplicația direct; altfel, pagina aceasta. */
function ah_app_intra() {
	$cod = preg_replace( '/\D/', '', (string) wp_unslash( $_GET['c'] ?? '' ) );
	$cod = 6 === strlen( $cod ) ? $cod : '';
	$ios = (string) get_option( 'ah_app_link_ios', '' );
	$and = (string) get_option( 'ah_app_link_android', '' );
	$mag = '';
	if ( $ios ) {
		$mag .= '<a class="b" href="' . esc_url( $ios ) . '">App Store</a>';
	}
	if ( $and ) {
		$mag .= '<a class="b" href="' . esc_url( $and ) . '">Google Play</a>';
	}
	ah_app_pagina( 'Intră în aplicația Adela Haru',
		'<div class="ahapp"><h1>Aplicația Adela Haru</h1>'
		. ( $cod ? '<p>Codul tău de intrare este:</p><p class="cod">' . esc_html( $cod ) . '</p><p>Deschide aplicația Adela Haru pe telefon și scrie codul acolo. Codul e valabil ' . AH_APP_COD_MIN . ' minute.</p>'
			: '<p>Deschide aplicația Adela Haru pe telefon și cere un cod nou cu adresa ta de email.</p>' )
		. ( $mag ? '<p style="margin-top:28px">Nu ai încă aplicația? O găsești aici:</p><p>' . $mag . '</p>' : '<p style="margin-top:28px">Aplicația va fi în curând în App Store și Google Play.</p>' )
		. '<p style="margin-top:28px;font-size:15px">Ai nevoie de ajutor? Scrie-ne la <a href="mailto:' . AH_APP_CONTACT . '" style="color:#96593F">' . AH_APP_CONTACT . '</a>.</p></div>' );
}

/**
 * apple-app-site-association (JSON, fără extensie).
 * /app/player nu e link universal: aplicația îl deschide în browser (Vimeo), iar iOS nu trebuie să-l
 * trimită înapoi în aplicație. Excluderea stă înaintea lui /app/* (regulile se citesc în ordine).
 */
function ah_app_aasa() {
	$team = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) get_option( 'ah_app_ios_team', '' ) ) );
	$cai  = array( '/app/*', '/meditatiile-mele/*', '/sala-de-curs/*', '/cursurile-mele/*' );
	$id   = $team ? $team . '.' . AH_APP_PACHET : '';
	$fara = '/app/player*';
	return array(
		'applinks' => array(
			'apps'    => array(),
			'details' => $id ? array( array(
				'appID'      => $id,
				'appIDs'     => array( $id ),
				'paths'      => array_merge( array( 'NOT ' . $fara ), $cai ),
				'components' => array_merge(
					array( array( '/' => $fara, 'exclude' => true ) ),
					array_map( function ( $c ) { return array( '/' => $c ); }, $cai )
				),
			) ) : array(),
		),
	);
}

/** assetlinks.json (Android). Amprentele: una sau mai multe (cheia de încărcare și cea de la Google Play). */
function ah_app_assetlinks() {
	$amp = array();
	foreach ( preg_split( '/[\s,;]+/', strtoupper( (string) get_option( 'ah_app_android_sha256', '' ) ) ) as $a ) {
		if ( preg_match( '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $a ) ) {
			$amp[] = $a;
		}
	}
	return $amp ? array( array(
		'relation' => array( 'delegate_permission/common.handle_all_urls' ),
		'target'   => array( 'namespace' => 'android_app', 'package_name' => AH_APP_PACHET, 'sha256_cert_fingerprints' => $amp ),
	) ) : array();
}

function ah_app_json_static( $date ) {
	status_header( 200 );
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: public, max-age=3600' );
	echo wp_json_encode( $date, JSON_UNESCAPED_SLASHES );
	exit;
}

/* Paginile noastre, înainte ca WordPress să caute o pagină cu adresa asta (altfel ar da 404). */
add_action( 'parse_request', function () {
	$c = ah_app_cale_relativa( $_SERVER['REQUEST_URI'] ?? '', (string) parse_url( home_url( '/' ), PHP_URL_PATH ) );
	if ( '.well-known/apple-app-site-association' === $c ) {
		ah_app_json_static( ah_app_aasa() );
	}
	if ( '.well-known/assetlinks.json' === $c ) {
		ah_app_json_static( ah_app_assetlinks() );
	}
	if ( 'app/player' === $c ) {
		ah_app_player();
	}
	if ( 'app/intra' === $c || 0 === strpos( $c, 'app/' ) ) {
		ah_app_intra(); // orice alt /app/… (link universal fără aplicație instalată): aceeași pagină
	}
}, 1 );

/* ==================================================================
   13. ADMIN: Meditațiile mele › Aplicația mobilă
   ================================================================== */

add_action( 'admin_menu', function () {
	$parinte = post_type_exists( 'ah_meditatie' ) ? 'edit.php?post_type=ah_meditatie' : 'woocommerce';
	add_submenu_page( $parinte, 'Aplicația mobilă', 'Aplicația mobilă', 'manage_options', 'ah-app-setari', 'ah_app_pagina_setari' );
}, 20 );

function ah_app_pagina_setari() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mesaje = array();
	if ( isset( $_POST['ah_app_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ah_app_nonce'] ) ), 'ah_app_setari' ) ) {
		update_option( 'ah_app_ios_team', strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['ios_team'] ?? '' ) ) ), false );
		update_option( 'ah_app_android_sha256', sanitize_textarea_field( wp_unslash( $_POST['android_sha256'] ?? '' ) ), false );
		update_option( 'ah_app_link_ios', esc_url_raw( wp_unslash( $_POST['link_ios'] ?? '' ) ), false );
		update_option( 'ah_app_link_android', esc_url_raw( wp_unslash( $_POST['link_android'] ?? '' ) ), false );
		$v = sanitize_text_field( wp_unslash( $_POST['versiune'] ?? '1.0.0' ) );
		update_option( 'ah_app_versiune_minima', preg_match( '/^\d+\.\d+\.\d+$/', $v ) ? $v : '1.0.0', false );
		update_option( 'ah_app_mesaj', array(
			'titlu' => sanitize_text_field( wp_unslash( $_POST['mesaj_titlu'] ?? '' ) ),
			'text'  => sanitize_textarea_field( wp_unslash( $_POST['mesaj_text'] ?? '' ) ),
		), false );
		$fcm = trim( (string) wp_unslash( $_POST['fcm'] ?? '' ) );
		if ( ! empty( $_POST['fcm_sterge'] ) ) {
			delete_option( 'ah_app_fcm' );
			$mesaje[] = 'Am șters cheia Firebase. Notificările sunt oprite.';
		} elseif ( '' !== $fcm ) {
			if ( ah_app_fcm_cont( $fcm ) && openssl_pkey_get_private( (string) ah_app_fcm_cont( $fcm )['private_key'] ) ) {
				update_option( 'ah_app_fcm', $fcm, false );
				$mesaje[] = 'Am salvat cheia Firebase. Notificările pornesc.';
			} else {
				$mesaje[] = 'Cheia Firebase nu pare bună (trebuie tot fișierul JSON al contului de serviciu). N-am salvat-o.';
			}
		}
		$mesaje[] = 'Am salvat setările.';
	}
	$caut = strtolower( sanitize_email( wp_unslash( $_REQUEST['email'] ?? '' ) ) );
	if ( $caut && isset( $_POST['ah_app_goleste'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ah_app_goleste'] ) ), 'ah_app_goleste' ) ) {
		global $wpdb;
		$wpdb->delete( ah_app_tabel_push(), array( 'email' => $caut ) );
		$wpdb->delete( ah_app_tabel_tokenuri(), array( 'email' => $caut ) );
		delete_transient( 'ah_app_lim_' . md5( $caut ) );
		$mesaje[] = 'Am golit telefoanele aplicației pentru ' . $caut . '. Poate intra din nou cu un cod nou.';
	}
	if ( $caut && isset( $_POST['ah_app_test'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ah_app_test'] ) ), 'ah_app_test' ) ) {
		global $wpdb;
		$r = $wpdb->get_results( $wpdb->prepare( 'SELECT id, email, fcm FROM ' . ah_app_tabel_push() . ' WHERE email = %s', $caut ), ARRAY_A );
		$n = ah_app_trimite_push( $r, 'Bună!', 'Aceasta e o notificare de probă din aplicația Adela Haru.', array( 'tip' => 'test' ) );
		$mesaje[] = 'Notificare de probă: trimisă la ' . (int) $n . ' din ' . count( (array) $r ) . ' telefoane (vezi jurnalul).';
	}
	foreach ( $mesaje as $m ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $m ) . '</p></div>';
	}
	$sa  = ah_app_fcm_cont();
	$msj = get_option( 'ah_app_mesaj' );
	$msj = is_array( $msj ) ? $msj : array( 'titlu' => '', 'text' => '' );
	$url = admin_url( ( post_type_exists( 'ah_meditatie' ) ? 'edit.php?post_type=ah_meditatie&' : 'admin.php?' ) . 'page=ah-app-setari' );

	echo '<div class="wrap"><h1>Aplicația mobilă Adela Haru</h1>';
	echo '<p>API-ul: <code>' . esc_html( rest_url( AH_APP_NS ) ) . '</code>. Aplicația nu vinde nimic: nu arată prețuri și nici linkuri spre cumpărare.</p>';

	echo '<h2>Caută o clientă</h2><form method="get" action="' . esc_url( admin_url( post_type_exists( 'ah_meditatie' ) ? 'edit.php' : 'admin.php' ) ) . '">';
	if ( post_type_exists( 'ah_meditatie' ) ) {
		echo '<input type="hidden" name="post_type" value="ah_meditatie">';
	}
	echo '<input type="hidden" name="page" value="ah-app-setari"><input type="email" name="email" value="' . esc_attr( $caut ) . '" placeholder="adresa de email" style="width:280px"> <button class="button">Caută</button></form>';
	if ( is_email( $caut ) ) {
		$d = ah_app_dispozitive( $caut );
		global $wpdb;
		$np = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ah_app_tabel_push() . ' WHERE email = %s', $caut ) );
		echo '<table class="widefat" style="max-width:720px;margin-top:12px"><tbody><tr><th style="width:200px">Telefoane în aplicație</th><td>' . count( $d ) . ' din ' . AH_APP_MAX_DISP;
		foreach ( $d as $x ) {
			echo '<br><span style="color:#777">' . esc_html( $x['disp_nume'] ? $x['disp_nume'] : $x['platforma'] ) . ' · intrat ' . esc_html( mysql2date( 'j.m.Y', $x['creat'] ) ) . ' · folosit ' . esc_html( mysql2date( 'j.m.Y', $x['folosit'] ) ) . '</span>';
		}
		echo '</td></tr><tr><th>Notificări</th><td>' . $np . ' telefoane abonate</td></tr></tbody></table>';
		echo '<form method="post" action="' . esc_url( $url ) . '" style="display:inline-block;margin:8px 8px 0 0"><input type="hidden" name="email" value="' . esc_attr( $caut ) . '">';
		wp_nonce_field( 'ah_app_goleste', 'ah_app_goleste' );
		echo '<button class="button">Golește telefoanele</button></form>';
		echo '<form method="post" action="' . esc_url( $url ) . '" style="display:inline-block;margin-top:8px"><input type="hidden" name="email" value="' . esc_attr( $caut ) . '">';
		wp_nonce_field( 'ah_app_test', 'ah_app_test' );
		echo '<button class="button">Trimite o notificare de probă</button></form>';
	}

	echo '<h2 style="margin-top:30px">Setări</h2><form method="post" action="' . esc_url( $url ) . '">';
	wp_nonce_field( 'ah_app_setari', 'ah_app_nonce' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label for="ios_team">iOS Team ID</label></th><td><input id="ios_team" name="ios_team" value="' . esc_attr( get_option( 'ah_app_ios_team', '' ) ) . '" class="regular-text" placeholder="ABCDE12345"><p class="description">Din developer.apple.com › Membership. Intră în /.well-known/apple-app-site-association.</p></td></tr>';
	echo '<tr><th><label for="android_sha256">Android SHA-256</label></th><td><textarea id="android_sha256" name="android_sha256" rows="3" cols="70" placeholder="AB:CD:…">' . esc_textarea( get_option( 'ah_app_android_sha256', '' ) ) . '</textarea><p class="description">Amprenta certificatului (Google Play Console › Integritatea aplicației › Semnarea aplicației). Poți pune două, pe rânduri separate. Intră în /.well-known/assetlinks.json.</p></td></tr>';
	echo '<tr><th><label for="link_ios">Linkul din App Store</label></th><td><input type="url" id="link_ios" name="link_ios" value="' . esc_attr( get_option( 'ah_app_link_ios', '' ) ) . '" class="regular-text"><p class="description">Apare pe pagina /app/intra, pentru cine nu are încă aplicația.</p></td></tr>';
	echo '<tr><th><label for="link_android">Linkul din Google Play</label></th><td><input type="url" id="link_android" name="link_android" value="' . esc_attr( get_option( 'ah_app_link_android', '' ) ) . '" class="regular-text"></td></tr>';
	echo '<tr><th><label for="versiune">Versiunea minimă</label></th><td><input id="versiune" name="versiune" value="' . esc_attr( get_option( 'ah_app_versiune_minima', '1.0.0' ) ) . '" style="width:110px"><p class="description">Aplicațiile mai vechi decât aceasta îi cer să actualizeze.</p></td></tr>';
	echo '<tr><th><label for="mesaj_titlu">Anunț în aplicație</label></th><td><input id="mesaj_titlu" name="mesaj_titlu" value="' . esc_attr( $msj['titlu'] ?? '' ) . '" class="regular-text" placeholder="Titlu"><br><textarea name="mesaj_text" rows="3" cols="70" placeholder="Textul anunțului">' . esc_textarea( $msj['text'] ?? '' ) . '</textarea><p class="description">Gol = fără anunț. Fără prețuri și fără linkuri de cumpărare (regulile Apple).</p></td></tr>';
	echo '<tr><th><label for="fcm">Cheia Firebase (FCM)</label></th><td>'
		. ( $sa ? '<p>Pusă: proiectul <code>' . esc_html( $sa['project_id'] ) . '</code>, contul <code>' . esc_html( $sa['client_email'] ) . '</code>. <label><input type="checkbox" name="fcm_sterge" value="1"> șterge-o</label></p>' : '<p><b>Nepusă:</b> notificările nu pleacă, se scriu doar în jurnal.</p>' )
		. '<textarea id="fcm" name="fcm" rows="4" cols="70" placeholder="{ &quot;type&quot;: &quot;service_account&quot;, … }" autocomplete="off"></textarea><p class="description">Firebase › Setări proiect › Conturi de serviciu › Generează cheie privată: lipește aici tot fișierul JSON. Gol = rămâne cea de acum.</p></td></tr>';
	echo '</tbody></table><p><button class="button button-primary">Salvează</button></p></form>';

	echo '<h2>Jurnal</h2><pre style="background:#fff;border:1px solid #ddd;padding:10px;max-height:300px;overflow:auto">';
	foreach ( array_reverse( array_slice( (array) get_option( 'ah_app_jurnal', array() ), -40 ) ) as $l ) {
		echo esc_html( $l ) . "\n";
	}
	echo '</pre></div>';
}

endif;
