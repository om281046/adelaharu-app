<?php
/**
 * Spațiul meu – modul aplicație (Mircea, 18 nov. 2026). Modul separat: NU schimbă fragmentele existente
 * (Spațiul meu 235, extensiile 266, 281, 295, Respiră-te acasă 220/265, Meditațiile mele 51, sala de curs 44, cărțile 231).
 * Arhitectura: docs/arhitectura.md (v2). Lista completă a celor ascunse în aplicație: server/README.md.
 *
 * Ce face, DOAR când cererea vine din aplicația „Adela Haru” (Android și iPhone):
 *   1. Recunoaște aplicația: User-Agent cu „AdelaHaruApp/<versiune> (ios|android)” sau cookie-ul ah_app
 *      (pus aici la prima cerere cu acel User-Agent, 1 an, Secure, Lax).
 *   2. Ține aplicația în cont: orice pagină din afara listei permise (Spațiul meu, sala de curs, Cărțile mele, /app/,
 *      politicile, /wp-json/, fișierele, plus calendarul și mementourile sălii de pe /) se întoarce la /spatiul-meu/ (302).
 *      Portalurile de plată nu se deschid. Politicile se văd fără antetul și subsolul magazinului, cu drum înapoi.
 *   3. Aplicația nu vinde nimic (Apple 3.1.1 / 3.1.3(a), Google Play): datele contului se curăță înainte să ajungă în pagină
 *      (comenzile, cursurile de cumpărat, prețurile, produsele din Ciclul meu, linkurile spre magazin), iar ce mai trimite
 *      spre cumpărare se ascunde cu stil și cu un script care urmărește desenările (ca 266).
 *   4. Puntea spre telefon (window.AHApp): linkurile din emailuri, notificările push (o singură întrebare, cu explicație),
 *      mementourile fazelor ciclului (notificări locale, aceeași regulă ca Ciclul meu), meditațiile descărcate pentru
 *      ascultat fără internet, comenzile de pe ecranul blocat (pe Android prin pluginul AhMedia), „Șterge contul” în „Mai mult”.
 *   5. REST ah-spa/v1: push (POST/DELETE), descarca, fisier (link semnat 15 minute, cu „Range”), sterge-cont, config.
 *      Ștergerea contului oprește și mementourile ei din sala de curs (44) și înscrierea la Respiră-te acasă (220).
 *   6. Notificări push prin Firebase (FCM HTTP v1), în loturi, prin cron: meditație nouă, înregistrare „Respiră-te acasă”,
 *      întâlnire care începe în 15 minute. Fără cheia contului de serviciu nu pleacă nimic: doar jurnal.
 *   7. /.well-known/apple-app-site-association, /.well-known/assetlinks.json și pagina /app/intra.
 *   8. Setări › Aplicația mobilă: Team ID, amprente SHA-256, cheia Firebase, magazinele, versiunea minimă, mesajul.
 *   9. Contul de recenzie (Apple / Google): cod fix pentru O SINGURĂ adresă, setată în Setări › Aplicația mobilă,
 *      prin rest_pre_dispatch pe POST /ah-sp/v1/cod și /ah-sp/v1/intra (235 neschimbat; docs/review-account.md).
 *  10. Fără analiză și urmărire în aplicație: Google Analytics, Microsoft Clarity, pixelul Meta (și partea lui din
 *      browser pentru Conversions API), atribuirea comenzilor WooCommerce și bara Complianz nu se încarcă; iframe-urile
 *      Vimeo primesc dnt=1, playerul Apple Podcasts devine un link spre aplicația Podcasts (2.1.0, 18 nov. 2026).
 *  11. Codul de intrare din Spațiul meu (235), și în browser: contor atomic pe adresă înaintea lui 235 (5 încercări pe cod,
 *      15 greșeli pe zi), prin rest_pre_dispatch / rest_post_dispatch; 235 rămâne neschimbat (2.2.0, 18 nov. 2026).
 *  2.2.0: puntea se încarcă dintr-un fișier cu versiune (cache un an), cu doar configurația și partea „devreme” în pagină;
 *      schimbarea contului pe același telefon curăță datele celei dinainte; mementourile cu text neutru pe ecranul blocat.
 *  2.3.0 (review 18 nov., runda 2): amprenta contului („cine”) dintr-un secret din baza de date (nu din sărurile WordPress),
 *      cu amprenta veche acceptată 60 de zile și alias la „Mută contul” (Setări › Aplicația mobilă); o pagină fără cont nu mai
 *      șterge descărcările și copia ciclului; rutele ah-sp, ah-spx, ah-spp, ah-spa fără cache (antete + LiteSpeed); POST /push/sterge; ștergerea
 *      contului terminată și după o închidere bruscă (Preferences.ah_sterge); contoarele vechi se curăță; ștergerea rândurilor
 *      „Pentru el” și când fragmentul lui e oprit; versiunea tabelelor doar după ce coloanele există; cronul verificat (anunț
 *      în admin după 26 h fără rulare); fusul orar în mementouri; accesibilitate (foi cu focus prins și Escape, comutatoare
 *      <button role=switch>, titlul secțiunii <h1>, zoom 200%, codul de intrare), modul de seară după telefon.
 *
 * În browser nu se schimbă nimic: fiecare cârlig de pagină întreabă întâi ah_spa_e_aplicatie().
 * Oprire: dezactivezi fragmentul. Site-ul rămâne exact ca înainte; aplicația arată Spațiul meu ca în browser.
 * Textele pentru cliente sunt CIORNĂ, de citit de Adela.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( defined( 'AH_SPA' ) ) {
	return; // încărcat deja (de exemplu, copie dublă a fragmentului)
}
define( 'AH_SPA', '2.3.0' );
define( 'AH_SPA_NS', 'ah-spa/v1' );
define( 'AH_SPA_PACHET', 'ro.adelaharu.app' );
define( 'AH_SPA_LINK_MIN', 15 );        // cât ține linkul semnat al unei descărcări
define( 'AH_SPA_LOT', 100 );            // telefoane pe lot de notificări
define( 'AH_SPA_TOKENURI_MAX', 10 );    // telefoane cu notificări pe o adresă
define( 'AH_SPA_TABEL_VER', '2' );      // 2: și tabelul contoarelor atomice (ah_spa_incercari, secțiunea 11)
define( 'AH_SPA_NOTIF_ID', 41000 );     // id-urile notificărilor locale ale ciclului: 41000–41099 (aceleași în puntea JS)
define( 'AH_SPA_COD_MAX', 5 );         // încercări pe un cod trimis de 235 (POST /ah-sp/v1/intra), secțiunea 11
define( 'AH_SPA_COD_ZI', 15 );         // coduri greșite pe o adresă într-o zi
define( 'AH_SPA_REC_MAX', 20 );        // coduri greșite pe contul de recenzie, apoi se oprește (secțiunea 9)
define( 'AH_SPA_CINE_TRECERE', 60 );   // zile în care amprenta veche (din sărurile WordPress) e recunoscută ca același cont
define( 'AH_SPA_CRON_ORE', 26 );       // ore fără nicio rulare a cronului, apoi anunț în admin (secțiunea 12)

if ( ! function_exists( 'ah_spa_e_aplicatie' ) ) :

/* ==================================================================
   1. CINE CERE: APLICAȚIA SAU BROWSERUL
   ================================================================== */

/** Din User-Agent: array( 'platforma' => ios|android, 'versiune' => '1.2.3' ), sau null dacă nu e aplicația. */
function ah_spa_ua_aplicatie( $ua ) {
	if ( ! preg_match( '~AdelaHaruApp/([0-9]+(?:\.[0-9]+){0,3})(?:\s*\((ios|android)\b)?~i', (string) $ua, $m ) ) {
		return null;
	}
	$p = isset( $m[2] ) ? strtolower( $m[2] ) : '';
	if ( '' === $p ) {
		$p = preg_match( '/iphone|ipad|ipod|ios/i', (string) $ua ) ? 'ios' : ( preg_match( '/android/i', (string) $ua ) ? 'android' : '' );
	}
	return array( 'platforma' => $p, 'versiune' => $m[1] );
}

function ah_spa_ua() {
	return isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore
}

/** Adevărat doar în aplicație: User-Agent-ul ei sau cookie-ul ah_app (ios|android). */
function ah_spa_e_aplicatie() {
	if ( ah_spa_ua_aplicatie( ah_spa_ua() ) ) {
		return true;
	}
	$c = isset( $_COOKIE['ah_app'] ) ? (string) $_COOKIE['ah_app'] : '';
	return in_array( $c, array( 'ios', 'android' ), true );
}

/** ios | android | '' */
function ah_spa_platforma() {
	$u = ah_spa_ua_aplicatie( ah_spa_ua() );
	if ( $u && $u['platforma'] ) {
		return $u['platforma'];
	}
	$c = isset( $_COOKIE['ah_app'] ) ? (string) $_COOKIE['ah_app'] : '';
	return in_array( $c, array( 'ios', 'android' ), true ) ? $c : '';
}

/** Versiunea aplicației din User-Agent, sau ''. */
function ah_spa_versiune() {
	$u = ah_spa_ua_aplicatie( ah_spa_ua() );
	return $u ? $u['versiune'] : '';
}

/** Cookie-ul ah_app: pus la prima cerere cu User-Agent-ul aplicației (și reînnoit dacă se schimbă platforma). */
function ah_spa_pune_cookie() {
	$u = ah_spa_ua_aplicatie( ah_spa_ua() );
	if ( ! $u || ! $u['platforma'] || headers_sent() ) {
		return;
	}
	if ( isset( $_COOKIE['ah_app'] ) && $_COOKIE['ah_app'] === $u['platforma'] ) {
		return;
	}
	setcookie( 'ah_app', $u['platforma'], array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	$_COOKIE['ah_app'] = $u['platforma'];
}

/** Paginile din aplicație nu intră în cache (au puntea și curățenia; pentru browser ar fi greșite). */
function ah_spa_fara_cache( $motiv = 'ah spa' ) {
	do_action( 'litespeed_control_set_nocache', $motiv );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
}

/* ==================================================================
   2. CE PAGINI SE DESCHID ÎN APLICAȚIE
   ================================================================== */

/** Începuturile de cale permise în aplicație. Filtrul ah_spa_permise le poate completa. */
function ah_spa_permise() {
	$sp = defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/';
	return array_values( array_unique( (array) apply_filters( 'ah_spa_permise', array(
		$sp,
		'/sala-de-curs/',
		'/cartile-mele/',
		'/meditatiile-mele/',
		'/app/',
		'/politica-de-confidentialitate/',   // zona „politici” (ah_spa_politici): fără antetul și subsolul magazinului
		'/termeni-si-conditii/',
		'/politica-de-cookie-uri/',
		'/wp-json/',
		'/wp-content/',
		'/wp-includes/',
	) ) ) );
}

/** Tipare (expresii regulate, pe gazdă + cale + întrebare) care trimit spre cumpărare: blocate oriunde ar fi. */
function ah_spa_tipare_blocate() {
	return array_values( (array) apply_filters( 'ah_spa_tipare_blocate', array(
		'(^|\.)stripe\.com',
		'(^|\.)buy\.stripe\.com',
		'/checkout',
		'/cart/',
		'/cos/',
		'/product/',
		'/produs/',
		'/black-friday',
		'/card-cadou',
		'/recomanda',
		'/cursuri-online',
		'add-to-cart=',
		'[?&]ah_bf=',
		'mergi=abonament',
		'/wp-json/ah-(hb|it)/v1/portal',
		'/wp-json/ah-hb/v1/abonare',
		'/wp-json/ah-it/v1/abonare',
	) ) );
}

/**
 * Adresele de pe prima pagină (/) care se deschid în aplicație, după cheia din întrebare: pagina partenerului (Ciclul meu),
 * sunetul meditațiilor (51) și ce servește sala de curs (44): fișierul de calendar (.ics), linkul scurt din SMS (ahp)
 * și oprirea mementourilor din email. Nimic din ele nu vinde.
 */
function ah_spa_chei_radacina() {
	return array_values( (array) apply_filters( 'ah_spa_chei_radacina', array( 'ciclul-ei', 'ah_med_api', 'ah_sc_ics', 'ah_sc_ics_r', 'ahp', 'ah_sc_ritm_stop', 'ah_sc_repeta_stop' ) ) );
}

/** Calea curată: „/spatiul-meu/”, cu litere mici, cu bară la final (în afară de fișiere). */
function ah_spa_cale( $cale ) {
	$c = strtolower( rawurldecode( (string) $cale ) );
	$c = '/' . ltrim( $c, '/' );
	$baza = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $baza && 0 === strpos( $c, $baza . '/' ) ) {
		$c = substr( $c, strlen( $baza ) );
	}
	$ultim = substr( $c, strrpos( $c, '/' ) + 1 );
	if ( '' !== $ultim && false === strpos( $ultim, '.' ) ) {
		$c .= '/';
	}
	return preg_replace( '~/{2,}~', '/', $c );
}

/** Linkul de intrare fără aplicație (/app/intra?cont=…) duce, în aplicație, direct în cont. */
function ah_spa_dest_intra( $q ) {
	$sp = home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' );
	if ( isset( $q['cont'] ) && is_string( $q['cont'] ) && preg_match( '/^[A-Za-z0-9_\-]+\.[A-Za-z0-9]+$/', $q['cont'] ) ) {
		return add_query_arg( 'cont', $q['cont'], $sp );
	}
	return $sp;
}

/**
 * Unde trimitem, în aplicație, o cerere pentru adresa $uri: '' = rămâne aici; altfel adresa spre care redirecționăm.
 * $devreme = true: înaintea fragmentelor existente; /meditatiile-mele/ o lăsăm întâi lui 235 (intrarea automată).
 */
function ah_spa_destinatie( $uri, $devreme = false ) {
	$p    = wp_parse_url( (string) $uri );
	$cale = ah_spa_cale( isset( $p['path'] ) ? $p['path'] : '/' );
	$q    = array();
	if ( ! empty( $p['query'] ) ) {
		parse_str( $p['query'], $q );
	}
	$sp = home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' );
	foreach ( array( 'add-to-cart', 'ah_bf', 'add_to_wishlist', 'ah_bf_test' ) as $k ) {
		if ( isset( $q[ $k ] ) ) {
			return $sp;
		}
	}
	if ( isset( $q['mergi'] ) && 'abonament' === $q['mergi'] ) {
		return $sp . '#abonamente'; // portalul de plată nu se deschide în aplicație; rămâne starea abonamentului
	}
	if ( '/' === $cale && array_intersect( ah_spa_chei_radacina(), array_keys( $q ) ) ) {
		return ''; // pagina partenerului, sunetul meditațiilor, calendarul și mementourile sălii (vezi ah_spa_chei_radacina)
	}
	if ( 0 === strpos( $cale, '/app/' ) ) {
		return ah_spa_dest_intra( $q );
	}
	if ( '/meditatiile-mele/' === $cale ) {
		return $devreme ? '' : $sp . '#meditatii';
	}
	foreach ( ah_spa_permise() as $c ) {
		if ( 0 === strpos( $cale, ah_spa_cale( $c ) ) ) {
			return '';
		}
	}
	return $sp;
}

/** Redirecționarea din aplicație (302, fără cache). */
function ah_spa_redirectioneaza( $devreme ) {
	if ( ! ah_spa_e_aplicatie() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ( function_exists( 'is_robots' ) && is_robots() ) || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
		return;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
	$dest = ah_spa_destinatie( $uri, $devreme );
	if ( '' === $dest ) {
		return;
	}
	ah_spa_fara_cache( 'ah spa redirect' );
	wp_safe_redirect( $dest, 302, 'Adela Haru app' );
	exit;
}
add_action( 'init', 'ah_spa_pune_cookie', 1 );
add_action( 'template_redirect', function () { ah_spa_redirectioneaza( true ); }, -100 );
add_action( 'template_redirect', function () { ah_spa_redirectioneaza( false ); }, 2 );

/* Portalurile de plată (Stripe) chemate direct: în aplicație se întorc la starea abonamentului. */
add_filter( 'rest_pre_dispatch', function ( $rez, $server, $req ) {
	if ( null !== $rez || ! ah_spa_e_aplicatie() || ! is_object( $req ) || ! method_exists( $req, 'get_route' ) ) {
		return $rez;
	}
	if ( preg_match( '~^/ah-(hb|it)/v1/(portal|abonare)~', (string) $req->get_route() ) ) {
		$r = new WP_REST_Response( null, 302 );
		$r->header( 'Location', home_url( ( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' ) . '#abonamente' ) );
		$r->header( 'Cache-Control', 'no-store, private' );
		return $r;
	}
	return $rez;
}, 5, 3 );

/* ==================================================================
   3. APLICAȚIA NU VINDE NIMIC: DATELE CONTULUI, CURĂȚATE LA SURSĂ
   (Spațiul meu nu are un filtru pe datele lui; le prindem în ieșirea shortcode-ului, prin do_shortcode_tag,
    și le curățăm înainte să ajungă în pagină. Aceeași curățenie o face și scriptul, ca rezervă.)
   ================================================================== */

/** Datele contului (window.AH_SP, din ah_sp_date) fără nimic de cumpărat. Funcție pură, testată. */
function ah_spa_curata_date( $d ) {
	if ( ! is_array( $d ) || ( $d['stare'] ?? '' ) !== 'cont' ) {
		return $d;
	}
	$d['comenzi'] = array();                     // fila Comenzi, „Ai o plată de făcut”, „Plătește”
	$d['inchise'] = array();                     // cursurile încă necumpărate (duc la pagina produsului)
	$d['cursuri'] = array_values( array_filter( (array) ( $d['cursuri'] ?? array() ), function ( $c ) {
		return is_array( $c ) && ( $c['stare'] ?? '' ) !== 'expirat'; // cursurile încheiate (sala le oferă din nou spre cumpărare)
	} ) );
	$med = array();
	foreach ( (array) ( $d['med_toate'] ?? array() ) as $x ) {
		if ( is_array( $x ) && ! empty( $x['acces'] ) ) {  // doar ce poate asculta; fără „Încă închise”, prețuri, „Deschide”
			$x['pret']    = '';
			$x['cumpara'] = '';
			$med[]        = $x;
		}
	}
	$d['med_toate'] = $med;
	$ab = array();
	foreach ( (array) ( $d['abonamente'] ?? array() ) as $a ) {
		if ( ! is_array( $a ) ) {
			continue;
		}
		$a['link']    = '';                          // fără „Gestionează” (portalul Stripe)
		$a['detaliu'] = str_replace( 'Acces plătit până pe ', 'Activ până pe ', (string) ( $a['detaliu'] ?? '' ) ); // CIORNĂ
		$ab[]         = $a;
	}
	$d['abonamente'] = $ab;
	if ( isset( $d['ciclu'] ) && is_array( $d['ciclu'] ) ) {
		$d['ciclu']['produse'] = new stdClass();     // produsele potrivite fiecărei faze (din Harta ciclului)
	}
	if ( isset( $d['podcast']['episoade'] ) && is_array( $d['podcast']['episoade'] ) ) {
		foreach ( $d['podcast']['episoade'] as $i => $e ) {
			if ( is_array( $e ) ) {
				$d['podcast']['episoade'][ $i ]['mai'] = null; // „Mai departe: <curs>” de sub episod
				// playerul Apple Podcasts (embed.podcasts.apple.com) nu se încarcă în aplicație: puntea pune în locul lui
				// linkul episodului (câmpul „apple”), care se deschide în aplicația Podcasts (vezi ah_spa_js, iframe-urile)
				if ( isset( $e['embed'] ) && '' !== (string) $e['embed'] ) {
					$d['podcast']['episoade'][ $i ]['embed'] = 'about:blank';
				}
			}
		}
	}
	if ( isset( $d['linkuri'] ) && is_array( $d['linkuri'] ) ) {
		// „site” rămâne: din el se face linkul pentru partener din Ciclul meu (CI.paLink = site + '?ciclul-ei#d=…').
		// Rândul „Înapoi pe site” îl scot stilul și curățenia (adresa primei pagini nu e în lista permisă).
		foreach ( array( 'recomanda', 'cadou', 'cursuri', 'yin' ) as $k ) {
			if ( isset( $d['linkuri'][ $k ] ) ) {
				$d['linkuri'][ $k ] = ''; // Dă mai departe, Card cadou, Vezi cursurile, Yin Nourish (vânzare)
			}
		}
	}
	$d['aplicatie'] = true;
	return $d;
}

/** Înlocuiește „window.AH_SP={…};” din ieșirea shortcode-ului cu datele curățate. Funcție pură, testată. */
function ah_spa_curata_iesire( $html ) {
	$html = (string) $html;
	$a    = '<script>window.AH_SP=';
	$p    = strpos( $html, $a );
	if ( false === $p ) {
		return $html;
	}
	$s = $p + strlen( $a );
	$f = strpos( $html, ';</script>', $s );
	if ( false === $f ) {
		return $html;
	}
	$d = json_decode( substr( $html, $s, $f - $s ), true );
	if ( ! is_array( $d ) ) {
		return $html;
	}
	$j = wp_json_encode( ah_spa_curata_date( $d ) );
	return $j ? substr( $html, 0, $s ) . $j . substr( $html, $f ) : $html;
}
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'ah_spatiul_meu' !== $tag || ! ah_spa_e_aplicatie() ) {
		return $out;
	}
	return ah_spa_curata_iesire( $out );
}, 20, 2 );

/* ==================================================================
   4. PAGINILE DIN APLICAȚIE: STILUL, PUNTEA ȘI CURĂȚENIA
   ================================================================== */

/** Paginile de politici care se deschid în aplicație (din ecranul de înscriere al lui 266 și din bannerul de cookie-uri). */
function ah_spa_politici() {
	return array_values( (array) apply_filters( 'ah_spa_politici', array( '/politica-de-confidentialitate/', '/termeni-si-conditii/', '/politica-de-cookie-uri/' ) ) );
}

/** Pe ce pagină din aplicație suntem: sp (Spațiul meu) | sala | carti | politici | ''. */
function ah_spa_zona() {
	if ( function_exists( 'ah_sp_e_pagina' ) && ah_sp_e_pagina() ) {
		return 'sp';
	}
	$c = ah_spa_cale( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH ) ); // phpcs:ignore
	if ( 0 === strpos( $c, '/sala-de-curs/' ) ) {
		return 'sala';
	}
	if ( 0 === strpos( $c, '/cartile-mele/' ) ) {
		return 'carti';
	}
	foreach ( ah_spa_politici() as $pol ) {
		if ( 0 === strpos( $c, ah_spa_cale( $pol ) ) ) {
			return 'politici'; // antetul temei (Shop, cursuri, Black Friday) și subsolul nu se văd în aplicație
		}
	}
	return '';
}

/** Setările aplicației (Setări › Aplicația mobilă). */
function ah_spa_setari() {
	$s = get_option( 'ah_spa_setari', array() );
	return array_merge( array(
		'ios_team'        => '',
		'android_sha256'  => '',
		'link_ios'        => '',
		'link_android'    => '',
		'min_ios'         => '',
		'min_android'     => '',
		'mesaj'           => '',
		'email_echipa'    => 'contact@adelaharu.ro',
		'push_meditatii'  => 1,
		'push_inreg'      => 1,
		'push_intalniri'  => 1,
	), is_array( $s ) ? $s : array() );
}

/** Configurația punții, pusă în pagină (fără date personale: doar o amprentă a adresei). */
function ah_spa_cfg( $zona, $email ) {
	$s  = ah_spa_setari();
	$pl = ah_spa_platforma();
	$sp = home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' );
	$h  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	return array(
		'v'              => AH_SPA,
		'zona'           => $zona,
		'cont'           => '' !== $email,
		'cine'           => '' !== $email ? ah_spa_cine( $email ) : '',
		// amprentele vechi ale aceluiași cont (trecerea de la sărurile WordPress, „Mută contul”): nu e „alt cont” (2.3.0)
		'cine_vechi'     => '' !== $email ? ah_spa_cine_vechi( $email ) : array(),
		'api'            => esc_url_raw( rest_url( AH_SPA_NS . '/' ) ),
		'pagina'         => $sp,
		'gazde'          => array_values( array_unique( array( $h, preg_replace( '/^www\./', '', $h ), 'www.' . preg_replace( '/^www\./', '', $h ) ) ) ),
		'permise'        => array_map( 'ah_spa_cale', ah_spa_permise() ),
		'tipare'         => ah_spa_tipare_blocate(),
		'ancore_blocate' => array( '#comenzi' ),
		'radacina'       => ah_spa_chei_radacina(),
		'platforma'      => $pl,
		'versiune'       => ah_spa_versiune(),
		'min'            => 'ios' === $pl ? (string) $s['min_ios'] : ( 'android' === $pl ? (string) $s['min_android'] : '' ),
		'mesaj'          => (string) $s['mesaj'],
		'magazin'        => 'ios' === $pl ? (string) $s['link_ios'] : ( 'android' === $pl ? (string) $s['link_android'] : '' ),
		'notif_id'       => AH_SPA_NOTIF_ID,
		// contul pentru el (ah-sp-partener.php), dacă fragmentul lui e activ: e intrat el pe acest telefon? (doar da/nu)
		// și dacă adresa ei are acces la ciclul cuiva (filtrul ah_spa_are_acces_el, din ah-sp-partener.php): rândul „Ciclul ei” în Mai mult
		'el'             => (bool) apply_filters( 'ah_spa_are_acces_el', function_exists( 'ah_spp_din_cookie' ) && function_exists( 'ah_spp_gata' ) && ah_spp_gata() && (bool) ah_spp_din_cookie(), $email ),
		'api_el'         => function_exists( 'ah_spp_gata' ) && defined( 'AH_SPP_NS' ) ? esc_url_raw( rest_url( AH_SPP_NS . '/' ) ) : '',
		'pagina_el'      => function_exists( 'ah_spp_gata' ) && defined( 'AH_SPP_PAGINA' ) ? esc_url_raw( home_url( AH_SPP_PAGINA ) ) : '',
	);
}

