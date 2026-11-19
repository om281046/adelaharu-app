<?php
/**
 * Spațiul meu – Pentru el (Mircea, 18 nov. 2026). Modul separat: NU schimbă fragmentele existente
 * (Spațiul meu 235, extensiile 266, Harta ciclului 41, modul aplicație ah-sp-aplicatie.php).
 * Arhitectura: docs/arhitectura.md, secțiunea „Contul pentru el”. Detalii și teste: docs/partener.md.
 *
 * Ce adaugă:
 *   1. În „Ciclul meu” al ei (pagina /spatiul-meu/, în browser și în aplicație): cardul „Pentru el”. Ea scrie prenumele și
 *      emailul lui și bifează acordul separat; el primește pe email un link personal permanent. Ea vede cine e invitat,
 *      dacă a intrat și ultima vizită; „Oprește accesul” (pe loc), „Trimite din nou invitația”. Cel mult 2 parteneri activi.
 *      Dacă ciclul ei nu e păstrat în cont, cardul îi cere întâi acordul pentru asta, prin butonul lui 235.
 *      Generatorul vechi de linkuri (?ciclul-ei) e ascuns; linkurile vechi merg mai departe (235 le servește).
 *   2. Pe ecranul de intrare din Spațiul meu: „Sunt partenerul ei” → „Deschide linkul primit pe email” / „Trimite-mi din nou linkul”.
 *   3. Pagina lui, /pentru-el/: „Azi” (unde e ea, ce trăiește, cum o poate sprijini, citatul, ce urmează), „Învață” (6 pagini,
 *      doar din textele de pe site) și Setări (notificări, emailurile fazelor, Ieși, Șterge accesul meu).
 *      Linkul /pentru-el/?t=<token> îl ține conectat un an (cookie semnat ah_spp), apoi adresa se curăță.
 *   4. REST ah-spp/v1: ale ei (parteneri, invita, opreste, retrimite), ale lui (azi, trimite-link, push, email, iesi, sterge).
 *      „azi” calculează pe server, din ciclul ei criptat (ah_sp_ciclu), cu EXACT regula CI.d / CI.lung / CI.faze / CI.azi
 *      din 235. Nu întoarce niciodată notițele ei, situația, simptomele sau zilele notate.
 *   5. Cron zilnic (și la 2 minute după ce ea își salvează ciclul): faza nouă → push (FCM, prin funcțiile modulului aplicație,
 *      dacă e activ) sau email (doar dacă el a cerut). Curățenie: oprite de peste 30 de zile; tot ce ține de ea când ciclul
 *      ei dispare din cont sau își șterge contul (cârligul ah_spa_cont_sters).
 *
 * 1.1.0 (review 18 nov.): menstruația întârziată, oricât de puțin, ajunge la el doar ca pauză, cu un text care nu spune
 * de ce; „Trimite din nou invitația” (ea) dă și o sesiune nouă, deci deconectează orice telefon sau browser al lui;
 * „Trimite-mi din nou linkul” (el) nu mai anulează linkul vechi până nu e folosit cel nou și trimite emailul după răspuns.
 * 1.2.0 (Mircea, 18 nov.): fără texte noi: tot ce citește el vine de pe site (41, 235, podcastul); fără inel și fără
 * „Ziua N din L” (din „azi” au plecat zi, lungime, harta și azi; începuturile doar pe ~5 săptămâni, la „Ce urmează”);
 * emailurile lui numesc operatorul (HARU TRAINING SRL) și politica; versiunea acordului ei (acord_v) stă lângă acord.
 * 1.3.0 (review 18 nov., a doua trecere): în ziua estimată a menstruației nu se mai schimbă nimic la el: iarna următoare apare
 * ca o fereastră (±2 zile), toamna ține „până la următoarea menstruație” încă 10 zile de întârziere, abia apoi pauza; o situație
 * hormonal/sarcină ajunge la el ca pauză după 1–3 zile sau la următoarea graniță de fază (pauza_de_la, tabelul 4); cheia
 * modulului nu mai stă în baza de date (AH_SPP_CHEIE sau sărurile WordPress; migrarea o dată, cookie-urile vechi 30 de zile);
 * rutele care schimbă ceva cer antetul X-AH-SPP; cronul zilnic se reprogramează la 8:10 la fiecare rulare (ora de vară);
 * semnul fazei se notează doar după o trimitere reușită; pagina lui: zone sigure, seara, foaie în loc de confirm(), butonul Înapoi.
 * 1.4.0 (review 18 nov., a doua rundă): adresa lui se verifică strict (diacriticele sau spațiile nu mai sunt „curățate” spre
 * adresa altcuiva); cheia veche (ah_spp_secret) rămâne 30 de zile, iar rândurile se recriptează abia la capăt (o întoarcere la
 * 1.2 în acest timp citește totul); notificarea lui nu mai spune faza pe ecranul blocat (Android: vizibilitate PRIVATE);
 * „Trimite din nou invitația” schimbă linkul doar dacă emailul a plecat; un link vechi oprit nu înlocuiește o sesiune activă;
 * invitat de două femei, telefonul lui primește notificările amândurora (tokenul stă lângă fiecare invitație a lui);
 * /invita sub lacăt (GET_LOCK), versiunea tabelelor doar după ce coloanele chiar există; cârligele ah_spa_cont_mutat și
 * ah_spa_are_acces_el, POST /push/sterge, ah_spp_ultima_rulare; pagina lui: focus și titlu la schimbarea ecranului, <main>,
 * zoom 200%, foi cu butoane unul sub altul.
 *
 * Datele lui: prenume, email (criptat AES-256-GCM cu o cheie separată, plus o amprentă HMAC pentru căutare), tokenul (doar
 * sha256), tokenurile push, ultima vizită, alegerea emailurilor. Legătura cu ea: ah_sp_ciclu_cheie( emailul ei ), nu emailul.
 * Nu face nimic dacă lipsesc funcțiile lui 235 (ah_sp_email, ah_sp_ciclu_*). Aplicația nu vinde nimic: în aplicație, pagina
 * lui nu arată linkurile spre cărți.
 * Oprire: dezactivezi fragmentul. Linkurile din emailuri duc atunci la o pagină inexistentă; datele rămân în tabele până
 * la ștergerea lor (vezi docs/partener.md). Puținele rânduri funcționale fără echivalent pe site: lista din docs/partener.md.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( defined( 'AH_SPP' ) ) {
	return; // încărcat deja (copie dublă a fragmentului)
}
define( 'AH_SPP', '1.4.0' );
define( 'AH_SPP_NS', 'ah-spp/v1' );
define( 'AH_SPP_PAGINA', '/pentru-el/' );
define( 'AH_SPP_MAX', 2 );              // parteneri activi pentru o femeie
define( 'AH_SPP_TABEL_VER', '5' );       // 2: token_nou_h (linkul cerut de el nu-l anulează pe cel vechi până nu e folosit); 3: acord_v; 4: pauza_de_la; 5: push (partener, token) unic
define( 'AH_SPP_ACORD_V', '2026-11-18' ); // versiunea textului acordului ei (dovada acordului, GDPR art. 7 alin. 1), păstrată lângă `acord`
define( 'AH_SPP_OPRIT_ZILE', 30 );      // rândurile oprite se șterg după atâtea zile
define( 'AH_SPP_URM_ZILE', 35 );        // câte zile înainte arată „începuturile” fazelor (la „Ce urmează”, ~5 săptămâni)
define( 'AH_SPP_PUSH_MAX', 5 );         // telefoane cu notificări pentru el
define( 'AH_SPP_FEREASTRA', 2 );        // iarna următoare: fereastra de ± atâtea zile în jurul zilei estimate (fără o zi anume)
define( 'AH_SPP_INTARZIERE', 10 );      // zile de întârziere în care el vede încă toamna (fără date); după ele, pauza
define( 'AH_SPP_TRANZITIE_ZILE', 30 );  // după migrarea cheii: cât timp mai sunt primite cookie-urile și linkurile de oprire vechi
if ( ! defined( 'AH_SPP_LINK_MS' ) ) {
	define( 'AH_SPP_LINK_MS', 1500 );  // timpul minim al /trimite-link, unde emailul nu poate pleca după răspuns
}

if ( ! function_exists( 'ah_spp_gata' ) ) :

/* ==================================================================
   0. DEPENDENȚE, TABELE, SECRETE
   ================================================================== */

/** Merge doar dacă Spațiul meu (235) e activ, cu ciclul în cont. */
function ah_spp_gata() {
	foreach ( array( 'ah_sp_email', 'ah_sp_email_norm', 'ah_sp_ciclu_cheie', 'ah_sp_ciclu_rand', 'ah_sp_ciclu_decripteaza' ) as $f ) {
		if ( ! function_exists( $f ) ) {
			return false;
		}
	}
	return true;
}

function ah_spp_tabel() {
	global $wpdb;
	return $wpdb->prefix . 'ah_spp_parteneri';
}
function ah_spp_tabel_push() {
	global $wpdb;
	return $wpdb->prefix . 'ah_spp_push';
}
/**
 * Tabelele (dbDelta, cu versiune): o singură dată, la primul init după activare sau după o versiune nouă. Versiunea se notează
 * doar dacă tabelele chiar au ieșit cum trebuie (SHOW COLUMNS / SHOW INDEX, 1.4.0): un dbDelta care n-a mers (drepturi, disc,
 * o eroare MySQL) se reîncearcă peste o oră, în loc să rămână pentru totdeauna pe jumătate. În 5, tokenul push e unic pentru
 * (partener, token), nu pentru token: același telefon stă lângă fiecare invitație a lui (invitat de două femei). dbDelta nu
 * șterge indexuri, așa că indexul unic vechi token_h pleacă întâi, de mână.
 */
function ah_spp_db() {
	if ( get_option( 'ah_spp_tabel' ) === AH_SPP_TABEL_VER || get_transient( 'ah_spp_db_incercat' ) ) {
		return;
	}
	global $wpdb;
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	$c = $wpdb->get_charset_collate();
	$t = ah_spp_tabel();
	$p = ah_spp_tabel_push();
	if ( ah_spp_are_tabel( $p ) ) {
		$ix = (array) $wpdb->get_results( "SHOW INDEX FROM {$p} WHERE Key_name = 'token_h'", ARRAY_A );
		if ( $ix && 0 === (int) ( $ix[0]['Non_unique'] ?? 1 ) ) {
			$wpdb->query( "ALTER TABLE {$p} DROP INDEX token_h" );
		}
	}
	dbDelta( "CREATE TABLE {$t} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n ea char(64) NOT NULL,\n nume varchar(60) NOT NULL DEFAULT '',\n nume_ea varchar(60) NOT NULL DEFAULT '',\n email_c text NOT NULL,\n email_h char(64) NOT NULL,\n token_h char(64) NOT NULL,\n token_nou_h char(64) NOT NULL DEFAULT '',\n ses char(32) NOT NULL,\n acord datetime NOT NULL,\n acord_v varchar(20) NOT NULL DEFAULT '',\n creat datetime NOT NULL,\n invitat datetime NOT NULL,\n intrat datetime DEFAULT NULL,\n vizita datetime DEFAULT NULL,\n oprit datetime DEFAULT NULL,\n email_faze tinyint(1) NOT NULL DEFAULT 0,\n faza_trimisa varchar(40) NOT NULL DEFAULT '',\n pauza_de_la varchar(10) NOT NULL DEFAULT '',\n PRIMARY KEY  (id),\n UNIQUE KEY token_h (token_h),\n KEY ea (ea),\n KEY email_h (email_h),\n KEY token_nou_h (token_nou_h)\n) {$c};" );
	dbDelta( "CREATE TABLE {$p} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n partener bigint(20) unsigned NOT NULL,\n token text NOT NULL,\n token_h char(64) NOT NULL,\n platforma varchar(10) NOT NULL DEFAULT '',\n creat datetime NOT NULL,\n vazut datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY partener_token (partener,token_h),\n KEY token_h (token_h),\n KEY partener (partener)\n) {$c};" );
	$lipsa = array_diff( array( 'token_nou_h', 'acord_v', 'pauza_de_la' ), ah_spp_coloane( $t ) );
	$ix    = (array) $wpdb->get_results( "SHOW INDEX FROM {$p} WHERE Key_name = 'partener_token'", ARRAY_A );
	if ( $lipsa || ! $ix ) {
		set_transient( 'ah_spp_db_incercat', 1, HOUR_IN_SECONDS );
		ah_spp_jurnal( 'tabelele NU s-au actualizat (' . ( $lipsa ? 'lipsesc ' . implode( ', ', $lipsa ) : 'push fără indexul partener_token' ) . '); se reîncearcă peste o oră' );
		return;
	}
	update_option( 'ah_spp_tabel', AH_SPP_TABEL_VER, false );
}
function ah_spp_are_tabel( $t ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $t ) : $t ) ) === $t;
}
/** Numele coloanelor unui tabel (gol dacă tabelul nu există). */
function ah_spp_coloane( $t ) {
	global $wpdb;
	return ah_spp_are_tabel( $t ) ? array_map( 'strval', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$t}" ) ) : array();
}

/**
 * Cheia modulului (separată de a ciclului din 235): criptarea emailului lui, amprentele, cookie-ul, linkurile de oprire.
 * Din 1.3.0 NU stă în baza de date: constanta AH_SPP_CHEIE din wp-config.php, dacă e definită, altfel derivată din sărurile
 * WordPress (AUTH_KEY/SALT + SECURE_AUTH_KEY/SALT, tot din wp-config.php). O copie a bazei de date nu mai ajunge pentru a
 * decripta emailurile lor. Schimbarea constantei sau a sărurilor = emailurile lor nu se mai pot citi, toți sunt deconectați
 * (docs/partener.md). Cheia veche din opțiunea ah_spp_secret (până la 1.2.0) se mută o dată: ah_spp_migreaza_cheia().
 */
function ah_spp_secret() {
	if ( defined( 'AH_SPP_CHEIE' ) && is_string( AH_SPP_CHEIE ) && '' !== AH_SPP_CHEIE ) {
		return hash_hmac( 'sha256', 'ah-spp|cheie', AH_SPP_CHEIE );
	}
	return hash_hmac( 'sha256', 'ah-spp|cheie', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) );
}
/** Subcheia pentru un scop; $secret = altă cheie decât cea curentă (doar la migrare). */
function ah_spp_cheie( $scop, $secret = null ) {
	return hash( 'sha256', ( null === $secret ? ah_spp_secret() : (string) $secret ) . '|' . $scop, true );
}
/** AES-256-GCM, același format ca ah_sp_ciclu_cripteaza (iv 12 + tag 16 + text, base64), cu cheia acestui modul. */
function ah_spp_cripteaza( $s, $secret = null ) {
	$iv  = random_bytes( 12 );
	$tag = '';
	$c   = openssl_encrypt( (string) $s, 'aes-256-gcm', ah_spp_cheie( 'email', $secret ), OPENSSL_RAW_DATA, $iv, $tag );
	return base64_encode( $iv . $tag . $c );
}
/**
 * Decriptarea. Fără $secret: cheia de acum și, cât ține tranziția (cheia veche e încă în ah_spp_secret), cea veche: rândurile
 * de dinainte de 1.3 rămân criptate cu ea până la capătul tranziției (ah_spp_migreaza_cheia), ca 1.2 să le poată citi încă.
 */
function ah_spp_decripteaza( $b, $secret = null ) {
	$r = base64_decode( (string) $b, true );
	if ( ! $r || strlen( $r ) < 29 ) {
		return null;
	}
	$p = openssl_decrypt( substr( $r, 28 ), 'aes-256-gcm', ah_spp_cheie( 'email', $secret ), OPENSSL_RAW_DATA, substr( $r, 0, 12 ), substr( $r, 12, 16 ) );
	if ( false === $p && null === $secret ) {
		$v = ah_spp_secret_vechi();
		if ( null !== $v ) {
			$p = openssl_decrypt( substr( $r, 28 ), 'aes-256-gcm', ah_spp_cheie( 'email', $v ), OPENSSL_RAW_DATA, substr( $r, 0, 12 ), substr( $r, 12, 16 ) );
		}
	}
	return false === $p ? null : $p;
}
/** Cheia veche (opțiunea ah_spp_secret, până la 1.2.0): există doar cât ține tranziția; altfel null. */
function ah_spp_secret_vechi() {
	$v = get_option( 'ah_spp_secret' );
	return is_string( $v ) && '' !== $v ? $v : null;
}
/**
 * Subcheile vechi (cookie, stop) cât ține tranziția de după trecerea la cheia nouă (AH_SPP_TRANZITIE_ZILE). Opțiunea
 * ah_spp_tranzitie ține doar aceste două subchei derivate și data de sfârșit; pleacă la capăt, odată cu cheia veche
 * (ah_spp_migreaza_cheia). Un site trecut prin 1.3 (cheia veche ștearsă deja) o șterge aici.
 */
function ah_spp_cheie_veche( $scop ) {
	$t = get_option( 'ah_spp_tranzitie' );
	if ( ! is_array( $t ) ) {
		return null;
	}
	if ( (int) ( $t['pana'] ?? 0 ) < time() ) {
		if ( null === ah_spp_secret_vechi() ) {
			delete_option( 'ah_spp_tranzitie' );
		}
		return null;
	}
	$h = (string) ( $t[ $scop ] ?? '' );
	return 64 === strlen( $h ) && ctype_xdigit( $h ) ? hex2bin( $h ) : null;
}
/**
 * Trecerea de la cheia din baza de date (ah_spp_secret, până la 1.2.0) la cheia din wp-config, în doi pași (1.4.0).
 * 1. Primul init: începe tranziția de AH_SPP_TRANZITIE_ZILE zile. Nimic nu se rescrie: rândurile vechi se citesc cu cheia veche
 *    (ah_spp_decripteaza, ah_spp_amprente, semnul fazei), cookie-urile și linkurile de oprire vechi sunt primite; ce e nou se
 *    scrie cu cheia nouă. Cheia veche rămâne în baza de date, deci o întoarcere la 1.2 în acest timp găsește aceeași cheie și
 *    aceleași rânduri (în 1.3 opțiunea se ștergea pe loc, iar 1.2 și-ar fi făcut alta: toate emailurile lor, pierdute).
 * 2. La capătul tranziției: fiecare email vechi se recriptează cu cheia nouă, amprenta și semnul fazei se refac, apoi cheia
 *    veche și subcheile pleacă. De aici, o întoarcere sub 1.3 nu mai e sigură (docs/partener.md).
 * Un rând care se decriptează deja cu cheia nouă e sărit (rânduri noi, o rulare întreruptă). Întoarce câte rânduri au fost mutate.
 */
function ah_spp_migreaza_cheia() {
	$vechi = ah_spp_secret_vechi();
	if ( null === $vechi || get_transient( 'ah_spp_migrare' ) ) {
		return 0;
	}
	$t = get_option( 'ah_spp_tranzitie' );
	if ( ! is_array( $t ) ) {
		update_option( 'ah_spp_tranzitie', array(
			'cookie' => bin2hex( ah_spp_cheie( 'cookie', $vechi ) ),
			'stop'   => bin2hex( ah_spp_cheie( 'stop', $vechi ) ),
			'pana'   => time() + AH_SPP_TRANZITIE_ZILE * DAY_IN_SECONDS,
		), false );
		ah_spp_jurnal( 'cheia nouă e activă; cheia veche rămâne ' . AH_SPP_TRANZITIE_ZILE . ' zile (până atunci, întoarcerea la 1.2 e sigură)' );
		return 0;
	}
	if ( (int) ( $t['pana'] ?? 0 ) >= time() ) {
		return 0;
	}
	set_transient( 'ah_spp_migrare', 1, 300 );
	global $wpdb;
	$n     = 0;
	$rau   = 0;
	$nou   = ah_spp_secret();
	$azi0  = ah_spp_azi0();
	$cache = array();
	foreach ( (array) $wpdb->get_results( 'SELECT id, ea, email_c, faza_trimisa FROM ' . ah_spp_tabel() . ' ORDER BY id ASC', ARRAY_A ) as $r ) {
		if ( null !== ah_spp_decripteaza( $r['email_c'], $nou ) ) {
			continue;
		}
		$em = ah_spp_decripteaza( $r['email_c'], $vechi );
		if ( null === $em ) {
			$rau++;
			continue;
		}
		$d = array( 'email_c' => ah_spp_cripteaza( $em ), 'email_h' => ah_spp_hash_email( $em ) );
		if ( '' !== (string) $r['faza_trimisa'] && ah_spp_gata() ) {
			if ( ! isset( $cache[ $r['ea'] ] ) ) {
				$cache[ $r['ea'] ] = ah_spp_ciclul_ei( $r['ea'], $azi0 );
			}
			$z = $cache[ $r['ea'] ]['z'] ?? null;
			if ( $z && hash_equals( ah_spp_semn_faza( $z, $vechi ), (string) $r['faza_trimisa'] ) ) {
				$d['faza_trimisa'] = ah_spp_semn_faza( $z );
			}
		}
		$wpdb->update( ah_spp_tabel(), $d, array( 'id' => (int) $r['id'] ) );
		$n++;
	}
	delete_option( 'ah_spp_secret' );
	delete_option( 'ah_spp_tranzitie' );
	delete_transient( 'ah_spp_migrare' );
	ah_spp_jurnal( 'cheia veche a plecat din baza de date: ' . $n . ' rânduri mutate' . ( $rau ? ', ' . $rau . ' care nu se puteau decripta' : '' ) );
	return $n;
}
function ah_spp_norm( $e ) {
	return strtolower( trim( (string) $e ) );
}
/** Amprenta emailului lui (căutare fără să-l decriptăm). */
function ah_spp_hash_email( $e, $secret = null ) {
	return hash_hmac( 'sha256', ah_spp_norm( $e ), ( null === $secret ? ah_spp_secret() : (string) $secret ) . '|el' );
}
/** Amprentele adresei lui: cu cheia de acum și, cât ține tranziția, cu cea veche (rândurile încă nemutate). */
function ah_spp_amprente( $e ) {
	$l = array( ah_spp_hash_email( $e ) );
	$v = ah_spp_secret_vechi();
	if ( null !== $v ) {
		$l[] = ah_spp_hash_email( $e, $v );
	}
	return $l;
}
/** Token nou: 32 de octeți aleatori, base64url (43 de caractere). Pe server rămâne doar sha256. */
function ah_spp_token_nou() {
	$t = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	return array( $t, hash( 'sha256', $t ) );
}
function ah_spp_token_valid( $t ) {
	return is_string( $t ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $t );
}
/** Legătura cu ea: aceeași cheie ca rândul ei din ah_sp_ciclu. */
function ah_spp_ea( $email ) {
	return ah_sp_ciclu_cheie( $email );
}
function ah_spp_acum() {
	return current_time( 'mysql' );
}
function ah_spp_azi0() {
	return ah_spp_zi0( wp_date( 'Y-m-d' ) );
}

