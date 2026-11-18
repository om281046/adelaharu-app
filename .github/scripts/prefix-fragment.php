<?php
/**
 * CI: toate funcțiile cu nume din fragmentul dat au prefixul lui (implicit ah_spa_), fără ciocniri cu 235, 51, 44, 231 etc.
 * Folosește tokenizerul PHP, deci ignoră funcțiile JavaScript din șirurile fragmentului.
 * Folosire: php .github/scripts/prefix-fragment.php server/ah-sp-aplicatie.php [ah_spa_]
 */
$pref = isset( $argv[2] ) ? $argv[2] : 'ah_spa_';
$t = token_get_all( file_get_contents( $argv[1] ) ); $rau = array();
for ( $i = 0, $n = count( $t ); $i < $n; $i++ ) {
  if ( is_array( $t[ $i ] ) && T_FUNCTION === $t[ $i ][0] ) {
    for ( $j = $i + 1; $j < $n && is_array( $t[ $j ] ) && T_WHITESPACE === $t[ $j ][0]; $j++ ) {}
    if ( $j < $n && is_array( $t[ $j ] ) && T_STRING === $t[ $j ][0] && 0 !== strpos( $t[ $j ][1], $pref ) ) { $rau[] = $t[ $j ][1] . ' (rândul ' . $t[ $j ][2] . ')'; }
  }
}
if ( $rau ) { echo '::error::' . $argv[1] . ': funcții fără prefixul ' . $pref . ': ' . implode( ', ', $rau ) . "\n"; exit( 1 ); }
echo $argv[1] . ': toate funcțiile au prefixul ' . $pref . ".\n";
