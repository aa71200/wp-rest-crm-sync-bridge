<?php
/**
 * Minimal WP_REST_Response stub for isolated CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

/**
 * Lightweight WordPress WP_REST_Response polyfill.
 */
class WP_REST_Response {

	/**
	 * Response payload.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Constructor.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 */
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/**
	 * Retrieve response data.
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Retrieve HTTP status code.
	 */
	public function get_status(): int {
		return $this->status;
	}
}
