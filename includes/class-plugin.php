<?php
/**
 * Plugin bootstrapper.
 *
 * @package CodexaWP\RestCrmBridge
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Retrieve singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register REST API initialization hook.
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register custom REST routes.
	 */
	public function register_routes(): void {
		$auth   = new Auth_Verifier();
		$client = new CRM_Client();

		( new Rest_Controller( $auth, $client ) )->register_routes();
	}
}
