<?php

namespace Cachetop\Stores;

abstract class Store {

	/**
	 * The store instance.
	 *
	 * @var Store
	 */
	private static $instance;

	/**
	 * Get the store instance.
	 *
	 * @param  array $args
	 *
	 * @return Store
	 */
	public static function instance( array $args = [] ) {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static( $args );
		}

		return self::$instance;
	}

	/**
	 * Delete cached data by key.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	abstract public function delete( $key );

	/**
	 * Flush will flush all cached data.
	 */
	abstract public function flush();

	/**
	 * Read cached string from store.
	 *
	 * @param  string $key
	 *
	 * @return null|string
	 */
	abstract public function read( $key );

	/**
	 * Set string to cache in store.
	 *
	 * @param  string $key
	 * @param  string $data
	 */
	abstract public function set( $key, $data );
}
