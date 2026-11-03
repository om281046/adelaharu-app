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
class WP_REST_Request {
	private $h = array();
	public function __construct( $h = array() ) { $this->h = $h; }
	public function get_header( $k ) { return $this->h[ $k ] ?? null; }
}

/** Un $wpdb în memorie: înțelege doar interogările simple din snippet (WHERE col = %s / %d). */
class Ah_Test_Wpdb {
	public $prefix = 'wp_';
	public $t = array();
	private $id = 0;
	public function prepare( $q, ...$a ) { return array( $q, $a ); }
	private function filtreaza( $p ) {
		list( $q, $a ) = is_array( $p ) ? $p : array( $p, array() );
		if ( ! preg_match( '/FROM\s+(\S+)(?:\s+WHERE\s+(\w+)\s*=\s*%[sd])?/i', $q, $m ) ) { return array(); }
		$rows = $this->t[ $m[1] ] ?? array();
		if ( ! empty( $m[2] ) ) {
			$rows = array_filter( $rows, function ( $r ) use ( $m, $a ) { return (string) ( $r[ $m[2] ] ?? '' ) === (string) $a[0]; } );
		}
		return array_values( $rows );
	}
	public function get_row( $p, $o = null ) { $r = $this->filtreaza( $p ); return $r ? $r[0] : null; }
	public function get_results( $p, $o = null ) { return $this->filtreaza( $p ); }
	public function get_var( $p ) {
		$r = $this->filtreaza( $p );
		if ( is_array( $p ) && preg_match( '/COUNT\(\*\)/i', $p[0] ) ) { return count( $r ); }
		return $r ? reset( $r[0] ) : null;
	}
	public function insert( $t, $d ) { $d['id'] = ++$this->id; $this->t[ $t ][ $d['id'] ] = $d; return 1; }
	private function potriveste( $r, $w ) { foreach ( $w as $k => $v ) { if ( (string) ( $r[ $k ] ?? '' ) !== (string) $v ) { return false; } } return true; }
	public function update( $t, $d, $w ) {
		$n = 0;
		foreach ( $this->t[ $t ] ?? array() as $id => $r ) {
			if ( $this->potriveste( $r, $w ) ) { $this->t[ $t ][ $id ] = array_merge( $r, $d ); $n++; }
		}
		return $n;
	}
	public function delete( $t, $w ) {
		$n = 0;
		foreach ( $this->t[ $t ] ?? array() as $id => $r ) {
			if ( $this->potriveste( $r, $w ) ) { unset( $this->t[ $t ][ $id ] ); $n++; }
		}
		return $n;
	}
}
$GLOBALS['wpdb'] = new Ah_Test_Wpdb();
