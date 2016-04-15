<?php

namespace Cachetop\Stores;

use Predis\Client;

class Redis extends Store {

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
		'expires' => 1,
		'scheme'  => 'tcp',
		'host'    => 'localhost',
		'port'    => 6379
	];

	/**
	 * The constructor.
	 *
	 * @param array $args
	 */
	protected function __construct( array $args ) {
		$this->args   = array_merge( $this->default_args, $args );
		$this->client = new Client( [
			'scheme' => $this->args['scheme'],
			'host'   => $this->args['host'],
			'port'   => $this->args['port'],
			'prefix' => 'cachetop'
		] );
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
	 * Delete cached data by key.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function delete( $key ) {
		return $this->execute_command( 'del', [$key] );
	}

	/**
	 * Flush will flush all cached data.
	 */
	public function flush() {
		$keys = $this->execute_command( 'keys', ['cachetop:*'] );

		foreach ( $keys as $key ) {
			$this->delete( $key );
		}
	}

	/**
	 * Read cached string from store.
	 *
	 * @param  string $key
	 *
	 * @return null|string
	 */
	public function read( $key ) {
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
		$data = base64_encode( $data );

		$this->execute_command( 'set', [$key, $data] );

		if ( $this->args['expires'] > 0 ) {
			$expire = HOUR_IN_SECONDS * $this->args['expires'];
			$this->execute_command( 'expire', [$key, $expire] );
		}
	}
}
