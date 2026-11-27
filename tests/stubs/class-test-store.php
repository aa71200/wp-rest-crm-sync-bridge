<?php
/**
 * In-memory state store for isolated wp-rest-crm-sync-bridge CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

namespace CodexaWP\RestCrmBridge\Tests;

/**
 * Holds stubbed options, transients, users, meta, routes, and HTTP responses.
 */
final class Test_Store {

	/**
	 * Option values keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	public static array $options = array();

	/**
	 * Transient values keyed by transient name.
	 *
	 * @var array<string, mixed>
	 */
	public static array $transients = array();

	/**
	 * Stubbed users keyed by user ID.
	 *
	 * @var array<int, \WP_User>
	 */
	public static array $users = array();

	/**
	 * User metadata keyed by user ID and meta key.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $user_meta = array();

	/**
	 * Number of update_user_meta writes performed.
	 *
	 * @var int
	 */
	public static int $meta_writes = 0;

	/**
	 * Capabilities granted to the active test user.
	 *
	 * @var array<string, bool>
	 */
	public static array $capabilities = array();

	/**
	 * Registered REST routes keyed by full route path.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public static array $routes = array();

	/**
	 * Canned HTTP response or WP_Error for wp_remote_post.
	 *
	 * @var array<string, mixed>|\WP_Error|null
	 */
	public static $remote_response = null;

	/**
	 * Last request passed to wp_remote_post.
	 *
	 * @var array<string, mixed>
	 */
	public static array $last_remote_request = array();

	/**
	 * Reset all in-memory state between test scenarios.
	 */
	public static function reset(): void {
		self::$options             = array();
		self::$transients          = array();
		self::$users               = array();
		self::$user_meta           = array();
		self::$meta_writes         = 0;
		self::$capabilities        = array();
		self::$routes              = array();
		self::$remote_response     = null;
		self::$last_remote_request = array();
	}
}
