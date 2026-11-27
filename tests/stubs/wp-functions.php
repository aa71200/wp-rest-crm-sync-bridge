<?php
/**
 * WordPress function stubs for isolated wp-rest-crm-sync-bridge CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

use CodexaWP\RestCrmBridge\Tests\Test_Store;

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

/**
 * Translate text stub.
 *
 * @param string $text   Text to translate.
 * @param string $domain Text domain.
 */
function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress signature compatibility.
	return $text;
}

/**
 * Escape HTML special characters.
 *
 * @param string $text Raw string.
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Sanitize URL stub.
 *
 * @param string $url Raw URL.
 */
function esc_url_raw( string $url ): string {
	return trim( $url );
}

/**
 * Return non-negative integer value.
 *
 * @param mixed $maybeint Input scalar.
 */
function absint( $maybeint ): int {
	return abs( (int) $maybeint );
}

/**
 * Register action hook stub.
 *
 * @param string   $hook_name Hook name.
 * @param callable $callback  Callback.
 * @param int      $priority  Priority.
 */
function add_action( string $hook_name, callable $callback, int $priority = 10 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress signature compatibility.
	return true;
}

/**
 * Return trailing-slashed directory path for plugin file.
 *
 * @param string $file File path.
 */
function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

/**
 * Check current user capability stub.
 *
 * @param string $capability Capability name.
 */
function current_user_can( string $capability ): bool {
	return ! empty( Test_Store::$capabilities[ $capability ] );
}

/**
 * Retrieve option value stub.
 *
 * @param string $option        Option name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_option( string $option, $default_value = false ) {
	return array_key_exists( $option, Test_Store::$options ) ? Test_Store::$options[ $option ] : $default_value;
}

/**
 * Retrieve transient value stub.
 *
 * @param string $transient Transient key.
 * @return mixed
 */
function get_transient( string $transient ) {
	return array_key_exists( $transient, Test_Store::$transients ) ? Test_Store::$transients[ $transient ] : false;
}

/**
 * Persist transient value stub.
 *
 * @param string $transient  Transient key.
 * @param mixed  $value      Transient value.
 * @param int    $expiration Expiration seconds.
 */
function set_transient( string $transient, $value, int $expiration = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress signature compatibility.
	Test_Store::$transients[ $transient ] = $value;
	return true;
}

/**
 * Look up user by ID or email stub.
 *
 * @param string     $field Lookup field ('id' or 'email').
 * @param int|string $value Lookup value.
 * @return WP_User|false
 */
function get_user_by( string $field, $value ) {
	foreach ( Test_Store::$users as $user ) {
		if ( 'id' === $field && (int) $value === $user->ID ) {
			return $user;
		}
		if ( 'email' === $field && strtolower( (string) $value ) === strtolower( $user->user_email ) ) {
			return $user;
		}
	}

	return false;
}

/**
 * Retrieve user metadata stub.
 *
 * @param int    $user_id User ID.
 * @param string $key     Meta key.
 * @param bool   $single  Single value flag.
 * @return mixed
 */
function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
	if ( '' === $key ) {
		return Test_Store::$user_meta[ $user_id ] ?? array();
	}

	if ( isset( Test_Store::$user_meta[ $user_id ] ) && array_key_exists( $key, Test_Store::$user_meta[ $user_id ] ) ) {
		return $single ? Test_Store::$user_meta[ $user_id ][ $key ] : array( Test_Store::$user_meta[ $user_id ][ $key ] );
	}

	return $single ? '' : array();
}

/**
 * Update user metadata stub and increment write counter.
 *
 * @param int    $user_id User ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Meta value.
 */
function update_user_meta( int $user_id, string $key, $value ): bool {
	if ( ! isset( Test_Store::$user_meta[ $user_id ] ) ) {
		Test_Store::$user_meta[ $user_id ] = array();
	}

	Test_Store::$user_meta[ $user_id ][ $key ] = $value;
	++Test_Store::$meta_writes;

	return true;
}

/**
 * Return stub site home URL.
 */
function home_url(): string {
	return 'https://example.test';
}

/**
 * Check whether value is a WP_Error instance.
 *
 * @param mixed $thing Value to check.
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/**
 * Sanitize plain text input.
 *
 * @param string $str Input text.
 */
function sanitize_text_field( string $str ): string {
	$cleaned = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( is_string( $cleaned ) ? $cleaned : '' );
}

/**
 * Sanitize key string.
 *
 * @param string $key Raw key.
 */
function sanitize_key( string $key ): string {
	$lower = strtolower( $key );
	$clean = preg_replace( '/[^a-z0-9_\-]/', '', $lower );
	return is_string( $clean ) ? $clean : '';
}

/**
 * Sanitize email string.
 *
 * @param string $email Raw email.
 */
function sanitize_email( string $email ): string {
	$clean = filter_var( trim( $email ), FILTER_SANITIZE_EMAIL );
	return is_string( $clean ) ? $clean : '';
}

/**
 * Encode value to JSON.
 *
 * @param mixed $data Data to encode.
 * @return string|false
 */
function wp_json_encode( $data ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress polyfill for isolated CLI tests.
	return json_encode( $data );
}

/**
 * Record REST route definition into Test_Store.
 *
 * @param string               $route_namespace Route namespace.
 * @param string               $route           Route pattern.
 * @param array<string, mixed> $args            Route arguments.
 */
function register_rest_route( string $route_namespace, string $route, array $args = array() ): bool {
	$full_path                        = '/' . trim( $route_namespace, '/' ) . '/' . ltrim( $route, '/' );
	Test_Store::$routes[ $full_path ] = $args;
	return true;
}

/**
 * Simulate wp_remote_post HTTP call.
 *
 * @param string               $url  Endpoint URL.
 * @param array<string, mixed> $args Request options.
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_post( string $url, array $args = array() ) {
	Test_Store::$last_remote_request = array(
		'url'  => $url,
		'args' => $args,
	);

	if ( null !== Test_Store::$remote_response ) {
		return Test_Store::$remote_response;
	}

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => '{"ok":true}',
	);
}

/**
 * Retrieve HTTP response code from stubbed response.
 *
 * @param array<string, mixed> $response HTTP response array.
 */
function wp_remote_retrieve_response_code( array $response ): int {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * Retrieve HTTP body from stubbed response.
 *
 * @param array<string, mixed> $response HTTP response array.
 */
function wp_remote_retrieve_body( array $response ): string {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}
