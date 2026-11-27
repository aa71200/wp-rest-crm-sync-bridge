<?php
/**
 * Request authentication and HMAC webhook signature verification.
 *
 * @package CodexaWP\RestCrmBridge
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin capability checks and webhook signature verification.
 */
final class Auth_Verifier {

	public const SIGNATURE_HEADER = 'x-wprb-signature';
	public const TIMESTAMP_HEADER = 'x-wprb-timestamp';
	public const REPLAY_WINDOW    = 300;

	/**
	 * Verify that the current user can run manual CRM sync actions.
	 *
	 * @return bool|WP_Error
	 */
	public function verify_admin_request() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'wprb_forbidden',
			__( 'You do not have permission to trigger CRM sync.', 'wp-rest-crm-sync-bridge' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Verify an inbound webhook using HMAC-SHA256 and a 5-minute replay window.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error
	 */
	public function verify_webhook_signature( WP_REST_Request $request ) {
		$secret = $this->get_webhook_secret();

		if ( '' === $secret ) {
			return new WP_Error(
				'wprb_secret_missing',
				__( 'Webhook secret is not configured on this site.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 500 )
			);
		}

		$timestamp = (string) $request->get_header( self::TIMESTAMP_HEADER );
		$signature = (string) $request->get_header( self::SIGNATURE_HEADER );

		if ( '' === $timestamp || '' === $signature || ! ctype_digit( $timestamp ) ) {
			return new WP_Error(
				'wprb_missing_auth_headers',
				__( 'Missing or malformed webhook authentication headers.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 401 )
			);
		}

		$ts_int = (int) $timestamp;
		if ( abs( time() - $ts_int ) > self::REPLAY_WINDOW ) {
			return new WP_Error(
				'wprb_timestamp_expired',
				__( 'Webhook timestamp is outside the allowed replay window.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 401 )
			);
		}

		$raw_body = $request->get_body();
		$expected = self::compute_signature( $timestamp, $raw_body, $secret );

		if ( ! hash_equals( $expected, strtolower( trim( $signature ) ) ) ) {
			return new WP_Error(
				'wprb_invalid_signature',
				__( 'Webhook signature verification failed.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Compute HMAC-SHA256 signature for timestamp + raw body.
	 *
	 * @param string $timestamp Unix timestamp header value.
	 * @param string $raw_body  Raw HTTP request body.
	 * @param string $secret    Shared HMAC secret.
	 */
	public static function compute_signature( string $timestamp, string $raw_body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
	}

	/**
	 * Retrieve configured webhook secret from constant or option.
	 */
	private function get_webhook_secret(): string {
		if ( defined( 'WPRB_WEBHOOK_SECRET' ) && is_string( WPRB_WEBHOOK_SECRET ) ) {
			return trim( WPRB_WEBHOOK_SECRET );
		}

		return trim( (string) get_option( 'wprb_webhook_secret', '' ) );
	}
}
