<?php

namespace Cachetop;

use Cachetop\Stores\Filesystem;
use Cachetop\Stores\Redis;

final class Cachetop {

	/**
	 * Query string action.
	 *
	 * @var null|string
	 */
	private $action;

	/**
	 * The cache store.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->action = isset( $_GET['cachetop'] ) ? $_GET['cachetop'] : null;
		$this->store  = Redis::instance();
		$this->setup_actions();
	}

	/**
	 * Add clear cache button to admin bar menu.
	 *
	 * @param object $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		$url   = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$url   = parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH );
		$color = $this->store->exists( $this->generate_hash( $url ) ) ? 'green' : 'red';
		$url   = $url . '?';

		// Add site cache menu.
		$wp_admin_bar->add_menu( [
			'id'    => 'cachetop',
			'title' => sprintf( '<span style="%s%s"></span> %s', 'height: 18px;width: 18px;border-radius: 50%;background: #ccc;display: inline-block;vertical-align: middle;margin-right: 7px;position: relative;bottom: 2px;color: #fff;text-align: center;vertical-align: middle;line-height: 19px;', 'background: ' . $color . ';', __( 'Site cache', 'cachetop' ) ),
			'href'  => $url . 'cachetop=clear'
		] );

		// Add clear cache menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-clear',
			'parent' => 'cachetop',
			'title'  => __( 'Clear cache', 'cachetop' ),
			'href'   => $url . 'cachetop=clear'
		] );

		// Add flush all caches menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-flush',
			'parent' => 'cachetop',
			'title'  => __( 'Flush all caches', 'cachetop' ),
			'href'   => $url . 'cachetop=flush'
		] );
	}

	/**
	 * Clear post cache.
	 *
	 * @param int $post_id
	 */
	public function clear_post_cache( $post_id = 0 ) {
		if ( empty( $post_id ) && get_the_ID() !== 0 ) {
			$post_id = get_the_ID();
		}

		if ( $hash = get_post_meta( $post_id, '_cachetop_hash', true ) ) {
			$this->store->delete( $hash );
		}
	}

	/**
	 * Handle cached or uncached pages.
	 */
	public function handle_cache() {
		// If a query string action exists
		// it should be handle.
		switch ( $this->action ) {
			case 'clear':
				$this->store->delete( $this->generate_hash() );
				$this->clear_post_cache();
				return;
			case 'flush':
				$this->store->flush();
				return;
			default:
				break;
		}

		// Check if the given url should be bypassed or not.
		if ( $this->should_bypass() ) {
			return;
		}

		// Generate a hash based on the url.
		$hash = $this->generate_hash();

		// Try to find the cached html for the hash.
		$cache = $this->store->read( $hash );

		// If the cache is empty, it should create a new.
		if ( empty( $cache ) ) {
			ob_start( [$this, 'set_cache'] );
			return;
		}

		// Render cached html.
		echo sprintf(
			'%s %s',
			$cache,
			sprintf( '<!-- cached by cachetop - %s - hash: %s -->', date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) ), $hash )
		);

		exit;
	}

	/**
	 * Genrate hash based on the current or given url.
	 *
	 * @param  string $url
	 * @param  bool   $qs
	 * @param  string $algo
	 *
	 * @return string
	 */
	private function generate_hash( $url = '', $qs = false, $algo = 'sha256' ) {
		if ( empty( $url ) ) {
			$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		if ( ! $qs ) {
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

		add_action( 'admin_bar_menu', [$this, 'admin_bar_menu'], 999 );
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
