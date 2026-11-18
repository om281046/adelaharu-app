<?php
/**
 * Harnașul vizual: construiește pagini statice cu Spațiul meu exact cum îl tipăresc fragmentele 235 (CSS, JS, ascunderea
 * temei) și 266 (extensiile), citite din reference/v2, hrănite cu datele de probă din tests/date/spatiul-meu.json.
 * Varianta „aplicatie” trece prin cârligele reale ale fragmentului ah-sp-aplicatie.php (do_shortcode_tag, wp_head),
 * cu User-Agent-ul aplicației. Plus o sală de curs de probă (butoanele 233, sugestiile 279, rândul HER BREATH, cadrul 235,
 * „Mai departe” de la final (44), „Vreau să continui” (214), calendarul .ics) și o pagină de politici (Termeni și condiții)
 * cu antetul și subsolul temei luate din reference/v2/spatiul-meu-public.html, plus banda Black Friday (178).
 * Rulare: php construieste.php <dosar>   (o face poze.cjs)
 */

$dir = rtrim( $argv[1] ?? '', '/' );
if ( '' === $dir || ! is_dir( $dir ) ) {
	fwrite( STDERR, "Folosire: php construieste.php <dosar>\n" );
	exit( 1 );
}
require __DIR__ . '/../stubs.php';
require __DIR__ . '/../../ah-sp-aplicatie.php';

$ref = __DIR__ . '/../../../reference/v2/';
$s235 = file_get_contents( $ref . 'sn235.php' );
$s266 = file_get_contents( $ref . 'sn266.php' );
$s233 = file_get_contents( $ref . 'sn233.php' );
$s279 = file_get_contents( $ref . 'sn279.php' );
function intre( $s, $a, $b ) {
	$i = strpos( $s, $a );
	if ( false === $i ) { throw new Exception( 'nu găsesc: ' . $a ); }
	$i += strlen( $a );
	$j = strpos( $s, $b, $i );
	if ( false === $j ) { throw new Exception( 'nu găsesc: ' . $b ); }
	return substr( $s, $i, $j - $i );
}
$css235   = intre( $s235, "\$css = <<<'AHSPCSS'\n", "\nAHSPCSS;" );
$js235    = intre( $s235, "\$js = <<<'AHSPJS'\n", "\nAHSPJS;" );
$ascunde  = intre( $s235, "\t\$ascunde = '", "';\n" );
$css266   = intre( $s266, "\$css = <<<'AHSPXCSS'\n", "\nAHSPXCSS;" );
$js266    = intre( $s266, "\$js = <<<'AHSPXJS'\n", "\nAHSPXJS;" );
$cadru    = eval( 'return ' . intre( $s235, "function ah_sp_cadru_css() {\n\treturn ", ' . "\n" . ah_sp_cadru_strans();' ) . ';' );
$css233   = eval( 'return ' . intre( $s233, "\t\$h  = ", ";\n\t\$h .= '<nav id=\"ahsb\"" ) . ';' );
$css279   = '<style>' . intre( $s279, "<style>\n", "</style>" ) . '</style>';

/* Urmărirea de pe site, luată din pagina publică salvată (reference/v2/spatiul-meu-public.html), exact cum o tipăresc
   pluginurile: Google Analytics (prin Complianz), Microsoft Clarity, Complianz (scriptul, stilurile, bara), atribuirea
   comenzilor WooCommerce, facebook-for-woocommerce („signals”), plus pixelul Meta clasic (codul de bază + <noscript>),
   pe care pagina salvată nu îl avea. În browser rămâne tot; în aplicație pagina trece prin filtrul fragmentului. */