/* ---------- jurnal și limite (fără adrese de email, fără tokenuri) ---------- */
function ah_spp_jurnal( $text ) {
	$j   = get_option( 'ah_spp_jurnal', array() );
	$j   = is_array( $j ) ? $j : array();
	$j[] = array( time(), (string) $text );
	update_option( 'ah_spp_jurnal', array_slice( $j, -150 ), false );
}
function ah_spp_limita( $cheie, $max, $sec = HOUR_IN_SECONDS ) {
	$k = 'ah_spp_l_' . substr( hash_hmac( 'sha256', (string) $cheie, ah_spp_secret() ), 0, 24 );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $k, $n + 1, $sec );
	return true;
}
function ah_spp_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return '' === $ip ? 'necunoscut' : $ip;
}

/* ==================================================================
   1. CICLUL EI: EXACT REGULA DIN 235 (CI.d, CI.zi0, CI.iso, CI.lung, CI.faze, CI.azi)
   Pe server datele vin doar din ah_sp_ciclu (date.ciclu = ce ținea ea în localStorage.ahsp_ciclu); ah_ciclu_v1 nu există aici.
   Numerele urmează regulile JavaScript (Number(), ||, Math.round), testul node le compară cu 235: tests/partener/faze.test.cjs.
   ================================================================== */

/** Adevărat în JavaScript (0, NaN, '', null, false sunt false; tablourile și obiectele sunt adevărate). */
function ah_spp_js_adevarat( $v ) {
	if ( null === $v || false === $v || '' === $v ) {
		return false;
	}
	if ( is_int( $v ) || is_float( $v ) ) {
		return ! ( 0.0 === (float) $v || is_nan( (float) $v ) );
	}
	return true;
}
/** Number(v) din JavaScript, pentru valorile care pot veni din JSON. */
function ah_spp_num( $v ) {
	if ( is_int( $v ) || is_float( $v ) ) {
		return (float) $v;
	}
	if ( is_bool( $v ) ) {
		return $v ? 1.0 : 0.0;
	}
	if ( null === $v ) {
		return 0.0;
	}
	if ( is_string( $v ) ) {
		$s = trim( $v, " \t\n\r\x0B\f" );
		if ( '' === $s ) {
			return 0.0;
		}
		if ( preg_match( '/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/D', $s ) ) {
			return (float) $s;
		}
		if ( preg_match( '/^0[xX][0-9a-fA-F]+$/D', $s ) ) {
			return (float) hexdec( substr( $s, 2 ) );
		}
		if ( preg_match( '/^0[bB][01]+$/D', $s ) ) {
			return (float) bindec( substr( $s, 2 ) );
		}
		if ( preg_match( '/^0[oO][0-7]+$/D', $s ) ) {
			return (float) octdec( substr( $s, 2 ) );
		}
		if ( preg_match( '/^([+-]?)Infinity$/D', $s, $m ) ) {
			return '-' === $m[1] ? -INF : INF;
		}
		return NAN;
	}
	if ( is_array( $v ) ) {
		if ( ! array_is_list( $v ) ) {
			return NAN; // obiect
		}
		if ( ! $v ) {
			return 0.0;
		}
		if ( 1 === count( $v ) ) { // [x] → String(x)
			$x = $v[0];
			if ( is_array( $x ) ) {
				return ah_spp_num( $x );
			}
			return ah_spp_num( null === $x ? '' : ( is_bool( $x ) ? ( $x ? 'true' : 'false' ) : (string) $x ) );
		}
		return NAN;
	}
	return NAN;
}
/** Zilele de la 1970-01-01 pentru o dată civilă (proleptic gregorian). */
function ah_spp_zile_civil( $y, $m, $d ) {
	$y  -= $m <= 2 ? 1 : 0;
	$era = intdiv( $y >= 0 ? $y : $y - 399, 400 );
	$yoe = $y - $era * 400;
	$doy = intdiv( 153 * ( $m + ( $m > 2 ? -3 : 9 ) ) + 2, 5 ) + $d - 1;
	$doe = $yoe * 365 + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) + $doy;
	return $era * 146097 + $doe - 719468;
}
/** CI.zi0: Date.UTC(an, lună-1, zi)/864e5, cu aceleași reguli (anii 0–99 → 1900+, luna și ziua se reportează). */
function ah_spp_zi0( $s ) {
	$p = explode( '-', (string) $s );
	$y = (int) ( $p[0] ?? 0 );
	$m = (int) ( $p[1] ?? 1 ) - 1;
	$d = (int) ( $p[2] ?? 1 );
	if ( $y >= 0 && $y <= 99 ) {
		$y += 1900;
	}
	$q  = (int) floor( $m / 12 );
	$y += $q;
	$m -= 12 * $q;
	return ah_spp_zile_civil( $y, $m + 1, 1 ) + $d - 1;
}
/** CI.iso: new Date(z*864e5).toISOString().slice(0,10). */
function ah_spp_iso( $z ) {
	return gmdate( 'Y-m-d', (int) floor( (float) $z ) * 86400 );
}
/** CI.d (fără ah_ciclu_v1): starturi valide, unice, cele mai noi întâi, cel mult 13; lung 21–40 (28), men 2–9 (5). */
function ah_spp_d( $x ) {
	if ( ! is_array( $x ) || array_is_list( $x ) ) {
		return null;
	}
	$st = array();
	$s  = $x['starturi'] ?? null;
	if ( is_array( $s ) && array_is_list( $s ) ) {
		$st = $s;
	} elseif ( ah_spp_js_adevarat( $s ) ) {
		$st = array( $s ); // concat cu o valoare care nu e tablou o adaugă ca element
	}
	$v = array();
	foreach ( $st as $q ) {
		if ( is_string( $q ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $q ) && ! in_array( $q, $v, true ) ) {
			$v[] = $q;
		}
	}
	if ( ! $v ) {
		return null;
	}
	sort( $v, SORT_STRING );
	$v  = array_slice( array_reverse( $v ), 0, 13 );
	$cl = function ( $val, $a, $b, $d ) {
		if ( ! ah_spp_js_adevarat( $val ) ) {
			return $d; // x.lung||v.c: v.c nu există pe server → Number(undefined) = NaN
		}
		$n = ah_spp_num( $val );
		return ( $n >= $a && $n <= $b ) ? $n : $d;
	};
	return array(
		'starturi' => $v,
		'lung'     => $cl( $x['lung'] ?? null, 21, 40, 28 ),
		'men'      => $cl( $x['men'] ?? null, 2, 9, 5 ),
	);
}
/** CI.lung: media diferențelor de 21–40 de zile dintre ultimele starturi (cel puțin 2), altfel lungimea notată. */
function ah_spp_lung( $x ) {
	$st  = array_map( 'ah_spp_zi0', $x['starturi'] );
	$dif = array();
	for ( $i = 0; $i + 1 < count( $st ) && count( $dif ) < 6; $i++ ) {
		$v = $st[ $i ] - $st[ $i + 1 ];
		if ( $v >= 21 && $v <= 40 ) {
			$dif[] = $v;
		}
	}
	if ( count( $dif ) >= 2 ) {
		return (float) floor( array_sum( $dif ) / count( $dif ) + 0.5 ); // Math.round
	}
	return ah_spp_js_adevarat( $x['lung'] ) ? (float) $x['lung'] : 28.0;
}
/** CI.faze: oS = max(L-16, m+2), oE = min(oS+4, L-2). */
function ah_spp_faze( $L, $m ) {
	$oS = max( $L - 16, $m + 2 );
	$oE = min( $oS + 4, $L - 2 );
	return array(
		array( 'f' => 'iarna', 'a' => 1, 'b' => $m ),
		array( 'f' => 'primavara', 'a' => $m + 1, 'b' => $oS - 1 ),
		array( 'f' => 'vara', 'a' => $oS, 'b' => $oE ),
		array( 'f' => 'toamna', 'a' => $oE + 1, 'b' => $L ),
	);
}
/** CI.azi, cu „azi” dat (zile de la 1970). Null dacă nu sunt date sau prima zi e în viitor. */
function ah_spp_azi( $x, $azi0 ) {
	if ( ! $x ) {
		return null;
	}
	$L  = ah_spp_lung( $x );
	$m  = ah_spp_js_adevarat( $x['men'] ) ? (float) $x['men'] : 5.0;
	$s0 = ah_spp_zi0( $x['starturi'][0] );
	$zi = $azi0 - $s0 + 1;
	if ( $zi < 1 ) {
		return null;
	}
	$fz = ah_spp_faze( $L, $m );
	$f  = 'toamna';
	$fi = 3;
	foreach ( $fz as $i => $p ) {
		if ( $zi >= $p['a'] && $zi <= $p['b'] ) {
			$f  = $p['f'];
			$fi = $i;
		}
	}
	return array( 'zi' => $zi, 'L' => $L, 'm' => $m, 'f' => $f, 'fi' => $fi, 's0' => $s0, 'fz' => $fz, 'urm' => $s0 + $L, 'peste' => $zi > $L );
}
/** Situațiile în care el vede faza (CI.sit: o valoare necunoscută sau lipsă înseamnă „natural”, ca pe ecranul ei). */
function ah_spp_sit_ok( $sit ) {
	$toate = array( 'natural', 'hormonal', 'sarcina', 'alaptare', 'perimenopauza' );
	$s     = is_string( $sit ) && in_array( $sit, $toate, true ) ? $sit : 'natural';
	return in_array( $s, array( 'natural', 'alaptare', 'perimenopauza' ), true );
}

/**
 * Starea ciclului ei, din rândul criptat: array( 'stare' => ok|pauza, 'z' => calculul CI.azi, 'motiv' => … ).
 * pauza: fără rând / fără date / prima zi în viitor / menstruația întârzie peste AH_SPP_INTARZIERE zile / situație fără faze.
 * Întârzierea de până la 10 zile NU e pauză (1.3.0): el vede mai departe toamna, „până la următoarea menstruație”, fără
 * date; așa, în ziua estimată și după ea nu se schimbă nimic la el (înainte, pauza apărea exact în ziua estimată).
 * Situația hormonal/sarcină: 'motiv' => 'situatie', cu 'z' = ce ar vedea el altfel (dacă ar vedea ceva); amânarea pauzei
 * se hotărăște pentru fiecare partener în ah_spp_stare().
 */
function ah_spp_ciclul_ei( $cheie, $azi0 ) {
	global $wpdb;
	if ( function_exists( 'ah_sp_ciclu_db' ) ) {
		ah_sp_ciclu_db();
	}
	$t = function_exists( 'ah_sp_ciclu_tabel' ) ? ah_sp_ciclu_tabel() : $wpdb->prefix . 'ah_sp_ciclu';
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT date FROM ' . $t . ' WHERE cheie = %s', $cheie ), ARRAY_A );
	if ( ! $r ) {
		return array( 'stare' => 'pauza', 'motiv' => 'fara_ciclu' );
	}
	$d = json_decode( (string) ah_sp_ciclu_decripteaza( $r['date'] ), true );
	$c = is_array( $d ) && isset( $d['ciclu'] ) ? $d['ciclu'] : null;
	if ( ! is_array( $c ) ) {
		return array( 'stare' => 'pauza', 'motiv' => 'date' );
	}
	$z = ah_spp_azi( ah_spp_d( $c ), $azi0 );
	if ( $z && $z['zi'] - $z['L'] - 1 > AH_SPP_INTARZIERE ) {
		$z = null; // întârziere lungă (zile după ziua estimată, ziua L+1): pauza, oricare ar fi situația
	}
	if ( ! ah_spp_sit_ok( $c['sit'] ?? null ) ) {
		return array( 'stare' => 'pauza', 'motiv' => 'situatie', 'z' => $z );
	}
	if ( ! $z ) {
		return array( 'stare' => 'pauza', 'motiv' => 'date' );
	}
	return array( 'stare' => 'ok', 'z' => $z );
}
/** Câte zile (1–3) așteaptă pauza după o situație nouă: aleatoriu pentru el, dar același la fiecare cerere (HMAC). */
function ah_spp_amanare( $r, $azi0 ) {
	return 1 + hexdec( substr( hash_hmac( 'sha256', 'amanare|' . (int) $r['id'] . '|' . (string) $r['ea'] . '|' . (int) $azi0, ah_spp_secret() ), 0, 6 ) ) % 3;
}
/**
 * Starea pentru un partener (rândul lui $r), din starea ciclului ei $c. Când ea trece la hormonal/sarcină, el nu vede pauza pe
 * loc (ar afla momentul schimbării): pauza începe la următoarea graniță de fază sau după 1–3 zile, care vine întâi (granița
 * spre iarnă nu se folosește: ar cădea pe ziua estimată a menstruației). Ziua e păstrată în rândul lui (pauza_de_la), deci
 * aceeași la fiecare cerere; se golește când situația ei revine.
 */
function ah_spp_stare( $r, $c, $azi0 ) {
	global $wpdb;
	$de_la = (string) ( $r['pauza_de_la'] ?? '' );
	if ( 'situatie' !== ( $c['motiv'] ?? '' ) ) {
		if ( '' !== $de_la && ! empty( $r['id'] ) ) {
			$wpdb->update( ah_spp_tabel(), array( 'pauza_de_la' => '' ), array( 'id' => (int) $r['id'] ) );
		}
		return $c;
	}
	$z = $c['z'] ?? null;
	if ( ! $z || empty( $r['id'] ) ) {
		return array( 'stare' => 'pauza', 'motiv' => 'situatie' );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $de_la ) ) {
		$zi = $azi0 + ah_spp_amanare( $r, $azi0 );
		if ( $z['fi'] < 3 ) {
			$zi = min( $zi, $z['s0'] + $z['fz'][ $z['fi'] + 1 ]['a'] - 1 );
		}
		$de_la = ah_spp_iso( $zi );
		$wpdb->update( ah_spp_tabel(), array( 'pauza_de_la' => $de_la ), array( 'id' => (int) $r['id'] ) );
	}
	return ah_spp_zi0( $de_la ) <= $azi0 ? array( 'stare' => 'pauza', 'motiv' => 'situatie' ) : array( 'stare' => 'ok', 'z' => $z );
}

/* ==================================================================
   2. TEXTELE: doar ce e deja pe site (decizia lui Mircea, 18 nov.). Fazele: Harta ciclului (41, ah_ciclu_faze);
   citatele și „cum o poți sprijini”: 235 (ah_sp_partener_texte); podcastul: 235 (ah_sp_podcast); numele fazelor și
   frazele paginii: pagina veche ?ciclul-ei din 235. Rândurile funcționale fără sursă pe site: docs/partener.md.
   ================================================================== */

/** Numele și culorile fazelor, ca F din pagina ?ciclul-ei (235); c2 = varianta pentru seară (mai deschisă, contrast AA). */
function ah_spp_nume_faze() {
	return array(
		'iarna'     => array( 'n' => 'Iarna ei interioară', 'f' => 'Faza menstruală', 'c' => '#7A5A75', 'c2' => '#D2B3CD' ),
		'primavara' => array( 'n' => 'Primăvara ei interioară', 'f' => 'Faza foliculară', 'c' => '#56704D', 'c2' => '#AFCDA4' ),
		'vara'      => array( 'n' => 'Vara ei interioară', 'f' => 'Faza ovulatorie', 'c' => '#9E533A', 'c2' => '#EDAE95' ),
		'toamna'    => array( 'n' => 'Toamna ei interioară', 'f' => 'Faza luteală', 'c' => '#85622E', 'c2' => '#E0C08A' ),
	);
}
/** Textele fiecărei faze pentru el: ce trăiește (41), cum o poate sprijini (235), citatele din Magia Ciclului Feminin (235). */
function ah_spp_texte_faze() {
	static $t = null;
	if ( null !== $t ) {
		return $t;
	}
	$t = function_exists( 'ah_sp_partener_texte' ) ? (array) ah_sp_partener_texte() : array();
	return $t;
}
/**
 * Notificarea lui la începutul unei faze (push): neutră, fără numele fazei (pe ecranul blocat o poate vedea oricine). Rând
 * funcțional (docs/partener.md); faza și „cum o poți sprijini” se văd doar în pagină.
 */
function ah_spp_push_faza() {
	return array( 'Ciclul ei', 'Începe o fază nouă. Vezi cum o poți sprijini.' );
}
/** Subiectul emailului fazei: neutru (se vede în lista de emailuri și în notificarea clientului de email). */
function ah_spp_subiect_faza() {
	return 'Ciclul ei: o fază nouă';
}
/** Titlul din emailul fazei: numele fazei și primul rând din „cum o poți sprijini” (ca fișierul .ics din 235). */
function ah_spp_mesaj_faza( $f ) {
	$N = ah_spp_nume_faze();
	$T = ah_spp_texte_faze();
	if ( ! isset( $N[ $f ] ) ) {
		return array( 'Ciclul ei', '' );
	}
	return array( 'Începe ' . $N[ $f ]['n'], (string) ( $T[ $f ]['sprijin'][0] ?? '' ) );
}
/** Mesajele de stare pentru el. „oprit”: cuvintele paginii ?ciclul-ei (235); „pauza”: rând funcțional, neutru (nu spune de ce). */
function ah_spp_mesaje() {
	return array(
		'pauza'     => 'Pentru moment, aici nu e nicio estimare.',
		'pauza_sub' => '',
		'oprit'     => 'Linkul a fost oprit',
		'oprit_sub' => 'Linkul acesta nu mai e activ. Dacă vrei să-l primești din nou, vorbește cu ea.',
	);
}
/** Nota de sub fazele lui: 41 („Fazele sunt o estimare…”) + pagina ?ciclul-ei din 235. */
function ah_spp_nota() {
	return 'Fazele sunt o estimare: nu sunt o metodă de contracepție. Corpul ei poate avea alt ritm într-o lună sau alta; cel mai bun reper rămâne ce îți spune ea. Ea poate opri oricând acest link.';
}

/**
 * „Învață”: 6 pagini, doar din textele de pe site. 1: cele 4 faze (41: pe_scurt, plus primul paragraf al „Notei” din
 * Spre feminitate, de pe Harta ciclului); 2–5: fiecare fază (41: pe_scurt, simti, ajuta, practica; 235: sprijin, citate);
 * 6: episoadele 2–6 din podcastul Intim Feminin (titlul și descrierea de pe Resurse gratuite, fără „Mai departe”).
 */
function ah_spp_invata( $in_aplicatie = false ) {
	$h  = function_exists( 'ah_ciclu_faze' ) ? (array) ah_ciclu_faze() : array();
	$T  = ah_spp_texte_faze();
	$N  = ah_spp_nume_faze();
	$pc = function_exists( 'ah_sp_podcast' ) ? (array) ah_sp_podcast() : array();
	$ep = array();
	foreach ( (array) ( $pc['episoade'] ?? array() ) as $e ) {
		$ep[ (int) ( $e['nr'] ?? 0 ) ] = array( 't' => 'podcast', 'titlu' => (string) ( $e['titlu'] ?? '' ), 'text' => (string) ( $e['desc'] ?? '' ), 'meta' => (string) ( $e['meta'] ?? '' ), 'url' => (string) ( $e['apple'] ?? '' ) );
	}
	$harta = array( 'iarna' => 'menstruala', 'primavara' => 'foliculara', 'vara' => 'ovulatorie', 'toamna' => 'premenstruala' );
	$P     = function ( $s ) { return array( 't' => 'p', 'text' => (string) $s ); };
	$H     = function ( $s ) { return array( 't' => 'h', 'text' => (string) $s ); };
	$UL    = function ( $l ) { return array( 't' => 'ul', 'l' => array_values( array_filter( array_map( 'strval', (array) $l ) ) ) ); };
	$CIT   = function ( $l, $sursa ) { return array( 't' => 'citat', 'l' => array_values( array_filter( array_map( 'strval', (array) $l ) ) ), 'sursa' => (string) $sursa ); };
	$sub   = function ( $k ) use ( $N, $T ) {
		return implode( ' · ', array_filter( array( $N[ $k ]['f'], (string) ( $T[ $k ]['arhetip'] ?? '' ), (string) ( $T[ $k ]['luna'] ?? '' ) ) ) );
	};

	$pag = array();

	// 1. Cele 4 faze (titlul secțiunii de pe Harta ciclului)
	$b = array();
	foreach ( $harta as $k => $hk ) {
		$b[] = $H( $N[ $k ]['n'] );
		$b[] = array( 't' => 'mic', 'text' => $sub( $k ) );
		$b[] = $P( (string) ( $h[ $hk ]['pe_scurt'] ?? '' ) );
	}
	$b[]   = $CIT( array( 'Aceste faze sunt mai degrabă niște etape energetice corespunzătoare ciclului feminin, observate de-a lungul timpului de către multe femei conștiente de corpul și de ciclul lor.' ), 'Ce le spune Adela femeilor, în Spre feminitate' );
	$pag[] = array( 'id' => 'faze', 'titlu' => 'Cele 4 faze ale ciclului feminin', 'sub' => 'Fragment din cartea Spre feminitate, de Adela Haru', 'c' => '#964E46', 'c2' => '#EDB7A4', 'b' => $b );

	// 2–5. Fiecare fază
	foreach ( $harta as $k => $hk ) {
		$f = $h[ $hk ] ?? array();
		$x = $T[ $k ] ?? array();
		$b = array( $P( (string) ( $f['pe_scurt'] ?? '' ) ), $P( (string) ( $f['simti'] ?? '' ) ) );
		if ( ! empty( $x['sprijin'] ) ) {
			$b[] = $H( 'Cum o poți sprijini' );
			$b[] = $UL( $x['sprijin'] );
		}
		$b[] = $CIT( array_merge( (array) ( $f['ajuta'] ?? array() ), array( (string) ( $f['practica'] ?? '' ) ) ), 'Ce le spune Adela femeilor, în Spre feminitate' );
		$b[] = $CIT( $x['citate'] ?? array(), 'Ce le spune Adela femeilor, în Magia Ciclului Feminin' );
		$pag[] = array( 'id' => $k, 'titlu' => $N[ $k ]['n'], 'sub' => $sub( $k ), 'c' => $N[ $k ]['c'], 'c2' => $N[ $k ]['c2'], 'b' => $b );
	}

	// 6. Podcastul
	$b = array();
	foreach ( array( 2, 3, 4, 5, 6 ) as $n ) {
		if ( isset( $ep[ $n ] ) ) {
			$b[] = $ep[ $n ];
		}
	}
	if ( $b ) {
		$pag[] = array( 'id' => 'podcast', 'titlu' => 'Din podcastul Intim Feminin', 'sub' => (string) ( $pc['titlu'] ?? '' ), 'c' => '#6B5B8E', 'c2' => '#C2B4E6', 'b' => $b );
	}

	// fără blocuri goale (dacă 41 lipsește, rămân textele din 235)
	foreach ( $pag as &$p ) {
		$p['b'] = array_values( array_filter( $p['b'], function ( $x ) {
			return ! ( in_array( $x['t'], array( 'p', 'h', 'mic' ), true ) && '' === trim( (string) $x['text'] ) ) && ! ( in_array( $x['t'], array( 'ul', 'citat' ), true ) && ! $x['l'] );
		} ) );
	}
	unset( $p );
	return $pag;
}