add_action( 'wp_head', function () {
	if ( ! ah_spa_e_aplicatie() ) {
		return;
	}
	$zona = ah_spa_zona();
	if ( '' === $zona ) {
		return;
	}
	ah_spa_fara_cache( 'ah spa pagina' );
	$email = function_exists( 'ah_sp_email' ) ? (string) ah_sp_email() : '';
	$json  = wp_json_encode( ah_spa_cfg( $zona, $email ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $json ) ) {
		return;
	}
	// Stilul și puntea vin dintr-un fișier cu versiune (ținut un an în cache); în pagină rămân doar configurația ei (S)
	// și partea „devreme” (contul de pe telefon, refacerea ciclului), care trebuie să ruleze înaintea lui 235.
	// După 266 (prioritatea 60).
	$f = '';
	foreach ( ah_spa_fonturi() as $u ) {
		$f .= '<link rel="preload" href="' . esc_url( $u ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
	echo $f . '<link rel="stylesheet" id="ahspa-css" href="' . esc_url( ah_spa_punte_url( 'css' ) ) . '">' . "\n" // phpcs:ignore
		. '<script id="ahspa-cfg">window.AH_SPA=' . $json . ';' . ah_spa_js_devreme() . '</script>' . "\n" // phpcs:ignore
		. '<script id="ahspa-js" src="' . esc_url( ah_spa_punte_url( 'js' ) ) . '" defer></script>' . "\n"; // phpcs:ignore
}, 61 );

/** Stilul punții, cu linkul „Înapoi pe site” (Spațiul meu și cadrul sălii) ascuns fără să golim adresa. */
function ah_spa_css_tot() {
	$acasa = esc_attr( home_url( '/' ) );
	return ah_spa_css() . "\n" . '#ahsp a[href="' . $acasa . '"],#ahspc-lat a[href="' . $acasa . '"]{display:none!important}';
}

/** Versiunea fișierelor punții: AH_SPA + amprenta conținutului (o schimbare de cod schimbă adresa, deci și cache-ul). */
function ah_spa_punte_ver() {
	static $v = null;
	if ( null === $v ) {
		$v = AH_SPA . '-' . substr( md5( ah_spa_js() . '|' . ah_spa_css_tot() ), 0, 10 );
	}
	return $v;
}

/** Adresa fișierului punții: js | css. */
function ah_spa_punte_url( $fel ) {
	return add_query_arg( array( 'f' => 'css' === $fel ? 'css' : 'js', 'v' => ah_spa_punte_ver() ), rest_url( AH_SPA_NS . '/punte' ) );
}

/**
 * Fonturile de pe primul ecran (Figtree 400 și Gilda Display, cum le servește Elementor de pe site): latin și latin-ext
 * (ă, â, î, ș, ț sunt în latin-ext). Doar preîncărcare: dacă site-ul le schimbă, pagina merge la fel, doar fără câștig.
 */
function ah_spa_fonturi() {
	$b = content_url( '/uploads/elementor/google-fonts/fonts/' );
	return array_values( (array) apply_filters( 'ah_spa_fonturi', array(
		$b . 'figtree-xms-huzqdcfdgfmm4s9dq.woff2',
		$b . 'figtree-xms-huzqdcfdgfmm4q9dbzs.woff2',
		$b . 'gildadisplay-t5tmiroymoayg0weoh7hwmer3t7prw.woff2',
		$b . 'gildadisplay-t5tmiroymoayg0weoh7hwmer3tdpr6oh.woff2',
	) ) );
}

/** GET /ah-spa/v1/punte?f=js|css&v=…: același conținut pentru toți (fără date personale), public, un an în cache. */
function ah_spa_r_punte( WP_REST_Request $r ) {
	$css = 'css' === (string) $r->get_param( 'f' );
	$c   = $css ? ah_spa_css_tot() : ah_spa_js();
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	status_header( 200 );
	header( 'Content-Type: ' . ( $css ? 'text/css' : 'application/javascript' ) . '; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	// cu versiunea în adresă: neschimbat un an; fără ea (sau cu una veche) doar 5 minute
	header( 'Cache-Control: ' . ( (string) $r->get_param( 'v' ) === ah_spa_punte_ver() ? 'public, max-age=31536000, immutable' : 'public, max-age=300' ) );
	echo $c; // phpcs:ignore
	exit;
}

/**
 * Partea „devreme” a punții, în pagină, înaintea lui 235 (puntea întreagă vine cu defer, după desenare):
 *  - clasa zonei pe <html> (stilul politicilor, al sălii);
 *  - contul de pe telefon: cheia ahspa_cine ține amprenta contului care a scris datele paginii. Alt cont (B după A, pe
 *    același telefon) ⇒ datele ei din localStorage (ciclul, notițele, partenerul, progresul) pleacă ÎNAINTE ca 235 să le
 *    citească sau să le urce în contul lui B; puntea curăță apoi Preferences, mementourile și descărcările
 *    (window.AHSPA_ALT_CONT). Fără amprentă (prima pornire după actualizare): doar o notăm. O amprentă din S.cine_vechi
 *    (cea din sărurile WordPress, în trecere, sau aliasul lăsat de „Mută contul”) e același cont: doar o rescriem (2.3.0);
 *  - modul de seară după telefon: dacă ea n-a ales nimic în „Aspect de seară” (266, ahspx_seara lipsă), pagina urmează
 *    tema întunecată a sistemului (clasa ahsp-seara, fără să scriem alegerea);
 *  - refacerea ciclului: fără date în pagină, pe telefon, cu cont, #ahsp stă ascuns până răspunde Preferences (cel mult
 *    2,5 s, și dacă puntea nu se încarcă).
 */
function ah_spa_js_devreme() {
	return <<<'AHSPAJS0'
(function(){var S=window.AH_SPA||{},H=document.documentElement,L;if(S.zona)H.classList.add('ahspa-z-'+S.zona);
try{L=window.localStorage;var v=L.getItem('ahspa_cine');if(S.cont&&S.cine){if(v&&v!==S.cine&&(S.cine_vechi||[]).indexOf(v)<0){Object.keys(L).forEach(function(k){if(/^(ahsp_|ahspx_|ahct_|ah_ciclu_v1$)/.test(k))L.removeItem(k);});window.AHSPA_ALT_CONT=1;}if(v!==S.cine)L.setItem('ahspa_cine',S.cine);}}catch(e){}
try{if(S.zona==='sp'&&L&&L.getItem('ahspx_seara')===null&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches){H.classList.add('ahsp-seara');var tc=document.querySelector('meta[name="theme-color"]');if(tc)tc.setAttribute('content','#221922');}}catch(e){}
try{var C=window.Capacitor,N=!!(C&&(typeof C.isNativePlatform==='function'?C.isNativePlatform():C.isNative));if(N&&S.cont&&S.zona==='sp'&&L&&!L.getItem('ahsp_ciclu')&&!L.getItem('ah_ciclu_v1')){H.classList.add('ahspa-refac');setTimeout(function(){H.classList.remove('ahspa-refac');},2500);}}catch(e){}})();
AHSPAJS0;
}

/* Corpul paginii: o clasă, ca stilul să fie sigur doar în aplicație. */
add_filter( 'body_class', function ( $cl ) {
	if ( ah_spa_e_aplicatie() && '' !== ah_spa_zona() ) {
		$cl[] = 'ahspa-app';
		$cl[] = 'ahspa-' . ah_spa_zona();
	}
	return $cl;
} );

/** Stilul din aplicație: ce se ascunde și elementele punții. */
function ah_spa_css() {
	return <<<'AHSPACSS'
/* ===== Spațiul meu – modul aplicație: nimic de cumpărat ===== */
#ahsp .cov.inchis,#ahsp .curs.inchis,#ahsp .cov-pret,#ahsp .med-desch,#ahsp a[href=""],#ahsp a[href="#comenzi"],#ahsp [data-t="comenzi"],
#ahsb,#rsg,.ah-bf-banda,#ah-bf-banda,#ah-bf-bara,#ahbf,#ahbq-banda,#ahbq-loc,.ahhb-lacat .ahs-buton,
#ahspc-lat a[href*="/recomanda"],#ahspc-lat a[href*="/card-cadou"],#ahspc-lat .jos,#ahspc-lat a[href*="#comenzi"],#ahspc-bara a[href*="#comenzi"],
a[href*="/v1/portal"],a[href*="mergi=abonament"],a[href*="add-to-cart="],a[href*="/checkout"],a[href*="stripe.com"],
#spx-inst-r,#ahsp .spx-inst,#ahs-urmator,#ah-dar7-curs{display:none!important}

/* ===== politicile (termeni, confidențialitate, cookie-uri): doar textul, cu drum înapoi ===== */
html.ahspa-z-politici .elementor-location-header,html.ahspa-z-politici .elementor-location-footer,html.ahspa-z-politici header.site-header,
html.ahspa-z-politici footer.site-footer,html.ahspa-z-politici .ast-breadcrumbs-wrapper,html.ahspa-z-politici #ahbq-banda,html.ahspa-z-politici #ahbq-loc,
html.ahspa-z-politici .elementor-location-popup,html.ahspa-z-politici [data-elementor-type=popup],html.ahspa-z-politici .dialog-widget,
html.ahspa-z-politici .skip-link,html.ahspa-z-politici #ah-bf-banda,html.ahspa-z-politici #ah-bf-bara{display:none!important}
html.ahspa-z-politici body{background:#FBF6F3!important;padding-top:calc(64px + env(safe-area-inset-top,0px))!important}
html.ahspa-z-politici .site-content>.ast-container,html.ahspa-z-politici #primary,html.ahspa-z-politici .site-main{max-width:760px!important;margin:0 auto!important;padding:0 16px 40px!important}
.ahspa-inapoi{position:fixed;left:0;right:0;top:0;z-index:2147481000;display:flex;align-items:center;gap:6px;min-height:56px;padding:env(safe-area-inset-top,0px) 12px 0;background:rgba(251,246,243,.94);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border-bottom:1px solid #EADFE6}
.ahspa-inapoi a{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 12px 0 6px;border-radius:999px;color:#964E46!important;font:500 16px "Figtree",system-ui,sans-serif!important;text-decoration:none!important}
.ahspa-inapoi svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* ===== elementele punții ===== */
.ahspa-ui{font-family:"Figtree",system-ui,-apple-system,sans-serif}
#ahsp .spa-dl{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;min-height:36px}
#ahsp .spa-dl button{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 12px 0 8px!important;border-radius:999px!important;border:1px solid var(--line)!important;background:rgba(255,255,255,.8)!important;color:var(--acc)!important;font:500 14px var(--text)!important}
#ahsp .spa-dl button.leg{border:0!important;background:none!important;padding:0 4px!important;color:var(--soft)!important;text-decoration:underline;text-underline-offset:3px;font-size:13.5px!important}
#ahsp .spa-dl button:disabled{opacity:.7}
#ahsp .spa-dl .gata{display:inline-flex;align-items:center;gap:6px;font:500 14px var(--text);color:var(--ok)}
#ahsp .spa-dl .err{flex-basis:100%;font-size:13.5px;color:#8A4430}
#ahsp .spa-dl svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ahspa-sw{flex:none;position:relative;width:48px;height:28px;border-radius:999px;background:var(--line,#EADFE6);transition:background .25s}
.ahspa-sw i{position:absolute;left:3px;top:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(60,30,40,.35);transition:transform .25s}
[role=switch][aria-checked=true] .ahspa-sw{background:var(--acc,#964E46)}
[role=switch][aria-checked=true] .ahspa-sw i{transform:translateX(20px)}
#ahsp .spa-pericol b{color:#A33A2E!important}
#ahsp .spa-pericol .ic-mic{color:#A33A2E}
#ahsp .spa-ver{font-size:13px;color:var(--soft);text-align:center;margin-top:-4px}
.ahspa-foaie{position:fixed;inset:0;z-index:2147483000;display:grid;align-items:end;background:rgba(40,25,30,.45);font-family:"Figtree",system-ui,sans-serif}
.ahspa-foaie .c{background:#FBF6F3;color:#4E3D34;border-radius:26px 26px 0 0;padding:26px 20px calc(22px + env(safe-area-inset-bottom,0px));display:grid;gap:12px;max-height:88vh;overflow:auto;animation:ahspaSus .3s cubic-bezier(.2,.8,.2,1)}
@keyframes ahspaSus{from{transform:translateY(30px);opacity:0}to{transform:none;opacity:1}}
@media (prefers-reduced-motion:reduce){.ahspa-foaie .c{animation:none}}
.ahspa-foaie h2{font:400 26px/1.2 "Gilda Display",Georgia,serif!important;color:#4E3D34!important;margin:0!important;text-transform:none!important;letter-spacing:normal!important}
.ahspa-foaie p,.ahspa-foaie li{margin:0;font-size:15.5px;line-height:1.55;color:#6E5246}
.ahspa-foaie ul{margin:0;padding-left:20px;display:grid;gap:4px}
.ahspa-foaie .b{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:6px}
.ahspa-foaie button,.ahspa-foaie a.btn{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 22px;border-radius:999px;border:0;background:#C13C30;color:#fff;font:500 16px "Figtree",system-ui,sans-serif;cursor:pointer;text-decoration:none;text-transform:none;letter-spacing:normal}
.ahspa-foaie button.lite{background:#fff;color:#4E3D34;border:1px solid #EADFE6}
.ahspa-foaie button:disabled{opacity:.5}
.ahspa-foaie label{display:flex;gap:12px;align-items:center;min-height:44px;font-size:15px;line-height:1.45;color:#4E3D34;cursor:pointer}
.ahspa-foaie label input{width:24px;height:24px;margin:0;accent-color:#964E46;flex:none}
html.ahspa-sters #ahsp{visibility:hidden}
html.ahspa-sters .ahspa-foaie{visibility:visible}
.ahspa-foaie .m{min-height:1.2em;font-size:14px;color:#8A4430}
@media (min-width:700px){.ahspa-foaie{align-items:center;justify-items:center}.ahspa-foaie .c{border-radius:26px;width:min(520px,92vw)}}
.ahspa-banda{position:fixed;left:10px;right:10px;top:calc(10px + env(safe-area-inset-top,0px));z-index:2147482000;display:grid;gap:8px;padding:16px 18px;border-radius:20px;background:#4E3D34;color:#FBF6F3;box-shadow:0 20px 40px -20px rgba(40,20,20,.7);font:15px/1.5 "Figtree",system-ui,sans-serif}
.ahspa-banda b{font:400 19px/1.25 "Gilda Display",Georgia,serif;color:#fff}
.ahspa-banda a{justify-self:start;display:inline-flex;align-items:center;min-height:44px;padding:0 18px;border-radius:999px;background:#FBF6F3;color:#4E3D34!important;text-decoration:none!important;font-weight:500}
html.ahsp-seara .ahspa-foaie .c{background:#2A1F28;color:#F6ECE6}
html.ahsp-seara .ahspa-foaie h2,html.ahsp-seara .ahspa-foaie label{color:#F6ECE6!important}
html.ahsp-seara .ahspa-foaie p,html.ahsp-seara .ahspa-foaie li{color:#E0D2CB}
html.ahsp-seara .ahspa-foaie button.lite{background:rgba(255,255,255,.08);color:#F6ECE6;border-color:rgba(255,255,255,.16)}
html.ahsp-seara #ahsp .spa-dl button{background:rgba(255,255,255,.08)!important;border-color:rgba(255,255,255,.16)!important}
html.ahsp-seara #ahsp .spa-pericol b,html.ahsp-seara #ahsp .spa-pericol .ic-mic{color:#F2A08F!important}
.spa-pod{margin:14px 0 0!important}
.spa-pod svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}

/* ===== zonele sigure (viewport-fit=cover în aplicație, ios.contentInset 'never'): sus și jos le are 235, aici și lateralele ===== */
#ahsp{padding-left:env(safe-area-inset-left,0px);padding-right:env(safe-area-inset-right,0px)}
#ahsp .sus{padding-left:calc(16px + env(safe-area-inset-left,0px))!important;padding-right:calc(16px + env(safe-area-inset-right,0px))!important}
#ahsp .bara,#ahspc-bara{padding-left:calc(6px + env(safe-area-inset-left,0px))!important;padding-right:calc(6px + env(safe-area-inset-right,0px))!important}
.ahspa-inapoi{padding-left:calc(12px + env(safe-area-inset-left,0px))!important;padding-right:calc(12px + env(safe-area-inset-right,0px))!important}
html.ahspa-z-carti body{padding-top:env(safe-area-inset-top,0px)}
/* Spațiul meu fără stilurile temei (ah_spa_scoate_tema): singurul lucru de care avea nevoie din Astra (harnașul vizual) */
html.ahspa-z-sp body{margin:0}

/* ===== sala de curs: bara „Înapoi în Spațiul meu” sus, ca la politici; nimic din ce o readuce la cumpărare ===== */
html.ahspa-z-sala body{padding-top:calc(64px + env(safe-area-inset-top,0px))!important}
.ahhb-lacat,#ah-dar7-curs{display:none!important}

/* ===== politicile fără tema site-ului: fonturile și culorile Spațiului meu ===== */
html.ahspa-z-politici body{font:16.5px/1.6 Figtree,system-ui,-apple-system,sans-serif!important;color:#4E3D34}
html.ahspa-z-politici h1,html.ahspa-z-politici h2{font-family:"Gilda Display",Georgia,serif!important;font-weight:400!important;color:#4E3D34}
html.ahspa-z-politici a{color:#964E46}

/* ===== Spațiul meu în aplicație: săgețile centrate, „Instrucțiuni” ca iconiță, procentul pe un rând ===== */
#ahsp .sageata{display:grid!important;place-items:center}
#ahsp .sageata svg{display:block;margin:0}
#ahsp .cov{position:relative}
#ahsp .cov-inst[data-spa-ic]{position:absolute;left:8px;top:8px;z-index:2;width:44px;height:44px;min-height:44px;padding:0!important;justify-content:center;border-radius:50%!important;background:rgba(255,255,255,.88)!important}
#ahsp .cov-inst[data-spa-ic] svg.ic{width:22px;height:22px}
#ahsp .spa-dl button{white-space:nowrap}
#ahsp .cov-play[data-spa] svg.ic,#ahsp .cov-play[data-spa] svg{fill:none!important;stroke:currentColor!important;stroke-width:1.8;margin:0!important;width:22px;height:22px}

/* ===== refacerea ciclului (puntea, secțiunea 3): Spațiul meu așteaptă o clipă copia de pe telefon ===== */
html.ahspa-refac #ahsp{visibility:hidden}

/* ===== fără bara de cookie-uri în aplicație: aici rămân doar cookie-uri strict necesare (fragmentul, secțiunea 10) ===== */
#cmplz-cookiebanner-container,#cmplz-manage-consent,.cmplz-cookiebanner,.cmplz-manage-consent,.cmplz-soft-cookiewall{display:none!important}
/* ===== accesibilitate (2.3.0) ===== */
/* rândurile din „Mai mult” sunt <button>: același aspect ca rândurile <a> din 235 */
#ahsp button.rand.spa-b{width:100%;text-align:left;font:inherit;color:inherit;margin:0;cursor:pointer;-webkit-appearance:none;appearance:none;border:1px solid #fff;background:rgba(255,255,255,.85)}
html.ahsp-seara #ahsp button.rand.spa-b{background:var(--card,#31242E);border-color:rgba(255,255,255,.08)}
#ahsp #spa-el .sageata{margin-left:auto}
#ahsp #spa-el .spa-dr{transform:scaleX(-1)}
#ahsp button.rand.spa-b:focus-visible,#ahsp a.rand:focus-visible,.ahspa-foaie button:focus-visible,.ahspa-foaie input:focus-visible,#ahsp .spa-dl button:focus-visible{outline:3px solid #964E46;outline-offset:2px}
html.ahsp-seara #ahsp button.rand.spa-b:focus-visible{outline-color:#EDB7A4}
.ahspa-sr{position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important;border:0!important}
/* titlul secțiunii: <h1> cu aspectul vechiului <span class="titlu"> */
#ahsp .sus h1#ahsp-tit{font:400 20px/1.2 var(--disp)!important;color:var(--ink)!important;margin:0!important;padding:0!important;min-width:0;overflow-wrap:anywhere}
#ahsp h1#ahsp-tit:focus{outline:none}
/* eticheta mică ce repetă titlul din antet (doar pe telefon: pe calculator antetul nu se vede) */
@media (max-width:899px){html.ahspa-z-sp #ahsp .et.spa-dublu{display:none}}
/* zoom 200% (ecran de 195 px): bara pe 5 coloane egale, etichetele se rup, nimic nu iese în lateral */
#ahsp .bara{grid-auto-flow:row!important;grid-template-columns:repeat(5,minmax(0,1fr))!important}
#ahsp .bara a{min-width:0;overflow-wrap:anywhere;text-align:center}
#ahsp .bara .pt{width:100%!important;max-width:52px}
#ahsp .grila{grid-template-columns:repeat(auto-fit,minmax(min(140px,100%),1fr))!important}
#ahsp .ecran>*,#ahsp .tile,#ahsp .spx-ev,#ahsp .spx-ev>*{min-width:0;max-width:100%}
#ahsp .spx-ev *{overflow-wrap:anywhere}
#ahsp>div,#ahsp>div>div,#ahsp main,#ahsp .ci-azi,#ahsp .dar,#ahsp .ecran,#ahsp .sectiune,#ahsp .salut,#ahsp .continua,#ahsp .grila>*{min-width:0;max-width:100%}
#ahsp .salut h1,#ahsp .continua b{overflow-wrap:anywhere}
#ahsp .ci-inel{max-width:100%;height:auto}
#ahsp .ci-hor svg{max-width:100%}
@media (max-width:260px){#ahsp .bara a{font-size:0!important;gap:0}#ahsp .sus{padding-left:8px!important;padding-right:8px!important}#ahsp main{padding-left:10px!important;padding-right:10px!important}#ahsp .cov-grila{grid-template-columns:minmax(0,1fr)!important}}
/* playerul nu acoperă sfârșitul listei */
html.ahspa-cu-player #ahsp main{padding-bottom:calc(190px + var(--jos,0px))!important}
/* descărcarea: „Descărcat” și „Șterge” pe un rând, aceeași înălțime în ambele stări */
#ahsp .spa-dl{min-height:44px}
#ahsp .spa-dl button.leg{min-height:44px}
/* foaia „Șterge contul”: butoanele rămân jos, la vedere, cât derulează textul */
.ahspa-foaie .c .b.lipit{position:sticky;bottom:calc(-22px - env(safe-area-inset-bottom,0px));margin:6px -20px calc(-22px - env(safe-area-inset-bottom,0px));padding:12px 20px calc(22px + env(safe-area-inset-bottom,0px));background:#FBF6F3;box-shadow:0 -12px 18px -14px rgba(40,25,30,.35)}
html.ahsp-seara .ahspa-foaie .c .b.lipit{background:#2A1F28}
@media (min-width:700px){.ahspa-foaie .c .b.lipit{margin-bottom:-22px;padding-bottom:22px;bottom:-22px}}
AHSPACSS;
}

/** Puntea: window.AHApp (linkuri, push, ciclu, descărcări, ecranul blocat, ștergerea contului) și curățenia paginii. */
function ah_spa_js() {
	return <<<'AHSPAJS'
(function(){
'use strict';
if(window.AHApp)return;
var S=window.AH_SPA||{}, d=document, H=d.documentElement;
var CAP=window.Capacitor||null, PL=(CAP&&CAP.Plugins)||{};
var NATIV=!!(CAP&&(typeof CAP.isNativePlatform==='function'?CAP.isNativePlatform():CAP.isNative));
if(S.zona)H.classList.add('ahspa-z-'+S.zona); /* stilul pe zone (politicile: fără antetul și subsolul temei) */
function ls(k,v){try{if(v===undefined)return JSON.parse(localStorage.getItem(k)||'null');localStorage.setItem(k,JSON.stringify(v));}catch(e){return null;}}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function scoate(el){if(el&&el.__ahInchide){var f=el.__ahInchide;el.__ahInchide=null;f();}if(el&&el.parentNode)el.parentNode.removeChild(el);}
function pr(p){return p&&typeof p.then==='function'?p:Promise.resolve(p);}
function are(n,m){return !!(PL[n]&&typeof PL[n][m]==='function');}
function cheama(n,m,a){if(!are(n,m))return Promise.reject(new Error('lipsă '+n+'.'+m));try{return pr(PL[n][m](a));}catch(e){return Promise.reject(e);}}
function asculta(n,ev,f){if(!are(n,'addListener'))return;try{pr(PL[n].addListener(ev,f)).catch(function(){});}catch(e){}}
var IC={casti:'M4 15v-3a8 8 0 0 1 16 0v3M4 15a2 2 0 0 1 2-2h1v7H6a2 2 0 0 1-2-2zM20 15a2 2 0 0 0-2-2h-1v7h1a2 2 0 0 0 2-2z',iesire:'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',ciclu:'M20 12a8 8 0 1 1-2.3-5.7M20 4v4.5h-4.5',calendar:'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',info:'M12 21a9 9 0 1 0 0-18a9 9 0 0 0 0 18zM12 11v5M12 7.5h.01',meditatii:'M12 4a3 3 0 1 1 0 6a3 3 0 0 1 0-6zM5 20c1-4 4-6 7-6s6 2 7 6M3 14c2 1 4 1 5 0M21 14c-2 1-4 1-5 0',jos:'M12 4v11M7 10l5 5 5-5M5 20h14',bifa:'M5 12.5l4.5 4.5L19 7.5',clopot:'M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0',luna:'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z',cos:'M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',inapoi:'M15 5l-7 7 7 7',nota:'M9 18V5l11-2v13M9 18a3 3 0 1 1-3-3a3 3 0 0 1 3 3zM20 16a3 3 0 1 1-3-3a3 3 0 0 1 3 3z'};
function ic(n,cls){return '<svg class="'+(cls||'ic')+'" viewBox="0 0 24 24" aria-hidden="true"><path d="'+IC[n]+'"/></svg>';}

/* ---------- Preferences (cheile citite și de pagina offline a aplicației) ---------- */
var PREF={
 ia:function(k){return cheama('Preferences','get',{key:k}).then(function(r){if(!r||r.value==null)return null;try{return JSON.parse(r.value);}catch(e){return r.value;}}).catch(function(){return null;});},
 pune:function(k,v){return cheama('Preferences','set',{key:k,value:JSON.stringify(v)}).catch(function(){});},
 scoate:function(k){return cheama('Preferences','remove',{key:k}).catch(function(){});}};

/* ---------- API-ul nostru (ah-spa/v1), cu cookie-ul contului ---------- */
function api(cale,o){o=o||{};var h={'X-AH-App':'1'};if(o.corp)h['Content-Type']='application/json';
 return fetch(S.api+cale,{method:o.metoda||'GET',credentials:'same-origin',cache:'no-store',headers:h,body:o.corp?JSON.stringify(o.corp):undefined})
  .then(function(r){return r.json().catch(function(){return {ok:false};}).then(function(j){j=j||{};j._cod=r.status;return j;});})
  .catch(function(){return {ok:false,retea:true};});}

/* ==================================================================
   1. LINKURI: ce e permis în aplicație
   ================================================================== */
function abs(u){try{return new URL(u,location.href);}catch(e){return null;}}
function eSite(x){return (S.gazde||[]).indexOf(x.hostname)>-1||x.host===location.host;}
function caleOk(p){p=String(p||'/').toLowerCase();var u=p.slice(p.lastIndexOf('/')+1);if(u&&u.indexOf('.')<0)p+='/';return (S.permise||[]).some(function(c){return p.indexOf(c)===0;});}
function tipar(x){var t=x.hostname+x.pathname+x.search;return (S.tipare||[]).some(function(r){try{return new RegExp(r,'i').test(t);}catch(e){return false;}});}
/* Adevărat dacă linkul trimite spre cumpărare sau în afara paginilor aplicației (pe site). */
function blocat(h){
 if(h==null)return false;h=String(h).trim();
 if(h==='')return true;                                   /* linkurile golite la sursă (Card cadou, Dă mai departe…) */
 if(h.charAt(0)==='#')return (S.ancore_blocate||[]).indexOf(h)>-1;
 if(/^(mailto|tel|sms|javascript):/i.test(h))return false;
 var x=abs(h);if(!x)return false;
 if(tipar(x))return true;
 if(!eSite(x))return false;                               /* Zoom, YouTube, Spotify, WhatsApp: se deschid în afara aplicației */
 if(x.pathname==='/'&&(S.radacina||[]).some(function(k){return x.searchParams.has(k);}))return false; /* partenerul, sunetul, calendarul sălii */
 if(caleOk(x.pathname))return (S.ancore_blocate||[]).indexOf(x.hash)>-1;
 return true;
}
function permis(u){var x=abs(u);return !!(x&&/^https?:$/.test(x.protocol)&&eSite(x)&&!blocat(x.href));}
/* /app/intra?cont=X (linkul din emailuri, pentru cine n-are aplicația): în aplicație direct /spatiul-meu/?cont=X, fără un drum în plus prin server */
function intra(u){var x=abs(u);if(!x||!eSite(x)||!/^\/app\/intra(\/|$)/i.test(x.pathname))return u;var y=abs(S.pagina);if(!y)return u;var c=x.searchParams.get('cont');if(c&&/^[A-Za-z0-9_\-]+\.[A-Za-z0-9]+$/.test(c))y.searchParams.set('cont',c);y.hash=x.hash;return y.href;}
function mergi(u){u=intra(u);var x=abs(u);if(!x)return;if(x.pathname===location.pathname&&x.search===location.search&&x.hash){location.hash=x.hash;return;}location.href=x.href;}

/* ==================================================================
   2. CURĂȚENIA: urmărim desenările paginii (ca 266) și scoatem ce trimite spre cumpărare
   ================================================================== */
function curataD(v){
 try{if(!v||v.stare!=='cont'||v.aplicatie)return v;
  v.comenzi=[];v.inchise=[];v.cursuri=(v.cursuri||[]).filter(function(c){return c&&c.stare!=='expirat';});
  v.med_toate=(v.med_toate||[]).filter(function(x){return x&&x.acces;}).map(function(x){x.pret='';x.cumpara='';return x;});
  v.abonamente=(v.abonamente||[]).map(function(a){a.link='';a.detaliu=String(a.detaliu||'').replace('Acces plătit până pe ','Activ până pe ');return a;});
  if(v.ciclu)v.ciclu.produse={};
  if(v.podcast&&v.podcast.episoade)v.podcast.episoade.forEach(function(e){if(e){e.mai=null;if(e.embed)e.embed='about:blank';}});
  if(v.linkuri)['recomanda','cadou','cursuri','yin'].forEach(function(k){if(k in v.linkuri)v.linkuri[k]='';});
  v.aplicatie=true;}catch(e){}
 return v;
}
try{
 if(window.AH_SP){curataD(window.AH_SP);}
 else{var ds=Object.getOwnPropertyDescriptor(window,'AH_SP')||{},g0=ds.get,s0=ds.set,_v;
  Object.defineProperty(window,'AH_SP',{configurable:true,enumerable:true,
   get:function(){return g0?g0():_v;},
   set:function(v){if(s0)s0(v);else _v=v;curataD(g0?g0():v);}});}
}catch(e){}
/* Iframe-urile din aplicație. Vimeo: dnt=1 (fără cookie-uri și statistici Vimeo; embed-urile din 235, 44, 51, 265 îl au
   deja, serverul îl pune și în HTML-ul paginii, aici îl primesc și cele desenate mai târziu). Apple Podcasts: playerul
   (embed.podcasts.apple.com) nu se încarcă în aplicație (trimite statistici de utilizare la Apple, iar într-un WKWebView
   redarea în cadru nu e sigură); în locul lui, linkul episodului, pe care telefonul îl deschide în aplicația Podcasts. */
function dntVimeo(u){var x=abs(u);if(!x||x.hostname!=='player.vimeo.com'||x.searchParams.get('dnt')==='1')return '';x.searchParams.set('dnt','1');return x.href;}
function linkPodcast(f){
 var m=/(\d+)/.exec(f.getAttribute('title')||''),P=(window.AH_SP&&window.AH_SP.podcast)||{},u='';
 if(m)(P.episoade||[]).forEach(function(e){if(e&&+e.nr===+m[1]&&e.apple)u=e.apple;});
 if(!u){var x=abs(f.getAttribute('src')||'');if(x&&x.hostname==='embed.podcasts.apple.com'){x.hostname='podcasts.apple.com';x.searchParams.delete('theme');u=x.href;}}
 u=u||P.apple||'';var y=abs(u);return y&&y.protocol==='https:'&&/(^|\.)apple\.com$/.test(y.hostname)?y.href:'';
}
function platf(){var p=CAP&&typeof CAP.getPlatform==='function'?CAP.getPlatform():'';return p==='ios'||p==='android'?p:S.platforma;}
/* Spotify: doar linkul emisiunii (site-ul nu are linkuri pe episod). */
function linkSpotify(){var P=(window.AH_SP&&window.AH_SP.podcast)||{},y=abs(P.spotify||'');return y&&y.protocol==='https:'&&y.hostname==='open.spotify.com'?y.href:'';}
/* Unde ascultă, după telefon: iPhone = Apple Podcasts (episodul) + Spotify; Android = Spotify (Apple Podcasts nu e aplicație de Android). */
function butoanePodcast(f){
 var a=platf()==='android'?'':linkPodcast(f),sp=linkSpotify(),h='';
 if(a)h+='<a class="btn lite" href="'+esc(a)+'" rel="noopener">'+ic('iesire')+'Ascultă în Apple Podcasts</a>'; /* CIORNĂ; aceeași iconiță ca Spotify: ambele ies din aplicație */
 if(sp)h+='<a class="btn lite" href="'+esc(sp)+'" rel="noopener">'+ic('iesire')+'Ascultă pe Spotify</a>'; /* CIORNĂ */
 return h;
}
function iframe(f){
 if(!f||f.tagName!=='IFRAME'||f.getAttribute('data-spa-ok'))return;
 var src=f.getAttribute('src')||'',ds=f.getAttribute('data-src-cmplz')||'';
 if(f.classList.contains('pod-ifr')||/^https?:\/\/embed\.podcasts\.apple\.com\//i.test(src)){
  var p=d.createElement('p');p.className='spa-pod ci-but';p.setAttribute('data-spa','1');
  p.innerHTML=butoanePodcast(f);
  if(f.parentNode)f.parentNode.replaceChild(p,f);return;}
 var n=dntVimeo(ds||src);
 if(n){f.setAttribute('data-spa-ok','1');if(ds)f.removeAttribute('data-src-cmplz');f.setAttribute('src',n);}
}
function iframeuri(r){try{(r||d).querySelectorAll('iframe').forEach(iframe);}catch(e){}}
function gol(el){return !el||!el.children.length;}
function curata(){
 var R=d.getElementById('ahsp')||d.body;if(!R)return;
 R.querySelectorAll('a[href]').forEach(function(a){
  if(a.closest('[data-spa]'))return;
  var h=a.getAttribute('href');if(!blocat(h))return;
  if(S.zona==='politici'&&a.closest('.entry-content,article')){var t=d.createElement('span');t.textContent=a.textContent;a.parentNode.replaceChild(t,a);return;} /* textul legal rămâne întreg, fără link */
  var bloc=a.closest('#ahsb,#rsg,.ah-bf-banda,#ah-bf-banda,#ah-bf-bara,#ahbf');
  if(bloc){scoate(bloc);return;}
  if(/\/v1\/portal|mergi=abonament/.test(h)){var p0=a.previousSibling;if(p0&&p0.nodeType===3)p0.nodeValue=p0.nodeValue.replace(/\s*·\s*$/,'');scoate(a);return;}
  var c=a.closest('.cov,li')||(a.matches('.rand,.tile,.continua,.rsg-c,.curs,.carte,.cov-btn')?a:null);
  if(c){scoate(c);return;}
  if(a.closest('#ahsp .lateral,#ahspc-lat,#ahspc-bara,.bara')){scoate(a);return;}
  var p=a.closest('p');
  if(p){scoate(p);return;}                               /* o frază care trimite spre cumpărare: pleacă întreagă */
  scoate(a);
 });
 R.querySelectorAll('#ahsp .cov.inchis,#ahsp .curs.inchis,#ahsp .cov-pret,#ahsp .med-desch').forEach(scoate);
 R.querySelectorAll('.ahs-mic').forEach(function(p){p.childNodes.forEach(function(n){if(n.nodeType===3&&n.nodeValue.indexOf('Abonament plătit până pe')>-1)n.nodeValue=n.nodeValue.replace('Abonament plătit până pe','Abonament activ până pe');});}); /* sala (224): doar starea. CIORNĂ */
 R.querySelectorAll('#ahsp .sectiune p.mic,#ahsp p.mic').forEach(function(p){var t=(p.textContent||'').trim();if(/^Oprești abonamentul/.test(t))scoate(p);
  else if(t.indexOf('emailul de la cumpărare')>-1&&!p.getAttribute('data-spa')){p.setAttribute('data-spa','1');p.textContent='Le citești aici, online. Fișierele pentru Kindle le găsești în emailul cu cartea.';}}); /* CIORNĂ */
 R.querySelectorAll('#ahsp .ci-but,#ahsp .mic-l,#ahspc-lat .mic').forEach(function(x){if(gol(x))scoate(x);});
 R.querySelectorAll('#ahsp .lista,#ahsp .cov-grila,#ahsp .cov-rand,#ahsp .grila').forEach(function(l){if(gol(l)){var s=l.closest('.sectiune');scoate(s&&s.querySelectorAll('.lista,.cov-grila,.cov-rand').length<=1?s:l);}});
 curataAplicatie();
}
function textNod(R,sel,vechi,nou){R.querySelectorAll(sel).forEach(function(el){el.childNodes.forEach(function(n){if(n.nodeType===3&&n.nodeValue.indexOf(vechi)>-1)n.nodeValue=n.nodeValue.replace(vechi,nou);});});}
/* Ce mai rămâne de potrivit în aplicație (texte, iconițe, sala de curs). CIORNĂ pentru texte. */
function curataAplicatie(){
 /* sala de curs: fără „Revino”, „Vreau să continui”, „Mai departe” și fără cutiile rămase doar cu titlul */
 d.querySelectorAll('.ahhb-lacat,#ah-dar7-curs,#ahs-urmator').forEach(scoate);
 d.querySelectorAll('p.ahs-mic').forEach(function(p){if(/Abonamentul îl oprești/.test(p.textContent||''))scoate(p);});
 d.querySelectorAll('.ahs-cutie').forEach(function(c){var k=[].slice.call(c.children);if(!k.length||k.every(function(x){return /^H[1-6]$/.test(x.tagName);}))scoate(c);});
 textNod(d,'.ahs-alte','Tot în comanda ta:','Tot de la tine:');
 /* „Mai mult” › Datele mele: fără „comenzi” */
 textNod(d,'#ahsp .rand .t span','Toate comenzile făcute cu această adresă apar aici.','Cursurile, cărțile și meditațiile de pe această adresă apar aici.');
 /* Abonamente: un calendar în locul cardului bancar */
 d.querySelectorAll('#ahsp path[d="M4 7h16v10H4zM4 11h16M8 15h3"]').forEach(function(x){x.setAttribute('d',IC.calendar);});
 /* Inner Temple: „Instrucțiuni” ca iconiță, pe copertă */
 d.querySelectorAll('#ahsp .cov-inst:not([data-spa-ic])').forEach(function(b){var c=b.closest('.cov'),t=c&&c.querySelector('.cov-t b'),n=t?'Instrucțiuni pentru „'+(t.textContent||'').trim()+'”':'Instrucțiuni';b.setAttribute('data-spa-ic','1');b.setAttribute('aria-label',n);b.setAttribute('title',n);b.innerHTML=ic('info');});
 /* Podcast: căștile în locul lui ▶ (episodul se ascultă în aplicația de podcasturi); pe Android fără Apple Podcasts */
 d.querySelectorAll('#ahsp .cov-pod .cov-play:not([data-spa])').forEach(function(x){x.setAttribute('data-spa','1');x.innerHTML=ic('casti');});
 if(platf()==='android')d.querySelectorAll('#ahsp a[href^="https://podcasts.apple.com"]').forEach(function(a){if(!a.closest('[data-spa]'))scoate(a);});
 /* sala, cărțile: aceeași bară de jos ca în Spațiul meu (Acasă, Ciclul, Cursuri, Inner Temple, Mai mult) */
 var B=d.getElementById('ahspc-bara');
 if(B&&!B.querySelector('a[href*="#meditatii"]')){var m=B.querySelector('a[href*="#mai"]'),c=B.querySelector('a[href*="#cursuri"]')||m;
  if(m&&c){var n=c.cloneNode(true);n.setAttribute('href',S.pagina+'#meditatii');n.removeAttribute('aria-current');n.setAttribute('data-spa','1');
   var pt=n.querySelector('path');if(pt)pt.setAttribute('d',IC.meditatii);
   var t=[].slice.call(n.childNodes).filter(function(x){return x.nodeType===3&&x.nodeValue.trim();})[0];if(t)t.nodeValue='Inner Temple';else{var sp=n.querySelector('.t');if(sp)sp.textContent='Inner Temple';}
   B.insertBefore(n,m);}}
}

/* ==================================================================
   3. CICLUL: aceeași regulă ca Ciclul meu (CI.d, CI.lung, CI.faze din fragmentul 235)
   ================================================================== */
/*CICLU-INCEPUT*/
var CX={
 zi0:function(s){var p=s.split('-');return Date.UTC(+p[0],p[1]-1,+p[2])/864e5;},
 azi0:function(){var n=new Date();return Date.UTC(n.getFullYear(),n.getMonth(),n.getDate())/864e5;},
 iso:function(z){return new Date(z*864e5).toISOString().slice(0,10);},
 /* CI.d: ahsp_ciclu + ah_ciclu_v1 (Harta ciclului, sala), cele mai noi 13 starturi, limitele 21–40 și 2–9 */
 d:function(x,v){v=v||{};var st=[];if(x&&x.starturi)st=st.concat(x.starturi);if(v.starturi)st=st.concat(v.starturi);if(v.s)st.push(v.s);st=st.filter(function(q,i){return /^\d{4}-\d{2}-\d{2}$/.test(q)&&st.indexOf(q)===i;}).sort().reverse().slice(0,13);if(!st.length)return null;var cl=function(n,a,b,dd){n=+n;return n>=a&&n<=b?n:dd;};return {starturi:st,lung:cl(x&&x.lung||v.c,21,40,28),men:cl(x&&x.men||v.m,2,9,5),sit:x&&x.sit};},
 lung:function(x){var st=x.starturi.map(CX.zi0),dif=[];for(var i=0;i+1<st.length&&dif.length<6;i++){var v=st[i]-st[i+1];if(v>=21&&v<=40)dif.push(v);}return dif.length>=2?Math.round(dif.reduce(function(a,b){return a+b;},0)/dif.length):(x.lung||28);},
 faze:function(L,m){var oS=Math.max(L-16,m+2),oE=Math.min(oS+4,L-2);return [{f:'iarna',a:1,b:m},{f:'primavara',a:m+1,b:oS-1},{f:'vara',a:oS,b:oE},{f:'toamna',a:oE+1,b:L}];},
 /* începuturile fazelor de azi încolo, pe următoarele `cicluri` cicluri; nimic cu pastile, în sarcină,
    înainte de prima zi sau când menstruația întârzie (atunci așteptăm ziua nouă notată de ea) */
 viitoare:function(x,azi,minAcum,cicluri,ora){
  if(!x||!x.starturi||!x.starturi.length)return [];
  if(x.sit==='hormonal'||x.sit==='sarcina')return [];
  cicluri=cicluri||3;ora=ora==null?9:ora;
  var L=CX.lung(x),m=x.men||5,s0=CX.zi0(x.starturi[0]),zi=azi-s0+1;
  if(zi<1||zi>L)return [];
  var fz=CX.faze(L,m),out=[],lim=azi+cicluri*L;
  for(var k=0;k<=cicluri+1;k++){fz.forEach(function(p){if(p.b<p.a)return;var z=s0+k*L+p.a-1;if(z<azi||z>lim)return;if(z===azi&&minAcum>=ora*60)return;out.push({f:p.f,zi:z,ziCiclu:p.a,ciclu:k});});}
  return out.sort(function(a,b){return a.zi-b.zi;});}
};
/*CICLU-SFARSIT*/
/* Textul mementourilor: neutru, pentru că apare și pe ecranul blocat (fără „menstruație” sau numele fazei; ce fază
   începe vede ea în Ciclul meu, unde o duce atingerea). Pe Android, în plus, canalul lor e „privat”. CIORNĂ, pentru Adela. */
var FT={t:'Ciclul meu',b:'Începe o fază nouă. Vezi ce îți aduce.'};
var CANAL='ah_ciclu',canalP=null;
function canal(){if(platf()!=='android'||!are('LocalNotifications','createChannel'))return Promise.resolve(false);
 if(!canalP)canalP=cheama('LocalNotifications','createChannel',{id:CANAL,name:'Mementourile ciclului',description:'O notificare la începutul fiecărei faze',importance:3,visibility:0}).then(function(){return true;},function(){canalP=null;return false;});
 return canalP;}
var NID=S.notif_id||41000;
function laOra(z,ora){var t=new Date(z*864e5);return new Date(t.getUTCFullYear(),t.getUTCMonth(),t.getUTCDate(),ora,0,0,0);}
function anuleazaCiclu(){
 var ids=[];for(var i=0;i<100;i++)ids.push({id:NID+i});
 return cheama('LocalNotifications','getPending').then(function(r){var p=((r&&r.notifications)||[]).filter(function(n){return +n.id>=NID&&+n.id<NID+100;}).map(function(n){return {id:+n.id};});return p.length?cheama('LocalNotifications','cancel',{notifications:p}):null;})
  .catch(function(){return cheama('LocalNotifications','cancel',{notifications:ids}).catch(function(){});});
}
var ciT=null, ciSem=null, ciSters=false;
/* O singură coadă pentru tot ce citește, anulează și programează mementourile (pornirea, Ciclul meu, comutatorul,
   ștergerea): două rulări suprapuse (getPending → cancel → schedule) ar putea lăsa mementouri după oprire. */
var ciQ=Promise.resolve();
function inCoada(f){var p=ciQ.then(f,f);ciQ=p.then(function(){},function(){});return p;}
function ciLocal(){return CX.d(ls('ahsp_ciclu'),ls('ah_ciclu_v1'));}
/* Refacerea: iOS (WKWebView, ITP) poate goli localStorage după câteva zile fără folosire; copia nativă din Preferences
   (ah_ciclu, scrisă mai jos, cu amprenta contului „cine”) rămâne. Dacă Ciclul meu nu are date în pagină, dar copia e a
   aceluiași cont, o punem înapoi în localStorage.ahsp_ciclu ÎNAINTE ca Spațiul meu să arate ciclul gol: #ahsp stă ascuns
   (cel mult 1,5 s) până răspunde Preferences; dacă 235 a desenat deja, îl punem să redeseneze (hashchange → arata()).
   Copia și mementourile NU se șterg aici. Se șterg doar la acțiuni explicite: „Șterge datele de pe acest telefon”
   (removeItem('ahsp_ciclu') când cheia exista, mai jos), ieșirea din cont și „Șterge contul” (curataLocal). */
/* amprenta contului de acum sau una veche a aceluiași cont (trecerea de la sărurile WordPress, „Mută contul”) */
function acelasi(c){return !!c&&(c===S.cine||(S.cine_vechi||[]).indexOf(c)>-1);}
function refacere(){
 if(!NATIV||!S.cont||S.zona!=='sp'||ciLocal()){H.classList.remove('ahspa-refac');return Promise.resolve(false);}
 H.classList.add('ahspa-refac');
 var gata=function(r){H.classList.remove('ahspa-refac');return r;};
 var ceas=new Promise(function(r){setTimeout(function(){r('timp');},1500);});
 return Promise.race([PREF.ia('ah_ciclu'),ceas]).then(function(v){
  if(v==='timp'||!v||typeof v!=='object'||!Array.isArray(v.starturi))return gata(false);
  if(!acelasi(v.cine)){PREF.scoate('ah_ciclu');return anuleazaCiclu().then(function(){return gata(false);},function(){return gata(false);});} /* copia altui cont de pe acest telefon: nu e a ei */
  var x=CX.d({starturi:v.starturi,lung:v.lung,men:v.men,sit:v.sit},null);
  if(!x||ciLocal())return gata(false); /* între timp pagina a scris singură ceva: nu suprascriem */
  try{localStorage.setItem('ahsp_ciclu',JSON.stringify({starturi:x.starturi,lung:x.lung,men:x.men,sit:x.sit||'natural'}));}catch(e){return gata(false);}
  var R=d.getElementById('ahsp');
  if(R&&R.children.length){try{window.dispatchEvent(new HashChangeEvent('hashchange'));}catch(e){}} /* 235 desenase fără date */
  return gata(true);
 }).catch(function(){return gata(false);});
}
/* Alt cont pe acest telefon (partea „devreme” a șters deja localStorage-ul celei dinainte): Preferences, mementourile și
   descărcările ei pleacă și ele, înainte de refacere și de mementourile contului de acum. */
function curataContVechi(){
 if(!window.AHSPA_ALT_CONT||!NATIV)return Promise.resolve();
 return Promise.all(['ah_ciclu','ah_ciclu_notif','ah_push_on','ah_push_intrebat','ah_deschideri'].map(function(k){return PREF.scoate(k);})
  .concat([anuleazaCiclu().catch(function(){}),DL.stergeTot().catch(function(){})])).catch(function(){});
}
/* „Șterge contul” întrerupt (aplicația închisă după răspunsul serverului, înainte de curățenie): Preferences.ah_sterge
   rămâne, deci la pornirea următoare curățăm tot de pe telefon, înaintea refacerii (care altfel ar aduce ciclul înapoi). */
function stergereRamasa(){
 if(!NATIV)return Promise.resolve();
 return PREF.ia('ah_sterge').then(function(v){if(v==null||v==='')return;return curataLocal(true,true).then(function(){return PREF.scoate('ah_sterge');});});
}
/* în microtask: DL (secțiunea 5) e definit abia mai jos */
var REF=inCoada(function(){return Promise.resolve().then(stergereRamasa).then(curataContVechi).then(refacere);});
/* Mementourile: pornite DOAR dacă ea a spus da (ah_ciclu_notif = 1, pus la „Da, anunță-mă” sau din comutator). Pe Android ≤ 12
   permisiunea e „granted” fără să fi întrebat-o cineva, deci permisiunea singură nu ajunge. Instalările de dinainte de 2.2.0
   (fără cheie) rămân pornite dacă ea spusese da notificărilor (ah_push_on = 1). */
function mementouriPornite(notif,pushOn){return +notif===1||(notif==null&&+pushOn===1);}
function ciclu(forteaza){
 return inCoada(function(){return REF.then(function(){
 if(!S.cont)return; /* fără cont nu schimbăm nimic aici: ieșirea și cookie-ul expirat le curăță curataLocal() */
 var x=ciLocal();
 return Promise.all([PREF.ia('ah_ciclu_notif'),cheama('LocalNotifications','checkPermissions').catch(function(){return {};}),PREF.ia('ah_push_on')]).then(function(r){
  var on=mementouriPornite(r[0],r[2]),perm=!!(r[1]&&r[1].display==='granted');
  /* și fusul orar: după o călătorie (alt fus), mementourile de la 9:00 se reprogramează pe ora locului */
  var sem=JSON.stringify([x,on,perm,S.cont,ciSters,CX.azi0(),new Date().getTimezoneOffset()]);
  if(!forteaza&&sem===ciSem)return;ciSem=sem;
  if(x)PREF.pune('ah_ciclu',{starturi:x.starturi,lung:x.lung,men:x.men,sit:x.sit||'natural',cine:S.cine,actualizat:new Date().toISOString()});
  else if(ciSters)PREF.scoate('ah_ciclu');
  else return; /* localStorage gol fără o ștergere cerută de ea (iOS a golit stocarea): copia și mementourile rămân */
  return anuleazaCiclu().then(function(){
   if(!on||!perm||!x)return;
   var n=new Date(),v=CX.viitoare(x,CX.azi0(),n.getHours()*60+n.getMinutes(),3,9).slice(0,100);
   if(!v.length)return;
   return canal().then(function(c){return cheama('LocalNotifications','schedule',{notifications:v.map(function(q,i){var n={id:NID+i,title:FT.t,body:FT.b,schedule:{at:laOra(q.zi,9),allowWhileIdle:true},extra:{url:S.pagina+'#ciclu',faza:q.f}};if(c)n.channelId=CANAL;return n;})});});
  });
 });
 });}).catch(function(){});
}
function ciCurand(){clearTimeout(ciT);ciT=setTimeout(function(){ciclu(false);},900);}
/* Ciclul meu scrie în localStorage: aflăm imediat (fără să schimbăm codul paginii). O ștergere a lui ahsp_ciclu care
   exista (butonul „Șterge datele de pe acest telefon” din 235, sau curataLocal) = ștergere cerută: copia nativă și
   mementourile pleacă pe loc, nu după 900 ms (aplicația se poate închide între timp și refacerea le-ar aduce înapoi). */
try{['setItem','removeItem'].forEach(function(m){var o=Storage.prototype[m];Storage.prototype[m]=function(k){var era=false;
 try{era=m==='removeItem'&&k==='ahsp_ciclu'&&this===window.localStorage&&this.getItem(k)!==null;}catch(e){}
 var r=o.apply(this,arguments);
 try{if(era){ciSters=true;if(NATIV){PREF.scoate('ah_ciclu');inCoada(anuleazaCiclu);}}if(this===window.localStorage&&(k==='ahsp_ciclu'||k==='ah_ciclu_v1'))ciCurand();}catch(e){}return r;};});}catch(e){}

/* ==================================================================
   4. NOTIFICĂRILE PUSH: o singură întrebare, la un moment potrivit, cu explicație
   ================================================================== */
var DISP='';
/* Push doar dacă aplicația are Firebase: PushNotifications.register()/unregister() fără Firebase opresc aplicația
   pe Android (FirebaseMessaging.getInstance()). AhApp.info().push spune asta; fără AhApp (aplicație veche) = fără push.
   Rămâne așa până când Firebase e configurat pe ambele platforme (și după, nu strică). */
var PUSH={
 dispP:null,
 /* AhApp.info().token: tipul tokenului pe care îl dă build-ul ('fcm' pe Android și pe iPhone cu Firebase Messaging;
    'apns' pe un iPhone fără Firebase). Serverul trimite doar prin FCM, deci fără 'fcm' nu cerem nimic. */
 disponibil:function(){if(!PUSH.dispP)PUSH.dispP=(NATIV?cheama('AhApp','info').then(function(r){return !!(r&&r.push===true&&(r.token==null||r.token==='fcm'));}):Promise.resolve(false)).catch(function(){return false;});return PUSH.dispP;},
 /* tipul unui token primit: tokenul APNs brut (64 de cifre hexa, iPhone fără Firebase) sau FCM (Android, iPhone cu Firebase) */
 fel:function(tok){return /^[0-9a-fA-F]{64}$/.test(String(tok||''))?'apns':'fcm';},
 platforma:function(){var p=CAP&&typeof CAP.getPlatform==='function'?CAP.getPlatform():'';return p==='ios'||p==='android'?p:S.platforma;},
 apns:false,
 /* „registration” de la @capacitor/push-notifications: doar un token FCM ajunge la server (pe iPhone, AppDelegate îl
    dă prin Firebase Messaging); un token APNs nu i-ar folosi la nimic (serverul l-ar refuza cu motiv: apns). */
 primit:function(tok){if(PUSH.fel(tok)!=='fcm'){PUSH.apns=true;return Promise.resolve();}return PUSH.trimite(tok);},
 stare:function(){return cheama('PushNotifications','checkPermissions').then(function(r){return (r&&r.receive)||'prompt';}).catch(function(){return 'indisponibil';});},
 legat:false,
 leaga:function(){if(PUSH.legat)return;PUSH.legat=true;
  asculta('PushNotifications','registration',function(t){if(t&&t.value)PUSH.primit(t.value);});
  asculta('PushNotifications','pushNotificationActionPerformed',function(a){var u=a&&a.notification&&a.notification.data&&a.notification.data.url;if(u&&permis(u))mergi(u);});
  asculta('LocalNotifications','localNotificationActionPerformed',function(a){var u=a&&a.notification&&a.notification.extra&&a.notification.extra.url;if(u&&permis(u))mergi(u);});},
 porneste:function(){return PUSH.disponibil().then(function(ok){if(!ok)return;PUSH.leaga();return cheama('PushNotifications','register');}).catch(function(){});},
 trimite:function(tok){if(!S.cont)return Promise.resolve();
  return PREF.ia('ah_push').then(function(v){var acum=Date.now();
   if(v&&v.token===tok&&PUSH.al(v)==='ea'&&v.cine===S.cine&&acum-(v.la||0)<7*864e5)return; /* (cine vechi: se retrimite o dată, ca serverul să lege tokenul de adresa de acum) */
   /* contul activ pe telefon e al ei (aplicația deschide contul ei când e intrată): un token al lui trece la ea (serverul îl scoate din tabelul lui) */
   return api('push',{metoda:'POST',corp:{token:tok,platforma:PUSH.platforma(),versiune:S.versiune,disp:DISP}}).then(function(j){if(j&&j.ok)return PREF.pune('ah_push',{token:tok,cont:'ea',cine:S.cine,la:acum});});});},
 /* Al cui e tokenul telefonului (Preferences.ah_push.cont): 'ea' (puntea aceasta) sau 'el' (pagina /pentru-el/ a partenerului,
    ah-sp-partener.php). Rândurile scrise înainte de contul pentru el nu au „cont”: sunt ale ei. */
 al:function(v){return v&&v.cont==='el'?'el':'ea';},
 /* Scoate tokenul lui de la /ah-spp/v1: POST /push/sterge (2.3.0, cu antetul lui X-AH-SPP); un fragment al lui mai vechi
    (404/405) primește DELETE, ca înainte. */
 scoateEl:function(tok){var h={'Content-Type':'application/json','X-AH-SPP':'1'},b=JSON.stringify({token:tok});
  return fetch(S.api_el+'push/sterge',{method:'POST',credentials:'same-origin',cache:'no-store',headers:h,body:b})
   .then(function(r){if(r.status===404||r.status===405)return fetch(S.api_el+'push',{method:'DELETE',credentials:'same-origin',cache:'no-store',headers:h,body:b});}).catch(function(){});},
 /* Scoate tokenul ei (POST /ah-spa/v1/push/sterge). Al lui rămâne cât timp e intrat pe acest telefon (S.el); altfel pleacă și el.
    doarEl: pe o pagină fără cont, doar tokenul rămas al lui (al ei rămâne: e tot telefonul ei, notificările o duc la intrare). */
 opreste:function(doarEl){var scos=false;return PREF.ia('ah_push').then(function(v){if(!v||!v.token)return;
   if(PUSH.al(v)==='el'){if(S.el||!S.api_el)return;scos=true;return PUSH.scoateEl(v.token).then(function(){return PREF.scoate('ah_push');});}
   if(doarEl)return;scos=true;return api('push/sterge',{metoda:'POST',corp:{token:v.token}}).then(function(){return PREF.scoate('ah_push');});})
  .then(function(){return scos?PUSH.disponibil():false;}).then(function(ok){if(ok)return cheama('PushNotifications','unregister').catch(function(){});});}
};
var foaieN=0;
/* Foile punții (întrebările, ștergerea): dialog modal adevărat. Fundalul (#ahsp > tot în afară de foi) devine inert, Tab
   se rotește în foaie, Escape = „Renunț” / „Închide” / „Am înțeles”, iar la închidere (scoate) focusul se întoarce la
   butonul care a deschis-o. Foaia cea mai nouă e cea activă. */
function focusabile(f){return [].slice.call(f.querySelectorAll('button,input,a[href],select,textarea,[tabindex]:not([tabindex="-1"])')).filter(function(x){return !x.disabled&&x.offsetParent!==null&&x.getAttribute('tabindex')!=='-1';});}
function foaie(html,cls){var R=d.getElementById('ahsp')||d.body,f=d.createElement('div'),id='ahspa-f'+(++foaieN),deschis=d.activeElement,inerte=[];f.className='ahspa-foaie ahspa-ui'+(cls?' '+cls:'');f.setAttribute('data-spa','1');
 f.innerHTML='<div class="c" role="dialog" aria-modal="true" aria-labelledby="'+id+'">'+html.replace('<h2>','<h2 id="'+id+'">')+'</div>';R.appendChild(f);
 [].forEach.call(R.children,function(c){if(c!==f&&!c.classList.contains('ahspa-foaie')&&!c.hasAttribute('inert')){c.setAttribute('inert','');inerte.push(c);}});
 f.__ahInchide=function(){inerte.forEach(function(c){c.removeAttribute('inert');});if(deschis&&deschis.focus&&d.contains(deschis)){try{deschis.focus({preventScroll:true});}catch(e){}}};
 setTimeout(function(){var b=f.querySelector('button:not([disabled]),input');if(b)b.focus();},60);return f;}
d.addEventListener('keydown',function(e){var l=d.querySelectorAll('.ahspa-foaie');if(!l.length)return;var f=l[l.length-1];
 if(e.key==='Escape'||e.key==='Esc'){var n=f.querySelector('[data-a="nu"],[data-a="inchide"],[data-a="ok"]');if(n){e.preventDefault();n.click();}return;}
 if(e.key!=='Tab')return;var q=focusabile(f);if(!q.length){e.preventDefault();return;}var a=q[0],z=q[q.length-1],x=d.activeElement;
 if(!f.contains(x)){e.preventDefault();(e.shiftKey?z:a).focus();return;}
 if(e.shiftKey&&x===a){e.preventDefault();z.focus();}else if(!e.shiftKey&&x===z){e.preventDefault();a.focus();}},true);
/* O întrebare cu o acțiune roșie și „Renunț” (în locul ferestrei confirm() a sistemului). */
function confirma(titlu,text,da){return new Promise(function(gata){var f=foaie('<h2>'+esc(titlu)+'</h2><p>'+esc(text)+'</p><div class="b"><button type="button" data-a="da">'+esc(da)+'</button><button type="button" class="lite" data-a="nu">Renunț</button></div>');
 f.addEventListener('click',function(e){var b=e.target.closest('[data-a]');if(!b)return;scoate(f);gata(b.getAttribute('data-a')==='da');});});}
function intreabaPush(dinMeniu){
 return PUSH.disponibil().then(function(ok){if(!ok)return false;return new Promise(function(gata){
  var f=foaie('<h2>Vrei să-ți dăm de veste?</h2><p>Îți trimitem o notificare doar când apare o meditație nouă, când se apropie o întâlnire la care te-ai înscris, când te așteaptă o înregistrare și la începutul fiecărei faze a ciclului tău.</p><p>Le poți opri oricând din „Mai mult”.</p><div class="b"><button type="button" data-a="da">Da, anunță-mă</button><button type="button" class="lite" data-a="nu">Nu acum</button></div>'); /* CIORNĂ */
  f.addEventListener('click',function(e){var b=e.target.closest('[data-a]');if(!b)return;var da=b.getAttribute('data-a')==='da';scoate(f);PREF.pune('ah_push_intrebat',1);
   if(!da){gata(false);return;}
   cheama('PushNotifications','requestPermissions').then(function(r){var ok=r&&r.receive==='granted';if(ok){PREF.pune('ah_push_on',1);PUSH.porneste();Promise.all([PREF.pune('ah_ciclu_notif',1),cheama('LocalNotifications','requestPermissions').catch(function(){})]).then(function(){ciclu(true);});}gata(ok);}).catch(function(){gata(false);});
  });
 });});
}
function momentPush(){
 if(!S.cont||!NATIV||S.zona!=='sp')return;
 Promise.all([PREF.ia('ah_push_intrebat'),PUSH.stare(),PREF.ia('ah_deschideri'),PREF.ia('ah_push_on'),PUSH.disponibil()]).then(function(r){
  if(!r[4])return; /* aplicația n-are Firebase: nici întrebare, nici înregistrare */
  if(+r[3]===1){if(r[1]==='granted')PUSH.porneste();return;} /* a spus da */
  if(r[0]||r[3]!=null)return; /* a răspuns deja (sau a oprit din „Mai mult”) */
  /* n-a fost întrebată: pe Android ≤ 12 permisiunea e „granted” fără nicio întrebare, deci întrebăm noi, cu explicația */
  if((r[1]!=='granted'&&r[1]!=='prompt'&&r[1]!=='prompt-with-rationale')||(r[2]||0)<2)return; /* nu la prima deschidere */
  setTimeout(function(){if(!d.querySelector('#ahsp .salut')||d.querySelector('.ahspa-foaie,#ahsp .foaie.arata,#ahsp .player.arata,#ahsp .spx-acord'))return;intreabaPush(false);},2500);
 });
}

/* ==================================================================
   5. MEDITAȚIILE DESCĂRCATE (ascultate și fără internet, din pagina offline a aplicației)
   ================================================================== */
var DIR=S.platforma==='ios'?'LIBRARY_NO_CLOUD':'DATA'; /* iOS: Library, fără copie în iCloud; Android: datele aplicației */
var DL={idx:null,lucru:{},
 incarca:function(){if(DL.idx)return Promise.resolve(DL.idx);return PREF.ia('ah_descarcate').then(function(v){DL.idx=Array.isArray(v)?v:[];return DL.idx;});},
 rand:function(id){return (DL.idx||[]).filter(function(r){return +r.id===+id;})[0]||null;},
 salveaza:function(){return PREF.pune('ah_descarcate',DL.idx||[]);},
 cale:function(p){return cheama('Filesystem','getUri',{path:p,directory:DIR}).then(function(r){return r.uri;});},
 progres:{},legat:false,
 descarca:function(id){
  if(DL.lucru[id])return DL.lucru[id];
  var j0;
  var tinta='';
  DL.lucru[id]=api('descarca?id='+encodeURIComponent(id)).then(function(j){
   if(!j||!j.ok||!j.url){var er=new Error((j&&j.mesaj)||'');er.ro=!!(j&&j.mesaj);throw er;}j0=j; /* er.ro: mesajul (în română) vine de la server */
   if(!DL.legat&&are('FileTransfer','addListener')){DL.legat=true;asculta('FileTransfer','progress',function(e){var f=e&&DL.progres[e.url];if(f&&e.lengthComputable&&e.contentLength)f(Math.round(e.bytes*100/e.contentLength));});}
   DL.progres[j.url]=function(p){arataDl(id,{p:p});};
   var f='meditatii/'+id+'.'+(j.ext||'mp3');tinta=f;
   return cheama('Filesystem','mkdir',{path:'meditatii',directory:DIR,recursive:true}).catch(function(){})
    .then(function(){return DL.cale(f);})
    .then(function(u){return cheama('FileTransfer','downloadFile',{url:j.url,path:u,progress:true});})
    .then(function(){var poza='';if(!j.imagine)return poza;var fp='meditatii/'+id+'.jpg';return DL.cale(fp).then(function(u){return cheama('FileTransfer','downloadFile',{url:j.imagine,path:u});}).then(function(){return fp;}).catch(function(){return '';});})
    .then(function(poza){return DL.incarca().then(function(){DL.idx=DL.idx.filter(function(r){return +r.id!==+id;});DL.idx.push({id:+id,titlu:j.titlu||'',durata:j.durata||0,fisier:f,poza:poza,dir:DIR,descarcat:new Date().toISOString()});return DL.salveaza();});});
  }).then(function(){delete DL.lucru[id];if(j0)delete DL.progres[j0.url];},function(e){delete DL.lucru[id];if(j0)delete DL.progres[j0.url];
   /* descărcarea s-a oprit la jumătate: fișierul neterminat nu rămâne pe telefon (n-ar avea rând în index, dar ar ocupa loc) */
   var sterge=tinta?[tinta,'meditatii/'+id+'.jpg'].map(function(x){return cheama('Filesystem','deleteFile',{path:x,directory:DIR}).catch(function(){});}):[];
   return Promise.all(sterge).then(function(){throw e;});});
  return DL.lucru[id];
 },
 /* Rândurile din index al căror fișier nu mai e pe telefon (șters de sistem, de o curățare, o descărcare veche ruptă) pleacă. O dată pe pagină. */
 verificat:null,
 verifica:function(){if(DL.verificat)return DL.verificat;
  DL.verificat=DL.incarca().then(function(l){if(!l.length||!are('Filesystem','stat'))return l;
   return Promise.all(l.map(function(r){return cheama('Filesystem','stat',{path:r.fisier,directory:r.dir||DIR}).then(function(){return true;},function(){return false;});})).then(function(ok){
    var noi=DL.idx.filter(function(r){var i=l.indexOf(r);return i<0||ok[i];});if(noi.length===DL.idx.length)return DL.idx;
    var lipsa=DL.idx.filter(function(r){return noi.indexOf(r)<0;});DL.idx=noi;
    lipsa.forEach(function(r){if(r.poza)cheama('Filesystem','deleteFile',{path:r.poza,directory:r.dir||DIR}).catch(function(){});});
    return DL.salveaza().then(function(){return DL.idx;});});}).catch(function(){return DL.idx||[];});
  return DL.verificat;},
 sterge:function(id){return DL.incarca().then(function(){var r=DL.rand(id);DL.idx=DL.idx.filter(function(q){return +q.id!==+id;});
  var p=[DL.salveaza()];if(r){[r.fisier,r.poza].forEach(function(f){if(f)p.push(cheama('Filesystem','deleteFile',{path:f,directory:r.dir||DIR}).catch(function(){}));});}return Promise.all(p);});},
 stergeTot:function(){return DL.incarca().then(function(){var t=(DL.idx||[]).slice();DL.idx=[];if(!t.length)return PREF.scoate('ah_descarcate');return Promise.all(t.map(function(r){return Promise.all([r.fisier,r.poza].filter(Boolean).map(function(f){return cheama('Filesystem','deleteFile',{path:f,directory:r.dir||DIR}).catch(function(){});}));})).then(function(){return cheama('Filesystem','rmdir',{path:'meditatii',directory:DIR,recursive:true}).catch(function(){});}).then(function(){return PREF.scoate('ah_descarcate');});});}
};
/* Titlul meditației de pe copertă (pentru numele accesibile ale butoanelor). */
function titluMed(id){var c=d.querySelector('#ahsp .cov[data-id="'+id+'"]'),b=c&&c.querySelector('.cov-t b');if(b)return (b.textContent||'').trim();var r=DL.rand(id);return r&&r.titlu||'';}
/* Un singur loc anunțat de cititorul de ecran (role=status) pentru descărcări: începutul, jumătatea, sfârșitul, eroarea. */
var anuntat={};
function anunta(t){var el=d.getElementById('ahspa-sr');if(!el){el=d.createElement('p');el.id='ahspa-sr';el.className='ahspa-sr';el.setAttribute('role','status');el.setAttribute('aria-live','polite');el.setAttribute('data-spa','1');d.body.appendChild(el);}el.textContent='';setTimeout(function(){el.textContent=t;},50);}
/* Textele de lângă fiecare meditație. CIORNĂ. „Descărcat” și „Șterge” pe un singur rând (aceeași înălțime a cartonașelor). */
function arataDl(id,st){
 var tt=titluMed(id),q='„'+tt+'”';
 if(st&&st.p!=null){var tr=st.p>=50?50:0;if(anuntat[id]!==tr){anuntat[id]=tr;anunta(tr?'Descărcarea e la jumătate: '+q+'.':'Se descarcă '+q+'.');}}
 else if(st&&st.err){delete anuntat[id];anunta('N-a mers descărcarea: '+q+'.');}
 else if(anuntat[id]!=null&&DL.rand(id)){delete anuntat[id];anunta(q+' e descărcată pe telefon.');}
 d.querySelectorAll('#ahsp .spa-dl[data-dl="'+id+'"]').forEach(function(el){
  var r=DL.rand(id),h;
  if(st&&st.p!=null)h='<button type="button" disabled>'+ic('jos')+'Se descarcă… '+st.p+'%</button>';
  else if(DL.lucru[id])h='<button type="button" disabled>'+ic('jos')+'Se descarcă…</button>';
  else if(r)h='<span class="gata">'+ic('bifa')+'Descărcat</span><button type="button" class="leg" data-spa-sterge="'+id+'" aria-label="Șterge descărcarea '+esc(q)+'">Șterge</button>';
  else h='<button type="button" data-spa-desc="'+id+'" aria-label="Descarcă '+esc(q)+' pe telefon">'+ic('jos')+'Descarcă</button>';
  if(st&&st.err)h+='<span class="err">'+esc(st.err)+'</span>';
  el.innerHTML=h;
 });
}
function meditatii(){
 if(!NATIV||!are('FileTransfer','downloadFile'))return;
 var c=d.querySelectorAll('#ahsp .cov[data-id]');if(!c.length)return;
 DL.verifica().then(function(){
  c.forEach(function(cv){if(cv.querySelector('.spa-dl')||!cv.querySelector('[data-play]'))return;var id=cv.getAttribute('data-id'),el=d.createElement('div');el.className='spa-dl';el.setAttribute('data-spa','1');el.setAttribute('data-dl',id);var b=cv.querySelector('.cov-btn');if(b&&b.nextSibling)cv.insertBefore(el,b.nextSibling);else cv.appendChild(el);arataDl(id);});
 });
}
d.addEventListener('click',function(e){
 var b=e.target.closest&&e.target.closest('[data-spa-desc],[data-spa-sterge]');if(!b)return;e.preventDefault();
 var id=b.getAttribute('data-spa-desc')||b.getAttribute('data-spa-sterge');
 if(b.hasAttribute('data-spa-sterge')){DL.sterge(id).then(function(){arataDl(id);anunta('Descărcarea „'+titluMed(id)+'” a fost ștearsă.');var n=d.querySelector('#ahsp .spa-dl[data-dl="'+id+'"] [data-spa-desc]');if(n)n.focus();});return;}
 arataDl(id,{p:0});
 /* mesajul serverului e în română; orice altă eroare (pluginul nativ, rețeaua, permisiunea pe Android 7–10) primește textul nostru */
 DL.descarca(id).then(function(){arataDl(id);},function(er){arataDl(id,{err:(er&&er.ro&&er.message?er.message:'N-a mers descărcarea. Verifică internetul și încearcă din nou.')});});
});

/* ==================================================================
   6. ECRANUL BLOCAT: Media Session pe orice sunet pornit în pagină (Inner Temple, sala de curs)
   ================================================================== */
var MS=null;
/* Titlul și poza piesei, din playerul Spațiului meu (235). */
function metaSunet(){
 var t=d.querySelector('#ahsp .player b'),im=d.querySelector('#ahsp .player .pl-img'),bg=im?(im.style.backgroundImage||'').replace(/^url\(["']?|["']?\)$/g,''):'';
 return {titlu:(t&&t.textContent)||d.title||'Adela Haru',poza:bg};
}
/* Android: WebView-ul nu leagă navigator.mediaSession de sistem și nu are serviciu de prim-plan, așa că Android poate
   opri sunetul în fundal. Pluginul AhMedia al aplicației ține serviciul și comenzile de pe ecranul blocat / din
   notificare / de pe căști: îi trimitem starea <audio> și primim înapoi apăsările. Pe iOS face WebKit totul singur. */
var AM={el:null,ceas:0,ascult:false,
 are:function(){var p=CAP&&typeof CAP.getPlatform==='function'?CAP.getPlatform():S.platforma;return NATIV&&p==='android'&&are('AhMedia','actualizeaza');}, /* AhMedia există doar în aplicația Android */
 trimite:function(){var a=AM.el;if(!a||!AM.are())return;var m=metaSunet(),x=m.poza?abs(m.poza):null,canta=!a.paused&&!a.ended;
  /* cât cântă: o actualizare la 15 s (poziția de pe ecranul blocat) */
  if(canta&&!AM.ceas)AM.ceas=setInterval(AM.trimite,15000);else if(!canta&&AM.ceas){clearInterval(AM.ceas);AM.ceas=0;}
  cheama('AhMedia','actualizeaza',{titlu:m.titlu,artist:'Adela Haru',poza:x&&x.protocol==='https:'?x.href:null,durata:a.duration&&isFinite(a.duration)?a.duration:0,pozitie:a.currentTime||0,canta:canta}).catch(function(){});},
 opreste:function(){if(AM.ceas){clearInterval(AM.ceas);AM.ceas=0;}if(AM.are())cheama('AhMedia','opreste').catch(function(){});},
 lega:function(a){
  if(!AM.ascult){AM.ascult=true;
   asculta('AhMedia','actiune',function(x){var e=AM.el;if(!e||!x)return;
    if(x.tip==='play')e.play();else if(x.tip==='pause')e.pause();
    else if(x.tip==='inainte')e.currentTime=Math.min(e.duration||1e9,e.currentTime+15);
    else if(x.tip==='inapoi')e.currentTime=Math.max(0,e.currentTime-15);
    else if(x.tip==='seek'&&x.pozitie!=null)e.currentTime=+x.pozitie||0;
    else if(x.tip==='stop'){e.pause();AM.opreste();}});
   window.addEventListener('pagehide',function(){AM.opreste();});}
  if(AM.el!==a){AM.el=a;
   var f=function(){if(AM.el===a)AM.trimite();};
   ['play','playing','pause','seeked','loadedmetadata'].forEach(function(n){a.addEventListener(n,f);});
   a.addEventListener('ended',function(){if(AM.el===a)AM.opreste();});}
  AM.trimite();
 }
};
/* Playerul lui 235: o regiune cu nume, iar poziția se citește ca timp („0:07 din 0:30”), nu ca număr din 1000. */
function ceasM(x){x=Math.max(0,Math.floor(x||0));var m=Math.floor(x/60),s=x%60;return m+':'+(s<10?'0':'')+s;}
function playerA11y(a){
 var pl=d.querySelector('#ahsp .player');if(!pl)return;
 if(pl.getAttribute('role')!=='region'){pl.setAttribute('role','region');pl.setAttribute('aria-label','Player');}
 var r=pl.querySelector('input[type=range]');if(!r)return;
 if(!r.getAttribute('aria-label'))r.setAttribute('aria-label','Poziția în meditație');
 var dur=a&&a.duration&&isFinite(a.duration)?a.duration:0;r.setAttribute('aria-valuetext',ceasM(a?a.currentTime:0)+(dur?' din '+ceasM(dur):''));
}
var A11Y=typeof WeakSet==='function'?new WeakSet():null;
function mediaSesiune(a){
 if(!a)return;
 if(A11Y&&!A11Y.has(a)){A11Y.add(a);var u=function(){playerA11y(a);};['timeupdate','loadedmetadata','seeked'].forEach(function(n){a.addEventListener(n,u);});}
 playerA11y(a);
 if(AM.are())AM.lega(a);
 if(!('mediaSession' in navigator))return;var ms=navigator.mediaSession;
 try{
  var mt=metaSunet(),bg=mt.poza,titlu=mt.titlu;
  if(window.MediaMetadata&&(!ms.metadata||ms.metadata.title!==titlu||(!ms.metadata.artwork||!ms.metadata.artwork.length)&&bg))ms.metadata=new MediaMetadata({title:titlu,artist:'Adela Haru',album:'Inner Temple',artwork:bg?[{src:bg,sizes:'512x512'}]:[]});
 }catch(e){}
 if(MS===a)return;MS=a;
 var set=function(n,f){try{ms.setActionHandler(n,f);}catch(e){}};
 set('play',function(){a.play();});set('pause',function(){a.pause();});
 set('seekbackward',function(x){a.currentTime=Math.max(0,a.currentTime-((x&&x.seekOffset)||15));});
 set('seekforward',function(x){a.currentTime=Math.min(a.duration||1e9,a.currentTime+((x&&x.seekOffset)||15));});
 set('seekto',function(x){if(x&&x.seekTime!=null)a.currentTime=x.seekTime;});
 set('previoustrack',null);set('nexttrack',null);
 var poz=function(){try{if(a.duration&&isFinite(a.duration)&&ms.setPositionState)ms.setPositionState({duration:a.duration,playbackRate:a.playbackRate||1,position:Math.min(a.currentTime,a.duration)});}catch(e){}};
 a.addEventListener('play',function(){try{ms.playbackState='playing';}catch(e){}poz();});
 a.addEventListener('pause',function(){try{ms.playbackState='paused';}catch(e){}poz();});
 a.addEventListener('loadedmetadata',poz);a.addEventListener('seeked',poz);
}
try{var _play=HTMLMediaElement.prototype.play;HTMLMediaElement.prototype.play=function(){var a=this;try{if(a.tagName==='AUDIO'||!a.tagName)setTimeout(function(){mediaSesiune(a);},0);}catch(e){}return _play.apply(this,arguments);};}catch(e){}

/* ==================================================================
   7. „MAI MULT” › APLICAȚIA: notificări, mementouri, descărcări, ștergerea contului
   ================================================================== */
/* Rândurile din „Mai mult” sunt butoane adevărate (comutatoarele: role=switch, aria-checked), cu aspectul rândurilor din 235. */
function sw(id,ico,t,s,on){return '<button type="button" class="rand spa-b" role="switch" aria-checked="'+(on?'true':'false')+'" id="'+id+'" data-spa="1"><span class="ic-mic">'+ic(ico)+'</span><span class="t"><b>'+t+'</b><span>'+s+'</span></span><span class="ahspa-sw" aria-hidden="true"><i></i></span></button>';}
function rb(id,ico,t,s,cls){return '<button type="button" class="rand spa-b'+(cls?' '+cls:'')+'" id="'+id+'" data-spa="1"><span class="ic-mic">'+ic(ico)+'</span><span class="t"><b>'+t+'</b><span>'+s+'</span></span></button>';}
function mai(){
 var iesi=d.getElementById('ahsp-iesi');if(!iesi||!S.cont)return;
 var sec=iesi.closest('.sectiune');if(!sec||!sec.parentNode)return;
 var spx=d.querySelector('#ahsp .spx-sec .lista'), loc=d.querySelector('#ahsp .spa-mai');
 if(loc&&spx&&!spx.contains(loc)){spx.appendChild(loc);var vechi=d.querySelector('#ahsp .spa-sec');if(vechi)scoate(vechi);return;}
 if(loc)return;
 loc=d.createElement('div');loc.className='spa-mai';loc.style.display='contents';loc.setAttribute('data-spa','1');
 if(spx)spx.appendChild(loc);
 else{var w=d.createElement('div');w.className='sectiune spa-sec';w.setAttribute('data-spa','1');w.innerHTML='<h2>Aplicația</h2><div class="lista"></div>';sec.parentNode.insertBefore(w,sec);w.querySelector('.lista').appendChild(loc);}
 Promise.all([PUSH.stare(),PREF.ia('ah_push_on'),PREF.ia('ah_ciclu_notif'),DL.incarca(),PUSH.disponibil()]).then(function(r){
  var pOn=r[0]==='granted'&&+r[1]===1,cOn=mementouriPornite(r[2],r[1]),n=(r[3]||[]).length;
  /* CIORNĂ */
  loc.innerHTML=(NATIV?(r[4]?sw('spa-push','clopot','Notificări','Meditații noi, întâlniri și înregistrări',pOn):'')+sw('spa-ciclu','ciclu','Mementourile ciclului','O notificare la începutul fiecărei faze',cOn&&r[0]==='granted'):'')+
   (n?rb('spa-dl-tot','nota','Meditații descărcate',n===1?'O meditație pe acest telefon. Atinge ca s-o ștergi.':n+' meditații pe acest telefon. Atinge ca să le ștergi.'):'')+
   /* contul pentru el: adresa ei are și acces la ciclul cuiva (ah_spa_are_acces_el) sau el e intrat pe acest telefon */
   (S.el&&S.pagina_el?'<a href="'+esc(S.pagina_el)+'" class="rand" id="spa-el" data-spa="1"><span class="ic-mic">'+ic('luna')+'</span><span class="t"><b>Ciclul ei</b><span>Pagina „Pentru el”, la care ai fost invitat</span></span><span class="sageata" aria-hidden="true">'+ic('inapoi','ic spa-dr')+'</span></a>':'')+
   rb('spa-sterge','cos','Șterge contul','Datele tale din Spațiul meu și de pe acest telefon','spa-pericol');
  var v=d.querySelector('#ahsp .spa-ver');if(!v&&S.versiune){v=d.createElement('p');v.className='spa-ver';v.setAttribute('data-spa','1');v.textContent='Aplicația Adela Haru · versiunea '+S.versiune;sec.appendChild(v);}
 });
}
function msg(t){var f=foaie('<h2>'+esc(t[0])+'</h2><p>'+esc(t[1])+'</p><div class="b"><button type="button" data-a="ok">Am înțeles</button></div>');f.addEventListener('click',function(e){if(e.target.closest('[data-a]'))scoate(f);});}
d.addEventListener('click',function(e){
 var a=e.target.closest&&e.target.closest('#spa-push,#spa-ciclu,#spa-dl-tot,#spa-sterge');if(!a)return;e.preventDefault();
 if(a.id==='spa-push'){var on=a.getAttribute('aria-checked')!=='true';
  if(!on){PREF.pune('ah_push_on',0);PUSH.opreste();a.setAttribute('aria-checked','false');return;}
  PUSH.stare().then(function(st){
   if(st==='granted'){PREF.pune('ah_push_on',1);PUSH.porneste();a.setAttribute('aria-checked','true');return;}
   if(st==='denied'){msg(['Notificările sunt oprite din telefon','Le poți porni din Setările telefonului › Adela Haru › Notificări. Apoi revino aici.']);return;} /* CIORNĂ */
   PREF.pune('ah_push_intrebat',1);cheama('PushNotifications','requestPermissions').then(function(r){if(r&&r.receive==='granted'){PREF.pune('ah_push_on',1);PUSH.porneste();a.setAttribute('aria-checked','true');ciclu(true);}}).catch(function(){});
  });return;}
 if(a.id==='spa-ciclu'){var on2=a.getAttribute('aria-checked')!=='true';PREF.pune('ah_ciclu_notif',on2?1:0).then(function(){
   if(!on2){a.setAttribute('aria-checked','false');ciclu(true);return;}
   cheama('LocalNotifications','checkPermissions').then(function(r){return r&&r.display==='granted'?r:cheama('LocalNotifications','requestPermissions');}).then(function(r){var ok=r&&r.display==='granted';a.setAttribute('aria-checked',ok?'true':'false');if(!ok)msg(['Notificările sunt oprite din telefon','Le poți porni din Setările telefonului › Adela Haru › Notificări.']);ciclu(true);}).catch(function(){});});return;}
 if(a.id==='spa-dl-tot'){confirma('Ștergi meditațiile descărcate?','Pleacă de pe acest telefon toate meditațiile descărcate. Le poți descărca din nou oricând.','Șterge').then(function(da){if(da)DL.stergeTot().then(function(){scoate(a);});});return;} /* CIORNĂ */
 if(a.id==='spa-sterge')stergeCont();
});
/* Ștergerea contului (Apple 5.1.1(v), Google Play). CIORNĂ. */
function stergeCont(){
 var f=foaie('<h2>Ștergi contul tău?</h2><p>Ce ștergem:</p><ul><li>datele ciclului păstrate în contul tău, emailurile fazelor și accesul celor pe care i-ai invitat să-ți vadă ciclul;</li><li>înscrierea ta în Spațiul meu, telefoanele conectate și notificările;</li><li>pe acest telefon: meditațiile descărcate, datele ciclului și mementourile.</li></ul><p>Comenzile și facturile rămân la noi, fiindcă legea ne cere să le păstrăm. Cursurile, cărțile și meditațiile cumpărate rămân ale tale: dacă vrei vreodată să revii, intri din nou cu adresa ta de email.</p><label><input type="checkbox" id="spa-st-ok"> Am înțeles și vreau să-mi șterg contul.</label><p class="m" id="spa-st-m" role="status"></p><div class="b lipit"><button type="button" data-a="da">Șterge contul</button><button type="button" class="lite" data-a="nu">Renunț</button></div>','spa-st');
 var ok=f.querySelector('#spa-st-ok'),bd=f.querySelector('[data-a="da"]');bd.disabled=true; /* „Șterge contul” doar după bifă */
 ok.addEventListener('change',function(){bd.disabled=!ok.checked;});
 f.addEventListener('click',function(e){var b=e.target.closest('[data-a]');if(!b)return;
  if(b.getAttribute('data-a')==='nu'){scoate(f);return;}
  if(b.getAttribute('data-a')==='inchide'){location.href=S.pagina;return;}
  var m=f.querySelector('#spa-st-m');if(!ok.checked){m.textContent='Bifează, te rog, că ai înțeles ce se șterge.';return;}
  b.disabled=true;m.textContent='Se șterge…';
  /* întâi semnul pe telefon (Preferences.ah_sterge): dacă aplicația se închide după răspuns, curățenia se face la pornire */
  PREF.pune('ah_sterge',S.cine||'1').then(function(){return api('sterge-cont',{metoda:'POST',corp:{confirmare:true}});}).then(function(j){
   if(!j||!j.ok){PREF.scoate('ah_sterge');b.disabled=false;m.textContent=j&&j.retea?'Nu e internet acum. Încearcă din nou.':(j&&j._cod>=500?'Nu am putut șterge contul acum. Mai încearcă puțin.':((j&&j.mesaj)||'N-a mers. Încearcă din nou sau scrie-ne pe WhatsApp.'));return;}
   H.classList.add('ahspa-sters'); /* în spate nu mai rămâne nimic din contul șters */
   return curataLocal(true).then(function(){return PREF.scoate('ah_sterge');}).then(function(){var c=f.querySelector('.c');c.setAttribute('aria-labelledby','spa-st-gata');c.innerHTML='<h2 id="spa-st-gata">Contul tău a fost șters</h2><p>Îți mulțumim că ai fost aici. Te așteptăm oricând.</p><div class="b"><button type="button" data-a="inchide">Închide</button></div>';var x=c.querySelector('button');if(x)x.focus();});
  });
 });
}
/* La ieșire și la ștergere: notificările, descărcările și cheile de pe telefon. tot = ieșirea cerută de ea sau ștergerea
   contului: și datele paginii (ciclul, notițele, partenerul, progresul), ca altcineva intrat după ea pe telefon să nu le
   moștenească. Fără „tot”: doar ce ține de cont pe telefon. dinCoada = chemată din coada mementourilor (pornirea). */
function curataLocal(tot,dinCoada){
 var p=[PUSH.opreste(),DL.stergeTot(),(dinCoada?anuleazaCiclu():inCoada(anuleazaCiclu)).catch(function(){}),PREF.scoate('ah_ciclu')];
 if(tot){
  ['ah_push_intrebat','ah_push_on','ah_ciclu_notif','ah_deschideri'].forEach(function(k){p.push(PREF.scoate(k));});
  try{Object.keys(localStorage).forEach(function(k){if(/^(ahsp_|ahspx_|ahct_|ah_ciclu_v1$|ahspa_cine$)/.test(k))localStorage.removeItem(k);});}catch(e){}
 }
 return Promise.race([Promise.all(p).catch(function(){}),new Promise(function(r){setTimeout(r,4000);})]);
}
/* „Ieși din cont pe acest telefon”: întâi curățăm, apoi lăsăm butonul paginii să-și facă treaba. */
d.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('#ahsp-iesi');if(!b||!NATIV||b.getAttribute('data-spa-gata'))return;e.preventDefault();e.stopImmediatePropagation();b.disabled=true;
 curataLocal(true).then(function(){b.disabled=false;b.setAttribute('data-spa-gata','1');b.click();});},true);

/* Fișierul de calendar al sălii (.ics, 44): WebView-ul nu deschide un atașament; îl dăm browserului din aplicație
   (pe iPhone, Safari îl adaugă în Calendar). Linkul e semnat, nu are nevoie de cookie-ul contului. */
d.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a||!NATIV||!are('Browser','open'))return;
 var x=abs(a.getAttribute('href'));if(!x||!eSite(x)||x.pathname!=='/'||!(x.searchParams.has('ah_sc_ics')||x.searchParams.has('ah_sc_ics_r')))return;
 e.preventDefault();cheama('Browser','open',{url:x.href}).catch(function(){location.href=x.href;});},true);

/* Politicile și sala de curs: o bară sus, cu drumul înapoi (spre ecranul de unde a venit, altfel spre Spațiul meu). */
function inapoi(){
 if((S.zona!=='politici'&&S.zona!=='sala')||!d.body||d.querySelector('.ahspa-inapoi'))return;
 var b=d.createElement('nav');b.className='ahspa-inapoi ahspa-ui';b.setAttribute('data-spa','1');b.setAttribute('aria-label','Înapoi');
 var el=!S.cont&&S.el&&S.pagina_el; /* partenerul (contul pentru el) a venit din pagina lui */
 b.innerHTML='<a href="'+esc(el?S.pagina_el:S.pagina)+'">'+ic('inapoi')+(el?'Înapoi la ciclul ei':'Înapoi în Spațiul meu')+'</a>'; /* CIORNĂ */
 b.firstChild.addEventListener('click',function(e){var r=d.referrer?abs(d.referrer):null;if(r&&eSite(r)&&r.pathname!==location.pathname&&history.length>1){e.preventDefault();history.back();}});
 d.body.insertBefore(b,d.body.firstChild);
}

/* ==================================================================
   8. VERSIUNEA MINIMĂ: o bandă cu linkul spre magazin
   ================================================================== */
function vMic(a,b){a=String(a||'0').split('.');b=String(b||'0').split('.');for(var i=0;i<Math.max(a.length,b.length);i++){var x=+a[i]||0,y=+b[i]||0;if(x!==y)return x<y;}return false;}
function versiune(){
 if(!S.min||!S.versiune||!vMic(S.versiune,S.min)||d.querySelector('.ahspa-banda'))return;
 var b=d.createElement('div');b.className='ahspa-banda ahspa-ui';b.setAttribute('data-spa','1');b.setAttribute('role','status');
 b.innerHTML='<b>E o versiune nouă a aplicației</b><span>'+esc(S.mesaj||'Actualizeaz-o, ca totul să meargă bine.')+'</span>'+(S.magazin?'<a href="'+esc(S.magazin)+'" target="_blank" rel="noopener">Actualizează</a>':''); /* CIORNĂ */
 d.body.appendChild(b);
}

/* ==================================================================
   8b. ACCESIBILITATE (2.3.0): titlul secțiunii, eticheta dublă, aspectul de seară, playerul, codul de intrare (235 neschimbat)
   ================================================================== */
/* Titlul de sus al secțiunii (235: <span id="ahsp-tit">) devine <h1 tabindex="-1">, cu același id (235 îl scrie mai departe
   prin getElementById) și același aspect. La schimbarea secțiunii primește focusul (fără derulare), iar titlul documentului
   spune unde e ea: „Ciclul meu · Spațiul meu”. */
function titluH1(){var t=d.getElementById('ahsp-tit');if(!t||t.tagName==='H1')return t;var h=d.createElement('h1');h.id='ahsp-tit';h.className=t.className;h.setAttribute('tabindex','-1');h.textContent=t.textContent;t.parentNode.replaceChild(h,t);return h;}
function titluDoc(){var t=d.getElementById('ahsp-tit'),x=t?(t.textContent||'').trim():'';if(x)d.title=x==='Spațiul meu'?x:x+' · Spațiul meu';}
/* Eticheta mică de sus a secțiunii, când repetă exact titlul din antet (de ex. „Ciclul meu” / „CICLUL MEU”): o ascunde stilul
   (doar pe telefon, unde antetul se vede). */
function eticheteDuble(){var t=d.getElementById('ahsp-tit'),x=t?(t.textContent||'').trim().toLowerCase():'';if(!x)return;
 d.querySelectorAll('#ahsp-main .ecran .et').forEach(function(e,i){var y=(e.textContent||'').trim().toLowerCase();e.classList.toggle('spa-dublu',i===0&&y===x);});}
/* „Aspect de seară” (266): comutatorul arată starea paginii (și când pagina urmează tema telefonului) */
function searaSync(){var w=d.getElementById('spx-seara');if(w)w.setAttribute('aria-checked',H.classList.contains('ahsp-seara')?'true':'false');}
try{if(S.zona==='sp'&&window.matchMedia){var mq=matchMedia('(prefers-color-scheme: dark)'),mqf=function(){var v=null;try{v=localStorage.getItem('ahspx_seara');}catch(e){}if(v===null){H.classList.toggle('ahsp-seara',mq.matches);searaSync();}};if(mq.addEventListener)mq.addEventListener('change',mqf);else if(mq.addListener)mq.addListener(mqf);}}catch(e){}
/* playerul (235) acoperă sfârșitul listei: cât se vede, pagina are loc dedesubt (clasa ahspa-cu-player pe <html>) */
var plObs=null;
function playerLoc(){var pl=d.querySelector('#ahsp .player');H.classList.toggle('ahspa-cu-player',!!(pl&&pl.classList.contains('arata')));
 if(pl&&!plObs&&window.MutationObserver){plObs=new MutationObserver(playerLoc);plObs.observe(pl,{attributes:true,attributeFilter:['class']});}}

/* Codul de intrare (235, ecranul de intrare). Fără să schimbăm 235: câmpul codului fără maxlength (lipirea „123 456” merge;
   235 păstrează doar cifrele), „Intră” doar cu 6 cifre, greșeala marcată (aria-invalid) cu focusul în câmp, „Trimite-mi
   un cod nou” separat de „Altă adresă”, iar după „prea multe încercări” / „cod expirat” câmpul se golește și focusul merge
   pe „Trimite-mi un cod nou”. O eroare a serverului (5xx) are textul ei. Limita din secțiunea 11 numără la fel: fiecare
   încercare ajunge tot la POST /ah-sp/v1/intra (un cod de altă lungime nici nu pleacă). CIORNĂ pentru texte. */
var IN={am_cod:false,rasp:null};
function emailIntrare(){var b=d.querySelector('#ahsp .intrare .cutie p b');return b?(b.textContent||'').trim():'';}
function invalid(el,da){if(!el)return;if(da)el.setAttribute('aria-invalid','true');else el.removeAttribute('aria-invalid');}
function intrareA11y(){
 var D0=window.AH_SP||{};if(S.cont||D0.stare==='cont')return;
 var c=d.getElementById('ahsp-c'),e=d.getElementById('ahsp-e'),m=d.getElementById('ahsp-m');
 if(c&&!c.getAttribute('data-spa')){c.setAttribute('data-spa','1');IN.am_cod=true;c.removeAttribute('maxlength');if(m)c.setAttribute('aria-describedby','ahsp-m');
  c.addEventListener('input',function(){invalid(c,false);});
  var alt=d.getElementById('ahsp-alt');
  if(alt&&!d.getElementById('spa-cod-nou')){alt.textContent='Altă adresă';var nb=d.createElement('button');nb.type='button';nb.className='legatura';nb.id='spa-cod-nou';nb.setAttribute('data-spa','1');nb.textContent='Trimite-mi un cod nou';alt.parentNode.insertBefore(nb,alt);
   nb.addEventListener('click',function(){var em=emailIntrare();if(!em||!D0.api)return;nb.disabled=true;if(m)m.textContent='Îți trimitem un cod nou…';
    fetch(D0.api+'cod',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em})}).then(function(r){return r.json().catch(function(){return {ok:false};}).then(function(j){j=j||{};j._cod=r.status;return j;});}).catch(function(){return {ok:false,mesaj:'Nu e internet acum. Încearcă din nou.'};})
     .then(function(j){nb.disabled=false;var c2=d.getElementById('ahsp-c');
      if(j.ok){if(m)m.textContent='Ți-am trimis un cod nou. Uită-te și în Promoții sau Spam.';if(c2){c2.value='';invalid(c2,false);c2.focus();}}
      else{if(m)m.textContent=j._cod>=500?'Nu am putut trimite codul acum. Mai încearcă puțin.':(j.mesaj||'Nu s-a putut trimite codul.');nb.focus();}});});}
 }
 if(e&&!e.getAttribute('data-spa')){e.setAttribute('data-spa','1');if(m)e.setAttribute('aria-describedby','ahsp-m');e.addEventListener('input',function(){invalid(e,false);});
  if(IN.am_cod){IN.am_cod=false;try{e.focus();}catch(x){}}} /* înapoi la adresă din pasul codului: focusul în câmp */
}
/* „Intră” / „Trimite-mi codul”: înaintea lui 235 (captură); un cod care nu are 6 cifre nu pleacă la server */
d.addEventListener('submit',function(ev){var f=ev.target;if(!f||f.id!=='ahsp-f')return;var m=d.getElementById('ahsp-m'),c=d.getElementById('ahsp-c'),e=d.getElementById('ahsp-e');
 if(c){var v=String(c.value||'').replace(/\D/g,'');if(v.length!==6){ev.preventDefault();ev.stopImmediatePropagation();if(m)m.textContent='Codul are 6 cifre.';invalid(c,true);c.focus();return;}c.value=v;}
 else if(e&&!/^\S+@\S+\.\S+$/.test(String(e.value||'').trim())){invalid(e,true);setTimeout(function(){e.focus();},0);}},true);
/* Răspunsul lui /ah-sp/v1/intra și /cod, văzut înainte ca 235 să-l citească: 5xx primește un text clar; după ce 235 arată
   mesajul, marcăm câmpul (greșit), sau îl golim și dăm focusul lui „Trimite-mi un cod nou” (prea multe încercări, expirat). */
if(S.zona==='sp'&&!S.cont&&window.fetch&&window.Response){var f0=window.fetch;
 window.fetch=function(u,o){var url=String(u&&u.url||u||''),m=String(o&&o.method||'GET').toUpperCase(),ruta=/\/ah-sp\/v1\/(intra|cod)$/.exec(url.split('?')[0]);
  var p=f0.apply(this,arguments);if(!ruta||m!=='POST')return p;
  return p.then(function(r){var st=r.status;
   var dupa=function(mes){setTimeout(function(){var c=d.getElementById('ahsp-c'),e=d.getElementById('ahsp-e'),nb=d.getElementById('spa-cod-nou');
    if(st===200)return;
    if(ruta[1]==='cod'){invalid(e,true);if(e)e.focus();return;}
    if(!c)return;
    if(st===429||/expirat|cod nou|unul nou/i.test(mes||'')){c.value='';invalid(c,false);if(nb)nb.focus();return;}
    invalid(c,true);try{c.focus();c.select();}catch(x){}},40);};
   if(st>=500){dupa('');return new Response(JSON.stringify({ok:false,mesaj:ruta[1]==='intra'?'Nu am putut verifica codul acum. Mai încearcă puțin.':'Nu am putut trimite codul acum. Mai încearcă puțin.'}),{status:st,headers:{'Content-Type':'application/json'}});}
   return r.clone().json().then(function(j){dupa(j&&j.mesaj);return r;},function(){dupa('');return r;});
  });};}

/* ==================================================================
   9. PORNIREA
   ================================================================== */
function dupaDesen(){try{curata();iframeuri();if(S.zona==='sp'){meditatii();mai();var t=titluH1();if(t&&!titluDoc.gata){titluDoc.gata=1;titluDoc();}eticheteDuble();searaSync();playerLoc();intrareA11y();bara200();}}catch(e){}}
/* la zoom mare (bara la 195 px): etichetele barei rămân ca nume accesibile chiar dacă stilul le ascunde */
function bara200(){d.querySelectorAll('#ahsp .bara a:not([aria-label])').forEach(function(a){var t=(a.textContent||'').trim();if(t)a.setAttribute('aria-label',t);});}
/* schimbarea secțiunii (după arata() din 235, care ascultă primul): titlul documentului și focusul pe titlul secțiunii */
window.addEventListener('hashchange',function(){if(S.zona!=='sp')return;setTimeout(function(){var t=titluH1();titluDoc();eticheteDuble();if(t&&!d.querySelector('.ahspa-foaie')){try{t.focus({preventScroll:true});}catch(e){}}},0);});
/* iframe-urile, oriunde în pagină (și în sala de curs, în afara #ahsp), imediat ce apar */
if(window.MutationObserver){new MutationObserver(function(l){l.forEach(function(r){r.addedNodes.forEach(function(n){if(n.nodeType!==1)return;if(n.tagName==='IFRAME')iframe(n);else if(n.getElementsByTagName&&n.getElementsByTagName('iframe').length)iframeuri(n);});});}).observe(H,{childList:true,subtree:true});}
var prog=false;function curand(){if(prog)return;prog=true;(window.queueMicrotask||function(f){Promise.resolve().then(f);})(function(){prog=false;dupaDesen();});}
if(window.MutationObserver){var legat=false,mo=new MutationObserver(function(){var R=d.getElementById('ahsp');if(R&&!legat){legat=true;mo.disconnect();mo.observe(R,{childList:true,subtree:true});}curand();});mo.observe(H,{childList:true,subtree:true});}
/* Ecranul de pornire (splash) rămâne până se vede pagina: îl ascundem după prima desenare (aplicația îl ascunde singură
   oricum după câteva secunde, launchShowDuration din capacitor.config.ts). */
var splash=false;function ascundeSplash(){if(splash||!NATIV)return;splash=true;(window.requestAnimationFrame||setTimeout)(function(){cheama('SplashScreen','hide',{fadeOutDuration:200}).catch(function(){});});}
d.addEventListener('DOMContentLoaded',function(){dupaDesen();versiune();inapoi();ascundeSplash();});
if(d.readyState!=='loading'){dupaDesen();setTimeout(function(){versiune();inapoi();ascundeSplash();},0);}
/* Linkurile care deschid aplicația (inclusiv linkul personal ?cont= din emailuri). */
asculta('App','appUrlOpen',function(e){if(e&&e.url&&permis(e.url))mergi(e.url);});
/* Butonul Înapoi de pe Android: întâi închide foaia de deasupra (a noastră, a Spațiului meu, playerul), apoi înapoi în
   istoric, iar la început aplicația trece în fundal (fără listener, Capacitor n-ar face nimic la rădăcină). */
function inapoiAndroid(e){
 var f=d.querySelectorAll('.ahspa-foaie');if(f.length){var u=f[f.length-1],n=u.querySelector('[data-a="nu"],[data-a="ok"],[data-a="inchide"]');if(n)n.click();else scoate(u);return;}
 var x=d.querySelector('#ahsp .foaie.arata .fo-x,#ahsp .foaie.arata [data-x],#ahsp .player.arata .pl-x');if(x){x.click();return;}
 if(e&&e.canGoBack&&!/\/offline\.html$/.test(location.pathname)){history.back();return;}
 cheama('App','minimizeApp').catch(function(){});
}
function pornesteNativ(){
 if(!NATIV)return;
 if(platf()==='android')asculta('App','backButton',inapoiAndroid);
 PUSH.leaga();
 cheama('Device','getId').then(function(r){DISP=String((r&&(r.identifier||r.uuid))||'').slice(0,64);}).catch(function(){});
 cheama('App','getLaunchUrl').then(function(r){var u=r&&r.url;if(!u||!permis(u))return;u=intra(u);var k='ahspa_lansare';try{if(sessionStorage.getItem(k)===u)return;sessionStorage.setItem(k,u);}catch(e){}var x=abs(u);if(x&&x.pathname+x.search!==location.pathname+location.search)mergi(u);}).catch(function(){});
 var prima=false;try{prima=!sessionStorage.getItem('ahspa_sesiune');sessionStorage.setItem('ahspa_sesiune','1');}catch(e){}
 REF.then(function(){return prima&&S.zona==='sp'&&S.cont?PREF.ia('ah_deschideri').then(function(n){return PREF.pune('ah_deschideri',(+n||0)+1);}):null;}).then(momentPush); /* după curățarea contului de dinainte */
 /* Fără cont (cookie expirat, sesiune pierdută): NU ștergem descărcările, copia ciclului sau mementourile ei (2.3.0): doar
    ieșirea cerută de ea, schimbarea contului și ștergerea contului curăță. Pleacă doar un token rămas al lui. */
 if(S.zona==='sp'&&!S.cont){PUSH.opreste(true);}
 else setTimeout(function(){ciclu(false);},800);
 /* revenirea în aplicație (alt fus orar, o zi nouă): mementourile se verifică din nou, prin aceeași coadă */
 asculta('App','resume',function(){ciclu(false);});
}
pornesteNativ();
window.AHApp={versiune:S.v,permis:permis,blocat:blocat,mergi:mergi,curata:curata,curataDate:curataD,ciclu:{calc:CX,viitoare:CX.viitoare,programeaza:function(){return ciclu(true);}},push:PUSH,intreabaPush:intreabaPush,descarcari:DL,mediaSesiune:mediaSesiune,mediaNativ:AM,stergeCont:stergeCont,curataLocal:curataLocal,inapoi:inapoiAndroid,intra:intra};
})();
AHSPAJS;
}

/* ==================================================================
   5. REST ah-spa/v1
   ================================================================== */

/** Răspuns privat: fără cache nicăieri (browser, LiteSpeed). */
function ah_spa_raspuns( $d, $cod = 200 ) {
	do_action( 'litespeed_control_set_nocache', 'ah spa rest' );
	$r = new WP_REST_Response( $d, $cod );
	$r->header( 'Cache-Control', 'no-store, private, max-age=0' );
	$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $r;
}

/**
 * Rutele cu date personale ale Spațiului meu (ah-sp, 235), ale extensiilor (ah-spx, 266), ale contului pentru el (ah-spp) și
 * ale noastre (ah-spa) nu intră în niciun cache (LiteSpeed, proxy, browser), oricine le-ar fi scris. Rămân în cache doar
 * fișierele punții (/ah-spa/v1/punte, aceleași pentru toți) și /ah-spa/v1/config (public). Pură (pe ruta dată).
 */
function ah_spa_ruta_privata( $ruta ) {
	$ruta = (string) $ruta;
	if ( ! preg_match( '~^/ah-sp(x|p|a)?/v1(/|$)~', $ruta ) ) {
		return false;
	}
	return ! preg_match( '~^/ah-spa/v1/(punte|config)/?$~', $ruta );
}
add_filter( 'rest_post_dispatch', function ( $rez, $server, $req ) {
	if ( ! is_object( $req ) || ! method_exists( $req, 'get_route' ) || ! ah_spa_ruta_privata( $req->get_route() ) ) {
		return $rez;
	}
	do_action( 'litespeed_control_set_nocache', 'ah spa rest privat' );
	if ( is_object( $rez ) && method_exists( $rez, 'header' ) ) {
		$rez->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$rez->header( 'Cache-Control', 'no-store, private' );
	}
	return $rez;
}, 99, 3 );

/** Cererile care schimbă ceva vin doar din pagina noastră: antetul X-AH-App (un alt site nu-l poate trimite fără CORS). */
function ah_spa_antet_ok( $r ) {
	return '1' === (string) $r->get_header( 'x_ah_app' );
}

/** Limită simplă pe o cheie (fără date personale în cheie: doar o amprentă). */
function ah_spa_limita( $cheie, $max, $sec = HOUR_IN_SECONDS ) {
	$k = 'ah_spa_l_' . substr( md5( $cheie ), 0, 20 );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $k, $n + 1, $sec );
	return true;
}

/** Jurnalul aplicației (ultimele 200 de rânduri; fără adrese de email, fără chei). */
function ah_spa_jurnal( $text ) {
	$j   = get_option( 'ah_spa_jurnal', array() );
	$j   = is_array( $j ) ? $j : array();
	$j[] = array( time(), (string) $text );
	update_option( 'ah_spa_jurnal', array_slice( $j, -200 ), false );
}

/* ---------- semnăturile ---------- */

function ah_spa_secret() {
	return hash_hmac( 'sha256', 'ah_spa|fisier', wp_salt( 'auth' ) );
}
/**
 * Secretul amprentei contului: creat O DATĂ, în baza de date (nu din sărurile WordPress: o rotire a sărurilor ar fi făcut
 * din fiecare clientă „alt cont”, iar puntea i-ar fi șters de pe telefon istoricul ciclului). add_option nu suprascrie,
 * deci două cereri deodată la prima pornire păstrează același secret (se recitește după scriere). Momentul creării
 * (ah_spa_cine_din) deschide trecerea de AH_SPA_CINE_TRECERE zile în care amprenta veche e recunoscută.
 */
function ah_spa_cine_secret() {
	$v = get_option( 'ah_spa_cine_secret', '' );
	if ( ! is_string( $v ) || strlen( $v ) < 32 ) {
		add_option( 'ah_spa_cine_secret', bin2hex( random_bytes( 32 ) ), '', 'no' );
		add_option( 'ah_spa_cine_din', time(), '', 'no' );
		$v = (string) get_option( 'ah_spa_cine_secret', '' );
	}
	return $v;
}
/** O amprentă scurtă a adresei (în linkuri și în pagină, în locul emailului). */
function ah_spa_cine( $email ) {
	return substr( hash_hmac( 'sha256', strtolower( trim( (string) $email ) ), ah_spa_cine_secret() . '|cine' ), 0, 16 );
}
/** Amprenta de dinainte de 2.3.0 (din sărurile WordPress): doar ca puntea s-o recunoască în timpul trecerii. */
function ah_spa_cine_salt( $email ) {
	return substr( hash_hmac( 'sha256', strtolower( trim( (string) $email ) ), ah_spa_secret() . '|cine' ), 0, 16 );
}
/**
 * Amprentele vechi ale aceluiași cont, pe care puntea le tratează ca „același cont” (nu șterge nimic, doar rescrie
 * ahspa_cine): amprenta din săruri în primele AH_SPA_CINE_TRECERE zile și aliasurile lăsate de „Mută contul”.
 */
function ah_spa_cine_vechi( $email, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$nou  = ah_spa_cine( $email );
	$o    = array();
	$din  = (int) get_option( 'ah_spa_cine_din', 0 );
	if ( $din && $acum - $din < AH_SPA_CINE_TRECERE * DAY_IN_SECONDS ) {
		$o[] = ah_spa_cine_salt( $email );
	}
	$al = get_option( 'ah_spa_alias', array() );
	foreach ( (array) ( is_array( $al ) && isset( $al[ $nou ] ) ? $al[ $nou ] : array() ) as $x ) {
		if ( is_array( $x ) && isset( $x[0] ) && $acum - (int) ( $x[1] ?? 0 ) < YEAR_IN_SECONDS ) {
			$o[] = (string) $x[0];
		}
	}
	return array_values( array_unique( array_diff( array_filter( $o, function ( $c ) { return (bool) preg_match( '/^[0-9a-f]{16}$/', $c ); } ), array( $nou ) ) ) );
}
function ah_spa_semn( $id, $exp, $cine ) {
	return substr( hash_hmac( 'sha256', 'fisier|' . (int) $id . '|' . (int) $exp . '|' . (string) $cine, ah_spa_secret() ), 0, 32 );
}
/** Linkul semnat al fișierului unei meditații: array( url, expira ). */
function ah_spa_link_fisier( $id, $email, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$exp  = $acum + AH_SPA_LINK_MIN * MINUTE_IN_SECONDS;
	$cine = ah_spa_cine( $email );
	return array(
		add_query_arg( array( 'id' => (int) $id, 'exp' => $exp, 'c' => $cine, 's' => ah_spa_semn( $id, $exp, $cine ) ), rest_url( AH_SPA_NS . '/fisier' ) ),
		$exp,
	);
}
function ah_spa_link_valid( $id, $exp, $cine, $sig, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$exp  = (int) $exp;
	if ( $id <= 0 || $exp < $acum || $exp > $acum + AH_SPA_LINK_MIN * MINUTE_IN_SECONDS + 60 || ! preg_match( '/^[0-9a-f]{16}$/', (string) $cine ) ) {
		return false;
	}
	return hash_equals( ah_spa_semn( $id, $exp, $cine ), (string) $sig );
}

/** „Range: bytes=a-b” → array( start, end ); false = fără Range (tot fișierul); null = interval imposibil (416). */
function ah_spa_interval( $range, $size ) {
	$size = (int) $size;
	if ( ! $range || ! preg_match( '/^bytes=(\d*)-(\d*)$/', trim( (string) $range ), $m ) || ( '' === $m[1] && '' === $m[2] ) ) {
		return false;
	}
	if ( '' === $m[1] ) {
		$start = max( 0, $size - (int) $m[2] );
		$end   = $size - 1;
	} else {
		$start = (int) $m[1];
		$end   = '' === $m[2] ? $size - 1 : min( (int) $m[2], $size - 1 );
	}
	if ( $size <= 0 || $start > $end || $start >= $size ) {
		return null;
	}
	return array( $start, $end );
}

/** Extensia fișierului audio, pentru numele de pe telefon. */
function ah_spa_ext( $cale ) {
	$e = strtolower( (string) pathinfo( (string) wp_parse_url( (string) $cale, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $e, array( 'mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'flac', 'mp4' ), true ) ? $e : 'mp3';
}

/** Trimite fișierul cu „Range” (derulare, reluarea descărcării) și iese. */
function ah_spa_streameaza( $cale, $mime, $nume ) {
	$size = (int) filesize( $cale );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $mime );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Disposition: attachment; filename="' . preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $nume ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, no-store' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	$i = ah_spa_interval( $_SERVER['HTTP_RANGE'] ?? '', $size ); // phpcs:ignore
	if ( null === $i ) {
		status_header( 416 );
		header( 'Content-Range: bytes */' . $size );
		exit;
	}
	if ( is_array( $i ) ) {
		status_header( 206 );
		header( 'Content-Range: bytes ' . $i[0] . '-' . $i[1] . '/' . $size );
	} else {
		status_header( 200 );
		$i = array( 0, $size - 1 );
	}
	header( 'Content-Length: ' . ( $i[1] - $i[0] + 1 ) );
	if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		exit;
	}
	$f = fopen( $cale, 'rb' );
	fseek( $f, $i[0] );
	$rest = $i[1] - $i[0] + 1;
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

/* ---------- accesul la o meditație: prin funcțiile fragmentului 51 ---------- */

/** true dacă adresa poate descărca meditația; altfel motivul: lipsa | acces | sunet | modul. */
function ah_spa_poate_descarca( $id, $email ) {
	if ( ! function_exists( 'ah_med_acces' ) || ! function_exists( 'ah_med_poate' ) || ! function_exists( 'ah_med_m' ) ) {
		return 'modul';
	}
	$p = get_post( (int) $id );
	if ( ! $p || 'ah_meditatie' !== $p->post_type || 'publish' !== $p->post_status ) {
		return 'lipsa';
	}
	if ( ! ah_med_poate( (int) $id, ah_med_acces( $email ) ) ) {
		return 'acces';
	}
	return '' !== (string) ah_med_m( (int) $id, 'audio' ) ? true : 'sunet';
}

/* ---------- push: tabelul tokenurilor ---------- */

function ah_spa_tabel() {
	global $wpdb;
	return $wpdb->prefix . 'ah_spa_push';
}
add_action( 'init', function () {
	if ( get_option( 'ah_spa_tabel' ) === AH_SPA_TABEL_VER ) {
		return;
	}
	global $wpdb;
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	$t = ah_spa_tabel();
	// contoarele atomice (secțiunea 11): cheia e o amprentă (fără adrese de email), n = încercări, expira = timestamp
	dbDelta( "CREATE TABLE " . ah_spa_tabel_contoare() . " (\n cheie char(40) NOT NULL,\n n int(10) unsigned NOT NULL DEFAULT 0,\n expira int(10) unsigned NOT NULL DEFAULT 0,\n PRIMARY KEY  (cheie)\n) " . $wpdb->get_charset_collate() . ';' );
	dbDelta( "CREATE TABLE {$t} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n email varchar(190) NOT NULL,\n token text NOT NULL,\n token_h char(64) NOT NULL,\n platforma varchar(10) NOT NULL DEFAULT '',\n versiune varchar(20) NOT NULL DEFAULT '',\n disp varchar(64) NOT NULL DEFAULT '',\n creat datetime NOT NULL,\n vazut datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY token_h (token_h),\n KEY email (email)\n) " . $wpdb->get_charset_collate() . ';' );
	// versiunea se notează doar dacă tabelele chiar au coloanele (un dbDelta eșuat — drepturi, disc plin — se reîncearcă)
	if ( ! ah_spa_schema_ok() ) {
		ah_spa_jurnal( 'tabelele aplicației nu s-au putut crea sau actualiza; se reîncearcă la cererea următoare' );
		return;
	}
	update_option( 'ah_spa_tabel', AH_SPA_TABEL_VER, false );
	if ( ! get_option( 'ah_spa_pornit' ) ) {
		update_option( 'ah_spa_pornit', time(), false );
	}
}, 5 );

/** Coloanele unui tabel (SHOW COLUMNS), cu litere mici; array gol dacă tabelul lipsește. */
function ah_spa_coloane( $t ) {
	global $wpdb;
	try {
		$r = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $t, ARRAY_A );
	} catch ( Throwable $e ) {
		return array();
	}
	return array_map( function ( $x ) { return strtolower( (string) ( $x['Field'] ?? '' ) ); }, is_array( $r ) ? $r : array() );
}

/** Adevărat dacă ambele tabele ale aplicației au toate coloanele versiunii AH_SPA_TABEL_VER. */
function ah_spa_schema_ok() {
	$cer = array(
		ah_spa_tabel()          => array( 'id', 'email', 'token', 'token_h', 'platforma', 'versiune', 'disp', 'creat', 'vazut' ),
		ah_spa_tabel_contoare() => array( 'cheie', 'n', 'expira' ),
	);
	foreach ( $cer as $t => $col ) {
		if ( array_diff( $col, ah_spa_coloane( $t ) ) ) {
			return false;
		}
	}
	return true;
}

/** Verifică cererea de înregistrare push. Întoarce array( 'ok' => true, 'date' => [...] ) sau array( 'ok' => false, 'motiv' => … ). */
function ah_spa_valideaza_push( $p ) {
	$p   = is_array( $p ) ? $p : array();
	$tok = isset( $p['token'] ) && is_string( $p['token'] ) ? trim( $p['token'] ) : '';
	if ( '' === $tok ) {
		return array( 'ok' => false, 'motiv' => 'token' );
	}
	if ( preg_match( '/^[0-9a-fA-F]{64}$/', $tok ) ) {
		return array( 'ok' => false, 'motiv' => 'apns' ); // tokenul APNs brut: aplicația trebuie să dea tokenul FCM (vezi README)
	}
	if ( strlen( $tok ) < 32 || strlen( $tok ) > 4096 || ! preg_match( '/^[A-Za-z0-9_\-:.]+$/', $tok ) ) {
		return array( 'ok' => false, 'motiv' => 'token' );
	}
	$pl = isset( $p['platforma'] ) ? strtolower( (string) $p['platforma'] ) : '';
	if ( ! in_array( $pl, array( 'ios', 'android' ), true ) ) {
		return array( 'ok' => false, 'motiv' => 'platforma' );
	}
	$v = isset( $p['versiune'] ) ? (string) $p['versiune'] : '';
	$v = preg_match( '/^[0-9]+(\.[0-9]+){0,3}$/', $v ) ? $v : '';
	$dp = isset( $p['disp'] ) ? preg_replace( '/[^A-Za-z0-9\-_]/', '', (string) $p['disp'] ) : '';
	return array( 'ok' => true, 'date' => array( 'token' => $tok, 'platforma' => $pl, 'versiune' => $v, 'disp' => substr( $dp, 0, 64 ) ) );
}

/** Leagă tokenul de adresă (același telefon: un singur rând; cel mult AH_SPA_TOKENURI_MAX pe adresă). */
function ah_spa_push_salveaza( $email, $x ) {
	global $wpdb;
	$t    = ah_spa_tabel();
	$h    = hash( 'sha256', $x['token'] );
	$acum = current_time( 'mysql' );
	$r    = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t} WHERE token_h = %s", $h ), ARRAY_A );
	if ( $r ) {
		$wpdb->update( $t, array( 'email' => $email, 'platforma' => $x['platforma'], 'versiune' => $x['versiune'], 'disp' => $x['disp'], 'vazut' => $acum ), array( 'id' => (int) $r['id'] ) );
	} else {
		$wpdb->insert( $t, array( 'email' => $email, 'token' => $x['token'], 'token_h' => $h, 'platforma' => $x['platforma'], 'versiune' => $x['versiune'], 'disp' => $x['disp'], 'creat' => $acum, 'vazut' => $acum ) );
	}
	// un token (un telefon) aparține unui singur cont: dacă era al partenerului (ah-sp-partener.php, wp_ah_spp_push), pleacă de acolo
	if ( function_exists( 'ah_spp_tabel_push' ) ) {
		$wpdb->delete( ah_spp_tabel_push(), array( 'token_h' => $h ) );
	}
	// același telefon cu un token nou: tokenul vechi pleacă
	if ( '' !== $x['disp'] ) {
		// doar rândurile ACESTEI adrese: „disp” vine de la telefon, deci nu poate scoate tokenurile altei cliente
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, token_h FROM {$t} WHERE disp = %s AND email = %s", $x['disp'], $email ), ARRAY_A ) as $v ) {
			if ( $v['token_h'] !== $h ) {
				$wpdb->delete( $t, array( 'id' => (int) $v['id'] ) );
			}
		}
	}
	$toate = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, vazut FROM {$t} WHERE email = %s", $email ), ARRAY_A );
	if ( count( $toate ) > AH_SPA_TOKENURI_MAX ) {
		usort( $toate, function ( $a, $b ) { return strcmp( (string) $a['vazut'], (string) $b['vazut'] ) ?: ( (int) $a['id'] - (int) $b['id'] ); } );
		foreach ( array_slice( $toate, 0, count( $toate ) - AH_SPA_TOKENURI_MAX ) as $v ) {
			$wpdb->delete( $t, array( 'id' => (int) $v['id'] ) );
		}
	}
	return true;
}

function ah_spa_r_push( WP_REST_Request $r ) {
	$e = function_exists( 'ah_sp_email' ) ? (string) ah_sp_email() : '';
	if ( '' === $e ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'cont' ), 403 );
	}
	if ( ! ah_spa_antet_ok( $r ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'antet' ), 403 );
	}
	if ( ! ah_spa_limita( 'push|' . $e, 30 ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'prea des' ), 429 );
	}
	$v = ah_spa_valideaza_push( $r->get_json_params() );
	if ( ! $v['ok'] ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => $v['motiv'] ), 400 );
	}
	ah_spa_push_salveaza( $e, $v['date'] );
	return ah_spa_raspuns( array( 'ok' => true ) );
}

/** Scoate un token. Nu cere contul: cine are tokenul e chiar telefonul acela (merge și după ieșirea din cont). */
function ah_spa_r_push_sterge( WP_REST_Request $r ) {
	if ( ! ah_spa_antet_ok( $r ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'antet' ), 403 );
	}
	$p   = (array) $r->get_json_params();
	$tok = isset( $p['token'] ) && is_string( $p['token'] ) ? trim( $p['token'] ) : '';
	if ( '' !== $tok && strlen( $tok ) <= 4096 ) {
		global $wpdb;
		$wpdb->delete( ah_spa_tabel(), array( 'token_h' => hash( 'sha256', $tok ) ) );
	}
	return ah_spa_raspuns( array( 'ok' => true ) );
}

function ah_spa_r_descarca( WP_REST_Request $r ) {
	$e = function_exists( 'ah_sp_email' ) ? (string) ah_sp_email() : '';
	if ( '' === $e ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'cont', 'mesaj' => 'Intră din nou în Spațiul meu.' ), 403 ); // CIORNĂ
	}
	$id = absint( $r->get_param( 'id' ) );
	if ( ! ah_spa_limita( 'desc|' . $e, 60 ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'prea des', 'mesaj' => 'Ai descărcat multe meditații în ultima oră. Mai încearcă puțin mai târziu.' ), 429 ); // CIORNĂ
	}
	$ok = ah_spa_poate_descarca( $id, $e );
	if ( true !== $ok ) {
		$cod = 'acces' === $ok ? 403 : ( 'modul' === $ok ? 503 : 404 );
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => $ok, 'mesaj' => 'acces' === $ok ? 'Meditația aceasta nu e deschisă pentru contul tău.' : 'Meditația nu se poate descărca acum.' ), $cod ); // CIORNĂ
	}
	$src = (string) ah_med_m( $id, 'audio' );
	$cl  = function_exists( 'ah_med_cale' ) ? (string) ah_med_cale( $src, $id ) : '';
	list( $url, $exp ) = ah_spa_link_fisier( $id, $e );
	$poza = get_the_post_thumbnail_url( $id, 'medium_large' );
	return ah_spa_raspuns( array(
		'ok'      => true,
		'url'     => $url,
		'expira'  => $exp,
		'titlu'   => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'durata'  => function_exists( 'ah_med_durata' ) ? (int) ah_med_durata( $id ) : 0,
		'imagine' => $poza ? (string) $poza : '',
		'ext'     => ah_spa_ext( $cl ? $cl : $src ),
	) );
}

/** Fișierul, prin linkul semnat (fără cookie: îl cere FileTransfer, din afara paginii). */
function ah_spa_r_fisier( WP_REST_Request $r ) {
	$id = absint( $r->get_param( 'id' ) );
	if ( ! ah_spa_link_valid( $id, (int) $r->get_param( 'exp' ), (string) $r->get_param( 'c' ), (string) $r->get_param( 's' ) ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'link' ), 403 );
	}
	$p = get_post( $id );
	if ( ! $p || 'ah_meditatie' !== $p->post_type || 'publish' !== $p->post_status || ! function_exists( 'ah_med_m' ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'lipsa' ), 404 );
	}
	$src = (string) ah_med_m( $id, 'audio' );
	$cl  = function_exists( 'ah_med_cale' ) ? (string) ah_med_cale( $src, $id ) : '';
	if ( $cl && is_readable( $cl ) ) {
		$m = wp_check_filetype( $cl );
		ah_spa_streameaza( $cl, $m['type'] ? $m['type'] : 'audio/mpeg', 'meditatie-' . $id . '.' . ah_spa_ext( $cl ) );
	}
	if ( $src && ! ctype_digit( $src ) && 0 === strpos( $src, 'https://' ) ) {
		$x = new WP_REST_Response( null, 302 );
		$x->header( 'Location', $src );
		$x->header( 'Cache-Control', 'no-store, private' );
		return $x;
	}
	return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'sunet' ), 404 );
}

/** Șterge tot ce ține de cont, în afară de comenzi și facturi. Întoarce ce s-a șters (pentru jurnal și teste). */
function ah_spa_sterge_cont( $email ) {
	global $wpdb;
	$e  = strtolower( trim( (string) $email ) );
	$md = md5( $e );
	$s  = array();
	// datele ciclului păstrate în cont (235: ca /ciclu-sterge, tot rândul)
	if ( function_exists( 'ah_sp_ciclu_tabel' ) && function_exists( 'ah_sp_ciclu_cheie' ) ) {
		if ( function_exists( 'ah_sp_ciclu_db' ) ) {
			ah_sp_ciclu_db();
		}
		$s['ciclu_cont'] = (int) $wpdb->delete( ah_sp_ciclu_tabel(), array( 'cheie' => ah_sp_ciclu_cheie( $e ) ) );
	}
	// emailurile fazelor (Harta ciclului, 41): data menstruației și lungimea ciclului
	if ( function_exists( 'ah_ciclu_tabel' ) ) {
		$s['ciclu_emailuri'] = (int) $wpdb->delete( ah_ciclu_tabel(), array( 'email' => $e ) );
	}
	// contul nou fără comenzi (281)
	$t = get_option( 'ah_sp_conturi', array() );
	if ( is_array( $t ) && isset( $t[ $e ] ) ) {
		unset( $t[ $e ] );
		update_option( 'ah_sp_conturi', $t, false );
		$s['cont_spatiul_meu'] = 1;
	}
	// vizitele (235)
	$v = get_option( 'ah_sp_vizite', array() );
	if ( is_array( $v ) && isset( $v[ $md ] ) ) {
		unset( $v[ $md ] );
		update_option( 'ah_sp_vizite', $v, false );
		$s['vizite'] = 1;
	}
	// notificările (aici)
	$s['push'] = (int) $wpdb->delete( ah_spa_tabel(), array( 'email' => $e ) );
	// telefoanele și ascultările din Inner Temple (51)
	foreach ( array( 'dispozitive' => 'ah_med_d_', 'minute' => 'ah_med_min_', 'ascultari' => 'ah_med_asc_' ) as $k => $pre ) {
		if ( false !== get_option( $pre . $md, false ) ) {
			delete_option( $pre . $md );
			$s[ 'med_' . $k ] = 1;
		}
	}
	// mementourile din sala de curs (44), ținute pe comenzile ei: „Ritmul meu” (_ah_sc_ritm) și „Repetă o practică”
	// (_ah_sc_repeta). Comenzile rămân; doar mementourile se opresc și numărul de telefon (SMS) se șterge.
	$sala = ah_spa_opreste_sala( $e );
	if ( $sala['ritm'] || $sala['repeta'] ) {
		$s['sala_ritm']   = $sala['ritm'];
		$s['sala_repeta'] = $sala['repeta'];
	}
	// înscrierea la Respiră-te acasă (220): prenumele, țara, WhatsApp; fără ea nu mai pleacă emailurile întâlnirilor
	$ri = get_option( 'ah_respira_inscrieri', array() );
	if ( is_array( $ri ) && isset( $ri[ $e ] ) ) {
		unset( $ri[ $e ] );
		update_option( 'ah_respira_inscrieri', $ri, false );
		$s['respira_inscriere'] = 1;
	}
	// contul pentru el (ah-sp-partener.php): cei invitați să-i vadă ciclul (rândurile lor și tokenurile lor push), pe loc.
	// Fragmentul lui face același lucru și pe cârligul de mai jos; aici doar ca numărul să intre în emailul echipei.
	if ( function_exists( 'ah_spp_gata' ) && function_exists( 'ah_spp_sterge_ale_ei' ) && function_exists( 'ah_spp_ea' ) && ah_spp_gata() ) {
		$n = (int) ah_spp_sterge_ale_ei( ah_spp_ea( $e ) );
		if ( $n ) {
			$s['pentru_el'] = $n;
		}
	} elseif ( ! function_exists( 'ah_spp_gata' ) && function_exists( 'ah_sp_ciclu_cheie' ) ) {
		// fragmentul lui e oprit (dezactivat), dar tabelele lui rămân: rândurile ei și tokenurile lor push pleacă direct (2.3.0)
		$n = ah_spa_sterge_parteneri_direct( ah_sp_ciclu_cheie( $e ) );
		if ( $n ) {
			$s['pentru_el'] = $n;
		}
	}
	delete_transient( 'ah_sp_rz_' . $md );
	delete_transient( 'ah_med_a_' . $md );
	do_action( 'ah_spa_cont_sters', $e, $s );
	return $s;
}

/** Există tabelul $t? (SHOW TABLES; în teste, SQLite răspunde prin înlocuitor.) */
function ah_spa_tabel_exista( $t ) {
	global $wpdb;
	try {
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === (string) $t;
	} catch ( Throwable $e ) {
		return false;
	}
}

/**
 * „Pentru el” fără fragmentul lui activ: șterge rândurile invitaților ei ({prefix}ah_spp_parteneri, după ea = cheia ciclului
 * ei din 235) și tokenurile lor push ({prefix}ah_spp_push, după id-ul partenerului). SQL pregătit, fără funcțiile lui.
 * Întoarce câte rânduri de invitați s-au șters.
 */
function ah_spa_sterge_parteneri_direct( $cheie ) {
	global $wpdb;
	$tp = $wpdb->prefix . 'ah_spp_parteneri';
	$tt = $wpdb->prefix . 'ah_spp_push';
	if ( '' === (string) $cheie || ! ah_spa_tabel_exista( $tp ) ) {
		return 0;
	}
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$tp} WHERE ea = %s", (string) $cheie ) ) );
	if ( ! $ids ) {
		return 0;
	}
	if ( ah_spa_tabel_exista( $tt ) ) {
		foreach ( $ids as $id ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$tt} WHERE partener = %d", $id ) );
		}
	}
	$n = 0;
	foreach ( $ids as $id ) {
		$n += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$tp} WHERE id = %d", $id ) );
	}
	return $n;
}

/**
 * Oprește mementourile sălii de curs (44) pe toate comenzile cu adresa ei: activ = false, fără SMS, fără telefon.
 * Lista scurtă a comenzilor cu mementouri o ține la zi tot funcția din 44 (ah_sc_ritm_index / ah_sc_repeta_index).
 * Întoarce câte s-au oprit, pe fiecare fel.
 */
function ah_spa_opreste_sala( $email ) {
	$n = array( 'ritm' => 0, 'repeta' => 0 );
	if ( ! function_exists( 'wc_get_orders' ) || ! is_email( $email ) ) {
		return $n;
	}
	$comenzi = wc_get_orders( array( 'billing_email' => $email, 'limit' => -1, 'type' => 'shop_order', 'return' => 'objects' ) );
	foreach ( (array) $comenzi as $c ) {
		if ( ! is_object( $c ) || ! method_exists( $c, 'get_meta' ) ) {
			continue;
		}
		$schimbat = false;
		foreach ( array( 'ritm' => array( '_ah_sc_ritm', 'ah_sc_ritm_index', 'Ritmul meu' ), 'repeta' => array( '_ah_sc_repeta', 'ah_sc_repeta_index', 'Repetă o practică' ) ) as $fel => $x ) {
			$r = $c->get_meta( $x[0] );
			if ( ! is_array( $r ) || ( empty( $r['activ'] ) && empty( $r['sms'] ) && '' === (string) ( $r['telefon'] ?? '' ) ) ) {
				continue;
			}
			$r['activ']   = false;
			$r['sms']     = false;
			$r['telefon'] = '';
			$c->update_meta_data( $x[0], $r );
			if ( function_exists( $x[1] ) ) {
				call_user_func( $x[1], $c, $r );
			}
			$c->add_order_note( $x[2] . ': mementourile au fost oprite și numărul de telefon șters (contul a fost șters din aplicație).' );
			$n[ $fel ]++;
			$schimbat = true;
		}
		if ( $schimbat ) {
			$c->save();
		}
	}
	return $n;
}

/** Cookie-urile contului pe acest telefon (Spațiul meu, Inner Temple, cărțile, sala). */
function ah_spa_sterge_cookieuri() {
	$nume = array( 'ah_sp', 'ah_med' );
	foreach ( array_keys( (array) $_COOKIE ) as $k ) {
		if ( preg_match( '/^(ah_ct_|ah_sc)/', (string) $k ) ) {
			$nume[] = (string) $k;
		}
	}
	foreach ( array_unique( $nume ) as $n ) {
		foreach ( array_unique( array( (string) COOKIE_DOMAIN, '' ) ) as $dom ) {
			if ( ! headers_sent() ) {
				setcookie( $n, '', array( 'expires' => time() - 3600, 'path' => '/', 'domain' => $dom, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			}
		}
		unset( $_COOKIE[ $n ] );
	}
}

function ah_spa_r_sterge_cont( WP_REST_Request $r ) {
	$e = function_exists( 'ah_sp_email' ) ? (string) ah_sp_email() : '';
	if ( '' === $e ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'cont', 'mesaj' => 'Intră din nou în Spațiul meu, apoi șterge contul.' ), 403 ); // CIORNĂ
	}
	$p = (array) $r->get_json_params();
	if ( ! ah_spa_antet_ok( $r ) || true !== ( $p['confirmare'] ?? null ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'confirmare' ), 400 );
	}
	if ( ! ah_spa_limita( 'sterge|' . $e, 5 ) ) {
		return ah_spa_raspuns( array( 'ok' => false, 'motiv' => 'prea des' ), 429 );
	}
	$s = ah_spa_sterge_cont( $e );
	ah_spa_sterge_cookieuri();
	$catre = (string) ah_spa_setari()['email_echipa'];
	if ( is_email( $catre ) ) {
		$ce = array();
		foreach ( $s as $k => $n ) {
			$ce[] = '- ' . $k . ': ' . (int) $n;
		}
		$rec = ah_spa_recenzie();
		wp_mail(
			$catre,
			'Aplicația: cerere de ștergere a contului' . ( '' !== $rec['email'] && $rec['email'] === strtolower( trim( $e ) ) ? ' (contul de recenzie)' : '' ),
			"O clientă și-a șters contul din aplicația Adela Haru.\n\nAdresa: {$e}\nCând: " . current_time( 'mysql' ) . "\n\nS-a șters automat:\n" . implode( "\n", $ce )
			. "\n\nCe înseamnă: ciclu_* = datele ciclului; sala_ritm / sala_repeta = comenzi pe care mementourile din sala de curs"
			. " (email și SMS) au fost oprite și telefonul șters (cu notă pe comandă); respira_inscriere = înscrierea la Respiră-te acasă"
			. " (prenume, țară, WhatsApp), ștearsă, deci nu mai primește emailurile întâlnirilor; pentru_el = persoanele invitate"
			. " să-i vadă ciclul („Pentru el”), șterse cu notificările lor."
			. "\n\nRămân (obligație legală): comenzile și facturile.\nDe verificat de mână: abonarea la scrisori și etichetele din EmailOctopus (inclusiv Respiră-te acasă), alte liste.\n"
		);
	}
	ah_spa_jurnal( 'cont șters: ' . ah_spa_cine( $e ) . ' (' . wp_json_encode( $s ) . ')' );
	return ah_spa_raspuns( array( 'ok' => true, 'mesaj' => 'Contul tău a fost șters.' ) ); // CIORNĂ
}

function ah_spa_r_config() {
	$s = ah_spa_setari();
	$r = new WP_REST_Response( array(
		'ok'              => true,
		'versiune_minima' => array( 'ios' => (string) $s['min_ios'], 'android' => (string) $s['min_android'] ),
		'mesaj'           => (string) $s['mesaj'],
		'magazine'        => array( 'ios' => (string) $s['link_ios'], 'android' => (string) $s['link_android'] ),
		'pagina'          => home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' ),
	), 200 );
	$r->header( 'Cache-Control', 'public, max-age=300' );
	return $r;
}

add_action( 'rest_api_init', function () {
	$t = '__return_true';
	register_rest_route( AH_SPA_NS, '/push', array(
		array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spa_r_push' ),
		array( 'methods' => 'DELETE', 'permission_callback' => $t, 'callback' => 'ah_spa_r_push_sterge' ), // aplicațiile de dinainte de 2.3.0
	) );
	// 2.3.0: scoaterea tokenului prin POST (unele rețele și pluginuri de cache taie corpul unui DELETE)
	register_rest_route( AH_SPA_NS, '/push/sterge', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spa_r_push_sterge' ) );
	register_rest_route( AH_SPA_NS, '/descarca', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spa_r_descarca' ) );
	register_rest_route( AH_SPA_NS, '/fisier', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spa_r_fisier' ) );
	register_rest_route( AH_SPA_NS, '/sterge-cont', array( 'methods' => 'POST', 'permission_callback' => $t, 'callback' => 'ah_spa_r_sterge_cont' ) );
	register_rest_route( AH_SPA_NS, '/config', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spa_r_config' ) );
	register_rest_route( AH_SPA_NS, '/punte', array( 'methods' => 'GET', 'permission_callback' => $t, 'callback' => 'ah_spa_r_punte' ) );
} );

/* ==================================================================
   6. NOTIFICĂRILE PUSH: Firebase Cloud Messaging (HTTP v1), în loturi
   ================================================================== */

function ah_spa_b64( $s ) {
	return rtrim( strtr( base64_encode( (string) $s ), '+/', '-_' ), '=' );
}

/** Contul de serviciu Firebase (JSON din setări), sau null. Fără el nu pleacă nimic: doar jurnal. */
function ah_spa_fcm_cont( $json = null ) {
	$json = null === $json ? (string) get_option( 'ah_spa_fcm', '' ) : (string) $json;
	if ( '' === trim( $json ) ) {
		return null;
	}
	$j = json_decode( $json, true );
	if ( ! is_array( $j ) || empty( $j['project_id'] ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
		return null;
	}
	return $j;
}

/** JWT-ul RS256 pentru tokenul OAuth Google. */
function ah_spa_fcm_jwt( $sa, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$cap  = ah_spa_b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$corp = ah_spa_b64( wp_json_encode( array(
		'iss'   => $sa['client_email'],
		'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
		'aud'   => ! empty( $sa['token_uri'] ) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token',
		'iat'   => $acum,
		'exp'   => $acum + 3600,
	) ) );
	$cheie = openssl_pkey_get_private( (string) $sa['private_key'] );
	$semn  = '';
	if ( ! $cheie || ! openssl_sign( $cap . '.' . $corp, $semn, $cheie, OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	return $cap . '.' . $corp . '.' . ah_spa_b64( $semn );
}

/** Tokenul OAuth (ținut 50 de minute). */
function ah_spa_fcm_acces( $sa ) {
	$k = 'ah_spa_fcm_at_' . md5( $sa['client_email'] );
	$v = get_transient( $k );
	if ( is_string( $v ) && '' !== $v ) {
		return $v;
	}
	$jwt = ah_spa_fcm_jwt( $sa );
	if ( '' === $jwt ) {
		ah_spa_jurnal( 'push: cheia contului de serviciu nu se poate citi' );
		return '';
	}
	$r = wp_remote_post( ! empty( $sa['token_uri'] ) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token', array(
		'timeout' => 15,
		'body'    => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ),
	) );
	$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['access_token'] ) ) {
		ah_spa_jurnal( 'push: Google nu a dat tokenul (' . ( is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r ) ) . ')' );
		return '';
	}
	set_transient( $k, (string) $j['access_token'], 50 * MINUTE_IN_SECONDS );
	return (string) $j['access_token'];
}

/** Mesajul FCM pentru un telefon. „data.url” = ce se deschide la atingere. */
function ah_spa_fcm_mesaj( $token, $titlu, $text, $date ) {
	return array(
		'message' => array(
			'token'        => (string) $token,
			'notification' => array( 'title' => (string) $titlu, 'body' => (string) $text ),
			'data'         => array_map( 'strval', (array) $date ),
			'android'      => array( 'priority' => 'high', 'notification' => array( 'color' => '#964E46', 'icon' => 'ic_stat_lotus' ) ), // pictograma albă din aplicație (res/drawable)
			'apns'         => array( 'payload' => array( 'aps' => array( 'sound' => 'default' ) ) ),
		),
	);
}

/**
 * Trimite la rândurile date. Întoarce câte au plecat; null dacă Google nu a dat tokenul (lotul se reia mai târziu).
 * Fără cheie: 0 și jurnal. Tokenurile pe care Firebase nu le mai cunoaște se șterg.
 */
function ah_spa_trimite_push( $randuri, $titlu, $text, $date = array(), $jurnal = true ) {
	$randuri = array_values( (array) $randuri );
	if ( ! $randuri ) {
		return 0;
	}
	$sa = ah_spa_fcm_cont();
	if ( ! $sa ) {
		ah_spa_jurnal( 'push OPRIT (fără cheia Firebase), ar fi plecat la ' . count( $randuri ) . ' telefoane: ' . $titlu );
		return 0;
	}
	$at = ah_spa_fcm_acces( $sa );
	if ( '' === $at ) {
		return null;
	}
	global $wpdb;
	$url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send';
	$ok  = 0;
	foreach ( $randuri as $r ) {
		$res = wp_remote_post( $url, array(
			'timeout' => 3,
			'headers' => array( 'Authorization' => 'Bearer ' . $at, 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( ah_spa_fcm_mesaj( $r['token'], $titlu, $text, $date ) ),
		) );
		$cod = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $cod ) {
			$ok++;
			continue;
		}
		$corp = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		if ( 404 === $cod || false !== strpos( $corp, 'UNREGISTERED' ) || ( 400 === $cod && false !== strpos( $corp, 'registration token' ) ) ) {
			$wpdb->delete( ah_spa_tabel(), array( 'id' => (int) $r['id'] ) );
		}
	}
	if ( $jurnal ) {
		ah_spa_jurnal( 'push: ' . $titlu . ' — ' . $ok . ' din ' . count( $randuri ) );
	}
	return $ok;
}

/** Următoarele rânduri din tabel, după id-ul $dupa. */
function ah_spa_push_dupa( $dupa, $cate = AH_SPA_LOT ) {
	global $wpdb;
	$r = $wpdb->get_results( $wpdb->prepare( 'SELECT id, email, token, platforma FROM ' . ah_spa_tabel() . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $dupa, (int) $cate ), ARRAY_A );
	return is_array( $r ) ? $r : array();
}

/* Lucrările (o notificare către mai multe telefoane) stau în opțiunea ah_spa_lucrari, cu un cursor; le duce cronul, în loturi. */
function ah_spa_lucrari() {
	$l = get_option( 'ah_spa_lucrari', array() );
	return is_array( $l ) ? $l : array();
}
function ah_spa_lucrare_noua( $tip, $param, $titlu, $text, $url ) {
	$l  = ah_spa_lucrari();
	$id = $tip . '-' . substr( md5( wp_json_encode( $param ) . '|' . microtime( true ) . '|' . wp_rand() ), 0, 10 );
	$l[ $id ] = array( 'tip' => $tip, 'param' => (array) $param, 'titlu' => (string) $titlu, 'text' => (string) $text, 'url' => (string) $url, 'cursor' => 0, 'trimise' => 0, 'creat' => time() );
	update_option( 'ah_spa_lucrari', $l, false );
	if ( ! wp_next_scheduled( 'ah_spa_lot' ) ) {
		wp_schedule_single_event( time(), 'ah_spa_lot' );
	}
	ah_spa_jurnal( 'lucrare nouă: ' . $tip . ' · ' . $titlu );
	return $id;
}

/** Cine primește o lucrare. Filtrul ah_spa_destinatar poate adăuga tipuri noi. */
function ah_spa_destinatar( $j, $email ) {
	static $cache = array();
	$k = md5( wp_json_encode( array( $j['tip'], $j['param'] ) ) );
	if ( ! isset( $cache[ $k ] ) ) {
		$cache[ $k ] = array();
	}
	if ( isset( $cache[ $k ][ $email ] ) ) {
		return $cache[ $k ][ $email ];
	}
	$da = false;
	$p  = (array) $j['param'];
	switch ( $j['tip'] ) {
		case 'meditatie':
			$da = function_exists( 'ah_med_poate' ) && function_exists( 'ah_med_acces' ) && ah_med_poate( (int) ( $p['id'] ?? 0 ), ah_med_acces( $email ) );
			break;
		case 'inreg':
		case 'intalnire':
			if ( ! isset( $cache[ $k ]['__lista'] ) ) {
				$cache[ $k ]['__lista'] = function_exists( 'ah_respira_inreg_destinatare' ) ? array_change_key_case( (array) ah_respira_inreg_destinatare( (int) ( $p['zi'] ?? 0 ) ), CASE_LOWER ) : array();
			}
			$da = isset( $cache[ $k ]['__lista'][ strtolower( $email ) ] );
			break;
		case 'proba':
			$da = strtolower( (string) ( $p['email'] ?? '' ) ) === strtolower( $email );
			break;
	}
	return $cache[ $k ][ $email ] = (bool) apply_filters( 'ah_spa_destinatar', $da, $j, $email );
}

/**
 * Un lot: cel mult $buget secunde (20), verificate înaintea fiecărui telefon. Cursorul se scrie după fiecare notificare
 * plecată, așa că o oprire la mijloc (timpul PHP, FCM încet) repetă cel mult telefonul la care s-a oprit.
 * Lacătul (120 s) e mai lung decât cel mai rău caz: 20 s + un apel FCM (3 s) + tokenul Google (15 s).
 */
function ah_spa_lot( $buget = 20 ) {
	$buget = is_numeric( $buget ) && $buget > 0 ? (float) $buget : 20.0;
	if ( get_transient( 'ah_spa_lot_lacat' ) ) {
		return;
	}
	set_transient( 'ah_spa_lot_lacat', 1, 120 );
	ah_spa_cron_noteaza( 'lot' );
	$t0     = microtime( true );
	$amana  = false;
	$gata_t = false;
	$cheie  = (bool) ah_spa_fcm_cont();
	$scrie  = function ( $id, $j ) {
		$l = ah_spa_lucrari();
		if ( isset( $l[ $id ] ) ) {
			$l[ $id ] = $j;
			update_option( 'ah_spa_lucrari', $l, false );
		}
	};
	foreach ( array_keys( ah_spa_lucrari() ) as $id ) {
		while ( ! $gata_t ) {
			$l = ah_spa_lucrari();
			if ( ! isset( $l[ $id ] ) ) {
				break;
			}
			$j       = $l[ $id ];
			$randuri = ah_spa_push_dupa( $j['cursor'], AH_SPA_LOT );
			if ( ! $randuri ) {
				unset( $l[ $id ] );
				update_option( 'ah_spa_lucrari', $l, false );
				ah_spa_jurnal( 'lucrare gata: ' . $j['tip'] . ' · ' . $j['titlu'] . ' — ' . (int) $j['trimise'] . ' telefoane' );
				break;
			}
			$date = array( 'url' => $j['url'], 'tip' => $j['tip'] );
			if ( ! $cheie ) {
				// fără cheia Firebase nu pleacă nimic: un singur rând de jurnal pe grup, apoi mai departe
				$cui = array_values( array_filter( $randuri, function ( $r ) use ( $j ) { return ah_spa_destinatar( $j, (string) $r['email'] ); } ) );
				if ( $cui ) {
					ah_spa_trimite_push( $cui, $j['titlu'], $j['text'], $date );
				}
				$ultim       = end( $randuri );
				$j['cursor'] = (int) $ultim['id'];
				$scrie( $id, $j );
				continue;
			}
			$trimise0 = (int) $j['trimise'];
			foreach ( $randuri as $r ) {
				if ( microtime( true ) - $t0 >= $buget ) {
					$gata_t = true;
					break;
				}
				if ( ah_spa_destinatar( $j, (string) $r['email'] ) ) {
					$rez = ah_spa_trimite_push( array( $r ), $j['titlu'], $j['text'], $date, false );
					if ( null === $rez ) {
						$amana = true; // Google nu a dat tokenul: telefonul acesta rămâne pentru data viitoare
						break;
					}
					$j['trimise'] += (int) $rez;
					$j['cursor']   = (int) $r['id'];
					$scrie( $id, $j ); // după fiecare notificare plecată: fără dubluri dacă cererea se oprește aici
					continue;
				}
				$j['cursor'] = (int) $r['id'];
			}
			$scrie( $id, $j );
			if ( (int) $j['trimise'] > $trimise0 ) {
				ah_spa_jurnal( 'push: ' . $j['titlu'] . ' — ' . ( (int) $j['trimise'] - $trimise0 ) . ' telefoane în acest lot' );
			}
			if ( $amana ) {
				break 2;
			}
		}
		if ( $gata_t ) {
			break;
		}
	}
	delete_transient( 'ah_spa_lot_lacat' );
	if ( ah_spa_lucrari() && ! wp_next_scheduled( 'ah_spa_lot' ) ) {
		wp_schedule_single_event( time() + ( $amana ? 300 : 10 ), 'ah_spa_lot' );
	}
}
add_action( 'ah_spa_lot', 'ah_spa_lot' );

/* ---------- declanșatorii ---------- */

/* Meditație nouă publicată: anunțul pleacă după 2 minute (poza și sunetul sunt deja salvate), celor care o pot asculta. */
add_action( 'transition_post_status', function ( $nou, $vechi, $post ) {
	if ( ! is_object( $post ) || 'ah_meditatie' !== $post->post_type || 'publish' !== $nou || 'publish' === $vechi ) {
		return;
	}
	wp_schedule_single_event( time() + 120, 'ah_spa_meditatie_noua', array( (int) $post->ID ) );
}, 10, 3 );
function ah_spa_meditatie_noua( $id ) {
	$id = (int) $id;
	if ( ! (int) ah_spa_setari()['push_meditatii'] || 'publish' !== get_post_status( $id ) || get_post_meta( $id, '_ah_spa_anuntat', true ) ) {
		return;
	}
	update_post_meta( $id, '_ah_spa_anuntat', time() );
	$sp = home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' );
	ah_spa_lucrare_noua( 'meditatie', array( 'id' => $id ), 'O meditație nouă în Inner Temple', html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ), $sp . '#meditatii' ); // CIORNĂ
}
add_action( 'ah_spa_meditatie_noua', 'ah_spa_meditatie_noua' );

/** Înregistrările „Respiră-te acasă” de anunțat: publicate (după pornirea fragmentului), încă active cel puțin o oră. Pură. */
function ah_spa_inreg_de_anuntat( $inreg, $trimise, $acum, $pornit = 0, $ore = 24 ) {
	$o = array();
	foreach ( (array) $inreg as $zi => $r ) {
		$pub = is_array( $r ) ? (int) ( $r['publicat'] ?? 0 ) : 0;
		if ( ! $pub || '' === trim( (string) ( $r['url'] ?? '' ) ) || ! empty( $r['retras'] ) ) {
			continue;
		}
		if ( $pub < (int) $pornit - 600 || $acum >= $pub + $ore * 3600 - 3600 ) {
			continue;
		}
		if ( isset( $trimise[ $zi ] ) && (int) $trimise[ $zi ] === $pub ) {
			continue;
		}
		$o[ (int) $zi ] = $pub;
	}
	return $o;
}

/** Întâlnirile care încep în următoarele 15 minute și n-au fost anunțate. Pură. */
function ah_spa_intalniri_de_anuntat( $lista, $trimise, $acum ) {
	$o = array();
	foreach ( (array) $lista as $x ) {
		if ( empty( $x['id'] ) || empty( $x['start'] ) || isset( $trimise[ $x['id'] ] ) ) {
			continue;
		}
		$dif = (int) $x['start'] - (int) $acum;
		if ( $dif > 0 && $dif <= 15 * 60 + 60 ) {
			$o[] = $x;
		}
	}
	return $o;
}

/** Întâlnirile cunoscute: Respiră-te acasă (220); alte module le pot adăuga prin filtrul ah_spa_intalniri. */
function ah_spa_intalniri() {
	$l = array();
	if ( function_exists( 'ah_respira_start' ) && function_exists( 'ah_respira_teme' ) ) {
		$teme = ah_respira_teme();
		foreach ( ah_respira_start() as $zi => $t ) {
			$l[] = array( 'id' => 'respira-' . (int) $zi . '-' . (int) $t, 'start' => (int) $t, 'tema' => (string) ( $teme[ $zi ] ?? '' ), 'tip' => 'intalnire', 'param' => array( 'zi' => (int) $zi ) );
		}
	}
	return (array) apply_filters( 'ah_spa_intalniri', $l );
}

/** La 5 minute: înregistrările noi, întâlnirile care încep curând și lucrările rămase. */
function ah_spa_tick() {
	ah_spa_cron_noteaza( 'tick' );
	if ( 0 === wp_rand( 0, 11 ) ) {
		ah_spa_contoare_curata(); // cam o dată pe oră (cronul rulează la 5 minute)
	}
	$s    = ah_spa_setari();
	$acum = time();
	$sp   = home_url( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/' );
	$tr   = get_option( 'ah_spa_trimis', array() );
	$tr   = is_array( $tr ) ? $tr : array();
	$sch  = false;
	if ( (int) $s['push_inreg'] && function_exists( 'ah_respira_inreg_toate' ) ) {
		$teme = function_exists( 'ah_respira_teme' ) ? ah_respira_teme() : array();
		foreach ( ah_spa_inreg_de_anuntat( ah_respira_inreg_toate(), (array) ( $tr['inreg'] ?? array() ), $acum, (int) get_option( 'ah_spa_pornit', 0 ), defined( 'AH_RESPIRA_INREG_ORE' ) ? AH_RESPIRA_INREG_ORE : 24 ) as $zi => $pub ) {
			$tr['inreg'][ $zi ] = $pub;
			$sch                = true;
			ah_spa_lucrare_noua( 'inreg', array( 'zi' => $zi ), 'Înregistrarea te așteaptă', 'Înregistrarea de la „' . ( $teme[ $zi ] ?? 'Respiră-te acasă' ) . '” e în Spațiul meu, 24 de ore.', $sp . '#acasa' ); // CIORNĂ
		}
	}
	if ( (int) $s['push_intalniri'] ) {
		foreach ( ah_spa_intalniri_de_anuntat( ah_spa_intalniri(), (array) ( $tr['intalniri'] ?? array() ), $acum ) as $x ) {
			$tr['intalniri'][ $x['id'] ] = $acum;
			$sch                         = true;
			ah_spa_lucrare_noua( $x['tip'] ?? 'intalnire', $x['param'] ?? array(), 'Ne vedem în 15 minute', ( '' !== ( $x['tema'] ?? '' ) ? '„' . $x['tema'] . '” începe la ' : 'Întâlnirea începe la ' ) . wp_date( 'H:i', (int) $x['start'] ) . '. Intră din Spațiul meu.', $sp . '#acasa' ); // CIORNĂ
		}
	}
	if ( $sch ) {
		update_option( 'ah_spa_trimis', $tr, false );
	}
	if ( ah_spa_lucrari() ) {
		ah_spa_lot();
	}
}
add_action( 'ah_spa_tick', 'ah_spa_tick' );
add_filter( 'cron_schedules', function ( $s ) {
	$s['ah_spa_5min'] = array( 'interval' => 300, 'display' => 'Aplicația mobilă – la 5 minute' );
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ah_spa_tick' ) ) {
		wp_schedule_event( time() + 60, 'ah_spa_5min', 'ah_spa_tick' );
	}
} );
/* O înregistrare publicată din Instrumente › Respiră-te acasă: verificăm imediat, nu peste 5 minute. */
add_action( 'update_option_ah_respira_inregistrari', function () {
	if ( ! wp_next_scheduled( 'ah_spa_tick', array( 'acum' ) ) ) {
		wp_schedule_single_event( time() + 20, 'ah_spa_tick', array( 'acum' ) );
	}
} );

/* ==================================================================
   7. LINKURILE UNIVERSALE ȘI /app/intra
   ================================================================== */

/** apple-app-site-association: Spațiul meu (și linkul personal ?cont= din emailuri) și /app/intra. */
function ah_spa_aasa() {
	$team = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ah_spa_setari()['ios_team'] ) );
	$id   = 10 === strlen( $team ) ? $team . '.' . AH_SPA_PACHET : '';
	$sp   = rtrim( defined( 'AH_SP_PAGINA' ) ? AH_SP_PAGINA : '/spatiul-meu/', '/' );
	// aceleași căi ca App Links din AndroidManifest.xml (path + pathPrefix cu „/”), ca linkul să se poarte la fel pe ambele;
	// /pentru-el = pagina partenerului (ah-sp-partener.php): linkul personal /pentru-el/?t= din emailul lui deschide aplicația
	$cai  = array( $sp, $sp . '/*', '/app/intra', '/app/intra/*', '/pentru-el', '/pentru-el/*' );
	return array(
		'applinks' => array(
			'apps'    => array(),
			'details' => $id ? array( array(
				'appID'      => $id,
				'appIDs'     => array( $id ),
				'paths'      => $cai,
				'components' => array_map( function ( $c ) { return array( '/' => $c ); }, $cai ),
			) ) : array(),
		),
	);
}

/** assetlinks.json (Android): una sau mai multe amprente (cheia de încărcare și cea de la Google Play). */
function ah_spa_assetlinks() {
	$amp = array();
	foreach ( preg_split( '/[\s,;]+/', strtoupper( (string) ah_spa_setari()['android_sha256'] ) ) as $a ) {
		if ( preg_match( '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $a ) ) {
			$amp[] = $a;
		}
	}
	return $amp ? array( array(
		'relation' => array( 'delegate_permission/common.handle_all_urls' ),
		'target'   => array( 'namespace' => 'android_app', 'package_name' => AH_SPA_PACHET, 'sha256_cert_fingerprints' => array_values( array_unique( $amp ) ) ),
	) ) : array();
}

function ah_spa_json_static( $date ) {
	status_header( 200 );
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: public, max-age=3600' );
	echo wp_json_encode( $date, JSON_UNESCAPED_SLASHES ); // phpcs:ignore
	exit;
}

/** /app/intra: pentru cine deschide linkul fără aplicație. CIORNĂ. */
function ah_spa_pagina_intra( $q ) {
	$s   = ah_spa_setari();
	$sp  = ah_spa_dest_intra( $q );
	$mag = '';
	if ( $s['link_ios'] ) {
		$mag .= '<a class="b" href="' . esc_url( $s['link_ios'] ) . '">App Store</a>';
	}
	if ( $s['link_android'] ) {
		$mag .= '<a class="b" href="' . esc_url( $s['link_android'] ) . '">Google Play</a>';
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Aplicația Adela Haru</title>'
		. '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(170deg,#FBF6F3,#F3EAF2);font:17px/1.6 Figtree,system-ui,-apple-system,sans-serif;color:#6E5246;padding:24px 16px;box-sizing:border-box}'
		. '.c{max-width:440px;width:100%;background:rgba(255,255,255,.8);border:1px solid #fff;border-radius:28px;padding:32px 24px;box-shadow:0 30px 60px -40px rgba(110,70,110,.6)}'
		. 'h1{font:400 32px/1.2 "Gilda Display",Georgia,serif;color:#4E3D34;margin:0 0 12px}p{margin:0 0 14px}.l{font:italic 16px "Cormorant Garamond",Georgia,serif;color:#964E46;margin:0 0 6px}'
		. '.b{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 22px;border-radius:999px;background:#C13C30;color:#fff;text-decoration:none;margin:0 8px 10px 0;font-weight:500}'
		. '.s{color:#964E46;text-decoration:underline;text-underline-offset:3px}</style></head><body><div class="c"><p class="l">Adela Haru</p><h1>Spațiul meu, în aplicație</h1>'
		. ( $mag ? '<p>Cursurile, meditațiile Inner Temple, cărțile și Ciclul meu, mereu la îndemână pe telefon.</p><p>' . $mag . '</p>' : '<p>Aplicația Adela Haru va fi în curând în App Store și Google Play.</p>' )
		. '<p>Până atunci, Spațiul meu te așteaptă și în browser: <a class="s" href="' . esc_url( $sp ) . '">deschide Spațiul meu</a>.</p></div></body></html>';
	exit;
}

add_action( 'parse_request', function () {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
	$c   = ah_spa_cale( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	$f   = rtrim( $c, '/' );
	if ( '/.well-known/apple-app-site-association' === $f || '/apple-app-site-association' === $f ) {
		ah_spa_json_static( ah_spa_aasa() );
	}
	if ( '/.well-known/assetlinks.json' === $f ) {
		ah_spa_json_static( ah_spa_assetlinks() );
	}
	if ( 0 === strpos( $c, '/app/' ) ) {
		$q = array();
		parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $q );
		if ( ah_spa_e_aplicatie() ) {
			ah_spa_fara_cache( 'ah spa intra' );
			wp_safe_redirect( ah_spa_dest_intra( $q ), 302 );
			exit;
		}
		ah_spa_pagina_intra( $q );
	}
}, 0 );

/* ==================================================================
   8. SETĂRI › APLICAȚIA MOBILĂ
   ================================================================== */

add_action( 'admin_menu', function () {
	add_options_page( 'Aplicația mobilă', 'Aplicația mobilă', 'manage_options', 'ah-spa', 'ah_spa_pagina_setari' );
} );

/** Curăță setările trimise din formular. Pură (testată). */
function ah_spa_curata_setari( $p, $vechi ) {
	$o   = $vechi;
	$ver = function ( $v ) { $v = trim( (string) $v ); return preg_match( '/^[0-9]+(\.[0-9]+){0,3}$/', $v ) ? $v : ''; };
	$o['ios_team'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $p['ios_team'] ?? '' ) ) );
	if ( 10 !== strlen( $o['ios_team'] ) ) {
		$o['ios_team'] = '';
	}
	$amp = array();
	foreach ( preg_split( '/[\s,;]+/', strtoupper( (string) ( $p['android_sha256'] ?? '' ) ) ) as $a ) {
		if ( preg_match( '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $a ) ) {
			$amp[] = $a;
		}
	}
	$o['android_sha256'] = implode( "\n", array_unique( $amp ) );
	foreach ( array( 'link_ios' => '~^https://apps\.apple\.com/~', 'link_android' => '~^https://play\.google\.com/~' ) as $k => $re ) {
		$u       = esc_url_raw( trim( (string) ( $p[ $k ] ?? '' ) ) );
		$o[ $k ] = preg_match( $re, $u ) ? $u : '';
	}
	$o['min_ios']      = $ver( $p['min_ios'] ?? '' );
	$o['min_android']  = $ver( $p['min_android'] ?? '' );
	$o['mesaj']        = sanitize_text_field( (string) ( $p['mesaj'] ?? '' ) );
	$o['email_echipa'] = is_email( trim( (string) ( $p['email_echipa'] ?? '' ) ) ) ? strtolower( trim( (string) $p['email_echipa'] ) ) : 'contact@adelaharu.ro';
	foreach ( array( 'push_meditatii', 'push_inreg', 'push_intalniri' ) as $k ) {
		$o[ $k ] = empty( $p[ $k ] ) ? 0 : 1;
	}
	return $o;
}

add_action( 'admin_post_ah_spa_setari', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nu ai voie aici.' );
	}
	check_admin_referer( 'ah_spa_setari' );
	$p = wp_unslash( $_POST ); // phpcs:ignore
	update_option( 'ah_spa_setari', ah_spa_curata_setari( $p, ah_spa_setari() ), false );
	list( $rec, $rec_err ) = ah_spa_curata_recenzie( $p, ah_spa_recenzie() );
	update_option( 'ah_spa_recenzie', $rec, false ); // non-autoload: se citește doar la /cod, /intra și aici
	if ( ! $rec['oprit'] ) {
		ah_spa_contor_sterge( 'rec_tot' ); // pornit din nou (sau cod nou): greșelile de dinainte nu mai contează
	}
	$msg = '' !== $rec_err ? 'recenzie_' . $rec_err : 'salvat';
	if ( ! empty( $p['sterge_fcm'] ) ) {
		delete_option( 'ah_spa_fcm' );
	} elseif ( '' !== trim( (string) ( $p['fcm'] ?? '' ) ) ) {
		if ( ah_spa_fcm_cont( (string) $p['fcm'] ) ) {
			update_option( 'ah_spa_fcm', trim( (string) $p['fcm'] ), false );
		} else {
			$msg = 'fcm';
		}
	}
	if ( ! empty( $p['proba'] ) && is_email( trim( (string) $p['proba'] ) ) ) {
		ah_spa_lucrare_noua( 'proba', array( 'email' => strtolower( trim( (string) $p['proba'] ) ) ), 'Probă din Adela Haru', 'Dacă vezi mesajul acesta, notificările merg.', home_url( '/spatiul-meu/' ) ); // CIORNĂ
		ah_spa_lot();
		$msg = 'proba';
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'ah-spa', 'm' => $msg ), admin_url( 'options-general.php' ) ) );
	exit;
} );