$pub_ref = file_get_contents( $ref . 'spatiul-meu-public.html' );
$urm_cap = '';
preg_match_all( '~<script\b([^>]*)>(.*?)</script>~is', $pub_ref, $sc_toate, PREG_SET_ORDER );
foreach ( $sc_toate as $x ) {
	if ( preg_match( '~data-service=|id="(cmplz-|wc-order-attribution|sourcebuster|wc-facebook)~', $x[1] ) || false !== strpos( $x[2], 'window.dataLayer=' ) || false !== strpos( $x[2], 'function cmplzCallClarity' ) ) {
		$urm_cap .= $x[0] . "\n";
	}
}
preg_match_all( "~<link\\b[^>]*id='cmplz-[^']*'[^>]*>~", $pub_ref, $ln_toate );
$urm_cap .= implode( "\n", $ln_toate[0] ) . "\n";
$urm_cap .= "<!-- Meta Pixel Code -->\n<script type='text/javascript'>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');</script>\n<script type='text/javascript'>fbq('init', '000000000000000', {}, {\"agent\": \"woocommerce-9.0-3.7.6\"});fbq('track', 'PageView', []);</script>\n<!-- End Meta Pixel Code -->\n";
$urm_corp = '<noscript><img height="1" width="1" style="display:none" alt="fbpx" src="https://www.facebook.com/tr?id=000000000000000&amp;ev=PageView&amp;noscript=1"></noscript>'
	. '<!-- Consent Management' . intre( $pub_ref, '<!-- Consent Management', '<div data-elementor-type="popup"' );
if ( substr_count( $urm_cap, '<script' ) < 12 || false === strpos( $urm_corp, 'cmplz-cookiebanner-container' ) ) { fwrite( STDERR, "Nu găsesc urmărirea în pagina publică salvată.\n" ); exit( 1 ); }
/* În aplicație, paginile trec prin ob_start-ul fragmentului (ah_spa_porneste_filtru_html → ah_spa_iesire_aplicatie). */
function prin_filtru( $html ) { return ah_spa_iesire_aplicatie( $html ); }

/* datele de probă: imaginile și sunetele sunt servite de harnaș */
$D = json_decode( file_get_contents( __DIR__ . '/../date/spatiul-meu.json' ), true );

