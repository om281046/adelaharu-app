<?php
/**
 * Înlocuitori minimi pentru funcțiile WordPress folosite de ah-app-api.php, ca logica pură
 * (tokenuri, coduri, semnături, limite, ciclul) să poată fi testată fără WordPress.
 * Nu reproduc WordPress întreg: doar cât trebuie testelor.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );
define( 'ARRAY_A', 'ARRAY_A' );
date_default_timezone_set( 'Europe/Bucharest' );

$GLOBALS['ah_test_hooks']      = array();
$GLOBALS['ah_test_options']    = array();
$GLOBALS['ah_test_transients'] = array();
$GLOBALS['ah_test_mail']       = array();
$GLOBALS['ah_test_timp']       = 0; // decalaj față de time(), pentru expirări

function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['ah_test_hooks'][] = array( $h, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['ah_test_hooks'][] = array( $h, $p ); return true; }
function do_action( $h, ...$a ) {}
function apply_filters( $h, $v, ...$a ) { return $v; }

function wp_salt( $s = 'auth' ) { return 'sare-de-test-' . $s; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['ah_test_options'] ) ? $GLOBALS['ah_test_options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['ah_test_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['ah_test_options'][ $k ] ); return true; }

function get_transient( $k ) {
	$t = $GLOBALS['ah_test_transients'][ $k ] ?? null;
	if ( ! $t || $t[1] < time() + $GLOBALS['ah_test_timp'] ) { return false; }
	return $t[0];
}
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['ah_test_transients'][ $k ] = array( $v, time() + $GLOBALS['ah_test_timp'] + ( $e ? $e : 1e9 ) ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['ah_test_transients'][ $k ] ); return true; }

function sanitize_email( $e ) { return preg_replace( '/[^a-zA-Z0-9@._+\-]/', '', (string) $e ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f | JSON_UNESCAPED_UNICODE ); }
function home_url( $p = '/' ) { return 'https://www.adelaharu.ro' . $p; }
function rest_url( $p = '' ) { return 'https://www.adelaharu.ro/wp-json/' . $p; }
function wp_date( $f, $t = null ) { return date( $f, null === $t ? time() : $t ); }
function current_time( $t ) { return date( 'Y-m-d H:i:s' ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Bucharest' ); }
function wp_generate_password( $n = 12, $s = true, $x = false ) { return substr( bin2hex( random_bytes( $n ) ), 0, $n ); }
function remove_accents( $s ) { return strtr( (string) $s, array( 'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't', 'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T' ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function wp_mail( $to, $s, $b, $h = array() ) { $GLOBALS['ah_test_mail'][] = array( $to, $s, $b ); return true; }
function get_user_by( $f, $v ) { return false; }
/* Cron: evenimentele programate se țin într-o listă, ca testele să le poată rula de mână. */
$GLOBALS['ah_test_cron'] = array();
function wp_schedule_single_event( $t, $h, $a = array() ) { $GLOBALS['ah_test_cron'][] = array( $t, $h, $a ); return true; }
function wp_schedule_event( $t, $r, $h, $a = array() ) { $GLOBALS['ah_test_cron'][] = array( $t, $h, $a, $r ); return true; }
function wp_next_scheduled( $h, $a = array() ) { foreach ( $GLOBALS['ah_test_cron'] as $e ) { if ( $e[1] === $h && $e[2] === $a ) { return $e[0]; } } return false; }
function wp_clear_scheduled_hook( $h, $a = array() ) {
	$GLOBALS['ah_test_cron'] = array_values( array_filter( $GLOBALS['ah_test_cron'], function ( $e ) use ( $h, $a ) { return ! ( $e[1] === $h && $e[2] === $a ); } ) );
	return 1;
}
/** Scoate din listă următorul eveniment cu cârligul dat (cum face wp-cron înainte să-l ruleze). */
function ah_test_cron_scoate( $h ) {
	foreach ( $GLOBALS['ah_test_cron'] as $i => $e ) {
		if ( $e[1] === $h ) { unset( $GLOBALS['ah_test_cron'][ $i ] ); $GLOBALS['ah_test_cron'] = array_values( $GLOBALS['ah_test_cron'] ); return $e; }
	}
	return null;
}

