<?php
/**
 * Minimal WP_User stub for isolated CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

/**
 * Lightweight WordPress WP_User polyfill.
 */
class WP_User {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public int $ID = 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Matches WordPress WP_User::$ID.

	/**
	 * User email address.
	 *
	 * @var string
	 */
	public string $user_email = '';

	/**
	 * User display name.
	 *
	 * @var string
	 */
	public string $display_name = '';

	/**
	 * Assigned user roles.
	 *
	 * @var array<int, string>
	 */
	public array $roles = array();

	/**
	 * Constructor.
	 *
	 * @param int                $id           User ID.
	 * @param string             $email        Email address.
	 * @param string             $display_name Display name.
	 * @param array<int, string> $roles        Role list.
	 */
	public function __construct(
		int $id = 0,
		string $email = '',
		string $display_name = '',
		array $roles = array()
	) {
		$this->ID           = $id;
		$this->user_email   = $email;
		$this->display_name = $display_name;
		$this->roles        = $roles;
	}
}
