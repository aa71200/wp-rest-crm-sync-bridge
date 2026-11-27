<?php
/**
 * Minimal WP_REST_Request stub for isolated CLI tests.
 *
 * @package CodexaWP\RestCrmBridge\Tests
 */

declare(strict_types=1);

/**
 * Lightweight WordPress WP_REST_Request polyfill.
 */
class WP_REST_Request {

	/**
	 * Lowercase-keyed request headers.
	 *
	 * @var array<string, string>
	 */
	private array $headers = array();

	/**
	 * Request parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $params = array();

	/**
	 * Raw request body.
	 *
	 * @var string
	 */
	private string $body = '';

	/**
	 * Set a single header value.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 */
	public function set_header( string $name, string $value ): void {
		$this->headers[ strtolower( $name ) ] = $value;
	}

	/**
	 * Retrieve a header value case-insensitively.
	 *
	 * @param string $name Header name.
	 */
	public function get_header( string $name ): ?string {
		$key = strtolower( $name );
		return $this->headers[ $key ] ?? null;
	}

	/**
	 * Set raw HTTP request body.
	 *
	 * @param string $body Raw request payload.
	 */
	public function set_body( string $body ): void {
		$this->body = $body;
	}

	/**
	 * Retrieve raw HTTP request body.
	 */
	public function get_body(): string {
		return $this->body;
	}

	/**
	 * Set a request parameter.
	 *
	 * @param string $key   Parameter name.
	 * @param mixed  $value Parameter value.
	 */
	public function set_param( string $key, $value ): void {
		$this->params[ $key ] = $value;
	}

	/**
	 * Retrieve a request parameter.
	 *
	 * @param string $key Parameter name.
	 * @return mixed
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}
}
