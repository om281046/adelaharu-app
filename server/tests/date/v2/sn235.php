<?php
/* GENERAT de server/tests/extrage-referinte.php din reference/v2/sn235.php (bucăți copiate neschimbate, doar pentru teste).
   Nu se editează de mână: rulează din nou scriptul dacă fragmentul de pe site se schimbă. Nu se urcă pe site. */
if ( ! defined( 'AH_TEST_REFERINTE' ) ) { return; }

function ah_sp_email_norm( $e ) { return strtolower( trim( (string) $e ) ); }

function ah_sp_ciclu_tabel() { global $wpdb; return $wpdb->prefix . 'ah_sp_ciclu'; }

function ah_sp_ciclu_db() {
	if ( '1' === get_option( 'ah_sp_ciclu_db' ) ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = ah_sp_ciclu_tabel();
	dbDelta( "CREATE TABLE {$t} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n cheie char(64) NOT NULL,\n date longtext NOT NULL,\n acord datetime NOT NULL,\n actualizat datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY cheie (cheie)\n) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'ah_sp_ciclu_db', '1', false );
}

function ah_sp_ciclu_secret() {
	$k = get_option( 'ah_sp_ciclu_secret' );
	if ( ! $k ) { $k = bin2hex( random_bytes( 32 ) ); add_option( 'ah_sp_ciclu_secret', $k, '', 'no' ); $k = get_option( 'ah_sp_ciclu_secret' ); }
	return (string) $k;
}

function ah_sp_ciclu_cheie( $e ) { return hash_hmac( 'sha256', ah_sp_email_norm( $e ), ah_sp_ciclu_secret() . '|cine' ); }

function ah_sp_ciclu_cripteaza( $s ) {
	$iv = random_bytes( 12 ); $tag = '';
	$c  = openssl_encrypt( $s, 'aes-256-gcm', hash( 'sha256', ah_sp_ciclu_secret() . '|date', true ), OPENSSL_RAW_DATA, $iv, $tag );
	return base64_encode( $iv . $tag . $c );
}

function ah_sp_ciclu_decripteaza( $b ) {
	$r = base64_decode( (string) $b, true );
	if ( ! $r || strlen( $r ) < 29 ) { return null; }
	$p = openssl_decrypt( substr( $r, 28 ), 'aes-256-gcm', hash( 'sha256', ah_sp_ciclu_secret() . '|date', true ), OPENSSL_RAW_DATA, substr( $r, 0, 12 ), substr( $r, 12, 16 ) );
	return false === $p ? null : $p;
}

function ah_sp_ciclu_rand( $e ) {
	global $wpdb; ah_sp_ciclu_db();
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ah_sp_ciclu_tabel() . ' WHERE cheie = %s', ah_sp_ciclu_cheie( $e ) ), ARRAY_A );
}