function ah_spa_pagina_setari() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s   = ah_spa_setari();
	$sa  = ah_spa_fcm_cont();
	$m   = isset( $_GET['m'] ) ? sanitize_key( wp_unslash( $_GET['m'] ) ) : ''; // phpcs:ignore
	global $wpdb;
	$n   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ah_spa_tabel() ); // phpcs:ignore
	$rand = function ( $et, $camp, $ajutor = '' ) {
		return '<tr><th scope="row">' . $et . '</th><td>' . $camp . ( $ajutor ? '<p class="description">' . $ajutor . '</p>' : '' ) . '</td></tr>';
	};
	$t = function ( $k, $v, $ph = '' ) {
		return '<input type="text" class="regular-text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '" placeholder="' . esc_attr( $ph ) . '">';
	};
	$c = function ( $k, $v, $et ) {
		return '<label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . ( $v ? ' checked' : '' ) . '> ' . esc_html( $et ) . '</label><br>';
	};
	echo '<div class="wrap"><h1>Aplicația mobilă Adela Haru</h1>';
	if ( 'salvat' === $m ) {
		echo '<div class="notice notice-success"><p>Setările sunt salvate.</p></div>';
	} elseif ( 'fcm' === $m ) {
		echo '<div class="notice notice-error"><p>Cheia Firebase nu pare un JSON de cont de serviciu (project_id, client_email, private_key). Celelalte setări sunt salvate.</p></div>';
	} elseif ( 'proba' === $m ) {
		echo '<div class="notice notice-info"><p>Proba a plecat (vezi jurnalul de mai jos).</p></div>';
	} elseif ( 'recenzie_email' === $m ) {
		echo '<div class="notice notice-error"><p>Adresa de recenzie nu pare o adresă de email: contul de recenzie e oprit (câmpul a rămas gol). Celelalte setări sunt salvate.</p></div>';
	} elseif ( 'mutat' === $m ) {
		$mm = get_transient( 'ah_spa_muta_rez_' . get_current_user_id() );
		$t1 = array();
		foreach ( (array) $mm as $k => $v ) {
			$t1[] = esc_html( $k . ': ' . (int) $v );
		}
		echo '<div class="notice notice-success"><p>Contul a fost mutat. ' . ( $t1 ? 'Mutat: ' . implode( ', ', $t1 ) . '.' : 'Nu era nimic de mutat (sau totul exista deja pe adresa nouă).' ) . ' Nu uita de comenzi (adresa de facturare).</p></div>'; // phpcs:ignore
	} elseif ( 0 === strpos( $m, 'muta_' ) ) {
		echo '<div class="notice notice-error"><p>Contul NU a fost mutat: ' . ( 'muta_aceeasi' === $m ? 'adresele sunt aceleași.' : 'scrie două adrese de email valide.' ) . '</p></div>';
	} elseif ( 0 === strpos( $m, 'recenzie_' ) ) {
		echo '<div class="notice notice-error"><p>Codul de recenzie NU s-a schimbat: trebuie să fie exact 6 cifre, greu de ghicit (nu 123456, 654321 sau aceeași cifră de 6 ori). Celelalte setări sunt salvate.</p></div>';
	}
	echo '<p>Fragmentul „Spațiul meu – modul aplicație” lucrează doar în aplicație. ' . (int) $n . ' telefoane primesc notificări.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ah_spa_setari">';
	wp_nonce_field( 'ah_spa_setari' );
	echo '<h2>Linkurile universale</h2><table class="form-table">'
		. $rand( 'Apple Team ID', $t( 'ios_team', $s['ios_team'], 'ABCDE12345' ), '10 caractere, din developer.apple.com › Membership. Pachetul: ' . AH_SPA_PACHET . '.' )
		. $rand( 'Amprentele SHA-256 (Android)', '<textarea name="android_sha256" rows="3" class="large-text code">' . esc_textarea( $s['android_sha256'] ) . '</textarea>', 'Din Google Play Console › Integritatea aplicației › Semnarea aplicației: cheia de semnare a aplicației și cheia de încărcare, câte una pe rând.' )
		. '</table><h2>Magazinele și versiunea minimă</h2><table class="form-table">'
		. $rand( 'Link App Store', $t( 'link_ios', $s['link_ios'], 'https://apps.apple.com/…' ) )
		. $rand( 'Link Google Play', $t( 'link_android', $s['link_android'], 'https://play.google.com/store/apps/details?id=' . AH_SPA_PACHET ) )
		. $rand( 'Versiunea minimă iPhone', $t( 'min_ios', $s['min_ios'], '1.0.0' ), 'Sub ea, aplicația arată o bandă cu „Actualizează”. Gol = fără bandă.' )
		. $rand( 'Versiunea minimă Android', $t( 'min_android', $s['min_android'], '1.0.0' ) )
		. $rand( 'Mesajul din bandă', $t( 'mesaj', $s['mesaj'], 'Actualizeaz-o, ca totul să meargă bine.' ) )
		. '</table><h2>Notificări</h2><table class="form-table">'
		. $rand( 'Cheia Firebase', '<p>' . ( $sa ? 'Pusă: proiectul <code>' . esc_html( $sa['project_id'] ) . '</code>, contul <code>' . esc_html( $sa['client_email'] ) . '</code>. <label><input type="checkbox" name="sterge_fcm" value="1"> Șterge cheia</label>' : '<strong>Lipsește.</strong> Până o pui, notificările nu pleacă; apar doar în jurnal.' ) . '</p><textarea name="fcm" rows="4" class="large-text code" placeholder="{&quot;type&quot;:&quot;service_account&quot;,…}"></textarea>', 'Firebase › Setările proiectului › Conturi de serviciu › Generează o cheie privată nouă. Lipește tot fișierul JSON. Cheia nu se mai afișează după salvare.' )
		. $rand( 'Ce anunțăm', $c( 'push_meditatii', $s['push_meditatii'], 'Meditație nouă (celor care o pot asculta)' ) . $c( 'push_inreg', $s['push_inreg'], 'Înregistrare „Respiră-te acasă” publicată (celor înscrise în ziua aceea)' ) . $c( 'push_intalniri', $s['push_intalniri'], 'Întâlnire care începe în 15 minute (celor înscrise)' ) )
		. $rand( 'Emailul echipei', $t( 'email_echipa', $s['email_echipa'] ), 'Aici vine anunțul când o clientă își șterge contul din aplicație.' )
		. $rand( 'Probă', $t( 'proba', '', 'adresa@exemplu.ro' ), 'O notificare de probă către telefoanele acestei adrese (trebuie să fi intrat în aplicație și să fi permis notificările).' )
		. '</table>';
	$rec = ah_spa_recenzie();
	echo '<h2>Contul de recenzie (Apple / Google)</h2><p>Recenzorii magazinelor nu au acces la nicio căsuță de email. Pentru <strong>o singură adresă</strong>, setată aici, codul de intrare din Spațiul meu e un cod fix, fără email. Accesul vine tot din comenzile reale ale adresei (comenzi manuale „Acces gratuit” / „Finalizată”, vezi docs/review-account.md). Golind adresa, funcția se oprește.</p><table class="form-table">'
		. $rand( 'Adresa de recenzie', $t( 'recenzie_email', $rec['email'], 'recenzie@adelaharu.ro' ), 'O căsuță reală, fără date personale reale. Gol = fără cont de recenzie.' )
		. $rand( 'Codul fix', '<input type="text" class="small-text" name="recenzie_cod" value="" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" placeholder="______"> <strong>Cod setat: ' . ( '' !== $rec['hash'] ? 'da' : 'nu' ) . '</strong>' . ( '' !== $rec['hash'] ? ' &nbsp; <label><input type="checkbox" name="recenzie_sterge_cod" value="1"> Șterge codul</label>' : '' ), 'Exact 6 cifre, greu de ghicit (nu 123456, 654321, 000000). Se păstrează doar amprenta lui, ca la o parolă: după salvare nu se mai afișează. Gol = rămâne codul de acum. Același cod se trece în App Store Connect (App Review Information › Password) și în Play Console (Acces la aplicație); nu în emailuri sau în chat.' )
		. $rand( 'Doar în aplicație', $c( 'recenzie_doar_app', $rec['doar_app'], 'Codul fix merge doar din aplicația Adela Haru (după User-Agent; reduce expunerea, nu e o barieră de securitate). Debifați doar dacă un recenzor vrea să verifice și pe web.' ) )
		. '</table>';
	if ( $rec['oprit'] ) {
		echo '<div class="notice notice-warning inline"><p><strong>Contul de recenzie e OPRIT</strong> din ' . esc_html( wp_date( 'Y-m-d H:i', (int) $rec['oprit'] ) ) . ', după ' . (int) AH_SPA_REC_MAX . ' coduri greșite. <label><input type="checkbox" name="recenzie_porneste" value="1"> Pornește din nou contul de recenzie</label> (sau pune un cod nou).</p></div>';
	}
	submit_button( 'Salvează' );
	echo '</form>';
	ah_spa_formular_muta();
	$cr = array();
	foreach ( array( 'ah_spa_tick' => 'ah_spa_cron_tick', 'ah_spa_lot' => 'ah_spa_cron_lot', 'ah_spp_zilnic' => 'ah_spp_ultima_rulare' ) as $k => $o ) {
		$x    = (int) get_option( $o, 0 );
		$cr[] = '<code>' . $k . '</code>: ' . ( $x ? esc_html( wp_date( 'Y-m-d H:i', $x ) ) : '—' );
	}
	echo '<p>Ultima rulare a cronului: ' . implode( ' · ', $cr ) . '</p>'; // phpcs:ignore
	echo '<h2>Adrese de verificat</h2><ul>'
		. '<li><a href="' . esc_url( home_url( '/.well-known/apple-app-site-association' ) ) . '" target="_blank">/.well-known/apple-app-site-association</a></li>'
		. '<li><a href="' . esc_url( home_url( '/.well-known/assetlinks.json' ) ) . '" target="_blank">/.well-known/assetlinks.json</a></li>'
		. '<li><a href="' . esc_url( home_url( '/app/intra' ) ) . '" target="_blank">/app/intra</a></li>'
		. '<li><a href="' . esc_url( rest_url( AH_SPA_NS . '/config' ) ) . '" target="_blank">' . esc_html( rest_url( AH_SPA_NS . '/config' ) ) . '</a></li></ul>';
	$j = array_reverse( array_slice( (array) get_option( 'ah_spa_jurnal', array() ), -50 ) );
	echo '<h2>Jurnal</h2><pre style="background:#fff;padding:12px;max-height:360px;overflow:auto">';
	foreach ( $j as $r ) {
		echo esc_html( wp_date( 'Y-m-d H:i', (int) $r[0] ) . '  ' . $r[1] ) . "\n";
	}
	echo $j ? '' : 'Nimic încă.';
	echo '</pre></div>';
}

