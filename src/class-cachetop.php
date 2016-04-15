<?php

use Cachetop\Stores\Filesystem;
use Cachetop\Stores\Redis;

final class Cachetop {

	/**
	 * The cache store.
	 *
	 * @var Store
	 */
	public $store;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->store = Redis::instance();
		$this->setup_actions();
	}

	/**
	 * Clear post cache.
	 *
	 * @param int $post_id
	 */
	public function clear_post_cache( $post_id ) {
		if ( $hash = get_post_meta( $post_id, '_cachetop_hash', true ) ) {
			$this->store->delete( $hash );
		}
	}

	/**
	 * Handle cached or uncached pages.
	 */
	public function handle_cache() {
		if ( $this->should_bypass() ) {
			return;
		}

		$hash = $this->generate_hash();

		// Try to find the cached html for the hash.
		$cache = $this->store->read( $hash );

		// If the cache is empty, it should create a new.
		if ( empty( $cache ) ) {
			ob_start( [$this, 'set_cache'] );
			return;
		}

		// Render cached html.
		echo $cache . sprintf( '<!-- cached by cachetop - %s - hash: %s -->', date_i18n(
				'd.m.Y H:i:s',
				current_time( 'timestamp' )
			), $hash );

		exit;
	}

	/**
	 * Genrate hash for the current or given url.
	 *
	 * @param  string $url
	 * @param  string $algo
	 *
	 * @return string
	 */
	private function generate_hash( $url = '', $algo = 'sha256' ) {
		if ( empty( $url ) ) {
			$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} else {
			$url = parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH );
		}

		return hash( $algo, $url );
	}

	/**
	 * Set cache data.
	 *
	 * @param string $data
	 */
	public function set_cache( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$hash = $this->generate_hash();

		try {
			$this->store->set( $hash, $data );
		} catch ( Exception $e ) {

		}

		// Save hash on the post for later use, e.g deleting cache file.
		if ( $id = get_the_ID() ) {
			update_post_meta( $id, '_cachetop_hash', $hash );
			update_post_meta( $id, '_cachetop_date', time() );
		}

		return $data;
	}

	/**
	 * Setup actions.
	 */
	private function setup_actions() {
		if ( is_admin() ) {
			add_action( 'save_post', [$this, 'clear_post_cache'], 0 );
		} else {
			add_action( 'template_redirect', [$this, 'handle_cache'], 0 );
		}
	}

	/**
	 * Should bypass cache?
	 *
	 * @return bool
	 */
	protected function should_bypass() {
		if ( apply_filters( 'cachetop/bypass', false ) ) {
			return true;
		}

		// Don't cache:
		// - Search page
		// - 404 page
		// - Feed page
		// - Trackback page
		// - Robots file
		// - If preview
		// - If post requires password.
		// - If user is logged in.
		if ( is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() || is_user_logged_in() ) {
			return true;
		}

		// Plugins like WooCommerce can have this constant.
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return true;
		}

		// Only cache get requests.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
			return true;
		}

		// Don't cache query string urls.
		if ( ! empty( $_GET ) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) && get_option( 'permalink_structure' ) ) {
			return true;
		}

		return false;
	}
}
