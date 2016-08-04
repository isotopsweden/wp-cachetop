<?php

namespace Cachetop\Stores;

use Predis\Client;

class Redis extends Store {

	/**
	 * The expires time in seconds.
	 *
	 * @var int
	 */
	private $expires = 3600;

	/**
	 * The Redis prefix.
	 *
	 * @var string
	 */
	private $prefix = 'cachetop:';

	/**
	 * Predis client instance.
	 *
	 * @var \Predis\Client
	 */
	private $client;

	/**
	 * Default arguments.
	 *
	 * @var array
	 */
	private $default_args = [
		'expires'  => 3600,
		'scheme'   => 'tcp',
		'host'     => 'localhost',
		'database' => 0,
		'port'     => 6379
	];

	/**
	 * The constructor.
	 *
	 * @param array $args
	 */
	public function __construct( array $args = [] ) {
		$this->args = array_merge( $this->default_args, $args );

		// Save expires config so we can ue `$this->args` to Redis client.
		$this->expires = $this->args['expires'];
		unset( $this->args['expires'] );

		// Create a new redis client with cacehtop prefix.
		$this->client = new Client(
			$this->args,
			[
				'prefix'   => $this->prefix
			]
		);
	}

	/**
	 * Execute a Predis command.
	 *
	 * @param  string $name
	 * @param  array  $arguments
	 *
	 * @return string
	 */
	protected function execute_command( $name, array $arguments ) {
		$command = $this->client->createCommand( $name, $arguments );

		return $this->client->executeCommand( $command );
	}

	/**
	 * Count number of keys.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function count() {
		return count( $this->execute_command( 'keys', ['*'] ) );
	}

	/**
	 * Delete cached data by key.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function delete( $key ) {
		return (bool) $this->execute_command( 'del', [$key] );
	}

	/**
	 * Check if key exists.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		return $this->execute_command( 'exists', [$key] ) === 1;
	}

	/**
	 * Flush will flush all cached data.
	 */
	public function flush() {
		$keys = $this->execute_command( 'keys', ['*'] );

		foreach ( $keys as $key ) {
			$this->delete( $key );
		}
	}

	/**
	 * Get cached string from store.
	 *
	 * @param  string $key
	 *
	 * @return null|string
	 */
	public function get( $key ) {
		if ( $data = $this->execute_command( 'get', [$key] ) ) {
			return base64_decode( $data );
		}
	}

	/**
	 * Set string to cache in store.
	 *
	 * @param  string $key
	 * @param  string $data
	 */
	public function set( $key, $data ) {
		$data = $this->minify( $data );
		$data = base64_encode( $data );

		$this->execute_command( 'set', [$key, $data] );

		if ( $this->expires > 0 ) {
			$this->execute_command( 'expire', [$key, $this->expires] );
		}
	}
}
