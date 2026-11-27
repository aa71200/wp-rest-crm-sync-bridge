<?php
/**
 * Custom WP_REST_Controller for inbound CRM webhooks and outbound user sync.
 *
 * @package CodexaWP\RestCrmBridge
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles plugin REST API routes.
 */
final class Rest_Controller extends WP_REST_Controller {

	public const ALLOWED_STATUSES = array( 'lead', 'qualified', 'customer', 'inactive' );
	public const EVENT_TTL        = DAY_IN_SECONDS;

	/**
	 * Auth verifier dependency.
	 *
	 * @var Auth_Verifier
	 */
	private Auth_Verifier $auth;

	/**
	 * CRM HTTP client dependency.
	 *
	 * @var CRM_Client
	 */
	private CRM_Client $client;

	/**
	 * Constructor.
	 *
	 * @param Auth_Verifier $auth   Request authentication verifier.
	 * @param CRM_Client    $client Outbound CRM HTTP client.
	 */
	public function __construct( Auth_Verifier $auth, CRM_Client $client ) {
		$this->namespace = 'codexawp-crm/v1';
		$this->auth      = $auth;
		$this->client    = $client;
	}

	/**
	 * Register REST routes and schemas.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/webhook/contact',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_inbound_webhook' ),
				'permission_callback' => array( $this->auth, 'verify_webhook_signature' ),
				'args'                => $this->get_webhook_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sync/user/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_outbound_user_sync' ),
				'permission_callback' => array( $this->auth, 'verify_admin_request' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Process a verified inbound CRM webhook with idempotent event deduplication.
	 *
	 * @param WP_REST_Request $request Validated REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_inbound_webhook( WP_REST_Request $request ) {
		$event_id = (string) $request->get_param( 'event_id' );
		$email    = (string) $request->get_param( 'email' );
		$status   = (string) $request->get_param( 'status' );
		$tags     = $request->get_param( 'tags' );

		$lock_key = 'wprb_evt_' . md5( $event_id );
		if ( false !== get_transient( $lock_key ) ) {
			return new WP_REST_Response(
				array(
					'processed' => false,
					'duplicate' => true,
					'event_id'  => $event_id,
				),
				200
			);
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof \WP_User ) {
			set_transient( $lock_key, 'ignored', self::EVENT_TTL );

			return new WP_REST_Response(
				array(
					'processed' => false,
					'reason'    => 'user_not_found',
					'event_id'  => $event_id,
				),
				202
			);
		}

		$clean_tags = array();
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$sanitized = sanitize_key( (string) $tag );
				if ( '' !== $sanitized ) {
					$clean_tags[] = $sanitized;
				}
			}
		}

		update_user_meta( $user->ID, '_wprb_crm_status', $status );
		update_user_meta( $user->ID, '_wprb_crm_tags', array_values( array_unique( $clean_tags ) ) );
		update_user_meta( $user->ID, '_wprb_last_event_id', $event_id );
		update_user_meta( $user->ID, '_wprb_last_synced_at', gmdate( 'c' ) );

		set_transient( $lock_key, 'processed', self::EVENT_TTL );

		return new WP_REST_Response(
			array(
				'processed' => true,
				'duplicate' => false,
				'event_id'  => $event_id,
				'user_id'   => $user->ID,
				'status'    => $status,
			),
			200
		);
	}

	/**
	 * Push a WordPress user's profile and CRM status to the configured external CRM.
	 *
	 * @param WP_REST_Request $request Validated REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_outbound_user_sync( WP_REST_Request $request ) {
		$user_id = absint( $request->get_param( 'id' ) );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user instanceof \WP_User ) {
			return new WP_Error(
				'wprb_user_not_found',
				__( 'Requested WordPress user does not exist.', 'wp-rest-crm-sync-bridge' ),
				array( 'status' => 404 )
			);
		}

		$payload = array(
			'source'       => 'wordpress',
			'site_url'     => home_url(),
			'wp_user_id'   => $user->ID,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => array_values( $user->roles ),
			'crm_status'   => (string) get_user_meta( $user->ID, '_wprb_crm_status', true ),
		);

		$idempotency_key = 'wp-user-' . $user->ID . '-' . md5( (string) wp_json_encode( $payload ) );
		$result          = $this->client->push_contact( $payload, $idempotency_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_user_meta( $user->ID, '_wprb_last_pushed_at', gmdate( 'c' ) );

		return new WP_REST_Response(
			array(
				'synced'          => true,
				'user_id'         => $user->ID,
				'idempotency_key' => $result['idempotency_key'],
				'upstream_code'   => $result['upstream_code'],
			),
			200
		);
	}

	/**
	 * Validation and sanitization schema for inbound CRM webhooks.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_webhook_args(): array {
		return array(
			'event_id' => array(
				'required'          => true,
				'type'              => 'string',
				'minLength'         => 6,
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'    => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
			'status'   => array(
				'required'          => true,
				'type'              => 'string',
				'enum'              => self::ALLOWED_STATUSES,
				'sanitize_callback' => 'sanitize_key',
			),
			'tags'     => array(
				'required' => false,
				'type'     => 'array',
				'default'  => array(),
				'items'    => array(
					'type' => 'string',
				),
			),
		);
	}
}