function ah_sp_partener_texte() {
	$harta = function_exists( 'ah_ciclu_faze' ) ? ah_ciclu_faze() : array();
	$map   = array( 'iarna' => 'menstruala', 'primavara' => 'foliculara', 'vara' => 'ovulatorie', 'toamna' => 'premenstruala' );
	$cit   = array(
		'iarna'     => array( 'Surse de căldură exterioară ar fi soarele, focul, apa caldă, băile, ceaiurile, supele, alimentele la cuptor sau chiar îmbrățișarea iubitului.', 'Și orice pauză de la sex, măcar de trei zile într-o lună poate fi chiar benefică pentru cuplu.' ),
		'primavara' => array( 'În această fază energia noastră este orientată mai mult înspre exterior, accesăm mai ușor energia masculină și suntem mai focalizate pe obiective ca niciodată.', '… în relația de cuplu, e de ținut cont că ne-ar fi de folos să cultivăm răbdarea, întrucât, pe fondul entuziasmului sau dorinței de acțiune, putem fi mai predispuse să grăbim sau să forțăm lucrurile.' ),
		'vara'      => array( 'Ne sprijină să devenim mai receptive la ceea ce ne pasionează, la focul nostru interior.', 'Pe scurt, putem să ne folosim de acest moment pentru a da naștere la orice alt proiect împreună.' ),
		'toamna'    => array( '… e perfect în regulă să simți nevoia de spațiu, de curățenie și mai ales de mai mult timp cu tine.', 'Când te-ai odihnit suficient, nevoia de creativitate și de blândețe față de sine apare implicit și natural.' ),
	);
	// CIORNĂ: de citit de Adela.
	$sprijin = array(
		'iarna'     => array( 'Retragerea ei nu e despre tine: e timpul ei de odihnă și de curățare.', 'Adu căldură: un ceai, o supă, o pătură, o îmbrățișare.', 'Preia din treburile casei fără să fie nevoie să-ți ceară.', 'Lasă-o pe ea să aleagă cât de aproape vrea să fiți în zilele astea.' ),
		'primavara' => array( 'Energia ei se întoarce: susține-i planurile și entuziasmul.', 'Propune ceva nou împreună: un loc, o activitate, o idee.', 'Dacă e prinsă în proiectele ei, nu e o depărtare de tine.', 'Răbdarea ta o ajută să nu grăbească lucrurile.' ),
		'vara'      => array( 'E faza în care se deschide cel mai ușor spre lume și spre tine.', 'Faceți timp pentru voi: o ieșire, o conversație lungă, un proiect comun.', 'Laudă-i darurile: acum le arată cel mai ușor.', 'E faza ei fertilă. Dacă nu vă doriți o sarcină, vorbiți deschis despre asta.' ),
		'toamna'    => array( 'Fă-i loc: timp doar pentru ea, fără vinovăție.', 'Ascult-o fără să-i dai sfaturi pe care nu ți le cere.', 'Dacă vede mai ușor ce nu merge, nu lua asta personal.', 'Ajut-o să încheie ce a rămas deschis și să se odihnească: odihna ei e cel mai bun dar.' ),
	);
	$o = array();
	foreach ( $map as $k => $h ) {
		$f = $harta[ $h ] ?? array();
		$o[ $k ] = array(
			'arhetip' => (string) ( $f['arhetip'] ?? '' ),
			'luna'    => (string) ( $f['luna'] ?? '' ),
			'ce'      => (string) ( $f['simti'] ?? '' ),
			'citate'  => $cit[ $k ],
			'sprijin' => $sprijin[ $k ],
		);
	}
	return $o;
}

