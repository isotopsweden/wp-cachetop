<?php

namespace Isotop\Cachetop;

if ( ! defined( 'WP_CLI_Command' ) ) {
	return;
}

class Command extends WP_CLI_Command {

	/**
	 * Flush cache by hash, post id, url or all posts.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Flush all posts.
	 *
	 * [--hash=<hash>]
	 * : Flush by cache hash.
	 *
	 * [--post_id=<post_id>]
	 * : Flush by post id
	 *
	 * [--url=<url>]
	 * : Flush by url.
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 */
	public function flush( $args, $assoc_args ) {
		// Flush all posts.
		if ( isset( $assoc_args['all'] ) ) {
			if ( cachetop_flush_all_posts() ) {
				WP_CLI::success( 'Cache flushed' );
			} else {
				WP_CLI::error( 'Cached not flushed' );
			}
		}

		// Flush by hash.
		if ( isset( $assoc_args['hash'] ) ) {
			if ( cachetop_flush_hash( $assoc_args['hash'] ) ) {
				WP_CLI::success( sprintf( 'Cache flushed for hash: %s', $assoc_args['hash'] ) );
			} else {
				WP_CLI::error( 'Cached not flushed' );
			}
		}

		// Flush post by id.
		if ( isset( $assoc_args['post_id'] ) && is_numeric( $assoc_args['post_id'] ) ) {
			if ( cachetop_flush_post( $args[0] ) ) {
				WP_CLI::success( sprintf( 'Cache flushed for post id: %s', $assoc_args['post_id'] ) );
			} else {
				WP_CLI::error( 'Cached not flushed' );
			}
		}

		// Flush by url.
		if ( isset( $assoc_args['url'] ) ) {
			if ( cachetop_flush_url( $assoc_args['url'] ) ) {
				WP_CLI::success( sprintf( 'Cache flushed for url: %s', $assoc_args['url'] ) );
			} else {
				WP_CLI::error( 'Cached not flushed' );
			}
		}
	}
}