/* ==================================================================
   3. RĂSPUNSUL „AZI” PENTRU EL (minim: nimic din contul ei în afară de faze)
   ================================================================== */

/**
 * Ce vede el azi. $r = rândul lui. Nu conține niciodată notițe, situația, simptomele sau zilele notate de ea.
 * Iarna următoare (menstruația) nu are o zi: doar fereastra „între de_la și pana_la” (±AH_SPP_FEREASTRA zile), arătată până
 * la capătul ei; toamna ține „până la următoarea menstruație” (pana_la null), iar începuturile se opresc la iarna următoare
 * (ce vine după ea depinde de ziua în care începe). Așa, răspunsul e identic în ziua dinainte, în ziua estimată și după ea.
 */
function ah_spp_azi_pentru( $r, $azi0 = null ) {
	$azi0 = null === $azi0 ? ah_spp_azi0() : (int) $azi0;
	$M    = ah_spp_mesaje();
	$baza = array( 'nume_ea' => (string) $r['nume_ea'], 'nume' => (string) $r['nume'] );
	if ( ! empty( $r['oprit'] ) ) {
		return $baza + array( 'stare' => 'oprit', 'mesaj' => $M['oprit'], 'sub' => $M['oprit_sub'] );
	}
	$c = ah_spp_stare( $r, ah_spp_ciclul_ei( $r['ea'], $azi0 ), $azi0 );
	if ( 'ok' !== $c['stare'] ) {
		return $baza + array( 'stare' => 'pauza', 'mesaj' => $M['pauza'], 'sub' => $M['pauza_sub'] );
	}
	$z   = $c['z'];
	$T   = ah_spp_texte_faze();
	$fz  = $z['fz'];
	$cur = $fz[ $z['fi'] ];
	$X   = $T[ $z['f'] ] ?? array();
	$ni  = ( $z['fi'] + 1 ) % 4;
	$nx  = $fz[ $ni ];
	$Y   = $T[ $nx['f'] ] ?? array();
	$f0  = $z['urm'] - AH_SPP_FEREASTRA;
	$f1  = $z['urm'] + AH_SPP_FEREASTRA;
	$fer = $azi0 <= $f1; // fereastra iernii următoare încă nu a trecut
	$pana = 'toamna' === $z['f'] ? null : ah_spp_iso( $z['s0'] + $cur['b'] - 1 );
	if ( 0 === $ni ) {
		$urm = array( 'de_la' => $fer ? ah_spp_iso( $f0 ) : null, 'pana_la' => $fer ? ah_spp_iso( $f1 ) : null );
	} else {
		$urm = array( 'de_la' => ah_spp_iso( $z['s0'] + $nx['a'] - 1 ), 'pana_la' => null );
	}
	$inc = array();
	foreach ( $fz as $p ) {
		$d = $z['s0'] + $p['a'] - 1;
		if ( 'iarna' !== $p['f'] && $d > $azi0 && $d <= $azi0 + AH_SPP_URM_ZILE ) {
			$inc[] = array( 'faza' => $p['f'], 'data' => ah_spp_iso( $d ), 'pana' => null );
		}
	}
	if ( $fer && $f0 <= $azi0 + AH_SPP_URM_ZILE ) {
		$inc[] = array( 'faza' => 'iarna', 'data' => ah_spp_iso( $f0 ), 'pana' => ah_spp_iso( $f1 ) );
	}
	// doar ce arată pagina (fără ziua din ciclu, lungimea sau harta ciclului: decizia lui Mircea, 18 nov.)
	return $baza + array(
		'stare'       => 'ok',
		'faza'        => $z['f'],
		'pana_la'     => $pana,
		'urmatoarea'  => array( 'faza' => $nx['f'], 'de_la' => $urm['de_la'], 'pana_la' => $urm['pana_la'], 'sprijin' => (string) ( $Y['sprijin'][0] ?? '' ), 'arhetip' => (string) ( $Y['arhetip'] ?? '' ) ),
		'inceputuri'  => $inc,
		'texte'       => array(
			'ce'      => (string) ( $X['ce'] ?? '' ),
			'sprijin' => array_values( array_map( 'strval', (array) ( $X['sprijin'] ?? array() ) ) ),
			'citate'  => array_values( array_map( 'strval', (array) ( $X['citate'] ?? array() ) ) ),
			'arhetip' => (string) ( $X['arhetip'] ?? '' ),
			'luna'    => (string) ( $X['luna'] ?? '' ),
		),
	);
}

/** Semnul fazei (pentru a observa schimbarea fără să păstrăm date): faza + o amprentă a zilei ei de început. */
function ah_spp_semn_faza( $z, $secret = null ) {
	if ( ! $z ) {
		return '';
	}
	$inceput = ah_spp_iso( $z['s0'] + $z['fz'][ $z['fi'] ]['a'] - 1 );
	return $z['f'] . ':' . substr( hash_hmac( 'sha256', $z['f'] . '|' . $inceput, ( null === $secret ? ah_spp_secret() : (string) $secret ) . '|faza' ), 0, 16 );
}

/* ==================================================================
   4. RÂNDURILE (el)
   ================================================================== */

function ah_spp_rand( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE id = %d', (int) $id ), ARRAY_A );
}
/**
 * Rândul după tokenul din link: cel curent (token_h) sau cel cerut de el prin „Trimite-mi din nou linkul” (token_nou_h).
 * Prima folosire a linkului nou îl face curent și îl anulează pe cel vechi (așa, o cerere făcută de altcineva cu adresa
 * lui nu-i strică linkul pe care îl are deja). Sesiunile deschise nu se ating aici.
 */
function ah_spp_rand_token( $t ) {
	if ( ! ah_spp_token_valid( $t ) ) {
		return null;
	}
	global $wpdb;
	$h = hash( 'sha256', $t );
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE token_h = %s', $h ), ARRAY_A );
	if ( $r ) {
		return $r;
	}
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . " WHERE token_nou_h = %s AND token_nou_h <> ''", $h ), ARRAY_A );
	if ( ! $r ) {
		return null;
	}
	$wpdb->update( ah_spp_tabel(), array( 'token_h' => $h, 'token_nou_h' => '' ), array( 'id' => (int) $r['id'] ) );
	return ah_spp_rand( $r['id'] );
}
/** Partenerii ei (activi; cu $si_oprite și cei opriți). */
function ah_spp_ale_ei( $cheie, $si_oprite = false ) {
	global $wpdb;
	$q = 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE ea = %s' . ( $si_oprite ? '' : ' AND oprit IS NULL' ) . ' ORDER BY id ASC';
	$r = $wpdb->get_results( $wpdb->prepare( $q, $cheie ), ARRAY_A );
	return is_array( $r ) ? $r : array();
}
/** Rândul unui partener al ei (sau null dacă nu e al ei). */
function ah_spp_al_ei( $cheie, $id ) {
	$r = ah_spp_rand( $id );
	return ( $r && hash_equals( (string) $r['ea'], (string) $cheie ) ) ? $r : null;
}
/** Ce vede ea despre fiecare: prenumele, emailul (îl știe, l-a scris ea), dacă a intrat, ultima vizită. */
function ah_spp_pentru_ea( $r ) {
	return array(
		'id'     => (int) $r['id'],
		'nume'   => (string) $r['nume'],
		'email'  => (string) ah_spp_decripteaza( $r['email_c'] ),
		'stare'  => empty( $r['intrat'] ) ? 'invitat' : 'a_intrat',
		'vizita' => empty( $r['vizita'] ) ? '' : substr( (string) $r['vizita'], 0, 10 ),
		'creat'  => substr( (string) $r['creat'], 0, 10 ),
	);
}
/** Șterge rândurile date (și tokenurile push ale lor). */
function ah_spp_sterge_randuri( $ids ) {
	global $wpdb;
	$n = 0;
	foreach ( array_map( 'intval', (array) $ids ) as $id ) {
		$wpdb->delete( ah_spp_tabel_push(), array( 'partener' => $id ) );
		$n += (int) $wpdb->delete( ah_spp_tabel(), array( 'id' => $id ) );
	}
	return $n;
}
/** Tot ce ține de ea (contul sau ciclul ei au fost șterse). */
function ah_spp_sterge_ale_ei( $cheie ) {
	$ids = array_map( function ( $r ) { return (int) $r['id']; }, ah_spp_ale_ei( $cheie, true ) );
	$n   = ah_spp_sterge_randuri( $ids );
	if ( $n ) {
		ah_spp_jurnal( 'șterse ' . $n . ' accese (ciclul sau contul ei a fost șters)' );
	}
	return $n;
}
/** Oprește accesul, pe loc: tokenurile push și emailurile fazelor se opresc; el vede „oprit” până la curățenie (30 de zile). */
function ah_spp_opreste( $r ) {
	global $wpdb;
	$wpdb->update( ah_spp_tabel(), array( 'oprit' => ah_spp_acum(), 'email_faze' => 0 ), array( 'id' => (int) $r['id'] ) );
	$wpdb->delete( ah_spp_tabel_push(), array( 'partener' => (int) $r['id'] ) );
}

/* ---------- cookie-ul lui: ah_spp = id.expirare.semnătură (semnătura leagă și sesiunea rândului) ---------- */
function ah_spp_semn_cookie( $id, $exp, $ses, $cheie = null ) {
	return substr( hash_hmac( 'sha256', 'ah_spp|' . (int) $id . '|' . (int) $exp . '|' . (string) $ses, null === $cheie ? ah_spp_cheie( 'cookie' ) : $cheie ), 0, 40 );
}
function ah_spp_valoare_cookie( $r, $exp ) {
	return (int) $r['id'] . '.' . (int) $exp . '.' . ah_spp_semn_cookie( $r['id'], $exp, $r['ses'] );
}
function ah_spp_cookie_brut( $val, $exp ) {
	if ( ! headers_sent() ) {
		setcookie( 'ah_spp', $val, array( 'expires' => $exp, 'path' => '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	}
	if ( '' === $val ) {
		unset( $_COOKIE['ah_spp'] );
	} else {
		$_COOKIE['ah_spp'] = $val;
	}
}
function ah_spp_pune_cookie( $r ) {
	$exp = time() + YEAR_IN_SECONDS;
	ah_spp_cookie_brut( ah_spp_valoare_cookie( $r, $exp ), $exp );
}
function ah_spp_scoate_cookie() {
	ah_spp_cookie_brut( '', time() - YEAR_IN_SECONDS );
}
/** Rândul lui din cookie, sau null (semnătură greșită, expirat, rând șters). */
function ah_spp_din_cookie() {
	$v = isset( $_COOKIE['ah_spp'] ) ? (string) wp_unslash( $_COOKIE['ah_spp'] ) : '';
	if ( ! preg_match( '/^(\d{1,19})\.(\d{1,12})\.([a-f0-9]{40})$/D', $v, $m ) || (int) $m[2] < time() ) {
		return null;
	}
	$r = ah_spp_rand( (int) $m[1] );
	if ( ! $r ) {
		return null;
	}
	if ( hash_equals( ah_spp_semn_cookie( $m[1], $m[2], $r['ses'] ), $m[3] ) ) {
		return $r;
	}
	// tranziția de după migrarea cheii: semnătura veche e primită, iar cookie-ul se semnează din nou (aceeași expirare)
	$k = ah_spp_cheie_veche( 'cookie' );
	if ( $k && hash_equals( ah_spp_semn_cookie( $m[1], $m[2], $r['ses'], $k ), $m[3] ) ) {
		ah_spp_cookie_brut( ah_spp_valoare_cookie( $r, (int) $m[2] ), (int) $m[2] );
		return $r;
	}
	return null;
}
/** El, din cookie sau din ?t= (aplicația poate cere „azi” și cu tokenul). */
function ah_spp_el( $req = null ) {
	$r = ah_spp_din_cookie();
	if ( $r ) {
		return $r;
	}
	$t = $req ? $req->get_param( 't' ) : null;
	if ( is_string( $t ) && '' !== $t ) {
		if ( ! ah_spp_limita( 'tok|' . ah_spp_ip(), 30 ) ) {
			return null;
		}
		return ah_spp_rand_token( $t );
	}
	return null;
}
/** Notează vizita (cel mult o dată la 10 minute) și, prima dată, „a intrat” + semnul fazei (fără notificare pentru faza de acum). */
function ah_spp_noteaza_vizita( $r ) {
	global $wpdb;
	$acum = ah_spp_acum();
	$d    = array();
	if ( empty( $r['intrat'] ) ) {
		$d['intrat'] = $acum;
	}
	if ( empty( $r['vizita'] ) || strtotime( (string) $r['vizita'] ) < strtotime( $acum ) - 600 ) {
		$d['vizita'] = $acum;
	}
	if ( '' === (string) $r['faza_trimisa'] && empty( $r['oprit'] ) ) {
		$a0 = ah_spp_azi0();
		$c  = ah_spp_stare( $r, ah_spp_ciclul_ei( $r['ea'], $a0 ), $a0 );
		if ( 'ok' === $c['stare'] ) {
			$d['faza_trimisa'] = ah_spp_semn_faza( $c['z'] );
		}
	}
	if ( $d ) {
		$wpdb->update( ah_spp_tabel(), $d, array( 'id' => (int) $r['id'] ) );
	}
	if ( empty( $r['intrat'] ) && empty( $r['oprit'] ) ) {
		ah_spp_push_leaga( $r ); // invitat și de altă femeie: telefonul lui primește și notificările ei
	}
}

/* ==================================================================
   5. EMAILURILE (cadrul comun: ah_mail_trimite / ah_mail_p, fragmentul 89). Cât mai puține cuvinte, din cele de pe site.
   ================================================================== */

function ah_spp_p( $h, $x = '' ) {
	return function_exists( 'ah_mail_p' ) ? ah_mail_p( $h, $x ) : '<p style="margin:0 0 18px;' . $x . '">' . $h . '</p>';
}
function ah_spp_mail( $email, $subiect, $a ) {
	$h = array( 'From: Adela Haru <contact@adelaharu.ro>', 'Reply-To: Adela Haru <contact@adelaharu.ro>' );
	if ( function_exists( 'ah_mail_trimite' ) ) {
		return (bool) ah_mail_trimite( $email, $subiect, $a, $h );
	}
	$h[]  = 'Content-Type: text/html; charset=UTF-8';
	$html = '<html><body style="margin:0;padding:28px 16px;background:#FDFAF8;font-family:Georgia,serif;color:#4E3D34"><div style="max-width:520px;margin:0 auto"><h1 style="font-weight:normal">' . esc_html( $a['titlu'] ) . '</h1>'
		. $a['continut'] . ( ! empty( $a['buton'] ) ? '<p><a href="' . esc_url( $a['buton'][1] ) . '">' . esc_html( $a['buton'][0] ) . '</a></p>' : '' ) . ( $a['dupa_buton'] ?? '' )
		. '<p style="font-size:13px;color:#8A776B">' . $a['motiv'] . ( ! empty( $a['dezabonare'] ) ? '<br>' . $a['dezabonare'] : '' ) . '</p></div></body></html>';
	return (bool) wp_mail( $email, $subiect, $html, $h );
}
/** Sub fiecare email al lui: cine prelucrează datele și politica (GDPR art. 14; HARU TRAINING SRL, ca în subsolul site-ului). */
function ah_spp_operator() {
	return ' Datele tale sunt prelucrate de HARU TRAINING SRL, operatorul site-ului adelaharu.ro. <a href="' . esc_url( home_url( '/politica-de-confidentialitate/' ) ) . '" style="color:#8A776B">Politica de confidențialitate</a>';
}
function ah_spp_link( $tok ) {
	return add_query_arg( 't', $tok, home_url( AH_SPP_PAGINA ) );
}
function ah_spp_prenume( $s, $implicit ) {
	$s = trim( (string) $s );
	return '' === $s ? $implicit : $s;
}
/** Invitația (prima dată și la „Trimite din nou”). */
function ah_spp_mail_invitatie( $r, $tok ) {
	$em = ah_spp_decripteaza( $r['email_c'] );
	if ( ! $em ) {
		return false;
	}
	$ea   = esc_html( ah_spp_prenume( $r['nume_ea'], 'Ea' ) );
	$el   = esc_html( ah_spp_prenume( $r['nume'], '' ) );
	// rândul ei: textul pe care 235 îl punea în mesajul de WhatsApp al linkului „Pentru el”
	$corp = ah_spp_p( ( '' !== $el ? 'Bună, ' . $el . ',' : 'Bună,' ) )
		. ah_spp_p( $ea . ': „Ți-am pregătit ceva, cu textele Adelei Haru: aici vezi în ce anotimp al ciclului sunt și cum mă poți sprijini.”' );
	return ah_spp_mail( $em, $ea . ' te invită să-i cunoști ciclul', array(
		'titlu'    => 'Ciclul ei',
		'eticheta' => 'Pentru el',
		'ton'      => 'cald',
		'continut' => $corp,
		'buton'    => array( 'Deschide pagina ei', ah_spp_link( $tok ) ),
		'motiv'    => 'Primești acest mesaj pentru că ' . $ea . ' a scris adresa ta în Spațiul meu, pe adelaharu.ro.' . ah_spp_operator(),
	) );
}
/** Linkul nou, cerut de el. */
function ah_spp_mail_link( $r, $tok ) {
	$em = ah_spp_decripteaza( $r['email_c'] );
	if ( ! $em ) {
		return false;
	}
	$ea = esc_html( ah_spp_prenume( $r['nume_ea'], 'ea' ) );
	return ah_spp_mail( $em, 'Linkul tău nou: ciclul ei', array(
		'titlu'    => 'Linkul tău nou',
		'eticheta' => 'Pentru el',
		'ton'      => 'cald',
		'continut' => ah_spp_p( 'Ai cerut pe adelaharu.ro un link nou spre pagina lui ' . $ea . '.' ),
		'buton'    => array( 'Deschide pagina ei', ah_spp_link( $tok ) ),
		'motiv'    => 'Primești acest mesaj pentru că cineva a cerut pe adelaharu.ro un link nou pentru adresa ta. Dacă n-ai fost tu, îl poți ignora.' . ah_spp_operator(),
	) );
}
/** Linkul de oprire a emailurilor fazelor (din orice email). */
function ah_spp_link_stop( $id ) {
	return add_query_arg( 'fara-emailuri', (int) $id . '.' . substr( hash_hmac( 'sha256', 'stop|' . (int) $id, ah_spp_cheie( 'stop' ) ), 0, 24 ), home_url( AH_SPP_PAGINA ) );
}
function ah_spp_stop_valid( $v ) {
	if ( ! is_string( $v ) || ! preg_match( '/^(\d{1,19})\.([a-f0-9]{24})$/D', $v, $m ) ) {
		return 0;
	}
	if ( hash_equals( substr( hash_hmac( 'sha256', 'stop|' . (int) $m[1], ah_spp_cheie( 'stop' ) ), 0, 24 ), $m[2] ) ) {
		return (int) $m[1];
	}
	$k = ah_spp_cheie_veche( 'stop' ); // linkurile din emailurile trimise înainte de migrarea cheii, cât ține tranziția
	return $k && hash_equals( substr( hash_hmac( 'sha256', 'stop|' . (int) $m[1], $k ), 0, 24 ), $m[2] ) ? (int) $m[1] : 0;
}
/** Emailul de la începutul unei faze (doar dacă el a cerut). */
function ah_spp_mail_faza( $r, $f ) {
	$em = ah_spp_decripteaza( $r['email_c'] );
	if ( ! $em ) {
		return false;
	}
	$T    = ah_spp_texte_faze();
	$X    = $T[ $f ] ?? array();
	$N    = ah_spp_nume_faze();
	$m    = ah_spp_mesaj_faza( $f );
	$corp = ah_spp_p( '<strong>' . esc_html( $N[ $f ]['n'] ?? '' ) . '</strong>' . ( ! empty( $X['arhetip'] ) ? ' · ' . esc_html( $X['arhetip'] ) : '' ), 'margin-bottom:6px' );
	if ( ! empty( $X['ce'] ) ) {
		$corp .= ah_spp_p( esc_html( $X['ce'] ) );
	}
	if ( ! empty( $X['sprijin'] ) ) {
		$corp .= ah_spp_p( '<strong>Cum o poți sprijini</strong><br>' . implode( '<br>', array_map( function ( $s ) { return '· ' . esc_html( $s ); }, (array) $X['sprijin'] ) ) );
	}
	$corp .= ah_spp_p( esc_html( ah_spp_nota() ), 'font-size:15px;color:#8A776B;' );
	return ah_spp_mail( $em, ah_spp_subiect_faza(), array(
		'titlu'      => $m[0],
		'eticheta'   => 'Pentru el',
		'ton'        => 'cald',
		'continut'   => $corp,
		'buton'      => array( 'Vezi pagina ei', home_url( AH_SPP_PAGINA ) ),
		'motiv'      => 'Primești acest mesaj pentru că ai pornit emailurile fazelor pe adelaharu.ro.' . ah_spp_operator(),
		'dezabonare' => '<a href="' . esc_url( ah_spp_link_stop( $r['id'] ) ) . '" style="color:#8A776B">Oprește emailurile</a>',
	) );
}

/* ==================================================================
   6. NOTIFICĂRILE PUSH ALE LUI (FCM HTTP v1 prin funcțiile modulului aplicație; altfel doar jurnal)
   ================================================================== */

/** Validarea tokenului: aceeași regulă ca modulul aplicație, dacă e activ. */
function ah_spp_valideaza_push( $p ) {
	if ( function_exists( 'ah_spa_valideaza_push' ) ) {
		return ah_spa_valideaza_push( $p );
	}
	$p   = is_array( $p ) ? $p : array();
	$tok = isset( $p['token'] ) && is_string( $p['token'] ) ? trim( $p['token'] ) : '';
	if ( preg_match( '/^[0-9a-fA-F]{64}$/D', $tok ) ) {
		return array( 'ok' => false, 'motiv' => 'apns' );
	}
	if ( strlen( $tok ) < 32 || strlen( $tok ) > 4096 || ! preg_match( '/^[A-Za-z0-9_\-:.]+$/D', $tok ) ) {
		return array( 'ok' => false, 'motiv' => 'token' );
	}
	$pl = isset( $p['platforma'] ) ? strtolower( (string) $p['platforma'] ) : '';
	if ( ! in_array( $pl, array( 'ios', 'android' ), true ) ) {
		return array( 'ok' => false, 'motiv' => 'platforma' );
	}
	return array( 'ok' => true, 'date' => array( 'token' => $tok, 'platforma' => $pl ) );
}
/**
 * Trimite la tokenurile lui. Refolosește ah_spa_fcm_cont / ah_spa_fcm_acces / ah_spa_fcm_mesaj (NU ah_spa_trimite_push:
 * aceea șterge rânduri după id din tabelul ei). Întoarce câte au plecat; tokenurile pe care Firebase nu le mai cunoaște se șterg.
 */
function ah_spp_trimite_push( $randuri, $titlu, $text, $url ) {
	$randuri = array_values( (array) $randuri );
	if ( ! $randuri ) {
		return 0;
	}
	if ( ! function_exists( 'ah_spa_fcm_cont' ) || ! function_exists( 'ah_spa_fcm_acces' ) || ! function_exists( 'ah_spa_fcm_mesaj' ) ) {
		ah_spp_jurnal( 'push OPRIT (modulul aplicație nu e activ), ar fi plecat la ' . count( $randuri ) . ' telefoane: ' . $titlu );
		return 0;
	}
	$sa = ah_spa_fcm_cont();
	if ( ! $sa ) {
		ah_spp_jurnal( 'push OPRIT (fără cheia Firebase), ar fi plecat la ' . count( $randuri ) . ' telefoane: ' . $titlu );
		return 0;
	}
	$at = ah_spa_fcm_acces( $sa );
	if ( '' === $at ) {
		return 0;
	}
	global $wpdb;
	$adresa = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send';
	$ok     = 0;
	foreach ( $randuri as $r ) {
		$res = wp_remote_post( $adresa, array(
			'timeout' => 3,
			'headers' => array( 'Authorization' => 'Bearer ' . $at, 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( ah_spp_mesaj_push( $r['token'], $titlu, $text, $url ) ),
		) );
		$cod = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $cod ) {
			$ok++;
			continue;
		}
		$corp = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		if ( 404 === $cod || false !== strpos( $corp, 'UNREGISTERED' ) || ( 400 === $cod && false !== strpos( $corp, 'registration token' ) ) ) {
			$wpdb->delete( ah_spp_tabel_push(), array( 'token_h' => (string) $r['token_h'] ) ); // de pe toate invitațiile lui
		}
	}
	ah_spp_jurnal( 'push: ' . $titlu . ' — ' . $ok . ' din ' . count( $randuri ) );
	return $ok;
}
/**
 * Mesajul FCM al lui: cel al modulului aplicație (ah_spa_fcm_mesaj, aceleași culori și pictogramă), plus vizibilitatea PRIVATE
 * pe Android: pe ecranul blocat se vede doar că a venit o notificare de la aplicație, fără text (1.4.0). Textul e oricum neutru
 * („Ciclul ei” / „Începe o fază nouă…”): faza se vede doar în pagină.
 */
function ah_spp_mesaj_push( $token, $titlu, $text, $url ) {
	$m = (array) ah_spa_fcm_mesaj( $token, $titlu, $text, array( 'url' => $url, 'tip' => 'pentru-el' ) );
	if ( isset( $m['message'] ) && is_array( $m['message'] ) ) {
		$a = isset( $m['message']['android'] ) && is_array( $m['message']['android'] ) ? $m['message']['android'] : array();
		$n = isset( $a['notification'] ) && is_array( $a['notification'] ) ? $a['notification'] : array();
		$n['visibility']         = 'PRIVATE';
		$a['notification']       = $n;
		$m['message']['android'] = $a;
	}
	return $m;
}
function ah_spp_push_ale_lui( $id ) {
	global $wpdb;
	$r = $wpdb->get_results( $wpdb->prepare( 'SELECT id, token, token_h, platforma FROM ' . ah_spp_tabel_push() . ' WHERE partener = %d ORDER BY id ASC', (int) $id ), ARRAY_A );
	return is_array( $r ) ? $r : array();
}

/* ==================================================================
   7. FAZA NOUĂ → NOTIFICARE (cron zilnic + după ce ea își salvează ciclul) ȘI CURĂȚENIA
   ================================================================== */

/**
 * Verifică un partener: dacă faza ei s-a schimbat față de ultima notată și faza nouă a început azi sau ieri → push / email.
 * Prima verificare (sau o fază schimbată din urmă, prin corectarea datelor) doar notează, fără mesaj.
 * Semnul fazei noi se „ia” întâi (UPDATE condiționat de semnul vechi: două rulări simultane nu trimit de două ori), iar dacă
 * nimic n-a plecat (FCM fără jeton de acces, eroare de rețea, emailul n-a plecat), semnul vechi se pune la loc, ca rularea
 * următoare să încerce din nou (1.3.0; înainte, notificarea se pierdea). Cu cel puțin un canal reușit, semnul rămâne.
 * Întoarce 'push' | 'email' | 'push+email' | 'notat' | 'reincearca' | ''.
 */
function ah_spp_verifica_partener( $r, $c, $azi0 ) {
	global $wpdb;
	if ( ! empty( $r['oprit'] ) ) {
		return '';
	}
	if ( 'ok' !== $c['stare'] ) {
		if ( '' !== (string) $r['faza_trimisa'] ) {
			$wpdb->update( ah_spp_tabel(), array( 'faza_trimisa' => '' ), array( 'id' => (int) $r['id'] ) );
		}
		return '';
	}
	$z    = $c['z'];
	$semn = ah_spp_semn_faza( $z );
	$era  = (string) $r['faza_trimisa'];
	if ( $semn === $era ) {
		return '';
	}
	// tranziția cheii: semnul notat cu cheia veche, pentru aceeași fază → nimic (rămâne cum l-ar citi și 1.2; se rescrie la capăt)
	$v = ah_spp_secret_vechi();
	if ( null !== $v && '' !== $era && hash_equals( ah_spp_semn_faza( $z, $v ), $era ) ) {
		return '';
	}
	if ( ! $wpdb->update( ah_spp_tabel(), array( 'faza_trimisa' => $semn ), array( 'id' => (int) $r['id'], 'faza_trimisa' => $era ) ) ) {
		return ''; // altă rulare a luat-o înainte
	}
	$inceput = $z['s0'] + $z['fz'][ $z['fi'] ]['a'] - 1;
	if ( '' === $era || empty( $r['intrat'] ) || $inceput < $azi0 - 1 || $z['peste'] ) {
		return 'notat';
	}
	$m      = ah_spp_push_faza();
	$out    = array();
	$tok    = ah_spp_push_ale_lui( $r['id'] );
	$incerc = false;
	if ( $tok ) {
		$incerc = true;
		if ( ah_spp_trimite_push( $tok, $m[0], $m[1], home_url( AH_SPP_PAGINA ) ) > 0 ) {
			$out[] = 'push';
		}
	}
	if ( ! empty( $r['email_faze'] ) ) {
		$incerc = true;
		if ( ah_spp_mail_faza( $r, $z['f'] ) ) {
			$out[] = 'email';
		}
	}
	if ( $out ) {
		return implode( '+', $out );
	}
	if ( $incerc ) {
		$wpdb->update( ah_spp_tabel(), array( 'faza_trimisa' => $era ), array( 'id' => (int) $r['id'], 'faza_trimisa' => $semn ) );
		ah_spp_jurnal( 'faza nouă: nimic n-a plecat, se reîncearcă la rularea următoare' );
		return 'reincearca';
	}
	return 'notat';
}
/** Toți partenerii ei (după ce și-a salvat ciclul) sau toți partenerii activi (cronul zilnic), cu un buget de timp. */
function ah_spp_verifica_toti( $cheie = '' ) {
	global $wpdb;
	if ( ! ah_spp_gata() ) {
		return array();
	}
	$azi0  = ah_spp_azi0();
	$start = microtime( true );
	$dupa  = '' === $cheie ? (int) get_option( 'ah_spp_cursor', 0 ) : 0;
	$q     = '' === $cheie
		? $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE oprit IS NULL AND id > %d ORDER BY id ASC LIMIT 500', $dupa )
		: $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE oprit IS NULL AND ea = %s ORDER BY id ASC', $cheie );
	$rand  = (array) $wpdb->get_results( $q, ARRAY_A );
	$cache = array();
	$out   = array();
	foreach ( $rand as $r ) {
		if ( '' === $cheie && microtime( true ) - $start > 20 ) {
			update_option( 'ah_spp_cursor', (int) $r['id'] - 1, false );
			wp_schedule_single_event( time() + 60, 'ah_spp_zilnic' );
			return $out;
		}
		if ( ! isset( $cache[ $r['ea'] ] ) ) {
			$cache[ $r['ea'] ] = ah_spp_ciclul_ei( $r['ea'], $azi0 );
		}
		$out[ (int) $r['id'] ] = ah_spp_verifica_partener( $r, ah_spp_stare( $r, $cache[ $r['ea'] ], $azi0 ), $azi0 );
	}
	if ( '' === $cheie ) {
		update_option( 'ah_spp_cursor', count( $rand ) >= 500 ? (int) end( $rand )['id'] : 0, false );
		if ( count( $rand ) >= 500 ) {
			wp_schedule_single_event( time() + 60, 'ah_spp_zilnic' );
		}
	}
	return $out;
}
/**
 * Curățenia: rândurile oprite de peste 30 de zile; rândurile ei când ciclul ei nu mai e în cont (oricum ar fi fost șters);
 * tokenurile push nevăzute de 270 de zile. Întoarce câte rânduri de parteneri au plecat.
 */
function ah_spp_curata() {
	global $wpdb;
	if ( ! ah_spp_gata() ) {
		return 0;
	}
	$prag = wp_date( 'Y-m-d H:i:s', time() - AH_SPP_OPRIT_ZILE * DAY_IN_SECONDS );
	$ids  = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . ah_spp_tabel() . ' WHERE oprit IS NOT NULL AND oprit < %s', $prag ) );
	$n    = ah_spp_sterge_randuri( $ids );
	if ( function_exists( 'ah_sp_ciclu_db' ) ) {
		ah_sp_ciclu_db();
	}
	$tc   = function_exists( 'ah_sp_ciclu_tabel' ) ? ah_sp_ciclu_tabel() : $wpdb->prefix . 'ah_sp_ciclu';
	$fara = (array) $wpdb->get_col( 'SELECT p.id FROM ' . ah_spp_tabel() . ' p LEFT JOIN ' . $tc . ' c ON c.cheie = p.ea WHERE c.id IS NULL' );
	$n   += ah_spp_sterge_randuri( $fara );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ah_spp_tabel_push() . ' WHERE vazut < %s', wp_date( 'Y-m-d H:i:s', time() - 270 * DAY_IN_SECONDS ) ) );
	if ( $n ) {
		ah_spp_jurnal( 'curățenie: ' . $n . ' accese șterse' );
	}
	return $n;
}