function ah_sp_podcast() {
	return array(
		'titlu'   => 'Intim Feminin, cu Adela Haru',
		'text'    => 'Nouă conversații despre feminitate, corp, ciclul feminin, sexualitate și relații, între 22 și 33 de minute fiecare.',
		'spotify' => 'https://open.spotify.com/show/36dsYnxW0VgGyrN9bDFKo2',
		'apple'   => 'https://podcasts.apple.com/ro/podcast/intim-feminin-cu-adela-haru/id1486663684',
		'episoade' => json_decode( <<<'AHSPPOD'
[{"nr": 1, "titlu": "Cum ne putem trezi senzualitatea", "meta": "6 noiembrie 2019 · 22 minute", "desc": "O perspectivă diferită despre senzualitate și pasiune, în cuplu și în viața de zi cu zi.", "embed": "https://embed.podcasts.apple.com/ro/podcast/1-cum-ne-putem-trezi-senzualitatea/id1486663684?i=1000456094034&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/1-cum-ne-putem-trezi-senzualitatea/id1486663684?i=1000456094034", "mai": ["Wild Love", "https://www.adelaharu.ro/curs-de-sexualitate-constienta-pentru-femei-wild-love/"]}, {"nr": 2, "titlu": "Menstruația conștientă", "meta": "15 noiembrie 2019 · 31 minute", "desc": "Despre rolul menstruației în viața unei femei, despre cum putem avea parte de o menstruație sănătoasă și despre cum ne cunoaștem mai bine prin ciclul nostru.", "embed": "https://embed.podcasts.apple.com/ro/podcast/2-menstruatia-constienta/id1486663684?i=1000456894117&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/2-menstruatia-constienta/id1486663684?i=1000456894117", "mai": ["Tainele Menstruației Conștiente", "https://www.adelaharu.ro/tainele-menstruatiei-constiente/"]}, {"nr": 3, "titlu": "Fertilitatea conștientă", "meta": "29 noiembrie 2019 · 26 minute", "desc": "Ce înseamnă fertilitatea în sensul mai amplu al cuvântului și ce putem face ca femei, fie că ne dorim o sarcină, fie că vrem să o evităm.", "embed": "https://embed.podcasts.apple.com/ro/podcast/3-fertilitatea-constienta/id1486663684?i=1000458204143&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/3-fertilitatea-constienta/id1486663684?i=1000458204143", "mai": ["Magia Ciclului Feminin", "https://www.adelaharu.ro/product/magia-ciclului-feminin/"]}, {"nr": 4, "titlu": "Despre nevoia de a fi iubit(ă) într-o relație", "meta": "9 decembrie 2019 · 27 minute", "desc": "Despre nevoia de a fi iubit(ă), despre iubirea de sine și despre importanța de a ne onora reciproc nevoile și aspirațiile.", "embed": "https://embed.podcasts.apple.com/ro/podcast/4-despre-nevoia-de-a-fi-iubit-a-intr-o-relatie/id1486663684?i=1000459098289&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/4-despre-nevoia-de-a-fi-iubit-a-intr-o-relatie/id1486663684?i=1000459098289", "mai": ["Relațiile conștiente", "https://www.adelaharu.ro/product/relatiile-constiente/"]}, {"nr": 5, "titlu": "Despre limitele personale în relațiile apropiate", "meta": "18 decembrie 2019 · 26 minute", "desc": "Ce facem atunci când ceilalți nu ne respectă alegerile sau sentimentele, în numele iubirii? Cum ne stabilim și cum ne respectăm propriile limite.", "embed": "https://embed.podcasts.apple.com/ro/podcast/5-despre-limitele-personale-in-relatiile-apropiate/id1486663684?i=1000459957528&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/5-despre-limitele-personale-in-relatiile-apropiate/id1486663684?i=1000459957528", "mai": ["Relațiile conștiente", "https://www.adelaharu.ro/product/relatiile-constiente/"]}, {"nr": 6, "titlu": "Energia sexuală în fazele ciclului feminin", "meta": "17 ianuarie 2020 · 33 minute", "desc": "Energia sexuală prin prisma celor patru faze ale ciclului feminin: cum ne înțelegem mai bine ciclul, de ce dorința vine și pleacă și cum ajungem la o viață sexuală echilibrată.", "embed": "https://embed.podcasts.apple.com/ro/podcast/6-energia-sexuala-in-fazele-ciclului-feminin/id1486663684?i=1000462910366&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/6-energia-sexuala-in-fazele-ciclului-feminin/id1486663684?i=1000462910366", "mai": ["Wild Love", "https://www.adelaharu.ro/curs-de-sexualitate-constienta-pentru-femei-wild-love/"]}, {"nr": 7, "titlu": "Cum putem accesa energia feminină prin dans", "meta": "10 februarie 2020 · 28 minute", "desc": "Ce putem descoperi despre noi prin dans și cum ajungem la stări de bine în timp ce dansăm. Vorbim și despre belly dance și despre practici de reechilibrare.", "embed": "https://embed.podcasts.apple.com/ro/podcast/7-cum-putem-accesa-energia-feminina-prin-dans/id1486663684?i=1000465163839&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/7-cum-putem-accesa-energia-feminina-prin-dans/id1486663684?i=1000465163839", "mai": ["Practici de feminitate", "https://www.adelaharu.ro/curs-de-initiere-in-practici-de-feminitate/"]}, {"nr": 8, "titlu": "Impactul actului sexual la nivel energetic", "meta": "17 februarie 2020 · 33 minute", "desc": "Despre sexualitatea conștientă și despre impactul pe termen lung pe care îl poate avea întâlnirea sexuală la nivel emoțional și energetic.", "embed": "https://embed.podcasts.apple.com/ro/podcast/8-impactul-actului-sexual-la-nivel-energetic/id1486663684?i=1000465804900&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/8-impactul-actului-sexual-la-nivel-energetic/id1486663684?i=1000465804900", "mai": ["Masterclass Energia Sexuală", "https://www.adelaharu.ro/product/masterclass-energia-sexuala-si-darurile-ei/"]}, {"nr": 9, "titlu": "Practici de conectare cu corpul în perioada de criză", "meta": "18 martie 2020 · 30 minute", "desc": "Avem nevoie să rămânem cât mai conectate la corp, ca să ne păstrăm centrarea, luciditatea și flexibilitatea. Practici de bază pentru perioadele grele.", "embed": "https://embed.podcasts.apple.com/ro/podcast/9-practici-de-conectare-cu-corpul-in-perioada-de-criza/id1486663684?i=1000468830777&theme=light", "apple": "https://podcasts.apple.com/ro/podcast/9-practici-de-conectare-cu-corpul-in-perioada-de-criza/id1486663684?i=1000468830777", "mai": ["Practici de respirație conștientă", "https://www.adelaharu.ro/practici-de-respiratie-constienta-pentru-femei-ocupate/"]}]
AHSPPOD
		, true ),
	);
}

