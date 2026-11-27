<?php
/**
 * Minimal WP_Error stub for isolated CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

/**
 * Lightweight WordPress WP_Error polyfill.
 */
class WP_Error {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Additional error data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Optional error data.
	 */
	public function __construct( string $code = '', string $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * Retrieve error code.
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * Retrieve error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Retrieve error data.
	 *
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->data;
	}
}