/* ==================================================================
   9. CONTUL DE RECENZIE (Apple App Review, Google Play „Acces la aplicație”)
   Recenzorii nu au acces la nicio căsuță de email, deci pentru O SINGURĂ adresă, setată în Setări › Aplicația mobilă,
   codul din Spațiul meu e fix. Prindem doar POST /ah-sp/v1/cod și /ah-sp/v1/intra, doar pentru acea adresă, prin
   rest_pre_dispatch (fragmentul 235 rămâne neschimbat). Orice altă adresă trece neatinsă. Accesul vine tot din comenzi
   reale, fără excepții în cod. Cerința și testele: docs/review-account.md.
   ================================================================== */

/** Setarea: array( email, hash (wp_hash_password al codului, niciodată codul), doar_app ). Gol = funcția nu există. */
function ah_spa_recenzie() {
	$r = get_option( 'ah_spa_recenzie', array() );
	$r = is_array( $r ) ? $r : array();
	return array(
		'email'    => isset( $r['email'] ) ? strtolower( trim( (string) $r['email'] ) ) : '',
		'hash'     => isset( $r['hash'] ) ? (string) $r['hash'] : '',
		'doar_app' => array_key_exists( 'doar_app', $r ) ? ( empty( $r['doar_app'] ) ? 0 : 1 ) : 1,
		'oprit'    => isset( $r['oprit'] ) ? (int) $r['oprit'] : 0, // momentul opririi după prea multe coduri greșite (0 = merge)
	);
}

