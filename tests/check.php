<?php
/**
 * Isolated CLI verification suite for wp-rest-crm-sync-bridge.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge\Tests;

use CodexaWP\RestCrmBridge\Auth_Verifier;
use CodexaWP\RestCrmBridge\CRM_Client;
use CodexaWP\RestCrmBridge\Rest_Controller;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_User;

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/stubs/class-test-store.php';
require_once __DIR__ . '/stubs/class-wp-error.php';
require_once __DIR__ . '/stubs/class-wp-user.php';
require_once __DIR__ . '/stubs/class-wp-rest-server.php';
require_once __DIR__ . '/stubs/class-wp-rest-controller.php';
require_once __DIR__ . '/stubs/class-wp-rest-request.php';
require_once __DIR__ . '/stubs/class-wp-rest-response.php';
require_once __DIR__ . '/stubs/wp-functions.php';
require_once dirname( __DIR__ ) . '/wp-rest-crm-sync-bridge.php';

/**
 * Assert condition or fail with a clear runtime exception.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @throws RuntimeException When assertion fails.
 */
function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Validate incoming parameters against a registered WP REST route schema.
 *
 * @param array<string, array<string, mixed>> $schema Route args schema.
 * @param array<string, mixed>                $input  Incoming request params.
 * @return array<string, mixed>|WP_Error
 */
function validate_against_route_schema( array $schema, array $input ) {
	$sanitized = array();

	foreach ( $schema as $param => $rules ) {
		$present = array_key_exists( $param, $input );

		if ( ! $present ) {
			if ( ! empty( $rules['required'] ) ) {
				return new WP_Error( 'rest_missing_callback_param', 'Missing parameter: ' . $param, array( 'status' => 400 ) );
			}
			$sanitized[ $param ] = $rules['default'] ?? null;
			continue;
		}

		$value = $input[ $param ];
		if ( 'string' === ( $rules['type'] ?? '' ) ) {
			if ( ! is_string( $value ) ) {
				return new WP_Error( 'rest_invalid_param', 'Expected string for ' . $param, array( 'status' => 400 ) );
			}
			if ( isset( $rules['minLength'] ) && strlen( $value ) < (int) $rules['minLength'] ) {
				return new WP_Error( 'rest_invalid_param', 'Too short: ' . $param, array( 'status' => 400 ) );
			}
			if ( isset( $rules['maxLength'] ) && strlen( $value ) > (int) $rules['maxLength'] ) {
				return new WP_Error( 'rest_invalid_param', 'Too long: ' . $param, array( 'status' => 400 ) );
			}
			if ( 'email' === ( $rules['format'] ?? '' ) && false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				return new WP_Error( 'rest_invalid_param', 'Invalid email: ' . $param, array( 'status' => 400 ) );
			}
			if ( isset( $rules['enum'] ) && ! in_array( $value, $rules['enum'], true ) ) {
				return new WP_Error( 'rest_invalid_param', 'Invalid enum value for ' . $param, array( 'status' => 400 ) );
			}
		} elseif ( 'array' === ( $rules['type'] ?? '' ) && ! is_array( $value ) ) {
			return new WP_Error( 'rest_invalid_param', 'Expected array for ' . $param, array( 'status' => 400 ) );
		}

		if ( isset( $rules['sanitize_callback'] ) && is_callable( $rules['sanitize_callback'] ) ) {
			$value = call_user_func( $rules['sanitize_callback'], $value );
		}

		$sanitized[ $param ] = $value;
	}

	return $sanitized;
}

/**
 * Build a signed REST webhook request fixture.
 *
 * @param string $timestamp Unix timestamp string.
 * @param string $body      Raw JSON body.
 * @param string $signature Optional override signature.
 */
function build_signed_request( string $timestamp, string $body, ?string $signature = null ): WP_REST_Request {
	$secret  = (string) Test_Store::$options['wprb_webhook_secret'];
	$request = new WP_REST_Request();
	$request->set_header( 'X-WPRB-Timestamp', $timestamp );
	$request->set_header(
		'X-WPRB-Signature',
		null !== $signature ? $signature : Auth_Verifier::compute_signature( $timestamp, $body, $secret )
	);
	$request->set_body( $body );

	return $request;
}

// Test 1: Valid and invalid HMAC-SHA256 signatures plus missing auth headers.
Test_Store::reset();
Test_Store::$options['wprb_webhook_secret'] = 'unit-test-shared-secret';
$auth                                       = new Auth_Verifier();
$now_timestamp                              = (string) time();
$raw_payload                                = '{"event_id":"evt_001","email":"buyer@example.test","status":"qualified"}';

$valid_request = build_signed_request( $now_timestamp, $raw_payload );
assert_true( true === $auth->verify_webhook_signature( $valid_request ), 'Valid HMAC signature should be accepted.' );

$tampered_request = build_signed_request( $now_timestamp, $raw_payload, str_repeat( '0', 64 ) );
$tampered_result  = $auth->verify_webhook_signature( $tampered_request );
assert_true(
	$tampered_result instanceof WP_Error && 'wprb_invalid_signature' === $tampered_result->get_error_code(),
	'Tampered HMAC signature must return wprb_invalid_signature.'
);

$missing_headers_request = new WP_REST_Request();
$missing_headers_result  = $auth->verify_webhook_signature( $missing_headers_request );
assert_true(
	$missing_headers_result instanceof WP_Error && 'wprb_missing_auth_headers' === $missing_headers_result->get_error_code(),
	'Missing auth headers must return wprb_missing_auth_headers.'
);
echo esc_html( 'PASS: Valid HMAC signature accepted and invalid/missing signatures rejected.' ) . "\n";