/* ==================================================================
   8. REST ah-spp/v1
   ================================================================== */

function ah_spp_raspuns( $d, $cod = 200 ) {
	do_action( 'litespeed_control_set_nocache', 'ah spp' );
	$r = new WP_REST_Response( $d, $cod );
	$r->header( 'Cache-Control', 'no-store, private' );
	return $r;
}
/**
 * Apărare CSRF în plus față de SameSite=Lax: rutele care schimbă ceva cer antetul X-AH-SPP: 1. Un alt site nu-l poate trimite
 * fără o cerere CORS preliminară, pe care WordPress nu o aprobă (antetul nu e în rest_allowed_cors_headers); un formular HTML
 * nu poate pune antete. Paginile noastre îl trimit. POST /push/sterge și DELETE /push nu-l cer: șterg doar tokenul dat, pe care
 * un alt site nu-l știe (și îl trimite și puntea ei, din ah-sp-aplicatie.php).
 */
function ah_spp_antet_ok( $q ) {
	return is_object( $q ) && method_exists( $q, 'get_header' ) && '1' === (string) $q->get_header( 'x_ah_spp' );
}
function ah_spp_fara_antet() {
	return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'antet' ), 403 );
}

/** Prenume: text simplu, fără linkuri sau adrese (ajunge în emailuri). */
function ah_spp_curata_nume( $s ) {
	$s = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $s ) ) );
	if ( '' === $s || preg_match( '~(://|www\.|@|\.(ro|com|net|org|eu|info|io)\b|[<>{}\[\]])~i', $s ) ) {
		return '';
	}
	return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, 40 ) : substr( $s, 0, 40 );
}

/* ---------- ea (cookie ah_sp) ---------- */

/** GET /parteneri → lista ei + dacă ciclul e păstrat în cont. */
function ah_spp_r_parteneri( $q ) {
	$e = ah_sp_email();
	if ( ! $e ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	$k = ah_spp_ea( $e );
	return ah_spp_raspuns( array(
		'ok'        => true,
		'ciclu'     => (bool) ah_sp_ciclu_rand( $e ),
		'max'       => AH_SPP_MAX,
		'parteneri' => array_map( 'ah_spp_pentru_ea', ah_spp_ale_ei( $k ) ),
	) );
}
/**
 * Adresa lui, strict (1.4.0): sanitize_email scoate pe tăcute diacriticele, spațiile și orice nu e ASCII, deci
 * „andrei.ștefan@gmail.com” ar fi devenit „andrei.tefan@gmail.com”, adresa altcuiva. Acum adresa trebuie să fie validă așa cum
 * a fost scrisă (doar litere mici și fără spațiile de la capete). Întoarce adresa, 'caractere' sau ''.
 */
function ah_spp_email_strict( $s ) {
	$e = strtolower( trim( is_string( $s ) ? $s : '' ) );
	if ( '' === $e ) {
		return '';
	}
	if ( preg_match( '/[^\x21-\x7E]/', $e ) ) {
		return 'caractere';
	}
	return ( is_email( $e ) && sanitize_email( $e ) === $e ) ? $e : '';
}
function ah_spp_mesaj_email( $v, $al_lui ) {
	return 'caractere' === $v ? 'Adresa conține caractere nepermise (diacritice sau spații).' : ( $al_lui ? 'Scrie, te rog, adresa lui de email întreagă.' : 'Scrie, te rog, adresa de email întreagă.' );
}
/**
 * Lacătul invitațiilor ei (MySQL GET_LOCK, 3 secunde): verificarea limitei AH_SPP_MAX și inserarea se fac sub el, așa că două
 * cereri trimise deodată (dublu clic, două telefoane) nu trec amândouă de limită. Numele: cel mult 64 de caractere în MySQL.
 */
function ah_spp_lacat( $k, $ia = true ) {
	global $wpdb;
	$n = 'ah_spp_' . substr( (string) $k, 0, 40 );
	if ( $ia ) {
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $n ) );
	}
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $n ) );
	return true;
}
/** POST /invita {nume, email, nume_ea, acord:true} */
function ah_spp_r_invita( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	global $wpdb;
	$e = ah_sp_email();
	if ( ! $e ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	$p     = (array) $q->get_json_params();
	$nume  = ah_spp_curata_nume( $p['nume'] ?? '' );
	$el    = ah_spp_email_strict( $p['email'] ?? '' );
	$numea = ah_spp_curata_nume( $p['nume_ea'] ?? '' );
	if ( true !== ( $p['acord'] ?? null ) && 1 !== ( $p['acord'] ?? null ) && '1' !== ( $p['acord'] ?? null ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'acord', 'mesaj' => 'Bifează acordul, ca să putem trimite invitația.' ), 400 );
	}
	if ( '' === $nume ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'nume', 'mesaj' => 'Scrie prenumele lui, fără linkuri sau adrese.' ), 400 );
	}
	if ( '' === $el || 'caractere' === $el ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'email', 'mesaj' => ah_spp_mesaj_email( $el, true ) ), 400 );
	}
	if ( $el === ah_spp_norm( $e ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'email', 'mesaj' => 'Adresa lui trebuie să fie alta decât a ta.' ), 400 );
	}
	if ( ! ah_sp_ciclu_rand( $e ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'ciclu', 'mesaj' => 'Păstrează întâi ciclul în contul tău.' ), 409 );
	}
	$k = ah_spp_ea( $e );
	if ( ! ah_spp_lacat( $k ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'ocupat', 'mesaj' => 'N-a mers. Mai încearcă o dată.' ), 503 );
	}
	$activ = ah_spp_ale_ei( $k );
	$h     = ah_spp_hash_email( $el );
	$am    = ah_spp_amprente( $el );
	foreach ( $activ as $r ) {
		if ( in_array( (string) $r['email_h'], $am, true ) ) {
			ah_spp_lacat( $k, false );
			return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'exista', 'mesaj' => 'L-ai invitat deja. Poți trimite din nou invitația din listă.' ), 409 );
		}
	}
	if ( count( $activ ) >= AH_SPP_MAX ) {
		ah_spp_lacat( $k, false );
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'max', 'mesaj' => 'Poți invita cel mult ' . AH_SPP_MAX . ' persoane. Oprește un acces ca să inviți pe altcineva.' ), 409 );
	}
	if ( ! ah_spp_limita( 'invita|' . $k, 6, DAY_IN_SECONDS ) || ! ah_spp_limita( 'invita-el|' . $h, 3, DAY_IN_SECONDS ) ) {
		ah_spp_lacat( $k, false );
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'prea des', 'mesaj' => 'Prea multe invitații azi. Mai încearcă mâine.' ), 429 );
	}
	list( $tok, $th ) = ah_spp_token_nou();
	$acum = ah_spp_acum();
	$wpdb->insert( ah_spp_tabel(), array(
		'ea'      => $k,
		'nume'    => $nume,
		'nume_ea' => $numea,
		'email_c' => ah_spp_cripteaza( $el ),
		'email_h' => $h,
		'token_h' => $th,
		'ses'     => bin2hex( random_bytes( 16 ) ),
		'acord'   => $acum,
		'acord_v' => AH_SPP_ACORD_V,
		'creat'   => $acum,
		'invitat' => $acum,
	) );
	$id = (int) $wpdb->insert_id;
	ah_spp_lacat( $k, false );
	$r    = $id ? ah_spp_rand( $id ) : null;
	$mail = $r ? ah_spp_mail_invitatie( $r, $tok ) : false;
	ah_spp_jurnal( 'invitație nouă' . ( $mail ? '' : ' (emailul NU a plecat)' ) );
	return ah_spp_raspuns( array( 'ok' => (bool) $r, 'email' => $mail, 'parteneri' => array_map( 'ah_spp_pentru_ea', ah_spp_ale_ei( $k ) ) ) );
}
/** POST /opreste {id} → pe loc. */
function ah_spp_r_opreste( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	$e = ah_sp_email();
	if ( ! $e ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	$k = ah_spp_ea( $e );
	$r = ah_spp_al_ei( $k, (int) ( $q->get_json_params()['id'] ?? 0 ) );
	if ( ! $r ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'nu' ), 404 );
	}
	if ( empty( $r['oprit'] ) ) {
		ah_spp_opreste( $r );
	}
	return ah_spp_raspuns( array( 'ok' => true, 'parteneri' => array_map( 'ah_spp_pentru_ea', ah_spp_ale_ei( $k ) ) ) );
}
/**
 * POST /retrimite {id} → token nou și sesiune nouă (linkul vechi și toate cookie-urile ah_spp deja date pentru el nu mai
 * merg, pe orice telefon sau browser), invitația din nou. Cel mult 3 pe zi pentru un partener. Dacă emailul nu pleacă, nimic
 * nu se schimbă (1.4.0; înainte, tokenul și sesiunea se schimbau oricum: el rămânea fără link și deconectat).
 */
function ah_spp_r_retrimite( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	global $wpdb;
	$e = ah_sp_email();
	if ( ! $e ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	$k = ah_spp_ea( $e );
	$r = ah_spp_al_ei( $k, (int) ( $q->get_json_params()['id'] ?? 0 ) );
	if ( ! $r || ! empty( $r['oprit'] ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'nu' ), 404 );
	}
	if ( ! ah_spp_limita( 'retrimite|' . $r['id'], 3, DAY_IN_SECONDS ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'prea des', 'mesaj' => 'Ai trimis deja invitația de câteva ori azi. Mai încearcă mâine.' ), 429 );
	}
	// întâi emailul, cu tokenul nou; rândul se schimbă doar dacă a plecat (altfel el ar rămâne fără niciun link valabil)
	list( $tok, $th ) = ah_spp_token_nou();
	$mail = ah_spp_mail_invitatie( $r, $tok );
	if ( $mail ) {
		$wpdb->update( ah_spp_tabel(), array( 'token_h' => $th, 'token_nou_h' => '', 'ses' => bin2hex( random_bytes( 16 ) ), 'invitat' => ah_spp_acum() ), array( 'id' => (int) $r['id'] ) );
	} else {
		ah_spp_jurnal( 'invitația retrimisă: emailul NU a plecat, linkul vechi rămâne' );
	}
	return ah_spp_raspuns( array( 'ok' => true, 'email' => $mail, 'parteneri' => array_map( 'ah_spp_pentru_ea', ah_spp_ale_ei( $k ) ) ) );
}

/* ---------- el (cookie ah_spp) ---------- */

/** GET /azi (cookie ah_spp sau ?t=). */
function ah_spp_r_azi( $q ) {
	$r = ah_spp_el( $q );
	if ( ! $r ) {
		return ah_spp_raspuns( array( 'ok' => false, 'stare' => 'iesit' ), 401 );
	}
	ah_spp_noteaza_vizita( $r );
	return ah_spp_raspuns( array( 'ok' => true ) + ah_spp_azi_pentru( $r ) + array( 'setari' => ah_spp_setari_lui( $r ) ) );
}
function ah_spp_setari_lui( $r ) {
	return array( 'email_faze' => ! empty( $r['email_faze'] ), 'push' => count( ah_spp_push_ale_lui( $r['id'] ) ) > 0 );
}
/**
 * Rulează $f după ce răspunsul a plecat spre browser (LiteSpeed / PHP-FPM), ca timpul răspunsului să nu spună dacă adresa
 * e invitată. Unde serverul nu poate închide cererea mai devreme, rulează pe loc; ah_spp_r_trimite_link egalizează atunci
 * timpul (AH_SPP_LINK_MS).
 */
function ah_spp_dupa_raspuns( $f ) {
	$inchide = function_exists( 'litespeed_finish_request' ) ? 'litespeed_finish_request' : ( function_exists( 'fastcgi_finish_request' ) ? 'fastcgi_finish_request' : '' );
	if ( '' === $inchide ) {
		call_user_func( $f );
		return false;
	}
	add_action( 'shutdown', function () use ( $f, $inchide ) {
		call_user_func( $inchide );
		call_user_func( $f );
	}, 999 );
	return true;
}
/**
 * POST /trimite-link {email} → același răspuns pentru orice adresă, în același timp. Dacă e invitat (activ), un link nou
 * (token_nou_h), trimis după răspuns. Linkul pe care îl are deja rămâne valabil până folosește linkul nou, deci altcineva
 * care știe adresa lui nu i-l poate strica. Limite: 5 pe oră de pe un IP, 3 pe zi pentru aceeași adresă (tăcut).
 */