/* Articole: doar câmpurile citite de declanșatorul push. */
$GLOBALS['ah_test_posts'] = array();
$GLOBALS['ah_test_meta']  = array();
function get_post_status( $id ) { return $GLOBALS['ah_test_posts'][ $id ]['status'] ?? false; }
function get_the_title( $id ) { return $GLOBALS['ah_test_posts'][ $id ]['titlu'] ?? ''; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['ah_test_meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['ah_test_meta'][ $id ][ $k ] = $v; return true; }

function add_query_arg( $args, $url ) {
	$q = http_build_query( $args );
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $q;
}

class WP_HTTP_Response {
	public $data; public $status; public $headers = array();
	public function __construct( $d = null, $s = 200, $h = array() ) { $this->data = $d; $this->status = $s; $this->headers = $h; }
	public function header( $k, $v ) { $this->headers[ $k ] = $v; }
	public function get_headers() { return $this->headers; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}
class WP_REST_Response extends WP_HTTP_Response {}
class WP_REST_Request implements ArrayAccess {
	private $h = array();
	private $j = null;
	private $u = array();
	public function __construct( $h = array(), $json = null, $url = array() ) { $this->h = $h; $this->j = $json; $this->u = $url; }
	public function get_header( $k ) { return $this->h[ $k ] ?? null; }
	public function get_json_params() { return $this->j; }
	public function get_body_params() { return array(); }
	public function get_route() { return '/ah-app/v1/test'; }
	public function offsetExists( $o ): bool { return isset( $this->u[ $o ] ); }
	public function offsetGet( $o ): mixed { return $this->u[ $o ] ?? null; }
	public function offsetSet( $o, $v ): void { $this->u[ $o ] = $v; }
	public function offsetUnset( $o ): void { unset( $this->u[ $o ] ); }
}

/**
 * Un $wpdb în memorie: înțelege doar interogările simple din snippet (WHERE col = %s / %d,
 * opțional „AND id > %d” și „LIMIT %d”).
 * Tabelul codurilor (ah_app_coduri) merge într-un SQLite adevărat, ca interogarea atomică a
 * încercărilor (UPDATE … CASE … WHERE n < 5) să fie rulată ca SQL, nu imitată.
 */
class Ah_Test_Wpdb {
	public $prefix  = 'wp_';
	public $options = 'wp_options';
	public $t = array();
	private $id = 0;
	public $sql;
	public function __construct() {
		$this->sql = new PDO( 'sqlite::memory:' );
		$this->sql->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->sql->exec( "CREATE TABLE wp_ah_app_coduri ( email varchar(190) NOT NULL PRIMARY KEY, hash char(64) NOT NULL DEFAULT '', n integer NOT NULL DEFAULT 0, exp integer NOT NULL DEFAULT 0, zi_n integer NOT NULL DEFAULT 0, zi_start integer NOT NULL DEFAULT 0 )" );
	}
	private function e_sql( $x ) { return false !== strpos( is_array( $x ) ? $x[0] : (string) $x, 'ah_app_coduri' ); }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function prepare( $q, ...$a ) {
		if ( $this->e_sql( $q ) ) {
			$i = 0;
			$sql = $this->sql;
			return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $a, $sql ) { $v = $a[ $i++ ]; return '%d' === $m[0] ? (string) (int) $v : $sql->quote( (string) $v ); }, $q );
		}
		return array( $q, $a );
	}
	private function filtreaza( $p ) {
		list( $q, $a ) = is_array( $p ) ? $p : array( $p, array() );
		if ( ! preg_match( '/FROM\s+(\S+)(?:\s+WHERE\s+(\w+)\s*=\s*%[sd])?/i', $q, $m ) ) { return array(); }
		$rows = $this->t[ $m[1] ] ?? array();
		if ( ! empty( $m[2] ) ) {
			$rows = array_filter( $rows, function ( $r ) use ( $m, $a ) { return (string) ( $r[ $m[2] ] ?? '' ) === (string) $a[0]; } );
		}
		if ( preg_match( '/AND id > %d/', $q ) ) {
			$rows = array_filter( $rows, function ( $r ) use ( $a ) { return (int) $r['id'] > (int) $a[1]; } );
		}
		$rows = array_values( $rows );
		if ( preg_match( '/LIMIT %d\s*$/', $q ) ) {
			$rows = array_slice( $rows, 0, (int) end( $a ) );
		}
		return $rows;
	}
	public function get_row( $p, $o = null ) {
		if ( $this->e_sql( $p ) ) { $r = $this->sql->query( $p )->fetch( PDO::FETCH_ASSOC ); return $r ? $r : null; }
		$r = $this->filtreaza( $p ); return $r ? $r[0] : null;
	}
	public function get_results( $p, $o = null ) { return $this->filtreaza( $p ); }
	public function get_col( $p ) {
		list( $q, $a ) = $p;
		if ( preg_match( '/FROM wp_options WHERE option_name LIKE %s/', $q ) ) {
			$re = ''; $esc = false;
			foreach ( str_split( (string) $a[0] ) as $c ) {
				if ( $esc ) { $re .= preg_quote( $c, '/' ); $esc = false; }
				elseif ( '\\' === $c ) { $esc = true; }
				elseif ( '%' === $c ) { $re .= '.*'; }
				elseif ( '_' === $c ) { $re .= '.'; }
				else { $re .= preg_quote( $c, '/' ); }
			}
			$re = '/^' . $re . '$/';
			return array_values( array_filter( array_keys( $GLOBALS['ah_test_options'] ), function ( $k ) use ( $re ) { return preg_match( $re, $k ); } ) );
		}
		return array_column( $this->filtreaza( $p ), 0 );
	}
	public function get_var( $p ) {
		$r = $this->filtreaza( $p );
		if ( is_array( $p ) && preg_match( '/COUNT\(\*\)/i', $p[0] ) ) { return count( $r ); }
		return $r ? reset( $r[0] ) : null;
	}
	public function query( $p ) {
		if ( $this->e_sql( $p ) ) { return $this->sql->exec( $p ); }
		return 0;
	}
	private function sql_where( $w ) {
		return implode( ' AND ', array_map( function ( $k, $v ) { return $k . ' = ' . $this->sql->quote( (string) $v ); }, array_keys( $w ), $w ) );
	}
	public function insert( $t, $d ) {
		if ( $this->e_sql( $t ) ) {
			try {
				return $this->sql->exec( 'INSERT INTO ' . $t . ' (' . implode( ',', array_keys( $d ) ) . ') VALUES (' . implode( ',', array_map( array( $this->sql, 'quote' ), array_map( 'strval', $d ) ) ) . ')' );
			} catch ( PDOException $e ) { return false; }
		}
		$d['id'] = ++$this->id; $this->t[ $t ][ $d['id'] ] = $d; return 1;
	}
	private function potriveste( $r, $w ) { foreach ( $w as $k => $v ) { if ( (string) ( $r[ $k ] ?? '' ) !== (string) $v ) { return false; } } return true; }
	public function update( $t, $d, $w ) {
		if ( $this->e_sql( $t ) ) {
			return $this->sql->exec( 'UPDATE ' . $t . ' SET ' . implode( ', ', array_map( function ( $k, $v ) { return $k . ' = ' . $this->sql->quote( (string) $v ); }, array_keys( $d ), $d ) ) . ' WHERE ' . $this->sql_where( $w ) );
		}
		$n = 0;
		foreach ( $this->t[ $t ] ?? array() as $id => $r ) {
			if ( $this->potriveste( $r, $w ) ) { $this->t[ $t ][ $id ] = array_merge( $r, $d ); $n++; }
		}
		return $n;
	}
	public function delete( $t, $w ) {
		if ( $this->e_sql( $t ) ) { return $this->sql->exec( 'DELETE FROM ' . $t . ' WHERE ' . $this->sql_where( $w ) ); }
		$n = 0;
		foreach ( $this->t[ $t ] ?? array() as $id => $r ) {
			if ( $this->potriveste( $r, $w ) ) { unset( $this->t[ $t ][ $id ] ); $n++; }
		}
		return $n;
	}
}
$GLOBALS['wpdb'] = new Ah_Test_Wpdb();
