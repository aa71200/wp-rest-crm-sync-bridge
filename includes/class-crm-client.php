<?php
/**
 * Outbound HTTP client for pushing sanitized WordPress contact payloads to an external CRM.
 *
 * @package CodexaWP\RestCrmBridge
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CRM_Client {

	/**
	 * Push a contact payload to the configured external CRM endpoint.
	 *
	 * @param array<string, mixed> $payload         Sanitized contact payload.
	 * @param string               $idempotency_key Unique request key for safe retries.
	 * @return array<string, mixed>|WP_Error
	 */
	public function push_contact( array $payload, string $idempotency_key ) {
		$endpoint = $this->get_endpoint_url();
		$api_key  = $this->get_api_key();

		if ( '' === $endpoint ) {
			return new WP_Error(
				'wprb_endpoint_unconfigured',
				__( 'External CRM endpoint URL is not configured.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 500 )
			);
		}

		$encoded_body = wp_json_encode( $payload );
		if ( false === $encoded_body ) {
			return new WP_Error(
				'wprb_encode_failed',
				__( 'Failed to encode CRM payload as JSON.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 500 )
			);
		}

		$headers = array(
			'Content-Type'    => 'application/json',
			'Accept'          => 'application/json',
			'Idempotency-Key' => $idempotency_key,
		);

		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => $encoded_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wprb_remote_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'wprb_crm_rejected',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'CRM endpoint returned HTTP %d.', 'wp-rest-crm-sync-bridge' ),
					$status_code
				),
				array(
					'status'        => 502,
					'upstream_code' => $status_code,
				)
			);
		}

		return array(
			'upstream_code'   => $status_code,
			'idempotency_key' => $idempotency_key,
			'response'        => json_decode( $raw_body, true ),
		);
	}

	/**
	 * Retrieve configured CRM endpoint URL.
	 */
	private function get_endpoint_url(): string {
		if ( defined( 'WPRB_CRM_ENDPOINT' ) && is_string( WPRB_CRM_ENDPOINT ) ) {
			return esc_url_raw( trim( WPRB_CRM_ENDPOINT ) );
		}

		return esc_url_raw( trim( (string) get_option( 'wprb_crm_endpoint', '' ) ) );
	}

	/**
	 * Retrieve configured CRM bearer token.
	 */
	private function get_api_key(): string {
		if ( defined( 'WPRB_CRM_API_KEY' ) && is_string( WPRB_CRM_API_KEY ) ) {
			return trim( WPRB_CRM_API_KEY );
		}

		return trim( (string) get_option( 'wprb_crm_api_key', '' ) );
	}
}