function ah_spp_r_trimite_link( $q ) {
	global $wpdb;
	$start   = microtime( true );
	$raspuns = array( 'ok' => true, 'mesaj' => 'Dacă adresa a fost invitată, primești în câteva minute un email cu un link nou. Uită-te și în Promoții sau Spam.' );
	$p       = (array) $q->get_json_params();
	if ( ! empty( $p['site'] ) ) {
		return ah_spp_raspuns( $raspuns ); // capcana pentru roboți
	}
	$el = ah_spp_email_strict( $p['email'] ?? '' );
	if ( '' === $el || 'caractere' === $el ) {
		return ah_spp_raspuns( array( 'ok' => false, 'mesaj' => ah_spp_mesaj_email( $el, false ) ), 400 );
	}
	if ( ! ah_spp_limita( 'link-ip|' . ah_spp_ip(), 5 ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'prea des', 'mesaj' => 'Prea multe cereri. Mai încearcă peste o oră.' ), 429 );
	}
	$h = ah_spp_hash_email( $el );
	if ( ! ah_spp_limita( 'link-el|' . $h, 3, DAY_IN_SECONDS ) ) {
		return ah_spp_raspuns( $raspuns );
	}
	$am     = ah_spp_amprente( $el );
	$rand   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ah_spp_tabel() . ' WHERE email_h IN (' . implode( ',', array_fill( 0, count( $am ), '%s' ) ) . ') AND oprit IS NULL ORDER BY id ASC LIMIT 3', ...$am ), ARRAY_A );
	$trimis = array();
	foreach ( $rand as $r ) {
		list( $tok, $th ) = ah_spp_token_nou();
		$wpdb->update( ah_spp_tabel(), array( 'token_nou_h' => $th ), array( 'id' => (int) $r['id'] ) );
		$trimis[] = array( (int) $r['id'], $tok );
	}
	$dupa = $trimis ? ah_spp_dupa_raspuns( function () use ( $trimis ) {
		foreach ( $trimis as $x ) {
			ah_spp_mail_link( ah_spp_rand( $x[0] ), $x[1] );
		}
	} ) : function_exists( 'litespeed_finish_request' ) || function_exists( 'fastcgi_finish_request' );
	if ( ! $dupa ) { // emailul a plecat pe loc: toate răspunsurile durează cel puțin AH_SPP_LINK_MS
		$rest = (int) AH_SPP_LINK_MS * 1000 - (int) ( ( microtime( true ) - $start ) * 1e6 );
		if ( $rest > 0 ) {
			usleep( $rest );
		}
	}
	return ah_spp_raspuns( $raspuns );
}
/**
 * Rândurile active ale aceluiași bărbat (aceeași adresă, invitat de una sau de două femei), cu $r primul. Tokenul telefonului
 * lui stă lângă fiecare (1.4.0): altfel, deschizând pagina celei de-a doua, telefonul ar fi trecut de la prima la ea și prima
 * n-ar mai fi ajuns la el. Pagina lui arată mereu a cui pagină e deschisă (nume_ea).
 */
function ah_spp_randuri_lui( $r ) {
	global $wpdb;
	$ids = array( (int) $r['id'] );
	$em  = ah_spp_decripteaza( $r['email_c'] );
	if ( null === $em ) {
		return $ids;
	}
	$am = ah_spp_amprente( $em );
	$l  = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . ah_spp_tabel() . ' WHERE email_h IN (' . implode( ',', array_fill( 0, count( $am ), '%s' ) ) . ') AND oprit IS NULL ORDER BY id ASC', ...$am ) );
	foreach ( $l as $id ) {
		if ( ! in_array( (int) $id, $ids, true ) ) {
			$ids[] = (int) $id;
		}
	}
	return $ids;
}
/** Pune tokenul lângă fiecare rând dat (sau îl actualizează) și păstrează cel mult AH_SPP_PUSH_MAX telefoane pe rând. */
function ah_spp_push_pune( $ids, $tok, $th, $pl, $vazut = null ) {
	global $wpdb;
	$acum = ah_spp_acum();
	foreach ( array_map( 'intval', (array) $ids ) as $id ) {
		$ex = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . ah_spp_tabel_push() . ' WHERE partener = %d AND token_h = %s', $id, $th ), ARRAY_A );
		if ( $ex ) {
			if ( null === $vazut ) {
				$wpdb->update( ah_spp_tabel_push(), array( 'platforma' => $pl, 'vazut' => $acum ), array( 'id' => (int) $ex['id'] ) );
			}
		} else {
			$wpdb->insert( ah_spp_tabel_push(), array( 'partener' => $id, 'token' => $tok, 'token_h' => $th, 'platforma' => $pl, 'creat' => $acum, 'vazut' => null === $vazut ? $acum : $vazut ) );
		}
		$toate = ah_spp_push_ale_lui( $id );
		foreach ( array_slice( $toate, 0, max( 0, count( $toate ) - AH_SPP_PUSH_MAX ) ) as $vechi ) {
			$wpdb->delete( ah_spp_tabel_push(), array( 'id' => (int) $vechi['id'] ) );
		}
	}
}
/** La prima intrare pe o invitație nouă: telefoanele lui de pe celelalte invitații active ajung și lângă ea. */
function ah_spp_push_leaga( $r ) {
	global $wpdb;
	$ids = ah_spp_randuri_lui( $r );
	if ( count( $ids ) < 2 ) {
		return;
	}
	$l = (array) $wpdb->get_results( 'SELECT token, token_h, platforma, vazut FROM ' . ah_spp_tabel_push() . ' WHERE partener IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') ORDER BY id ASC', ARRAY_A );
	$v = array();
	foreach ( $l as $x ) {
		$v[ $x['token_h'] ] = $x;
	}
	foreach ( $v as $x ) {
		ah_spp_push_pune( $ids, $x['token'], $x['token_h'], $x['platforma'], $x['vazut'] );
	}
}
/** POST /push {token, platforma} (el); POST /push/sterge sau DELETE /push {token} (fără cont: cine are tokenul e telefonul acela). */
function ah_spp_r_push( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	global $wpdb;
	$r = ah_spp_din_cookie();
	if ( ! $r || ! empty( $r['oprit'] ) ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	// Pe un telefon pe care e intrată și ea (cookie-ul ah_sp), aplicația deschide contul ei: notificările telefonului sunt ale ei
	// (puntea modulului aplicație le leagă de adresa ei). Pagina lui nu oferă atunci notificări; aici e plasa de siguranță.
	if ( function_exists( 'ah_sp_email' ) && '' !== (string) ah_sp_email() ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'cont_ea' ), 409 );
	}
	$v = ah_spp_valideaza_push( $q->get_json_params() );
	if ( ! $v['ok'] ) {
		return ah_spp_raspuns( $v, 400 );
	}
	if ( ! ah_spp_limita( 'push|' . $r['id'], 30 ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'prea des' ), 429 );
	}
	$x   = $v['date'];
	$th  = hash( 'sha256', $x['token'] );
	$ids = ah_spp_randuri_lui( $r );
	// un token (un telefon) aparține unui singur cont: dacă era al altui bărbat (alt cont pe același telefon), pleacă de acolo…
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ah_spp_tabel_push() . ' WHERE token_h = %s AND partener NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')', $th ) );
	// …iar dacă era legat de contul ei (wp_ah_spa_push), pleacă și de acolo
	if ( function_exists( 'ah_spa_tabel' ) ) {
		$wpdb->delete( ah_spa_tabel(), array( 'token_h' => $th ) );
	}
	ah_spp_push_pune( $ids, $x['token'], $th, $x['platforma'] );
	return ah_spp_raspuns( array( 'ok' => true ) );
}
/** Scoate tokenul dat de pe toate invitațiile lui (Ieși, Șterge accesul meu, comutatorul oprit). */
function ah_spp_r_push_sterge( $q ) {
	global $wpdb;
	$t = (string) ( $q->get_json_params()['token'] ?? '' );
	if ( '' !== $t ) {
		$wpdb->delete( ah_spp_tabel_push(), array( 'token_h' => hash( 'sha256', $t ) ) );
	}
	return ah_spp_raspuns( array( 'ok' => true ) );
}
/** POST /email {on} → emailurile la începutul fazelor. */
function ah_spp_r_email( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	global $wpdb;
	$r = ah_spp_din_cookie();
	if ( ! $r || ! empty( $r['oprit'] ) ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	$on = ! empty( $q->get_json_params()['on'] );
	$wpdb->update( ah_spp_tabel(), array( 'email_faze' => $on ? 1 : 0 ), array( 'id' => (int) $r['id'] ) );
	return ah_spp_raspuns( array( 'ok' => true, 'email_faze' => $on ) );
}
/** POST /iesi → cookie-ul pleacă (tokenul push îl scoate pagina, cu DELETE /push). */
function ah_spp_r_iesi( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	ah_spp_scoate_cookie();
	return ah_spp_raspuns( array( 'ok' => true ) );
}
/** POST /sterge {confirmare:true} → rândul lui și tokenurile push, pe loc. */
function ah_spp_r_sterge( $q ) {
	if ( ! ah_spp_antet_ok( $q ) ) {
		return ah_spp_fara_antet();
	}
	$r = ah_spp_din_cookie();
	if ( ! $r ) {
		return ah_spp_raspuns( array( 'ok' => false ), 403 );
	}
	if ( empty( $q->get_json_params()['confirmare'] ) ) {
		return ah_spp_raspuns( array( 'ok' => false, 'motiv' => 'confirmare' ), 400 );
	}
	global $wpdb;
	foreach ( ah_spp_push_ale_lui( $r['id'] ) as $x ) { // telefoanele lui pleacă și de pe celelalte invitații
		$wpdb->delete( ah_spp_tabel_push(), array( 'token_h' => (string) $x['token_h'] ) );
	}
	ah_spp_sterge_randuri( array( (int) $r['id'] ) );
	ah_spp_scoate_cookie();
	ah_spp_jurnal( 'un partener și-a șters accesul' );
	return ah_spp_raspuns( array( 'ok' => true ) );
}

/* ==================================================================
   9. PAGINA LUI (/pentru-el/) ȘI PARTEA EI DIN SPAȚIUL MEU
   ================================================================== */

function ah_spp_e_pagina_el() {
	$p = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
	return rtrim( strtolower( $p ), '/' ) === rtrim( AH_SPP_PAGINA, '/' );
}
function ah_spp_in_aplicatie() {
	return function_exists( 'ah_spa_e_aplicatie' ) && ah_spa_e_aplicatie();
}
/** În aplicație, pe /spatiul-meu/ fără contul ei dar cu al lui → adresa paginii lui (aplicația pornește mereu din Spațiul meu). */
function ah_spp_redirect_aplicatie() {
	$sp = defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/';
	$p  = rtrim( strtolower( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH ) ), '/' ) . '/';
	if ( $p === $sp && ah_spp_in_aplicatie() && ! ah_sp_email() && empty( $_GET['cont'] ) && empty( $_GET['intra'] ) && ah_spp_din_cookie() ) { // phpcs:ignore
		return home_url( AH_SPP_PAGINA );
	}
	return '';
}
/**
 * Cererea spre /pentru-el/: ?t= → cookie + adresa curată (array( 'redirect' => … )); ?fara-emailuri= → emailurile fazelor
 * oprite; altfel pagina (array( 'mesaj' => '' | 'link' | 'fara_emailuri' | 'oprit' )). 'oprit': linkul unei invitații oprite,
 * deschis pe un telefon cu o sesiune activă (de exemplu, invitația altei femei): sesiunea rămâne.
 */
