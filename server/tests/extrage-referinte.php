<?php
/**
 * Copiile de referință pentru testele din CI (review 18 nov.). reference/ e în .gitignore (copiile întregi ale
 * fragmentelor de pe site), deci în CI testele serverului nu le au. Scriptul copiază, NESCHIMBATE, doar bucățile de care
 * au nevoie testele, în server/tests/date/v2/ (în repo):
 *   - sn235.php: funcțiile ah_sp_* folosite de stubs-ul partenerului și liniile obiectului CI (pentru sn235-ciclu.cjs);
 *   - sn41.php: ah_ciclu_faze (textele fazelor);
 *   - spatiul-meu-public.html: pagina publică salvată (GA, Complianz, pixelul Meta), pentru testul „fără urmărire”.
 * Testele citesc reference/v2/ când există (local) și server/tests/date/v2/ altfel (CI).
 *
 * Folosire, din rădăcina proiectului:
 *   php server/tests/extrage-referinte.php             # scrie copiile
 *   php server/tests/extrage-referinte.php --verifica  # doar compară (iese cu 1 dacă reference/v2 s-a schimbat)
 * Fără reference/v2: nu face nimic (iese cu 0), ca să poată rula oriunde.
 */

$rad  = dirname( __DIR__, 2 );
$src  = $rad . '/reference/v2/';
$dest = __DIR__ . '/date/v2/';
$verifica = in_array( '--verifica', $argv, true );

if ( ! is_dir( $src ) ) {
	echo "Fără reference/v2: nimic de extras (testele folosesc server/tests/date/v2/).\n";
	exit( 0 );
}

/** O funcție PHP de la începutul rândului până la „}” de la începutul rândului (aceeași regulă ca stubs-ul partenerului). */
function ah_ref_functie( $s, $nume, $fisier ) {
	$q = preg_quote( $nume, '/' );
	if ( preg_match( '/^function ' . $q . '\s*\([^\n]*\}[ \t]*$/m', $s, $m ) ) {
		return $m[0];
	}
	if ( ! preg_match( '/^function ' . $q . '\s*\(.*?^\}/ms', $s, $m ) ) {
		fwrite( STDERR, "lipsește {$nume} din {$fisier}\n" );
		exit( 1 );
	}
	return $m[0];
}

$antet = "<?php\n/* GENERAT de server/tests/extrage-referinte.php din reference/v2/%s (bucăți copiate neschimbate, doar pentru teste).\n   Nu se editează de mână: rulează din nou scriptul dacă fragmentul de pe site se schimbă. Nu se urcă pe site. */\n";

/* 235: funcțiile ciclului și ale partenerului + liniile CI */
$s235 = file_get_contents( $src . 'sn235.php' );
$out  = sprintf( $antet, 'sn235.php' ) . "if ( ! defined( 'AH_TEST_REFERINTE' ) ) { return; }\n\n";
foreach ( array( 'ah_sp_email_norm', 'ah_sp_ciclu_tabel', 'ah_sp_ciclu_db', 'ah_sp_ciclu_secret', 'ah_sp_ciclu_cheie', 'ah_sp_ciclu_cripteaza', 'ah_sp_ciclu_decripteaza', 'ah_sp_ciclu_rand', 'ah_sp_partener_texte', 'ah_sp_podcast' ) as $f ) {
	$out .= ah_ref_functie( $s235, $f, 'sn235.php' ) . "\n\n";
}
$linii = array();
foreach ( array( ' d:function(){', ' zi0:function(s)', ' azi0:function()', ' iso:function(z)', ' lung:function(x)', ' faze:function(L,m)', ' azi:function()' ) as $inc ) {
	$gasit = null;
	foreach ( explode( "\n", $s235 ) as $l ) {
		if ( 0 === strpos( $l, $inc ) ) {
			$gasit = $l;
			break;
		}
	}
	if ( null === $gasit ) {
		fwrite( STDERR, "lipsește în sn235.php: {$inc}\n" );
		exit( 1 );
	}
	$linii[] = $gasit;
}
$out .= "/* Obiectul CI (JavaScript), linie cu linie, pentru server/tests/sn235-ciclu.cjs */\n\$ah_ref_ci = <<<'AHREFCI'\n" . implode( "\n", $linii ) . "\nAHREFCI;\n";

/* 41: textele fazelor */
$s41  = file_get_contents( $src . 'sn41.php' );
$out41 = sprintf( $antet, 'sn41.php' ) . "if ( ! defined( 'AH_TEST_REFERINTE' ) ) { return; }\n\n" . ah_ref_functie( $s41, 'ah_ciclu_faze', 'sn41.php' ) . "\n";

$fisiere = array(
	'sn235.php'               => $out,
	'sn41.php'                => $out41,
	'spatiul-meu-public.html' => file_get_contents( $src . 'spatiul-meu-public.html' ),
);
$diferit = array();
foreach ( $fisiere as $n => $c ) {
	$t = $dest . $n;
	if ( ! is_file( $t ) || file_get_contents( $t ) !== $c ) {
		$diferit[] = $n;
		if ( ! $verifica ) {
			if ( ! is_dir( $dest ) ) {
				mkdir( $dest, 0755, true );
			}
			file_put_contents( $t, $c );
		}
	}
}
if ( $verifica && $diferit ) {
	fwrite( STDERR, 'reference/v2 s-a schimbat față de server/tests/date/v2 (' . implode( ', ', $diferit ) . "): rulează php server/tests/extrage-referinte.php.\n" );
	exit( 1 );
}
echo $diferit ? ( $verifica ? '' : 'Scris: ' . implode( ', ', $diferit ) . "\n" ) : "Copiile de referință sunt la zi.\n";
exit( 0 );
