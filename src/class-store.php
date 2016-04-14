<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryCacheStore;
use League\Flysystem\Cached\Storage\Predis as RedisCacheStore;
use League\Flysystem\FileNotFoundException;

class Store {

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
		'expires' => HOUR_IN_SECONDS * 1,
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
	public function __construct( array $args ) {
		$this->args = array_merge( $this->default_args, $args );
		$this->filesystem = new Filesystem( $this->get_adapter() );
	}

	/**
	 * Delete cached data by hash.
	 *
	 * @param  string $hash
	 *
	 * @return bool
	 */
	public function delete( $hash ) {
		if ( ! is_string( $hash ) ) {
			return false;
		}

		$file = $this->get_file_name( $hash );

		return $this->filesystem->delete( $file );
	}

	/**
	 * Get store adapter.
	 *
	 * @return \League\Flysystem\Cached\CachedAdapter
	 */
	protected function get_adapter() {
		$local_adapter = new Adapter( WP_CONTENT_DIR . '/cache/cachetop' );
		$cache_store   = $this->args['redis']
			? new RedisCacheStore() : new MemoryCacheStore();

		return new CachedAdapter( $local_adapter, $cache_store );
	}

	/**
	 * Get file name for the hash.
	 *
	 * @param  string $hash
	 *
	 * @return string
	 */
	protected function get_file_name( $hash ) {
		return sprintf( '%s.html', $hash );
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
	 * @param  string $hash
	 *
	 * @return null|string
	 */
	public function read( $hash ) {
		if ( ! is_string( $hash ) ) {
			return;
		}

		try {
			$file = $this->get_file_name( $hash );

			// Expire the file if the expires time is not zero.
			if ( $this->args['expires'] > 0 ) {
				$time = $this->filesystem->getTimestamp( $file );

				// If time is bigger than expires and file timestamp
				// the file should be deleted and null should be returned
				// since the cache has expired.
				if ( time() > $this->args['expires'] * $time ) {
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
	 * @param  string $hash
	 * @param  string $data
	 * @param  int    $lifetime
	 */
	public function set( $hash, $data, $lifetime ) {
		if ( ! is_string( $hash ) || ! is_string( $data ) ) {
			return;
		}

		$file = $this->get_file_name( $hash );
		$data = $this->minify( $data );

		$this->filesystem->write( $file, $data );
	}
}