function ah_spp_proceseaza_el() {
	if ( isset( $_GET['t'] ) ) { // phpcs:ignore
		$t = (string) wp_unslash( $_GET['t'] ); // phpcs:ignore
		$r = ah_spp_limita( 'tok|' . ah_spp_ip(), 30 ) ? ah_spp_rand_token( $t ) : null;
		// linkul unei invitații oprite nu ia locul unei sesiuni active (alt rând): rămâne pagina deschisă, cu un rând scurt
		$c = $r && ! empty( $r['oprit'] ) ? ah_spp_din_cookie() : null;
		if ( $c && empty( $c['oprit'] ) && (int) $c['id'] !== (int) $r['id'] ) {
			return array( 'mesaj' => 'oprit' );
		}
		if ( $r ) {
			ah_spp_pune_cookie( $r );
			if ( empty( $r['oprit'] ) ) {
				ah_spp_noteaza_vizita( $r );
			}
			return array( 'redirect' => home_url( AH_SPP_PAGINA ) );
		}
		return array( 'mesaj' => 'link' );
	}
	if ( isset( $_GET['fara-emailuri'] ) ) { // phpcs:ignore
		$id = ah_spp_stop_valid( (string) wp_unslash( $_GET['fara-emailuri'] ) ); // phpcs:ignore
		if ( $id && ah_spp_rand( $id ) ) {
			global $wpdb;
			$wpdb->update( ah_spp_tabel(), array( 'email_faze' => 0 ), array( 'id' => $id ) );
		}
		return array( 'mesaj' => $id ? 'fara_emailuri' : 'link' );
	}
	return array( 'mesaj' => '' );
}
function ah_spp_template_redirect() {
	if ( ! ah_spp_gata() ) {
		return;
	}
	$u = ah_spp_redirect_aplicatie();
	if ( '' !== $u ) {
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'ah spp' );
		wp_safe_redirect( $u, 302 );
		exit;
	}
	if ( ! ah_spp_e_pagina_el() ) {
		return;
	}
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'ah spp' );
	$x = ah_spp_proceseaza_el();
	if ( ! empty( $x['redirect'] ) ) {
		wp_safe_redirect( $x['redirect'], 302 );
		exit;
	}
	status_header( 200 );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Referrer-Policy: no-referrer' );
	// statistici (modulul aplicație, dacă e activ): doar un număr pe zi și platformă, fără nimic despre el sau despre ea
	if ( function_exists( 'ah_spa_stat_inc' ) && function_exists( 'ah_spp_din_cookie' ) && ah_spp_din_cookie() ) {
		ah_spa_stat_inc( 'el_deschidere', function_exists( 'ah_spa_platforma' ) ? ah_spa_platforma() : '' );
	}
	echo ah_spp_pagina_el( ah_spp_cfg_el( $x['mesaj'] ) ); // phpcs:ignore
	exit;
}
/** Configurația paginii lui (fără emailul lui; „azi” deja calculat, ca pagina să apară fără așteptare). */
function ah_spp_cfg_el( $mesaj = '' ) {
	$app = ah_spp_in_aplicatie();
	$r   = ah_spp_din_cookie();
	$azi = null;
	if ( $r ) {
		ah_spp_noteaza_vizita( $r );
		$azi = array( 'ok' => true ) + ah_spp_azi_pentru( $r ) + array( 'setari' => ah_spp_setari_lui( $r ) );
	}
	return array(
		'api'     => esc_url_raw( rest_url( AH_SPP_NS . '/' ) ),
		'app'     => $app,
		'cont_ea' => $app && '' !== (string) ah_sp_email(), // în aplicație, telefonul e al ei: fără notificări pentru el aici
		'azi'     => $azi,
		'mesaj'   => $mesaj,
		'faze'    => ah_spp_nume_faze(),
		'invata'  => ah_spp_invata( $app ),
		'nota'    => ah_spp_nota(),
		// rândul despre cărți al paginii ?ciclul-ei (235); în aplicație nu (aplicația nu vinde nimic)
		'carti'   => $app ? array() : array(
			array( 'Magia Ciclului Feminin', esc_url_raw( home_url( '/product/magia-ciclului-feminin/' ) ) ),
			array( 'Relațiile conștiente', esc_url_raw( home_url( '/product/relatiile-constiente/' ) ) ),
		),
		'gdpr'    => esc_url_raw( home_url( '/politica-de-confidentialitate/' ) ),
		'sp'      => esc_url_raw( home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' ) ),
		'acasa'   => esc_url_raw( home_url( AH_SPP_PAGINA ) ),
		'icon'    => function_exists( 'get_site_icon_url' ) ? (string) get_site_icon_url( 180 ) : '',
	);
}

/** Pagina lui: o aplicație mică, în stilul paginii ?ciclul-ei din 235 (Gilda Display, Cormorant, Figtree, piersică). */
function ah_spp_pagina_el( $cfg ) {
	$css = <<<'AHSPPCSS'
:root{--ink:#4E3D34;--ink2:#6E5246;--soft:#7A5F51;--line:#EADFE6;--acc:#964E46;--red:#C13C30;--ok:#56704D;--err:#8A4430;
 --card:rgba(255,255,255,.85);--card2:rgba(255,255,255,.7);--plin:#fff;--margine:#fff;--camp:#fff;--bara:rgba(251,246,243,.94);--bara-l:rgba(78,61,52,.08);
 --umbra:0 22px 40px -32px rgba(110,70,110,.6);--fundal-c:#FBF6F3;
 --fundal:radial-gradient(90% 50% at 100% 0%,rgba(214,194,238,.55),transparent 70%),radial-gradient(70% 40% at 0% 100%,rgba(250,214,222,.5),transparent 70%),linear-gradient(170deg,#FBF6F3,#F3EAF2);
 --sus-bg:radial-gradient(80% 90% at 100% 0%,rgba(205,184,236,.6),transparent 70%),linear-gradient(150deg,#FBEEF2,#F1E4F4);
 --disp:"Gilda Display",Georgia,serif;--quote:"Cormorant Garamond",Georgia,serif;--text:"Figtree",system-ui,-apple-system,sans-serif;
 --sus:env(safe-area-inset-top,0px);--jos:env(safe-area-inset-bottom,0px);--st:env(safe-area-inset-left,0px);--dr:env(safe-area-inset-right,0px);
 color-scheme:light}
/* seara: aceleași tonuri ca „Aspect de seară” din Spațiul meu (266); urmează telefonul, iar alegerea ei din Spațiul meu
   (ahspx_seara, pe același telefon) are întâietate: html.seara / html.zi */
@media (prefers-color-scheme:dark){:root:not(.zi){--ink:#F6ECE6;--ink2:#E0D2CB;--soft:#B8A7A5;--line:rgba(255,255,255,.16);--acc:#EDB7A4;--ok:#A9CBA6;--err:#F2B7A0;
 --card:rgba(255,255,255,.06);--card2:rgba(255,255,255,.05);--plin:#2C212A;--margine:rgba(255,255,255,.1);--camp:rgba(255,255,255,.08);--bara:rgba(34,25,34,.95);--bara-l:rgba(255,255,255,.1);
 --umbra:none;--fundal-c:#221922;
 --fundal:radial-gradient(90% 50% at 100% 0%,rgba(120,92,165,.34),transparent 70%),radial-gradient(70% 40% at 0% 100%,rgba(150,70,92,.26),transparent 70%),linear-gradient(170deg,#221922,#2A1D27);
 --sus-bg:radial-gradient(80% 90% at 100% 0%,rgba(120,92,165,.3),transparent 70%),linear-gradient(150deg,#31242E,#2A1F2C);color-scheme:dark}}
:root.seara{--ink:#F6ECE6;--ink2:#E0D2CB;--soft:#B8A7A5;--line:rgba(255,255,255,.16);--acc:#EDB7A4;--ok:#A9CBA6;--err:#F2B7A0;
 --card:rgba(255,255,255,.06);--card2:rgba(255,255,255,.05);--plin:#2C212A;--margine:rgba(255,255,255,.1);--camp:rgba(255,255,255,.08);--bara:rgba(34,25,34,.95);--bara-l:rgba(255,255,255,.1);
 --umbra:none;--fundal-c:#221922;
 --fundal:radial-gradient(90% 50% at 100% 0%,rgba(120,92,165,.34),transparent 70%),radial-gradient(70% 40% at 0% 100%,rgba(150,70,92,.26),transparent 70%),linear-gradient(170deg,#221922,#2A1D27);
 --sus-bg:radial-gradient(80% 90% at 100% 0%,rgba(120,92,165,.3),transparent 70%),linear-gradient(150deg,#31242E,#2A1F2C);color-scheme:dark}
/* culoarea fazei: --c (zi) și --c2 (seară, mai deschisă, contrast AA pe fundalul închis), alese în --k */
*{--k:var(--c,var(--acc))}
@media (prefers-color-scheme:dark){:root:not(.zi) *{--k:var(--c2,var(--c,var(--acc)))}}
:root.seara *{--k:var(--c2,var(--c,var(--acc)))}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;background:var(--fundal-c)}
body{margin:0;min-height:100vh;min-height:100dvh;color:var(--ink2);font:16.5px/1.6 var(--text);-webkit-font-smoothing:antialiased;background:var(--fundal) fixed;background-color:var(--fundal-c)}
button{font:inherit;cursor:pointer}
a{color:var(--acc)}
:focus-visible{outline:2px solid var(--acc);outline-offset:2px}
/* zonele sigure, o singură dată: sus/jos în #spp și în .bara; stânga/dreapta (telefonul culcat) tot acolo */
#spp{max-width:620px;margin:0 auto;padding:calc(10px + var(--sus)) calc(16px + var(--dr)) calc(96px + var(--jos)) calc(16px + var(--st))}
.cap{display:flex;justify-content:space-between;align-items:center;gap:12px;min-height:52px}
.cap .marca{font:400 23px var(--disp);color:var(--ink);text-decoration:none}
.cap .marca small{display:block;font:italic 400 15px/1 var(--quote);color:var(--acc);margin-top:2px}
.cap .set{width:46px;height:46px;border-radius:50%;border:1px solid var(--margine);background:var(--card);display:grid;place-items:center;color:var(--ink)}
.ic{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.ecran{display:grid;grid-template-columns:minmax(0,1fr);gap:18px;animation:sppIn .3s ease} /* minmax: la zoom 200% coloana nu crește după conținut */
@keyframes sppIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.ecran,.foaie .c{animation:none}}
.card,section{background:var(--card);border:1px solid var(--margine);border-radius:22px;padding:20px;box-shadow:var(--umbra)}
.sus{display:grid;gap:4px;text-align:center;justify-items:center;background:var(--sus-bg)}
.et{margin:0;font:500 12.5px var(--text);letter-spacing:.15em;text-transform:uppercase;color:var(--soft)}
h1{margin:0;font:400 30px/1.2 var(--disp);color:var(--ink);overflow-wrap:anywhere}
h1:focus{outline:none}
@media (max-width:330px){h1,.pag h1{font-size:23px}h2{font-size:19px}.card,section{padding:16px}} /* zoom 200%: cuvintele lungi încap întregi */
h1:focus-visible{outline:2px solid var(--acc);outline-offset:4px}
main{display:block}
.sus h1{color:var(--k)}
.arh{margin:0;font:italic 400 22px var(--quote);color:var(--ink)}
h2{margin:0 0 10px;font:400 22px/1.25 var(--disp);color:var(--ink)}
h3{margin:14px 0 6px;font:400 19px var(--disp);color:var(--ink)}
section p,.card p{margin:0 0 8px}
section p:last-child,.card p:last-child{margin-bottom:0}
ul{margin:0;padding-left:20px;display:grid;gap:8px}
li::marker{color:var(--k)}
.citat blockquote{margin:10px 0 0;padding-left:14px;border-left:3px solid var(--k);font:italic 400 20px/1.45 var(--quote);color:var(--ink)}
.mic{font-size:15px;color:var(--soft);margin:4px 0 0}
.nota{font-size:14.5px;color:var(--soft);margin:2px 4px}
.pt{width:14px;height:14px;border-radius:50%;background:var(--k);flex:none}
.urm-tot{display:grid;gap:12px;border-left:4px solid var(--k);padding-left:14px;margin-bottom:14px}
.urm-tot p{margin:0}
.urm-tot b{font:400 24px/1.25 var(--disp);color:var(--k)}
.luni{display:grid;gap:8px;list-style:none;margin:0;padding:0}
.luni .r{display:flex;flex-wrap:wrap;gap:4px 12px;align-items:center;padding:10px 12px;border-radius:14px;background:var(--card2);border:1px solid var(--margine)}
.luni .r b{font:400 17px var(--disp);color:var(--ink);flex:1}
.luni .r span{font-size:14.5px;color:var(--soft);font-variant-numeric:tabular-nums;text-align:right}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:50px;padding:0 22px;border:0;border-radius:999px;background:var(--red);color:#fff;font:500 16px var(--text);text-decoration:none;max-width:100%;text-align:center}
.btn.lite{background:var(--camp);color:var(--ink);border:1px solid var(--line)}
.btn[disabled]{opacity:.6}
/* ținte de atingere de cel puțin 44 px: legăturile-buton și linkurile din rânduri */
.leg{display:inline-flex;align-items:center;background:none;border:0;padding:0 2px;color:var(--acc);text-decoration:underline;text-underline-offset:3px;font-size:15px;min-height:44px}
.mic a,.nota a,.pod a,.leg-r{display:inline-flex;align-items:center;min-height:44px}
.but{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
input[type=email],input[type=url],input[type=text]{width:100%;min-height:52px;border:1px solid var(--line);border-radius:14px;padding:0 14px;font:17px var(--text);color:var(--ink);background:var(--camp)}
input[aria-invalid=true]{border-color:var(--red)}
label.l{display:block;font-size:15px;font-weight:500;color:var(--ink2)}
label.l input{margin-top:6px}
.msg{min-height:1.3em;color:var(--err);font-size:15px;margin:0}
.msg.ok{color:var(--ok)}
.msg:empty{display:none}
#spp form{display:grid;gap:12px;justify-items:start}
#spp form label.l{justify-self:stretch}
.ecran>.msg{margin:6px 4px 0}
.lista{display:grid;grid-template-columns:minmax(0,1fr);gap:10px}
.lista a,.lista button.rand{display:flex;gap:14px;align-items:center;width:100%;text-align:left;padding:16px;border-radius:18px;background:var(--card);border:1px solid var(--margine);color:inherit;text-decoration:none;box-shadow:var(--umbra)}
.lista .t{flex:1;min-width:0}
.lista b{display:block;font:400 19px/1.25 var(--disp);color:var(--ink)}
.lista .t span{display:block;font-size:14.5px;color:var(--soft)}
.lista .dot{width:12px;height:40px;border-radius:999px;background:var(--k);flex:none;opacity:.85}
.inapoi{display:inline-flex;align-items:center;gap:6px;min-height:44px;color:var(--acc);background:none;border:0;padding:0;font-size:15px}
.pag h1{font-size:28px;color:var(--k)}
.pag section h2{margin:14px 0 6px;font:400 19px var(--disp)}
.intra h1{margin-top:14px}
.pod{display:grid;gap:4px;padding:14px 16px;border-radius:16px;background:var(--card2);border:1px solid var(--margine);margin-top:12px}
.pod b{font:400 18px var(--disp);color:var(--ink)}
.pod a{font-size:15px}
.sw{display:flex;gap:14px;align-items:center;justify-content:space-between;width:100%;padding:16px;border-radius:18px;background:var(--card);border:1px solid var(--margine);text-align:left;color:inherit}
.sw .t{flex:1}
.sw b{display:block;font:400 19px var(--disp);color:var(--ink)}
.sw .t span{display:block;font-size:14.5px;color:var(--soft)}
.sw i{flex:none;position:relative;width:48px;height:28px;border-radius:999px;background:var(--line);transition:background .2s}
.sw i::after{content:"";position:absolute;left:3px;top:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(60,30,40,.35);transition:transform .2s}
.sw[aria-checked=true] i{background:var(--acc)}
.sw[aria-checked=true] i::after{transform:translateX(20px)}
.anunt{display:grid;gap:10px;background:var(--plin)}
.anunt .mic{margin:0}
.bara{position:fixed;left:0;right:0;bottom:0;z-index:20;display:flex;justify-content:center;gap:6px;padding:8px calc(16px + var(--dr)) calc(8px + var(--jos)) calc(16px + var(--st));background:var(--bara);border-top:1px solid var(--bara-l);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.bara a{flex:1;max-width:200px;display:grid;justify-items:center;gap:2px;min-height:54px;padding-top:6px;color:var(--soft);text-decoration:none;font:500 13px var(--text);border-radius:14px}
.bara a[aria-current=page]{color:var(--acc)}
.bara a[aria-current=page] .ic{stroke-width:2}
.gol{text-align:center;display:grid;gap:10px;justify-items:center}
.spp-app .spp-carte{display:none!important}
/* foaia de confirmare (în loc de window.confirm) */
.foaie{position:fixed;inset:0;z-index:40;display:flex;align-items:flex-end;justify-content:center;background:rgba(40,24,32,.45)}
.foaie .c{width:100%;max-width:520px;display:grid;gap:12px;background:var(--plin);color:var(--ink2);border-radius:24px 24px 0 0;padding:22px calc(20px + var(--dr)) calc(20px + var(--jos)) calc(20px + var(--st));box-shadow:0 -20px 50px -20px rgba(40,20,30,.5);animation:sppSus .25s ease}
@keyframes sppSus{from{transform:translateY(24px);opacity:0}to{transform:none;opacity:1}}
.foaie h2{margin:0}
.foaie p{margin:0}
/* butoanele foii: unul sub altul, pe toată lățimea (fără rânduri rupte la 390 px; la zoom 200% textul se poate rupe) */
.foaie .but,.but.col{flex-direction:column;align-items:stretch}
.foaie .but .btn,.but.col .btn{width:100%;white-space:nowrap}
.but.col .leg{align-self:center}
@media (max-width:330px){.foaie .but .btn,.but.col .btn{white-space:normal;padding:8px 16px}}
/* în aplicație: fără cereri spre Google, deci fără Cormorant; citatele în Figtree cursiv, subțire */
.spp-app{--quote:var(--text)}
.spp-app .cap .marca small{font:italic 400 13px/1 var(--text);letter-spacing:.02em}
.spp-app .arh{font:italic 300 19px/1.4 var(--text)}
.spp-app .citat blockquote{font:italic 300 18px/1.55 var(--text)}
/* bara sus doar pe ecrane mari (telefonul culcat rămâne cu bara jos) */
@media (min-width:700px) and (min-height:600px){.bara{top:0;bottom:auto;padding-top:calc(8px + var(--sus));padding-bottom:8px;border-top:0;border-bottom:1px solid var(--bara-l)}#spp{padding-top:calc(76px + var(--sus));padding-bottom:calc(40px + var(--jos))}}
AHSPPCSS;
	$js = <<<'AHSPPJS'
(function(){
var D=window.AHSPP||{},R=document.getElementById('spp'),F=D.faze||{},A=D.azi,ORD=['iarna','primavara','vara','toamna'];
var LUNI=['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
var P={azi:'M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4M12 8a4 4 0 1 0 0 8a4 4 0 0 0 0-8z',
 invata:'M4 5h11a3 3 0 0 1 3 3v12H7a3 3 0 0 1-3-3zM18 8h2v12M8 9h6M8 13h6',
 set:'M12 15a3 3 0 1 0 0-6a3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
 inapoi:'M15 6l-6 6 6 6',sageata:'M9 6l6 6-6 6'};
function cul(o){return o?'--c:'+esc(o.c)+(o.c2?';--c2:'+esc(o.c2):''):'';}
function ic(n){return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="'+P[n]+'"/></svg>';}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function data(iso){if(!iso)return '';var p=String(iso).split('-');return (+p[2])+' '+LUNI[(+p[1])-1];}
/* fereastra iernii următoare: „16–20 noiembrie” / „30 noiembrie – 3 decembrie”; cu „între”: „între 16 și 20 noiembrie” */
function interval(a,b,intre){var x=String(a).split('-'),y=String(b).split('-'),aceeasi=x[1]===y[1];
 if(intre)return 'între '+(aceeasi?(+x[2]):data(a))+' și '+data(b);return (aceeasi?(+x[2])+'–':data(a)+' – ')+data(b);}
function ls(k,v){try{if(v===undefined){var x=localStorage.getItem(k);return x?JSON.parse(x):null;}if(v===null)localStorage.removeItem(k);else localStorage.setItem(k,JSON.stringify(v));}catch(e){return null;}}
function cere(u,o){o=o||{};var h={'Accept':'application/json'};if(o.body){h['Content-Type']='application/json';}if((o.method||'GET')!=='GET')h['X-AH-SPP']='1';return fetch(D.api+u,{method:o.method||'GET',credentials:'same-origin',cache:'no-store',headers:h,body:o.body?JSON.stringify(o.body):undefined}).then(function(r){return r.json().catch(function(){return {ok:false};}).then(function(j){j=j||{};j._cod=r.status;return j;});}).catch(function(){return {ok:false,mesaj:'Nu e internet acum. Încearcă din nou.'};});}
var CAP=window.Capacitor,APP=!!(D.app&&CAP&&CAP.Plugins);
function plugin(n){try{return CAP&&CAP.Plugins&&CAP.Plugins[n]||null;}catch(e){return null;}}
function chem(n,m,a){var p=plugin(n);if(!p||typeof p[m]!=='function')return Promise.reject(new Error('lipsă '+n+'.'+m));try{return Promise.resolve(p[m](a));}catch(e){return Promise.reject(e);}}
/* Notificările lui (doar în aplicație). Aceleași reguli ca puntea ei (ah-sp-aplicatie.php): doar cu AhApp.info() push:true și
   token:'fcm' (fără Firebase, register() oprește aplicația pe Android; pe iPhone fără Firebase tokenul e APNs, inutil serverului)
   și doar dacă pe telefon nu e intrată și ea (D.cont_ea: aplicația deschide atunci contul ei, iar telefonul îi aparține ei).
   Scoaterea tokenului: POST /push/sterge (unele firewalluri ale gazdei blochează DELETE).
   Tokenul telefonului stă în Preferences.ah_push = {token, cont:'el'|'ea', …}, aceeași cheie ca la ea: un telefon = un cont. */
var PUSHOK=false,PUSHLOC=false;
var PREF={ia:function(k){return chem('Preferences','get',{key:k}).then(function(r){if(!r||r.value==null)return null;try{return JSON.parse(r.value);}catch(e){return r.value;}}).catch(function(){return null;});},
 pune:function(k,v){return chem('Preferences','set',{key:k,value:JSON.stringify(v)}).catch(function(){});},
 scoate:function(k){return chem('Preferences','remove',{key:k}).catch(function(){});}};
function pushDisponibil(){if(!APP||D.cont_ea||!plugin('PushNotifications'))return Promise.resolve(false);
 return chem('AhApp','info').then(function(r){return !!(r&&r.push===true&&(r.token==null||r.token==='fcm'));}).catch(function(){return false;});}
if(D.app)document.documentElement.classList.add('spp-app');
/* adresa curată: fără ?t= (un link expirat sau oprit) sau ?fara-emailuri= în bară și în istoric */
if(location.search&&window.history&&history.replaceState){try{history.replaceState(null,'',location.pathname+location.hash);}catch(e){}}

/* ---------- capul și bara ---------- */
function cap(){return '<header class="cap"><a class="marca" href="#azi">Ciclul ei<small>Adela Haru</small></a>'+(A&&A.ok?'<button type="button" class="set" id="spp-set" aria-label="Setări">'+ic('set')+'</button>':'')+'</header>';}
function bara(h){if(!(A&&A.ok))return '';var t=function(id,n,i){return '<a href="#'+id+'"'+(h===id?' aria-current="page"':'')+'>'+ic(i)+n+'</a>';};return '<nav class="bara" aria-label="Ciclul ei">'+t('azi','Azi','azi')+t('invata','Învață','invata')+'</nav>';}

/* ---------- Azi ---------- */
function anunt(){var s=A.setari||{};if(s.push||PUSHLOC||s.email_faze||ls('spp_anunt_nu'))return '';
 var pushOk=PUSHOK;
 return '<section class="anunt" id="spp-anunt"><h2>Vrei să afli când începe o fază nouă?</h2>'+(pushOk?'':'<p class="mic">Primești un email la începutul fiecărei faze.</p>')+'<div class="but"><button class="btn" type="button" data-a="'+(pushOk?'push':'email')+'">Da, anunță-mă</button><button class="leg" type="button" data-a="nu">Nu acum</button></div><p class="msg" id="spp-anunt-m" aria-live="polite"></p></section>';}
/* pauza: mesajul neutru și, dedesubt, drumul spre Învață (titlul primei pagini, de pe site) */
/* linkul unei invitații oprite, deschis cu o sesiune activă: pagina rămâne, cu un rând scurt (o singură dată) */
function sus(){return D.mesaj==='oprit'?'<p class="msg" role="status">Linkul acesta nu mai e activ.</p>':'';}
function pauza(){var L=D.invata||[],p0=L[0];
 return '<div class="ecran">'+sus()+'<div class="card gol sus" style="--c:#7A5F51;--c2:#B8A7A5"><p class="et">'+esc(A.nume_ea||'Ea')+'</p><h1>'+esc(A.mesaj)+'</h1>'+(A.sub?'<p>'+esc(A.sub)+'</p>':'')+'</div>'+
  (p0?'<div class="lista"><a href="#invata" style="'+cul(p0)+'"><span class="dot"></span><span class="t"><b>Învață</b><span>'+esc(p0.titlu)+'</span></span>'+ic('sageata')+'</a></div>':'')+
  '<p class="nota">'+esc(D.nota||'')+'</p></div>';}
function azi(){
 if(A.stare==='oprit')return '<div class="ecran"><div class="card gol"><p class="et">Ciclul ei</p><h1>'+esc(A.mesaj)+'</h1><p>'+esc(A.sub)+'</p><button class="btn lite" type="button" data-a="sterge">Șterge accesul meu</button></div></div>';
 if(A.stare!=='ok')return pauza();
 var X=A.texte||{},C=F[A.faza],U=A.urmatoarea||{},N=F[U.faza]||{};
 /* până când: o dată în jur; toamna, până la următoarea menstruație (fără dată: nu se schimbă în ziua estimată) */
 var pana=A.pana_la?'până în jur de '+esc(data(A.pana_la)):(A.faza==='toamna'?'până la următoarea menstruație':'');
 /* „Ce urmează”: faza următoare, mare (iarna: o fereastră, nu o zi), apoi începuturile până la iarna următoare */
 var cand=U.de_la&&U.pana_la?', '+esc(interval(U.de_la,U.pana_la,true)):(U.de_la?', din jurul datei de '+esc(data(U.de_la)):'');
 var luni=(A.inceputuri||[]).filter(function(x,i){return !(i===0&&x.faza===U.faza);}).map(function(x){return '<li class="r" style="'+cul(F[x.faza])+'"><span class="pt"></span><b>'+esc(F[x.faza].n)+'</b><span>'+esc(x.pana?interval(x.data,x.pana):data(x.data))+'</span></li>';}).join('');
 return '<div class="ecran">'+sus()+
  '<div class="card sus" style="'+cul(C)+'"><p class="et">Azi, '+esc(A.nume_ea||'ea')+' e în</p><h1>'+esc(C.n)+'</h1><p class="arh">'+esc(X.arhetip||'')+(X.luna?' · '+esc(X.luna):'')+'</p>'+(pana?'<p class="mic">'+pana+'</p>':'')+'</div>'+
  anunt()+
  (X.ce?'<section style="'+cul(C)+'"><h2>Ce trăiește acum</h2><p>'+esc(X.ce)+'</p></section>':'')+
  ((X.sprijin||[]).length?'<section style="'+cul(C)+'"><h2>Cum o poți sprijini</h2><ul>'+X.sprijin.map(function(s){return '<li>'+esc(s)+'</li>';}).join('')+'</ul></section>':'')+
  ((X.citate||[]).length?'<section class="citat" style="'+cul(C)+'"><p class="et">Ce le spune Adela femeilor, în Magia Ciclului Feminin</p>'+X.citate.map(function(s){return '<blockquote>'+esc(s)+'</blockquote>';}).join('')+'</section>':'')+
  '<section style="'+cul(N)+'"><h2>Ce urmează</h2><div class="urm-tot"><p><b>'+esc(N.n||'')+'</b>'+(U.arhetip?' · '+esc(U.arhetip):'')+cand+'.</p>'+(U.sprijin?'<p class="mic">'+esc(U.sprijin)+'</p>':'')+'</div>'+
   (luni?'<ul class="luni">'+luni+'</ul>':'')+'</section>'+
  '<p class="nota">'+esc(D.nota||'')+'</p>'+
 '</div>';}

/* ---------- Învață ---------- */
function invata(id){var L=D.invata||[];
 var K=D.carti||[];
 if(!id)return '<div class="ecran"><div style="margin-top:14px"><h1>Învață</h1></div><div class="lista">'+L.map(function(p){return '<a href="#invata/'+esc(p.id)+'" style="'+cul(p)+'"><span class="dot"></span><span class="t"><b>'+esc(p.titlu)+'</b><span>'+esc(p.sub)+'</span></span>'+ic('sageata')+'</a>';}).join('')+'</div>'+
  '<p class="nota">'+esc(D.nota||'')+'</p>'+
  (K.length?'<p class="nota spp-carte">Vrei să înțelegi mai mult? '+K.map(function(c){return '<a class="spp-carte" href="'+esc(c[1])+'">'+esc(c[0])+'</a>';}).join(' și ')+', cărțile Adelei Haru.</p>':'')+'</div>';
 var p=L.filter(function(x){return x.id===id;})[0];if(!p)return invata('');
 var b=p.b.map(function(x){
  if(x.t==='p')return '<p>'+esc(x.text)+'</p>';
  if(x.t==='h')return '<h2>'+esc(x.text)+'</h2>';
  if(x.t==='mic')return '<p class="mic" style="margin:-4px 0 6px">'+esc(x.text)+'</p>';
  if(x.t==='ul')return '<ul>'+x.l.map(function(s){return '<li>'+esc(s)+'</li>';}).join('')+'</ul>';
  if(x.t==='citat')return '<div class="citat"><p class="et" style="margin-top:12px">'+esc(x.sursa)+'</p>'+x.l.map(function(s){return '<blockquote>'+esc(s)+'</blockquote>';}).join('')+'</div>';
  if(x.t==='podcast')return '<div class="pod"><p class="et">Podcastul Intim Feminin</p><b>'+esc(x.titlu)+'</b><span class="mic">'+esc(x.meta)+'</span><p>'+esc(x.text)+'</p>'+(x.url?'<a href="'+esc(x.url)+'" target="_blank" rel="noopener" aria-label="Ascultă „'+esc(x.titlu)+'” în Apple Podcasts">Ascultă episodul</a>':'')+'</div>';
  return '';}).join('');
 return '<div class="ecran pag" style="'+cul(p)+'"><button class="inapoi" type="button" data-a="inapoi-invata" aria-label="Înapoi la Învață">'+ic('inapoi')+'Învață</button><section><p class="et">'+esc(p.sub)+'</p><h1 style="margin:4px 0 12px">'+esc(p.titlu)+'</h1>'+b+'</section></div>';}

/* ---------- Setări ---------- */
function setari(){var s=A.setari||{},pushOk=PUSHOK;
 return '<div class="ecran"><button class="inapoi" type="button" data-a="inapoi">'+ic('inapoi')+'Înapoi</button><div><p class="et">Setări</p><h1 style="margin-top:4px">'+(A.nume?'Bună, '+esc(A.nume):'Setări')+'</h1></div>'+
  '<div class="lista">'+
  (pushOk?'<button class="sw" type="button" role="switch" id="spp-sw-push" aria-checked="'+(PUSHLOC?'true':'false')+'"><span class="t"><b>Notificări</b></span><i aria-hidden="true"></i></button>':'')+
  (A.stare!=='oprit'?'<button class="sw" type="button" role="switch" id="spp-sw-email" aria-checked="'+(s.email_faze?'true':'false')+'"><span class="t"><b>Emailurile fazelor</b></span><i aria-hidden="true"></i></button>':'')+
  '</div><p class="msg" id="spp-set-m" aria-live="polite"></p>'+
  (D.app&&D.cont_ea?'<p class="mic">Pe acest telefon e deschis contul ei, așa că notificările ajung la ea. <a href="'+esc(D.sp)+'">Spațiul meu</a></p>':'')+
  '<div class="but col"><button class="btn lite" type="button" data-a="iesi">'+(D.app?'Ieși din cont pe acest telefon':'Ieși din cont')+'</button><button class="leg" type="button" data-a="sterge">Șterge accesul meu</button></div>'+
  '<p class="mic"><a href="'+esc(D.gdpr)+'">Politica de confidențialitate</a></p></div>';}

/* ---------- fără cont ---------- */
function intrare(){var m=D.mesaj==='link'?'<p class="msg" role="alert">Linkul acesta nu mai e valabil. Cere unul nou mai jos.</p>':(D.mesaj==='fara_emailuri'?'<p class="msg ok" role="status">Gata, nu mai primești emailurile fazelor.</p>':'');
 /* titlul ecranului (h1, cititorul de ecran îl caută); „Deschide linkul” e drumul principal, „Trimite-mi linkul” al doilea */
 return '<div class="ecran intra"><h1>Intră în pagina ei</h1>'+m+
  '<section><h2>Deschide linkul primit pe email</h2>'+
  '<form id="spp-lp" novalidate><label class="l">Linkul copiat din email<input type="url" id="spp-lpi" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="https://www.adelaharu.ro/pentru-el/?t=…"></label><p class="msg" id="spp-lpm" aria-live="polite"></p><button class="btn" id="spp-lpb">Deschide linkul</button></form></section>'+
  '<section><h2>Trimite-mi din nou linkul</h2><form id="spp-f" novalidate><label class="l">Adresa ta de email<input type="email" id="spp-e" autocomplete="email" inputmode="email" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="numele@exemplu.ro"></label><input type="text" id="spp-s" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><p class="msg" id="spp-m" aria-live="polite"></p><button class="btn lite" id="spp-b">Trimite-mi linkul</button></form></section>'+
  (D.app?'<p class="nota"><a href="'+esc(D.sp)+'">Înapoi la Spațiul meu</a></p>':'')+'</div>';}

/* ---------- foaia de confirmare (în loc de window.confirm: se vede în aplicație, are „Renunț”) ---------- */
var FOAIE=null;
function inchideFoaie(){if(!FOAIE)return false;var f=FOAIE;FOAIE=null;f.el.remove();if(f.inapoi&&f.inapoi.focus)f.inapoi.focus();return true;}
function foaie(titlu,text,actiune,cb){inchideFoaie();var el=document.createElement('div');el.className='foaie';
 el.innerHTML='<div class="c" role="dialog" aria-modal="true" aria-labelledby="spp-foaie-t"><h2 id="spp-foaie-t">'+esc(titlu)+'</h2>'+(text?'<p>'+esc(text)+'</p>':'')+'<div class="but"><button class="btn" type="button" data-f="da">'+esc(actiune)+'</button><button class="btn lite" type="button" data-f="nu">Renunț</button></div></div>';
 FOAIE={el:el,inapoi:document.activeElement};document.body.appendChild(el);
 el.addEventListener('click',function(e){if(e.target===el){inchideFoaie();return;}var b=e.target.closest('[data-f]');if(!b)return;var da=b.getAttribute('data-f')==='da';inchideFoaie();if(da)cb();});
 el.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();inchideFoaie();}else if(e.key==='Tab'){var l=el.querySelectorAll('button'),p=l[0],u=l[l.length-1];if(e.shiftKey&&document.activeElement===p){e.preventDefault();u.focus();}else if(!e.shiftKey&&document.activeElement===u){e.preventDefault();p.focus();}}});
 setTimeout(function(){var b=el.querySelector('[data-f="nu"]');if(b)b.focus();},30);}

/* ---------- desenarea ---------- */
function hash(){return (location.hash||'').slice(1);}
var TITLU='Ciclul ei · Adela Haru';
function arata(){var h=hash(),corp,tab='azi',t='';
 if(!A||!A.ok){R.innerHTML=cap()+'<main>'+intrare()+'</main>';document.title=TITLU;lega();return;}
 if(h==='setari'){corp=setari();tab='';t='Setări';}
 else if(h.indexOf('invata')===0){var id=h.split('/')[1]||'',p=(D.invata||[]).filter(function(x){return x.id===id;})[0];corp=invata(id);tab='invata';t=p?p.titlu:'Învață';}
 else{corp=azi();t='Azi';}
 R.innerHTML=cap()+'<main>'+corp+'</main>'+bara(tab);document.title=t+' · '+TITLU;lega();}
/* după schimbarea ecranului (Azi / Învață / o pagină / Setări): focus pe titlul noului ecran, fără derulare */
function laTitlu(){var t=R.querySelector('main h1');if(!t)return;t.setAttribute('tabindex','-1');try{t.focus({preventScroll:true});}catch(e){t.focus();}}
function reincarca(){return cere('azi').then(function(j){if(j&&j.ok){A=j;}else if(j&&j._cod===401){A=null;}arata();});}
function mergiAzi(){if(hash()!=='azi'&&hash()!=='')location.hash='#azi';else arata();window.scrollTo(0,0);}

/* ---------- notificările (doar în aplicație) ---------- */
var regLegat=false,regGata=null;
function regFinal(ok){var f=regGata;regGata=null;if(f)f(ok);}
function legaReg(){if(regLegat)return;regLegat=true;var PN=plugin('PushNotifications');if(!PN)return;
 try{PN.addListener('registration',function(x){primit(x&&x.value).then(regFinal);});PN.addListener('registrationError',function(){regFinal(false);});}catch(e){}}
/* tokenul primit: doar FCM ajunge la server (APNs brut, 64 de cifre hexa: nu); apoi telefonul e notat ca al lui */
function primit(tok){tok=String(tok||'');if(!tok||/^[0-9a-fA-F]{64}$/.test(tok))return Promise.resolve(false);
 var pl=CAP&&CAP.getPlatform?CAP.getPlatform():'';
 return cere('push',{method:'POST',body:{token:tok,platforma:pl}}).then(function(j){if(!(j&&j.ok))return false;ls('spp_push',tok);PUSHLOC=true;return PREF.pune('ah_push',{token:tok,cont:'el',la:Date.now()}).then(function(){return true;});});}
function pornestePush(){if(!PUSHOK)return Promise.resolve(false);
 return chem('PushNotifications','checkPermissions').then(function(p){return p&&p.receive==='granted'?p:chem('PushNotifications','requestPermissions');}).then(function(p){
  if(!p||p.receive!=='granted')return false;legaReg();
  return new Promise(function(gata){var t=setTimeout(function(){regFinal(false);},15000);regGata=function(ok){clearTimeout(t);gata(ok);};chem('PushNotifications','register').catch(function(){regFinal(false);});});
 }).catch(function(){return false;});}
/* „Ieși”, „Șterge accesul meu”, comutatorul oprit: scoate DOAR tokenul lui. Dacă între timp telefonul a trecut la contul ei
   (ah_push.cont = 'ea'), tokenul ei nu se atinge (nici unregister()). */
function opresteePush(){return PREF.ia('ah_push').then(function(v){var al=!!(v&&v.cont==='el'&&v.token),t=al?v.token:ls('spp_push');ls('spp_push',null);PUSHLOC=false;
 var p=[];if(al)p.push(PREF.scoate('ah_push'));if(t)p.push(cere('push/sterge',{method:'POST',body:{token:t}}));
 return Promise.all(p).then(function(){if(al&&PUSHOK)return chem('PushNotifications','unregister').catch(function(){});});});}
if(APP){var PN0=plugin('PushNotifications');if(PN0){try{PN0.addListener('pushNotificationActionPerformed',function(a){var u=a&&a.notification&&a.notification.data&&a.notification.data.url;if(!u||u.indexOf(location.origin+'/')!==0)return;
   var x;try{x=new URL(u);}catch(e){return;}if(x.pathname===location.pathname&&!x.search){mergiAzi();if(A&&A.ok)reincarca();}else location.href=u;});}catch(e){}}
 var AP=plugin('App');if(AP){try{AP.addListener('appUrlOpen',function(e){var u=e&&e.url;if(u&&u.indexOf(location.origin+'/')===0)location.href=u;});}catch(e){}}
 /* la deschidere: se poate cere push pe acest telefon? e deja al lui? (tokenul se retrimite o dată pe săptămână, ca la ea) */
 pushDisponibil().then(function(ok){PUSHOK=ok;return ok?PREF.ia('ah_push'):null;}).then(function(v){PUSHLOC=!!(v&&v.cont==='el'&&v.token);
  if(PUSHLOC&&A&&A.ok&&A.stare!=='oprit'&&Date.now()-(+v.la||0)>7*864e5){chem('PushNotifications','checkPermissions').then(function(p){if(p&&p.receive==='granted'){legaReg();return chem('PushNotifications','register');}}).catch(function(){});}
  if(PUSHOK&&A&&A.ok)arata();});}

/* ---------- acțiunile ---------- */
/* linkul lipit (sau doar tokenul): îl deschidem aici, în aceeași pagină (în aplicație rămâne în aplicație) */
function tokDin(s){s=String(s||'').trim();var m=s.match(/[?&]t=([A-Za-z0-9_-]{43})(?![A-Za-z0-9_-])/)||s.match(/^([A-Za-z0-9_-]{43})$/);return m?m[1]:'';}
function lega(){var g=function(id){return document.getElementById(id);};
 if(g('spp-set'))g('spp-set').onclick=function(){location.hash='#setari';};
 var lp=g('spp-lp');if(lp)lp.addEventListener('submit',function(e){e.preventDefault();var i=g('spp-lpi'),t=tokDin(i.value);if(!t){g('spp-lpm').textContent='Linkul nu pare întreg. Copiază-l din email, de la „Deschide pagina ei”.';i.setAttribute('aria-invalid','true');i.focus();return;}i.removeAttribute('aria-invalid');location.href=location.pathname+'?t='+encodeURIComponent(t);});
 var f=g('spp-f');if(f)f.addEventListener('submit',function(e){e.preventDefault();var ie=g('spp-e'),em=ie.value.trim(),m=g('spp-m'),b=g('spp-b');m.classList.remove('ok');
  /* diacriticele sau spațiile din adresă nu se „curăță” (ar fi adresa altcuiva): le spunem pe loc */
  var gr=/[^\x21-\x7E]/.test(em)?'Adresa conține caractere nepermise (diacritice sau spații).':(!/^\S+@\S+\.\S+$/.test(em)?'Scrie, te rog, adresa de email întreagă.':'');
  if(gr){m.textContent=gr;ie.setAttribute('aria-invalid','true');ie.focus();return;}ie.removeAttribute('aria-invalid');b.disabled=true;f.setAttribute('aria-busy','true');
  cere('trimite-link',{method:'POST',body:{email:em,site:g('spp-s').value}}).then(function(j){b.disabled=false;f.removeAttribute('aria-busy');var bun=!!(j&&j.ok);m.classList.toggle('ok',bun);m.textContent=(j&&j.mesaj)||'N-a mers. Mai încearcă o dată.';if(!bun)b.focus();});});
 var sp=g('spp-sw-push');if(sp)sp.onclick=function(){var on=sp.getAttribute('aria-checked')!=='true',m=g('spp-set-m');sp.disabled=true;
  (on?pornestePush():opresteePush().then(function(){return true;})).then(function(ok){sp.disabled=false;if(on&&!ok){m.textContent='Notificările sunt oprite pentru aplicație. Le poți porni din setările telefonului.';return;}A.setari=A.setari||{};A.setari.push=on;sp.setAttribute('aria-checked',on?'true':'false');m.textContent='';});};
 var se=g('spp-sw-email');if(se)se.onclick=function(){var on=se.getAttribute('aria-checked')!=='true';se.disabled=true;cere('email',{method:'POST',body:{on:on}}).then(function(j){se.disabled=false;if(j&&j.ok){A.setari=A.setari||{};A.setari.email_faze=on;se.setAttribute('aria-checked',on?'true':'false');}else g('spp-set-m').textContent='N-a mers. Mai încearcă o dată.';});};
 R.querySelectorAll('[data-a]').forEach(function(b){b.onclick=function(){var a=b.getAttribute('data-a');
  if(a==='inapoi'){mergiAzi();}
  else if(a==='inapoi-invata'){location.hash='#invata';}
  else if(a==='nu'){ls('spp_anunt_nu',1);var x=g('spp-anunt');if(x)x.remove();}
  else if(a==='push'){b.disabled=true;pornestePush().then(function(ok){if(ok){A.setari=A.setari||{};A.setari.push=true;var x=g('spp-anunt');if(x)x.innerHTML='<p class="msg ok" role="status">Primești notificările fazelor.</p>';}else{b.disabled=false;g('spp-anunt-m').textContent='Notificările sunt oprite pentru aplicație. Le poți porni din setările telefonului.';}});}
  else if(a==='email'){b.disabled=true;cere('email',{method:'POST',body:{on:true}}).then(function(j){if(j&&j.ok){A.setari=A.setari||{};A.setari.email_faze=true;var x=g('spp-anunt');if(x)x.innerHTML='<p class="msg ok" role="status">Primești emailurile fazelor.</p>';}else{b.disabled=false;g('spp-anunt-m').textContent='N-a mers. Mai încearcă o dată.';}});}
  else if(a==='iesi'){opresteePush().then(function(){return cere('iesi',{method:'POST',body:{}});}).then(function(){A=null;if(D.app&&D.sp){location.href=D.sp;return;}location.hash='';arata();laTitlu();});}
  else if(a==='sterge'){foaie('Ștergi accesul tău la ciclul ei?','','Șterge accesul meu',function(){
   opresteePush().then(function(){return cere('sterge',{method:'POST',body:{confirmare:true}});}).then(function(j){if(j&&j.ok){A=null;D.mesaj='';location.hash='';R.innerHTML=cap()+'<main><div class="ecran"><div class="card gol"><h1>Accesul tău a fost șters</h1></div></div></main>';laTitlu();}});});}
 };});}
window.addEventListener('hashchange',function(){inchideFoaie();if(D.mesaj==='oprit')D.mesaj='';arata();window.scrollTo(0,0);laTitlu();});
/* butonul Înapoi de pe Android (doar în aplicație): închide foaia; dintr-o pagină din Învață → Învață; din Setări sau Învață → Azi;
   altfel aplicația trece în fundal (fără să iasă din cont) */
if(CAP){var APB=plugin('App');if(APB&&typeof APB.addListener==='function'){try{APB.addListener('backButton',function(){
 if(inchideFoaie())return;var h=hash();
 if(h.indexOf('invata/')===0){location.hash='#invata';return;}
 if(h==='setari'||h==='invata'){mergiAzi();return;}
 chem('App','minimizeApp').catch(function(){});});}catch(e){}}}
/* ecranul de pornire: pagina lui nu încarcă puntea Spațiului meu, așa că îl ascunde singură */
if(CAP){try{chem('SplashScreen','hide').catch(function(){});}catch(e){}}
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'&&A&&A.ok&&(!hash()||hash()==='azi'))reincarca();});
arata();
})();
AHSPPJS;
	$app   = ! empty( $cfg['app'] );
	$icon  = ! empty( $cfg['icon'] ) ? '<link rel="icon" href="' . esc_url( $cfg['icon'] ) . '"><link rel="apple-touch-icon" href="' . esc_url( $cfg['icon'] ) . '">' : '';
	// fonturile site-ului, găzduite local (Elementor: Figtree, Gilda Display). Cormorant nu e găzduit pe site (doar Google), deci îl
	// încarcă doar browserul, ca pagina ?ciclul-ei din 235; în aplicație nu (fără cereri spre terți): citatele în Figtree cursiv
	$font  = '<link rel="stylesheet" href="' . esc_url( home_url( '/wp-content/uploads/elementor/google-fonts/css/figtree.css' ) ) . '"><link rel="stylesheet" href="' . esc_url( home_url( '/wp-content/uploads/elementor/google-fonts/css/gildadisplay.css' ) ) . '">';
	if ( ! $app ) {
		$font .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital@1&display=swap">';
	}
	// seara: alegerea „Aspect de seară” din Spațiul meu (266, localStorage.ahspx_seara pe același telefon), altfel telefonul
	$seara = '<script>try{var s=localStorage.getItem("ahspx_seara");if(s==="1")document.documentElement.classList.add("seara");else if(s==="0")document.documentElement.classList.add("zi");}catch(e){}</script>';
	return '<!doctype html><html lang="ro"' . ( $app ? ' class="spp-app"' : '' ) . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><meta name="color-scheme" content="light dark"><meta name="theme-color" content="#FBF6F3" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#221922" media="(prefers-color-scheme: dark)"><title>Ciclul ei · Adela Haru</title>' . $icon
		. $seara . $font . '<style>' . $css . '</style></head><body><div id="spp"><noscript>Pagina are nevoie de JavaScript.</noscript></div>'
		. '<script>window.AHSPP=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';</script><script>' . $js . '</script></body></html>';
}

/**
 * Partea ei, pe /spatiul-meu/ (ca 266: stil + script care urmăresc desenările lui 235, fără să-i atingă codul):
 * cardul „Pentru el” în Ciclul meu (contul ei) și „Sunt partenerul ei” pe ecranul de intrare (fără cont).
 */
function ah_spp_head_ea() {
	if ( ! ah_spp_gata() || ! function_exists( 'ah_sp_e_pagina' ) || ! ah_sp_e_pagina() ) {
		return;
	}
	$e   = (string) ah_sp_email();
	$cfg = array(
		'cont'  => '' !== $e,
		'api'   => esc_url_raw( rest_url( AH_SPP_NS . '/' ) ),
		'max'   => AH_SPP_MAX,
		'el'    => esc_url_raw( home_url( AH_SPP_PAGINA ) ),
	);
	$css = <<<'AHSPPECSS'
/* ===== Spațiul meu – Pentru el ===== */
#ahsp .ci-bloc:has(#ci-pa-fa),#ahsp .ci-bloc:has(#ci-pa-link){display:none}
#ahsp .spp-card{display:grid;gap:14px}
#ahsp .spp-card .ci-l input{width:100%;min-height:52px;border:1px solid var(--line);border-radius:14px;padding:0 14px;font:17px var(--text);color:var(--ink);background:#fff;margin-top:6px}
#ahsp .spp-p{display:grid;gap:10px;padding:16px;border-radius:18px;background:rgba(255,255,255,.8);border:1px solid #fff}
#ahsp .spp-p-t{display:flex;gap:12px;align-items:baseline;justify-content:space-between;flex-wrap:wrap}
#ahsp .spp-p-t b{font:400 20px var(--disp);color:var(--ink)}
#ahsp .spp-p-t span{font-size:14.5px;color:var(--soft);overflow-wrap:anywhere}
#ahsp .spp-st{display:flex;align-items:flex-start;gap:8px;font-size:15px;color:var(--ink2);margin:0}
#ahsp .spp-st i{width:9px;height:9px;border-radius:50%;background:#C9B8AE;flex:none;margin-top:.55em}
#ahsp .spp-st.da i{background:var(--ok)}
#ahsp .spp-lista{display:grid;gap:10px}
#ahsp .spp-vechi{font-size:15px;color:var(--ink2);background:rgba(255,255,255,.6);border:1px dashed var(--line);border-radius:16px;padding:12px 14px}
#ahsp .spp-card .legatura,#ahsp .spp-cutie-el .legatura{display:inline-flex;align-items:center;min-height:44px}
#ahsp .spp-vechi .legatura{display:inline-flex;align-items:center;min-height:44px;vertical-align:middle}
#ahsp #spp-msg{margin:0}
#ahsp #spp-msg.ok,#ahsp .spp-cutie-el .mesaj.ok{color:var(--ok)}
#ahsp .spp-cutie-el .mesaj:empty{display:none}
#ahsp .spp-card input[aria-invalid=true],#ahsp .spp-cutie-el input[aria-invalid=true]{border-color:#C13C30}
#ahsp .spp-foaie{position:fixed;inset:0;z-index:60;display:flex;align-items:flex-end;justify-content:center;background:rgba(40,24,32,.45)}
#ahsp .spp-foaie .c{width:100%;max-width:520px;display:grid;gap:12px;background:#FBF6F3;color:var(--ink2);border-radius:24px 24px 0 0;padding:22px calc(20px + env(safe-area-inset-right,0px)) calc(20px + env(safe-area-inset-bottom,0px)) calc(20px + env(safe-area-inset-left,0px));box-shadow:0 -20px 50px -20px rgba(40,20,30,.5)}
#ahsp .spp-foaie h2{margin:0;font:400 22px/1.25 var(--disp);color:var(--ink)}
#ahsp .spp-foaie p{margin:0}
#ahsp .spp-foaie .ci-but{display:flex;flex-direction:column;align-items:stretch;gap:10px}
#ahsp .spp-foaie .btn{width:100%;white-space:nowrap}
@media (max-width:330px){#ahsp .spp-foaie .btn{white-space:normal}}
#ahsp .spp-intro{display:grid;gap:8px}
#ahsp .spp-el{margin-top:2px;justify-self:center}
#ahsp .spp-cutie-el{display:grid;gap:14px}
#ahsp .spp-cutie-el .spp-pas{display:grid;gap:8px;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.8);border:1px solid #fff;text-align:left}
#ahsp .spp-cutie-el .spp-pas b{font:400 19px var(--disp);color:var(--ink)}
#ahsp .spp-cutie-el input{width:100%;min-height:52px;border:1px solid var(--line);border-radius:14px;padding:0 14px;font:17px var(--text);color:var(--ink);background:#fff}
html.ahsp-seara #ahsp .spp-p,html.ahsp-seara #ahsp .spp-cutie-el .spp-pas,html.ahsp-seara #ahsp .spp-vechi{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1)}
html.ahsp-seara #ahsp .spp-foaie .c{background:#2A1F28;color:#E0D2CB}
html.ahsp-seara #ahsp .spp-foaie h2{color:#F6ECE6}
html.ahsp-seara #ahsp .spp-card input[aria-invalid=true],html.ahsp-seara #ahsp .spp-cutie-el input[aria-invalid=true]{border-color:#F2A08F}
AHSPPECSS;
	$js = <<<'AHSPPEJS'
(function(){
var X=window.AH_SPP||{},d=document,H=d.documentElement;
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function ls(k){try{var x=localStorage.getItem(k);return x?JSON.parse(x):null;}catch(e){return null;}}
var LUNI=['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
function data(iso){if(!iso)return '';var p=String(iso).split('-');return (+p[2])+' '+LUNI[(+p[1])-1];}
function cere(u,b){return fetch(X.api+u,{method:b?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:b?{'Content-Type':'application/json','X-AH-SPP':'1'}:{},body:b?JSON.stringify(b):undefined}).then(function(r){return r.json().catch(function(){return {ok:false};});}).catch(function(){return {ok:false,mesaj:'Nu e internet acum. Încearcă din nou.'};});}
var S={j:null,t:0,cere:false,form:false,msg:'',ok:false};

/* ---------- Ciclul meu (contul ei): cardul „Pentru el” ---------- */
function prenumeEa(){var n=(window.AH_SP&&window.AH_SP.nume)||'';return String(n).split(' ')[0];}
function acordTxt(n){return 'Sunt de acord ca '+(n?esc(n):'[prenume]')+' să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.';}
/* cardul are două părți redesenate (a: lista și formularul; b: linkul vechi) și, între ele, un singur rând de mesaj
   (#spp-msg, role=status) care nu se redesenează niciodată, ca cititorul de ecran să-l anunțe */
function carte(){
 var j=S.j,x=ls('ahsp_ciclu')||{},sit=x.sit,faraFaze=sit==='hormonal'||sit==='sarcina',L=j&&j.parteneri||[];
 if(!j)return {a:'<p class="mic" aria-busy="true">Se încarcă…</p>',b:''};
 if(faraFaze&&!L.length)return null;
 /* textul blocului „Pentru partenerul tău” din 235 (paBloc), fără frazele despre linkul vechi */
 var h='<div class="spp-intro"><p>Un link în care el vede doar în ce anotimp ești azi, ce trăiești și cum te poate sprijini, cu rânduri ale Adelei din <i>Magia Ciclului Feminin</i>.</p></div>';
 if(faraFaze)h+='<p class="ci-sit">Cât timp situația ta e „'+(sit==='hormonal'?'Contracepție hormonală':'Sunt însărcinată')+'”, el vede doar că estimările sunt în pauză.</p>';
 if(L.length)h+='<div class="spp-lista">'+L.map(function(p){var intrat=p.stare==='a_intrat';return '<div class="spp-p" data-id="'+p.id+'"><div class="spp-p-t"><b>'+esc(p.nume)+'</b><span>'+esc(p.email)+'</span></div><p class="spp-st'+(intrat?' da':'')+'"><i></i>'+(intrat?'A intrat'+(p.vizita?' · ultima vizită: '+esc(data(p.vizita)):''):'Invitat pe '+esc(data(p.creat))+' · încă nu a intrat')+'</p><div class="ci-but"><button class="btn lite" type="button" data-spp="retrimite">'+(intrat?'Trimite-i din nou linkul':'Trimite din nou invitația')+'</button><button class="legatura" type="button" data-spp="opreste">Oprește accesul</button></div></div>';}).join('')+'</div>';
 var plin=L.length>=(X.max||2);
 if(!j.ciclu){
  h+='<div class="ci-bloc"><h3>Păstrează-ți datele în cont</h3><p>Ca el să vadă faza ta, ciclul tău trebuie păstrat în contul tău.</p><div class="ci-but"><button class="btn" type="button" data-spp="sync">Păstrează-le în contul meu</button></div></div>';
 }else if(plin){
  h+='<p class="mic">Ai invitat '+L.length+' persoane, cât se poate. Oprește un acces ca să inviți pe altcineva.</p>';
 }else if(L.length&&!S.form){
  h+='<div class="ci-but"><button class="btn lite" type="button" data-spp="form">Invită pe altcineva</button></div>';
 }else{
  h+='<form class="spp-form" id="spp-form" novalidate><div style="display:grid;gap:12px"><label class="ci-l">Prenumele lui<input type="text" id="spp-nume" maxlength="40" autocomplete="off" autocapitalize="words"></label><label class="ci-l">Emailul lui<input type="email" id="spp-email" inputmode="email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="numele@exemplu.ro"></label><label class="ci-l">Numele tău, așa cum vrei să-l vadă<input type="text" id="spp-nume-ea" maxlength="40" autocapitalize="words" value="'+esc(prenumeEa())+'"></label>'+
   '<label class="ci-acord"><input type="checkbox" id="spp-acord"><span id="spp-acord-t">'+acordTxt('')+'</span></label>'+
   '<button class="btn" type="submit" id="spp-trimite">Trimite invitația</button></div></form>';
 }
 var v=d.getElementById('ci-pa-link')?'<p class="spp-vechi">Ai și un link vechi „Pentru el”. <button class="legatura" type="button" data-spp="vechi">Oprește linkul vechi</button></p>':'';
 return {a:h,b:v};}
function mesaj(t,ok){S.msg=t||'';S.ok=!!ok;var m=d.getElementById('spp-msg');if(!m)return;m.textContent=S.msg;m.classList.toggle('ok',S.ok&&!!S.msg);
 if(S.msg&&m.getBoundingClientRect().bottom>(window.innerHeight||0)-90)m.scrollIntoView({block:'center',behavior:'smooth'});}
/* câmpul greșit: aria-invalid + focus (și mesajul, sub formular) */
function gresit(id,t){var i=d.getElementById(id);mesaj(t);if(i){i.setAttribute('aria-invalid','true');i.focus();}}
function deseneazaCard(){var loc=d.getElementById('spp-loc');if(!loc)return;var c=loc.querySelector('.spp-c');var h=carte();
 if(!h){loc.style.display='none';return;}loc.style.display='';
 if(!c.querySelector('#spp-msg'))c.innerHTML='<div class="spp-card"><div class="spp-a"></div><p class="ci-msg" id="spp-msg" role="status" aria-live="polite"></p><div class="spp-b"></div></div>';
 var A=c.querySelector('.spp-a'),B=c.querySelector('.spp-b');
 /* nu redesenăm formularul cât timp scrie în el */
 if(A.getAttribute('data-h')!==h.a){var f=d.getElementById('spp-form'),val=null;if(f)val={n:d.getElementById('spp-nume').value,e:d.getElementById('spp-email').value,ea:d.getElementById('spp-nume-ea').value,a:d.getElementById('spp-acord').checked};
  A.innerHTML=h.a;A.setAttribute('data-h',h.a);
  var f2=d.getElementById('spp-form');if(f2&&val){d.getElementById('spp-nume').value=val.n;d.getElementById('spp-email').value=val.e;d.getElementById('spp-nume-ea').value=val.ea;d.getElementById('spp-acord').checked=val.a;d.getElementById('spp-acord-t').innerHTML=acordTxt(val.n.trim());}
  legaCard(A);}
 if(B.getAttribute('data-h')!==h.b){B.innerHTML=h.b;B.setAttribute('data-h',h.b);legaCard(B);}
 mesaj(S.msg,S.ok);}
function incarca(){if(S.cere||(S.j&&Date.now()-S.t<30000))return;S.cere=true;cere('parteneri').then(function(j){S.cere=false;if(j&&j.ok){S.j=j;S.t=Date.now();}deseneazaCard();});}
function legaCard(c){
 var f=c.querySelector('#spp-form');
 if(f){var n=d.getElementById('spp-nume');n.addEventListener('input',function(){d.getElementById('spp-acord-t').innerHTML=acordTxt(n.value.trim());});
  f.addEventListener('input',function(e){if(e.target&&e.target.removeAttribute)e.target.removeAttribute('aria-invalid');});
  f.addEventListener('change',function(e){if(e.target&&e.target.id==='spp-acord')e.target.removeAttribute('aria-invalid');});
  f.addEventListener('submit',function(e){e.preventDefault();var b=d.getElementById('spp-trimite');
   var o={nume:n.value.trim(),email:d.getElementById('spp-email').value.trim(),nume_ea:d.getElementById('spp-nume-ea').value.trim(),acord:d.getElementById('spp-acord').checked};
   if(!o.nume){gresit('spp-nume','Scrie prenumele lui.');return;}
   /* diacriticele sau spațiile din adresă nu se „curăță” (ar fi adresa altcuiva): le spunem pe loc */
   if(/[^\x21-\x7E]/.test(o.email)){gresit('spp-email','Adresa conține caractere nepermise (diacritice sau spații).');return;}
   if(!/^\S+@\S+\.\S+$/.test(o.email)){gresit('spp-email','Scrie, te rog, adresa lui de email întreagă.');return;}
   if(!o.acord){gresit('spp-acord','Bifează acordul, ca să putem trimite invitația.');return;}
   b.disabled=true;f.setAttribute('aria-busy','true');mesaj('Se trimite…');
   cere('invita',o).then(function(j){b.disabled=false;f.removeAttribute('aria-busy');if(j&&j.ok){S.j.parteneri=j.parteneri;S.form=false;S.msg=j.email===false?'Invitația e făcută, dar emailul nu a plecat. Încearcă „Trimite din nou invitația”.':'Gata. '+o.nume+' primește acum emailul cu linkul lui.';S.ok=j.email!==false;deseneazaCard();}
    else{if(j&&j.motiv==='ciclu'){S.j.ciclu=false;deseneazaCard();}var t=(j&&j.mesaj)||'N-a mers. Mai încearcă o dată.',camp={nume:'spp-nume',email:'spp-email',acord:'spp-acord'}[j&&j.motiv];if(camp&&d.getElementById(camp))gresit(camp,t);else{mesaj(t);var b2=d.getElementById('spp-trimite');if(b2)b2.focus();}}});});}
 c.querySelectorAll('[data-spp]').forEach(function(b){b.onclick=function(){var a=b.getAttribute('data-spp'),p=b.closest('.spp-p'),id=p?+p.getAttribute('data-id'):0;
  if(a==='form'){S.form=true;S.msg='';deseneazaCard();var x=d.getElementById('spp-nume');if(x)x.focus();return;}
  if(a==='sync'){var s=d.querySelector('#ahsp [data-ci-sync]');b.disabled=true;mesaj('Se salvează…');
   /* prin butonul lui 235; dacă în 8 s nu s-a notat salvarea (ahsp_ciclu_sync), cererea n-a mers: o spunem, nu reîncărcăm pe tăcute */
   if(s){s.click();var t0=Date.now(),iv=setInterval(function(){var gata=!!ls('ahsp_ciclu_sync');if(gata||Date.now()-t0>8000){clearInterval(iv);if(!gata){b.disabled=false;mesaj('Nu am putut salva. Verifică internetul și mai încearcă.');b.focus();return;}S.j=null;S.msg='';incarca();}},400);}
   else{fetch((window.AH_SP&&window.AH_SP.api||'')+'ciclu-date',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({acord:1,date:{ciclu:ls('ahsp_ciclu'),note:ls('ahsp_ciclu_note')||{},partener:ls('ahsp_partener')}})}).then(function(r){return r.json();}).then(function(j){if(!(j&&j.ok))throw new Error('nu');try{localStorage.setItem('ahsp_ciclu_sync',JSON.stringify({la:j.actualizat}));}catch(e){}S.j=null;S.msg='';incarca();}).catch(function(){b.disabled=false;mesaj('Nu am putut salva. Verifică internetul și mai încearcă.');b.focus();});}
   return;}
  if(a==='vechi'){var st=d.getElementById('ci-pa-stop');if(st)st.click();return;}
  if(a==='opreste'){var nm=p.querySelector('b').textContent;foaie('Oprești accesul lui '+nm+'?','Nu mai vede nimic din ciclul tău, de acum.','Oprește accesul',function(){b.disabled=true;
   cere('opreste',{id:id}).then(function(j){if(j&&j.ok){S.j.parteneri=j.parteneri;S.msg='Accesul lui '+nm+' s-a oprit.';S.ok=true;}else{S.msg='N-a mers. Mai încearcă o dată.';S.ok=false;}deseneazaCard();});});return;}
  if(a==='retrimite'){b.disabled=true;cere('retrimite',{id:id}).then(function(j){b.disabled=false;if(j&&j.ok){S.j.parteneri=j.parteneri;S.ok=j.email!==false;S.msg=j.email===false?'Emailul nu a plecat. Mai încearcă puțin mai târziu.':'Am trimis din nou invitația, cu un link nou. Linkul vechi nu mai merge, iar telefoanele sau browserele pe care l-a deschis sunt deconectate.';}else{S.ok=false;S.msg=(j&&j.mesaj)||'N-a mers. Mai încearcă o dată.';}deseneazaCard();});return;}
 };});}
/* foaia de confirmare (în loc de window.confirm), în #ahsp ca să ia stilurile și seara lui 235/266 */
var FO=null;
function inchideFoaie(){if(!FO)return false;var f=FO;FO=null;f.el.remove();if(f.inapoi&&f.inapoi.focus)f.inapoi.focus();return true;}
function foaie(titlu,text,actiune,cb){inchideFoaie();var el=d.createElement('div');el.className='spp-foaie';
 el.innerHTML='<div class="c" role="dialog" aria-modal="true" aria-labelledby="spp-foaie-t"><h2 id="spp-foaie-t">'+esc(titlu)+'</h2>'+(text?'<p>'+esc(text)+'</p>':'')+'<div class="ci-but"><button class="btn" type="button" data-f="da">'+esc(actiune)+'</button><button class="btn lite" type="button" data-f="nu">Renunț</button></div></div>';
 FO={el:el,inapoi:d.activeElement};(d.getElementById('ahsp')||d.body).appendChild(el);
 el.addEventListener('click',function(e){if(e.target===el){inchideFoaie();return;}var b=e.target.closest('[data-f]');if(!b)return;var da=b.getAttribute('data-f')==='da';inchideFoaie();if(da)cb();});
 el.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();inchideFoaie();}else if(e.key==='Tab'){var l=el.querySelectorAll('button'),p=l[0],u=l[l.length-1];if(e.shiftKey&&d.activeElement===p){e.preventDefault();u.focus();}else if(!e.shiftKey&&d.activeElement===u){e.preventDefault();p.focus();}}});
 setTimeout(function(){var b=el.querySelector('[data-f="nu"]');if(b)b.focus();},30);}
function ciclu(ecran){
 var set=ecran.querySelector('#ci-setari');if(!set)return;
 var vechi=d.getElementById('ci-pa-fa')||d.getElementById('ci-pa-link');if(vechi){var bl=vechi.closest('.ci-bloc');if(bl)bl.style.display='none';}
 if(ecran.querySelector('#spp-loc'))return;
 var sec=set.closest('.sectiune');if(!sec||!sec.parentNode)return;
 var w=d.createElement('div');w.className='sectiune';w.id='spp-loc';w.innerHTML='<h2>Pentru el</h2><div class="dar spp-c"></div>';
 sec.parentNode.insertBefore(w,sec);deseneazaCard();incarca();}

/* ---------- ecranul de intrare: „Sunt partenerul ei” ---------- */
function intrare(){var f=d.getElementById('ahsp-f');if(!f||!d.getElementById('ahsp-e'))return;var cut=f.closest('.cutie');if(!cut||cut.querySelector('#spp-el'))return;
 var b=d.createElement('button');b.type='button';b.className='legatura spp-el';b.id='spp-el';b.textContent='Sunt partenerul ei';cut.appendChild(b);
 b.onclick=function(){var w=d.createElement('div');w.className='cutie spp-cutie-el';w.id='spp-cutie-el';
  w.innerHTML='<p class="logo">Adela Haru</p><h1>Pentru el</h1>'+
   '<div class="spp-pas"><b>Deschide linkul primit pe email</b>'+
   '<form id="spp-lp" novalidate style="display:grid;gap:10px"><input type="url" id="spp-lpi" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="Linkul copiat din email" aria-label="Linkul copiat din email"><p class="mesaj" id="spp-lpm" aria-live="polite"></p><button class="btn" id="spp-lpb">Deschide linkul</button></form></div>'+
   '<div class="spp-pas"><b>Trimite-mi din nou linkul</b><form id="spp-lf" novalidate style="display:grid;gap:10px"><input type="email" id="spp-le" autocomplete="email" inputmode="email" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="numele@exemplu.ro" aria-label="Adresa ta de email"><p class="mesaj" id="spp-lm" aria-live="polite"></p><button class="btn lite" id="spp-lb">Trimite-mi linkul</button></form></div>'+
   '<button class="legatura" type="button" id="spp-inapoi">Înapoi la intrarea în Spațiul meu</button>';
  cut.style.display='none';cut.parentNode.insertBefore(w,cut.nextSibling);
  d.getElementById('spp-inapoi').onclick=function(){w.remove();cut.style.display='';};
  d.getElementById('spp-lp').addEventListener('submit',function(e){e.preventDefault();var s=String(d.getElementById('spp-lpi').value||'').trim(),m=s.match(/[?&]t=([A-Za-z0-9_-]{43})(?![A-Za-z0-9_-])/)||s.match(/^([A-Za-z0-9_-]{43})$/);
   var li=d.getElementById('spp-lpi');if(!m){d.getElementById('spp-lpm').textContent='Linkul nu pare întreg. Copiază-l din email, de la „Deschide pagina ei”.';li.setAttribute('aria-invalid','true');li.focus();return;}li.removeAttribute('aria-invalid');location.href=(X.el||'/pentru-el/')+'?t='+encodeURIComponent(m[1]);});
  var lf=d.getElementById('spp-lf');lf.addEventListener('submit',function(e){e.preventDefault();var le=d.getElementById('spp-le'),em=le.value.trim(),m=d.getElementById('spp-lm'),bb=d.getElementById('spp-lb');m.classList.remove('ok');
   var gr=/[^\x21-\x7E]/.test(em)?'Adresa conține caractere nepermise (diacritice sau spații).':(!/^\S+@\S+\.\S+$/.test(em)?'Scrie, te rog, adresa de email întreagă.':'');
   if(gr){m.textContent=gr;le.setAttribute('aria-invalid','true');le.focus();return;}le.removeAttribute('aria-invalid');bb.disabled=true;lf.setAttribute('aria-busy','true');
   cere('trimite-link',{email:em}).then(function(j){bb.disabled=false;lf.removeAttribute('aria-busy');var bun=!!(j&&j.ok);m.classList.toggle('ok',bun);m.textContent=(j&&j.mesaj)||'N-a mers. Mai încearcă o dată.';if(!bun)bb.focus();});});
 };}

/* ---------- urmărim desenările lui 235 ---------- */
function dupaDesen(){try{
 if(!X.cont){intrare();return;}
 var main=d.getElementById('ahsp-main');if(!main)return;var ecran=main.querySelector('.ecran');if(!ecran)return;ciclu(ecran);
}catch(e){}}
if(window.MutationObserver){var legat=false;var mo=new MutationObserver(function(){var R=d.getElementById('ahsp');if(R&&!legat){legat=true;mo.disconnect();mo.observe(R,{childList:true,subtree:true});}dupaDesen();});
 mo.observe(H,{childList:true,subtree:true});d.addEventListener('DOMContentLoaded',dupaDesen);if(d.readyState!=='loading')dupaDesen();}
})();
AHSPPEJS;
	echo '<style id="ahspp-css">' . $css . '</style>' . "\n" . '<script id="ahspp-js">window.AH_SPP=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';' . $js . '</script>' . "\n"; // phpcs:ignore
}

/* ==================================================================
   10. CÂRLIGELE
   ================================================================== */

/** Următoarea rulare a cronului zilnic: 8:10, ora site-ului (calculată de fiecare dată, deci și după schimbarea orei). */
function ah_spp_urmatoarea_rulare( $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$tz   = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Bucharest' );
	$d    = ( new DateTimeImmutable( '@' . $acum ) )->setTimezone( $tz )->setTime( 8, 10, 0 );
	if ( $d->getTimestamp() <= $acum ) {
		$d = $d->modify( '+1 day' )->setTime( 8, 10, 0 );
	}
	return $d->getTimestamp();
}
/**
 * Cronul zilnic e un lanț de evenimente unice (nu wp_schedule_event 'daily', care adună 86400 s și alunecă cu o oră la
 * schimbarea orei): fiecare rulare o programează pe următoarea, la 8:10. Nimic în plus dacă e deja ceva programat (de
 * exemplu continuarea după bugetul de timp, la un minut: după ea vine iarăși 8:10). Evenimentul recurent din 1.2.0 pleacă.
 */
function ah_spp_programeaza() {
	if ( function_exists( 'wp_get_schedule' ) && wp_get_schedule( 'ah_spp_zilnic' ) ) {
		wp_clear_scheduled_hook( 'ah_spp_zilnic' );
	}
	if ( ! wp_next_scheduled( 'ah_spp_zilnic' ) ) {
		wp_schedule_single_event( ah_spp_urmatoarea_rulare(), 'ah_spp_zilnic' );
	}
}
add_action( 'init', function () {
	if ( ! ah_spp_gata() ) {
		return;
	}
	ah_spp_db();
	ah_spp_migreaza_cheia();
	ah_spp_programeaza();
} );
add_action( 'ah_spp_zilnic', function () {
	update_option( 'ah_spp_ultima_rulare', time(), false ); // modulul aplicație arată un aviz în admin dacă e veche (cronul s-a oprit)
	if ( ! get_transient( 'ah_spp_lacat' ) ) {
		set_transient( 'ah_spp_lacat', 1, 120 );
		ah_spp_curata();
		ah_spp_verifica_toti();
		delete_transient( 'ah_spp_lacat' );
	}
	if ( ah_spp_gata() ) {
		ah_spp_programeaza();
	}
} );
add_action( 'ah_spp_verifica', function ( $cheie ) {
	ah_spp_verifica_toti( (string) $cheie );
} );

/* după ce ea își salvează ciclul (235: POST /ah-sp/v1/ciclu-date) → verificare în 2 minute; după ciclu-sterge → accesele ei pleacă */
add_filter( 'rest_request_after_callbacks', function ( $raspuns, $handler, $cerere ) {
	if ( ! ah_spp_gata() || ! is_object( $cerere ) || ! method_exists( $cerere, 'get_route' ) ) {
		return $raspuns;
	}
	$ruta = (string) $cerere->get_route();
	if ( '/ah-sp/v1/ciclu-date' !== $ruta && '/ah-sp/v1/ciclu-sterge' !== $ruta ) {
		return $raspuns;
	}
	$e = ah_sp_email();
	$d = ( is_object( $raspuns ) && method_exists( $raspuns, 'get_data' ) ) ? $raspuns->get_data() : null;
	if ( ! $e || ! is_array( $d ) || empty( $d['ok'] ) ) {
		return $raspuns;
	}
	$k = ah_spp_ea( $e );
	if ( '/ah-sp/v1/ciclu-sterge' === $ruta ) {
		ah_spp_sterge_ale_ei( $k );
	} elseif ( 'POST' === strtoupper( (string) ( method_exists( $cerere, 'get_method' ) ? $cerere->get_method() : 'POST' ) ) && ah_spp_ale_ei( $k ) && ! wp_next_scheduled( 'ah_spp_verifica', array( $k ) ) ) {
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'ah_spp_verifica', array( $k ) );
	}
	return $raspuns;
}, 10, 3 );

/* ștergerea contului ei din aplicație (ah-sp-aplicatie.php, POST /ah-spa/v1/sterge-cont) */
add_action( 'ah_spa_cont_sters', function ( $email ) {
	if ( ah_spp_gata() && is_string( $email ) && '' !== $email ) {
		ah_spp_sterge_ale_ei( ah_spp_ea( $email ) );
	}
}, 10, 1 );

/* ea și-a schimbat adresa contului (ah-sp-aplicatie.php): rândurile ei trec pe cheia nouă (ah_sp_ciclu_cheie, ca ciclul ei) */
add_action( 'ah_spa_cont_mutat', function ( $vechi, $nou ) {
	if ( ! ah_spp_gata() || ! is_string( $vechi ) || ! is_string( $nou ) || '' === $vechi || '' === $nou ) {
		return;
	}
	$kv = ah_spp_ea( $vechi );
	$kn = ah_spp_ea( $nou );
	if ( $kv === $kn ) {
		return;
	}
	global $wpdb;
	$n = (int) $wpdb->update( ah_spp_tabel(), array( 'ea' => $kn ), array( 'ea' => $kv ) );
	if ( $n ) {
		ah_spp_jurnal( 'adresa ei s-a schimbat: ' . $n . ' accese mutate' );
	}
}, 10, 2 );

/* aplicația ei arată rândul „Ciclul ei” (spre /pentru-el/) doar dacă pe acest telefon/browser e un acces activ al lui */
add_filter( 'ah_spa_are_acces_el', function ( $are ) {
	if ( ! ah_spp_gata() ) {
		return (bool) $are;
	}
	$r = ah_spp_din_cookie();
	return $r ? empty( $r['oprit'] ) : (bool) $are; // cu un cookie al lui, decide acesta (un acces oprit nu mai deschide nimic)
}, 10, 1 );

/* în aplicație, pagina lui e permisă (modulul aplicație trimite altfel orice cale necunoscută la /spatiul-meu/) */
add_filter( 'ah_spa_permise', function ( $l ) {
	$l   = (array) $l;
	$l[] = AH_SPP_PAGINA;
	return $l;
} );

add_action( 'template_redirect', 'ah_spp_template_redirect', 1 );
add_action( 'wp_head', 'ah_spp_head_ea', 62 ); // după 266 (60) și modul aplicație (61)

add_action( 'rest_api_init', function () {
	if ( ! ah_spp_gata() ) {
		return;
	}
	$t = '__return_true';
	register_rest_route( AH_SPP_NS, '/parteneri', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spp_r_parteneri' ) );
	register_rest_route( AH_SPP_NS, '/invita', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_invita' ) );
	register_rest_route( AH_SPP_NS, '/opreste', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_opreste' ) );
	register_rest_route( AH_SPP_NS, '/retrimite', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_retrimite' ) );
	register_rest_route( AH_SPP_NS, '/azi', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spp_r_azi' ) );
	register_rest_route( AH_SPP_NS, '/trimite-link', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_trimite_link' ) );
	register_rest_route( AH_SPP_NS, '/push', array(
		array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_push' ),
		array( 'methods' => 'DELETE', 'permission_callback' => $t, 'callback' => 'ah_spp_r_push_sterge' ), // compatibilitate (puntea ei, versiunile vechi)
	) );
	// POST în loc de DELETE: unele firewalluri (WAF) ale gazdei blochează DELETE
	register_rest_route( AH_SPP_NS, '/push/sterge', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_push_sterge' ) );
	register_rest_route( AH_SPP_NS, '/email', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_email' ) );
	register_rest_route( AH_SPP_NS, '/iesi', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_iesi' ) );
	register_rest_route( AH_SPP_NS, '/sterge', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spp_r_sterge' ) );
} );

endif;
