<?php

namespace Isotop\Cachetop\Stores;

use Isotop\Cachetop\Minify;

abstract class Store {

	/**
	 * The class instance.
	 *
	 * @var \Isotop\Cachetop\Stores\Store
	 */
	private static $instance;

	/**
	 * Get the class instance.
	 *
	 * @param  array $args
	 *
	 * @return \Isotop\Cachetop\Stores\Store
	 */
	public static function instance( array $args = [] ) {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static( $args );
		}

		return self::$instance;
	}

	/**
	 * Count number of keys.
	 *
	 * @return bool
	 */
	abstract public function count();

	/**
	 * Delete cached data by key.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	abstract public function delete( $key );

	/**
	 * Check if key exists.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	abstract public function exists( $key );

	/**
	 * Flush will flush all cached data.
	 */
	abstract public function flush();

	/**
	 * Get cached string from store.
	 *
	 * @param  string $key
	 *
	 * @return null|string
	 */
	abstract public function get( $key );

	/**
	 * Minify html.
	 *
	 * @param  string $data
	 *
	 * @return string
	 */
	protected function minify( $data ) {
		return ( new Minify )->compress( $data );
	}

	/**
	 * Set string to cache in store.
	 *
	 * @param  string $key
	 * @param  string $data
	 */
	abstract public function set( $key, $data );
}