/** '' dacă codul se poate folosi; altfel motivul: format | repetat | sir. Pură. */
function ah_spa_recenzie_cod_slab( $cod ) {
	$cod = (string) $cod;
	if ( ! preg_match( '/^[0-9]{6}$/', $cod ) ) {
		return 'format';
	}
	if ( preg_match( '/^([0-9])\1{5}$/', $cod ) ) {
		return 'repetat'; // 000000, 111111…
	}
	if ( false !== strpos( '01234567890', $cod ) || false !== strpos( '09876543210', $cod ) ) {
		return 'sir'; // 123456, 654321, 012345, 987654…
	}
	return '';
}

/**
 * Setarea trimisă din formular → array( setarea nouă, eroare ). Eroare: '' | email | format | repetat | sir.
 * Un cod refuzat nu schimbă codul de acum. Codul gol = rămâne cel de acum. Pură (în afară de wp_hash_password).
 */
function ah_spa_curata_recenzie( $p, $vechi ) {
	$o   = array_merge( array( 'email' => '', 'hash' => '', 'doar_app' => 1, 'oprit' => 0 ), is_array( $vechi ) ? $vechi : array() );
	$err = '';
	$e   = strtolower( trim( (string) ( $p['recenzie_email'] ?? '' ) ) );
	if ( '' !== $e && ! is_email( $e ) ) {
		$err = 'email';
		$e   = '';
	}
	$o['email']    = $e;
	$o['doar_app'] = empty( $p['recenzie_doar_app'] ) ? 0 : 1;
	$cod           = trim( (string) ( $p['recenzie_cod'] ?? '' ) );
	if ( ! empty( $p['recenzie_sterge_cod'] ) ) {
		$o['hash'] = '';
	} elseif ( '' !== $cod ) {
		$m = ah_spa_recenzie_cod_slab( $cod );
		if ( '' === $m ) {
			$o['hash']  = wp_hash_password( $cod ); // doar amprenta; codul nu se păstrează nicăieri
			$o['oprit'] = 0;                        // un cod nou pornește din nou contul de recenzie
		} elseif ( '' === $err ) {
			$err = $m;
		}
	}
	if ( ! empty( $p['recenzie_porneste'] ) ) {
		$o['oprit'] = 0; // „Pornește din nou contul de recenzie” (contorul greșelilor se golește la salvare)
	}
	return array( $o, $err );
}

