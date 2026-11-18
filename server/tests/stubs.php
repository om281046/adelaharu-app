<?php
/**
 * Înlocuitori minimi pentru WordPress, ca fragmentul ah-sp-aplicatie.php să poată fi încărcat și testat fără site.
 * Cârligele se țin minte (ca testele să le poată rula), $wpdb e un SQLite în memorie (interogările sunt SQL adevărat).
 * Nu reproduc WordPress întreg: doar cât trebuie testelor și harnașului vizual.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'COOKIE_DOMAIN', false );
date_default_timezone_set( 'Europe/Bucharest' );

$GLOBALS['ah_test_hooks']      = array();
$GLOBALS['ah_test_options']    = array();
$GLOBALS['ah_test_transients'] = array();
$GLOBALS['ah_test_mail']       = array();
$GLOBALS['ah_test_cron']       = array();
$GLOBALS['ah_test_posts']      = array();
$GLOBALS['ah_test_meta']       = array();
$GLOBALS['ah_test_jurnal_do']  = array();
$GLOBALS['ah_test_email']      = '';   // ce întoarce ah_sp_email() (clienta intrată în Spațiul meu)

/* ---------- cârlige ---------- */
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['ah_test_hooks'][ $h ][] = array( $cb, $p, $n ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function do_action( $h, ...$a ) { $GLOBALS['ah_test_jurnal_do'][] = $h; }
function apply_filters( $h, $v, ...$a ) { return $v; }
function did_action( $h ) { return 0; }
/** Rulează callback-urile fragmentului legate de un cârlig (în ordinea priorității) ca filtru. */
function ah_test_filtru( $h, $v, ...$a ) {
	$l = $GLOBALS['ah_test_hooks'][ $h ] ?? array();
	usort( $l, function ( $x, $y ) { return $x[1] <=> $y[1]; } );
	foreach ( $l as $c ) { $v = call_user_func_array( $c[0], array_slice( array_merge( array( $v ), $a ), 0, max( 1, $c[2] ) ) ); }
	return $v;
}
/** Rulează callback-urile unei acțiuni și întoarce ce au tipărit. */
function ah_test_actiune( $h, ...$a ) {
	$l = $GLOBALS['ah_test_hooks'][ $h ] ?? array();
	usort( $l, function ( $x, $y ) { return $x[1] <=> $y[1]; } );
	ob_start();
	foreach ( $l as $c ) { call_user_func_array( $c[0], array_slice( $a, 0, $c[2] ) ); }
	return ob_get_clean();
}

/* ---------- opțiuni, tranzitorii ---------- */
function wp_salt( $s = 'auth' ) { return 'sare-de-test-' . $s; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['ah_test_options'] ) ? $GLOBALS['ah_test_options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['ah_test_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $d = '', $a = null ) { if ( ! array_key_exists( $k, $GLOBALS['ah_test_options'] ) ) { $GLOBALS['ah_test_options'][ $k ] = $v; } return true; }
function delete_option( $k ) { unset( $GLOBALS['ah_test_options'][ $k ] ); return true; }
function get_transient( $k ) { $t = $GLOBALS['ah_test_transients'][ $k ] ?? null; return ( ! $t || $t[1] < time() ) ? false : $t[0]; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['ah_test_transients'][ $k ] = array( $v, time() + ( $e ? $e : 1e9 ) ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['ah_test_transients'][ $k ] ); return true; }

/* ---------- texte, adrese ---------- */
function sanitize_email( $e ) { return preg_replace( '/[^a-zA-Z0-9@._+\-]/', '', (string) $e ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_url_raw( $s ) { $s = trim( (string) $s ); return preg_match( '~^https?://~i', $s ) ? $s : ''; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function home_url( $p = '/' ) { return 'https://www.adelaharu.ro' . $p; }
function content_url( $p = '' ) { return 'https://www.adelaharu.ro/wp-content' . $p; }
function rest_url( $p = '' ) { return 'https://www.adelaharu.ro/wp-json/' . ltrim( $p, '/' ); }
function admin_url( $p = '' ) { return 'https://www.adelaharu.ro/wp-admin/' . $p; }
function add_query_arg( $args, $url, $url3 = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $url ); $url = $url3; } // forma add_query_arg( cheie, valoare, url )
	$h = ''; if ( false !== ( $i = strpos( $url, '#' ) ) ) { $h = substr( $url, $i ); $url = substr( $url, 0, $i ); }
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ) . $h;
}
function wp_date( $f, $t = null ) { return date( $f, null === $t ? time() : $t ); }
function current_time( $t ) { return date( 'Y-m-d H:i:s' ); }
function wp_rand( $a = 0, $b = 0 ) { return random_int( 0, 1000000 ); }
function is_ssl() { return true; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function nocache_headers() {}
function status_header( $c ) { $GLOBALS['ah_test_status'] = $c; }
function wp_check_filetype( $f ) { return array( 'ext' => 'mp3', 'type' => 'audio/mpeg' ); }
function wp_mail( $to, $s, $b, $h = array() ) { $GLOBALS['ah_test_mail'][] = array( $to, $s, $b ); return true; }
function current_user_can( $c ) { return false; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { public $code; public $message; public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; } public function get_error_message() { return $this->message; } }
/* parolele (codul de recenzie): ca în WordPress, doar amprenta; verificarea în timp constant */
function wp_hash_password( $p ) { return password_hash( (string) $p, PASSWORD_BCRYPT, array( 'cost' => 4 ) ); }
function wp_check_password( $p, $h, $id = '' ) { $GLOBALS['ah_test_check_pw'] = ( $GLOBALS['ah_test_check_pw'] ?? 0 ) + 1; return '' !== (string) $h && password_verify( (string) $p, (string) $h ); }
/* coada de scripturi și stiluri (doar ce folosește scoaterea urmăririi) */
class Ah_Test_Deps { public $queue = array(); public $registered = array(); public $scoase = array(); public $sterse = array();
	public function adauga( $h, $src, $deps = array() ) { $this->queue[] = $h; $this->registered[ $h ] = (object) array( 'src' => $src, 'deps' => $deps ); } }
function wp_dequeue_script( $h ) { $o = $GLOBALS['wp_scripts']; $o->queue = array_values( array_diff( $o->queue, array( $h ) ) ); $o->scoase[] = $h; }
function wp_deregister_style( $h ) { $o = $GLOBALS['wp_styles']; unset( $o->registered[ $h ] ); $o->sterse[] = $h; }
function wp_dequeue_style( $h ) { $o = $GLOBALS['wp_styles']; $o->queue = array_values( array_diff( $o->queue, array( $h ) ) ); $o->scoase[] = $h; }

/* Redirecționarea: o excepție, ca testul să vadă unde ar fi plecat. */
class Ah_Test_Redirect extends Exception { public $loc; public function __construct( $l ) { $this->loc = $l; parent::__construct( $l ); } }
function wp_safe_redirect( $l, $s = 302, $x = '' ) { throw new Ah_Test_Redirect( $l ); }
function wp_redirect( $l, $s = 302 ) { throw new Ah_Test_Redirect( $l ); }

/* ---------- cron ---------- */
function wp_schedule_single_event( $t, $h, $a = array() ) { $GLOBALS['ah_test_cron'][] = array( $t, $h, $a ); return true; }
function wp_schedule_event( $t, $r, $h, $a = array() ) { $GLOBALS['ah_test_cron'][] = array( $t, $h, $a, $r ); return true; }
function wp_next_scheduled( $h, $a = array() ) { foreach ( $GLOBALS['ah_test_cron'] as $e ) { if ( $e[1] === $h && $e[2] === $a ) { return $e[0]; } } return false; }

/* ---------- articole ---------- */
function get_post( $id ) { $p = $GLOBALS['ah_test_posts'][ (int) $id ] ?? null; return $p ? (object) $p : null; }
function get_post_status( $id ) { return $GLOBALS['ah_test_posts'][ (int) $id ]['post_status'] ?? false; }
function get_the_title( $id ) { return $GLOBALS['ah_test_posts'][ (int) $id ]['post_title'] ?? ''; }
function get_the_post_thumbnail_url( $id, $m = '' ) { return $GLOBALS['ah_test_posts'][ (int) $id ]['poza'] ?? false; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['ah_test_meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['ah_test_meta'][ $id ][ $k ] = $v; return true; }

/* ---------- REST ---------- */
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
	private $h; private $j; private $p; private $route; private $metoda;
	public function __construct( $h = array(), $json = null, $param = array(), $route = '/', $metoda = 'GET' ) {
		$this->h = array(); foreach ( $h as $k => $v ) { $this->h[ strtolower( str_replace( '-', '_', $k ) ) ] = $v; }
		$this->j = $json; $this->p = $param; $this->route = $route; $this->metoda = $metoda;
	}
	public function get_method() { return $this->metoda; }
	public function get_header( $k ) { return $this->h[ strtolower( str_replace( '-', '_', $k ) ) ] ?? null; }
	public function get_json_params() { return $this->j; }
	public function get_param( $k ) { return $this->p[ $k ] ?? ( is_array( $this->j ) ? ( $this->j[ $k ] ?? null ) : null ); }
	public function get_route() { return $this->route; }
}

/* ---------- WooCommerce: comenzile (doar ce folosește ștergerea contului) ---------- */
class Ah_Test_Comanda {
	public $id; public $email; public $meta; public $note = array(); public $salvari = 0;
	public function __construct( $id, $email, $meta = array() ) { $this->id = $id; $this->email = $email; $this->meta = $meta; }
	public function get_id() { return $this->id; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function get_billing_email() { return $this->email; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function delete_meta_data( $k ) { unset( $this->meta[ $k ] ); }
	public function add_order_note( $n ) { $this->note[] = $n; }
	public function save() { $this->salvari++; }
}
$GLOBALS['ah_test_wc'] = array();
function wc_get_orders( $a ) {
	$e = strtolower( (string) ( $a['billing_email'] ?? '' ) );
	return array_values( array_filter( $GLOBALS['ah_test_wc'], function ( $c ) use ( $e ) { return '' !== $e && strtolower( $c->email ) === $e; } ) );
}

/* ---------- HTTP (FCM): răspunsurile le alege testul ---------- */
$GLOBALS['ah_test_http'] = array();
function wp_remote_post( $url, $a = array() ) {
	$GLOBALS['ah_test_http'][] = array( $url, $a );
	$f = $GLOBALS['ah_test_http_raspuns'] ?? null;
	return $f ? $f( $url, $a ) : array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }

/* ---------- $wpdb: SQLite în memorie ---------- */
class Ah_Test_Wpdb {
	public $prefix = 'wp_';
	public $pdo;
	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'CREATE TABLE wp_ah_spa_push ( id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, token TEXT NOT NULL, token_h TEXT NOT NULL UNIQUE, platforma TEXT DEFAULT \'\', versiune TEXT DEFAULT \'\', disp TEXT DEFAULT \'\', creat TEXT, vazut TEXT )' );
		$this->pdo->exec( 'CREATE TABLE wp_ah_sp_ciclu ( id INTEGER PRIMARY KEY AUTOINCREMENT, cheie TEXT UNIQUE, date TEXT, acord TEXT, actualizat TEXT )' );
		$this->pdo->exec( 'CREATE TABLE wp_ah_ciclu ( id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, token TEXT, start TEXT )' );
		$this->pdo->exec( 'CREATE TABLE wp_ah_spa_incercari ( cheie TEXT PRIMARY KEY, n INTEGER NOT NULL DEFAULT 0, expira INTEGER NOT NULL DEFAULT 0 )' );
	}
	public function get_charset_collate() { return ''; }
	public function prepare( $q, ...$a ) {
		$i = 0; $pdo = $this->pdo;
		return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $a, $pdo ) { $v = $a[ $i++ ]; return '%d' === $m[0] ? (string) (int) $v : $pdo->quote( (string) $v ); }, $q );
	}
	public function get_row( $q, $o = null ) { $r = $this->pdo->query( $q )->fetch( PDO::FETCH_ASSOC ); return $r ? $r : null; }
	public function get_results( $q, $o = null ) { return $this->pdo->query( $q )->fetchAll( PDO::FETCH_ASSOC ); }
	public function get_col_test( $q ) { return array_map( 'strval', $this->pdo->query( $q )->fetchAll( PDO::FETCH_COLUMN ) ); }
	public function get_var( $q ) { $r = $this->pdo->query( $q )->fetch( PDO::FETCH_NUM ); return $r ? $r[0] : null; }
	private function where( $w ) { return implode( ' AND ', array_map( function ( $k, $v ) { return $k . ' = ' . $this->pdo->quote( (string) $v ); }, array_keys( $w ), $w ) ); }
	public function insert( $t, $d ) { return $this->pdo->exec( 'INSERT INTO ' . $t . ' (' . implode( ',', array_keys( $d ) ) . ') VALUES (' . implode( ',', array_map( array( $this->pdo, 'quote' ), array_map( 'strval', $d ) ) ) . ')' ); }
	public function update( $t, $d, $w ) { return $this->pdo->exec( 'UPDATE ' . $t . ' SET ' . implode( ', ', array_map( function ( $k, $v ) { return $k . ' = ' . $this->pdo->quote( (string) $v ); }, array_keys( $d ), $d ) ) . ' WHERE ' . $this->where( $w ) ); }
	public function delete( $t, $w ) { return $this->pdo->exec( 'DELETE FROM ' . $t . ' WHERE ' . $this->where( $w ) ); }
	/* MySQL → SQLite: doar „INSERT IGNORE” (contoarele atomice); restul e SQL comun amândurora */
	public function query( $q ) { $GLOBALS['ah_test_sql'][] = $q; return $this->pdo->exec( preg_replace( '/^INSERT IGNORE\b/i', 'INSERT OR IGNORE', $q ) ); }
}
$GLOBALS['wpdb'] = new Ah_Test_Wpdb();

/* ---------- fragmentele site-ului de care se leagă modulul (doar ce folosește) ---------- */
function ah_sp_email() { return $GLOBALS['ah_test_email']; }
function ah_sp_e_pagina() { return ! empty( $GLOBALS['ah_test_e_pagina'] ); }