/* Obiectul CI (JavaScript), linie cu linie, pentru server/tests/sn235-ciclu.cjs */
$ah_ref_ci = <<<'AHREFCI'
 d:function(){var x=ls('ahsp_ciclu'),v=ls('ah_ciclu_v1')||{},st=[];if(x&&x.starturi)st=st.concat(x.starturi);if(v.starturi)st=st.concat(v.starturi);if(v.s)st.push(v.s);st=st.filter(function(q,i){return /^\d{4}-\d{2}-\d{2}$/.test(q)&&st.indexOf(q)===i;}).sort().reverse().slice(0,13);if(!st.length)return null;var cl=function(n,a,b,d){n=+n;return n>=a&&n<=b?n:d;};return {starturi:st,lung:cl(x&&x.lung||v.c,21,40,28),men:cl(x&&x.men||v.m,2,9,5),sit:x&&x.sit};},
 zi0:function(s){var p=s.split('-');return Date.UTC(+p[0],p[1]-1,+p[2])/864e5;},
 azi0:function(){var n=new Date();return Date.UTC(n.getFullYear(),n.getMonth(),n.getDate())/864e5;},
 iso:function(z){return new Date(z*864e5).toISOString().slice(0,10);},
 lung:function(x){var st=x.starturi.map(CI.zi0),dif=[];for(var i=0;i+1<st.length&&dif.length<6;i++){var v=st[i]-st[i+1];if(v>=21&&v<=40)dif.push(v);}return dif.length>=2?Math.round(dif.reduce(function(a,b){return a+b;},0)/dif.length):(x.lung||28);},
 faze:function(L,m){var oS=Math.max(L-16,m+2),oE=Math.min(oS+4,L-2);return [{f:'iarna',a:1,b:m},{f:'primavara',a:m+1,b:oS-1},{f:'vara',a:oS,b:oE},{f:'toamna',a:oE+1,b:L}];},
 azi:function(){var x=CI.d();if(!x)return null;var L=CI.lung(x),m=x.men||5,s0=CI.zi0(x.starturi[0]),zi=CI.azi0()-s0+1;if(zi<1)return null;var fz=CI.faze(L,m),f='toamna';fz.forEach(function(p){if(zi>=p.a&&zi<=p.b)f=p.f;});return {zi:zi,L:L,m:m,f:f,s0:s0,fz:fz,urm:s0+L,peste:zi>L};}
AHREFCI;