/** Adresa IP a cererii. */
function ah_spa_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore
}

/** IP-ul trunchiat pentru jurnal: /24 la IPv4, /48 la IPv6. Pură. */
function ah_spa_ip_scurt( $ip ) {
	$b = @inet_pton( (string) $ip ); // phpcs:ignore
	if ( false === $b || '' === (string) $ip ) {
		return '?';
	}
	if ( 4 === strlen( $b ) ) {
		return inet_ntop( substr( $b, 0, 3 ) . "\0" ) . '/24';
	}
	return inet_ntop( substr( $b, 0, 6 ) . str_repeat( "\0", 10 ) ) . '/48';
}

/** Filtrul: null (= ruta din 235 merge normal) pentru orice altă cerere decât POST /cod, /intra cu adresa de recenzie. */
function ah_spa_recenzie_filtru( $rez, $server, $req ) {
	if ( null !== $rez || ! is_object( $req ) || ! method_exists( $req, 'get_method' ) || ! method_exists( $req, 'get_route' ) ) {
		return $rez;
	}
	if ( 'POST' !== strtoupper( (string) $req->get_method() ) ) {
		return $rez;
	}
	$ruta = rtrim( (string) $req->get_route(), '/' );
	if ( '/ah-sp/v1/cod' !== $ruta && '/ah-sp/v1/intra' !== $ruta ) {
		return $rez;
	}
	if ( ! function_exists( 'ah_sp_seteaza_cookie' ) ) {
		return $rez; // fără 235 nu facem nimic
	}
	$r = ah_spa_recenzie();
	if ( '' === $r['email'] || '' === $r['hash'] || $r['oprit'] ) {
		return $rez; // oprit după prea multe coduri greșite: adresa trece pe drumul obișnuit (cod pe email, contorul din secțiunea 11)
	}
	$e = $req->get_param( 'email' );
	$e = is_scalar( $e ) ? strtolower( trim( (string) $e ) ) : ''; // ca ah_sp_email_norm din 235
	if ( $e !== $r['email'] ) {
		return $rez;
	}
	if ( $r['doar_app'] && ! ah_spa_e_aplicatie() ) {
		return $rez; // pe web, adresa de recenzie primește codul pe email, ca oricine
	}
	return '/ah-sp/v1/cod' === $ruta ? ah_spa_recenzie_cod() : ah_spa_recenzie_intra( $e, $req->get_param( 'cod' ), $r['hash'] );
}
add_filter( 'rest_pre_dispatch', 'ah_spa_recenzie_filtru', 4, 3 );