/* întâlnirile ei (266 + 265): Respiră-te acasă, cu o înregistrare activă, o întâlnire azi și una mâine */
$t    = time();
$b64  = function ( $u ) { return 'b64:' . base64_encode( $u ); };
$X = array(
	'cont'       => true,
	'nume'       => 'Ana',
	'ganduri'    => array( 'Ia-ți un moment doar pentru tine.' ),
	'evenimente' => array( array(
		'id'       => 'respira-te-acasa-nov-2026',
		'titlu'    => 'Respiră-te acasă',
		'eticheta' => 'Respiră-te acasă',
		'zoom'     => $b64( 'https://us06web.zoom.us/j/81234567890?pwd=abc' ),
		'mod'      => 'zoom',
		'schimba'  => 'https://www.adelaharu.ro/wp-json/ah-respira/v1/mod?e=YW5h&m=youtube&s=abc',
		'zile'     => array(
			array( 'nr' => 1, 'tema' => 'Eliberarea', 'start' => $t - 20 * 3600, 'durata' => 3600, 'fus' => 7200, 'inreg' => array( 'tip' => 'vimeo', 'src' => $b64( 'https://player.vimeo.com/video/1?h=a' ), 'pana' => $t + 5 * 3600, 'sala' => 'https://www.adelaharu.ro/spatiul-meu/?respira_sala=1' ) ),
			array( 'nr' => 2, 'tema' => 'Inima', 'start' => $t + 3 * 3600, 'durata' => 3600, 'fus' => 7200 ),
			array( 'nr' => 3, 'tema' => 'Pântecul și energia vitală', 'start' => $t + 27 * 3600, 'durata' => 3600, 'fus' => 7200 ),
		),
	) ),
	'acum'       => $t,
	'api'        => 'https://www.adelaharu.ro/wp-json/ah-spx/v1/',
	'termeni'    => 'https://www.adelaharu.ro/termeni-si-conditii/',
	'gdpr'       => 'https://www.adelaharu.ro/politica-de-confidentialitate/',
);
$cap266 = '<style id="ahspx-css">' . $css266 . '</style>' . "\n" . '<script id="ahspx-js">window.AH_SPX=' . wp_json_encode( $X, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';' . $js266 . '</script>' . "\n";

/* ieșirea shortcode-ului [ah_spatiul_meu], ca în 235 */
$sc = '<style id="ahsp-css">' . $ascunde . $css235 . '</style><div id="ahsp"></div><script>window.AH_SP=' . wp_json_encode( $D ) . ';</script><script>' . $js235 . '</script>';

function pagina( $titlu, $cap, $clase, $corp ) {
	return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#FBF6F3"><title>' . $titlu . '</title>'
		. '<link rel="stylesheet" href="/fonts/fonts.css">' . "\n" . $cap . '</head><body class="' . $clase . '">' . $corp . '</body></html>';
}
$GLOBALS['ah_test_email']    = 'ana@exemplu.ro';
$GLOBALS['ah_test_e_pagina'] = true;
$_SERVER['REQUEST_URI']      = '/spatiul-meu/';

/* browserul: fără nimic de la fragmentul nou (cârligele lui nu fac nimic) */
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
unset( $_COOKIE['ah_app'] );
$cap_b = ah_test_actiune( 'wp_head' );
$sc_b  = ah_test_filtru( 'do_shortcode_tag', $sc, 'ah_spatiul_meu' );
if ( '' !== $cap_b || $sc_b !== $sc ) { fwrite( STDERR, "Fragmentul a schimbat pagina în browser!\n" ); exit( 1 ); }
$clase_b = implode( ' ', ah_test_filtru( 'body_class', array( 'page', 'ahsp-pagina' ) ) );
file_put_contents( $dir . '/sp-browser.html', pagina( 'Spațiul meu', $urm_cap . $cap266, $clase_b, '<div class="site-content"><article><div class="entry-content">' . $sc_b . '</div></article></div>' . $urm_corp ) );

/* aplicația */
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/1.0.0 (ios)';
update_option( 'ah_spa_setari', array( 'min_ios' => '', 'link_ios' => 'https://apps.apple.com/ro/app/adela-haru/id0000000000' ) );
$cap_a   = ah_test_actiune( 'wp_head' );
$sc_a    = ah_test_filtru( 'do_shortcode_tag', $sc, 'ah_spatiul_meu' );
$clase_a = implode( ' ', ah_test_filtru( 'body_class', array( 'page', 'ahsp-pagina' ) ) );
file_put_contents( $dir . '/sp-aplicatie.html', prin_filtru( pagina( 'Spațiul meu', $urm_cap . $cap266 . $cap_a, $clase_a, '<div class="site-content"><article><div class="entry-content">' . $sc_a . '</div></article></div>' . $urm_corp ) ) );

/* sala de curs de probă (lecție din „Respiră-te acasă”, cu ce adaugă 233, 279, 224 și cadrul 235) */
$ic = function ( $d ) { return '<svg class="ahspc-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>'; };
$P  = array( 'acasa' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', 'ciclu' => 'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z', 'cursuri' => 'M4 5h11a3 3 0 0 1 3 3v12H7a3 3 0 0 1-3-3zM18 8h2v12M8 9h6M8 13h6', 'comenzi' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6', 'mai' => 'M5 12h.01M12 12h.01M19 12h.01' );
$u  = 'https://www.adelaharu.ro/spatiul-meu/';
$nav = '<nav id="ahspc-lat" aria-label="Spațiul meu"><a class="marca" href="' . $u . '#acasa">Spațiul meu<small>Adela Haru</small></a><a href="' . $u . '#cursuri" aria-current="page">' . $ic( $P['cursuri'] ) . '<span class="t">Cursurile mele</span></a><div class="sep"></div><div class="mic"><a href="https://www.adelaharu.ro/recomanda/">' . $ic( $P['mai'] ) . '<span class="t">Dă mai departe</span></a><a href="https://www.adelaharu.ro/card-cadou/">' . $ic( $P['mai'] ) . '<span class="t">Card cadou</span></a><a href="' . $u . '#mai">' . $ic( $P['mai'] ) . '<span class="t">Ajutor și datele mele</span></a></div><div class="jos mic"><a href="https://www.adelaharu.ro/">' . $ic( $P['acasa'] ) . '<span class="t">Înapoi pe site</span></a></div></nav>'
	. '<nav id="ahspc-bara" aria-label="Spațiul meu"><a href="' . $u . '#acasa"><span class="pt">' . $ic( $P['acasa'] ) . '</span>Acasă</a><a href="' . $u . '#ciclu"><span class="pt">' . $ic( $P['ciclu'] ) . '</span>Ciclul</a><a href="' . $u . '#cursuri" aria-current="page"><span class="pt">' . $ic( $P['cursuri'] ) . '</span>Cursuri</a><a href="' . $u . '#comenzi"><span class="pt">' . $ic( $P['comenzi'] ) . '</span>Comenzi</a><a href="' . $u . '#mai"><span class="pt">' . $ic( $P['mai'] ) . '</span>Mai mult</a></nav>';
$sala_css = '<style>body{margin:0;font:16px/1.6 Figtree,system-ui,sans-serif;color:#47372F}.ahs{max-width:760px;margin:0 auto;padding:16px}.ahs-cap{padding:8px 0}.ahs-cap h1{font:400 28px/1.2 "Gilda Display",Georgia,serif;margin:4px 0}.ahs-mic{font-size:14px;color:#7A6254}.ahs-cutie{background:rgba(255,255,255,.8);border:1px solid #EADCE3;border-radius:18px;padding:18px;margin:14px 0}.ahs-buton{display:inline-flex;align-items:center;min-height:48px;padding:0 20px;border-radius:999px;background:#8A4430;color:#fff!important;text-decoration:none}.ahs-video{aspect-ratio:16/9;border-radius:14px;background:linear-gradient(150deg,#7A5A75,#3E3040);display:grid;place-items:center;color:#fff}audio{width:100%;margin-top:10px}</style>';
$sala = '<div class="ahs" id="ahs"><header class="ahs-cap"><p class="ahs-mic" style="letter-spacing:.14em;text-transform:uppercase">Respiră-te acasă</p><h1>Ziua 1 · Eliberarea</h1><p class="ahs-mic">Abonament plătit până pe 18 decembrie 2026 · <a href="https://www.adelaharu.ro/wp-json/ah-hb/v1/portal?o=14900&amp;k=wc_order_x" style="color:inherit;text-decoration:underline">Abonamentul meu</a></p></header>'
	. '<nav id="ahsb" aria-label="Mai mult de la Adela"><a class="bf" href="https://www.adelaharu.ro/black-friday/">Black Friday <b>−40%</b></a><a href="https://www.adelaharu.ro/cartile-mele/?comanda=15003&amp;cheie=wc_order_y">Cărțile mele</a><a href="https://www.adelaharu.ro/card-cadou/">Card cadou</a><a href="https://www.adelaharu.ro/recomanda/">Women Empower Women</a></nav>'
	. '<div class="ahs-cutie"><div class="ahs-video"><iframe id="ahs-vimeo" src="https://player.vimeo.com/video/1197728140?h=cff7aa9195&amp;title=0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="Înregistrarea (Vimeo)" style="width:100%;aspect-ratio:16/9;border:0"></iframe></div><audio controls src="https://www.adelaharu.ro/audio/lectie.wav"></audio><p class="ahs-mic" id="ahs-nota">Înregistrarea rămâne aici 24 de ore.</p></div>'
	. '<section id="rsg" aria-label="Mai departe"><p class="rsg-h">Dacă vrei să mergi mai departe</p><p class="rsg-s">Câteva drumuri pe care le poți continua cu Adela, oricând simți.</p><div class="rsg-l"><a class="rsg-c" href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/?utm_source=x" target="_blank"><img src="/img/curs-4.svg" alt="" width="64" height="64"><span class="rsg-t"><span class="rsg-k">Program online</span><span class="rsg-n">HER BREATH, HER RESET</span><span class="rsg-d">9 zile de respirație ghidată.</span><span class="rsg-v">Vezi detalii →</span></span></a><a class="rsg-c" href="https://www.adelaharu.ro/product/meditatie-pentru-manifestarea-intentiilor/?utm_source=x" target="_blank"><img src="/img/med-5.svg" alt="" width="64" height="64"><span class="rsg-t"><span class="rsg-k">Meditație</span><span class="rsg-n">Meditație pentru manifestarea intențiilor</span><span class="rsg-d">O meditație scurtă despre intenție.</span><span class="rsg-v">Vezi detalii →</span></span></a><a class="rsg-c" href="https://www.adelaharu.ro/spatiul-meu/#ciclu"><img src="/img/dar.svg" alt="" width="64" height="64"><span class="rsg-t"><span class="rsg-k">Gratuit, în Spațiul meu</span><span class="rsg-n">Ciclul meu</span><span class="rsg-d">Calendarul ciclului tău.</span><span class="rsg-v">Deschide calendarul →</span></span></a></div></section>'
	. '<div class="ahs-cutie ahhb-lacat"><p class="ahs-mic">HER BREATH, HER RESET</p><h2 style="font:400 22px Georgia,serif;margin:0">Ana, practicile te așteaptă</h2><p>Accesul tău s-a încheiat pe 2 noiembrie 2026. Progresul și jurnalul tău sunt păstrate aici.</p><p><a class="ahs-buton" href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/?revin=14900&amp;k=wc_order_x#abonare">Revino · 49 lei / lună</a></p><p class="ahs-mic">Ai parcurs 4 din 9 practici. Abonamentul îl oprești oricând.</p></div>'
	. '<div class="ahs-cutie"><h2 style="font:400 22px Georgia,serif;margin:0">Accesul s-a încheiat</h2><p>Abonamentul tău la HER BREATH s-a încheiat. Dacă simți să revii, te poți abona din nou de pe <a href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/#abonare">pagina programului</a>.</p></div>'
 	. '<div class="ahs-cutie" id="ritm"><h2 style="font:400 22px Georgia,serif;margin:0">Ritmul tău</h2><p>În fiecare marți, la 08:00. Îți trimitem un memento pe email.</p><div class="ahs-unelte"><a class="ahs-buton usor" href="https://www.adelaharu.ro/?ah_sc_ics=14770&amp;s=0123456789abcdef">Adaugă în calendarul meu</a></div></div>'
	. '<section class="ahs-pas-final" id="ahs-urmator"><h3>Mai departe</h3><a class="ahs-urm" href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/"><span class="ahs-urm-t"><b>HER BREATH, HER RESET</b><span>Nouă practici de respirație, în fiecare lună.</span><em>49 lei / lună</em><span class="ahs-urm-b">Vezi programul →</span></span></a></section>'
	. '<section id="ah-dar7-curs" aria-label="Mai departe"><div class="k"><p class="q">Ce anume este diferit acum?</p><p>Dacă ți-a făcut bine, te invit să aduci respirația într-un ritual zilnic. Am adunat nouă practici care aduc contribuție în viața ta.</p><a class="cont" href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/?abonare=1">Vreau să continui practica lunară →</a><details><summary>Vezi cele nouă practici</summary><ol><li>Respirația inimii</li></ol><p class="pret">HER BREATH · 49 lei / lună · îl oprești oricând · <a href="https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/" style="color:inherit">despre program</a></p></details></div></section>'
	. '<p class="ahs-mic ahs-alte">Tot în comanda ta: <a href="https://www.adelaharu.ro/sala-de-curs/?comanda=15002&amp;cheie=wc_order_z&amp;curs=wild-love">Wild Love</a></p></div>';
$_SERVER['REQUEST_URI']      = '/sala-de-curs/?comanda=14770&cheie=wc_order_r';
$GLOBALS['ah_test_e_pagina'] = false;
$_SERVER['HTTP_USER_AGENT']  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
$cap_sb = ah_test_actiune( 'wp_head' );
file_put_contents( $dir . '/sala-browser.html', pagina( 'Sala de curs', $cadru . $css233 . $css279 . $sala_css . $urm_cap . $cap_sb, 'ahspc-on', $sala . $nav . $urm_corp ) );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/1.0.0 (ios)';
$cap_sa = ah_test_actiune( 'wp_head' );
file_put_contents( $dir . '/sala-aplicatie.html', prin_filtru( pagina( 'Sala de curs', $cadru . $css233 . $css279 . $sala_css . $urm_cap . $cap_sa, 'ahspc-on ahspa-app', $sala . $nav . $urm_corp ) ) );

/* Termeni și condiții: antetul și subsolul temei exact ca pe site (din pagina publică salvată), fără scripturi, plus banda Black Friday */
$pub    = file_get_contents( $ref . 'spatiul-meu-public.html' );
$fara_s = function ( $h ) { return preg_replace( '~<script\b.*?</script>~is', '', $h ); };
$antet  = $fara_s( '<div id="ahbq-banda"' . intre( $pub, '<div id="ahbq-banda"', '<div id="content" class="site-content">' ) );
$subsol = $fara_s( '<div data-elementor-type="footer"' . intre( $pub, '<div data-elementor-type="footer"', '<!-- Consent Management' ) );
$bf     = '<div class="ah-bf-banda" id="ah-bf-banda" role="region" aria-label="Black Friday"><b>Black Friday</b><span class="ah-bf-lung">−40% la cursuri, cărți și meditații · </span><span class="ah-bf-timp">până vineri, 23:59</span> · <a href="https://www.adelaharu.ro/black-friday/">Vezi ofertele</a><button type="button" class="ah-bf-x" aria-label="Închide">×</button></div>';
$termeni = '<div id="content" class="site-content"><div class="ast-container"><div id="primary" class="content-area primary"><main id="main" class="site-main"><article class="page type-page status-publish"><header class="entry-header"><h1 class="entry-title">Termeni și condiții</h1></header><div class="entry-content clear">'
	. '<h2>1. Cine suntem</h2><p>Site-ul www.adelaharu.ro este administrat de Adela Haru. Ne poți scrie oricând la <a href="mailto:contact@adelaharu.ro">contact@adelaharu.ro</a>.</p>'
	. '<h2>2. Conturile și datele tale</h2><p>Datele personale le prelucrăm după <a href="https://www.adelaharu.ro/politica-de-confidentialitate/">Politica de confidențialitate</a>. Cookie-urile sunt descrise în <a href="https://www.adelaharu.ro/politica-de-cookie-uri/">Politica de cookie-uri</a>.</p>'
	. '<h2>3. Programele online</h2><p>Programele sunt descrise pe pagina fiecăruia (de exemplu, <a href="https://www.adelaharu.ro/product/magia-ciclului-feminin/">Magia Ciclului Feminin</a>). Accesul începe după confirmarea comenzii, iar abonamentele lunare se pot opri oricând.</p>'
	. '<h2>4. Dreptul de retragere</h2><p>Pentru conținutul digital, dreptul de retragere se pierde odată cu începerea accesului, cu acordul tău expres.</p>'
	. '</div></article></main></div></div></div>';
foreach ( array( 'browser' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'aplicatie' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/1.0.0 (ios)' ) as $var => $ua ) {
	$_SERVER['HTTP_USER_AGENT'] = $ua;
	unset( $_COOKIE['ah_app'] );
	$_SERVER['REQUEST_URI']      = '/termeni-si-conditii/';
	$GLOBALS['ah_test_e_pagina'] = false;
	$GLOBALS['ah_test_email']    = '';
	$cap_t   = ah_test_actiune( 'wp_head' );
	$clase_t = implode( ' ', ah_test_filtru( 'body_class', array( 'page-template-default', 'page', 'theme-astra', 'ast-plain-container', 'elementor-default' ) ) );
	if ( 'browser' === $var && '' !== $cap_t ) { fwrite( STDERR, "Fragmentul a schimbat politicile în browser!\n" ); exit( 1 ); }
	$pag_t = pagina( 'Termeni și condiții', $urm_cap . $cap_t, $clase_t, $bf . $antet . $termeni . $subsol . $urm_corp );
	file_put_contents( $dir . '/termeni-' . $var . '.html', 'aplicatie' === $var ? prin_filtru( $pag_t ) : $pag_t );
}
/* puntea și stilul ei, ca le servește /wp-json/ah-spa/v1/punte (în pagină doar configurația și partea „devreme”) */
file_put_contents( $dir . '/punte.js', ah_spa_js() );
file_put_contents( $dir . '/punte.css', ah_spa_css_tot() );
/* Spațiul meu în aplicație CU stilurile temei de acum (server/tests/date/tema/site.css: Astra, WooCommerce, Elementor, blocuri), ca
   harnașul să compare: fără ele (cum le scoate ah_spa_scoate_tema) pagina trebuie să arate la fel. */
$sp_a = file_get_contents( $dir . '/sp-aplicatie.html' );
file_put_contents( $dir . '/sp-aplicatie-tema.html', preg_replace( '~<link rel="stylesheet" href="/fonts/fonts.css">~', '<link rel="stylesheet" href="/tema/site.css">$0', $sp_a, 1 ) );
echo "gata\n";