// Test 2: Timestamp replay protection window (rejects timestamps older than 300 seconds).
$stale_timestamp = (string) ( time() - ( Auth_Verifier::REPLAY_WINDOW + 30 ) );
$stale_request   = build_signed_request( $stale_timestamp, $raw_payload );
$stale_result    = $auth->verify_webhook_signature( $stale_request );
assert_true(
	$stale_result instanceof WP_Error && 'wprb_timestamp_expired' === $stale_result->get_error_code(),
	'Expired webhook timestamp must be rejected with wprb_timestamp_expired.'
);
echo esc_html( 'PASS: Stale webhook timestamps outside the 300s replay window are rejected.' ) . "\n";

// Test 3: Route schema validation for missing required fields, invalid email, and invalid status enum.
$client     = new CRM_Client();
$controller = new Rest_Controller( $auth, $client );
$controller->register_routes();

$webhook_route = Test_Store::$routes['/codexawp-crm/v1/webhook/contact'] ?? array();
assert_true( ! empty( $webhook_route['args'] ), 'Webhook route schema args must be registered.' );

$schema_args     = $webhook_route['args'];
$missing_email   = validate_against_route_schema(
	$schema_args,
	array(
		'event_id' => 'evt_missing_email',
		'status'   => 'lead',
	)
);
$invalid_email   = validate_against_route_schema(
	$schema_args,
	array(
		'event_id' => 'evt_bad_email',
		'email'    => 'not-an-email',
		'status'   => 'lead',
	)
);
$invalid_status  = validate_against_route_schema(
	$schema_args,
	array(
		'event_id' => 'evt_bad_status',
		'email'    => 'buyer@example.test',
		'status'   => 'unknown',
	)
);
$valid_validated = validate_against_route_schema(
	$schema_args,
	array(
		'event_id' => 'evt_valid_100',
		'email'    => 'buyer@example.test',
		'status'   => 'customer',
		'tags'     => array( 'VIP Tier', 'vip_tier', 'b2b' ),
	)
);

assert_true( $missing_email instanceof WP_Error, 'Missing required email must fail schema validation.' );
assert_true( $invalid_email instanceof WP_Error, 'Malformed email must fail schema validation.' );
assert_true( $invalid_status instanceof WP_Error, 'Invalid status enum must fail schema validation.' );
assert_true( is_array( $valid_validated ) && 'customer' === $valid_validated['status'], 'Valid webhook payload must pass schema validation.' );
echo esc_html( 'PASS: Route schema rejects missing fields, invalid emails, and disallowed status values.' ) . "\n";

// Test 4: Idempotent event_id deduplication and user meta persistence.
Test_Store::$users[42] = new WP_User( 42, 'buyer@example.test', 'Test Buyer', array( 'customer' ) );

$webhook_request = new WP_REST_Request();
foreach ( $valid_validated as $param_key => $param_value ) {
	$webhook_request->set_param( $param_key, $param_value );
}

$first_response = $controller->handle_inbound_webhook( $webhook_request );
$first_data     = $first_response->get_data();
$writes_after_1 = Test_Store::$meta_writes;

assert_true( 200 === $first_response->get_status() && true === $first_data['processed'], 'First webhook delivery should process and return HTTP 200.' );
assert_true( 'customer' === get_user_meta( 42, '_wprb_crm_status', true ), 'CRM status user meta must be updated.' );
assert_true( array( 'viptier', 'vip_tier', 'b2b' ) === get_user_meta( 42, '_wprb_crm_tags', true ), 'Sanitized tags must be deduplicated and stored.' );

$replay_response = $controller->handle_inbound_webhook( $webhook_request );
$replay_data     = $replay_response->get_data();

assert_true(
	200 === $replay_response->get_status()
	&& false === $replay_data['processed']
	&& true === $replay_data['duplicate']
	&& Test_Store::$meta_writes === $writes_after_1,
	'Replayed event_id must return duplicate=true without rewriting user meta.'
);
echo esc_html( 'PASS: Idempotent event_id deduplication skips duplicate writes on webhook replay.' ) . "\n";

// Test 5: Admin capability gate and outbound CRM sync payload + Idempotency-Key header.
$denied_admin = $auth->verify_admin_request();
assert_true( $denied_admin instanceof WP_Error && 'wprb_forbidden' === $denied_admin->get_error_code(), 'Non-admin user must be denied sync permission.' );

Test_Store::$capabilities['manage_options'] = true;
Test_Store::$options['wprb_crm_endpoint']   = 'https://crm.example.test/api/contacts';
Test_Store::$options['wprb_crm_api_key']    = 'test-bearer-token';
assert_true( true === $auth->verify_admin_request(), 'Admin with manage_options must pass permission check.' );

$sync_request = new WP_REST_Request();
$sync_request->set_param( 'id', 42 );
$sync_response = $controller->handle_outbound_user_sync( $sync_request );
$sync_data     = $sync_response->get_data();
$sent_headers  = Test_Store::$last_remote_request['args']['headers'] ?? array();

assert_true( 200 === $sync_response->get_status() && true === $sync_data['synced'], 'Outbound user sync must return HTTP 200.' );
assert_true( ! empty( $sent_headers['Idempotency-Key'] ), 'Outbound CRM request must include Idempotency-Key header.' );
assert_true( 'Bearer test-bearer-token' === ( $sent_headers['Authorization'] ?? '' ), 'Outbound CRM request must include Bearer token.' );
echo esc_html( 'PASS: Admin capability check and outbound CRM sync headers verified.' ) . "\n";
echo esc_html( 'ALL 5 CHECKS PASSED.' ) . "\n";