/** /cod cu adresa de recenzie: aceeași formă ca 235, fără email și fără contoarele lui (ah_sp_ip_*, ah_sp_em_*). */
function ah_spa_recenzie_cod() {
	$ip = ah_spa_ip();
	if ( ! ah_spa_limita( 'recenzie_cod|' . $ip, 30 ) ) {
		ah_spa_jurnal( 'recenzie: prea multe coduri cerute (' . ah_spa_ip_scurt( $ip ) . ')' );
		return ah_spa_raspuns( array( 'ok' => false, 'mesaj' => 'Ai cerut deja câteva coduri. Încearcă din nou peste 15 minute.' ), 429 );
	}
	ah_spa_jurnal( 'recenzie: cod cerut (' . ah_spa_ip_scurt( $ip ) . ')' );
	return ah_spa_raspuns( array( 'ok' => true ) );
}

/**
 * /intra cu adresa de recenzie: codul fix (nu expiră, nu e de unică folosință). Contoare atomice (secțiunea 11), rezervate
 * ÎNAINTE de comparare (o rafală în paralel nu primește mai multe încercări):
 *  - pe IP: 5 încercări la 15 minute, apoi 429 până trec cele 15 minute (recenzia nu se blochează singură);
 *  - pe toată adresa: după AH_SPA_REC_MAX (20) coduri greșite, contul de recenzie se OPREȘTE (opțiunea „oprit”), echipa
 *    primește un email, iar adresa trece pe drumul obișnuit (cod pe email). Îl pornește din nou administratorul
 *    (Setări › Aplicația mobilă). O intrare reușită golește ambele contoare.
 */
function ah_spa_recenzie_intra( $e, $cod, $hash ) {
	$ip  = ah_spa_ip();
	$kip = 'rec_ip|' . ah_spa_ip_cheie( $ip );
	if ( ah_spa_contor( $kip, 15 * MINUTE_IN_SECONDS ) > 5 ) {
		ah_spa_jurnal( 'recenzie: prea multe încercări (' . ah_spa_ip_scurt( $ip ) . ')' );
		return ah_spa_raspuns( array( 'ok' => false, 'mesaj' => 'Prea multe încercări. Cere un cod nou.' ), 429 );
	}
	$tot = ah_spa_contor( 'rec_tot', 10 * YEAR_IN_SECONDS );
	if ( $tot > AH_SPA_REC_MAX ) {
		ah_spa_recenzie_opreste( $tot - 1 );
		return ah_spa_raspuns( array( 'ok' => false, 'mesaj' => 'Prea multe încercări. Cere un cod nou.' ), 429 );
	}
	$cod = preg_replace( '/\D/', '', is_scalar( $cod ) ? (string) $cod : '' );
	if ( 6 !== strlen( $cod ) || ! wp_check_password( $cod, (string) $hash ) ) {
		ah_spa_jurnal( 'recenzie: cod greșit (' . ah_spa_ip_scurt( $ip ) . ', ' . $tot . ' din ' . AH_SPA_REC_MAX . ')' );
		if ( $tot >= AH_SPA_REC_MAX ) {
			ah_spa_recenzie_opreste( $tot );
		}
		return ah_spa_raspuns( array( 'ok' => false, 'mesaj' => 'Codul nu se potrivește. Verifică cifrele.' ), 403 );
	}
	ah_spa_contor_sterge( $kip );
	ah_spa_contor_sterge( 'rec_tot' );
	ah_sp_seteaza_cookie( $e );
	ah_spa_fara_cache( 'ah spa recenzie' );
	$g = ah_spa_recenzie_goleste_disp( $e );
	ah_spa_jurnal( 'recenzie: intrare (' . ah_spa_ip_scurt( $ip ) . '; dispozitive golite: meditații ' . (int) $g['meditatii'] . ', sala ' . (int) $g['sala'] . ')' );
	return ah_spa_raspuns( array( 'ok' => true ) );
}

/** Oprește contul de recenzie (o singură dată) și anunță echipa pe email. */
function ah_spa_recenzie_opreste( $greseli ) {
	$r = get_option( 'ah_spa_recenzie', array() );
	$r = is_array( $r ) ? $r : array();
	if ( ! empty( $r['oprit'] ) ) {
		return;
	}
	$r['oprit'] = time();
	update_option( 'ah_spa_recenzie', $r, false );
	ah_spa_jurnal( 'recenzie: OPRIT după ' . (int) $greseli . ' coduri greșite' );
	$catre = (string) ah_spa_setari()['email_echipa'];
	if ( is_email( $catre ) ) {
		wp_mail(
			$catre,
			'Aplicația: contul de recenzie a fost oprit',
			'Contul de recenzie (Apple / Google) a primit ' . (int) $greseli . " coduri greșite, așa că l-am oprit: codul fix nu mai merge, iar adresa de recenzie primește codul pe email, ca oricine.\n\n"
			. "Dacă recenzia e în curs: Setări › Aplicația mobilă › Contul de recenzie › bifează „Pornește din nou contul de recenzie” (sau pune un cod nou) și salvează.\n"
			. "Dacă nu e nicio recenzie în curs, cineva a încercat să ghicească codul: pune un cod nou înainte să-l pornești.\n"
		);
	}
}

/**
 * Apple testează pe mai multe telefoane, uneori în paralel: la fiecare intrare cu codul fix golim DOAR pentru adresa
 * de recenzie dispozitivele meditațiilor (51) și ale sălii de curs (44), ca butoanele „Golește dispozitivele” din admin.
 */
function ah_spa_recenzie_goleste_disp( $e ) {
	$n = array( 'meditatii' => 0, 'sala' => 0 );
	$e = strtolower( trim( (string) $e ) );
	$r = ah_spa_recenzie();
	if ( '' === $e || $e !== $r['email'] ) {
		return $n; // pentru orice altă adresă, nimic
	}
	if ( function_exists( 'ah_med_cheie_disp' ) ) {
		$k = ah_med_cheie_disp( $e );
		if ( false !== get_option( $k, false ) ) {
			delete_option( $k );
			$n['meditatii'] = 1;
		}
	}
	if ( function_exists( 'ah_sc_dispozitive' ) && function_exists( 'wc_get_orders' ) ) {
		foreach ( (array) wc_get_orders( array( 'billing_email' => $e, 'limit' => -1, 'type' => 'shop_order', 'return' => 'objects' ) ) as $c ) {
			if ( ! is_object( $c ) || ! method_exists( $c, 'delete_meta_data' ) || ! method_exists( $c, 'get_meta' ) ) {
				continue;
			}
			if ( method_exists( $c, 'get_billing_email' ) && strtolower( trim( (string) $c->get_billing_email() ) ) !== $e ) {
				continue;
			}
			if ( empty( $c->get_meta( '_ah_sc_disp' ) ) ) {
				continue;
			}
			$c->delete_meta_data( '_ah_sc_disp' );
			$c->save();
			$n['sala']++;
		}
	}
	return $n;
}

/* ==================================================================
   10. FĂRĂ ANALIZĂ ȘI URMĂRIRE ÎN APLICAȚIE
   În aplicație nu se încarcă Google Analytics (gtag, Site Kit, GTM), Microsoft Clarity, pixelul Meta
   (facebook-for-woocommerce, inclusiv partea din browser a Conversions API: fbq, _fbp/_fbc, „signals”), atribuirea
   comenzilor WooCommerce (sourcebuster, order-attribution) și bara de cookie-uri Complianz. Fără ele, în aplicație
   rămân doar cookie-uri strict necesare (ah_sp, ah_app, ah_med, ale sălii), iar Vimeo merge cu dnt=1: nu e nimic
   pentru care să cerem acord, deci bara nu mai are rost (art. 5 alin. 3 din Directiva 2002/58, Legea 506/2004 art. 4
   alin. 6). Pe site, în browser, nu se schimbă nimic: bara și scripturile rămân exact ca acum.
   Trei straturi, toate doar când ah_spa_e_aplicatie():
     a) la sursă: filtrele pluginurilor și scoaterea din coada de scripturi/stiluri (wp_enqueue_scripts, wp_print_*);
     b) ce e tipărit direct în pagină (fragmentul GA al Complianz, Clarity, pixelul): ieșirea paginilor din aplicație
        trece prin ah_spa_fara_urmarire() (un singur ob_start, doar pe Spațiul meu, sala, cărți, politici);
     c) Conversions API trimis de server în timpul unei cereri din aplicație: oprit la pre_http_request.
   ================================================================== */

/** Tiparele adreselor de urmărire (în src, href, data-cmplz-src). Filtrul ah_spa_urmarire_tipare le poate completa. */
function ah_spa_urmarire_tipare() {
	return array_values( (array) apply_filters( 'ah_spa_urmarire_tipare', array(
		'googletagmanager\.com',
		'google-analytics\.com',
		'analytics\.google\.com',
		'connect\.facebook\.net',
		'facebook\.com/tr',
		'clarity\.ms',
		'/sourcebuster/',
		'order-attribution',
		'/facebook-for-woocommerce/',
		'/complianz-gdpr/',
		'/uploads/complianz/',
		'/google-site-kit/',
		'/duracelltomi-google-tag-manager/',
	) ) );
}

/** Adevărat dacă scriptul / stilul (după „handle” și adresă) e de urmărire sau e bara de cookie-uri. Pură. */
function ah_spa_e_urmarire( $handle, $src = '' ) {
	$h = strtolower( (string) $handle );
	if ( preg_match( '/^(ahsp|ahspa|ahspx)/', $h ) || 'cmplz-document' === $h ) {
		return false; // ale noastre; stilul documentelor Complianz (politica de cookie-uri, pagina legală) rămâne
	}
	if ( preg_match( '/^(cmplz-|complianz|sourcebuster|wc-order-attribution|wc-facebook|facebook-for-woocommerce|google_gtagjs|googlesitekit-consent|gtm4wp|clarity)/', $h ) ) {
		return true;
	}
	$src = (string) $src;
	return '' !== $src && (bool) preg_match( '~' . implode( '|', ah_spa_urmarire_tipare() ) . '~i', $src );
}

