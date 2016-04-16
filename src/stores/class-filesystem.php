<?php

namespace Cachetop\Stores;

use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryCacheStore;
use League\Flysystem\Cached\Storage\Predis as RedisCacheStore;
use League\Flysystem\FileNotFoundException;

class Filesystem extends Store {

	/**
	 * Current arguments.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Default arguments.
	 *
	 * @var array
	 */
	private $default_args = [
		'expires' => 1,
		'redis'   => false
	];

	/**
	 * The filesystem instance.
	 *
	 * @var \League\Flysystem\Filesystem
	 */
	private $filesystem;

	/**
	 * The constructor.
	 *
	 * @param array $args
	 */
	protected function __construct( array $args ) {
		$this->args       = array_merge( $this->default_args, $args );
		$this->filesystem = new LeagueFilesystem( $this->get_adapter() );
	}

	/**
	 * Check if key exists.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		if ( ! is_string( $key ) ) {
			return false;
		}

		$file = $this->get_file_name( $key );

		return $this->filesystem->has( $file );
	}

	/**
	 * Delete cached data by key.
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public function delete( $key ) {
		if ( ! is_string( $key ) ) {
			return false;
		}

		$file = $this->get_file_name( $key );

		return $this->filesystem->delete( $file );
	}

	/**
	 * Flush will flush all cached data.
	 */
	public function flush() {
		/**
		 * @TODO implement
		 */
		// maybe?
		// $this->filesystem->deleteDir('.');
	}

	/**
	 * Get store adapter.
	 *
	 * @return \League\Flysystem\Cached\CachedAdapter
	 */
	protected function get_adapter() {
		$local_adapter = new Adapter( WP_CONTENT_DIR . '/cache/cachetop' );
		$cache_store   = $this->args['redis'] ? new RedisCacheStore() : new MemoryCacheStore();

		return new CachedAdapter( $local_adapter, $cache_store );
	}

	/**
	 * Get file name for the key.
	 *
	 * @param  string $key
	 *
	 * @return string
	 */
	protected function get_file_name( $key ) {
		return sprintf( '%s.html', $key );
	}

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
	 * Read cached string from store.
	 *
	 * @param  string $key
	 *
	 * @return null|string
	 */
	public function read( $key ) {
		if ( ! is_string( $key ) ) {
			return;
		}

		try {
			$file = $this->get_file_name( $key );

			// Expire the file if the expires time is not zero.
			if ( $this->args['expires'] > 0 ) {
				$time = $this->filesystem->getTimestamp( $file );

				// If time is bigger than expires and file timestamp
				// the file should be deleted and null should be returned
				// since the cache has expired.
				if ( time() > ( HOUR_IN_SECONDS * $this->args['expires'] ) * $time ) {
					$this->filesystem->delete( $file );

					return;
				}
			}

			// Try to read the file.
			$content = $this->filesystem->read( $file );

			// Delete the file if empty.
			if ( empty( $content ) ) {
				$this->filesystem->delete( $file );
			}

			return $content;
		} catch ( FileNotFoundException $e ) {
			return;
		}
	}

	/**
	 * Set string to cache in filesystem.
	 *
	 * @param  string $key
	 * @param  string $data
	 */
	public function set( $key, $data ) {
		if ( ! is_string( $key ) || ! is_string( $data ) ) {
			return;
		}

		$file = $this->get_file_name( $key );
		$data = $this->minify( $data );

		$this->filesystem->write( $file, $data );
	}
}