/** a) Scoate din coadă scripturile și stilurile de urmărire (și ce depinde de ele). */
function ah_spa_scoate_urmarire_coada() {
	if ( ! ah_spa_e_aplicatie() || is_admin() ) {
		return;
	}
	foreach ( array( 'wp_scripts' => 'wp_dequeue_script', 'wp_styles' => 'wp_dequeue_style' ) as $g => $f ) {
		$o = isset( $GLOBALS[ $g ] ) ? $GLOBALS[ $g ] : null;
		if ( ! is_object( $o ) || empty( $o->queue ) || ! function_exists( $f ) ) {
			continue;
		}
		foreach ( (array) $o->queue as $h ) {
			$src = isset( $o->registered[ $h ] ) && is_object( $o->registered[ $h ] ) ? (string) $o->registered[ $h ]->src : '';
			if ( ah_spa_e_urmarire( $h, $src ) ) {
				$f( $h );
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ah_spa_scoate_urmarire_coada', 9999 );
add_action( 'wp_print_styles', 'ah_spa_scoate_urmarire_coada', 9999 );
add_action( 'wp_print_scripts', 'ah_spa_scoate_urmarire_coada', 9999 );
add_action( 'wp_print_footer_scripts', 'ah_spa_scoate_urmarire_coada', 1 );

/* a) Filtrele pluginurilor (dacă un plugin lipsește sau își schimbă filtrul, rămân b și c). */
$ah_spa_nu = function ( $v ) {
	return ah_spa_e_aplicatie() ? false : $v;
};
$ah_spa_da = function ( $v ) {
	return ah_spa_e_aplicatie() ? true : $v;
};
add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', $ah_spa_nu, 99 ); // pixelul Meta și evenimentele lui
add_filter( 'wc_order_attribution_allow_tracking', $ah_spa_nu, 99 );                // atribuirea comenzilor WooCommerce
add_filter( 'cmplz_site_needs_cookiewarning', $ah_spa_nu, 99 );                     // bara Complianz
add_filter( 'googlesitekit_analytics-4_tag_blocked', $ah_spa_da, 99 );              // Site Kit, dacă e folosit
add_filter( 'googlesitekit_analytics_tag_blocked', $ah_spa_da, 99 );
add_filter( 'googlesitekit_tagmanager_tag_blocked', $ah_spa_da, 99 );
unset( $ah_spa_nu, $ah_spa_da );

/* c) Conversions API (Meta) trimis de server în timpul unei cereri venite din aplicație: nu pleacă. */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || ! ah_spa_e_aplicatie() ) {
		return $pre;
	}
	if ( preg_match( '~^https://graph\.facebook\.com/[^?#]*/events\b~i', (string) $url ) ) {
		return new WP_Error( 'ah_spa_fara_urmarire', 'Aplicația Adela Haru: fără evenimente de urmărire.' );
	}
	return $pre;
}, 10, 3 );

/** Adevărat dacă un <script> (atributele + conținutul) e de urmărire sau al barei Complianz. Pură. */
function ah_spa_script_urmarire( $atr, $corp ) {
	$atr  = (string) $atr;
	$corp = (string) $corp;
	// ale noastre și ale Spațiului meu (235, 266) nu se ating niciodată, chiar dacă întreabă de gtag
	if ( preg_match( '~\bid\s*=\s*["\']?(ahsp|ahspa|ahspx)~i', $atr ) || false !== strpos( $corp, 'window.AH_SP' ) ) {
		return false;
	}
	if ( preg_match( '~\b(?:src|data-cmplz-src|data-src)\s*=\s*["\'][^"\']*(?:' . implode( '|', ah_spa_urmarire_tipare() ) . ')~i', $atr ) ) {
		return true;
	}
	if ( preg_match( '~\bid\s*=\s*["\']?(?:cmplz-|complianz|wc-order-attribution|sourcebuster|wc-facebook|facebook-for-woocommerce|google_gtagjs|googlesitekit-consent|gtm4wp|clarity)~i', $atr ) ) {
		return true;
	}
	if ( preg_match( '~\bdata-category\s*=\s*["\']?(?:statistics|marketing)~i', $atr ) ) {
		return true; // scripturile ținute de Complianz până la acord (GA, Clarity)
	}
	// fragmentele scrise direct în pagină: definiția gtag și configurarea, pixelul Meta, Clarity, Complianz pentru Clarity
	return (bool) preg_match( '~function\s+gtag\s*\(|gtag\s*\(\s*[\'"](?:js|config|consent|set)[\'"]|fbq\s*\(\s*[\'"](?:init|track|consent|set|dataProcessingOptions)[\'"]|fbevents\.js|connect\.facebook\.net|googletagmanager\.com|google-analytics\.com|clarity\.ms|cmplzCallClarity|window\.clarity|\bsbjs\.init|wc_order_attribution\s*=~', $corp );
}

/** Scoate un <div id="…">…</div> întreg (cu tot ce e în el); cu $atr, după alt atribut (ex. data-elementor-type). Pură. */
function ah_spa_scoate_div( $html, $id, $atr = 'id' ) {
	$html = (string) $html;
	if ( ! preg_match( '~<div\b[^>]*\s' . preg_quote( $atr, '~' ) . '\s*=\s*["\']' . preg_quote( $id, '~' ) . '["\'][^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$start = $m[0][1];
	$poz   = $start + strlen( $m[0][0] );
	$adanc = 1;
	while ( $adanc > 0 && preg_match( '~<(/?)div\b[^>]*>~i', $html, $t, PREG_OFFSET_CAPTURE, $poz ) ) {
		$adanc += '/' === $t[1][0] ? -1 : 1;
		$poz    = $t[0][1] + strlen( $t[0][0] );
	}
	return $adanc > 0 ? $html : substr( $html, 0, $start ) . substr( $html, $poz );
}

/** b) Pagina fără analiză, urmărire și bara Complianz. Restul HTML-ului rămâne octet cu octet. Pură. */
function ah_spa_fara_urmarire( $html ) {
	$html = (string) $html;
	$out  = preg_replace_callback( '~<script\b([^>]*)>(.*?)</script\s*>~is', function ( $m ) {
		return ah_spa_script_urmarire( $m[1], $m[2] ) ? '' : $m[0];
	}, $html );
	$html = is_string( $out ) ? $out : $html;
	// <noscript> cu imaginea pixelului (facebook.com/tr) sau cadrul GTM
	$out  = preg_replace_callback( '~<noscript\b[^>]*>(.*?)</noscript\s*>~is', function ( $m ) {
		return preg_match( '~facebook\.com/tr|googletagmanager\.com/ns\.html~i', $m[1] ) ? '' : $m[0];
	}, $html );
	$html = is_string( $out ) ? $out : $html;
	// stilurile barei Complianz și legăturile preconnect / dns-prefetch spre serviciile de urmărire
	$tip  = implode( '|', ah_spa_urmarire_tipare() );
	$out  = preg_replace_callback( '~<link\b[^>]*>~i', function ( $m ) use ( $tip ) {
		if ( preg_match( '~\bid\s*=\s*["\']cmplz-(?!document)~i', $m[0] ) || preg_match( '~\bhref\s*=\s*["\'][^"\']*(?:' . $tip . ')~i', $m[0] ) ) {
			return '';
		}
		return $m[0];
	}, $html );
	$html = is_string( $out ) ? $out : $html;
	foreach ( array( 'cmplz-cookiebanner-container', 'cmplz-manage-consent' ) as $id ) {
		$html = ah_spa_scoate_div( $html, $id );
	}
	$out = preg_replace( '~<!--\s*Consent Management powered by Complianz.*?-->\s*~s', '', $html );
	return is_string( $out ) ? $out : $html;
}

/** Adresa unui player Vimeo cu dnt=1 (fără cookie-uri și statistici Vimeo). Altă adresă: neschimbată. Pură. */
function ah_spa_vimeo_dnt( $url ) {
	$url = (string) $url;
	if ( ! preg_match( '~^(?:https?:)?//player\.vimeo\.com/~i', $url ) ) {
		return $url;
	}
	$f = '';
	if ( false !== ( $i = strpos( $url, '#' ) ) ) {
		$f   = substr( $url, $i );
		$url = substr( $url, 0, $i );
	}
	$amp = false !== strpos( $url, '&amp;' ) ? '&amp;' : '&'; // în HTML, adresa poate avea &amp;
	$p   = strpos( $url, '?' );
	$baza = false === $p ? $url : substr( $url, 0, $p );
	$q    = false === $p ? array() : explode( '&', str_replace( '&amp;', '&', substr( $url, $p + 1 ) ) );
	if ( in_array( 'dnt=1', $q, true ) ) {
		return $url . $f;
	}
	$q   = array_values( array_filter( $q, function ( $x ) { return '' !== $x && 0 !== stripos( $x, 'dnt=' ); } ) );
	$q[] = 'dnt=1';
	return $baza . '?' . implode( $amp, $q ) . $f;
}

/** Orice <iframe> Vimeo din pagină primește dnt=1 (și cele ținute de Complianz în data-src-cmplz, care altfel n-ar mai porni). Pură. */
function ah_spa_vimeo_dnt_html( $html ) {
	$out = preg_replace_callback( '~<iframe\b[^>]*>~i', function ( $m ) {
		$tag = $m[0];
		if ( false === stripos( $tag, 'player.vimeo.com' ) ) {
			return $tag;
		}
		if ( preg_match( '~\bdata-src-cmplz\s*=\s*(["\'])([^"\']*)\1~i', $tag, $c ) && false !== stripos( $c[2], 'player.vimeo.com' ) ) {
			$nou = ah_spa_vimeo_dnt( $c[2] );
			$tag = preg_replace( '~\s\bdata-src-cmplz\s*=\s*(["\'])[^"\']*\1~i', '', $tag );
			if ( preg_match( '~\ssrc\s*=\s*(["\'])[^"\']*\1~i', $tag ) ) {
				return preg_replace( '~(\ssrc\s*=\s*)(["\'])[^"\']*\2~i', '$1"' . str_replace( '$', '\$', $nou ) . '"', $tag, 1 );
			}
			return preg_replace( '~^<iframe\b~i', '<iframe src="' . str_replace( '$', '\$', $nou ) . '"', $tag, 1 );
		}
		return preg_replace_callback( '~(\ssrc\s*=\s*)(["\'])([^"\']*)\2~i', function ( $s ) {
			return $s[1] . $s[2] . ah_spa_vimeo_dnt( $s[3] ) . $s[2];
		}, $tag, 1 );
	}, (string) $html );
	return is_string( $out ) ? $out : (string) $html;
}

/** Ieșirea unei pagini din aplicație (callback-ul ob_start): fără urmărire, Vimeo cu dnt=1. */
function ah_spa_iesire_aplicatie( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	foreach ( headers_list() as $h ) {
		if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'html' ) ) {
			return $html; // un fișier, JSON, calendar: neatins
		}
	}
	$html = ah_spa_viewport_cover( ah_spa_vimeo_dnt_html( ah_spa_fara_urmarire( $html ) ) );
	return 'sp' === ah_spa_zona() ? ah_spa_fara_elementor( $html ) : $html;
}

/**
 * Pagina din aplicație desenează până în marginile ecranului (viewport-fit=cover; ios.contentInset = 'never' în
 * capacitor.config.ts): sus și jos le ține 235 cu env(safe-area-inset-*), lateralele stilul punții. Pură.
 */
function ah_spa_viewport_cover( $html ) {
	$html = (string) $html;
	if ( ! preg_match( '~<meta\b[^>]*\bname\s*=\s*["\']viewport["\'][^>]*>~i', $html, $m ) ) {
		$out = preg_replace( '~<head\b[^>]*>~i', '$0<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', $html, 1 );
		return is_string( $out ) ? $out : $html;
	}
	if ( false !== stripos( $m[0], 'viewport-fit' ) ) {
		return $html;
	}
	$nou = preg_replace( '~(\bcontent\s*=\s*)(["\'])([^"\']*)\2~i', '$1$2$3, viewport-fit=cover$2', $m[0], 1 );
	return is_string( $nou ) && $nou !== $m[0] ? str_replace( $m[0], $nou, $html ) : $html;
}

/** Spațiul meu (235 desenează tot ecranul și ascunde antetul, subsolul și popup-urile Elementor): marcajul lor nu mai pleacă. Pură. */
function ah_spa_fara_elementor( $html ) {
	foreach ( array( 'header', 'footer', 'popup' ) as $tip ) {
		for ( $i = 0; $i < 20; $i++ ) {
			$nou = ah_spa_scoate_div( $html, $tip, 'data-elementor-type' );
			if ( $nou === $html ) {
				break;
			}
			$html = $nou;
		}
	}
	return $html;
}

/**
 * Stilurile temei și ale magazinului pe care 235 le ascunde oricum (fără ele, Spațiul meu arată la fel: harnașul vizual
 * compară pozele, server/tests/vizual). Pe toate paginile ei: blocurile WordPress, stilurile globale, WooCommerce. Doar pe
 * Spațiul meu (care nu folosește nimic din temă): și Astra, Elementor (cadrul și kitul 18). Fonturile (elementor-gf-local-*)
 * rămân. Filtru: ah_spa_stiluri_tema.
 */
function ah_spa_stiluri_tema( $zona ) {
	$l = array();
	if ( in_array( $zona, array( 'sp', 'sala', 'carti' ), true ) ) {
		$l = array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'woocommerce-inline', 'wc-blocks-style' );
		if ( 'sp' === $zona ) {
			$l = array_merge( $l, array( 'astra-theme-css', 'elementor-frontend', 'elementor-post-18' ) );
		}
	}
	return array_values( (array) apply_filters( 'ah_spa_stiluri_tema', $l, $zona ) );
}

/** Ce scoatem din coada de stiluri: din lista de mai sus, doar ce nu e cerut (deps) de un stil care rămâne. Pură. */
function ah_spa_stiluri_de_scos( $coada, $inregistrate, $lista ) {
	$scos = array_values( array_intersect( (array) $coada, (array) $lista ) );
	do {
		$schimbat = false;
		foreach ( (array) $coada as $h ) {
			if ( in_array( $h, $scos, true ) ) {
				continue;
			}
			$deps = isset( $inregistrate[ $h ] ) && is_object( $inregistrate[ $h ] ) && ! empty( $inregistrate[ $h ]->deps ) ? (array) $inregistrate[ $h ]->deps : array();
			foreach ( $deps as $dep ) {
				if ( in_array( $dep, $scos, true ) ) {
					$scos     = array_values( array_diff( $scos, array( $dep ) ) );
					$schimbat = true;
				}
			}
		}
	} while ( $schimbat );
	return $scos;
}

/** Scoate (dequeue + deregister, cu stilurile lor „inline”) stilurile temei pe paginile ei din aplicație. */
function ah_spa_scoate_tema() {
	if ( ! ah_spa_e_aplicatie() || is_admin() || ! isset( $GLOBALS['wp_styles'] ) || ! is_object( $GLOBALS['wp_styles'] ) ) {
		return;
	}
	$o = $GLOBALS['wp_styles'];
	foreach ( ah_spa_stiluri_de_scos( (array) $o->queue, (array) $o->registered, ah_spa_stiluri_tema( ah_spa_zona() ) ) as $h ) {
		wp_dequeue_style( $h );
		wp_deregister_style( $h );
	}
}
add_action( 'wp_enqueue_scripts', 'ah_spa_scoate_tema', 10000 );
add_action( 'wp_print_styles', 'ah_spa_scoate_tema', 10000 );

/** b) Un singur ob_start, doar în aplicație, doar pe paginile ei (Spațiul meu, sala, cărți, politici). */
function ah_spa_porneste_filtru_html() {
	if ( ! ah_spa_e_aplicatie() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	if ( '' === ah_spa_zona() ) {
		return false; // sunetul meditațiilor, calendarul sălii, alte adrese: neatinse (și nebufferizate)
	}
	return ob_start( 'ah_spa_iesire_aplicatie' );
}
add_action( 'template_redirect', 'ah_spa_porneste_filtru_html', 9999 );

/* ==================================================================
   11. CODUL DE INTRARE (235), ȘI ÎN BROWSER: CONTOR ATOMIC PE ADRESĂ
   235 numără încercările la /ah-sp/v1/intra cu un transient citit, comparat (bcrypt, lent) și abia apoi scris: cereri
   trimise deodată citesc toate același număr, deci o rafală primește mult mai mult decât 5 încercări pe cod. Fără să
   schimbăm 235: înaintea lui (rest_pre_dispatch, prioritatea 6, după contul de recenzie) un contor atomic pe adresă
   (aceeași normalizare ca ah_sp_email_norm) se mărește ÎNAINTE de verificare. Peste 5 încercări pe codul trimis sau peste
   15 coduri greșite pe zi: 429, cu răspunsul lui 235. Fereastra codului se golește când /cod trimite un cod nou, iar o
   intrare reușită golește fereastra (și nu se socotește greșeală pe zi). Lucrează și în browser: apără intrarea de pe site.
   ================================================================== */

function ah_spa_tabel_contoare() {
	global $wpdb;
	return $wpdb->prefix . 'ah_spa_incercari';
}

/** Cheia unui contor: o amprentă (fără adresa de email în baza de date). */
function ah_spa_contor_cheie( $k ) {
	return sha1( 'ah_spa|' . (string) $k );
}

/** Cheia pe IP: IPv6 pe /64 (un telefon schimbă ușor adresa în rețeaua lui), IPv4 întreg. Pură. */
function ah_spa_ip_cheie( $ip ) {
	$b = @inet_pton( (string) $ip ); // phpcs:ignore
	if ( false === $b ) {
		return (string) $ip;
	}
	return 16 === strlen( $b ) ? bin2hex( substr( $b, 0, 8 ) ) . '/64' : (string) $ip;
}

/**
 * Mărește contorul $k (fereastra $sec secunde de la prima încercare) și întoarce valoarea de după. Atomic: INSERT IGNORE
 * + un singur UPDATE (rândul e blocat cât se scrie), apoi citirea. Două cereri deodată nu pot citi amândouă aceeași
 * valoare înaintea propriei creșteri: cel mult $max cereri văd o valoare ≤ $max. Fără tabel (încă necreat): transient.
 */
function ah_spa_contor( $k, $sec ) {
	global $wpdb;
	$t    = ah_spa_tabel_contoare();
	$c    = ah_spa_contor_cheie( $k );
	$acum = time();
	try {
		$a = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (cheie, n, expira) VALUES (%s, 0, %d)", $c, $acum + (int) $sec ) );
		$b = false === $a ? false : $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET n = CASE WHEN expira < %d THEN 1 ELSE n + 1 END, expira = CASE WHEN expira < %d THEN %d ELSE expira END WHERE cheie = %s", $acum, $acum, $acum + (int) $sec, $c ) );
		$n = false === $b ? null : $wpdb->get_var( $wpdb->prepare( "SELECT n FROM {$t} WHERE cheie = %s", $c ) );
	} catch ( Throwable $e ) {
		$n = null;
	}
	if ( null !== $n && false !== $b ) {
		return (int) $n;
	}
	$tk = 'ah_spa_c_' . substr( $c, 0, 24 ); // rezerva: ca înainte, fără garanția de atomicitate
	$v  = (int) get_transient( $tk ) + 1;
	set_transient( $tk, $v, (int) $sec );
	return $v;
}

/** Golește contorul $k. */
function ah_spa_contor_sterge( $k ) {
	global $wpdb;
	$c = ah_spa_contor_cheie( $k );
	try {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ah_spa_tabel_contoare() . ' WHERE cheie = %s', $c ) );
	} catch ( Throwable $e ) {
		unset( $e );
	}
	delete_transient( 'ah_spa_c_' . substr( $c, 0, 24 ) );
}

/** Curățenia contoarelor: rândurile expirate de peste o zi pleacă, câte cel mult 5000 o dată (din ah_spa_tick). */
function ah_spa_contoare_curata( $acum = null ) {
	global $wpdb;
	$acum = null === $acum ? time() : (int) $acum;
	try {
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ah_spa_tabel_contoare() . ' WHERE expira < %d LIMIT 5000', $acum - DAY_IN_SECONDS ) );
	} catch ( Throwable $e ) {
		return 0;
	}
}

/** Scade contorul $k cu 1 (o încercare care s-a dovedit bună nu e o greșeală). */
function ah_spa_contor_scade( $k ) {
	global $wpdb;
	try {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . ah_spa_tabel_contoare() . ' SET n = n - 1 WHERE cheie = %s AND n > 0', ah_spa_contor_cheie( $k ) ) );
	} catch ( Throwable $e ) {
		unset( $e );
	}
}

/** Adresa din cerere, normalizată ca în 235 (ah_sp_email_norm); '' dacă nu e text. */
function ah_spa_cod_email( $req ) {
	$e = is_object( $req ) && method_exists( $req, 'get_param' ) ? $req->get_param( 'email' ) : '';
	return is_scalar( $e ) ? strtolower( trim( (string) $e ) ) : '';
}

/** Ruta cererii, dacă e POST /ah-sp/v1/cod sau /intra; altfel ''. */
function ah_spa_cod_ruta( $req ) {
	if ( ! is_object( $req ) || ! method_exists( $req, 'get_method' ) || ! method_exists( $req, 'get_route' ) || 'POST' !== strtoupper( (string) $req->get_method() ) ) {
		return '';
	}
	$r = rtrim( (string) $req->get_route(), '/' );
	return '/ah-sp/v1/cod' === $r || '/ah-sp/v1/intra' === $r ? $r : '';
}

/** rest_pre_dispatch: înainte ca 235 să verifice codul, încercarea se socotește. null = 235 continuă. */
function ah_spa_cod_paza( $rez, $server, $req ) {
	if ( null !== $rez || '/ah-sp/v1/intra' !== ah_spa_cod_ruta( $req ) || ! function_exists( 'ah_sp_seteaza_cookie' ) ) {
		return $rez; // un răspuns dat deja (contul de recenzie), altă rută, fără 235: neatins
	}
	$e  = ah_spa_cod_email( $req );
	if ( ! is_email( $e ) ) {
		return $rez; // o adresă care nu e adresă nu are cod (235 răspunde „expirat”): fără rând nou în tabelul contoarelor
	}
	$nc = ah_spa_contor( 'cod|' . $e, HOUR_IN_SECONDS );     // încercări pe codul de acum (se golește la un cod nou)
	$nz = ah_spa_contor( 'zi|' . $e, DAY_IN_SECONDS );       // greșeli pe zi (o intrare reușită scade 1)
	if ( $nc <= AH_SPA_COD_MAX && $nz <= AH_SPA_COD_ZI ) {
		return $rez;
	}
	if ( $nc > AH_SPA_COD_MAX ) {
		delete_transient( 'ah_sp_cod_' . md5( $e ) ); // codul de acum nu mai e bun (ca 235 la a 5-a greșeală): ea cere altul
	}
	ah_spa_jurnal( 'intrare: prea multe încercări pe o adresă (' . ( $nc > AH_SPA_COD_MAX ? 'codul de acum' : 'ziua de azi' ) . '; ' . ah_spa_ip_scurt( ah_spa_ip() ) . ')' );
	return ah_spa_raspuns( array( 'ok' => false, 'mesaj' => 'Prea multe încercări. Cere un cod nou.' ), 429 ); // forma și textul lui 235
}
add_filter( 'rest_pre_dispatch', 'ah_spa_cod_paza', 6, 3 );

/** rest_post_dispatch: un cod nou trimis (/cod 200) golește fereastra; o intrare reușită (/intra 200) la fel. */
function ah_spa_cod_dupa( $rez, $server, $req ) {
	$ruta = ah_spa_cod_ruta( $req );
	if ( '' === $ruta || ! is_object( $rez ) || ! method_exists( $rez, 'get_status' ) || 200 !== (int) $rez->get_status() ) {
		return $rez;
	}
	$d = method_exists( $rez, 'get_data' ) ? $rez->get_data() : null;
	if ( ! is_array( $d ) || true !== ( $d['ok'] ?? null ) ) {
		return $rez;
	}
	$e = ah_spa_cod_email( $req );
	ah_spa_contor_sterge( 'cod|' . $e );
	if ( '/ah-sp/v1/intra' === $ruta ) {
		ah_spa_contor_scade( 'zi|' . $e );
	}
	return $rez;
}
add_filter( 'rest_post_dispatch', 'ah_spa_cod_dupa', 10, 3 );

/* ==================================================================
   12. „MUTĂ CONTUL” (adresă nouă) ȘI SĂNĂTATEA CRONULUI (2.3.0)
   ================================================================== */

/**
 * Mută datele aplicației de pe o adresă pe alta (clienta și-a schimbat emailul): rândul ciclului din 235 (ah_sp_ciclu.cheie,
 * HMAC prin ah_sp_ciclu_cheie; datele criptate nu depind de adresă), emailurile fazelor (41), tokenurile push, telefoanele și
 * ascultările din Inner Temple (51), vizitele (235). Ce există deja pe adresa nouă NU se suprascrie (rămâne pe cea veche și
 * apare în rezultat ca „…_ocupat”). Lasă un alias (amprenta veche → cea nouă), ca puntea să nu ia telefonul ei drept „alt
 * cont” și să nu-i șteargă ciclul de pe telefon, apoi do_action( 'ah_spa_cont_mutat', $vechi, $nou ) (contul pentru el își
 * mută acolo rândurile). Comenzile (accesul) NU se mută aici: adresa de facturare se schimbă în WooCommerce.
 * Întoarce array( 'ok' => bool, 'motiv' => '' | email | aceeasi, 'mutat' => array( ce => n ) ).
 */
function ah_spa_muta_cont( $vechi, $nou ) {
	global $wpdb;
	$v = strtolower( trim( (string) $vechi ) );
	$n = strtolower( trim( (string) $nou ) );
	if ( ! is_email( $v ) || ! is_email( $n ) ) {
		return array( 'ok' => false, 'motiv' => 'email', 'mutat' => array() );
	}
	if ( $v === $n ) {
		return array( 'ok' => false, 'motiv' => 'aceeasi', 'mutat' => array() );
	}
	$m = array();
	// ciclul din cont (235)
	if ( function_exists( 'ah_sp_ciclu_tabel' ) && function_exists( 'ah_sp_ciclu_cheie' ) ) {
		if ( function_exists( 'ah_sp_ciclu_db' ) ) {
			ah_sp_ciclu_db();
		}
		$t  = ah_sp_ciclu_tabel();
		$kv = ah_sp_ciclu_cheie( $v );
		$kn = ah_sp_ciclu_cheie( $n );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE cheie = %s", $kv ) ) ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE cheie = %s", $kn ) ) ) {
				$m['ciclu_ocupat'] = 1;
			} else {
				$m['ciclu'] = (int) $wpdb->update( $t, array( 'cheie' => $kn ), array( 'cheie' => $kv ) );
			}
		}
	}
	// emailurile fazelor (41)
	if ( function_exists( 'ah_ciclu_tabel' ) ) {
		$t = ah_ciclu_tabel();
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE email = %s", $v ) ) ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE email = %s", $n ) ) ) {
				$m['ciclu_emailuri_ocupat'] = 1;
			} else {
				$m['ciclu_emailuri'] = (int) $wpdb->update( $t, array( 'email' => $n ), array( 'email' => $v ) );
			}
		}
	}
	// tokenurile push (aici)
	$np = (int) $wpdb->update( ah_spa_tabel(), array( 'email' => $n ), array( 'email' => $v ) );
	if ( $np ) {
		$m['push'] = $np;
	}
	// telefoanele, minutele și ascultările din Inner Temple (51): după md5 al adresei
	foreach ( array( 'med_dispozitive' => 'ah_med_d_', 'med_minute' => 'ah_med_min_', 'med_ascultari' => 'ah_med_asc_' ) as $k => $pre ) {
		$x = get_option( $pre . md5( $v ), null );
		if ( null === $x ) {
			continue;
		}
		if ( null !== get_option( $pre . md5( $n ), null ) ) {
			$m[ $k . '_ocupat' ] = 1;
			continue;
		}
		update_option( $pre . md5( $n ), $x, false );
		delete_option( $pre . md5( $v ) );
		$m[ $k ] = 1;
	}
	// vizitele (235)
	$vz = get_option( 'ah_sp_vizite', array() );
	if ( is_array( $vz ) && isset( $vz[ md5( $v ) ] ) && ! isset( $vz[ md5( $n ) ] ) ) {
		$vz[ md5( $n ) ] = $vz[ md5( $v ) ];
		unset( $vz[ md5( $v ) ] );
		update_option( 'ah_sp_vizite', $vz, false );
		$m['vizite'] = 1;
	}
	ah_spa_alias_adauga( $v, $n );
	do_action( 'ah_spa_cont_mutat', $v, $n );
	ah_spa_jurnal( 'cont mutat: ' . ah_spa_cine( $v ) . ' → ' . ah_spa_cine( $n ) . ' (' . wp_json_encode( $m ) . ')' );
	return array( 'ok' => true, 'motiv' => '', 'mutat' => $m );
}

/** Aliasul: pe telefonul ei, amprentele adresei vechi (cea nouă și cea din săruri) = același cont cu adresa nouă (un an). */
function ah_spa_alias_adauga( $vechi, $nou, $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$al   = get_option( 'ah_spa_alias', array() );
	$al   = is_array( $al ) ? $al : array();
	$cn   = ah_spa_cine( $nou );
	$l    = isset( $al[ $cn ] ) && is_array( $al[ $cn ] ) ? $al[ $cn ] : array();
	foreach ( array_unique( array_merge( array( ah_spa_cine( $vechi ), ah_spa_cine_salt( $vechi ) ), ah_spa_cine_vechi( $vechi, $acum ) ) ) as $c ) {
		$l   = array_values( array_filter( $l, function ( $x ) use ( $c ) { return is_array( $x ) && ( $x[0] ?? '' ) !== $c; } ) );
		$l[] = array( $c, $acum );
	}
	$al[ $cn ] = array_slice( $l, -10 ); // cel mult 10 amprente vechi pe cont, cele mai noi
	update_option( 'ah_spa_alias', $al, false );
}

add_action( 'admin_post_ah_spa_muta', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nu ai voie aici.' );
	}
	check_admin_referer( 'ah_spa_muta' );
	$p = wp_unslash( $_POST ); // phpcs:ignore
	$r = ah_spa_muta_cont( (string) ( $p['muta_vechi'] ?? '' ), (string) ( $p['muta_nou'] ?? '' ) );
	if ( $r['ok'] ) {
		set_transient( 'ah_spa_muta_rez_' . get_current_user_id(), $r['mutat'], 10 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'ah-spa', 'm' => $r['ok'] ? 'mutat' : 'muta_' . $r['motiv'] ), admin_url( 'options-general.php' ) ) );
	exit;
} );

/** Formularul „Mută contul” (în Setări › Aplicația mobilă; doar administratorii, cu nonce). */
function ah_spa_formular_muta() {
	echo '<h2>Mută contul pe o adresă nouă</h2><p>Când o clientă își schimbă adresa de email: ciclul păstrat în cont, emailurile fazelor, notificările, telefoanele din Inner Temple și invitațiile „Pentru el” trec pe adresa nouă, iar aplicația de pe telefonul ei o recunoaște ca același cont (nu-i șterge nimic de pe telefon). <strong>Accesul (comenzile) nu se mută aici:</strong> schimbă adresa de facturare pe comenzile ei în WooCommerce. Ce există deja pe adresa nouă nu se suprascrie.</p>'
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ah_spa_muta">';
	wp_nonce_field( 'ah_spa_muta' );
	echo '<table class="form-table"><tr><th scope="row"><label for="ah-muta-v">Adresa veche</label></th><td><input type="email" class="regular-text" id="ah-muta-v" name="muta_vechi" required autocomplete="off"></td></tr>'
		. '<tr><th scope="row"><label for="ah-muta-n">Adresa nouă</label></th><td><input type="email" class="regular-text" id="ah-muta-n" name="muta_nou" required autocomplete="off"></td></tr></table>';
	submit_button( 'Mută contul', 'secondary' );
	echo '</form>';
}

/** Momentul rulării unui cron al aplicației: tick (la 5 minute) sau lot (notificările). */
function ah_spa_cron_noteaza( $ce, $acum = null ) {
	update_option( 'ah_spa_cron_' . sanitize_key( $ce ), null === $acum ? time() : (int) $acum, false );
}

/**
 * Cronurile oprite: array( nume => ore de la ultima rulare ) pentru cele care n-au rulat de AH_SPA_CRON_ORE ore: ah_spa_tick
 * (al nostru, la 5 minute; dacă n-a rulat niciodată, de la pornirea fragmentului) și cronul zilnic al contului pentru el
 * (opțiunea ah_spp_ultima_rulare, dacă fragmentul lui e activ și o scrie). ah_spa_lot rulează doar când sunt notificări de
 * trimis, deci lipsa lui nu e un semn de oprire (doar se afișează în setări).
 */
function ah_spa_cron_oprite( $acum = null ) {
	$acum = null === $acum ? time() : (int) $acum;
	$lim  = AH_SPA_CRON_ORE * HOUR_IN_SECONDS;
	$o    = array();
	$tick = (int) get_option( 'ah_spa_cron_tick', 0 );
	$din  = $tick ? $tick : (int) get_option( 'ah_spa_pornit', 0 );
	if ( $din && $acum - $din > $lim ) {
		$o['ah_spa_tick'] = (int) floor( ( $acum - $din ) / HOUR_IN_SECONDS );
	}
	$spp = (int) get_option( 'ah_spp_ultima_rulare', 0 );
	if ( $spp > 0 && function_exists( 'ah_spp_gata' ) && $acum - $spp > $lim ) {
		$o['ah_spp_zilnic'] = (int) floor( ( $acum - $spp ) / HOUR_IN_SECONDS );
	}
	return $o;
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = ah_spa_cron_oprite();
	if ( ! $o ) {
		return;
	}
	$ce = array();
	foreach ( $o as $k => $h ) {
		$ce[] = '<code>' . esc_html( $k ) . '</code> (' . (int) $h . ' h)';
	}
	echo '<div class="notice notice-warning"><p><strong>Aplicația mobilă:</strong> cronul WordPress nu a mai rulat de mult: ' . implode( ', ', $ce ) // phpcs:ignore
		. '. Notificările și curățenia nu pleacă până nu pornește din nou. Verifică DISABLE_WP_CRON și cronul serverului (server/README.md, „Cronul”).</p></div>';
} );

endif;
